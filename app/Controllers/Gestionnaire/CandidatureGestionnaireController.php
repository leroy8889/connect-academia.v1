<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Database, Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, Dossier, Filiere, Formation, PieceRequise, NotificationOrientation};
use Models\Notification;
use Models\User;
use Services\Orientation\{DocumentStorage, JournalGestionnaire, PdfStream, RecepisseService, SmsService, WhatsAppService};

class CandidatureGestionnaireController
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    /**
     * Filières configurées par l'établissement (menu Filière → Spécialité).
     * Fallback sur les niveaux génériques historiques (libellé => libellé,
     * les dossiers stockent le libellé depuis la migration filieres_specialites).
     */
    private function filieresEtab(): array
    {
        $rows = (new Filiere())->findActiveByEtab($this->etabId);
        if (empty($rows)) {
            $labels = array_values(self::FILIERES);
            return ['options' => array_combine($labels, $labels), 'specialites' => []];
        }
        $options = [];
        $specs   = [];
        foreach ($rows as $r) {
            $options[$r['nom']] = $r['nom'];
            $specs[$r['nom']]   = $r['specialites'];
        }
        return ['options' => $options, 'specialites' => $specs];
    }

    /** Valide filière/spécialité soumises (spécialité exigée si la filière en a). */
    private function validerFiliereSpecialite(array $cfg, string $filiere, string $specialite, bool $filiereRequise): array
    {
        $errors = [];
        if ($filiere === '') {
            if ($filiereRequise) {
                $errors['filiere_visee'] = 'Filière obligatoire pour une inscription.';
            }
            return [$errors, ''];
        }
        if (!isset($cfg['options'][$filiere])) {
            $errors['filiere_visee'] = 'Filière inconnue.';
        } elseif (!empty($cfg['specialites'][$filiere])) {
            if ($specialite === '' || !in_array($specialite, $cfg['specialites'][$filiere], true)) {
                $errors['specialite_visee'] = 'Choisissez une spécialité pour cette filière.';
            }
        } else {
            $specialite = '';
        }
        return [$errors, $specialite];
    }

    public function index(): void
    {
        $etab    = (new Etablissement())->findById($this->etabId);
        $filters = [
            'statut'  => $_GET['statut'] ?? '',
            'origine' => $_GET['origine'] ?? '',
            'q'       => trim($_GET['q'] ?? ''),
        ];
        $dossiers = (new Dossier())->findByEtab($this->etabId, $filters);

        Response::view('gestionnaire/candidatures/index', [
            'pageTitle' => 'Candidatures — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'dossiers'  => $dossiers,
            'filters'   => $filters,
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function show(int $id): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $documents = $dossierModel->getDocumentsByDossier($id);
        $storage   = new DocumentStorage();
        $signedUrls= [];
        foreach ($documents as $doc) {
            try {
                $signedUrls[$doc['id']] = $storage->signedUrl($doc['cle_b2'], 15);
            } catch (\Throwable) {
                $signedUrls[$doc['id']] = null;
            }
        }

        $etab = (new Etablissement())->findById($this->etabId);
        $sms  = new SmsService();

        Response::view('gestionnaire/candidatures/show', [
            'pageTitle'    => 'Dossier #' . $id,
            'etab'         => $etab,
            'dossier'      => $dossier,
            'documents'    => $documents,
            'signedUrls'   => $signedUrls,
            'recepisse'    => $dossierModel->getRecepisse($id),
            'attestation'  => $dossierModel->getAttestation($id),
            'estInscription'=> $dossierModel->hasInscription($id),
            'filieres'     => self::FILIERES,
            'smsHistorique'=> $sms->historique($id, 5),
            'smsSimulation'=> $sms->estSimulation(),
            'flash'        => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'waUrl'        => Session::getFlash('wa_url'),
        ], 'gestionnaire');
    }

    // ── Édition des informations du candidat ─────────────────────────────────

    public function edit(int $id): void
    {
        $dossier = (new Dossier())->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $filieresCfg = $this->filieresEtab();

        Response::view('gestionnaire/candidatures/edit', [
            'pageTitle'   => 'Modifier le dossier #' . $id,
            'etab'        => (new Etablissement())->findById($this->etabId),
            'dossier'     => $dossier,
            'filieres'    => $filieresCfg['options'],
            'specialites' => $filieresCfg['specialites'],
            'errors'      => Session::getFlash('form_errors') ?? [],
            'old'         => Session::getFlash('form_old') ?? [],
        ], 'gestionnaire');
    }

    public function update(int $id): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $errors = [];
        $nom    = trim($_POST['nom_complet']    ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $tel    = trim($_POST['telephone']      ?? '');
        $genre  = $_POST['genre']               ?? '';
        $naiss  = trim($_POST['date_naissance'] ?? '');
        $lieu   = trim($_POST['lieu_naissance'] ?? '');
        $nation = trim($_POST['nationalite']    ?? '');

        if ($nom    === '') $errors['nom_complet']   = 'Obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide.';
        if ($tel    === '') $errors['telephone']     = 'Obligatoire.';
        if (!in_array($genre, ['homme','femme'], true)) $errors['genre'] = 'Obligatoire.';
        if ($naiss  === '') $errors['date_naissance']= 'Obligatoire.';
        if ($lieu   === '') $errors['lieu_naissance']= 'Obligatoire.';
        if ($nation === '') $errors['nationalite']   = 'Obligatoire.';

        // Unicité de l'e-mail dans l'établissement — le dossier courant est
        // exclu pour qu'une modification sans changement d'e-mail reste possible.
        if (!isset($errors['email'])) {
            if ($dossierModel->findByEmailInEtab($email, $this->etabId, $id)) {
                $errors['email'] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        $filiere = $_POST['filiere_visee'] ?? '';
        [$errFiliere, $specialite] = $this->validerFiliereSpecialite(
            $this->filieresEtab(),
            (string)$filiere,
            trim((string)($_POST['specialite_visee'] ?? '')),
            false
        );
        $errors = array_merge($errors, $errFiliere);

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('form_old', $_POST);
            Response::redirect('/gestionnaire/candidatures/' . $id . '/modifier');
            return;
        }

        $ok = $dossierModel->updateInfosByEtab($id, $this->etabId, [
            'nom_complet'          => $nom,
            'genre'                => $genre,
            'date_naissance'       => $naiss,
            'lieu_naissance'       => $lieu,
            'nationalite'          => $nation,
            'email'                => $email,
            'indicatif_tel'        => $_POST['indicatif_tel'] ?? '+241',
            'telephone'            => $tel,
            'adresse'              => trim($_POST['adresse']          ?? '') ?: null,
            'ville'                => trim($_POST['ville']            ?? '') ?: null,
            'pays_provenance'      => trim($_POST['pays_provenance']  ?? '') ?: null,
            'dernier_etablissement'=> trim($_POST['dernier_etablissement'] ?? '') ?: null,
            'filiere_visee'        => $filiere ?: null,
            'specialite_visee'     => $specialite ?: null,
            'niveau_entree'        => trim($_POST['niveau_entree']    ?? '') ?: null,
        ]);

        if ($ok) {
            JournalGestionnaire::log('candidature.modification', 'dossier', $id,
                'Modification du dossier #' . $id . ' — ' . $nom);
            Session::flash('success', 'Informations du candidat mises à jour.');
        } else {
            Session::flash('error', 'La mise à jour a échoué. Réessayez.');
        }
        Response::redirect('/gestionnaire/candidatures/' . $id);
    }

    // ── Envoi de SMS au candidat ─────────────────────────────────────────────

    public function sendSms(int $id): void
    {
        $dossier = (new Dossier())->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $numero = SmsService::normaliserNumero(
            (string)($dossier['indicatif_tel'] ?? '+241'),
            (string)($dossier['telephone'] ?? '')
        );
        if ($numero === null) {
            Session::flash('error', 'Le numéro de téléphone du candidat est invalide — corrigez-le via « Modifier » avant d\'envoyer un SMS.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        $sms = new SmsService();
        if (!$sms->estConfigure()) {
            Session::flash('error', 'La passerelle SMS n\'est pas configurée (SMS_DRIVER / SMS_TWILIO_* dans .env).');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        $resultat = $sms->envoyer(
            $numero,
            (string)($_POST['message'] ?? ''),
            $id,
            $this->etabId,
            (int)(Session::gestionnaireId() ?? 0)
        );

        if ($resultat['success']) {
            JournalGestionnaire::log('candidature.sms', 'dossier', $id,
                'SMS envoyé au candidat du dossier #' . $id . ' (' . $numero . ')');
            Session::flash('success', $resultat['statut'] === 'simule'
                ? "SMS simulé (mode développement) — journalisé pour {$numero}."
                : "SMS envoyé au candidat ({$numero}).");
        } else {
            Session::flash('error', 'SMS non envoyé : ' . ($resultat['erreur'] ?? 'erreur inconnue'));
        }
        Response::redirect('/gestionnaire/candidatures/' . $id);
    }

    /**
     * Message in-app au candidat — même mécanique que le super admin
     * (Admin\UsersController::sendMessage) : cloche navbar du compte
     * (type "announcement", actor NULL = message officiel) + page
     * Notifications de l'espace élève orientation.
     */
    public function envoyerMessage(int $id): void
    {
        $dossier = (new Dossier())->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            Session::flash('error', 'Le message est vide.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }
        if (mb_strlen($message) > 500) {
            Session::flash('error', 'Message trop long (500 caractères max).');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        $userId = (int)($dossier['eleve_id'] ?? 0);
        if ($userId <= 0) {
            Session::flash('error', 'Ce candidat n\'a pas de compte Connect\'Academia — utilisez le SMS pour le contacter.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        $etabNom = (string)($dossier['etab_nom'] ?? 'Votre établissement');

        // Cloche navbar (compte communauté / hub) — actor_id NULL = message officiel
        (new Notification())->create([
            'user_id' => $userId,
            'type'    => 'announcement',
            'message' => mb_substr($etabNom . ' : ' . $message, 0, 500),
        ]);

        // Espace élève orientation (page Notifications) — même message
        (new NotificationOrientation())->create([
            'user_id'          => $userId,
            'etablissement_id' => $this->etabId,
            'type'             => 'message_etablissement',
            'titre'            => 'Message de ' . $etabNom,
            'message'          => $message,
        ]);

        // Doublon WhatsApp — les candidats sont bien plus joignables sur WhatsApp.
        // On génère un lien « click-to-chat » wa.me pré-rempli : le message in-app
        // est déjà délivré ; le gestionnaire achève l'envoi dans WhatsApp d'un clic.
        // On vise le numéro RENSEIGNÉ À LA CRÉATION DU COMPTE (users.telephone),
        // avec repli sur le numéro du dossier si le compte n'en a pas.
        $wa = $this->lienWhatsApp($userId, $dossier, $etabNom, $message);
        if (!empty($wa['url'])) {
            Session::flash('wa_url', $wa['url']);
        }

        JournalGestionnaire::log('candidature.message', 'dossier', $id,
            'Message envoyé à ' . (string)($dossier['nom_complet'] ?? 'candidat') . ' (dossier #' . $id . ')' . $wa['journal']);

        Session::flash('success', 'Message envoyé à ' . ($dossier['nom_complet'] ?? 'ce candidat')
            . ' — il le recevra dans les notifications de son compte.' . $wa['flash']);
        Response::redirect('/gestionnaire/candidatures/' . $id);
    }

    /**
     * Construit un lien WhatsApp « click-to-chat » (wa.me) pré-rempli avec le
     * message, à destination du numéro du candidat. N'envoie rien : c'est le
     * gestionnaire qui valide l'envoi dans WhatsApp (aucune API requise, donc
     * plus de « simulation » — le message part réellement).
     *
     * @return array{url: ?string, flash: string, journal: string}
     */
    private function lienWhatsApp(int $userId, array $dossier, string $etabNom, string $message): array
    {
        // 1. Numéro du compte (users.telephone, format national gabonais 0XXXXXXXX)
        $numeroCompte = null;
        try {
            $st = Database::getInstance()->getConnection()->prepare('SELECT telephone FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $numeroCompte = (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            error_log('[WhatsApp] Lecture numéro compte impossible : ' . $e->getMessage());
        }

        $numeroE164 = null;
        if (!empty($numeroCompte)) {
            $numeroE164 = SmsService::normaliserNumero('+241', $numeroCompte);
        }
        // Repli : numéro du dossier
        if ($numeroE164 === null) {
            $numeroE164 = SmsService::normaliserNumero(
                (string)($dossier['indicatif_tel'] ?? '+241'),
                (string)($dossier['telephone'] ?? '')
            );
        }

        if ($numeroE164 === null) {
            return [
                'url'     => null,
                'flash'   => ' (WhatsApp indisponible : aucun numéro valide au compte du candidat.)',
                'journal' => ' — WhatsApp indisponible (numéro absent)',
            ];
        }

        // wa.me exige le numéro international SANS le « + », message url-encodé.
        $numeroWa = preg_replace('/\D+/', '', $numeroE164);
        $texte    = $etabNom . " (Connect'Academia)\n\n" . $message;
        $url      = 'https://wa.me/' . $numeroWa . '?text=' . rawurlencode($texte);

        return [
            'url'     => $url,
            'flash'   => ' Ouvre WhatsApp pour finaliser l\'envoi au candidat.',
            'journal' => ' — lien WhatsApp généré (' . $numeroE164 . ')',
        ];
    }

    /**
     * Attestation de préinscription — PDF généré à la demande.
     * Regénérée à la demande depuis la base : design et logo toujours à jour.
     */
    public function recepisse(int $id): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $etab      = (new Etablissement())->findById($this->etabId) ?? [];
        $recepisse = $dossierModel->getRecepisse($id);

        if ($recepisse) {
            // Déjà émis : réimpression sans aucune limite, quel que soit l'état
            // actuel de l'option (un document officiel délivré reste délivré).
            $numero = (string)$recepisse['numero_recepisse'];
            try {
                $dateEmission = new \DateTime((string)$recepisse['date_emission']);
            } catch (\Throwable) {
                $dateEmission = new \DateTime();
            }
        } else {
            // Première émission : soumise au paramètre de l'établissement.
            if ((int)($etab['genere_recepisse'] ?? 1) !== 1) {
                Session::flash('error', 'La génération d\'attestation de préinscription est désactivée dans les paramètres de votre établissement.');
                Response::redirect('/gestionnaire/candidatures/' . $id);
                return;
            }
            $numero       = $dossierModel->reserveRecepisse($id);
            $dateEmission = new \DateTime();
        }

        $token = $dossierModel->tokenVerificationRecepisse($id);
        $svc   = new RecepisseService();

        PdfStream::emettre(
            $svc->renderPdf($dossier, $etab, $numero, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    public function updateStatut(int $id): void
    {
        $statut = $_POST['statut'] ?? '';
        $model  = new Dossier();

        if (!$model->updateStatutByEtab($id, $this->etabId, $statut)) {
            Session::flash('error', 'Statut invalide.');
        } else {
            $message = 'Statut mis à jour.';
            // Validation d'une inscription → attestation officielle délivrée.
            // delivrerAttestation ne fait rien pour une préinscription (pas de
            // ligne inscriptions) : celle-ci n'exige aucune validation.
            if (in_array($statut, ['inscrit','accepte'], true) && $this->delivrerAttestation($id)) {
                $message = 'Inscription validée — attestation d\'inscription délivrée au candidat.';
            }
            $this->notifierCandidatStatut($id, $statut, $model);
            JournalGestionnaire::log('candidature.statut', 'dossier', $id,
                'Statut du dossier #' . $id . ' changé en « ' . $statut . ' »');
            Session::flash('success', $message);
        }
        Response::redirect('/gestionnaire/candidatures/' . $id);
    }

    /** Notification in-app au candidat (espace élève) lors d'un changement de statut. */
    private function notifierCandidatStatut(int $dossierId, string $statut, Dossier $model): void
    {
        $dossier = $model->findById($dossierId);
        if (!$dossier || empty($dossier['eleve_id'])) {
            return;
        }

        $etabNom        = $dossier['etab_nom'] ?? '';
        $estInscription = $model->hasInscription($dossierId);

        [$type, $titre, $message] = match ($statut) {
            'inscrit' => ['inscription_validee', 'Inscription validée',
                   'Félicitations ! ' . $etabNom . ' a validé votre inscription — votre attestation d\'inscription est disponible dans votre espace.'],
            'accepte' => $estInscription
                ? ['inscription_validee', 'Inscription validée',
                   'Félicitations ! ' . $etabNom . ' a validé votre dossier — votre attestation d\'inscription est disponible dans votre espace.']
                : ['preinscription_acceptee', 'Préinscription acceptée',
                   $etabNom . ' a accepté votre préinscription.'],
            'refuse'  => ['dossier_refuse', 'Dossier refusé',
                   'Votre dossier auprès de ' . $etabNom . ' n\'a pas été retenu.'],
            'paye'    => ['paiement_confirme', 'Paiement confirmé',
                   'Votre paiement auprès de ' . $etabNom . ' a été confirmé.'],
            default   => [null, null, null],
        };

        if ($type === null) {
            return;
        }

        (new NotificationOrientation())->create([
            'user_id'          => (int)$dossier['eleve_id'],
            'etablissement_id' => (int)$dossier['etablissement_id'],
            'type'             => $type,
            'titre'            => $titre,
            'message'          => $message,
        ]);
    }

    /**
     * Attestation d'inscription — PDF généré à la demande.
     * Regénérée à la demande depuis la base : design et logo toujours à jour.
     * Réservée aux dossiers d'inscription validés (statut "accepte").
     */
    public function attestation(int $id): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $etab        = (new Etablissement())->findById($this->etabId) ?? [];
        $attestation = $dossierModel->getAttestation($id);

        if ($attestation) {
            // Déjà émise : réimpression illimitée, même si le statut a changé
            // depuis (le document officiel reste archivé et téléchargeable).
            $numero = (string)$attestation['numero_attestation'];
            try {
                $dateEmission = new \DateTime((string)$attestation['date_emission']);
            } catch (\Throwable) {
                $dateEmission = new \DateTime();
            }
        } else {
            // Première émission : dossier validé requis + option activée.
            if (!in_array($dossier['statut'], ['inscrit','accepte'], true)) {
                Session::flash('error', 'L\'attestation n\'est délivrée qu\'après validation de l\'inscription (statut « Inscrit »).');
                Response::redirect('/gestionnaire/candidatures/' . $id);
                return;
            }
            if ((int)($etab['genere_attestation'] ?? 1) !== 1) {
                Session::flash('error', 'La génération d\'attestation d\'inscription est désactivée dans les paramètres de votre établissement.');
                Response::redirect('/gestionnaire/candidatures/' . $id);
                return;
            }
            $numero       = $dossierModel->reserveAttestation($id);
            $dateEmission = new \DateTime();
        }

        $token = $dossierModel->tokenVerificationAttestation($id);
        $svc   = new \Services\Orientation\AttestationService();

        PdfStream::emettre(
            $svc->renderPdf($dossier, $etab, $numero, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Délivre l'attestation d'inscription après validation : numéro attribué,
     * HTML autonome archivé sur B2, e-mail au candidat. Idempotent.
     * Retourne true si une attestation existe (créée maintenant ou avant).
     */
    private function delivrerAttestation(int $dossierId): bool
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($dossierId);
        if (!$dossier) {
            return false;
        }

        // L'attestation ne concerne que les dossiers d'inscription — un dossier
        // de préinscription accepté conserve son simple récépissé.
        if (!$dossierModel->hasInscription($dossierId)) {
            return false;
        }

        if ($dossierModel->getAttestation($dossierId)) {
            return true; // déjà délivrée (revalidation)
        }

        $etab = (new Etablissement())->findById($this->etabId) ?? [];

        // Option désactivée par l'établissement : aucune attestation auto-délivrée.
        if ((int)($etab['genere_attestation'] ?? 1) !== 1) {
            return false;
        }

        try {
            // Numéro réservé atomiquement (ligne créée d'abord) — anti-collision
            $numero = $dossierModel->reserveAttestation($dossierId);
            $html   = (new \Services\Orientation\AttestationService())->render($dossier, $etab, $numero, null,
                $dossierModel->tokenVerificationAttestation($dossierId));

            $cle = sprintf('attestations/%d/%s.html', $dossierId, $numero);
            try {
                (new DocumentStorage())->uploadContent($html, $cle);
                $dossierModel->updateAttestationCle($dossierId, $cle);
            } catch (\Throwable $e) {
                // Stockage indisponible : numéro conservé, fichier regénéré à la demande
                error_log('[Attestation] Upload B2 échoué dossier #' . $dossierId . ' : ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('[Attestation] Génération échouée dossier #' . $dossierId . ' : ' . $e->getMessage());
            return false;
        }

        $this->envoyerEmailValidation($dossier, $etab, $numero);
        return true;
    }

    /** E-mail candidat : dossier validé, attestation disponible dans l'espace. */
    private function envoyerEmailValidation(array $dossier, array $etab, string $numero): void
    {
        $email = trim((string)($dossier['email'] ?? ''));
        if ($email === '') {
            return;
        }

        $nom      = $dossier['nom_complet'] ?? 'Candidat';
        $etabNom  = $etab['nom'] ?? '—';
        $couleurH = htmlspecialchars($etab['couleur_principale'] ?? '#8B52FA', ENT_QUOTES, 'UTF-8');
        $etabNomH = htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8');
        $nomH     = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
        $numeroH  = htmlspecialchars($numero, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,Helvetica,Arial,sans-serif;background:#f4f4f9;margin:0;padding:32px 16px">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">'
            . '<div style="background:' . $couleurH . ';padding:32px 36px">'
            . '<h1 style="color:#fff;font-size:20px;font-weight:800;margin:0">Inscription validée 🎉</h1>'
            . '<p style="color:rgba(255,255,255,.85);font-size:14px;margin:8px 0 0">' . $etabNomH . '</p>'
            . '</div>'
            . '<div style="padding:32px 36px">'
            . '<p style="font-size:15px;color:#333;margin-bottom:20px">Bonjour <strong>' . $nomH . '</strong>,<br><br>'
            . 'Excellente nouvelle : votre dossier d\'inscription à <strong>' . $etabNomH . '</strong> a été <strong>validé</strong>.</p>'
            . '<p style="font-size:14px;color:#333;margin-bottom:24px">Votre attestation d\'inscription officielle <strong>' . $numeroH . '</strong> est disponible dans votre espace personnel Connect\'Academia.</p>'
            . '<p style="margin:0 0 24px"><a href="https://connectacademia.io/eleve/orientation" style="background:' . $couleurH . ';color:#fff;text-decoration:none;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:700;display:inline-block">Télécharger mon attestation</a></p>'
            . '<p style="font-size:13px;color:#666;border-top:1px solid #eee;padding-top:20px">L\'établissement vous contactera pour la suite du processus (rentrée, frais, documents complémentaires).</p>'
            . '<p style="font-size:12px;color:#aaa;margin-top:24px">Connect\'Academia · <a href="https://connectacademia.io" style="color:#aaa">connectacademia.io</a></p>'
            . '</div></div></body></html>';

        try {
            \Core\Queue::push('send_email', [
                'to'      => $email,
                'toName'  => $nom,
                'subject' => "Inscription validée — {$etabNom}",
                'html'    => $html,
            ]);
        } catch (\Throwable $e) {
            error_log('[Attestation] Email validation échoué : ' . $e->getMessage());
        }
    }

    public function create(): void
    {
        $type        = ($_GET['type'] ?? '') === 'inscription' ? 'inscription' : 'preinscription';
        $etab        = (new Etablissement())->findById($this->etabId);
        $formations  = (new Formation())->findByEtab($this->etabId);
        $pieces      = $type === 'inscription' ? (new PieceRequise())->findByEtab($this->etabId) : [];
        $filieresCfg = $this->filieresEtab();

        Response::view('gestionnaire/candidatures/create', [
            'pageTitle'   => $type === 'inscription'
                ? 'Nouvelle inscription — guichet'
                : 'Nouvelle préinscription — guichet',
            'typeDossier' => $type,
            'etab'        => $etab,
            'formations'  => $formations,
            'pieces'      => $pieces,
            'filieres'    => $filieresCfg['options'],
            'specialites' => $filieresCfg['specialites'],
            'errors'      => Session::getFlash('form_errors') ?? [],
            'old'         => Session::getFlash('form_old') ?? [],
        ], 'gestionnaire');
    }

    public function store(): void
    {
        $type   = ($_POST['type_dossier'] ?? '') === 'inscription' ? 'inscription' : 'preinscription';
        $errors = [];
        $nom    = trim($_POST['nom_complet']    ?? '');
        // E-mail normalisé (minuscules) : sert de clé d'identité du candidat —
        // rattachement du compte, unicité dans l'établissement, envoi des mails.
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $tel    = trim($_POST['telephone']      ?? '');
        $genre  = $_POST['genre']               ?? '';
        $naiss  = trim($_POST['date_naissance'] ?? '');
        $lieu   = trim($_POST['lieu_naissance'] ?? '');
        $nation = trim($_POST['nationalite']    ?? '');

        if ($nom    === '') $errors['nom_complet']   = 'Obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide.';
        if ($tel    === '') $errors['telephone']     = 'Obligatoire.';
        if (!in_array($genre, ['homme','femme'], true)) $errors['genre'] = 'Obligatoire.';
        if ($naiss  === '') $errors['date_naissance']= 'Obligatoire.';
        if ($lieu   === '') $errors['lieu_naissance']= 'Obligatoire.';
        if ($nation === '') $errors['nationalite']   = 'Obligatoire.';

        // Unicité de l'e-mail DANS l'établissement : un même candidat ne peut
        // pas avoir deux dossiers (donc deux identités) au guichet. Requête
        // bornée à $this->etabId — aucune fuite sur les autres établissements.
        if (!isset($errors['email'])) {
            if ((new Dossier())->findByEmailInEtab($email, $this->etabId)) {
                $errors['email'] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        // Filière / spécialité (filière obligatoire pour une inscription)
        [$errFiliere, $specialite] = $this->validerFiliereSpecialite(
            $this->filieresEtab(),
            (string)($_POST['filiere_visee'] ?? ''),
            trim((string)($_POST['specialite_visee'] ?? '')),
            $type === 'inscription'
        );
        $errors = array_merge($errors, $errFiliere);

        // Une inscription au guichet exige un paiement hors ligne
        $montant = (int)($_POST['montant_paiement'] ?? 0);
        $mode    = $_POST['mode_paiement'] ?? '';
        if ($type === 'inscription') {
            if ($montant <= 0) {
                $errors['montant_paiement'] = 'Montant obligatoire pour une inscription.';
            }
            if (!in_array($mode, ['espece','virement','mobile_money'], true)) {
                $errors['mode_paiement'] = 'Mode de paiement obligatoire.';
            }
        }

        // Compte espace candidat (optionnel) : mot de passe défini au guichet
        $comptePassword = (string)($_POST['compte_password'] ?? '');
        $compteConfirm  = (string)($_POST['compte_password_confirm'] ?? '');
        if ($comptePassword !== '' || $compteConfirm !== '') {
            if (strlen($comptePassword) < 8 || strlen($comptePassword) > 128) {
                $errors['compte_password'] = 'Mot de passe : 8 caractères minimum.';
            } elseif ($comptePassword !== $compteConfirm) {
                $errors['compte_password_confirm'] = 'La confirmation ne correspond pas au mot de passe.';
            } elseif (!isset($errors['email']) && (new User())->findByEmail($email)) {
                // Un compte Connect'Academia existe déjà pour cet e-mail : on ne
                // crée pas de second compte et on ne touche jamais au mot de
                // passe existant. Message explicite plutôt qu'un silence.
                $errors['compte_password'] = 'Un compte existe déjà avec cet e-mail. '
                    . 'Laissez les champs mot de passe vides : le dossier sera rattaché à ce compte.';
            }
        }

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('form_old', self::oldSansMotDePasse($_POST));
            Response::redirect('/gestionnaire/candidatures/creer?type=' . $type);
            return;
        }

        $dossierModel = new Dossier();
        $agentId      = Session::gestionnaireId() ?? 0;

        $dossierId = $dossierModel->createGuichet([
            'nom_complet'          => $nom,
            'email'                => $email,
            'telephone'            => $tel,
            'indicatif_tel'        => $_POST['indicatif_tel']        ?? '+241',
            'genre'                => $genre,
            'date_naissance'       => $naiss,
            'lieu_naissance'       => $lieu,
            'nationalite'          => $nation,
            'adresse'              => trim($_POST['adresse']          ?? '') ?: null,
            'ville'                => trim($_POST['ville']            ?? '') ?: null,
            'pays_provenance'      => trim($_POST['pays_provenance']  ?? '') ?: null,
            'dernier_etablissement'=> trim($_POST['dernier_etablissement'] ?? '') ?: null,
            'filiere_visee'        => $_POST['filiere_visee']         ?? null,
            'specialite_visee'     => $specialite ?: null,
            'niveau_entree'        => trim($_POST['niveau_entree']    ?? '') ?: null,
        ], $this->etabId, (int)$agentId);

        // Compte espace candidat : créé seulement si un mot de passe a été défini
        $noteCompte = '';
        if ($comptePassword !== '') {
            $noteCompte = $this->creerCompteCandidat($nom, $email, $comptePassword, $dossierId);
        }

        // ── Préinscription au guichet : récépissé officiel, aucun paiement ──
        if ($type === 'preinscription') {
            $etab = (new Etablissement())->findById($this->etabId);
            if ($etab && (int)($etab['genere_recepisse'] ?? 0) === 1) {
                $this->genererRecepisseGuichet($dossierId, $etab, $_POST);
            }
            JournalGestionnaire::log('candidature.creation', 'dossier', $dossierId,
                'Préinscription #' . $dossierId . ' enregistrée au guichet — ' . $nom);
            Session::flash('success', "Préinscription #{$dossierId} enregistrée au guichet." . $noteCompte);
            Response::redirect('/gestionnaire/candidatures/' . $dossierId);
            return;
        }

        // ── Inscription au guichet : pièces éventuelles + paiement hors ligne ──
        $this->enregistrerPiecesGuichet($dossierId);

        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        try {
            $dossierModel->createPaiementHorsligne(
                $dossierId, $this->etabId, $montant, $mode, (int)$agentId
            );
            $dossierModel->createInscription($dossierId, $this->etabId);
            $dossierModel->updateStatut($dossierId, 'paye');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Guichet] Échec paiement hors ligne dossier #' . $dossierId . ': ' . $e->getMessage());
            Session::flash('error', 'Dossier créé, mais le paiement n\'a pas pu être enregistré. Réessayez depuis la fiche du dossier.');
            Response::redirect('/gestionnaire/candidatures/' . $dossierId);
            return;
        }

        JournalGestionnaire::log('candidature.creation', 'dossier', $dossierId,
            'Inscription #' . $dossierId . ' enregistrée au guichet — ' . $nom
            . ' (paiement ' . number_format($montant, 0, ',', ' ') . ' XAF)');
        Session::flash('success', "Inscription #{$dossierId} enregistrée au guichet — paiement de " . number_format($montant, 0, ',', ' ') . ' XAF confirmé.' . $noteCompte);
        Response::redirect('/gestionnaire/candidatures/' . $dossierId);
    }

    // ── Privé : compte espace candidat créé au guichet ───────────────────────

    /**
     * Crée le compte « visiteur » du candidat avec le mot de passe défini par
     * le gestionnaire, puis rattache ses dossiers (eleve_id) par email.
     * Ne modifie JAMAIS le mot de passe d'un compte existant.
     * Retourne une note à concaténer au message de succès.
     */
    private function creerCompteCandidat(string $nomComplet, string $email, string $password, int $dossierId): string
    {
        $emailNorm = strtolower($email);

        try {
            $userModel = new User();
            $existant  = $userModel->findByEmail($emailNorm);

            if ($existant) {
                (new Dossier())->claimByEmail((int)$existant['id'], $emailNorm);
                return " Un compte existait déjà avec cet email : le dossier y a été rattaché, le mot de passe existant reste inchangé.";
            }

            // nom_complet → prénom = premier mot, nom = le reste
            $parts  = preg_split('/\s+/', trim($nomComplet), 2) ?: [];
            $prenom = $parts[0] ?? $nomComplet;
            $nomFam = $parts[1] ?? $prenom;

            $userId = $userModel->create([
                'nom'      => mb_substr($nomFam, 0, 100),
                'prenom'   => mb_substr($prenom, 0, 100),
                'email'    => $emailNorm,
                'password' => $password,
                'role'     => 'visiteur',
                'serie_id' => null,
            ]);
            (new Dossier())->claimByEmail($userId, $emailNorm);

            return " Compte candidat créé : connexion avec {$emailNorm} et le mot de passe défini.";
        } catch (\Throwable $e) {
            error_log('[Guichet] Création compte candidat échouée dossier #' . $dossierId . ' : ' . $e->getMessage());
            Session::flash('error', "Le dossier est enregistré, mais le compte candidat n'a pas pu être créé. Le candidat pourra créer son espace lui-même sur /orientation/compte avec le même email.");
            return '';
        }
    }

    private static function oldSansMotDePasse(array $post): array
    {
        unset($post['compte_password'], $post['compte_password_confirm']);
        return $post;
    }

    // ── Privé : pièces justificatives déposées au guichet ────────────────────

    private function enregistrerPiecesGuichet(int $dossierId): void
    {
        if (empty($_FILES['pieces']['name']) || !is_array($_FILES['pieces']['name'])) {
            return;
        }

        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
        ];
        $dossierModel = new Dossier();
        $pieceModel   = new PieceRequise();
        $storage      = null;
        $finfo        = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($_FILES['pieces']['name'] as $pieceId => $name) {
            if (($_FILES['pieces']['error'][$pieceId] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp  = $_FILES['pieces']['tmp_name'][$pieceId];
            $size = (int)($_FILES['pieces']['size'][$pieceId] ?? 0);
            if ($size <= 0 || $size > 10 * 1024 * 1024) {
                continue;
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowed[$mime])) {
                continue;
            }

            $piece   = $pieceModel->findById((int)$pieceId, $this->etabId);
            $libelle = $piece['libelle'] ?? ('Pièce #' . (int)$pieceId);
            $cle     = sprintf(
                'dossiers/%d/pieces/%d-%s.%s',
                $dossierId, (int)$pieceId, bin2hex(random_bytes(4)), $allowed[$mime]
            );

            try {
                $storage ??= new DocumentStorage();
                $storage->upload($tmp, $cle, $mime);
                $dossierModel->addDocument([
                    'dossier_id'       => $dossierId,
                    'eleve_id'         => 0,
                    'piece_requise_id' => (int)$pieceId,
                    'libelle'          => $libelle,
                    'cle_b2'           => $cle,
                    'mime_type'        => $mime,
                    'taille_octets'    => $size,
                ]);
            } catch (\Throwable $e) {
                error_log('[Guichet] Upload pièce échoué dossier #' . $dossierId . ' : ' . $e->getMessage());
            }
        }
    }

    // ── Privé : récépissé de préinscription (même gabarit que le flux en ligne) ─

    private function genererRecepisseGuichet(int $dossierId, array $etab, array $p): void
    {
        $dossierModel = new Dossier();

        try {
            // Numéro réservé atomiquement (ligne créée d'abord) — anti-collision
            $numero = $dossierModel->reserveRecepisse($dossierId);
            $svc    = new RecepisseService();
            $token  = $dossierModel->tokenVerificationRecepisse($dossierId);
            $pdf    = $svc->renderPdf($p, $etab, $numero, null, $token);

            // Archive PDF (document identique à celui téléchargé) ; repli HTML
            // si dompdf échoue, pour ne jamais perdre l'archive.
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
                (new DocumentStorage())->uploadContent($contenu, $cle, $mime);
                $dossierModel->updateRecepisseCle($dossierId, $cle);
            } catch (\Throwable $e) {
                // Stockage indisponible : le numéro reste enregistré, le fichier sera regénéré
                error_log('[Guichet] Upload récépissé échoué dossier #' . $dossierId . ' : ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('[Guichet] Génération récépissé échouée dossier #' . $dossierId . ' : ' . $e->getMessage());
        }
    }
}
