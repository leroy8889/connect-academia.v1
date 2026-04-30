# 🔒 PRD-03 — Authentification & Sécurité
## Connect'Academia — Système d'Auth et Sécurité

> **Référence** : PRD principal v1.0 — Sections 14, 9.2, 9.3, 10.1
> **Usage Cursor** : Implémenter en priorité (Phase 1). Toutes les pages protégées dépendent de ce module.

---

## 1. Vue d'ensemble

Connect'Academia gère **deux espaces distincts** avec sessions séparées :
- **Espace Élève** : session basée sur `$_SESSION['user_id']`
- **Espace Admin** : session basée sur `$_SESSION['admin_id']`

Un élève connecté ne peut **jamais** accéder au back-office, et vice-versa.

---

## 2. Authentification Élève

### 2.1 Page Inscription — `/register.php`

**Formulaire** :

| Champ | Type HTML | Validation côté client | Validation côté serveur |
|---|---|---|---|
| Prénom | `text` | Requis, 2–50 chars | `filter_var` + longueur |
| Nom | `text` | Requis, 2–50 chars | `filter_var` + longueur |
| Email | `email` | Requis, format email | Unique en BDD |
| Mot de passe | `password` | Requis, min 8 chars | Min 8 chars |
| Confirmation MDP | `password` | Doit correspondre | Égalité avec password |
| Série | `select` | Requis | Valeur dans [A1,A2,B,C,D] |

**Comportement PHP** :
```php
// Hash du mot de passe
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Insertion en BDD (requête préparée)
$stmt = $pdo->prepare("
    INSERT INTO users (nom, prenom, email, password, serie_id)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$nom, $prenom, $email, $hash, $serie_id]);

// Création de session + redirection
$_SESSION['user_id']    = $pdo->lastInsertId();
$_SESSION['user_serie'] = $serie_id;
header('Location: /dashboard.php');
exit;
```

**Gestion erreurs** :
- Affichage d'erreurs inline sous chaque champ invalide
- Message "Cet email est déjà utilisé" si email dupliqué
- Toast SweetAlert2 en cas d'erreur serveur

---

### 2.2 Page Connexion — `/login.php`

**Formulaire** : Email + Mot de passe

**Comportement PHP** :
```php
session_start();
session_regenerate_id(true); // Sécurité : regénérer l'ID après login

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_serie'] = $user['serie_id'];
    $_SESSION['user_nom']   = $user['prenom'];

    // Mettre à jour last_login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
        ->execute([$user['id']]);

    header('Location: /dashboard.php');
    exit;
} else {
    $error = "Email ou mot de passe incorrect."; // Message volontairement vague
}
```

**Sécurités** :
- Message d'erreur générique (ne révèle pas si l'email existe)
- Protection brute force : throttling AJAX ou `sleep(1)` côté PHP

---

### 2.3 Déconnexion — `/logout.php`

```php
session_start();
session_destroy();
header('Location: /login.php');
exit;
```

---

## 3. Authentification Admin

### 3.1 Page Connexion Admin — `/admin/login.php`

**Design** : Fond sombre `#2D2D2D`, logo centré, minimal et élégant.

**Formulaire** : Email + Mot de passe

**Sécurités supplémentaires** :
- Rate limiting : **3 tentatives max** par IP en 15 minutes
- Compteur stocké en session : `$_SESSION['login_attempts']`

```php
// Rate limiting simple
if (($_SESSION['login_attempts'] ?? 0) >= 3) {
    http_response_code(429);
    die(json_encode(['error' => 'Trop de tentatives. Réessayez dans 15 minutes.']));
}

$stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_role'] = $admin['role'];
    unset($_SESSION['login_attempts']);
    header('Location: /admin/dashboard.php');
    exit;
} else {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $error = "Identifiants incorrects.";
}
```

---

### 3.2 Déconnexion Admin — `/admin/logout.php`

```php
session_start();
session_destroy();
header('Location: /admin/login.php');
exit;
```

---

## 4. Middleware de protection des routes

### `/includes/auth_check.php` — Pages élève
```php
<?php
require_once __DIR__ . '/config.php';
session_start();

// Timeout de session
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: /login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
```

### `/includes/admin_check.php` — Pages admin
```php
<?php
require_once __DIR__ . '/config.php';
session_start();

if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: /admin/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}
```

**Usage** : Inclure en première ligne de chaque page protégée :
```php
<?php require_once __DIR__ . '/includes/auth_check.php'; ?>
```

---

## 5. Protection CSRF

### Génération du token
```php
// Dans includes/helpers.php
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

### Intégration dans les formulaires
```html
<form method="POST" action="/api/auth.php">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <!-- ... -->
</form>
```

### Vérification côté PHP (en début d'API POST)
```php
function verifyCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Token CSRF invalide.']));
    }
}
```

---

## 6. Sécurité des données

### Hachage des mots de passe
```php
// Création
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Vérification
$valid = password_verify($inputPassword, $storedHash);
```

### Requêtes SQL — PDO préparées (obligatoire)
```php
// ✅ Correct
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ INTERDIT — jamais de concaténation SQL directe
$result = $pdo->query("SELECT * FROM users WHERE email = '$email'");
```

### Protection XSS
```php
// Dans includes/helpers.php
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
```

Utilisation dans les vues :
```html
<p><?= e($user['nom']) ?></p>
```

---

## 7. Upload sécurisé de PDF

```php
// Validation MIME type réel (pas juste l'extension)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['fichier']['tmp_name']);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Seuls les fichiers PDF sont acceptés.']));
}

// Validation taille
if ($_FILES['fichier']['size'] > UPLOAD_MAX_SIZE) {
    die(json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 50 Mo).']));
}

// Renommage aléatoire pour éviter les conflits et l'exécution de code
$newName    = uniqid('ressource_', true) . '.pdf';
$uploadPath = UPLOAD_PATH . $serie . '/' . $newName;

move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadPath);
```

---

## 8. En-têtes de sécurité HTTP

Ajouter dans `.htaccess` ou dans un fichier `includes/security_headers.php` :

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

---

## 9. Règles métier — Authentification

1. Un élève **inactif** (`is_active = 0`) ne peut pas se connecter, même avec les bons identifiants.
2. Les **sessions élève et admin** sont totalement indépendantes (variables de session différentes).
3. Le **timeout de session** est de **2 heures** d'inactivité.
4. Après login, l'ID de session est **régénéré** (`session_regenerate_id(true)`) pour prévenir la fixation de session.
5. La **déconnexion** détruit complètement la session (`session_destroy()`).
6. Les mots de passe sont **toujours hashés en bcrypt** avec coût 12, jamais stockés en clair.

---

*PRD-03 Authentification & Sécurité — Connect'Academia v1.0*
