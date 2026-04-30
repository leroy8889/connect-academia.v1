# 5. Fonctionnalités Détaillées — Front-Office

## 5.1 Authentification & Gestion de Compte

### 5.1.1 Inscription Élève

L'élève accède à la page d'inscription et saisit les informations suivantes :

- **Nom & Prénom** (champs texte obligatoires)
- **Adresse email** institutionnelle (validée par regex + vérification unicité)
- **Mot de passe** (minimum 8 caractères, confirmation requise, indicateur de force)
- **Photo de profil** (upload JPG/PNG, max 5 Mo, recadrage automatique en cercle 400×400px)
- **Classe** (sélecteur : 6ème, 5ème, 4ème, 3ème, Seconde, Première, Terminale + option personnalisée)
- **Niveau scolaire** (Collège / Lycée / Supérieur)

Après soumission, un email de vérification est envoyé. L'accès est conditionné à la validation de l'email. L'administrateur peut également activer/désactiver les comptes manuellement.

### 5.1.2 Inscription Enseignant

L'enseignant suit un flux similaire avec des champs spécifiques :

- **Nom & Prénom** (obligatoires)
- **Email professionnel** (format vérifié)
- **Mot de passe** sécurisé
- **Photo de profil**
- **Matière(s) enseignée(s)** (sélection multiple : Maths, Physique-Chimie, SVT, Français, Histoire-Géo, Anglais, Espagnol, Philosophie, NSI, EPS, Arts...)
- **Établissement d'appartenance** (texte libre ou liste)
- Badge **'Enseignant'** automatiquement affiché sur son profil

> Le compte enseignant peut nécessiter une validation manuelle par l'administrateur pour éviter les usurpations d'identité.

### 5.1.3 Connexion

- Formulaire email + mot de passe
- Option **"Se souvenir de moi"** (cookie persistant 30 jours)
- Lien **"Mot de passe oublié"** → réinitialisation par email (token unique, expiration 1h)
- Protection contre le brute-force (rate limiting : max 5 tentatives / 15 min par IP)
- Redirection post-connexion vers le feed principal

---

## 5.2 Feed Principal (Fil d'Actualité)

### 5.2.1 Structure et Affichage

Le feed est l'écran principal de l'application. Il affiche les publications sous forme de cartes empilées verticalement, inspiré d'Instagram :

- Scroll vertical infini avec **lazy loading** (chargement de 10 posts par lot)
- **Actualisation automatique** toutes les 30 secondes (polling AJAX) avec notification de nouveaux posts
- Indicateur de nouveaux posts en haut du feed (*"X nouvelles publications"*)
- **Filtre** par matière, par type de publication, par classe
- **Barre de recherche** globale (recherche dans le contenu des posts et les noms d'utilisateurs)

### 5.2.2 Carte de Publication

Chaque publication est présentée dans une carte "liquid glass" avec les éléments suivants :

- **En-tête** : Photo de profil (cercle, 48px) + Nom complet + Badge rôle (Élève/Enseignant) + Classe ou Matière + Date relative (*"il y a 2h"*)
- **Contenu textuel** : Rendu du texte avec support des mentions `@utilisateur` et `#hashtags`
- **Image** : Affichage responsive si une image est jointe (aspect-ratio préservé, max-height 500px, click pour agrandir)
- **Tags** : Chips colorées pour la matière et la classe cible
- **Badge de statut** : *'Question résolue ✓'* pour les questions marquées comme résolues
- **Zone d'interactions** : Bouton ❤️ Like + compteur | Bouton 💬 Commentaires + compteur | Bouton 🔗 Partager | Bouton ⚠️ Signaler

### 5.2.3 Swipe vers la droite — Panneau de Commentaires

L'interaction clé de l'application. Inspirée des apps mobiles modernes :

- **Sur mobile** : swipe vers la droite sur la carte → ouverture d'un panneau latéral (bottom sheet ou side panel) affichant les commentaires
- **Sur desktop** : click sur l'icône commentaire → panneau glissant depuis la droite (drawer) sans quitter le feed
- Le panneau affiche : liste des commentaires (photo + nom + texte + date + like du commentaire) + champ de saisie avec bouton Envoyer
- Support des **réponses imbriquées** (replies) avec indentation visuelle
- Bouton **'Meilleure réponse'** visible pour l'auteur de la question et les enseignants
- Animation fluide avec effet spring/ease-out

### 5.2.4 Interactions

- **Like / Unlike** : Animation de cœur (scale + bounce + changement de couleur vers `#8B52FA`). Requête AJAX sans rechargement de page.
- **Commentaire** : Soumission AJAX, ajout instantané dans le panneau sans rechargement
- **Mention @username** : Autocomplétion des utilisateurs lors de la saisie
- **#Hashtag** : Cliquable → filtre le feed par ce hashtag
- **Signalement** : Modal de confirmation avec raisons prédéfinies (Contenu inapproprié, Spam, Harcèlement, Autre)

---

## 5.3 Création de Publication

### 5.3.1 Bouton de Création

Bouton flottant circulaire **(+)** en bas à droite de l'écran (FAB — Floating Action Button), couleur `#8B52FA`, toujours visible lors du scroll.

### 5.3.2 Modal / Page de Création

- **Type de publication** : Question (?), Ressource (📚), Partage (🔗), Annonce (uniquement Enseignants & Admins)
- **Champ texte riche** (contenteditable avec support mention `@utilisateur` et `#hashtags`)
- **Upload d'image** : Drag & drop ou click. Preview instantanée. Recadrage optionnel.
- **Tags** : Sélection de matière(s) et classe(s) cible(s)
- **Aperçu** de la publication avant envoi
- Barre de progression lors de l'upload
- **Validation** : Contenu non vide, image < 10 Mo, formats acceptés JPG/PNG/GIF/WEBP

---

## 5.4 Page Profil Personnel

### 5.4.1 En-tête du Profil

- Photo de profil grande (120px, encerclement avec bordure `#8B52FA`)
- Nom complet + Badge rôle
- Bio courte (max 160 caractères)
- Informations : Classe (élève) ou Matière(s) (enseignant)
- Statistiques : **X Publications | X Followers | X Following**
- Bouton **'Modifier le profil'** (si profil personnel) ou **'Suivre / Abonné'** (si profil d'un autre)

### 5.4.2 Onglets du Profil

- **Publications** : Grille des posts de l'utilisateur (style galerie Instagram)
- **Questions résolues** : Posts marqués comme résolus
- **Réponses** : Commentaires posés par l'utilisateur
- **Sauvegardés** : Posts mis en favoris/bookmarkés

### 5.4.3 Modification du Profil

- Changement de photo de profil (recadrage automatique)
- Modification bio, nom, prénom
- Mise à jour de la classe (élève) ou matière(s) (enseignant)
- Changement d'email (confirmation par email)
- Changement de mot de passe (ancien MDP requis)

---

## 5.5 Système de Notifications

- Centre de notifications accessible via icône **cloche** dans la navbar
- **Badge rouge** indiquant le nombre de notifications non lues
- **Types** : Like sur ma publication | Commentaire sur ma publication | Réponse à mon commentaire | Mention @moi | Nouvelle annonce de l'établissement | Publication de quelqu'un que je suis
- Actualisation toutes les 60 secondes via polling AJAX
- Marquage individuel ou global comme **'lu'**
- Clic sur notification → redirection vers la publication concernée

---

## 5.6 Recherche & Découverte

- Barre de **recherche globale** (posts, utilisateurs, hashtags, matières)
- **Page Exploration** : Trending posts, Top questions de la semaine, Enseignants les plus actifs
- **Filtres avancés** : Par matière, par classe, par date, par type (questions/ressources/annonces)
- **Suggestions** d'utilisateurs à suivre (même classe, même matière)

---

## 5.7 Messagerie Directe *(Optionnel — V2)*

Pour éviter le bruit des messages de masse, la messagerie directe est limitée :

- **Élève → Enseignant** : Autorisé (pour questions privées liées aux cours)
- **Élève → Élève** : Limité aux personnes se suivant mutuellement
- Interface simple : liste de conversations + fil de messages + pièce jointe
- Pas de groupes (pour préserver la structure du feed)

---
