<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Journal des scans de QR code (spec §7.3, §12.4).
 *
 * TOUT scan est enregistré, y compris — et surtout — les échecs. Un jeton
 * inexistant présenté vingt fois depuis la même adresse n'est pas une faute de
 * frappe : c'est une tentative d'énumération, et seule la trace des échecs
 * permet de la voir.
 *
 * L'écriture ne peut jamais faire échouer la vérification : une trace perdue
 * est un incident, une page de vérification en erreur en est un plus grave
 * — l'agent a un candidat devant lui.
 */
final class ScanQr
{
    public const VALIDE                  = 'valide';
    public const NON_VALIDE_50           = 'non_valide_50';
    public const ETABLISSEMENT_INCORRECT = 'etablissement_incorrect';
    public const JETON_REVOQUE           = 'jeton_revoque';
    public const INTROUVABLE             = 'introuvable';
    public const DEJA_INSCRIT            = 'deja_inscrit';
    public const NON_AUTHENTIFIE         = 'non_authentifie';

    /** Longueur maximale conservée pour un jeton présenté (colonne VARCHAR(64)). */
    private const MAX_TOKEN_TENTE = 64;

    /**
     * @param array{
     *   jeton_id?:int|null, dossier_id?:int|null, token_tente?:string|null,
     *   scanne_par?:int|null, acteur_role?:string|null,
     *   etablissement_id?:int|null, resultat:string
     * } $scan
     */
    public static function enregistrer(array $scan): void
    {
        try {
            Database::getInstance()->getConnection()->prepare(
                'INSERT INTO scans_qr
                   (jeton_id, dossier_id, token_tente, scanne_par, acteur_role,
                    etablissement_id, resultat, adresse_ip, user_agent, scanne_le)
                 VALUES (:jeton, :dossier, :token, :par, :role, :etab, :resultat, :ip, :ua, NOW())'
            )->execute([
                ':jeton'    => self::entierOuNull($scan['jeton_id']         ?? null),
                ':dossier'  => self::entierOuNull($scan['dossier_id']       ?? null),
                // Le jeton présenté est tronqué et nettoyé : il vient du client,
                // et il sera relu dans un écran d'administration.
                ':token'    => self::tokenTente($scan['token_tente'] ?? null),
                ':par'      => self::entierOuNull($scan['scanne_par']       ?? null),
                ':role'     => $scan['acteur_role'] ?? null,
                ':etab'     => self::entierOuNull($scan['etablissement_id'] ?? null),
                ':resultat' => self::resultatValide((string)$scan['resultat']),
                ':ip'       => self::ip(),
                ':ua'       => self::userAgent(),
            ]);
        } catch (\Throwable $e) {
            error_log('[ScanQr] Écriture échouée : ' . $e->getMessage());
        }
    }

    /** Résultat à consigner pour un dossier trouvé et accessible à l'agent. */
    public static function resultatPourStatut(string $statut): string
    {
        return match (DossierStatut::normaliser($statut)) {
            DossierStatut::VALIDE_50  => self::VALIDE,
            DossierStatut::VALIDE_100 => self::DEJA_INSCRIT,
            default                    => self::NON_VALIDE_50,
        };
    }

    /**
     * Derniers scans d'un dossier (fiche dossier, back-office).
     *
     * @return list<array<string,mixed>>
     */
    public static function pourDossier(int $dossierId, int $limite = 50): array
    {
        $limite = max(1, min($limite, 200));

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT s.*, u.nom AS agent_nom, u.prenom AS agent_prenom
                 FROM scans_qr s
                 LEFT JOIN users u ON u.id = s.scanne_par
                 WHERE s.dossier_id = ?
                 ORDER BY s.scanne_le DESC
                 LIMIT {$limite}"
            );
            $st->execute([$dossierId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[ScanQr] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Adresses ayant accumulé des échecs sur une période — alimente l'écran de
     * sécurité du super administrateur.
     *
     * @return list<array{adresse_ip:string,echecs:int,dernier:string}>
     */
    public static function ipSuspectes(int $heures = 24, int $seuil = 20): array
    {
        $heures = max(1, min($heures, 24 * 30));
        $seuil  = max(1, $seuil);

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT adresse_ip, COUNT(*) AS echecs, MAX(scanne_le) AS dernier
                 FROM scans_qr
                 WHERE resultat IN (:introuvable, :etab, :revoque)
                   AND scanne_le >= DATE_SUB(NOW(), INTERVAL :heures HOUR)
                   AND adresse_ip IS NOT NULL
                 GROUP BY adresse_ip
                 HAVING echecs >= :seuil
                 ORDER BY echecs DESC'
            );
            $st->execute([
                ':introuvable' => self::INTROUVABLE,
                ':etab'        => self::ETABLISSEMENT_INCORRECT,
                ':revoque'     => self::JETON_REVOQUE,
                ':heures'      => $heures,
                ':seuil'       => $seuil,
            ]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[ScanQr] Analyse des IP échouée : ' . $e->getMessage());

            return [];
        }
    }

    /** Libellé lisible d'un résultat, pour les écrans d'administration. */
    public static function libelle(string $resultat): string
    {
        return match ($resultat) {
            self::VALIDE                  => 'Validé à 50 % — présentation acceptée',
            self::NON_VALIDE_50           => 'Dossier pas encore validé à 50 %',
            self::ETABLISSEMENT_INCORRECT => 'Établissement incorrect',
            self::JETON_REVOQUE           => 'Jeton révoqué',
            self::INTROUVABLE             => 'Jeton inconnu',
            self::DEJA_INSCRIT            => 'Déjà inscrit',
            self::NON_AUTHENTIFIE         => 'Scan sans authentification',
            default                        => $resultat,
        };
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private static function resultatValide(string $resultat): string
    {
        $connus = [
            self::VALIDE, self::NON_VALIDE_50, self::ETABLISSEMENT_INCORRECT,
            self::JETON_REVOQUE, self::INTROUVABLE, self::DEJA_INSCRIT,
            self::NON_AUTHENTIFIE,
        ];

        return in_array($resultat, $connus, true) ? $resultat : self::INTROUVABLE;
    }

    private static function tokenTente(?string $valeur): ?string
    {
        if ($valeur === null || trim($valeur) === '') {
            return null;
        }

        $propre = preg_replace('/[^A-Za-z0-9\-]/', '', $valeur) ?? '';

        return $propre === '' ? null : substr($propre, 0, self::MAX_TOKEN_TENTE);
    }

    private static function entierOuNull(mixed $valeur): ?int
    {
        return $valeur === null || $valeur === '' || (int)$valeur === 0 ? null : (int)$valeur;
    }

    /**
     * REMOTE_ADDR uniquement. X-Forwarded-For est falsifiable par le client :
     * l'accepter permettrait à un attaquant de répartir ses tentatives sur des
     * adresses inventées et de rendre la détection d'énumération inopérante.
     */
    private static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private static function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (!is_string($ua) || $ua === '') {
            return null;
        }

        // Chaîne fournie par le client, relue en back-office : on retire les
        // caractères de contrôle et on borne à la taille de la colonne.
        $ua = preg_replace('/[\x00-\x1F\x7F]/', '', $ua) ?? '';

        return mb_substr($ua, 0, 255);
    }
}
