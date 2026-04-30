# Connect'Academia — Analyse Technique & Point de Vue CTO
**Par Claude Code (Sonnet 4.6) — Avril 2026**

---

## 1. État des Lieux : Ce Qui Existe Réellement

### Vision du Cahier des Charges vs Réalité du Code

| Module | CDC dit | Réalité observée | Maturité |
|---|---|---|---|
| **Apprentissage** | "Développé (projet séparé)" | Fonctionnel, 38 fichiers PHP, IA Gemini intégrée | **~75%** |
| **Communauté** | "À créer entièrement" | Architecture MVC complète, 52 fichiers PHP — mais **sans le Chat** | **~55%** |
| **Orientation** | "Projet principal existant" | 5 fichiers **HTML statiques uniquement**, zéro backend | **~10%** |
| **Auth unifié** | "Via `/auth/`" | 2 fichiers HTML sans PHP (`login.html`, `inscription.html`) | **~5%** |
| **Hub Central** | "À créer" | Répertoire vide | **0%** |
| **Admin Back-Office** | "À compléter" | Répertoire racine **entièrement vide** | **0%** |
| **Redis** | "À configurer" | Aucun fichier de config, aucune intégration | **0%** |
| **AWS S3 / CloudFront** | "À configurer" | Aucune trace dans le code | **0%** |
| **Paiement** | "À intégrer" | Aucune trace dans le code | **0%** |

---

## 2. Le Problème Architectural Fondamental

C'est le point le plus critique du projet, et il n'est pas mentionné clairement dans le cahier des charges.

### Deux projets, deux architectures incompatibles

**`public/Apprentissage 2/`** — PHP procédural classique
```
includes/config.php      → constantes globales
includes/db.php          → PDO singleton
includes/auth_check.php  → session_start() + vérif manuelle
api/gemini.php           → endpoint direct
dashboard.php            → page PHP classique
```

**`public/community 4/`** — MVC structuré avec routeur
```
app/Core/Router.php      → routeur avec middleware
app/Core/Database.php    → classe Database encapsulée
app/Controllers/         → controllers séparés par domaine
app/Models/              → modèles ORM maison
app/Views/               → templates organisés par layout
config/routes.php        → toutes les routes centralisées
```

**Conséquence directe** : ces deux modules ne peuvent pas simplement coexister dans un même projet sans décision architecturale explicite. Forcer une intégration sans ce choix produit une codebase impossible à maintenir.

---

## 3. Les Conflits Concrets à Résoudre

### 3.1 Conflit de table `users` — Critique

La table `users` d'Apprentissage et celle de Communauté sont incompatibles :

| Champ | Apprentissage | Communauté |
|---|---|---|
| Clé primaire | `INT` | `INT UNSIGNED` |
| Mot de passe | `password` | `password_hash` |
| UUID | absent | `CHAR(36)` NOT NULL |
| Rôle | absent | `ENUM('eleve','enseignant','admin')` |
| Série (bac) | `serie_id FK` | absent |
| Bio, établissement | absents | présents |
| Compteurs sociaux | absents | `posts_count`, `followers_count`, `following_count` |
| Vérification email | absent | `is_verified`, `email_token` |

La migration n'est pas un simple `ALTER TABLE` — c'est une refonte du modèle utilisateur central.

### 3.2 Authentification dupliquée

- Apprentissage : session PHP native (`$_SESSION['user_id']`)
- Communauté : session PHP native + middleware CSRF (`Core/Session.php`)
- Admin Apprentissage : `$_SESSION['admin_id']`
- Admin Communauté : session admin séparée
- Auth racine prévu : JWT + Redis (spécifié dans le CDC)

**Quatre systèmes d'auth** pour une plateforme censée partager la session entre modules.

### 3.3 Double admin panel

La communauté a déjà son propre back-office complet (DashboardController, UsersController, ReportsController, SettingsController) avec son propre login admin. Le CDC demande un admin unifié avec 2FA TOTP. Ces deux systèmes vont se marcher dessus.

### 3.4 Le Chat est absent

Le cahier des charges décrit en détail un chat par salons (Long Polling). Dans `community 4`, il n'existe **aucun fichier** lié au chat : pas de modèle `Message.php`, pas de `ChatController.php`, pas de vue salon, pas de route `/chat`. C'est une fonctionnalité à construire entièrement, contrairement à ce que laisse entendre "À créer entièrement".

### 3.5 Sécurité : API Key exposée

Dans `public/Apprentissage 2/includes/config.php` :
```php
define('GEMINI_API_KEY', 'AIzaSyCoJAh_Okx6had13hcUewafRaOEuHOOf4U');
```
La clé Gemini est en clair dans le code. Si ce projet est versionné (git) un jour, cette clé sera compromise. Elle doit impérativement migrer vers un `.env`.

---

## 4. Ce Qui Fonctionne Bien — À Préserver

### Module Apprentissage
- Viewer PDF avec suivi de progression (945 lignes, fonctionnel)
- Intégration Gemini AI avec rate limiting par session
- Système de favoris avec API JSON
- Admin CRUD pour ressources, séries, utilisateurs
- Schéma BDD propre avec 12 tables et index de performance

### Module Communauté
- Architecture MVC solide et extensible
- Routeur avec middleware (auth, csrf, admin) bien conçu
- Modèles séparés (Post, Comment, Like, Follow, Bookmark, Notification)
- Système de signalement de contenu
- Admin panel avec gestion des utilisateurs, rapports, paramètres
- Fichier `.env` et `.env.example` présents — bonne pratique

---

## 5. Mon Point de Vue : Les Décisions Critiques

### Décision 1 — Choisir une architecture principale

**Ma recommandation : adopter l'architecture MVC de la Communauté comme base.**

Raisons :
- Elle est plus structurée et scalable pour 10 000+ utilisateurs
- Elle a déjà Router, Middleware, Models — ce qui est réutilisable
- La migration du code procédural Apprentissage vers des controllers est faisable
- L'inverse (faire du MVC avec du procédural comme socle) est beaucoup plus risqué

Le coût : réécrire les pages Apprentissage comme views/controllers. Estimé à 3-5 jours.

### Décision 2 — La migration BDD doit être faite AVANT tout le reste

L'ordre du CDC (priorité 1) est correct. Mais la fusion des tables `users` doit être réfléchie maintenant. Mon schéma recommandé pour la table unifiée :

```sql
CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid            CHAR(36) NOT NULL UNIQUE,
  nom             VARCHAR(100) NOT NULL,
  prenom          VARCHAR(100) NOT NULL,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  serie_id        INT DEFAULT NULL,           -- Apprentissage
  role            ENUM('eleve','enseignant','admin') DEFAULT 'eleve',
  photo_profil    VARCHAR(500) DEFAULT NULL,
  bio             VARCHAR(160) DEFAULT NULL,
  etablissement   VARCHAR(255) DEFAULT NULL,
  is_verified     TINYINT(1) DEFAULT 0,
  is_active       TINYINT(1) DEFAULT 1,
  is_deleted      TINYINT(1) DEFAULT 0,
  email_token     VARCHAR(64) DEFAULT NULL,
  posts_count     INT UNSIGNED DEFAULT 0,
  followers_count INT UNSIGNED DEFAULT 0,
  following_count INT UNSIGNED DEFAULT 0,
  last_login      DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Décision 3 — Auth JWT vs sessions PHP

Le CDC demande JWT + Redis pour le front-office. C'est techniquement pertinent pour l'extensibilité, mais complexe à implémenter proprement en PHP sans framework. Si le temps est limité, une session PHP partagée bien configurée (`session_name`, `session_save_path`) est plus rapide et moins risquée. JWT est indispensable uniquement pour l'API mobile future ou les microservices.

**Ma recommandation** : sessions PHP partagées pour v1, JWT uniquement pour l'admin (comme spécifié).

### Décision 4 — L'Orientation est à reconstruire

Ce n'est pas "intégrer" quelque chose — c'est 5 pages HTML sans backend, sans BDD, sans auth. La fonctionnalité Orientation (pré-inscriptions, immersion établissements) représente un chantier de développement complet. Elle doit être traitée comme un nouveau module, pas comme une base existante.

---

## 6. Risques Identifiés

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Régression Apprentissage lors de la migration | Haute | Critique | Tests manuels après chaque étape, garder l'original intact |
| Conflit de session entre modules avant unification | Haute | Élevé | Unifier auth en priorité absolue |
| Clé Gemini exposée si versioning | Haute | Élevé | Migrer vers .env immédiatement |
| Dépassement scope (MVP trop large) | Moyenne | Critique | Définir un MVP strict, différer paiement et AWS |
| Incompatibilité PHP version sur hébergeur | Basse | Élevé | Vérifier version PHP MAMP vs production |
| Agrégateur paiement gabonais non disponible en sandbox | Basse | Moyen | Contacter l'agrégateur tôt pour obtenir les credentials test |

---

## 7. Plan d'Exécution Révisé

Par rapport à l'ordre du CDC, je propose ces ajustements :

### Phase 0 — Préparation (Avant tout code)
1. Mettre la clé Gemini dans un `.env` à la racine
2. Créer `.gitignore` pour protéger `.env`
3. Décider officiellement de l'architecture (MVC recommandé)
4. Créer la structure de répertoires unifiée vide

### Phase 1 — Fondations (CDC Priorités 1 & 2)
5. Schéma BDD unifié (table `users` fusionnée + toutes les tables)
6. Auth PHP unifié avec sessions partagées : `/auth/connexion`, `/auth/inscription`
7. Middleware d'authentification commun

### Phase 2 — Hub & Navigation (CDC Priorité 4)
8. Page Hub avec 3 cartes (Apprentissage / Communauté / Orientation)
9. Barre de navigation persistante
10. Logique d'abonnement (période gratuite 1 jour)

### Phase 3 — Intégration Apprentissage (CDC Priorité 3)
11. Migration des pages Apprentissage vers la structure unifiée
12. Vérification de tous les endpoints API apprentissage
13. Suppression de l'admin apprentissage (mutualisé dans l'admin unifié)

### Phase 4 — Communauté (CDC Priorité 5)
14. Migration du module community 4 vers la structure unifiée
15. Développement du Chat (Long Polling) — fonctionnalité manquante
16. Tests du fil d'actualité, likes, commentaires, follows

### Phase 5 — Infrastructure (CDC Priorités 6, 7, 8)
17. Configuration Redis (sessions, cache, rate limiting)
18. Admin unifié avec auth 2FA (TOTP)
19. Intégration AWS S3 + CloudFront

### Phase 6 — Paiement & Production (CDC Priorités 9–12)
20. Intégration agrégateur paiement gabonais
21. Tests de sécurité complets
22. Déploiement et monitoring

---

## 8. Estimation Honnête de l'Ampleur

En développement PHP solo assisté par IA, avec le code existant comme base :

| Phase | Durée estimée |
|---|---|
| Phase 0 (préparation) | 0.5 jour |
| Phase 1 (fondations BDD + auth) | 3-4 jours |
| Phase 2 (Hub) | 1-2 jours |
| Phase 3 (Apprentissage intégré) | 3-4 jours |
| Phase 4 (Communauté + Chat) | 4-6 jours |
| Phase 5 (Redis + Admin 2FA + AWS) | 4-5 jours |
| Phase 6 (Paiement + prod) | 3-5 jours |
| **Total** | **~3 à 4 semaines de développement intensif** |

---

## 9. Ce Que Je Ferais en Premier

Si je devais choisir les 3 actions à faire maintenant :

1. **Déplacer la clé Gemini dans `.env`** — sécurité immédiate, 15 minutes de travail
2. **Créer le schéma BDD unifié** — c'est le socle de tout, impossible d'avancer sans
3. **Construire l'auth PHP commun** — sans auth partagée, tous les modules restent des îles

Le reste peut s'empiler logiquement dessus.

---

*Analyse produite par Claude Code (Anthropic) — Avril 2026*
*Basée sur l'inspection du code source et du cahier des charges v1.0*
*Projet Connect'Academia — Gabon — Confidentiel*
