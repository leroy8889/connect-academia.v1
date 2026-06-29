<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Models\{Report, Post};

class SignalementsController
{
    private Report $report;

    public function __construct()
    {
        $this->report = new Report();
    }

    public function index(): void
    {
        $pending  = $this->report->getByStatus('pending',  50);
        $reviewed = $this->report->getByStatus('reviewed', 30);
        $rejected = $this->report->getByStatus('dismissed', 30);

        Response::view('admin/signalements/index', [
            'pageTitle'         => 'Signalements — Admin',
            'breadcrumbSection' => 'Communauté',
            'breadcrumbPage'    => 'Signalements',
            'pending'           => $pending,
            'reviewed'          => $reviewed,
            'rejected'          => $rejected,
        ], 'admin');
    }

    public function traiter(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = $_POST['action'] ?? $this->jsonInput('action');

        if (!in_array($action, ['reviewed', 'dismissed', 'delete_post'])) {
            Response::json(['success' => false, 'message' => 'Action invalide.'], 422);
        }

        if ($action === 'delete_post') {
            $report = $this->report->findById($id);
            if ($report && !empty($report['post_id'])) {
                (new Post())->delete((int) $report['post_id']);
            }
            $this->report->updateStatus($id, 'reviewed');
            Response::json([
                'success' => true,
                'message' => 'Post supprimé et signalement traité.',
                'status'  => 'reviewed',
            ]);
        }

        $this->report->updateStatus($id, $action);

        Response::json([
            'success' => true,
            'message' => $action === 'reviewed' ? 'Signalement examiné.' : 'Signalement rejeté (post conservé).',
            'status'  => $action,
        ]);
    }

    private function jsonInput(string $key): string
    {
        static $payload = null;
        if ($payload === null) {
            $payload = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        }
        return (string) ($payload[$key] ?? '');
    }
}
