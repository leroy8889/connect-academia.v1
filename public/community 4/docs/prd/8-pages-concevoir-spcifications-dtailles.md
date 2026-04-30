# 8. Pages à Concevoir — Spécifications Détaillées

## 8.1 Page Inscription / Connexion

**Layout :**
- Split-screen sur desktop : gauche (60%) formulaire, droite (40%) illustration animée ou gradient
- Mobile : plein écran avec scroll vertical
- Toggle Inscription / Connexion via onglets ou lien en bas de formulaire
- Sélecteur de rôle visible sur la page d'inscription : *'Je suis élève'* vs *'Je suis enseignant'* (cartes cliquables avec icônes)

**Éléments :**
- Logo StudyLink centré en haut
- Formulaire avec champs validés en temps réel (border vert/rouge selon validité)
- Upload photo de profil avec prévisualisation circulaire
- Conditions d'utilisation (checkbox obligatoire)
- CTA **'Créer mon compte'** bouton primaire `#8B52FA`
- Lien retour vers connexion

---

## 8.2 Page d'Accueil — Fil d'Actualité (Feed)

**Layout 3 colonnes (Desktop) :**
- **Colonne gauche** (250px) : Navigation principale + infos profil rapide + raccourcis classes/matières
- **Colonne centrale** (600px max-width) : Feed des publications
- **Colonne droite** (300px) : Suggestions de personnes à suivre + Top questions de la semaine + Hashtags tendances

**Layout Mobile (< 768px) :**
- Colonne unique full-width
- Navbar bottom fixe (Home, Explore, +, Notifs, Profil)
- FAB (+) central dans la bottom bar

**Éléments du Feed :**
- Barre de filtre horizontal sticky : *Tout | Questions | Ressources | Annonces | Mes Classes*
- Cartes de publication (cf. section 5.2.2)
- Skeleton loading pendant le chargement (animations de pulse)
- Message *'Aucune publication'* si feed vide avec CTA créer première publication
- Bouton *'Charger plus'* ou infinite scroll

---

## 8.3 Page Profil Personnel

**Header du Profil :**
- Photo de profil 120px avec bordure gradient violet
- Bouton 'Modifier' (icône crayon) en haut à droite
- Infos condensées + stats sur une ligne
- Bouton 'Créer une publication' visible

**Grille des Publications :**
- Vue grille 3 colonnes (comme Instagram) avec hover preview
- Switch vers vue liste détaillée
- Onglets filtrables (Posts | Résolus | Commentaires | Sauvegardés)

---

## 8.4 Pages Back-Office

- **Dashboard** : Grille de KPI cards + graphiques + liste des activités récentes
- **Utilisateurs** : Table + filters sidebar + modale détail utilisateur
- **Publications** : Table + preview modale + actions en lot
- **Signalements** : Vue Kanban avec drag & drop
- **Paramètres** : Formulaires sectionnés avec sauvegarde auto

---
