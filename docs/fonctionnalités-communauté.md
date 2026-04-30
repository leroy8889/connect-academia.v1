# Analyse du Projet avec Focus Admin et Tableau de Bord

## Objectif du document

Ce document synthétise l'analyse du projet **StudyLink** avec un focus prioritaire sur la **partie administration** et plus particulièrement sur le **tableau de bord admin**.

L'objectif est de :

- recenser les fonctionnalités réellement présentes dans le code ;
- illustrer clairement le rôle de chaque écran admin ;
- distinguer ce qui est **implémenté** de ce qui est seulement **affiché** ou **prévu visuellement**.

---

## 1. Vue d'ensemble du projet

StudyLink est une plateforme communautaire orientée éducation avec deux grands espaces :

- un **front-office** pour les utilisateurs connectés ;
- un **back-office admin** séparé, accessible via `/admin/login`.

### Front-office repéré dans le projet

- authentification classique utilisateur ;
- fil d'actualité ;
- exploration de publications ;
- création, modification et suppression de posts ;
- likes, bookmarks et signalements ;
- commentaires, réponses et meilleure réponse ;
- profils utilisateurs ;
- suivi d'autres utilisateurs ;
- notifications.

### Back-office repéré dans le projet

- authentification admin dédiée ;
- dashboard principal ;
- gestion des utilisateurs ;
- vue matières / classes ;
- modération des signalements ;
- paramètres de plateforme ;
- support et informations système.

---

## 2. Architecture Admin

## Accès admin

- URL de connexion dédiée : `/admin/login`
- contrôle du rôle admin avant accès aux pages protégées ;
- refus d'accès si l'utilisateur n'est pas connecté, n'est pas admin, est supprimé ou suspendu ;
- journalisation des connexions admin réussies ou échouées dans `admin_logins`.

## Navigation du back-office

Le layout admin contient une sidebar qui centralise les entrées suivantes :

1. `Dashboard`
2. `Users`
3. `Subjects`
4. `Reports`
5. `Settings`
6. `Support`

Le dashboard joue donc le rôle de **hub principal** du back-office.

---

## 3. Tableau de Bord Admin : fonctionnalités réellement présentes

Le dashboard admin est accessible via `/admin`.

Il repose sur quatre briques principales :

1. des **KPI cards**
2. un **graphique de croissance des inscriptions**
3. un **bloc d'activité par matière**
4. un **tableau d'activité récente**

Une API interne permet aussi d'actualiser dynamiquement les données via `/admin/api/stats`.

---

## 3.1 KPI Cards

Le tableau de bord affiche **4 cartes KPI principales**.

### 1. Total Students

- compte le nombre d'utilisateurs avec le rôle `eleve` ;
- ignore les comptes supprimés ;
- affiche une variation en pourcentage par rapport au mois précédent.

### 2. Active Teachers

- compte les utilisateurs `enseignant` actifs ;
- ignore les comptes supprimés ;
- affiche une évolution mensuelle.

### 3. Active Users (MAU)

- mesure les utilisateurs actifs sur les 30 derniers jours ;
- se base sur `last_login` ;
- calcule une variation versus la période précédente.

### 4. Engagement Rate

- calculé à partir des posts et commentaires rapportés au nombre d'utilisateurs actifs ;
- affiché en pourcentage ;
- accompagné d'une variation mensuelle.

### Données complémentaires calculées côté serveur

Même si elles ne sont pas toutes affichées en carte dans le dashboard principal, le contrôleur calcule aussi :

- `total_posts`
- `total_comments`
- `pending_reports`

Ces données servent notamment à l'interface globale et au badge de notification.

### Illustration rapide

| Carte | Ce qu'elle montre | Source principale |
|---|---|---|
| Total Students | volume total d'élèves | table `users` |

| Active Users (MAU) | utilisateurs actifs sur 30 jours | table `users` |
| Engagement Rate | niveau d'interaction global | tables `posts`, `comments`, `users` |

---

## 3.2 Graphique "Registration Growth"

Le dashboard contient un graphique principal intitulé **Registration Growth**.

### Fonctionnalités présentes

- affichage d'un graphique linéaire avec **Chart.js** ;
- récupération des données depuis le serveur ;
- périodes sélectionnables :
  - `7D`
  - `30D`
  - `90D`
- mise à jour dynamique lors du changement de période ;
- remplissage visuel avec animation.

### Données utilisées

Le backend prépare séparément :

- les inscriptions d'élèves ;
- les inscriptions d'enseignants ;
- les labels de dates.

### Comportement réel de l'interface

Le frontend combine actuellement les deux séries pour n'afficher qu'une **courbe globale d'inscriptions**.

Autrement dit :

- le backend distingue bien `students` et `teachers` ;
- l'écran affiche une ligne agrégée `students + teachers`.

### Illustration fonctionnelle

```text
Choix période -> appel /admin/api/stats?period=7|30|90
             -> récupération des statistiques
             -> mise à jour du graphique
```

### Cas de secours prévu

Si l'API ne renvoie pas de données exploitables, le JavaScript génère un jeu de données visuel de secours pour conserver un rendu graphique.

---

## 3.3 Bloc "Activity by Subject"

Le dashboard affiche un panneau dédié à l'activité par matière.

### Fonctionnalités présentes

- récupération des **5 matières les plus actives** ;
- calcul du nombre de posts par matière ;
- conversion en pourcentage relatif selon la matière la plus active ;
- affichage sous forme de barres horizontales ;
- animation visuelle des barres au chargement ;
- rafraîchissement dynamique lors du polling des stats.

### Ce que cela permet d'interpréter

- quelles matières génèrent le plus de publications ;
- quelle matière domine par rapport aux autres ;
- quelles catégories concentrent l'activité.

### Exemple de lecture

```text
Mathématiques  100%
SVT             74%
Français        52%
Histoire        38%
Physique        31%
```

### Point important

Le bouton **"View Detailed Breakdown"** est présent dans l'interface, mais aucun comportement métier n'est branché derrière dans le JavaScript ou les routes observées.

---

## 3.4 Tableau "Recent Activity"

Le dashboard contient une table d'activité récente.

### Colonnes affichées

- `USER`
- `ACTION`
- `GROUP/SUBJECT`
- `TIMESTAMP`
- `STATUS`

### Types d'activités réellement injectés

Le backend mélange plusieurs flux :

1. les derniers utilisateurs inscrits ;
2. les derniers posts publiés ;
3. les derniers signalements créés.

### Exemples d'actions générées

- un étudiant qui rejoint une classe ;
- un enseignant qui publie une ressource ;
- un utilisateur qui poste une question ;
- un utilisateur qui remonte un problème de modération.

### Enrichissements présents

- avatar utilisateur ;
- rôle traduit en anglais (`Student`, `Teacher`, `Admin`) ;
- sujet ou groupe associé ;
- temps relatif du type `2 hours ago` ;
- badge de statut `success` ou `pending`.

### Utilité concrète

Ce tableau permet à l'admin de surveiller rapidement :

- les nouvelles inscriptions ;
- la création de contenu ;
- la pression de modération.

---

## 3.5 Actualisation dynamique du dashboard

Le dashboard n'est pas statique.

### Fonctionnalités présentes

- polling automatique toutes les `30 secondes` ;
- appel à `/admin/api/stats` ;
- mise à jour en direct :
  - des KPI ;
  - du tableau d'activité récente ;
  - des barres d'activité par matière ;
- effet visuel de surbrillance sur les KPI mis à jour.

### Schéma de fonctionnement

```text
Dashboard chargé
-> initialisation du graphique
-> activation du polling 30s
-> récupération périodique des nouvelles stats
-> rafraîchissement partiel de l'écran sans rechargement complet
```

---

## 3.6 Interactions d'interface présentes dans le dashboard

### Éléments fonctionnels

- menu mobile pour ouvrir/fermer la sidebar ;
- changement de période du graphique ;
- animation des chiffres KPI ;
- animation des barres matières ;
- toast visuel ;
- bouton d'export avec retour visuel.

### Éléments visibles mais non reliés à une vraie logique métier

- champ `Search analytics...` dans le header ;
- bouton de notifications du header ;
- bouton `Export Report` : affiche des toasts mais ne génère pas réellement de fichier ;
- bouton `View Detailed Breakdown` : pas de comportement observé.

### Conclusion sur ce point

Le dashboard est donc **partiellement interactif** :

- les statistiques dynamiques sont bien branchées ;
- certaines actions d'interface restent encore au stade de **placeholder UX**.

---

## 4. Les autres fonctionnalités admin accessibles depuis le dashboard

Le tableau de bord n'est pas isolé. Il renvoie vers plusieurs modules de gestion.

---

## 4.1 Gestion des utilisateurs

Page : `/admin/users`

### Fonctionnalités présentes

- liste paginée des utilisateurs ;
- pagination par `25` éléments ;
- filtres rapides :
  - tous
  - étudiants
  - enseignants
  - admins
  - suspendus
- recherche par nom, prénom ou email ;
- affichage des informations principales :
  - identité
  - email
  - rôle
  - classe ou matière
  - statut
  - date d'inscription
  - dernier login
- suspension / réactivation d'un compte ;
- suppression logique d'un utilisateur ;
- protection contre l'auto-suppression de l'administrateur connecté.

### Illustration fonctionnelle

```text
Admin -> liste des utilisateurs
      -> filtre un rôle
      -> recherche un nom
      -> suspend / réactive
      -> supprime un compte non admin
```

### Ce qui n'a pas été trouvé

- édition complète du profil utilisateur ;
- export CSV / Excel ;
- création d'admin depuis ce module ;
- visualisation détaillée d'un profil depuis le back-office.

---

## 4.2 Gestion des matières et classes

Page : `/admin/subjects`

### Fonctionnalités présentes

- lecture de la liste officielle des matières depuis les settings ;
- lecture de la liste des classes depuis les settings ;
- consolidation avec les données réelles des posts ;
- tableau d'analyse par matière avec :
  - nombre de posts
  - nombre d'utilisateurs
  - total de likes
  - total de commentaires
  - dernière activité
  - barre d'intensité visuelle
- repérage des matières non officielles via le badge `Unofficial` ;
- affichage des classes disponibles.

### Valeur du module

Cette page permet d'identifier :

- les matières les plus vivantes ;
- les matières réellement utilisées par rapport à la configuration officielle ;
- les écarts entre la théorie de la plateforme et les usages réels.

### Limite actuelle

Il s'agit d'une **vue analytique** ; aucune création, modification ou suppression de matière/classe n'est branchée directement depuis cet écran.

---

## 4.3 Modération des signalements

Page : `/admin/reports`

### Fonctionnalités présentes

- liste paginée des signalements ;
- tri prioritaire des `pending` avant les autres ;
- filtres par statut :
  - all
  - pending
  - reviewed
  - dismissed
- affichage du signaleur ;
- affichage de la raison du signalement ;
- aperçu du contenu signalé ;
- identification de l'auteur du contenu ;
- consultation du statut ;
- changement de statut d'un signalement ;
- rejet d'un signalement ;
- suppression logique du contenu signalé ;
- marquage automatique du report comme `reviewed` après suppression du contenu ;
- ajout d'une note admin lors du traitement côté backend.

### Raisons gérées

- `inappropriate`
- `spam`
- `harassment`
- `other`

### Illustration métier

```text
Signalement reçu
-> admin ouvre Reports
-> examine le contenu
-> choisit :
   reviewed
   dismissed
   delete reported content
```

### Ce que cela apporte au dashboard

Le dashboard utilise directement cette donnée pour :

- afficher les signalements dans l'activité récente ;
- afficher le nombre de signalements en attente dans le badge du header.

---

## 4.4 Paramètres de la plateforme

Page : `/admin/settings`

### Fonctionnalités présentes

- édition du nom du site ;
- édition de la description du site ;
- définition de la taille maximale d'upload ;
- configuration des classes ;
- configuration des matières ;
- activation / désactivation :
  - du système de follow
  - de la messagerie directe
- enregistrement des paramètres en base ;
- message de succès après sauvegarde.

### Clés réellement gérées côté backend

- `site_name`
- `site_description`
- `classes_list`
- `matieres_list`
- `enable_messaging`
- `enable_follow`
- `max_upload_mb`

### Limites actuelles

Le backend ne gère pas ici :

- SMTP ;
- bannière globale ;
- logo ;
- permissions fines d'admin ;
- options avancées de sécurité.

---

## 4.5 Support et informations système

Page : `/admin/support`

### Fonctionnalités présentes

- affichage de la version PHP ;
- affichage de la version de la base de données ;
- affichage de l'environnement applicatif ;
- statistiques de base de données :
  - utilisateurs
  - posts
  - commentaires
  - signalements
  - signalements en attente
- informations serveur ;
- limites PHP comme upload et mémoire ;
- tableau des dernières connexions admin si la table `admin_logins` existe ;
- bloc d'aide visuelle Documentation / FAQ / Contact.

### Intérêt

Cet écran sert de zone de diagnostic rapide pour l'admin ou l'équipe technique.

---

## 5. Authentification Admin

L'espace admin possède son propre écran de connexion.

### Fonctionnalités présentes

- formulaire email + mot de passe ;
- protection CSRF ;
- vérification stricte du rôle `admin` ;
- refus si compte désactivé ;
- mémorisation de l'email en cas d'échec ;
- bouton d'affichage du mot de passe ;
- redirection vers le dashboard après succès ;
- lien retour vers le site principal ;
- journalisation des tentatives réussies et échouées.

### Ce qui n'a pas été trouvé

- 2FA ;
- gestion multi-rôles admin détaillée ;
- expiration spéciale de session admin codée ici ;
- écran de gestion des permissions admin.

---

## 6. Ce que le tableau de bord permet réellement à un admin

En pratique, le dashboard permet déjà à un administrateur de :

1. surveiller la croissance de la plateforme ;
2. mesurer l'activité globale ;
3. repérer les matières les plus actives ;
4. voir les dernières inscriptions et publications ;
5. identifier rapidement les signalements ;
6. accéder ensuite aux modules de gestion détaillés.

### Lecture opérationnelle du dashboard

```text
Observer les KPI
-> comprendre la santé globale
-> voir la tendance des inscriptions
-> repérer les sujets les plus actifs
-> détecter les événements récents
-> basculer vers Users / Reports / Settings selon le besoin
```

---

## 7. Écarts entre la documentation produit et l'implémentation réelle

Le projet contient une documentation PRD/back-office plus ambitieuse que le code actuellement observé.

### Fonctionnalités documentées mais non retrouvées comme implémentation claire

- heatmap d'activité ;
- donut chart de répartition des rôles ;
- gestion avancée des publications côté admin ;
- vue kanban de signalements ;
- export réel de rapports ;
- recherche analytics branchée ;
- création d'admins ;
- permissions fines Super Admin / Modérateur ;
- 2FA admin ;
- journaux d'audit complets ;
- édition avancée des utilisateurs ;
- export utilisateurs CSV/Excel.

### Conclusion

Le back-office actuel est **fonctionnel et crédible**, mais il correspond à une **version intermédiaire** :

- le socle analytics/modération/settings est bien en place ;
- plusieurs éléments du PRD restent encore à développer.

---

## 8. Synthèse finale du tableau de bord

### Fonctionnalités effectivement présentes dans le dashboard

- 4 KPI principaux ;
- variation mensuelle sur les KPI ;
- graphique d'inscriptions avec changement de période ;
- activité par matière avec top 5 ;
- tableau d'activité récente ;
- rafraîchissement automatique toutes les 30 secondes ;
- navigation directe vers les modules admin ;
- badge de signalements en attente ;
- animations d'interface ;
- responsive avec sidebar mobile.

### Fonctionnalités visibles mais incomplètes

- export report simulé ;
- recherche analytics non branchée ;
- bouton détails matière non branché ;
- bouton notifications sans logique observée.

### Positionnement global

Le dashboard admin de StudyLink remplit déjà trois rôles importants :

- **pilotage** de la plateforme ;
- **surveillance** de l'activité ;
- **porte d'entrée** vers la modération et l'administration.

Il constitue donc une bonne base de back-office, avec plusieurs zones prêtes à être enrichies dans une prochaine phase.

