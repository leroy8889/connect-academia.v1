# 🗄️ PRD-02 — Base de Données MySQL
## Connect'Academia — Modèle de Données Complet

> **Référence** : PRD principal v1.0 — Section 8, Annexe A
> **Usage Cursor** : Créer le fichier `database/schema.sql` et `database/seed.sql` à partir de ce document.

---

## 1. Informations générales

- **SGBD** : MySQL 8.x
- **Encodage** : `utf8mb4` (support emojis)
- **Connexion** : PDO avec requêtes préparées exclusivement
- **Nom de la base** : `connect_academia`

---

## 2. Schéma des tables (`database/schema.sql`)

### 2.1 Table `series`
```sql
CREATE TABLE series (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(10) NOT NULL,           -- A1, A2, B, C, D
    description TEXT DEFAULT NULL,
    couleur     VARCHAR(7) DEFAULT '#8B52FA',   -- couleur hex pour l'UI
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 2.2 Table `users` (Élèves)
```sql
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,           -- bcrypt, coût 12
    serie_id   INT NOT NULL,
    avatar     VARCHAR(255) DEFAULT NULL,
    is_active  TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);
```

### 2.3 Table `admins`
```sql
CREATE TABLE admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,           -- bcrypt
    role       ENUM('super_admin', 'admin') DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 2.4 Table `matieres`
```sql
CREATE TABLE matieres (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    icone     VARCHAR(50) DEFAULT 'book',        -- nom d'icône Lucide Icons
    serie_id  INT NOT NULL,
    ordre     INT DEFAULT 0,                     -- ordre d'affichage
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);
```

### 2.5 Table `chapitres`
```sql
CREATE TABLE chapitres (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titre      VARCHAR(200) NOT NULL,
    matiere_id INT NOT NULL,
    ordre      INT DEFAULT 0,
    is_active  TINYINT(1) DEFAULT 1,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id)
);
```

### 2.6 Table `ressources`
```sql
CREATE TABLE ressources (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    titre          VARCHAR(255) NOT NULL,
    description    TEXT DEFAULT NULL,
    type           ENUM('cours', 'td', 'ancienne_epreuve') NOT NULL,
    fichier_path   VARCHAR(500) NOT NULL,         -- chemin relatif : /uploads/ressources/D/maths/ressource_xxx.pdf
    fichier_nom    VARCHAR(255) NOT NULL,          -- nom original du fichier uploadé
    taille_fichier INT DEFAULT 0,                 -- taille en Ko
    matiere_id     INT NOT NULL,
    chapitre_id    INT DEFAULT NULL,              -- optionnel
    serie_id       INT NOT NULL,
    annee          YEAR DEFAULT NULL,             -- pour les anciennes épreuves uniquement
    admin_id       INT NOT NULL,
    nb_vues        INT DEFAULT 0,
    is_deleted     TINYINT(1) DEFAULT 0,          -- soft delete
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matiere_id)  REFERENCES matieres(id),
    FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
    FOREIGN KEY (serie_id)    REFERENCES series(id),
    FOREIGN KEY (admin_id)    REFERENCES admins(id)
);
```

### 2.7 Table `progressions`
```sql
CREATE TABLE progressions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    ressource_id  INT NOT NULL,
    statut        ENUM('non_commence', 'en_cours', 'termine') DEFAULT 'non_commence',
    pourcentage   INT DEFAULT 0,                  -- 0 à 100
    temps_passe   INT DEFAULT 0,                  -- total cumulé en secondes
    derniere_page INT DEFAULT 1,                  -- dernière page PDF consultée
    started_at    DATETIME DEFAULT NULL,
    completed_at  DATETIME DEFAULT NULL,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_ressource (user_id, ressource_id),
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 2.8 Table `sessions_revision`
```sql
CREATE TABLE sessions_revision (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    ressource_id    INT NOT NULL,
    debut           DATETIME NOT NULL,
    fin             DATETIME DEFAULT NULL,
    duree_secondes  INT DEFAULT 0,               -- durée calculée à la fermeture
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 2.9 Table `favoris`
```sql
CREATE TABLE favoris (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    ressource_id INT NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favori (user_id, ressource_id),
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);
```

### 2.10 Table `notifications`
```sql
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,                  -- NULL = notification globale (tous les élèves)
    titre      VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('info', 'success', 'warning', 'nouvelle_ressource') DEFAULT 'info',
    lu         TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 3. Index de performance

```sql
-- Filtrage fréquent
CREATE INDEX idx_ressources_serie     ON ressources(serie_id);
CREATE INDEX idx_ressources_matiere   ON ressources(matiere_id);
CREATE INDEX idx_ressources_type      ON ressources(type);
CREATE INDEX idx_progressions_user    ON progressions(user_id);
CREATE INDEX idx_sessions_user        ON sessions_revision(user_id);
CREATE INDEX idx_notifications_user   ON notifications(user_id, lu);
CREATE INDEX idx_matieres_serie       ON matieres(serie_id);
CREATE INDEX idx_chapitres_matiere    ON chapitres(matiere_id);
```

---

## 4. Données initiales (`database/seed.sql`)

### 4.1 Séries
```sql
INSERT INTO series (nom, description, couleur) VALUES
('A1', 'Terminale A1 — Lettres et Langues',            '#8B52FA'),
('A2', 'Terminale A2 — Lettres et Sciences Humaines',  '#6C3FC9'),
('B',  'Terminale B — Sciences Économiques',           '#4A90D9'),
('C',  'Terminale C — Mathématiques et Physique',      '#E85D04'),
('D',  'Terminale D — Sciences de la Vie et de la Terre', '#2DC653');
```

### 4.2 Matières par série

```sql
-- === SÉRIE A1 ===
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques',      'calculator',   1, 1),
('Français / Littérature', 'book-open', 1, 2),
('Philosophie',        'lightbulb',    1, 3),
('Anglais (LV1)',      'globe',        1, 4),
('Histoire-Géographie','map',          1, 5),
('SVT',                'leaf',         1, 6),
('LV2 Espagnol',       'languages',    1, 7);

-- === SÉRIE A2 ===
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques',      'calculator',   2, 1),
('Français / Littérature', 'book-open', 2, 2),
('Philosophie',        'lightbulb',    2, 3),
('Anglais (LV1)',      'globe',        2, 4),
('Histoire-Géographie','map',          2, 5),
('SVT',                'leaf',         2, 6),
('Économie / Gestion', 'trending-up',  2, 7),
('LV2 Espagnol',       'languages',    2, 8);

-- === SÉRIE B ===
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Économie / Gestion', 'trending-up',  3, 1),
('Mathématiques',      'calculator',   3, 2),
('Philosophie',        'lightbulb',    3, 3),
('Français / Littérature', 'book-open', 3, 4),
('Anglais (LV1)',      'globe',        3, 5),
('Histoire-Géographie','map',          3, 6),
('Comptabilité',       'receipt',      3, 7),
('LV2 Espagnol',       'languages',    3, 8);

-- === SÉRIE C ===
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques',      'calculator',   4, 1),
('Physique-Chimie',    'flask-conical', 4, 2),
('SVT',                'leaf',         4, 3),
('Philosophie',        'lightbulb',    4, 4),
('Français / Littérature', 'book-open', 4, 5),
('Anglais (LV1)',      'globe',        4, 6);

-- === SÉRIE D ===
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('SVT',                'leaf',         5, 1),
('Mathématiques',      'calculator',   5, 2),
('Physique-Chimie',    'flask-conical', 5, 3),
('Philosophie',        'lightbulb',    5, 4),
('Français / Littérature', 'book-open', 5, 5),
('Anglais (LV1)',      'globe',        5, 6);
```

### 4.3 Admin par défaut
```sql
-- Mot de passe : Admin@2024 (à changer immédiatement)
INSERT INTO admins (nom, prenom, email, password, role) VALUES
('Admin', 'Connect', 'admin@connectacademia.ga',
 '$2y$12$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', -- bcrypt généré
 'super_admin');
```

---

## 5. Référentiel matières par série

| Matière | A1 | A2 | B | C | D |
|---|:---:|:---:|:---:|:---:|:---:|
| Mathématiques | ✓ | ✓ | ✓ | ✓ | ✓ |
| Français / Littérature | ✓ | ✓ | ✓ | ✓ | ✓ |
| Philosophie | ✓ | ✓ | ✓ | ✓ | ✓ |
| Anglais (LV1) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Histoire-Géographie | ✓ | ✓ | ✓ | — | — |
| SVT | ✓ | ✓ | — | ✓ | ✓ |
| Physique-Chimie | — | — | — | ✓ | ✓ |
| Économie / Gestion | — | ✓ | ✓ | — | — |
| LV2 Espagnol | ✓ | ✓ | ✓ | — | — |
| Comptabilité | — | — | ✓ | — | — |

---

## 6. Règles métier liées aux données

1. **Soft delete** sur `ressources` : ne jamais supprimer physiquement une ressource, utiliser `is_deleted = 1`. Les progressions associées sont conservées.
2. **Contrainte UNIQUE** sur `progressions(user_id, ressource_id)` : une seule entrée par élève/ressource, mise à jour par `INSERT ... ON DUPLICATE KEY UPDATE`.
3. **Contrainte UNIQUE** sur `favoris(user_id, ressource_id)` : toggle géré côté PHP.
4. **Notifications globales** : `user_id = NULL` signifie visible par tous les élèves.
5. **Progression %** calculée par : `(pages vues / total pages PDF) * 100`, arrondi à l'entier.
6. **Statut `termine`** : déclenché quand `pourcentage = 100` OU clic manuel "Marquer comme terminé".

---

*PRD-02 Base de Données — Connect'Academia v1.0*
