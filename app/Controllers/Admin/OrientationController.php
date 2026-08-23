<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Queue, Redis, Response, Session};
use Models\Orientation\{Avis, ChampFormulaire, Dossier, Etablissement, Reversement};
use Services\Orientation\{AuditService, DocumentStorage, SlugService};

class OrientationController
{
    /** Nombre maximum de photos campus par établissement. */
    private const MAX_CAMPUS_PHOTOS = 10;

    // ── Liste des établissements ──────────────────────────────────────────

    public function index(): void
    {
        $model    = new Etablissement();
        $revModel = new Reversement();

        $etablissements = $model->findAllWithPaiement();

        // Notes moyennes des avis (affichées sur les cartes kanban)
        $avisAgg = [];
        try {
            $avisAgg = (new Avis())->aggregatesForEtabs(array_column($etablissements, 'id'));
        } catch (\Throwable $e) {
            error_log('[Admin/Orientation] Agrégats avis échoués : ' . $e->getMessage());
        }

        Response::view('admin/orientation/index', [
            'pageTitle'      => 'Orientation — Vue globale',
            'etablissements' => $etablissements,
            'stats'          => $model->countByStatut(),
            'statsGlobales'  => $model->statsGlobales(),
            'statsParEtab'   => $model->statsParEtablissement(),
            'statsVilles'    => (new Dossier())->statsVilles(),
            'avisAgg'        => $avisAgg,
            'gestionnaires'  => $model->findAllGestionnaires(),
            'nbARejouer'     => $revModel->countARejouer(),
            'flash'          => Session::getFlash('success'),
            'flashError'     => Session::getFlash('error'),
        ], 'admin');
    }

    public function toggleGestionnaire(int $id): void
    {
        $db   = \Core\Database::getInstance()->getConnection();
        $user = $db->prepare('SELECT is_active FROM users WHERE id = ? AND is_deleted = 0');
        $user->execute([$id]);
        $row  = $user->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            Session::flash('error', 'Utilisateur introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        $newStatus = $row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$newStatus, $id]);

        AuditService::log(
            'gestionnaire.toggled',
            'user',
            $id,
            Session::get('admin_id') ?? null,
            ['is_active' => $newStatus]
        );

        $label = $newStatus ? 'activé' : 'suspendu';
        Session::flash('success', "Accès gestionnaire {$label}.");
        Response::redirect('/admin/orientation');
    }

    // ── POST /admin/api/orientation/etablissements/{id}/message ───────────
    // L'administration écrit à un établissement : le message atterrit dans les
    // notifications de chaque gestionnaire lié (cloche + page Notifications du
    // portail gestionnaire). Aucun email — canal in-app uniquement.
    public function sendMessage(int $id): void
    {
        $etab = (new Etablissement())->findById($id);
        if (!$etab) {
            Response::json(['success' => false, 'message' => 'Établissement introuvable.'], 404);
            return;
        }

        $message = trim($_POST['message'] ?? '');
        $sujet   = trim($_POST['sujet'] ?? '');

        if ($message === '') {
            Response::json(['success' => false, 'message' => 'Le message est vide.'], 422);
            return;
        }
        if (mb_strlen($message) > 1000) {
            Response::json(['success' => false, 'message' => 'Message trop long (1000 caractères max).'], 422);
            return;
        }
        if (mb_strlen($sujet) > 120) {
            Response::json(['success' => false, 'message' => 'Objet trop long (120 caractères max).'], 422);
            return;
        }

        $notif          = new \Models\Orientation\NotificationOrientation();
        $gestionnaireIds = $notif->findGestionnaireIds($id);

        if (empty($gestionnaireIds)) {
            Response::json(['success' => false, 'message' => 'Aucun gestionnaire actif n\'est lié à cet établissement — impossible de délivrer le message.'], 422);
            return;
        }

        $titre = $sujet !== '' ? $sujet : 'Message de Connect\'Academia';
        foreach ($gestionnaireIds as $uid) {
            $notif->create([
                'user_id'          => (int) $uid,
                'etablissement_id' => $id,
                'type'             => 'message_admin',
                'titre'            => $titre,
                'message'          => $message,
            ]);
        }

        AuditService::log(
            'etablissement.message',
            'etablissement',
            $id,
            Session::get('admin_id') ?? null,
            ['destinataires' => count($gestionnaireIds), 'sujet' => $titre]
        );

        Response::json([
            'success' => true,
            'message' => 'Message envoyé à ' . $etab['nom'] . ' (' . count($gestionnaireIds) . ' gestionnaire' . (count($gestionnaireIds) > 1 ? 's' : '') . ' notifié' . (count($gestionnaireIds) > 1 ? 's' : '') . ').',
        ]);
    }

    // ── Formulaire de création ────────────────────────────────────────────

    public function create(): void
    {
        Response::view('admin/orientation/create', [
            'pageTitle' => 'Ajouter un établissement',
            'old'       => Session::getFlash('form_old') ?? [],
            'errors'    => Session::getFlash('form_errors') ?? [],
        ], 'admin');
    }

    /**
     * Signe une URL PUT pour l'upload DIRECT navigateur → B2 (logo, photo, vidéo).
     * Appelé en AJAX par les formulaires création/édition AVANT l'envoi du form :
     * les gros fichiers (vidéo jusqu'à 500 Mo, ~1 Mo/s) ne transitent plus par
     * PHP → plus aucun timeout FastCGI/nginx possible. Réservé aux admins.
     */
    public function presign(): void
    {
        $in          = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        $kind        = (string)($in['kind'] ?? '');
        $contentType = strtolower(trim((string)($in['contentType'] ?? '')));

        try {
            $c = DocumentStorage::contraintesMedia($kind); // valide le type
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => 'Type de média invalide.'], 422);
            return;
        }

        if (!in_array($contentType, $c['mimes'], true)) {
            Response::json(['success' => false, 'error' => 'Format non accepté (' . ($contentType ?: 'inconnu') . ').'], 422);
            return;
        }

        $ext = DocumentStorage::extensionMedia($contentType);
        $key = rtrim($c['prefix'], '/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(8)) . '.' . $ext;

        try {
            $url = (new DocumentStorage())->presignPut($key, $contentType);
        } catch (\Throwable $e) {
            error_log('[Orientation] presign échoué : ' . $e->getMessage());
            Response::json(['success' => false, 'error' => 'Service de stockage indisponible.'], 503);
            return;
        }

        Response::json([
            'success'     => true,
            'key'         => $key,
            'url'         => $url,
            'contentType' => $contentType,
            'maxBytes'    => $c['maxBytes'],
        ]);
    }

    /**
     * Valide côté serveur un média uploadé en DIRECT (navigateur → B2) : la clé
     * doit porter le bon préfixe, exister dans le bucket, avec une taille et un
     * MIME conformes. Restaure la barrière de sécurité que l'upload direct
     * court-circuite. Supprime l'objet fautif le cas échéant.
     *
     * @return string la clé validée
     * @throws \RuntimeException message destiné à l'utilisateur final
     */
    private static function validerMediaUploade(DocumentStorage $storage, string $cle, string $kind): string
    {
        $c = DocumentStorage::contraintesMedia($kind);
        if (!str_starts_with($cle, $c['prefix'])) {
            throw new \RuntimeException('Clé de fichier invalide.');
        }
        $meta = $storage->objectMeta($cle); // lève une exception si l'objet est absent
        if ($meta['size'] <= 0 || $meta['size'] > $c['maxBytes']) {
            try { $storage->delete($cle); } catch (\Throwable $e) {}
            throw new \RuntimeException('Fichier trop volumineux ou vide (' . $kind . ').');
        }
        // Le Content-Type est SIGNÉ à la présignature : B2 le renvoie tel quel,
        // le client n'a pas pu le falsifier → contrôle MIME fiable.
        if (!in_array(strtolower($meta['contentType']), $c['mimes'], true)) {
            try { $storage->delete($cle); } catch (\Throwable $e) {}
            throw new \RuntimeException('Format de fichier non accepté (' . $kind . ').');
        }
        return $cle;
    }

    // ── Traitement du formulaire ──────────────────────────────────────────

    public function store(): void
    {
        // POST vidé par PHP quand la taille totale dépasse post_max_size
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('form_errors', ['logo' => 'Fichiers trop volumineux — la taille totale dépasse la limite du serveur (' . ini_get('post_max_size') . ').']);
            Response::redirect('/admin/orientation/creer');
            return;
        }

        $model  = new Etablissement();
        $errors = [];

        // ── [1] Validation ────────────────────────────────────────────────
        $nom   = trim($_POST['nom'] ?? '');
        $type  = $_POST['type'] ?? '';
        $ville = trim($_POST['ville'] ?? '');
        $email = trim($_POST['email_contact'] ?? '');
        $gNom    = trim($_POST['gestionnaire_nom'] ?? '');
        $gPrenom = trim($_POST['gestionnaire_prenom'] ?? '');
        $gEmail  = trim($_POST['gestionnaire_email'] ?? '');
        $gPassword        = $_POST['gestionnaire_password'] ?? '';
        $gPasswordConfirm = $_POST['gestionnaire_password_confirm'] ?? '';

        if ($nom   === '') $errors['nom']   = 'Nom obligatoire.';
        if (!in_array($type, ['universite', 'grande_ecole', 'institut'], true))
            $errors['type'] = 'Type invalide.';
        if ($ville === '') $errors['ville'] = 'Ville obligatoire.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email_contact'] = 'Email de contact invalide.';
        // Logo présent SOIT en upload direct (clé B2 dans le POST) SOIT dans $_FILES (repli)
        $logoKeyPre = trim($_POST['logo_key'] ?? '');
        if ($logoKeyPre === '' && empty($_FILES['logo']['tmp_name']))
            $errors['logo'] = 'Logo obligatoire.';
        if ($gNom    === '') $errors['gestionnaire_nom']    = 'Nom du gestionnaire obligatoire.';
        if ($gPrenom === '') $errors['gestionnaire_prenom'] = 'Prénom du gestionnaire obligatoire.';
        if (!filter_var($gEmail, FILTER_VALIDATE_EMAIL))
            $errors['gestionnaire_email'] = 'Email du gestionnaire invalide.';

        // Mot de passe gestionnaire : optionnel — vide = généré automatiquement
        if ($gPassword !== '') {
            if (strlen($gPassword) < 8) {
                $errors['gestionnaire_password'] = 'Mot de passe : 8 caractères minimum.';
            } elseif ($gPassword !== $gPasswordConfirm) {
                $errors['gestionnaire_password_confirm'] = 'La confirmation ne correspond pas au mot de passe.';
            }
        }

        $formations = $_POST['formations'] ?? [];
        if (empty($formations) || !isset($formations[0]['intitule']) || trim($formations[0]['intitule'] ?? '') === '') {
            $errors['formations'] = 'Au moins une formation obligatoire.';
        }

        $couleur = trim($_POST['couleur_principale'] ?? '#1F4E79');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
            $couleur = '#1F4E79';
        }

        $statut = $_POST['statut'] ?? 'brouillon';
        if (!in_array($statut, ['actif', 'inactif', 'brouillon'], true)) {
            $statut = 'brouillon';
        }

        // Commission plateforme sur la scolarité (%) — appliquée à chaque
        // commission créée sur un dossier « inscrit » de cet établissement
        $tauxCommission = self::parseTauxCommission($_POST['taux_commission'] ?? '', $errors);

        // Localisation : code d'intégration Google Maps (iframe ou URL src directe)
        $mapEmbedUrl = self::parseMapEmbed($_POST['map_embed'] ?? '', $errors);

        // Photos campus : max 10, erreurs d'upload PHP remontées clairement
        self::validerPhotosCampus($errors);
        self::validerUploadVideo($errors);

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('form_old', self::oldSansMotDePasse($_POST));
            Response::redirect('/admin/orientation/creer');
            return;
        }

        // Email gestionnaire : réutiliser un compte gestionnaire orphelin d'une
        // tentative précédente, mais ne JAMAIS toucher un compte élève/admin existant.
        $gestionnaireExistant = null;
        $existingUser = $model->findUserByEmail($gEmail);
        if ($existingUser) {
            if ($model->emailExistsAndLinked($gEmail)) {
                Session::flash('form_errors', ['gestionnaire_email' => 'Cet email est déjà utilisé par un gestionnaire d\'un autre établissement.']);
                Session::flash('form_old', self::oldSansMotDePasse($_POST));
                Response::redirect('/admin/orientation/creer');
                return;
            }
            if ($existingUser['role'] !== 'gestionnaire') {
                Session::flash('form_errors', ['gestionnaire_email' => 'Cet email appartient déjà à un compte utilisateur de la plateforme. Utilisez une autre adresse pour le gestionnaire.']);
                Session::flash('form_old', self::oldSansMotDePasse($_POST));
                Response::redirect('/admin/orientation/creer');
                return;
            }
            // Compte gestionnaire orphelin (créé lors d'une tentative avortée) → réutilisé
            $gestionnaireExistant = $existingUser;
        }

        // ── [2] Slug ──────────────────────────────────────────────────────
        $slug = SlugService::fromNom($nom);

        // ── [3] Médias ────────────────────────────────────────────────────
        // Chemin normal : fichiers déjà poussés en DIRECT vers B2 par le
        // navigateur (clés présentes dans le POST) → on ne fait que les VALIDER
        // (headObject : existence + taille + MIME). Aucun octet lourd ne
        // transite par PHP, donc aucun risque de timeout serveur.
        // Repli : fichiers dans $_FILES (JavaScript désactivé) → upload serveur
        // classique, couvert par set_time_limit.
        set_time_limit(600);

        $storage   = new DocumentStorage();
        $logoKey   = '';
        $cachetKey = null;
        $imgKeys   = [];
        $videoKey  = null;

        $imgKeysPre = array_values(array_filter(array_map(
            fn($k) => trim((string)$k),
            (array)($_POST['images_keys'] ?? [])
        )));
        $videoKeyPre = trim($_POST['video_key'] ?? '');

        try {
            // Logo (obligatoire)
            if ($logoKeyPre !== '') {
                $logoKey = self::validerMediaUploade($storage, $logoKeyPre, 'logo');
            } elseif (!empty($_FILES['logo']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['logo']['name'] ?? '', PATHINFO_EXTENSION));
                $logoKey = $storage->upload($_FILES['logo']['tmp_name'], "logos/{$slug}.{$ext}", $_FILES['logo']['type'], false);
            }

            // Cachet (optionnel — upload serveur simple, tampon officiel pour les documents PDF)
            if (!empty($_FILES['cachet']['tmp_name']) && ($_FILES['cachet']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $extCachet = strtolower(pathinfo($_FILES['cachet']['name'] ?? '', PATHINFO_EXTENSION)) ?: 'png';
                $cachetKey = $storage->upload($_FILES['cachet']['tmp_name'], "cachets/{$slug}.{$extCachet}", $_FILES['cachet']['type'] ?? 'image/png', false);
            }

            // Photos campus (optionnel, max 10)
            if (!empty($imgKeysPre)) {
                foreach (array_slice($imgKeysPre, 0, self::MAX_CAMPUS_PHOTOS) as $k) {
                    $imgKeys[] = self::validerMediaUploade($storage, $k, 'image');
                }
            } else {
                $imgKeys = self::uploadPhotosCampus($storage, $slug);
            }

            // Vidéo (optionnel)
            if ($videoKeyPre !== '') {
                $videoKey = self::validerMediaUploade($storage, $videoKeyPre, 'video');
            } else {
                $videoKey = self::uploadVideoPresentation($storage, $slug);
            }
        } catch (\Throwable $e) {
            error_log('[Orientation] Média B2 échoué : ' . $e->getMessage());
            Session::flash('form_errors', ['logo' => 'Échec média : ' . $e->getMessage()]);
            Session::flash('form_old', self::oldSansMotDePasse($_POST));
            Response::redirect('/admin/orientation/creer');
            return;
        }

        // ── [4] Préparation JSON ──────────────────────────────────────────
        $badges   = array_values(array_filter(array_map('trim', (array)($_POST['badges'] ?? []))));
        $atouts   = array_values(array_filter(array_map('trim', (array)($_POST['atouts'] ?? []))));
        $stats    = [];
        foreach ((array)($_POST['stat_label'] ?? []) as $i => $label) {
            $label = trim($label);
            $val   = trim($_POST['stat_valeur'][$i] ?? '');
            if ($label !== '' && $val !== '') {
                $stats[] = ['label' => $label, 'valeur' => $val];
            }
        }

        $infosPaiement = [];
        foreach ((array)($_POST['mode_paiement'] ?? []) as $mode) {
            if (trim($mode) !== '') $infosPaiement[] = trim($mode);
        }

        // ── [5-8] Toutes les écritures DB dans une transaction atomique ───
        $pdo         = \Core\Database::getInstance()->getConnection();
        $mdpPersonnalise = ($gPassword !== '');
        $mdpTemp     = $mdpPersonnalise ? $gPassword : bin2hex(random_bytes(6));
        $etabId      = 0;
        $userId      = 0;

        $pdo->beginTransaction();
        try {
            $etabId = $model->create([
                'slug'                 => $slug,
                'nom'                  => $nom,
                'type'                 => $type,
                'ville'                => $ville,
                'logo_cle'             => $logoKey,
                'cachet_cle'           => $cachetKey,
                'couleur_principale'   => $couleur,
                'statut'               => $statut,
                'label_campagne'       => trim($_POST['label_campagne'] ?? '') ?: null,
                'titre_accroche'       => trim($_POST['titre_accroche'] ?? '') ?: null,
                'sous_titre'           => trim($_POST['sous_titre'] ?? '') ?: null,
                'date_limite_dossier'  => trim($_POST['date_limite_dossier'] ?? ''),
                'description'          => trim($_POST['description'] ?? '') ?: null,
                'video_url'            => null,
                'video_cle'            => $videoKey,
                'citation_texte'       => trim($_POST['citation_texte'] ?? '') ?: null,
                'citation_auteur'      => trim($_POST['citation_auteur'] ?? '') ?: null,
                'citation_fonction'    => trim($_POST['citation_fonction'] ?? '') ?: null,
                'adresse'              => trim($_POST['adresse'] ?? '') ?: null,
                'latitude'             => '',
                'longitude'            => '',
                'map_embed_url'        => $mapEmbedUrl,
                'email_contact'        => $email ?: null,
                'telephone'            => trim($_POST['telephone'] ?? '') ?: null,
                'whatsapp'             => trim($_POST['whatsapp'] ?? '') ?: null,
                'prix_preinscription'  => self::toInt($_POST['prix_preinscription'] ?? 0),
                'prix_inscription'     => self::toInt($_POST['prix_inscription'] ?? 0),
                'frais_dossier'        => self::toInt($_POST['frais_dossier'] ?? 0),
                'frais_scolarite'      => self::toInt($_POST['frais_scolarite'] ?? 0),
                'frais_scolarite_boursier' => self::toInt($_POST['frais_scolarite_boursier'] ?? 0),
                'frais_scolarite_master' => self::toInt($_POST['frais_scolarite_master'] ?? 0),
                'frais_scolarite_master_boursier' => self::toInt($_POST['frais_scolarite_master_boursier'] ?? 0),
                'frais_uniforme'       => self::toInt($_POST['frais_uniforme'] ?? 0),
                'taux_commission_scolarite' => $tauxCommission,
                'infos_paiement'       => !empty($infosPaiement) ? json_encode($infosPaiement) : null,
                'preinscription_active'=> isset($_POST['preinscription_active']) ? 1 : 0,
                'genere_recepisse'     => isset($_POST['genere_recepisse']) ? 1 : 0,
                'genere_attestation'   => isset($_POST['genere_attestation']) ? 1 : 0,
                'badges_confiance'     => !empty($badges) ? json_encode($badges) : null,
                'atouts'               => !empty($atouts) ? json_encode($atouts) : null,
                'statistiques'         => !empty($stats) ? json_encode($stats) : null,
                'images_cles'          => !empty($imgKeys) ? json_encode($imgKeys) : null,
            ]);

            foreach ($formations as $ordre => $f) {
                $intitule = trim($f['intitule'] ?? '');
                if ($intitule === '') continue;
                $model->addFormation($etabId, [
                    'intitule'    => $intitule,
                    'niveau'      => trim($f['niveau'] ?? ''),
                    'icone'       => trim($f['icone'] ?? ''),
                    'duree_texte' => trim($f['duree_texte'] ?? ''),
                    'places_texte'=> trim($f['places_texte'] ?? ''),
                    'prix'        => self::toInt($f['prix'] ?? 0),
                    'prix_unite'  => trim($f['prix_unite'] ?? 'FCFA/an') ?: 'FCFA/an',
                    'statut'      => in_array($f['statut'] ?? '', ['ouverte','bientot','fermee'], true)
                                        ? $f['statut'] : 'ouverte',
                    'ordre'       => $ordre,
                ]);
            }

            $mmNumero = trim($_POST['mm_numero'] ?? '');
            $mmMode   = trim($_POST['mm_mode'] ?? '');
            if ($mmNumero !== '' && $mmMode !== '') {
                $model->addCompteEncaissement($etabId, [
                    'withdraw_mode'      => $mmMode,
                    'numero_mobile_money'=> $mmNumero,
                    'titulaire'          => trim($_POST['mm_titulaire'] ?? '') ?: null,
                ]);
            }

            (new ChampFormulaire())->insertDefaultSet($etabId);

            if ($gestionnaireExistant !== null) {
                // Réutiliser le compte orphelin : nouveau mot de passe + identité à jour
                $userId = (int)$gestionnaireExistant['id'];
                $model->resetGestionnaire($userId, $gNom, $gPrenom, $mdpTemp);
            } else {
                $userId = $model->createGestionnaire([
                    'nom'      => $gNom,
                    'prenom'   => $gPrenom,
                    'email'    => $gEmail,
                    'password' => $mdpTemp,
                ]);
            }
            $model->linkGestionnaire($etabId, $userId);

            $pdo->commit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Orientation] Échec création établissement : ' . $e->getMessage());
            Session::flash('form_errors', ['nom' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()]);
            Session::flash('form_old', self::oldSansMotDePasse($_POST));
            Response::redirect('/admin/orientation/creer');
            return;
        }

        // ── [9] Email identifiants (hors transaction — timeout SMTP ne rompt pas la DB)
        $emailSent = $this->sendCredentials($gPrenom, $gNom, $gEmail, $mdpTemp, $nom, $mdpPersonnalise);

        // ── [10] Audit + invalidation cache + Succès ─────────────────────
        AuditService::log(
            'etablissement.created',
            'etablissement',
            $etabId,
            Session::get('admin_id') ?? null,
            ['nom' => $nom, 'slug' => $slug, 'type' => $type]
        );

        $redis = Redis::getInstance();
        $redis->del(
            "orientation:marketplace:universite",
            "orientation:marketplace:institut",
            "orientation:marketplace:grande_ecole",
            "orientation:stats:global"
        );

        $msg = "Établissement « {$nom} » créé (slug : {$slug}).";
        if (!$emailSent) {
            $msg .= ' ⚠ Email gestionnaire non envoyé — vérifier config SMTP.';
            if ($mdpPersonnalise) {
                $msg .= ' Le mot de passe défini est bien enregistré — communique-le au gestionnaire.';
            } else {
                $msg .= self::isLocal()
                    ? " [DEV] Mot de passe temporaire du gestionnaire : {$mdpTemp}"
                    : ' Mot de passe temporaire à communiquer manuellement.';
            }
        }
        // Sitemap : un nouvel établissement actif doit être référencé.
        (new \Services\SitemapGenerator())->writeToDisk();

        Session::flash('success', $msg);
        Response::redirect('/admin/orientation');
    }

    /** Entier robuste : tolère "250 000", "250.000", "250,000" saisis à la française. */
    private static function toInt(mixed $val): int
    {
        return max(0, (int)preg_replace('/[^\d]/', '', (string)$val));
    }

    /**
     * Taux de commission scolarité (%) : nombre entre 0 et 100, virgule
     * française acceptée. Vide = 10 % (taux historique de la plateforme).
     */
    private static function parseTauxCommission(string $brut, array &$errors): float
    {
        $brut = str_replace([' ', ','], ['', '.'], trim($brut));
        if ($brut === '') {
            return 10.0;
        }
        if (!is_numeric($brut)) {
            $errors['taux_commission'] = 'Taux de commission invalide — saisissez un nombre (ex : 10).';
            return 10.0;
        }
        $taux = (float)$brut;
        if ($taux < 0 || $taux > 100) {
            $errors['taux_commission'] = 'Le taux de commission doit être compris entre 0 et 100 %.';
            return 10.0;
        }
        return round($taux, 2);
    }

    /**
     * Extrait et valide l'URL d'intégration Google Maps.
     * Accepte le code iframe complet (Partager → Intégrer une carte) ou l'URL src seule.
     * Ne stocke JAMAIS le HTML brut — uniquement l'URL validée (anti-XSS).
     */
    private static function parseMapEmbed(string $input, array &$errors): ?string
    {
        $input = trim($input);
        if ($input === '') return null;

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) {
            $url = html_entity_decode($m[1], ENT_QUOTES);
        } else {
            $url = $input;
        }

        if (!preg_match('#^https://www\.google\.com/maps/embed#', $url)) {
            $errors['map_embed'] = 'Code de carte invalide — colle le code « Intégrer une carte » fourni par Google Maps (Partager → Intégrer une carte).';
            return null;
        }

        return $url;
    }

    /** Message lisible pour un code d'erreur $_FILES. */
    private static function uploadErrMsg(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop volumineux (limite serveur : ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_PARTIAL => 'envoi interrompu — réessaie',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'erreur serveur (dossier temporaire)',
            default => 'erreur d\'envoi (code ' . $code . ')',
        };
    }

    /** Valide le lot de photos campus : max 10 + codes d'erreur PHP. */
    private static function validerPhotosCampus(array &$errors): void
    {
        $files = $_FILES['images_campus'] ?? [];
        if (empty($files['name']) || !is_array($files['name'])) return;

        $nb = 0;
        foreach ($files['error'] as $idx => $err) {
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $nb++;
            if ($err !== UPLOAD_ERR_OK) {
                $nomFichier = $files['name'][$idx] ?? ('photo ' . ($idx + 1));
                $errors['images_campus'] = 'Photo « ' . $nomFichier . ' » : ' . self::uploadErrMsg((int)$err) . '.';
                return;
            }
        }

        if ($nb > self::MAX_CAMPUS_PHOTOS) {
            $errors['images_campus'] = 'Maximum ' . self::MAX_CAMPUS_PHOTOS . ' photos campus (' . $nb . ' sélectionnées).';
        }
    }

    /** Valide le fichier vidéo (code d'erreur PHP uniquement — MIME validé par DocumentStorage). */
    private static function validerUploadVideo(array &$errors): void
    {
        $err = $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (is_array($err) || $err === UPLOAD_ERR_NO_FILE || $err === UPLOAD_ERR_OK) return;
        $errors['video'] = 'Vidéo : ' . self::uploadErrMsg((int)$err) . '.';
    }

    /** Upload les photos campus vers B2 (max 10) et retourne les clés. */
    private static function uploadPhotosCampus(DocumentStorage $storage, string $slug): array
    {
        $files = $_FILES['images_campus'] ?? [];
        $keys  = [];
        if (empty($files['tmp_name']) || !is_array($files['tmp_name'])) return $keys;

        $horodatage = time(); // clés uniques → jamais d'image obsolète servie par un cache
        $i = 0;
        foreach ($files['tmp_name'] as $idx => $tmpPath) {
            if (!$tmpPath || ($files['error'][$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if ($i >= self::MAX_CAMPUS_PHOTOS) break;
            $ext    = strtolower(pathinfo($files['name'][$idx] ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
            $keys[] = $storage->upload(
                $tmpPath,
                "images/{$slug}/campus_{$horodatage}_{$i}.{$ext}",
                $files['type'][$idx] ?? 'image/jpeg',
                false
            );
            $i++;
        }
        return $keys;
    }

    /** Upload la vidéo de présentation vers B2, retourne la clé ou null si aucun fichier. */
    private static function uploadVideoPresentation(DocumentStorage $storage, string $slug): ?string
    {
        if (empty($_FILES['video']['tmp_name']) || ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($_FILES['video']['name'] ?? '', PATHINFO_EXTENSION)) ?: 'mp4';
        return $storage->uploadVideo(
            $_FILES['video']['tmp_name'],
            "videos/{$slug}/presentation_" . time() . ".{$ext}"
        );
    }

    /** Ne jamais re-remplir les champs mot de passe dans le formulaire après erreur. */
    private static function oldSansMotDePasse(array $post): array
    {
        unset($post['gestionnaire_password'], $post['gestionnaire_password_confirm']);
        return $post;
    }

    private static function isLocal(): bool
    {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        return ($_ENV['APP_ENV'] ?? 'production') !== 'production'
            || str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1');
    }

    // ── Fiche détail établissement ────────────────────────────────────────

    public function show(int $id): void
    {
        $model = new Etablissement();
        $etab  = $model->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        // URLs signées B2 (logo + images campus + vidéo) — jamais bloquant
        $logoUrl      = '';
        $imageUrls    = [];
        $videoFileUrl = '';
        try {
            $storage = new DocumentStorage();
            if (!empty($etab['logo_cle'])) {
                $logoUrl = $storage->signedUrl($etab['logo_cle'], 10);
            }
            foreach (json_decode($etab['images_cles'] ?? '[]', true) ?: [] as $cle) {
                $imageUrls[] = $storage->signedUrl($cle, 10);
            }
            if (!empty($etab['video_cle'])) {
                $videoFileUrl = $storage->signedUrl($etab['video_cle'], 10);
            }
        } catch (\Throwable $e) {
            error_log('[Orientation] signedUrl fiche admin : ' . $e->getMessage());
        }

        Response::view('admin/orientation/show', [
            'pageTitle'     => $etab['nom'],
            'etab'          => $etab,
            'formations'    => $model->getFormations($id),
            'gestionnaires' => $model->findGestionnairesByEtab($id),
            'statsEtab'     => $model->statsEtablissement($id),
            'logoUrl'       => $logoUrl,
            'imageUrls'     => $imageUrls,
            'videoFileUrl'  => $videoFileUrl,
            'flash'         => Session::getFlash('success'),
            'flashError'    => Session::getFlash('error'),
        ], 'admin');
    }

    // ── Formulaire de modification ────────────────────────────────────────

    public function edit(int $id): void
    {
        $model = new Etablissement();
        $etab  = $model->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        Response::view('admin/orientation/edit', [
            'pageTitle'  => 'Modifier — ' . $etab['nom'],
            'etab'       => $etab,
            'formations' => $model->getFormations($id),
            'old'        => Session::getFlash('form_old') ?? [],
            'errors'     => Session::getFlash('form_errors') ?? [],
        ], 'admin');
    }

    // ── Traitement de la modification ─────────────────────────────────────

    public function update(int $id): void
    {
        // POST vidé par PHP quand la taille totale dépasse post_max_size
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('form_errors', ['logo' => 'Fichiers trop volumineux — la taille totale dépasse la limite du serveur (' . ini_get('post_max_size') . ').']);
            Response::redirect('/admin/orientation/' . $id . '/modifier');
            return;
        }

        $model = new Etablissement();
        $etab  = $model->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        $errors = [];

        // ── [1] Validation (mêmes règles que la création, logo optionnel) ─
        $nom   = trim($_POST['nom'] ?? '');
        $type  = $_POST['type'] ?? '';
        $ville = trim($_POST['ville'] ?? '');
        $email = trim($_POST['email_contact'] ?? '');

        if ($nom   === '') $errors['nom']   = 'Nom obligatoire.';
        if (!in_array($type, ['universite', 'grande_ecole', 'institut'], true))
            $errors['type'] = 'Type invalide.';
        if ($ville === '') $errors['ville'] = 'Ville obligatoire.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email_contact'] = 'Email de contact invalide.';

        $formations = $_POST['formations'] ?? [];
        $auMoinsUne = false;
        foreach ((array)$formations as $f) {
            if (trim($f['intitule'] ?? '') !== '') { $auMoinsUne = true; break; }
        }
        if (!$auMoinsUne) {
            $errors['formations'] = 'Au moins une formation obligatoire.';
        }

        $couleur = trim($_POST['couleur_principale'] ?? '#1F4E79');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
            $couleur = '#1F4E79';
        }

        $statut = $_POST['statut'] ?? 'brouillon';
        if (!in_array($statut, ['actif', 'inactif', 'brouillon'], true)) {
            $statut = 'brouillon';
        }

        // Commission plateforme sur la scolarité (%) — appliquée à chaque
        // commission créée sur un dossier « inscrit » de cet établissement
        $tauxCommission = self::parseTauxCommission($_POST['taux_commission'] ?? '', $errors);

        // Localisation : code d'intégration Google Maps (iframe ou URL src directe)
        $mapEmbedUrl = self::parseMapEmbed($_POST['map_embed'] ?? '', $errors);

        // Photos campus : max 10, erreurs d'upload PHP remontées clairement
        self::validerPhotosCampus($errors);
        self::validerUploadVideo($errors);

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('form_old', $_POST);
            Response::redirect('/admin/orientation/' . $id . '/modifier');
            return;
        }

        // ── [2] Slug conservé — les liens publics ne cassent jamais ──────
        $slug = $etab['slug'];

        // ── [3] Médias : remplacés seulement si nouveau fichier envoyé ───
        // Comme à la création : chemin normal = clés uploadées en DIRECT vers B2
        // (dans le POST), simplement validées ici ; repli = $_FILES (upload
        // serveur). Sans nouveau média, on conserve l'existant.
        set_time_limit(600);

        $logoKey   = $etab['logo_cle'] ?? '';
        $cachetKey = $etab['cachet_cle'] ?? null;
        $imgKeys   = json_decode($etab['images_cles'] ?? '[]', true) ?: [];
        $videoKey  = $etab['video_cle'] ?? null;
        $videoUrl  = $etab['video_url'] ?? null; // lien YouTube legacy conservé tant qu'aucun fichier

        $logoKeyPre  = trim($_POST['logo_key'] ?? '');
        $imgKeysPre  = array_values(array_filter(array_map(
            fn($k) => trim((string)$k),
            (array)($_POST['images_keys'] ?? [])
        )));
        $videoKeyPre = trim($_POST['video_key'] ?? '');

        try {
            $storage = new DocumentStorage();

            // Logo
            if ($logoKeyPre !== '' || !empty($_FILES['logo']['tmp_name'])) {
                if ($logoKeyPre !== '') {
                    $logoKey = self::validerMediaUploade($storage, $logoKeyPre, 'logo');
                } else {
                    $ext     = strtolower(pathinfo($_FILES['logo']['name'] ?? '', PATHINFO_EXTENSION));
                    $logoKey = $storage->upload($_FILES['logo']['tmp_name'], "logos/{$slug}.{$ext}", $_FILES['logo']['type'], false);
                }
                // La clé B2 peut rester identique après remplacement : purge du
                // data URI mis en cache pour les récépissés/attestations.
                \Services\Orientation\LogoDataUri::invalider($etab['logo_cle'] ?? '');
                \Services\Orientation\LogoDataUri::invalider($logoKey);
            }

            // Cachet (optionnel — remplacé seulement si nouveau fichier envoyé)
            if (!empty($_FILES['cachet']['tmp_name']) && ($_FILES['cachet']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $extCachet = strtolower(pathinfo($_FILES['cachet']['name'] ?? '', PATHINFO_EXTENSION)) ?: 'png';
                $cachetKey = $storage->upload($_FILES['cachet']['tmp_name'], "cachets/{$slug}.{$extCachet}", $_FILES['cachet']['type'] ?? 'image/png', false);
            }

            // Photos campus
            $campusFiles = $_FILES['images_campus'] ?? [];
            if (!empty($imgKeysPre)) {
                $nouvelles = [];
                foreach (array_slice($imgKeysPre, 0, self::MAX_CAMPUS_PHOTOS) as $k) {
                    $nouvelles[] = self::validerMediaUploade($storage, $k, 'image');
                }
                $imgKeys = $nouvelles;
            } elseif (!empty($campusFiles['tmp_name']) && !empty(array_filter($campusFiles['tmp_name']))) {
                $imgKeys = self::uploadPhotosCampus($storage, $slug);
            }

            // Vidéo
            if ($videoKeyPre !== '' || !empty($_FILES['video']['tmp_name'])) {
                $ancienneVideo = $videoKey;
                if ($videoKeyPre !== '') {
                    $videoKey = self::validerMediaUploade($storage, $videoKeyPre, 'video');
                } else {
                    $videoKey = self::uploadVideoPresentation($storage, $slug);
                }
                $videoUrl = null; // le fichier remplace définitivement le lien YouTube
                if ($ancienneVideo && $ancienneVideo !== $videoKey) {
                    try { $storage->delete($ancienneVideo); } catch (\Throwable $e) {
                        error_log('[Orientation] Suppression ancienne vidéo échouée : ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[Orientation] Média B2 échoué (édition) : ' . $e->getMessage());
            Session::flash('form_errors', ['logo' => 'Échec média : ' . $e->getMessage()]);
            Session::flash('form_old', $_POST);
            Response::redirect('/admin/orientation/' . $id . '/modifier');
            return;
        }

        // ── [4] Préparation JSON ──────────────────────────────────────────
        $badges = array_values(array_filter(array_map('trim', (array)($_POST['badges'] ?? []))));
        $atouts = array_values(array_filter(array_map('trim', (array)($_POST['atouts'] ?? []))));
        $stats  = [];
        foreach ((array)($_POST['stat_label'] ?? []) as $i => $label) {
            $label = trim($label);
            $val   = trim($_POST['stat_valeur'][$i] ?? '');
            if ($label !== '' && $val !== '') {
                $stats[] = ['label' => $label, 'valeur' => $val];
            }
        }

        $infosPaiement = [];
        foreach ((array)($_POST['mode_paiement'] ?? []) as $mode) {
            if (trim($mode) !== '') $infosPaiement[] = trim($mode);
        }

        // ── [5] Écritures DB en transaction atomique ─────────────────────
        $pdo = \Core\Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        try {
            $model->update($id, [
                'nom'                  => $nom,
                'type'                 => $type,
                'ville'                => $ville,
                'logo_cle'             => $logoKey,
                'cachet_cle'           => $cachetKey,
                'couleur_principale'   => $couleur,
                'statut'               => $statut,
                'label_campagne'       => trim($_POST['label_campagne'] ?? '') ?: null,
                'titre_accroche'       => trim($_POST['titre_accroche'] ?? '') ?: null,
                'sous_titre'           => trim($_POST['sous_titre'] ?? '') ?: null,
                'date_limite_dossier'  => trim($_POST['date_limite_dossier'] ?? ''),
                'description'          => trim($_POST['description'] ?? '') ?: null,
                'video_url'            => $videoUrl,
                'video_cle'            => $videoKey,
                'citation_texte'       => trim($_POST['citation_texte'] ?? '') ?: null,
                'citation_auteur'      => trim($_POST['citation_auteur'] ?? '') ?: null,
                'citation_fonction'    => trim($_POST['citation_fonction'] ?? '') ?: null,
                'adresse'              => trim($_POST['adresse'] ?? '') ?: null,
                'latitude'             => $etab['latitude'] ?? '',
                'longitude'            => $etab['longitude'] ?? '',
                'map_embed_url'        => $mapEmbedUrl,
                'email_contact'        => $email ?: null,
                'telephone'            => trim($_POST['telephone'] ?? '') ?: null,
                'whatsapp'             => trim($_POST['whatsapp'] ?? '') ?: null,
                'prix_preinscription'  => self::toInt($_POST['prix_preinscription'] ?? 0),
                'prix_inscription'     => self::toInt($_POST['prix_inscription'] ?? 0),
                'frais_dossier'        => self::toInt($_POST['frais_dossier'] ?? 0),
                'frais_scolarite'      => self::toInt($_POST['frais_scolarite'] ?? 0),
                'frais_scolarite_boursier' => self::toInt($_POST['frais_scolarite_boursier'] ?? 0),
                'frais_scolarite_master' => self::toInt($_POST['frais_scolarite_master'] ?? 0),
                'frais_scolarite_master_boursier' => self::toInt($_POST['frais_scolarite_master_boursier'] ?? 0),
                'frais_uniforme'       => self::toInt($_POST['frais_uniforme'] ?? 0),
                'taux_commission_scolarite' => $tauxCommission,
                'infos_paiement'       => !empty($infosPaiement) ? json_encode($infosPaiement) : null,
                'preinscription_active'=> isset($_POST['preinscription_active']) ? 1 : 0,
                'genere_recepisse'     => isset($_POST['genere_recepisse']) ? 1 : 0,
                'genere_attestation'   => isset($_POST['genere_attestation']) ? 1 : 0,
                'badges_confiance'     => !empty($badges) ? json_encode($badges) : null,
                'atouts'               => !empty($atouts) ? json_encode($atouts) : null,
                'statistiques'         => !empty($stats) ? json_encode($stats) : null,
                'images_cles'          => !empty($imgKeys) ? json_encode($imgKeys) : null,
            ]);

            // Formations : upsert par id — préserve formation_id des dossiers existants
            $keepIds = [];
            $ordre   = 0;
            foreach ((array)$formations as $f) {
                $intitule = trim($f['intitule'] ?? '');
                if ($intitule === '') continue;

                $data = [
                    'intitule'    => $intitule,
                    'niveau'      => trim($f['niveau'] ?? ''),
                    'icone'       => trim($f['icone'] ?? ''),
                    'duree_texte' => trim($f['duree_texte'] ?? ''),
                    'places_texte'=> trim($f['places_texte'] ?? ''),
                    'prix'        => self::toInt($f['prix'] ?? 0),
                    'prix_unite'  => trim($f['prix_unite'] ?? 'FCFA/an') ?: 'FCFA/an',
                    'statut'      => in_array($f['statut'] ?? '', ['ouverte','bientot','fermee'], true)
                                        ? $f['statut'] : 'ouverte',
                    'ordre'       => $ordre++,
                ];

                $fId = (int)($f['id'] ?? 0);
                if ($fId > 0) {
                    $model->updateFormation($fId, $id, $data);
                    $keepIds[] = $fId;
                } else {
                    $keepIds[] = $model->addFormationReturningId($id, $data);
                }
            }
            $model->deleteFormationsExcept($id, $keepIds);

            $pdo->commit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Orientation] Échec modification établissement : ' . $e->getMessage());
            Session::flash('form_errors', ['nom' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()]);
            Session::flash('form_old', $_POST);
            Response::redirect('/admin/orientation/' . $id . '/modifier');
            return;
        }

        // ── [6] Audit + invalidation cache ────────────────────────────────
        AuditService::log(
            'etablissement.updated',
            'etablissement',
            $id,
            Session::get('admin_id') ?? null,
            ['nom' => $nom, 'slug' => $slug, 'statut' => $statut]
        );

        Redis::getInstance()->del(
            "orientation:etab:{$slug}",
            "orientation:marketplace:universite",
            "orientation:marketplace:institut",
            "orientation:marketplace:grande_ecole",
            "orientation:stats:global"
        );

        // Sitemap : le slug ou le statut a pu changer → réécriture du fichier.
        (new \Services\SitemapGenerator())->writeToDisk();

        Session::flash('success', "Établissement « {$nom} » mis à jour.");
        Response::redirect('/admin/orientation/' . $id . '/voir');
    }

    // ── Formulaire config paiement ────────────────────────────────────────

    public function showPaiement(int $id): void
    {
        $etabModel = new Etablissement();
        $etab      = $etabModel->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        Response::view('admin/orientation/paiement', [
            'pageTitle' => 'Configurer paiement — ' . $etab['nom'],
            'etab'      => $etab,
            'errors'    => Session::getFlash('form_errors') ?? [],
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'admin');
    }

    // ── Paiement établissement (UPSERT compte mobile money) ───────────────

    public function updatePaiement(int $id): void
    {
        $etabModel = new Etablissement();
        $etab      = $etabModel->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        $mode          = trim($_POST['withdraw_mode']       ?? '');
        $numero        = trim($_POST['numero_mobile_money'] ?? '');
        $numeroConfirm = trim($_POST['numero_confirmation'] ?? '');
        $titulaire     = trim($_POST['titulaire']           ?? '') ?: null;
        $taux          = max(0, min(100, (float)($_POST['taux_commission'] ?? 5)));
        $activer       = isset($_POST['activer_inscription']);

        $errors = [];
        if (!in_array($mode, ['airtel-ga', 'moov-ga'], true))
            $errors[] = 'Mode de paiement invalide.';
        if (!preg_match('/^\+?[0-9]{8,15}$/', $numero))
            $errors[] = 'Numéro Mobile Money invalide.';

        // Double confirmation si le numéro change
        $ancienNumero = $etab['numero_mobile_money'] ?? '';
        $numeroChange = ($ancienNumero !== '' && $ancienNumero !== $numero);
        if ($numeroChange && $numeroConfirm !== $numero) {
            $errors[] = 'Confirmation du numéro incorrecte — saisissez le nouveau numéro une seconde fois.';
        }

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            Response::redirect('/admin/orientation/' . $id . '/paiement');
            return;
        }

        $adminId  = Session::get('admin_id') ?? null;
        $revModel = new Reversement();
        $revModel->upsertCompte($id, [
            'withdraw_mode'       => $mode,
            'numero_mobile_money' => $numero,
            'titulaire'           => $titulaire,
            'taux_commission'     => $taux,
            'statut'              => $activer ? 'actif' : 'en_attente',
        ]);

        if ($activer) {
            $etabModel->toggleInscriptionActive($id, true);
        }

        // Audit si numéro changé
        if ($numeroChange) {
            AuditService::log(
                'reversement.numero_changed',
                'etablissement',
                $id,
                $adminId,
                ['ancien' => $ancienNumero, 'nouveau' => $numero, 'mode' => $mode]
            );
        }

        // Audit global paiement update
        AuditService::log(
            'paiement.compte_updated',
            'etablissement',
            $id,
            $adminId,
            ['mode' => $mode, 'taux' => $taux, 'activer' => $activer]
        );

        // Invalidation cache Redis page établissement
        $slug = $etab['slug'] ?? '';
        if ($slug) {
            Redis::getInstance()->del(
                "orientation:etab:{$slug}",
                "orientation:marketplace:universite",
            "orientation:marketplace:institut",
                "orientation:marketplace:grande_ecole"
            );
        }

        Session::flash('success', 'Compte de reversement mis à jour.');
        Response::redirect('/admin/orientation');
    }

    // ── Activation / désactivation inscriptions ───────────────────────────

    public function activerInscription(int $id): void
    {
        $etabModel = new Etablissement();
        $etab      = $etabModel->findById($id);

        if (!$etab) {
            Session::flash('error', 'Établissement introuvable.');
            Response::redirect('/admin/orientation');
            return;
        }

        // Exige compte actif
        if ($etab['paiement_statut'] !== 'actif') {
            Session::flash('error', 'Configurez d\'abord le compte Mobile Money (statut : actif).');
            Response::redirect('/admin/orientation');
            return;
        }

        $actuel = (bool)$etab['inscription_active'];
        $etabModel->toggleInscriptionActive($id, !$actuel);

        AuditService::log(
            'inscription.toggle',
            'etablissement',
            $id,
            Session::get('admin_id') ?? null,
            ['active' => !$actuel, 'etab_nom' => $etab['nom']]
        );

        // Invalidation cache Redis
        $slug = $etab['slug'] ?? '';
        if ($slug) {
            Redis::getInstance()->del("orientation:etab:{$slug}");
        }

        $label = !$actuel ? 'activées' : 'désactivées';
        Session::flash('success', "Inscriptions {$label} pour « {$etab['nom']} ».");
        Response::redirect('/admin/orientation');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function sendCredentials(
        string $prenom,
        string $nom,
        string $email,
        string $mdp,
        string $etabNom,
        bool $mdpPersonnalise = false
    ): bool {
        $labelMdp     = $mdpPersonnalise ? 'Mot de passe' : 'Mot de passe temporaire';
        $consigneMdp  = $mdpPersonnalise
            ? 'Ce mot de passe a été défini par l\'administrateur — tu peux le changer à tout moment.'
            : 'Change ce mot de passe dès ta première connexion.';
        $url = htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/') . '/gestionnaire/login', ENT_QUOTES);
        $hp  = htmlspecialchars($prenom, ENT_QUOTES);
        $he  = htmlspecialchars($email, ENT_QUOTES);
        $hm  = htmlspecialchars($mdp, ENT_QUOTES);
        $hn  = htmlspecialchars($etabNom, ENT_QUOTES);

        $html = <<<HTML
        <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
        <body style="font-family:Montserrat,sans-serif;background:#F5F5F7;padding:40px 0;color:#1A1A1A;">
          <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
            <div style="background:#8B52FA;padding:32px 40px;">
              <p style="color:#fff;font-size:20px;font-weight:700;margin:0;">Connect'Academia</p>
              <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;">Module Orientation</p>
            </div>
            <div style="padding:32px 40px;">
              <p style="font-size:15px;">Bonjour <strong>{$hp}</strong>,</p>
              <p style="color:#4A4A4A;font-size:14px;line-height:1.6;">
                Ton compte gestionnaire pour <strong>{$hn}</strong> a été créé sur Connect'Academia.
              </p>
              <div style="background:#F3EFFF;border-radius:12px;padding:20px 24px;margin:20px 0;">
                <p style="margin:0 0 8px;font-size:13px;color:#4A4A4A;">Email</p>
                <p style="margin:0 0 16px;font-size:15px;font-weight:600;">{$he}</p>
                <p style="margin:0 0 8px;font-size:13px;color:#4A4A4A;">{$labelMdp}</p>
                <p style="margin:0;font-size:18px;font-weight:700;letter-spacing:0.08em;color:#8B52FA;">{$hm}</p>
              </div>
              <p style="color:#FF5252;font-size:13px;">{$consigneMdp}</p>
              <a href="{$url}" style="display:inline-block;margin-top:16px;background:#8B52FA;color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:600;font-size:14px;">
                Accéder à mon espace établissement
              </a>
            </div>
            <div style="padding:16px 40px;border-top:1px solid rgba(0,0,0,0.06);font-size:12px;color:#9E9E9E;">
              Connect'Academia — Votre passerelle vers l'avenir.
            </div>
          </div>
        </body></html>
        HTML;

        $text = "Bonjour {$prenom},\n\nCompte gestionnaire pour {$etabNom} créé.\n"
              . "Email : {$email}\nMot de passe : {$mdp}\n\n"
              . "Connecte-toi sur " . rtrim($_ENV['APP_URL'] ?? '', '/') . "/gestionnaire/login. "
              . $consigneMdp;

        return Queue::push('send_email', [
            'to'      => $email,
            'toName'  => $prenom . ' ' . $nom,
            'subject' => "Tes accès gestionnaire — Connect'Academia",
            'html'    => $html,
            'text'    => $text,
        ]);
    }
}
