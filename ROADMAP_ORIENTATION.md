# ROADMAP — Module Orientation · Connect'Academia
> Ingénieur fullstack — Architecture MVC · Redis · Backblaze B2 · MoneyFusion  
> Cible : août 2026 — Gabon

---

## 0. Vision d'ensemble

Un seul code, N établissements. Architecture **multi-tenant pilotée par les données** :  
- Aucun fichier généré par établissement  
- Une ligne en base = un site à l'URL `/orientation/{slug}`  
- Le gabarit `docs/gabarit/` est le template unique (déjà conçu et validé)  
- La couleur `--brand` change tout le theming via `color-mix`  
- Redis cache les données établissement et les listes marketplace

```
FLUX GLOBAL
───────────────────────────────────────────────────────────────────
Public/Élève               Back-office admin        Back-office gestionnaire
                                                     (par établissement)
/orientation               /admin/orientation/      /gestionnaire/
  Hub (accueil)              établissements         dashboard
  ↓                          créer / éditer         formations CRUD
/orientation/universites   ────────────────────     actualités CRUD
/orientation/grandes-ecoles                         candidatures
  Marketplace (filtre)      Super-Admin voit tout   pièces requises
  ↓                                                 formulaires config
/orientation/{slug}        B2 (logos, docs, PDFs)   saisie au guichet
  Page établissement ←───── Redis (cache 10 min) ──→ reversements reçus
  ↓
/orientation/{slug}/preinscription
/orientation/{slug}/inscription  (3 étapes)
  ↓                                                  Espace élève
MoneyFusion webhook ──────────────────────────────→ /eleve/orientation
  Encaissement → Reversement auto école              mes dossiers / reçus
───────────────────────────────────────────────────────────────────
```

---

## 1. Restructuration des fichiers existants

### 1.1 Ce qui change dans `public/orientation/`

**Situation actuelle** (à supprimer / renommer) :
```
public/orientation/
  orientation.html      → remplacé par vue PHP (conserver le design !)
  universites.html      → remplacé par vue PHP marketplace
  prives.html           → remplacé par vue PHP marketplace
  uni.css               → absorbé dans les assets CSS du module
```

**Nouveau état cible** (seuls les assets statiques restent ici) :
```
public/orientation/
  assets/               ← CSS/JS/images spécifiques du module orientation
    css/
      orientation.css   ← styles hub + marketplace (extraits de orientation.html)
      etablissement.css ← site.css du gabarit renommé
      tokens.css        ← tokens.css du gabarit (mutualisé)
    js/
      orientation.js    ← JS hub + popup
      etablissement.js  ← site.js du gabarit
    images/             ← images statiques orientation (boussole, backgrounds)
```

Les routes `/orientation`, `/orientation/universites`, etc. sont désormais gérées par le routeur PHP → Controller → Vue.

### 1.2 Principe de conservation du design

Le design de `orientation.html` est **INTÉGRALEMENT conservé** :
- Hero full-screen avec background image + overlay sombre
- Montserrat 900 + boussole icon pour le titre
- Search container avec tabs (Formations / Écoles / Métiers / Pays)
- Section établissements : cartes glass-morphism avec icône flottante
- Section vidéo de présentation
- Section Ambassador
- Footer existant de Connect'Academia
- Popup "bientôt disponible" → **supprimée** (module live)

Le gabarit `docs/gabarit/index.html` est **INTÉGRALEMENT conservé** pour chaque page établissement. On remplace uniquement les valeurs en dur par des variables PHP.

---

## 2. Architecture MVC — Structure des fichiers

```
app/
├── Controllers/
│   ├── Orientation/
│   │   ├── OrientationHubController.php        # /orientation (hub + marketplace)
│   │   ├── EtablissementController.php          # /orientation/{slug}
│   │   ├── CandidatureController.php            # préinscription + inscription 3 étapes
│   │   ├── PaiementOrientationController.php    # webhooks MoneyFusion (pay-in + payout)
│   │   └── EspaceEleveOrientationController.php # /eleve/orientation (dossiers élève)
│   ├── Admin/
│   │   └── Orientation/
│   │       ├── EtablissementAdminController.php # CRUD établissements (super-admin)
│   │       └── ReconciliationController.php     # reversements échoués (super-admin)
│   └── Gestionnaire/
│       ├── GestionnaireDashboardController.php  # tableau de bord école
│       ├── FormationController.php              # CRUD formations
│       ├── ActualiteController.php              # CRUD actualités/blog
│       ├── CandidatureGestionnaireController.php # consulter + traiter dossiers
│       ├── FormulaireConfigController.php       # config champs formulaire
│       └── PieceRequiseController.php           # config pièces justificatives
│
├── Models/
│   ├── Orientation/
│   │   ├── EtablissementModel.php
│   │   ├── FormationModel.php
│   │   ├── ActualiteModel.php
│   │   ├── DossierInscriptionModel.php
│   │   ├── PieceJustificativeModel.php
│   │   ├── ChampFormulaireModel.php
│   │   ├── RecepissModel.php
│   │   ├── PaiementOrientationModel.php
│   │   ├── ReversementModel.php
│   │   ├── EtablissementPaiementModel.php
│   │   ├── VisiteModel.php
│   │   └── NotificationOrientationModel.php
│
├── Services/
│   ├── Orientation/
│   │   ├── SlugService.php             # génération + unicité slug
│   │   ├── ChampFormulaireRenderer.php # rendu HTML formulaire depuis BDD
│   │   ├── PdfService.php             # génération reçus + récépissés (TCPDF/Dompdf)
│   │   ├── MoneyFusionService.php     # encaissement + payout API
│   │   ├── ReversementService.php     # logique reversement + idempotence
│   │   └── DocumentStorage.php        # upload/download Backblaze B2 (AWS SDK)
│
└── Views/
    ├── orientation/
    │   ├── hub/
    │   │   └── index.php              # hub (design orientation.html converti)
    │   ├── marketplace/
    │   │   ├── universites.php        # marketplace universités
    │   │   └── grandes_ecoles.php     # marketplace grandes écoles
    │   ├── etablissement/
    │   │   └── show.php               # gabarit multi-tenant (index.html branché)
    │   ├── candidature/
    │   │   ├── preinscription.php     # formulaire préinscription
    │   │   ├── preinscription_succes.php  # succès + téléchargement récépissé
    │   │   ├── inscription_etape1.php # infos personnelles
    │   │   ├── inscription_etape2.php # formation + dépôt pièces
    │   │   └── inscription_etape3.php # paiement + récapitulatif
    │   └── espace_eleve/
    │       ├── dashboard.php          # mes préinscriptions + inscriptions
    │       ├── dossier.php            # détail d'un dossier
    │       └── recu.php               # reçu PDF (lien B2 temporaire)
    ├── admin/
    │   └── orientation/
    │       ├── index.php              # liste tous les établissements
    │       ├── create.php             # formulaire création établissement
    │       ├── edit.php               # édition établissement
    │       └── reconciliation.php     # reversements à rejouer
    └── gestionnaire/
        ├── dashboard.php              # vue globale (stats + actions rapides)
        ├── formations/index.php       # liste + CRUD formations
        ├── actualites/index.php       # liste + CRUD blog
        ├── candidatures/index.php     # dossiers reçus (filtres en ligne / guichet)
        ├── candidatures/show.php      # détail dossier + pièces B2
        ├── formulaires/index.php      # config champs formulaire
        └── pieces/index.php           # config pièces requises
```

---

## 3. Base de données — Schéma complet

> Toutes les tables portent un `etablissement_id` filtré depuis la session, jamais depuis l'URL.

### 3.1 Script de migration (ordre à respecter)

```sql
-- ① Établissements (cœur multi-tenant)
CREATE TABLE etablissements (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  slug                  VARCHAR(120) UNIQUE NOT NULL,
  nom                   VARCHAR(200) NOT NULL,
  type                  ENUM('universite','grande_ecole','institut') NOT NULL,
  ville                 VARCHAR(100) NOT NULL,
  logo_cle              VARCHAR(255),            -- chemin B2
  couleur_principale    VARCHAR(7) DEFAULT '#1F4E79',
  statut                ENUM('actif','inactif','brouillon') DEFAULT 'brouillon',
  -- Hero
  label_campagne        VARCHAR(160),
  titre_accroche        VARCHAR(255),
  sous_titre            TEXT,
  date_limite_dossier   DATE,
  -- Présentation
  description           TEXT,
  video_url             VARCHAR(255),
  citation_texte        TEXT,
  citation_auteur       VARCHAR(150),
  citation_fonction     VARCHAR(150),
  -- Contact
  adresse               VARCHAR(255),
  latitude              DECIMAL(10,7),
  longitude             DECIMAL(10,7),
  email_contact         VARCHAR(150),
  telephone             VARCHAR(30),
  whatsapp              VARCHAR(30),
  -- Tarifs
  prix_preinscription   INT DEFAULT 0,
  prix_inscription      INT DEFAULT 0,
  frais_dossier         INT DEFAULT 0,
  infos_paiement        JSON,
  -- Flags candidature
  preinscription_active TINYINT DEFAULT 1,
  genere_recepisse      TINYINT DEFAULT 1,
  inscription_active    TINYINT DEFAULT 0,
  -- Listes flexibles JSON
  badges_confiance      JSON,
  atouts                JSON,
  statistiques          JSON,
  images_cles           JSON,
  date_creation         DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug),
  INDEX idx_statut (statut),
  INDEX idx_type (type)
);

-- ② Formations (cartes filières dans le gabarit)
CREATE TABLE formations (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  intitule          VARCHAR(200) NOT NULL,
  niveau            VARCHAR(60),
  icone             VARCHAR(40),
  duree_texte       VARCHAR(80),
  places_texte      VARCHAR(80),
  prix              INT DEFAULT 0,
  prix_unite        VARCHAR(20) DEFAULT 'FCFA/an',
  statut            ENUM('ouverte','bientot','fermee') DEFAULT 'ouverte',
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
);

-- ③ Actualités / Blog
CREATE TABLE actualites (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  titre             VARCHAR(200) NOT NULL,
  categorie         VARCHAR(60),
  date_publication  DATE,
  image_cle         VARCHAR(255),
  extrait           TEXT,
  contenu           LONGTEXT,
  a_la_une          TINYINT DEFAULT 0,
  statut            ENUM('publie','brouillon') DEFAULT 'brouillon',
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
);

-- ④ Dossiers d'inscription (moteur des candidatures)
CREATE TABLE dossiers_inscription (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  eleve_id                INT,                            -- FK users
  etablissement_id        INT NOT NULL,
  formation_id            INT,
  filiere_visee           ENUM('socle_l1_l2','licence_l3','master_m1_m2','certification'),
  niveau_entree           VARCHAR(60),
  -- Identité
  nom_complet             VARCHAR(200),
  genre                   ENUM('homme','femme','autre'),
  date_naissance          DATE,
  lieu_naissance          VARCHAR(120),
  nationalite             VARCHAR(80),
  -- Contact
  email                   VARCHAR(150),
  indicatif_tel           VARCHAR(8) DEFAULT '+241',
  telephone               VARCHAR(30),
  adresse                 VARCHAR(255),
  ville                   VARCHAR(120),
  pays_provenance         VARCHAR(80),
  dernier_etablissement   VARCHAR(200),
  -- Champs personnalisés école
  reponses_personnalisees JSON,
  -- Traçabilité
  origine                 ENUM('en_ligne','etablissement') DEFAULT 'en_ligne',
  cree_par_user_id        INT,
  statut                  ENUM('brouillon','soumis','paye','accepte','refuse') DEFAULT 'brouillon',
  date_creation           DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_eleve (eleve_id),
  INDEX idx_statut (statut),
  INDEX idx_origine (origine)
);

-- ⑤ Pièces justificatives requises (config par école + niveau)
CREATE TABLE pieces_justificatives_requises (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  niveau            VARCHAR(60),              -- NULL = tous niveaux
  libelle           VARCHAR(180) NOT NULL,
  obligatoire       TINYINT DEFAULT 1,
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
);

-- ⑥ Champs de formulaire configurables
CREATE TABLE champs_formulaire (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  type_formulaire   ENUM('preinscription','inscription') NOT NULL,
  section           VARCHAR(60),
  cle               VARCHAR(60),
  libelle           VARCHAR(120),
  type_champ        ENUM('text','email','tel','date','select','textarea','fichier'),
  options           JSON,
  obligatoire       TINYINT DEFAULT 1,
  actif             TINYINT DEFAULT 1,
  standard          TINYINT DEFAULT 1,
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab_type (etablissement_id, type_formulaire)
);

-- ⑦ Récépissés de préinscription (PDF B2)
CREATE TABLE recepisses (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  numero_recepisse  VARCHAR(50) UNIQUE,       -- PRE-2026-000123
  cle_pdf           VARCHAR(255),             -- chemin B2
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
);

-- ⑧ Documents déposés (pièces justificatives élèves dans B2)
CREATE TABLE documents_orientation (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id          INT NOT NULL,
  eleve_id            INT NOT NULL,
  piece_requise_id    INT,                    -- FK pieces_justificatives_requises
  libelle             VARCHAR(180),           -- copie au moment du dépôt
  cle_b2              VARCHAR(255) NOT NULL,
  mime_type           VARCHAR(80),
  taille_octets       INT,
  date_depot          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_dossier (dossier_id),
  INDEX idx_eleve (eleve_id)
);

-- ⑨ Journal d'accès documents (audit B2)
CREATE TABLE journal_acces_documents (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  document_id       INT NOT NULL,
  user_id           INT NOT NULL,
  action            ENUM('consultation','telechargement') DEFAULT 'consultation',
  ip                VARCHAR(45),
  date_acces        DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doc (document_id)
);

-- ⑩ Admins établissements (gestionnaires)
CREATE TABLE etablissement_admins (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  user_id           INT NOT NULL,
  role              ENUM('gestionnaire','agent') DEFAULT 'gestionnaire',
  date_creation     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  UNIQUE (etablissement_id, user_id),
  INDEX idx_user (user_id)
);

-- ⑪ Pré-inscriptions (table légère, le dossier porte les données)
CREATE TABLE preinscriptions (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  date_preinscription DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
);

-- ⑫ Inscriptions confirmées
CREATE TABLE inscriptions (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  etablissement_id  INT NOT NULL,
  date_inscription  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_etab (etablissement_id)
);

-- ⑬ Paiements (encaissement MoneyFusion + hors ligne)
CREATE TABLE paiements_orientation (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id              INT NOT NULL,
  etablissement_id        INT NOT NULL,
  montant                 INT NOT NULL,
  mode                    ENUM('en_ligne','espece','virement','mobile_money') DEFAULT 'en_ligne',
  token_pay               VARCHAR(100),       -- identifiant MoneyFusion
  statut                  ENUM('initie','en_attente','reussi','echoue') DEFAULT 'initie',
  enregistre_par_user_id  INT,                -- agent qui saisit paiement hors ligne
  date_paiement           DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_confirmation       DATETIME,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_statut (statut)
);

-- ⑭ Reçus PDF (post-paiement)
CREATE TABLE recus_orientation (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  paiement_id       INT NOT NULL UNIQUE,
  numero_recu       VARCHAR(50) UNIQUE,       -- REC-2026-000456
  cle_pdf           VARCHAR(255),
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id)
);

-- ⑮ Destination de reversement par école
CREATE TABLE etablissement_paiement (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id        INT NOT NULL UNIQUE,
  pays_code               VARCHAR(4) DEFAULT 'ga',
  withdraw_mode           VARCHAR(40),        -- airtel-ga | moov-ga
  numero_mobile_money     VARCHAR(30) NOT NULL,
  titulaire               VARCHAR(150),
  taux_commission         DECIMAL(5,2),       -- NULL = taux global plateforme
  statut                  ENUM('actif','inactif','en_attente') DEFAULT 'en_attente',
  date_connexion          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- ⑯ Reversements automatiques (suivi payout)
CREATE TABLE reversements (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  paiement_id           INT NOT NULL UNIQUE,  -- idempotence : 1 seul reversement/paiement
  etablissement_id      INT NOT NULL,
  montant_brut          INT NOT NULL,
  commission_plateforme INT NOT NULL DEFAULT 0,
  montant_reverse       INT NOT NULL,
  numero_destination    VARCHAR(30),
  withdraw_mode         VARCHAR(40),
  token_pay             VARCHAR(100),
  statut                ENUM('initie','en_cours','reussi','echoue','a_rejouer') DEFAULT 'initie',
  tentatives            INT DEFAULT 0,
  message_erreur        VARCHAR(255),
  date_initiation       DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_confirmation     DATETIME,
  FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_statut (statut)
);

-- ⑰ Visites par établissement
CREATE TABLE visites_etablissement (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  ip                VARCHAR(45),
  date_visite       DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_etab (etablissement_id)
);

-- ⑱ Notifications orientation
CREATE TABLE notifications_orientation (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  user_id           INT NOT NULL,
  etablissement_id  INT,
  type              VARCHAR(60),
  titre             VARCHAR(200),
  message           TEXT,
  lu                TINYINT DEFAULT 0,
  date_creation     DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
);
```

---

## 4. Routes à ajouter dans le routeur

```php
// ── FRONT PUBLIC (module Orientation) ──────────────────────────
GET  /orientation                           → OrientationHubController::index()
GET  /orientation/universites               → OrientationHubController::marketplace('universite')
GET  /orientation/grandes-ecoles            → OrientationHubController::marketplace('grande_ecole')
GET  /orientation/{slug}                    → EtablissementController::show($slug)

// ── CANDIDATURES ────────────────────────────────────────────────
GET  /orientation/{slug}/preinscription     → CandidatureController::preinscription($slug)
POST /orientation/{slug}/preinscription     → CandidatureController::preinscriptionSubmit($slug)
GET  /orientation/{slug}/preinscription/succes/{id} → CandidatureController::preinscriptionSucces($id)

GET  /orientation/{slug}/inscription/etape/1   → CandidatureController::inscriptionEtape1($slug)
POST /orientation/{slug}/inscription/etape/1   → CandidatureController::inscriptionEtape1Submit($slug)
GET  /orientation/{slug}/inscription/etape/2   → CandidatureController::inscriptionEtape2($slug)
POST /orientation/{slug}/inscription/etape/2   → CandidatureController::inscriptionEtape2Submit($slug)
GET  /orientation/{slug}/inscription/etape/3   → CandidatureController::inscriptionEtape3($slug)
POST /orientation/{slug}/inscription/etape/3/pay → CandidatureController::inscriptionPay($slug)

// ── WEBHOOKS MONEYFUSION ────────────────────────────────────────
POST /webhook/orientation/payin             → PaiementOrientationController::webhookPayin()
POST /webhook/orientation/payout            → PaiementOrientationController::webhookPayout()

// ── ESPACE ÉLÈVE ────────────────────────────────────────────────
GET  /eleve/orientation                     → EspaceEleveOrientationController::index()
GET  /eleve/orientation/dossier/{id}        → EspaceEleveOrientationController::dossier($id)
GET  /eleve/orientation/recu/{id}           → EspaceEleveOrientationController::recu($id)
GET  /eleve/orientation/document/{id}       → EspaceEleveOrientationController::document($id)

// ── SUPER-ADMIN ORIENTATION ─────────────────────────────────────
GET  /admin/orientation                     → EtablissementAdminController::index()
GET  /admin/orientation/creer              → EtablissementAdminController::create()
POST /admin/orientation/creer              → EtablissementAdminController::store()
GET  /admin/orientation/{id}/editer        → EtablissementAdminController::edit($id)
POST /admin/orientation/{id}/editer        → EtablissementAdminController::update($id)
POST /admin/orientation/{id}/statut        → EtablissementAdminController::updateStatut($id)
GET  /admin/orientation/reconciliation     → ReconciliationController::index()
POST /admin/orientation/reconciliation/{id}/rejouer → ReconciliationController::rejouer($id)

// ── GESTIONNAIRE ────────────────────────────────────────────────
GET  /gestionnaire                          → GestionnaireDashboardController::index()
// formations
GET  /gestionnaire/formations               → FormationController::index()
POST /gestionnaire/formations               → FormationController::store()
POST /gestionnaire/formations/{id}          → FormationController::update($id)
POST /gestionnaire/formations/{id}/delete   → FormationController::destroy($id)
// actualités
GET  /gestionnaire/actualites               → ActualiteController::index()
POST /gestionnaire/actualites               → ActualiteController::store()
// candidatures
GET  /gestionnaire/candidatures             → CandidatureGestionnaireController::index()
GET  /gestionnaire/candidatures/{id}        → CandidatureGestionnaireController::show($id)
POST /gestionnaire/candidatures/{id}/statut → CandidatureGestionnaireController::updateStatut($id)
GET  /gestionnaire/candidatures/creer       → CandidatureGestionnaireController::create()  // guichet
POST /gestionnaire/candidatures/creer       → CandidatureGestionnaireController::store()
// config formulaires
GET  /gestionnaire/formulaires              → FormulaireConfigController::index()
POST /gestionnaire/formulaires/{id}/toggle  → FormulaireConfigController::toggle($id)
// config pièces
GET  /gestionnaire/pieces                   → PieceRequiseController::index()
POST /gestionnaire/pieces                   → PieceRequiseController::store()
```

---

## 5. Intégration Redis

Redis cache :
1. **Page établissement** : données complètes `$etab` + formations + actualités → clé `orientation:etab:{slug}` — TTL 10 min, invalidé à chaque modification admin/gestionnaire
2. **Marketplace** : liste des cartes établissements actifs (par type) → clé `orientation:marketplace:{type}` — TTL 5 min
3. **Stats hub** : totaux établissements / préinscriptions → clé `orientation:stats:global` — TTL 30 min
4. **Session candidature** : état multi-étapes inscription → clé `orientation:dossier:{session_id}` — TTL 1h (reprise possible)

```php
// Exemple dans EtablissementController
public function show(string $slug): void {
    $cacheKey = "orientation:etab:{$slug}";
    $data = $this->redis->get($cacheKey);
    if (!$data) {
        $etab = EtablissementModel::findBySlug($slug);
        if (!$etab || $etab['statut'] !== 'actif') { $this->abort(404); }
        $data = [
            'etab'       => $etab,
            'formations' => FormationModel::byEtab($etab['id']),
            'actualites' => ActualiteModel::byEtab($etab['id'], 3),
        ];
        $this->redis->setex($cacheKey, 600, serialize($data));
    } else {
        $data = unserialize($data);
    }
    // Incrémenter visite (non mis en cache)
    VisiteModel::record($data['etab']['id'], $_SERVER['REMOTE_ADDR']);
    $this->view('orientation/etablissement/show', $data);
}
```

---

## 6. Services clés à implémenter

### 6.1 SlugService
```php
// app/Services/Orientation/SlugService.php
public static function generate(string $nom): string {
    $slug = strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $nom));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    // Vérifier unicité, ajouter -2, -3… si nécessaire
    return $slug;
}
```

### 6.2 ChampFormulaireRenderer
Charge les champs depuis `champs_formulaire` via PDO préparé, génère le HTML par `type_champ`. À la création d'un établissement, insère automatiquement le **jeu standard** (11 champs : prénom/nom, genre, date/lieu naissance, nationalité, email, téléphone, pays, ville, adresse, filière, dernier établissement).

### 6.3 PdfService
Génère les PDFs avec **TCPDF** ou **Dompdf** :
- **Récépissé** : `PRE-AAAA-xxxxxx` + logo établissement (via B2 signed URL) + couleur `--brand` + identité candidat + filière
- **Reçu de paiement** : `REC-AAAA-xxxxxx` + montant + formation + date

Upload résultant dans B2 (`recepisses/` ou `recus/`), stocke la clé en BDD.

### 6.4 MoneyFusionService
```php
// Encaissement : créer transaction, rediriger élève
public function initierPaiement(array $dossier, int $montant, string $callbackUrl): string
// Payout : reverser à l'école
public function reverserVersEcole(array $etablissementPaiement, int $montant, string $webhookUrl): string
// Validation webhook (signature)
public function validerWebhook(string $payload, string $signature): bool
```

### 6.5 ReversementService
Déclenché par le webhook pay-in confirmé :
1. Vérifier `reversements.paiement_id` n'existe pas (`UNIQUE` → idempotence)
2. Calculer `montant_reverse = montant_brut - commission`
3. Créer ligne `reversements` statut `initie`
4. Appeler `MoneyFusionService::reverserVersEcole()`
5. Stocker `token_pay`, passer statut `en_cours`
6. Webhook payout → `reussi` ou `echoue` / `a_rejouer`

### 6.6 DocumentStorage (B2)
Déjà décrit dans le Plan d'action (section 11). Intégration AWS SDK PHP pour B2 S3-compatible. Méthodes : `upload()`, `signedUrl(int $minuttes = 10)`, `delete()`. Validation vrai MIME + taille max avant upload.

---

## 7. Pages — Spécification détaillée

### 7.1 Hub `/orientation` (priorité #1)

**Objectif** : page d'accueil du module. Design de `orientation.html` conservé intégralement, rendu en PHP.

**Sections** :
1. **Header** → navigation Connect'Academia (layout existant)
2. **Hero** → background image Gabon, overlay, titre "Mon Orientation", tagline, search bar + tabs (Formations / Écoles / Métiers / Pays)  
   - Tabs "Formations" + "Métiers" + "Pays" = phases futures (désactivés visuellement avec tooltip "Bientôt")  
   - Tab "Écoles" → active, filtre les cartes ci-dessous
3. **Section établissements** → 2 cartes glass-morphism : "Universités" + "Grandes écoles" avec bouton Voir → liens vers marketplaces
4. **Section vidéo** → player HTML5 (video Connect'Academia)
5. **Section stats** → compteurs dynamiques depuis BDD (établissements actifs, préinscriptions, inscriptions)  
   → données depuis Redis `orientation:stats:global`
6. **Section Ambassador** → conservée telle quelle
7. **Footer** → footer existant Connect'Academia
8. **Popup** → SUPPRIMÉE (module live, plus "bientôt disponible")

### 7.2 Marketplace `/orientation/universites` et `/orientation/grandes-ecoles`

**Données** : `SELECT id, slug, nom, ville, logo_cle, couleur_principale, prix_inscription FROM etablissements WHERE statut='actif' AND type=? ORDER BY nom`

**Composants** :
- Header Connect'Academia
- Bannière titre (ex: "Universités du Gabon")
- Filtres : ville, fourchette de prix, search texte
- Grille de cartes (logo B2, nom, ville, type, prix min, bouton "Découvrir")  
  → Design cohérent avec orientation.html (même famille de cartes glass-morphism)
- Pagination ou infinite scroll (si >12 résultats)
- Footer

### 7.3 Page établissement `/orientation/{slug}`

Template `docs/gabarit/index.html` converti en `show.php`. Toutes les valeurs en dur remplacées par variables PHP (voir Plan d'action section 3.4). `<image-slot>` → `<img src="<?= $lienB2 ?>">`. Couleur `--brand` injectée dans `<head>`. Redis cache 10 min.

### 7.4 Préinscription `/orientation/{slug}/preinscription`

- Formulaire mono-page rendu par `ChampFormulaireRenderer` (type `preinscription`)
- Sections : Identité / Contact / Formation souhaitée
- Submit POST → validation serveur (champs `obligatoire=1`) → dossier `soumis` → récépissé PDF → B2 → succès
- Page succès : numéro récépissé + bouton téléchargement + bouton "Passer à l'inscription"

### 7.5 Inscription — 3 étapes `/orientation/{slug}/inscription`

**Étape 1 — Informations** (GET/POST)
- Formulaire `ChampFormulaireRenderer` (type `inscription`)
- Pré-remplissage si utilisateur connecté ou dossier préinscription existant
- Sauvegarde dossier `brouillon` en session Redis + BDD
- Barre de progression : étape 1/3

**Étape 2 — Formation & Dossier** (GET/POST)
- Choix filière (`filiere_visee`) + niveau (`niveau_entree`)
- Liste pièces justificatives depuis `pieces_justificatives_requises` WHERE `niveau=? OR niveau IS NULL`
- Upload fichier par fichier → `DocumentStorage::upload()` → ligne `documents_orientation`
- Validation : pièces `obligatoire=1` bloquantes (pas de bouton "Passer")
- Barre de progression : étape 2/3

**Étape 3 — Paiement** (GET/POST)
- Récapitulatif : identité + formation + prix
- Bouton "Payer" → `MoneyFusionService::initierPaiement()` → redirection MoneyFusion
- Webhook pay-in confirmé → dossier `paye` → `inscriptions` créée → reçu PDF → B2 → notification
- `ReversementService` déclenché → reversement auto école
- Barre de progression : étape 3/3

---

## 8. Plan de développement par phases

### Phase 1 — Fondations BDD + B2 (Semaine 1)
```
[ ] Exécuter le script de migration complet (section 3)
[ ] Vérifier FK, index, contrainte UNIQUE reversements.paiement_id
[ ] Configurer .env : B2_KEY_ID, B2_APPLICATION_KEY, B2_BUCKET, B2_ENDPOINT, B2_REGION
[ ] composer require aws/aws-sdk-php
[ ] Implémenter DocumentStorage.php (upload, signedUrl 10 min, delete)
[ ] Test B2 : upload PDF test → vérifier lien temporaire → supprimer test_b2.php
[ ] Livrable : BDD solide + B2 opérationnel
```

### Phase 2 — Restructuration front existant (Semaine 1-2)
```
[ ] Créer public/orientation/assets/ (extraire CSS/JS de orientation.html)
[ ] Convertir orientation.html → Views/orientation/hub/index.php (design intact)
[ ] Supprimer popup "bientôt disponible"
[ ] Convertir universites.html → Views/orientation/marketplace/universites.php
[ ] Convertir prives.html → Views/orientation/marketplace/grandes_ecoles.php
[ ] Câbler les routes GET /orientation, /universites, /grandes-ecoles
[ ] OrientationHubController::index() avec stats dynamiques + Redis
[ ] OrientationHubController::marketplace() avec filtres + Redis
[ ] Livrable : toutes les pages existantes rendues en PHP, même design
```

### Phase 3 — Insertion établissement (Super-admin) (Semaine 2)
```
[ ] EtablissementAdminController CRUD
[ ] SlugService (génération + unicité)
[ ] Formulaire création aligné sur sections gabarit (section 4 du Plan d'action)
[ ] Upload logo + images → DocumentStorage + B2
[ ] INSERT etablissements + formations + champs_formulaire standard (11 champs)
[ ] INSERT etablissement_admins + création compte gestionnaire (password_hash)
[ ] Email identifiants gestionnaire (PHPMailer)
[ ] Livrable : super-admin peut créer un établissement complet en 5 min
```

### Phase 4 — Page établissement multi-tenant (Semaine 2-3)
```
[ ] Convertir docs/gabarit/index.html → Views/orientation/etablissement/show.php
[ ] Remplacer toutes valeurs en dur par variables PHP (tableau section 3.4 du Plan)
[ ] Remplacer <image-slot> par <img src="<?= $signedUrl ?>">
[ ] Injecter --brand dans <head>
[ ] Boucles PHP : formations, actualités, badges JSON, atouts JSON, stats JSON
[ ] EtablissementController::show() avec Redis cache 10 min
[ ] Compteur visites (non mis en cache)
[ ] Routage /orientation/{slug} → 404 si inactif ou introuvable
[ ] Livrable : chaque école insérée a son site, design gabarit intact
```

### Phase 5 — Préinscription (Semaine 3)
```
[ ] ChampFormulaireRenderer : chargement BDD → génération HTML par type_champ
[ ] CandidatureController::preinscription() (GET : formulaire)
[ ] CandidatureController::preinscriptionSubmit() (POST : validation serveur)
[ ] Dossier statut 'soumis' + génération récépissé PDF (PdfService + B2)
[ ] Numérotation PRE-AAAA-xxxxxx (séquence en BDD)
[ ] Page succès : bouton téléchargement (lien B2 signé 10 min)
[ ] Livrable : préinscription gratuite de bout en bout + récépissé PDF
```

### Phase 6 — Inscription 3 étapes (Semaine 3-4)
```
[ ] Étape 1 : formulaire pré-rempli + sauvegarde Redis + BDD (statut brouillon)
[ ] Étape 2 : sélecteur filière/niveau + upload pièces dynamiques (B2) + validation obligatoire
[ ] Étape 3 : récapitulatif + intégration MoneyFusion (MoneyFusionService)
[ ] Webhook pay-in → dossier 'paye' → inscription créée → reçu PDF → notification
[ ] Livrable : inscription payante de bout en bout
```

### Phase 7 — Reversement automatique MoneyFusion (Semaine 4)
```
[ ] Config plateforme MoneyFusion dans .env (lien encaissement + clé payout)
[ ] Onboarding école : formulaire numéro mobile money → etablissement_paiement
[ ] ReversementService : calcul commission → POST /withdraw → stockage token_pay
[ ] Webhook payout → statut reussi/echoue/a_rejouer
[ ] Idempotence : contrainte UNIQUE vérifiée avant tout reversement
[ ] Écran réconciliation admin : liste reversements a_rejouer + bouton Rejouer
[ ] Activation inscription_active conditionnelle (etablissement_paiement.statut = 'actif')
[ ] Livrable : élève paie → école reçoit automatiquement (moins commission)
```

### Phase 8 — Tableaux de bord (Semaine 4-5)
```
[ ] Espace élève : mes dossiers (préinscriptions + inscriptions) + reçus + documents
[ ] Tableau de bord gestionnaire : stats + CRUD formations + actualités + candidatures
[ ] Saisie au guichet : gestionnaire crée dossier (origine='etablissement') + paiement hors ligne
[ ] Filtres par origine (en ligne / guichet) + badge agent
[ ] Config formulaires (toggle actif, réordonner, ajouter champ)
[ ] Config pièces requises (CRUD par niveau)
[ ] Tableau de bord super-admin : vue globale + gestion accès
[ ] Livrable : chaque acteur pilote son espace
```

### Phase 9 — Finitions & Sécurité (Semaine 5-6)
```
[ ] Audit filtrage etablissement_id sur TOUTES les requêtes tableaux de bord
[ ] htmlspecialchars() sur tout texte d'établissement affiché (revue systématique)
[ ] Test d'isolation : un gestionnaire NE PEUT PAS voir les données d'un autre
[ ] Notifications (email + BDD) : préinscription reçue, inscription confirmée, reversement
[ ] Journal d'audit : actions sensibles loggées
[ ] Tests de performance : indexes, EXPLAIN sur requêtes marketplace
[ ] Invalidation Redis sur toutes les modifications
[ ] Sécurité webhook MoneyFusion : validation signature HMAC
[ ] Changement numéro reversement : double confirmation + journal obligatoire
[ ] Livrable : module prêt pour mise en production
```

---

## 9. Sécurité — Points non négociables

| Risque | Protection |
|--------|-----------|
| Multi-tenant leak | `etablissement_id` toujours depuis `$_SESSION`, jamais `$_GET`/`$_POST` |
| Injection SQL | PDO `prepare()` + `bindParam()` systématique |
| XSS | `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` sur toute donnée affichée |
| CSRF | Token CSRF sur TOUS les formulaires POST |
| Accès documents B2 | `DocumentStorage::signedUrl()` uniquement, accès via `telecharger_document.php` qui vérifie les droits + journalise |
| Fichiers malveillants | Validation vrai MIME (finfo_file) + extension + taille max |
| Double reversement | `UNIQUE` sur `reversements.paiement_id` |
| Paiement falsifié | Validation webhook MoneyFusion côté serveur uniquement (signature HMAC) |
| Détournement reversement | Modification numéro mobile money = super-admin seulement + double confirmation + journal |
| Vol de session | `session_regenerate_id(true)` à l'auth + cookies `HttpOnly; Secure; SameSite=Strict` |
| Mots de passe | `password_hash()` Bcrypt + `password_verify()` |
| Secrets MoneyFusion | `.env` uniquement, hors dossier web, hors Git |

---

## 10. Conventions de code

```
Namespaces :
  App\Controllers\Orientation\
  App\Controllers\Admin\Orientation\
  App\Controllers\Gestionnaire\
  App\Models\Orientation\
  App\Services\Orientation\

Nommage :
  Controllers : CandidatureController, EtablissementController
  Modèles     : EtablissementModel (méthodes statiques PDO préparées)
  Services    : SlugService, PdfService, MoneyFusionService, ReversementService
  Vues        : snake_case (inscription_etape1.php)

Redis keys :
  orientation:etab:{slug}          TTL 600s
  orientation:marketplace:{type}   TTL 300s
  orientation:stats:global         TTL 1800s
  orientation:dossier:{session_id} TTL 3600s

Constantes .env à ajouter :
  B2_KEY_ID, B2_APPLICATION_KEY, B2_BUCKET, B2_ENDPOINT, B2_REGION
  MF_API_URL, MF_LINK_ENCAISSEMENT, MF_PRIVATE_KEY_PAYOUT, MF_WEBHOOK_SECRET
  PDF_LOGO_DEFAULT, PDF_SIGNATURE_SECRET
  COMMISSION_PLATEFORME_PERCENT (ex: 5 pour 5%)

⚠️ PÉRIMÈTRE B2 — RÈGLE ABSOLUE :
  Backblaze B2 est EXCLUSIVEMENT utilisé par le module Orientation.
  Aucun autre module Connect'Academia (cours, communauté, profil, etc.)
  ne doit écrire ou lire dans ce bucket.
  Fichiers stockés dans B2 : logos établissements, images actualités,
  pièces justificatives élèves, récépissés PDF, reçus PDF.
  Tout le reste de la plateforme utilise le stockage local (public/uploads/).
```

---

## 11. Checklist finale avant mise en production

```
BDD
[ ] Toutes les tables créées (18 tables)
[ ] FK + index vérifiés (EXPLAIN sur requêtes marketplace)
[ ] Contrainte UNIQUE reversements.paiement_id testée

B2
[ ] Bucket Private + SSE-B2 activé
[ ] Clé limitée au seul bucket
[ ] .env B2 hors dossier web + hors Git
[ ] DocumentStorage testé (upload → lien 10 min → refus après expiry)

Établissements
[ ] Création via admin testée de bout en bout
[ ] Slug unique généré automatiquement
[ ] Jeu standard de 11 champs formulaire inséré à la création
[ ] Email gestionnaire envoyé (PHPMailer)
[ ] Page établissement : --brand injectée, palette correcte
[ ] Aucun <image-slot> en production (remplacés par <img B2>)

Candidatures
[ ] Préinscription : récépissé PDF généré et stocké B2
[ ] Inscription étape 2 : pièces obligatoires bloquantes (pas de "Passer")
[ ] Inscription étape 3 : paiement validé uniquement par webhook
[ ] Reçu PDF généré et stocké B2
[ ] Session candidature persistée Redis (reprise possible)

Paiements & Reversements
[ ] Webhook pay-in : signature HMAC validée
[ ] Reversement déclenché uniquement après pay-in confirmé
[ ] Idempotence : double reversement impossible (UNIQUE testé)
[ ] Webhook payout : statut reussi/echoue mis à jour
[ ] Écran réconciliation : reversements a_rejouer listés et rejouables
[ ] Inscription payante désactivée si etablissement_paiement.statut != 'actif'

Sécurité
[ ] etablissement_id depuis session sur TOUTES les routes gestionnaire
[ ] htmlspecialchars() sur tout texte affiché — revue ligne par ligne
[ ] Test d'isolation : gestionnaire A ne voit PAS les données de B
[ ] Secrets MoneyFusion dans .env, IP VPS enregistrée chez MoneyFusion
[ ] HTTPS + cookies sécurisés activés

Design
[ ] Hub /orientation : design orientation.html intact, popup supprimée
[ ] Marketplace : cohérence design avec hub (cartes, couleurs, typo)
[ ] Page établissement : gabarit docs/gabarit/ intact, multi-tenant opérationnel
[ ] Responsive vérifié sur mobile (375px) + tablette (768px) + desktop
```

---

## 12. Ordre de priorité absolu (MVP août 2026)

| Priorité | Fonctionnalité |
|----------|---------------|
| 🔴 P0 | BDD + B2 + DocumentStorage |
| 🔴 P0 | Création établissement (admin) + page multi-tenant |
| 🔴 P0 | Hub + Marketplace (design conservé) |
| 🔴 P0 | Préinscription + récépissé PDF |
| 🟠 P1 | Inscription 3 étapes + paiement MoneyFusion |
| 🟠 P1 | Reversement automatique + réconciliation |
| 🟡 P2 | Tableau de bord gestionnaire complet |
| 🟡 P2 | Espace élève |
| 🟢 P3 | Blog/actualités établissement |
| 🟢 P3 | Saisie au guichet |
| 🟢 P3 | Notifications push |

---

*ROADMAP v1 — Connect'Academia Module Orientation — Juillet 2026*  
*Basé sur Plan_Action_Module_Orientation.md v8 + gabarit docs/gabarit/ + design orientation.html*
