# Analyse fonctionnelle du projet

## 1. Périmètre analysé

Cette analyse est basée sur le code réellement présent dans le dépôt au moment de l'audit, avec un focus prioritaire sur la partie `admin/` et sur le tableau de bord d'administration.

## 2. Vue d'ensemble du projet

`Connect'Academia` est une plateforme web PHP/MySQL pour les élèves de Terminale au Gabon.

### Parcours global de la plateforme

```text
Élève
  -> inscription / connexion
  -> dashboard élève
  -> navigation par matières et ressources
  -> lecture PDF
  -> progression, favoris, notifications

Administrateur
  -> connexion admin
  -> dashboard admin
  -> gestion utilisateurs
  -> gestion ressources
  -> gestion séries
```

### Briques techniques identifiées

| Brique | Rôle |
|---|---|
| `admin/*.php` | Interface back-office |
| `api/*.php` | Endpoints AJAX et actions métier |
| `assets/js/admin.js` | Comportements UI admin, surtout mobile |
| `assets/js/upload.js` | Upload PDF avec drag and drop et progression |
| `assets/css/admin.css` | Styles du back-office |
| `database/schema.sql` | Structure complète des données |
| `database/seed.sql` | Données initiales et compte admin par défaut |

## 3. Ce qui existe réellement côté admin

### Pages admin présentes dans le dépôt

| Page | Présence | Rôle réel |
|---|---|---|
| `admin/index.php` | Oui | Redirige vers login ou dashboard |
| `admin/login.php` | Oui | Connexion administrateur |
| `admin/dashboard.php` | Oui | Tableau de bord principal |
| `admin/users.php` | Oui | Liste des utilisateurs |
| `admin/series.php` | Oui | Liste des séries |
| `admin/ressources.php` | Oui | Liste, upload et suppression de ressources |
| `admin/logout.php` | Oui | Déconnexion |
| `admin/reset_session.php` | Oui | Reset de session |

### Pages visibles dans le menu mais absentes du dépôt

| Entrée visible | Fichier attendu | État |
|---|---|---|
| Statistics / Analytiques | `admin/stats.php` | Absent |
| Notifications | `admin/notifications.php` | Absent |
| Settings | `admin/settings.php` | Absent |

Conclusion importante : l'admin affiché à l'écran est plus avancé que l'admin réellement implémenté. Une partie du menu correspond encore à une cible produit décrite dans les PRD, mais pas encore codée.

## 4. Analyse détaillée du tableau de bord admin

Fichier principal analysé : `admin/dashboard.php`

### 4.1 Objectif du dashboard

Le dashboard admin sert de page d'atterrissage après connexion. Il donne une vue synthétique de la plateforme avec :

1. des indicateurs clés,
2. un graphique de répartition,
3. deux tableaux de suivi rapide,
4. des raccourcis de navigation vers les autres modules admin.

### 4.2 Structure visuelle du dashboard

```text
Sidebar fixe
  - Dashboard
  - Users
  - Series & Subjects
  - Resources
  - Statistics
  - Notifications
  - Settings
  - Logout

Topbar
  - breadcrumb
  - cloche visuelle
  - bloc profil admin statique

Contenu principal
  - 4 cartes KPI
  - 1 graphique Chart.js
  - 1 tableau "Dernières inscriptions"
  - 1 tableau "Ressources récentes"
```

### 4.3 Fonctionnalités présentes dans le dashboard

#### A. Contrôle d'accès admin

Avant l'affichage du dashboard, `includes/admin_check.php` :

- vérifie qu'une session admin existe,
- contrôle l'inactivité via `SESSION_TIMEOUT`,
- redirige vers `admin/login.php` si nécessaire.

#### B. Navigation latérale persistante

La sidebar admin permet un accès direct aux zones majeures du back-office.

Fonctionnel aujourd'hui :

- `Dashboard`
- `Users`
- `Series & Subjects`
- `Resources`
- `Logout`

Affiché mais non disponible dans le code :

- `Statistics`
- `Notifications`
- `Settings`

#### C. Topbar d'administration

La topbar fournit :

- un breadcrumb de page,
- une icône de notification purement visuelle,
- une carte profil admin statique avec avatar "A", libellé "Admin user" et rôle "Super Admin".

Remarque : ces informations ne sont pas injectées dynamiquement depuis la table `admins`.

#### D. Cartes KPI

Le dashboard expose 4 indicateurs principaux.

| KPI affiché | Source SQL réelle | Ce que cela mesure réellement |
|---|---|---|
| `Total Students` | `COUNT(*) FROM users WHERE is_active = 1` | Nombre d'élèves actifs |
| `Resources Published` | `COUNT(*) FROM ressources WHERE is_deleted = 0` | Nombre de ressources publiées non supprimées |
| `Views this week` | `SUM(nb_vues)` sur `ressources` créées depuis 7 jours | Total des vues des ressources récentes, pas des vues réellement survenues cette semaine |
| `Total Revision Time` | `SUM(duree_secondes) FROM sessions_revision` | Temps cumulé de révision de tous les élèves |

Illustration fonctionnelle :

```text
KPI 1 -> suit la base élèves active
KPI 2 -> suit le volume documentaire disponible
KPI 3 -> suit la visibilité des nouvelles ressources
KPI 4 -> suit le temps d'engagement cumulé
```

#### E. Graphique "Élèves par série"

Le dashboard affiche un graphique en barres `Chart.js`.

Fonctionnement :

- requête SQL regroupée par série,
- récupération de la couleur de chaque série depuis la base,
- génération d'un histogramme,
- fallback avec message `Aucune donnée disponible` si la série ne remonte aucun résultat.

Source de données réelle :

`series LEFT JOIN users ON users.serie_id = series.id AND users.is_active = 1`

Utilité métier :

- comparer les effectifs par série,
- visualiser rapidement les séries les plus peuplées,
- réutiliser la couleur métier stockée en base.

#### F. Tableau "Dernières inscriptions"

Le dashboard remonte les 5 derniers élèves inscrits.

Colonnes présentes :

- nom et prénom,
- série,
- date d'inscription,
- statut.

Comportements utiles :

- affichage des initiales dans un avatar généré côté vue,
- badge `ACTIVE` ou `PENDING` selon `is_active`,
- lien `View All` vers `admin/users.php`.

#### G. Tableau "Ressources récentes"

Le dashboard remonte les 5 ressources les plus récemment créées.

Colonnes présentes :

- titre,
- type,
- matière,
- vues.

Comportements utiles :

- badge de type de ressource,
- affichage du compteur de vues,
- lien `Manage All` vers `admin/ressources.php`.

### 4.4 Comportements UX autour du dashboard

#### Responsive admin

Le fichier `assets/js/admin.js` ajoute un menu hamburger mobile quand la largeur passe sous `640px`.

Fonctionnalités présentes :

- bouton d'ouverture du menu,
- overlay de fermeture,
- changement d'icône menu / fermeture,
- fermeture automatique au clic sur un lien,
- réinitialisation dynamique au resize.

#### Rendu visuel

Le fichier `assets/css/admin.css` fournit :

- layout admin fixe avec sidebar sombre,
- topbar blanche,
- cartes KPI,
- cartes de graphiques,
- tableaux stylés,
- composants de formulaire,
- styles d'upload,
- comportement responsive mobile.

## 5. Fonctionnalités admin hors dashboard

Même si votre demande cible surtout le dashboard, voici les modules réellement utilisables autour de lui.

### 5.1 Connexion admin

Page : `admin/login.php`

Fonctionnalités présentes :

- authentification par email et mot de passe,
- vérification du hash avec `password_verify`,
- création de session admin,
- stockage du rôle en session,
- redirection vers le dashboard après succès,
- message d'erreur en cas d'identifiants invalides.

Points observés :

- la case "Maintenir ma session active pour 24h" est affichée mais non exploitée,
- le rate limiting mentionné dans les PRD est explicitement désactivé,
- les champs affichent des valeurs par défaut dans le formulaire.

### 5.2 Gestion des utilisateurs

Page : `admin/users.php`

Fonctionnalités réellement présentes :

- listing des utilisateurs,
- affichage nom, email, série, date d'inscription, dernière connexion, statut,
- boutons `Voir` et `Désactiver` dans l'interface.

Limite actuelle :

- les boutons affichés n'ont pas de logique métier connectée,
- pas de recherche,
- pas de filtres,
- pas de pagination,
- pas de modal fiche élève,
- pas d'action AJAX.

### 5.3 Gestion des séries

Page : `admin/series.php`

Fonctionnalités réellement présentes :

- listing des séries,
- affichage description,
- affichage couleur,
- statut actif / inactif,
- bouton `Modifier` dans l'interface.

Limite actuelle :

- pas d'édition effective,
- pas de gestion détaillée des matières,
- pas de drag-and-drop,
- pas de CRUD complet.

### 5.4 Gestion des ressources

Page : `admin/ressources.php`

Fonctionnalités réellement présentes :

- liste des ressources non supprimées,
- affichage du titre et du nombre de vues,
- ouverture d'une ressource dans `viewer.php`,
- suppression logique via `api/delete_ressource.php`,
- ouverture d'une modale d'ajout,
- formulaire d'upload,
- chargement dynamique des matières selon la série via `api/matieres.php`,
- upload AJAX avec barre de progression,
- confirmation et alertes SweetAlert2.

#### Parcours d'ajout de ressource

```text
Admin
  -> clique sur "Nouveau"
  -> ouvre la modale
  -> renseigne titre / description / type / série / matière / chapitre
  -> dépose un PDF
  -> upload AJAX vers api/upload.php
  -> insertion BDD
  -> succès puis rechargement de la page
```

#### Validations réellement présentes

Dans `assets/js/upload.js` :

- PDF uniquement,
- taille max côté front : 20 Mo.

Dans `api/upload.php` :

- session admin obligatoire,
- méthode POST obligatoire,
- champs requis,
- vérification MIME type `application/pdf`,
- taille max côté serveur via `UPLOAD_MAX_SIZE`,
- création automatique du dossier d'upload,
- création automatique du chapitre si nécessaire,
- insertion de la ressource en base.

#### Suppression de ressource

`api/delete_ressource.php` réalise une suppression logique :

- la ligne reste en base,
- `is_deleted = 1`,
- `updated_at = NOW()`.

## 6. Ce que le tableau de bord admin permet concrètement aujourd'hui

En usage réel, le dashboard admin permet de :

1. vérifier rapidement le nombre d'élèves actifs,
2. suivre le stock de ressources publiées,
3. observer le volume de vues des ressources récentes,
4. mesurer le temps total de révision enregistré,
5. comparer les effectifs par série via un graphique,
6. surveiller les 5 dernières inscriptions,
7. surveiller les 5 dernières ressources publiées,
8. accéder rapidement aux modules de gestion déjà codés.

## 7. Écarts et limites importantes relevés

Cette section est utile car elle distingue le fonctionnel réel du fonctionnel simplement affiché ou prévu.

### Dashboard

- Le KPI `Views this week` ne calcule pas les vues de cette semaine au sens strict.
  Il additionne `nb_vues` des ressources créées dans les 7 derniers jours.
- Le profil admin affiché dans la topbar est statique.
- Les entrées `Statistics`, `Notifications` et `Settings` sont visibles mais les pages n'existent pas.

### Connexion admin

- Le rate limiting annoncé dans la documentation n'est pas actif.
- Le mode "remember me" n'est pas implémenté.

### Ressources

- L'interface d'upload affiche `20 Mo`, alors que l'API serveur mentionne `50 Mo` via le message d'erreur.
- Le select des matières contient d'abord des options codées en dur, puis il est rechargé dynamiquement par l'API.
- La suppression est logique uniquement, pas physique.

### Sécurité

- Des helpers CSRF existent dans `includes/helpers.php`, mais ils ne sont pas branchés sur les principales actions admin observées.

## 8. Synthèse finale

Le back-office admin actuellement codé est un socle fonctionnel crédible, surtout autour de 3 axes :

1. la consultation synthétique via le dashboard,
2. l'administration des ressources PDF,
3. la surveillance simple des utilisateurs et des séries.

Le tableau de bord admin est déjà utile pour piloter l'activité générale, mais il reste un dashboard de supervision légère, pas encore un centre d'administration complet. Plusieurs éléments visibles dans l'interface relèvent encore d'une intention produit décrite dans les PRD, mais non matérialisée dans le code du dépôt.
