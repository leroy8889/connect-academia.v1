# PLAN D'INTÉGRATION PAIEMENT — Connect'Academia
**Passerelle : MoneyFusion · Plan mensuel 2 000 XAF · PHP MVC**
Version 1.0 · Mai 2026 · CTO : ONA-DAVID LEROY

---

## 1. Vue d'ensemble

### Objectif
Activer le système de paiement MoneyFusion. L'utilisateur dispose de **15 heures** d'accès gratuit après inscription. Passé ce délai, tout l'accès est bloqué (modules Apprentissage + Communauté) jusqu'à souscription d'un abonnement.

**Lancement v1 :**
| Plan | Prix | Disponibilité |
|---|---|---|
| Mensuel | 2 000 XAF / mois | ✅ Actif |
| Annuel | 15 000 XAF / an | ❌ Désactivé (lancement) |

### État actuel (ce qui existe déjà — à ne pas recréer)
| Élément | Statut | Notes |
|---|---|---|
| Table `abonnements` | ✅ Existe | Schéma complet |
| Table `transactions` | ✅ Existe | Schéma complet |
| `AbonnementController.php` | ✅ Existe | Stubs fonctionnels |
| `PaiementController.php` | ✅ Existe | Retourne NOT_IMPLEMENTED |
| `AbonneMiddleware.php` | ✅ Existe | Fonctionnel (jours) |
| `Models/Abonnement.php` | ✅ Existe | `getActif`, `creer` OK |
| Vues `abonnement/` | ✅ Existent | Placeholder "bientôt disponible" |
| Routes paiement/abonnement | ✅ Configurées | `config/routes.php` lignes 27-31 |
| Redis (`Core/Redis.php` + `Cache.php`) | ✅ Fonctionnel | Avec fallback silencieux |
| `Models/Transaction.php` | ❌ Absent | À créer |
| Admin paiement | ❌ Absent | Controller + vue à créer |

---

## 2. Architecture MoneyFusion — Points Critiques

### Comment fonctionne MoneyFusion
MoneyFusion **n'expose pas d'API REST classique** pour créer des sessions dynamiques.
Il fonctionne via un **lien de paiement statique** (checkout hébergé) :

```
Lien : https://my.moneyfusion.net/69f90337c24e38cb97f1d3e6
```

L'utilisateur remplit sur la page MoneyFusion :
- Nom complet
- Email (pour livraison / confirmation)
- Numéro WhatsApp / mobile

Moyens de paiement supportés : Orange Money, MTN Money, Moov Money, Wave, Visa, Mastercard

Après paiement → MoneyFusion envoie un **webhook POST** à notre URL configurée dans le dashboard.

### Problème : lier un paiement à un utilisateur
Le lien est statique — n'importe qui peut l'utiliser avec n'importe quel email.

**Solution retenue : Redis pending lock**
```
Avant redirection :
  → Créer transaction BDD {statut: en_attente, référence: CA-{userId}-{ts}-{rand}}
  → Stocker Redis: payment_pending:{userId} = {reference, email, plan} TTL 1800s

Dans le webhook :
  → Extraire email du payload
  → Trouver user par email en BDD
  → Vérifier payment_pending:{userId} existe dans Redis
  → Si tout OK → activer abonnement
```

**Garanties :**
1. Seul un user authentifié ayant cliqué "Payer" depuis notre app peut activer son abonnement
2. Window de 30 min pour compléter le paiement
3. L'email MoneyFusion doit correspondre au compte enregistré

> **Note technique :** Les champs exacts du webhook MoneyFusion (noms des propriétés JSON) ne sont pas documentés publiquement. Le handler logue le payload brut complet — ajuster les champs après réception du premier vrai webhook ou contact support MoneyFusion (info@moneyfusion.net).

---

## 3. Flux Complet

```
[User] tente d'accéder à /apprentissage ou /communaute
       │
       ▼
[AbonneMiddleware]
  ├─ Période gratuite (15h depuis created_at) non expirée → ACCÈS OK
  ├─ Abonnement actif en cache Redis (abonnement:{userId}) → ACCÈS OK
  ├─ Abonnement actif en BDD → ACCÈS OK + mise en cache Redis
  ├─ Abonnement expiré → /abonnement/renouveler
  └─ Aucun abonnement → /abonnement/choisir
       │
       ▼
[/abonnement/choisir]
  - Plan mensuel 2000 XAF → bouton actif
  - Plan annuel 15000 XAF → bouton désactivé "Bientôt disponible"
       │
       ▼ Clic "Payer 2000 XAF"
[JS fetch POST /api/paiement/initier] (auth + CSRF)
       │
       ▼
[PaiementController::initier()]
  1. Récupère userId + email depuis session
  2. Vérifie pas de payment_pending actif (anti-doublon)
  3. Génère reference: CA-{userId}-{timestamp}-{rand4}
  4. INSERT transaction {statut:en_attente, plan:mensuel, montant:2000, ref}
  5. SET Redis payment_pending:{userId} = {ref,email,plan} TTL 1800
  6. Retourne JSON {success:true, payment_url:"https://my.moneyfusion.net/69f90337c24e38cb97f1d3e6"}
       │
       ▼
[JS redirige vers l'URL MoneyFusion]
       │
       ▼
[Utilisateur paie sur MoneyFusion]
       │
       ├─────────────────────────────────────┐
       │                                     │
       ▼ (webhook background)               ▼ (redirect user)
[POST /api/paiement/callback]          [GET /paiement/retour]
  1. Lit raw body                        Vérifie abonnement actif
  2. Vérifie HMAC-SHA256                 Redirige → /abonnement/confirmation
  3. Parse JSON payload                  (si abonnement actif) ou /abonnement/choisir
  4. Extrait email + statut + ref
  5. Trouve user par email
  6. Vérifie Redis payment_pending:{id}
  7. Si statut = succès :
     - UPDATE transaction {statut:succes, aggregateur_ref, webhook_payload}
     - INSERT abonnement {plan:mensuel, +30 jours}
     - DEL Redis payment_pending:{userId}
     - DEL Cache Redis abonnement:{userId}
  8. Si statut ≠ succès :
     - UPDATE transaction {statut:echec}
     - DEL Redis payment_pending:{userId}
  9. HTTP 200 OK
       │
       ▼
[/abonnement/confirmation]
  Page félicitations + détails abonnement
  Bouton → /hub
```

---

## 4. Modifications détaillées — 9 étapes ordonnées

### ÉTAPE 1 : Configuration — `.env` + `AbonneMiddleware`
**Durée estimée : 20 min**

**`.env` — modifications :**
```dotenv
# Remplacer PERIODE_GRATUITE_JOURS par :
PERIODE_GRATUITE_HEURES=15

# Ajouter section MoneyFusion :
MONEYFUSION_PAYMENT_LINK=https://my.moneyfusion.net/69f90337c24e38cb97f1d3e6
MONEYFUSION_WEBHOOK_SECRET=     # À récupérer dans le dashboard MoneyFusion

# Prix (pour vérification côté serveur) :
PRIX_MENSUEL_XAF=2000
PRIX_ANNUEL_XAF=15000
```

**`app/Middleware/AbonneMiddleware.php` — modifier le calcul période gratuite :**
```php
// Avant :
$periodeJours = (int) ($_ENV['PERIODE_GRATUITE_JOURS'] ?? 1);
$expireAt     = $createdAt + ($periodeJours * 86400);

// Après :
$periodeHeures = (int) ($_ENV['PERIODE_GRATUITE_HEURES'] ?? 15);
$expireAt      = $createdAt + ($periodeHeures * 3600);
```

Ajouter aussi le **cache Redis** dans `AbonneMiddleware` :
```php
// Avant requête BDD, vérifier cache :
$cached = \Core\Cache::get("abonnement:{$userId}");
if ($cached !== null && $cached !== false) {
    $abonnement = $cached; // Utiliser cache si disponible
} else {
    $abonnement = (new \Models\Abonnement())->getActif($userId);
    if ($abonnement) {
        \Core\Cache::set("abonnement:{$userId}", $abonnement, 3600);
    }
}
```

**`database/001_schema_unifie.sql` — settings :**
```sql
-- Mettre à jour seed :
INSERT INTO settings (setting_key, setting_value) VALUES
('periode_gratuite_heures', '15'),   -- Remplace periode_gratuite_jours
('enable_paiement', '1'),            -- Activer paiement
...
```

---

### ÉTAPE 2 : Modèle `Transaction`
**Durée estimée : 30 min**
**Fichier à créer : `app/Models/Transaction.php`**

Méthodes nécessaires :
```php
namespace Models;

class Transaction extends BaseModel
{
    protected string $table = 'transactions';

    // Créer une transaction en_attente
    public function creer(int $userId, string $plan, float $montant, string $reference): int

    // Trouver par référence interne
    public function findByReference(string $reference): array|false

    // Trouver la dernière en_attente d'un user
    public function findEnAttente(int $userId): array|false

    // Mettre à jour statut + champs agrégateur
    public function mettreAJour(int $id, string $statut, ?string $agregRef = null, ?string $webhookPayload = null, ?string $methodePaiement = null): void

    // Historique user
    public function getByUser(int $userId, int $limit = 20): array

    // Admin : liste paginée avec filtres
    public function getAll(array $filters, int $limit, int $offset): array
    public function countAll(array $filters): int

    // Admin : total CA (transactions succes)
    public function totalCA(?string $periode = null): float
}
```

---

### ÉTAPE 3 : `PaiementController::initier()`
**Durée estimée : 30 min**
**Fichier : `app/Controllers/PaiementController.php`**

```php
public function initier(): void
{
    $userId = Session::userId();
    $user   = (new User())->findById($userId);

    // Rate limit : max 5 tentatives / 10 min / userId
    $rateLimiter = new \Core\RateLimiter();
    if (!$rateLimiter->allow("paiement_initier:{$userId}", 5, 600)) {
        Response::json(['success' => false, 'error' => ['code' => 'RATE_LIMIT', 'message' => 'Trop de tentatives']], 429);
    }

    // Anti-doublon : vérifier pas de payment_pending actif
    $redis = \Core\Redis::getInstance();
    if ($redis->isAvailable() && $redis->exists("payment_pending:{$userId}")) {
        // Pending déjà en cours → retourner le même lien
        Response::json(['success' => true, 'payment_url' => $_ENV['MONEYFUSION_PAYMENT_LINK']]);
    }

    // Générer référence unique
    $reference = sprintf('CA-%d-%d-%s', $userId, time(), strtoupper(bin2hex(random_bytes(2))));

    // Créer transaction en BDD
    $txId = (new \Models\Transaction())->creer($userId, 'mensuel', 2000.00, $reference);

    // Stocker dans Redis (TTL 30 min)
    if ($redis->isAvailable()) {
        $redis->set("payment_pending:{$userId}", json_encode([
            'reference' => $reference,
            'tx_id'     => $txId,
            'email'     => $user['email'],
            'plan'      => 'mensuel',
            'ts'        => time(),
        ]), 1800);
    }

    Response::json(['success' => true, 'payment_url' => $_ENV['MONEYFUSION_PAYMENT_LINK']]);
}
```

---

### ÉTAPE 4 : `PaiementController::callback()` — Webhook Handler
**Durée estimée : 1h**
**Fichier : `app/Controllers/PaiementController.php`**

C'est la partie la plus critique. Le handler doit être **idempotent** (safe si MoneyFusion envoie le webhook plusieurs fois).

```php
public function callback(): void
{
    // 1. Lire le raw body (essentiel pour HMAC)
    $rawBody = file_get_contents('php://input');
    error_log('[MF Webhook] Payload reçu: ' . $rawBody); // Log complet pour debug

    // 2. Vérification HMAC-SHA256 (si secret configuré)
    $secret = $_ENV['MONEYFUSION_WEBHOOK_SECRET'] ?? '';
    if (!empty($secret)) {
        $receivedSig = $_SERVER['HTTP_X_MONEYFUSION_SIGNATURE']
                    ?? $_SERVER['HTTP_X_SIGNATURE']
                    ?? '';
        $expectedSig = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expectedSig, $receivedSig)) {
            error_log('[MF Webhook] Signature invalide');
            http_response_code(403);
            exit;
        }
    }

    // 3. Parser le JSON
    $payload = json_decode($rawBody, true);
    if (!$payload) {
        error_log('[MF Webhook] JSON invalide');
        http_response_code(200); // Ne pas retourner 4xx pour éviter retry infini
        echo 'OK';
        exit;
    }

    // ⚠️  CHAMPS À AJUSTER selon doc réelle MoneyFusion
    // Ces noms de champs sont des estimations — vérifier avec le premier vrai webhook reçu
    $statut       = $payload['status']          ?? $payload['statut']          ?? '';
    $emailPayeur  = $payload['email']           ?? $payload['customer_email']  ?? '';
    $agregRef     = $payload['transaction_id']  ?? $payload['reference']       ?? '';
    $methode      = $payload['payment_method']  ?? $payload['method']          ?? null;
    $montantRecu  = (float) ($payload['amount'] ?? $payload['montant']         ?? 0);

    // 4. Trouver le user par email
    $userModel = new \Models\User();
    $user      = $userModel->findByEmail($emailPayeur);
    if (!$user) {
        error_log("[MF Webhook] User non trouvé pour email: {$emailPayeur}");
        http_response_code(200);
        echo 'OK';
        exit;
    }

    $userId    = (int) $user['id'];
    $txModel   = new \Models\Transaction();
    $abModel   = new \Models\Abonnement();
    $redis     = \Core\Redis::getInstance();

    // 5. Vérifier le pending Redis
    $pendingRaw = $redis->isAvailable() ? $redis->get("payment_pending:{$userId}") : null;
    $pending    = $pendingRaw ? json_decode($pendingRaw, true) : null;

    // 6. Trouver la transaction en_attente
    $transaction = $txModel->findEnAttente($userId);
    if (!$transaction) {
        error_log("[MF Webhook] Aucune transaction en_attente pour user {$userId}");
        http_response_code(200);
        echo 'OK';
        exit;
    }

    $txId = (int) $transaction['id'];

    // 7. Idempotence : ignorer si déjà traité
    if ($transaction['statut'] === 'succes') {
        error_log("[MF Webhook] Transaction {$txId} déjà traitée (idempotence)");
        http_response_code(200);
        echo 'OK';
        exit;
    }

    // 8. Traiter selon le statut du paiement
    // ⚠️  Valeurs exactes de statut à confirmer avec MoneyFusion (ex: "success", "SUCCESS", "paid", "completed")
    $estSucces = in_array(strtolower($statut), ['success', 'succes', 'paid', 'completed', 'successful'], true);

    if ($estSucces) {
        // Vérification montant (sécurité basique)
        $montantAttendu = (float) ($_ENV['PRIX_MENSUEL_XAF'] ?? 2000);
        if ($montantRecu > 0 && $montantRecu < $montantAttendu) {
            error_log("[MF Webhook] Montant insuffisant: reçu {$montantRecu}, attendu {$montantAttendu}");
            $txModel->mettreAJour($txId, 'echec', $agregRef, $rawBody);
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // Mettre à jour la transaction
        $txModel->mettreAJour($txId, 'succes', $agregRef, $rawBody, $methode);

        // Créer/prolonger l'abonnement (30 jours)
        $abonnementId = $abModel->creer($userId, 'mensuel', 30);

        // Lier abonnement à la transaction
        // (UPDATE transactions SET abonnement_id = ? WHERE id = ?)

        // Invalider Redis
        if ($redis->isAvailable()) {
            $redis->del("payment_pending:{$userId}");
            $redis->del("abonnement:{$userId}");
            // ↑ Force rechargement depuis BDD au prochain accès
        }

        // Notification optionnelle (type 'abonnement' dans notifications table)
        // TODO: INSERT INTO notifications (user_id, type, message) ...

        error_log("[MF Webhook] Abonnement activé pour user {$userId}");

    } else {
        // Paiement échoué ou annulé
        $txModel->mettreAJour($txId, 'echec', $agregRef, $rawBody);
        if ($redis->isAvailable()) {
            $redis->del("payment_pending:{$userId}");
        }
        error_log("[MF Webhook] Paiement échoué pour user {$userId}, statut: {$statut}");
    }

    http_response_code(200);
    echo 'OK';
}
```

---

### ÉTAPE 5 : Route retour post-paiement
**Durée estimée : 15 min**

MoneyFusion peut supporter un paramètre `returnUrl` dans l'URL de paiement (à tester/confirmer).
Si supporté, passer `?returnUrl=https://app.com/paiement/retour` dans le lien.

**Ajouter dans `config/routes.php` :**
```php
$router->get('/paiement/retour', 'PaiementController@retour', ['auth']);
```

**Méthode `retour()` dans `PaiementController` :**
```php
public function retour(): void
{
    $userId     = Session::userId();
    $abonnement = (new \Models\Abonnement())->getActif($userId);

    if ($abonnement) {
        Response::redirect('/abonnement/confirmation');
    } else {
        // Webhook pas encore reçu → attendre (polling côté client possible)
        Response::redirect('/abonnement/choisir?status=pending');
    }
}
```

---

### ÉTAPE 6 : Vues abonnement mises à jour
**Durée estimée : 45 min**

#### `app/Views/abonnement/choisir.php` — Réécrire
Points clés :
- Plan mensuel : bouton actif → déclenche `POST /api/paiement/initier` via `fetch()`, puis `window.location.href = payment_url`
- Plan annuel : bouton `disabled` + badge "Bientôt disponible"
- Afficher l'email de l'utilisateur + instruction "Utilisez cet email lors du paiement"
- Loader pendant l'appel AJAX
- Gestion d'erreur (rate limit, etc.)

```html
<!-- Plan mensuel — bouton JavaScript -->
<button id="btn-payer-mensuel" class="btn btn--primary btn--full" onclick="initierPaiement()">
  Payer 2 000 XAF / mois
</button>

<p class="hint">
  ⚠️ Utilisez l'adresse email <strong><?= htmlspecialchars($user['email']) ?></strong>
  lors du paiement pour lier votre abonnement.
</p>

<script>
async function initierPaiement() {
  const btn = document.getElementById('btn-payer-mensuel');
  btn.disabled = true;
  btn.textContent = 'Redirection...';
  try {
    const r = await fetch('/api/paiement/initier', {
      method: 'POST',
      headers: {'X-CSRF-Token': '<?= csrf_token() ?>', 'Content-Type': 'application/json'}
    });
    const data = await r.json();
    if (data.success) {
      window.location.href = data.payment_url;
    } else {
      alert('Erreur : ' + (data.error?.message ?? 'Réessayez'));
      btn.disabled = false;
      btn.textContent = 'Payer 2 000 XAF / mois';
    }
  } catch(e) {
    alert('Erreur réseau. Réessayez.');
    btn.disabled = false;
    btn.textContent = 'Payer 2 000 XAF / mois';
  }
}
</script>
```

#### `app/Views/abonnement/confirmation.php` — Enrichir
- Titre : "Abonnement activé !"
- Détails : plan, date de début, date d'expiration
- Récupérer abonnement depuis BDD pour afficher les dates réelles
- Bouton → /hub

#### `app/Views/abonnement/renouveler.php` — Mettre à jour
- Afficher la date d'expiration de l'ancien abonnement
- Lien direct → /abonnement/choisir

---

### ÉTAPE 7 : Admin — Module Paiement
**Durée estimée : 1h30**

#### `app/Controllers/Admin/PaiementController.php` — Créer

```php
namespace Controllers\Admin;

class PaiementController
{
    public function index(): void
    {
        // Filtres : statut, plan, date_debut, date_fin, q (search email/nom)
        // Pagination 25 par page
        // Stats en haut : total transactions, total CA succes, abonnements actifs
        // Liste transactions avec : ref, user (nom+email), plan, montant, statut badge, methode, created_at
        // Lien vers profil user admin
    }
}
```

#### `app/Views/admin/paiement/index.php` — Créer

Dashboard contient :
- **Cartes stats** :
  - Total transactions (toutes)
  - CA total (statut=succes, en XAF)
  - Abonnements actifs actuellement
  - Abonnements expirés ce mois
- **Tableau transactions** :
  - Colonnes : Référence | Utilisateur | Plan | Montant | Statut | Méthode | Date
  - Badge statut coloré : vert=succes, orange=en_attente, rouge=echec
  - Filtre par statut, date range
  - Clic référence → modal avec webhook_payload formaté (super_admin only)
- **Filtres** : statut, période (7j/30j/custom), recherche (email / nom)

#### Admin Users — Ajouter colonne abonnement
**Fichier modifié : `app/Views/admin/users/index.php`**

Ajouter dans le tableau utilisateurs :
- Colonne "Abonnement" : badge Actif (vert) / Expiré (rouge) / Gratuit (bleu) / Aucun (gris)
- Date d'expiration si actif
- Plan souscrit

**Fichier modifié : `app/Controllers/Admin/UsersController.php`**

Dans la requête SQL de `getAllUsers()`, ajouter un LEFT JOIN :
```sql
LEFT JOIN (
  SELECT user_id, plan, statut, fin
  FROM abonnements
  WHERE statut = 'actif' AND fin > NOW()
  ORDER BY fin DESC
  LIMIT 1
) AS ab ON ab.user_id = users.id
```

---

### ÉTAPE 8 : Routes — Ajouts
**Durée estimée : 10 min**
**Fichier : `config/routes.php`**

```php
// Ajouter :
$router->get('/paiement/retour',    'PaiementController@retour',       ['auth']);
$router->get('/admin/paiement',     'Admin\PaiementController@index',   ['admin']);
```

---

### ÉTAPE 9 : Settings BDD + `.env` prod
**Durée estimée : 10 min**

```sql
UPDATE settings SET setting_value = '1'  WHERE setting_key = 'enable_paiement';
UPDATE settings SET setting_value = '15' WHERE setting_key = 'periode_gratuite_jours';
-- Ou INSERT si la clé heures n'existe pas encore :
INSERT INTO settings (setting_key, setting_value) VALUES ('periode_gratuite_heures', '15');
```

---

## 5. Redis — Clés Paiement

| Clé Redis | Valeur | TTL | Rôle |
|---|---|---|---|
| `payment_pending:{userId}` | JSON `{ref, email, plan, ts}` | 1800s (30 min) | Lock paiement en cours + vérif webhook |
| `abonnement:{userId}` | JSON `{statut, plan, fin, days_left}` | 3600s (1h) | Cache statut abonnement pour middleware |

**Règle absolue :** Invalider `abonnement:{userId}` dès qu'un abonnement est créé ou modifié.

Ces clés s'ajoutent aux clés existantes documentées dans le CDC-FINAL.md section 9.

---

## 6. Sécurité

| Risque | Mitigation |
|---|---|
| Paiement frauduleux (faux email) | Redis pending lock : seul un user authentifié ayant initié depuis l'app peut activer |
| Replay webhook | Idempotence : si transaction déjà 'succes', ignorer silencieusement |
| Signature falsifiée | HMAC-SHA256 avec `MONEYFUSION_WEBHOOK_SECRET` (hash_equals constant-time) |
| Montant trafiqué | Vérification montant reçu ≥ montant attendu côté serveur |
| Injection webhook | Raw payload loggé, puis parsé via json_decode (pas eval, pas SQL concat) |
| Spam /api/paiement/initier | Rate limit Redis 5 tentatives / 10 min / userId |
| Double abonnement | findEnAttente() + idempotence + vérification Redis |
| CSRF sur initier | Token CSRF (déjà en place dans routes) |

---

## 7. Modèle de données — Traçabilité complète

### Table `transactions` — champs utilisés
```
reference          → CA-{userId}-{timestamp}-{rand4}  (ID interne unique)
aggregateur_ref    → ID transaction MoneyFusion (depuis webhook)
webhook_payload    → Corps complet du webhook (JSON brut, pour audit)
methode_paiement   → Orange Money / MTN / Wave / Visa / etc.
statut             → en_attente → succes | echec | rembourse
montant            → 2000.00
devise             → XAF
plan               → mensuel | annuel
created_at         → Heure initiation
updated_at         → Heure confirmation webhook
```

### Table `abonnements` — champs utilisés
```
user_id     → Utilisateur concerné
plan        → mensuel
statut      → actif | expire | en_attente
debut       → NOW() à la confirmation webhook
fin         → NOW() + 30 jours
```

### Vue admin — ce qui sera visible
- Toutes les transactions avec statut temps réel
- Qui a payé, quand, combien, avec quoi
- Lien bidirectionnel user ↔ transactions ↔ abonnements
- Webhook payload brut disponible (super_admin) pour debug
- Historique complet par utilisateur

---

## 8. Plan annuel désactivé — Implémentation

Dans `choisir.php` :
```html
<!-- Plan annuel : désactivé visuellement -->
<div class="plan-card plan-card--disabled">
  <span class="badge-soon">Bientôt disponible</span>
  <h3>Annuel</h3>
  <div class="price">15 000 <span>XAF</span></div>
  <button disabled class="btn btn--full">Bientôt disponible</button>
</div>
```

Pas de route backend pour le plan annuel. Si quelqu'un appelle `POST /api/paiement/initier` avec `plan=annuel` → rejeter avec `PLAN_UNAVAILABLE`.

---

## 9. Checklist Mise en Production

```
Configuration
[ ] Récupérer MONEYFUSION_WEBHOOK_SECRET depuis dashboard MoneyFusion
[ ] Mettre MONEYFUSION_WEBHOOK_SECRET dans .env prod
[ ] Mettre PERIODE_GRATUITE_HEURES=15 (pas 365)
[ ] Vérifier MONEYFUSION_PAYMENT_LINK correct dans .env
[ ] APP_URL = vraie URL de production

Dashboard MoneyFusion
[ ] Configurer URL webhook → https://{domain}/api/paiement/callback
[ ] Vérifier que MoneyFusion envoie bien un POST JSON au webhook
[ ] Tester avec un vrai paiement de test (1 XAF ou montant minimum)
[ ] Vérifier les logs PHP : voir le payload webhook brut reçu
[ ] Ajuster les noms de champs dans callback() selon payload réel reçu

Base de données
[ ] UPDATE settings SET setting_value = '1' WHERE setting_key = 'enable_paiement'
[ ] UPDATE settings SET setting_value = '15' WHERE setting_key = 'periode_gratuite_heures'
[ ] Vérifier tables abonnements + transactions créées

Tests fonctionnels
[ ] Test flux complet : inscription → accès 15h → blocage → paiement → confirmation
[ ] Test double webhook (idempotence)
[ ] Test webhook avec mauvais email → rien ne se passe
[ ] Test signature invalide → HTTP 403
[ ] Test admin /admin/paiement → tableau complet
[ ] Test admin /admin/utilisateurs → colonne abonnement visible
[ ] Test AbonneMiddleware avec cache Redis (valider que cache fonctionne)
[ ] Test rate limit /api/paiement/initier (5 appels rapides)

Redis
[ ] Confirmer Redis accessible en prod (redis-cli ping)
[ ] Vérifier invalidation cache abonnement:{userId} après activation
[ ] Vérifier TTL payment_pending:{userId} = 1800s après initiation
```

---

## 10. Ordre d'exécution — Estimation

| # | Fichier(s) | Action | Durée |
|---|---|---|---|
| 1 | `.env` | Ajouter clés MoneyFusion + PERIODE_GRATUITE_HEURES | 10 min |
| 2 | `AbonneMiddleware.php` | Passer de jours → heures + cache Redis | 20 min |
| 3 | `Models/Transaction.php` | Créer modèle complet | 30 min |
| 4 | `Models/User.php` | Ajouter `findByEmail()` si absent | 10 min |
| 5 | `PaiementController.php` | Implémenter `initier()` | 30 min |
| 6 | `PaiementController.php` | Implémenter `callback()` (webhook) | 1h |
| 7 | `PaiementController.php` | Ajouter `retour()` | 15 min |
| 8 | `Views/abonnement/choisir.php` | Réécrire avec JS fetch + instructions email | 45 min |
| 9 | `Views/abonnement/confirmation.php` | Enrichir avec détails abonnement | 20 min |
| 10 | `Controllers/Admin/PaiementController.php` | Créer dashboard admin | 45 min |
| 11 | `Views/admin/paiement/index.php` | Créer vue complète | 45 min |
| 12 | `Controllers/Admin/UsersController.php` | Ajouter JOIN abonnement | 20 min |
| 13 | `Views/admin/users/index.php` | Ajouter colonne abonnement | 20 min |
| 14 | `config/routes.php` | Ajouter 2 routes | 5 min |
| 15 | `database/` | Migration settings prod | 10 min |
| **Total** | | | **~6h** |

---

## 11. Questions à régler avec MoneyFusion

Avant de passer en production, contacter MoneyFusion (info@moneyfusion.net) pour confirmer :

1. **Format du webhook** : quels sont les noms exacts des champs JSON ? (`status`, `email`, `transaction_id`, `amount`...)
2. **Valeur du statut succès** : `"success"` ? `"SUCCESS"` ? `"paid"` ?
3. **Header signature** : quel header HTTP contient la signature HMAC ? (`X-Signature` ? `X-MoneyFusion-Signature` ?)
4. **URL de retour** : le lien supporte-t-il un paramètre `returnUrl=` ou `redirect_url=` pour rediriger l'utilisateur après paiement ?
5. **Clé webhook** : comment récupérer le `WEBHOOK_SECRET` dans le dashboard ?
6. **Environnement de test** : existe-t-il un mode sandbox pour tester sans vrai paiement ?

> En attendant les réponses : le handler logge le payload brut complet → premier vrai paiement → ajuster les champs dans `callback()`.

---

*Connect'Academia — Plan Paiement v1.0 — Mai 2026*
*Confidentiel — Gabon*
