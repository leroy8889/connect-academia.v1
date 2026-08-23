<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Response, Session, Hashid, TenantGuard};
use Models\Orientation\Dossier;
use Models\Orientation\Etablissement;
use Models\Orientation\NotificationOrientation;
use Models\User;
use Services\Orientation\{DocumentStorage, RecepisseService, AttestationService, PdfStream};

/**
 * Espace personnel Orientation — commun aux élèves de la plateforme
 * et aux comptes visiteur (parents, étudiants, candidats libres).
 *
 * App-shell à onglets (maquette compte/Compte.html) : vue d'ensemble,
 * profil, préinscriptions, inscriptions, paiements, reçus, documents,
 * notifications. Toutes les vues rendent dans le layout `espace-eleve`.
 */
class EspaceEleveOrientationController
{
    private Dossier $dossierModel;
    private NotificationOrientation $notifModel;
    private ?DocumentStorage $storage = null;
    private bool $storageFailed = false;

    public function __construct()
    {
        $this->dossierModel = new Dossier();
        $this->notifModel   = new NotificationOrientation();
    }

    // ── Contexte partagé (sidebar, compteurs, buckets) ────────────────────────

    /**
     * Charge l'utilisateur, ses dossiers et les compteurs de la sidebar.
     * Les brouillons (étape 1 d'inscription sauvegardée, pas encore soumis)
     * sont rangés avec les inscriptions : ce sont des dossiers d'inscription
     * à reprendre, jamais des préinscriptions (celles-ci sont soumises d'un bloc).
     */
    private function contexte(): array
    {
        $userId = (int)Session::userId();
        $user   = (new User())->findById($userId) ?? [];
        $email  = $user['email'] ?? '';

        $dossiers   = $this->dossierModel->findByUserContext($userId, $email);
        $documents  = $this->dossierModel->findDocumentsByUserContext($userId, $email);
        $nonLues    = $this->notifModel->countUnread($userId);

        $preins = [];
        $inscs  = [];
        foreach ($dossiers as $d) {
            if (!empty($d['inscription_id']) || ($d['statut'] ?? '') === 'brouillon') {
                $inscs[] = $d;
            } else {
                $preins[] = $d;
            }
        }

        // Dossier "actif" : le plus récent non refusé, inscription en priorité.
        $actif = null;
        foreach ($inscs as $d) {
            if (($d['statut'] ?? '') !== 'refuse') { $actif = $d; break; }
        }
        if (!$actif) {
            foreach ($preins as $d) {
                if (($d['statut'] ?? '') !== 'refuse') { $actif = $d; break; }
            }
        }
        if (!$actif && !empty($dossiers)) {
            $actif = $dossiers[0];
        }

        return [
            'userId'    => $userId,
            'user'      => $user,
            'email'     => $email,
            'dossiers'  => $dossiers,
            'preins'    => $preins,
            'inscs'     => $inscs,
            'documents' => $documents,
            'actif'     => $actif,
            'navCounts' => [
                'preinscriptions' => count($preins),
                'inscriptions'    => count($inscs),
                'documents'       => count($documents),
                'notifications'   => $nonLues,
                'dossier_actif'   => $actif !== null,
            ],
        ];
    }

    /** URL signée B2 silencieuse — null si stockage indisponible ou clé vide. */
    private function signedUrl(?string $cle): ?string
    {
        if (!$cle || $this->storageFailed) {
            return null;
        }
        try {
            $this->storage ??= new DocumentStorage();
            return $this->storage->signedUrl($cle, 10);
        } catch (\Throwable $e) {
            $this->storageFailed = true;
            error_log('[EspaceOrientation] B2 indisponible : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lien de consultation d'une pièce justificative.
     *
     * On ne distribue plus l'URL signée B2 : sa clé contient l'identifiant du
     * dossier (`dossiers/43/pieces/…`), visible dans la barre d'adresse, et le
     * lien reste valable hors session pendant sa durée de vie. Le fichier passe
     * désormais par l'application, derrière un jeton opaque et le contrôle de
     * propriété (voir document()).
     */
    private static function lienDocument(array $doc): ?string
    {
        $id = (int)($doc['id'] ?? 0);
        return $id > 0 ? url('/eleve/orientation/document/' . hid($id)) : null;
    }

    /**
     * Pièces justificatives d'un dossier d'inscription : requises par
     * l'établissement (niveau du dossier + communes) croisées avec les
     * documents déjà déposés. Retourne [['libelle','obligatoire','ok','doc'], …].
     */
    private function piecesPourDossier(array $dossier): array
    {
        if (empty($dossier['inscription_id']) && ($dossier['statut'] ?? '') !== 'brouillon') {
            return [];
        }

        $groupes = $this->dossierModel->getPiecesGroupedByNiveau((int)$dossier['etablissement_id']);
        $niveau  = $dossier['niveau_entree'] ?? '';
        $requises = array_merge($groupes['__tous'] ?? [], ($niveau !== '' ? ($groupes[$niveau] ?? []) : []));

        $deposes = $this->dossierModel->getDocumentsByDossier((int)$dossier['id']);
        $parPiece = [];
        $horsListe = [];
        foreach ($deposes as $doc) {
            if (!empty($doc['piece_requise_id'])) {
                $parPiece[(int)$doc['piece_requise_id']] = $doc;
            } else {
                $horsListe[] = $doc;
            }
        }

        $rows = [];
        foreach ($requises as $p) {
            $doc = $parPiece[(int)$p['id']] ?? null;
            $rows[] = [
                'libelle'     => $p['libelle'],
                'obligatoire' => (bool)$p['obligatoire'],
                'ok'          => $doc !== null,
                'doc'         => $doc,
            ];
        }
        // Pièces déposées hors référentiel (guichet, référentiel modifié depuis)
        foreach ($horsListe as $doc) {
            $rows[] = [
                'libelle'     => $doc['libelle'] ?: 'Document',
                'obligatoire' => false,
                'ok'          => true,
                'doc'         => $doc,
            ];
        }
        return $rows;
    }

    // ── Onglets ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $ctx = $this->contexte();

        // Pièces du dossier actif (aperçu "Pièces à finaliser")
        $pieces = $ctx['actif'] ? $this->piecesPourDossier($ctx['actif']) : [];

        // Fil d'activité dérivé des dossiers (toujours à jour, zéro dépendance)
        $feed = [];
        foreach ($ctx['dossiers'] as $d) {
            $etab = $d['etab_nom'] ?? '';
            $feed[] = [
                'date' => $d['date_creation'] ?? null,
                'icon' => !empty($d['inscription_id']) ? 'briefcase' : 'fileText',
                'tone' => '',
                'txt'  => (!empty($d['inscription_id']) ? 'Dossier d\'inscription créé pour <b>' : 'Dossier créé pour <b>') . e($etab) . '</b>.',
                'cat'  => !empty($d['inscription_id']) ? 'Inscription' : 'Dossier',
            ];
            if (!empty($d['numero_recepisse'])) {
                $feed[] = [
                    'date' => $d['recepisse_date'] ?? $d['date_creation'],
                    'icon' => 'fileText', 'tone' => 'p',
                    'txt'  => 'Votre <b>attestation de préinscription</b> (' . e($d['numero_recepisse']) . ') est disponible.',
                    'cat'  => 'Préinscription',
                ];
            }
            if (!empty($d['paiement_montant'])) {
                $feed[] = [
                    'date' => $d['paiement_date'] ?? $d['date_creation'],
                    'icon' => 'card', 'tone' => 'g',
                    'txt'  => 'Paiement de <b>' . number_format((int)$d['paiement_montant'], 0, ',', ' ') . ' XAF</b> confirmé — ' . e($etab) . '.',
                    'cat'  => 'Paiement',
                ];
            }
            if (!empty($d['numero_attestation']) && in_array($d['statut'] ?? '', ['inscrit','accepte'], true)) {
                $feed[] = [
                    'date' => $d['attestation_date'] ?? $d['date_creation'],
                    'icon' => 'checkCircle', 'tone' => 'g',
                    'txt'  => 'Inscription <b>validée</b> par ' . e($etab) . ' — attestation ' . e($d['numero_attestation']) . ' délivrée.',
                    'cat'  => 'Établissement',
                ];
            }
            if (($d['statut'] ?? '') === 'refuse') {
                $feed[] = [
                    'date' => $d['date_creation'],
                    'icon' => 'alert', 'tone' => 't',
                    'txt'  => 'Dossier <b>refusé</b> par ' . e($etab) . '.',
                    'cat'  => 'Établissement',
                ];
            }
        }
        usort($feed, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $feed = array_slice($feed, 0, 6);

        Response::view('orientation/espace_eleve/dashboard', [
            'pageTitle' => "Mon compte — Espace orientation",
            'activeTab' => 'overview',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'actif'     => $ctx['actif'],
            'preins'    => $ctx['preins'],
            'inscs'     => $ctx['inscs'],
            'pieces'    => $pieces,
            'feed'      => $feed,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function preinscriptions(): void
    {
        $ctx = $this->contexte();

        // Les vues pointent la route interne /dossier/{jeton}/recepisse : plus
        // aucune URL signée B2 côté front (la clé B2 contient l'ID du dossier).
        $rows = $ctx['preins'];

        Response::view('orientation/espace_eleve/preinscriptions', [
            'pageTitle' => "Mes préinscriptions — Espace orientation",
            'activeTab' => 'preinscriptions',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'dossiers'  => $rows,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function inscriptions(): void
    {
        $ctx = $this->contexte();

        $rows = [];
        foreach ($ctx['inscs'] as $d) {
            $pieces = $this->piecesPourDossier($d);
            $oblig  = array_filter($pieces, fn($p) => $p['obligatoire']);
            $d['pieces']          = $pieces;
            $d['pieces_ok']       = count(array_filter($pieces, fn($p) => $p['ok']));
            $d['pieces_total']    = count($pieces);
            $d['oblig_manquantes'] = count(array_filter($oblig, fn($p) => !$p['ok']));
            $rows[] = $d;
        }

        Response::view('orientation/espace_eleve/inscriptions', [
            'pageTitle' => "Mes inscriptions — Espace orientation",
            'activeTab' => 'inscriptions',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'dossiers'  => $rows,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function paiements(): void
    {
        $ctx = $this->contexte();
        $paiements = $this->dossierModel->findPaiementsByUserContext($ctx['userId'], $ctx['email']);

        Response::view('orientation/espace_eleve/paiements', [
            'pageTitle' => "Mes paiements — Espace orientation",
            'activeTab' => 'paiements',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'paiements' => $paiements,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function recus(): void
    {
        $ctx = $this->contexte();
        $paiements = $this->dossierModel->findPaiementsByUserContext($ctx['userId'], $ctx['email']);

        $recus = [];
        foreach ($paiements as $p) {
            if (!empty($p['numero_recu'])) {
                $p['recu_url'] = $this->signedUrl($p['recu_cle'] ?? null);
                $recus[] = $p;
            }
        }

        Response::view('orientation/espace_eleve/recus', [
            'pageTitle' => "Mes reçus — Espace orientation",
            'activeTab' => 'recus',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'recus'     => $recus,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function documents(): void
    {
        $ctx = $this->contexte();

        // Documents groupés par dossier + pièces manquantes des inscriptions
        $groupes = [];
        foreach ($ctx['dossiers'] as $d) {
            $pieces = $this->piecesPourDossier($d);
            $docs   = [];
            foreach ($pieces as $p) {
                if ($p['ok'] && $p['doc']) {
                    $p['doc']['url_doc'] = self::lienDocument($p['doc']);
                }
                $docs[] = $p;
            }
            // Préinscriptions pures : aucune pièce requise, on n'affiche pas de groupe vide
            if (empty($docs)) {
                continue;
            }
            $groupes[] = ['dossier' => $d, 'pieces' => $docs];
        }

        Response::view('orientation/espace_eleve/documents', [
            'pageTitle' => "Mes documents — Espace orientation",
            'activeTab' => 'documents',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'groupes'   => $groupes,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function notifications(): void
    {
        $ctx    = $this->contexte();
        $notifs = $this->notifModel->findByUser($ctx['userId'], 50);

        Response::view('orientation/espace_eleve/notifications', [
            'pageTitle' => "Mes notifications — Espace orientation",
            'activeTab' => 'notifications',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'notifs'    => $notifs,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function marquerNotificationsLues(): void
    {
        $this->notifModel->markAllRead((int)Session::userId());
        Session::flash('success', 'Toutes les notifications ont été marquées comme lues.');
        Response::redirect('/eleve/orientation/notifications');
    }

    // ── Détail d'un dossier ───────────────────────────────────────────────────

    public function dossier(string $hash): void
    {
        $id  = Hashid::decode($hash);
        $ctx = $this->contexte();

        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $pieces = $this->piecesPourDossier($dossier);
        foreach ($pieces as &$p) {
            if ($p['ok'] && $p['doc']) {
                $p['doc']['url_doc'] = self::lienDocument($p['doc']);
            }
        }
        unset($p);

        $recuUrl = $this->signedUrl($dossier['recu_cle'] ?? null);

        Response::view('orientation/espace_eleve/dossier', [
            'pageTitle'      => 'Dossier — ' . ($dossier['etab_nom'] ?? ''),
            'activeTab'      => !empty($dossier['inscription_id']) || ($dossier['statut'] ?? '') === 'brouillon'
                                ? 'inscriptions' : 'preinscriptions',
            'user'           => $ctx['user'],
            'navCounts'      => $ctx['navCounts'],
            'dossier'        => $dossier,
            'pieces'         => $pieces,
            'recuUrl'        => $recuUrl,
            'flash'          => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    /**
     * Attestation de préinscription — PDF généré à la demande.
     * Regénérée à la demande depuis la base : le téléchargement fonctionne
     * toujours, même si le fichier B2 est absent (upload échoué, ancien dossier).
     */
    public function recepisse(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;
        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $etab      = (new Etablissement())->findById((int)$dossier['etablissement_id']) ?? [];
        $recepisse = $this->dossierModel->getRecepisse($id);

        if ($recepisse && !empty($recepisse['numero_recepisse'])) {
            $numero = (string)$recepisse['numero_recepisse'];
            try { $dateEmission = new \DateTime((string)$recepisse['date_emission']); }
            catch (\Throwable) { $dateEmission = new \DateTime(); }
        } else {
            $numero       = $this->dossierModel->reserveRecepisse($id);
            $dateEmission = new \DateTime();
        }

        $token         = $this->dossierModel->tokenVerificationRecepisse($id);
        $svc           = new RecepisseService();
        $numeroFacture = 'FAC-' . substr($numero, 4);

        PdfStream::emettre(
            $svc->renderPdfComplet($dossier, $etab, $numero, $numeroFacture, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Attestation d'inscription — PDF généré à la demande.
     * Réservée aux inscriptions validées (statut « inscrit », ou « accepte » legacy).
     */
    public function attestation(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;
        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $etab        = (new Etablissement())->findById((int)$dossier['etablissement_id']) ?? [];
        $attestation = $this->dossierModel->getAttestation($id);

        if ($attestation && !empty($attestation['numero_attestation'])) {
            // Déjà délivrée : téléchargeable sans limite par le candidat, même
            // si le statut a évolué depuis (le document reste archivé).
            $numero = (string)$attestation['numero_attestation'];
            try { $dateEmission = new \DateTime((string)$attestation['date_emission']); }
            catch (\Throwable) { $dateEmission = new \DateTime(); }
        } else {
            // Première émission : réservée aux dossiers validés.
            if (!in_array($dossier['statut'] ?? '', ['inscrit','accepte'], true)) {
                Session::flash('error', "L'attestation d'inscription n'est disponible qu'après validation de votre dossier par l'établissement.");
                Response::redirect('/eleve/orientation/dossier/' . $hash);
                return;
            }
            $numero       = $this->dossierModel->reserveAttestation($id);
            $dateEmission = new \DateTime();
        }

        $token = $this->dossierModel->tokenVerificationAttestation($id);
        $svc   = new AttestationService();

        PdfStream::emettre(
            $svc->renderPdf($dossier, $etab, $numero, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Sert une pièce justificative déposée par le candidat.
     *
     * Le fichier transite par l'application au lieu d'une URL signée B2 :
     *   - l'URL ne contient plus qu'un jeton opaque (l'ID du dossier n'apparaît
     *     plus dans la barre d'adresse, et n'est pas énumérable) ;
     *   - la propriété du dossier est revérifiée à CHAQUE consultation, alors
     *     qu'un lien signé restait valable hors session jusqu'à expiration.
     *
     * Le type MIME servi est restreint : tout ce qui n'est pas un PDF ou une
     * image connue part en téléchargement `octet-stream`, jamais interprété par
     * le navigateur (un HTML ou un SVG rendu inline sur notre domaine serait un
     * XSS stocké).
     */
    public function document(string $hash): void
    {
        $docId = Hashid::decode($hash);
        $doc   = $docId !== null ? $this->dossierModel->findDocumentById($docId) : null;

        if (!$doc) {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        $ctx     = $this->contexte();
        $dossier = $this->dossierModel->findByIdWithOwnership(
            (int)$doc['dossier_id'], $ctx['userId'], $ctx['email']
        );

        if (!$dossier) {
            TenantGuard::denyOwnership('eleve.document', $docId);
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        $cle = (string)($doc['cle_b2'] ?? '');
        if ($cle === '') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        try {
            $contenu = (new DocumentStorage())->getContent($cle);
        } catch (\Throwable $e) {
            error_log('[EspaceOrientation] Lecture document #' . $docId . ' échouée : ' . $e->getMessage());
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document indisponible'], 'main');
            return;
        }

        // Types affichables sans risque ; le reste est forcé en téléchargement.
        $inlineOk = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime     = strtolower(trim((string)($doc['mime_type'] ?? '')));
        $inline   = in_array($mime, $inlineOk, true);

        $nom = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($doc['libelle'] ?? 'document'));
        $nom = ($nom === null || $nom === '') ? 'document' : substr($nom, 0, 80);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nom . '"');
        header('Content-Length: ' . strlen($contenu));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $contenu;
    }

    // ── Profil ────────────────────────────────────────────────────────────────

    public function profil(): void
    {
        $ctx = $this->contexte();

        // Identité issue du dernier dossier déposé (pré-remplit les démarches)
        $identite = null;
        foreach ($ctx['dossiers'] as $d) {
            if (($d['statut'] ?? '') !== 'brouillon') { $identite = $d; break; }
        }
        $identite ??= $ctx['dossiers'][0] ?? null;

        Response::view('orientation/espace_eleve/profil', [
            'pageTitle' => "Mon profil — Espace orientation",
            'activeTab' => 'profil',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'identite'  => $identite,
            'stats'     => $this->dossierModel->countByUserContext($ctx['userId'], $ctx['email']),
            'errors'    => Session::getFlash('errors', []),
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function updateProfil(): void
    {
        $userId    = (int)Session::userId();
        $userModel = new User();
        $user      = $userModel->findById($userId);

        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $email  = trim(strtolower($_POST['email'] ?? ''));

        $errors = [];
        if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 100) {
            $errors['prenom'] = 'Le prénom doit contenir entre 2 et 100 caractères.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
            $errors['nom'] = 'Le nom doit contenir entre 2 et 100 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Adresse email invalide.';
        }

        // Changement d'email : mot de passe actuel obligatoire + unicité
        if (empty($errors) && $email !== ($user['email'] ?? '')) {
            $currentPassword = $_POST['current_password'] ?? '';
            if (!$currentPassword || !$userModel->verifyPassword($currentPassword, $user['password_hash'])) {
                $errors['current_password'] = 'Mot de passe actuel requis pour changer d\'adresse email.';
            } else {
                $existing = $userModel->findByEmail($email);
                if ($existing && (int)$existing['id'] !== $userId) {
                    $errors['email'] = 'Cette adresse email est déjà utilisée par un autre compte.';
                }
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Response::redirect('/eleve/orientation/profil');
        }

        try {
            $userModel->updateProfile($userId, ['prenom' => $prenom, 'nom' => $nom]);
            if ($email !== ($user['email'] ?? '')) {
                $userModel->updateEmail($userId, $email);
                // Les dossiers restent liés via eleve_id ; on rattache aussi
                // ceux éventuellement déposés avec la nouvelle adresse.
                $this->dossierModel->claimByEmail($userId, $email);
            }
            Session::set('user_name', $prenom . ' ' . $nom);
        } catch (\Exception $e) {
            error_log('[EspaceOrientation] Profil update échoué : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
            Response::redirect('/eleve/orientation/profil');
        }

        Session::flash('success', 'Profil mis à jour.');
        Response::redirect('/eleve/orientation/profil');
    }

    public function updatePassword(): void
    {
        $userId    = (int)Session::userId();
        $userModel = new User();
        $user      = $userModel->findById($userId);

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirmation'] ?? '';

        $errors = [];
        if (!$userModel->verifyPassword($current, $user['password_hash'])) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        }
        if (strlen($new) < 8 || strlen($new) > 128) {
            $errors['new_password'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($new !== $confirm) {
            $errors['new_password_confirmation'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Response::redirect('/eleve/orientation/profil');
        }

        try {
            $userModel->updatePassword($userId, $new);
            Session::regenerate();
        } catch (\Exception $e) {
            error_log('[EspaceOrientation] Password update échoué : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
            Response::redirect('/eleve/orientation/profil');
        }

        Session::flash('success', 'Mot de passe mis à jour.');
        Response::redirect('/eleve/orientation/profil');
    }
}
