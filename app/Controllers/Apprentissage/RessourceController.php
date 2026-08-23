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

    /**
     * Sert un PDF stocké hors du web root (repli `storage/uploads/ressources/`,
     * utilisé quand `public/uploads/` n'est pas accessible en écriture à PHP).
     *
     * Sécurité :
     *  · réservé aux sessions authentifiées (élève OU administrateur) — même
     *    barrière que la bibliothèque elle-même ;
     *  · le chemin vient EXCLUSIVEMENT de la base, jamais de la requête ;
     *    l'id est casté en entier et le chemin re-confiné par
     *    `RessourceStorage::absolutePath()` (realpath + racine autorisée) ;
     *  · réponse forcée en `application/pdf` + `nosniff` + `Content-Disposition:
     *    inline` avec un nom de fichier assaini : aucun rendu HTML possible.
     */
    public function fichier(string $id): void
    {
        if (!Session::userId() && !Session::adminId()) {
            Response::redirect('/auth/connexion');
            return;
        }

        $ressourceId = (int) $id;
        $ressource   = $ressourceId > 0 ? (new Ressource())->findById($ressourceId) : false;

        $abs = $ressource
            ? \Core\RessourceStorage::absolutePath((string) ($ressource['fichier_path'] ?? ''))
            : null;

        if ($abs === null) {
            http_response_code(404);
            echo 'Ressource introuvable.';
            return;
        }

        // Nom proposé au téléchargement : on ne garde que des caractères sûrs
        // pour ne pas pouvoir injecter dans l'en-tête Content-Disposition.
        $nom = (string) ($ressource['fichier_nom'] ?? 'ressource.pdf');
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nom) ?: 'ressource.pdf';
        if (!preg_match('/\.pdf$/i', $nom)) {
            $nom .= '.pdf';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string) filesize($abs));
        header('Content-Disposition: inline; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($abs);
        exit;
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
