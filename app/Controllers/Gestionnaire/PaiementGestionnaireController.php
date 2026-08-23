<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session, TenantGuard};
use Models\Orientation\{Dossier, Etablissement, Reversement};
use Services\Orientation\DocumentStorage;

/**
 * Paiements d'inscription reçus par l'établissement (espace gestionnaire).
 *
 * Liste chaque paiement en ligne avec son reçu PDF (facture candidat), le
 * reversement mobile money associé (montant net, commission plateforme,
 * statut) et les totaux encaissés.
 */
class PaiementGestionnaireController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $dossierModel = new Dossier();
        $etab         = (new Etablissement())->findById($this->etabId);
        $compte       = (new Reversement())->getCompteEtab($this->etabId);

        Response::view('gestionnaire/paiements/index', [
            'pageTitle' => 'Paiements — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'paiements' => $dossierModel->findPaiementsByEtab($this->etabId),
            'stats'     => $dossierModel->statsPaiementsEtab($this->etabId),
            'compte'    => $compte,
        ], 'gestionnaire');
    }

    /** Redirige vers une URL signée B2 du reçu PDF (lien court-vécu). */
    public function recu(int $paiementId): void
    {
        $dossierModel = new Dossier();
        $paiement     = $dossierModel->findPaiementById($paiementId);

        // Cloisonnement : le paiement doit appartenir à l'établissement du gestionnaire
        if (!$paiement || (int)$paiement['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($paiement, $this->etabId, 'gestionnaire.paiement.recu', $paiementId);
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Introuvable'], 'main');
            return;
        }

        $recu = $dossierModel->getRecu($paiementId);
        if (!$recu || empty($recu['cle_pdf'])) {
            Session::flash('error', 'Aucun reçu disponible pour ce paiement.');
            Response::redirect('/gestionnaire/paiements');
            return;
        }

        try {
            $url = (new DocumentStorage())->signedUrl((string)$recu['cle_pdf'], 10);
        } catch (\Throwable $e) {
            error_log('[GestionnairePaiements] signedUrl échoué : ' . $e->getMessage());
            Session::flash('error', 'Reçu momentanément indisponible. Réessayez.');
            Response::redirect('/gestionnaire/paiements');
            return;
        }

        header('Location: ' . $url);
        exit;
    }
}
