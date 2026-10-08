<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\RateLimiter;
use Core\Response;
use Core\SecurityMonitor;
use Core\Session;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Services\Orientation\JetonDossier;
use Services\Orientation\Journal;
use Services\Orientation\ScanQr;
use Services\Orientation\SituationBourse;

/**
 * Vérification d'un dossier par scan du QR code ou saisie du code court
 * (spec §7).
 *
 * ── Différence avec VerificationController ─────────────────────────────────
 * L'ancien contrôleur vérifie l'authenticité d'un DOCUMENT (récépissé de
 * préinscription, attestation) : route publique, il montre ce qui est déjà
 * imprimé sur le papier.
 *
 * Celui-ci vérifie un DOSSIER, en direct, et sert à décider — c'est depuis
 * cette page qu'un agent valide une inscription à 100 %. Il affiche donc
 * l'identité et la photo du candidat, et exige une authentification.
 *
 * ── Ce que le contrôle regarde, dans l'ordre (spec §7.3) ───────────────────
 *   1. le jeton existe-t-il ?
 *   2. est-il actif, non révoqué ?
 *   3. l'agent connecté appartient-il à l'établissement du dossier ?
 *   4. quel est le statut réel du dossier ?
 *
 * ── Ce qui est journalisé ──────────────────────────────────────────────────
 * TOUS les scans, y compris les échecs (spec §7.3, point 5). Un jeton
 * inexistant est tracé avec son IP : c'est le signal d'une tentative
 * d'énumération, et c'est ce que la limitation de débit vient contenir.
 */
class VerificationDossierController
{
    /** Spec §7.5 : 30 tentatives par minute et par adresse IP. */
    private const LIMITE_PAR_MINUTE = 30;
    private const FENETRE_SECONDES  = 60;

    /** Au-delà, le super administrateur est alerté (spec §7.5). */
    private const ECHECS_AVANT_ALERTE = 20;

    private Dossier $dossierModel;

    public function __construct()
    {
        $this->dossierModel = new Dossier();
    }

    /**
     * GET /v/{token} — page de vérification atteinte depuis le QR code.
     */
    public function show(string $token): void
    {
        $this->verifier(trim($token), 'qr');
    }

    /**
     * POST /verification/code-court — saisie manuelle, solution de repli quand
     * le scan est impossible (document abîmé, téléphone sans caméra).
     */
    public function parCodeCourt(): void
    {
        $saisie = trim((string)($_POST['code_court'] ?? ''));

        if ($saisie === '') {
            Session::flash('scan_erreur', 'Saisissez le code à 8 caractères imprimé sous le QR code.');
            Response::redirect($this->urlRetour());
            return;
        }

        $this->verifier($saisie, 'code_court');
    }

    // ── Cœur du contrôle ─────────────────────────────────────────────────────

    private function verifier(string $valeur, string $canal): void
    {
        // ── Limitation de débit (spec §7.5) ──
        // Posée AVANT toute requête sur les jetons : sans elle, la route
        // devient un oracle qui répond « existe / n'existe pas » aussi vite
        // qu'on l'interroge.
        $cle = RateLimiter::ipKey('verif_dossier');

        if (RateLimiter::tooManyAttempts($cle, self::LIMITE_PAR_MINUTE)) {
            ScanQr::enregistrer([
                'token_tente' => $valeur,
                'resultat'    => ScanQr::INTROUVABLE,
                'acteur_role' => $this->roleActeur(),
                'scanne_par'  => $this->idActeur(),
            ]);

            $this->rendre('limite', [
                'attente' => RateLimiter::availableIn($cle),
            ], 429);
            return;
        }

        RateLimiter::hit($cle, self::FENETRE_SECONDES);

        // ── Authentification (spec §7.3, point 3) ──
        // Aucune donnée personnelle n'est montrée à un visiteur anonyme. On
        // mémorise la destination pour y revenir après la connexion.
        if (!$this->estAgent()) {
            ScanQr::enregistrer([
                'token_tente' => $valeur,
                'resultat'    => ScanQr::NON_AUTHENTIFIE,
            ]);

            Session::set('redirect_apres_login', '/v/' . rawurlencode($valeur));
            Response::redirect('/gestionnaire/login');
            return;
        }

        $agentId = $this->idActeur();
        $etabId  = $this->etablissementActeur();

        // ── 1 & 2 : le jeton existe-t-il, est-il actif ? ──
        $jeton = JetonDossier::resoudre($valeur);

        if ($jeton === null) {
            $echecs = $this->compterEchec();

            ScanQr::enregistrer([
                'token_tente'      => $valeur,
                'resultat'         => ScanQr::INTROUVABLE,
                'scanne_par'       => $agentId,
                'acteur_role'      => $this->roleActeur(),
                'etablissement_id' => $etabId,
            ]);

            if ($echecs >= self::ECHECS_AVANT_ALERTE) {
                SecurityMonitor::accessViolation(
                    'Énumération probable de jetons de vérification',
                    ['echecs_consecutifs' => $echecs, 'canal' => $canal]
                );
            }

            $this->rendre('introuvable', []);
            return;
        }

        // Jeton reconnu : le compteur d'échecs consécutifs repart de zéro.
        RateLimiter::clear(RateLimiter::ipKey('verif_echec'));

        $dossier = $this->dossierModel->findById($jeton['dossier_id']);

        if ($dossier === null) {
            ScanQr::enregistrer([
                'jeton_id'    => $jeton['jeton_id'],
                'token_tente' => $valeur,
                'resultat'    => ScanQr::INTROUVABLE,
                'scanne_par'  => $agentId,
                'acteur_role' => $this->roleActeur(),
            ]);

            $this->rendre('introuvable', []);
            return;
        }

        if (!$jeton['actif']) {
            ScanQr::enregistrer([
                'jeton_id'         => $jeton['jeton_id'],
                'dossier_id'       => (int)$dossier['id'],
                'token_tente'      => $valeur,
                'resultat'         => ScanQr::JETON_REVOQUE,
                'scanne_par'       => $agentId,
                'acteur_role'      => $this->roleActeur(),
                'etablissement_id' => $etabId,
            ]);

            $this->rendre('revoque', ['dossier' => $dossier]);
            return;
        }

        // ── 3 : cloisonnement par établissement (spec §7.4) ──
        // Un agent d'un autre établissement ne voit RIEN du dossier — pas même
        // le nom du candidat. L'incident remonte au super administrateur.
        if (!$this->estSuperAdmin() && (int)$dossier['etablissement_id'] !== $etabId) {
            ScanQr::enregistrer([
                'jeton_id'         => $jeton['jeton_id'],
                'dossier_id'       => (int)$dossier['id'],
                'token_tente'      => $valeur,
                'resultat'         => ScanQr::ETABLISSEMENT_INCORRECT,
                'scanne_par'       => $agentId,
                'acteur_role'      => $this->roleActeur(),
                'etablissement_id' => $etabId,
            ]);

            SecurityMonitor::accessViolation(
                'Scan d’un dossier appartenant à un autre établissement',
                [
                    'agent_id'          => $agentId,
                    'etablissement_agent'   => $etabId,
                    'etablissement_dossier' => (int)$dossier['etablissement_id'],
                    'dossier_id'        => (int)$dossier['id'],
                ]
            );

            $this->rendre('etablissement_incorrect', []);
            return;
        }

        // ── 4 : statut réel, lu maintenant ──
        $statut = DossierStatut::normaliser((string)$dossier['statut']);

        ScanQr::enregistrer([
            'jeton_id'         => $jeton['jeton_id'],
            'dossier_id'       => (int)$dossier['id'],
            'token_tente'      => $valeur,
            'resultat'         => ScanQr::resultatPourStatut($statut),
            'scanne_par'       => $agentId,
            'acteur_role'      => $this->roleActeur(),
            'etablissement_id' => $etabId,
        ]);

        Journal::dossier(
            Journal::QR_SCANNE,
            $dossier,
            ['canal' => $canal, 'statut' => $statut, 'resultat' => ScanQr::resultatPourStatut($statut)],
            ['acteur_id' => $agentId, 'acteur_role' => $this->roleActeur()]
        );

        $this->rendre('dossier', [
            'dossier'       => $dossier,
            'statut'        => $statut,
            'documents'     => $this->dossierModel->getDocumentsByDossier((int)$dossier['id']),
            'bourse'        => SituationBourse::libelleCourt($dossier['statut_bourse'] ?? null),
            'peutValider100'=> $statut === DossierStatut::VALIDE_50,
        ]);
    }

    // ── Rendu ────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $donnees
     */
    private function rendre(string $cas, array $donnees, int $code = 200): void
    {
        http_response_code($code);
        header('X-Robots-Tag: noindex, nofollow');

        Response::view('orientation/verification-dossier', $donnees + [
            'cas'            => $cas,
            'bandeau'        => self::BANDEAUX[$cas] ?? self::BANDEAUX['introuvable'],
            'csrf'           => Session::getCsrfToken(),
            'pageTitle'      => 'Vérification de dossier',
            // Où revient l'agent : l'écran de scan pour un gestionnaire, le
            // journal des dossiers pour un administrateur — qui n'a pas accès
            // à /gestionnaire/*, protégé par GestionnaireMiddleware.
            'retour'         => $this->urlRetour(),
            'retourLibelle'  => Session::isGestionnaire()
                ? 'Scanner un autre dossier'
                : 'Retour au journal des dossiers',
            // La fiche détaillée vit dans l'espace gestionnaire : elle n'est
            // proposée qu'à celui qui peut réellement l'ouvrir.
            'peutOuvrirFiche' => Session::isGestionnaire(),
        ], '');
    }

    /**
     * Réponses possibles (spec §7.4). La couleur passe avant le texte :
     * un agent de guichet ne lit pas un paragraphe, il regarde une couleur.
     */
    private const BANDEAUX = [
        'introuvable' => [
            'ton'   => 'danger',
            'titre' => 'Document non reconnu',
            'texte' => 'Ce code ne correspond à aucun dossier. Vérifiez la saisie, ou refusez le document.',
        ],
        'revoque' => [
            'ton'   => 'danger',
            'titre' => 'Document annulé',
            'texte' => 'Ce récépissé a été annulé et remplacé. Demandez au candidat sa version à jour.',
        ],
        'etablissement_incorrect' => [
            'ton'   => 'danger',
            'titre' => 'Ce dossier ne concerne pas votre établissement',
            'texte' => 'Vous ne pouvez pas consulter ce dossier. L’incident a été signalé.',
        ],
        'limite' => [
            'ton'   => 'danger',
            'titre' => 'Trop de vérifications',
            'texte' => 'Patientez quelques instants avant de réessayer.',
        ],
        'dossier' => [
            'ton'   => 'neutre',
            'titre' => '',
            'texte' => '',
        ],
    ];

    /**
     * Destination du bouton « retour » de l'écran de résultat.
     *
     * /etablissement/scanner n'a jamais existé dans la table de routage : le
     * lien renvoyait sur une 404. L'écran de scan est /gestionnaire/scanner,
     * et il est fermé aux sessions purement administrateur.
     */
    private function urlRetour(): string
    {
        return Session::isGestionnaire() ? '/gestionnaire/scanner' : '/admin/dossiers';
    }

    // ── Identité de l'acteur ─────────────────────────────────────────────────

    private function estAgent(): bool
    {
        return Session::isGestionnaire() || Session::isAdmin();
    }

    private function estSuperAdmin(): bool
    {
        return Session::isAdmin() && Session::adminRole() === 'super_admin';
    }

    private function idActeur(): ?int
    {
        return Session::isGestionnaire() ? Session::gestionnaireId() : Session::adminId();
    }

    private function roleActeur(): string
    {
        if (Session::isGestionnaire()) {
            return 'gestionnaire';
        }
        if (Session::isAdmin()) {
            return Session::adminRole() ?? 'admin';
        }

        return 'anonyme';
    }

    private function etablissementActeur(): int
    {
        return (int)(Session::get('gestionnaire_etab_id') ?? 0);
    }

    /**
     * Incrémente et retourne le nombre d'échecs consécutifs pour cette IP.
     * Le compteur est remis à zéro dès qu'un jeton valide est présenté.
     */
    private function compterEchec(): int
    {
        return RateLimiter::hit(RateLimiter::ipKey('verif_echec'), 3600);
    }
}
