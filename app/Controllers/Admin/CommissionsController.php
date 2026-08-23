<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database, AdminPermissions};
use Models\Orientation\CommissionScolarite;
use PDO;

/**
 * Commissions scolarité — gestion depuis « Gestion des utilisateurs ».
 *
 * Le super admin crée une commission sur un dossier « inscrit » en saisissant
 * la scolarité ; le taux configuré sur l'établissement s'applique
 * automatiquement (10 % par défaut). Il coche ensuite chaque tranche
 * (40/30/30) encaissée en présentiel.
 */
class CommissionsController
{
    private PDO $db;
    private CommissionScolarite $model;

    public function __construct()
    {
        $this->db    = Database::getInstance()->getConnection();
        $this->model = new CommissionScolarite();
    }

    // ── GET /admin/api/utilisateurs/{id}/commissions ─────────────────────
    // Dossiers « inscrit » de l'utilisateur + commissions existantes (tranches incluses).
    public function forUser(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);

        $st = $this->db->prepare(
            "SELECT id, prenom, nom FROM users WHERE id = ? AND is_deleted = 0"
        );
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        Response::json([
            'success'     => true,
            'user'        => $user,
            'dossiers'    => $this->model->dossiersInscritsForUser($userId),
            'commissions' => $this->model->findByUserWithTranches($userId),
            'read_only'   => AdminPermissions::isUsersReadOnly(Session::adminRole() ?? ''),
        ]);
    }

    // ── POST /admin/api/commissions ──────────────────────────────────────
    // Body: dossier_id, montant_scolarite
    public function store(): void
    {
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $dossierId = (int) ($_POST['dossier_id'] ?? $this->jsonInput('dossier_id'));
        $montant   = (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['montant_scolarite'] ?? $this->jsonInput('montant_scolarite')));

        if (!$dossierId) {
            Response::json(['success' => false, 'message' => 'Dossier manquant.'], 422);
        }
        if ($montant <= 0 || $montant > 100000000) {
            Response::json(['success' => false, 'message' => 'Montant de scolarité invalide.'], 422);
        }

        try {
            $id = $this->model->createForDossier($dossierId, $montant, Session::adminId());
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Commissions] create: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erreur lors de la création de la commission.'], 500);
        }

        $taux = $this->db->prepare('SELECT taux_commission FROM commissions_scolarite WHERE id = ? LIMIT 1');
        $taux->execute([$id]);
        $tauxApplique = rtrim(rtrim(number_format((float) $taux->fetchColumn(), 2, ',', ' '), '0'), ',');

        Response::json([
            'success' => true,
            'id'      => $id,
            'message' => "Commission de {$tauxApplique} % créée — 3 tranches (40 % / 30 % / 30 %) à encaisser.",
        ]);
    }

    // ── PATCH /admin/api/commissions/tranches/{id} ───────────────────────
    // Body: paye (bool) — coche/décoche une tranche encaissée en présentiel.
    public function toggleTranche(array $params): void
    {
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $trancheId = (int) ($params['id'] ?? 0);
        $payeRaw   = $_POST['paye'] ?? $this->jsonInput('paye');
        $paye      = filter_var($payeRaw, FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $this->model->setTranchePayee($trancheId, $paye, Session::adminId());
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Commissions] toggleTranche: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erreur lors de la mise à jour de la tranche.'], 500);
        }

        Response::json([
            'success' => true,
            'message' => $paye
                ? "Tranche {$result['numero']} marquée comme encaissée."
                : "Tranche {$result['numero']} remise en attente.",
            'tranche' => $result,
        ]);
    }

    private function jsonInput(string $key): mixed
    {
        static $payload = null;
        if ($payload === null) {
            $payload = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        }
        return $payload[$key] ?? '';
    }
}
