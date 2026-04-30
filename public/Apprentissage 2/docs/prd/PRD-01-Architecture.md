# 📐 PRD-01 — Architecture Technique
## Connect'Academia — Document d'Architecture

> **Référence** : PRD principal v1.0 — Sections 5, 7, 17, Annexe B
> **Usage Cursor** : Lire en premier avant tout développement. Définit la structure globale du projet.

---

## 1. Vue d'ensemble du projet

| Champ | Détail |
|---|---|
| **Nom** | Connect'Academia |
| **Type** | Application Web (PHP + JS Vanilla, SPA-like) |
| **Langue** | Français |
| **Pays** | Gabon 🇬🇦 |
| **Cible** | Élèves de Terminale (Séries A1, A2, B, C, D) + Administrateurs |

---

## 2. Stack Technique

| Couche | Technologie | Détail |
|---|---|---|
| **Frontend** | HTML5 + CSS3 + JavaScript ES6+ | Vanilla JS, **aucun framework** |
| **Backend** | PHP 8.x | Architecture MVC légère |
| **Base de données** | MySQL 8.x | Connexion via PDO uniquement |
| **Lecteur PDF** | PDF.js (Mozilla) | Intégré via viewer custom |
| **Upload fichiers** | PHP `move_uploaded_file()` | Stockage local `/uploads/` |
| **Sessions** | PHP Sessions + CSRF Token | Sécurisation de tous les formulaires POST |
| **Icônes** | Lucide Icons (CDN) | |
| **Graphiques Admin** | Chart.js (CDN) | |
| **Notifications UI** | SweetAlert2 (CDN) | Modales et toasts |

---

## 3. Architecture des dossiers (structure complète)

```
connect-academia/
│

├── login.php                          # Connexion élève
├── register.php                       # Inscription élève
├── dashboard.php                      # Dashboard élève
├── matieres.php                       # Liste des matières
├── ressources.php                     # Ressources d'une matière
├── viewer.php                         # Lecteur PDF
├── progression.php                    # Page progression élève
├── profil.php                         # Page profil élève
├── favoris.php                        # Page favoris élève
├── notifications.php                  # Notifications élève
├── logout.php                         # Déconnexion élève
│
├── admin/
│  
│   ├── login.php                      # Login admin
│   ├── logout.php                     # Déconnexion admin
│   ├── dashboard.php                  # Dashboard admin (KPIs + graphiques)
│   ├── users.php                      # Gestion utilisateurs élèves
│   ├── ressources.php                 # Gestion ressources PDF
│   ├── series.php                     # Gestion séries & matières & chapitres
│   ├── stats.php                      # Statistiques avancées
│   ├── notifications.php              # Envoi notifications
│   └── settings.php                   # Paramètres plateforme
│
├── api/
│   ├── auth.php                       # Authentification
│   ├── users.php                      # CRUD utilisateurs
│   ├── series.php                     # GET séries actives
│   ├── matieres.php                   # GET matières par série
│   ├── chapitres.php                  # GET chapitres par matière
│   ├── ressources.php                 # GET/POST/PUT/DELETE ressources
│   ├── progression.php                # POST progression (start/heartbeat/end)
│   ├── favoris.php                    # POST toggle favori
│   ├── notifications.php              # GET/POST notifications
│   ├── upload.php                     # POST upload PDF
│   └── stats.php                      # GET statistiques admin
│
├── includes/
│   ├── db.php                         # Connexion PDO MySQL (singleton)
│   ├── auth_check.php                 # Middleware auth élève
│   ├── admin_check.php                # Middleware auth admin
│   ├── helpers.php                    # Fonctions utilitaires globales
│   └── config.php                     # Configuration (DB, paths, constantes)
│
├── assets/
│   ├── css/
│   │   ├── main.css                   # Styles communs (reset, typo, variables CSS)
│   │   ├── front.css                  # Styles front-office élève
│   │   ├── admin.css                  # Styles back-office admin
│   │   └── components/
│   │       ├── cards.css              # Cartes ressources
│   │       ├── sidebar.css            # Sidebars front & admin
│   │       ├── modal.css              # Fenêtres modales
│   │       └── progress.css           # Barres et cercles de progression
│   ├── js/
│   │   ├── main.js                    # JS global (utils, init)
│   │   ├── viewer.js                  # Logique PDF.js + timer révision
│   │   ├── progression.js             # Tracking progression AJAX
│   │   ├── admin.js                   # Logique back-office
│   │   ├── upload.js                  # Gestion upload fichiers avec progress bar
│   │   └── charts.js                  # Charts Chart.js admin
│   └── img/
│       ├── logo.svg                   # Logo Connect'Academia (fichier fourni)
│       ├── favicon.ico
│       └── illustrations/             # Illustrations hero, empty states
│
├── uploads/                           # ⚠️ Hors exécution PHP (.htaccess deny PHP)
│   └── ressources/
│       ├── A1/
│       ├── A2/
│       ├── B/
│       ├── C/
│       └── D/
│
├── database/
│   ├── schema.sql                     # Création de toutes les tables
│   └── seed.sql                       # Données initiales (séries, matières)
│
├── .htaccess                          # Réécriture URL + sécurité globale
└── README.md
```

---

## 4. Séparation des espaces

### Front-Office (Espace Élève)
- Racine du projet (`/`)
- Pages publiques : `index.php`, `login.php`, `register.php`
- Pages protégées (session élève) : toutes les autres
- Inclure `/includes/auth_check.php` en haut de chaque page protégée

### Back-Office (Espace Admin)
- Sous-dossier `/admin/`
- Toutes les pages sont protégées (session admin)
- Inclure `/includes/admin_check.php` en haut de chaque page admin

### API (Endpoints PHP)
- Sous-dossier `/api/`
- Retournent du JSON avec `Content-Type: application/json`
- Vérification de session avant chaque opération sensible

---

## 5. Middleware d'authentification

### `/includes/auth_check.php` — Protection pages élève
```php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
```

### `/includes/admin_check.php` — Protection pages admin
```php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}
```

---

## 6. Configuration — `/includes/config.php`

```php
<?php
// Base de données
define('DB_HOST',    'localhost');
define('DB_NAME',    'connect_academia');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Upload
define('UPLOAD_PATH',     __DIR__ . '/../uploads/ressources/');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50 Mo
define('ALLOWED_MIME',    'application/pdf');

// Session
define('SESSION_TIMEOUT', 7200); // 2 heures

// Application
define('BASE_URL',           'http://localhost/connect-academia');
define('ALLOW_PDF_DOWNLOAD', true); // Configurable par admin via settings
```

---

## 7. Connexion BDD — `/includes/db.php`

```php
<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

---

## 8. Routing & URLs

| URL | Fichier PHP | Accès |
|---|---|---|
| `/` | `index.php` | Public |
| `/login.php` | `login.php` | Public |
| `/register.php` | `register.php` | Public |
| `/dashboard.php` | `dashboard.php` | Élève connecté |
| `/matieres.php?serie=D` | `matieres.php` | Élève connecté |
| `/ressources.php?matiere=5` | `ressources.php` | Élève connecté |
| `/viewer.php?ressource=12` | `viewer.php` | Élève connecté |
| `/progression.php` | `progression.php` | Élève connecté |
| `/profil.php` | `profil.php` | Élève connecté |
| `/favoris.php` | `favoris.php` | Élève connecté |
| `/admin/` | `admin/dashboard.php` | Admin connecté |
| `/admin/login.php` | `admin/login.php` | Public |
| `/admin/users.php` | `admin/users.php` | Admin connecté |
| `/admin/ressources.php` | `admin/ressources.php` | Admin connecté |
| `/admin/series.php` | `admin/series.php` | Admin connecté |
| `/admin/stats.php` | `admin/stats.php` | Admin connecté |

---

## 9. Sécurité `.htaccess` (racine)

```apache
# Activer la réécriture
RewriteEngine On

# Sécurité : bloquer accès aux fichiers sensibles
<FilesMatch "\.(sql|log|env|ini|conf)$">
    Deny from all
</FilesMatch>

# Désactiver le listing des dossiers
Options -Indexes

# GZIP compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>

# Cache navigateur sur assets statiques
<FilesMatch "\.(css|js|png|jpg|svg|ico)$">
    Header set Cache-Control "max-age=86400, public"
</FilesMatch>
```

### `/uploads/.htaccess`
```apache
# Bloquer toute exécution PHP dans le dossier uploads
php_flag engine off
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
```

---

## 10. API — Conventions de réponse JSON

Toutes les routes API retournent :

```json
// Succès
{ "success": true, "data": { ... } }

// Erreur
{ "success": false, "error": "Message d'erreur lisible" }
```

En-tête systématique :
```php
header('Content-Type: application/json; charset=utf-8');
```

---

## 11. Phases de développement (Roadmap)

### Phase 1 — MVP (6–8 semaines)
- Setup MySQL (toutes les tables + seed)
- Authentification élèves + admin
- API ressources (CRUD)
- Système upload PDF
- API progression (start / heartbeat / end)
- Front-office : Landing, Login, Register, Dashboard, Matières, Ressources, Viewer PDF
- Back-office : Login admin, Dashboard KPIs, Upload ressources, Gestion utilisateurs

### Phase 2 — Enrichissement (4–6 semaines)
- Favoris, Notifications, Page Progression complète
- Recherche globale AJAX
- Export CSV utilisateurs
- Statistiques avancées Chart.js
- Gestion séries/matières/chapitres depuis admin
- Responsive mobile optimisé

### Phase 3 — Gamification (4 semaines)
- Système de badges
- Heatmap d'activité
- Notifications temps réel (polling AJAX)
- PWA basique (mode hors ligne)
- Rapport progression téléchargeable PDF
- Commentaires sur les ressources

---

*PRD-01 Architecture — Connect'Academia v1.0*
