# Fonctionnalités du Tableau de Bord Admin - StudyLink

## Vue d'ensemble
Le tableau de bord admin de StudyLink est une interface centralisée permettant aux administrateurs de surveiller et gérer la plateforme communautaire éducative. Il offre une vue en temps réel des métriques clés, des tendances d'utilisation et des activités récentes.

## Fonctionnalités Principales

### 1. Cartes KPI (Key Performance Indicators)
Le tableau de bord affiche quatre métriques essentielles sous forme de cartes visuelles :

#### 📊 Total Students
- **Description** : Nombre total d'étudiants inscrits sur la plateforme
- **Calcul** : Comptage des utilisateurs avec `role = 'eleve'` et `is_deleted = 0`
- **Évolution** : Comparaison avec le mois précédent
- **Affichage** : Nombre formaté avec indicateur de croissance (+/- %)

#### 👨‍🏫 Active Teachers
- **Description** : Nombre d'enseignants actifs et vérifiés
- **Calcul** : Comptage des utilisateurs avec `role = 'enseignant'`, `is_deleted = 0`, `is_active = 1`
- **Évolution** : Comparaison avec le mois précédent
- **Affichage** : Nombre formaté avec indicateur de croissance

#### 👥 Active Users (MAU - Monthly Active Users)
- **Description** : Utilisateurs actifs mensuels (connectés dans les 30 derniers jours)
- **Calcul** : Comptage des utilisateurs avec `last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)`
- **Évolution** : Comparaison avec le mois précédent
- **Affichage** : Nombre formaté avec indicateur de croissance

#### 📈 Engagement Rate
- **Description** : Taux d'engagement basé sur les interactions
- **Calcul** : `(posts + commentaires) / utilisateurs actifs × 10`
- **Évolution** : Comparaison avec le mois précédent
- **Affichage** : Pourcentage avec indicateur de croissance

### 2. Graphique de Croissance des Inscriptions
- **Type** : Graphique linéaire interactif
- **Données** : Inscriptions quotidiennes d'étudiants et d'enseignants
- **Périodes** : Boutons de basculement (7 jours, 30 jours, 90 jours)
- **Fonctionnalités** :
  - Affichage cumulatif des inscriptions
  - Séparation par rôle (étudiants vs enseignants)
  - Mise à jour dynamique via API
- **Technologie** : Chart.js pour le rendu

### 3. Activité par Matière
- **Type** : Graphique à barres horizontales
- **Données** : Top 5 des matières par nombre de posts
- **Métriques** :
  - Nombre de posts par matière
  - Pourcentage relatif (barre colorée)
  - Couleurs distinctes pour chaque matière
- **Calcul** : `GROUP BY matiere_tag` sur la table `posts`

### 4. Activité Récente
- **Type** : Tableau paginé avec informations détaillées
- **Colonnes** :
  - **USER** : Avatar, nom, rôle de l'utilisateur
  - **ACTION** : Description de l'activité
  - **GROUP/SUBJECT** : Matière ou classe concernée
  - **TIMESTAMP** : Moment relatif ("il y a X minutes/heures/jours")
  - **STATUS** : Badge de statut (success/pending)
- **Types d'activités trackées** :
  - Nouveaux utilisateurs inscrits
  - Nouveaux posts publiés
  - Signalements de modération
- **Tri** : Par date décroissante (plus récent en premier)

### 5. Métriques Supplémentaires
- **Total Posts** : Nombre total de publications
- **Total Comments** : Nombre total de commentaires
- **Pending Reports** : Signalements en attente de modération

## Fonctionnalités Techniques

### Mise à Jour Dynamique
- **Endpoint API** : `/admin/api/stats`
- **Paramètres** : `period` (7, 30, 90 jours)
- **Format** : JSON avec données pour KPIs, graphiques et activités
- **Fréquence** : Mise à jour manuelle via boutons de période

### Sécurité et Autorisation
- **Middleware** : `admin` requis pour tous les accès
- **Session** : Vérification du rôle administrateur
- **CSRF Protection** : Tokens pour toutes les requêtes

### Base de Données
- **Tables utilisées** :
  - `users` : Données utilisateurs et rôles
  - `posts` : Publications et métadonnées
  - `comments` : Commentaires et interactions
  - `reports` : Signalements de modération
- **Requêtes optimisées** : Comptages et agrégations pour performances

## Navigation Intégrée
Le tableau de bord sert de hub central vers les autres sections admin :
- **Users** : Gestion des utilisateurs (activation/suspension/suppression)
- **Subjects** : Gestion des matières et classes
- **Reports** : Modération des signalements
- **Settings** : Configuration de la plateforme
- **Support** : Informations système et support

## Interface Utilisateur
- **Design** : Interface moderne avec sidebar de navigation
- **Responsive** : Adapté aux écrans desktop et mobiles
- **Thème** : Palette de couleurs cohérente (violet principal)
- **Composants** : Cartes, graphiques, tableaux, badges de statut

Cette analyse couvre l'ensemble des fonctionnalités du tableau de bord admin, offrant une compréhension complète de ses capacités de monitoring et de gestion de la plateforme StudyLink.</content>
<filePath>/Applications/MAMP/htdocs/community/fonctionnalité.md