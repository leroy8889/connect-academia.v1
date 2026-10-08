<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session};
use Models\Orientation\{Etablissement, CommissionScolarite};

/**
 * Page dédiée aux commissions Connect'Academia (% scolarité recouvré en
 * présentiel, en tranches — cf. CommissionDossier::REPARTITION_TRANCHES).
 * Auparavant affichée sur la vue d'ensemble,
 * elle dispose désormais de sa propre page pour ne pas surcharger le dashboard.
 */
class CommissionGestionnaireController
{
    public function index(): void
    {
        $etabId = (int)Session::get('gestionnaire_etab_id');
        $etab   = (new Etablissement())->findById($etabId);

        if (!$etab) {
            Session::remove('gestionnaire_etab_id');
            Session::remove('gestionnaire_role');
            Response::redirect('/gestionnaire/login');
            return;
        }

        try {
            $commissions = (new CommissionScolarite())->findByEtabWithTranches($etabId);
        } catch (\Throwable $e) {
            error_log('[Gestionnaire] commissions: ' . $e->getMessage());
            $commissions = [];
        }

        Response::view('gestionnaire/commissions/index', [
            'pageTitle'   => 'Commissions Connect\'Academia — ' . $etab['nom'],
            'etab'        => $etab,
            'commissions' => $commissions,
            'flash'       => Session::getFlash('success'),
            'flashError'  => Session::getFlash('error'),
        ], 'gestionnaire');
    }
}
