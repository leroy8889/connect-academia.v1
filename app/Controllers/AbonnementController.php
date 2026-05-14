<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session};
use Models\{Abonnement, User};

class AbonnementController
{
    public function choisir(): void
    {
        $userId     = Session::userId();
        $abonnement = (new Abonnement())->getActif($userId);

        if ($abonnement) {
            Response::redirect('/abonnement/confirmation');
        }

        $user   = (new User())->findById($userId);
        $status = $_GET['status'] ?? '';

        Response::view('abonnement/choisir', [
            'pageTitle'  => "Choisir un abonnement — Connect'Academia",
            'user'       => $user,
            'abonnement' => null,
            'status'     => $status,
        ]);
    }

    public function confirmation(): void
    {
        $userId     = Session::userId();
        $abonnement = (new Abonnement())->getActif($userId);
        $user       = (new User())->findById($userId);

        Response::view('abonnement/confirmation', [
            'pageTitle'  => "Abonnement activé — Connect'Academia",
            'abonnement' => $abonnement,
            'user'       => $user,
        ]);
    }

    public function renouveler(): void
    {
        $userId  = Session::userId();
        $expired = (new Abonnement())->getExpire($userId);

        Response::view('abonnement/renouveler', [
            'pageTitle' => "Renouveler — Connect'Academia",
            'expired'   => $expired,
        ]);
    }
}
