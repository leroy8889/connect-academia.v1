<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use PDO;

/**
 * Code court d'établissement — le « ESM » de CA-2026-ESM-000147 (spec §5.5).
 *
 * 3 à 5 lettres majuscules, unique, stocké dans etablissements.code_court.
 * Les établissements créés avant la refonte n'en ont pas : le code est alors
 * dérivé du nom à la première utilisation, puis figé en base. Une fois posé,
 * il ne doit plus changer — les références déjà émises le contiennent.
 */
final class CodeEtablissement
{
    private const LONGUEUR_MIN = 3;
    private const LONGUEUR_MAX = 5;

    /** Mots ignorés lors de la dérivation par initiales. */
    private const MOTS_VIDES = [
        'de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'et', 'en', 'au', 'aux',
        'pour', 'sur', 'a',
    ];

    /** Cache mémoire par requête : évite de relire la table à chaque appel. */
    private static array $cache = [];

    /**
     * Code court de l'établissement, généré et persisté si absent.
     * Retourne un code de repli déterministe si l'établissement est introuvable
     * — la génération de référence ne doit jamais planter la soumission.
     */
    public static function pour(int $etablissementId): string
    {
        if (isset(self::$cache[$etablissementId])) {
            return self::$cache[$etablissementId];
        }

        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare('SELECT nom, code_court FROM etablissements WHERE id = ? LIMIT 1');
            $st->execute([$etablissementId]);
            $etab = $st->fetch(PDO::FETCH_ASSOC);

            if ($etab === false) {
                return self::$cache[$etablissementId] = self::repli($etablissementId);
            }

            $existant = self::nettoyer((string)($etab['code_court'] ?? ''));
            if ($existant !== '') {
                return self::$cache[$etablissementId] = $existant;
            }

            $code = self::attribuer($db, $etablissementId, (string)$etab['nom']);

            return self::$cache[$etablissementId] = $code;
        } catch (\Throwable $e) {
            error_log('[CodeEtablissement] Échec pour #' . $etablissementId . ' : ' . $e->getMessage());

            return self::$cache[$etablissementId] = self::repli($etablissementId);
        }
    }

    /**
     * Force un code choisi par un administrateur. Retourne false si le code est
     * invalide ou déjà pris par un autre établissement.
     */
    public static function definir(int $etablissementId, string $code): bool
    {
        $code = self::nettoyer($code);
        if ($code === '') {
            return false;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $st = $db->prepare(
                'UPDATE etablissements SET code_court = ? WHERE id = ?'
            );
            $st->execute([$code, $etablissementId]);

            unset(self::$cache[$etablissementId]);

            return true;
        } catch (\PDOException $e) {
            // 23000 = violation d'unicité : le code est déjà porté ailleurs.
            if (($e->errorInfo[0] ?? '') !== '23000') {
                error_log('[CodeEtablissement] definir() : ' . $e->getMessage());
            }

            return false;
        }
    }

    /**
     * Propose un code libre pour un nom donné, sans l'enregistrer
     * (prévisualisation dans le formulaire d'administration).
     */
    public static function suggerer(string $nom, ?int $exclureId = null): string
    {
        try {
            $db = Database::getInstance()->getConnection();

            foreach (self::candidats($nom) as $candidat) {
                if (self::estLibre($db, $candidat, $exclureId)) {
                    return $candidat;
                }
            }
        } catch (\Throwable $e) {
            error_log('[CodeEtablissement] suggerer() : ' . $e->getMessage());
        }

        return self::aleatoire();
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Attribue le premier candidat libre. L'unicité finale est garantie par
     * l'index UNIQUE : en cas de course entre deux requêtes simultanées, on
     * relit la valeur gagnante plutôt que d'échouer.
     */
    private static function attribuer(PDO $db, int $etablissementId, string $nom): string
    {
        foreach (self::candidats($nom) as $candidat) {
            if (!self::estLibre($db, $candidat, $etablissementId)) {
                continue;
            }

            try {
                $st = $db->prepare(
                    'UPDATE etablissements SET code_court = ?
                     WHERE id = ? AND (code_court IS NULL OR code_court = \'\')'
                );
                $st->execute([$candidat, $etablissementId]);

                if ($st->rowCount() > 0) {
                    return $candidat;
                }

                // Une autre requête a posé le code entre-temps : on le relit.
                $relu = self::lireCode($db, $etablissementId);
                if ($relu !== '') {
                    return $relu;
                }
            } catch (\PDOException $e) {
                if (($e->errorInfo[0] ?? '') !== '23000') {
                    throw $e;
                }
                // Collision d'unicité : on passe au candidat suivant.
            }
        }

        // Aucun candidat dérivé du nom n'est libre : on tire au sort jusqu'à
        // trouver une place. Borné pour ne jamais boucler indéfiniment.
        for ($essai = 0; $essai < 50; $essai++) {
            $candidat = self::aleatoire();
            try {
                $st = $db->prepare(
                    'UPDATE etablissements SET code_court = ?
                     WHERE id = ? AND (code_court IS NULL OR code_court = \'\')'
                );
                $st->execute([$candidat, $etablissementId]);

                if ($st->rowCount() > 0) {
                    return $candidat;
                }

                $relu = self::lireCode($db, $etablissementId);
                if ($relu !== '') {
                    return $relu;
                }
            } catch (\PDOException $e) {
                if (($e->errorInfo[0] ?? '') !== '23000') {
                    throw $e;
                }
            }
        }

        return self::repli($etablissementId);
    }

    private static function lireCode(PDO $db, int $etablissementId): string
    {
        $st = $db->prepare('SELECT code_court FROM etablissements WHERE id = ?');
        $st->execute([$etablissementId]);

        return self::nettoyer((string)($st->fetchColumn() ?: ''));
    }

    private static function estLibre(PDO $db, string $code, ?int $exclureId): bool
    {
        $sql    = 'SELECT COUNT(*) FROM etablissements WHERE code_court = ?';
        $params = [$code];

        if ($exclureId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exclureId;
        }

        $st = $db->prepare($sql);
        $st->execute($params);

        return (int)$st->fetchColumn() === 0;
    }

    /**
     * Candidats dérivés du nom, du plus lisible au moins lisible.
     *
     * « École Supérieure de Management » → ESM, ESMA, ESMAN, ECOL, ECOLE…
     *
     * @return list<string>
     */
    private static function candidats(string $nom): array
    {
        $normalise = self::translitterer($nom);
        $mots      = array_values(array_filter(
            preg_split('/[^A-Z0-9]+/', $normalise) ?: [],
            static fn(string $mot): bool =>
                $mot !== '' && !in_array(strtolower($mot), self::MOTS_VIDES, true)
        ));

        $candidats = [];

        // 1. Initiales des mots significatifs.
        $initiales = '';
        foreach ($mots as $mot) {
            $initiales .= $mot[0];
        }
        if (strlen($initiales) >= self::LONGUEUR_MIN) {
            $candidats[] = substr($initiales, 0, self::LONGUEUR_MAX);
            if (strlen($initiales) > self::LONGUEUR_MIN) {
                $candidats[] = substr($initiales, 0, self::LONGUEUR_MIN);
            }
        }

        // 2. Initiales complétées par les lettres du premier mot.
        if ($initiales !== '' && $mots !== []) {
            $base = $initiales;
            $suite = substr($mots[0], 1);
            for ($i = 0; $i < strlen($suite) && strlen($base) < self::LONGUEUR_MAX; $i++) {
                $base .= $suite[$i];
                if (strlen($base) >= self::LONGUEUR_MIN) {
                    $candidats[] = $base;
                }
            }
        }

        // 3. Troncature du premier mot.
        if ($mots !== []) {
            for ($len = self::LONGUEUR_MIN; $len <= self::LONGUEUR_MAX; $len++) {
                if (strlen($mots[0]) >= $len) {
                    $candidats[] = substr($mots[0], 0, $len);
                }
            }
        }

        $candidats = array_values(array_unique(array_filter(
            array_map([self::class, 'nettoyer'], $candidats),
            static fn(string $c): bool => $c !== ''
        )));

        return $candidats;
    }

    /**
     * Retire les accents et met en majuscules ASCII.
     * Passe par Core\Texte : le code court entre dans des références déjà
     * émises, il doit être identique quel que soit le système qui le calcule.
     */
    private static function translitterer(string $texte): string
    {
        $ascii = \Core\Texte::sansAccents($texte);

        return strtoupper(preg_replace('/[^A-Za-z0-9 ]/', ' ', $ascii) ?? '');
    }

    /** Normalise un code : majuscules A-Z/0-9, longueur bornée, sinon ''. */
    private static function nettoyer(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        if (strlen($code) < self::LONGUEUR_MIN) {
            return '';
        }

        return substr($code, 0, self::LONGUEUR_MAX);
    }

    /** Code aléatoire de 5 lettres, alphabet sans caractères ambigus. */
    private static function aleatoire(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code     = '';
        for ($i = 0; $i < self::LONGUEUR_MAX; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * Repli déterministe si la base est inaccessible : jamais de référence
     * vide, et deux établissements différents ne partagent pas le même repli.
     */
    private static function repli(int $etablissementId): string
    {
        return 'E' . str_pad((string)($etablissementId % 10000), 4, '0', STR_PAD_LEFT);
    }
}
