<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{AnneeUniversitaire, Hashid, Queue, Response, Session, TenantGuard};
use Models\User;
use Models\Orientation\{Dossier, DossierStatut, Etablissement, Filiere, NotificationOrientation};
use Services\Orientation\{AuditService, DocumentStorage, JetonDossier, Journal, Niveau, PdfStream,
    PieceDossier, PolitiqueBoursiers, RecepisseService, ReferenceDossier, SituationBourse,
    SoumissionDossier, UniciteInscription};

class CandidatureController
{
    private Etablissement $etabModel;
    private Dossier       $dossierModel;

    /** Compte connecté, résolu au plus une fois (cf. compteConnecte()). */
    private ?array $compte   = null;
    private bool   $compteLu = false;

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
     * Compte connecté, lu une seule fois par instance.
     *
     * Le cache est une PROPRIÉTÉ, pas une variable statique de méthode : une
     * statique est partagée par toutes les instances de la classe, et un
     * second contrôleur construit dans le même process (tests, appels en
     * série) hériterait de la session du premier.
     *
     * @return array<string,mixed>|null
     */
    private function compteConnecte(): ?array
    {
        if ($this->compteLu) {
            return $this->compte;
        }

        $userId        = (int)(Session::userId() ?? 0);
        $this->compte  = $userId > 0 ? ((new User())->findById($userId) ?: null) : null;
        $this->compteLu = true;

        return $this->compte;
    }

    /** Adresse du compte connecté, normalisée, ou null si visiteur. */
    private function emailCompte(): ?string
    {
        $user  = $this->compteConnecte();
        $email = strtolower(trim((string)($user['email'] ?? '')));

        return $email !== '' ? $email : null;
    }

    /**
     * Préremplit le formulaire avec les informations du compte connecté.
     *
     * Deux comportements distincts, et la distinction compte :
     *  - nom et téléphone sont des SUGGESTIONS : ils ne s'appliquent qu'aux
     *    champs encore vides, et le candidat peut les corriger (le nom d'état
     *    civil du dossier n'est pas forcément celui du compte) ;
     *  - l'e-mail est IMPOSÉ : c'est la clé qui relie le dossier au compte
     *    (findByIdWithOwnership, notifications, envoi des documents). Il écrase
     *    donc toujours la saisie, y compris après une erreur de validation, de
     *    sorte que le champ affiché corresponde à ce que le serveur retiendra.
     *
     * @param array<string,mixed> $old Saisie précédente (flash), éventuellement vide.
     * @return array<string,mixed>
     */
    private function prefillFromUser(array $old): array
    {
        if (!Session::isLoggedIn()) {
            return $old;
        }

        $user = $this->compteConnecte();
        if ($user === null) {
            return $old;
        }

        $vide = static fn(string $cle): bool => trim((string)($old[$cle] ?? '')) === '';

        $nomCompte = trim(trim((string)($user['prenom'] ?? '')) . ' ' . trim((string)($user['nom'] ?? '')));
        if ($nomCompte !== '' && $vide('nom_complet')) {
            $old['nom_complet'] = $nomCompte;
        }

        $email = $this->emailCompte();
        if ($email !== null) {
            $old['email'] = $email;
        }

        return $old;
    }

    /**
     * Contrôle serveur du verrou d'e-mail (barrière réelle : le `readonly` du
     * formulaire n'est qu'un confort, il se retire en deux clics).
     *
     * Retourne le message d'erreur à afficher, ou null si la saisie est
     * acceptable. Un visiteur non connecté n'est pas concerné.
     */
    private function erreurEmailCompte(array $p): ?string
    {
        $compte = $this->emailCompte();
        if ($compte === null) {
            return null;
        }

        $saisi = strtolower(trim((string)($p['email'] ?? '')));

        if ($saisi === '' || $saisi === $compte) {
            return null;
        }

        return 'L’adresse e-mail est celle de votre compte (' . $compte . ') : c’est elle qui '
             . 'rattache le dossier à votre espace. Pour en utiliser une autre, modifiez d’abord '
             . 'votre profil.';
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

    /**
     * Valide le niveau d'entrée posté et retourne le LIBELLÉ à enregistrer.
     *
     * Le formulaire propose une liste fermée, mais un POST direct peut envoyer
     * n'importe quoi : la liste du navigateur n'est pas une validation. On
     * n'accepte donc QUE les codes connus (L1 → M2), et l'on enregistre leur
     * libellé — c'est cette chaîne que les PDF et les e-mails affichent.
     *
     * Un code inconnu est refusé plutôt que conservé tel quel : le niveau
     * détermine désormais un tarif, et laisser passer une valeur libre
     * revient à laisser un candidat choisir sa ligne de grille.
     *
     * @return array{0:array<string,string>, 1:?string} [erreurs, libellé]
     */
    private function validerNiveau(mixed $poste): array
    {
        $libelle = Niveau::libellePourCode((string)$poste);

        if ($libelle === null) {
            return [['niveau_entree' => 'Choisissez votre niveau d’entrée.'], null];
        }

        return [[], $libelle];
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

    /**
     * Le règlement passe-t-il encore par la plateforme pour cet établissement ?
     *
     * Depuis la refonte (spec §1), la scolarité se règle en présentiel : la
     * plateforme ne collecte plus l'argent, elle en enregistre le montant à la
     * validation à 100 %. Le paiement en ligne n'a PAS été retiré du code — il
     * est piloté par `etablissements.mode_paiement_inscription` (spec §17), ce
     * qui permet à un partenaire de le réactiver sans redéveloppement.
     */
    private function paiementEnLigneActif(array $etab): bool
    {
        $mode  = (string)($etab['mode_paiement_inscription'] ?? 'presentiel');
        $total = (int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0);

        return in_array($mode, ['en_ligne', 'mixte'], true) && $total > 0;
    }

    /**
     * Libellés du fil d'étapes. Le parcours compte toujours trois temps
     * (spec §5) ; seule la nature du troisième change selon le mode de
     * règlement de l'établissement.
     *
     * @return array{labels:list<string>, paiement:bool, total:int}
     */
    private function etapesParcours(array $etab): array
    {
        $paiement = $this->paiementEnLigneActif($etab);

        return [
            'labels'   => ['Informations', 'Pièces du dossier', $paiement ? 'Paiement' : 'Confirmation'],
            'paiement' => $paiement,
            'total'    => 3,
        ];
    }

    /**
     * Charge le dossier en cours de saisie, ou redirige.
     *
     * Trois barrières, toutes côté serveur (spec §15.1) :
     *  1. l'identifiant vient de la SESSION, jamais de l'URL — il n'est donc
     *     ni devinable ni énumérable ;
     *  2. le dossier doit appartenir à l'établissement du parcours en cours ;
     *  3. le dossier doit appartenir au compte connecté.
     *
     * Retourne null après avoir déclenché la redirection.
     *
     * @return array<string,mixed>|null
     */
    private function chargerBrouillon(string $slug, array $etab): ?array
    {
        $sessionKey = $this->sessionKey($slug);

        if (empty($_SESSION[$sessionKey])) {
            Response::redirect("/orientation/{$slug}/inscription");
            return null;
        }

        $dossier = $this->dossierModel->findById((int)$_SESSION[$sessionKey]);

        $valide = $dossier !== null
            && (int)$dossier['etablissement_id'] === (int)$etab['id']
            && (
                empty($dossier['eleve_id'])
                || (int)$dossier['eleve_id'] === (int)Session::userId()
            );

        if (!$valide) {
            unset($_SESSION[$sessionKey]);
            Response::redirect("/orientation/{$slug}/inscription");
            return null;
        }

        return $dossier;
    }

    /**
     * Longueurs maximales, calées sur les colonnes de `dossiers_inscription`.
     *
     * MySQL tourne en `STRICT_TRANS_TABLES` : une valeur trop longue n'est pas
     * tronquée, l'INSERT lève « Data too long ». Le try/catch de la soumission
     * rattrapait bien l'exception, mais rendait au candidat un « Une erreur est
     * survenue. Veuillez réessayer. » sans dire QUEL champ pose problème : il
     * resoumettait la même valeur indéfiniment et ne pouvait jamais terminer sa
     * préinscription. Contrôler ici transforme une impasse en message précis.
     *
     * Le compte est fait en CARACTÈRES (`mb_strlen`), comme MySQL le fait pour
     * une colonne utf8mb4 : `strlen` compte des octets et refuserait à tort un
     * nom accentué bien plus court que la limite.
     *
     * @var array<string,array{int,string}> champ => [longueur max, libellé]
     */
    private const LONGUEURS = [
        'nom_complet'           => [200, 'Le nom complet'],
        'lieu_naissance'        => [120, 'Le lieu de naissance'],
        'nationalite'           => [80,  'La nationalité'],
        'email'                 => [150, 'L’adresse e-mail'],
        'indicatif_tel'         => [8,   'L’indicatif téléphonique'],
        'telephone'             => [30,  'Le numéro de téléphone'],
        'adresse'               => [255, 'L’adresse'],
        'ville'                 => [120, 'La ville'],
        'pays_provenance'       => [80,  'Le pays de provenance'],
        'dernier_etablissement' => [200, 'Le dernier établissement fréquenté'],
        'serie_bac'             => [20,  'La série du baccalauréat'],
        'filiere_visee'         => [190, 'La filière visée'],
        'specialite_visee'      => [190, 'La spécialité visée'],
        'niveau_entree'         => [60,  'Le niveau d’entrée'],
    ];

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

        // Bornes de longueur — appliquées à TOUT champ connu qui est présent,
        // pas seulement à ceux déclarés obligatoires : `indicatif_tel` et
        // `specialite_visee` ne figurent dans aucune règle et partaient
        // pourtant tels quels vers la base.
        foreach (self::LONGUEURS as $champ => [$max, $libelle]) {
            if (!isset($fields[$champ]) || isset($errors[$champ])) {
                continue;
            }
            if (mb_strlen(trim((string)$fields[$champ])) > $max) {
                $errors[$champ] = $libelle . ' ne doit pas dépasser ' . $max . ' caractères.';
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
            'etab'            => $etab,
            'formations'      => $formations,
            'filieres'        => $filieresCfg['options'],
            'specialites'     => $filieresCfg['specialites'],
            'indicatifs'      => self::INDICATIFS,
            'old'             => $old,
            'errors'          => $errors,
            // Non nul = compte connecté : le champ e-mail devient un champ
            // verrouillé, et le serveur refuse toute autre adresse.
            'emailVerrouille' => $this->emailCompte(),
            'csrf'            => Session::getCsrfToken(),
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

        // Verrou d'e-mail : l'adresse du compte connecté est celle du dossier.
        $erreurEmail = $this->erreurEmailCompte($p);
        if ($erreurEmail !== null) {
            $errors['email'] = $erreurEmail;
        }

        $filieresCfg = $this->filieresEtab((int)$etab['id']);
        [$errFiliere, $specialite] = $this->validerFiliereSpecialite(
            $filieresCfg,
            (string)($p['filiere_visee'] ?? ''),
            trim((string)($p['specialite_visee'] ?? ''))
        );
        $errors = array_merge($errors, $errFiliere);

        [$errNiveau, $niveauLibelle] = $this->validerNiveau($p['niveau_entree'] ?? '');
        $errors = array_merge($errors, $errNiveau);

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
                'niveau_entree'         => $niveauLibelle,
                'nom_complet'           => trim($p['nom_complet']),
                'genre'                 => $p['genre'],
                'date_naissance'        => $p['date_naissance'],
                'lieu_naissance'        => trim($p['lieu_naissance']),
                'nationalite'           => trim($p['nationalite']),
                // L'adresse du compte prime : elle est la clé de rattachement
                // du dossier à l'espace candidat (cf. erreurEmailCompte()).
                'email'                 => $this->emailCompte() ?? trim(strtolower($p['email'])),
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

    // ── Unicité de l'inscription (une par école et par année) ────────────────

    /**
     * L'inscription qui interdit d'en ouvrir une nouvelle dans cet
     * établissement pour l'année en cours, ou null.
     *
     * L'identité testée est celle du COMPTE connecté — l'étape 1 exige d'être
     * connecté (requireCompte) — complétée par l'adresse du dossier en cours de
     * saisie. Les deux, parce qu'un candidat peut avoir un dossier ouvert au
     * guichet sous son e-mail sans que `eleve_id` y ait encore été rattaché.
     *
     * @param array<string,mixed> $etab
     * @param string|null         $emailSaisi Adresse du formulaire, si connue.
     * @return array<string,mixed>|null
     */
    private function inscriptionExistante(array $etab, ?string $emailSaisi = null): ?array
    {
        // L'adresse du compte prime sur celle du formulaire : c'est elle que le
        // dossier portera (cf. erreurEmailCompte()).
        $email = $this->emailCompte()
            ?? (is_string($emailSaisi) ? trim(strtolower($emailSaisi)) : null);

        return UniciteInscription::existante(
            (int)$etab['id'],
            Session::userId(),
            $email
        );
    }

    /**
     * L'inscription déjà menée à 100 % dans un AUTRE établissement pour l'année
     * en cours, ou null.
     *
     * Complément indissociable de self::inscriptionExistante() : celle-ci
     * regarde l'établissement visé, celle-là tous les autres. Les deux
     * s'appellent aux mêmes endroits, dans cet ordre — un candidat qui reprend
     * son propre brouillon ici ne doit pas se heurter d'abord à un refus.
     *
     * @param array<string,mixed> $etab
     * @param string|null         $emailSaisi Adresse du formulaire, si connue.
     * @return array<string,mixed>|null
     */
    private function inscriptionFinaliseeAilleurs(array $etab, ?string $emailSaisi = null): ?array
    {
        $email = $this->emailCompte()
            ?? (is_string($emailSaisi) ? trim(strtolower($emailSaisi)) : null);

        return UniciteInscription::inscriptionFinaliseeAilleurs(
            (int)$etab['id'],
            Session::userId(),
            $email
        );
    }

    /**
     * Écran de refus « déjà inscrit ailleurs ».
     *
     * Il renvoie vers le dossier concerné et non vers une page morte : le
     * candidat doit pouvoir vérifier de quelle inscription on parle, et c'est
     * depuis ce dossier qu'il demandera une annulation s'il a changé d'avis.
     *
     * @param array<string,mixed> $etab
     * @param array<string,mixed> $ailleurs Retour de UniciteInscription.
     */
    private function refuserInscriptionAilleurs(array $etab, array $ailleurs): void
    {
        Response::view('orientation/candidature-fermee', [
            'etab'        => $etab,
            'titre'       => 'Vous êtes déjà inscrit pour cette année',
            'message'     => UniciteInscription::messageCandidatAutreEtablissement($ailleurs),
            'lien'        => url('/eleve/orientation/dossier/' . Hashid::encode((int)$ailleurs['id'])),
            'lienLibelle' => 'Voir mon inscription',
        ], '');
    }

    /**
     * Écran de refus : il nomme le dossier existant et renvoie vers l'espace
     * candidat, où il se poursuit. Un refus sans issue serait un cul-de-sac.
     *
     * @param array<string,mixed> $etab
     * @param array<string,mixed> $existante Retour de UniciteInscription.
     */
    private function refuserDoublonInscription(array $etab, array $existante): void
    {
        Response::view('orientation/candidature-fermee', [
            'etab'        => $etab,
            'titre'       => 'Inscription déjà ouverte',
            'message'     => UniciteInscription::messageCandidat($existante),
            'lien'        => url('/eleve/orientation/dossier/' . Hashid::encode((int)$existante['id'])),
            'lienLibelle' => 'Ouvrir mon dossier',
        ], '');
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

        // Une seule inscription par établissement et par année académique.
        // Contrôlé ICI pour ne pas faire remplir quinze champs à un candidat
        // qu'on refusera à la soumission — et de nouveau dans saveEtape1(),
        // parce qu'un écran n'est pas une garantie.
        $existante = $this->inscriptionExistante($etab);

        if ($existante !== null) {
            // Son propre brouillon, la session ayant été perdue (autre poste,
            // cookie expiré) : on le REPREND au lieu de le refuser. Le dossier
            // qui « bloque » est celui qu'il était en train de remplir — lui
            // opposer un écran de refus serait absurde. La session est réarmée
            // avec le même contrôle d'appartenance que chargerBrouillon().
            $sienBrouillon = (string)($existante['statut'] ?? '') === DossierStatut::BROUILLON
                && (int)($existante['eleve_id'] ?? 0) === (int)Session::userId()
                && (int)Session::userId() > 0;

            if ($sienBrouillon) {
                $_SESSION[$sessionKey] = (int)$existante['id'];
                Response::redirect("/orientation/{$slug}/inscription/etape-2");
                return;
            }

            $this->refuserDoublonInscription($etab, $existante);
            return;
        }

        // Une inscription menée à 100 % ailleurs ferme l'année entière. Contrôlé
        // ici pour ne pas laisser un candidat remplir un formulaire qu'on
        // refusera à l'enregistrement — et de nouveau dans saveEtape1().
        $ailleurs = $this->inscriptionFinaliseeAilleurs($etab);

        if ($ailleurs !== null) {
            $this->refuserInscriptionAilleurs($etab, $ailleurs);
            return;
        }

        $old    = $this->prefillFromUser(Session::getFlash('old', []));
        $errors = Session::getFlash('errors', []);

        Response::view('orientation/inscription-etape1', [
            'etab'            => $etab,
            'indicatifs'      => self::INDICATIFS,
            'old'             => $old,
            'errors'          => $errors,
            // Établissement fermé aux boursiers : seule reste « Je ne suis pas
            // boursier », et l'écran retire le bloc entier (PolitiqueBoursiers).
            'bourseActive'    => PolitiqueBoursiers::accepte($etab),
            'bourseOptions'   => PolitiqueBoursiers::options($etab),
            'bourseNote'      => SituationBourse::NOTE_EXPLICATIVE,
            'etapes'          => $this->etapesParcours($etab),
            // Non nul = compte connecté : champ e-mail verrouillé côté écran,
            // et refusé côté serveur s'il est forcé (erreurEmailCompte()).
            'emailVerrouille' => $this->emailCompte(),
            'csrf'            => Session::getCsrfToken(),
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

        // Établissement fermé aux boursiers : toute déclaration de bourse est
        // ramenée à « non boursier » AVANT validation. Le candidat n'a pas vu
        // la question ; un POST forgé qui la répond quand même n'obtient rien.
        $p = PolitiqueBoursiers::normaliserPost($etab, $_POST);

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

        // Verrou d'e-mail : l'adresse du compte connecté est celle du dossier.
        $erreurEmail = $this->erreurEmailCompte($p);
        if ($erreurEmail !== null) {
            $errors['email'] = $erreurEmail;
        }

        // ── Situation de bourse (spec §5.2) ──
        // Le formulaire affiche et masque les champs conditionnels en
        // JavaScript ; la validation est intégralement refaite ici. Une
        // soumission falsifiée côté client — radio retiré du DOM, date
        // effacée, valeur d'ENUM forgée — est rejetée comme un champ vide.
        $bourse = SituationBourse::valider($p);
        $errors = $errors + $bourse['erreurs'];

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $p);
            Response::redirect("/orientation/{$slug}/inscription");
            return;
        }

        // ── Une seule inscription par établissement et par année ───────────
        // Le verrou qui compte : l'écran a pu être contourné (formulaire resté
        // ouvert dans un onglet, POST direct, second dossier ouvert entre
        // l'affichage et l'envoi). Il est posé APRÈS la validation des champs
        // pour que le candidat voie d'abord ses éventuelles erreurs de saisie,
        // et JUSTE AVANT le create() : rien ne s'intercale entre le contrôle et
        // l'écriture.
        $existante = $this->inscriptionExistante($etab, $p['email'] ?? null);

        if ($existante !== null) {
            // Même traitement qu'à l'affichage : son propre brouillon se
            // REPREND, il ne se refuse pas. Cas réel — le formulaire est resté
            // ouvert dans un onglet pendant qu'un second l'a créé.
            if ((string)($existante['statut'] ?? '') === DossierStatut::BROUILLON
                && (int)Session::userId() > 0
                && (int)($existante['eleve_id'] ?? 0) === (int)Session::userId()
            ) {
                $_SESSION[$this->sessionKey($slug)] = (int)$existante['id'];
                Response::redirect("/orientation/{$slug}/inscription/etape-2");
                return;
            }

            $this->refuserDoublonInscription($etab, $existante);
            return;
        }

        // ── Une seule inscription ABOUTIE par année, tous établissements ────
        // Le verrou qui compte, posé au même endroit que le précédent : juste
        // avant create(), pour qu'aucun écran contourné (onglet resté ouvert,
        // POST direct) ne puisse ouvrir un second dossier ferme dans l'année.
        $ailleurs = $this->inscriptionFinaliseeAilleurs($etab, $p['email'] ?? null);

        if ($ailleurs !== null) {
            $this->refuserInscriptionAilleurs($etab, $ailleurs);
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
                // L'adresse du compte prime : elle est la clé de rattachement
                // du dossier à l'espace candidat (cf. erreurEmailCompte()).
                'email'                 => $this->emailCompte() ?? trim(strtolower($p['email'])),
                'indicatif_tel'         => $p['indicatif_tel'] ?? '+241',
                'telephone'             => trim($p['telephone']),
                'adresse'               => trim($p['adresse']),
                'ville'                 => trim($p['ville']),
                'pays_provenance'       => trim($p['pays_provenance']),
                'dernier_etablissement' => trim($p['dernier_etablissement']),
                'annee_academique'      => AnneeUniversitaire::code(),
                'statut'                => 'brouillon',
            ]);

            $_SESSION[$this->sessionKey($slug)] = $dossierId;

            // La déclaration de bourse passe par son service dédié : il pose
            // l'horodatage côté serveur et écrit historique_statut_bourse avec
            // declare_par = 'etudiant'.
            SituationBourse::enregistrer(
                $dossierId,
                $bourse['valeurs'],
                SituationBourse::PAR_ETUDIANT,
                Session::userId()
            );

            Journal::ecrire(Journal::DOSSIER_DEMARRE, [
                'utilisateur_id'   => Session::userId(),
                'objet_type'       => 'dossier',
                'objet_id'         => $dossierId,
                'etablissement_id' => (int)$etab['id'],
                'donnees'          => [
                    'etablissement' => $etab['nom'] ?? null,
                    'statut_bourse' => $bourse['valeurs']['statut_bourse'],
                ],
            ]);
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
        $etab    = $this->loadEtab($slug);
        $dossier = $this->chargerBrouillon($slug, $etab);
        if ($dossier === null) {
            return;
        }

        $filieresCfg  = $this->filieresEtab((int)$etab['id']);
        $statutBourse = $dossier['statut_bourse'] ?? null;

        // Liste construite à partir de la déclaration de l'étape 1 (spec §5.3).
        // Elle n'est PAS figée : l'attestation ANBG n'apparaît que pour un
        // candidat qui s'est déclaré boursier.
        //
        // Le filtre par NIVEAU, lui, ne peut pas être appliqué au rendu : le
        // niveau d'entrée se choisit sur CETTE page, sous les yeux du candidat.
        // On envoie donc toutes les pièces de la situation de bourse, chacune
        // portant sa portée résolue (data-niveaux), et le navigateur masque
        // celles qui ne concernent pas le niveau sélectionné. Le serveur
        // réapplique la même règle à la soumission — le filtrage client est un
        // confort d'affichage, jamais une autorisation.
        $pieces = $this->dossierModel->piecesPourBourse((int)$etab['id'], $statutBourse);

        Response::view('orientation/inscription-etape2', [
            'etab'          => $etab,
            'dossier'       => $dossier,
            'pieces'        => $pieces,
            'deposees'      => PieceDossier::deposeesParPiece((int)$dossier['id']),
            'statutBourse'  => $statutBourse,
            // Établissement fermé aux boursiers : le rappel « situation
            // déclarée » n'a plus lieu d'être — la question n'a jamais été
            // posée au candidat.
            'bourseActive'  => PolitiqueBoursiers::accepte($etab),
            'bourseLibelle' => SituationBourse::libelleCourt($statutBourse),
            'piecesBourse'  => SituationBourse::pieceAssociee($statutBourse),
            'tailleMax'     => PieceDossier::TAILLE_MAX_OCTETS,
            'tailleMaxTxt'  => PieceDossier::formaterTaille(PieceDossier::TAILLE_MAX_OCTETS),
            'pieceAccept'   => PieceDossier::acceptHtml(),
            'pieceFormats'  => PieceDossier::formatsLisibles(),
            'filieres'      => $filieresCfg['options'],
            'specialites'   => $filieresCfg['specialites'],
            'etapes'        => $this->etapesParcours($etab),
            'old'           => Session::getFlash('old', []),
            'errors'        => Session::getFlash('errors', []),
            'csrf'          => Session::getCsrfToken(),
        ], '');
    }

    public function saveEtape2(string $slug): void
    {
        $etab    = $this->loadEtab($slug);
        $dossier = $this->chargerBrouillon($slug, $etab);
        if ($dossier === null) {
            return;
        }

        $dossierId    = (int)$dossier['id'];
        $etabId       = (int)$etab['id'];
        $statutBourse = $dossier['statut_bourse'] ?? null;

        $filiere = $_POST['filiere_visee'] ?? '';

        $filieresCfg = $this->filieresEtab($etabId);
        [$errors, $specialite] = $this->validerFiliereSpecialite(
            $filieresCfg,
            (string)$filiere,
            trim((string)($_POST['specialite_visee'] ?? ''))
        );

        // Le couple formation + niveau fixe le tarif appliqué à l'inscription :
        // les deux sont contrôlés côté serveur contre la configuration réelle,
        // jamais contre ce que le navigateur a bien voulu envoyer.
        [$errNiveau, $niveau] = $this->validerNiveau($_POST['niveau_entree'] ?? '');
        $errors = array_merge($errors, $errNiveau);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        $this->dossierModel->updateFiliere($dossierId, (string)$filiere, (string)$niveau, $specialite ?: null);

        // Le niveau vient d'être écrit : le dossier en mémoire doit le porter
        // AVANT que les pièces attendues n'en soient déduites. Sans cela, le
        // périmètre serait calculé sur le niveau précédent (ou sur rien du
        // tout au premier passage).
        $dossier['niveau_entree']    = (string)$niveau;
        $dossier['filiere_visee']    = (string)$filiere;
        $dossier['specialite_visee'] = $specialite ?: null;

        // ── Dépôt des pièces ──
        // Le lot est borné à la liste attendue POUR CE DOSSIER — niveau
        // d'entrée ET situation de bourse compris. Un champ « piece_X » forgé
        // qui ne figure pas dans cette liste est ignoré.
        $attendues = $this->dossierModel->piecesAttenduesPourDossier($dossier);
        $depot     = PieceDossier::deposerLot($_FILES, $dossier, $attendues, Session::userId(), $statutBourse);

        // ── Contrôle serveur des pièces obligatoires (spec §5.2) ──
        // Recalculé ici à partir de ce qui est enregistré en BASE, jamais d'une
        // valeur postée : masquer un champ côté client ne dispense de rien.
        // L'attestation de bourse est exigée d'un boursier confirmé ; une pièce
        // réservée à un autre niveau ne l'est de personne d'autre.
        $manquantes = $this->dossierModel->piecesManquantesPourDossier($dossier, []);

        if ($manquantes !== [] || $depot['erreurs'] !== []) {
            $messages = [];

            if ($manquantes !== []) {
                $libelles = array_map(
                    static fn(array $p): string => (string)$p['libelle'],
                    $manquantes
                );
                $messages[] = 'Pièces obligatoires manquantes : ' . implode(', ', $libelles) . '.';
            }

            foreach ($depot['erreurs'] as $erreur) {
                $messages[] = $erreur;
            }

            Session::flash('errors', ['pieces' => implode(' ', $messages)]);
            Session::flash('old', $_POST);
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        // ── Étape 3 ──
        // Paiement en ligne encore actif pour cet établissement : on garde le
        // parcours MoneyFusion, le dossier n'est soumis qu'après encaissement.
        if ($this->paiementEnLigneActif($etab)) {
            Response::redirect("/orientation/{$slug}/inscription/etape-3");
            return;
        }

        // Mode présentiel (défaut depuis la refonte) : le dossier part tout de
        // suite à l'établissement. La scolarité se règlera sur place.
        $resultat = SoumissionDossier::executer($dossier, $etab, Session::userId());

        if (!$resultat['ok']) {
            Session::flash('errors', ['general' => (string)$resultat['erreur']]);
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        $dossierFinal = $resultat['dossier'] ?? $dossier;
        if (!empty($dossierFinal['email'])) {
            $this->envoyerEmailDossierRecu(
                (string)$dossierFinal['email'],
                (string)($dossierFinal['nom_complet'] ?? 'Candidat'),
                $etab,
                $dossierFinal
            );
        }

        unset($_SESSION[$this->sessionKey($slug)]);
        Response::redirect(
            "/orientation/{$slug}/inscription/confirmation?dossier=" . Hashid::encode($dossierId)
        );
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
            // Le gabarit écrivait ses trois pastilles en dur, et nommait
            // l'étape 2 « Formation & dossier » là où tout le reste du parcours
            // affiche « Pièces du dossier ». Il lit désormais la même source
            // que les autres écrans.
            'etapes'   => $this->etapesParcours($etab),
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

    // ── INSCRIPTION ÉTAPE 3 : confirmation (mode présentiel) ─────────────────

    /**
     * Écran de confirmation « Dossier envoyé » (spec §5.4).
     *
     * Aucune saisie : il annonce la référence, rappelle explicitement que le
     * candidat n'est PAS encore inscrit, et renvoie vers son espace. C'est le
     * point de la spec §2 sur lequel il ne faut pas transiger — un dossier
     * déposé n'est pas une inscription, et l'interface doit le dire.
     */
    public function inscriptionConfirmation(string $slug): void
    {
        $etab    = $this->loadEtab($slug);
        $dossier = $this->chargerDossierPossede($slug, $etab, (string)($_GET['dossier'] ?? ''));

        if ($dossier === null) {
            return;
        }

        // Un dossier encore en brouillon n'a rien à confirmer : on renvoie le
        // candidat là où il s'est arrêté.
        if (DossierStatut::normaliser((string)$dossier['statut']) === DossierStatut::BROUILLON) {
            Response::redirect("/orientation/{$slug}/inscription/etape-2");
            return;
        }

        $jeton = JetonDossier::actif((int)$dossier['id']);

        Response::view('orientation/inscription-confirmation', [
            'etab'          => $etab,
            'dossier'       => $dossier,
            'reference'     => (string)($dossier['reference'] ?? ''),
            'codeCourt'     => $jeton !== null ? JetonDossier::formaterCodeCourt($jeton['code_court']) : null,
            'prochaines'    => SoumissionDossier::prochainesEtapes($etab),
            'filiere'       => $this->filiereAvecSpecialite($dossier),
            'bourseLibelle' => SituationBourse::libelleCourt($dossier['statut_bourse'] ?? null),
            'etapes'        => $this->etapesParcours($etab),
            'anneeLibelle'  => AnneeUniversitaire::courante(),
        ], '');
    }

    /**
     * Charge un dossier désigné par un identifiant haché, en vérifiant la
     * propriété (spec §15.1 : ne jamais se reposer sur la difficulté à deviner
     * un identifiant). Tolère un identifiant numérique hérité, mais applique
     * alors les mêmes contrôles.
     *
     * @return array<string,mixed>|null
     */
    private function chargerDossierPossede(string $slug, array $etab, string $identifiant): ?array
    {
        $identifiant = trim($identifiant);
        if ($identifiant === '') {
            Response::redirect("/orientation/{$slug}");
            return null;
        }

        $dossierId = Hashid::decode($identifiant) ?? (ctype_digit($identifiant) ? (int)$identifiant : null);
        $userId    = Session::userId();

        if ($dossierId === null || $userId === null) {
            Response::redirect("/orientation/{$slug}");
            return null;
        }

        // Appartenance vérifiée EN BASE, pas en PHP : findByIdWithOwnership
        // ne rend la ligne que si le dossier est rattaché au compte, par
        // identifiant ou par e-mail (dossier ouvert au guichet avant la
        // création du compte). Un identifiant deviné ne suffit donc pas.
        $email   = (string)((new User())->findById($userId)['email'] ?? '');
        $dossier = $this->dossierModel->findByIdWithOwnership($dossierId, $userId, $email);

        if ($dossier === null || (int)$dossier['etablissement_id'] !== (int)$etab['id']) {
            Response::redirect("/orientation/{$slug}");
            return null;
        }

        return $dossier;
    }

    public function inscriptionSucces(string $slug): void
    {
        $etab    = $this->loadEtab($slug);
        $dossier = $this->chargerDossierPossede($slug, $etab, (string)($_GET['dossier'] ?? ''));

        if ($dossier === null) {
            return;
        }

        $dossierId = (int)$dossier['id'];

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

            // Plafond de taille : celui de PieceDossier, jamais celui, plus
            // large, du stockage. Sans ce test, un client qui contourne le
            // contrôle JavaScript déposait un fichier au-delà de la limite
            // annoncée au candidat.
            if ((int)$file['size'] > PieceDossier::TAILLE_MAX_OCTETS) {
                continue;
            }

            // Le type RÉEL du fichier, jamais celui annoncé par le navigateur :
            // c'est cette valeur qui est stockée en base, et c'est elle que la
            // page de consultation renverra au navigateur (le contenu B2 étant
            // désormais chiffré, il n'y a plus de MIME exploitable côté bucket).
            $finfo   = new \finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($file['tmp_name']) ?: (string)$file['type'];
            if (!PieceDossier::typeAccepte($mime)) {
                continue;
            }
            $cle     = sprintf('dossiers/%d/pieces/%s-%s', $dossierId, $key, basename($file['name']));

            try {
                $storage->uploadChiffre($file['tmp_name'], $cle, $mime);
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
                // Jamais rattacher un dossier d'élève au compte d'un
                // gestionnaire d'établissement : espaces cloisonnés.
                if ($userModel->isEtablissementAccount((int) $existant['id'])) {
                    return null;
                }
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
