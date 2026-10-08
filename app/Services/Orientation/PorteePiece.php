<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Portée d'une pièce justificative requise : à QUELS niveaux d'entrée
 * s'applique-t-elle ?
 *
 * ── Le problème que ce service résout ────────────────────────────────────────
 *
 * `pieces_justificatives_requises.niveau` et `dossiers_inscription.niveau_entree`
 * ne parlent pas la même langue, et personne ne les avait jamais réconciliés :
 *
 *   · la colonne de la PIÈCE porte des libellés de CYCLE hérités du premier
 *     back-office — « Socle L1-L2 », « Licence L3 », « Master M1-M2 » ;
 *   · la colonne du DOSSIER porte un niveau d'ANNÉE — « Licence 1 »,
 *     « Master 1 » — quand elle n'est pas du texte libre historique
 *     (« LICENCE2 », « Bac+3 », « Lincence 1 »…).
 *
 * Les deux écrans qui filtraient comparaient ces chaînes à l'identique
 * (`'Master M1-M2' === 'Licence 1'`). La comparaison échouait donc TOUJOURS, et
 * les deux erreurs symétriques en découlaient :
 *
 *   · à l'inscription, une pièce réservée au master restait affichée à un
 *     candidat de licence (la règle « ne jamais masquer une pièce obligatoire »
 *     la rattrapait à chaque fois) ;
 *   · dans l'espace élève, une pièce de master déjà déposée par un candidat de
 *     master DISPARAISSAIT de sa liste — refus de l'établissement compris, sans
 *     aucun moyen de la remplacer.
 *
 * Ce service est le seul endroit où la question se tranche. Les deux colonnes
 * passent par lui, jamais par une comparaison de chaînes.
 *
 * ── Le parti pris en cas de doute ────────────────────────────────────────────
 *
 * Quand une valeur n'est pas interprétable — un intitulé de portée inconnu
 * (« Certification »), ou un niveau de dossier en texte libre que
 * {@see Niveau::code()} n'arrive pas à normaliser — la pièce est considérée
 * comme CONCERNÉE. Montrer une pièce de trop coûte une ligne à l'écran ; en
 * masquer une bloque un dossier sans que le candidat comprenne pourquoi.
 */
final class PorteePiece
{
    /**
     * Niveaux couverts par la portée écrite sur une pièce.
     *
     * @return list<string>|null Codes {@see Niveau} concernés, ou null pour
     *                           « tous les niveaux » (tronc commun).
     */
    public static function codes(?string $porteePiece): ?array
    {
        $texte = self::normaliser((string)$porteePiece);
        if ($texte === '') {
            return null;
        }

        // 1. Codes d'année explicites : « L1 », « Socle L1-L2 », « M1-M2 ».
        //    C'est la forme la plus précise, elle prime sur tout le reste.
        if (preg_match_all('/\b([lm])\s*([1-3])\b/', $texte, $trouves, PREG_SET_ORDER) > 0) {
            $codes = [];
            foreach ($trouves as $m) {
                $code = strtoupper($m[1] . $m[2]);
                if (Niveau::estValide($code)) {
                    $codes[$code] = true;
                }
            }
            if ($codes !== []) {
                return array_keys($codes);
            }
        }

        // 2. Cycle nommé sans année : « Master », « Licence » → tout le cycle.
        //    Le master est cherché EN PREMIER pour la même raison que dans
        //    Niveau::code() — « Master 1 » contient un « 1 » comme une licence.
        if (preg_match('/\b(master|mastere|mba)\b/', $texte) === 1) {
            return self::codesDuCycle(TarifCycle::MASTER);
        }
        if (preg_match('/\b(licence|bachelor|bts|dut|deug)\b/', $texte) === 1) {
            return self::codesDuCycle(TarifCycle::LICENCE);
        }

        // 3. Intitulé de portée inconnu (« Certification »…) : tronc commun.
        //    Voir l'en-tête — on préfère une pièce de trop à un dossier bloqué.
        return null;
    }

    /**
     * Cette pièce est-elle demandée à un dossier de ce niveau d'entrée ?
     *
     * @param string|null $porteePiece   `pieces_justificatives_requises.niveau`
     * @param string|null $niveauDossier `dossiers_inscription.niveau_entree`
     */
    public static function concerne(?string $porteePiece, ?string $niveauDossier): bool
    {
        $portee = self::codes($porteePiece);
        if ($portee === null) {
            return true; // Tronc commun : demandée à tout le monde.
        }

        $code = Niveau::code($niveauDossier);
        if ($code === null) {
            // Niveau pas encore choisi, ou texte libre historique illisible :
            // on ne masque rien. Le serveur repassera derrière au moment où le
            // niveau sera réellement connu.
            return true;
        }

        return in_array($code, $portee, true);
    }

    /**
     * Portée sous forme de liste de codes pour un attribut HTML, destinée au
     * filtrage côté navigateur du formulaire d'inscription (le niveau y est
     * choisi sur la MÊME page que les pièces, le serveur ne peut donc pas
     * trancher au rendu).
     *
     * Chaîne vide = tronc commun. Le navigateur applique exactement la règle
     * ci-dessus, et le serveur la réapplique à la soumission : le filtrage
     * client n'est qu'un confort, jamais une autorisation.
     */
    public static function attribut(?string $porteePiece): string
    {
        $codes = self::codes($porteePiece);

        return $codes === null ? '' : implode(',', $codes);
    }

    /**
     * Libellé lisible d'une portée, pour les écrans de configuration.
     * Ex. « Socle L1-L2 » → « Licence 1, Licence 2 ».
     */
    public static function libelle(?string $porteePiece): string
    {
        $codes = self::codes($porteePiece);
        if ($codes === null) {
            return 'Tous niveaux';
        }

        return implode(', ', array_map(
            static fn(string $code): string => Niveau::libelle($code),
            $codes
        ));
    }

    /**
     * Portées proposées à l'établissement quand il configure ses pièces.
     *
     * Les valeurs sont celles que `codes()` sait relire sans ambiguïté :
     * `''` (tronc commun), un cycle entier, ou une année précise. Configurer
     * une pièce ne produit donc plus de vocabulaire à réconcilier après coup —
     * c'est l'origine de la panne que ce service répare.
     *
     * @return array<string,array<string,string>> Groupes d'<optgroup>.
     */
    public static function options(): array
    {
        $groupes = ['' => ['' => 'Tous niveaux (tronc commun)']];

        foreach ([TarifCycle::LICENCE, TarifCycle::MASTER] as $cycle) {
            $libelleCycle = TarifCycle::libelle($cycle);
            $entrees      = [strtoupper($cycle) => 'Tout le cycle ' . $libelleCycle];

            foreach (self::codesDuCycle($cycle) as $code) {
                $entrees[$code] = Niveau::libelle($code);
            }

            $groupes[$libelleCycle] = $entrees;
        }

        return $groupes;
    }

    /**
     * Les mêmes options, complétées par la valeur DÉJÀ enregistrée si elle
     * n'en fait pas partie.
     *
     * Les référentiels existants portent des intitulés hérités du premier
     * back-office (« Socle L1-L2 », « Certification »). Les faire disparaître
     * du menu déroulant reviendrait à réattribuer silencieusement la pièce à
     * « tous niveaux » au premier enregistrement de l'établissement, sans que
     * personne ne l'ait demandé.
     *
     * @return array<string,array<string,string>>
     */
    public static function optionsAvec(?string $valeurActuelle): array
    {
        $groupes = self::options();
        $valeur  = trim((string)$valeurActuelle);

        if ($valeur === '') {
            return $groupes;
        }

        foreach ($groupes as $entrees) {
            if (array_key_exists($valeur, $entrees)) {
                return $groupes;
            }
        }

        $groupes['Portée enregistrée'] = [$valeur => $valeur . ' (' . self::libelle($valeur) . ')'];

        return $groupes;
    }

    /**
     * @return list<string> Codes d'un cycle, dans l'ordre du cursus.
     */
    private static function codesDuCycle(string $cycle): array
    {
        $codes = [];
        foreach (Niveau::codes() as $code) {
            if (Niveau::cycle($code) === $cycle) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Minuscules, sans accents, séparateurs ramenés à l'espace.
     *
     * `-` et `_` sont ramenés à l'espace pour que `\b` borne réellement les
     * codes : sans cela « L1-L2 » et « master_m1_m2 » passeraient au travers de
     * toutes les expressions ci-dessus.
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
