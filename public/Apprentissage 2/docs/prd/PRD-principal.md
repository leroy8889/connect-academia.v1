# 📘 PRD — Connect'Academia
## Product Requirements Document — v1.0

> **Plateforme Web d'Entraide Pédagogique pour les Classes de Terminale au Gabon**

---

## Table des matières

1. [Vue d'ensemble du projet](#1-vue-densemble-du-projet)
2. [Contexte & Problématique](#2-contexte--problématique)
3. [Objectifs du produit](#3-objectifs-du-produit)
4. [Personas & Utilisateurs](#4-personas--utilisateurs)
5. [Architecture générale](#5-architecture-générale)
6. [Charte graphique & Identité visuelle](#6-charte-graphique--identité-visuelle)
7. [Stack Technique](#7-stack-technique)
8. [Modèle de données — Base MySQL](#8-modèle-de-données--base-mysql)
9. [Front-Office — Espace Élève](#9-front-office--espace-élève)
10. [Back-Office — Espace Administrateur](#10-back-office--espace-administrateur)
11. [Fonctionnalités transversales](#11-fonctionnalités-transversales)
12. [Spécifications des pages](#12-spécifications-des-pages)
13. [Règles métier](#13-règles-métier)
14. [Sécurité & Authentification](#14-sécurité--authentification)
15. [Performance & Accessibilité](#15-performance--accessibilité)
16. [Roadmap & Phases de développement](#16-roadmap--phases-de-développement)
17. [Arborescence des fichiers du projet](#17-arborescence-des-fichiers-du-projet)

---

## 1. Vue d'ensemble du projet

| Champ | Détail |
|---|---|
| **Nom du produit** | Connect'Academia |
| **Type** | Application Web (SPA-like avec PHP + JS) |
| **Cible principale** | Élèves de Terminale au Gabon (Séries A1, A2, B, C, D) |
| **Cible secondaire** | Enseignants & Administrateurs scolaires |
| **Objectif core** | Centraliser, organiser et rendre accessibles les ressources académiques (cours, TDs, anciennes épreuves) |
| **Langue** | Français |
| **Pays** | Gabon 🇬🇦 |
| **Version initiale** | V1.0 — Terminale uniquement |

---

## 2. Contexte & Problématique

### 2.1 Contexte

Dans le système éducatif gabonais, les élèves de Terminale préparent le **Baccalauréat** — examen national déterminant pour leur avenir. Aujourd'hui, les ressources pédagogiques sont :

- **Dispersées** : sur des groupes WhatsApp, clés USB, photocopies, Google Drive personnels.
- **Non structurées** : sans classement par série, matière ou chapitre.
- **Inégalement accessibles** : certains élèves ont plus de ressources que d'autres selon leur réseau.
- **Non traçables** : impossible de mesurer la progression ou le temps de révision d'un élève.

### 2.2 Problématique

> Comment offrir à chaque élève de Terminale gabonais un accès équitable, organisé et motivant aux ressources pédagogiques nécessaires à la réussite du Baccalauréat ?

### 2.3 Solution proposée

Connect'Academia est une plateforme web centralisée qui permet :
- Aux **élèves** de consulter cours, TD et anciennes épreuves, suivre leur progression et gérer leur temps de révision.
- Aux **administrateurs** de gérer l'ensemble du contenu, des utilisateurs et des statistiques depuis un tableau de bord puissant.

---

## 3. Objectifs du produit

### 3.1 Objectifs fonctionnels

- ✅ Permettre l'inscription et la connexion sécurisée des élèves par série.
- ✅ Afficher les ressources pédagogiques (PDF) organisées par Série → Matière → Chapitre.
- ✅ Permettre à l'administrateur d'uploader et nommer des cours/exercices (PDF).
- ✅ Afficher les ressources à la manière de Coursera (vignettes, progression, lecteur intégré).
- ✅ Tracker le temps passé sur chaque chapitre/matière.
- ✅ Afficher la progression globale de l'élève.
- ✅ Gérer les utilisateurs depuis le back-office (CRUD complet).
- ✅ Offrir des statistiques d'utilisation à l'administrateur.

### 3.2 Objectifs non-fonctionnels

- ⚡ Temps de chargement des pages < 3 secondes.
- 📱 Interface responsive (mobile-first).
- 🔒 Données sécurisées (mots de passe hashés, sessions PHP sécurisées).
- 🎨 Design épuré, moderne, inspiré de Linear et Attio pour l'admin, de Coursera pour l'espace élève.

---

## 4. Personas & Utilisateurs

### 4.1 Élève — Persona principal

| | |
|---|---|
| **Prénom fictif** | Madeleine O. |
| **Âge** | 17 ans |
| **Série** | Terminale D |
| **Comportement** | Révise principalement sur mobile le soir, cherche les cours rapidement |
| **Frustration** | Ne sait pas où trouver les anciennes épreuves, pas de suivi de ses révisions |
| **Attente** | Un espace propre, rapide, avec tout au même endroit |

### 4.2 Administrateur — Persona secondaire

| | |
|---|---|
| **Prénom fictif** | M. Obame |
| **Rôle** | Responsable pédagogique de l'établissement |
| **Comportement** | Travaille sur desktop, gère le contenu chaque semaine |
| **Frustration** | Perd du temps à distribuer les cours manuellement |
| **Attente** | Un dashboard clair pour uploader du contenu et voir l'activité des élèves |

---

## 5. Architecture générale

```
connect-academia/
│
├── FRONT-OFFICE (public/)         ← Espace Élève
│   ├── Authentification (login/register)
│   ├── Dashboard Élève
│   ├── Sélection Série → Matière
│   ├── Liste des ressources (Cours, TDs, Épreuves)
│   ├── Lecteur PDF intégré
│   └── Profil & Progression
│
├── BACK-OFFICE (admin/)           ← Espace Admin
│   ├── Authentification Admin
│   ├── Dashboard CRM
│   ├── Gestion Utilisateurs
│   ├── Gestion Séries & Matières
│   ├── Upload Ressources (PDF)
│   └── Statistiques & Analytics
│
├── API (api/)                     ← Endpoints PHP
│   ├── auth.php
│   ├── users.php
│   ├── series.php
│   ├── matieres.php
│   ├── ressources.php
│   └── progression.php
│
└── BASE DE DONNÉES (MySQL)
    └── connect_academia.sql
```

---

## 6. Charte graphique & Identité visuelle

### 6.1 Palette de couleurs

| Rôle | Couleur | Hex | Usage |
|---|---|---|---|
| **Primaire** | Violet | `#8B52FA` | CTA, boutons principaux, accents actifs |
| **Secondaire** | Violet clair | `#F3EFFF` | Backgrounds de cartes, hover states |
| **Neutre sombre** | Charcoal | `#2D2D2D` | Textes, sidebar admin, dark sections |
| **Neutre clair** | Blanc | `#FFFFFF` | Backgrounds principaux, cartes |

### 6.2 Typographie

| Usage | Police | Taille | Poids |
|---|---|---|---|
| Titres H1 | Inter / Poppins | 32–40px | 700 |
| Titres H2 | Inter / Poppins | 24–28px | 600 |
| Corps de texte | Inter | 14–16px | 400 |
| Labels / Badges | Inter | 12px | 500 |

### 6.3 Composants UI de base

- **Bouton Primaire** : fond `#8B52FA`, texte blanc, border-radius `8px`
- **Bouton Secondaire** : fond `#F3EFFF`, texte `#8B52FA`, border `1px solid #8B52FA`
- **Cartes ressources** : fond blanc, ombre légère `box-shadow: 0 2px 8px rgba(0,0,0,0.08)`, radius `12px`
- **Sidebar Admin** : fond `#2D2D2D`, icônes et texte blanc/gris clair
- **Badge Série** : fond `#F3EFFF`, texte `#8B52FA`, style pill
- **Barre de progression** : couleur `#8B52FA`, fond `#F3EFFF`

### 6.4 Logo

- Fichier fourni : `Logo_1_CA_COMPLET.svg`
- Utilisation : header front-office, login admin, favicon
- Ne jamais déformer ni recolorer le logo

---

## 7. Stack Technique

| Couche | Technologie | Détail |
|---|---|---|
| **Frontend** | HTML5 + CSS3 + JavaScript (ES6+) | Vanilla JS, pas de framework |
| **Backend** | PHP 8.x | Architecture MVC légère |
| **Base de données** | MySQL 8.x | PDO pour les requêtes |
| **Lecteur PDF** | PDF.js (Mozilla) | Intégré en iframe ou viewer custom |
| **Upload fichiers** | PHP `move_uploaded_file()` | Stockage local `/uploads/` |
| **Sessions** | PHP Sessions + CSRF Token | Sécurisation des formulaires |
| **Icônes** | Lucide Icons (CDN) | Léger, moderne |
| **Graphiques Admin** | Chart.js (CDN) | Statistiques visuelles |
| **Notifications** | SweetAlert2 (CDN) | Modales et toasts élégants |

---

## 8. Modèle de données — Base MySQL

### 8.1 Table `users` (Élèves)

```sql
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,         -- bcrypt
    serie_id      INT NOT NULL,
    avatar        VARCHAR(255) DEFAULT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME DEFAULT NULL,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);
```

### 8.2 Table `admins`

```sql
CREATE TABLE admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','admin') DEFAULT 'admin',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 8.3 Table `series`

```sql
CREATE TABLE series (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(10) NOT NULL,          -- A1, A2, B, C, D
    description   TEXT DEFAULT NULL,
    couleur       VARCHAR(7) DEFAULT '#8B52FA',  -- couleur hex pour l'UI
    is_active     TINYINT(1) DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Données initiales
INSERT INTO series (nom, description) VALUES
('A1', 'Terminale A1 — Lettres et Langues'),
('A2', 'Terminale A2 — Lettres et Sciences Humaines'),
('B',  'Terminale B — Sciences Économiques'),
('C',  'Terminale C — Mathématiques et Physique'),
('D',  'Terminale D — Sciences de la Vie et de la Terre');
```

### 8.4 Table `matieres`

```sql
CREATE TABLE matieres (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    icone         VARCHAR(50) DEFAULT 'book',    -- nom icône Lucide
    serie_id      INT NOT NULL,
    ordre         INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);
```

### 8.5 Table `chapitres`

```sql
CREATE TABLE chapitres (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    titre         VARCHAR(200) NOT NULL,
    matiere_id    INT NOT NULL,
    ordre         INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id)
);
```

### 8.6 Table `ressources`

```sql
CREATE TABLE ressources (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    description     TEXT DEFAULT NULL,
    type            ENUM('cours','td','ancienne_epreuve') NOT NULL,
    fichier_path    VARCHAR(500) NOT NULL,        -- chemin relatif du PDF
    fichier_nom     VARCHAR(255) NOT NULL,        -- nom original du fichier
    taille_fichier  INT DEFAULT 0,               -- en Ko
    matiere_id      INT NOT NULL,
    chapitre_id     INT DEFAULT NULL,
    serie_id        INT NOT NULL,
    annee           YEAR DEFAULT NULL,           -- pour anciennes épreuves
    admin_id        INT NOT NULL,
    nb_vues         INT DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (serie_id) REFERENCES series(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);
```

### 8.7 Table `progressions`

```sql
CREATE TABLE progressions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    ressource_id    INT NOT NULL,
    statut          ENUM('non_commence','en_cours','termine') DEFAULT 'non_commence',
    pourcentage     INT DEFAULT 0,               -- 0 à 100
    temps_passe     INT DEFAULT 0,               -- en secondes
    derniere_page   INT DEFAULT 1,               -- dernière page PDF consultée
    started_at      DATETIME DEFAULT NULL,
    completed_at    DATETIME DEFAULT NULL,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_ressource (user_id, ressource_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 8.8 Table `sessions_revision`

```sql
CREATE TABLE sessions_revision (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    ressource_id    INT NOT NULL,
    debut           DATETIME NOT NULL,
    fin             DATETIME DEFAULT NULL,
    duree_secondes  INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 8.9 Table `favoris`

```sql
CREATE TABLE favoris (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    ressource_id    INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favori (user_id, ressource_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 8.10 Table `notifications`

```sql
CREATE TABLE notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,            -- NULL = notification globale
    titre           VARCHAR(200) NOT NULL,
    message         TEXT NOT NULL,
    type            ENUM('info','success','warning','nouvelle_ressource') DEFAULT 'info',
    lu              TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 9. Front-Office — Espace Élève

### 9.1 Page d'accueil publique `/index.php`

**Objectif** : Landing page présentant la plateforme avant connexion.

**Sections** :
- Header avec logo Connect'Academia + boutons "Connexion" et "S'inscrire"
- Hero section : titre accrocheur + illustration + CTA "Commencer à réviser"
- Section "Comment ça marche" (3 étapes illustrées)
- Section "Les séries disponibles" (5 cartes : A1, A2, B, C, D)
- Section statistiques : Nombre de cours, élèves inscrits, matières disponibles
- Footer avec liens utiles

---

### 9.2 Page Inscription `/register.php`

**Champs du formulaire** :

| Champ | Type | Validation |
|---|---|---|
| Prénom | text | Requis, 2–50 chars |
| Nom | text | Requis, 2–50 chars |
| Email | email | Requis, unique en BDD |
| Mot de passe | password | Requis, min 8 chars |
| Confirmer mot de passe | password | Doit correspondre |
| Série | select | Requis (A1/A2/B/C/D) |

**Comportement** :
- Validation côté client (JS) + côté serveur (PHP)
- Affichage d'erreurs inline sous chaque champ
- Si succès → redirection vers `/dashboard.php`
- Mot de passe hashé avec `password_hash()` (bcrypt)

---

### 9.3 Page Connexion `/login.php`

**Champs** : Email + Mot de passe

**Comportement** :
- Vérification PHP + `password_verify()`
- Si succès → session créée + redirection `/dashboard.php`
- Si échec → message d'erreur discret ("Email ou mot de passe incorrect")
- Lien "Mot de passe oublié" (v2)

---

### 9.4 Dashboard Élève `/dashboard.php`

**Layout** :
- Sidebar gauche fixe avec : Logo, Navigation, Avatar + nom élève
- Zone principale scrollable

**Composants du dashboard** :

#### 9.4.1 Bannière de bienvenue
- "Bonjour [Prénom] 👋"
- Sous-titre : "Continuez là où vous vous êtes arrêté"
- Badge série de l'élève (ex : "Terminale D")

#### 9.4.2 Statistiques personnelles (4 cartes)

| Carte | Données affichées |
|---|---|
| 📚 Cours consultés | Nombre total / nombre disponibles |
| ⏱️ Temps de révision | Durée totale cette semaine |
| ✅ Ressources terminées | Nombre de PDFs complétés |
| 🔥 Série en cours | Matière la plus consultée |

#### 9.4.3 Panel Séries

- Grille de 5 cartes séries (A1, A2, B, C, D)
- La série de l'élève est mise en avant (bordure violette)
- Un élève peut consulter les ressources de n'importe quelle série
- Au clic → liste des matières de la série

#### 9.4.4 Section "Reprendre là où vous êtes"

- Liste des 3 dernières ressources consultées (en cours)
- Barre de progression individuelle pour chacune
- Bouton "Continuer" → ouvre le PDF à la dernière page consultée

#### 9.4.5 Section "Récemment ajoutés"

- Grille de cartes ressources récentes (style Coursera)
- Tags : type (Cours / TD / Épreuve) + matière + série

#### 9.4.6 Sidebar navigation

```
🏠 Tableau de bord
📂 Mes Matières
⭐ Mes Favoris
📊 Ma Progression
🔔 Notifications
👤 Mon Profil
🚪 Déconnexion
```

---

### 9.5 Page Matières `/matieres.php?serie=D`

**Layout** : Grille de cartes matières

**Chaque carte matière contient** :
- Icône de la matière (Lucide)
- Nom de la matière
- Nombre de ressources disponibles
- Progression globale de l'élève dans cette matière (barre %)

**Matières par série (référence)** :

| Série | Matières principales |
|---|---|
| **A1** | Philosophie, Français, Anglais, Histoire-Géo, LV2, SVT, Maths |
| **A2** | Philosophie, Français, Anglais, Histoire-Géo, LV2, SVT, Éco |
| **B** | Économie, Mathématiques, Philosophie, Français, Anglais, Histoire-Géo, Comptabilité |
| **C** | Mathématiques, Physique-Chimie, SVT, Philosophie, Français, Anglais |
| **D** | SVT, Mathématiques, Physique-Chimie, Philosophie, Français, Anglais |

---

### 9.6 Page Ressources d'une matière `/ressources.php?matiere=5`

**Inspiration design : Coursera**

**Structure de la page** :

#### Header matière
- Titre de la matière + série
- Nombre total de ressources
- Progression globale dans la matière (grande barre)

#### Onglets de filtrage
```
[Tous] [Cours] [Travaux Dirigés] [Anciennes Épreuves]
```

#### Grille de cartes ressources (style Coursera)

Chaque carte contient :
- Miniature/Icône représentative (PDF icon stylisé)
- Titre de la ressource
- Type badge (Cours / TD / Épreuve)
- Chapitre associé
- Durée estimée de lecture
- Barre de progression individuelle (0%, en cours, 100%)
- Icône favori (cœur) togglable
- Bouton "Consulter" → ouvre le viewer PDF

---

### 9.7 Page Lecteur PDF `/viewer.php?ressource=12`

**Layout** :
- Breadcrumb : Dashboard → Matière → Titre ressource
- Colonne gauche (30%) : Table des matières / infos ressource
- Colonne droite (70%) : PDF.js viewer

**Fonctionnalités du viewer** :
- Navigation page par page
- Zoom in/out
- Mode plein écran
- Téléchargement PDF (optionnel selon config admin)
- Sauvegarde automatique de la dernière page consultée (AJAX toutes les 30s)

**Panneau d'infos (gauche)** :
- Titre + type de ressource
- Barre de progression (% pages vues)
- Chronomètre de révision actif (⏱️ affiché en temps réel)
- Bouton "Marquer comme terminé"
- Bouton "Ajouter aux favoris"
- Section "Autres ressources de cette matière"

---

### 9.8 Page Ma Progression `/progression.php`

**Sections** :

#### Récapitulatif global
- Progression totale (toutes matières confondues) — grand cercle de progression
- Temps total de révision cette semaine
- Tableau de bord gamification : niveau, badges obtenus

#### Progression par matière
- Tableau ou liste avec barre de progression par matière
- Nombre de ressources : terminées / en cours / non commencées

#### Historique de révision
- Timeline des sessions de révision (date, durée, ressource)

#### Badges & Récompenses (gamification)

| Badge | Condition |
|---|---|
| 🥇 Premier pas | Première ressource consultée |
| 📖 Lecteur assidu | 5 ressources terminées |
| ⏰ Marathonien | 10h de révision cumulées |
| 🎯 Série complète | Toutes les matières d'une série commencées |
| 🏆 Champion du Bac | 80% de progression sur sa série |

---

### 9.9 Page Mon Profil `/profil.php`

**Champs modifiables** :
- Prénom / Nom
- Email
- Série
- Avatar (upload image)
- Mot de passe (changer)

**Statistiques récapitulatives** (lecture seule) :
- Date d'inscription
- Nombre de ressources consultées
- Temps total de révision

---

### 9.10 Page Favoris `/favoris.php`

- Grille de toutes les ressources marquées comme favorites
- Mêmes cartes que la page ressources
- Bouton retirer des favoris

---

### 9.11 Page Notifications `/notifications.php`

- Liste chronologique des notifications (nouvelles ressources, annonces admin)
- Badge compteur dans la sidebar (nb non lues)
- Marquer tout comme lu

---

## 10. Back-Office — Espace Administrateur

### 10.1 Page Connexion Admin `/admin/login.php`

- Design minimal et élégant (fond sombre `#2D2D2D`)
- Logo Connect'Academia centré
- Champs : Email + Mot de passe
- Sécurité : rate limiting sur les tentatives (3 max)

---

### 10.2 Dashboard Admin `/admin/dashboard.php`

**Inspiré de Linear & Attio** — Interface épurée, sidebar sombre, contenu clair.

#### Layout global Admin
```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR SOMBRE (#2D2D2D)   │   ZONE PRINCIPALE (blanc) │
│                             │                           │
│  Logo Connect'Academia      │   Topbar : breadcrumb +   │
│                             │   notifications + avatar  │
│  Navigation :               │                           │
│  - Dashboard                │   Contenu de la page      │
│  - Utilisateurs             │                           │
│  - Séries & Matières        │                           │
│  - Ressources               │                           │
│  - Statistiques             │                           │
│  - Notifications            │                           │
│  - Paramètres               │                           │
│  - Déconnexion              │                           │
└─────────────────────────────────────────────────────────┘
```

#### KPI Cards (4 cartes en haut)

| KPI | Icône |
|---|---|
| Total Élèves inscrits | 👥 |
| Ressources publiées | 📄 |
| Vues cette semaine | 👁️ |
| Temps de révision total (toutes séries) | ⏱️ |

#### Graphiques

- **Inscriptions par jour** (30 derniers jours) — Line chart Chart.js
- **Ressources par type** — Donut chart (Cours / TD / Épreuves)
- **Activité par série** — Bar chart horizontal

#### Tableau "Dernières inscriptions"
- 5 derniers élèves inscrits avec : Nom, Série, Date, Statut

#### Tableau "Ressources récentes"
- 5 dernières ressources ajoutées avec : Titre, Type, Matière, Vues

---

### 10.3 Gestion Utilisateurs `/admin/users.php`

**Tableau principal** :

| Colonne | Détail |
|---|---|
| Avatar + Nom Prénom | Cliquable → fiche élève |
| Email | |
| Série | Badge coloré |
| Date d'inscription | Relatif (il y a 3 jours) |
| Dernière connexion | |
| Statut | Actif / Inactif (toggle) |
| Actions | Voir / Modifier / Désactiver / Supprimer |

**Fonctionnalités** :
- Recherche en temps réel (nom, email)
- Filtre par série (dropdown)
- Filtre par statut
- Export CSV de la liste
- Pagination (20 par page)
- Vue fiche élève détaillée : statistiques de progression, liste des ressources consultées

---

### 10.4 Gestion Séries & Matières `/admin/series.php`

#### Section Séries
- Liste des 5 séries avec : Nom, Description, Nb matières, Nb élèves, Statut actif/inactif
- Formulaire d'édition inline

#### Section Matières
- Tableau par série
- Ajout matière : Nom + Icône + Série + Ordre d'affichage
- Modification / suppression
- Réorganisation par drag-and-drop (JS Sortable)

#### Section Chapitres
- Par matière : liste des chapitres avec ordre
- CRUD complet

---

### 10.5 Gestion Ressources `/admin/ressources.php`

#### Liste des ressources

**Tableau avec colonnes** :

| Colonne | Détail |
|---|---|
| Titre | Cliquable → aperçu |
| Type | Badge (Cours / TD / Épreuve) |
| Série | Badge |
| Matière | |
| Chapitre | |
| Taille | En Ko/Mo |
| Vues | Compteur |
| Date | |
| Actions | Voir / Modifier / Supprimer |

**Filtres** :
- Par série
- Par matière
- Par type
- Par chapitre
- Recherche par titre

#### Formulaire Upload Ressource (modal ou page dédiée)

```
Titre de la ressource *
Description (optionnel)
Type * [Cours | Travail Dirigé | Ancienne Épreuve]
Série *
Matière * (chargée dynamiquement selon la série)
Chapitre (optionnel, chargé selon la matière)
Année (si Ancienne Épreuve)
Fichier PDF * [Drag & Drop ou clic pour uploader]
   → Aperçu du nom + taille
   → Validation : PDF uniquement, max 50Mo
```

**Comportement upload** :
- Barre de progression d'upload (JS)
- Validation MIME type côté PHP (`application/pdf`)
- Renommage automatique du fichier (slug + timestamp)
- Stockage dans `/uploads/ressources/[serie]/[matiere]/`

---

### 10.6 Statistiques `/admin/stats.php`

**Sections** :

#### Statistiques Globales
- Utilisateurs actifs (connectés dans les 7 derniers jours)
- Top 10 ressources les plus consultées
- Temps moyen de révision par série

#### Statistiques par Série
- Tableau comparatif : nb élèves, nb ressources, taux de complétion

#### Carte d'activité (GitHub-style heatmap)
- Visualisation des connexions par jour sur les 3 derniers mois

#### Rapport de progression
- Répartition des élèves par niveau de progression (0-25%, 25-50%, 50-75%, 75-100%)
- Graphique en barres

---

### 10.7 Notifications Admin `/admin/notifications.php`

- Composer et envoyer une notification à :
  - Tous les élèves
  - Une série spécifique
  - Un élève spécifique
- Historique des notifications envoyées
- Statistiques de lecture (nb lus / nb total)

---

### 10.8 Paramètres `/admin/settings.php`

- Informations de l'établissement (nom, logo, contact)
- Gestion des admins (ajouter/modifier/supprimer)
- Configuration upload : taille max fichier, formats autorisés
- Toggle : permettre ou non le téléchargement des PDFs par les élèves
- Maintenance mode (cache le front-office avec message)

---

## 11. Fonctionnalités transversales

### 11.1 Système de progression & temps de révision

**Fonctionnement technique** :

```javascript
// Démarrage de session de révision au chargement du viewer
function startRevisionSession(ressourceId) {
    const startTime = Date.now();
    // Envoyer au backend via AJAX : création session_revision
    fetch('/api/progression.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'start', ressource_id: ressourceId })
    });
    return startTime;
}

// Sauvegarde toutes les 30 secondes
setInterval(() => {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    fetch('/api/progression.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'heartbeat',
            ressource_id: ressourceId,
            temps: elapsed,
            page_actuelle: currentPage
        })
    });
}, 30000);

// À la fermeture de la page
window.addEventListener('beforeunload', endRevisionSession);
```

**Calcul progression** :
- Progression en % = `(pages vues / total pages PDF) * 100`
- Statut "terminé" : atteindre la dernière page OU clic manuel "Marquer comme terminé"

### 11.2 Système de recherche

- Barre de recherche globale dans le header (front-office)
- Recherche en temps réel via AJAX (debounce 300ms)
- Recherche sur : titre ressource, matière, chapitre
- Résultats groupés par type

### 11.3 Responsive Design

| Breakpoint | Comportement |
|---|---|
| Mobile < 768px | Sidebar cachée → hamburger menu, 1 colonne |
| Tablet 768–1024px | Sidebar icônes seulement, 2 colonnes |
| Desktop > 1024px | Sidebar complète, layout normal |

### 11.4 Gestion des erreurs

- Page 404 personnalisée (style Connect'Academia)
- Page 403 pour accès non autorisé
- Messages d'erreur inline sur les formulaires
- Toast notifications pour les actions (succès/erreur)

---

## 12. Spécifications des pages

### 12.1 URLs et routing

| URL | Page | Auth |
|---|---|---|
| `/` | Landing page | Public |
| `/login.php` | Connexion élève | Public |
| `/register.php` | Inscription | Public |
| `/dashboard.php` | Dashboard élève | Élève |
| `/matieres.php` | Liste matières | Élève |
| `/ressources.php` | Ressources d'une matière | Élève |
| `/viewer.php` | Lecteur PDF | Élève |
| `/progression.php` | Ma progression | Élève |
| `/profil.php` | Mon profil | Élève |
| `/favoris.php` | Mes favoris | Élève |
| `/admin/` | Dashboard admin | Admin |
| `/admin/login.php` | Connexion admin | Public |
| `/admin/users.php` | Gestion utilisateurs | Admin |
| `/admin/ressources.php` | Gestion ressources | Admin |
| `/admin/series.php` | Gestion séries/matières | Admin |
| `/admin/stats.php` | Statistiques | Admin |
| `/admin/notifications.php` | Notifications | Admin |
| `/admin/settings.php` | Paramètres | Admin |

### 12.2 API Endpoints PHP

```
GET  /api/series.php                    → Liste des séries actives
GET  /api/matieres.php?serie_id=X       → Matières d'une série
GET  /api/chapitres.php?matiere_id=X    → Chapitres d'une matière
GET  /api/ressources.php?matiere_id=X   → Ressources filtrées
POST /api/progression.php               → Mise à jour progression
POST /api/favoris.php                   → Toggle favori
GET  /api/stats.php                     → Stats admin
POST /api/upload.php                    → Upload PDF
```

---

## 13. Règles métier

1. **Un élève ne peut s'inscrire qu'avec une seule série** mais peut consulter les ressources de toutes les séries.
2. **Un PDF ne peut être uploadé que par un admin connecté.**
3. **La progression est calculée automatiquement** à partir du nombre de pages consultées.
4. **Une session de révision commence** dès l'ouverture du viewer PDF et se termine à la fermeture.
5. **Les ressources supprimées** côté admin restent en base (soft delete) — les progressions associées sont conservées.
6. **Un élève inactif** (désactivé par l'admin) ne peut plus se connecter.
7. **Le téléchargement de PDF** n'est autorisé que si l'admin l'a activé dans les paramètres.
8. **Les notifications** sans `user_id` sont visibles par tous les élèves.

---

## 14. Sécurité & Authentification

### 14.1 Authentification

- Sessions PHP avec `session_start()` et `session_regenerate_id()` après login
- Timeout de session : 2 heures d'inactivité
- Séparation stricte sessions élèves / admins (tables distinctes)

### 14.2 Sécurité des données

```php
// Hachage des mots de passe
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$valid = password_verify($input, $hash);

// Requêtes préparées PDO (JAMAIS de concaténation SQL directe)
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// Protection XSS
$safe = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

// CSRF Token sur tous les formulaires POST
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;
```

### 14.3 Upload sécurisé

```php
// Validation MIME type réel (pas juste l'extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpFile);
if ($mimeType !== 'application/pdf') {
    die('Fichier invalide');
}

// Renommage pour éviter l'exécution de code
$newName = uniqid('ressource_', true) . '.pdf';

// Répertoire hors racine web (ou .htaccess deny all)
$uploadPath = __DIR__ . '/../private/uploads/' . $newName;
```

### 14.4 Protection des routes

```php
// Middleware d'authentification (inclus en haut de chaque page protégée)
// /includes/auth_check.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// /includes/admin_check.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}
```

---

## 15. Performance & Accessibilité

### 15.1 Optimisations performance

- Minification CSS/JS en production
- Compression GZIP activée (`.htaccess`)
- Cache navigateur sur les assets statiques (images, CSS, JS)
- Lazy loading des images
- Pagination des listes (20 items max par page)
- Index MySQL sur les colonnes fréquemment filtrées (`serie_id`, `matiere_id`, `user_id`)

### 15.2 Accessibilité (WCAG 2.1 AA)

- Contraste texte/fond conforme (ratio ≥ 4.5:1)
- Navigation clavier complète
- Attributs `aria-label` sur les boutons icônes
- Focus visible sur les éléments interactifs
- Textes alternatifs sur toutes les images

---

## 16. Roadmap & Phases de développement

### Phase 1 — MVP (6–8 semaines)

**Backend & Base de données**
- [ ] Setup MySQL : création de toutes les tables
- [ ] Seed data : séries, quelques matières et chapitres
- [ ] Système d'authentification (élèves + admin)
- [ ] API ressources (CRUD complet)
- [ ] Système upload PDF
- [ ] API progression (start/heartbeat/end)

**Front-Office**
- [ ] Landing page
- [ ] Inscription / Connexion
- [ ] Dashboard élève (KPIs + reprendre là où j'en suis)
- [ ] Page matières par série
- [ ] Page liste ressources (cartes Coursera)
- [ ] Viewer PDF avec timer
- [ ] Système de progression basique

**Back-Office**
- [ ] Login admin
- [ ] Dashboard avec KPIs
- [ ] Gestion ressources (upload PDF, nommage, catégorisation)
- [ ] Gestion utilisateurs (liste + activer/désactiver)

---

### Phase 2 — Enrichissement (4–6 semaines)

- [ ] Système de favoris
- [ ] Notifications (admin → élèves)
- [ ] Page progression élève complète (graphiques)
- [ ] Recherche globale
- [ ] Export CSV utilisateurs
- [ ] Statistiques admin avancées (Chart.js)
- [ ] Gestion séries/matières/chapitres depuis l'admin
- [ ] Responsive mobile optimisé

---

### Phase 3 — Gamification & Avancé (4 semaines)

- [ ] Système de badges
- [ ] Heatmap d'activité
- [ ] Notifications en temps réel (polling AJAX)
- [ ] Mode hors ligne (PWA basique)
- [ ] Rapport de progression téléchargeable (PDF)
- [ ] Section commentaires sur les ressources

---

## 17. Arborescence des fichiers du projet

```
connect-academia/
├── login.php                          # Connexion élève
├── register.php                       # Inscription élève
├── dashboard.php                      # Dashboard élève
├── matieres.php                       # Liste des matières
├── ressources.php                     # Ressources d'une matière
├── viewer.php                         # Lecteur PDF
├── progression.php                    # Page progression
├── profil.php                         # Page profil
├── favoris.php                        # Page favoris
├── notifications.php                  # Notifications élève
├── logout.php                         # Déconnexion
│
├── admin/
│   ├── index.php → dashboard.php      # Redirect
│   ├── login.php                      # Login admin
│   ├── logout.php
│   ├── dashboard.php                  # Dashboard admin
│   ├── users.php                      # Gestion utilisateurs
│   ├── ressources.php                 # Gestion ressources
│   ├── series.php                     # Gestion séries & matières
│   ├── stats.php                      # Statistiques
│   ├── notifications.php              # Envoyer notifications
│   └── settings.php                   # Paramètres
│
├── api/
│   ├── auth.php
│   ├── users.php
│   ├── series.php
│   ├── matieres.php
│   ├── chapitres.php
│   ├── ressources.php
│   ├── progression.php
│   ├── favoris.php
│   ├── notifications.php
│   └── stats.php
│
├── includes/
│   ├── db.php                         # Connexion PDO MySQL
│   ├── auth_check.php                 # Middleware auth élève
│   ├── admin_check.php                # Middleware auth admin
│   ├── helpers.php                    # Fonctions utilitaires
│   └── config.php                     # Config (DB credentials, paths)
│
├── assets/
│   ├── css/
│   │   ├── main.css                   # Styles communs
│   │   ├── front.css                  # Styles front-office
│   │   ├── admin.css                  # Styles back-office
│   │   └── components/
│   │       ├── cards.css
│   │       ├── sidebar.css
│   │       ├── modal.css
│   │       └── progress.css
│   ├── js/
│   │   ├── main.js
│   │   ├── viewer.js                  # Logique PDF.js + timer
│   │   ├── progression.js             # Tracking progression
│   │   ├── admin.js                   # Logique back-office
│   │   ├── upload.js                  # Gestion upload fichiers
│   │   └── charts.js                  # Chart.js admin
│   └── img/
│       ├── logo.svg                   # Logo Connect'Academia
│       ├── favicon.ico
│       └── illustrations/
│
├── uploads/                           # Fichiers uploadés (PDFs)
│   └── ressources/
│       ├── A1/
│       ├── A2/
│       ├── B/
│       ├── C/
│       └── D/
│
├── uploads/.htaccess                  # Désactiver l'exécution PHP dans uploads
│
├── database/
│   ├── schema.sql                     # Création tables
│   └── seed.sql                       # Données initiales (séries, matières)
│
├── .htaccess                          # Réécriture URL + sécurité
└── README.md
```

---

## Annexe A — Matières de référence par série

> Ces données serviront à remplir la table `matieres` via le seed SQL.

| Matière | A1 | A2 | B | C | D |
|---|:---:|:---:|:---:|:---:|:---:|
| Mathématiques | ✓ | ✓ | ✓ | ✓ | ✓ |
| Français / Littérature | ✓ | ✓ | ✓ | ✓ | ✓ |
| Philosophie | ✓ | ✓ | ✓ | ✓ | ✓ |
| Anglais (LV1) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Histoire-Géographie | ✓ | ✓ | ✓ | — | — |
| SVT | ✓ | ✓ | — | ✓ | ✓ |
| Physique-Chimie | — | — | — | ✓ | ✓ |
| Économie / Gestion | — | ✓ | ✓ | — | — |
| LV2 (Espagnol) | ✓ | ✓ | ✓ | — | — |
| Comptabilité | — | — | ✓ | — | — |

---

## Annexe B — Variables d'environnement & Configuration

```php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'connect_academia');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_PATH', __DIR__ . '/../uploads/ressources/');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50 Mo
define('BASE_URL', 'http://localhost/connect-academia');

define('SESSION_TIMEOUT', 7200); // 2 heures
define('ALLOW_PDF_DOWNLOAD', true); // configurable par admin
```

---

*Document rédigé pour le développement de Connect'Academia — Gabon 🇬🇦*
*Version 1.0 — Prêt pour implémentation avec Cursor AI*
