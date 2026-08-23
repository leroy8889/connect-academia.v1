# Plan d'action — Module « Orientation » de Connect'Academia

**Préinscriptions, inscriptions et paiements en ligne dans les universités et grandes écoles du Gabon**

*Document d'architecture et de mise en œuvre — rédigé comme un cahier de route pour un développeur seul ou une petite équipe.*

> **Version 8 (intégration Backblaze B2 consolidée)** — Le contenu opérationnel du guide d'intégration B2 est intégré à la section 11 : état de la mise en place (compte, bucket et clé d'application déjà créés), configuration `.env`, installation du SDK, rôle des fichiers de code, test de vérification, checklist sécurité et dépannage. Les tables `documents_orientation` et `journal_acces_documents` restent définies au schéma (section 2).
>
> **Version 7 (Option A : reversement automatique)** — Un compte plateforme encaisse, puis reverse automatiquement au numéro mobile money de chaque école. Tables `etablissement_paiement` (revue) et `reversements`.
>
> **Version 6 (paiements directs)** — Première version de l'architecture de paiement (remplacée par la v7).
>
> **Version 5 (saisie au guichet & origine)** — Colonnes `origine` / `cree_par_user_id`, champ `mode` sur les paiements.
>
> **Version 4 (préinscription & formulaires configurables)** — Tables `champs_formulaire`, `recepisses`, section 7 restructurée.
>
> **Version 3 (assistant d'inscription)** — Tables `dossiers_inscription`, `pieces_justificatives_requises`.
>
> **Version 2 (mise à jour template)** — Gabarit réel : branchement du template, vigilance `image-slot.js`.

---

## 0. Le principe fondamental à comprendre AVANT de coder

Tu as eu une excellente intuition : *« aller coder à chaque fois pour insérer une école, ce sera pénible et source de vulnérabilités »*. C'est exactement le bon raisonnement. Voici la règle d'or que tout architecte applique ici :

> **On ne crée jamais un site par établissement. On crée UN SEUL site, qui change d'apparence et de contenu selon l'établissement demandé dans l'URL.**

Quand tu dis « le site de l'établissement est automatiquement créé », cela ne veut **pas** dire qu'on génère des fichiers HTML/PHP sur le serveur. Cela veut dire qu'on **ajoute une ligne dans la base de données**, et que le code, en lisant cette ligne, **affiche** un site personnalisé à la volée.

C'est ce qu'on appelle une architecture **multi-tenant** (multi-locataire) : un seul immeuble (ton code), plusieurs appartements (les établissements), chacun avec sa déco (logo, couleurs, prix) mais la même structure.

**Ton template confirme déjà ce principe.** Dans `site.css`, toute la palette d'un établissement est dérivée d'**une seule variable** :

```css
:root { --brand: #1F4E79; }   /* ← on change CETTE valeur par établissement */
```

Grâce à `color-mix`, ton CSS calcule automatiquement les déclinaisons (`--brand-deep`, `--brand-tint`, `--brand-line`, `--brand-glow`) à partir de cette couleur. Autrement dit : injecter la couleur de l'école dans cette ligne suffit à reteinter tout le site. C'est l'illustration parfaite du multi-tenant piloté par les données.

### Pourquoi c'est la bonne approche

| Approche naïve (à éviter) | Approche multi-tenant (la bonne) |
|---|---|
| Un dossier de fichiers par école | Une ligne en base de données par école |
| 40 écoles = 40 copies du code à maintenir | 40 écoles = 1 code unique |
| Une faille = à corriger 40 fois | Une faille = à corriger 1 fois |
| Ajouter une école = coder | Ajouter une école = remplir un formulaire |
| Risque d'incohérence énorme | Cohérence garantie |

### Comment ça marche concrètement (le routage)

Chaque établissement est identifié par un **slug** : un nom court et unique dans l'URL.

```
connectacademia.io/orientation                          → la marketplace (liste des écoles)
connectacademia.io/orientation/bgfi-business-school     → le "site" de BBS
connectacademia.io/orientation/universite-omar-bongo    → le "site" de l'UOB
```

Le même fichier PHP reçoit le slug, va chercher en base l'établissement correspondant, et affiche **le même gabarit (ton template)** rempli avec les données de cette école précise. Le site existe à la seconde où la ligne est créée en base, sans générer aucun fichier.

---

## 1. Architecture générale du module

```
┌─────────────────────────────────────────────────────────────────┐
│                      CONNECT'ACADEMIA                            │
│                     Module ORIENTATION                          │
└─────────────────────────────────────────────────────────────────┘

   BACK-OFFICE                FRONT-OFFICE              STOCKAGE
   (Super-Admin)             (Élèves + public)
   ┌──────────────┐          ┌──────────────┐         ┌──────────┐
   │ Insérer un   │          │ Marketplace  │         │ Backblaze│
   │ établissement│─────┐    │ des écoles   │         │    B2    │
   │ (formulaire) │     │    │              │         │          │
   │              │     │    │ Site école   │◀───┐    │ logos    │
   │ Voir toutes  │     │    │ (TON template│    │    │ images   │
   │ les écoles   │     ▼    │  unique)     │    │    │ bulletins│
   └──────────────┘  ┌─────────────┐        │    │    │ reçus    │
                     │ BASE MySQL  │        │    │    └────▲─────┘
   TABLEAU DE BORD   │             │        │    │         │
   ÉTABLISSEMENT     │ etablisse-  │◀───────┘    │         │
   ┌──────────────┐  │ ments       │             │         │
   │ Visites      │  │ formations  │   Préinscription /     │
   │ Préinscript. │◀─│ actualites  │   Inscription +        │
   │ Inscriptions │  │ preinscrip. │   Paiement ────────────┘
   │ Reçus        │  │ inscriptions│
   │ Formulaires  │  │ paiements   │   ESPACE ÉLÈVE
   │ Paiements    │  │ documents   │   ┌──────────────┐
   └──────────────┘  │ visites     │   │ Mes préinscr.│
                     │ notifs      │   │ Mes inscript.│
                     └─────────────┘   │ Mes reçus    │
                                       │ Mes paiements│
                                       │ Notifications│
                                       └──────────────┘
```

Trois acteurs, trois espaces, **une seule base de données** qui les relie : le Super-Admin (toi), l'Établissement (gestionnaire), l'Élève.

---

## 2. La base de données — le cœur du système

C'est la fondation. **Version enrichie** : le schéma ci-dessous couvre désormais tous les champs réellement affichés dans ton template.

### 2.1 Table `etablissements`

Le « profil » de chaque école. C'est cette ligne qui fait exister le site. Les colonnes sont regroupées par zone du template.

**Identité & apparence**

| Colonne | Type | Zone du template |
|---|---|---|
| `id` | INT, PK | — |
| `slug` | VARCHAR(120), UNIQUE | URL |
| `nom` | VARCHAR(200) | `#estName`, footer |
| `type` | ENUM(`universite`,`grande_ecole`,`institut`) | `#estSub` |
| `ville` | VARCHAR(100) | `#estSub`, contact |
| `logo_cle` | VARCHAR(255) | logo header / hero / footer (B2) |
| `couleur_principale` | VARCHAR(7) | injectée dans `--brand` |
| `statut` | ENUM(`actif`,`inactif`,`brouillon`) | visibilité |

**Hero (bandeau d'accueil)**

| Colonne | Type | Zone du template |
|---|---|---|
| `label_campagne` | VARCHAR(160) | eyebrow « Candidatures 2026–2027 ouvertes » |
| `titre_accroche` | VARCHAR(255) | `<h1>` du hero |
| `sous_titre` | TEXT | `.hero-sub` |
| `date_limite_dossier` | DATE | annotation `.float-deadline` |

**Présentation (section « Qui sommes-nous »)**

| Colonne | Type | Zone du template |
|---|---|---|
| `description` | TEXT | paragraphes `.about-body` |
| `video_url` | VARCHAR(255) | section vidéo (embed YouTube) |
| `citation_texte` | TEXT | `.about-quote p` |
| `citation_auteur` | VARCHAR(150) | `.who strong` |
| `citation_fonction` | VARCHAR(150) | `.who span` |

**Contact & paiement**

| Colonne | Type | Zone du template |
|---|---|---|
| `adresse` | VARCHAR(255) | bloc contact |
| `latitude` | DECIMAL(10,7) | carte |
| `longitude` | DECIMAL(10,7) | carte |
| `email_contact` | VARCHAR(150) | contact |
| `telephone` | VARCHAR(30) | contact |
| `whatsapp` | VARCHAR(30) | contact |
| `prix_preinscription` | INT | tarifs (XAF, 0 = gratuit) |
| `prix_inscription` | INT | tarifs (XAF) |
| `frais_dossier` | INT | tarifs (XAF) |
| `infos_paiement` | JSON | modes de paiement (IBAN, mobile money…) |

**Listes flexibles (stockées en JSON pour éviter des tables de trop)**

| Colonne | Type | Zone du template |
|---|---|---|
| `badges_confiance` | JSON | la « trust row » (Agréée État, CAMES…) — tableau de `{icone, label}` |
| `atouts` | JSON | `.about-list` — tableau de phrases |
| `statistiques` | JSON | `.about-stats` — tableau de `{valeur, label}` (ex. `2003`/« Année de création ») |
| `images_cles` | JSON | photos campus / about / vidéo (chemins B2) |
| `date_creation` | DATETIME | — |

> **Astuce d'architecte** : les vraies tables (relationnelles) servent aux données qu'on filtre, trie ou compte (formations, paiements…). Les listes purement d'affichage (badges, atouts, stats) vont en **JSON** : moins de tables, plus simple à éditer depuis le formulaire admin.

### 2.2 Table `formations` (alimente la section « Filières »)

Chaque carte `.filiere` du template = une ligne ici.

| Colonne | Type | Zone du template |
|---|---|---|
| `id` | INT, PK | — |
| `etablissement_id` | INT, FK | — |
| `intitule` | VARCHAR(200) | `.filiere h3` |
| `niveau` | VARCHAR(60) | `.filiere-niveau` (ex. « Licence · Bac+3 ») |
| `icone` | VARCHAR(40) | choix d'icône `.filiere-ic` |
| `duree_texte` | VARCHAR(80) | 1re ligne `.filiere-meta` |
| `places_texte` | VARCHAR(80) | 2e ligne `.filiere-meta` |
| `prix` | INT | `.filiere-price b` |
| `prix_unite` | VARCHAR(20) | « FCFA/an » |
| `statut` | ENUM(`ouverte`,`bientot`,`fermee`) | `.filiere-statut` |
| `ordre` | INT | ordre d'affichage |

### 2.3 Table `actualites` (alimente la section « Blog ») — *optionnelle*

| Colonne | Type | Zone du template |
|---|---|---|
| `id` | INT, PK | — |
| `etablissement_id` | INT, FK | — |
| `titre` | VARCHAR(200) | `.post h3` |
| `categorie` | VARCHAR(60) | `.post-cat` |
| `date_publication` | DATE | `.post-date` |
| `image_cle` | VARCHAR(255) | `.post-media` (B2) |
| `extrait` | TEXT | `.post p` |
| `contenu` | LONGTEXT | page article |
| `a_la_une` | TINYINT | carte `.featured` |
| `statut` | ENUM(`publie`,`brouillon`) | visibilité |

> Si le blog n'est pas prioritaire pour août 2026, cette table peut attendre une phase ultérieure : le template affichera simplement la section vide ou masquée.

### 2.4 Table `dossiers_inscription` (alimente l'assistant d'inscription) — NOUVEAU

Chaque candidature remplie via l'assistant en 3 étapes crée un dossier ici. Il porte les informations personnelles (étape 1) et le choix de filière (étape 2).

| Colonne | Type | Étape / zone |
|---|---|---|
| `id` | INT, PK | — |
| `eleve_id` | INT, FK | candidat connecté |
| `etablissement_id` | INT, FK | établissement visé |
| `formation_id` | INT, FK (nullable) | choisie à l'étape 2 |
| `filiere_visee` | ENUM(`socle_l1_l2`,`licence_l3`,`master_m1_m2`,`certification`) | étape 2 |
| `niveau_entree` | VARCHAR(60) | étape 2 (ex. « Licence 3 ») |
| `nom_complet` | VARCHAR(200) | étape 1 |
| `genre` | ENUM(`homme`,`femme`,`autre`) | étape 1 |
| `date_naissance` | DATE | étape 1 |
| `lieu_naissance` | VARCHAR(120) | étape 1 |
| `nationalite` | VARCHAR(80) | étape 1 |
| `email` | VARCHAR(150) | étape 1 |
| `indicatif_tel` | VARCHAR(8) | étape 1 (ex. « +241 ») |
| `telephone` | VARCHAR(30) | étape 1 |
| `adresse` | VARCHAR(255) | étape 1 |
| `ville` | VARCHAR(120) | étape 1 |
| `pays_provenance` | VARCHAR(80) | étape 1 |
| `dernier_etablissement` | VARCHAR(200) | étape 1 |
| `origine` | ENUM(`en_ligne`,`etablissement`) | qui a saisi le dossier (défaut `en_ligne`) |
| `cree_par_user_id` | INT, FK (nullable) | l'agent qui a saisi au guichet (`NULL` si en ligne) |
| `statut` | ENUM(`brouillon`,`soumis`,`paye`,`accepte`,`refuse`) | suivi |
| `date_creation` | DATETIME | — |

> **Traçabilité de l'origine (v5)** : `origine = 'en_ligne'` signifie que le candidat a rempli le formulaire lui-même ; `origine = 'etablissement'` signifie que c'est un agent de l'école qui a saisi le dossier depuis son tableau de bord (candidat venu au guichet). Dans ce dernier cas, `cree_par_user_id` indique **quel** agent l'a saisi. Cette colonne permet de **dissocier** à tout moment les candidatures en ligne de celles saisies par l'administration — dans les listes, les filtres et les statistiques.

> **Lien avec les autres tables** : le dossier est l'objet de travail de l'assistant (créé en `brouillon` dès l'étape 1). Quand le paiement de l'étape 3 réussit, on crée une ligne `inscriptions` (avec `dossier_id` en clé étrangère) et une ligne `paiements`. Une **préinscription** réutilise les étapes 1 et 2 sans paiement : son dossier reste au statut `soumis`.

### 2.5 Table `pieces_justificatives_requises` (config de l'étape 2) — NOUVEAU

C'est elle qui rend la liste des documents **dynamique selon le niveau**. Chaque établissement définit, pour chaque niveau, les pièces attendues.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT, PK | — |
| `etablissement_id` | INT, FK | l'école qui exige la pièce |
| `niveau` | VARCHAR(60), NULL | niveau concerné (`NULL` = tous niveaux, ex. le passeport) |
| `libelle` | VARCHAR(180) | « Passeport (ou pièce d'identité équivalente) », « Relevés de notes — Licence 2 »… |
| `obligatoire` | TINYINT | 1 = bloquant, 0 = facultatif |
| `ordre` | INT | ordre d'affichage |

> Exemple concret tiré de ta maquette « Licence L3 » : une ligne `niveau = NULL, libelle = Passeport, obligatoire = 1` (valable partout), plus des lignes `niveau = Licence 3` pour le bac, les relevés L2, l'attestation L2 et la lettre de motivation.

### 2.6 Table `champs_formulaire` (formulaires configurables) — NOUVEAU

C'est le **moteur de configuration des formulaires**. Chaque établissement décide quels champs apparaissent dans sa préinscription et son inscription, lesquels sont obligatoires, et peut même ajouter des champs spécifiques — sans toucher au code.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT, PK | — |
| `etablissement_id` | INT, FK | l'école concernée |
| `type_formulaire` | ENUM(`preinscription`,`inscription`) | quel parcours |
| `section` | VARCHAR(60) | regroupement : « Identité », « Contact », « Formation souhaitée » |
| `cle` | VARCHAR(60) | nom machine (`lieu_naissance`, `nationalite`…) |
| `libelle` | VARCHAR(120) | texte affiché (« Lieu de naissance ») |
| `type_champ` | ENUM(`text`,`email`,`tel`,`date`,`select`,`textarea`,`fichier`) | type d'entrée |
| `options` | JSON | valeurs pour les listes déroulantes (genre, pays…) |
| `obligatoire` | TINYINT | 1 = requis |
| `actif` | TINYINT | 0 = masqué pour cet établissement |
| `standard` | TINYINT | 1 = champ standard fourni par défaut, 0 = champ ajouté par l'école |
| `ordre` | INT | ordre d'affichage |

> **Astuce de mise en route** : à la création d'un établissement, on **insère automatiquement un jeu de champs standard** (ceux des maquettes : prénom & nom, genre, date/lieu de naissance, nationalité, e-mail, téléphone, pays, ville, adresse, filière, niveau, dernier établissement). L'école part donc d'un formulaire complet et identique aux maquettes, qu'elle peut ensuite ajuster.

### 2.7 Table `recepisses` (justificatif de préinscription) — NOUVEAU

La préinscription génère un **récépissé officiel** (PDF) téléchargeable immédiatement. Différent du reçu de paiement (`recus`), car gratuit et émis sans transaction.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT, PK | — |
| `dossier_id` | INT, FK | → `dossiers_inscription` |
| `numero_recepisse` | VARCHAR(50), UNIQUE | ex. `PRE-2026-000123` |
| `cle_pdf` | VARCHAR(255) | chemin du PDF dans B2 |
| `date_emission` | DATETIME | — |

### 2.8 Paramètres de candidature (colonnes ajoutées à `etablissements`)

Quelques drapeaux pour piloter le comportement par établissement :

| Colonne | Type | Rôle |
|---|---|---|
| `preinscription_active` | TINYINT | la préinscription est-elle ouverte ? |
| `genere_recepisse` | TINYINT | génère-t-on un récépissé à la préinscription ? |
| `inscription_active` | TINYINT | l'inscription payante est-elle ouverte ? |

> Le caractère gratuit de la préinscription se déduit de `prix_preinscription = 0` (déjà au schéma). Pour stocker les réponses aux **champs personnalisés** (non standard), on ajoute une colonne `reponses_personnalisees JSON` à `dossiers_inscription` : les champs standard restent des colonnes (rapides à filtrer), les champs spécifiques vont en JSON (souples).

### 2.9 Évolution de la table `documents_orientation`

La v1 figeait les types de documents dans un `ENUM` (`bulletin_t1`, `attestation_bac`…). Puisque les pièces sont désormais **propres à chaque établissement et à chaque niveau**, on assouplit la table :

| Changement | Avant (v1) | Après (v3) |
|---|---|---|
| Type de pièce | `type_document ENUM(...)` figé | `piece_requise_id INT FK` → `pieces_justificatives_requises` |
| Libellé lisible | déduit de l'ENUM | `libelle VARCHAR(180)` (copie au moment du dépôt) |
| Rattachement | `user_id` seul | + `dossier_id INT FK` → `dossiers_inscription` |

Ainsi, un même fichier déposé est relié à la fois au candidat, à son dossier d'inscription, et à la pièce qu'il vient satisfaire. Le reste de la table (chemin B2, MIME, taille, date, journal d'accès) ne change pas.

### 2.10 Tables transactionnelles (inchangées depuis la v1, sauf `paiements`)

`etablissement_admins`, `preinscriptions`, `inscriptions` (désormais avec `dossier_id`), `recus`, `notifications`, `visites`, plus `journal_acces_documents` (stockage B2). Rappel : presque toutes portent une colonne `etablissement_id` qui sert à l'isolation (section 12).

**Évolution de `paiements` (v5)** : on ajoute une colonne `mode` ENUM(`en_ligne`,`espece`,`virement`,`mobile_money`) et une colonne `enregistre_par_user_id` (nullable). Cela permet à un agent qui saisit une inscription au guichet d'**enregistrer un paiement hors ligne** (espèces, virement…) et de tracer qui l'a enregistré. Les paiements en ligne gardent `mode = 'en_ligne'` et restent validés par le callback MoneyFusion.

### 2.11 Table `etablissement_paiement` (destination de reversement) — NOUVEAU

En **Option A**, la plateforme encaisse sur **son** compte MoneyFusion puis reverse automatiquement à chaque école. Cette table stocke donc **où** reverser : le numéro mobile money de l'école et son opérateur. L'école n'a **pas** de compte MoneyFusion à créer.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT, PK | — |
| `etablissement_id` | INT, FK, UNIQUE | l'école concernée |
| `pays_code` | VARCHAR(4) | code pays MoneyFusion (`ga` pour le Gabon) |
| `withdraw_mode` | VARCHAR(40) | opérateur de reversement (`airtel-ga` ou `moov-ga`) |
| `numero_mobile_money` | VARCHAR(30) | numéro destinataire du reversement |
| `titulaire` | VARCHAR(150) | nom du titulaire (vérification) |
| `taux_commission` | DECIMAL(5,2), NULL | commission plateforme spécifique (sinon valeur globale par défaut) |
| `statut` | ENUM(`actif`,`inactif`,`en_attente`) | actif seulement après vérification |
| `date_connexion` | DATETIME | — |

> **Important** : les identifiants MoneyFusion de la **plateforme** (lien d'API d'encaissement + clé privée de payout `moneyfusion-private-key`) ne sont **pas** dans cette table — ce sont des secrets plateforme, stockés chiffrés dans le `.env`, jamais en base ni en clair. Tant que `statut ≠ 'actif'`, l'inscription payante de l'école reste désactivée (`inscription_active`), mais la préinscription gratuite reste possible.

### 2.12 Table `reversements` (suivi des payouts automatiques) — NOUVEAU

Chaque reversement automatique vers une école est tracé ici. C'est la table qui rend l'Option A fiable : elle permet l'idempotence, la reprise des échecs et la réconciliation.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT, PK | — |
| `paiement_id` | INT, FK, UNIQUE | encaissement d'origine (UNIQUE = un seul reversement par paiement → idempotence) |
| `etablissement_id` | INT, FK | bénéficiaire |
| `montant_brut` | INT | montant de l'inscription encaissé |
| `commission_plateforme` | INT | part conservée par la plateforme |
| `montant_reverse` | INT | montant réellement envoyé à l'école |
| `numero_destination` | VARCHAR(30) | numéro mobile money visé |
| `withdraw_mode` | VARCHAR(40) | opérateur utilisé |
| `token_pay` | VARCHAR(100) | identifiant retourné par l'API `withdraw` (suivi du payout) |
| `statut` | ENUM(`initie`,`en_cours`,`reussi`,`echoue`,`a_rejouer`) | état du reversement |
| `tentatives` | INT | nombre d'essais (pour la reprise) |
| `message_erreur` | VARCHAR(255), NULL | dernière erreur éventuelle |
| `date_initiation` | DATETIME | — |
| `date_confirmation` | DATETIME, NULL | quand le webhook payout a confirmé |

---

## 3. Le branchement du template sur la base (NOUVEAU)

Cette section explique concrètement comment ton `index.html` figé devient un gabarit dynamique, sans changer son apparence.

### 3.1 Le principe : remplacer le « en dur » par des variables

Partout où ton template contient une valeur écrite en dur, on la remplace par une variable PHP issue de la base. Le HTML et le CSS ne bougent pas.

```php
AVANT (ton template figé) :
   <strong id="estName">BGFI Business School</strong>
   <span id="estSub">Grande école · Libreville</span>

APRÈS (branché sur la base) :
   <strong id="estName"><?= htmlspecialchars($etab['nom']) ?></strong>
   <span id="estSub"><?= htmlspecialchars($etab['type_label'].' · '.$etab['ville']) ?></span>
```

> **Règle de sécurité absolue** : toute donnée affichée passe par `htmlspecialchars()`. C'est ce qui empêche une faille XSS si un texte d'établissement contient du code malveillant.

### 3.2 La couleur de marque (le cœur du theming)

Une seule ligne, injectée dans le `<head>`, reteinte tout le site :

```php
<style>
  :root { --brand: <?= htmlspecialchars($etab['couleur_principale']) ?>; }
</style>
```

Ton CSS fait le reste automatiquement via `color-mix`. Rien d'autre à toucher.

### 3.3 Les zones répétées : on boucle sur la base

Les 6 cartes de filières écrites à la main deviennent une boucle sur la table `formations` :

```php
<div class="filiere-grid">
  <?php foreach ($formations as $f): ?>
    <article class="filiere">
      <div class="filiere-top">
        <span class="filiere-niveau"><?= htmlspecialchars($f['niveau']) ?></span>
        <span class="filiere-statut <?= $f['statut']==='ouverte' ? '' : 'closed' ?>">
          <span class="dot"></span><?= $f['statut']==='ouverte' ? 'Ouverte' : 'Bientôt' ?>
        </span>
      </div>
      <h3><?= htmlspecialchars($f['intitule']) ?></h3>
      <!-- … durée, places, prix … -->
      <div class="filiere-foot">
        <div class="filiere-price">
          <small>À partir de</small>
          <b><?= number_format($f['prix'],0,',',' ') ?><span> <?= $f['prix_unite'] ?></span></b>
        </div>
        <a class="btn btn-primary btn-sm"
           href="/orientation/<?= $etab['slug'] ?>/preinscription?formation=<?= $f['id'] ?>">
           Préinscription</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>
```

Même principe pour le blog (`foreach` sur `actualites`), les badges, les atouts et les statistiques (`foreach` sur les tableaux JSON).

### 3.4 Tableau complet de correspondance « zone du template → source »

| Zone du template | Source en base |
|---|---|
| Logo (header, hero, footer) | `etablissements.logo_cle` → image B2 |
| Nom de l'établissement | `etablissements.nom` |
| Sous-titre (type · ville) | `etablissements.type` + `ville` |
| Couleur de marque | `etablissements.couleur_principale` → `--brand` |
| Eyebrow « Candidatures… » | `etablissements.label_campagne` |
| Titre hero `<h1>` | `etablissements.titre_accroche` |
| Sous-titre hero | `etablissements.sous_titre` |
| Date limite (annotation) | `etablissements.date_limite_dossier` |
| Trust row (badges) | `etablissements.badges_confiance` (JSON) |
| Cartes filières | table `formations` (boucle) |
| Vidéo | `etablissements.video_url` |
| Photo « about » | `etablissements.images_cles` → B2 |
| Citation + auteur | `etablissements.citation_*` |
| Liste d'atouts | `etablissements.atouts` (JSON) |
| Statistiques (2003, +1200…) | `etablissements.statistiques` (JSON) |
| Articles de blog | table `actualites` (boucle) |
| Prix & frais | `etablissements.prix_*`, `frais_dossier` + `formations.prix` |
| Modes de paiement | `etablissements.infos_paiement` (JSON) |
| Adresse / carte | `etablissements.adresse`, `latitude`, `longitude` |
| Email / téléphone / WhatsApp | `etablissements.email_contact`, `telephone`, `whatsapp` |

### 3.5 POINT DE VIGILANCE MAJEUR : `image-slot.js` est un outil de DESIGN, pas de production

Ton composant `<image-slot>` est très pratique **pour concevoir et prévisualiser** la maquette, mais il ne doit **pas** servir à afficher les images sur les vrais sites des établissements. Pourquoi :

- Il sauvegarde les images via `window.omelette.writeFile` et un fichier local `.image-slots.state.json`. Ce pont `omelette` n'existe **que dans l'environnement de conception** ; sur ton VPS en production, il n'existe pas.
- Il stocke les images en *data-URL* dans un état local, ce qui est incompatible avec ton objectif (images dans Backblaze B2).

**La bonne approche en production :**

```php
<!-- Pendant le design : -->
<image-slot id="about-img" shape="rounded" radius="24" placeholder="…"></image-slot>

<!-- En production (site public d'un établissement) : -->
<img class="about-img" src="<?= $lienImageB2 ?>" alt="Campus <?= htmlspecialchars($etab['nom']) ?>">
```

- **Sur le site public d'un établissement** : on remplace chaque `<image-slot>` par un `<img>` classique dont la source est un **lien B2** (signé si l'image est privée, ou public si c'est un visuel non sensible comme un logo).
- **Dans le formulaire d'insertion (admin)** : on n'utilise pas `image-slot` non plus ; on met de simples champs `<input type="file">` qui **uploadent vers B2** via la classe `DocumentStorage` déjà écrite.
- **`image-slot.js` reste utile uniquement** comme outil interne de prototypage visuel, si tu veux composer une maquette avant de coder. Il ne part pas en production.

### 3.6 Organisation des fichiers (statique vs dynamique)

| Type | Où | Exemple |
|---|---|---|
| CSS / JS partagés (identiques pour tous) | servis en statique | `tokens.css`, `site.css`, `site.js` |
| Gabarit PHP unique | un seul fichier | `template_etablissement.php` |
| Images propres à chaque école | Backblaze B2 | logo, photos, vidéo, articles |
| Données propres à chaque école | MySQL | tout le contenu textuel et les prix |

Les feuilles de style et le script d'interactions sont **mutualisés** : tous les établissements chargent le même `site.css` et le même `site.js`. Seules les données et les images changent.

---

## 4. Le flux d'insertion d'un établissement (back-office)

C'est la partie que tu voulais la plus pédagogique. La voici, étape par étape.

### Vue d'ensemble du flux

```
Admin remplit le formulaire
        │
        ▼
[1] Validation des données (champs obligatoires, formats, prix ≥ 0)
        │
        ▼
[2] Génération automatique du slug (nom → bgfi-business-school)
        │
        ▼
[3] Upload du logo + images vers Backblaze B2 (input file → DocumentStorage)
        │
        ▼
[4] INSERT dans `etablissements` (+ INSERT des formations associées)
        │
        ▼
[5] Création du compte gestionnaire de l'établissement
        │
        ▼
[6] Envoi des identifiants au gestionnaire (email PHPMailer)
        │
        ▼
✅ Le site (ton template) ET le tableau de bord EXISTENT déjà
   (générés à la volée à partir des lignes créées)
```

### Détail de chaque étape

**[1] Validation.** Nom non vide, type valide, email correct, prix numériques et positifs, couleur au format hexadécimal, logo fourni au bon format. Si une règle échoue, on n'enregistre rien et on affiche un message clair. *Ne jamais faire confiance aux données du formulaire.*

**[2] Génération du slug.** « BGFI Business School » → `bgfi-business-school` (minuscules, accents retirés, espaces en tirets). On vérifie l'unicité ; sinon on ajoute un suffixe (`-2`).

**[3] Upload des médias vers B2.** Logo, photos et vidéo passent par `DocumentStorage`. On stocke en base uniquement les **chemins** retournés. (Pas d'`image-slot` ici — de vrais `<input type="file">`.)

**[4] Insertion en base.** Un `INSERT` dans `etablissements`, puis un `INSERT` par formation saisie. **C'est l'instant où le site naît** : l'URL `/orientation/{slug}` répond immédiatement.

**[5] Création du compte gestionnaire.** Compte dans `users` (mot de passe temporaire haché avec `password_hash`), relié à l'établissement via `etablissement_admins`.

**[6] Notification.** Email au gestionnaire avec ses identifiants et le lien de son tableau de bord (PHPMailer).

### Structure du formulaire d'insertion (alignée sur le template)

| Section du formulaire | Champs | Champs du template alimentés |
|---|---|---|
| **Identité** *(obligatoire)* | Nom, type, ville, couleur principale | header, sous-titre, `--brand` |
| **Médias** *(obligatoire : logo)* | Logo, photo campus, vidéo, images | logo, about, vidéo |
| **Accroche** *(optionnel)* | Label campagne, titre hero, sous-titre, date limite | hero |
| **Présentation** *(optionnel)* | Description, citation + auteur + fonction | about |
| **Atouts / badges / stats** *(optionnel)* | Listes (ajout de lignes) | trust row, about-list, about-stats |
| **Contact** *(obligatoire)* | Adresse, coordonnées GPS, email, téléphone, WhatsApp | contact |
| **Tarifs & paiement** *(obligatoire)* | Prix préinscription, inscription, frais dossier, modes de paiement | tarifs |
| **Compte d'encaissement** *(requis pour l'inscription payante)* | Numéro mobile money de l'école + opérateur (Airtel/Moov) + titulaire → `etablissement_paiement` | (reversement automatique, section 10) |
| **Formations** *(obligatoire : ≥ 1)* | Liste dynamique : intitulé, niveau, icône, durée, places, prix, statut | filières |
| **Gestionnaire** *(obligatoire)* | Nom, email du responsable | (création de compte) |

> **Conseil pratique** : ne rends obligatoires que les champs marqués ci-dessus. Les sections « optionnel » peuvent avoir des valeurs par défaut sensées, pour que l'admin puisse créer une école complète en 5 minutes et l'enrichir ensuite.

### Pourquoi cette approche élimine les vulnérabilités

- **Aucun code généré dynamiquement** : on n'écrit jamais de fichier `.php` à la volée. On insère seulement des données.
- **Validation centralisée** : une seule fonction protège les 40 écoles.
- **Requêtes préparées PDO** partout : pas d'injection SQL.
- **`htmlspecialchars()` à l'affichage** : pas de XSS.
- **Séparation données / présentation** : ton template est figé et sûr ; seules les données changent.

---

## 5. Le système de templating (un code, 40 sites)

Le fichier `template_etablissement.php` suit cette logique :

```
1. Lire le slug depuis l'URL
2. Chercher l'établissement (requête préparée) → 404 si introuvable/inactif
3. Charger ses formations, actualités, et décoder ses champs JSON
4. Injecter la couleur dans --brand
5. Afficher TON template rempli avec ces données
6. Incrémenter le compteur de visites de cet établissement
```

Tu écris ce gabarit **une seule fois**, à partir de ton `index.html` actuel. C'est le même principe que les thèmes WordPress : un thème, des milliers de sites selon le contenu.

---

## 6. Front-office — la marketplace (espace élève et public)

### 6.1 La page marketplace
Une grille de cartes (une par établissement actif) façon boutique : logo, nom, ville, type, fourchette de prix, bouton « Découvrir », filtres (type, ville, prix) et recherche. Techniquement : `SELECT * FROM etablissements WHERE statut='actif'` puis boucle d'affichage. Ajouter une école à la marketplace = ne rien faire : elle apparaît dès qu'elle est active.

### 6.2 Le parcours de l'élève
```
Marketplace → Site établissement (ton template) → Choix d'une formation
   → Préinscription (gratuite ou payante)
   → Si acceptée : Inscription → Paiement MoneyFusion → Reçu PDF
   → Notification + reçu dans l'espace élève
```

### 6.3 L'espace élève (tableau de bord personnel unique)
Un seul espace, tous établissements confondus : mes préinscriptions, mes inscriptions, mes paiements, mes reçus (liens temporaires B2), mes documents, mes notifications.

---

## 7. Les parcours de candidature : préinscription & inscription (NOUVEAU)

Le module propose **deux parcours**, qui partagent le même moteur de formulaire et la même charte que ton template (`tokens.css`, `site.css`, couleur `--brand`) :

| | Préinscription | Inscription |
|---|---|---|
| Format | page unique | assistant en 3 étapes |
| Coût | gratuite (en général) | payante (MoneyFusion) |
| Pièces justificatives | non | oui (étape 2) |
| Résultat | **récépissé** PDF immédiat | reçu de paiement + dossier complet |
| Statut du dossier | `soumis` | `paye` |

### 7.1 Le moteur de formulaire configurable (le point clé)

Tu l'as bien identifié : **les formulaires comportent des éléments à configurer selon l'établissement**. On ne code donc pas un formulaire par école. À la place, le formulaire est **construit à la volée** à partir de la table `champs_formulaire`.

```sql
SELECT * FROM champs_formulaire
WHERE etablissement_id = ?
  AND type_formulaire = ?      -- 'preinscription' ou 'inscription'
  AND actif = 1
ORDER BY section, ordre;
```

Le code boucle ensuite sur ces champs et génère chaque entrée selon son `type_champ` (texte, liste, date, fichier…), dans sa section (« Identité », « Contact », « Formation souhaitée »). Conséquences :

- **Chaque établissement adapte son formulaire** depuis son tableau de bord : activer/désactiver un champ, le rendre obligatoire, le réordonner, ou en ajouter un spécifique.
- **Le rendu reste identique à tes maquettes** : c'est le même HTML/CSS, seules les lignes affichées changent.
- **La validation est pilotée par la base** : un champ `obligatoire = 1` est vérifié côté serveur, quel que soit l'établissement.

> **Démarrage immédiat** : grâce au jeu de champs standard inséré à la création de l'établissement (section 2.6), une école dispose dès le départ d'un formulaire complet, conforme aux maquettes, sans rien configurer. La configuration n'est utile que pour les écoles aux besoins particuliers.

### 7.2 La préinscription (page unique + récépissé)

Sous-titre de la maquette : « La préinscription est gratuite et instantanée. Remplissez le formulaire, téléchargez votre récépissé officiel, puis finalisez votre inscription quand vous le souhaitez. »

**Champs (générés depuis `champs_formulaire`, type `preinscription`)**, regroupés comme sur la maquette :

| Section | Champs |
|---|---|
| **Identité** | Prénom & nom, genre, date de naissance, lieu de naissance, nationalité |
| **Contact** | E-mail, téléphone (indicatif + numéro), pays de provenance, ville, adresse |
| **Formation souhaitée** | Filière, niveau d'entrée, dernier établissement fréquenté |

**Déroulé :**
```
Candidat remplit la page → Validation serveur (champs obligatoires)
   → Création du dossier (statut "soumis")
   → Génération du RÉCÉPISSÉ PDF (à l'image de l'établissement)
   → Stockage du PDF dans B2 + ligne `recepisses`
   → Téléchargement immédiat proposé + notification
   → (option) « Passer à l'inscription payante » → bascule vers l'assistant 7.3
```

Le **récépissé** est un PDF généré côté serveur (logo + couleurs de l'établissement, numéro unique `PRE-AAAA-xxxxxx`, identité du candidat, filière visée, date). Comme c'est un justificatif non sensible destiné au candidat, il peut être proposé en téléchargement direct via un lien temporaire B2.

> **Pas de paiement, pas de pièces** à cette étape : la préinscription doit rester « instantanée » comme l'annonce ta maquette. Les drapeaux `preinscription_active` et `genere_recepisse` (section 2.8) permettent à un établissement de désactiver la préinscription ou le récépissé s'il ne les souhaite pas.

### 7.3 L'inscription (assistant en 3 étapes)

L'inscription se déroule dans un **assistant multi-étapes** avec barre de progression : **Informations → Formation & dossier → Paiement**.

**Étape 1 — Informations personnelles.** Mêmes champs que la préinscription (générés depuis `champs_formulaire`, type `inscription`), enregistrés dans `dossiers_inscription`. Sous-titre : « Ces informations seront utilisées pour créer votre dossier candidat ». Si le candidat est connecté ou a déjà préinscrit, on **pré-remplit** depuis ses données existantes. Le dossier est sauvegardé en `brouillon` dès la fin de l'étape.

**Étape 2 — Formation & dossier académique.** Choix de la filière (Socle L1-L2 / Licence L3 / Master M1-M2 / Certification → `filiere_visee`) et du niveau (→ `niveau_entree`), puis **dépôt des pièces justificatives, dont la liste est dynamique** :

```sql
SELECT * FROM pieces_justificatives_requises
WHERE etablissement_id = ?
  AND (niveau = ? OR niveau IS NULL)   -- NULL = exigée à tous les niveaux
ORDER BY ordre;
```

Chaque pièce déposée part **directement dans Backblaze B2** via `DocumentStorage` (validation du vrai type MIME + taille), puis une ligne `documents_orientation` est créée, reliée au dossier et à la pièce. La maquette montre un bouton « Passer » : pratique pour une démo, mais en production on **bloque** tant que les pièces `obligatoire = 1` ne sont pas déposées.

**Étape 3 — Paiement.** Récapitulatif puis paiement via **MoneyFusion** (montant = prix d'inscription de la formation, ou de l'établissement par défaut). Le déroulé technique est celui de la section 10 : on crée la ligne `paiements`, on redirige, et **c'est le callback serveur** qui valide. En cas de succès : le dossier passe à `paye`, une ligne `inscriptions` est créée, le reçu PDF est généré et stocké dans B2, et l'élève comme l'établissement sont notifiés.

### 7.4 Gestion de l'état & sécurité des parcours

On sauvegarde l'avancement à chaque étape (le dossier passe de `brouillon` à `soumis` puis `paye`), pour permettre la reprise et éviter toute perte de saisie. **Aucune donnée personnelle ne transite par l'URL** ; tout passe par la session et des requêtes POST. La validation des champs obligatoires se fait **toujours côté serveur**, jamais seulement en JavaScript.

---

## 8. Le tableau de bord de l'établissement

Chaque gestionnaire voit **uniquement les données de son école** (filtrage strict sur `etablissement_id`).

| Fonctionnalité | Source |
|---|---|
| Nombre de visites | `visites` filtrée |
| Préinscriptions reçues | `dossiers_inscription` (statut `soumis`) filtrés |
| Inscriptions | `inscriptions` filtrée |
| Paiements reçus | `paiements` filtrée |
| Reversements reçus | `reversements` filtrés (montant net crédité + statut) |
| Reçus émis | `recus` (via paiements) |
| **Créer une préinscription** | saisie au guichet → dossier `origine = 'etablissement'` |
| **Créer une inscription** | saisie au guichet → dossier + paiement (mode hors ligne possible) |
| Gérer les formations | `formations` (CRUD) — alimente directement les cartes du site |
| Configurer les formulaires | `champs_formulaire` (CRUD) — active/ordonne/ajoute des champs de pré/inscription |
| Gérer les pièces requises | `pieces_justificatives_requises` (CRUD) — pilote l'étape 2 de l'assistant |
| Gérer les actualités | `actualites` (CRUD) — alimente le blog du site |
| Récépissés émis | `recepisses` (préinscriptions validées) |
| Traiter les candidatures | consulter le dossier, ses pièces (liens B2), accepter / refuser |

### 8.1 Saisie d'une candidature par l'administration (NOUVEAU)

Un agent de l'établissement peut créer lui-même une préinscription ou une inscription, pour un candidat venu sur place. Le principe d'économie de code s'applique encore : **on réutilise exactement les mêmes formulaires** que le parcours en ligne (section 7), simplement ouverts depuis le tableau de bord. Deux différences seulement :

1. Le dossier est marqué `origine = 'etablissement'` et `cree_par_user_id` reçoit l'identifiant de l'agent.
2. Pour une inscription, le paiement peut être enregistré **hors ligne** (espèces, virement, mobile money) au lieu de passer par MoneyFusion — avec `paiements.mode` correspondant et `enregistre_par_user_id` renseigné.

### 8.2 Dissocier le « en ligne » de l'« administration »

Grâce à la colonne `origine`, le tableau de bord distingue clairement les deux sources partout :

- **Filtre** « Toutes / En ligne / Saisies par l'établissement » sur les listes de préinscriptions et d'inscriptions.
- **Badge** d'origine sur chaque ligne (et le nom de l'agent pour les saisies au guichet).
- **Statistiques séparées** : par exemple « 120 préinscriptions en ligne · 35 au guichet ». Côté requête, il suffit d'ajouter `AND origine = ?` au filtre habituel sur `etablissement_id`.

> Cette traçabilité est aussi utile à l'audit : on sait toujours qui a créé un dossier, ce qui protège l'établissement en cas de litige.

> Conséquence élégante du template branché : quand un gestionnaire ajoute une formation depuis son tableau de bord, **une nouvelle carte `.filiere` apparaît automatiquement** sur son site. Aucun code à toucher.

---

## 9. Le tableau de bord Super-Admin (back-office)

Liste de tous les établissements (statut, actions), formulaire d'insertion (section 4), statistiques globales (total écoles, préinscriptions, inscriptions, revenus), gestion des accès gestionnaires, journal d'activité (audit).

---

## 10. Les paiements (MoneyFusion) — Option A : un compte + reversement automatique

**Principe directeur** : un **seul compte MoneyFusion** (celui de la plateforme) encaisse les inscriptions, puis le serveur **reverse automatiquement** à chaque école sur son **numéro mobile money**. La plateforme ne conserve jamais les fonds : ils ne font que transiter quelques secondes, le temps du reversement automatique.

### 10.1 Pourquoi cette architecture

MoneyFusion dépose les fonds dans le compte dont la clé d'API a servi, et **n'offre pas de répartition automatique multi-bénéficiaires** (« split ») au moment du paiement. Avec un seul compte, l'argent arrive donc forcément d'abord chez la plateforme. Pour le faire parvenir aux écoles sans manipulation manuelle, on utilise l'**API de reversement (payout)** de MoneyFusion, qui envoie de l'argent vers n'importe quel numéro mobile money. Le Gabon est couvert : reversement possible via **Airtel Money** (`airtel-ga`) et **Moov** (`moov-ga`), en XAF.

> Conséquence : chaque école n'a **pas besoin de compte MoneyFusion**. Elle fournit seulement un numéro mobile money à son nom.

### 10.2 Les identifiants (où vivent les secrets)

| Secret | Emplacement |
|---|---|
| Lien d'API d'**encaissement** de la plateforme | `.env` (chiffré, jamais en base) |
| Clé privée de **payout** (`moneyfusion-private-key`) | `.env` (chiffré, jamais en base) |
| Numéro mobile money **de chaque école** | table `etablissement_paiement` |

L'IP du VPS (`85.190.96.109`) doit être enregistrée dans les réglages MoneyFusion de la plateforme (exigé pour l'API payout).

### 10.3 Le flux complet (encaissement → reversement)

```
1. L'élève paie l'inscription de l'école X (montant fixé par X)
        │  encaissement sur le compte MoneyFusion de la PLATEFORME
2. Webhook pay-in confirmé → paiement "reussi", reçu PDF généré (B2)
        │
3. Calcul : montant_reverse = montant − commission_plateforme (− frais MoneyFusion)
        │
4. Création d'une ligne `reversements` (statut "initie")
        │
5. POST https://pay.moneyfusion.net/api/v1/withdraw
        │  { countryCode:"ga", phone: <numéro X>, amount: <montant_reverse>,
        │    withdraw_mode:"airtel-ga"|"moov-ga", webhook_url: <callback payout> }
        │  → on stocke le `tokenPay` retourné dans la ligne reversement
6. Webhook payout (payout.session.completed | payout.session.cancelled)
        │
7. Mise à jour du reversement : "reussi" ou "echoue"/"a_rejouer"
        │
8. 💰 L'école X a reçu son argent automatiquement, en quelques secondes
```

### 10.4 Les garde-fous indispensables (ce qui rend l'Option A fiable)

1. **Reverser uniquement après confirmation pay-in.** Jamais sur la simple redirection navigateur : on attend le webhook d'encaissement, qui fait foi.
2. **Idempotence.** La contrainte `UNIQUE` sur `reversements.paiement_id` garantit qu'un encaissement ne déclenche **jamais deux reversements**.
3. **Gestion des échecs.** Si le pay-in réussit mais le payout échoue (numéro erroné, opérateur indisponible), l'argent est sur le compte plateforme et l'école n'a rien reçu. Le reversement passe en `a_rejouer` ; un **écran de réconciliation** liste les reversements en attente/échoués, avec un bouton « Rejouer ». On incrémente `tentatives`.
4. **Sécurité du numéro de destination.** Modifier le numéro de reversement d'une école est l'action la plus sensible du système (risque de détournement). Réservée à un rôle autorisé, avec **double confirmation et journalisation**.

### 10.5 La commission de la plateforme

Comme l'argent transite par la plateforme, la commission peut être **prélevée automatiquement** avant le reversement :

```
montant_reverse = montant_inscription − commission_plateforme
```

- `commission_plateforme` est configurable : un taux (%) ou un montant fixe, global avec surcharge possible par école (`etablissement_paiement.taux_commission`).
- Elle doit couvrir **les frais MoneyFusion (encaissement + reversement) + la marge** de la plateforme. D'où l'importance de mesurer les frais réels (test recommandé : encaisser 1 000 XAF, reverser, comparer).
- Si tu préfères le modèle **abonnement**, mets la commission à 0 et reverse le montant intégral ; la plateforme se rémunère alors via un forfait facturé aux écoles.

### 10.6 Note réglementaire

Les fonds transitant par le compte de la plateforme, l'ensemble des inscriptions apparaît comme passant par elle. Aux yeux d'un régulateur ou du fisc, cela peut s'apparenter à de l'encaissement pour compte de tiers, avec des obligations associées (LCB-FT, fiscalité). **Ce document est technique et ne constitue pas un avis juridique** : ce point doit être validé par un professionnel au Gabon avant le lancement.

---

## 11. Stockage des fichiers (Backblaze B2) — intégration

Tout ce qui est fichier (documents sensibles et visuels) est stocké dans Backblaze B2. Cette section consolide l'intégration technique.

### 11.1 Où sont stockés les fichiers

| Type de fichier | Préfixe B2 | Sensibilité |
|---|---|---|
| Logos d'établissements | `etablissements/{id}/logo_…` | public possible |
| Photos / vidéos / articles | `etablissements/{id}/…` | public possible |
| Bulletins, attestations, pièces élèves | `documents/{user_id}/…` | **sensible** (lien temporaire) |
| Récépissés de préinscription PDF | `recepisses/{annee}/{numero}.pdf` | à traiter comme sensible |
| Reçus de paiement PDF | `recus/{annee}/{numero}.pdf` | à traiter comme sensible |

La base ne stocke que les **chemins**. Les documents **sensibles** ne sont jamais publics : consultation via **liens temporaires de 10 minutes**. Les **visuels non sensibles** (logo, campus) peuvent être servis en lecture publique pour la rapidité d'affichage. Rappel : on remplace les `<image-slot>` par des `<img>` pointant vers ces fichiers B2 (section 3.5).

### 11.2 État de la mise en place

Les trois premières étapes sont **déjà réalisées** :

- ✅ **Compte B2 créé** en région **EU Central (Amsterdam)** — la plus proche du Gabon (choix irréversible), avec 2FA activée.
- ✅ **Bucket privé créé** : `connect-academia-documents`, en mode **Private**, **chiffrement SSE-B2 activé**. Endpoint noté : `s3.eu-central-003.backblazeb2.com` (contient la région `eu-central-003`).
- ✅ **Clé d'application créée** selon le moindre privilège : limitée au seul bucket `connect-academia-documents`, accès Read/Write. Le `keyID` et l'`applicationKey` ont été copiés (affichés une seule fois par B2).

Restent les étapes d'intégration logicielle ci-dessous (4 à 8 du guide).

### 11.3 Configuration `.env`

Ajouter au `.env` du projet, avec les vraies valeurs :

```
B2_KEY_ID=le_keyID
B2_APPLICATION_KEY=l_applicationKey
B2_BUCKET=connect-academia-documents
B2_ENDPOINT=https://s3.eu-central-003.backblazeb2.com
B2_REGION=eu-central-003
```

Règles : la région exacte est celle contenue dans l'endpoint (la recopier telle quelle) ; le `.env` ne doit **jamais** être dans un dossier accessible par le web ni versionné dans Git (`.gitignore`).

### 11.4 Installation du SDK AWS pour PHP

En SSH sur le VPS :

```bash
cd /var/www/connectacademia.io
composer require aws/aws-sdk-php
```

> **Pourquoi le SDK d'AWS pour Backblaze ?** B2 parle le « langage S3 ». Le SDK AWS est la bibliothèque officielle et fiable ; on lui indique juste de s'adresser à l'endpoint de B2 au lieu de celui d'Amazon. Avantage : une migration éventuelle vers AWS ne changerait pas le code.

### 11.5 Les fichiers de code et leur rôle

| Fichier | Rôle |
|---|---|
| `DocumentStorage.php` | Cœur du système : validation (vrai type MIME + taille), upload chiffré, génération de liens temporaires, suppression |
| `upload_document.php` | Endpoint appelé lors du dépôt d'une pièce (étape 2 de l'inscription) ; enregistre les métadonnées et journalise |
| `telecharger_document.php` | **Gardien** : vérifie les droits avant toute consultation, journalise, puis délivre un lien temporaire |
| `test_b2.php` | Script de vérification (à supprimer après le test) |

Adaptations : vérifier le chemin du fichier de connexion PDO en haut des endpoints. **Cohérence v3+** : le dépôt de pièces n'utilise plus une liste figée de types, mais la table `pieces_justificatives_requises` (section 7.3) ; chaque fichier déposé est relié au dossier et à la pièce via `documents_orientation` (section 2.9).

### 11.6 Architecture (flux de bout en bout)

```
ÉLÈVE ──upload──> upload_document.php ──> DocumentStorage ──> Bucket B2 (privé, chiffré)
                        │                                          ▲
                        └──> MySQL (métadonnées + journal)         │
                                                                   │
ÉLÈVE/UNIVERSITÉ ──> telecharger_document.php                      │
                        │  1. connecté ?                           │
                        │  2. autorisé à voir CE document ?        │
                        │  3. journalisation                       │
                        └──4. lien temporaire (10 min) ────────────┘
```

Point central : **aucun fichier sensible n'est accessible par URL directe**. Toute consultation passe par `telecharger_document.php`, qui vérifie les droits, journalise, puis délivre un lien qui expire au bout de 10 minutes.

### 11.7 Test de vérification

Déposer `test_b2.php` à la racine, puis en SSH : `php test_b2.php`. Le script vérifie dans l'ordre : variables `.env` chargées → connexion au bucket → upload d'un PDF de test → génération d'un lien temporaire (l'ouvrir : le PDF s'affiche ; réessayer après 11 min : l'accès doit être **refusé**, preuve que la sécurité fonctionne) → suppression. **Supprimer `test_b2.php`** du serveur une fois le test réussi.

### 11.8 Checklist sécurité B2

- [ ] Bucket en mode **Private** (jamais Public)
- [ ] Chiffrement par défaut **activé** sur le bucket
- [ ] Clé d'application **limitée au seul bucket**
- [ ] **2FA activée** sur le compte B2
- [ ] `.env` **hors du dossier web** et **hors de Git**
- [ ] `test_b2.php` **supprimé** après le test
- [ ] Tables `documents_orientation` et `journal_acces_documents` créées (section 2)
- [ ] Lien temporaire vérifié : accessible avant 10 min, refusé après

### 11.9 Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `SignatureDoesNotMatch` | applicationKey incorrecte ou tronquée | Vérifier la clé dans le `.env`, régénérer si besoin |
| `The specified bucket does not exist` | Nom de bucket ou endpoint erroné | Vérifier `B2_BUCKET` et `B2_ENDPOINT` |
| `Access Denied` au test | Clé non rattachée à ce bucket | Recréer la clé limitée au bon bucket |
| Le lien temporaire ne s'ouvre pas | Endpoint ou région incohérents | Recopier l'endpoint exact depuis la console B2 |
| `class S3Client not found` | SDK non installé | Relancer `composer require aws/aws-sdk-php` |
| Erreur de connexion MySQL | Chemin de `db.php` incorrect | Corriger le `require_once` en haut des endpoints |

---

## 12. Sécurité — les barrières non négociables

| Risque | Barrière |
|---|---|
| Une école voit les données d'une autre | Filtrer CHAQUE requête sur `etablissement_id`, déduit de la session, jamais de l'URL |
| Injection SQL | Requêtes préparées PDO partout |
| XSS via contenu d'établissement | `htmlspecialchars()` sur tout texte affiché dans le template |
| Élève accède au document d'un autre | Contrôle de droits dans `telecharger_document.php` |
| Paiement falsifié | Validation par le webhook serveur MoneyFusion uniquement |
| Secrets MoneyFusion plateforme | Lien d'encaissement + clé de payout dans le `.env` chiffré, jamais en base ni dans Git |
| Double reversement | Contrainte `UNIQUE` sur `reversements.paiement_id` (idempotence) |
| Détournement de fonds | Changer le numéro de reversement d'une école = action réservée à un rôle autorisé, double confirmation + journal |
| Reversement perdu (payout échoué) | File `reversements` avec statut `a_rejouer` + écran de réconciliation |
| Fichier malveillant uploadé | Validation du vrai type MIME + taille (dans `DocumentStorage`) |
| Vol de session | Cookies `HttpOnly` + `Secure`, HTTPS partout |
| Mot de passe en clair | `password_hash()` / `password_verify()` |
| Accès non autorisé aux tableaux de bord | Vérifier le rôle ET l'appartenance à l'établissement à chaque page |

**La règle d'or multi-tenant** : l'`etablissement_id` de filtrage vient **toujours** de la session de l'utilisateur connecté, **jamais** d'un paramètre d'URL modifiable.

---

## 13. Plan d'action par phases

Chaque phase est livrable et testable avant la suivante.

### Phase 1 — Les fondations (base de données)
- [ ] Créer toutes les tables de la section 2 (avec les **nouveaux champs** du template)
- [ ] Vérifier clés étrangères et index (`etablissement_id`, `eleve_id`, `slug`)
- [ ] *Livrable : schéma solide*

### Phase 1 bis — Intégration Backblaze B2 (section 11)
- [x] Compte B2 (région EU), bucket privé chiffré, clé d'application (déjà faits)
- [ ] Configurer le `.env` (clés B2) + installer le SDK (`composer require aws/aws-sdk-php`)
- [ ] Déposer `DocumentStorage.php`, `upload_document.php`, `telecharger_document.php`
- [ ] Lancer `test_b2.php`, vérifier le lien temporaire, puis le supprimer
- [ ] *Livrable : stockage sécurisé opérationnel pour les documents*

### Phase 2 — Insertion d'établissement (back-office)
- [ ] Formulaire d'insertion aligné sur les sections du template (section 4)
- [ ] Validation + slug + upload B2 (input file) + INSERT établissement & formations
- [ ] Création auto du compte gestionnaire + email
- [ ] *Livrable : on ajoute une école sans coder*

### Phase 3 — Brancher TON template (front)
- [ ] Convertir `index.html` en `template_etablissement.php`
- [ ] Remplacer les valeurs en dur par les variables (section 3.4)
- [ ] **Remplacer les `<image-slot>` par des `<img>` B2** (section 3.5)
- [ ] Injecter `--brand` ; boucles sur `formations`, `actualites`, JSON
- [ ] Routage par slug + compteur de visites
- [ ] *Livrable : chaque école insérée a son site, identique à ton design*

### Phase 4 — La marketplace
- [ ] Page liste avec cartes, filtres, recherche
- [ ] *Livrable : les élèves parcourent les écoles*

### Phase 5 — Les parcours de candidature (préinscription & inscription)
- [ ] Moteur de formulaire configurable : rendu d'un formulaire depuis `champs_formulaire` + validation serveur pilotée par la base
- [ ] Jeu de champs standard inséré automatiquement à la création d'un établissement
- [ ] **Préinscription** : page unique → dossier `soumis` → génération du récépissé PDF (B2) → téléchargement
- [ ] **Inscription** : assistant 3 étapes (infos → filière/niveau + pièces dynamiques → paiement, voir Phase 6)
- [ ] Bascule « Passer à l'inscription payante » depuis la préinscription
- [ ] *Livrable : un élève peut préinscrire (récépissé) ou s'inscrire de bout en bout*

### Phase 6 — Encaissement + reversement automatique (Option A)
- [ ] Config plateforme MoneyFusion dans le `.env` (lien d'encaissement + clé de payout), IP du VPS enregistrée
- [ ] Onboarding école : numéro mobile money + opérateur (Airtel/Moov) + titulaire → `etablissement_paiement`
- [ ] Encaissement + webhook pay-in → paiement "reussi" + reçu PDF (B2)
- [ ] Reversement auto via `POST /withdraw` → ligne `reversements` + `tokenPay`
- [ ] Webhook payout → statut `reussi`/`echoue`, **idempotence** (un reversement par paiement)
- [ ] Écran de réconciliation : reversements `a_rejouer` + bouton « Rejouer »
- [ ] Commission plateforme déduite avant reversement (configurable)
- [ ] Activation conditionnelle : `inscription_active` seulement si `etablissement_paiement` `actif`
- [ ] *Livrable : l'élève paie, l'école est créditée automatiquement, la plateforme garde sa commission*

### Phase 7 — Les trois tableaux de bord
- [ ] Espace élève (suivi des opérations)
- [ ] Tableau de bord établissement : statistiques, CRUD (formations, actualités, formulaires, pièces)
- [ ] **Saisie au guichet** : créer une préinscription / inscription depuis le tableau de bord (`origine = 'etablissement'`, paiement hors ligne possible)
- [ ] **Filtres et stats par origine** (en ligne vs établissement)
- [ ] Tableau de bord super-admin (vue globale)
- [ ] *Livrable : chacun pilote son espace*

### Phase 8 — Finitions
- [ ] Notifications, journal d'audit, tests d'isolation entre établissements
- [ ] *Livrable : module prêt pour août 2026*

---

## 14. Checklist de mise en production

- [ ] Toutes les tables créées (champs template + `dossiers_inscription`, `pieces_justificatives_requises`, `champs_formulaire`, `recepisses`)
- [ ] Insertion d'établissement testée de bout en bout
- [ ] Jeu de champs standard inséré automatiquement à la création d'un établissement
- [ ] Slug unique vérifié automatiquement
- [ ] `--brand` injectée et palette correcte par établissement
- [ ] **Aucun `<image-slot>` en production** : remplacés par `<img>` B2
- [ ] `tokens.css` / `site.css` / `site.js` servis en statique mutualisé
- [ ] Formulaires rendus depuis `champs_formulaire` (configurables par établissement)
- [ ] Validation des champs obligatoires faite côté serveur, pas seulement en JS
- [ ] Préinscription : récépissé PDF généré, stocké en B2, téléchargeable
- [ ] Assistant d'inscription : les 3 étapes enchaînées, dossier sauvegardé à chaque étape
- [ ] Étape 2 : pièces requises chargées dynamiquement selon le niveau
- [ ] Pièces `obligatoire = 1` bloquantes (pas de bouton « Passer » en production)
- [ ] Chaque pièce déposée part dans B2 et est reliée au dossier + à la pièce requise
- [ ] Saisie au guichet possible : dossier `origine = 'etablissement'` + `cree_par_user_id` renseigné
- [ ] Paiement hors ligne enregistrable (mode espèces/virement/mobile money) avec agent tracé
- [ ] Listes et statistiques dissociées par origine (en ligne vs établissement)
- [ ] Secrets MoneyFusion plateforme dans le `.env` chiffré ; IP du VPS enregistrée chez MoneyFusion
- [ ] Numéro mobile money + opérateur de chaque école stockés dans `etablissement_paiement`
- [ ] Encaissement validé par webhook pay-in (jamais par la redirection)
- [ ] Reversement automatique via `/withdraw` vers le numéro de l'école, tracé dans `reversements`
- [ ] Idempotence : un seul reversement par paiement (contrainte `UNIQUE`)
- [ ] Reversements échoués repérables et rejouables (écran de réconciliation)
- [ ] Changement du numéro de reversement : rôle autorisé + double confirmation + journal
- [ ] Inscription payante ouverte seulement si `etablissement_paiement` est `actif`
- [ ] Commission plateforme calibrée au-dessus des frais MoneyFusion (test de frais effectué)
- [ ] Médias stockés dans B2, jamais en local public (sauf visuels non sensibles assumés)
- [ ] Filtrage `etablissement_id` vérifié sur CHAQUE tableau de bord
- [ ] `htmlspecialchars()` sur tout texte d'établissement affiché
- [ ] Paiement validé uniquement par callback serveur
- [ ] Reçus PDF générés et stockés dans B2
- [ ] Liens de documents sensibles temporaires (10 min)
- [ ] Requêtes préparées PDO partout ; mots de passe hachés
- [ ] Test d'intrusion : une école ne peut PAS voir les données d'une autre
- [ ] HTTPS + cookies sécurisés

---

## 15. Erreurs classiques à éviter (conseils d'expérience)

1. **Générer des fichiers par établissement.** Jamais. Tout est piloté par les données.
2. **Déployer `image-slot.js` en production.** C'est un outil de design ; en prod, des `<img>` B2.
3. **Faire confiance à l'`etablissement_id` de l'URL.** Toujours via la session.
4. **Valider le paiement sur la redirection navigateur.** Toujours via le callback serveur.
5. **Afficher un texte d'établissement sans `htmlspecialchars()`.** Porte ouverte au XSS.
6. **Stocker les fichiers sensibles dans un dossier web public.** Toujours B2 + liens temporaires.
7. **Coder la validation à plusieurs endroits.** Une fonction centrale, réutilisée.
8. **Oublier les index** sur `etablissement_id`, `eleve_id`, `slug`.
9. **Mélanger les rôles.** Un middleware clair : super-admin / gestionnaire / élève.

---

## Rappel juridique

Le traitement de données personnelles de mineurs au Gabon relève de la loi sur la protection des données (autorité : **ANPDCP**). Documents sensibles, paiements et données scolaires impliquent des obligations de confidentialité et de conservation. Une consultation juridique locale est recommandée avant le lancement. Ce document est technique et ne constitue pas un avis juridique.

---

*Plan d'action v2 — Connect'Academia — Module Orientation — connectacademia.io — Juin 2026*
