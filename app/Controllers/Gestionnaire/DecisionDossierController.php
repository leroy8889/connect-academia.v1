<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\Response;
use Core\Session;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\JournalGestionnaire;
use Services\Orientation\PieceDossier;

/**
 * Zone décision du back-office établissement (spec §8.2 à §8.4).
 *
 * Ce contrôleur est délibérément mince : il lit le formulaire, appelle
 * DecisionEtablissement, et redirige avec un message. Aucune règle métier ici
 * — sinon elle finirait dupliquée le jour où le même geste devra être fait
 * depuis l'écran de scan ou depuis la console d'administration.
 *
 * Toutes les routes sont en POST, protégées par CSRF et par le middleware
 * `gestionnaire`. L'appartenance du dossier à l'établissement est revérifiée
 * dans le service, jamais supposée depuis l'URL.
 */
class DecisionDossierController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)(Session::get('gestionnaire_etab_id') ?? 0);
    }

    /** POST /gestionnaire/candidatures/{id}/valider-50 */
    public function valider50(int $id): void
    {
        $resultat = DecisionEtablissement::valider50(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            $_POST['commentaire'] ?? null
        );

        $this->terminer($id, $resultat, 'candidature.valider50', 'Dossier #' . $id . ' validé à 50 %');
    }

    /** POST /gestionnaire/candidatures/{id}/demander-piece */
    public function demanderPiece(int $id): void
    {
        $resultat = DecisionEtablissement::demanderPiece(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            (string)($_POST['pieces_attendues'] ?? '')
        );

        $this->terminer($id, $resultat, 'candidature.piece_demandee', 'Pièce demandée sur le dossier #' . $id);
    }

    /** POST /gestionnaire/candidatures/{id}/rejeter */
    public function rejeter(int $id): void
    {
        $resultat = DecisionEtablissement::rejeter(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            (string)($_POST['motif'] ?? '')
        );

        $this->terminer($id, $resultat, 'candidature.rejet', 'Dossier #' . $id . ' rejeté');
    }

    /**
     * POST /gestionnaire/candidatures/{id}/valider-100
     *
     * Point le plus sensible du système : c'est le seul endroit où la
     * plateforme capte encore l'information financière, puisque l'argent ne
     * transite plus par elle (spec §8.4).
     */
    public function valider100(int $id): void
    {
        $resultat = DecisionEtablissement::valider100(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            [
                'montant_scolarite_annuelle' => $_POST['montant_scolarite_annuelle'] ?? null,
                'montant_encaisse_initial'   => $_POST['montant_encaisse_initial']   ?? null,
                // Frais d'inscription : nature distincte de la scolarité, donc
                // champ distinct. Ils ne sont ni déduits du reste à régler, ni
                // inclus dans la base de calcul de la commission.
                'montant_frais_inscription'  => $_POST['montant_frais_inscription']  ?? null,
                'mode_paiement'              => (string)($_POST['mode_paiement']     ?? ''),
                'reference_paiement'         => $_POST['reference_paiement']         ?? null,
                'date_paiement'              => (string)($_POST['date_paiement']     ?? ''),
            ],
            $_POST['commentaire'] ?? null
        );

        // Les erreurs de saisie repartent avec le formulaire pour que l'agent
        // ne retape pas tout — au guichet, un candidat attend devant lui.
        if (!$resultat['ok'] && ($resultat['erreurs'] ?? []) !== []) {
            Session::flash('erreurs_100', $resultat['erreurs']);
            Session::flash('old_100', [
                'montant_scolarite_annuelle' => $_POST['montant_scolarite_annuelle'] ?? '',
                'montant_encaisse_initial'   => $_POST['montant_encaisse_initial']   ?? '',
                'montant_frais_inscription'  => $_POST['montant_frais_inscription']  ?? '',
                'mode_paiement'              => $_POST['mode_paiement']              ?? '',
                'reference_paiement'         => $_POST['reference_paiement']         ?? '',
                'date_paiement'              => $_POST['date_paiement']              ?? '',
            ]);
        }

        $this->terminer($id, $resultat, 'candidature.valider100', 'Inscription #' . $id . ' validée à 100 %');
    }

    /** POST /gestionnaire/candidatures/{id}/prolonger */
    public function prolonger(int $id): void
    {
        $resultat = DecisionEtablissement::prolonger(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            (int)($_POST['jours'] ?? 0)
        );

        $this->terminer($id, $resultat, 'candidature.prolongation', 'Délai prolongé sur le dossier #' . $id);
    }

    /**
     * POST /gestionnaire/candidatures/{id}/annuler-decision
     *
     * Retour arrière sur la dernière décision (spec §8.2, droit à l'erreur).
     * L'écran ne dit jamais QUELLE décision annuler : c'est la machine à états
     * qui le détermine, à partir de l'état réel du dossier au moment du clic.
     * Deux agents qui cliquent en même temps ne peuvent donc pas défaire deux
     * décisions différentes.
     */
    public function annuler(int $id): void
    {
        $resultat = DecisionEtablissement::annuler(
            $id,
            $this->etabId,
            Session::gestionnaireId(),
            $_POST['motif_annulation'] ?? null
        );

        $this->terminer($id, $resultat, 'candidature.annulation', 'Décision annulée sur le dossier #' . $id);
    }

    /** POST /gestionnaire/candidatures/{id}/relancer */
    public function relancer(int $id): void
    {
        $resultat = DecisionEtablissement::relancer($id, $this->etabId, Session::gestionnaireId());

        $this->terminer($id, $resultat, 'candidature.relance', 'Candidat relancé sur le dossier #' . $id);
    }

    /**
     * POST /gestionnaire/candidatures/{id}/piece/{documentId}
     * Accepter ou refuser une pièce, individuellement (spec §8.2, zone pièces).
     */
    public function deciderPiece(int $id, int $documentId): void
    {
        // Le dossier doit appartenir à l'établissement AVANT toute écriture :
        // la clause de cloisonnement est reprise dans PieceDossier::decider,
        // mais on refuse d'entrer si l'agent n'a rien à faire ici.
        $dossier = $this->dossierDeLEtablissement($id);

        if ($dossier === null) {
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $decision = (string)($_POST['decision'] ?? '');
        $motif    = $_POST['motif'] ?? null;

        $ok = PieceDossier::decider($documentId, $id, $decision, $motif, Session::gestionnaireId());

        if ($ok) {
            Session::flash('success', $decision === 'accepte'
                ? 'Pièce acceptée.'
                : 'Pièce refusée. Le candidat est prévenu et peut la remplacer depuis son espace.');
            JournalGestionnaire::log(
                'candidature.piece',
                'dossier',
                $id,
                'Pièce #' . $documentId . ' ' . ($decision === 'accepte' ? 'acceptée' : 'refusée')
            );

            // Un refus SANS notification est un cul-de-sac : le candidat ne sait
            // pas quoi corriger, et son espace ne le lui dit pas. Le message
            // nomme la pièce, donne le motif, et pointe l'endroit où déposer la
            // nouvelle version.
            if ($decision === 'refuse') {
                $this->notifierRefusPiece($dossier, $documentId, (string)$motif);
            }
        } else {
            Session::flash('error', $decision === 'refuse'
                ? 'Le motif du refus est obligatoire.'
                : 'La pièce n’a pas pu être mise à jour.');
        }

        Response::redirect('/gestionnaire/candidatures/' . $id . '#pieces');
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Message de retour + trace back-office, puis retour à la fiche.
     *
     * @param array{ok:bool, message:string} $resultat
     */
    private function terminer(int $id, array $resultat, string $action, string $trace): void
    {
        if ($resultat['ok']) {
            Session::flash('success', $resultat['message']);
            JournalGestionnaire::log($action, 'dossier', $id, $trace);
        } else {
            Session::flash('error', $resultat['message']);
        }

        Response::redirect('/gestionnaire/candidatures/' . $id);
    }

    /**
     * Charge le dossier en vérifiant qu'il appartient bien à l'établissement.
     *
     * @return array<string,mixed>|null
     */
    private function dossierDeLEtablissement(int $id): ?array
    {
        $dossier = (new \Models\Orientation\Dossier())->findById($id);

        if ($dossier === null || (int)$dossier['etablissement_id'] !== $this->etabId) {
            if ($dossier !== null) {
                \Core\TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.piece', $id);
            }

            return null;
        }

        return $dossier;
    }

    /**
     * Prévient le candidat qu'une pièce est refusée, et lui dit quoi faire.
     *
     * Le libellé de la pièce est relu en base plutôt que repris du formulaire :
     * c'est le nom réel du document dans le dossier, pas celui qu'un POST
     * pourrait annoncer.
     */
    private function notifierRefusPiece(array $dossier, int $documentId, string $motif): void
    {
        if (empty($dossier['eleve_id'])) {
            return; // Dossier déposé au guichet, sans compte rattaché.
        }

        $libelle = 'Une pièce de votre dossier';

        try {
            $st = \Core\Database::getInstance()->getConnection()->prepare(
                'SELECT libelle FROM documents_orientation WHERE id = ? AND dossier_id = ? LIMIT 1'
            );
            $st->execute([$documentId, (int)$dossier['id']]);
            $trouve = $st->fetchColumn();

            if ($trouve !== false && trim((string)$trouve) !== '') {
                $libelle = '« ' . trim((string)$trouve) . ' »';
            }
        } catch (\Throwable $e) {
            error_log('[DecisionDossier] Libellé de pièce illisible : ' . $e->getMessage());
        }

        $motif = trim($motif);

        (new \Models\Orientation\NotificationOrientation())->create([
            'user_id'          => (int)$dossier['eleve_id'],
            'etablissement_id' => (int)$dossier['etablissement_id'],
            'type'             => 'piece_refusee',
            'titre'            => 'Une pièce de votre dossier n’est pas conforme',
            'message'          => $libelle . ' a été refusée par l’établissement.'
                . ($motif !== '' ? ' Motif : ' . $motif . '.' : '')
                . ' Rendez-vous dans le détail de votre dossier, rubrique « Pièces justificatives », '
                . 'pour téléverser une nouvelle version. Votre dossier repartira ensuite en analyse.',
        ]);
    }
}
