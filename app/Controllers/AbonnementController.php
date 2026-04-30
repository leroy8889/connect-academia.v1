<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session};
use Models\Abonnement;

class AbonnementController
{
    public function choisir(): void
    {
        $userId     = Session::userId();
        $abonnement = (new Abonnement())->getActif($userId);

        Response::view('abonnement/choisir', [
            'pageTitle'  => "Choisir un abonnement — Connect'Academia",
            'abonnement' => $abonnement,
        ]);
    }

    public function confirmation(): void
    {
        Response::view('abonnement/confirmation', [
            'pageTitle' => "Confirmation — Connect'Academia",
        ]);
    }

    public function renouveler(): void
    {
        Response::view('abonnement/renouveler', [
            'pageTitle' => "Renouveler — Connect'Academia",
        ]);
    }
}
