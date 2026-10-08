<?php
declare(strict_types=1);

use Core\Router;

$router = new Router();

// ── AUTH ──────────────────────────────────────────────────────────────────
$router->get('/auth/connexion',                 'AuthController@showConnexion');
$router->post('/auth/connexion',                'AuthController@connexion',         ['csrf']);
$router->get('/auth/choisir',                   'AuthController@showChoixInscription');
$router->get('/auth/inscription',               'AuthController@showInscription');
$router->post('/auth/inscription',              'AuthController@inscription',        ['csrf']);
$router->post('/auth/deconnexion',              'AuthController@deconnexion',        ['csrf']);
$router->get('/auth/mot-de-passe-oublie',       'AuthController@showForgot');
$router->post('/auth/mot-de-passe-oublie',      'AuthController@forgot',             ['csrf']);
$router->get('/auth/reinitialiser/{token}',     'AuthController@showReset');
$router->post('/auth/reinitialiser',            'AuthController@reset',              ['csrf']);

// Redirige la racine selon l'état de connexion
$router->get('/', 'HubController@root');

// Sitemap XML dynamique (pages publiques + chaque établissement actif)
$router->get('/sitemap.xml', 'SitemapController@index');

// ── MÉDIAS (repli storage/) ───────────────────────────────────────────────
// Images de publications et avatars écrits dans storage/ quand public/uploads
// n'est pas inscriptible en prod. Même visibilité qu'un fichier de public/ :
// noms aléatoires 32 hex, aucun contrôle d'accès supplémentaire à conserver.
$router->get('/media/{type}/{fichier}', 'MediaController@show');

// ── DOCUMENTATION (publique) ──────────────────────────────────────────────
$router->get('/documentation', 'DocumentationController@index');

// ── HUB ───────────────────────────────────────────────────────────────────
$router->get('/hub',                            'HubController@index',               ['auth']);
$router->get('/api/hub/stats',                  'HubController@stats',               ['auth']);

// ── ABONNEMENT (legacy) ───────────────────────────────────────────────────
// La plateforme est désormais 100 % gratuite : les anciennes URLs
// d'abonnement redirigent vers le hub pour ne casser aucun lien.
$router->get('/abonnement/choisir',             'HubController@legacyAbonnement',    ['auth']);
$router->get('/abonnement/confirmation',        'HubController@legacyAbonnement',    ['auth']);
$router->get('/abonnement/renouveler',          'HubController@legacyAbonnement',    ['auth']);

// ── APPRENTISSAGE ─────────────────────────────────────────────────────────
$router->get('/apprentissage',                              'Apprentissage\DashboardController@index',       ['auth']);
$router->get('/apprentissage/matieres',                     'Apprentissage\DashboardController@matieres',    ['auth']);
$router->get('/apprentissage/ressources',                   'Apprentissage\RessourceController@index',       ['auth']);
$router->get('/apprentissage/viewer/{id}',                  'Apprentissage\RessourceController@viewer',      ['auth']);
// Service des PDF rangés hors du web root (repli storage/, cf. Core\RessourceStorage).
// Pas de middleware : le contrôleur accepte une session ÉLÈVE **ou** ADMIN, les
// deux devant pouvoir ouvrir le document depuis leurs interfaces respectives.
$router->get('/ressources/fichier/{id}',                    'Apprentissage\RessourceController@fichier');
$router->get('/apprentissage/progression',                  'Apprentissage\ProgressionController@index',     ['auth']);
$router->get('/apprentissage/favoris',                      'Apprentissage\FavorisController@index',         ['auth']);
$router->get('/api/apprentissage/matieres',                 'Apprentissage\RessourceController@matieres',    ['auth']);
$router->get('/api/apprentissage/ressources',               'Apprentissage\RessourceController@liste',       ['auth']);
$router->get('/api/apprentissage/series',                   'Apprentissage\RessourceController@series',      ['auth']);
$router->post('/api/apprentissage/ia/question',             'Apprentissage\IaController@question',           ['auth', 'csrf']);
$router->get('/api/apprentissage/ia/historique/{id}',       'Apprentissage\IaController@historique',         ['auth']);
$router->post('/api/apprentissage/ia/nouvelle-conversation','Apprentissage\IaController@nouvelleConversation',['auth', 'csrf']);
$router->post('/api/apprentissage/progression',             'Apprentissage\ProgressionController@update',    ['auth', 'csrf']);
$router->post('/api/apprentissage/favoris/{id}',            'Apprentissage\FavorisController@toggle',        ['auth', 'csrf']);

// ── COMMUNAUTÉ ────────────────────────────────────────────────────────────
$router->get('/communaute',                                 'Communaute\FeedController@index',               ['auth']);
$router->get('/communaute/explorer',                        'Communaute\FeedController@explore',             ['auth']);
$router->get('/communaute/profil/{uuid}',                   'Communaute\UserController@show',                ['auth']);
$router->get('/communaute/mon-profil',                      'Communaute\UserController@monProfil',           ['auth']);
$router->get('/communaute/chat',                            'Communaute\ChatController@salons',              ['auth']);
$router->get('/communaute/chat/{salon_id}',                 'Communaute\ChatController@salon',               ['auth']);

$router->get('/api/communaute/posts',                       'Communaute\PostController@index',               ['auth']);
$router->post('/api/communaute/posts',                      'Communaute\PostController@store',               ['auth', 'csrf']);
$router->put('/api/communaute/posts/{id}',                  'Communaute\PostController@update',              ['auth', 'csrf']);
$router->delete('/api/communaute/posts/{id}',               'Communaute\PostController@destroy',             ['auth', 'csrf']);
$router->post('/api/communaute/posts/{id}/like',            'Communaute\PostController@like',                ['auth', 'csrf']);
$router->post('/api/communaute/posts/{id}/report',          'Communaute\PostController@report',              ['auth', 'csrf']);
$router->post('/api/communaute/posts/{id}/bookmark',        'Communaute\PostController@bookmark',            ['auth', 'csrf']);

$router->get('/api/communaute/posts/{id}/comments',         'Communaute\CommentController@index',            ['auth']);
$router->post('/api/communaute/posts/{id}/comments',        'Communaute\CommentController@store',            ['auth', 'csrf']);
$router->delete('/api/communaute/comments/{id}',            'Communaute\CommentController@destroy',          ['auth', 'csrf']);
$router->patch('/api/communaute/comments/{id}/best',        'Communaute\CommentController@markBest',         ['auth', 'csrf']);

$router->get('/api/communaute/salons/{id}/messages/poll',   'Communaute\ChatController@poll',                ['auth']);
$router->post('/api/communaute/salons/{id}/messages',       'Communaute\ChatController@send',                ['auth', 'csrf']);

$router->get('/api/notifications',                          'Communaute\NotificationController@index',       ['auth']);
$router->get('/api/notifications/count',                    'Communaute\NotificationController@count',       ['auth']);
$router->patch('/api/notifications/read-all',               'Communaute\NotificationController@readAll',     ['auth', 'csrf']);
$router->patch('/api/notifications/{id}/read',              'Communaute\NotificationController@read',        ['auth', 'csrf']);

$router->post('/api/users/{id}/follow',                     'Communaute\UserController@follow',              ['auth', 'csrf']);
$router->get('/api/users/search',                           'Communaute\UserController@search',              ['auth']);
$router->post('/api/communaute/profil/update',              'Communaute\UserController@updateProfile',       ['auth', 'csrf']);

$router->get('/api/communaute/comments/{id}/replies',       'Communaute\CommentController@replies',          ['auth']);
$router->post('/api/communaute/comments/{id}/like',         'Communaute\CommentController@likeComment',      ['auth', 'csrf']);

// ── ADMIN ─────────────────────────────────────────────────────────────────
$router->get('/Administratorconnect/login',                 'Admin\AdminAuthController@showLogin');
$router->post('/Administratorconnect/login',                'Admin\AdminAuthController@login',               ['csrf']);
$router->get('/Administratorconnect/forgot-password',       'Admin\AdminAuthController@forgotPassword');
$router->post('/Administratorconnect/forgot-password',      'Admin\AdminAuthController@sendResetLink',        ['csrf']);
$router->get('/Administratorconnect/reset-password',        'Admin\AdminAuthController@showResetPassword');
$router->post('/Administratorconnect/reset-password',       'Admin\AdminAuthController@updatePassword',      ['csrf']);

$router->post('/Administratorconnect/verifier-2fa',         'Admin\AdminAuthController@verify2fa',           ['csrf']);
$router->post('/admin/logout',                              'Admin\AdminAuthController@logout',              ['csrf']);

$router->get('/admin',                                      'Admin\DashboardController@index',               ['admin']);
$router->get('/admin/api/stats',                            'Admin\DashboardController@stats',               ['admin']);
$router->get('/admin/bilans/export',                        'Admin\BilansController@export',                 ['admin']);
// Diagnostic de la chaîne PDF (super-admin uniquement, contrôlé dans l'action).
$router->get('/admin/bilans/diagnostic',                    'Admin\BilansController@diagnostic',              ['admin']);

$router->get('/admin/utilisateurs',                         'Admin\UsersController@index',                   ['admin']);
$router->get('/admin/api/utilisateurs/statuts',             'Admin\UsersController@statuts',                 ['admin']);
$router->get('/admin/api/utilisateurs/{id}',                'Admin\UsersController@show',                    ['admin']);
$router->post('/admin/api/utilisateurs',                    'Admin\UsersController@store',                   ['admin', 'csrf']);
$router->patch('/admin/api/utilisateurs/{id}',              'Admin\UsersController@update',                  ['admin', 'csrf']);
$router->patch('/admin/api/utilisateurs/{id}/toggle',       'Admin\UsersController@toggle',                  ['admin', 'csrf']);
$router->delete('/admin/api/utilisateurs/{id}',             'Admin\UsersController@delete',                  ['admin', 'csrf']);
$router->post('/admin/api/utilisateurs/{id}/message',       'Admin\UsersController@sendMessage',             ['admin', 'csrf']);

// Commissions scolarité 10 % (tranches 40/30/30 encaissées en présentiel)
$router->get('/admin/api/utilisateurs/{id}/commissions',    'Admin\CommissionsController@forUser',           ['admin']);
$router->post('/admin/api/commissions',                     'Admin\CommissionsController@store',             ['admin', 'csrf']);
$router->patch('/admin/api/commissions/tranches/{id}',      'Admin\CommissionsController@toggleTranche',     ['admin', 'csrf']);

$router->get('/admin/contenu',                              'Admin\ContenuController@index',                 ['admin']);
$router->post('/admin/api/contenu/ressource',               'Admin\ContenuController@storeRessource',        ['admin', 'csrf']);
$router->patch('/admin/api/contenu/ressource/{id}',         'Admin\ContenuController@updateRessource',       ['admin', 'csrf']);
$router->delete('/admin/api/contenu/ressource/{id}',        'Admin\ContenuController@deleteRessource',       ['admin', 'csrf']);
$router->get('/admin/api/matieres',                         'Admin\ContenuController@getMatieres',           ['admin']);

$router->get('/admin/series-matieres',                      'Admin\SeriesController@index',                  ['admin']);
$router->post('/admin/api/series/serie',                    'Admin\SeriesController@storeSerie',              ['admin', 'csrf']);
$router->post('/admin/api/series/matiere',                  'Admin\SeriesController@storeMatiere',            ['admin', 'csrf']);
// AJOUT : Route de suppression pour les séries
$router->delete('/admin/api/series/serie/{id}',             'Admin\SeriesController@deleteSerie',            ['admin', 'csrf']);

$router->get('/admin/communaute',                           'Admin\CommunauteController@index',              ['admin']);
$router->delete('/admin/api/communaute/posts/{id}',         'Admin\CommunauteController@deletePost',         ['admin', 'csrf']);
$router->patch('/admin/api/communaute/posts/{id}/pin',      'Admin\CommunauteController@pinPost',            ['admin', 'csrf']);
$router->patch('/admin/api/communaute/reports/{id}',        'Admin\CommunauteController@traiterReport',      ['admin', 'csrf']);

$router->get('/admin/signalements',                         'Admin\SignalementsController@index',             ['admin']);
$router->patch('/admin/api/signalements/{id}',              'Admin\SignalementsController@traiter',           ['admin', 'csrf']);

// ── ANNONCES ───────────────────────────────────────────────────────────────
$router->get('/admin/annonces',                             'Admin\AnnoncesController@index',                ['admin']);
$router->get('/admin/api/annonces/{id}',                    'Admin\AnnoncesController@show',                 ['admin']);
$router->post('/admin/api/annonces',                        'Admin\AnnoncesController@store',                ['admin', 'csrf']);
$router->patch('/admin/api/annonces/{id}',                  'Admin\AnnoncesController@update',               ['admin', 'csrf']);
$router->patch('/admin/api/annonces/{id}/toggle',           'Admin\AnnoncesController@toggle',               ['admin', 'csrf']);
$router->delete('/admin/api/annonces/{id}',                 'Admin\AnnoncesController@delete',               ['admin', 'csrf']);
$router->get('/api/annonces/active',                        'Admin\AnnoncesController@activeForHub',         ['auth']);

$router->get('/admin/paiement',                                      'Admin\PaiementController@index',               ['admin']);
// ── Suivi des commissions (spec §10.3) ────────────────────────────────────
// Volontairement HORS des préfixes AdminPermissions : c'est la vue comptable
// transversale de la plateforme, réservée au super administrateur — même
// principe que /admin/securite.
$router->get('/admin/commissions',                                   'Admin\SuiviCommissionsController@index',       ['admin']);
$router->post('/admin/commissions/{id}/statut',                      'Admin\SuiviCommissionsController@changerStatut', ['admin', 'csrf']);
// Encaissement tranche par tranche (40/30/30) : c'est le super administrateur
// qui voit l'argent arriver, l'établissement lit le résultat sur son tableau.
$router->post('/admin/commissions/tranches/{id}',                    'Admin\SuiviCommissionsController@tranche',      ['admin', 'csrf']);
// Abandon d'année — super administrateur uniquement (garde doublée côté
// contrôleur : une route reste appelable à la main).
$router->post('/admin/commissions/{id}/abandon',                     'Admin\SuiviCommissionsController@abandon',      ['admin', 'csrf']);
$router->post('/admin/commissions/{id}/abandon/lever',               'Admin\SuiviCommissionsController@leverAbandon', ['admin', 'csrf']);
$router->post('/admin/commissions/{id}/revision/clore',              'Admin\SuiviCommissionsController@cloreRevision', ['admin', 'csrf']);

// ── Traçabilité super administrateur (spec §11, §13.4) ────────────────────
// Hors préfixes AdminPermissions : réservé au super administrateur, comme
// /admin/securite. Le journal est en CONSULTATION SEULE — aucune route ne
// permet d'en modifier ou d'en supprimer une entrée (spec §11.3).
$router->get('/admin/utilisateurs/{id}',                        'Admin\TracabiliteController@utilisateur',    ['admin']);
$router->get('/admin/dossiers',                                 'Admin\TracabiliteController@dossiers',       ['admin']);
$router->get('/admin/journal',                                  'Admin\TracabiliteController@journal',        ['admin']);
$router->get('/admin/tableaux-de-bord',                         'Admin\TracabiliteController@tableauxDeBord', ['admin']);
$router->post('/admin/etablissements/{id}/taux',                'Admin\TracabiliteController@modifierTaux',   ['admin', 'csrf']);
$router->post('/admin/dossiers/{id}/revoquer-jeton',            'Admin\TracabiliteController@revoquerJeton',  ['admin', 'csrf']);
$router->get('/admin/api/paiement/transactions',                     'Admin\PaiementController@transactions',         ['admin']);
$router->post('/admin/api/paiement/transactions/{id}/confirmer',     'Admin\PaiementController@confirmer',            ['admin', 'csrf']);
$router->post('/admin/api/paiement/sync',                            'Admin\PaiementController@sync',                  ['admin', 'csrf']);

// ── Orientation — Pages publiques ────────────────────────────────────────────
$router->get('/orientation',                                    'Orientation\MarketplaceController@index');

// Vérification d'authenticité des documents (scan du QR code imprimé sur les
// récépissés de préinscription et attestations d'inscription) — public
$router->get('/verifier/{token}',                               'Orientation\VerificationController@show');

// ── Vérification d'un DOSSIER par scan QR / code court (spec §7) ───────────
// À distinguer de /verifier/{token} au-dessus, qui atteste l'authenticité d'un
// DOCUMENT et reste public. Ici l'agent consulte l'état réel d'un dossier et
// peut décider : authentification exigée, cloisonnement par établissement,
// limitation de débit à 30 tentatives/minute/IP, et journalisation de TOUS les
// scans — succès comme échecs.
$router->get('/v/{token}',                                      'Orientation\VerificationDossierController@show');
$router->post('/verification/code-court',                       'Orientation\VerificationDossierController@parCodeCourt', ['csrf']);
$router->get('/orientation/universites',                        'Orientation\MarketplaceController@universites');
$router->get('/orientation/grandes-ecoles',                     'Orientation\MarketplaceController@grandesEcoles');
$router->get('/orientation/recherche',                          'Orientation\MarketplaceController@recherche');

// Compte express (grand public — suivi des dossiers sans compte élève)
$router->get('/orientation/compte',                             'Orientation\CompteOrientationController@show');
$router->post('/orientation/compte',                            'Orientation\CompteOrientationController@store', ['csrf']);

// Avis 5 étoiles sur les établissements (façon Google Reviews)
$router->get('/api/orientation/avis/{id}',                      'Orientation\AvisController@liste');
$router->post('/api/orientation/avis',                          'Orientation\AvisController@store',   ['auth', 'csrf']);
$router->delete('/api/orientation/avis/{id}',                   'Orientation\AvisController@destroy', ['auth', 'csrf']);

// Préinscription
$router->get('/orientation/{slug}/preinscription',              'Orientation\CandidatureController@preinscription');
$router->post('/orientation/{slug}/preinscription',             'Orientation\CandidatureController@submitPreinscription', ['csrf']);
$router->get('/orientation/{slug}/preinscription/succes',       'Orientation\CandidatureController@preinscriptionSucces');
// Attestation de préinscription rendue à la demande (jeton opaque + laissez-passer
// session/propriétaire) — remplace l'URL signée B2 qui exposait l'ID du dossier.
$router->get('/orientation/{slug}/preinscription/attestation',  'Orientation\CandidatureController@preinscriptionAttestation');

// Inscription (wizard 3 étapes — infos → formation & dossier → paiement
// MoneyFusion ; le dossier passe en "paye" une fois le paiement confirmé,
// l'attestation est délivrée après validation par l'établissement)
$router->get('/orientation/{slug}/inscription',                         'Orientation\CandidatureController@inscriptionEtape1');
$router->post('/orientation/{slug}/inscription/etape-1',                'Orientation\CandidatureController@saveEtape1',        ['csrf']);
$router->get('/orientation/{slug}/inscription/etape-2',                 'Orientation\CandidatureController@inscriptionEtape2');
$router->post('/orientation/{slug}/inscription/etape-2',                'Orientation\CandidatureController@saveEtape2',        ['csrf']);
$router->get('/orientation/{slug}/inscription/etape-3',                 'Orientation\CandidatureController@inscriptionEtape3');
$router->post('/orientation/{slug}/inscription/etape-3/payer',          'Orientation\CandidatureController@payEtape3',         ['csrf']);
$router->get('/orientation/{slug}/inscription/retour',                  'Orientation\CandidatureController@retourPaiement');
$router->get('/orientation/{slug}/inscription/succes',                  'Orientation\CandidatureController@inscriptionSucces');
// Étape 3 en mode présentiel (défaut) : écran de confirmation « Dossier envoyé ».
// L'identifiant du dossier circule haché (Core\Hashid) et l'appartenance est
// revérifiée en base — cf. spec §5.4 et §15.1.
$router->get('/orientation/{slug}/inscription/confirmation',            'Orientation\CandidatureController@inscriptionConfirmation');

// Webhook MoneyFusion orientation
$router->post('/webhook/orientation/payin',                             'Orientation\PaiementOrientationController@webhookPayin');
$router->post('/webhook/orientation/payout',                            'Orientation\PaiementOrientationController@webhookPayout');

$router->get('/orientation/{slug}/actualite/{id}',             'Orientation\PublicController@actualite');
$router->get('/orientation/{slug}/formations/{id}',            'Orientation\PublicController@formationDetail');
$router->get('/orientation/{slug}',                             'Orientation\PublicController@show');

// ── Orientation — Établissements ─────────────────────────────────────────────
$router->get('/admin/orientation',                              'Admin\OrientationController@index',                 ['admin']);
$router->get('/admin/orientation/creer',                        'Admin\OrientationController@create',                ['admin']);
$router->post('/admin/orientation/presign',                     'Admin\OrientationController@presign',               ['admin', 'csrf']);
$router->post('/admin/orientation',                             'Admin\OrientationController@store',                 ['admin', 'csrf']);
$router->get('/admin/orientation/{id}/voir',                    'Admin\OrientationController@show',                  ['admin']);
$router->get('/admin/orientation/{id}/modifier',                'Admin\OrientationController@edit',                  ['admin']);
$router->post('/admin/orientation/{id}/modifier',               'Admin\OrientationController@update',                ['admin', 'csrf']);
$router->get('/admin/orientation/{id}/paiement',                'Admin\OrientationController@showPaiement',          ['admin']);
$router->post('/admin/orientation/{id}/paiement',               'Admin\OrientationController@updatePaiement',        ['admin', 'csrf']);
$router->post('/admin/orientation/{id}/activer-inscription',    'Admin\OrientationController@activerInscription',    ['admin', 'csrf']);

// Formations & grille tarifaire (scolarité par niveau et situation de bourse).
// Mêmes tables que /gestionnaire/filieres : le super administrateur et le
// gestionnaire alimentent un seul catalogue, jamais deux.
$router->post('/admin/orientation/{id}/formations',             'Admin\OrientationController@storeFiliere',          ['admin', 'csrf']);
$router->post('/admin/orientation/{id}/formations/{fid}/tarifs','Admin\OrientationController@tarifsFiliere',         ['admin', 'csrf']);
$router->post('/admin/orientation/{id}/formations/{fid}/delete','Admin\OrientationController@destroyFiliere',        ['admin', 'csrf']);

// Paiements globaux (tous établissements)
$router->get('/admin/orientation/paiements',                    'Admin\PaiementsOrientationController@index',        ['admin']);
$router->get('/admin/orientation/paiements/{id}/recu',          'Admin\PaiementsOrientationController@recu',         ['admin']);

// Réconciliation reversements
$router->get('/admin/orientation/reconciliation',               'Admin\ReconciliationController@index',              ['admin']);
$router->post('/admin/orientation/reconciliation/{id}/rejouer', 'Admin\ReconciliationController@rejouer',            ['admin', 'csrf']);
$router->post('/admin/orientation/gestionnaires/{id}/toggle',   'Admin\OrientationController@toggleGestionnaire',   ['admin', 'csrf']);
$router->post('/admin/orientation/{id}/message',                'Admin\OrientationController@sendMessage',           ['admin', 'csrf']);

$router->get('/admin/analytics',                            'Admin\AnalyticsController@index',               ['admin']);

$router->get('/admin/notifications',                        'Admin\NotificationsController@index',            ['admin']);
$router->post('/admin/api/notifications/mark-all',          'Admin\NotificationsController@markAllRead',      ['admin', 'csrf']);

$router->get('/admin/parametres',                           'Admin\ParametresController@index',               ['admin']);
$router->post('/admin/parametres',                          'Admin\ParametresController@save',                ['admin', 'csrf']);
$router->post('/admin/parametres/2fa/activer',              'Admin\ParametresController@enable2fa',           ['admin', 'csrf']);
$router->post('/admin/parametres/2fa/desactiver',           'Admin\ParametresController@disable2fa',          ['admin', 'csrf']);
$router->post('/admin/parametres/password',                 'Admin\ParametresController@changePassword',      ['admin', 'csrf']);
$router->post('/admin/api/cache/clear',                     'Admin\ParametresController@clearCache',          ['admin', 'csrf']);
$router->post('/admin/api/admins',                          'Admin\ParametresController@storeAdmin',          ['admin', 'csrf']);
$router->delete('/admin/api/admins/{id}',                   'Admin\ParametresController@deleteAdmin',         ['admin', 'csrf']);

// ── SÉCURITÉ (détection d'intrusion — super-admin uniquement) ───────────────
// Le chemin /admin/securite n'est dans aucune liste de rôle (AdminPermissions)
// → seul super_admin y accède. Double-garde aussi dans le contrôleur.
$router->get('/admin/securite',                             'Admin\SecurityController@index',                 ['admin']);
$router->get('/admin/api/securite/count',                   'Admin\SecurityController@count',                 ['admin']);
$router->post('/admin/api/securite/vue',                    'Admin\SecurityController@markAllSeen',           ['admin', 'csrf']);
$router->post('/admin/api/securite/vider',                  'Admin\SecurityController@clearResolved',         ['admin', 'csrf']);
$router->post('/admin/api/securite/blocage',                'Admin\SecurityController@toggleBlocking',        ['admin', 'csrf']);
$router->post('/admin/api/securite/{id}/statut',            'Admin\SecurityController@updateStatut',          ['admin', 'csrf']);

// ── CONTACT ───────────────────────────────────────────────────────────────
$router->get('/contact',                                    'ContactController@index');
$router->post('/api/contact',                               'ContactController@submit');
$router->get('/admin/contact',                              'Admin\ContactController@index',                  ['admin']);
$router->patch('/admin/api/contact/{id}/read',              'Admin\ContactController@markRead',               ['admin', 'csrf']);
$router->post('/admin/api/contact/mark-all-read',           'Admin\ContactController@markAllRead',            ['admin', 'csrf']);
$router->delete('/admin/api/contact/{id}',                  'Admin\ContactController@delete',                 ['admin', 'csrf']);

// ── ESPACE ÉLÈVE — Orientation ────────────────────────────────────────────
$router->get('/eleve/orientation',                              'Orientation\EspaceEleveOrientationController@index',   ['auth']);
$router->get('/eleve/orientation/profil',                       'Orientation\EspaceEleveOrientationController@profil',  ['auth']);
$router->post('/eleve/orientation/profil',                      'Orientation\EspaceEleveOrientationController@updateProfil',      ['auth', 'csrf']);
$router->post('/eleve/orientation/profil/mot-de-passe',         'Orientation\EspaceEleveOrientationController@updatePassword',    ['auth', 'csrf']);
$router->get('/eleve/orientation/preinscriptions',              'Orientation\EspaceEleveOrientationController@preinscriptions',   ['auth']);
$router->get('/eleve/orientation/inscriptions',                 'Orientation\EspaceEleveOrientationController@inscriptions',      ['auth']);
$router->get('/eleve/orientation/paiements',                    'Orientation\EspaceEleveOrientationController@paiements',         ['auth']);
$router->get('/eleve/orientation/recus',                        'Orientation\EspaceEleveOrientationController@recus',             ['auth']);
$router->get('/eleve/orientation/documents',                    'Orientation\EspaceEleveOrientationController@documents',         ['auth']);
$router->get('/eleve/orientation/notifications',                'Orientation\EspaceEleveOrientationController@notifications',     ['auth']);
$router->post('/eleve/orientation/notifications/tout-lu',       'Orientation\EspaceEleveOrientationController@marquerNotificationsLues', ['auth', 'csrf']);
// Identifiants de dossier hachés (jeton opaque HMAC, cf. Core\Hashid) — plus de
// PK séquentielle exposée dans l'URL, énumération impossible.
$router->get('/eleve/orientation/document/{hash}',            'Orientation\EspaceEleveOrientationController@document',   ['auth']);
$router->get('/eleve/orientation/dossier/{hash}/recepisse',    'Orientation\EspaceEleveOrientationController@recepisse',   ['auth']);
$router->get('/eleve/orientation/dossier/{hash}/attestation',  'Orientation\EspaceEleveOrientationController@attestation', ['auth']);
// Reçu de règlement de scolarité : preuve du VERSEMENT, à ne pas confondre
// avec l'attestation, qui prouve l'inscription. Rendu à la demande.
$router->get('/eleve/orientation/dossier/{hash}/recu',         'Orientation\EspaceEleveOrientationController@recuInscription', ['auth']);
$router->get('/eleve/orientation/dossier/{hash}',              'Orientation\EspaceEleveOrientationController@dossier', ['auth']);
// Mise à jour de la situation de bourse par l'étudiant (spec §9.4).
// Possible à tout moment, y compris après l'inscription définitive : elle ne
// modifie ni le statut du dossier, ni la commission.
$router->post('/eleve/orientation/dossier/{hash}/bourse',     'Orientation\EspaceEleveOrientationController@updateBourse', ['auth', 'csrf']);
// Complément de dossier (spec §9.3) : remplacer une pièce refusée, déposer une
// pièce demandée. Sans ces routes, une notification « pièce non conforme » ne
// mène nulle part et le dossier reste bloqué jusqu'à un appel téléphonique.
$router->post('/eleve/orientation/dossier/{hash}/piece',      'Orientation\EspaceEleveOrientationController@remplacerPiece',  ['auth', 'csrf']);
$router->post('/eleve/orientation/dossier/{hash}/renvoyer',   'Orientation\EspaceEleveOrientationController@renvoyerDossier', ['auth', 'csrf']);

// ── GESTIONNAIRE — Authentification (espace dédié) ───────────────────────
$router->get('/gestionnaire/login',                             'Gestionnaire\GestionnaireAuthController@showLogin');
$router->post('/gestionnaire/login',                            'Gestionnaire\GestionnaireAuthController@login',   ['csrf']);
$router->post('/gestionnaire/verifier-2fa',                     'Gestionnaire\GestionnaireAuthController@verify2fa', ['csrf']);
$router->post('/gestionnaire/logout',                           'Gestionnaire\GestionnaireAuthController@logout',  ['csrf']);

// ── GESTIONNAIRE — Dashboard ──────────────────────────────────────────────
$router->get('/gestionnaire',                                   'Gestionnaire\GestionnaireDashboardController@index',   ['gestionnaire']);
$router->get('/gestionnaire/bilan',                             'Gestionnaire\GestionnaireDashboardController@bilan',   ['gestionnaire']);
$router->get('/gestionnaire/commissions',                       'Gestionnaire\CommissionGestionnaireController@index',  ['gestionnaire']);

// ── GESTIONNAIRE — Candidatures ───────────────────────────────────────────
// Écran de scan du guichet (spec §8.5) : caméra si le navigateur sait décoder,
// sinon appareil photo du téléphone, et dans tous les cas saisie du code court.
$router->get('/gestionnaire/scanner',                           'Gestionnaire\ScannerController@index',                      ['gestionnaire']);
$router->get('/gestionnaire/candidatures',                      'Gestionnaire\CandidatureGestionnaireController@index',       ['gestionnaire']);
$router->get('/gestionnaire/candidatures/creer',                'Gestionnaire\CandidatureGestionnaireController@create',      ['gestionnaire']);
$router->post('/gestionnaire/candidatures/creer',               'Gestionnaire\CandidatureGestionnaireController@store',       ['gestionnaire', 'csrf']);
$router->get('/gestionnaire/candidatures/{id}',                 'Gestionnaire\CandidatureGestionnaireController@show',        ['gestionnaire']);
$router->get('/gestionnaire/candidatures/{id}/recepisse',       'Gestionnaire\CandidatureGestionnaireController@recepisse',   ['gestionnaire']);
$router->get('/gestionnaire/candidatures/{id}/attestation',     'Gestionnaire\CandidatureGestionnaireController@attestation', ['gestionnaire']);
// Reçu de règlement de scolarité, réimprimable au guichet (rendu à la demande).
$router->get('/gestionnaire/candidatures/{id}/recu',            'Gestionnaire\CandidatureGestionnaireController@recu',        ['gestionnaire']);
// Consultation d'une pièce justificative. Elle transite par l'application au
// lieu d'une URL signée Backblaze : les pièces sont chiffrées au repos
// (AES-256-GCM), et l'appartenance du dossier est revérifiée à chaque ouverture.
$router->get('/gestionnaire/candidatures/{id}/document/{documentId}', 'Gestionnaire\CandidatureGestionnaireController@document', ['gestionnaire']);
$router->post('/gestionnaire/candidatures/{id}/statut',         'Gestionnaire\CandidatureGestionnaireController@updateStatut',['gestionnaire', 'csrf']);
// ── Zone décision (spec §8.2 → §8.4) ──────────────────────────────────────
// Chaque décision passe par la machine à états : aucune de ces routes n'écrit
// directement la colonne `statut`. L'appartenance du dossier à l'établissement
// est revérifiée côté service, jamais déduite de l'URL.
$router->post('/gestionnaire/candidatures/{id}/valider-50',     'Gestionnaire\DecisionDossierController@valider50',     ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/demander-piece', 'Gestionnaire\DecisionDossierController@demanderPiece', ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/rejeter',        'Gestionnaire\DecisionDossierController@rejeter',       ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/valider-100',    'Gestionnaire\DecisionDossierController@valider100',    ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/prolonger',      'Gestionnaire\DecisionDossierController@prolonger',     ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/relancer',       'Gestionnaire\DecisionDossierController@relancer',      ['gestionnaire', 'csrf']);
// Retour arrière sur la dernière décision (droit à l'erreur du guichet).
// L'état rendu n'est pas dans l'URL : il est déduit de l'état réel du dossier.
$router->post('/gestionnaire/candidatures/{id}/annuler-decision', 'Gestionnaire\DecisionDossierController@annuler',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/piece/{documentId}', 'Gestionnaire\DecisionDossierController@deciderPiece', ['gestionnaire', 'csrf']);
$router->get('/gestionnaire/candidatures/{id}/modifier',        'Gestionnaire\CandidatureGestionnaireController@edit',        ['gestionnaire']);
$router->post('/gestionnaire/candidatures/{id}/modifier',       'Gestionnaire\CandidatureGestionnaireController@update',      ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/sms',            'Gestionnaire\CandidatureGestionnaireController@sendSms',     ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/candidatures/{id}/message',        'Gestionnaire\CandidatureGestionnaireController@envoyerMessage', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Formations ─────────────────────────────────────────────
$router->get('/gestionnaire/formations',                        'Gestionnaire\FormationController@index',   ['gestionnaire']);
$router->post('/gestionnaire/formations',                       'Gestionnaire\FormationController@store',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/formations/{id}',                  'Gestionnaire\FormationController@update',  ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/formations/{id}/delete',           'Gestionnaire\FormationController@destroy', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Actualités ─────────────────────────────────────────────
$router->get('/gestionnaire/actualites',                        'Gestionnaire\ActualiteController@index',   ['gestionnaire']);
$router->post('/gestionnaire/actualites',                       'Gestionnaire\ActualiteController@store',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/actualites/{id}',                  'Gestionnaire\ActualiteController@update',  ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/actualites/{id}/delete',           'Gestionnaire\ActualiteController@destroy', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Config formulaires ────────────────────────────────────
$router->get('/gestionnaire/formulaires',                       'Gestionnaire\FormulaireConfigController@index',   ['gestionnaire']);
$router->post('/gestionnaire/formulaires',                      'Gestionnaire\FormulaireConfigController@store',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/formulaires/{id}/toggle',          'Gestionnaire\FormulaireConfigController@toggle',  ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/formulaires/{id}/delete',          'Gestionnaire\FormulaireConfigController@destroy', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Filières & spécialités ─────────────────────────────────
$router->get('/gestionnaire/filieres',                          'Gestionnaire\FiliereController@index',              ['gestionnaire']);
$router->post('/gestionnaire/filieres',                         'Gestionnaire\FiliereController@store',              ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/filieres/{id}',                    'Gestionnaire\FiliereController@update',             ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/filieres/{id}/delete',             'Gestionnaire\FiliereController@destroy',            ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/filieres/{id}/tarifs',             'Gestionnaire\FiliereController@tarifs',             ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/filieres/{id}/specialites',        'Gestionnaire\FiliereController@storeSpecialite',    ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/specialites/{id}',                 'Gestionnaire\FiliereController@updateSpecialite',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/specialites/{id}/delete',          'Gestionnaire\FiliereController@destroySpecialite',  ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Pièces requises ────────────────────────────────────────
$router->get('/gestionnaire/pieces',                            'Gestionnaire\PieceRequiseController@index',   ['gestionnaire']);
$router->post('/gestionnaire/pieces',                           'Gestionnaire\PieceRequiseController@store',   ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/pieces/{id}',                      'Gestionnaire\PieceRequiseController@update',  ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/pieces/{id}/delete',               'Gestionnaire\PieceRequiseController@destroy', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Paiements d'inscription ────────────────────────────────
$router->get('/gestionnaire/paiements',                         'Gestionnaire\PaiementGestionnaireController@index', ['gestionnaire']);
$router->get('/gestionnaire/paiements/{id}/recu',               'Gestionnaire\PaiementGestionnaireController@recu',  ['gestionnaire']);

// ── GESTIONNAIRE — Paramètres établissement ───────────────────────────────
$router->get('/gestionnaire/parametres',                        'Gestionnaire\ParametresController@index',  ['gestionnaire']);
$router->post('/gestionnaire/parametres',                       'Gestionnaire\ParametresController@update', ['gestionnaire', 'csrf']);

// ── GESTIONNAIRE — Profil ─────────────────────────────────────────────────
$router->get('/gestionnaire/historique',                        'Gestionnaire\HistoriqueController@index',      ['gestionnaire']);
$router->get('/gestionnaire/notifications',                     'Gestionnaire\NotificationController@index',       ['gestionnaire']);
$router->post('/gestionnaire/notifications/lire-tout',          'Gestionnaire\NotificationController@markAllRead', ['gestionnaire', 'csrf']);

$router->get('/gestionnaire/profil',                            'Gestionnaire\ProfilController@index',          ['gestionnaire']);
$router->post('/gestionnaire/profil',                           'Gestionnaire\ProfilController@update',         ['gestionnaire', 'csrf']);
$router->post('/gestionnaire/profil/mot-de-passe',              'Gestionnaire\ProfilController@updatePassword', ['gestionnaire', 'csrf']);
