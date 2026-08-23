<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Hashid, Queue, Response, Session, TenantGuard};
use Models\User;
use Models\Orientation\{Dossier, Etablissement, Filiere, NotificationOrientation};
use Services\Orientation\{AuditService, DocumentStorage, PdfStream, RecepisseService};

class CandidatureController
{
    private Etablissement $etabModel;
    private Dossier       $dossierModel;

    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    private const INDICATIFS = [
        '+241' => '+241 Gabon',
        '+237' => '+237 Cameroun',
        '+225' => '+225 Côte d\'Ivoire',
        '+242' => '+242 Congo',
        '+243' => '+243 RDC',
        '+33'  => '+33 France',
        '+1'   => '+1 Canada/USA',
    ];

    public function __construct()
    {
        $this->etabModel    = new Etablissement();
        $this->dossierModel = new Dossier();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function loadEtab(string $slug): array
    {
        // Réutilise le bundle mis en cache par la page publique (PublicController,
        // clé `orientation:etab:{slug}`, invalidée à chaque modification) : les
        // étapes du wizard ne retouchent la base qu'en cas de cache froid.
        $etab   = null;
        $cached = \Core\Redis::getInstance()->get("orientation:etab:{$slug}");
        if ($cached !== false && is_array($cached) && !empty($cached['etab'])) {
            $etab = $cached['etab'];
        }

        $etab ??= $this->etabModel->findBySlug($slug);

        if (!$etab || $etab['statut'] !== 'actif') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Page introuvable'], 'main');
            exit;
        }
        return $etab;
    }

    private function sessionKey(string $slug): string
    {
        return 'insc_dossier_' . preg_replace('/[^a-z0-9_]/', '_', $slug);
    }

    /**
     * Formate un numéro gabonais pour MoneyFusion : « 241XXXXXXXXX ».
     * Tolère les entrées déjà préfixées (+241, 00241, 241) et les espaces,
     * évitant le double indicatif « 241+241… » qui faisait rejeter le paiement.
     */
    private static function formatNumeroMoneyFusion(string $telephone, string $indicatif): string
    {
        $national = \Core\Phone::normalizeGabon($telephone);
        if ($national !== null) {
            return '241' . substr($national, 1); // 0XXXXXXXX -> 241XXXXXXXX
        }

        // Numéro non reconnu comme gabonais valide : nettoyage best-effort.
        $digits = preg_replace('/\D+/', '', $telephone) ?? '';
        $digits = preg_replace('/^(00)?241/', '', $digits); // retire indicatif présent
        $digits = ltrim($digits, '0');                      // retire 0 national

        $cc = preg_replace('/\D+/', '', $indicatif) ?: '241';
        return $cc . $digits;
    }

    /**
     * Préremplit le formulaire avec les infos du compte connecté
     * (élève ou visiteur) quand aucune saisie précédente n'existe.
     */
    private function prefillFromUser(array $old): array
    {
        if (!empty($old) || !Session::isLoggedIn()) {
            return $old;
        }
        $user = (new \Models\User())->findById((int)Session::userId());
        if (!$user) {
            return $old;
        }
        return [];
    }

    /**
     * Filières configurées par l'établissement (menu déroulant dynamique
     * Filière → Spécialité). Fallback sur les niveaux génériques historiques
     * si l'établissement n'a encore rien configuré — rien ne casse.
     *
     * Retourne :
     *  - options     : [valeur => libellé] pour le <select> filière
     *  - specialites : [filière => [spécialité, …]] pour la cascade JS
     *  - dynamic     : true si les filières viennent de la config établissement
     */
    private function filieresEtab(int $etabId): array
    {
        $rows = (new Filiere())->findActiveByEtab($etabId);
        if (empty($rows)) {
            // Depuis la migration filieres_specialites, les dossiers stockent le
            // libellé (VARCHAR) et non plus la clé ENUM → options libellé => libellé.
            $labels = array_values(self::FILIERES);
            return ['options' => array_combine($labels, $labels), 'specialites' => [], 'dynamic' => false];
        }

        $options = [];
        $specs   = [];
        foreach ($rows as $r) {
            $options[$r['nom']] = $r['nom'];
            $specs[$r['nom']]   = $r['specialites'];
        }
        return ['options' => $options, 'specialites' => $specs, 'dynamic' => true];
    }

    /**
     * Valide le couple filière / spécialité soumis.
     * La spécialité n'est exigée que si la filière choisie en possède.
     * Retourne [erreurs, spécialité normalisée].
     */
    private function validerFiliereSpecialite(array $cfg, string $filiere, string $specialite): array
    {
        $errors = [];
        if ($filiere === '' || !isset($cfg['options'][$filiere])) {
            $errors['filiere_visee'] = 'Choisissez une filière.';
        } elseif (!empty($cfg['specialites'][$filiere])) {
            if ($specialite === '' || !in_array($specialite, $cfg['specialites'][$filiere], true)) {
                $errors['specialite_visee'] = 'Choisissez une spécialité.';
            }
        } else {
            $specialite = ''; // filière sans spécialités configurées
        }
        return [$errors, $specialite];
    }

    /** Libellé filière : mappe les anciennes clés ENUM, sinon valeur brute. */
    private function filiereLibelle(?string $val): string
    {
        return self::FILIERES[$val ?? ''] ?? (($val !== null && $val !== '') ? $val : '—');
    }

    /** "Filière — Spécialité" pour les emails / notifications. */
    private function filiereAvecSpecialite(array $d): string
    {
        $lbl  = $this->filiereLibelle($d['filiere_visee'] ?? null);
        $spec = trim((string)($d['specialite_visee'] ?? ''));
        return $spec !== '' ? $lbl . ' — ' . $spec : $lbl;
    }

    /**
     * Barrière compte : la préinscription et l'inscription exigent désormais un
     * compte créé/connecté AVANT de commencer la démarche. Le candidat visiteur
     * configure son compte au départ (et non plus à la fin), ce qui pré-remplit
     * ses informations (prefillFromUser) et rattache le dossier dès sa création.
     * Visiteur non connecté → page de création de compte express, avec retour
     * automatique vers le flux une fois le compte créé ou la connexion faite.
     * Retourne false après avoir déclenché la redirection (qui fait exit()).
     */
    private function requireCompte(string $returnUrl): bool
    {
        if (Session::isLoggedIn()) {
            return true;
        }
        Response::redirect('/orientation/compte?redirect=' . urlencode($returnUrl));
        return false;
    }

    private function validate(array $fields, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $req) {
            if ($req && empty(trim((string)($fields[$field] ?? '')))) {
                $errors[$field] = 'Ce champ est requis.';
            }
        }
        if (isset($fields['email']) && !empty($fields['email'])) {
            if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresse e-mail invalide.';
            }
        }
        return $errors;
    }

    // ── PREINSCRIPTION ────────────────────────────────────────────────────────

    public function preinscription(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        if (!$this->requireCompte("/orientation/{$slug}/preinscription")) {
            return;
        }

        if (!(bool)$etab['preinscription_active']) {
            Response::view('orientation/candidature-fermee', [
                'etab'    => $etab,
                'message' => 'La préinscription en ligne est actuellement fermée pour cet établissement.',
            ], '');
            return;
        }

        $formations  = $this->etabModel->getFormations((int)$etab['id']);
        $filieresCfg = $this->filieresEtab((int)$etab['id']);
        $old    = $this->prefillFromUser(Session::getFlash('old', []));
        $errors = Session::getFlash('errors', []);

        Response::view('orientation/preinscription', [
            'etab'        => $etab,
            'formations'  => $formations,
            'filieres'    => $filieresCfg['options'],
            'specialites' => $filieresCfg['specialites'],
            'indicatifs'  => self::INDICATIFS,
            'old'         => $old,
            'errors'      => $errors,
            'csrf'        => Session::getCsrfToken(),
        ], '');
    }

    public function submitPreinscription(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        if (!$this->requireCompte("/orientation/{$slug}/preinscription")) {
            return;
        }

        if (!(bool)$etab['preinscription_active']) {
            Response::redirect("/orientation/{$slug}/preinscription");
            return;
        }

        $p = $_POST;

        $errors = $this->validate($p, [
            'nom_complet'           => true,
            'genre'                 => true,
            'date_naissance'        => true,
            'lieu_naissance'        => true,
            'nationalite'           => true,
            'email'                 => true,
            'telephone'             => true,
            'pays_provenance'       => true,
            'ville'                 => true,
            'adresse'               => true,
            'filiere_visee'         => true,
            'niveau_entree'         => true,
            'dernier_etablissement' => true,
            'serie_bac'             => true,
            'annee_bac'             => true,
        ]);

        $filieresCfg = $this->filieresEtab((int)$etab['id']);
        [$errFiliere, $specialite] = $this->validerFiliereSpecialite(
            $filieresCfg,
            (string)($p['filiere_visee'] ?? ''),
            trim((string)($p['specialite_visee'] ?? ''))
        );
        $errors = array_merge($errors, $errFiliere);

        // Le genre alimente une colonne ENUM : on n'accepte que les valeurs du
        // formulaire, jamais une valeur forgée (POST direct).
        if (!in_array($p['genre'] ?? '', ['homme', 'femme'], true)) {
            $errors['genre'] = 'Genre invalide.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $p);
            Response::redirect("/orientation/{$slug}/preinscription");
            return;
        }

        try {
            $dossierId = $this->dossierModel->create([
                'etablissement_id'      => (int)$etab['id'],
                'eleve_id'              => Session::userId() ?: $this->rattacherOuCreerCompte($p),
                'filiere_visee'         => $p['filiere_visee'],
                'specialite_visee'      => $specialite ?: null,
                'niveau_entree'         => trim($p['niveau_entree']),
                'nom_complet'           => trim($p['nom_complet']),
                'genre'                 => $p['genre'],
                'date_naissance'        => $p['date_naissance'],
                'lieu_naissance'        => trim($p['lieu_naissance']),
                'nationalite'           => trim($p['nationalite']),
                'email'                 => trim(strtolower($p['email'])),
                'indicatif_tel'         => $p['indicatif_tel'] ?? '+241',
                'telephone'             => trim($p['telephone']),
                'adresse'               => trim($p['adresse']),
                'ville'                 => trim($p['ville']),
                'pays_provenance'       => trim($p['pays_provenance']),
                'dernier_etablissement' => trim($p['dernier_etablissement']),
                'serie_bac'             => trim($p['serie_bac']),
                'annee_bac'             => (int)$p['annee_bac'],
                'statut'                => 'soumis',
            ]);

            if ((bool)$etab['genere_recepisse']) {
                $this->genererRecepisse($dossierId, $etab, $p);
            }

            // Notification aux gestionnaires de l'établissement
            $this->notifierGestionnairesPreinscription($etab, $p, $dossierId);

            // Notification in-app au candidat connecté (espace élève)
            $eleveId = (int)(Session::userId() ?? 0);
            if ($eleveId > 0) {
                $recepisse = $this->dossierModel->getRecepisse($dossierId);
                (new NotificationOrientation())->create([
                    'user_id'          => $eleveId,
                    'etablissement_id' => (int)$etab['id'],
                    'type'             => 'preinscription_recepisse',
                    'titre'            => 'Préinscription enregistrée',
                    'message'          => 'Votre préinscription à ' . ($etab['nom'] ?? '') . ' est enregistrée'
                                        . (!empty($recepisse['numero_recepisse']) ? ' — attestation ' . $recepisse['numero_recepisse'] . ' disponible dans votre espace.' : '.'),
                ]);
            }

        } catch (\Throwable $e) {
            error_log('[Preinscription] Erreur création dossier : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Une erreur est survenue. Veuillez réessayer.']);
            Session::flash('old', $p);
            Response::redirect("/orientation/{$slug}/preinscription");
            return;
        }

        // Le dossier tout juste créé est mémorisé en session : c'est lui qui
        // autorise l'accès à la page de succès (voir dossierAutorise()).
        self::memoriserDossierSucces($dossierId);

        Response::redirect("/orientation/{$slug}/preinscription/succes?dossier=" . Hashid::encode($dossierId));
    }

    /**
     * Mémorise en session les dossiers que ce visiteur vient de déposer.
     * Sert de laissez-passer pour la page de succès et l'attestation associée,
     * y compris pour un visiteur non connecté.
     */
    private static function memoriserDossierSucces(int $dossierId): void
    {
        $liste = array_map('intval', (array)(Session::get('preins_ok') ?? []));
        $liste[] = $dossierId;
        // 20 derniers dépôts : borne la taille de la session
        Session::set('preins_ok', array_values(array_slice(array_unique($liste), -20)));
    }

    /**
     * Résout `?dossier=` (jeton opaque Hashid) en dossier réellement accessible.
     *
     * Deux laissez-passer possibles, jamais l'identifiant seul :
     *   1. le dossier vient d'être déposé dans cette session ;
     *   2. l'utilisateur connecté en est propriétaire (eleve_id ou e-mail).
     * Sans quoi la tentative est journalisée (sonde IDOR) et refusée.
     */
    private function dossierAutorise(string $slug, array $etab): ?array
    {
        $raw = trim((string)($_GET['dossier'] ?? ''));
        $id  = $raw !== '' ? Hashid::decode($raw) : null;
        if ($id === null || $id <= 0) {
            return null;
        }

        $dossier = $this->dossierModel->findById($id);
        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            return null;
        }

        $autorises = array_map('intval', (array)(Session::get('preins_ok') ?? []));
        if (in_array($id, $autorises, true)) {
            return $dossier;
        }

        $userId = (int)(Session::userId() ?? 0);
        $email  = strtolower(trim((string)(Session::user()['email'] ?? '')));
        $owner  = ($userId > 0 && (int)($dossier['eleve_id'] ?? 0) === $userId)
               || ($email !== '' && strtolower(trim((string)($dossier['email'] ?? ''))) === $email);

        if (!$owner) {
            TenantGuard::denyOwnership('orientation.preinscription_succes', $id);
            return null;
        }
        return $dossier;
    }

    public function preinscriptionSucces(string $slug): void
    {
        $etab    = $this->loadEtab($slug);
        $dossier = $this->dossierAutorise($slug, $etab);

        if (!$dossier) {
            Response::redirect("/orientation/{$slug}/preinscription");
            return;
        }

        $dossierId = (int)$dossier['id'];
        $recepisse = $this->dossierModel->getRecepisse($dossierId);

        // Lien interne (jeton opaque) plutôt qu'une URL signée B2 : l'identifiant
        // du dossier ne transite plus dans l'URL et le document est toujours
        // rendu depuis la base — jamais un instantané figé au dépôt.
        $recepisseUrl = ($recepisse && !empty($recepisse['numero_recepisse']))
            ? url("/orientation/{$slug}/preinscription/attestation?dossier=" . Hashid::encode($dossierId))
            : null;

        Response::view('orientation/preinscription-succes', [
            'etab'         => $etab,
            'dossier'      => $dossier,
            'recepisse'    => $recepisse,
            'recepisseUrl' => $recepisseUrl,
            'filieres'     => self::FILIERES,
        ], '');
    }

    /**
     * Attestation de préinscription — page imprimable, rendue à la demande
     * depuis la base. Même laissez-passer que la page de succès.
     */
    public function preinscriptionAttestation(string $slug): void
    {
        $etab    = $this->loadEtab($slug);
        $dossier = $this->dossierAutorise($slug, $etab);

        if (!$dossier) {
            Response::redirect("/orientation/{$slug}/preinscription");
            return;
        }

        $dossierId = (int)$dossier['id'];
        $recepisse = $this->dossierModel->getRecepisse($dossierId);

        if ($recepisse && !empty($recepisse['numero_recepisse'])) {
            $numero = (string)$recepisse['numero_recepisse'];
            try { $dateEmission = new \DateTime((string)$recepisse['date_emission']); }
            catch (\Throwable) { $dateEmission = new \DateTime(); }
        } else {
            $numero       = $this->dossierModel->reserveRecepisse($dossierId);
            $dateEmission = new \DateTime();
        }

        $token         = $this->dossierModel->tokenVerificationRecepisse($dossierId);
        $svc           = new RecepisseService();
        $numeroFacture = 'FAC-' . substr($numero, 4);

        PdfStream::emettre(
            $svc->renderPdfComplet($dossier, $etab, $numero, $numeroFacture, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    // ── INSCRIPTION ÉTAPE 1 ───────────────────────────────────────────────────

    public function inscriptionEtape1(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        if (!$this->requireCompte("/orientation/{$slug}/inscription")) {
            return;
        }

        if (!(bool)$etab['inscription_active']) {
            Response::view('orientation/candidature-fermee', [
                'etab'    => $etab,
                'message' => 'L\'inscription en ligne n\'est pas encore ouverte pour cet établissement.',
            ], '');
            return;
        }

        // Si un dossier est déjà en cours, rediriger à l'étape suivante
        $sessionKey = $this->sessionKey($slug);
        if (!empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        $old    = $this->prefillFromUser(Session::getFlash('old', []));
        $errors = Session::getFlash('errors', []);

        Response::view('orientation/inscription-etape1', [
            'etab'       => $etab,
            'indicatifs' => self::INDICATIFS,
            'old'        => $old,
            'errors'     => $errors,
            'csrf'       => Session::getCsrfToken(),
        ], '');
    }

    public function saveEtape1(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        if (!$this->requireCompte("/orientation/{$slug}/inscription")) {
            return;
        }

        if (!(bool)$etab['inscription_active']) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $p = $_POST;

        $errors = $this->validate($p, [
            'nom_complet'           => true,
            'genre'                 => true,
            'date_naissance'        => true,
            'lieu_naissance'        => true,
            'nationalite'           => true,
            'email'                 => true,
            'telephone'             => true,
            'adresse'               => true,
            'ville'                 => true,
            'pays_provenance'       => true,
            'dernier_etablissement' => true,
        ]);

        // Le genre alimente une colonne ENUM : on n'accepte que les valeurs du
        // formulaire, jamais une valeur forgée (POST direct).
        if (!in_array($p['genre'] ?? '', ['homme', 'femme'], true)) {
            $errors['genre'] = 'Genre invalide.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $p);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        try {
            $dossierId = $this->dossierModel->create([
                'etablissement_id'      => (int)$etab['id'],
                'eleve_id'              => Session::userId() ?: $this->rattacherOuCreerCompte($p),
                'nom_complet'           => trim($p['nom_complet']),
                'genre'                 => $p['genre'],
                'date_naissance'        => $p['date_naissance'],
                'lieu_naissance'        => trim($p['lieu_naissance']),
                'nationalite'           => trim($p['nationalite']),
                'email'                 => trim(strtolower($p['email'])),
                'indicatif_tel'         => $p['indicatif_tel'] ?? '+241',
                'telephone'             => trim($p['telephone']),
                'adresse'               => trim($p['adresse']),
                'ville'                 => trim($p['ville']),
                'pays_provenance'       => trim($p['pays_provenance']),
                'dernier_etablissement' => trim($p['dernier_etablissement']),
                'statut'                => 'brouillon',
            ]);

            $_SESSION[$this->sessionKey($slug)] = $dossierId;
        } catch (\Throwable $e) {
            error_log('[Inscription E1] Erreur : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur serveur. Veuillez réessayer.']);
            Session::flash('old', $p);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        Response::redirect("/orientation/{$slug}/inscription/etape-2");
    }

    // ── INSCRIPTION ÉTAPE 2 ───────────────────────────────────────────────────

    public function inscriptionEtape2(string $slug): void
    {
        $etab       = $this->loadEtab($slug);
        $sessionKey = $this->sessionKey($slug);

        if (empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $dossierId = (int)$_SESSION[$sessionKey];
        $dossier   = $this->dossierModel->findById($dossierId);

        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $pieces      = $this->dossierModel->getPiecesGroupedByNiveau((int)$etab['id']);
        $filieresCfg = $this->filieresEtab((int)$etab['id']);
        $old    = Session::getFlash('old', []);
        $errors = Session::getFlash('errors', []);

        Response::view('orientation/inscription-etape2', [
            'etab'        => $etab,
            'dossier'     => $dossier,
            'pieces'      => $pieces,
            'filieres'    => $filieresCfg['options'],
            'specialites' => $filieresCfg['specialites'],
            'old'         => $old,
            'errors'      => $errors,
            'csrf'        => Session::getCsrfToken(),
        ], '');
    }

    public function saveEtape2(string $slug): void
    {
        $etab       = $this->loadEtab($slug);
        $sessionKey = $this->sessionKey($slug);

        if (empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $dossierId = (int)$_SESSION[$sessionKey];
        $dossier   = $this->dossierModel->findById($dossierId);

        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $filiere = $_POST['filiere_visee'] ?? '';
        $niveau  = trim($_POST['niveau_entree'] ?? '');
        $passer  = isset($_POST['passer']);

        $filieresCfg = $this->filieresEtab((int)$etab['id']);
        [$errors, $specialite] = $this->validerFiliereSpecialite(
            $filieresCfg,
            (string)$filiere,
            trim((string)($_POST['specialite_visee'] ?? ''))
        );
        if (empty($niveau)) {
            $errors['niveau_entree'] = 'Le niveau d\'entrée est requis.';
        }

        // Vérification pièces obligatoires (seulement si bouton "Suivant", pas "Passer")
        if (!$passer && empty($errors)) {
            $obligatoires = $this->dossierModel->getPiecesObligatoires((int)$etab['id']);
            $manquantes   = [];
            foreach ($obligatoires as $pieceId) {
                $key = "piece_{$pieceId}";
                if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
                    $manquantes[] = $pieceId;
                }
            }
            if (!empty($manquantes)) {
                $errors['pieces'] = 'Veuillez déposer toutes les pièces obligatoires (marquées "Requis") avant de continuer.';
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        $this->dossierModel->updateFiliere($dossierId, $filiere, $niveau, $specialite ?: null);

        // Upload des pièces justificatives (silencieux si B2 indisponible)
        $this->uploadPieces($dossierId, (int)$etab['id']);

        // ── Étape 3 : paiement en ligne ──
        // Si l'inscription est payante, le dossier reste en session et n'est
        // soumis à l'établissement qu'une fois le paiement confirmé
        // (ConfirmationInscriptionService, via webhook ou retour MoneyFusion).
        $total = (int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0);
        if ($total > 0) {
            Response::redirect("/orientation/{$slug}/inscription/etape-3");
            return;
        }

        // Inscription gratuite : finalisation immédiate. Le dossier passe en
        // "soumis" et attend la validation de l'établissement, qui délivrera
        // alors l'attestation d'inscription.
        $this->dossierModel->updateStatut($dossierId, 'soumis');
        $this->dossierModel->createInscription($dossierId, (int)$etab['id']);

        $dossierFinal = $this->dossierModel->findById($dossierId) ?? $dossier;
        $this->notifierGestionnairesInscription($etab, $dossierFinal, $dossierId);
        if (!empty($dossierFinal['email'])) {
            $this->envoyerEmailDossierRecu($dossierFinal['email'], $dossierFinal['nom_complet'] ?? 'Candidat', $etab, $dossierFinal);
        }

        // Notification in-app au candidat (espace élève)
        if (!empty($dossierFinal['eleve_id'])) {
            (new NotificationOrientation())->create([
                'user_id'          => (int)$dossierFinal['eleve_id'],
                'etablissement_id' => (int)$etab['id'],
                'type'             => 'inscription_soumise',
                'titre'            => 'Dossier d\'inscription soumis',
                'message'          => 'Votre dossier d\'inscription à ' . ($etab['nom'] ?? '') . ' a bien été soumis — il est maintenant à l\'étude par l\'établissement.',
            ]);
        }

        unset($_SESSION[$sessionKey]);
        Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
    }

    // ── INSCRIPTION ÉTAPE 3 : paiement en ligne ──────────────────────────────

    public function inscriptionEtape3(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        $sessionKey = $this->sessionKey($slug);
        if (empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $dossierId = (int)$_SESSION[$sessionKey];
        $dossier   = $this->dossierModel->findById($dossierId);

        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        // Déjà payé (webhook arrivé avant le retour navigateur)
        if (in_array($dossier['statut'], ['paye', 'accepte'], true)) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
            return;
        }

        // Dossier incomplet (filière non choisie) : retour à l'étape 2
        if (empty($dossier['filiere_visee'])) {
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        Response::view('orientation/inscription-etape3', [
            'etab'     => $etab,
            'dossier'  => $dossier,
            'filieres' => self::FILIERES,
            'errors'   => Session::getFlash('errors') ?? [],
            'csrf'     => Session::getCsrfToken(),
        ], '');
    }

    // ── PAIEMENT ÉTAPE 3 ─────────────────────────────────────────────────────

    public function payEtape3(string $slug): void
    {
        $etab = $this->loadEtab($slug);

        if (!(bool)$etab['inscription_active']) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $sessionKey = $this->sessionKey($slug);
        if (empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        $dossierId = (int)$_SESSION[$sessionKey];
        $dossier   = $this->dossierModel->findById($dossierId);

        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        // Déjà payé
        if (in_array($dossier['statut'], ['paye', 'accepte'])) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
            return;
        }

        $total = (int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0);

        // Inscription gratuite → finalisation directe, sans paiement
        if ($total === 0) {
            $this->dossierModel->updateStatut($dossierId, 'soumis');
            $this->dossierModel->createInscription($dossierId, (int)$etab['id']);
            $dossierFinal = $this->dossierModel->findById($dossierId) ?? $dossier;
            $this->notifierGestionnairesInscription($etab, $dossierFinal, $dossierId);
            if (!empty($dossierFinal['email'])) {
                $this->envoyerEmailDossierRecu($dossierFinal['email'], $dossierFinal['nom_complet'] ?? 'Candidat', $etab, $dossierFinal);
            }
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
            return;
        }

        // Auto-détection URL (tunnel CF, ngrok, localhost…)
        $proto  = $_SERVER['HTTP_X_FORWARDED_PROTO']
               ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $appUrl = $host
            ? rtrim($proto . '://' . $host . BASE_URL, '/')
            : rtrim($_ENV['APP_URL'] ?? '', '/');

        $paiementId = $this->dossierModel->createPaiement([
            'dossier_id'       => $dossierId,
            'etablissement_id' => (int)$etab['id'],
            'montant'          => $total,
            'mode'             => 'en_ligne',
            'statut'           => 'initie',
        ]);

        $reference       = "ORI-{$paiementId}-" . date('Ymd');
        $nom             = trim($dossier['nom_complet'] ?? 'Candidat');
        $montantAEnvoyer = \Core\MoneyFusion::calculateAmountToSend((float)$total);
        // MoneyFusion attend un numéro « 241XXXXXXXXX » sans + ni espaces.
        // Le champ telephone peut déjà contenir l'indicatif (+241…) : sinon
        // l'ancien code produisait « 241+241 07 14 … » et MF rejetait.
        $tel = self::formatNumeroMoneyFusion(
            $dossier['telephone'] ?? '',
            $dossier['indicatif_tel'] ?? '+241'
        );

        $mfResult = \Core\MoneyFusion::initiate([
            'totalPrice'    => $montantAEnvoyer,
            'article'       => [["Inscription {$etab['nom']}" => $montantAEnvoyer]],
            'personal_Info' => [[
                'paiementId'  => $paiementId,
                'dossierId'   => $dossierId,
                'reference'   => $reference,
                'etabSlug'    => $slug,
                'email'       => $dossier['email'] ?? '',
                'phoneNumber' => $tel,
            ]],
            'numeroSend'  => $tel,
            'nomclient'   => $nom,
            'return_url'  => $appUrl . "/orientation/{$slug}/inscription/retour?p={$paiementId}",
            'webhook_url' => $appUrl . '/webhook/orientation/payin',
        ]);

        if (!($mfResult['statut'] ?? false) || empty($mfResult['url'])) {
            error_log('[OrientationPay] Échec MF: ' . json_encode($mfResult));
            Session::flash('errors', ['general' => 'Impossible d\'initier le paiement. Veuillez réessayer.']);
            Response::redirect("/orientation/{$slug}/inscription/etape-3");
            return;
        }

        $mfToken = $mfResult['token'] ?? null;
        if ($mfToken) {
            $this->dossierModel->updatePaiementToken($paiementId, $mfToken);
        }
        $this->dossierModel->updatePaiementStatut($paiementId, 'en_attente');

        error_log("[OrientationPay] Initié — dossier:{$dossierId} paiement:{$paiementId} token:" . \Core\MoneyFusion::maskToken($mfToken));

        header('Location: ' . $mfResult['url']);
        exit;
    }

    public function retourPaiement(string $slug): void
    {
        $etab       = $this->loadEtab($slug);
        $paiementId = (int)($_GET['p'] ?? 0);

        if (!$paiementId) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $paiement = $this->dossierModel->findPaiementById($paiementId);
        if (!$paiement || (int)$paiement['etablissement_id'] !== (int)$etab['id']) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $dossierId = (int)$paiement['dossier_id'];
        $dossier   = $this->dossierModel->findById($dossierId);

        // Déjà confirmé par webhook ?
        if ($paiement['statut'] === 'reussi' || ($dossier && $dossier['statut'] === 'paye')) {
            unset($_SESSION[$this->sessionKey($slug)]);
            Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
            return;
        }

        // Vérification active via API MF — seul data.statut === 'paid' fait foi
        // (le statut top-level de paiementNotif signifie « requête OK », pas « payé »)
        $mfToken   = $paiement['token_pay'] ?? '';
        $confirmed = false;

        if ($mfToken) {
            $statusData = \Core\MoneyFusion::checkStatus($mfToken);
            $statut     = strtolower((string)($statusData['data']['statut'] ?? ''));
            if ($statut === 'paid') {
                $confirmed = \Services\Orientation\ConfirmationInscriptionService::confirmer($paiementId, $mfToken);
            }
        }

        if ($confirmed) {
            unset($_SESSION[$this->sessionKey($slug)]);
            Response::redirect("/orientation/{$slug}/inscription/succes?dossier={$dossierId}");
        } else {
            Session::flash('errors', ['paiement' => 'Votre paiement est en cours de traitement. Vous recevrez un e-mail de confirmation dès qu\'il sera validé.']);
            Response::redirect("/orientation/{$slug}/inscription/etape-3");
        }
    }

    public function inscriptionSucces(string $slug): void
    {
        $etab      = $this->loadEtab($slug);
        $dossierId = (int)($_GET['dossier'] ?? 0);

        if (!$dossierId) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $dossier = $this->dossierModel->findById($dossierId);
        if (!$dossier || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            Response::redirect("/orientation/{$slug}");
            return;
        }

        $paiement = $this->dossierModel->findLastPaiementByDossier($dossierId);
        $recuUrl  = null;

        if ($paiement) {
            $recu = $this->dossierModel->getRecu((int)$paiement['id']);
            if ($recu && !empty($recu['cle_pdf'])) {
                try {
                    $storage = new DocumentStorage();
                    $recuUrl = $storage->signedUrl($recu['cle_pdf'], 10);
                } catch (\Throwable $e) {
                    error_log('[InscriptionSucces] signedUrl reçu échoué : ' . $e->getMessage());
                }
            }
        }

        Response::view('orientation/paiement-succes', [
            'etab'     => $etab,
            'dossier'  => $dossier,
            'paiement' => $paiement,
            'recuUrl'  => $recuUrl,
            'filieres' => self::FILIERES,
        ], '');
    }

    // ── Privé : upload pièces ─────────────────────────────────────────────────

    private function uploadPieces(int $dossierId, int $etablissementId): void
    {
        if (empty($_FILES)) {
            return;
        }

        try {
            $storage = new DocumentStorage();
        } catch (\Throwable $e) {
            error_log('[Inscription E2] B2 init échoué : ' . $e->getMessage());
            return;
        }

        foreach ($_FILES as $key => $file) {
            if (!str_starts_with($key, 'piece_')) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            $pieceId = (int)str_replace('piece_', '', $key);
            $libelle = basename($file['name']);
            $mime    = $file['type'];
            $cle     = sprintf('dossiers/%d/pieces/%s-%s', $dossierId, $key, basename($file['name']));

            try {
                $storage->upload($file['tmp_name'], $cle, $mime, false);
                $this->dossierModel->addDocument([
                    'dossier_id'       => $dossierId,
                    'eleve_id'         => Session::userId() ?? 0,
                    'piece_requise_id' => $pieceId ?: null,
                    'libelle'          => $libelle,
                    'cle_b2'           => $cle,
                    'mime_type'        => $mime,
                    'taille_octets'    => $file['size'],
                ]);
            } catch (\Throwable $e) {
                error_log("[Inscription E2] Upload pièce {$key} échoué : " . $e->getMessage());
            }
        }
    }

    // ── Privé : génération récépissé ──────────────────────────────────────────

    private function genererRecepisse(int $dossierId, array $etab, array $p): void
    {
        // Numéro réservé atomiquement (ligne créée d'abord) : garantit l'unicité
        // et évite la collision PRE-AAAA-NNNN entre établissements.
        try {
            $numero = $this->dossierModel->reserveRecepisse($dossierId);
        } catch (\Throwable $e) {
            error_log('[Preinscription] Réservation numéro récépissé échouée : ' . $e->getMessage());
            return;
        }

        // Archive PDF (même document que celui téléchargé par le candidat) ;
        // repli HTML si dompdf échoue, pour ne jamais perdre l'archive.
        $svc           = new RecepisseService();
        $token         = $this->dossierModel->tokenVerificationRecepisse($dossierId);
        $numeroFacture = 'FAC-' . substr($numero, 4);
        $pdf           = $svc->renderPdfComplet($p, $etab, $numero, $numeroFacture, null, $token);

        if ($pdf !== null) {
            $contenu = $pdf;
            $mime    = 'application/pdf';
            $cle     = sprintf('recepisses/%d/%s.pdf', $dossierId, $numero);
        } else {
            $contenu = $svc->render($p, $etab, $numero, null, $token);
            $mime    = 'text/html; charset=utf-8';
            $cle     = sprintf('recepisses/%d/%s.html', $dossierId, $numero);
        }

        try {
            $storage = new DocumentStorage();
            $storage->uploadContent($contenu, $cle, $mime);
            $this->dossierModel->updateRecepisseCle($dossierId, $cle);
        } catch (\Throwable $e) {
            error_log('[Preinscription] Upload récépissé échoué : ' . $e->getMessage());
            // Numéro conservé — le récépissé est regénéré à la demande (espace/gestionnaire)
        }
    }

    // ── Email candidat : dossier d'inscription reçu (avant validation) ───────

    /**
     * Rattache la candidature à un compte élève : compte existant retrouvé
     * par e-mail, sinon création automatique + e-mail d'activation (lien de
     * définition du mot de passe via le flux reset existant).
     * Ne bloque jamais la candidature en cas d'échec.
     */
    private function rattacherOuCreerCompte(array $p): ?int
    {
        $email = trim(strtolower($p['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $userModel = new User();

            $existant = $userModel->findByEmail($email);
            if ($existant) {
                return (int)$existant['id'];
            }

            // "Nom complet" → prénom (1er mot) + nom (reste)
            $mots   = preg_split('/\s+/', trim($p['nom_complet'] ?? '')) ?: [];
            $prenom = array_shift($mots) ?: 'Candidat';
            $nom    = $mots ? implode(' ', $mots) : $prenom;

            $userId = $userModel->create([
                'nom'       => $nom,
                'prenom'    => $prenom,
                'email'     => $email,
                'telephone' => trim(($p['indicatif_tel'] ?? '+241') . ($p['telephone'] ?? '')) ?: null,
                // Mot de passe aléatoire jamais communiqué — l'élève le définit
                // via le lien envoyé par e-mail
                'password'  => bin2hex(random_bytes(16)),
            ]);

            $this->envoyerEmailCompteCree($email, $prenom);

            return $userId;
        } catch (\Throwable $e) {
            error_log('[Orientation] rattacherOuCreerCompte : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * E-mail de bienvenue avec lien de définition du mot de passe
     * (réutilise la table password_resets et la route /auth/reinitialiser).
     */
    private function envoyerEmailCompteCree(string $email, string $prenom): void
    {
        try {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $db = \Core\Database::getInstance()->getConnection();
            \Core\PasswordResetSchema::ensure($db);
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $db->prepare("INSERT INTO password_resets (email, token_hash, created_at) VALUES (?, ?, NOW())")
               ->execute([$email, $tokenHash]);

            $proto  = $_SERVER['HTTP_X_FORWARDED_PROTO']
                   ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $host   = $_SERVER['HTTP_HOST'] ?? '';
            $appUrl = $host
                ? rtrim($proto . '://' . $host . BASE_URL, '/')
                : rtrim($_ENV['APP_URL'] ?? 'https://connectacademia.io', '/');
            $lien   = $appUrl . '/auth/reinitialiser/' . $token;

            $prenomSafe = htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8');
            $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,Helvetica,Arial,sans-serif;background:#f4f4f9;margin:0;padding:32px 16px">'
                . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">'
                . '<div style="background:#8B52FA;padding:32px 36px">'
                . '<h1 style="color:#fff;font-size:20px;font-weight:800;margin:0">Votre compte Connect\'Academia est prêt</h1>'
                . '</div>'
                . '<div style="padding:32px 36px">'
                . '<p style="font-size:15px;color:#333;margin-bottom:24px">Bonjour <strong>' . $prenomSafe . '</strong>,<br><br>'
                . 'Un compte a été créé automatiquement avec votre candidature. Il vous permet de suivre '
                . 'l\'avancement de votre dossier, recevoir les notifications de l\'établissement et échanger '
                . 'avec lui depuis votre espace élève.</p>'
                . '<p style="text-align:center;margin:28px 0">'
                . '<a href="' . htmlspecialchars($lien, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#8B52FA;color:#fff;font-weight:700;font-size:15px;text-decoration:none;padding:14px 28px;border-radius:10px">Définir mon mot de passe</a></p>'
                . '<p style="font-size:13px;color:#888">Ce lien est valable 1 heure. Passé ce délai, utilisez '
                . '« Mot de passe oublié » sur la page de connexion avec cette adresse e-mail.</p>'
                . '</div></div></body></html>';

            Queue::push('send_email', [
                'to'      => $email,
                'toName'  => $prenom,
                'subject' => 'Votre compte Connect\'Academia — définissez votre mot de passe',
                'html'    => $html,
            ]);
        } catch (\Throwable $e) {
            error_log('[Orientation] envoyerEmailCompteCree : ' . $e->getMessage());
        }
    }

    private function envoyerEmailDossierRecu(string $email, string $nom, array $etab, array $dossier): void
    {
        $etabNom        = $etab['nom'] ?? '—';
        $couleur        = $etab['couleur_principale'] ?? '#8B52FA';
        $filiereLibelle = $this->filiereAvecSpecialite($dossier);
        $niveauEntree   = $dossier['niveau_entree'] ?? '—';
        $date           = date('d/m/Y à H:i');
        $espaceUrl      = 'https://connectacademia.io/eleve/orientation';

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,Helvetica,Arial,sans-serif;background:#f4f4f9;margin:0;padding:32px 16px">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">'
            . '<div style="background:' . htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') . ';padding:32px 36px">'
            . '<h1 style="color:#fff;font-size:20px;font-weight:800;margin:0">Dossier d\'inscription reçu ✓</h1>'
            . '<p style="color:rgba(255,255,255,.85);font-size:14px;margin:8px 0 0">' . htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . '<div style="padding:32px 36px">'
            . '<p style="font-size:15px;color:#333;margin-bottom:24px">Bonjour <strong>' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '</strong>,<br><br>'
            . 'Votre dossier d\'inscription à <strong>' . htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8') . '</strong> a bien été déposé. '
            . 'Il est maintenant <strong>en cours d\'examen</strong> par l\'établissement.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:24px">'
            . '<tr style="background:#f8f7ff"><td style="padding:10px 14px;font-size:13px;color:#888;width:140px">Formation</td><td style="padding:10px 14px;font-size:14px;font-weight:600;color:#1a1228">' . htmlspecialchars($filiereLibelle . ($niveauEntree !== '—' ? ' — ' . $niveauEntree : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 14px;font-size:13px;color:#888">Statut</td><td style="padding:10px 14px;font-size:14px;font-weight:600;color:#b45309">En attente de validation</td></tr>'
            . '<tr style="background:#f8f7ff"><td style="padding:10px 14px;font-size:13px;color:#888">Déposé le</td><td style="padding:10px 14px;font-size:14px;color:#333">' . $date . '</td></tr>'
            . '</table>'
            . '<p style="font-size:13px;color:#666">Dès que l\'établissement aura validé votre dossier, votre <strong>attestation d\'inscription</strong> sera disponible dans votre espace personnel :</p>'
            . '<p style="margin:18px 0"><a href="' . $espaceUrl . '" style="background:' . htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') . ';color:#fff;text-decoration:none;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:700;display:inline-block">Suivre mon dossier</a></p>'
            . '<p style="font-size:12px;color:#aaa;margin-top:24px">Connect\'Academia · <a href="https://connectacademia.io" style="color:#aaa">connectacademia.io</a></p>'
            . '</div></div></body></html>';

        Queue::push('send_email', [
            'to'      => $email,
            'toName'  => $nom,
            'subject' => "Dossier d'inscription reçu — {$etabNom}",
            'html'    => $html,
        ]);
    }

    // ── Notification gestionnaires (inscription en ligne reçue) ──────────────

    private function notifierGestionnairesInscription(array $etab, array $dossier, int $dossierId): void
    {
        $etabId   = (int)$etab['id'];
        $etabNom  = $etab['nom'] ?? '';
        $couleurH = htmlspecialchars($etab['couleur_principale'] ?? '#8B52FA', ENT_QUOTES, 'UTF-8');
        $etabNomH = htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8');
        $nomH     = htmlspecialchars(trim($dossier['nom_complet'] ?? ''), ENT_QUOTES, 'UTF-8');
        $emailH   = htmlspecialchars(trim($dossier['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $filiereH = htmlspecialchars($this->filiereAvecSpecialite($dossier), ENT_QUOTES, 'UTF-8');
        $dateH    = date('d/m/Y à H:i');

        $gestionnaires = $this->etabModel->findGestionnairesEmail($etabId);
        $notif         = new NotificationOrientation();

        foreach ($gestionnaires as $g) {
            $gPrenomH = htmlspecialchars($g['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
            $html = <<<HTML
            <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
            <body style="font-family:Montserrat,sans-serif;background:#F5F5F7;padding:40px 0;color:#1A1A1A;">
              <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                <div style="background:{$couleurH};padding:28px 36px;">
                  <p style="color:#fff;font-size:18px;font-weight:700;margin:0;">Nouvelle inscription à valider</p>
                  <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;">{$etabNomH}</p>
                </div>
                <div style="padding:28px 36px;">
                  <p style="font-size:14px;">Bonjour <strong>{$gPrenomH}</strong>,</p>
                  <p style="font-size:14px;color:#4A4A4A;line-height:1.6;">
                    Un nouveau dossier d'inscription vient d'être déposé en ligne.
                    Il attend votre validation pour délivrer l'attestation d'inscription.
                  </p>
                  <div style="background:#F9F9FB;border-radius:12px;padding:16px 20px;margin:16px 0;border:1px solid #E5E5E5;">
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Candidat</p>
                    <p style="margin:0 0 12px;font-size:14px;font-weight:600;">{$nomH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Email</p>
                    <p style="margin:0 0 12px;font-size:14px;">{$emailH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Filière</p>
                    <p style="margin:0;font-size:14px;font-weight:600;">{$filiereH}</p>
                  </div>
                  <p style="font-size:12px;color:#9E9E9E;">Reçu le {$dateH} — Dossier #{$dossierId}</p>
                </div>
              </div>
            </body></html>
            HTML;

            Queue::push('send_email', [
                'to'      => $g['email'],
                'toName'  => ($g['prenom'] ?? '') . ' ' . ($g['nom'] ?? ''),
                'subject' => "Nouvelle inscription à valider — {$etabNom}",
                'html'    => $html,
            ]);

            $notif->create([
                'user_id'          => (int)$g['id'],
                'etablissement_id' => $etabId,
                'type'             => 'inscription_recue',
                'titre'            => "Nouvelle inscription à valider — {$etabNom}",
                'message'          => "Dossier #{$dossierId} — " . ($dossier['nom_complet'] ?? '') . " — {$filiereH}",
            ]);
        }
    }

    // ── Notification gestionnaires (preinscription reçue) ─────────────────────

    private function notifierGestionnairesPreinscription(array $etab, array $p, int $dossierId): void
    {
        $etabId         = (int)$etab['id'];
        $etabNom        = $etab['nom'] ?? '';
        $couleur        = $etab['couleur_principale'] ?? '#8B52FA';
        $nomCandidatH   = htmlspecialchars(trim($p['nom_complet'] ?? ''), ENT_QUOTES, 'UTF-8');
        $emailCandidatH = htmlspecialchars(trim($p['email'] ?? ''),        ENT_QUOTES, 'UTF-8');
        $filiereH       = htmlspecialchars($this->filiereAvecSpecialite($p), ENT_QUOTES, 'UTF-8');
        $etabNomH       = htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8');
        $couleurH       = htmlspecialchars($couleur,  ENT_QUOTES, 'UTF-8');
        $dateH          = date('d/m/Y à H:i');

        $gestionnaires = $this->etabModel->findGestionnairesEmail($etabId);
        $notif         = new NotificationOrientation();

        foreach ($gestionnaires as $g) {
            // Email gestionnaire
            $gPrenomH = htmlspecialchars($g['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
            $html = <<<HTML
            <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
            <body style="font-family:Montserrat,sans-serif;background:#F5F5F7;padding:40px 0;color:#1A1A1A;">
              <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                <div style="background:{$couleurH};padding:28px 36px;">
                  <p style="color:#fff;font-size:18px;font-weight:700;margin:0;">Nouvelle préinscription</p>
                  <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;">{$etabNomH}</p>
                </div>
                <div style="padding:28px 36px;">
                  <p style="font-size:14px;">Bonjour <strong>{$gPrenomH}</strong>,</p>
                  <p style="font-size:14px;color:#4A4A4A;line-height:1.6;">
                    Une nouvelle préinscription vient d'être reçue sur Connect'Academia.
                  </p>
                  <div style="background:#F9F9FB;border-radius:12px;padding:16px 20px;margin:16px 0;border:1px solid #E5E5E5;">
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Candidat</p>
                    <p style="margin:0 0 12px;font-size:14px;font-weight:600;">{$nomCandidatH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Email</p>
                    <p style="margin:0 0 12px;font-size:14px;">{$emailCandidatH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Filière</p>
                    <p style="margin:0;font-size:14px;font-weight:600;">{$filiereH}</p>
                  </div>
                  <p style="font-size:12px;color:#9E9E9E;">Reçu le {$dateH} — Dossier #{$dossierId}</p>
                </div>
              </div>
            </body></html>
            HTML;

            Queue::push('send_email', [
                'to'      => $g['email'],
                'toName'  => ($g['prenom'] ?? '') . ' ' . ($g['nom'] ?? ''),
                'subject' => "Nouvelle préinscription — {$etabNom}",
                'html'    => $html,
            ]);

            // Notification BDD gestionnaire
            $notif->create([
                'user_id'          => (int)$g['id'],
                'etablissement_id' => $etabId,
                'type'             => 'preinscription_recue',
                'titre'            => "Nouvelle préinscription — {$etabNom}",
                'message'          => "Dossier #{$dossierId} — {$p['nom_complet']} — {$filiereH}",
            ]);
        }
    }
}
