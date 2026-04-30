<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session};
use Models\Favori;

class FavorisController
{
    public function index(): void
    {
        $userId = Session::userId();

        Response::view('apprentissage/favoris', [
            'pageTitle' => "Mes Favoris — Connect'Academia",
            'extraCss'  => ['apprentissage.css'],
            'extraJs'   => ['apprentissage.js'],
            'favoris'   => (new Favori())->getByUser($userId),
        ]);
    }

    public function toggle(string $id): void
    {
        $userId      = Session::userId();
        $ressourceId = (int) $id;

        if ($ressourceId < 1) {
            Response::json(['success' => false, 'error' => ['message' => 'ID invalide']], 400);
        }

        $result = (new Favori())->toggle($userId, $ressourceId);
        Response::json(['success' => true, 'data' => $result]);
    }
}
