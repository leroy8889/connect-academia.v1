<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Texte;

/**
 * Cycle d'études d'une formation vitrine (`formations.niveau`).
 *
 * ── Pourquoi une déduction et pas une colonne ────────────────────────────────
 *
 * `formations.niveau` est un champ de saisie LIBRE du back-office : « Bac+3 »,
 * « Licence 3 », « M2 », « BTS », ou rien du tout. Contrairement à
 * `dossiers_inscription.niveau_entree` (liste fermée, voir {@see Niveau}), il
 * n'entre dans aucun calcul de prix : c'est une mention d'affichage. Le fermer
 * aurait obligé chaque établissement à ressaisir toutes ses formations pour un
 * gain nul côté métier.
 *
 * La page publique, elle, a besoin d'un regroupement : une école qui publie
 * vingt formations les déversait toutes d'un bloc. On déduit donc le cycle du
 * texte, avec un repli explicite — « Autres parcours » — pour tout ce que la
 * table ne reconnaît pas. Aucune formation n'est jamais perdue : un libellé
 * inconnu est rangé, pas masqué.
 *
 * ── Ordre des groupes ────────────────────────────────────────────────────────
 *
 * Celui du cursus : Licence, Master, Doctorat, puis le reste. Les groupes vides
 * ne sont pas rendus.
 */
final class CycleFormation
{
    public const LICENCE  = 'licence';
    public const MASTER   = 'master';
    public const DOCTORAT = 'doctorat';
    public const AUTRE    = 'autre';

    /** Clé → libellé affiché en tête de groupe. L'ordre est celui du cursus. */
    private const LIBELLES = [
        self::LICENCE  => 'Cycle Licence',
        self::MASTER   => 'Cycle Master',
        self::DOCTORAT => 'Cycle Doctorat',
        self::AUTRE    => 'Autres parcours',
    ];

    /**
     * Motifs de reconnaissance, testés dans CET ordre.
     *
     * Le doctorat passe avant le master, et le master avant la licence : un
     * intitulé comme « Master / Doctorat » doit tomber dans le plus élevé, et
     * « Licence puis Master » ne doit pas être capté par « licence » avant
     * d'avoir été confronté à « master ». Les motifs sont appliqués sur un
     * texte minuscule et désaccentué.
     */
    private const MOTIFS = [
        self::DOCTORAT => '/(?:\bdoctorat\b|\bdoctorate\b|\bphd\b|\bd\.?e\.?a\b|bac\s*\+\s*[78])/',
        self::MASTER   => '/(?:\bmaster[es]?\b|\bmastere\b|\bmsc\b|\bmba\b|\bm[12]\b|\bmagistere\b|bac\s*\+\s*[45])/',
        self::LICENCE  => '/(?:\blicence\b|\bbachelor\b|\bbts\b|\bdut\b|\bbut\b|\bdeug\b|\bdts\b|\bdeust\b|\bl[123]\b|bac\s*\+\s*[123]\b)/',
    ];

    /**
     * Cycle déduit d'un libellé de niveau libre.
     *
     * Retourne toujours une clé valide : {@see self::AUTRE} quand rien n'est
     * reconnu, y compris pour une chaîne vide.
     */
    public static function depuisNiveau(?string $niveau): string
    {
        $texte = mb_strtolower(Texte::sansAccents(trim((string)$niveau)), 'UTF-8');

        if ($texte === '') {
            return self::AUTRE;
        }

        foreach (self::MOTIFS as $cle => $motif) {
            if (preg_match($motif, $texte) === 1) {
                return $cle;
            }
        }

        return self::AUTRE;
    }

    /**
     * Cycle d'une formation vitrine complète.
     *
     * Le champ `niveau` prime. Quand il est vide ou illisible — c'est le cas
     * de beaucoup de catalogues, où le cycle n'est écrit que dans l'intitulé
     * (« Master Comptabilité Gestion », « Licence Pro Logistique ») — on
     * interroge l'intitulé plutôt que de ranger la formation dans « Autres
     * parcours » par défaut. L'intitulé n'est JAMAIS consulté quand le niveau
     * a parlé : une contradiction entre les deux se tranche en faveur du
     * champ prévu pour ça.
     *
     * @param array<string,mixed> $formation Ligne de la table `formations`.
     */
    public static function depuisFormation(array $formation): string
    {
        $cycle = self::depuisNiveau(isset($formation['niveau']) ? (string)$formation['niveau'] : null);

        if ($cycle !== self::AUTRE) {
            return $cycle;
        }

        return self::depuisNiveau(isset($formation['intitule']) ? (string)$formation['intitule'] : null);
    }

    /** Libellé d'un cycle, ou « Autres parcours » si la clé est inconnue. */
    public static function libelle(string $cle): string
    {
        return self::LIBELLES[$cle] ?? self::LIBELLES[self::AUTRE];
    }

    /**
     * Regroupe des formations par cycle, dans l'ordre du cursus.
     *
     * Chaque formation apparaît une fois et une seule. L'ordre d'affichage
     * choisi par l'établissement (`ordre`, puis `id`) est conservé À
     * L'INTÉRIEUR de chaque groupe : le regroupement ne réécrit pas son
     * classement, il le sectionne.
     *
     * @param list<array<string,mixed>> $formations Sortie de Etablissement::getFormations().
     * @return list<array{cle:string, libelle:string, formations:list<array<string,mixed>>}>
     */
    public static function grouper(array $formations): array
    {
        $paquets = [];

        foreach ($formations as $f) {
            $paquets[self::depuisFormation($f)][] = $f;
        }

        $sortie = [];

        foreach (array_keys(self::LIBELLES) as $cle) {
            if (empty($paquets[$cle])) {
                continue;
            }

            $sortie[] = [
                'cle'        => $cle,
                'libelle'    => self::LIBELLES[$cle],
                'formations' => $paquets[$cle],
            ];
        }

        return $sortie;
    }
}
