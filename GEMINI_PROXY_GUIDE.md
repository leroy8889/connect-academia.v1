# Guide : Proxy Gemini API pour VPS bloqué géographiquement

## Contexte du problème

Ton VPS a l'IP `85.190.96.109`. Google détecte que cette IP vient d'une région
non autorisée (l'API Gemini n'est pas disponible au Gabon et dans certaines zones).
Résultat : chaque requête vers `generativelanguage.googleapis.com` retourne une
erreur 403 ou une connexion refusée.

**Le chatbot BACY** dans `IaController.php` (ligne 130) appelle directement
l'API Gemini depuis ton VPS → ça échoue.

**La solution** : faire passer les requêtes par un serveur proxy dans un pays autorisé
(France, USA, etc.) avant qu'elles atteignent Google.

```
[VPS Gabon] ──→ [Proxy FR/USA] ──→ [API Gemini Google] ──→ retour
```

---

## Architecture de la solution

Il y a 3 options, de la plus simple à la plus robuste :

| Option | Description | Coût | Complexité |
|--------|-------------|------|------------|
| **A** | Variable `GEMINI_API_URL` vers un proxy Workers Cloudflare | Gratuit | Faible |
| **B** | Serveur proxy PHP sur un VPS secondaire (OVH France) | ~3€/mois | Moyenne |
| **C** | Proxy via un service dédié (Nginx + IP résidentielle) | Variable | Haute |

**Recommandation : Option A** — Cloudflare Workers est gratuit (100 000 req/jour),
déployable en 10 minutes, et ton code PHP n'a besoin que d'un changement de variable.

---

## Option A — Cloudflare Workers (recommandée)

### Comment ça fonctionne

Cloudflare Workers est un runtime JavaScript qui s'exécute sur les serveurs de
Cloudflare dans le monde entier. Tu crées un "worker" (un petit script JS) qui :

1. Reçoit ta requête depuis le VPS
2. La transmet à l'API Gemini de Google (depuis un datacenter USA/Europe)
3. Renvoie la réponse à ton VPS

Google voit l'IP de Cloudflare (pays autorisé), pas ton IP gabonaise.

### Étape 1 — Créer un compte Cloudflare

1. Va sur [https://dash.cloudflare.com/sign-up](https://dash.cloudflare.com/sign-up)
2. Crée un compte gratuit (email + mot de passe)
3. Pas besoin d'ajouter un domaine pour les Workers

### Étape 2 — Créer le Worker proxy

1. Dans le dashboard Cloudflare, clique sur **"Workers & Pages"** dans le menu gauche
2. Clique **"Create application"** → **"Create Worker"**
3. Donne un nom, par exemple : `gemini-proxy`
4. Clique **"Deploy"** (déploie le code par défaut d'abord)
5. Clique ensuite **"Edit code"**

Remplace **tout le code** par ceci :

```javascript
export default {
  async fetch(request, env) {

    // Sécurité : vérifie que la requête vient bien de ton serveur
    const secret = request.headers.get('X-Proxy-Secret');
    if (secret !== env.PROXY_SECRET) {
      return new Response('Unauthorized', { status: 401 });
    }

    // Récupère la clé API Gemini depuis les variables d'environnement du Worker
    const geminiKey = env.GEMINI_API_KEY;
    if (!geminiKey) {
      return new Response('API key not configured', { status: 500 });
    }

    // Reconstruit l'URL vers Google Gemini
    const url = new URL(request.url);
    const geminiUrl = 'https://generativelanguage.googleapis.com' + url.pathname + url.search;

    // Clone la requête vers Gemini
    const proxyRequest = new Request(geminiUrl, {
      method: request.method,
      headers: {
        'Content-Type': 'application/json',
      },
      body: request.method !== 'GET' ? request.body : undefined,
    });

    // Envoie la requête à Google et retourne la réponse
    const response = await fetch(proxyRequest);
    return new Response(response.body, {
      status: response.status,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
      },
    });
  },
};
```

Clique **"Save and Deploy"**.

### Étape 3 — Configurer les variables d'environnement du Worker

Ce Worker a besoin de 2 secrets :

1. Dans la page de ton Worker, va dans **"Settings"** → **"Variables and Secrets"**
2. Clique **"Add variable"** pour chaque :

| Variable | Valeur |
|----------|--------|
| `GEMINI_API_KEY` | Ta clé API Gemini (celle dans ton `.env`) |
| `PROXY_SECRET` | Un mot de passe secret que tu inventes, ex: `ca_proxy_2026_xaf` |

⚠️ **Pour `GEMINI_API_KEY` et `PROXY_SECRET`** : utilise le type **"Secret"** (pas
"Text") pour qu'ils soient chiffrés.

3. Clique **"Deploy"** pour appliquer.

### Étape 4 — Noter l'URL de ton Worker

Après déploiement, ton Worker aura une URL du type :

```
https://gemini-proxy.TON-COMPTE.workers.dev
```

Note cette URL, tu en auras besoin à l'étape suivante.

### Étape 5 — Modifier le `.env` du VPS

Sur ton VPS, ouvre le fichier `.env` du projet et modifie (ou ajoute) ces lignes :

```dotenv
# Avant (appel direct bloqué) :
# GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent

# Après (via le proxy Cloudflare) :
GEMINI_API_URL=https://gemini-proxy.TON-COMPTE.workers.dev/v1beta/models/gemini-2.5-flash:generateContent
GEMINI_PROXY_SECRET=ca_proxy_2026_xaf
```

Remplace `TON-COMPTE` par ton sous-domaine Cloudflare réel.
Remplace `ca_proxy_2026_xaf` par le secret que tu as choisi à l'étape 3.

### Étape 6 — Modifier IaController.php

Le controller doit envoyer le header secret au proxy. Voici le seul bloc à modifier.

**Fichier** : `app/Controllers/Apprentissage/IaController.php`

**Avant** (lignes 136–148 environ) :

```php
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);
```

**Après** :

```php
$ch = curl_init($apiUrl);

// Headers : ajoute le secret proxy si configuré
$headers = ['Content-Type: application/json'];
$proxySecret = $_ENV['GEMINI_PROXY_SECRET'] ?? '';
if ($proxySecret !== '') {
    $headers[] = 'X-Proxy-Secret: ' . $proxySecret;
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);
```

### Étape 7 — Tester

Depuis ton VPS, teste avec curl :

```bash
curl -s -X POST \
  "https://gemini-proxy.TON-COMPTE.workers.dev/v1beta/models/gemini-2.5-flash:generateContent?key=TA_CLE_GEMINI" \
  -H "Content-Type: application/json" \
  -H "X-Proxy-Secret: ca_proxy_2026_xaf" \
  -d '{"contents":[{"role":"user","parts":[{"text":"Dis bonjour"}]}]}' \
  | head -c 500
```

Si tu reçois une réponse JSON avec du texte → **ça fonctionne**.
Si tu reçois `Unauthorized` → le secret ne correspond pas, vérifie les variables Cloudflare.
Si tu reçois une erreur 403 de Google → la clé API est invalide ou le quota est dépassé.

---

## Option B — Proxy PHP sur VPS secondaire (si Cloudflare ne suffit pas)

Si tu veux maîtriser 100% de l'infrastructure, loue un petit VPS en France
(OVH VPS Starter ~3€/mois) et déploie ce proxy PHP.

### proxy.php (à placer sur le VPS français)

```php
<?php
// Fichier : proxy.php sur VPS France
// Protège avec un secret partagé

$secret = 'ca_proxy_2026_xaf'; // Même valeur que dans .env du VPS Gabon

if (($_SERVER['HTTP_X_PROXY_SECRET'] ?? '') !== $secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$apiKey = 'TA_CLE_GEMINI_ICI'; // Stocke ici ou dans un .env sur ce VPS
$geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$body = file_get_contents('php://input');

$ch = curl_init($geminiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
```

Et dans le `.env` du VPS Gabon :

```dotenv
GEMINI_API_URL=https://ton-vps-france.com/proxy.php
GEMINI_PROXY_SECRET=ca_proxy_2026_xaf
```

La modification de `IaController.php` est identique à l'Option A (Étape 6).

---

## Sécurité — Points importants

1. **Ne jamais exposer la clé Gemini** dans le code JS du Worker ou dans une URL
   publique. Elle doit toujours rester dans les variables d'environnement chiffrées.

2. **Le secret `PROXY_SECRET`** empêche n'importe qui d'utiliser ton proxy pour
   appeler Gemini à tes frais. Change-le si tu suspectes une fuite.

3. **Le Worker Cloudflare gratuit** offre 100 000 requêtes/jour. Avec ta limite
   de 20 requêtes/utilisateur/jour dans BACY, tu peux servir 5 000 élèves/jour
   sans atteindre cette limite.

4. **Logs** : dans le dashboard Cloudflare Workers, tu peux voir les logs en
   temps réel (onglet "Logs") pour diagnostiquer si quelque chose ne passe pas.

---

## Résumé des fichiers modifiés

| Fichier | Modification |
|---------|-------------|
| `.env` (VPS) | Ajouter `GEMINI_API_URL` et `GEMINI_PROXY_SECRET` |
| `app/Controllers/Apprentissage/IaController.php` | Ajouter header `X-Proxy-Secret` dans cURL |
| Worker Cloudflare (nouveau) | Script JS du proxy |

---

## Flux complet après implémentation

```
Élève clique "Envoyer" dans BACY
    ↓
IaController::question()
    ↓
cURL POST vers https://gemini-proxy.workers.dev  (avec X-Proxy-Secret)
    ↓
Cloudflare Worker (IP USA/Europe, pays autorisé par Google)
    ↓
Google Gemini API  ← voit l'IP Cloudflare, pas l'IP gabonaise
    ↓
Réponse JSON remonte par le même chemin
    ↓
BACY affiche la réponse à l'élève
```
