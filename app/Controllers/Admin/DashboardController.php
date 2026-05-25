<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Core\Session;
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
        // Cache croissance data in session — data changes at most monthly
        $cacheKey = 'adm_dash_croissance';
        $cacheTTL = 300; // 5 minutes

        $cached = Session::get($cacheKey);
        if ($cached && is_array($cached) && isset($cached['ts']) && (time() - $cached['ts']) < $cacheTTL) {
            $allCroissance = $cached['data'];
        } else {
            $allCroissance = [
                3  => $this->model->getCroissanceInscriptions(3),
                6  => $this->model->getCroissanceInscriptions(6),
                12 => $this->model->getCroissanceInscriptions(12),
            ];
            Session::set($cacheKey, ['ts' => time(), 'data' => $allCroissance]);
        }

        $kpi              = $this->model->getDashboardStats();
        $croissance       = $allCroissance[6];
        $parSerie         = $this->model->getRepartitionSeries();
        $dernieres        = $this->model->getDernieresInscriptions(5);
        $activiteRecente  = $this->model->getActiviteRecente(5);
        $activiteMatieres = $this->model->getActiviteParMatiere(5);

        Response::view('admin/dashboard/index', [
            'pageTitle'          => "Dashboard — Admin",
            'breadcrumbSection'  => 'Pilotage',
            'breadcrumbPage'     => 'Vue d\'ensemble',
            'kpi'                => $kpi,
            'croissance'         => $croissance,
            'allCroissance'      => $allCroissance,
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
