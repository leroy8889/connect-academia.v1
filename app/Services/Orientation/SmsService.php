<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\Phone;

/**
 * Envoi de SMS aux candidats (guichet gestionnaire).
 *
 * Pilotes disponibles via SMS_DRIVER (.env) :
 *   - "twilio" : API Twilio (SMS_TWILIO_SID, SMS_TWILIO_TOKEN, SMS_TWILIO_FROM)
 *   - "log"    : aucun envoi réel — message tracé dans error_log et journalisé
 *                en base avec le statut "simule" (mode développement, défaut).
 *
 * Chaque tentative est journalisée dans la table sms_orientation
 * (migration : Database/migration_sms_orientation.sql).
 */
class SmsService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = strtolower(trim($_ENV['SMS_DRIVER'] ?? 'log'));
    }

    /** Le service est-il prêt à envoyer de vrais SMS ? */
    public function estConfigure(): bool
    {
        if ($this->driver === 'twilio') {
            return ($_ENV['SMS_TWILIO_SID'] ?? '') !== ''
                && ($_ENV['SMS_TWILIO_TOKEN'] ?? '') !== ''
                && ($_ENV['SMS_TWILIO_FROM'] ?? '') !== '';
        }
        return $this->driver === 'log';
    }

    /** Mode simulation (aucun SMS réel ne part) ? */
    public function estSimulation(): bool
    {
        return $this->driver === 'log';
    }

    /**
     * Normalise un numéro au format E.164 à partir de l'indicatif + numéro local.
     * Retourne null si le numéro est inexploitable.
     */
    public static function normaliserNumero(string $indicatif, string $telephone): ?string
    {
        $indicatif = preg_replace('/[^\d+]/', '', trim($indicatif)) ?: '+241';
        if ($indicatif[0] !== '+') {
            $indicatif = '+' . $indicatif;
        }

        // Gabon (+241) : plan de numérotation 2020 — le 0 national fait partie
        // du format international (+241 0XX XX XX XX). Ne PAS le supprimer,
        // sinon wa.me / Twilio reçoivent un numéro écorché (074… → 74…).
        if ($indicatif === '+241') {
            $national = Phone::normalizeGabon($telephone);
            return $national === null ? null : '+241' . $national;
        }

        $tel = preg_replace('/\D/', '', trim($telephone)) ?? '';
        if ($tel === '') {
            return null;
        }
        // Numéro déjà saisi avec l'indicatif (ex. 24106123456)
        $sansPlus = ltrim($indicatif, '+');
        if (str_starts_with($tel, $sansPlus)) {
            $tel = substr($tel, strlen($sansPlus));
        }
        // Zéro national de tête (ex. 06 12 34 56 → 6123456…)
        $tel = ltrim($tel, '0');

        if (strlen($tel) < 6 || strlen($tel) > 12) {
            return null;
        }
        return $indicatif . $tel;
    }

    /**
     * Envoie un SMS et journalise la tentative.
     *
     * @return array{success: bool, statut: string, erreur: ?string}
     */
    public function envoyer(
        string $numeroE164,
        string $message,
        int $dossierId,
        int $etablissementId,
        int $envoyePar
    ): array {
        $message = trim($message);

        if ($message === '') {
            return ['success' => false, 'statut' => 'echec', 'erreur' => 'Message vide.'];
        }
        if (mb_strlen($message) > 480) {
            return ['success' => false, 'statut' => 'echec', 'erreur' => 'Message trop long (480 caractères max).'];
        }
        if (!preg_match('/^\+\d{8,15}$/', $numeroE164)) {
            return ['success' => false, 'statut' => 'echec', 'erreur' => 'Numéro de téléphone invalide.'];
        }

        [$success, $statut, $reference, $erreur] = match ($this->driver) {
            'twilio' => $this->envoyerTwilio($numeroE164, $message),
            'log'    => $this->envoyerLog($numeroE164, $message),
            default  => [false, 'echec', null, 'Pilote SMS inconnu : ' . $this->driver],
        };

        $this->journaliser($dossierId, $etablissementId, $envoyePar, $numeroE164, $message, $statut, $reference, $erreur);

        return ['success' => $success, 'statut' => $statut, 'erreur' => $erreur];
    }

    /** Historique des SMS d'un dossier (plus récents en premier). */
    public function historique(int $dossierId, int $limit = 10): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare('
                SELECT s.*, TRIM(CONCAT(COALESCE(u.prenom, ""), " ", COALESCE(u.nom, ""))) AS envoye_par_nom
                FROM sms_orientation s
                LEFT JOIN users u ON u.id = s.envoye_par
                WHERE s.dossier_id = ?
                ORDER BY s.id DESC
                LIMIT ' . max(1, $limit)
            );
            $st->execute([$dossierId]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Table absente (migration non appliquée) : l'UI reste fonctionnelle
            error_log('[SMS] Lecture historique impossible : ' . $e->getMessage());
            return [];
        }
    }

    // ── Pilotes ──────────────────────────────────────────────────────────────

    /** @return array{0: bool, 1: string, 2: ?string, 3: ?string} */
    private function envoyerTwilio(string $to, string $message): array
    {
        $sid   = $_ENV['SMS_TWILIO_SID']   ?? '';
        $token = $_ENV['SMS_TWILIO_TOKEN'] ?? '';
        $from  = $_ENV['SMS_TWILIO_FROM']  ?? '';

        if ($sid === '' || $token === '' || $from === '') {
            return [false, 'echec', null, 'Passerelle SMS non configurée (SMS_TWILIO_* manquants).'];
        }

        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $sid . ':' . $token,
            CURLOPT_POSTFIELDS     => http_build_query(['To' => $to, 'From' => $from, 'Body' => $message]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return [false, 'echec', null, 'Connexion à la passerelle SMS impossible : ' . $err];
        }

        $json = json_decode((string)$body, true) ?: [];
        if ($code >= 200 && $code < 300) {
            return [true, 'envoye', $json['sid'] ?? null, null];
        }
        $detail = $json['message'] ?? ('HTTP ' . $code);
        return [false, 'echec', null, 'Envoi refusé par la passerelle : ' . $detail];
    }

    /** @return array{0: bool, 1: string, 2: ?string, 3: ?string} */
    private function envoyerLog(string $to, string $message): array
    {
        error_log(sprintf('[SMS simulé] → %s : %s', $to, $message));
        return [true, 'simule', null, null];
    }

    // ── Journal ──────────────────────────────────────────────────────────────

    private function journaliser(
        int $dossierId,
        int $etablissementId,
        int $envoyePar,
        string $destinataire,
        string $message,
        string $statut,
        ?string $reference,
        ?string $erreur
    ): void {
        try {
            Database::getInstance()->getConnection()->prepare('
                INSERT INTO sms_orientation
                  (dossier_id, etablissement_id, envoye_par, destinataire, message, statut, reference_externe, erreur)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $dossierId, $etablissementId, $envoyePar,
                $destinataire, $message, $statut, $reference,
                $erreur !== null ? mb_substr($erreur, 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[SMS] Journalisation impossible : ' . $e->getMessage());
        }
    }
}
