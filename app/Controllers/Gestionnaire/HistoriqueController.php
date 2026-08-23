<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session};
use Models\Orientation\Etablissement;
use Services\Orientation\JournalGestionnaire;

/**
 * Historique des actions des gestionnaires de l'établissement.
 * Lecture seule — les entrées sont écrites par JournalGestionnaire
 * depuis chaque contrôleur de l'espace gestionnaire.
 */
class HistoriqueController
{
    private const PAR_PAGE = 30;

    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab = (new Etablissement())->findById($this->etabId);

        $categorie = (string)($_GET['categorie'] ?? '');
        if (!isset(JournalGestionnaire::CATEGORIES[$categorie])) {
            $categorie = '';
        }

        $filters = ['categorie' => $categorie];
        $total   = JournalGestionnaire::countByEtab($this->etabId, $filters);
        $pages   = max(1, (int)ceil($total / self::PAR_PAGE));
        $page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);

        $entrees = JournalGestionnaire::listByEtab(
            $this->etabId,
            $filters,
            self::PAR_PAGE,
            ($page - 1) * self::PAR_PAGE
        );

        Response::view('gestionnaire/historique/index', [
            'pageTitle'  => 'Historique — ' . ($etab['nom'] ?? ''),
            'etab'       => $etab,
            'entrees'    => $entrees,
            'categories' => JournalGestionnaire::CATEGORIES,
            'categorie'  => $categorie,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
        ], 'gestionnaire');
    }
}
