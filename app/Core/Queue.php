<?php
declare(strict_types=1);

namespace Core;

use Models\Orientation\Dossier;
use Models\Orientation\Etablissement;
use Services\Orientation\RecuService;

/**
 * File de jobs Redis minimale — décharge les tâches lentes (envoi e-mail SMTP,
 * génération PDF + upload B2) hors du cycle requête → les workers PHP-FPM ne
 * restent plus bloqués à attendre un service externe.
 *
 * Conception :
 *  - Payloads JSON via rawCommand → bypass du sérialiseur/compression phpredis,
 *    donc inspectables en `redis-cli` et indépendants des extensions (igbinary)
 *    installées côté web vs côté CLI (aucun risque de mismatch).
 *  - `handle()` = source de vérité UNIQUE de l'exécution, partagée par le worker
 *    et par le repli synchrone.
 *  - Dégradation gracieuse : si Redis est indisponible (ou QUEUE_SYNC=true), le
 *    job s'exécute immédiatement en ligne → aucun travail perdu, comportement
 *    identique à l'ancien code synchrone.
 *  - Reprises avec backoff (10s, 60s) via un sorted set « delayed » ; au-delà de
 *    MAX_ATTEMPTS, le job part dans la liste « failed » (dead-letter) pour audit.
 *
 * Clés Redis (préfixées `ca:`) :
 *   queue:jobs    (list)        — jobs prêts, FIFO (LPUSH ← / RPOP →)
 *   queue:delayed (sorted set)  — reprises programmées, score = timestamp d'échéance
 *   queue:failed  (list)        — jobs morts après épuisement des tentatives
 */
final class Queue
{
    private const K_JOBS    = 'queue:jobs';
    private const K_DELAYED = 'queue:delayed';
    private const K_FAILED  = 'queue:failed';

    private const MAX_ATTEMPTS = 3;
    /** Backoff (secondes) appliqué après la 1re puis la 2e tentative échouée. */
    private const BACKOFF = [10, 60];
    /** Fenêtre anti-doublon e-mail (secondes) : un mail identique ne repart pas. */
    private const MAIL_DEDUP_TTL = 600;

    // ── Côté producteur (requête web) ───────────────────────────────────────

    /**
     * Empile un job. Retourne true si mis en file OU exécuté en ligne avec succès.
     *
     * @param string $type    Type de job (voir handle()).
     * @param array  $payload Données du job (sérialisables JSON — scalaires de préférence).
     * @param int    $delay   Délai en secondes avant éligibilité (0 = immédiat).
     */
    public static function push(string $type, array $payload, int $delay = 0): bool
    {
        $now = time();
        $job = [
            'id'           => bin2hex(random_bytes(8)),
            'type'         => $type,
            'payload'      => $payload,
            'attempts'     => 0,
            'created_at'   => $now,
            'available_at' => $now + max(0, $delay),
        ];

        $redis = Redis::getInstance();

        // Repli synchrone : Redis absent, ou mode SYNC forcé (dev local sans worker).
        if (!$redis->isAvailable() || ($_ENV['QUEUE_SYNC'] ?? 'false') === 'true') {
            return self::runInline($job);
        }

        $data = self::encode($job);
        if ($data === false) {
            error_log("[Queue] Payload non sérialisable ({$type}) → exécution inline");
            return self::runInline($job);
        }

        try {
            $client = $redis->client();
            $prefix = $redis->prefix();
            if ($delay > 0) {
                $client->rawCommand('ZADD', $prefix . self::K_DELAYED, (string)$job['available_at'], $data);
            } else {
                $client->rawCommand('LPUSH', $prefix . self::K_JOBS, $data);
            }
            return true;
        } catch (\Throwable $e) {
            error_log("[Queue] push({$type}) échoué, exécution inline : " . $e->getMessage());
            return self::runInline($job);
        }
    }

    private static function runInline(array $job): bool
    {
        try {
            self::handle((string)$job['type'], (array)$job['payload']);
            return true;
        } catch (\Throwable $e) {
            error_log("[Queue] Job inline {$job['type']} échoué : " . $e->getMessage());
            return false;
        }
    }

    // ── Dispatch (source unique — worker ET repli inline) ───────────────────

    /**
     * Exécute un job. DOIT lever une exception en cas d'échec récupérable pour
     * déclencher une reprise côté worker.
     */
    public static function handle(string $type, array $payload): void
    {
        switch ($type) {
            case 'send_email':
                $to      = (string)($payload['to']      ?? '');
                $subject = (string)($payload['subject'] ?? '');
                $html    = (string)($payload['html']    ?? '');

                // Dédup : un mail IDENTIQUE (même destinataire + sujet + corps) ne
                // part pas deux fois dans la fenêtre TTL. La clé inclut le corps →
                // deux notifications distinctes (candidats différents) passent quand
                // même. Marqué APRÈS succès → un retry légitime (envoi raté) n'est
                // jamais bloqué.
                $redis   = Redis::getInstance();
                $lockKey = 'mailsent:' . sha1($to . "\x1f" . $subject . "\x1f" . $html);
                if ($redis->isAvailable() && $redis->exists($lockKey)) {
                    error_log("[Queue] send_email ignoré (doublon récent) → {$to} — {$subject}");
                    return;
                }

                $ok = Mailer::send(
                    $to,
                    (string)($payload['toName'] ?? ''),
                    $subject,
                    $html,
                    (string)($payload['text'] ?? '')
                );
                if (!$ok) {
                    throw new \RuntimeException('Mailer::send a renvoyé false');
                }

                if ($redis->isAvailable()) {
                    $redis->set($lockKey, '1', self::MAIL_DEDUP_TTL);
                }
                return;

            case 'generate_recu':
                $dossierId  = (int)($payload['dossierId']  ?? 0);
                $paiementId = (int)($payload['paiementId'] ?? 0);
                $etabId     = (int)($payload['etabId']     ?? 0);
                $montant    = (int)($payload['montant']    ?? 0);

                if ($dossierId <= 0 || $paiementId <= 0 || $etabId <= 0) {
                    throw new \RuntimeException('generate_recu : identifiants invalides');
                }

                // Re-fetch frais : payload minuscule, données jamais périmées.
                $dossier = (new Dossier())->findById($dossierId);
                $etab    = (new Etablissement())->findById($etabId);
                if (!$dossier || !$etab) {
                    throw new \RuntimeException('generate_recu : dossier/établissement introuvable');
                }

                // RecuService est idempotent (reçu déjà généré → no-op).
                $cle = RecuService::generer($dossierId, $paiementId, $dossier, $etab, $montant);
                if ($cle === null) {
                    throw new \RuntimeException('RecuService::generer a échoué');
                }
                return;

            default:
                throw new \RuntimeException("Type de job inconnu : {$type}");
        }
    }

    // ── Côté consommateur (worker CLI) ──────────────────────────────────────

    /**
     * Réserve le prochain job prêt (null si file vide). Migre d'abord les jobs
     * différés arrivés à échéance vers la file principale.
     */
    public static function reserve(): ?array
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return null;
        }
        $client = $redis->client();
        $prefix = $redis->prefix();

        self::migrateDelayed($client, $prefix);

        $raw = $client->rawCommand('RPOP', $prefix . self::K_JOBS);
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }

        $job = self::decode((string)$raw);
        if ($job === null) {
            error_log('[Queue] Job illisible ignoré : ' . substr((string)$raw, 0, 200));
            return null;
        }
        return $job;
    }

    /**
     * Reprogramme un job échoué (backoff) ou l'envoie en dead-letter après
     * épuisement des tentatives.
     */
    public static function release(array $job, string $error): void
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return;
        }
        $client = $redis->client();
        $prefix = $redis->prefix();

        $job['attempts']   = (int)($job['attempts'] ?? 0) + 1;
        $job['last_error'] = $error;
        $data = self::encode($job);
        if ($data === false) {
            error_log("[Queue] Job irrécupérable (encode) : {$job['type']}");
            return;
        }

        if ($job['attempts'] >= self::MAX_ATTEMPTS) {
            $job['failed_at'] = time();
            $client->rawCommand('RPUSH', $prefix . self::K_FAILED, self::encode($job) ?: $data);
            error_log("[Queue] ÉCHEC DÉFINITIF {$job['type']} #{$job['id']} après {$job['attempts']} tentatives : {$error}");
            return;
        }

        $backoff = self::BACKOFF[$job['attempts'] - 1] ?? 60;
        $job['available_at'] = time() + $backoff;
        $data = self::encode($job) ?: $data;
        $client->rawCommand('ZADD', $prefix . self::K_DELAYED, (string)$job['available_at'], $data);
        error_log("[Queue] Reprise {$job['type']} #{$job['id']} dans {$backoff}s (tentative {$job['attempts']}/" . self::MAX_ATTEMPTS . ") : {$error}");
    }

    /**
     * Déplace les jobs différés échus vers la file principale. Le ZREM atomique
     * garantit qu'avec N workers, un seul job n'est requeueé qu'une fois.
     */
    private static function migrateDelayed(\Redis $client, string $prefix): void
    {
        $now = time();
        $due = $client->rawCommand(
            'ZRANGEBYSCORE', $prefix . self::K_DELAYED, '-inf', (string)$now, 'LIMIT', '0', '100'
        );
        if (!is_array($due) || $due === []) {
            return;
        }
        foreach ($due as $data) {
            if ((int)$client->rawCommand('ZREM', $prefix . self::K_DELAYED, $data) === 1) {
                $client->rawCommand('LPUSH', $prefix . self::K_JOBS, $data);
            }
        }
    }

    // ── Observabilité ───────────────────────────────────────────────────────

    /** Compteurs de file pour supervision (dashboard admin, healthcheck). */
    public static function stats(): array
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return ['available' => false, 'pending' => 0, 'delayed' => 0, 'failed' => 0];
        }
        $c = $redis->client();
        $p = $redis->prefix();
        return [
            'available' => true,
            'pending'   => (int)$c->rawCommand('LLEN',  $p . self::K_JOBS),
            'delayed'   => (int)$c->rawCommand('ZCARD', $p . self::K_DELAYED),
            'failed'    => (int)$c->rawCommand('LLEN',  $p . self::K_FAILED),
        ];
    }

    // ── (dé)sérialisation JSON ──────────────────────────────────────────────

    private static function encode(array $job): string|false
    {
        return json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function decode(string $raw): ?array
    {
        $job = json_decode($raw, true);
        return is_array($job) ? $job : null;
    }
}
