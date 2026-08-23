<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session};
use Models\Orientation\{Etablissement, NotificationOrientation};

/**
 * Notifications du portail gestionnaire.
 *
 * Les messages de l'administration Connect'Academia (module Orientation admin →
 * « Message ») sont poussés vers chaque gestionnaire lié à l'établissement :
 * une ligne notifications_orientation par user_id de gestionnaire. On les lit
 * donc simplement par user_id (= gestionnaire_id de session).
 */
class NotificationController
{
    public function index(): void
    {
        $userId = (int) Session::gestionnaireId();
        $etabId = (int) Session::get('gestionnaire_etab_id');
        $etab   = (new Etablissement())->findById($etabId);

        $model  = new NotificationOrientation();
        $notifs = $model->findByUser($userId, 60);

        Response::view('gestionnaire/notifications/index', [
            'pageTitle'  => 'Notifications',
            'etab'       => $etab,
            'notifs'     => $notifs,
            'flash'      => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    /** POST — marque toutes les notifications du gestionnaire comme lues. */
    public function markAllRead(): void
    {
        $userId = (int) Session::gestionnaireId();
        (new NotificationOrientation())->markAllRead($userId);
        Session::flash('success', 'Toutes les notifications sont marquées comme lues.');
        Response::redirect('/gestionnaire/notifications');
    }
}
