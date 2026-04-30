# StudyLink — PRD Architecture Technique
## Document d'Architecture Logicielle & Système

**Version :** 1.0  
**Date :** Février 2026  
**Projet :** StudyLink — Réseau Social Scolaire  
**Stack :** PHP 8.x · MySQL 8.x · HTML5 · CSS3 · JavaScript Vanilla  
**Statut :** Référence technique pour développement

---

## Table des Matières

1. [Vue d'Ensemble de l'Architecture](#1-vue-densemble-de-larchitecture)

3. [Architecture MVC — PHP](#3-architecture-mvc--php)

5. [Flux d'Authentification](#5-flux-dauthentification)
6. [Architecture du Feed & Temps Réel](#7-architecture-du-feed--temps-réel)
7. [Architecture Front-End](#7-architecture-front-end)
8. [Architecture Back-Office](#8-architecture-back-office)
9. [Sécurité — Couches de Protection](#9-sécurité--couches-de-protection)
10. [Flux de Données Complets](#10-flux-de-données-complets)
11. [Configuration Serveur & Déploiement](#11-configuration-serveur--déploiement)
12. [Conventions de Code](#12-conventions-de-code)

---

## 1. Vue d'Ensemble de l'Architecture

### 1.1 Diagramme Global

```
┌─────────────────────────────────────────────────────────────────┐
│                          CLIENT (Browser)                        │
│                                                                   │
│   ┌──────────────────┐          ┌──────────────────────────┐    │
│   │   FRONT-OFFICE   │          │      BACK-OFFICE          │    │
│   │     │          │                 /admin/index.php       │    │
│   │  Élèves & Profs  │          │    Admins & Modérateurs   │    │
│   └────────┬─────────┘          └────────────┬─────────────┘    │
└────────────┼────────────────────────────────-┼──────────────────┘
             │  HTTP/HTTPS Requests             │
             ▼                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                        SERVEUR WEB (Apache / Nginx)              │
│                     Réécriture URL (.htaccess)                   │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      APPLICATION PHP (MVC)                       │
│                                                                   │
│   ┌──────────┐    ┌──────────────┐    ┌──────────────────────┐  │
│   │  Router  │───▶│  Middleware  │───▶│    Controllers       │  │
│   │          │    │  Auth/CSRF   │    │  Auth / Post / User  │  │
│   └──────────┘    └──────────────┘    └──────────┬───────────┘  │
│                                                   │              │
│                   ┌───────────────────────────────┤              │
│                   ▼                               ▼              │
│          ┌──────────────┐               ┌──────────────────┐    │
│          │    Models    │               │     Views        │    │
│          │  User/Post   │               │  Templates PHP   │    │
│          │  Comment/Like│               │  Front & Admin   │    │
│          └──────┬───────┘               └──────────────────┘    │
└─────────────────┼───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                        MySQL 8.x                                 │
│   users │ posts │ comments │ likes │ follows │ notifications     │
│   reports │ sessions │ password_resets │ settings               │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Principes Architecturaux

| Principe | Décision | Justification |
|---|---|---|
| Pattern | MVC (Model-View-Controller) | Séparation claire des responsabilités, maintenabilité |
| Routing | Router PHP custom (URL propres) | URLs SEO-friendly, pas de `?page=xxx` |
| Accès BDD | PDO + Prepared Statements | Protection SQL injection native |
| Auth | Sessions PHP + CSRF tokens | Simple, sécurisé, sans dépendance externe |
| Temps réel | Polling AJAX (30s) | Compatible hébergement mutualisé, simple à implémenter |
| Frontend | Vanilla JS (pas de framework) | Légèreté, compatibilité, pas de build step |
| Upload | PHP natif + validation MIME | Contrôle total, sans dépendances |

---



## 3. Architecture MVC — PHP

### 3.1 Le Router

Le fichier `public/.htaccess` redirige toutes les requêtes vers `public/index.php` :

```apache
# public/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Le `Router.php` mappe les URLs vers les controllers :

```php
// config/routes.php

// ── AUTH ──────────────────────────────────────────
$router->get('/login',                    'AuthController@showLogin');
$router->post('/login',                   'AuthController@login');
$router->get('/register',                 'AuthController@showRegister');
$router->post('/register',                'AuthController@register');
$router->post('/logout',                  'AuthController@logout');
$router->post('/forgot-password',         'AuthController@forgotPassword');
$router->post('/reset-password',          'AuthController@resetPassword');
$router->get('/verify-email/{token}',     'AuthController@verifyEmail');

// ── FEED ──────────────────────────────────────────
$router->get('/',                         'FeedController@index',      ['auth']);
$router->get('/explore',                  'FeedController@explore',    ['auth']);

// ── POSTS ─────────────────────────────────────────
$router->get('/api/posts',                'PostController@index',      ['auth']);
$router->post('/api/posts',               'PostController@store',      ['auth', 'csrf']);
$router->put('/api/posts/{id}',           'PostController@update',     ['auth', 'csrf']);
$router->delete('/api/posts/{id}',        'PostController@destroy',    ['auth', 'csrf']);
$router->post('/api/posts/{id}/like',     'PostController@like',       ['auth']);
$router->post('/api/posts/{id}/report',   'PostController@report',     ['auth', 'csrf']);

// ── COMMENTS ──────────────────────────────────────
$router->get('/api/posts/{id}/comments',  'CommentController@index',   ['auth']);
$router->post('/api/posts/{id}/comments', 'CommentController@store',   ['auth', 'csrf']);
$router->delete('/api/comments/{id}',     'CommentController@destroy', ['auth', 'csrf']);
$router->patch('/api/comments/{id}/best', 'CommentController@markBest',['auth', 'csrf']);

// ── USERS ─────────────────────────────────────────
$router->get('/profile',                  'UserController@me',         ['auth']);
$router->get('/profile/{id}',             'UserController@show',       ['auth']);
$router->put('/api/users/{id}',           'UserController@update',     ['auth', 'csrf']);
$router->post('/api/users/{id}/follow',   'UserController@follow',     ['auth']);
$router->get('/api/users/search',         'UserController@search',     ['auth']);

// ── NOTIFICATIONS ─────────────────────────────────
$router->get('/api/notifications',           'NotificationController@index',   ['auth']);
$router->put('/api/notifications/{id}/read', 'NotificationController@read',    ['auth']);
$router->put('/api/notifications/read-all',  'NotificationController@readAll', ['auth']);

// ── ADMIN ─────────────────────────────────────────
$router->get('/admin',                    'Admin\DashboardController@index',    ['admin']);
$router->get('/admin/users',              'Admin\UserAdminController@index',    ['admin']);
$router->get('/admin/users/{id}',         'Admin\UserAdminController@show',     ['admin']);
$router->patch('/admin/users/{id}',       'Admin\UserAdminController@update',   ['admin', 'csrf']);
$router->delete('/admin/users/{id}',      'Admin\UserAdminController@destroy',  ['admin', 'csrf']);
$router->get('/admin/posts',              'Admin\PostAdminController@index',    ['admin']);
$router->delete('/admin/posts/{id}',      'Admin\PostAdminController@destroy',  ['admin', 'csrf']);
$router->patch('/admin/posts/{id}/pin',   'Admin\PostAdminController@pin',      ['admin', 'csrf']);
$router->get('/admin/reports',            'Admin\ReportController@index',       ['admin']);
$router->patch('/admin/reports/{id}',     'Admin\ReportController@update',      ['admin', 'csrf']);
$router->get('/admin/settings',           'Admin\SettingsController@index',     ['admin']);
$router->post('/admin/settings',          'Admin\SettingsController@update',    ['admin', 'csrf']);
$router->get('/admin/api/stats',          'Admin\DashboardController@stats',    ['admin']);
```

### 3.2 BaseModel — Connexion PDO

```php
// app/Models/BaseModel.php
class BaseModel {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function findById(int $id): array|false {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE id = ? AND is_deleted = 0",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
    }
}
```

### 3.3 Singleton Database

```php
// app/Core/Database.php
class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME']
        );
        $this->connection = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }
}
```

### 3.4 Middleware Stack

Les middlewares s'exécutent avant chaque controller. Ils sont déclarés dans les routes :

```
Request ──► Router ──► [AuthMiddleware] ──► [CsrfMiddleware] ──► Controller ──► Response
                              │                      │
                         Session ?             Token valide ?
                         Non → redirect /login  Non → 403
```

```php
// app/Middleware/AuthMiddleware.php
class AuthMiddleware {
    public function handle(): void {
        if (!Session::get('user_id')) {
            Response::redirect('/login');
            exit;
        }
        // Vérifier que le compte est toujours actif
        $user = (new User())->findById(Session::get('user_id'));
        if (!$user || !$user['is_active']) {
            Session::destroy();
            Response::redirect('/login?reason=suspended');
            exit;
        }
    }
}
```

---

## 

## 5. Flux d'Authentification

### 5.1 Inscription — Diagramme de Flux

```
Client                          Server (PHP)                    MySQL
  │                                  │                              │
  │── POST /register ───────────────►│                              │
  │   {nom, prenom, email,           │                              │
  │    password, role, classe}       │                              │
  │                                  │── Validate inputs ──────────►│
  │                                  │   CsrfMiddleware             │
  │                                  │   Validator::check()         │
  │                                  │                              │
  │                                  │── SELECT email ─────────────►│
  │                                  │◄── exists? ─────────────────│
  │                                  │                              │
  │                                  │   Si email existe :          │
  │◄── 422 {error: email_taken} ────│                              │
  │                                  │                              │
  │                                  │   password_hash = bcrypt(12) │
  │                                  │   uuid = UUID()              │
  │                                  │   token = random_bytes(32)   │
  │                                  │                              │
  │                                  │── INSERT users ─────────────►│
  │                                  │◄── user_id ─────────────────│
  │                                  │                              │
  │                                  │── Mailer::sendVerification() │
  │                                  │   [email avec token]         │
  │                                  │                              │
  │◄── 201 {redirect: /verify-sent}─│                              │
  │                                  │                              │
  │  [Clic lien email]               │                              │
  │── GET /verify-email/{token} ────►│                              │
  │                                  │── UPDATE users               │
  │                                  │   SET is_verified = 1 ──────►│
  │◄── redirect /login ─────────────│                              │
```

### 5.2 Connexion & Session

```
Client                     PHP                          MySQL
  │                          │                              │
  │── POST /login ──────────►│                              │
  │   {email, password}      │── SELECT * FROM users       │
  │                          │   WHERE email = ? ──────────►│
  │                          │◄── user row ────────────────│
  │                          │                              │
  │                          │   password_verify()          │
  │                          │   [bcrypt check]             │
  │                          │                              │
  │                          │   Si OK :                    │
  │                          │   session_regenerate_id()    │
  │                          │   $_SESSION['user_id'] = id  │
  │                          │   $_SESSION['role'] = role   │
  │                          │   $_SESSION['csrf'] = token  │
  │                          │                              │
  │                          │── UPDATE last_login ────────►│
  │                          │                              │
  │◄── 200 {redirect: /} ───│                              │
  │   [Cookie PHPSESSID set] │                              │
```

### 5.3 Structure de la Session PHP

```php
// Contenu de $_SESSION après connexion
$_SESSION = [
    'user_id'    => 42,
    'user_uuid'  => 'a1b2c3d4-...',
    'user_role'  => 'eleve',          // ou 'enseignant', 'admin'
    'user_name'  => 'Koffi Mensah',
    'user_photo' => '/uploads/avatars/a1b2c3_avatar.jpg',
    'csrf_token' => 'random_64_char_hex_string',
    'login_at'   => 1708123456,
];
```

### 5.4 Réinitialisation du Mot de Passe

```
User          Client           Server                Email           MySQL
  │               │               │                     │               │
  │── saisit ──►  │               │                     │               │
  │   email       │── POST ──────►│                     │               │
  │               │  /forgot-pwd  │── SELECT user ─────────────────────►│
  │               │               │◄── user found ─────────────────────│
  │               │               │                     │               │
  │               │               │   token = bin2hex(  │               │
  │               │               │     random_bytes(32))│              │
  │               │               │   expires = +1h     │               │
  │               │               │── INSERT pwd_resets ───────────────►│
  │               │               │── send email ──────►│               │
  │               │               │                     │── link ──────►│
  │               │               │                     │  /reset/{token}│
  │◄── reçoit ─────────────────────────────────────────│               │
  │   email       │               │                     │               │
  │── clic lien ►│── GET /reset ─►│── validate token ──────────────────►│
  │               │   /{token}    │◄── valid + non expiré ─────────────│
  │               │               │                     │               │
  │── saisit ──►  │── POST ──────►│   bcrypt(newPwd)    │               │
  │   new MDP     │  /reset-pwd   │── UPDATE password ─────────────────►│
  │               │               │── mark token used ─────────────────►│
  │               │◄── redirect   │                     │               │
  │               │   /login      │                     │               │
```

---

## 6. Architecture du Feed & Temps Réel

### 6.1 Algorithme du Feed

Le feed n'est pas un simple `ORDER BY created_at DESC`. Il intègre une logique de priorisation :

```sql
-- Requête principale du feed (FeedController::index)
SELECT
    p.*,
    u.nom, u.prenom, u.photo_profil, u.role, u.classe, u.matiere,
    -- L'utilisateur courant a-t-il liké ce post ?
    EXISTS(
        SELECT 1 FROM likes
        WHERE post_id = p.id AND user_id = :current_user_id
    ) AS is_liked_by_me,
    -- Score de pertinence
    (
        p.likes_count * 2 +
        p.comments_count * 3 +
        -- Boost si auteur suivi
        (SELECT COUNT(*) FROM follows
         WHERE follower_id = :current_user_id
         AND followed_id = p.user_id) * 10 +
        -- Boost si même classe
        IF(u.classe = :user_classe, 5, 0) +
        -- Pénalité ancienneté (posts > 7j descendent)
        IF(p.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY), -20, 0)
    ) AS relevance_score

FROM posts p
INNER JOIN users u ON p.user_id = u.id
WHERE
    p.is_deleted = 0
    -- Posts épinglés toujours en haut (traités séparément)
    AND p.is_pinned = 0
    -- Filtre optionnel matière
    AND (:matiere = '' OR p.matiere_tag = :matiere)
    -- Pagination cursor-based (plus performant que OFFSET)
    AND p.id < :last_post_id

ORDER BY relevance_score DESC, p.created_at DESC
LIMIT 10;
```

### 6.2 Polling AJAX — Architecture Temps Réel

```
Browser (feed.js)                    Server (PostController)
      │                                        │
      │  ── toutes les 30 secondes ──          │
      │── GET /api/posts?after={last_id} ─────►│
      │                                        │── SELECT posts WHERE id > last_id
      │                                        │── retourner nouveaux posts
      │◄── 200 { new_posts: [...], count: 3 } ─│
      │                                        │
      │  Si count > 0 :                        │
      │  Affiche bandeau "3 nouvelles           │
      │  publications — Cliquer pour voir"      │
      │                                        │
      │  Clic sur le bandeau :                 │
      │  Prepend les nouveaux posts au feed     │
      │  Animation slide-down                  │
```

```javascript
// public/assets/js/components/feed.js

class FeedPoller {
    constructor() {
        this.lastPostId = document.querySelector('.post-card')?.dataset.postId ?? 0;
        this.pollInterval = parseInt(window.STUDYLINK_CONFIG.feedRefreshInterval) || 30000;
        this.pendingPosts = [];
    }

    start() {
        setInterval(() => this.poll(), this.pollInterval);
    }

    async poll() {
        try {
            const res = await API.get(`/api/posts?after=${this.lastPostId}&limit=10`);
            if (res.data.count > 0) {
                this.pendingPosts = res.data.new_posts;
                this.showNewPostsBanner(res.data.count);
            }
        } catch (e) {
            console.warn('Feed poll failed:', e);
        }
    }

    showNewPostsBanner(count) {
        const banner = document.getElementById('new-posts-banner');
        banner.textContent = `${count} nouvelle${count > 1 ? 's' : ''} publication${count > 1 ? 's' : ''} — Cliquer pour voir`;
        banner.classList.add('visible');
        banner.onclick = () => this.prependPosts();
    }

    prependPosts() {
        const feed = document.getElementById('feed-container');
        this.pendingPosts.reverse().forEach(post => {
            const card = renderPostCard(post);
            card.classList.add('animate-slide-in');
            feed.prepend(card);
        });
        this.lastPostId = this.pendingPosts[0].id;
        document.getElementById('new-posts-banner').classList.remove('visible');
        this.pendingPosts = [];
    }
}
```

### 6.3 Chargement Infini (Infinite Scroll)

```javascript
// Intersection Observer pour le lazy loading
const sentinel = document.getElementById('feed-sentinel');
const observer = new IntersectionObserver(async (entries) => {
    if (entries[0].isIntersecting && !isLoading && hasMore) {
        isLoading = true;
        showSkeletons(3);

        const oldestId = document.querySelector('.post-card:last-child')?.dataset.postId;
        const res = await API.get(`/api/posts?before=${oldestId}&limit=10`);

        hideSkeletons();
        res.data.posts.forEach(post => appendPostCard(post));
        hasMore = res.data.has_more;
        isLoading = false;
    }
}, { threshold: 0.1 });

observer.observe(sentinel);
```

---

## 7. Architecture Front-End

### 7.1 Module JavaScript Central (api.js)

Tous les appels réseau passent par ce wrapper. Il gère automatiquement le token CSRF et les erreurs :

```javascript
// public/assets/js/api.js
const API = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,

    async request(method, url, data = null) {
        const options = {
            method,
            headers: {
                'Content-Type':  'application/json',
                'X-CSRF-Token':  this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (data) options.body = JSON.stringify(data);

        const res = await fetch(url, options);

        if (res.status === 401) {
            window.location.href = '/login';
            return;
        }
        if (res.status === 403) throw new Error('Accès refusé');
        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message ?? 'Erreur serveur');
        }
        return res.json();
    },

    get:    (url)         => API.request('GET', url),
    post:   (url, data)   => API.request('POST', url, data),
    put:    (url, data)   => API.request('PUT', url, data),
    patch:  (url, data)   => API.request('PATCH', url, data),
    delete: (url)         => API.request('DELETE', url),
};
```

### 7.2 Gestion du Like (Optimistic UI)

```javascript
// public/assets/js/components/post.js
async function toggleLike(postId, btn) {
    const counter = btn.querySelector('.like-count');
    const isLiked = btn.classList.contains('liked');

    // Optimistic update — mise à jour immédiate sans attendre le serveur
    btn.classList.toggle('liked');
    counter.textContent = parseInt(counter.textContent) + (isLiked ? -1 : 1);
    btn.querySelector('.heart-icon').classList.add('animate-heart');

    try {
        await API.post(`/api/posts/${postId}/like`);
    } catch (e) {
        // Rollback si erreur
        btn.classList.toggle('liked');
        counter.textContent = parseInt(counter.textContent) + (isLiked ? 1 : -1);
        showToast('Erreur lors du like', 'error');
    }
}
```

### 7.3 CSS — Variables Globales (Design System)

```css
/* public/assets/css/main.css */
:root {
    /* ── Palette ─────────────────────────── */
    --color-primary:       #8B52FA;
    --color-primary-dark:  #6B35D9;
    --color-primary-light: #C4A8FD;
    --color-lavender:      #F3EFFF;
    --color-dark:          #2D2D2D;
    --color-white:         #FFFFFF;
    --color-gray-100:      #F8F9FA;
    --color-gray-200:      #F1F3F5;
    --color-gray-400:      #CED4DA;
    --color-gray-600:      #868E96;

    /* ── Liquid Glass ─────────────────────── */
    --glass-bg:            rgba(255, 255, 255, 0.75);
    --glass-bg-dark:       rgba(45, 45, 45, 0.6);
    --glass-blur:          blur(20px) saturate(180%);
    --glass-border:        1px solid rgba(255, 255, 255, 0.35);
    --glass-shadow:        0 8px 32px rgba(139, 82, 250, 0.12);

    /* ── Typographie ──────────────────────── */
    --font-family:         'Inter', -apple-system, sans-serif;
    --font-size-xs:        0.75rem;
    --font-size-sm:        0.875rem;
    --font-size-base:      1rem;
    --font-size-lg:        1.125rem;
    --font-size-xl:        1.25rem;
    --font-size-2xl:       1.5rem;

    /* ── Espacements ──────────────────────── */
    --space-1: 4px;   --space-2: 8px;   --space-3: 12px;
    --space-4: 16px;  --space-5: 20px;  --space-6: 24px;
    --space-8: 32px;  --space-10: 40px; --space-12: 48px;

    /* ── Bordures & Rayons ────────────────── */
    --radius-sm:  8px;
    --radius-md:  16px;
    --radius-lg:  20px;
    --radius-xl:  28px;
    --radius-full: 9999px;

    /* ── Transitions ──────────────────────── */
    --transition-fast:   all 0.15s ease;
    --transition-base:   all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-spring: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);

    /* ── Z-index ──────────────────────────── */
    --z-dropdown:   100;
    --z-sticky:     200;
    --z-overlay:    300;
    --z-modal:      400;
    --z-toast:      500;
}
```

---

## 8. Architecture Back-Office

### 8.1 Flux des Statistiques Temps Réel

```
Browser (dashboard.php)              PHP (DashboardController)         MySQL
       │                                        │                          │
       │  [Page chargée]                        │                          │
       │── GET /admin/api/stats ───────────────►│                          │
       │                                        │── COUNT queries ─────────►│
       │                                        │   (users, posts, etc.)    │
       │◄── 200 { kpis: {...}, charts: {...} } ─│◄──────────────────────────│
       │                                        │                          │
       │  Chart.js render()                     │                          │
       │  KPI cards update                      │                          │
       │                                        │                          │
       │  ── toutes les 30 secondes ──          │                          │
       │── GET /admin/api/stats?refresh=1 ─────►│                          │
       │◄── 200 { updated_kpis: {...} } ────────│                          │
       │  Mise à jour cards avec animation      │                          │
```

### 8.2 Réponse JSON des Stats

```json
{
  "success": true,
  "data": {
    "kpis": {
      "total_eleves":          342,
      "total_enseignants":      28,
      "active_today":           87,
      "total_posts":          1250,
      "posts_today":            34,
      "total_comments":       4820,
      "resolution_rate":      68.4,
      "pending_reports":         5
    },
    "charts": {
      "inscriptions_weekly": {
        "labels": ["S1","S2","S3","S4","S5","S6","S7","S8","S9","S10","S11","S12"],
        "eleves":       [12, 18, 25, 30, 22, 28, 35, 40, 18, 22, 30, 35],
        "enseignants":  [ 2,  3,  1,  4,  2,  3,  2,  5,  1,  2,  3,  4]
      },
      "posts_by_matiere": {
        "labels": ["Maths","Physique","Français","Histoire","SVT","Anglais"],
        "data":   [320, 280, 250, 180, 150, 120]
      },
      "user_repartition": {
        "labels": ["Élèves","Enseignants","Admins"],
        "data":   [342, 28, 3]
      }
    },
    "recent_activity": [
      { "type": "new_user",   "message": "Koffi Mensah vient de s'inscrire",  "time": "il y a 2min" },
      { "type": "new_report", "message": "Nouveau signalement en attente",     "time": "il y a 5min" },
      { "type": "new_post",   "message": "34 publications aujourd'hui",        "time": "aujourd'hui" }
    ]
  }
}
```

### 8.3 Kanban des Signalements — Architecture

```javascript
// Kanban drag & drop natif (HTML5 Drag API, pas de lib externe)

// Colonnes : pending → reviewed → dismissed

document.querySelectorAll('.kanban-card').forEach(card => {
    card.addEventListener('dragstart', e => {
        e.dataTransfer.setData('reportId', card.dataset.reportId);
        card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('dragging'));
});

document.querySelectorAll('.kanban-column').forEach(col => {
    col.addEventListener('dragover', e => {
        e.preventDefault();
        col.classList.add('drag-over');
    });
    col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
    col.addEventListener('drop', async e => {
        e.preventDefault();
        col.classList.remove('drag-over');
        const reportId = e.dataTransfer.getData('reportId');
        const newStatus = col.dataset.status;

        await API.patch(`/admin/reports/${reportId}`, { status: newStatus });

        const card = document.querySelector(`[data-report-id="${reportId}"]`);
        col.querySelector('.kanban-cards').appendChild(card);
    });
});
```

---

## 9. Sécurité — Couches de Protection

### 9.1 Diagramme des Couches

```
Request
   │
   ▼
[1] HTTPS / TLS ──────────── Chiffrement transport
   │
   ▼
[2] Rate Limiting (IP) ────── Max 60 req/min, 5 logins/15min
   │
   ▼
[3] Input Sanitization ─────── strip_tags(), trim(), filter_input()
   │
   ▼
[4] CSRF Token Check ──────── Chaque POST/PUT/DELETE
   │
   ▼
[5] Auth Middleware ────────── Session valide + compte actif
   │
   ▼
[6] Authorization Check ─────── Peut-il agir sur CETTE ressource ?
   │
   ▼
[7] Prepared Statements ─────── Toutes les requêtes SQL
   │
   ▼
[8] Output Escaping ────────── htmlspecialchars() avant affichage
   │
   ▼
Response
```

### 9.2 Implémentation CSRF

```php
// Génération du token (à chaque session)
// app/Core/Session.php
public static function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

// Dans chaque layout HTML (app/Views/layouts/main.php)
<meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">

// Vérification côté serveur (app/Middleware/CsrfMiddleware.php)
public function handle(): void {
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['_csrf_token']
            ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid CSRF token']));
        }
    }
}
```

### 9.3 Sécurisation des Uploads

```php
// app/Core/Uploader.php
class Uploader {
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_SIZE_MB  = 10;

    public function handle(array $file, string $dir): string {
        // 1. Taille
        if ($file['size'] > self::MAX_SIZE_MB * 1024 * 1024) {
            throw new \Exception("Fichier trop volumineux (max " . self::MAX_SIZE_MB . " Mo)");
        }

        // 2. Vérification MIME réelle (pas l'extension déclarée)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME)) {
            throw new \Exception("Type de fichier non autorisé");
        }

        // 3. Nom de fichier sécurisé (jamais le nom original)
        $ext      = explode('/', $mimeType)[1];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = BASE_PATH . '/public/uploads/' . $dir . '/' . $filename;

        // 4. Déplacement hors du tmp
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \Exception("Échec de l'upload");
        }

        return '/uploads/' . $dir . '/' . $filename;
    }
}
```

### 9.4 Variables d'Environnement (.env)

```bash
# .env (NE JAMAIS COMMITER — ajouter au .gitignore)

# Application
APP_NAME=StudyLink
APP_URL=https://studylink.monecole.fr
APP_ENV=production         # local | staging | production
APP_DEBUG=false
APP_KEY=your_32_char_secret_key_here

# Base de données
DB_HOST=localhost
DB_PORT=3306
DB_NAME=studylink_db
DB_USER=studylink_user
DB_PASS=your_strong_password_here

# Email (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@monecole.fr
MAIL_PASSWORD=your_app_password
MAIL_FROM_NAME="StudyLink"
MAIL_ENCRYPTION=tls

# Sessions
SESSION_LIFETIME=7200      # 2h en secondes
SESSION_NAME=studylink_sess

# Upload
MAX_UPLOAD_MB=10
UPLOAD_PATH=/public/uploads
```

---

## 10. Flux de Données Complets

### 10.1 Créer une Publication

```
Client                    PHP                           MySQL
  │                         │                               │
  │── POST /api/posts ──────►│                               │
  │  {type, contenu,         │                               │
  │   matiere_tag,           │ [AuthMiddleware]              │
  │   classe_tag,            │ [CsrfMiddleware]              │
  │   image: File}           │                               │
  │                          │ Validator::check([           │
  │                          │   'contenu' => 'required',   │
  │                          │   'type'    => 'in:...',     │
  │                          │ ])                           │
  │                          │                               │
  │                          │ Si image jointe :             │
  │                          │   Uploader::handle()          │
  │                          │   → /uploads/posts/xxx.jpg   │
  │                          │                               │
  │                          │── INSERT INTO posts ─────────►│
  │                          │◄── post_id ──────────────────│
  │                          │                               │
  │                          │── Créer notifications ───────►│
  │                          │   pour les followers          │
  │                          │                               │
  │◄── 201 { post: {...} } ─│                               │
  │                          │                               │
  │  JS: prependPostCard()   │                               │
  │  Animation slide-in      │                               │
```

### 10.2 Ajouter un Commentaire

```
Client                       PHP                         MySQL
  │                            │                             │
  │── POST /api/posts/42/      │                             │
  │   comments ───────────────►│                             │
  │   { contenu, parent_id }   │                             │
  │                            │── INSERT comments ─────────►│
  │                            │── UPDATE posts              │
  │                            │   SET comments_count +1 ───►│
  │                            │                             │
  │                            │── INSERT notification       │
  │                            │   (pour auteur du post) ───►│
  │                            │                             │
  │◄── 201 { comment: {...} } ─│                             │
  │                            │                             │
  │  JS: appendCommentToPanel()│                             │
  │  Animation fade-in         │                             │
```

### 10.3 Format de Réponse API Standard

```json
// Succès
{
    "success": true,
    "data": { ... },
    "message": "Opération réussie",
    "pagination": {
        "page": 1,
        "per_page": 10,
        "total": 150,
        "total_pages": 15,
        "has_more": true,
        "last_id": 89
    }
}

// Erreur de validation
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Données invalides",
        "fields": {
            "contenu": "Ce champ est obligatoire",
            "matiere_tag": "Valeur non autorisée"
        }
    }
}

// Erreur serveur
{
    "success": false,
    "error": {
        "code": "SERVER_ERROR",
        "message": "Une erreur est survenue. Veuillez réessayer."
    }
}
```

---

## 11. Configuration Serveur & Déploiement

### 11.1 Apache — .htaccess

```apache
# public/.htaccess
Options -Indexes
ServerSignature Off

# ── Réécriture URL ──────────────────────────────
RewriteEngine On

# Bloquer l'accès aux fichiers cachés (.env, .git...)
RewriteRule (^|/)\.(?!well-known) - [F]

# Ne pas réécrire les fichiers/dossiers existants
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Tout rediriger vers index.php
RewriteRule ^(.*)$ index.php [QSA,L]

# ── Headers de Sécurité ──────────────────────────
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options    "nosniff"
    Header always set X-Frame-Options           "DENY"
    Header always set X-XSS-Protection          "1; mode=block"
    Header always set Referrer-Policy           "strict-origin-when-cross-origin"
    Header always set Permissions-Policy        "geolocation=(), microphone=(), camera=()"
    Header always set Content-Security-Policy   "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;"
</IfModule>

# ── Cache statique ───────────────────────────────
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>

# ── Compression GZIP ─────────────────────────────
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
```

### 11.2 Variables d'Environnement PHP

```php
// public/index.php — Bootstrap principal
<?php
define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// Charger les variables d'environnement
$env = parse_ini_file(BASE_PATH . '/.env');
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Mode erreur selon l'environnement
if ($_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Autoloader (sans Composer si besoin)
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require $file;
});

// Session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   1);  // HTTPS only
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
session_name($_ENV['SESSION_NAME'] ?? 'studylink_sess');
session_start();

// Router
require BASE_PATH . '/config/routes.php';
$router->dispatch();
```

### 11.3 Checklist de Déploiement en Production

```
PRÉ-DÉPLOIEMENT
  [ ] .env configuré avec vraies valeurs (BDD, SMTP, APP_KEY)
  [ ] APP_ENV=production, APP_DEBUG=false
  [ ] HTTPS activé (certificat SSL valide)
  [ ] Dossier uploads/ accessible en écriture (chmod 755)
  [ ] Dossier storage/logs/ accessible en écriture

BASE DE DONNÉES
  [ ] database/schema.sql importé
  [ ] User MySQL créé avec les seuls droits nécessaires (SELECT, INSERT, UPDATE, DELETE)
  [ ] Pas de root en production

SÉCURITÉ
  [ ] .env dans .gitignore et inaccessible depuis le web
  [ ] Dossier app/ hors de public/
  [ ] Headers de sécurité .htaccess actifs
  [ ] Vérifier que /admin est protégé

PERFORMANCE
  [ ] mod_deflate (GZIP) activé
  [ ] Cache headers sur les assets statiques
  [ ] Index MySQL vérifiés (EXPLAIN sur les requêtes du feed)

POST-DÉPLOIEMENT
  [ ] Tester l'inscription (élève + enseignant)
  [ ] Tester l'envoi d'email de vérification
  [ ] Tester le feed, like, commentaire
  [ ] Tester l'accès /admin
  [ ] Vérifier les logs (storage/logs/app.log)
```

---

## 12. Conventions de Code

### 12.1 PHP — Conventions

```php
// Nommage
// Classes       → PascalCase      : class PostController
// Méthodes      → camelCase       : public function getUserById()
// Variables     → camelCase       : $userId, $postData
// Constantes    → SCREAMING_SNAKE : define('MAX_UPLOAD_MB', 10)
// Fichiers      → PascalCase.php  : PostController.php

// Typage strict (PHP 8+)
declare(strict_types=1);

// Toujours utiliser les types de retour
public function findById(int $id): array|false { ... }
public function store(array $data): int { ... }   // retourne l'ID inséré
public function destroy(int $id): bool { ... }

// Les méthodes de controller répondent toujours en JSON pour les routes /api/
public function like(int $postId): void {
    // ...logique...
    Response::json(['success' => true, 'likes_count' => $newCount]);
}
```

### 12.2 JavaScript — Conventions

```javascript
// Nommage
// Variables/fonctions → camelCase   : getUserPosts()
// Constantes          → UPPER_SNAKE : const MAX_RETRY = 3
// Classes             → PascalCase  : class FeedPoller
// Fichiers            → kebab-case  : post-card.js

// Toujours utiliser async/await (pas de .then().catch())
async function loadComments(postId) {
    try {
        const res = await API.get(`/api/posts/${postId}/comments`);
        renderComments(res.data.comments);
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Données passées depuis PHP via data-attributes
// <div class="post-card" data-post-id="42" data-liked="true">
const postId = card.dataset.postId;
const isLiked = card.dataset.liked === 'true';
```

### 12.3 CSS — Conventions BEM

```css
/* Block__Element--Modifier */

/* Block */
.post-card { ... }

/* Element */
.post-card__header { ... }
.post-card__avatar { ... }
.post-card__content { ... }
.post-card__actions { ... }

/* Modifier */
.post-card--pinned { ... }       /* post épinglé */
.post-card--resolved { ... }     /* question résolue */

/* États JS */
.post-card.is-liked { ... }
.post-card.is-loading { ... }
```

### 12.4 Commits Git — Convention

```
feat:     Nouvelle fonctionnalité
fix:      Correction de bug
style:    CSS/UI uniquement
refactor: Refactoring sans changement de comportement
docs:     Documentation uniquement
db:       Changement de schéma BDD
security: Correctif de sécurité
perf:     Optimisation de performance

Exemples :
feat: ajouter le swipe panel des commentaires
fix: corriger le compteur de likes après unlike
security: ajouter rate limiting sur /api/auth/login
db: ajouter index composites sur posts pour le feed
```

---

*StudyLink — Architecture PRD v1.0 — Février 2026*  
*Document de référence technique pour l'équipe de développement*