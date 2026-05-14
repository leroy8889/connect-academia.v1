<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Database, Cache};
use Models\{Transaction, Abonnement};
use PDO;

class PaiementController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $filters = [
            'statut'     => $_GET['statut']     ?? '',
            'plan'       => $_GET['plan']        ?? '',
            'date_debut' => $_GET['date_debut']  ?? '',
            'date_fin'   => $_GET['date_fin']    ?? '',
            'q'          => trim($_GET['q']      ?? ''),
        ];

        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = 25;
        $offset     = ($page - 1) * $perPage;

        $txModel    = new Transaction();
        $total      = $txModel->countAll($filters);
        $totalPages = (int) ceil($total / $perPage) ?: 1;
        $transactions = $txModel->getAll($filters, $perPage, $offset);

        $counts     = $txModel->countByStatut();
        $caTotal    = $txModel->totalCA();
        $ca30j      = $txModel->totalCA('30j');
        $ca7j       = $txModel->totalCA('7j');

        $abonnementsActifs = (int) $this->db->query(
            "SELECT COUNT(*) FROM abonnements WHERE statut = 'actif' AND fin > NOW()"
        )->fetchColumn();

        $abonnementsExpiresM = (int) $this->db->query(
            "SELECT COUNT(*) FROM abonnements WHERE YEAR(fin) = YEAR(NOW()) AND MONTH(fin) = MONTH(NOW()) AND (statut = 'expire' OR fin <= NOW())"
        )->fetchColumn();

        Response::view('admin/paiement/index', [
            'pageTitle'           => 'Paiements — Admin',
            'breadcrumbSection'   => 'Finance',
            'breadcrumbPage'      => 'Paiements',
            'transactions'        => $transactions,
            'counts'              => $counts,
            'caTotal'             => $caTotal,
            'ca30j'               => $ca30j,
            'ca7j'                => $ca7j,
            'abonnementsActifs'   => $abonnementsActifs,
            'abonnementsExpiresM' => $abonnementsExpiresM,
            'filters'             => $filters,
            'page'                => $page,
            'totalPages'          => $totalPages,
            'total'               => $total,
        ], 'admin');
    }

    public function transactions(): void
    {
        $filters = [
            'statut' => $_GET['statut'] ?? '',
            'q'      => trim($_GET['q'] ?? ''),
        ];

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        $txModel = new Transaction();
        $rows    = $txModel->getAll($filters, $perPage, $offset);
        $total   = $txModel->countAll($filters);

        Response::json([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'page'    => $page,
        ]);
    }

    public function sync(): void
    {
        $txModel   = new Transaction();
        $pending   = $txModel->getAllEnAttente(120);
        $activated = 0;
        $skipped   = 0;

        foreach ($pending as $tx) {
            $mfToken = $tx['aggregateur_ref'] ?? null;
            if (!$mfToken) { $skipped++; continue; }

            $verified = $this->verifierPaiementMF($mfToken);
            if (!$verified) { $skipped++; continue; }

            $userId  = (int) $tx['user_id'];
            $txId    = (int) $tx['id'];
            $methode = $verified['operateur'] ?? $verified['payment_method'] ?? 'moneyfusion';

            $txModel->mettreAJour($txId, 'succes', $mfToken, json_encode($verified), $methode);
            (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);

            $redis = \Core\Redis::getInstance();
            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            Cache::forget("abonnement:{$userId}");

            error_log("[Admin Sync] Abonnement activé — user:{$userId} tx:{$txId}");
            $activated++;
        }

        Response::json([
            'success'   => true,
            'activated' => $activated,
            'skipped'   => $skipped,
            'message'   => "{$activated} abonnement(s) activé(s), {$skipped} ignoré(s).",
        ]);
    }

    private function verifierPaiementMF(string $mfPaymentToken): array|false
    {
        if (empty($mfPaymentToken)) return false;

        $url = 'https://pay.moneyfusion.net/api/v3/payments/status/' . rawurlencode($mfPaymentToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$raw) {
            error_log("[Admin Sync] cURL MF error: {$err} token:{$mfPaymentToken}");
            return false;
        }

        error_log("[Admin Sync] MF verify token:{$mfPaymentToken} — {$raw}");
        $resp = json_decode($raw, true);
        if (!$resp || empty($resp['data'])) return false;

        $status = strtolower($resp['data']['status'] ?? '');
        return $status === 'paid' ? $resp['data'] : false;
    }

    public function confirmer(int $txId): void
    {
        $txModel = new Transaction();
        $tx      = $txModel->findById($txId);

        if (!$tx) {
            Response::json(['success' => false, 'message' => 'Transaction introuvable'], 404);
        }

        if ($tx['statut'] !== 'en_attente') {
            Response::json(['success' => false, 'message' => 'Transaction déjà traitée (statut: ' . $tx['statut'] . ')'], 409);
        }

        $userId = (int) $tx['user_id'];
        $plan   = $tx['plan'] ?? 'mensuel';

        $txModel->mettreAJour($txId, 'succes', 'ADMIN_CONFIRM_MANUEL', null, 'admin');
        (new Abonnement())->creer($userId, $plan, 30);

        $redis = \Core\Redis::getInstance();
        if ($redis->isAvailable()) {
            $redis->del("payment_pending:{$userId}");
        }
        \Core\Cache::forget("abonnement:{$userId}");

        error_log("[Admin] Transaction {$txId} confirmée manuellement pour user {$userId}");

        Response::json(['success' => true, 'message' => 'Abonnement activé manuellement.']);
    }
}
