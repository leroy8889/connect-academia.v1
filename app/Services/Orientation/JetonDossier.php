<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use PDO;

/**
 * Jeton de vérification du dossier (spec §7).
 *
 * Principe fondamental : le QR code ne contient AUCUNE donnée du dossier,
 * seulement une URL portant un jeton opaque. C'est la base qui répond, pas le
 * papier — un récépissé imprimé à 0 % reste exact à 50 % et à 100 %.
 *
 * Deux identifiants pour le même jeton :
 *   - `token`      : 48 caractères hexadécimaux (192 bits d'entropie), dans le QR.
 *   - `code_court` : 8 caractères d'un alphabet sans ambiguïté, saisissable à la
 *                    main quand le scan est impossible (spec §7.2).
 *
 * Aucun des deux n'est dérivable de l'identifiant du dossier.
 */
final class JetonDossier
{
    /** Alphabet du code court : ni I, ni O, ni 0, ni 1 (spec §7.2). */
    private const ALPHABET_COURT  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LONGUEUR_COURTE = 8;
    private const LONGUEUR_TOKEN  = 48;

    /** Tentatives d'insertion avant abandon en cas de collision d'unicité. */
    private const MAX_ESSAIS = 10;

    /**
     * Jeton actif du dossier, créé si absent.
     *
     * Idempotente : appelée deux fois pour le même dossier, elle renvoie le
     * même jeton. C'est ce qui permet à la tâche de rattrapage des PDF de
     * régénérer un récépissé sans invalider celui déjà imprimé.
     *
     * @return array{id:int,token:string,code_court:string}|null
     */
    public static function pour(int $dossierId, ?PDO $db = null): ?array
    {
        $db ??= Database::getInstance()->getConnection();

        $existant = self::actif($dossierId, $db);
        if ($existant !== null) {
            return $existant;
        }

        return self::creer($dossierId, $db);
    }

    /**
     * Jeton actif du dossier, sans en créer.
     *
     * @return array{id:int,token:string,code_court:string}|null
     */
    public static function actif(int $dossierId, ?PDO $db = null): ?array
    {
        try {
            $db ??= Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT id, token, code_court FROM jetons_verification
                 WHERE dossier_id = ? AND actif = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$dossierId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : self::hydrater($ligne);
        } catch (\Throwable $e) {
            error_log('[JetonDossier] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Résout un jeton présenté par un agent (QR ou code court).
     *
     * Ne fait AUCUN contrôle de droit : c'est l'appelant (contrôleur de
     * vérification) qui décide ce que l'agent a le droit de voir, et qui
     * journalise le scan dans tous les cas, succès comme échec.
     *
     * @return array{
     *   jeton_id:int, dossier_id:int, actif:bool, revoque_le:?string
     * }|null
     */
    public static function resoudre(string $valeur): ?array
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        try {
            $db = Database::getInstance()->getConnection();

            // Un code court normalisé fait 8 caractères ; tout le reste est
            // traité comme un token long. Les deux colonnes sont UNIQUE, donc
            // aucune ambiguïté possible.
            $court = self::normaliserCodeCourt($valeur);
            if ($court !== null) {
                $st = $db->prepare(
                    'SELECT id, dossier_id, actif, revoque_le FROM jetons_verification
                     WHERE code_court = ? LIMIT 1'
                );
                $st->execute([$court]);
            } else {
                $token = strtolower($valeur);
                if (preg_match('/^[0-9a-f]{' . self::LONGUEUR_TOKEN . '}$/', $token) !== 1) {
                    return null;
                }
                $st = $db->prepare(
                    'SELECT id, dossier_id, actif, revoque_le FROM jetons_verification
                     WHERE token = ? LIMIT 1'
                );
                $st->execute([$token]);
            }

            $ligne = $st->fetch(PDO::FETCH_ASSOC);
            if ($ligne === false) {
                return null;
            }

            return [
                'jeton_id'   => (int)$ligne['id'],
                'dossier_id' => (int)$ligne['dossier_id'],
                'actif'      => (bool)$ligne['actif'],
                'revoque_le' => $ligne['revoque_le'] ?? null,
            ];
        } catch (\Throwable $e) {
            error_log('[JetonDossier] Résolution échouée : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Révoque le jeton courant et en émet un nouveau (spec §7.5).
     * Le récépissé précédent devient invalide : l'appelant doit régénérer le
     * PDF et prévenir l'étudiant.
     *
     * @return array{id:int,token:string,code_court:string}|null Le nouveau jeton.
     */
    public static function revoquerEtRegenerer(int $dossierId, ?int $adminId = null): ?array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $db->prepare(
                'UPDATE jetons_verification
                 SET actif = 0, revoque_le = NOW(), revoque_par = ?
                 WHERE dossier_id = ? AND actif = 1'
            )->execute([$adminId, $dossierId]);

            return self::creer($dossierId, $db);
        } catch (\Throwable $e) {
            error_log('[JetonDossier] Révocation échouée #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Code court formaté pour l'impression : « ABCD-EFGH ».
     * Groupes de 4 séparés par un tiret, pour la lecture à voix haute (§7.2).
     */
    public static function formaterCodeCourt(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');

        return strlen($code) === self::LONGUEUR_COURTE
            ? substr($code, 0, 4) . '-' . substr($code, 4)
            : $code;
    }

    /**
     * Ramène une saisie humaine à sa forme canonique, ou null si ce n'est pas
     * un code court plausible. Tolère espaces, tirets et minuscules.
     */
    public static function normaliserCodeCourt(string $saisie): ?string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $saisie) ?? '');

        if (strlen($code) !== self::LONGUEUR_COURTE) {
            return null;
        }

        // Confusions classiques à la saisie manuelle : l'alphabet exclut
        // I/O/0/1, on ramène donc les caractères saisis vers leur voisin réel.
        $code = strtr($code, ['I' => 'J', 'O' => 'Q', '0' => 'Q', '1' => 'J']);

        if (strspn($code, self::ALPHABET_COURT) !== self::LONGUEUR_COURTE) {
            return null;
        }

        return $code;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * @return array{id:int,token:string,code_court:string}|null
     */
    private static function creer(int $dossierId, PDO $db): ?array
    {
        for ($essai = 0; $essai < self::MAX_ESSAIS; $essai++) {
            $token = bin2hex(random_bytes(self::LONGUEUR_TOKEN / 2));
            $court = self::genererCodeCourt();

            try {
                $st = $db->prepare(
                    'INSERT INTO jetons_verification (dossier_id, token, code_court, actif, cree_le)
                     VALUES (?, ?, ?, 1, NOW())'
                );
                $st->execute([$dossierId, $token, $court]);

                return [
                    'id'         => (int)$db->lastInsertId(),
                    'token'      => $token,
                    'code_court' => $court,
                ];
            } catch (\PDOException $e) {
                // 23000 : collision sur token ou code_court — on retire.
                if (($e->errorInfo[0] ?? '') !== '23000') {
                    error_log('[JetonDossier] Création échouée #' . $dossierId . ' : ' . $e->getMessage());

                    return null;
                }
            }
        }

        error_log('[JetonDossier] Création abandonnée après ' . self::MAX_ESSAIS . ' collisions (#' . $dossierId . ')');

        return null;
    }

    private static function genererCodeCourt(): string
    {
        $taille = strlen(self::ALPHABET_COURT);
        $code   = '';

        for ($i = 0; $i < self::LONGUEUR_COURTE; $i++) {
            $code .= self::ALPHABET_COURT[random_int(0, $taille - 1)];
        }

        return $code;
    }

    /**
     * @param array<string,mixed> $ligne
     * @return array{id:int,token:string,code_court:string}
     */
    private static function hydrater(array $ligne): array
    {
        return [
            'id'         => (int)$ligne['id'],
            'token'      => (string)$ligne['token'],
            'code_court' => (string)$ligne['code_court'],
        ];
    }
}
