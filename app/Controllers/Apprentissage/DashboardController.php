<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session};
use Models\{Progression, Matiere, Ressource};

class DashboardController
{
    public function index(): void
    {
        $userId  = Session::userId();
        $serieId = Session::get('user_serie_id');

        $progressionModel = new Progression();
        $stats = [
            'cours'       => $progressionModel->getCoursStats($userId, (int) $serieId),
            'temps'       => $progressionModel->getTempsHebdo($userId),
            'terminees'   => $progressionModel->getTerminees($userId),
            'matiere_fav' => $progressionModel->getMatiereFavorite($userId),
        ];

        $enCours  = $progressionModel->getEnCours($userId, 3);
        $recentes = (new Ressource())->getRecentes(6);

        $serie = null;
        if ($serieId) {
            $serie = (new Matiere())->findSerie((int) $serieId);
        }

        Response::view('apprentissage/dashboard', [
            'pageTitle'           => "Tableau de bord — Connect'Academia",
            'extraCss'            => ['apprentissage.css'],
            'extraJs'             => ['apprentissage.js'],
            'stats'               => $stats,
            'en_cours'            => $enCours,
            'serie'               => $serie,
            'ressources_recentes' => $recentes,
        ]);
    }

    public function matieres(): void
    {
        $userId  = Session::userId();
        // Priorité : ?serie= en GET, sinon session user
        $serieId = !empty($_GET['serie']) ? (int) $_GET['serie'] : (int) Session::get('user_serie_id');

        $matiereModel = new Matiere();
        $allSeries    = $matiereModel->getSeries();
        $matieres     = [];
        $serie        = null;

        if ($serieId > 0) {
            $matieres = $matiereModel->getBySerie($serieId, $userId);
            $serie    = $matiereModel->findSerie($serieId);
        }

        Response::view('apprentissage/matieres', [
            'pageTitle'  => "Mes matières — Connect'Academia",
            'extraCss'   => ['apprentissage.css'],
            'extraJs'    => ['apprentissage.js'],
            'matieres'   => $matieres,
            'serie'      => $serie,
            'all_series' => $allSeries,
        ]);
    }
}
