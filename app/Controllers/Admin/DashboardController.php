<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Cache};
use Models\Admin;
use Models\Bilan;

class DashboardController
{
    private Admin $model;

    public function __construct()
    {
        $this->model = new Admin();
    }

    public function index(): void
    {
        // KPI globaux — cache Redis 5 min (partagé entre tous les admins connectés)
        $kpi = Cache::remember('admin:dashboard:stats', 300, fn() => $this->model->getDashboardStats());

        // Croissance inscriptions (3 / 6 / 12 mois) — cache 5 min
        $allCroissance = Cache::remember('admin:dashboard:croissance', 300, function () {
            return [
                3  => $this->model->getCroissanceInscriptions(3),
                6  => $this->model->getCroissanceInscriptions(6),
                12 => $this->model->getCroissanceInscriptions(12),
            ];
        });

        // Répartition séries — cache 10 min (stable)
        $parSerie = Cache::remember('admin:dashboard:series', 600, fn() => $this->model->getRepartitionSeries());

        // Activité par matière — cache 5 min
        $activiteMatieres = Cache::remember(
            'admin:dashboard:matieres',
            300,
            fn() => $this->model->getActiviteParMatiere(5)
        );

        // Dernières inscriptions + activité récente : toujours frais (données temps réel)
        $dernieres       = $this->model->getDernieresInscriptions(5);
        $activiteRecente = $this->model->getActiviteRecente(5);
        $croissance      = $allCroissance[6];

        // Bilans PDF : liste des établissements pour le sélecteur — cache 10 min
        $bilanEtabs = Cache::remember('admin:dashboard:bilan-etabs', 600, fn() => (new Bilan())->etablissementsSelect());

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
            'bilanEtabs'         => $bilanEtabs,
        ], 'admin');
    }

    public function stats(): void
    {
        Response::json([
            'success' => true,
            'data'    => Cache::remember(
                'admin:dashboard:stats',
                300,
                fn() => $this->model->getDashboardStats()
            ),
        ]);
    }
}
