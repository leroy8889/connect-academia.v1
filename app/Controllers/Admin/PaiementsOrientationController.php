<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Models\Orientation\{Dossier, Etablissement};
use Services\Orientation\DocumentStorage;

/**
 * Vue globale super admin des paiements d'inscription (module Orientation) :
 * tous les paiements MoneyFusion de tous les établissements, avec commission
 * plateforme, statut du reversement mobile money et accès aux reçus PDF.
 */
class PaiementsOrientationController
{
    public function index(): void
    {
        $dossierModel = new Dossier();

        $etabId = (int)($_GET['etab'] ?? 0) ?: null;
        $statut = (string)($_GET['statut'] ?? '');
        if (!in_array($statut, ['initie', 'en_attente', 'reussi', 'echoue'], true)) {
            $statut = '';
        }

        Response::view('admin/orientation/paiements', [
            'pageTitle'  => 'Paiements établissements',
            'paiements'  => $dossierModel->findAllPaiements($etabId, $statut),
            'stats'      => $dossierModel->statsPaiementsGlobal(),
            'etabs'      => (new Etablissement())->findAll(),
            'etabFiltre' => $etabId,
            'statFiltre' => $statut,
        ], 'admin');
    }

    /** Redirige vers l'URL signée B2 du reçu PDF. */
    public function recu(int $paiementId): void
    {
        $dossierModel = new Dossier();
        $recu = $dossierModel->getRecu($paiementId);

        if (!$recu || empty($recu['cle_pdf'])) {
            Session::flash('error', 'Aucun reçu disponible pour ce paiement.');
            Response::redirect('/admin/orientation/paiements');
            return;
        }

        try {
            $url = (new DocumentStorage())->signedUrl((string)$recu['cle_pdf'], 10);
        } catch (\Throwable $e) {
            error_log('[AdminPaiements] signedUrl échoué : ' . $e->getMessage());
            Session::flash('error', 'Reçu momentanément indisponible. Réessayez.');
            Response::redirect('/admin/orientation/paiements');
            return;
        }

        header('Location: ' . $url);
        exit;
    }
}
