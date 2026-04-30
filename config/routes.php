<?php
declare(strict_types=1);

use Core\Router;

$router = new Router();

// ── AUTH ──────────────────────────────────────────────────────────────────
$router->get('/auth/connexion',                 'AuthController@showConnexion');
$router->post('/auth/connexion',                'AuthController@connexion',         ['csrf']);
$router->get('/auth/inscription',               'AuthController@showInscription');
$router->post('/auth/inscription',              'AuthController@inscription',        ['csrf']);
$router->post('/auth/deconnexion',              'AuthController@deconnexion',        ['csrf']);
$router->get('/auth/mot-de-passe-oublie',       'AuthController@showForgot');
$router->post('/auth/mot-de-passe-oublie',      'AuthController@forgot',             ['csrf']);
$router->get('/auth/reinitialiser/{token}',     'AuthController@showReset');
$router->post('/auth/reinitialiser',            'AuthController@reset',              ['csrf']);

// Redirige la racine selon l'état de connexion
$router->get('/', 'HubController@root');

// ── HUB ───────────────────────────────────────────────────────────────────
$router->get('/hub',                            'HubController@index',               ['auth']);
$router->get('/api/hub/stats',                  'HubController@stats',               ['auth']);

// ── ABONNEMENT ────────────────────────────────────────────────────────────
$router->get('/abonnement/choisir',             'AbonnementController@choisir',      ['auth']);
$router->get('/abonnement/confirmation',        'AbonnementController@confirmation', ['auth']);
$router->get('/abonnement/renouveler',          'AbonnementController@renouveler',   ['auth']);
$router->post('/api/paiement/initier',          'PaiementController@initier',        ['auth', 'csrf']);
$router->post('/api/paiement/callback',         'PaiementController@callback');

// ── APPRENTISSAGE ─────────────────────────────────────────────────────────
$router->get('/apprentissage',                              'Apprentissage\DashboardController@index',       ['auth', 'abonne']);
$router->get('/apprentissage/matieres',                     'Apprentissage\DashboardController@matieres',    ['auth', 'abonne']);
$router->get('/apprentissage/ressources',                   'Apprentissage\RessourceController@index',       ['auth', 'abonne']);
$router->get('/apprentissage/viewer/{id}',                  'Apprentissage\RessourceController@viewer',      ['auth', 'abonne']);
$router->get('/apprentissage/progression',                  'Apprentissage\ProgressionController@index',     ['auth', 'abonne']);
$router->get('/apprentissage/favoris',                      'Apprentissage\FavorisController@index',         ['auth', 'abonne']);
$router->get('/api/apprentissage/matieres',                 'Apprentissage\RessourceController@matieres',    ['auth']);
$router->get('/api/apprentissage/ressources',               'Apprentissage\RessourceController@liste',       ['auth']);
$router->get('/api/apprentissage/series',                   'Apprentissage\RessourceController@series',      ['auth']);
$router->post('/api/apprentissage/ia/question',             'Apprentissage\IaController@question',           ['auth', 'csrf']);
$router->get('/api/apprentissage/ia/historique/{id}',       'Apprentissage\IaController@historique',         ['auth']);
$router->post('/api/apprentissage/progression',             'Apprentissage\ProgressionController@update',    ['auth']);
$router->post('/api/apprentissage/favoris/{id}',            'Apprentissage\FavorisController@toggle',        ['auth']);

// ── COMMUNAUTÉ ────────────────────────────────────────────────────────────
$router->get('/communaute',                                 'Communaute\FeedController@index',               ['auth', 'abonne']);
$router->get('/communaute/explorer',                        'Communaute\FeedController@explore',             ['auth', 'abonne']);
$router->get('/communaute/profil/{id}',                     'Communaute\UserController@show',                ['auth', 'abonne']);
$router->get('/communaute/chat',                            'Communaute\ChatController@salons',              ['auth', 'abonne']);
$router->get('/communaute/chat/{salon_id}',                 'Communaute\ChatController@salon',               ['auth', 'abonne']);

$router->get('/api/communaute/posts',                       'Communaute\PostController@index',               ['auth']);
$router->post('/api/communaute/posts',                      'Communaute\PostController@store',               ['auth', 'csrf']);
$router->put('/api/communaute/posts/{id}',                  'Communaute\PostController@update',              ['auth', 'csrf']);
$router->delete('/api/communaute/posts/{id}',               'Communaute\PostController@destroy',             ['auth', 'csrf']);
$router->post('/api/communaute/posts/{id}/like',            'Communaute\PostController@like',                ['auth']);
$router->post('/api/communaute/posts/{id}/report',          'Communaute\PostController@report',              ['auth', 'csrf']);
$router->post('/api/communaute/posts/{id}/bookmark',        'Communaute\PostController@bookmark',            ['auth']);

$router->get('/api/communaute/posts/{id}/comments',         'Communaute\CommentController@index',            ['auth']);
$router->post('/api/communaute/posts/{id}/comments',        'Communaute\CommentController@store',            ['auth', 'csrf']);
$router->delete('/api/communaute/comments/{id}',            'Communaute\CommentController@destroy',          ['auth', 'csrf']);
$router->patch('/api/communaute/comments/{id}/best',        'Communaute\CommentController@markBest',         ['auth', 'csrf']);

$router->get('/api/communaute/salons/{id}/messages/poll',   'Communaute\ChatController@poll',                ['auth']);
$router->post('/api/communaute/salons/{id}/messages',       'Communaute\ChatController@send',                ['auth', 'csrf']);

$router->get('/api/notifications',                          'Communaute\NotificationController@index',       ['auth']);
$router->get('/api/notifications/count',                    'Communaute\NotificationController@count',       ['auth']);
$router->patch('/api/notifications/read-all',               'Communaute\NotificationController@readAll',     ['auth']);
$router->patch('/api/notifications/{id}/read',              'Communaute\NotificationController@read',        ['auth']);

$router->post('/api/users/{id}/follow',                     'Communaute\UserController@follow',              ['auth']);
$router->get('/api/users/search',                           'Communaute\UserController@search',              ['auth']);
$router->post('/api/communaute/profil/update',              'Communaute\UserController@updateProfile',       ['auth', 'csrf']);

$router->get('/api/communaute/comments/{id}/replies',       'Communaute\CommentController@replies',          ['auth']);
$router->post('/api/communaute/comments/{id}/like',         'Communaute\CommentController@likeComment',      ['auth']);

// ── ADMIN ─────────────────────────────────────────────────────────────────
$router->get('/admin/login',                                'Admin\AdminAuthController@showLogin');
$router->post('/admin/login',                               'Admin\AdminAuthController@login',               ['csrf']);
$router->post('/admin/verifier-2fa',                        'Admin\AdminAuthController@verify2fa',           ['csrf']);
$router->post('/admin/logout',                              'Admin\AdminAuthController@logout',              ['csrf']);

$router->get('/admin',                                      'Admin\DashboardController@index',               ['admin']);
$router->get('/admin/api/stats',                            'Admin\DashboardController@stats',               ['admin']);

$router->get('/admin/utilisateurs',                         'Admin\UsersController@index',                   ['admin']);
$router->get('/admin/api/utilisateurs/{id}',                'Admin\UsersController@show',                    ['admin']);
$router->post('/admin/api/utilisateurs',                    'Admin\UsersController@store',                   ['admin', 'csrf']);
$router->patch('/admin/api/utilisateurs/{id}',              'Admin\UsersController@update',                  ['admin', 'csrf']);
$router->patch('/admin/api/utilisateurs/{id}/toggle',       'Admin\UsersController@toggle',                  ['admin', 'csrf']);
$router->delete('/admin/api/utilisateurs/{id}',             'Admin\UsersController@delete',                  ['admin', 'csrf']);

$router->get('/admin/contenu',                              'Admin\ContenuController@index',                 ['admin']);
$router->post('/admin/api/contenu/ressource',               'Admin\ContenuController@storeRessource',        ['admin', 'csrf']);
$router->patch('/admin/api/contenu/ressource/{id}',         'Admin\ContenuController@updateRessource',       ['admin', 'csrf']);
$router->delete('/admin/api/contenu/ressource/{id}',        'Admin\ContenuController@deleteRessource',       ['admin', 'csrf']);
$router->get('/admin/api/matieres',                         'Admin\ContenuController@getMatieres',           ['admin']);

$router->get('/admin/series-matieres',                      'Admin\SeriesController@index',                  ['admin']);
$router->post('/admin/api/series/serie',                    'Admin\SeriesController@storeSerie',              ['admin', 'csrf']);
$router->post('/admin/api/series/matiere',                  'Admin\SeriesController@storeMatiere',            ['admin', 'csrf']);

$router->get('/admin/communaute',                           'Admin\CommunauteController@index',              ['admin']);
$router->delete('/admin/api/communaute/posts/{id}',         'Admin\CommunauteController@deletePost',         ['admin', 'csrf']);
$router->patch('/admin/api/communaute/posts/{id}/pin',      'Admin\CommunauteController@pinPost',            ['admin', 'csrf']);
$router->patch('/admin/api/communaute/reports/{id}',        'Admin\CommunauteController@traiterReport',      ['admin', 'csrf']);

$router->get('/admin/signalements',                         'Admin\SignalementsController@index',             ['admin']);
$router->patch('/admin/api/signalements/{id}',              'Admin\SignalementsController@traiter',           ['admin', 'csrf']);

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
