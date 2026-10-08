<?php
declare(strict_types=1);

/**
 * Isolation de la tarification pour la suite de tests.
 *
 * ── Pourquoi ce fichier existe ─────────────────────────────────────────────
 * Les lots 3, 4, 7 et 8 travaillent sur le PREMIER établissement de la base de
 * développement (`ORDER BY id LIMIT 1`) et fixent ses tarifs par un UPDATE sur
 * la fiche. C'était suffisant quand la fiche était la seule source de vérité.
 *
 * Elle ne l'est plus. `TarifCycle` interroge désormais, DANS CET ORDRE :
 *
 *   1. `tarifs_formation`      — grille par filière / cycle / niveau
 *   2. `filieres_etablissement`— montants hérités portés par la filière
 *   3. `etablissements`        — la fiche, que les tests écrivent
 *
 * Dès qu'un développeur saisit une grille sur cet établissement depuis
 * l'interface, les points 1 et 2 prennent la main et les montants écrits par
 * les tests ne sont plus jamais lus : vingt et une assertions tombent, sans
 * qu'aucune ligne de code applicatif ne soit en cause. Le test criait au loup.
 *
 * `isolerTarifsEtablissement()` met les deux sources supérieures en sommeil le
 * temps du test et les restaure à l'extinction du processus — y compris sur
 * erreur fatale, `register_shutdown_function` étant appelé dans tous les cas.
 * La suite redevient hermétique : son verdict ne dépend plus de ce que
 * contient la base de développement.
 *
 * NB : aucune ligne n'est perdue. Les grilles sont réinsérées avec leur `id`
 * d'origine, pour que les clés étrangères qui les visent restent valides.
 */

/**
 * Neutralise grilles et montants de filière pour un établissement, le temps
 * du test. Idempotent : deux appels sur le même établissement n'en font qu'un.
 */
function isolerTarifsEtablissement(PDO $db, int $etabId): void
{
    /** @var array<int,true> $deja */
    static $deja = [];

    if ($etabId <= 0 || isset($deja[$etabId])) {
        return;
    }
    $deja[$etabId] = true;

    // ── 1. Fiche établissement : les quatre colonnes de frais d'inscription ──
    // Les tests remettent `prix_inscription` et `frais_dossier`, mais pas
    // celles-ci : sans mise à zéro, `resoudreFraisInscription()` s'arrête au
    // point 1 de son échelle et le tarif public n'est jamais atteint.
    $colonnesFrais = [
        'frais_inscription_licence',
        'frais_inscription_licence_boursier',
        'frais_inscription_master',
        'frais_inscription_master_boursier',
    ];
    $fraisOrigine = [];
    foreach ($colonnesFrais as $col) {
        try {
            $st = $db->prepare("SELECT `{$col}` FROM etablissements WHERE id = ?");
            $st->execute([$etabId]);
            $val = $st->fetchColumn();
            if ($val === false) {
                continue;
            }
            $fraisOrigine[$col] = $val;
            $db->prepare("UPDATE etablissements SET `{$col}` = 0 WHERE id = ?")->execute([$etabId]);
        } catch (Throwable) {
            // Colonne absente sur une base plus ancienne : rien à neutraliser.
        }
    }

    // ── 2. Filières : grille par niveau, puis montants hérités ──────────────
    $filiereIds   = [];
    $grilleOrigine = [];
    $filiereOrigine = [];

    try {
        $st = $db->prepare('SELECT id, frais_inscription, frais_scolarite
                              FROM filieres_etablissement WHERE etablissement_id = ?');
        $st->execute([$etabId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $filiereIds[]     = (int)$f['id'];
            $filiereOrigine[] = $f;
        }
    } catch (Throwable) {
        $filiereIds = [];
    }

    if ($filiereIds !== []) {
        $trous = implode(',', array_fill(0, count($filiereIds), '?'));

        try {
            $st = $db->prepare("SELECT * FROM tarifs_formation WHERE filiere_id IN ({$trous})");
            $st->execute($filiereIds);
            $grilleOrigine = $st->fetchAll(PDO::FETCH_ASSOC);

            $db->prepare("DELETE FROM tarifs_formation WHERE filiere_id IN ({$trous})")
               ->execute($filiereIds);
        } catch (Throwable) {
            $grilleOrigine = [];
        }

        try {
            $db->prepare("UPDATE filieres_etablissement
                             SET frais_inscription = 0, frais_scolarite = 0
                           WHERE id IN ({$trous})")->execute($filiereIds);
        } catch (Throwable) {
            $filiereOrigine = [];
        }
    }

    // ── 3. Remise en état, quoi qu'il arrive ────────────────────────────────
    register_shutdown_function(
        static function () use ($db, $etabId, $fraisOrigine, $grilleOrigine, $filiereOrigine): void {
            foreach ($fraisOrigine as $col => $val) {
                try {
                    $db->prepare("UPDATE etablissements SET `{$col}` = ? WHERE id = ?")
                       ->execute([$val, $etabId]);
                } catch (Throwable) {
                }
            }

            foreach ($filiereOrigine as $f) {
                try {
                    $db->prepare('UPDATE filieres_etablissement
                                     SET frais_inscription = ?, frais_scolarite = ?
                                   WHERE id = ?')
                       ->execute([$f['frais_inscription'], $f['frais_scolarite'], $f['id']]);
                } catch (Throwable) {
                }
            }

            foreach ($grilleOrigine as $ligne) {
                try {
                    $colonnes = array_keys($ligne);
                    $sql = 'INSERT INTO tarifs_formation (`' . implode('`,`', $colonnes) . '`) VALUES ('
                         . implode(',', array_fill(0, count($colonnes), '?')) . ')';
                    $db->prepare($sql)->execute(array_values($ligne));
                } catch (Throwable) {
                }
            }
        }
    );
}
