# Connect'Academia — Cahier des Charges Technique v1.0

**Connect'Academia**

*Plateforme Éducative du Gabon*

## CAHIER DES CHARGES — TÂCHES RESTANTES

**CTO: ONA-DAVID LEROY**

Version 1.0  ·  MAI 2026

Stack : HTML / CSS / JavaScript  ·  PHP  ·  MySQL  ·  Redis  ·  AWS S3 + CloudFront

| **Module** | **Statut Actuel** | **Action Requise** |
| --- | --- | --- |
| Apprentissage | Développé (projet séparé) | Intégrer dans le projet principal |
| Orientation | Projet principal existant | Base de référence |
| Communauté | À créer entièrement | Développement from scratch |
| Hub Central | À créer | Page d'accueil connectée |
| Back-Office Admin | À compléter | Auth 2FA + tableau de bord |
| Paiement | À intégrer | API agrégateur gabonais |
| Redis + AWS | À configurer | Performance & stockage |

---

# 1. Contexte et Objectifs

## 1.1 Présentation du Projet

Connect'Academia est une plateforme éducative web destinée aux élèves de terminale (toutes séries) et aux étudiants gabonais. La première version regroupe trois fonctionnalités principales unifiées sous un même espace :

- **Apprentissage** : Cours, révisions et IA éducative pour la préparation au Bac
- **Orientation** : Immersion dans l'enseignement supérieur, pré-inscriptions et paiement en ligne
- **Communauté** : Réseau d'entraide, fil d'actualité académique et chat

## 1.2 Objectifs du Cahier des Charges

Ce document décrit précisément les tâches restantes à implémenter pour rendre la plateforme fonctionnelle et opérationnelle. Il est destiné à guider le développement assisté par IA (Cursor, Claude Code).

- Unifier les projets séparés en une plateforme unique
- Développer la fonctionnalité Communauté manquante
- Créer l'espace Hub de navigation centrale
- Finaliser le back-office administrateur avec authentification 2FA
- Intégrer le système de paiement via agrégateur local
- Configurer Redis, AWS S3 et CloudFront
- Assurer la scalabilité pour +10 000 utilisateurs
- Corriger les bugs et rendre la plateforme opérationnelle

---

# 2. Architecture Générale de la Plateforme

## 2.1 Structure des Répertoires

```
connect-academia/
├── public/                    # Front-office
│   ├── auth/                  # Connexion, inscription
│   ├── hub/                   # Page Hub centrale (à créer)
│   ├── apprentissage/         # Module apprentissage (à intégrer)
│   ├── communaute/            # Module communauté (à créer)
│   └── orientation/           # Module orientation (existant)
├── admin/                     # Back-office administrateur
├── api/                       # Endpoints PHP (REST API)
├── config/                    # database.php, redis.php, aws.php
├── includes/                  # Middleware, helpers
└── uploads/                   # Fichiers temporaires (avant S3)
```

## 2.2 Flux d'Authentification Global

Le flux suivant s'applique à tous les utilisateurs front-office :

- L'utilisateur s'inscrit ou se connecte via `/auth/`
- Après vérification JWT, il est redirigé vers la Page Hub
- Depuis le Hub, il choisit le module : Apprentissage, Communauté ou Orientation
- Si l'abonnement est expiré, redirection vers `/abonnement/choisir`

---

# 3. Tâche 1 — Unification de la Plateforme

## 3.1 Objectif

Fusionner le projet Apprentissage dans le projet principal Orientation pour obtenir une plateforme unique hébergée sur un seul domaine.

## 3.2 Actions Requises

### 3.2.1 Analyse et Inventaire

- Lister tous les fichiers du projet Apprentissage (pages PHP, assets CSS/JS, images)
- Identifier les conflits de nommage (fichiers, variables, fonctions) entre les deux projets
- Cartographier toutes les routes existantes des deux projets

### 3.2.2 Intégration des Fichiers

- Copier tous les fichiers du module Apprentissage dans `public/apprentissage/`
- Intégrer les assets CSS/JS spécifiques sans écraser les assets globaux
- Créer un fichier CSS global `assets/css/global.css` pour les styles partagés

### 3.2.3 Ajustement des Routes et Liens

- Mettre à jour tous les liens internes du module Apprentissage (chemins relatifs → absolus)
- Créer un fichier de routage centralisé `config/routes.php`
- Vérifier et corriger les redirections après connexion/déconnexion
- S'assurer que les sessions sont partagées entre les modules

### 3.2.4 Configuration du Projet Unifié

- Mettre à jour le fichier `.htaccess` pour gérer toutes les routes
- Centraliser la configuration dans `config/app.php` (URL de base, environnement, debug)
- Créer un fichier `.env` pour les variables d'environnement (ne pas versionner)
- Suppression du côté Admin de la partie apprentissage en conservant les fonctionnalités, pour les mutualiser dans la partie admin du projet unifié

## 3.3 Critères de Validation

- Toutes les pages des deux projets sont accessibles depuis un seul domaine
- Aucun lien cassé (404) dans les deux modules
- La session est maintenue lors de la navigation entre modules

---

# 4. Tâche 2 — Intégration du Module Apprentissage

## 4.1 Pages à Intégrer

| **Route** | **Page** | **Description** |
| --- | --- | --- |
| /apprentissage/ | Accueil | Dashboard de l'élève |
| /apprentissage/cours | Liste des cours | Cours par matière et série |
| /apprentissage/cours/{id} | Détail cours | Contenu du cours |
| /apprentissage/revision | Révisions | Fiches de révision par matière |
| /apprentissage/ia | IA Éducative | Chat IA pour aide aux devoirs |
| /apprentissage/exercices | Exercices | QCM et exercices types Bac |
| /apprentissage/progression | Progression | Suivi personnel de l'élève |

## 4.2 Endpoints API à Créer/Vérifier

```
GET    /api/apprentissage/cours              → liste des cours
GET    /api/apprentissage/cours/{id}         → détail d'un cours
GET    /api/apprentissage/matieres           → liste des matières
GET    /api/apprentissage/series             → séries du bac (A, B, C, D...)
GET    /api/apprentissage/exercices          → liste exercices
POST   /api/apprentissage/exercices/{id}/reponse → soumettre une réponse
GET    /api/apprentissage/progression        → progression de l'utilisateur
POST   /api/apprentissage/ia/question        → poser une question à l'IA
```

---

# 5. Tâche 3 — Migration et Unification de la Base de Données

## 5.1 Objectif

Utiliser la base de données du projet Apprentissage comme base principale et y ajouter les tables des autres fonctionnalités. Toutes les migrations doivent être versionnées. Ajuster la table `users` de la base de données principale à celle de la table `users` de la base de données de la partie communauté, pour qu'elle soit compatible aux fonctionnalités Apprentissage et communauté.

## 5.2 Base de données Apprentissage (base de données principale)

```sql
-- Connect'Academia - Schéma de base de données
-- MySQL 8.x

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ia_conversations;
DROP TABLE IF EXISTS favoris;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS sessions_revision;
DROP TABLE IF EXISTS progressions;
DROP TABLE IF EXISTS ressources;
DROP TABLE IF EXISTS chapitres;
DROP TABLE IF EXISTS matieres;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS series;

SET FOREIGN_KEY_CHECKS = 1;

-- Table series
CREATE TABLE series (
   id          INT AUTO_INCREMENT PRIMARY KEY,
   nom         VARCHAR(10) NOT NULL,
   description TEXT DEFAULT NULL,
   couleur     VARCHAR(7) DEFAULT '#8B52FA',
   is_active   TINYINT(1) DEFAULT 1,
   created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table users (Élèves)
CREATE TABLE users (
   id         INT AUTO_INCREMENT PRIMARY KEY,
   nom        VARCHAR(100) NOT NULL,
   prenom     VARCHAR(100) NOT NULL,
   email      VARCHAR(150) UNIQUE NOT NULL,
   password   VARCHAR(255) NOT NULL,
   serie_id   INT NOT NULL,
   avatar     VARCHAR(255) DEFAULT NULL,
   is_active  TINYINT(1) DEFAULT 1,
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
   last_login DATETIME DEFAULT NULL,
   FOREIGN KEY (serie_id) REFERENCES series(id)
);

-- Table admins
CREATE TABLE admins (
   id         INT AUTO_INCREMENT PRIMARY KEY,
   nom        VARCHAR(100) NOT NULL,
   prenom     VARCHAR(100) NOT NULL,
   email      VARCHAR(150) UNIQUE NOT NULL,
   password   VARCHAR(255) NOT NULL,
   role       ENUM('super_admin', 'admin') DEFAULT 'admin',
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table matieres
CREATE TABLE matieres (
   id        INT AUTO_INCREMENT PRIMARY KEY,
   nom       VARCHAR(100) NOT NULL,
   icone     VARCHAR(50) DEFAULT 'book',
   serie_id  INT NOT NULL,
   ordre     INT DEFAULT 0,
   is_active TINYINT(1) DEFAULT 1,
   FOREIGN KEY (serie_id) REFERENCES series(id)
);

-- Table chapitres
CREATE TABLE chapitres (
   id         INT AUTO_INCREMENT PRIMARY KEY,
   titre      VARCHAR(200) NOT NULL,
   matiere_id INT NOT NULL,
   ordre      INT DEFAULT 0,
   is_active  TINYINT(1) DEFAULT 1,
   FOREIGN KEY (matiere_id) REFERENCES matieres(id)
);

-- Table ressources
CREATE TABLE ressources (
   id             INT AUTO_INCREMENT PRIMARY KEY,
   titre          VARCHAR(255) NOT NULL,
   description    TEXT DEFAULT NULL,
   type           ENUM('cours', 'td', 'ancienne_epreuve') NOT NULL,
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
   FOREIGN KEY (matiere_id)  REFERENCES matieres(id),
   FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
   FOREIGN KEY (serie_id)    REFERENCES series(id),
   FOREIGN KEY (admin_id)    REFERENCES admins(id)
);

-- Table progressions
CREATE TABLE progressions (
   id            INT AUTO_INCREMENT PRIMARY KEY,
   user_id       INT NOT NULL,
   ressource_id  INT NOT NULL,
   statut        ENUM('non_commence', 'en_cours', 'termine') DEFAULT 'non_commence',
   pourcentage   INT DEFAULT 0,
   temps_passe   INT DEFAULT 0,
   derniere_page INT DEFAULT 1,
   started_at    DATETIME DEFAULT NULL,
   completed_at  DATETIME DEFAULT NULL,
   updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY unique_user_ressource (user_id, ressource_id),
   FOREIGN KEY (user_id)      REFERENCES users(id),
   FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table sessions_revision
CREATE TABLE sessions_revision (
   id              INT AUTO_INCREMENT PRIMARY KEY,
   user_id         INT NOT NULL,
   ressource_id    INT NOT NULL,
   debut           DATETIME NOT NULL,
   fin             DATETIME DEFAULT NULL,
   duree_secondes  INT DEFAULT 0,
   FOREIGN KEY (user_id)      REFERENCES users(id),
   FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table favoris
CREATE TABLE favoris (
   id           INT AUTO_INCREMENT PRIMARY KEY,
   user_id      INT NOT NULL,
   ressource_id INT NOT NULL,
   created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
   UNIQUE KEY unique_favori (user_id, ressource_id),
   FOREIGN KEY (user_id)      REFERENCES users(id),
   FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table notifications
CREATE TABLE notifications (
   id         INT AUTO_INCREMENT PRIMARY KEY,
   user_id    INT DEFAULT NULL,
   titre      VARCHAR(200) NOT NULL,
   message    TEXT NOT NULL,
   type       ENUM('info', 'success', 'warning', 'nouvelle_ressource') DEFAULT 'info',
   lu         TINYINT(1) DEFAULT 0,
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Créer la table ia_conversations
CREATE TABLE IF NOT EXISTS ia_conversations (
   id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
   user_id       INT              NOT NULL,
   document_id   INT              NOT NULL,
   document_type ENUM('cours','td','ancienne_epreuve') NOT NULL,
   user_message  TEXT             NOT NULL,
   ia_response   TEXT             NOT NULL,
   created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   KEY idx_user_id    (user_id),
   KEY idx_document   (document_id, document_type),
   KEY idx_created_at (created_at),
   FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
   FOREIGN KEY (document_id)  REFERENCES ressources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index de performance
CREATE INDEX idx_ressources_serie     ON ressources(serie_id);
CREATE INDEX idx_ressources_matiere   ON ressources(matiere_id);
CREATE INDEX idx_ressources_type      ON ressources(type);
CREATE INDEX idx_progressions_user    ON progressions(user_id);
CREATE INDEX idx_sessions_user        ON sessions_revision(user_id);
CREATE INDEX idx_notifications_user   ON notifications(user_id, lu);
CREATE INDEX idx_matieres_serie       ON matieres(serie_id);
CREATE INDEX idx_chapitres_matiere    ON chapitres(matiere_id);
```

### Tables de la base de données Communauté (tables à fusionner avec la base de données principale)

```sql
-- ============================================================
-- StudyLink — Schéma de Base de Données
-- MySQL 8.x / MariaDB 10.x
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS studylink_db
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci;

USE studylink_db;

-- ── UTILISATEURS ──────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `uuid`              CHAR(36)            NOT NULL,
   `nom`               VARCHAR(100)        NOT NULL,
   `prenom`            VARCHAR(100)        NOT NULL,
   `email`             VARCHAR(255)        NOT NULL,
   `password_hash`     VARCHAR(255)        NOT NULL,
   `photo_profil`      VARCHAR(500)        DEFAULT NULL,
   `bio`               VARCHAR(160)        DEFAULT NULL,
   `role`              ENUM('eleve','enseignant','admin') NOT NULL DEFAULT 'eleve',
   `classe`            VARCHAR(50)         DEFAULT NULL,
   `niveau`            VARCHAR(50)         DEFAULT NULL,
   `matiere`           VARCHAR(255)        DEFAULT NULL COMMENT 'Pour les enseignants, séparées par des virgules',
   `etablissement`     VARCHAR(255)        DEFAULT NULL,
   `is_verified`       TINYINT(1)          NOT NULL DEFAULT 0,
   `is_active`         TINYINT(1)          NOT NULL DEFAULT 1,
   `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
   `email_token`       VARCHAR(64)         DEFAULT NULL,
   `last_login`        DATETIME            DEFAULT NULL,
   `posts_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
   `followers_count`   INT UNSIGNED        NOT NULL DEFAULT 0,
   `following_count`   INT UNSIGNED        NOT NULL DEFAULT 0,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   UNIQUE KEY `uk_users_uuid` (`uuid`),
   UNIQUE KEY `uk_users_email` (`email`),
   INDEX `idx_users_role` (`role`),
   INDEX `idx_users_classe` (`classe`),
   INDEX `idx_users_active` (`is_active`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PUBLICATIONS ──────────────────────────────────────────
DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `user_id`           INT UNSIGNED        NOT NULL,
   `type`              ENUM('question','ressource','partage','annonce') NOT NULL DEFAULT 'partage',
   `contenu`           TEXT                NOT NULL,
   `image`             VARCHAR(500)        DEFAULT NULL,
   `matiere_tag`       VARCHAR(50)         DEFAULT NULL,
   `classe_tag`        VARCHAR(50)         DEFAULT NULL,
   `hashtags`          VARCHAR(500)        DEFAULT NULL COMMENT 'Tags séparés par des virgules',
   `is_resolved`       TINYINT(1)          NOT NULL DEFAULT 0,
   `is_pinned`         TINYINT(1)          NOT NULL DEFAULT 0,
   `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
   `likes_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
   `comments_count`    INT UNSIGNED        NOT NULL DEFAULT 0,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   INDEX `idx_posts_user` (`user_id`),
   INDEX `idx_posts_type` (`type`),
   INDEX `idx_posts_matiere` (`matiere_tag`),
   INDEX `idx_posts_created` (`created_at` DESC),
   INDEX `idx_posts_feed` (`is_deleted`, `is_pinned`, `created_at` DESC),
   INDEX `idx_posts_classe` (`classe_tag`),
   CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── COMMENTAIRES ──────────────────────────────────────────
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `post_id`           INT UNSIGNED        NOT NULL,
   `user_id`           INT UNSIGNED        NOT NULL,
   `parent_id`         INT UNSIGNED        DEFAULT NULL COMMENT 'Pour les réponses imbriquées',
   `contenu`           TEXT                NOT NULL,
   `is_best_answer`    TINYINT(1)          NOT NULL DEFAULT 0,
   `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
   `likes_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   INDEX `idx_comments_post` (`post_id`, `created_at`),
   INDEX `idx_comments_user` (`user_id`),
   INDEX `idx_comments_parent` (`parent_id`),
   CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LIKES ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `likes`;
CREATE TABLE `likes` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `user_id`           INT UNSIGNED        NOT NULL,
   `post_id`           INT UNSIGNED        DEFAULT NULL,
   `comment_id`        INT UNSIGNED        DEFAULT NULL,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   UNIQUE KEY `uk_likes_user_post` (`user_id`, `post_id`),
   UNIQUE KEY `uk_likes_user_comment` (`user_id`, `comment_id`),
   INDEX `idx_likes_post` (`post_id`),
   INDEX `idx_likes_comment` (`comment_id`),
   CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FOLLOWS ───────────────────────────────────────────────
DROP TABLE IF EXISTS `follows`;
CREATE TABLE `follows` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `follower_id`       INT UNSIGNED        NOT NULL,
   `followed_id`       INT UNSIGNED        NOT NULL,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   UNIQUE KEY `uk_follows` (`follower_id`, `followed_id`),
   INDEX `idx_follows_followed` (`followed_id`),
   CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_follows_followed` FOREIGN KEY (`followed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS ─────────────────────────────────────────
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `user_id`           INT UNSIGNED        NOT NULL COMMENT 'Destinataire',
   `actor_id`          INT UNSIGNED        DEFAULT NULL COMMENT 'Qui a déclenché',
   `type`              ENUM('like','comment','reply','mention','follow','announcement') NOT NULL,
   `message`           VARCHAR(500)        NOT NULL,
   `link`              VARCHAR(500)        DEFAULT NULL,
   `post_id`           INT UNSIGNED        DEFAULT NULL,
   `is_read`           TINYINT(1)          NOT NULL DEFAULT 0,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   INDEX `idx_notif_user` (`user_id`, `is_read`, `created_at` DESC),
   INDEX `idx_notif_actor` (`actor_id`),
   CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_notif_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SIGNALEMENTS ──────────────────────────────────────────
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `reporter_id`       INT UNSIGNED        NOT NULL,
   `post_id`           INT UNSIGNED        DEFAULT NULL,
   `comment_id`        INT UNSIGNED        DEFAULT NULL,
   `reason`            ENUM('inappropriate','spam','harassment','other') NOT NULL,
   `description`       TEXT                DEFAULT NULL,
   `status`            ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
   `admin_note`        TEXT                DEFAULT NULL,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   INDEX `idx_reports_status` (`status`),
   INDEX `idx_reports_post` (`post_id`),
   CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_reports_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
   CONSTRAINT `fk_reports_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PASSWORD RESETS ───────────────────────────────────────
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `email`             VARCHAR(255)        NOT NULL,
   `token`             VARCHAR(64)         NOT NULL,
   `is_used`           TINYINT(1)          NOT NULL DEFAULT 0,
   `expires_at`        DATETIME            NOT NULL,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   INDEX `idx_pwreset_token` (`token`),
   INDEX `idx_pwreset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS (Plateforme) ─────────────────────────────────
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `setting_key`       VARCHAR(100)        NOT NULL,
   `setting_value`     TEXT                DEFAULT NULL,
   `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BOOKMARKS ─────────────────────────────────────────────
DROP TABLE IF EXISTS `bookmarks`;
CREATE TABLE `bookmarks` (
   `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
   `user_id`           INT UNSIGNED        NOT NULL,
   `post_id`           INT UNSIGNED        NOT NULL,
   `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`id`),
   UNIQUE KEY `uk_bookmarks` (`user_id`, `post_id`),
   CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_bookmarks_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DONNÉES INITIALES ─────────────────────────────────────
-- Créer un compte admin par défaut (mot de passe : admin123)
INSERT INTO `users` (`uuid`, `nom`, `prenom`, `email`, `password_hash`, `role`, `is_verified`, `is_active`)
VALUES (
   UUID(),
   'Admin',
   'StudyLink',
   'admin@studylink.fr',
   '$2y$12$Fo8mne/tIXsaa7Prd6Xsdu31TsXf7u3XmOGlPGhriwg4SXdqSGe6S',
   'admin',
   1,
   1
);

-- Paramètres par défaut
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'StudyLink'),
('site_description', 'Le réseau social bienveillant pour l''éducation'),
('classes_list', '6ème,5ème,4ème,3ème,Seconde,Première,Terminale'),
('matieres_list', 'Mathématiques,Physique-Chimie,SVT,Français,Histoire-Géographie,Anglais,Espagnol,Philosophie,NSI,EPS,Arts,Musique'),
('enable_messaging', '0'),
('enable_follow', '1'),
('max_upload_mb', '10');

SET FOREIGN_KEY_CHECKS = 1;
```

### Tables Admin

```
admins                        → comptes administrateurs
historique_connexions_admin   → audit des connexions admin
```

## 5.3 Script de Migration

- Créer `database/migrate.php` pour exécuter les migrations dans l'ordre
- Créer `database/seed.php` pour les données de test
- Nommer les fichiers : `001_create_users.sql`, `002_communaute.sql`, etc.
- Tester la migration sur un environnement de staging

---

# 6. Tâche 4 — Fonctionnalité Communauté

## 6.1 Description

La fonctionnalité Communauté comprend un Réseau d'Entraide (fil d'actualité académique) et un Espace Chat en temps réel basé sur le Long Polling.

## 6.2 Pages à Créer

| **Route** | **Page** | **Description** |
| --- | --- | --- |
| /communaute/ | Accueil | Fil d'actualité + accès aux salons |
| /communaute/publier | Nouvelle publication | Formulaire de publication |
| /communaute/publication/{id} | Détail | Réponses, likes, commentaires |
| /communaute/chat | Liste des salons | Tous les salons disponibles |
| /communaute/chat/{salon_id} | Salon | Chat en temps réel |
| /communaute/profil/{user_id} | Profil | Activité publique d'un membre |

## 6.3 Endpoints API

```
GET    /api/communaute/publications              → liste paginée (20/page)
POST   /api/communaute/publications              → créer une publication
GET    /api/communaute/publications/{id}         → détail
PUT    /api/communaute/publications/{id}         → modifier (auteur, 30 min)
DELETE /api/communaute/publications/{id}         → supprimer
POST   /api/communaute/publications/{id}/reponses → répondre
POST   /api/communaute/publications/{id}/reaction → liker
GET    /api/communaute/salons/{id}/messages/poll  → Long Polling
POST   /api/communaute/salons/{id}/messages       → envoyer message
```

## 6.4 Règles Métier

- Modification d'une publication autorisée dans les 30 minutes
- L'auteur peut accepter une réponse comme 'meilleure réponse'
- Les publications épinglées (admins) apparaissent en tête du fil
- Filtres : par type (question/article), par série, par matière
- Signalement de contenu inapproprié possible

---

# 7. Tâche 5 — Espace Hub

## 7.1 Description

Après connexion ou inscription, l'utilisateur est redirigé vers la Page Hub (`/hub`). C'est le point central de navigation de la plateforme.

## 7.2 Contenu de la Page Hub

- Nom et prénom de l'utilisateur connecté
- Statut de l'abonnement (gratuit, actif, expiré)
- 3 cartes de navigation : Apprentissage, Communauté, Orientation
- Notification si la période gratuite expire bientôt
- Barre de navigation persistante

## 7.3 Logique de Redirection

```
Non connecté               → /auth/connexion
Période gratuite valide    → Accès accordé
Période gratuite expirée   → /abonnement/choisir
Abonnement actif           → Accès accordé
Abonnement expiré          → /abonnement/renouveler
```

## 7.4 Endpoints API

```
GET  /api/hub/profil    → Infos utilisateur + statut abonnement
GET  /api/hub/stats     → Statistiques personnelles de l'utilisateur
```

---

# 8. Tâche 6 — Espace Administrateur (Back-Office)

## 8.1 Authentification Admin — Flux Complet

- L'admin saisit son email + mot de passe → `POST /admin/api/auth/login`
- Vérification bcrypt en base de données
- Après 5 tentatives échouées → compte bloqué (déblocage par Super Admin)
- Si authentification OK → envoi du code 2FA (TOTP)
- L'admin saisit le code 2FA → `POST /admin/api/auth/verify-2fa`
- Génération JWT (rôle=admin, expiration=24h) + Refresh Token (Redis)
- Redirection vers `/admin/dashboard`
- Déconnexion automatique après 30 min d'inactivité

## 8.2 Règles de Sécurité Admin

| **Règle** | **Détail** |
| --- | --- |
| Tentatives échouées | Blocage après 5 tentatives — déblocage Super Admin uniquement |
| JWT | Rôle admin dans le payload — expiration 24h |
| Refresh Token | Stocké dans Redis — TTL 24h |
| Inactivité | Déconnexion automatique après 30 min (Redis TTL) |
| Historique | Toutes les connexions loggées (IP, User-Agent, statut) |
| 2FA | TOTP compatible Google Authenticator |

## 8.3 Modules du Tableau de Bord

### Dashboard Principal

- Statistiques : total utilisateurs, nouveaux aujourd'hui/ce mois
- Abonnements actifs / expirés / en attente
- Revenus du jour / semaine / mois
- Graphiques : inscriptions par semaine, revenus par mois

### Gestion des Utilisateurs

- Liste paginée avec filtres (nom, email, série, statut abonnement)
- Activer/désactiver un compte, modifier l'abonnement
- Voir l'activité d'un utilisateur
- Export CSV

### Gestion du Contenu

- CRUD matières, cours, chapitres, exercices
- Upload de documents PDF vers S3

### Modération Communauté

- Voir toutes les publications avec signalements
- Épingler / supprimer des publications
- Gérer les salons de discussion

### Gestion des Paiements

- Liste de toutes les transactions avec statut
- Statistiques financières (revenus par plan, taux de conversion)
- Initier des remboursements

### Gestion des Admins (Super Admin)

- Créer/désactiver des comptes admin
- Modifier les rôles : super_admin, admin, modérateur
- Débloquer des comptes verrouillés

---

# 9. Tâche 7 — Intégration du Système de Paiement

## 9.1 Modèle d'Abonnement

| **Plan** | **Durée** | **Devise** |
| --- | --- | --- |
| Mensuel | 30 jours | FCFA (XAF) 2000 |
| Annuel | 365 jours | FCFA (XAF) 15 000 |

> ℹ️ **Règle** : Période gratuite de 1 jour après inscription. Passé ce délai, un abonnement actif est requis pour accéder à la plateforme.

## 9.2 Flux de Paiement

- L'utilisateur choisit un plan → `POST /api/paiement/initier`
- Le serveur crée une transaction en BDD (statut: en_attente)
- Le serveur appelle l'API de l'agrégateur → reçoit une URL de paiement
- Redirection vers l'URL de paiement de l'agrégateur
- L'utilisateur effectue le paiement (mobile money, carte, etc.)
- L'agrégateur appelle le webhook → `POST /api/paiement/callback`
- Le serveur met à jour la transaction + l'abonnement de l'utilisateur
- Redirection vers `/abonnement/confirmation`

## 9.3 Sécurité du Webhook

Chaque notification de l'agrégateur doit être vérifiée via une signature HMAC-SHA256 pour éviter les fraudes :

```php
$expected = hash_hmac('sha256', $payload, $secret);
return hash_equals($expected, $signature_recue);
```

---

# 10. Tâche 8 — Intégration de Redis

## 10.1 Cas d'Usage Redis

| **Usage** | **Clé Redis** | **TTL** |
| --- | --- | --- |
| Session utilisateur | session:{session_id} | 2 heures |
| Cache liste des cours | cache:cours:{serie} | 1 heure |
| Tentatives connexion | login_attempts:{ip} | 15 min |
| Tentatives admin | admin_attempts:{email} | 1 heure |
| Verrouillage compte admin | admin_locked:{email} | Permanent |
| Token de refresh admin | refresh_token:{admin_id} | 24 heures |
| Inactivité admin | admin_activity:{admin_id} | 30 min |
| Statut abonnement | abonnement:{user_id} | 1 heure |
| Buffer messages chat | chat:{salon_id}:messages | 10 min |

## 10.2 Gestion de l'Inactivité Admin

```php
// À chaque requête admin, renouveler le TTL
if (!$redis->exists('admin_activity:'.$adminId)) {
    // Expiration → déconnecter l'admin
    logoutAdmin();
}
$redis->expire('admin_activity:'.$adminId, 1800); // 30 min
```

---

# 11. Tâche 9 — Scalabilité et Performance

## 11.1 Index Base de Données

```sql
CREATE INDEX idx_users_email       ON users(email);
CREATE INDEX idx_publications_date ON publications(created_at DESC);
CREATE INDEX idx_messages_salon    ON messages_chat(salon_id, created_at DESC);
CREATE INDEX idx_abonnements_user  ON abonnements(user_id, statut);
```

## 11.2 Architecture Modulaire

Chaque fonctionnalité doit être isolée pour permettre l'ajout futur de nouveaux modules :

- Un répertoire indépendant par module avec ses propres endpoints API
- Un autoloader organisé par namespace (PSR-4 ou équivalent)
- Documentation des APIs dans `docs/API.md`
- Prévoir une interface `ModuleInterface.php` pour les futurs modules

## 11.3 Cache de Contenu

- Mettre en cache les listes de cours avec Redis (TTL : 1h)
- Mettre en cache les statistiques du dashboard admin (TTL : 5 min)
- Utiliser CloudFront pour tous les assets statiques et PDFs

---

# 12. Infrastructure Cloud — AWS S3 + CloudFront

## 12.1 Types de Fichiers Stockés sur S3

| **Type de Fichier** | **Répertoire S3** | **Type d'Accès** |
| --- | --- | --- |
| Documents cours (PDF) | cours/documents/ | Via CloudFront (URL signée) |
| Dossiers pré-inscription | orientation/dossiers/{user_id}/ | Privé (URL pré-signée) |
| Avatars utilisateurs | avatars/ | Public |
| Fichiers chat (images) | communaute/medias/ | URL signée (24h) |

## 12.2 Librairie

Utiliser le SDK officiel AWS PHP :

```bash
composer require aws/aws-sdk-php
```

> ℹ️ Stocker les credentials AWS uniquement dans le fichier `.env` — jamais dans le code source.

---

# 13. Sécurité Globale

## 13.1 Règles Générales

- Toutes les requêtes SQL doivent utiliser des requêtes préparées (PDO)
- Toutes les sorties HTML doivent être échappées (`htmlspecialchars()`)
- Tous les formulaires POST doivent avoir un token CSRF
- Les mots de passe doivent être hashés avec bcrypt (`password_hash()`)
- Les JWT doivent être signés avec HS256 et un secret de 32+ caractères
- Les uploads de fichiers doivent valider le type MIME et la taille

## 13.2 Rate Limiting via Redis

| **Endpoint** | **Limite** | **Fenêtre** |
| --- | --- | --- |
| Connexion front-end | 10 tentatives | 15 minutes / IP |
| Connexion admin | 5 tentatives | 1 heure / email |
| API générale | 100 requêtes | 1 minute / token JWT |
| Upload fichiers | 10 uploads | 1 heure / utilisateur |

## 13.3 En-têtes HTTP de Sécurité

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'; ...
```

---

# 14. Tests et Mise en Production

## 14.1 Tests Fonctionnels

- Flux complet : inscription → Hub → choix module → accès contenu
- Flux de paiement (simulation + environnement réel)
- Authentification admin (login, 2FA, blocage, déconnexion auto)
- Upload et récupération de documents via S3
- Chat en Long Polling

## 14.2 Tests de Sécurité

- Injections SQL (requêtes préparées partout)
- XSS (échappement des sorties)
- CSRF (tokens sur tous les formulaires POST)
- Routes admin inaccessibles sans JWT valide
- Validation de la signature des webhooks de paiement

## 14.3 Checklist de Mise en Production

- Variables d'environnement configurées (`.env`)
- `APP_DEBUG=false` en production
- HTTPS configuré (certificat SSL)
- `.htaccess` sécurisé
- Sauvegardes automatiques de la BDD configurées
- Logs d'erreurs PHP redirigés vers un fichier
- Redis configuré et démarré
- Bucket S3 configuré avec bonnes permissions
- CloudFront configuré avec l'origine S3
- Webhook de paiement testé en production
- Emails transactionnels testés
- Plan de sauvegarde Redis configuré (RDB ou AOF)

---

# 15. Priorités et Ordre d'Exécution

Les tâches doivent être réalisées dans l'ordre suivant pour minimiser les blocages entre équipes :

| **Priorité** | **Tâche** | **Dépendances** |
| --- | --- | --- |
| 1 | Migration BDD unifiée | Aucune |
| 2 | Unification des projets (structure) | BDD |
| 3 | Intégration module Apprentissage | Unification |
| 4 | Espace Hub | Unification + Auth |
| 5 | Fonctionnalité Communauté | Hub + BDD |
| 6 | Intégration Redis | Communauté + Hub |
| 7 | Espace Admin (auth 2FA + dashboard) | BDD + Redis |
| 8 | Intégration AWS S3 + CloudFront | Admin |
| 9 | Intégration système de paiement | Admin + Redis |
| 10 | Tests et débogage | Tout |
| 11 | Optimisation performance | Tests |
| 12 | Mise en production | Tout |

---

*Connect'Academia — Cahier des Charges v1.0 — Avril 2026*

*Document destiné au développement assisté par IA (Cursor, Claude Code)*

*Confidentiel — Projet Connect'Academia — Gabon*