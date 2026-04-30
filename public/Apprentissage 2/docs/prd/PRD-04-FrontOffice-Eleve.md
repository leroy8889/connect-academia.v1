# 🎓 PRD-04 — Front-Office : Espace Élève
## Connect'Academia — Spécifications des Pages Élève

> **Référence** : PRD principal v1.0 — Section 9
> **Usage Cursor** : Développer les pages élève dans l'ordre indiqué (Phase 1 → Phase 2).
> **Design** : Inspiration Coursera — Cartes visuelles, progression visible, mobile-first.

---

## 1. Layout global Front-Office

Toutes les pages protégées (élève connecté) partagent ce layout :

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR (fixe, gauche, 240px)                          │
│  - Logo Connect'Academia                                │
│  - Navigation principale                               │
│  - Avatar + Nom + Série de l'élève                      │
│                    │   ZONE PRINCIPALE (scrollable)     │
│                    │   - Topbar optionnel (breadcrumb)   │
│                    │   - Contenu de la page             │
└─────────────────────────────────────────────────────────┘
```

### Sidebar navigation (front-office)
```
🏠 Tableau de bord      → /dashboard.php
📂 Mes Matières         → /matieres.php
⭐ Mes Favoris          → /favoris.php
📊 Ma Progression       → /progression.php
🔔 Notifications        → /notifications.php  [badge nb non lus]
👤 Mon Profil           → /profil.php
🚪 Déconnexion          → /logout.php
```

---

## 2. Landing Page — `/index.php` (publique)

**Objectif** : Présenter la plateforme, convaincre de s'inscrire.

### Sections (de haut en bas) :
1. **Header** : Logo + boutons "Connexion" et "S'inscrire"
2. **Hero** : Titre accrocheur + illustration + CTA "Commencer à réviser"
   - Titre suggéré : *"Tout ce qu'il faut pour décrocher ton Bac"*
3. **Comment ça marche** : 3 étapes illustrées (S'inscrire → Choisir sa matière → Réviser)
4. **Séries disponibles** : 5 cartes (A1, A2, B, C, D) avec couleur et description
5. **Statistiques** : Compteurs animés (nb cours disponibles, nb élèves inscrits, nb matières)
6. **Footer** : liens utiles, contact

**Comportement** : Si l'élève est déjà connecté (`$_SESSION['user_id']` existe), rediriger vers `/dashboard.php`.

---

## 3. Page Inscription — `/register.php`

**Design** : Formulaire centré, fond clair, logo en haut.

### Champs :
| Champ | Type | Règles |
|---|---|---|
| Prénom | text | Requis, 2–50 chars |
| Nom | text | Requis, 2–50 chars |
| Email | email | Requis, unique en BDD |
| Mot de passe | password | Requis, min 8 chars |
| Confirmer mot de passe | password | Doit correspondre |
| Série | select | Requis, options : A1 / A2 / B / C / D |

### Comportement :
- Validation JS temps réel (inline, sous chaque champ)
- Validation PHP côté serveur
- En cas de succès → session créée + redirection `/dashboard.php`
- Lien "Déjà inscrit ? Se connecter" en bas du formulaire

---

## 4. Page Connexion — `/login.php`

**Design** : Identique à register, épuré.

### Champs :
- Email
- Mot de passe

### Comportement :
- Message d'erreur discret : *"Email ou mot de passe incorrect"*
- Lien *"Mot de passe oublié ?"* (fonctionnalité v2, afficher désactivé en v1)
- Lien *"Pas encore inscrit ? S'inscrire"*
- Si connexion réussie → redirection `/dashboard.php` (ou `?redirect=` si présent dans l'URL)

---

## 5. Dashboard Élève — `/dashboard.php`

### 5.1 Bannière de bienvenue
```
Bonjour [Prénom] 👋
Continuez là où vous vous êtes arrêté
[Badge Terminale D]
```

### 5.2 Cartes KPI (4 cartes en haut)

| Carte | Icône | Données |
|---|---|---|
| Cours consultés | 📚 | X / Y disponibles |
| Temps de révision | ⏱️ | Heures cette semaine |
| Ressources terminées | ✅ | Nb PDFs complétés |
| Matière favorite | 🔥 | Matière la plus consultée |

**Source** : Requêtes SQL sur `progressions` et `sessions_revision` du user connecté.

### 5.3 Panel Séries
- Grille de **5 cartes** (A1, A2, B, C, D)
- La série de l'élève : **bordure violette `#8B52FA`** + badge "Ma série"
- Chaque carte affiche : Nom série, description courte, nb ressources disponibles
- Un élève peut consulter toutes les séries (pas seulement la sienne)
- Clic → `/matieres.php?serie=[nom]`

### 5.4 Section "Reprendre là où vous êtes"
- **3 dernières ressources** avec statut `en_cours`
- Pour chaque ressource :
  - Titre + type badge + matière
  - Barre de progression (% pages vues)
  - Bouton "Continuer" → `/viewer.php?ressource=[id]` (ouverture à `derniere_page`)

### 5.5 Section "Récemment ajoutés"
- Grille de **6 cartes ressources** les plus récentes
- Design Coursera (voir §7 pour spec carte ressource)
- Triées par `created_at DESC`

---

## 6. Page Matières — `/matieres.php?serie=[nom]`

### Header de la page
- Titre : "Matières — Terminale [Série]"
- Badge de la série

### Grille de cartes matières
Chaque carte contient :
- Icône Lucide de la matière
- Nom de la matière
- Nombre de ressources disponibles
- Barre de progression élève dans cette matière (% moyen des ressources)
- Couleur de fond selon la série

**Requête** :
```sql
SELECT m.*, 
       COUNT(r.id) AS nb_ressources,
       COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
FROM matieres m
LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
WHERE m.serie_id = ? AND m.is_active = 1
GROUP BY m.id
ORDER BY m.ordre ASC
```

---

## 7. Page Ressources d'une matière — `/ressources.php?matiere=[id]`

### Header matière
- Titre de la matière + badge série
- Nombre total de ressources
- Grande barre de progression globale dans la matière

### Onglets de filtrage
```
[Tous]  [Cours]  [Travaux Dirigés]  [Anciennes Épreuves]
```

### Carte Ressource (design Coursera)

Chaque carte contient :
| Élément | Détail |
|---|---|
| Icône PDF stylisée | Couleur selon le type |
| Titre | Tronqué à 2 lignes |
| Badge type | `Cours` / `TD` / `Épreuve` (couleurs différentes) |
| Chapitre | Si renseigné |
| Année | Pour les anciennes épreuves uniquement |
| Barre de progression | `0%` / `en cours [X%]` / `✅ Terminé` |
| Icône cœur | Toggle favori (AJAX, sans rechargement) |
| Bouton "Consulter" | → `/viewer.php?ressource=[id]` |

---

## 8. Lecteur PDF — `/viewer.php?ressource=[id]`

### Layout
```
┌─────────────────────────────────────────────────────────┐
│  Breadcrumb : Dashboard > Matière > Titre ressource     │
├──────────────────┬──────────────────────────────────────┤
│  PANNEAU GAUCHE  │         PDF.js VIEWER                │
│  (30% largeur)   │         (70% largeur)                │
│                  │                                      │
│  - Titre         │  ◀ Page X / Y ▶   🔍 Zoom           │
│  - Type badge    │  ⛶ Plein écran   ⬇ Télécharger      │
│  - Progression % │                                      │
│  - ⏱️ Chrono     │         [PDF rendu par PDF.js]       │
│  - ✅ Terminé    │                                      │
│  - ⭐ Favori     │                                      │
│  - Autres        │                                      │
│    ressources    │                                      │
└──────────────────┴──────────────────────────────────────┘
```

### Fonctionnalités PDF.js
- Navigation page par page (précédent / suivant)
- Zoom in / out (molette + boutons)
- Mode plein écran
- Téléchargement PDF (affiché uniquement si `ALLOW_PDF_DOWNLOAD = true`)
- **Ouverture à `derniere_page`** si l'élève reprend une ressource

### Système de tracking (voir PRD-07 pour détail complet)
- Démarrage automatique du chronomètre à l'ouverture
- Sauvegarde AJAX toutes les 30 secondes : progression %, dernière page, temps passé
- Sauvegarde à `beforeunload`

---

## 9. Page Ma Progression — `/progression.php`

### Récapitulatif global
- **Grand cercle de progression** : % toutes matières confondues (Donut Chart.js)
- Temps total de révision cette semaine
- Niveau et badges gamification obtenus

### Progression par matière
Tableau ou liste avec pour chaque matière :
- Nom + icône
- Barre de progression %
- Nb ressources : terminées / en cours / non commencées

### Historique de révision
- Timeline chronologique : date, durée, ressource consultée
- Limité aux 20 dernières sessions

### Badges & Récompenses

| Badge | Icône | Condition de déblocage |
|---|---|---|
| Premier pas | 🥇 | Première ressource consultée |
| Lecteur assidu | 📖 | 5 ressources terminées |
| Marathonien | ⏰ | 10 heures de révision cumulées |
| Série complète | 🎯 | Toutes les matières de sa série commencées |
| Champion du Bac | 🏆 | 80% de progression sur sa série |

---

## 10. Page Mon Profil — `/profil.php`

### Champs modifiables
- Prénom / Nom
- Email (avec vérification unicité)
- Série (changement possible)
- Avatar (upload image, formats : JPG/PNG, max 2Mo)
- Changer le mot de passe (champ ancien MDP + nouveau + confirmation)

### Statistiques (lecture seule)
- Date d'inscription
- Nombre de ressources consultées
- Temps total de révision

---

## 11. Page Favoris — `/favoris.php`

- Grille de toutes les ressources marquées comme favorites
- Même design que les cartes de la page ressources (§7)
- Bouton "Retirer des favoris" (toggle AJAX)
- Message vide si aucun favori : *"Vous n'avez pas encore de favoris. Commencez à réviser !"*

---

## 12. Page Notifications — `/notifications.php`

- Liste chronologique des notifications reçues
- Chaque notification : titre, message, type (icône colorée), date relative
- Marquer tout comme lu (bouton en haut)
- Badge compteur dans la sidebar (nb non lues), mis à jour via AJAX au chargement

---

## 13. Responsive Design

| Breakpoint | Comportement |
|---|---|
| Mobile < 768px | Sidebar cachée → hamburger menu, layout 1 colonne, cards empilées |
| Tablet 768–1024px | Sidebar réduite (icônes seulement), 2 colonnes |
| Desktop > 1024px | Sidebar complète, layout standard, 3–4 colonnes cards |

---

## 14. Pages d'erreur

- **404** : Page non trouvée, style Connect'Academia, lien retour dashboard
- **403** : Accès non autorisé, message discret

---

## 15. Ordre de développement recommandé (Phase 1)

1. `/includes/auth_check.php` (middleware)
2. Layout commun (sidebar + CSS de base)
3. `login.php` + `register.php`
4. `dashboard.php` (sans les sections dynamiques)
5. `matieres.php`
6. `ressources.php`
7. `viewer.php` (PDF.js + timer basique)
8. `progression.php` (version simplifiée)
9. `profil.php`
10. `favoris.php` + `notifications.php` (Phase 2)

---

*PRD-04 Front-Office Élève — Connect'Academia v1.0*
