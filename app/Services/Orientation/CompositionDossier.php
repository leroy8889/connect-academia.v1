<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Composition du dossier d'INSCRIPTION — les pièces que le candidat devra
 * apporter pour transformer sa préinscription en inscription.
 *
 * ── Le problème que ça règle ─────────────────────────────────────────────────
 *
 * Jusqu'ici, l'attestation de préinscription se contentait d'annoncer
 * « l'ensemble des pièces justificatives correspondant à votre niveau
 * d'entrée ». Le candidat repartait sans savoir quoi réunir, revenait au
 * guichet avec un dossier incomplet, et l'établissement le renvoyait chez lui.
 * La liste existe — elle est juste restée sur une affiche à l'accueil.
 *
 * Le super administrateur la saisit désormais sur la fiche établissement. Elle
 * est imprimée sur l'attestation, sur la facture proforma, et publiée sur le
 * gabarit public.
 *
 * ── Trois groupes, et pas quatre ─────────────────────────────────────────────
 *
 *   · COMMUN  — exigé de tous : acte de naissance, photos, copie de la CNI…
 *   · LICENCE — ce qui S'AJOUTE au commun pour un candidat L1 → L3.
 *   · MASTER  — ce qui S'AJOUTE au commun pour un candidat M1 → M2.
 *
 * Le groupe commun n'est pas un confort d'écriture : sans lui, chaque
 * établissement ressaisirait deux fois les mêmes six pièces, et la première
 * divergence entre les deux copies passerait inaperçue jusqu'au jour où un
 * candidat de master se verrait refuser un document que la licence exigeait.
 *
 * Le doctorat n'a pas de groupe : `dossiers_inscription.niveau_entree` est une
 * liste FERMÉE L1 → M2 ({@see Niveau}), aucun dossier ne peut donc porter un
 * cycle doctoral. En inventer un ici aurait créé un champ que rien ne lit.
 *
 * ── Le cycle n'est jamais deviné ici ─────────────────────────────────────────
 *
 * Il vient de {@see TarifCycle::detecter()}, la table de vérité déjà utilisée
 * pour choisir le TARIF du même dossier. Deux détections concurrentes du même
 * cycle finiraient par diverger : un candidat verrait le tarif du master et les
 * pièces de la licence, sans que rien ne signale l'incohérence.
 *
 * ── Stockage ─────────────────────────────────────────────────────────────────
 *
 * `etablissements.composition_dossier`, JSON, NULL quand rien n'est saisi.
 * Voir Database/migration_composition_dossier.sql. La colonne peut être
 * absente en production (migration jouée à la main) : toutes les lectures
 * passent par {@see self::decoder()}, qui rend simplement trois listes vides.
 */
final class CompositionDossier
{
    public const COMMUN  = 'commun';
    public const LICENCE = 'licence';
    public const MASTER  = 'master';

    /** Groupes, dans l'ordre d'affichage. La clé est celle du JSON stocké. */
    private const GROUPES = [
        self::COMMUN  => 'Pièces communes à tous les candidats',
        self::LICENCE => 'Cycle Licence',
        self::MASTER  => 'Cycle Master',
    ];

    /**
     * Garde-fous de saisie. Ils ne protègent pas d'une faute de frappe, mais
     * d'un POST forgé : la colonne est un TEXT, et rien n'empêcherait d'y
     * pousser un mégaoctet de libellés qui seraient ensuite imprimés sur
     * chaque PDF de l'établissement.
     */
    private const MAX_PIECES  = 40;
    private const MAX_LONGUEUR = 200;

    /** Cycle tarifaire → clé de groupe. Seule table de rattachement. */
    private const CYCLE_VERS_GROUPE = [
        TarifCycle::LICENCE => self::LICENCE,
        TarifCycle::MASTER  => self::MASTER,
    ];

    /** @return array<string,string> Clé de groupe → libellé, dans l'ordre. */
    public static function groupes(): array
    {
        return self::GROUPES;
    }

    public static function libelleGroupe(string $cle): string
    {
        return self::GROUPES[$cle] ?? '';
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    /**
     * Valeur à stocker depuis les champs postés (`composition_commun[]`,
     * `composition_licence[]`, `composition_master[]`).
     *
     * Retourne null quand les trois groupes sont vides : « non renseigné »
     * s'écrit NULL en base, pas `{"commun":[],…}`. Sans ça, une fiche jamais
     * remplie serait indiscernable d'une fiche délibérément vidée, et les
     * documents ne sauraient pas s'ils doivent afficher le texte générique.
     *
     * @param array<string,mixed> $post Typiquement $_POST.
     */
    public static function encoder(array $post): ?string
    {
        $sortie = [];

        foreach (array_keys(self::GROUPES) as $cle) {
            $pieces = self::nettoyer($post['composition_' . $cle] ?? null);
            if ($pieces !== []) {
                $sortie[$cle] = $pieces;
            }
        }

        if ($sortie === []) {
            return null;
        }

        // JSON_UNESCAPED_UNICODE : les libellés sont français et relus à l'œil
        // en base par l'exploitant ; « é » partout rendrait la colonne
        // illisible sans rien apporter.
        return json_encode($sortie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Libellés d'un groupe : vidés de leur blanc, dédoublonnés, bornés.
     *
     * Le dédoublonnage est insensible à la casse et aux accents parce que
     * « Acte de naissance » et « acte de naissance » saisis à deux minutes
     * d'intervalle sont la même pièce — et deux lignes identiques sur un
     * document officiel font douter de tout le reste.
     *
     * @return list<string>
     */
    private static function nettoyer(mixed $brut): array
    {
        if (!is_array($brut)) {
            return [];
        }

        $pieces = [];
        $vues   = [];

        foreach ($brut as $valeur) {
            if (!is_scalar($valeur)) {
                continue;
            }

            // Les sauts de ligne casseraient la puce sur les gabarits PDF, où
            // chaque pièce occupe exactement une ligne de table.
            $libelle = trim(preg_replace('/\s+/u', ' ', (string)$valeur) ?? '');
            if ($libelle === '') {
                continue;
            }

            $libelle = mb_substr($libelle, 0, self::MAX_LONGUEUR);

            // Core\Texte::cle() est la forme canonique déjà utilisée ailleurs
            // pour comparer deux libellés saisis à la main.
            $empreinte = \Core\Texte::cle($libelle);
            if (isset($vues[$empreinte])) {
                continue;
            }

            $vues[$empreinte] = true;
            $pieces[]         = $libelle;

            if (count($pieces) >= self::MAX_PIECES) {
                break;
            }
        }

        return $pieces;
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Les trois groupes d'une fiche établissement, toujours dans le même
     * ordre et toujours présents (listes vides si rien n'est saisi).
     *
     * Tolère une fiche issue d'un cache écrit AVANT la migration : la clé est
     * alors simplement absente du tableau.
     *
     * @param array<string,mixed> $etab Ligne `etablissements`.
     * @return array{commun:list<string>, licence:list<string>, master:list<string>}
     */
    public static function depuisEtab(array $etab): array
    {
        return self::decoder($etab['composition_dossier'] ?? null);
    }

    /**
     * @return array{commun:list<string>, licence:list<string>, master:list<string>}
     */
    public static function decoder(mixed $json): array
    {
        $vide = [self::COMMUN => [], self::LICENCE => [], self::MASTER => []];

        if (!is_string($json) || trim($json) === '') {
            return $vide;
        }

        $decode = json_decode($json, true);
        if (!is_array($decode)) {
            // JSON corrompu : on n'affiche rien plutôt que d'imprimer des
            // fragments sur un document officiel.
            return $vide;
        }

        foreach (array_keys($vide) as $cle) {
            $vide[$cle] = self::nettoyer($decode[$cle] ?? null);
        }

        return $vide;
    }

    /** Vrai si au moins une pièce est saisie, tous groupes confondus. */
    public static function estRenseignee(array $etab): bool
    {
        foreach (self::depuisEtab($etab) as $pieces) {
            if ($pieces !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liste applicable à un CYCLE : les pièces communes, puis celles du cycle.
     *
     * L'ordre n'est pas cosmétique — le candidat coche du haut vers le bas, et
     * les pièces communes sont celles qu'il a déjà pour la plupart.
     *
     * @param array<string,mixed> $etab
     * @param string $cycle TarifCycle::LICENCE | TarifCycle::MASTER
     * @return list<string>
     */
    public static function pourCycle(array $etab, string $cycle): array
    {
        $groupes = self::depuisEtab($etab);
        $cle     = self::CYCLE_VERS_GROUPE[$cycle] ?? self::LICENCE;

        return array_values(array_merge($groupes[self::COMMUN], $groupes[$cle]));
    }

    /**
     * Liste applicable à un DOSSIER : son cycle est celui qui décide déjà de
     * son tarif ({@see TarifCycle::detecter()}).
     *
     * @param array<string,mixed> $etab
     * @param array<string,mixed> $dossier Ligne `dossiers_inscription` ou données de formulaire.
     * @return list<string>
     */
    public static function pourDossier(array $etab, array $dossier): array
    {
        return self::pourCycle($etab, TarifCycle::detecter($dossier));
    }

    /**
     * Groupes non vides, prêts pour le gabarit public : le visiteur n'a pas
     * encore de dossier, on lui montre donc les deux cycles côte à côte.
     *
     * @param array<string,mixed> $etab
     * @return list<array{cle:string, libelle:string, pieces:list<string>}>
     */
    public static function groupesRenseignes(array $etab): array
    {
        $groupes = self::depuisEtab($etab);
        $sortie  = [];

        foreach (self::GROUPES as $cle => $libelle) {
            if ($groupes[$cle] === []) {
                continue;
            }

            $sortie[] = ['cle' => $cle, 'libelle' => $libelle, 'pieces' => $groupes[$cle]];
        }

        return $sortie;
    }
}
