<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use Core\TarifFormationSchema;
use PDO;
use Services\Orientation\Niveau;
use Services\Orientation\TarifCycle;

/**
 * Grille tarifaire d'une formation : scolarité par cycle, par niveau et selon
 * la situation de bourse (table `tarifs_formation`).
 *
 * ── La forme de la grille ────────────────────────────────────────────────────
 *
 * Une ligne = (formation, cycle, niveau). Le niveau VIDE signifie « tout le
 * cycle ». Chaque ligne porte deux montants : non boursier et boursier.
 *
 * Cette forme unique absorbe les deux pratiques du terrain sans que
 * l'établissement ait à choisir un « mode » :
 *
 *   · tarif unique par cycle → il ne remplit que la ligne de cycle ;
 *   · tarif par année        → il remplit L1, L2, L3, et laisse la ligne de
 *                              cycle servir de repli (typiquement pour les
 *                              boursiers, dont toutes les années valent le
 *                              même prix).
 *
 * Un montant à 0 n'est pas « gratuit », c'est « non renseigné » : la
 * résolution descend alors d'un cran. C'est la convention déjà en vigueur sur
 * toutes les colonnes de tarif de la plateforme, on ne l'inverse pas ici.
 *
 * ── Cloisonnement ────────────────────────────────────────────────────────────
 *
 * Toutes les écritures passent par un JOIN sur `filieres_etablissement` borné
 * à l'établissement appelant. Un `filiere_id` forgé venant d'un autre
 * établissement ne produit aucune ligne — pas une erreur, pas un effet.
 */
class TarifFormation
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Grille complète d'une formation, indexée pour la vue.
     *
     * @return array<string,array<string,array{scolarite_non_boursier:int,scolarite_boursier:int}>>
     *         ['licence' => ['' => [...], 'L1' => [...], …], 'master' => [...]]
     */
    public function grille(int $filiereId): array
    {
        $grille = [
            TarifCycle::LICENCE => [],
            TarifCycle::MASTER  => [],
        ];

        if ($filiereId <= 0 || !TarifFormationSchema::grilleDisponible($this->db)) {
            return $grille;
        }

        try {
            $st = $this->db->prepare(
                'SELECT cycle, niveau, scolarite_non_boursier, scolarite_boursier
                   FROM tarifs_formation WHERE filiere_id = ?'
            );
            $st->execute([$filiereId]);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $cycle = (string)$ligne['cycle'];
                if (!isset($grille[$cycle])) {
                    continue;
                }
                $grille[$cycle][(string)$ligne['niveau']] = [
                    'scolarite_non_boursier' => (int)$ligne['scolarite_non_boursier'],
                    'scolarite_boursier'     => (int)$ligne['scolarite_boursier'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('[TarifFormation] Grille illisible #' . $filiereId . ' : ' . $e->getMessage());
        }

        return $grille;
    }

    /**
     * Grilles de plusieurs formations en une requête — écran de liste.
     *
     * @param list<int> $filiereIds
     * @return array<int,array<string,array<string,array{scolarite_non_boursier:int,scolarite_boursier:int}>>>
     */
    public function grillesPour(array $filiereIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $filiereIds), static fn(int $i): bool => $i > 0)));

        $vide    = [TarifCycle::LICENCE => [], TarifCycle::MASTER => []];
        $grilles = array_fill_keys($ids, $vide);

        if ($ids === [] || !TarifFormationSchema::grilleDisponible($this->db)) {
            return $grilles;
        }

        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->db->prepare(
                "SELECT filiere_id, cycle, niveau, scolarite_non_boursier, scolarite_boursier
                   FROM tarifs_formation WHERE filiere_id IN ({$in})"
            );
            $st->execute($ids);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $fid   = (int)$ligne['filiere_id'];
                $cycle = (string)$ligne['cycle'];
                if (!isset($grilles[$fid][$cycle])) {
                    continue;
                }
                $grilles[$fid][$cycle][(string)$ligne['niveau']] = [
                    'scolarite_non_boursier' => (int)$ligne['scolarite_non_boursier'],
                    'scolarite_boursier'     => (int)$ligne['scolarite_boursier'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('[TarifFormation] Grilles illisibles : ' . $e->getMessage());
        }

        return $grilles;
    }

    /**
     * Remplace la grille d'une formation par celle qui est postée.
     *
     * Le formulaire poste la grille ENTIÈRE : une ligne vidée par
     * l'établissement doit disparaître, pas survivre parce qu'elle n'était plus
     * mentionnée. On travaille donc en UPSERT + suppression des lignes devenues
     * nulles, dans une transaction — une grille à moitié écrite tarifierait
     * faux entre deux requêtes.
     *
     * @param array<string,array<string,array{non_boursier?:mixed,boursier?:mixed}>> $grille
     *        ['licence' => ['' => ['non_boursier' => '990000', 'boursier' => '']], …]
     * @return bool false si la formation n'appartient pas à l'établissement.
     */
    public function remplacerGrille(int $filiereId, int $etabId, array $grille): bool
    {
        if ($filiereId <= 0 || $etabId <= 0 || !TarifFormationSchema::grilleDisponible($this->db)) {
            return false;
        }

        // Cloisonnement : la formation doit appartenir à l'établissement qui
        // demande l'écriture. Contrôlé ici, pas dans le contrôleur, pour que
        // toute porte d'entrée (super admin, gestionnaire) soit couverte.
        $st = $this->db->prepare(
            'SELECT 1 FROM filieres_etablissement WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$filiereId, $etabId]);
        if ($st->fetchColumn() === false) {
            return false;
        }

        $lignes = [];
        foreach ([TarifCycle::LICENCE, TarifCycle::MASTER] as $cycle) {
            foreach (self::niveauxDuCycle($cycle) as $niveau) {
                $saisie = $grille[$cycle][self::cleFormulaire($niveau)] ?? $grille[$cycle][$niveau] ?? [];

                $lignes[] = [
                    'cycle'        => $cycle,
                    'niveau'       => $niveau,
                    'non_boursier' => self::montant($saisie['non_boursier'] ?? null),
                    'boursier'     => self::montant($saisie['boursier'] ?? null),
                ];
            }
        }

        $externe = $this->db->inTransaction();
        if (!$externe) {
            $this->db->beginTransaction();
        }

        try {
            $upsert = $this->db->prepare(
                'INSERT INTO tarifs_formation
                   (filiere_id, cycle, niveau, scolarite_non_boursier, scolarite_boursier)
                 VALUES (:filiere, :cycle, :niveau, :nb, :b)
                 ON DUPLICATE KEY UPDATE
                   scolarite_non_boursier = VALUES(scolarite_non_boursier),
                   scolarite_boursier     = VALUES(scolarite_boursier)'
            );

            $purge = $this->db->prepare(
                'DELETE FROM tarifs_formation
                  WHERE filiere_id = :filiere AND cycle = :cycle AND niveau = :niveau'
            );

            foreach ($lignes as $l) {
                // Une ligne entièrement vide n'est pas stockée : elle
                // encombrerait la grille sans jamais être retenue par la
                // résolution, et masquerait ce qui est réellement configuré.
                if ($l['non_boursier'] === 0 && $l['boursier'] === 0) {
                    $purge->execute([
                        ':filiere' => $filiereId,
                        ':cycle'   => $l['cycle'],
                        ':niveau'  => $l['niveau'],
                    ]);
                    continue;
                }

                $upsert->execute([
                    ':filiere' => $filiereId,
                    ':cycle'   => $l['cycle'],
                    ':niveau'  => $l['niveau'],
                    ':nb'      => $l['non_boursier'],
                    ':b'       => $l['boursier'],
                ]);
            }

            if (!$externe) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[TarifFormation] Écriture de grille échouée #' . $filiereId . ' : ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Clé de FORMULAIRE de la ligne « tout le cycle ».
     *
     * En base, cette ligne porte un `niveau` vide. Impossible de reprendre la
     * chaîne vide dans le nom du champ HTML : `tarifs[licence][][non_boursier]`
     * ne signifie pas « clé vide » pour PHP, mais « ajoute à la fin du
     * tableau ». La ligne serait arrivée sous l'index 0 et n'aurait JAMAIS été
     * enregistrée — un tarif de cycle saisi puis silencieusement perdu.
     *
     * On transporte donc un sentinelle explicite, retraduit en chaîne vide à
     * l'entrée. Le préfixe `__` le distingue de tout code de niveau réel.
     */
    public const CLE_CYCLE = '__cycle';

    /** Clé de formulaire d'un niveau : son code, ou le sentinelle pour le cycle. */
    public static function cleFormulaire(string $niveau): string
    {
        return $niveau === '' ? self::CLE_CYCLE : $niveau;
    }

    /**
     * Niveaux saisissables d'un cycle, ligne « tout le cycle » en tête.
     *
     * @return list<string> ['', 'L1', 'L2', 'L3'] ou ['', 'M1', 'M2']
     */
    public static function niveauxDuCycle(string $cycle): array
    {
        $niveaux = [''];
        foreach (Niveau::codes() as $code) {
            if (Niveau::cycle($code) === $cycle) {
                $niveaux[] = $code;
            }
        }

        return $niveaux;
    }

    /** Libellé d'une case de la grille : « Tout le cycle » ou « Licence 2 ». */
    public static function libelleNiveau(string $niveau): string
    {
        return $niveau === '' ? 'Tout le cycle' : Niveau::libelle($niveau);
    }

    /**
     * Montant saisi → entier positif. Accepte « 1 200 000 », « 1.200.000 »,
     * « 1200000 ». Tout ce qui n'est pas exploitable vaut 0, c'est-à-dire
     * « non renseigné » — jamais une exception qui perdrait toute la grille.
     */
    private static function montant(mixed $brut): int
    {
        if ($brut === null || $brut === '') {
            return 0;
        }

        $propre = preg_replace('/[^0-9]/', '', (string)$brut) ?? '';
        if ($propre === '') {
            return 0;
        }

        // Borné comme les autres montants de la plateforme (INT signé MySQL).
        return min((int)$propre, 2_000_000_000);
    }
}
