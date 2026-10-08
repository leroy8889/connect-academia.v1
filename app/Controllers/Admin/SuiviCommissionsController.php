<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Database;
use Core\Response;
use Core\Session;
use Services\Orientation\CommissionDossier;
use PDO;

/**
 * Suivi des commissions de la plateforme (spec §10.3).
 *
 * Écran réservé au super administrateur : c'est la vue comptable transversale,
 * tous établissements confondus. Le back-office établissement, lui, ne voit que
 * ses propres commissions (Gestionnaire\CommissionGestionnaireController).
 *
 * Distinct de Admin\CommissionsController, qui gère les commissions saisies à
 * la main avec leurs tranches de reversement. Ici, on suit celles qui naissent
 * automatiquement d'une validation à 100 %.
 */
class SuiviCommissionsController
{
    /**
     * Défense en profondeur : le middleware 'admin' laisse passer les
     * modérateurs, qui n'ont rien à voir dans la comptabilité transversale
     * de la plateforme. Même barrière que /admin/securite.
     */
    public function __construct()
    {
        if (!in_array(Session::adminRole(), ['super_admin', 'admin'], true)) {
            Session::flash('error', 'Espace réservé au super administrateur.');
            Response::redirect('/admin');
        }
    }

    public function index(): void
    {
        $filtres = [
            'etablissement_id' => (int)($_GET['etablissement_id'] ?? 0) ?: null,
            'statut'           => trim((string)($_GET['statut'] ?? '')),
            'periode'          => trim((string)($_GET['periode'] ?? '')),
            'du'               => $this->date($_GET['du'] ?? null),
            'au'               => $this->date($_GET['au'] ?? null),
        ];
        $filtres = array_filter($filtres, static fn($v): bool => $v !== null && $v !== '');

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $parPage = 60;

        Response::view('admin/commissions/suivi', [
            'pageTitle'      => 'Suivi des commissions',
            'commissions'    => CommissionDossier::rechercher($filtres, $parPage, ($page - 1) * $parPage),
            'totaux'         => CommissionDossier::totaux($filtres),
            // Encaissé / restant dû : la question que pose vraiment cet écran.
            'tranches'       => CommissionDossier::totauxTranches($filtres),
            // L'abandon d'année ferme une créance : geste du super
            // administrateur seul, jamais d'un rôle « admin » délégué.
            'peutAbandon'    => Session::adminRole() === 'super_admin',
            'statuts'        => CommissionDossier::statuts(),
            'etablissements' => $this->etablissements(),
            'periodes'       => $this->periodes(),
            'filtres'        => $filtres,
            'page'           => $page,
            'csrf'           => Session::getCsrfToken(),
            'flash'          => Session::getFlash('success'),
            'flashError'     => Session::getFlash('error'),
        ], 'admin');
    }

    /**
     * POST /admin/commissions/{id}/statut — facturer, marquer réglée, annuler.
     */
    public function changerStatut(array $params): void
    {
        $id     = (int)($params['id'] ?? 0);
        $statut = (string)($_POST['statut'] ?? '');

        $ok = CommissionDossier::changerStatut(
            $id,
            $statut,
            Session::adminId(),
            $_POST['reference_reglement'] ?? null
        );

        if ($ok) {
            Session::flash('success', 'Commission mise à jour : ' . CommissionDossier::libelle($statut) . '.');
        } else {
            // Le refus le plus fréquent : une commission déjà réglée. On ne
            // réécrit pas une opération comptable close.
            Session::flash('error',
                'Mise à jour impossible : une commission réglée, annulée ou en abandon d’année '
                . 'ne change plus de statut.');
        }

        Response::redirect($this->retour());
    }

    /**
     * POST /admin/commissions/tranches/{id}
     *
     * Encaisse (ou remet en attente) une tranche de commission. C'est ICI que
     * l'argent est constaté : le super administrateur est le seul à le voir
     * arriver. L'établissement, lui, lit le résultat sur son propre tableau
     * de bord — il ne coche rien.
     */
    public function tranche(array $params): void
    {
        $trancheId = (int)($params['id'] ?? 0);
        $encaisser = ($_POST['action'] ?? '') !== 'annuler';

        $resultat = CommissionDossier::payerTranche(
            $trancheId,
            $encaisser,
            Session::adminId(),
            $_POST['reference_reglement'] ?? null
        );

        Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        Response::redirect($this->retour());
    }

    /**
     * POST /admin/commissions/{id}/abandon
     *
     * Constate l'abandon d'année d'un étudiant inscrit à 100 %.
     *
     * Réservé au SUPER ADMINISTRATEUR. L'établissement signale la situation par
     * ses propres moyens ; il ne la constate pas lui-même — laisser sa console
     * fermer ses tranches reviendrait à lui laisser annuler sa dette.
     */
    public function abandon(array $params): void
    {
        if (!$this->superAdmin()) {
            return;
        }

        $dossierId = $this->dossierDeCommission((int)($params['id'] ?? 0));

        if ($dossierId === null) {
            Session::flash('error', 'Commission introuvable.');
            Response::redirect($this->retour());
            return;
        }

        $resultat = CommissionDossier::declarerAbandon(
            $dossierId,
            Session::adminId(),
            (string)($_POST['motif'] ?? '')
        );

        Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        Response::redirect($this->retour());
    }

    /** POST /admin/commissions/{id}/abandon/lever — abandon déclaré par erreur. */
    public function leverAbandon(array $params): void
    {
        if (!$this->superAdmin()) {
            return;
        }

        $dossierId = $this->dossierDeCommission((int)($params['id'] ?? 0));

        if ($dossierId === null) {
            Session::flash('error', 'Commission introuvable.');
            Response::redirect($this->retour());
            return;
        }

        $resultat = CommissionDossier::leverAbandon($dossierId, Session::adminId());

        Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        Response::redirect($this->retour());
    }

    /**
     * Clôt le signalement de révision d'une commission.
     *
     * Ne touche à AUCUN montant. Le super administrateur déclare avoir
     * réexaminé la base après un changement de situation de bourse — qu'il ait
     * décidé de la corriger à la main ou de la laisser telle quelle. Les deux
     * sont des décisions ; seule l'absence de décision devait rester visible.
     *
     * Réservé au super administrateur, comme l'abandon : c'est un arbitrage
     * financier, pas une tâche de suivi.
     */
    public function cloreRevision(array $params): void
    {
        if (!$this->superAdmin()) {
            return;
        }

        $commissionId = (int)($params['id'] ?? 0);

        if (CommissionDossier::cloreRevision($commissionId, Session::adminId())) {
            Session::flash('success', 'Signalement levé. Les montants sont inchangés.');
        } else {
            Session::flash('error', 'Aucun signalement à lever sur cette commission.');
        }

        Response::redirect($this->retour());
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Garde du super administrateur, DOUBLÉE côté serveur : le bouton absent de
     * l'écran ne protège rien, la route reste appelable à la main.
     */
    private function superAdmin(): bool
    {
        if (Session::adminRole() === 'super_admin') {
            return true;
        }

        Session::flash('error', 'Seul un super administrateur peut statuer sur un abandon d’année.');
        Response::redirect($this->retour());

        return false;
    }

    /** Dossier porté par une commission, ou null. */
    private function dossierDeCommission(int $commissionId): ?int
    {
        if ($commissionId <= 0) {
            return null;
        }

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT dossier_id FROM commissions_scolarite WHERE id = ? LIMIT 1'
            );
            $st->execute([$commissionId]);
            $id = $st->fetchColumn();

            return $id === false ? null : (int)$id;
        } catch (\Throwable $e) {
            error_log('[SuiviCommissions] Dossier de commission illisible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Retour à la liste EN CONSERVANT les filtres.
     *
     * Sans cela, encaisser une tranche depuis « établissement X, période
     * 2026-09 » renvoie sur la liste complète : l'agent doit refiltrer à chaque
     * geste, et finit par en oublier.
     */
    private function retour(): string
    {
        $garde = ['etablissement_id', 'statut', 'periode', 'du', 'au', 'page'];
        $query = [];

        foreach ($garde as $cle) {
            $valeur = $_POST['retour_' . $cle] ?? null;
            if (is_string($valeur) && trim($valeur) !== '') {
                $query[$cle] = trim($valeur);
            }
        }

        return '/admin/commissions' . ($query === [] ? '' : '?' . http_build_query($query));
    }


    /** @return list<array{id:int,nom:string}> */
    private function etablissements(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                'SELECT DISTINCT e.id, e.nom
                 FROM etablissements e
                 JOIN commissions_scolarite c ON c.etablissement_id = e.id
                 ORDER BY e.nom'
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[SuiviCommissions] Établissements illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Périodes réellement présentes : on ne propose pas un mois vide.
     *
     * @return list<string>
     */
    private function periodes(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                'SELECT DISTINCT periode_facturation
                 FROM commissions_scolarite
                 WHERE periode_facturation IS NOT NULL
                 ORDER BY periode_facturation DESC
                 LIMIT 36'
            );

            return $st->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (\Throwable $e) {
            error_log('[SuiviCommissions] Périodes illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** Normalise une date de filtre, ou null si la saisie n'est pas une date. */
    private function date(mixed $valeur): ?string
    {
        if (!is_string($valeur) || trim($valeur) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($valeur));

        return $date !== false && $date->format('Y-m-d') === trim($valeur) ? $date->format('Y-m-d') : null;
    }
}
