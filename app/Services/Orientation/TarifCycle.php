<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\TarifFormationSchema;
use PDO;

/**
 * Tarif applicable à un dossier : scolarité, frais d'inscription, et base de
 * commission qui en découle.
 *
 * ── Pourquoi ce service existe ───────────────────────────────────────────────
 *
 * L'agent du guichet ne SAISIT plus le montant de la scolarité. Les tarifs sont
 * fixés par le super administrateur (fiche établissement et grille des
 * formations) ; les faire ressaisir au guichet, c'est accepter que la base de
 * calcul d'une commission dépende d'une frappe.
 *
 * ── Ce que le tarif dépend, désormais ────────────────────────────────────────
 *
 * Trois dimensions, et pas une de plus :
 *
 *   1. LA FORMATION — la filière visée porte sa propre grille
 *      (`tarifs_formation`). Deux filières du même établissement peuvent avoir
 *      des tarifs entièrement différents ; c'est le cas réel des partenaires.
 *
 *   2. LE NIVEAU — un établissement peut tarifer L1, L2 et L3 séparément.
 *      Un autre applique un tarif unique à tout le cycle. La grille porte les
 *      deux : une ligne par niveau, plus une ligne « tout le cycle » qui sert
 *      de repli. Aucun des deux établissements n'a à déclarer un « mode ».
 *
 *   3. LA SITUATION DE BOURSE — chaque ligne porte deux montants. Un boursier
 *      dont le tarif n'est pas renseigné retombe sur le tarif non boursier :
 *      c'est ce qui permet à un établissement à tarif unique de ne rien saisir
 *      de plus qu'avant.
 *
 * ── La règle de commission ───────────────────────────────────────────────────
 *
 * La base de la commission dépend de la situation de bourse déclarée :
 *
 *   · boursier confirmé      → les FRAIS D'INSCRIPTION **par défaut**. Sa
 *                              scolarité est prise en charge par l'ANBG ; la
 *                              commissionner reviendrait à facturer
 *                              l'établissement sur un flux qu'il ne perçoit pas
 *                              de l'étudiant.
 *                              Ce seul cas est NÉGOCIABLE : la fiche
 *                              établissement porte `base_commission_boursier`,
 *                              réglable par le super administrateur, parce que
 *                              certains accords prévoient la scolarité — que
 *                              l'établissement perçoit alors de l'ANBG.
 *   · non boursier           → la SCOLARITÉ. L'étudiant reste redevable de
 *                              la totalité. Inchangé. Non négociable.
 *
 * Le taux, lui, reste GELÉ dans le dossier à la validation à 100 %
 * (`taux_commission_applique`) : voir CommissionDossier.
 *
 * ── Zéro n'est pas gratuit ───────────────────────────────────────────────────
 *
 * Sur tous les champs de tarif de la plateforme, 0 signifie « non renseigné ».
 * La résolution descend alors d'un cran plutôt que de retenir un montant nul.
 * Cette convention est celle des colonnes existantes : on ne l'inverse pas ici,
 * au risque de faire passer gratuitement toutes les fiches incomplètes.
 */
final class TarifCycle
{
    public const LICENCE = 'licence';
    public const MASTER  = 'master';

    /** Natures possibles de la base de commission (colonne `base_commission`). */
    public const BASE_SCOLARITE         = 'scolarite';
    public const BASE_FRAIS_INSCRIPTION = 'frais_inscription';

    /**
     * Assiette retenue pour un BOURSIER quand l'établissement ne dit rien.
     *
     * Le défaut est celui de la règle générale : sa scolarité étant prise en
     * charge par l'ANBG, la commissionner reviendrait à facturer un flux que
     * l'établissement ne perçoit pas de l'étudiant.
     */
    public const BASE_BOURSIER_DEFAUT = self::BASE_FRAIS_INSCRIPTION;

    /** Valeurs acceptées par `etablissements.base_commission_boursier`. */
    public const BASES_BOURSIER = [self::BASE_FRAIS_INSCRIPTION, self::BASE_SCOLARITE];

    /**
     * Réglage `base_commission_boursier` par établissement, mémorisé pour la
     * durée du process. Voir baseBoursierEtablissement().
     *
     * @var array<int,string>
     */
    private static array $cacheBaseBoursier = [];

    private const LIBELLES = [
        self::LICENCE => 'Licence',
        self::MASTER  => 'Master',
    ];

    private const LIBELLES_BASE = [
        self::BASE_SCOLARITE         => 'Scolarité annuelle',
        self::BASE_FRAIS_INSCRIPTION => 'Frais d’inscription',
    ];

    /** Colonnes de scolarité de la fiche établissement, par cycle et bourse. */
    private const COLONNES_ETAB_SCOLARITE = [
        self::LICENCE => ['non_boursier' => 'frais_scolarite',        'boursier' => 'frais_scolarite_boursier'],
        self::MASTER  => ['non_boursier' => 'frais_scolarite_master', 'boursier' => 'frais_scolarite_master_boursier'],
    ];

    /** Colonnes de frais d'inscription de la fiche établissement. */
    private const COLONNES_ETAB_INSCRIPTION = [
        self::LICENCE => ['non_boursier' => 'frais_inscription_licence', 'boursier' => 'frais_inscription_licence_boursier'],
        self::MASTER  => ['non_boursier' => 'frais_inscription_master',  'boursier' => 'frais_inscription_master_boursier'],
    ];

    // ── Cycle et niveau ──────────────────────────────────────────────────────

    /**
     * Cycle du dossier.
     *
     * Le niveau d'entrée est désormais choisi dans une liste fermée, mais les
     * dossiers antérieurs portent du texte libre : on passe donc par
     * Niveau::code(), qui sait lire les deux. En dernier recours seulement, on
     * cherche un indice de master dans la filière et la spécialité.
     *
     * En l'absence de tout indice on retombe sur la Licence — le cycle d'entrée
     * le plus courant, et le tarif le plus bas : une erreur de détection ne peut
     * donc pas surfacturer un établissement.
     */
    public static function detecter(array $dossier): string
    {
        $cycle = Niveau::cycle(Niveau::code((string)($dossier['niveau_entree'] ?? '')));
        if ($cycle !== null) {
            return $cycle;
        }

        $indices = trim(
            (string)($dossier['niveau_entree']    ?? '') . ' ' .
            (string)($dossier['filiere_visee']    ?? '') . ' ' .
            (string)($dossier['specialite_visee'] ?? '')
        );

        return self::estMaster($indices) ? self::MASTER : self::LICENCE;
    }

    public static function libelle(string $cycle): string
    {
        return self::LIBELLES[$cycle] ?? self::LIBELLES[self::LICENCE];
    }

    public static function libelleBase(string $base): string
    {
        return self::LIBELLES_BASE[$base] ?? self::LIBELLES_BASE[self::BASE_SCOLARITE];
    }

    /**
     * Un texte libre désigne-t-il un cycle Master ?
     *
     * « M1 » et « M2 » sont bornés par \b pour ne pas capturer « BTSM1 » ou une
     * référence quelconque ; « mastère » et « MBA » sont traités comme du Master
     * parce que c'est ainsi qu'ils sont tarifés dans les fiches réelles.
     */
    private static function estMaster(string $texte): bool
    {
        $texte = mb_strtolower($texte);

        // Accents retirés : « mastère » et « mastere » doivent tomber ensemble.
        $texte = strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o',
            'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        // Séparateurs ramenés à l'espace : les clés historiques de filière
        // s'écrivent « master_m1_m2 », et `_` est un caractère de mot pour \b —
        // sans cette normalisation, la borne ne tombe jamais et le cycle Master
        // passerait au tarif Licence.
        $texte = preg_replace('/[^a-z0-9]+/', ' ', $texte) ?? '';

        return preg_match('/\b(master|mastere|mba|m1|m2|doctorat|phd)\b/', $texte) === 1;
    }

    // ── Résolution ───────────────────────────────────────────────────────────

    /**
     * Montant de scolarité annuelle applicable, ou null si rien n'est
     * configuré nulle part.
     *
     * @param array<string,mixed>      $dossier      Ligne du dossier.
     * @param array<string,mixed>|null $etab         Fiche établissement, si déjà chargée.
     * @param string|null              $statutBourse Force la situation de bourse
     *                                               (sinon celle du dossier).
     */
    public static function scolariteApplicable(array $dossier, ?array $etab = null, ?string $statutBourse = null): ?float
    {
        return self::detail($dossier, $etab, $statutBourse)['montant'];
    }

    /**
     * Frais d'inscription applicables au dossier.
     *
     * Signature rétrocompatible : appelée avec la seule fiche établissement,
     * elle répond pour le cycle Licence non boursier — c'est-à-dire le tarif
     * public affiché, comme avant.
     *
     * @param array<string,mixed>|null $etab
     * @param array<string,mixed>|null $dossier Dossier, pour connaître cycle et bourse.
     */
    public static function fraisInscription(?array $etab, ?array $dossier = null, ?string $statutBourse = null): ?float
    {
        if ($etab === null) {
            return null;
        }

        if ($dossier !== null) {
            return self::detail($dossier, $etab, $statutBourse)['frais_inscription'];
        }

        $bourse = $statutBourse !== null ? (string)$statutBourse : null;

        return self::resoudreFraisInscription($etab, self::LICENCE, $bourse, null)['montant'];
    }

    /**
     * Détail complet du tarif retenu : montants, provenance, base de commission.
     *
     * L'agent doit pouvoir constater, sans ouvrir la base, que le montant n'est
     * pas sorti de nulle part — et le support doit pouvoir répondre « c'est la
     * grille de la formation » quand un établissement conteste sa commission.
     *
     * @return array{
     *   cycle:string, cycle_libelle:string, niveau:?string, niveau_libelle:string,
     *   montant:?float, source:string, tarif_boursier:?float, configure:bool,
     *   statut_bourse:?string, est_boursier:bool,
     *   frais_inscription:?float, frais_inscription_source:string,
     *   base_commission:string, base_commission_libelle:string,
     *   montant_base_commission:?float, base_configuree:bool
     * }
     */
    public static function detail(array $dossier, ?array $etab = null, ?string $statutBourse = null): array
    {
        $etab ??= self::etablissement((int)($dossier['etablissement_id'] ?? 0));

        $cycle  = self::detecter($dossier);
        $niveau = Niveau::code((string)($dossier['niveau_entree'] ?? ''));
        $bourse = $statutBourse ?? ($dossier['statut_bourse'] ?? null);
        $bourse = $bourse !== null ? (string)$bourse : null;

        $estBoursier = $bourse === SituationBourse::BOURSIER;

        // Fiche filière et grille chargées UNE fois : ce bloc est appelé sur le
        // chemin de la validation à 100 %, et trois résolutions successives
        // rejoueraient sinon six requêtes pour lire deux fois la même chose.
        $filiere = self::filiereDuDossier($dossier);
        $grille  = self::grillePourFiliere($filiere);

        $scolarite = self::resoudreScolarite($etab, $cycle, $niveau, $bourse, $grille, $filiere);
        $frais     = self::resoudreFraisInscription($etab, $cycle, $bourse, $filiere);

        // Tarif boursier affiché à titre indicatif au guichet : il permet à
        // l'agent de constater que la fiche distingue bien les deux situations.
        // Recalculé plutôt que déduit : pour un établissement à tarif unique,
        // il vaut le tarif non boursier, et c'est bien ce que paiera le boursier.
        $tarifBoursier = $estBoursier
            ? $scolarite['montant']
            : self::resoudreScolarite(
                $etab, $cycle, $niveau, SituationBourse::BOURSIER, $grille, $filiere
            )['montant'];

        // Le réglage se lit sur la fiche DÉJÀ chargée : pas une requête de plus
        // sur le chemin du guichet. Certains appelants passent pourtant une
        // fiche partielle (SELECT ciblé) : on retombe alors sur la lecture
        // dédiée, mise en cache. Sans ce repli, l'aperçu affiché à l'agent
        // pourrait annoncer une assiette que la commission ne retiendra pas.
        $reglage = array_key_exists('base_commission_boursier', (array)$etab)
            ? (string)$etab['base_commission_boursier']
            : ($estBoursier
                ? self::baseBoursierEtablissement((int)($dossier['etablissement_id'] ?? 0))
                : null);

        $base = self::baseCommission($bourse, $reglage);

        return [
            'cycle'          => $cycle,
            'cycle_libelle'  => self::libelle($cycle),
            'niveau'         => $niveau,
            'niveau_libelle' => $niveau !== null ? Niveau::libelle($niveau) : '',

            'montant'        => $scolarite['montant'],
            'source'         => $scolarite['source'],
            'configure'      => $scolarite['montant'] !== null,

            'statut_bourse'  => $bourse,
            'est_boursier'   => $estBoursier,
            'tarif_boursier' => $tarifBoursier,

            'frais_inscription'        => $frais['montant'],
            'frais_inscription_source' => $frais['source'],

            'base_commission'         => $base,
            'base_commission_libelle' => self::libelleBase($base),
            'montant_base_commission' => $base === self::BASE_FRAIS_INSCRIPTION
                ? $frais['montant']
                : $scolarite['montant'],
            'base_configuree' => ($base === self::BASE_FRAIS_INSCRIPTION
                ? $frais['montant']
                : $scolarite['montant']) !== null,
        ];
    }

    /**
     * Scolarité annuelle d'une formation, pour un niveau et une situation de
     * bourse donnés — SANS dossier.
     *
     * La page publique d'un établissement doit annoncer ses prix avant que le
     * moindre dossier n'existe. Elle passe par ici plutôt que de relire la
     * table `tarifs_formation` pour son compte : la cascade de replis (ligne de
     * niveau → ligne de cycle → fiche établissement → fiche filière) est une
     * règle métier, et une deuxième implémentation aurait fini par afficher au
     * candidat un prix que le guichet ne lui aurait pas facturé.
     *
     * `$grille` et `$etab` sont passés par l'appelant, déjà chargés : la page
     * publique affiche une dizaine de formations et n'a pas à rejouer la même
     * requête pour chacune.
     *
     * @param array<string,array<string,array<string,mixed>>> $grille  Grille de la formation ({@see \Models\Orientation\TarifFormation::grille()}).
     * @param array<string,mixed>|null                        $etab    Fiche établissement.
     * @param array<string,mixed>|null                        $filiere Fiche filière (repli historique `frais_scolarite`).
     *
     * @return array{montant:float|null, source:string}
     */
    public static function scolaritePourFormation(
        array $grille,
        string $cycle,
        ?string $niveau,
        ?string $statutBourse,
        ?array $etab = null,
        ?array $filiere = null
    ): array {
        if (!isset($grille[$cycle])) {
            $grille[$cycle] = [];
        }

        return self::resoudreScolarite($etab, $cycle, $niveau, $statutBourse, $grille, $filiere);
    }

    /**
     * Nature de la base de commission pour une situation de bourse.
     *
     * Point d'entrée unique de la règle : CommissionDossier, le guichet et les
     * écrans de suivi lisent tous celui-ci. Une règle métier recopiée à trois
     * endroits finit toujours par diverger à deux d'entre eux.
     *
     * Une situation NON RENSEIGNÉE est traitée comme non boursière : c'est le
     * cas des dossiers antérieurs à la déclaration de bourse, et la scolarité
     * est bien ce sur quoi ils ont été facturés.
     *
     * ── Le cas boursier est NÉGOCIABLE, les autres ne le sont pas ────────────
     *
     * `$baseBoursierEtab` porte l'arbitrage inscrit sur la fiche établissement
     * (`base_commission_boursier`), réservé au super administrateur : certains
     * accords prévoient que la commission d'un boursier porte sur la scolarité,
     * que l'établissement perçoit alors de l'ANBG.
     *
     * Ce réglage ne s'applique QU'AUX BOURSIERS CONFIRMÉS. Un non-boursier
     * reste commissionné sur la scolarité quoi qu'il arrive : il y reste
     * redevable de la totalité, et aucun accord ne porte là-dessus. Une valeur
     * inconnue ou nulle retombe sur le défaut historique — un réglage illisible
     * ne doit pas changer une facturation.
     */
    public static function baseCommission(?string $statutBourse, ?string $baseBoursierEtab = null): string
    {
        if ($statutBourse !== SituationBourse::BOURSIER) {
            return self::BASE_SCOLARITE;
        }

        return self::normaliserBaseBoursier($baseBoursierEtab);
    }

    /**
     * Ramène un réglage d'établissement à une valeur sûre.
     *
     * Tout ce qui n'est pas explicitement reconnu retombe sur le défaut : cette
     * valeur vient d'une colonne qui peut être absente (migration non rejouée),
     * nulle (ligne antérieure) ou corrompue par une reprise de données, et une
     * assiette de facturation ne se devine pas.
     */
    public static function normaliserBaseBoursier(?string $valeur): string
    {
        $valeur = $valeur !== null ? trim($valeur) : '';

        return in_array($valeur, self::BASES_BOURSIER, true) ? $valeur : self::BASE_BOURSIER_DEFAUT;
    }

    /**
     * Réglage `base_commission_boursier` d'un établissement.
     *
     * Mis en cache pour la durée du process, par établissement : cette méthode
     * est appelée sur le chemin de la validation à 100 % et sur les écrans de
     * suivi qui listent des centaines de commissions. Une requête par ligne
     * affichée serait un N+1 sur une table lue en permanence, pour une donnée
     * qui ne change qu'à la main, dans le back-office.
     *
     * La lecture est bornée à UNE colonne : la fiche établissement complète
     * pèse plusieurs kilo-octets (descriptions, JSON de badges, images) et
     * n'a rien à faire ici.
     */
    public static function baseBoursierEtablissement(int $etablissementId, ?PDO $db = null): string
    {
        if ($etablissementId <= 0) {
            return self::BASE_BOURSIER_DEFAUT;
        }

        if (array_key_exists($etablissementId, self::$cacheBaseBoursier)) {
            return self::$cacheBaseBoursier[$etablissementId];
        }

        $valeur = self::BASE_BOURSIER_DEFAUT;

        try {
            $db ??= Database::getInstance()->getConnection();

            if (TarifFormationSchema::baseBoursierDisponible($db)) {
                $st = $db->prepare(
                    'SELECT base_commission_boursier FROM etablissements WHERE id = ? LIMIT 1'
                );
                $st->execute([$etablissementId]);
                $brut = $st->fetchColumn();

                $valeur = self::normaliserBaseBoursier($brut === false ? null : (string)$brut);
            }
        } catch (\Throwable $e) {
            error_log(
                '[TarifCycle] Base de commission boursier illisible #' . $etablissementId
                . ' : ' . $e->getMessage()
            );
        }

        return self::$cacheBaseBoursier[$etablissementId] = $valeur;
    }

    /**
     * Vide le cache des réglages d'établissement.
     *
     * Appelé après une modification de fiche par le super administrateur, et
     * par les tests. Sans cela, l'écran qui suit l'enregistrement afficherait
     * encore l'ancien réglage.
     */
    public static function oublierReglages(): void
    {
        self::$cacheBaseBoursier = [];
    }

    /**
     * Montant servant de base à la commission, pour un dossier DÉJÀ validé.
     *
     * Lit les montants figés sur le dossier (`montant_scolarite_annuelle`,
     * `montant_frais_inscription`), jamais la configuration courante : après la
     * validation à 100 %, c'est ce qui a été constaté au guichet qui fait foi.
     *
     * Le réglage `base_commission_boursier` de l'établissement n'est lu que
     * pour un boursier confirmé — pour toute autre situation la réponse est
     * connue d'avance (la scolarité), et interroger la base serait une requête
     * pour rien sur le chemin de la validation à 100 %.
     *
     * @return array{base:string, montant:?float}
     */
    public static function baseDepuisDossier(
        array $dossier,
        ?string $statutBourse = null,
        ?PDO $db = null
    ): array {
        $bourse = $statutBourse ?? ($dossier['statut_bourse'] ?? null);
        $bourse = $bourse !== null ? (string)$bourse : null;

        $reglage = $bourse === SituationBourse::BOURSIER
            ? self::baseBoursierEtablissement((int)($dossier['etablissement_id'] ?? 0), $db)
            : null;

        $base = self::baseCommission($bourse, $reglage);

        $brut = $base === self::BASE_FRAIS_INSCRIPTION
            ? ($dossier['montant_frais_inscription'] ?? null)
            : ($dossier['montant_scolarite_annuelle'] ?? null);

        $montant = ($brut === null || $brut === '') ? null : (float)$brut;

        return [
            'base'    => $base,
            // 0 n'est pas une base de facturation : un établissement qui ne
            // prend pas de frais d'inscription ne doit pas produire une
            // commission de 0 F, mais aucune commission du tout.
            'montant' => ($montant !== null && $montant > 0) ? $montant : null,
        ];
    }

    // ── Interne : scolarité ──────────────────────────────────────────────────

    /**
     * Échelle de recherche de la scolarité, du plus précis au plus permissif.
     *
     * Pour un BOURSIER :
     *   1. grille de la formation, niveau exact, colonne boursier
     *   2. grille de la formation, tout le cycle, colonne boursier
     *   3. fiche établissement, tarif boursier du cycle
     *   … puis, si rien n'est configuré côté boursier, la même échelle côté
     *   NON boursier (4 → 8). Un établissement à tarif unique ne saisit ainsi
     *   qu'une seule colonne, et ses boursiers sont tarifés comme les autres.
     *
     * Pour un NON BOURSIER, l'échelle démarre directement au point 4.
     *
     * @return array{montant:?float, source:string}
     */
    private static function resoudreScolarite(
        ?array $etab,
        string $cycle,
        ?string $niveau,
        ?string $bourse,
        array $grille,
        ?array $filiere
    ): array {
        $colonne = self::COLONNES_ETAB_SCOLARITE[$cycle] ?? self::COLONNES_ETAB_SCOLARITE[self::LICENCE];

        $pistes = [];

        if ($bourse === SituationBourse::BOURSIER) {
            $pistes[] = ['grille', $cycle, $niveau, 'scolarite_boursier',     'grille_niveau_boursier'];
            $pistes[] = ['grille', $cycle, '',      'scolarite_boursier',     'grille_cycle_boursier'];
            $pistes[] = ['etab',   $colonne['boursier'],                      'etablissement_boursier'];
        }

        $pistes[] = ['grille', $cycle, $niveau, 'scolarite_non_boursier', 'grille_niveau'];
        $pistes[] = ['grille', $cycle, '',      'scolarite_non_boursier', 'grille_cycle'];
        $pistes[] = ['etab',   $colonne['non_boursier'],                  'etablissement'];

        foreach ($pistes as $piste) {
            if ($piste[0] === 'grille') {
                [, $c, $n, $col, $source] = $piste;
                if ($n === null) {
                    continue; // niveau indéterminé : la piste « niveau exact » n'existe pas
                }
                $valeur = (float)($grille[$c][$n][$col] ?? 0);
            } else {
                [, $col, $source] = $piste;
                $valeur = $etab !== null ? (float)($etab[$col] ?? 0) : 0.0;
            }

            if ($valeur > 0) {
                return ['montant' => $valeur, 'source' => $source];
            }
        }

        // Repli historique : la fiche filière portait un montant unique, sans
        // cycle ni bourse. Conservé pour les établissements qui n'ont pas
        // encore rempli leur grille.
        $legacy = (float)($filiere['frais_scolarite'] ?? 0);
        if ($legacy > 0) {
            return ['montant' => $legacy, 'source' => 'filiere'];
        }

        // Dernier repli : les fiches anciennes n'ont qu'un montant de licence.
        $licence = $etab !== null ? (float)($etab['frais_scolarite'] ?? 0) : 0.0;
        if ($licence > 0) {
            return ['montant' => $licence, 'source' => 'etablissement_licence'];
        }

        return ['montant' => null, 'source' => 'aucune'];
    }

    // ── Interne : frais d'inscription ────────────────────────────────────────

    /**
     * Échelle de recherche des frais d'inscription.
     *
     *   1. fiche établissement, cycle + bourse (les quatre colonnes dédiées)
     *   2. fiche établissement, cycle, tarif non boursier
     *   3. fiche filière (`frais_inscription`), montant unique historique
     *   4. `prix_inscription` + `frais_dossier` — le tarif public affiché,
     *      comportement d'avant la refonte
     *
     * @return array{montant:?float, source:string}
     */
    private static function resoudreFraisInscription(
        ?array $etab,
        string $cycle,
        ?string $bourse,
        ?array $filiere
    ): array {
        if ($etab === null) {
            return ['montant' => null, 'source' => 'aucune'];
        }

        if (TarifFormationSchema::fraisInscriptionDisponible()) {
            $colonne = self::COLONNES_ETAB_INSCRIPTION[$cycle] ?? self::COLONNES_ETAB_INSCRIPTION[self::LICENCE];

            if ($bourse === SituationBourse::BOURSIER) {
                $valeur = (float)($etab[$colonne['boursier']] ?? 0);
                if ($valeur > 0) {
                    return ['montant' => $valeur, 'source' => 'etablissement_boursier'];
                }
            }

            $valeur = (float)($etab[$colonne['non_boursier']] ?? 0);
            if ($valeur > 0) {
                return ['montant' => $valeur, 'source' => 'etablissement'];
            }
        }

        $legacy = (float)($filiere['frais_inscription'] ?? 0);
        if ($legacy > 0) {
            return ['montant' => $legacy, 'source' => 'filiere'];
        }

        $public = (int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0);

        return $public > 0
            ? ['montant' => (float)$public, 'source' => 'tarif_public']
            : ['montant' => null,           'source' => 'aucune'];
    }

    // ── Interne : accès aux données ──────────────────────────────────────────

    /**
     * Fiche établissement, lue à CHAQUE appel.
     *
     * Volontairement sans cache : ces colonnes portent la base de calcul d'une
     * commission. Un tarif corrigé par le super administrateur doit s'appliquer
     * à la validation suivante, pas au prochain redémarrage du processus — et
     * un worker de longue vie servirait sinon des montants périmés.
     *
     * @return array<string,mixed>|null
     */
    public static function etablissement(int $etablissementId): ?array
    {
        if ($etablissementId <= 0) {
            return null;
        }

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM etablissements WHERE id = ? LIMIT 1'
            );
            $st->execute([$etablissementId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[TarifCycle] Fiche établissement illisible #' . $etablissementId . ' : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Fiche de la filière visée par le dossier, cherchée par NOM.
     *
     * Le dossier stocke le libellé de la filière, pas son identifiant : c'est
     * le choix fait par migration_filieres_specialites, et le changer
     * obligerait à rétro-remplir des milliers de dossiers dont la filière a pu
     * être renommée ou supprimée depuis. La recherche par nom échoue
     * proprement (null) quand la filière n'existe plus.
     *
     * @return array<string,mixed>|null
     */
    private static function filiereDuDossier(array $dossier): ?array
    {
        $nom    = trim((string)($dossier['filiere_visee'] ?? ''));
        $etabId = (int)($dossier['etablissement_id'] ?? 0);

        if ($nom === '' || $etabId <= 0) {
            return null;
        }

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT id, frais_inscription, frais_scolarite
                   FROM filieres_etablissement
                  WHERE etablissement_id = ? AND nom = ?
                  LIMIT 1'
            );
            $st->execute([$etabId, $nom]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[TarifCycle] Fiche filière illisible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Grille tarifaire d'une fiche filière déjà chargée.
     *
     * @param array<string,mixed>|null $filiere
     * @return array<string,array<string,array{scolarite_non_boursier:int,scolarite_boursier:int}>>
     */
    private static function grillePourFiliere(?array $filiere): array
    {
        if ($filiere === null || !TarifFormationSchema::grilleDisponible()) {
            return [self::LICENCE => [], self::MASTER => []];
        }

        return (new \Models\Orientation\TarifFormation())->grille((int)$filiere['id']);
    }
}
