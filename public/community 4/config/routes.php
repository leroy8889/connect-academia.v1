<?php
declare(strict_types=1);

use Core\Router;

$router = new Router();

// ── AUTH ──────────────────────────────────────────
$router->get('/login',                    'AuthController@showLogin');
$router->post('/login',                   'AuthController@login',          ['csrf']);
$router->get('/register',                 'AuthController@showRegister');
$router->post('/register',                'AuthController@register',       ['csrf']);
$router->post('/logout',                  'AuthController@logout',         ['csrf']);
$router->get('/forgot-password',          'AuthController@showForgotPassword');
$router->post('/forgot-password',         'AuthController@forgotPassword', ['csrf']);
$router->post('/reset-password',          'AuthController@resetPassword',  ['csrf']);
$router->get('/verify-email/{token}',     'AuthController@verifyEmail');

// ── FEED ──────────────────────────────────────────
$router->get('/',                         'FeedController@index',      ['auth']);
$router->get('/explore',                  'FeedController@explore',    ['auth']);

// ── POSTS ─────────────────────────────────────────
$router->get('/api/posts',                'PostController@index',      ['auth']);
$router->get('/api/posts/search',         'PostController@search',    ['auth']);
$router->post('/api/posts',               'PostController@store',      ['auth', 'csrf']);
$router->put('/api/posts/{id}',           'PostController@update',     ['auth', 'csrf']);
$router->delete('/api/posts/{id}',        'PostController@destroy',    ['auth', 'csrf']);
$router->post('/api/posts/{id}/like',     'PostController@like',       ['auth']);
$router->post('/api/posts/{id}/report',   'PostController@report',     ['auth', 'csrf']);
$router->post('/api/posts/{id}/bookmark', 'PostController@bookmark',   ['auth']);

// ── COMMENTS ──────────────────────────────────────
$router->get('/api/posts/{id}/comments',  'CommentController@index',   ['auth']);
$router->post('/api/posts/{id}/comments', 'CommentController@store',   ['auth', 'csrf']);
$router->get('/api/comments/{id}/replies','CommentController@replies', ['auth']);
$router->delete('/api/comments/{id}',     'CommentController@destroy', ['auth', 'csrf']);
$router->patch('/api/comments/{id}/best', 'CommentController@markBest',['auth', 'csrf']);
$router->post('/api/comments/{id}/like',  'CommentController@likeComment', ['auth']);

// ── USERS ─────────────────────────────────────────
$router->get('/profile',                  'UserController@me',         ['auth']);
$router->get('/profile/{id}',            'UserController@show',       ['auth']);
$router->put('/api/users/{id}',          'UserController@update',     ['auth', 'csrf']);
$router->post('/api/users/{id}/avatar',  'UserController@uploadAvatar', ['auth', 'csrf']);
$router->post('/api/users/{id}/follow',  'UserController@follow',     ['auth']);
$router->get('/api/users/search',        'UserController@search',     ['auth']);

// ── NOTIFICATIONS ─────────────────────────────────
$router->get('/api/notifications',             'NotificationController@index',    ['auth']);
$router->get('/api/notifications/count',       'NotificationController@count',    ['auth']);
$router->patch('/api/notifications/{id}/read', 'NotificationController@read',     ['auth']);
$router->patch('/api/notifications/read-all',  'NotificationController@readAll',  ['auth']);

// ── ADMIN AUTH (séparé du front-office) ───────────
$router->get('/admin/login',                 'Admin\AdminAuthController@showLogin');
$router->post('/admin/login',                'Admin\AdminAuthController@login',   ['csrf']);
$router->post('/admin/logout',               'Admin\AdminAuthController@logout',  ['csrf']);

// ── ADMIN DASHBOARD ───────────────────────────────
$router->get('/admin',                       'Admin\DashboardController@index',    ['admin']);
$router->get('/admin/api/stats',             'Admin\DashboardController@stats',    ['admin']);

// ── ADMIN USERS ───────────────────────────────────
$router->get('/admin/users',                 'Admin\UsersController@index',        ['admin']);
$router->patch('/admin/api/users/{id}/toggle', 'Admin\UsersController@toggle',    ['admin', 'csrf']);
$router->delete('/admin/api/users/{id}',     'Admin\UsersController@destroy',      ['admin', 'csrf']);

// ── ADMIN SUBJECTS ────────────────────────────────
$router->get('/admin/subjects',              'Admin\SubjectsController@index',     ['admin']);

// ── ADMIN REPORTS ─────────────────────────────────
$router->get('/admin/reports',               'Admin\ReportsController@index',      ['admin']);
$router->patch('/admin/api/reports/{id}',    'Admin\ReportsController@update',     ['admin', 'csrf']);
$router->delete('/admin/api/reports/{id}/content', 'Admin\ReportsController@deleteContent', ['admin', 'csrf']);

// ── ADMIN SETTINGS ────────────────────────────────
$router->get('/admin/settings',              'Admin\SettingsController@index',     ['admin']);
$router->post('/admin/api/settings',         'Admin\SettingsController@update',    ['admin', 'csrf']);

// ── ADMIN SUPPORT ─────────────────────────────────
$router->get('/admin/support',               'Admin\SupportController@index',      ['admin']);

