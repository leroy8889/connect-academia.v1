# Connect'Academia — Cahier des Charges Technique FINAL
**CTO : ONA-DAVID LEROY**
Version 2.0 · Avril 2026 · Deadline prioritaire

---

## Résumé Exécutif

Connect'Academia est une plateforme éducative web gabonaise (élèves Terminale + étudiants). Trois sous-projets indépendants existent déjà et doivent être **unifiés sous une seule codebase MVC**. Ce document est le guide de développement définitif.

**Stack retenu** : PHP (MVC) · MySQL 8.x · Redis · AWS S3 + CloudFront · HTML/CSS/JS

**Décisions architecturales arrêtées** :
- Architecture **MVC unique** pour tous les modules (basée sur `community 4`)
- **Une seule authentification** — les auth individuelles d'Apprentissage et Communauté sont supprimées
- **Un seul espace admin** — les admins individuels d'Apprentissage et Communauté sont supprimés
- **Orientation = redirection** vers la page HTML existante depuis le Hub (pas de backend pour v1)
- **Base de données unifiée** — une seule BDD `connect_academia`

---

## État des Projets Sources

| Source | Emplacement actuel | Ce qui est conservé | Ce qui est supprimé |
|---|---|---|---|
| **Apprentissage** | `public/Apprentissage 2/` | Logique métier, API, views courses/progression/IA | Auth (login.php, register.php, logout.php), Admin (`/admin/`) |
| **Communauté** | `public/community 4/` | Toute la logique MVC (Controllers, Models, Views feed/profil) | Auth (AuthController, views auth), Admin (Admin/* Controllers et views) |
| **Orientation** | `public/orientation/` | Les fichiers HTML tels quels | Rien |

---

## 1. Structure Cible du Projet Unifié

```
connect-academia/
├── .env                           # Variables d'environnement (jamais versionné)
├── .env.example                   # Modèle de configuration
├── .htaccess                      # Routage URL propre
├── index.php                      # Point d'entrée unique
├── composer.json
│
├── app/
│   ├── Core/
│   │   ├── Router.php             # Repris de community 4
│   │   ├── Database.php           # Repris de community 4
│   │   ├── Session.php            # Repris de community 4
│   │   ├── Response.php           # Repris de community 4
│   │   ├── Uploader.php           # Repris de community 4
│   │   └── Validator.php          # Repris de community 4
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php     # Repris + adapté
│   │   ├── AdminMiddleware.php    # Repris + adapté (avec JWT)
│   │   └── CsrfMiddleware.php     # Repris de community 4
│   │
│   ├── Controllers/
│   │   ├── AuthController.php     # NOUVEAU — auth unifiée
│   │   ├── HubController.php      # NOUVEAU
│   │   │
│   │   ├── Apprentissage/
│   │   │   ├── DashboardController.php   # Migré depuis dashboard.php
│   │   │   ├── RessourceController.php   # Migré depuis ressources.php
│   │   │   ├── ProgressionController.php # Migré depuis progression.php
│   │   │   ├── FavorisController.php     # Migré depuis favoris.php
│   │   │   └── IaController.php          # Migré depuis api/gemini.php
│   │   │
│   │   ├── Communaute/
│   │   │   ├── FeedController.php        # Repris de community 4
│   │   │   ├── PostController.php        # Repris de community 4
│   │   │   ├── CommentController.php     # Repris de community 4
│   │   │   ├── UserController.php        # Repris de community 4
│   │   │   ├── NotificationController.php # Repris de community 4
│   │   │   └── ChatController.php        # NOUVEAU — Long Polling
│   │   │
│   │   └── Admin/
│   │       ├── AdminAuthController.php   # NOUVEAU — avec 2FA TOTP
│   │       ├── DashboardController.php   # NOUVEAU — unifié
│   │       ├── UsersController.php       # Fusion community + apprentissage
│   │       ├── ContenuController.php     # NOUVEAU — CRUD cours/ressources
│   │       ├── CommunauteController.php  # Repris Admin/ReportsController
│   │       ├── PaiementController.php    # NOUVEAU
│   │       └── AdminsController.php      # NOUVEAU — gestion super admin
│   │
│   ├── Models/
│   │   ├── BaseModel.php          # Repris de community 4
│   │   ├── User.php               # REFONDU — schéma unifié
│   │   ├── Admin.php              # NOUVEAU
│   │   ├── Serie.php              # Migré depuis Apprentissage
│   │   ├── Matiere.php            # Migré depuis Apprentissage
│   │   ├── Chapitre.php           # Migré depuis Apprentissage
│   │   ├── Ressource.php          # Migré depuis Apprentissage
│   │   ├── Progression.php        # Migré depuis Apprentissage
│   │   ├── Favori.php             # Migré depuis Apprentissage
│   │   ├── IaConversation.php     # Migré depuis Apprentissage
│   │   ├── Post.php               # Repris de community 4
│   │   ├── Comment.php            # Repris de community 4
│   │   ├── Like.php               # Repris de community 4
│   │   ├── Follow.php             # Repris de community 4
│   │   ├── Bookmark.php           # Repris de community 4
│   │   ├── Notification.php       # Repris de community 4 (unifié)
│   │   ├── Report.php             # Repris de community 4
│   │   ├── Salon.php              # NOUVEAU — salons de chat
│   │   ├── Message.php            # NOUVEAU — messages chat
│   │   ├── Abonnement.php         # NOUVEAU
│   │   └── Transaction.php        # NOUVEAU
│   │
│   └── Views/
│       ├── layouts/
│       │   ├── main.php           # Layout principal front-office
│       │   ├── admin.php          # Layout admin
│       │   └── admin-auth.php     # Layout login admin
│       ├── auth/
│       │   ├── connexion.php      # NOUVEAU — page connexion unifiée
│       │   └── inscription.php    # NOUVEAU — page inscription unifiée
│       ├── hub/
│       │   └── index.php          # NOUVEAU — hub central
│       ├── apprentissage/
│       │   ├── dashboard.php      # Migré
│       │   ├── ressources.php     # Migré
│       │   ├── viewer.php         # Migré (viewer PDF)
│       │   ├── progression.php    # Migré
│       │   └── favoris.php        # Migré
│       ├── communaute/
│       │   ├── feed.php           # Repris
│       │   ├── explore.php        # Repris
│       │   ├── profil.php         # Repris
│       │   ├── chat-salons.php    # NOUVEAU
│       │   └── chat-salon.php     # NOUVEAU
│       ├── abonnement/
│       │   ├── choisir.php        # NOUVEAU
│       │   ├── confirmation.php   # NOUVEAU
│       │   └── renouveler.php     # NOUVEAU
│       ├── admin/
│       │   ├── auth/login.php     # NOUVEAU — login admin 2FA
│       │   ├── dashboard/         # NOUVEAU
│       │   ├── users/             # Fusion
│       │   ├── contenu/           # NOUVEAU
│       │   ├── communaute/        # Repris
│       │   ├── paiement/          # NOUVEAU
│       │   └── admins/            # NOUVEAU
│       └── errors/
│           └── 404.php
│
├── config/
│   ├── routes.php                 # Toutes les routes unifiées
│   ├── app.php                    # Config générale
│   ├── database.php               # Config BDD
│   └── redis.php                  # Config Redis
│
├── database/
│   ├── 001_schema_unifie.sql      # Schéma complet (voir section 3)
│   └── 002_seed.sql               # Données de test
│
├── public/
│   ├── orientation/               # Fichiers HTML existants (inchangés)
│   ├── assets/
│   │   ├── css/
│   │   │   ├── global.css
│   │   │   ├── apprentissage.css
│   │   │   ├── communaute.css
│   │   │   └── admin.css
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   ├── apprentissage.js
│   │   │   ├── communaute.js
│   │   │   ├── chat.js            # Long Polling
│   │   │   └── admin.js
│   │   └── images/
│   └── uploads/                   # Temporaire avant S3
│
└── storage/
    └── logs/
```

---

## 2. Flux Utilisateur Global

```
Accès URL                          → index.php (point d'entrée)
Non connecté                       → /auth/connexion
Connexion réussie                  → /hub

/hub (Hub Central)
  ├── Card Apprentissage           → /apprentissage
  ├── Card Communauté              → /communaute
  └── Card Orientation             → /public/orientation/orientation.html (redirect direct)

/apprentissage                     → dashboard élève
/communaute                        → fil d'actualité
/admin                             → login admin 2FA → dashboard admin
```

---

## 3. Base de Données Unifiée

### 3.1 Table `users` — Schéma Fusionné (critique)

```sql
CREATE TABLE users (
  id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
  uuid            CHAR(36)            NOT NULL,
  nom             VARCHAR(100)        NOT NULL,
  prenom          VARCHAR(100)        NOT NULL,
  email           VARCHAR(255)        NOT NULL,
  password_hash   VARCHAR(255)        NOT NULL,
  serie_id        INT                 DEFAULT NULL,
  role            ENUM('eleve','enseignant','admin') NOT NULL DEFAULT 'eleve',
  photo_profil    VARCHAR(500)        DEFAULT NULL,
  bio             VARCHAR(160)        DEFAULT NULL,
  etablissement   VARCHAR(255)        DEFAULT NULL,
  is_verified     TINYINT(1)          NOT NULL DEFAULT 0,
  is_active       TINYINT(1)          NOT NULL DEFAULT 1,
  is_deleted      TINYINT(1)          NOT NULL DEFAULT 0,
  email_token     VARCHAR(64)         DEFAULT NULL,
  posts_count     INT UNSIGNED        NOT NULL DEFAULT 0,
  followers_count INT UNSIGNED        NOT NULL DEFAULT 0,
  following_count INT UNSIGNED        NOT NULL DEFAULT 0,
  last_login      DATETIME            DEFAULT NULL,
  created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_uuid (uuid),
  UNIQUE KEY uk_email (email),
  INDEX idx_role (role),
  INDEX idx_active (is_active, is_deleted),
  FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Schéma Complet — Tables Ordonnées

```sql
-- DATABASE: connect_academia
-- VERSION: 2.0 (unifiée)

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── SÉRIES (Apprentissage) ────────────────────────────────────
CREATE TABLE series (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(10) NOT NULL,
  description TEXT DEFAULT NULL,
  couleur     VARCHAR(7) DEFAULT '#8B52FA',
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ADMINS ────────────────────────────────────────────────────
CREATE TABLE admins (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nom            VARCHAR(100) NOT NULL,
  prenom         VARCHAR(100) NOT NULL,
  email          VARCHAR(150) UNIQUE NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('super_admin','admin','moderateur') DEFAULT 'admin',
  totp_secret    VARCHAR(64) DEFAULT NULL,
  totp_enabled   TINYINT(1) DEFAULT 0,
  is_active      TINYINT(1) DEFAULT 1,
  is_locked      TINYINT(1) DEFAULT 0,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HISTORIQUE CONNEXIONS ADMIN ───────────────────────────────
CREATE TABLE historique_connexions_admin (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT DEFAULT NULL,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  statut     ENUM('succes','echec','2fa_echec','bloque') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── UTILISATEURS (table unifiée) ──────────────────────────────
CREATE TABLE users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid            CHAR(36) NOT NULL,
  nom             VARCHAR(100) NOT NULL,
  prenom          VARCHAR(100) NOT NULL,
  email           VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  serie_id        INT DEFAULT NULL,
  role            ENUM('eleve','enseignant','admin') NOT NULL DEFAULT 'eleve',
  photo_profil    VARCHAR(500) DEFAULT NULL,
  bio             VARCHAR(160) DEFAULT NULL,
  etablissement   VARCHAR(255) DEFAULT NULL,
  is_verified     TINYINT(1) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
  email_token     VARCHAR(64) DEFAULT NULL,
  posts_count     INT UNSIGNED NOT NULL DEFAULT 0,
  followers_count INT UNSIGNED NOT NULL DEFAULT 0,
  following_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_login      DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_uuid (uuid),
  UNIQUE KEY uk_email (email),
  INDEX idx_role (role),
  INDEX idx_active (is_active, is_deleted),
  FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ABONNEMENTS ───────────────────────────────────────────────
CREATE TABLE abonnements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  plan        ENUM('gratuit','mensuel','annuel') NOT NULL DEFAULT 'gratuit',
  statut      ENUM('actif','expire','en_attente') NOT NULL DEFAULT 'actif',
  debut       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fin         DATETIME NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_abonnements_user (user_id, statut),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TRANSACTIONS PAIEMENT ─────────────────────────────────────
CREATE TABLE transactions (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  abonnement_id       INT DEFAULT NULL,
  reference           VARCHAR(100) UNIQUE NOT NULL,
  montant             DECIMAL(10,2) NOT NULL,
  devise              VARCHAR(10) DEFAULT 'XAF',
  plan                ENUM('mensuel','annuel') NOT NULL,
  statut              ENUM('en_attente','succes','echec','rembourse') DEFAULT 'en_attente',
  methode_paiement    VARCHAR(50) DEFAULT NULL,
  aggregateur_ref     VARCHAR(200) DEFAULT NULL,
  webhook_payload     TEXT DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MATIERES (Apprentissage) ──────────────────────────────────
CREATE TABLE matieres (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nom       VARCHAR(100) NOT NULL,
  icone     VARCHAR(50) DEFAULT 'book',
  serie_id  INT NOT NULL,
  ordre     INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (serie_id) REFERENCES series(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CHAPITRES ─────────────────────────────────────────────────
CREATE TABLE chapitres (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  titre      VARCHAR(200) NOT NULL,
  matiere_id INT NOT NULL,
  ordre      INT DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── RESSOURCES ────────────────────────────────────────────────
CREATE TABLE ressources (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  titre          VARCHAR(255) NOT NULL,
  description    TEXT DEFAULT NULL,
  type           ENUM('cours','td','ancienne_epreuve') NOT NULL,
  fichier_path   VARCHAR(500) NOT NULL,
  fichier_nom    VARCHAR(255) NOT NULL,
  taille_fichier INT DEFAULT 0,
  matiere_id     INT NOT NULL,
  chapitre_id    INT DEFAULT NULL,
  serie_id       INT NOT NULL,
  annee          YEAR DEFAULT NULL,
  admin_id       INT NOT NULL,
  nb_vues        INT DEFAULT 0,
  is_deleted     TINYINT(1) DEFAULT 0,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ressources_serie (serie_id),
  INDEX idx_ressources_matiere (matiere_id),
  INDEX idx_ressources_type (type),
  FOREIGN KEY (matiere_id)  REFERENCES matieres(id),
  FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
  FOREIGN KEY (serie_id)    REFERENCES series(id),
  FOREIGN KEY (admin_id)    REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROGRESSIONS ──────────────────────────────────────────────
CREATE TABLE progressions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  ressource_id  INT NOT NULL,
  statut        ENUM('non_commence','en_cours','termine') DEFAULT 'non_commence',
  pourcentage   INT DEFAULT 0,
  temps_passe   INT DEFAULT 0,
  derniere_page INT DEFAULT 1,
  started_at    DATETIME DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_ressource (user_id, ressource_id),
  INDEX idx_progressions_user (user_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SESSIONS RÉVISION ─────────────────────────────────────────
CREATE TABLE sessions_revision (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  ressource_id   INT NOT NULL,
  debut          DATETIME NOT NULL,
  fin            DATETIME DEFAULT NULL,
  duree_secondes INT DEFAULT 0,
  INDEX idx_sessions_user (user_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FAVORIS ───────────────────────────────────────────────────
CREATE TABLE favoris (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  ressource_id INT NOT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_favori (user_id, ressource_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CONVERSATIONS IA ──────────────────────────────────────────
CREATE TABLE ia_conversations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  ressource_id  INT NOT NULL,
  user_message  TEXT NOT NULL,
  ia_response   TEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_ia_user (user_id),
  INDEX idx_ia_ressource (ressource_id),
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ressource_id) REFERENCES ressources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── POSTS (Communauté) ────────────────────────────────────────
CREATE TABLE posts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  type          ENUM('question','ressource','partage','annonce') NOT NULL DEFAULT 'partage',
  contenu       TEXT NOT NULL,
  image         VARCHAR(500) DEFAULT NULL,
  matiere_tag   VARCHAR(50) DEFAULT NULL,
  serie_tag     VARCHAR(10) DEFAULT NULL,
  hashtags      VARCHAR(500) DEFAULT NULL,
  is_resolved   TINYINT(1) NOT NULL DEFAULT 0,
  is_pinned     TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
  likes_count   INT UNSIGNED NOT NULL DEFAULT 0,
  comments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_posts_user (user_id),
  INDEX idx_posts_feed (is_deleted, is_pinned, created_at DESC),
  INDEX idx_posts_type (type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── COMMENTAIRES ──────────────────────────────────────────────
CREATE TABLE comments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id        INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  parent_id      INT UNSIGNED DEFAULT NULL,
  contenu        TEXT NOT NULL,
  is_best_answer TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1) NOT NULL DEFAULT 0,
  likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_comments_post (post_id, created_at),
  INDEX idx_comments_parent (parent_id),
  FOREIGN KEY (post_id)   REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LIKES ─────────────────────────────────────────────────────
CREATE TABLE likes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  post_id    INT UNSIGNED DEFAULT NULL,
  comment_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_likes_user_post (user_id, post_id),
  UNIQUE KEY uk_likes_user_comment (user_id, comment_id),
  FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)    REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FOLLOWS ───────────────────────────────────────────────────
CREATE TABLE follows (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  follower_id INT UNSIGNED NOT NULL,
  followed_id INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_follows (follower_id, followed_id),
  INDEX idx_follows_followed (followed_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BOOKMARKS ─────────────────────────────────────────────────
CREATE TABLE bookmarks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  post_id    INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bookmarks (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SALONS DE CHAT ────────────────────────────────────────────
CREATE TABLE salons (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  serie_tag   VARCHAR(10) DEFAULT NULL,
  matiere_tag VARCHAR(50) DEFAULT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  created_by  INT DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MESSAGES CHAT ─────────────────────────────────────────────
CREATE TABLE messages_chat (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  salon_id   INT NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  contenu    TEXT NOT NULL,
  is_deleted TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_salon (salon_id, created_at DESC),
  FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS (unifiées) ──────────────────────────────────
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  actor_id   INT UNSIGNED DEFAULT NULL,
  type       ENUM('like','comment','reply','mention','follow','announcement','nouvelle_ressource','abonnement') NOT NULL,
  message    VARCHAR(500) NOT NULL,
  link       VARCHAR(500) DEFAULT NULL,
  post_id    INT UNSIGNED DEFAULT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_notif_user (user_id, is_read, created_at DESC),
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SIGNALEMENTS ──────────────────────────────────────────────
CREATE TABLE reports (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT UNSIGNED NOT NULL,
  post_id     INT UNSIGNED DEFAULT NULL,
  comment_id  INT UNSIGNED DEFAULT NULL,
  reason      ENUM('inappropriate','spam','harassment','other') NOT NULL,
  description TEXT DEFAULT NULL,
  status      ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  admin_note  TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_reports_status (status),
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)     REFERENCES posts(id) ON DELETE SET NULL,
  FOREIGN KEY (comment_id)  REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PASSWORD RESETS ───────────────────────────────────────────
CREATE TABLE password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  token      VARCHAR(64) NOT NULL,
  is_used    TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_pwreset_token (token),
  INDEX idx_pwreset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS ──────────────────────────────────────────────────
CREATE TABLE settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── DONNÉES INITIALES ─────────────────────────────────────────
INSERT INTO series (nom, description, couleur) VALUES
('A', 'Lettres et Sciences Humaines', '#FF6B6B'),
('B', 'Sciences Économiques', '#4ECDC4'),
('C', 'Mathématiques et Sciences Physiques', '#8B52FA'),
('D', 'Sciences de la Vie et de la Terre', '#45B7D1'),
('F3', 'Génie Électronique', '#96CEB4'),
('G', 'Techniques de Gestion', '#FFEAA7');

INSERT INTO admins (nom, prenom, email, password_hash, role)
VALUES ('Admin', 'Connect Academia', 'admin@connect-academia.ga',
        '$2y$12$placeholder_change_this', 'super_admin');

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Connect''Academia'),
('periode_gratuite_jours', '1'),
('prix_mensuel_xaf', '2000'),
('prix_annuel_xaf', '15000'),
('gemini_rate_limit_per_minute', '10'),
('enable_chat', '1'),
('enable_paiement', '0'),
('max_upload_mb', '50');
```

---

## 4. Fichier `.env` — Structure Obligatoire

```dotenv
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8888/connect-academia
APP_SECRET=changez_ce_secret_32_caracteres_min

# Base de données
DB_HOST=localhost
DB_NAME=connect_academia
DB_USER=root
DB_PASS=root
DB_CHARSET=utf8mb4

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# AWS S3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=connect-academia
AWS_CLOUDFRONT_URL=

# Gemini AI
GEMINI_API_KEY=AIzaSyCoJAh_Okx6had13hcUewafRaOEuHOOf4U
GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
GEMINI_MAX_TOKENS=1024
GEMINI_TEMPERATURE=0.7

# Agrégateur Paiement (à compléter)
PAYMENT_API_URL=
PAYMENT_PUBLIC_KEY=
PAYMENT_SECRET_KEY=
PAYMENT_WEBHOOK_SECRET=
```

---

## 5. Routes Unifiées — `config/routes.php`

```php
// ── AUTH ──────────────────────────────────────────────────────
GET  /auth/connexion              → AuthController@showConnexion
POST /auth/connexion              → AuthController@connexion      [csrf]
GET  /auth/inscription            → AuthController@showInscription
POST /auth/inscription            → AuthController@inscription    [csrf]
POST /auth/deconnexion            → AuthController@deconnexion    [csrf]
GET  /auth/mot-de-passe-oublie    → AuthController@showForgot
POST /auth/mot-de-passe-oublie    → AuthController@forgot         [csrf]
GET  /auth/reinitialiser/{token}  → AuthController@showReset
POST /auth/reinitialiser          → AuthController@reset          [csrf]

// ── HUB ───────────────────────────────────────────────────────
GET  /hub                         → HubController@index           [auth]
GET  /api/hub/profil              → HubController@profil          [auth]
GET  /api/hub/stats               → HubController@stats           [auth]

// ── ABONNEMENT ────────────────────────────────────────────────
GET  /abonnement/choisir          → AbonnementController@choisir  [auth]
POST /api/paiement/initier        → PaiementController@initier    [auth, csrf]
POST /api/paiement/callback       → PaiementController@callback   (public, vérifié HMAC)
GET  /abonnement/confirmation     → AbonnementController@confirmation [auth]
GET  /abonnement/renouveler       → AbonnementController@renouveler   [auth]

// ── APPRENTISSAGE ─────────────────────────────────────────────
GET  /apprentissage                              → Apprentissage\DashboardController@index        [auth, abonne]
GET  /apprentissage/ressources                   → Apprentissage\RessourceController@index        [auth, abonne]
GET  /apprentissage/viewer/{id}                  → Apprentissage\RessourceController@viewer       [auth, abonne]
GET  /apprentissage/progression                  → Apprentissage\ProgressionController@index      [auth, abonne]
GET  /apprentissage/favoris                      → Apprentissage\FavorisController@index          [auth, abonne]
GET  /api/apprentissage/matieres                 → Apprentissage\RessourceController@matieres     [auth]
GET  /api/apprentissage/ressources               → Apprentissage\RessourceController@liste        [auth]
GET  /api/apprentissage/series                   → Apprentissage\RessourceController@series       [auth]
POST /api/apprentissage/ia/question              → Apprentissage\IaController@question            [auth, csrf]
GET  /api/apprentissage/ia/historique/{id}       → Apprentissage\IaController@historique          [auth]
POST /api/apprentissage/progression              → Apprentissage\ProgressionController@update     [auth]
POST /api/apprentissage/favoris/{id}             → Apprentissage\FavorisController@toggle         [auth]

// ── COMMUNAUTÉ ────────────────────────────────────────────────
GET  /communaute                                 → Communaute\FeedController@index                [auth, abonne]
GET  /communaute/explorer                        → Communaute\FeedController@explore              [auth, abonne]
GET  /communaute/profil/{id}                     → Communaute\UserController@show                 [auth, abonne]
GET  /communaute/chat                            → Communaute\ChatController@salons               [auth, abonne]
GET  /communaute/chat/{salon_id}                 → Communaute\ChatController@salon                [auth, abonne]

GET  /api/communaute/posts                       → Communaute\PostController@index                [auth]
POST /api/communaute/posts                       → Communaute\PostController@store                [auth, csrf]
PUT  /api/communaute/posts/{id}                  → Communaute\PostController@update               [auth, csrf]
DELETE /api/communaute/posts/{id}                → Communaute\PostController@destroy              [auth, csrf]
POST /api/communaute/posts/{id}/like             → Communaute\PostController@like                 [auth]
POST /api/communaute/posts/{id}/report           → Communaute\PostController@report               [auth, csrf]
POST /api/communaute/posts/{id}/bookmark         → Communaute\PostController@bookmark             [auth]

GET  /api/communaute/posts/{id}/comments         → Communaute\CommentController@index             [auth]
POST /api/communaute/posts/{id}/comments         → Communaute\CommentController@store             [auth, csrf]
DELETE /api/communaute/comments/{id}             → Communaute\CommentController@destroy           [auth, csrf]
PATCH /api/communaute/comments/{id}/best         → Communaute\CommentController@markBest          [auth, csrf]

GET  /api/communaute/salons/{id}/messages/poll   → Communaute\ChatController@poll                 [auth]
POST /api/communaute/salons/{id}/messages        → Communaute\ChatController@send                 [auth, csrf]

GET  /api/notifications                          → Communaute\NotificationController@index        [auth]
GET  /api/notifications/count                    → Communaute\NotificationController@count        [auth]
PATCH /api/notifications/{id}/read               → Communaute\NotificationController@read         [auth]

// ── ADMIN ─────────────────────────────────────────────────────
GET  /admin                                      → Admin\DashboardController@index                [admin]
GET  /admin/login                                → Admin\AdminAuthController@showLogin
POST /admin/login                                → Admin\AdminAuthController@login                [csrf]
POST /admin/verifier-2fa                         → Admin\AdminAuthController@verify2fa            [csrf]
POST /admin/logout                               → Admin\AdminAuthController@logout               [csrf]

GET  /admin/utilisateurs                         → Admin\UsersController@index                    [admin]
PATCH /admin/api/utilisateurs/{id}/toggle        → Admin\UsersController@toggle                   [admin, csrf]

GET  /admin/contenu                              → Admin\ContenuController@index                  [admin]
POST /admin/api/contenu/ressource                → Admin\ContenuController@storeRessource         [admin, csrf]
DELETE /admin/api/contenu/ressource/{id}         → Admin\ContenuController@deleteRessource        [admin, csrf]

GET  /admin/communaute                           → Admin\CommunauteController@index               [admin]
PATCH /admin/api/communaute/reports/{id}         → Admin\CommunauteController@traiterReport       [admin, csrf]

GET  /admin/paiement                             → Admin\PaiementController@index                 [admin]
GET  /admin/admins                               → Admin\AdminsController@index                   [super_admin]
```

---

## 6. Middleware `abonne`

Toutes les routes des modules Apprentissage et Communauté sont protégées par le middleware `abonne` :

```
Utilisateur connecté + (période gratuite non expirée OU abonnement actif) → Accès
Utilisateur connecté + période gratuite expirée + pas d'abonnement         → /abonnement/choisir
Utilisateur connecté + abonnement expiré                                   → /abonnement/renouveler
```

La période gratuite est de **1 jour** à partir de `created_at`.

---

## 7. Authentification Admin — 2FA TOTP

### Flux complet

```
POST /admin/login
  → Vérification email + password_hash (bcrypt)
  → Si 5 échecs → admin.is_locked = 1 (débloqué par super_admin uniquement)
  → Si OK → stocker admin_id en session temporaire
  → Redirection vers page saisie code 2FA

POST /admin/verifier-2fa
  → Vérifier le code TOTP avec la bibliothèque TOTP
  → Si OK → session admin complète + JWT (24h) + Redis activity TTL 30min
  → Si KO → incrémenter compteur, log historique_connexions_admin
  → Redirection vers /admin/dashboard

À chaque requête admin
  → Vérifier JWT valide
  → Vérifier Redis admin_activity:{admin_id} existe (sinon logout auto)
  → Renouveler TTL Redis à 30 min
```

### Clés Redis — Admin

| Clé | Valeur | TTL |
|---|---|---|
| `admin_activity:{id}` | `1` | 30 min |
| `admin_attempts:{email}` | compteur | 1h |
| `admin_locked:{email}` | `1` | Permanent |
| `refresh_token:{admin_id}` | token | 24h |

---

## 8. Chat — Long Polling

Le chat fonctionne par **Long Polling** (pas de WebSocket — pas de serveur dédié requis).

```
Client → GET /api/communaute/salons/{id}/messages/poll?last_id={dernier_id}
Serveur → Attend jusqu'à 25 secondes
  → Si nouveaux messages arrivés → répond immédiatement avec les messages
  → Si timeout (25s) → répond avec tableau vide
  → Client relance immédiatement une nouvelle requête

Client → POST /api/communaute/salons/{id}/messages
  → Insère le message en BDD
  → Invalide le cache Redis du salon (TTL 10 min)
```

**Modèles nécessaires** (à créer) : `Salon.php`, `Message.php`
**Contrôleur** : `Communaute/ChatController.php`
**Vues** : `communaute/chat-salons.php`, `communaute/chat-salon.php`
**JS** : `public/assets/js/chat.js` (boucle long polling)

---

## 9. Redis — Tous les Cas d'Usage

| Clé | Valeur | TTL | Usage |
|---|---|---|---|
| `session:{session_id}` | données session | 2h | Sessions utilisateurs |
| `cache:cours:{serie_id}` | JSON liste ressources | 1h | Cache apprentissage |
| `login_attempts:{ip}` | compteur | 15 min | Rate limiting front |
| `admin_attempts:{email}` | compteur | 1h | Rate limiting admin |
| `admin_locked:{email}` | `1` | Permanent | Blocage admin |
| `refresh_token:{admin_id}` | token | 24h | JWT admin |
| `admin_activity:{admin_id}` | `1` | 30 min | Inactivité admin |
| `abonnement:{user_id}` | statut JSON | 1h | Cache statut abonnement |
| `chat:{salon_id}:messages` | buffer JSON | 10 min | Buffer messages chat |
| `rate_api:{user_id}` | compteur | 1 min | Rate limiting API général |

---

## 10. AWS S3 + CloudFront

| Fichier | Chemin S3 | Accès |
|---|---|---|
| Cours PDF | `cours/documents/` | CloudFront URL signée |
| Avatars | `avatars/` | Public |
| Images posts | `communaute/medias/` | URL signée 24h |
| Dossiers pré-inscription | `orientation/dossiers/{user_id}/` | URL pré-signée privée |

```bash
composer require aws/aws-sdk-php
```

Credentials AWS **uniquement dans `.env`** — jamais dans le code.

---

## 11. Paiement — Agrégateur Gabonais

### Plans

| Plan | Durée | Prix |
|---|---|---|
| Mensuel | 30 jours | 2 000 XAF |
| Annuel | 365 jours | 15 000 XAF |

### Flux

```
1. User choisit plan → POST /api/paiement/initier
2. Serveur crée transaction (statut: en_attente) en BDD
3. Appel API agrégateur → URL de paiement reçue
4. Redirection user vers URL paiement
5. Agrégateur appelle webhook → POST /api/paiement/callback
6. Serveur vérifie signature HMAC-SHA256
7. Mise à jour transaction + abonnement
8. Redirection → /abonnement/confirmation
```

### Vérification webhook

```php
$expected = hash_hmac('sha256', $rawPayload, $_ENV['PAYMENT_WEBHOOK_SECRET']);
if (!hash_equals($expected, $receivedSignature)) {
    http_response_code(403);
    exit;
}
```

---

## 12. Sécurité — Règles Absolues

- Toutes les requêtes SQL via **PDO avec requêtes préparées** — aucune concaténation
- Toutes les sorties HTML via `htmlspecialchars()` — aucune exception
- Tous les formulaires POST avec **token CSRF**
- Mots de passe : `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`
- Uploads : validation type MIME + taille avant tout traitement
- Headers HTTP de sécurité dans `.htaccess` : `X-Content-Type-Options`, `X-Frame-Options: DENY`, `X-XSS-Protection`, `HSTS`
- Variables sensibles **uniquement dans `.env`** : clé Gemini, credentials AWS, secrets paiement, clé JWT

### Rate Limiting Redis

| Endpoint | Limite | Fenêtre |
|---|---|---|
| `/auth/connexion` | 10 tentatives | 15 min / IP |
| `/admin/login` | 5 tentatives | 1h / email |
| `/api/apprentissage/ia/question` | 10 requêtes | 1 min / user |
| API générale | 100 requêtes | 1 min / token |

---

## 13. Ordre d'Exécution — Priorités Absolues

Compte tenu de la deadline, l'ordre est strictement séquentiel (chaque phase débloque la suivante) :

| Priorité | Tâche | Durée estimée | Dépendances |
|---|---|---|---|
| **1** | Phase 0 : `.env` + `.gitignore` + structure répertoires cible | 2h | Aucune |
| **2** | BDD unifiée : `database/001_schema_unifie.sql` + migration | 3-4h | Structure |
| **3** | Core MVC unifié : `index.php` + `app/Core/*` + `.htaccess` | 2-3h | Structure |
| **4** | Auth unifiée : `AuthController` + vues `connexion.php` / `inscription.php` | 4-5h | Core + BDD |
| **5** | Hub : `HubController` + vue `hub/index.php` + redirect Orientation | 2-3h | Auth |
| **6** | Migration Apprentissage → MVC (controllers + views) + suppression auth/admin propres | 6-8h | Hub + BDD |
| **7** | Intégration Communauté (migration community 4 → structure unifiée) + suppression auth/admin propres | 4-6h | Hub + BDD |
| **8** | Chat Long Polling : `ChatController` + `Salon.php` + `Message.php` + `chat.js` | 4-5h | Communauté |
| **9** | Redis : sessions + cache + rate limiting + inactivité admin | 3-4h | Auth |
| **10** | Admin Unifié : auth 2FA + dashboard + gestion users/contenu/communauté | 6-8h | BDD + Redis |
| **11** | AWS S3 + CloudFront | 3-4h | Admin |
| **12** | Système Paiement + Abonnement | 4-6h | Admin + Redis |
| **13** | Tests, debug, mise en production | 3-4h | Tout |
| **Total** | | **~50-65h** | |

---

## 14. Ce Qui Est Explicitement Hors Scope v1

- Module Orientation backend (pré-inscriptions, formulaires) — redirection HTML uniquement
- Application mobile
- Notifications push (email ou SMS)
- Messagerie privée entre utilisateurs
- Système de QCM / exercices interactifs (Apprentissage v2)

---

*Connect'Academia — Cahier des Charges Final v2.0 — Avril 2026*
*Confidentiel — Gabon*
