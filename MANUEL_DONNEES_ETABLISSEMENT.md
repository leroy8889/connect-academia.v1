# Cahier de collecte — Données pour la création d'un site établissement partenaire

> **À quoi sert ce document**
> Il liste **toutes les informations** que le Super Administrateur saisit dans le formulaire « Ajouter un établissement » de Connect'Academia. Chaque information saisie construit directement la **page publique** (site web) de l'établissement partenaire.
> Ce fichier sert de base pour rédiger le **manuel / fiche de collecte** à remettre à chaque établissement partenaire afin qu'il fournisse des données propres, complètes et au bon format.
>
> **Légende**
> - 🔴 **Obligatoire** — la création échoue sans cette information.
> - ⚪ **Optionnel** — améliore la page publique mais non bloquant.
> - `nom_technique` = nom du champ dans le formulaire (utile côté dev).

---

## Vue d'ensemble — 10 sections du formulaire

| # | Section | Rôle sur le site public | Champs obligatoires |
|---|---------|-------------------------|---------------------|
| 1 | Identité | En-tête, nom, couleur de marque | Nom, Type, Ville |
| 2 | Médias | Logo, photos campus, vidéo | Logo |
| 3 | Accroche | Bandeau hero (haut de page) | — |
| 4 | Présentation | Section « À propos » | — |
| 5 | Atouts, badges & stats | Éléments de confiance | — |
| 6 | Contact | Coordonnées + carte | Email |
| 7 | Tarifs & paiement | Grille tarifaire | — |
| 8 | Encaissement | Compte Mobile Money (reversement) | — |
| 9 | Formations | Catalogue des filières | ≥ 1 formation |
| 10 | Gestionnaire | Compte de l'établissement | Prénom, Nom, Email |

---

## §1 — Identité

| Information | Technique | Type | Obligatoire | Contraintes / Format | Exemple |
|-------------|-----------|------|:-----------:|----------------------|---------|
| Nom officiel | `nom` | Texte | 🔴 | Nom complet officiel | `BGFI Business School` |
| Type d'établissement | `type` | Choix | 🔴 | **Université**, **Grande école** ou **Institut** (une seule valeur) | `Grande école` |
| Ville | `ville` | Texte | 🔴 | Ville du campus principal | `Libreville` |
| Couleur principale de marque | `couleur_principale` | Couleur | ⚪ | Format hexadécimal `#RRGGBB`. Défaut : `#1F4E79`. Sert de couleur d'accent sur tout le site (`--brand`) | `#1F4E79` |
| Préinscription activée | `preinscription_active` | Oui/Non | ⚪ | Activée par défaut | `Oui` |
| Génère un récépissé PDF | `genere_recepisse` | Oui/Non | ⚪ | Activé par défaut | `Oui` |

> ℹ️ **Statut initial** (`statut` : Brouillon / Actif / Inactif) est réglé par l'administrateur, **pas** demandé au partenaire. Défaut : Brouillon.

**À demander au partenaire :** nom exact, nature de l'établissement, ville, et **charte couleur** (code hexadécimal du logo/de la marque).

---

## §2 — Médias

| Information | Technique | Type | Obligatoire | Contraintes | 
|-------------|-----------|------|:-----------:|-------------|
| Logo | `logo` | Fichier image | 🔴 | Formats : **JPG, PNG, WEBP**. Taille max : **10 Mo**. Fond transparent (PNG) recommandé |
| Photos du campus | `images_campus[]` | Fichiers image (multiple) | ⚪ | **Jusqu'à 10 photos**. Formats JPG/PNG/WEBP. Max **10 Mo par photo** |
| Vidéo de présentation | `video` | Fichier vidéo | ⚪ | Max **500 Mo**. Formats : **MP4, WebM, OGG, MOV, AVI, MKV, WMV, MPEG, 3GP** |

> ⚠️ Le **vrai type de fichier est vérifié côté serveur** (pas seulement l'extension). Un fichier renommé sera refusé.

**À demander au partenaire :** logo haute qualité (idéalement PNG transparent), 3 à 10 photos représentatives du campus/des locaux, et une courte vidéo institutionnelle si disponible.

---

## §3 — Accroche (bandeau hero) — ⚪ Optionnel

Contenu du **grand bandeau en haut** de la page publique.

| Information | Technique | Type | Contraintes | Exemple |
|-------------|-----------|------|-------------|---------|
| Label campagne | `label_campagne` | Texte court | Petit badge au-dessus du titre | `Inscriptions 2025-2026 ouvertes` |
| Date limite dossier | `date_limite_dossier` | Date | Format date | `2026-09-30` |
| Titre principal du hero | `titre_accroche` | Texte | Accroche forte | `Forge ton avenir à l'ESAG-NDE` |
| Sous-titre | `sous_titre` | Texte long | Phrase sous le titre | `Formations d'excellence en gestion et commerce` |

**À demander au partenaire :** un slogan / phrase d'accroche, la date de clôture des candidatures, et le message de campagne en cours.

---

## §4 — Présentation (section « À propos ») — ⚪ Optionnel

| Information | Technique | Type | Contraintes | Exemple |
|-------------|-----------|------|-------------|---------|
| Description de l'établissement | `description` | Texte long | Histoire, mission, valeurs | `Fondée en 1998, notre école…` |
| Citation | `citation_texte` | Texte long | Phrase de la direction | `« L'excellence est notre seule ambition. »` |
| Auteur de la citation | `citation_auteur` | Texte | Nom de la personne | `Pr. Jean Dupont` |
| Fonction de l'auteur | `citation_fonction` | Texte | Titre / poste | `Directeur général` |

**À demander au partenaire :** un paragraphe de présentation, et éventuellement une citation officielle du dirigeant (texte + nom + fonction).

---

## §5 — Atouts, badges & statistiques — ⚪ Optionnel

Éléments de confiance affichés sur la page. **Chaque élément est une entrée à ajouter une par une.**

| Information | Technique | Type | Contraintes | Exemple |
|-------------|-----------|------|-------------|---------|
| Badges de confiance | `badges[]` | Liste | Max **80 caractères** par badge | `Accrédité CAMES` |
| Points forts (atouts) | `atouts[]` | Liste | Max **120 caractères** par atout | `Enseignants qualifiés à 90%` |
| Statistiques clés | `stat_label[]` + `stat_valeur[]` | Liste de paires | Un **libellé** + une **valeur** par ligne | Libellé : `Taux d'insertion` — Valeur : `87%` |

**À demander au partenaire :** accréditations/labels officiels, 3 à 5 points forts, et quelques chiffres marquants (taux d'insertion, nb d'étudiants, années d'existence, partenariats…).

---

## §6 — Contact

| Information | Technique | Type | Obligatoire | Contraintes | Exemple |
|-------------|-----------|------|:-----------:|-------------|---------|
| Adresse physique | `adresse` | Texte | ⚪ | Adresse postale complète | `BP 2512, Libreville, Gabon` |
| Carte Google Maps | `map_embed` | Code intégration | ⚪ | **Code « Intégrer une carte »** de Google Maps. Doit être un lien `https://www.google.com/maps/embed…`. Seule l'URL est conservée (sécurité) | `<iframe src="https://www.google.com/maps/embed…">` |
| Email de contact | `email_contact` | Email | 🔴 | Adresse email valide | `contact@ecole.ga` |
| Téléphone | `telephone` | Téléphone | ⚪ | Avec indicatif pays | `+241 01 23 45 67` |
| WhatsApp | `whatsapp` | Téléphone | ⚪ | Avec indicatif pays | `+241 06 XX XX XX` |

> 🗺️ **Obtenir le code carte :** Google Maps → chercher l'établissement → **Partager** → onglet **« Intégrer une carte »** → **Copier le HTML** → coller dans le champ.

**À demander au partenaire :** adresse complète, email de contact officiel, numéros téléphone + WhatsApp, et le lien de localisation Google Maps.

---

## §7 — Tarifs & modes de paiement

Montants en **FCFA**. `0` = gratuit. Défaut de chaque prix : `0`.

| Information | Technique | Type | Contraintes | Exemple |
|-------------|-----------|------|-------------|---------|
| Prix préinscription | `prix_preinscription` | Nombre (FCFA) | Entier ≥ 0 | `0` |
| Prix inscription | `prix_inscription` | Nombre (FCFA) | Entier ≥ 0 | `150000` |
| Frais de dossier | `frais_dossier` | Nombre (FCFA) | Entier ≥ 0 | `5000` |
| Modes de paiement acceptés | `mode_paiement[]` | Liste | Un mode par entrée | `Airtel Money`, `Orange Money`, `Virement` |

> ℹ️ Les montants tolèrent les espaces/séparateurs (`250 000`, `250.000` → `250000`).

**À demander au partenaire :** grille tarifaire (préinscription, inscription, frais de dossier) et moyens de paiement acceptés.

---

## §8 — Compte d'encaissement (Mobile Money) — ⚪ Optionnel

Numéro Mobile Money pour le **reversement automatique** des paiements. Activable plus tard.

| Information | Technique | Type | Contraintes | Exemple |
|-------------|-----------|------|-------------|---------|
| Opérateur | `mm_mode` | Choix | **Airtel Money**, **Moov Money** ou **Orange Money** | `Airtel Money` |
| Numéro Mobile Money | `mm_numero` | Téléphone | Numéro du compte de réception | `+241 06 XX XX XX` |
| Titulaire du compte | `mm_titulaire` | Texte | Nom complet du titulaire | `ESAG-NDE SARL` |

> ⚠️ Le compte n'est enregistré que si **opérateur + numéro** sont tous deux renseignés. L'inscription **payante** ne s'active qu'après validation de ce compte (étape ultérieure — reversement : Airtel Money ou Moov Money).

**À demander au partenaire :** opérateur, numéro Mobile Money de réception, et nom du titulaire.

---

## §9 — Formations — 🔴 Au moins une obligatoire

**Section répétable** : ajouter autant de formations que nécessaire. Chaque formation devient une carte du catalogue.

| Information | Technique | Type | Obligatoire | Contraintes | Exemple |
|-------------|-----------|------|:-----------:|-------------|---------|
| Intitulé | `formations[][intitule]` | Texte | 🔴 | Nom de la filière | `Licence en Informatique` |
| Niveau | `formations[][niveau]` | Texte | ⚪ | Niveau visé | `Bac+3` |
| Prix | `formations[][prix]` | Nombre (FCFA) | ⚪ | Entier ≥ 0 | `750000` |
| Durée | `formations[][duree_texte]` | Texte | ⚪ | Durée en clair | `3 ans` |
| Places | `formations[][places_texte]` | Texte | ⚪ | Capacité en clair | `60 places` |
| Icône | `formations[][icone]` | Texte | ⚪ | Nom d'icône Lucide | `laptop-2` |
| Statut | `formations[][statut]` | Choix | ⚪ | **Ouverte** / **Bientôt** / **Fermée** (défaut : Ouverte) | `Ouverte` |

> ℹ️ L'unité de prix par défaut est `FCFA/an`.

**À demander au partenaire :** liste complète des filières avec, pour chacune : intitulé, niveau, prix annuel, durée, nombre de places, et statut d'ouverture.

---

## §10 — Compte gestionnaire — 🔴 Obligatoire

Compte qui permettra à l'établissement de **gérer lui-même** son espace. Les identifiants sont **envoyés par email automatiquement**.

| Information | Technique | Type | Obligatoire | Contraintes |
|-------------|-----------|------|:-----------:|-------------|
| Prénom | `gestionnaire_prenom` | Texte | 🔴 | — |
| Nom | `gestionnaire_nom` | Texte | 🔴 | — |
| Email | `gestionnaire_email` | Email | 🔴 | Email valide et **unique** — pas déjà utilisé par un autre compte de la plateforme |
| Mot de passe | `gestionnaire_password` | Texte | ⚪ | Min **8 caractères**. **Si vide → généré automatiquement** |
| Confirmation mot de passe | `gestionnaire_password_confirm` | Texte | ⚪ (si mot de passe saisi) | Doit être identique au mot de passe |

> 📧 Dans tous les cas, l'email + mot de passe sont envoyés au gestionnaire. Connexion : `/gestionnaire/login`.

**À demander au partenaire :** identité (prénom, nom) et **email professionnel dédié** de la personne référente qui gérera l'espace établissement.

---

## Checklist de collecte à remettre au partenaire

**Identité & marque**
- [ ] Nom officiel complet
- [ ] Type (université / grande école / institut)
- [ ] Ville du campus
- [ ] Code couleur de marque (hexadécimal)

**Visuels**
- [ ] Logo (PNG transparent, < 10 Mo)
- [ ] 3 à 10 photos du campus (< 10 Mo chacune)
- [ ] Vidéo de présentation (optionnelle, < 500 Mo)

**Contenu éditorial**
- [ ] Slogan / titre d'accroche + sous-titre
- [ ] Date limite des candidatures
- [ ] Texte de présentation (histoire, mission)
- [ ] Citation du dirigeant (+ nom + fonction)
- [ ] Accréditations / labels (badges)
- [ ] Points forts (3 à 5)
- [ ] Statistiques clés (chiffres marquants)

**Contact**
- [ ] Adresse physique complète
- [ ] Email de contact officiel
- [ ] Téléphone + WhatsApp
- [ ] Lien Google Maps (code « Intégrer une carte »)

**Tarifs & paiement**
- [ ] Prix préinscription / inscription / frais de dossier
- [ ] Modes de paiement acceptés
- [ ] Compte Mobile Money reversement (opérateur + numéro + titulaire)

**Formations** (pour chacune)
- [ ] Intitulé, niveau, prix, durée, places, statut

**Compte gestionnaire**
- [ ] Prénom, nom et email dédié du référent
