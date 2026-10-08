<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Database, Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, Dossier, Filiere, Formation, PieceRequise, NotificationOrientation};
use Models\Notification;
use Models\User;
use Services\Orientation\{DocumentStorage, JournalGestionnaire, PdfStream, PieceDossier, PolitiqueBoursiers, RecepisseService, SituationBourse, SmsService, UniciteInscription, WhatsAppService};

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

    /**
     * Liste des dossiers — vue unique filtrable (spec §8.1).
     *
     * Les filtres sont persistés en session : un agent qui ouvre une fiche
     * puis revient retrouve exactement la même liste, sans refaire ses
     * réglages vingt fois par jour.
     */
    public function index(): void
    {
        $etab  = (new Etablissement())->findById($this->etabId);
        $model = new Dossier();

        $filtres = $this->filtresPersistants();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $parPage = 50;

        $total = $model->countByEtabFiltre($this->etabId, $filtres);
        $pages = max(1, (int)ceil($total / $parPage));
        $page  = min($page, $pages);

        Response::view('gestionnaire/candidatures/index', [
            'pageTitle'  => 'Dossiers — ' . ($etab['nom'] ?? ''),
            'etab'       => $etab,
            'dossiers'   => $model->findByEtabFiltre($this->etabId, $filtres, $parPage, ($page - 1) * $parPage),
            'compteurs'  => $model->compteursParStatut($this->etabId, $filtres),
            // Onglets préinscriptions / inscriptions : deux parcours distincts,
            // deux listes. Mélangés, l'agent applique à une préinscription les
            // gestes de l'inscription.
            'compteursType' => $model->compteursParType($this->etabId, $filtres),
            'filieres'   => $model->filieresPresentes($this->etabId),
            'filtres'    => $filtres,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'flash'      => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    /**
     * Filtres de la liste, mémorisés en session (spec §8.1 : « filtres
     * persistants »). `?reset=1` les efface.
     *
     * @return array<string,string>
     */
    private function filtresPersistants(): array
    {
        $cle = 'gest_filtres_dossiers_' . $this->etabId;

        if (isset($_GET['reset'])) {
            Session::remove($cle);

            return ['type' => '', 'statut' => '', 'filiere' => '', 'statut_bourse' => '', 'du' => '', 'au' => '', 'q' => '', 'tri' => ''];
        }

        $champs   = ['type', 'statut', 'filiere', 'statut_bourse', 'du', 'au', 'q', 'tri'];
        $memorise = Session::get($cle, []);
        $memorise = is_array($memorise) ? $memorise : [];

        // Une requête sans aucun paramètre de filtre est une simple navigation :
        // on restitue alors ce qui était mémorisé plutôt que de tout effacer.
        $recus = array_intersect_key($_GET, array_flip($champs));

        $filtres = [];
        foreach ($champs as $champ) {
            $valeur = $recus !== []
                ? trim((string)($_GET[$champ] ?? ''))
                : trim((string)($memorise[$champ] ?? ''));

            // Bornage : ces valeurs partent dans des requêtes préparées, mais
            // aussi dans les liens de pagination et le HTML de la page.
            $filtres[$champ] = mb_substr($valeur, 0, 120);
        }

        Session::set($cle, $filtres);

        return $filtres;
    }

    /**
     * Fiche dossier — trois zones : identité, pièces, décision (spec §8.2).
     */
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

        // Consultation des pièces : lien INTERNE, plus d'URL signée Backblaze.
        //
        // Les pièces sont chiffrées au repos (AES-256-GCM) : un lien pointant
        // directement sur le bucket rendrait une enveloppe binaire illisible.
        // Mais ce n'est pas la seule raison du changement — une URL signée
        // restait valable hors session pendant toute sa durée de vie, copiable
        // et transmissible. Le lien interne, lui, refait le contrôle
        // d'appartenance à CHAQUE ouverture (cf. self::document()).
        $documents  = $dossierModel->getDocumentsByDossier($id);
        $signedUrls = [];
        foreach ($documents as $doc) {
            $signedUrls[$doc['id']] = !empty($doc['cle_b2'])
                ? url('/gestionnaire/candidatures/' . $id . '/document/' . (int)$doc['id'])
                : null;
        }

        $etab   = (new Etablissement())->findById($this->etabId);
        $sms    = new SmsService();
        $statut = \Models\Orientation\DossierStatut::normaliser((string)$dossier['statut']);

        // Commission et son échéancier : l'établissement doit voir où en est le
        // recouvrement sur CE dossier, sans quitter la fiche.
        $commission = \Services\Orientation\CommissionDossier::pourDossier($id);
        $tranches   = $commission !== null
            ? \Services\Orientation\CommissionDossier::tranches((int)$commission['id'])
            : [];

        Response::view('gestionnaire/candidatures/show', [
            'pageTitle'      => 'Dossier ' . ($dossier['reference'] ?? '#' . $id),
            'etab'           => $etab,
            'dossier'        => $dossier,
            'statut'         => $statut,
            'documents'      => $documents,
            'signedUrls'     => $signedUrls,
            'recepisse'      => $dossierModel->getRecepisse($id),
            'attestation'    => $dossierModel->getAttestation($id),
            // Nature du dossier — règle unique du modèle. hasInscription() ne
            // regardait que la table `inscriptions`, remplie à la validation à
            // 100 % : un dossier d'inscription en cours passait donc pour une
            // préinscription, et une préinscription héritait de tout le
            // parcours d'inscription (paliers 50 %, tarifs, commission).
            'estInscription' => \Models\Orientation\Dossier::estInscription($dossier),
            'filieres'       => self::FILIERES,
            'smsHistorique'  => $sms->historique($id, 5),
            'smsSimulation'  => $sms->estSimulation(),

            // ── Zone décision (spec §8.2) ──
            // Les actions proposées viennent de la machine à états : l'écran ne
            // décide de rien, il montre ce qui est réellement permis.
            'actions'        => self::actionsSelonNature($dossierModel->transitionsPossibles($dossier, 'etablissement'), $dossier),
            'modesPaiement'  => \Services\Orientation\DecisionEtablissement::modesPaiement(),
            'scolarite'      => \Services\Orientation\DecisionEtablissement::scolaritePreremplie($dossier),
            'fraisProposes'  => \Services\Orientation\DecisionEtablissement::fraisInscriptionPreremplis($etab, $dossier),
            // Tarif APPLIQUÉ (cycle, montant, provenance) : l'agent constate,
            // il ne saisit plus. Le montant vient de la fiche établissement
            // remplie par le super administrateur.
            'tarif'          => \Services\Orientation\DecisionEtablissement::tarifApplicable($dossier, $etab),
            'commission'     => $commission,
            'tranches'       => $tranches,
            'tauxCourant'    => $dossierModel->tauxCommissionApplicable($this->etabId),

            // ── Traçabilité ──
            'historique'     => $this->historiqueStatuts($id),
            'histBourse'     => \Services\Orientation\SituationBourse::historique($id),
            'scans'          => \Services\Orientation\ScanQr::pourDossier($id, 10),
            'jeton'          => \Services\Orientation\JetonDossier::actif($id),

            'erreurs100'     => Session::getFlash('erreurs_100', []),
            'old100'         => Session::getFlash('old_100', []),
            'flash'          => Session::getFlash('success'),
            'flashError'     => Session::getFlash('error'),
            'waUrl'          => Session::getFlash('wa_url'),
        ], 'gestionnaire');
    }

    /**
     * Historique des changements de statut, du plus récent au plus ancien.
     *
     * @return list<array<string,mixed>>
     */
    private function historiqueStatuts(int $dossierId): array
    {
        try {
            $st = \Core\Database::getInstance()->getConnection()->prepare(
                'SELECT h.*, u.nom AS acteur_nom, u.prenom AS acteur_prenom
                 FROM historique_statut_dossier h
                 LEFT JOIN users u ON u.id = h.acteur_id
                 WHERE h.dossier_id = ?
                 ORDER BY h.cree_le DESC, h.id DESC
                 LIMIT 50'
            );
            $st->execute([$dossierId]);

            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Gestionnaire] Historique illisible #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }
    }

    // ── Consultation d'une pièce justificative ───────────────────────────────

    /**
     * Sert une pièce du dossier à l'agent, en clair, après contrôle des droits.
     *
     * Remplace le lien Backblaze signé qui figurait sur la fiche. Deux gains,
     * dans cet ordre d'importance :
     *
     *  1. la pièce est chiffrée dans le bucket (AES-256-GCM, scellée sur sa clé
     *     de stockage) : sans passer par ici, personne ne la lit — pas même
     *     avec la clé d'API Backblaze ;
     *  2. l'appartenance du dossier à l'établissement est revérifiée à chaque
     *     ouverture, et le document est vérifié comme appartenant à CE dossier.
     *     Une URL signée, elle, survivait à la fin de la session et se
     *     transmettait par copier-coller.
     *
     * Le type MIME servi vient de la base, pas du bucket : le contenu stocké
     * étant une enveloppe binaire, B2 n'a plus d'opinion utile sur sa nature.
     * `Core\FichierBinaire` restreint ensuite ce qui peut s'afficher en ligne
     * (tout le reste part en téléchargement, jamais interprété par le
     * navigateur) et neutralise la CSP qui rendait les PDF en page blanche.
     */
    public function document(int $id, int $documentId): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.document', $id);
            http_response_code(404);
            Session::flash('error', 'Document introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        $doc = $dossierModel->findDocumentById($documentId);

        // Le document doit appartenir au dossier demandé : sans ce contrôle,
        // un agent pourrait lire n'importe quelle pièce de la plateforme en
        // gardant l'un de SES dossiers dans l'URL.
        if (!$doc || (int)($doc['dossier_id'] ?? 0) !== $id || empty($doc['cle_b2'])) {
            http_response_code(404);
            Session::flash('error', 'Document introuvable.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        try {
            $contenu = (new DocumentStorage())->getContentClair((string)$doc['cle_b2']);
        } catch (\Throwable $e) {
            error_log('[Gestionnaire] Lecture document #' . $documentId . ' échouée : ' . $e->getMessage());
            http_response_code(404);
            Session::flash('error', 'Ce document est momentanément indisponible.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        JournalGestionnaire::log(
            'candidature.piece.consultation',
            'dossier',
            $id,
            'Consultation de la pièce « ' . (string)($doc['libelle'] ?? 'document') . ' »',
            ['document_id' => $documentId]
        );

        \Core\FichierBinaire::emettre(
            $contenu,
            (string)($doc['mime_type'] ?? ''),
            (string)($doc['libelle'] ?? 'document'),
            true
        );
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
        $etab        = (new Etablissement())->findById($this->etabId);

        Response::view('gestionnaire/candidatures/edit', [
            'pageTitle'   => 'Modifier le dossier #' . $id,
            'etab'        => $etab,
            'dossier'     => $dossier,
            'filieres'    => $filieresCfg['options'],
            'specialites' => $filieresCfg['specialites'],
            // Situation de bourse : corrigeable au guichet, tracée au nom de
            // l'agent (SituationBourse::PAR_ETABLISSEMENT).
            'bourseActive'  => PolitiqueBoursiers::accepte($etab),
            'bourseOptions' => PolitiqueBoursiers::options($etab),
            'bourseNote'    => SituationBourse::NOTE_EXPLICATIVE,
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

        // Mêmes exigences qu'à la création : la modification ne doit pas être
        // une porte de sortie pour vider les champs qu'on vient de rendre
        // obligatoires.
        $scolarite = $this->validerScolarite($_POST);
        $errors    = array_merge($errors, $scolarite['erreurs']);

        // Situation de bourse — correction possible au guichet, tracée.
        $etab   = (new Etablissement())->findById($this->etabId);
        $post   = PolitiqueBoursiers::normaliserPost($etab, $_POST);
        $bourse = SituationBourse::valider($post);
        $errors = $errors + $bourse['erreurs'];

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
            // Le niveau est une liste fermée : on n'enregistre QUE le libellé
            // d'un code connu (L1 → M2). Une valeur forgée devient null plutôt
            // que d'entrer telle quelle — le niveau détermine un tarif, il ne
            // peut pas être saisi librement, même par un agent de guichet.
            'niveau_entree'        => \Services\Orientation\Niveau::libellePourCode((string)($_POST['niveau_entree'] ?? '')),
            'serie_bac'            => $scolarite['serie_bac'],
            'annee_bac'            => $scolarite['annee_bac'],
        ]);

        // La situation de bourse ne passe PAS par updateInfosByEtab : elle a sa
        // propre chaîne (historique, réévaluation de la base de commission), et
        // elle est idempotente — redéclarer la même chose n'écrit rien.
        if ($bourse['valeurs']['statut_bourse'] !== null) {
            SituationBourse::enregistrer(
                $id,
                $bourse['valeurs'],
                SituationBourse::PAR_ETABLISSEMENT,
                Session::gestionnaireId() ?: null,
                null,
                'Correction saisie au guichet.'
            );
        }

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

        // Même document qu'en ligne : attestation + facture proforma. La
        // proforma porte la composition du dossier d'inscription — l'imprimer
        // au guichet sans elle renverrait le candidat sans savoir quoi réunir.
        $numeroFacture = 'FAC-' . substr($numero, 4);

        PdfStream::emettre(
            $svc->renderPdfComplet($dossier, $etab, $numero, $numeroFacture, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Décisions réellement proposables, une fois la nature du dossier prise en
     * compte.
     *
     * La machine à états ignore volontairement la différence préinscription /
     * inscription : elle ne connaît que des états, et depuis « soumis » elle
     * ouvre les mêmes portes aux deux. Or une PRÉINSCRIPTION ne se décide pas :
     * elle n'engage rien, ne demande rien, et son attestation est délivrée dès
     * le dépôt. Il n'y a donc AUCUNE décision à prendre dessus — ni validation,
     * ni refus, ni demande de pièce.
     *
     * Le service refuse ces mêmes gestes côté serveur (DecisionEtablissement) :
     * cette liste ne fait que ne pas les proposer.
     *
     * @param list<string> $actions
     * @return list<string>
     */
    private static function actionsSelonNature(array $actions, array $dossier): array
    {
        return \Models\Orientation\Dossier::estInscription($dossier) ? $actions : [];
    }

    /**
     * Changement de statut hérité — conservé pour les préinscriptions saisies
     * avant la machine à états.
     *
     * Une INSCRIPTION ne passe plus par ici : ses états s'écrivent par
     * Dossier::transition(), qui seule gèle le taux de commission, date les
     * jalons et journalise. Y forcer « inscrit » depuis cette route créerait un
     * inscrit sans commission ni attestation.
     */
    public function updateStatut(int $id): void
    {
        $statut = $_POST['statut'] ?? '';
        $model  = new Dossier();

        $dossier = $model->findById($id);
        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        if (\Models\Orientation\Dossier::estInscription($dossier)) {
            Session::flash('error', 'Ce dossier est une inscription : utilisez la zone de décision de sa fiche.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        // Une préinscription n'a que trois états : reçue, acceptée, refusée.
        // « payé » et « inscrit » appartiennent au parcours d'inscription.
        if (!in_array($statut, ['soumis', 'accepte', 'refuse'], true)) {
            Session::flash('error', 'Statut invalide pour une préinscription.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

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
        $estInscription = Dossier::estInscription($dossier);

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
            // Une préinscription ne donne JAMAIS lieu à une attestation
            // d'inscription : elle n'inscrit personne. Le lien n'apparaît pas
            // sur sa fiche, mais l'URL reste devinable.
            if (!Dossier::estInscription($dossier)) {
                Session::flash('error', 'Ce dossier est une préinscription : seule l\'attestation de préinscription est délivrée.');
                Response::redirect('/gestionnaire/candidatures/' . $id);
                return;
            }
            // Première émission : dossier validé requis + option activée.
            if (!in_array($dossier['statut'], ['inscrit','accepte','valide_100'], true)) {
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
     * GET /gestionnaire/candidatures/{id}/recu
     *
     * Réimpression du reçu de règlement de scolarité au guichet. C'est là que
     * l'argent change de main : un reçu que seul le candidat peut sortir depuis
     * son espace ne sert à rien face à quelqu'un qui vient de payer en espèces.
     *
     * Le document est rendu à la demande depuis les colonnes du dossier : deux
     * impressions donnent le même reçu, au même numéro.
     */
    public function recu(int $id): void
    {
        $dossierModel = new Dossier();
        $dossier      = $dossierModel->findById($id);

        if (!$dossier || (int)$dossier['etablissement_id'] !== $this->etabId) {
            TenantGuard::denyEtab($dossier, $this->etabId, 'gestionnaire.dossier', $id);
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/gestionnaire/candidatures');
            return;
        }

        if (!\Services\Orientation\RecuInscriptionService::disponible($dossier)) {
            Session::flash('error', 'Le reçu n\'existe qu\'après la validation à 100 %, '
                . 'avec le montant encaissé renseigné.');
            Response::redirect('/gestionnaire/candidatures/' . $id);
            return;
        }

        $numero = \Services\Orientation\RecuInscriptionService::numero($dossier);

        \Services\Orientation\Journal::dossier(
            \Services\Orientation\Journal::RECU_TELECHARGE,
            $dossier,
            ['numero' => $numero],
            ['acteur_id' => Session::gestionnaireId(), 'acteur_role' => 'etablissement']
        );

        PdfStream::emettre(
            \Services\Orientation\RecuInscriptionService::rendre($id),
            static fn(): string => '<!doctype html><meta charset="utf-8">'
                . '<p style="font-family:sans-serif;padding:40px">Le reçu est en cours de '
                . 'génération. Réessayez dans quelques instants.</p>',
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

    /**
     * Champs de scolarité que le formulaire public rend obligatoires et que le
     * guichet laissait passer vides : provenance, adresse, dernier
     * établissement, niveau, série et année du bac.
     *
     * Le niveau reste une liste fermée (L1 → M2) : il détermine un tarif, il
     * ne se saisit pas librement, même par un agent.
     *
     * @param array<string,mixed> $post
     * @return array{erreurs:array<string,string>, serie_bac:?string, annee_bac:?int}
     */
    private function validerScolarite(array $post): array
    {
        $erreurs = [];

        foreach ([
            'pays_provenance'       => 'Pays de provenance obligatoire.',
            'ville'                 => 'Ville obligatoire.',
            'adresse'               => 'Adresse obligatoire.',
            'dernier_etablissement' => 'Dernier établissement obligatoire.',
        ] as $champ => $message) {
            if (trim((string)($post[$champ] ?? '')) === '') {
                $erreurs[$champ] = $message;
            }
        }

        if (\Services\Orientation\Niveau::libellePourCode((string)($post['niveau_entree'] ?? '')) === null) {
            $erreurs['niveau_entree'] = 'Niveau d’entrée obligatoire.';
        }

        $serie = trim((string)($post['serie_bac'] ?? ''));
        if ($serie === '') {
            $erreurs['serie_bac'] = 'Série du Bac obligatoire.';
        } elseif (mb_strlen($serie) > 20) {
            $erreurs['serie_bac'] = 'Série du Bac trop longue (20 caractères maximum).';
        }

        // Bornes identiques au champ public : une année hors plage est une
        // faute de frappe, pas une donnée à conserver.
        $anneeBrute = trim((string)($post['annee_bac'] ?? ''));
        $annee      = null;
        if ($anneeBrute === '') {
            $erreurs['annee_bac'] = 'Année d’obtention du Bac obligatoire.';
        } elseif (!ctype_digit($anneeBrute)
                  || (int)$anneeBrute < 1970
                  || (int)$anneeBrute > (int)date('Y')) {
            $erreurs['annee_bac'] = 'Année invalide (entre 1970 et ' . date('Y') . ').';
        } else {
            $annee = (int)$anneeBrute;
        }

        return [
            'erreurs'   => $erreurs,
            'serie_bac' => $serie !== '' ? $serie : null,
            'annee_bac' => $annee,
        ];
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
            // Situation de bourse : mêmes options qu'à l'étape 1 du parcours
            // public, filtrées par la politique de l'établissement.
            'bourseActive'  => PolitiqueBoursiers::accepte($etab),
            'bourseOptions' => PolitiqueBoursiers::options($etab),
            'bourseNote'    => SituationBourse::NOTE_EXPLICATIVE,
            'errors'      => Session::getFlash('form_errors') ?? [],
            'old'         => Session::getFlash('form_old') ?? [],
        ], 'gestionnaire');
    }

    public function store(): void
    {
        $type   = ($_POST['type_dossier'] ?? '') === 'inscription' ? 'inscription' : 'preinscription';
        $etab   = (new Etablissement())->findById($this->etabId);
        // Établissement fermé aux boursiers : toute déclaration est ramenée à
        // « non boursier » AVANT validation, exactement comme côté public.
        $post   = PolitiqueBoursiers::normaliserPost($etab, $_POST);
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

        // ── Une seule inscription par candidat et par année académique ─────
        // Contrôle posé AVANT le verrou d'e-mail générique : les deux
        // refuseraient ce dossier, mais celui-ci sait dire lequel et pourquoi,
        // et l'agent peut ouvrir le dossier existant au lieu de chercher.
        // Ne concerne QUE les inscriptions : une préinscription reste libre.
        if (!isset($errors['email']) && $type === 'inscription') {
            $existante = UniciteInscription::existante($this->etabId, null, $email);

            if ($existante !== null) {
                $errors['email'] = UniciteInscription::messageGuichet($existante);
            }
        }

        // ── Une seule inscription ABOUTIE par année, tous établissements ────
        // Le guichet est soumis à la même règle que le parcours en ligne, sinon
        // elle ne vaut rien : un candidat refusé sur le site se présenterait au
        // comptoir et obtiendrait le second dossier qu'on venait de lui
        // interdire. L'agent voit le nom de l'établissement d'accueil — il peut
        // vérifier et orienter, au lieu de conclure à une panne.
        if (!isset($errors['email']) && $type === 'inscription') {
            $ailleurs = UniciteInscription::inscriptionFinaliseeAilleurs($this->etabId, null, $email);

            if ($ailleurs !== null) {
                $errors['email'] = UniciteInscription::messageGuichetAutreEtablissement($ailleurs);
            }
        }

        // Unicité de l'e-mail DANS l'établissement : un même candidat ne peut
        // pas avoir deux dossiers (donc deux identités) au guichet. Requête
        // bornée à $this->etabId — aucune fuite sur les autres établissements.
        if (!isset($errors['email'])) {
            if ((new Dossier())->findByEmailInEtab($email, $this->etabId)) {
                $errors['email'] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        // ── Champs que le formulaire public exige déjà ──────────────────────
        // Le guichet les acceptait vides : un même candidat produisait donc un
        // dossier complet en ligne et un dossier troué au guichet — dont le
        // récépissé imprimait « — » sur la série et l'année du bac.
        $scolarite = $this->validerScolarite($_POST);
        $errors    = array_merge($errors, $scolarite['erreurs']);

        // ── Situation de bourse (spec §5.2) ─────────────────────────────────
        // Validée par le même service que le parcours public : une valeur
        // forgée ou une date ANBG aberrante est rejetée ici aussi.
        $bourse = SituationBourse::valider($post);
        $errors = $errors + $bourse['erreurs'];

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
            'niveau_entree'        => \Services\Orientation\Niveau::libellePourCode((string)($_POST['niveau_entree'] ?? '')),
            'serie_bac'            => $scolarite['serie_bac'],
            'annee_bac'            => $scolarite['annee_bac'],
        ], $this->etabId, (int)$agentId);

        // Situation de bourse : écrite APRÈS la création du dossier, par le
        // service qui trace l'historique. Rôle « établissement » et identité de
        // l'agent : la déclaration est celle du candidat, la saisie est la
        // nôtre, et les deux doivent rester distinguables (spec §18).
        if ($bourse['erreurs'] === [] && $bourse['valeurs']['statut_bourse'] !== null) {
            SituationBourse::enregistrer(
                $dossierId,
                $bourse['valeurs'],
                SituationBourse::PAR_ETABLISSEMENT,
                (int)$agentId ?: null,
                null,
                'Déclaration recueillie au guichet.'
            );
        }

        // Compte espace candidat : créé seulement si un mot de passe a été défini
        $noteCompte = '';
        if ($comptePassword !== '') {
            $noteCompte = $this->creerCompteCandidat($nom, $email, $comptePassword, $dossierId);
        }

        // ── Préinscription au guichet : récépissé officiel, aucun paiement ──
        if ($type === 'preinscription') {
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

        // Formats et plafond de taille : la règle est celle de PieceDossier, la
        // même qu'en ligne. Recopier la liste ici l'avait déjà fait diverger
        // (le guichet acceptait 10 Mo quand le candidat était borné à moins).
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
            if ($size <= 0 || $size > PieceDossier::TAILLE_MAX_OCTETS) {
                continue;
            }
            $mime      = $finfo->file($tmp) ?: '';
            $extension = PieceDossier::extensionPour($mime);
            if ($extension === null) {
                continue;
            }

            $piece   = $pieceModel->findById((int)$pieceId, $this->etabId);
            $libelle = $piece['libelle'] ?? ('Pièce #' . (int)$pieceId);
            $cle     = sprintf(
                'dossiers/%d/pieces/%d-%s.%s',
                $dossierId, (int)$pieceId, bin2hex(random_bytes(4)), $extension
            );

            try {
                $storage ??= new DocumentStorage();
                // Pièce déposée au guichet : même protection qu'en ligne —
                // chiffrée au repos, consultable uniquement via l'application.
                $storage->uploadChiffre($tmp, $cle, $mime);
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

            // Archive identique au document remis : attestation + proforma
            // (celle-ci porte la composition du dossier d'inscription).
            $numeroFacture = 'FAC-' . substr($numero, 4);
            $pdf           = $svc->renderPdfComplet($p, $etab, $numero, $numeroFacture, null, $token);

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
