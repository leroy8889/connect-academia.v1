# AVANT_PRODUCTION — Checklist complète avant mise en ligne

> **Ce fichier doit être supprimé du serveur de production après avoir tout vérifié.**  
> Il ne contient pas de secrets — il indique seulement quoi changer et où.

---

## AVERTISSEMENT CRITIQUE — LIS CECI EN PREMIER

Ton projet a **3 noms de domaine différents** selon les fichiers.  
Avant de toucher quoi que ce soit, décide **une seule fois** quel domaine tu utilises :

| Domaine trouvé dans le code | Fichiers concernés |
|---|---|
| `connect-academia.net` | `.env` (APP_URL) |
| `connectacademia.io` | Toutes les pages HTML, les vues PHP |
| `connectacademia.com` | `.env` (MAIL_FROM_ADDRESS), PHP controllers |

**Choix à faire maintenant :** Quel est ton domaine définitif de production ?  
Une fois décidé, cherche-remplace dans tout le projet avec ton éditeur.

---

## ÉTAPE 1 — Fichier `.env` (à la racine)

C'est le fichier le plus important. Il centralise toute la configuration.

### 1.1 — URL de l'application

```
APP_URL=https://connect-academia.net/connect%27academia.v1
```

**Problème :** L'URL contient le dossier MAMP (`connect%27academia.v1`). En production, l'app est à la racine du domaine.

**Ce que tu dois mettre :**
```
APP_URL=https://TON-DOMAINE.COM
```

Exemple si ton domaine est `connectacademia.io` :
```
APP_URL=https://connectacademia.io
```

---

### 1.2 — Clé secrète de l'application

```
APP_SECRET=ConnectAcademia_Secret_Gabon_2026_XAF
```

**Problème :** Cette clé est prévisible. Elle signe les webhooks de paiement MoneyFusion — si quelqu'un la connaît, il peut forger de faux paiements confirmés.

**Ce que tu dois mettre :** Une chaîne aléatoire longue. Génère-en une comme ceci :

```bash
# Dans ton terminal, exécute cette commande :
openssl rand -hex 64
```

Copie le résultat et remplace dans `.env` :
```
APP_SECRET=LE_RESULTAT_DE_LA_COMMANDE_CI_DESSUS
```

---

### 1.3 — Base de données

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=connect_academia1
DB_USER=root
DB_PASS=root
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

**Problèmes :**
- `DB_USER=root` et `DB_PASS=root` → identifiants dev MAMP, jamais utilisés en production
- `DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock` → chemin MAMP, n'existe pas sur un serveur

**Ce que tu dois mettre :**

```
DB_HOST=127.0.0.1         ← garder si MySQL local, sinon l'IP du serveur DB
DB_PORT=3306              ← garder sauf si ton hébergeur utilise un autre port
DB_NAME=nom_de_ta_bdd_prod
DB_USER=nom_utilisateur_bdd_prod
DB_PASS=mot_de_passe_fort_bdd_prod
DB_SOCKET=                 ← laisser VIDE (supprimer le chemin MAMP)
```

Chez ton hébergeur, crée un utilisateur MySQL dédié avec seulement les droits : `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX`.

---

### 1.4 — Redis

```
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
```

**À vérifier :** Ton hébergeur fournit-il Redis ? Si non, les fonctionnalités de rate limiting tomberont en fallback silencieux (ce n'est pas bloquant mais affaiblit la sécurité). Si Redis est disponible :
- Remplis `REDIS_PASSWORD` avec le mot de passe fourni
- Change `REDIS_HOST` si Redis n'est pas local

---

### 1.5 — Clé API Gemini (IA)

```
GEMINI_API_KEY=AIzaSyACWTtFGXyC75c_M95zxhUKq_sdGrlm630
```

**DANGER : Cette clé est visible dans le code source.** Si le fichier `.env` est accessible publiquement (mauvaise config serveur), quelqu'un peut utiliser ton quota Gemini.

Actions à faire :
1. Dans la Google Cloud Console, **restreins cette clé** aux seuls domaines/IPs de ton serveur de production
2. Vérifie que `.htaccess` bloque bien l'accès à `.env` (c'est déjà fait dans ton code)
3. Après le déploiement, vérifie en tapant `https://tondomaine.com/.env` → tu dois avoir une erreur 403 ou 404

---

### 1.6 — Mot de passe email (PHPMailer)

```
MAIL_USERNAME=connectacademiaheb@gmail.com
MAIL_PASSWORD=fxbucacvmlldmsik
MAIL_FROM_ADDRESS=security@connectacademia.com
```

**Problèmes :**
- `MAIL_FROM_ADDRESS=security@connectacademia.com` mais dans toutes tes pages HTML tu affiches `contact@connectacademia.io` → incohérent pour les utilisateurs
- Le mot de passe Gmail est en clair dans `.env` → normal pour un app password Google, mais le fichier `.env` ne doit JAMAIS être accessible sur le web

**Ce que tu dois faire :**
1. Aligne `MAIL_FROM_ADDRESS` avec l'adresse affichée dans tes pages HTML
2. Si tu as changé le mot de passe Gmail, mets à jour `MAIL_PASSWORD`

---

### 1.7 — AWS S3 (optionnel)

```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_CLOUDFRONT_URL=
```

Ces champs sont vides. Si tu n'utilises pas S3 pour l'instant, laisse vide. Si tu l'actives plus tard, remplis-les.

---

## ÉTAPE 2 — Fichiers à SUPPRIMER avant le déploiement

Ces fichiers ne doivent **jamais** se trouver sur le serveur de production.

### 2.1 — `install.php` (CRITIQUE)

**Risque :** Ce fichier permet à n'importe qui de réinitialiser ou reconfigurer ta base de données depuis un navigateur.

```bash
# Supprime ce fichier avant de déployer :
rm install.php
```

### 2.2 — `ngrok-start.sh`

Script de développement pour exposer ton MAMP local. Inutile en production.

```bash
rm ngrok-start.sh
```

### 2.3 — `mamp-watchdog.sh`

Script de surveillance MAMP. Inutile en production.

```bash
rm mamp-watchdog.sh
```

---

## ÉTAPE 3 — Cohérence du domaine dans les pages HTML et PHP

Après avoir choisi ton domaine définitif (voir AVERTISSEMENT en haut), tu dois mettre à jour ces fichiers :

### Fichiers HTML statiques — email de contact

Cherche `contact@connectacademia.io` dans ces fichiers et remplace par ton email de contact réel :

| Fichier | Lignes concernées |
|---|---|
| [app/Views/landing.php](app/Views/landing.php) | Ligne ~1030 |
| [app/Views/layouts/main.php](app/Views/layouts/main.php) | Ligne ~230 |
| [assets/lib/contact.html](assets/lib/contact.html) | Ligne ~681 |
| [assets/lib/tarifs.html](assets/lib/tarifs.html) | Ligne ~534 |
| [assets/lib/mentions-legales.html](assets/lib/mentions-legales.html) | Lignes ~941, 1080, 1087, 1232, 1410, 1541, 1543, 1589 |
| [public/orientation/orientation.html](public/orientation/orientation.html) | Ligne ~1257 |

### Fichiers HTML statiques — URL du site

Cherche `connectacademia.io` (URL cliquable) dans :

| Fichier | Ligne |
|---|---|
| [assets/lib/mentions-legales.html](assets/lib/mentions-legales.html) | Lignes ~945, 1002, 1545, 1547 |

---

## ÉTAPE 4 — `manifest.json` (PWA)

```json
"start_url": "./",
"scope": "./"
```

**Problème :** En production à la racine du domaine, `./` fonctionne. Mais si jamais l'app est dans un sous-dossier, la PWA ne s'installera pas correctement.

**Recommandation :** Remplace par des chemins absolus :

```json
"start_url": "/",
"scope": "/"
```

Fichier : [manifest.json](manifest.json)

---

## ÉTAPE 5 — Vérifications serveur (à faire chez l'hébergeur)

Ces points ne se modifient pas dans le code mais sont critiques pour la mise en production :

### 5.1 — PHP doit être configuré en mode production

Dans le `php.ini` de ton hébergeur :
```ini
display_errors = Off
log_errors = On
error_reporting = E_ALL
```

Ton `.env` a déjà `APP_DEBUG=false` — c'est bien. Mais `display_errors` doit aussi être `Off` au niveau PHP.

### 5.2 — Vérifier que `.env` est inaccessible

Ouvre ton navigateur et tape : `https://tondomaine.com/.env`

Tu dois voir une erreur **403 Forbidden** ou **404 Not Found**.  
Si tu vois le contenu du fichier → ton hébergeur ignore le `.htaccess`. C'est une fuite critique. Contacte immédiatement le support de l'hébergeur.

### 5.3 — HTTPS obligatoire

Vérifie que ton certificat SSL est actif. Toutes les pages doivent se charger en `https://`.  
Ajoute une redirection HTTP → HTTPS dans `.htaccess` si ce n'est pas géré par l'hébergeur :

```apache
# Ajouter au début de .htaccess, après "RewriteEngine On" :
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 5.4 — Base de données : changer le mot de passe admin Connect'Academia

Dans la table `admins` de ta base de données, l'administrateur principal a un mot de passe de développement. Crée un nouveau mot de passe fort via le panneau admin **avant** d'ouvrir la plateforme au public.

---

## ÉTAPE 6 — Compte admin : changer les identifiants par défaut

Si tu as créé le compte admin avec `install.php` en développement, le mot de passe est celui que tu as tapé lors de l'installation sur MAMP.

**Action :** Connecte-toi à `/Administratorconnect/login`, va dans les paramètres admin et change le mot de passe. Active aussi le **TOTP** (double authentification) si ce n'est pas encore fait.

---

## RÉCAPITULATIF — Liste de contrôle finale

Coche chaque point avant de déployer :

- [ ] `.env` — `APP_URL` mis à jour avec le vrai domaine (sans sous-dossier)
- [ ] `.env` — `APP_SECRET` remplacé par une clé aléatoire (`openssl rand -hex 64`)
- [ ] `.env` — `DB_USER`, `DB_PASS`, `DB_NAME` mis à jour avec les credentials production
- [ ] `.env` — `DB_SOCKET` vidé (supprimer le chemin MAMP)
- [ ] `.env` — `MAIL_FROM_ADDRESS` aligné avec l'email affiché dans les pages HTML
- [ ] `.env` — Clé Gemini restreinte aux IPs du serveur dans Google Cloud Console
- [ ] `install.php` supprimé
- [ ] `ngrok-start.sh` supprimé
- [ ] `mamp-watchdog.sh` supprimé
- [ ] Domaine unifié dans tous les fichiers HTML (`contact@...` et URL site)
- [ ] `manifest.json` — `start_url` et `scope` mis à `"/"`
- [ ] HTTPS actif et redirection HTTP → HTTPS configurée
- [ ] `.env` inaccessible depuis le navigateur (test manuel)
- [ ] `display_errors = Off` dans php.ini
- [ ] Mot de passe admin changé en production
- [ ] TOTP activé pour le compte admin

---

*Généré le 27 mai 2026 — Connect'Academia v1*
