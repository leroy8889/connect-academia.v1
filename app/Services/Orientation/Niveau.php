<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Niveaux d'entrée — liste fermée L1 → M2.
 *
 * ── Pourquoi une liste fermée ────────────────────────────────────────────────
 *
 * `dossiers_inscription.niveau_entree` était un champ de saisie libre. Tant que
 * le niveau n'était qu'une mention imprimée sur un récépissé, c'était sans
 * conséquence. À partir du moment où il DÉTERMINE UN PRIX — un établissement
 * peut tarifer L1, L2 et L3 séparément — le texte libre devient un défaut :
 * « licence deux », « 2ème année » ou « L II » ne correspondent à aucune ligne
 * de la grille, et le guichet se retrouve sans tarif applicable.
 *
 * Le formulaire propose donc une liste. Mais le code doit continuer de lire les
 * dossiers déposés AVANT ce changement : `code()` normalise le texte libre
 * historique et retrouve le niveau dans la grande majorité des cas. Quand elle
 * n'y arrive pas, elle retourne null et la résolution de tarif retombe sur le
 * tarif du cycle — jamais sur une erreur.
 *
 * ── Ce qui est stocké ────────────────────────────────────────────────────────
 *
 * Le LIBELLÉ (« Licence 2 »), pas le code (« L2 »). Trois raisons :
 *   · une dizaine d'écrans, de PDF et d'e-mails affichent `niveau_entree`
 *     tel quel — stocker « L2 » les aurait tous dégradés ;
 *   · les dossiers existants portent déjà des libellés, la colonne reste donc
 *     homogène ;
 *   · `code()` fait le chemin inverse à la lecture, pour un coût nul.
 */
final class Niveau
{
    public const L1 = 'L1';
    public const L2 = 'L2';
    public const L3 = 'L3';
    public const M1 = 'M1';
    public const M2 = 'M2';

    /** Code → libellé affiché et stocké. L'ordre est celui du cursus. */
    private const LIBELLES = [
        self::L1 => 'Licence 1',
        self::L2 => 'Licence 2',
        self::L3 => 'Licence 3',
        self::M1 => 'Master 1',
        self::M2 => 'Master 2',
    ];

    /** Code → cycle tarifaire. Seule table de vérité du rattachement. */
    private const CYCLES = [
        self::L1 => TarifCycle::LICENCE,
        self::L2 => TarifCycle::LICENCE,
        self::L3 => TarifCycle::LICENCE,
        self::M1 => TarifCycle::MASTER,
        self::M2 => TarifCycle::MASTER,
    ];

    /**
     * @return list<string> Codes, dans l'ordre du cursus.
     */
    public static function codes(): array
    {
        return array_keys(self::LIBELLES);
    }

    /**
     * Options du <select>, prêtes pour la vue : [code => libellé].
     *
     * @return array<string,string>
     */
    public static function options(): array
    {
        return self::LIBELLES;
    }

    /**
     * Options groupées par cycle, pour un <optgroup>.
     *
     * @return array<string,array<string,string>> ['Licence' => ['L1' => 'Licence 1', …], …]
     */
    public static function optionsParCycle(): array
    {
        $groupes = [];
        foreach (self::LIBELLES as $code => $libelle) {
            $groupes[TarifCycle::libelle(self::CYCLES[$code])][$code] = $libelle;
        }

        return $groupes;
    }

    public static function estValide(?string $code): bool
    {
        return $code !== null && isset(self::LIBELLES[$code]);
    }

    /** Libellé d'un code, ou chaîne vide si le code est inconnu. */
    public static function libelle(?string $code): string
    {
        return self::LIBELLES[(string)$code] ?? '';
    }

    /** Cycle tarifaire d'un code, ou null si le code est inconnu. */
    public static function cycle(?string $code): ?string
    {
        return self::CYCLES[(string)$code] ?? null;
    }

    /**
     * Libellé à STOCKER pour une valeur postée.
     *
     * Le formulaire poste un code (« L2 ») ; on enregistre le libellé
     * (« Licence 2 »). Une valeur inconnue retourne null : l'appelant décide
     * alors s'il refuse la saisie ou s'il conserve le texte tel quel (cas des
     * écrans de reprise, où un ancien dossier peut porter autre chose).
     */
    public static function libellePourCode(string $poste): ?string
    {
        $code = strtoupper(trim($poste));

        return self::LIBELLES[$code] ?? null;
    }

    /**
     * Code d'un niveau écrit en clair — le chemin de retour.
     *
     * Reconnaît « Licence 2 », « licence2 », « L2 », « L 2 », « 2ème année de
     * licence », « Master 1 », « M1 », « Bac+2 »… Retourne null quand rien ne
     * ressort : c'est un signal exploitable (« tarifer au cycle »), pas un
     * échec.
     *
     * Ordre d'analyse volontaire : on cherche d'ABORD le master. « Master 1 »
     * contient le chiffre 1 comme « Licence 1 », et une recherche de licence
     * menée en premier sur un texte contenant « bac+1 » se tromperait de cycle
     * — donc de tarif.
     */
    public static function code(?string $texte): ?string
    {
        $t = self::normaliser((string)$texte);
        if ($t === '') {
            return null;
        }

        // Codes bruts, bornés : « m1 » oui, « btsm1 » non.
        if (preg_match('/\b([lm])\s*([1-3])\b/', $t, $m) === 1) {
            $code = strtoupper($m[1] . $m[2]);
            if (isset(self::LIBELLES[$code])) {
                return $code;
            }
        }

        $estMaster  = preg_match('/\b(master|mastere|mba)\b/', $t) === 1;
        $estLicence = preg_match('/\b(licence|bachelor|bts|dut|deug)\b/', $t) === 1;

        // Rang exprimé en chiffres ou en lettres, dans l'ordre de fréquence
        // réelle des saisies observées.
        $rang = null;
        if (preg_match('/\b([1-5])\b/', $t, $m) === 1) {
            $rang = (int)$m[1];
        } elseif (preg_match('/\b(premiere|1ere|1er)\b/', $t) === 1) {
            $rang = 1;
        } elseif (preg_match('/\b(deuxieme|seconde|2eme|2e)\b/', $t) === 1) {
            $rang = 2;
        } elseif (preg_match('/\b(troisieme|3eme|3e)\b/', $t) === 1) {
            $rang = 3;
        }

        if ($estMaster) {
            // « Master » sans rang = M1 : c'est le niveau d'ENTRÉE dans le
            // cycle, et un candidat n'entre pas en M2 sans le préciser.
            return $rang === 2 ? self::M2 : self::M1;
        }

        if ($estLicence) {
            return match ($rang) {
                2       => self::L2,
                3       => self::L3,
                default => self::L1,
            };
        }

        // « Bac+3 » : le chiffre porte le nombre d'années après le bac, pas le
        // rang dans un cycle. Bac+4 et Bac+5 tombent donc en master.
        if (preg_match('/\bbac\s*\+?\s*([1-5])\b/', $t, $m) === 1) {
            return match ((int)$m[1]) {
                1       => self::L1,
                2       => self::L2,
                3       => self::L3,
                4       => self::M1,
                default => self::M2,
            };
        }

        return null;
    }

    /**
     * Minuscules, sans accents, séparateurs ramenés à l'espace.
     *
     * `_` est un caractère de mot pour `\b` : sans cette normalisation, les
     * clés historiques du type « master_m1_m2 » ne déclencheraient aucune borne
     * et passeraient au travers de toutes les expressions ci-dessus.
     */
    private static function normaliser(string $texte): string
    {
        $t = mb_strtolower(trim($texte));

        $t = strtr($t, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o',
            'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim(preg_replace('/[^a-z0-9+]+/', ' ', $t) ?? '');
    }
}
