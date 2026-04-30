# 6. Back-Office — Interface d'Administration

## 6.1 Authentification Admin

- URL dédiée : `/admin/login` (séparée du front-office)
- Authentification par email + mot de passe
- Support **multi-administrateurs** (rôles : Super Admin, Modérateur)
- **2FA optionnel** (code TOTP via Google Authenticator)
- Journal des connexions admin (IP, date, navigateur)
- Session sécurisée avec expiration automatique (8h d'inactivité)

---

## 6.2 Tableau de Bord Principal (Dashboard)

### 6.2.1 KPI Cards — Statistiques en Temps Réel

> Inspiré du design Attio/Linear : cartes minimalistes avec typographie fine et données claires.

| Indicateur | Source | Actualisation |
|---|---|---|
| Total Élèves inscrits | `COUNT(users WHERE role='eleve')` | Temps réel (30s) |
| Total Enseignants inscrits | `COUNT(users WHERE role='enseignant')` | Temps réel (30s) |
| Utilisateurs actifs aujourd'hui | `COUNT(last_login = CURDATE())` | Temps réel (30s) |
| Publications totales | `COUNT(posts WHERE is_deleted=0)` | Temps réel (30s) |
| Publications aujourd'hui | `COUNT(posts WHERE DATE=CURDATE())` | Temps réel (30s) |
| Commentaires totaux | `COUNT(comments)` | Toutes les 5min |
| Taux de résolution des questions | `(resolved/total questions)*100` | Toutes les 5min |
| Signalements en attente | `COUNT(reports WHERE status='pending')` | Temps réel (30s) |

### 6.2.2 Graphiques & Visualisations

- **Graphique linéaire** : Inscriptions par semaine (Élèves vs Enseignants, 12 dernières semaines)
- **Graphique barres** : Publications par matière (Top 10 matières les plus actives)
- **Graphique circulaire (Donut)** : Répartition élèves / enseignants / admins
- **Heatmap d'activité** : Jours et heures d'utilisation (style GitHub contributions)
- **Courbe tendance** : Évolution des likes et commentaires sur 30 jours
- Bibliothèque recommandée : **Chart.js** (gratuite, légère, sans dépendances)

---

## 6.3 Gestion des Utilisateurs

### 6.3.1 Liste des Utilisateurs

- Table paginée (25 entrées/page) avec colonnes : Photo | Nom | Email | Rôle | Classe/Matière | Statut | Date inscription | Actions
- **Filtres** : Par rôle (élève / enseignant / admin), par statut (actif / suspendu), par classe
- Recherche rapide par nom ou email
- Export **CSV ou Excel** de la liste
- Tri par colonne (ascendant/descendant)

### 6.3.2 Actions sur un Utilisateur

- Voir le profil complet (toutes les publications, commentaires, likes)
- Activer / Désactiver le compte
- Réinitialiser le mot de passe (envoi d'email)
- Modifier les informations (classe, matière, rôle)
- Supprimer définitivement le compte (avec confirmation et archivage des données)
- Promouvoir en Modérateur

### 6.3.3 Création de Compte Admin

- Formulaire dédié : email + rôle + envoi d'invitation par email
- Gestion des permissions : **Super Admin** (accès complet) vs **Modérateur** (modération uniquement)
- Journal d'audit des actions admin

---

## 6.4 Gestion des Publications

### 6.4.1 Liste des Publications

- Table paginée avec colonnes : ID | Auteur | Type | Extrait | Matière | Date | Likes | Commentaires | Signalements | Statut | Actions
- **Filtres** : Par type, par matière, par date, par statut (actif/supprimé/épinglé), par signalements
- Visualisation préchargée de la publication au survol (tooltip)

### 6.4.2 Actions sur les Publications

- Modifier le contenu d'une publication
- Supprimer une publication (soft delete + notification à l'auteur)
- **Épingler** une publication (apparaît en top du feed pour tous)
- Masquer temporairement (en attente de modération)
- Marquer comme **'Annonce Officielle'** (style différent dans le feed)
- Créer une nouvelle publication administrative (annonce d'établissement visible par tous)

---

## 6.5 Gestion des Signalements

- **Vue Kanban** des signalements : colonnes *'En attente'*, *'En cours d'examen'*, *'Traité'*
- Drag & drop entre colonnes (style Linear/Trello)
- Chaque carte affiche : contenu signalé + auteur + signaleur + raison + date
- **Actions** : Rejeter le signalement (Dismiss) | Supprimer le contenu | Avertir l'utilisateur | Bannir l'utilisateur
- Réponse automatique à l'utilisateur signalant (email de résolution)

---

## 6.6 Modération des Commentaires

- Liste de tous les commentaires avec filtres (signalés, récents, par utilisateur)
- **Actions** : Supprimer, Masquer, Marquer comme meilleure réponse
- Aperçu du post parent pour le contexte

---

## 6.7 Paramètres de la Plateforme

- Nom de l'établissement et logo
- Listes de classes disponibles (personnalisable par l'admin)
- Liste de matières disponibles
- Activation/désactivation des fonctionnalités (messagerie directe, système de follow...)
- Paramètres email (SMTP : serveur, port, expéditeur)
- Bannières d'annonces globales (message affiché sur tout le front-office)

---
