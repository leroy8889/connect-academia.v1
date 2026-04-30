# ⚙️ PRD-05 — Back-Office : Espace Administrateur
## Connect'Academia — Spécifications des Pages Admin

> **Référence** : PRD principal v1.0 — Section 10
> **Usage Cursor** : Développer après le front-office élève (Phase 1 — semaines 4-6).
> **Design** : Inspiration Linear & Attio — Sidebar sombre, interface épurée, données denses.

---

## 1. Layout global Admin

Toutes les pages admin partagent ce layout :

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR SOMBRE (#2D2D2D)  │   ZONE PRINCIPALE (blanc)  │
│  250px fixe                │                            │
│                            │  TOPBAR (64px) :           │
│  Logo Connect'Academia     │  Breadcrumb | 🔔 | Avatar   │
│  ─────────────             │                            │
│  📊 Dashboard              │  CONTENU DE LA PAGE        │
│  👥 Utilisateurs           │  (scrollable)              │
│  📚 Séries & Matières      │                            │
│  📄 Ressources             │                            │
│  📈 Statistiques           │                            │
│  🔔 Notifications          │                            │
│  ⚙️  Paramètres             │                            │
│  ─────────────             │                            │
│  🚪 Déconnexion            │                            │
└─────────────────────────────────────────────────────────┘
```

**CSS sidebar** : `background: #2D2D2D`, texte et icônes blancs / gris clair, item actif : fond `#8B52FA`.

---

## 2. Page Connexion Admin — `/admin/login.php`

- Design minimaliste : fond `#2D2D2D`, logo Connect'Academia centré, carte blanche
- Champs : Email + Mot de passe
- Rate limiting : 3 tentatives max par session
- Bouton "Se connecter" violet `#8B52FA`
- Aucun lien d'inscription (accès uniquement via super_admin)

---

## 3. Dashboard Admin — `/admin/dashboard.php`

### 3.1 KPI Cards (4 cartes en haut)

| KPI | Icône | Requête SQL |
|---|---|---|
| Total Élèves inscrits | 👥 | `COUNT(*) FROM users WHERE is_active = 1` |
| Ressources publiées | 📄 | `COUNT(*) FROM ressources WHERE is_deleted = 0` |
| Vues cette semaine | 👁️ | `SUM(nb_vues)` sur 7 jours |
| Temps de révision total | ⏱️ | `SUM(duree_secondes) FROM sessions_revision` formaté en heures |

### 3.2 Graphiques Chart.js

**Graphique 1 — Inscriptions par jour** (30 derniers jours)
- Type : Line chart
- Données : `COUNT(*) GROUP BY DATE(created_at)` sur `users`

**Graphique 2 — Ressources par type**
- Type : Donut chart
- Données : `COUNT(*) GROUP BY type` sur `ressources`
- Couleurs : Cours = `#8B52FA`, TD = `#4A90D9`, Épreuves = `#E85D04`

**Graphique 3 — Activité par série**
- Type : Bar chart horizontal
- Données : `SUM(temps_passe) GROUP BY serie_id` depuis `progressions` jointure `ressources`

### 3.3 Tableaux récapitulatifs

**Dernières inscriptions** (5 lignes) :
| Élève | Série | Date | Statut |
|---|---|---|---|
| Nom + Prénom | Badge série | Il y a X jours | Actif / Inactif |

**Ressources récentes** (5 lignes) :
| Titre | Type | Matière | Vues |
|---|---|---|---|

---

## 4. Gestion Utilisateurs — `/admin/users.php`

### Tableau principal

| Colonne | Détail |
|---|---|
| Avatar + Nom Prénom | Cliquable → modal fiche élève |
| Email | |
| Série | Badge coloré (A1/A2/B/C/D) |
| Date d'inscription | Format relatif : "il y a 3 jours" |
| Dernière connexion | Format relatif |
| Statut | Toggle Actif / Inactif (AJAX) |
| Actions | Voir 👁️ / Modifier ✏️ / Désactiver 🚫 / Supprimer 🗑️ |

### Fonctionnalités
- **Recherche temps réel** AJAX (nom, email) — debounce 300ms
- **Filtres** : par série (dropdown) + par statut (actif/inactif)
- **Pagination** : 20 élèves par page
- **Export CSV** : toute la liste filtrée

### Modal "Fiche élève"
- Informations profil (nom, email, série, avatar, date inscription)
- Statistiques : nb ressources consultées, temps total révision, nb favoris
- Liste des 5 dernières ressources consultées avec progression

---

## 5. Gestion Séries & Matières — `/admin/series.php`

### Section Séries
Tableau des 5 séries :
- Nom, Description, Couleur (color picker), Nb matières, Nb élèves, Statut (toggle)
- Formulaire d'édition inline (clic sur la ligne)

### Section Matières
- Sélecteur de série → affiche les matières de cette série
- Tableau : Nom, Icône, Ordre, Statut, Actions (modifier/supprimer)
- Formulaire ajout matière : Nom + Icône (dropdown Lucide) + Série + Ordre
- **Réorganisation drag-and-drop** avec JS Sortable.js (mettre à jour `ordre` via AJAX)

### Section Chapitres
- Sélecteur de matière → affiche les chapitres
- CRUD complet : Titre + Ordre
- Drag-and-drop pour réorganiser

---

## 6. Gestion Ressources — `/admin/ressources.php`

### Tableau ressources

| Colonne | Détail |
|---|---|
| Titre | Cliquable → aperçu PDF |
| Type | Badge (Cours 📘 / TD 📝 / Épreuve 📋) |
| Série | Badge coloré |
| Matière | |
| Chapitre | Si renseigné |
| Taille | Affichée en Ko ou Mo |
| Vues | Compteur |
| Date | Format court |
| Actions | Voir / Modifier / Supprimer |

### Filtres
- Par série (dropdown)
- Par matière (dropdown, rechargé dynamiquement selon la série)
- Par type (Cours / TD / Épreuve)
- Recherche par titre (AJAX)

### Formulaire Upload Ressource

**Accessible via** : Bouton "+ Ajouter une ressource" → modal ou page dédiée

```
┌─────────────────────────────────────────┐
│  Titre *                                 │
│  Description (optionnel)                 │
│  Type * [Cours | Travail Dirigé | Épreuve] │
│  Série *                                 │
│  Matière * (chargée selon la série)      │
│  Chapitre (optionnel)                    │
│  Année (si Ancienne Épreuve)             │
│                                         │
│  ┌─────────────────────────────────┐    │
│  │   📂 Glissez votre PDF ici      │    │
│  │   ou cliquez pour sélectionner  │    │
│  │   [Nom fichier + taille]        │    │
│  └─────────────────────────────────┘    │
│                                         │
│  [████████░░░░░░░░] 52% — Upload...     │
│                                         │
│  [Annuler]              [Publier]        │
└─────────────────────────────────────────┘
```

**Comportement upload** :
- Zone drag & drop + clic
- Barre de progression d'upload JS (XMLHttpRequest avec `progress` event)
- Validation côté client : PDF uniquement, max 50 Mo
- Validation côté PHP : MIME type `application/pdf`, taille
- Renommage automatique : `uniqid('ressource_', true) . '.pdf'`
- Stockage : `/uploads/ressources/[serie]/[matiere_slug]/`
- Après succès → toast SweetAlert2 "Ressource publiée !" + rechargement tableau

---

## 7. Statistiques — `/admin/stats.php`

### 7.1 Statistiques Globales
- Utilisateurs actifs (connectés dans les 7 derniers jours)
- Top 10 ressources les plus consultées (tableau + barres)
- Temps moyen de révision par série

### 7.2 Statistiques par Série
Tableau comparatif :
| Série | Nb élèves | Nb ressources | Taux de complétion moyen |
|---|---|---|---|

### 7.3 Heatmap d'activité (style GitHub)
- Visualisation des connexions par jour sur les 3 derniers mois
- Intensité = nombre de connexions ce jour
- Implémentation : grille CSS + données `sessions_revision`

### 7.4 Répartition progression élèves
- Graphique en barres : nb élèves par tranche de progression
  - 0–25%, 25–50%, 50–75%, 75–100%
- Données : `AVG(pourcentage) GROUP BY user_id` sur `progressions`

---

## 8. Notifications Admin — `/admin/notifications.php`

### Composer une notification
```
Destinataire * :
  ○ Tous les élèves
  ○ Une série → [sélectionner]
  ○ Un élève spécifique → [recherche par nom]

Titre * : ___________________
Message * : _________________
Type : [Info | Succès | Avertissement | Nouvelle ressource]

[Envoyer la notification]
```

### Historique des notifications envoyées
Tableau : Titre | Destinataire | Date | Nb lus / Nb total | Actions (supprimer)

---

## 9. Paramètres — `/admin/settings.php`

### Sections

**Informations plateforme** :
- Nom de l'établissement
- Logo (upload)
- Email de contact

**Configuration upload** :
- Taille max fichier (slider + input, défaut 50 Mo)
- Toggle : Permettre le téléchargement des PDFs par les élèves

**Mode maintenance** :
- Toggle : Activer/désactiver (cache le front-office avec message personnalisable)

**Gestion des admins** :
- Tableau des admins (nom, email, rôle)
- Ajouter un admin (email + mot de passe temporaire)
- Supprimer un admin (impossible de supprimer son propre compte)
- Modifier le rôle (super_admin uniquement)

---

## 10. Règles métier Back-Office

1. Seul un **admin connecté** peut uploader des PDFs.
2. La suppression d'une ressource est un **soft delete** (`is_deleted = 1`), les progressions sont conservées.
3. Désactiver un élève (`is_active = 0`) l'empêche de se connecter immédiatement.
4. Les statistiques sont calculées **en temps réel** via requêtes SQL (pas de cache v1).
5. Un admin ne peut pas se supprimer lui-même.
6. Le **mode maintenance** bloque le front-office mais laisse le back-office accessible.

---

## 11. Ordre de développement recommandé

**Phase 1** :
1. `/admin/login.php` (auth admin)
2. Layout commun admin (sidebar CSS + JS)
3. `/admin/dashboard.php` (KPIs uniquement, sans graphiques)
4. `/admin/ressources.php` (upload + liste)
5. `/admin/users.php` (liste + activer/désactiver)

**Phase 2** :
6. Graphiques Chart.js sur dashboard
7. `/admin/series.php` (gestion complète)
8. `/admin/stats.php`
9. `/admin/notifications.php`
10. `/admin/settings.php`

---

*PRD-05 Back-Office Admin — Connect'Academia v1.0*
