<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session};
use Models\Progression;

class ProgressionController
{
    public function index(): void
    {
        $userId = Session::userId();
        $progressionModel = new Progression();

        Response::view('apprentissage/progression', [
            'pageTitle'           => "Ma Progression — Connect'Academia",
            'extraCss'            => ['apprentissage.css'],
            'extraJs'             => ['apprentissage.js'],
            'progression_globale' => $progressionModel->getGlobale($userId),
            'temps_semaine'       => $progressionModel->getTempsHebdo($userId),
            'progression_matieres' => $progressionModel->getParMatiere($userId),
        ]);
    }

    public function update(): void
    {
        $userId = Session::userId();
        $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $ressourceId  = (int) ($input['ressource_id'] ?? 0);
        $pourcentage  = (int) ($input['pourcentage'] ?? 0);
        $dernierePage = (int) ($input['derniere_page'] ?? 1);
        $action       = $input['action'] ?? 'update';
        $duree        = (int) ($input['duree_secondes'] ?? 0);

        if ($ressourceId < 1) {
            Response::json(['success' => false, 'error' => ['message' => 'ID ressource invalide']], 400);
        }

        $progressionModel = new Progression();

        if ($action === 'complete') {
            $progressionModel->marquerTermine($userId, $ressourceId);
        } else {
            $progressionModel->upsert($userId, $ressourceId, [
                'pourcentage'  => $pourcentage,
                'derniere_page' => $dernierePage,
            ]);
        }

        if ($duree > 0) {
            $progressionModel->logSession($userId, $ressourceId, $duree);
        }

        Response::json(['success' => true, 'message' => 'Progression mise à jour']);
    }
}
