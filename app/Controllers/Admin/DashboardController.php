<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Models\Admin;

class DashboardController
{
    private Admin $model;

    public function __construct()
    {
        $this->model = new Admin();
    }

    public function index(): void
    {
        $kpi            = $this->model->getDashboardStats();
        $croissance     = $this->model->getCroissanceInscriptions();
        $parSerie       = $this->model->getRepartitionSeries();
        $dernieres      = $this->model->getDernieresInscriptions(5);
        $activiteRecente = $this->model->getActiviteRecente(5);
        $activiteMatieres = $this->model->getActiviteParMatiere(5);

        Response::view('admin/dashboard/index', [
            'pageTitle'          => "Dashboard — Admin",
            'breadcrumbSection'  => 'Pilotage',
            'breadcrumbPage'     => 'Vue d\'ensemble',
            'kpi'                => $kpi,
            'croissance'         => $croissance,
            'parSerie'           => $parSerie,
            'dernieres'          => $dernieres,
            'activiteRecente'    => $activiteRecente,
            'activiteMatieres'   => $activiteMatieres,
        ], 'admin');
    }

    public function stats(): void
    {
        Response::json([
            'success' => true,
            'data'    => $this->model->getDashboardStats(),
        ]);
    }
}
