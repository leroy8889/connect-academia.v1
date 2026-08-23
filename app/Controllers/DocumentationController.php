<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;

/**
 * Documentation publique — guide d'utilisation de la plateforme.
 * Deux parcours documentés : élève (compte complet, 3 modules) et
 * visiteur grand public (module Orientation uniquement).
 */
class DocumentationController
{
    public function index(): void
    {
        Response::view('documentation', [
            'pageTitle' => "Documentation — Connect'Academia",
        ], '');
    }
}
