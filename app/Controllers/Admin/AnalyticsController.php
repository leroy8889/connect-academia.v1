<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Models\Admin;

class AnalyticsController
{
    private Admin $model;

    public function __construct()
    {
        $this->model = new Admin();
    }

    public function index(): void
    {
        $heatmap       = $this->model->getHeatmapData();
        $topRessources = $this->model->getTopRessources(7);
        $funnel        = $this->model->getFunnelData();
        $repartition   = $this->getRepartitionRoles();

        Response::view('admin/analytics/index', [
            'pageTitle'         => 'Analytics — Admin',
            'breadcrumbSection' => 'Système',
            'breadcrumbPage'    => 'Analytics',
            'heatmap'           => $heatmap,
            'topRessources'     => $topRessources,
            'funnel'            => $funnel,
            'repartition'       => $repartition,
        ], 'admin');
    }

    private function getRepartitionRoles(): array
    {
        $counts = $this->model->getUserCountsByRole();
        return [
            'eleve'      => (int) ($counts['eleve'] ?? 0),
            'enseignant' => (int) ($counts['enseignant'] ?? 0),
            'admin'      => (int) ($counts['admin'] ?? 0),
        ];
    }
}
