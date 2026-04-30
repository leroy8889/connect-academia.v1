<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Models\Report;

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
        $rejected = $this->report->getByStatus('rejected', 30);

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

        if (!in_array($action, ['reviewed', 'rejected'])) {
            Response::json(['success' => false, 'message' => 'Action invalide.'], 422);
        }

        $this->report->updateStatus($id, $action);

        Response::json([
            'success' => true,
            'message' => $action === 'reviewed' ? 'Signalement examiné.' : 'Signalement rejeté.',
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
