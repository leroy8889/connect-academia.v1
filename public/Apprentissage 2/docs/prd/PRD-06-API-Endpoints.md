# 🔌 PRD-06 — API PHP : Endpoints
## Connect'Academia — Spécifications des APIs

> **Référence** : PRD principal v1.0 — Sections 12.2, 11
> **Usage Cursor** : Créer chaque fichier dans `/api/`. Toutes les réponses sont en JSON.

---

## 1. Conventions générales

### Format de réponse
```json
// Succès
{ "success": true, "data": { ... } }

// Erreur
{ "success": false, "error": "Message lisible" }
```

### En-têtes obligatoires (en haut de chaque fichier API)
```php
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();
```

### Vérification d'authentification dans les APIs protégées
```php
// Pour les routes élève
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

// Pour les routes admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Accès refusé']));
}
```

---

## 2. `/api/series.php`

### GET `/api/series.php`
Retourne toutes les séries actives.

**Auth** : Non requis (public)

**Réponse** :
```json
{
  "success": true,
  "data": [
    { "id": 1, "nom": "A1", "description": "Terminale A1 — Lettres et Langues", "couleur": "#8B52FA" },
    { "id": 5, "nom": "D",  "description": "Terminale D — Sciences de la Vie...", "couleur": "#2DC653" }
  ]
}
```

**SQL** :
```sql
SELECT id, nom, description, couleur FROM series WHERE is_active = 1 ORDER BY nom ASC
```

---

## 3. `/api/matieres.php`

### GET `/api/matieres.php?serie_id=5`
Retourne les matières d'une série avec le compteur de ressources.

**Auth** : Élève connecté

**Paramètres** : `serie_id` (obligatoire)

**Réponse** :
```json
{
  "success": true,
  "data": [
    {
      "id": 12, "nom": "SVT", "icone": "leaf", "ordre": 1,
      "nb_ressources": 8,
      "progression_moyenne": 42
    }
  ]
}
```

**SQL** :
```sql
SELECT m.id, m.nom, m.icone, m.ordre,
       COUNT(DISTINCT r.id) AS nb_ressources,
       COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
FROM matieres m
LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = :user_id
WHERE m.serie_id = :serie_id AND m.is_active = 1
GROUP BY m.id
ORDER BY m.ordre ASC
```

---

## 4. `/api/chapitres.php`

### GET `/api/chapitres.php?matiere_id=12`
Retourne les chapitres d'une matière.

**Auth** : Élève connecté

**Réponse** :
```json
{
  "success": true,
  "data": [
    { "id": 5, "titre": "Chapitre 1 — La cellule", "ordre": 1 }
  ]
}
```

---

## 5. `/api/ressources.php`

### GET `/api/ressources.php`
Retourne les ressources avec filtres optionnels.

**Auth** : Élève connecté

**Paramètres GET optionnels** :
- `matiere_id`
- `serie_id`
- `type` (`cours` | `td` | `ancienne_epreuve`)
- `chapitre_id`
- `search` (recherche sur le titre)
- `page` (pagination, défaut 1)
- `limit` (défaut 20)

**Réponse** :
```json
{
  "success": true,
  "data": {
    "ressources": [
      {
        "id": 42,
        "titre": "Cours — La mitose",
        "type": "cours",
        "matiere": "SVT",
        "chapitre": "Chapitre 2",
        "taille_fichier": 1240,
        "nb_vues": 87,
        "annee": null,
        "created_at": "2024-09-15",
        "progression": { "statut": "en_cours", "pourcentage": 45, "derniere_page": 12 },
        "est_favori": true
      }
    ],
    "total": 48,
    "page": 1,
    "pages_total": 3
  }
}
```

### POST `/api/ressources.php` (Admin uniquement)
Upload + création d'une ressource.

**Auth** : Admin connecté

**Body** : `multipart/form-data`
- `titre` (requis)
- `type` (requis)
- `serie_id` (requis)
- `matiere_id` (requis)
- `chapitre_id` (optionnel)
- `description` (optionnel)
- `annee` (optionnel)
- `fichier` (requis, PDF max 50 Mo)

**Logique PHP** :
```php
// Validation MIME
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['fichier']['tmp_name']);
if ($mimeType !== 'application/pdf') { /* erreur 400 */ }

// Renommage + déplacement
$newName = uniqid('ressource_', true) . '.pdf';
$path    = UPLOAD_PATH . $serie . '/' . $newName;
move_uploaded_file($_FILES['fichier']['tmp_name'], $path);

// INSERT en BDD
```

### DELETE `/api/ressources.php?id=42` (Admin uniquement)
Soft delete de la ressource.

```sql
UPDATE ressources SET is_deleted = 1 WHERE id = :id
```

---

## 6. `/api/progression.php`

### POST `/api/progression.php`
Gestion de la progression de lecture d'un PDF.

**Auth** : Élève connecté

**Body JSON** : `action` + données selon l'action

---

#### Action `start`
Démarre une session de révision.

```json
{ "action": "start", "ressource_id": 42 }
```

**PHP** :
```php
// Créer ou récupérer la progression
$stmt = $pdo->prepare("
    INSERT INTO progressions (user_id, ressource_id, statut, started_at)
    VALUES (?, ?, 'en_cours', NOW())
    ON DUPLICATE KEY UPDATE statut = 'en_cours', started_at = COALESCE(started_at, NOW())
");

// Créer une session de révision
$stmt = $pdo->prepare("
    INSERT INTO sessions_revision (user_id, ressource_id, debut) VALUES (?, ?, NOW())
");
// Stocker l'ID de session dans $_SESSION['session_revision_id']
```

---

#### Action `heartbeat`
Sauvegarde toutes les 30 secondes.

```json
{
  "action": "heartbeat",
  "ressource_id": 42,
  "temps": 120,
  "page_actuelle": 8,
  "total_pages": 24
}
```

**PHP** :
```php
$pourcentage = min(100, round(($page_actuelle / $total_pages) * 100));

$stmt = $pdo->prepare("
    UPDATE progressions
    SET temps_passe = :temps,
        derniere_page = :page,
        pourcentage = :pct,
        statut = IF(:pct >= 100, 'termine', 'en_cours'),
        completed_at = IF(:pct >= 100, NOW(), NULL)
    WHERE user_id = :uid AND ressource_id = :rid
");

// Mettre à jour la durée de la session en cours
$stmt = $pdo->prepare("
    UPDATE sessions_revision
    SET duree_secondes = :temps
    WHERE id = :session_id
");
```

---

#### Action `end`
Fermeture du viewer (beforeunload).

```json
{ "action": "end", "ressource_id": 42, "temps": 385, "page_actuelle": 15, "total_pages": 24 }
```

**PHP** : Même logique que heartbeat + `fin = NOW()` sur `sessions_revision`.

---

#### Action `complete`
Marquage manuel "Terminé".

```json
{ "action": "complete", "ressource_id": 42 }
```

```php
UPDATE progressions
SET statut = 'termine', pourcentage = 100, completed_at = NOW()
WHERE user_id = ? AND ressource_id = ?
```

---

## 7. `/api/favoris.php`

### POST `/api/favoris.php`
Toggle favori (ajoute si absent, retire si présent).

**Auth** : Élève connecté

**Body JSON** :
```json
{ "ressource_id": 42 }
```

**PHP** :
```php
// Vérifier si le favori existe
$exists = /* SELECT COUNT(*) */;

if ($exists) {
    // Supprimer
    $pdo->prepare("DELETE FROM favoris WHERE user_id = ? AND ressource_id = ?")->execute([$uid, $rid]);
    echo json_encode(['success' => true, 'action' => 'removed', 'est_favori' => false]);
} else {
    // Ajouter
    $pdo->prepare("INSERT INTO favoris (user_id, ressource_id) VALUES (?, ?)")->execute([$uid, $rid]);
    echo json_encode(['success' => true, 'action' => 'added', 'est_favori' => true]);
}
```

---

## 8. `/api/notifications.php`

### GET `/api/notifications.php`
Retourne les notifications de l'élève connecté.

**Auth** : Élève connecté

**Paramètre** : `?unread=1` pour seulement les non lues

```sql
SELECT * FROM notifications
WHERE (user_id = :uid OR user_id IS NULL)
ORDER BY created_at DESC
LIMIT 50
```

### POST `/api/notifications.php` (Admin)
Envoyer une notification.

**Body JSON** :
```json
{
  "titre": "Nouveaux cours SVT disponibles !",
  "message": "Des cours de SVT pour la série D ont été ajoutés.",
  "type": "nouvelle_ressource",
  "cible": "serie",
  "serie_id": 5
}
```

**Logique** :
- `cible = "tous"` : `user_id = NULL`
- `cible = "serie"` : `INSERT` pour chaque élève de la série
- `cible = "eleve"` : `INSERT` pour l'élève ciblé

### PATCH `/api/notifications.php`
Marquer comme lue(s).

```json
{ "action": "mark_read", "id": 12 }
// ou
{ "action": "mark_all_read" }
```

---

## 9. `/api/stats.php` (Admin uniquement)

### GET `/api/stats.php?type=dashboard`
Retourne les KPIs du dashboard admin.

```json
{
  "success": true,
  "data": {
    "total_eleves": 342,
    "ressources_publiees": 128,
    "vues_semaine": 1847,
    "temps_revision_total_heures": 2341
  }
}
```

### GET `/api/stats.php?type=inscriptions&days=30`
Données pour le graphique inscriptions.

```json
{
  "success": true,
  "data": [
    { "date": "2024-09-01", "count": 12 },
    { "date": "2024-09-02", "count": 8 }
  ]
}
```

### GET `/api/stats.php?type=series`
Activité par série.

---

## 10. `/api/auth.php`

### POST `/api/auth.php` — Action `check_email`
Vérifie si un email est déjà utilisé (inscription temps réel).

```json
{ "action": "check_email", "email": "eleve@example.com" }
```

**Réponse** :
```json
{ "success": true, "available": false }
```

---

## 11. Résumé des endpoints

| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/series.php` | Public | Liste séries actives |
| GET | `/api/matieres.php?serie_id=X` | Élève | Matières + progression |
| GET | `/api/chapitres.php?matiere_id=X` | Élève | Chapitres d'une matière |
| GET | `/api/ressources.php` | Élève | Ressources filtrées (pagination) |
| POST | `/api/ressources.php` | Admin | Upload + création ressource |
| DELETE | `/api/ressources.php?id=X` | Admin | Soft delete ressource |
| POST | `/api/progression.php` | Élève | Tracking progression PDF |
| POST | `/api/favoris.php` | Élève | Toggle favori |
| GET | `/api/notifications.php` | Élève | Liste notifications |
| POST | `/api/notifications.php` | Admin | Envoyer notification |
| PATCH | `/api/notifications.php` | Élève | Marquer comme lu |
| GET | `/api/stats.php` | Admin | Statistiques dashboard |
| POST | `/api/auth.php` | Public | Vérifications auth |

---

*PRD-06 API Endpoints — Connect'Academia v1.0*
