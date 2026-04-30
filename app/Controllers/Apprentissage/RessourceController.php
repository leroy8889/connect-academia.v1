<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session};
use Models\{Ressource, Matiere, Favori, Progression};

class RessourceController
{
    public function index(): void
    {
        $userId   = Session::userId();
        $serieId  = (int) Session::get('user_serie_id');
        $matiereId = (int) ($_GET['matiere'] ?? 0);
        $typeFilter = $_GET['type'] ?? 'tous';

        $matiereModel = new Matiere();

        if ($matiereId < 1) {
            Response::redirect('/apprentissage');
            return;
        }

        $matiere = $matiereModel->findActive($matiereId);
        if (!$matiere) {
            Response::redirect('/apprentissage');
            return;
        }

        $ressources = (new Ressource())->getByMatiere($matiereId, $userId, $typeFilter);

        Response::view('apprentissage/ressources', [
            'pageTitle'  => e($matiere['nom']) . " — Connect'Academia",
            'extraCss'   => ['apprentissage.css'],
            'extraJs'    => ['apprentissage.js'],
            'matiere'    => $matiere,
            'ressources' => $ressources,
            'type_filter' => $typeFilter,
        ]);
    }

    public function viewer(string $id): void
    {
        $ressourceId  = (int) $id;
        $userId       = Session::userId();

        $ressourceModel = new Ressource();
        $ressource = $ressourceModel->findWithProgression($ressourceId, $userId);

        if (!$ressource) {
            Response::redirect('/apprentissage');
            return;
        }

        $ressourceModel->incrementVues($ressourceId);
        $autres = $ressourceModel->getAutres($ressourceId, 5);
        $estFavori = (new Favori())->isFavori($userId, $ressourceId);

        Response::view('apprentissage/viewer', [
            'pageTitle'   => e($ressource['titre']) . " — Connect'Academia",
            'extraCss'    => ['apprentissage.css'],
            'extraJs'     => ['viewer.js', 'apprentissage.js'],
            'ressource'   => $ressource,
            'autres'      => $autres,
            'est_favori'  => $estFavori,
        ]);
    }

    // ── API ──────────────────────────────────────────────────────────────

    public function matieres(): void
    {
        $serieId = (int) Session::get('user_serie_id');
        $matieres = (new Matiere())->getBySerie($serieId, Session::userId());
        Response::json(['success' => true, 'data' => ['matieres' => $matieres]]);
    }

    public function liste(): void
    {
        $userId    = Session::userId();
        $matiereId = !empty($_GET['matiere']) ? (int) $_GET['matiere'] : null;
        $type      = $_GET['type'] ?? null;
        $limit     = min((int) ($_GET['limit'] ?? 20), 50);

        $ressources = (new Ressource())->getListeApi($userId, $matiereId, $type, $limit);
        Response::json(['success' => true, 'data' => ['ressources' => $ressources]]);
    }

    public function series(): void
    {
        $series = (new Matiere())->getSeries();
        Response::json(['success' => true, 'data' => ['series' => $series]]);
    }
}
