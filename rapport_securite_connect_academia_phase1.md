# Rapport de Sécurité Applicative — Connect'Academia
## Phase 1 : Analyse Statique du Code Source (SAST)

---

**Projet :** Connect'Academia  
**Version analysée :** v1  
**Date d'analyse :** 18 mai 2026  
**Rédigé par :** Équipe Sécurité  
**Destinataires :** Équipe de développement  
**Classification :** 🔴 CONFIDENTIEL — Usage interne uniquement  

---

## Table des matières

1. [Introduction et objectifs](#1-introduction-et-objectifs)
2. [Périmètre d'analyse](#2-périmètre-danalyse)
3. [Outils utilisés](#3-outils-utilisés)
4. [Résultats de l'analyse — Semgrep (SAST)](#4-résultats-de-lanalyse--semgrep-sast)
5. [Résultats de l'analyse — TruffleHog (Détection de secrets)](#5-résultats-de-lanalyse--trufflehog-détection-de-secrets)
6. [Tableau de synthèse des vulnérabilités](#6-tableau-de-synthèse-des-vulnérabilités)
7. [Plan de remédiation prioritaire](#7-plan-de-remédiation-prioritaire)
8. [Conclusion](#8-conclusion)

---

## 1. Introduction et objectifs

Ce rapport documente les résultats de la **Phase 1** de l'audit de sécurité de l'application web **Connect'Academia**. Cette phase, appelée **SAST (Static Application Security Testing)** ou Analyse Statique du Code Source, consiste à examiner le code source de l'application **sans l'exécuter**, à la manière d'une relecture minutieuse d'un document avant envoi.

### Objectif principal

Détecter les failles de sécurité, les mauvaises pratiques de développement et les secrets exposés **avant que le code n'arrive en environnement de production**, réduisant ainsi la surface d'attaque dès la phase de développement.

### Pourquoi l'analyse statique ?

- Elle ne nécessite pas de déploiement de l'application
- Elle couvre 100 % du code source versionné
- Elle identifie les problèmes à la source, là où ils sont les moins coûteux à corriger
- Elle peut être intégrée dans une pipeline CI/CD pour une surveillance continue

---

## 2. Périmètre d'analyse

| Élément | Détail |
|---|---|
| Application | Connect'Academia v1 |
| Fichiers scannés | 388 fichiers (suivis par Git) |
| Fichiers ignorés | 53 fichiers > 1 Mo / 84 fichiers correspondant aux patterns `.semgrepignore` |
| Langages couverts | PHP, HTML, SQL, JavaScript |
| Règles appliquées (Semgrep) | 257 règles |
| Lignes analysées | ~99,9 % des lignes |
| Chunks analysés (TruffleHog) | 32 080 chunks — 421 Mo |

---

## 3. Outils utilisés

### 3.1 Semgrep OSS — Analyse statique (SAST)

**Semgrep** est un outil d'analyse statique open source qui permet de détecter des patterns de code dangereux à l'aide de règles de sécurité. Il analyse la syntaxe et la structure du code pour identifier des vulnérabilités connues (injections, mauvaises configurations, appels dangereux, etc.).

- **Version utilisée :** Semgrep OSS (Community)
- **Commande exécutée :**

```bash
semgrep scan --config auto
```

- **Résultat global :** 34 findings, tous classés BLOCKING

### 3.2 TruffleHog — Détection de secrets

**TruffleHog** est un outil spécialisé dans la détection de secrets (clés API, tokens, mots de passe) exposés dans le code source et l'historique Git. Il utilise des détecteurs intelligents capables de **vérifier activement** si les secrets trouvés sont encore valides.

- **Version utilisée :** TruffleHog v3.95.3
- **Commande exécutée :**

```bash
trufflehog filesystem .
```

- **Résultat global :** 4 secrets vérifiés (actifs) + 3 non vérifiés

---

## 4. Résultats de l'analyse — Semgrep (SAST)

### Capture des résultats Semgrep

```
┌──────────────────┐
│ 34 Code Findings │
└──────────────────┘
Ran 257 rules on 388 files: 34 findings.
✅ Scan completed successfully.
 • Findings: 34 (34 blocking)
```

> ⚠️ **Toutes les 34 vulnérabilités détectées sont classées BLOCKING**, ce qui signifie qu'elles doivent être corrigées avant toute mise en production ou déploiement.

---

### 4.1 Clé API exposée dans le fichier `.env`

**Règle déclenchée :** `generic.secrets.security.detected-generic-api-key`  
**Sévérité :** 🔴 CRITIQUE — BLOCKING  
**Fichier :** `.env` — Ligne 38

**Code détecté :**
```
GEMINI_API_KEY=[REDACTED-GEMINI-KEY-1]
```

**Analyse :**  
Une clé API Google Gemini est stockée en clair dans le fichier `.env`. Si ce fichier est versionné dans Git (ce que confirme TruffleHog), il est accessible à quiconque peut lire le dépôt. Cette clé a été **confirmée comme active** par TruffleHog.

**Risques :**
- Utilisation frauduleuse de l'API Gemini aux frais du propriétaire
- Accès non autorisé aux fonctionnalités IA de l'application
- Facturation excessive ou interruption de service

**Remédiation :**
1. Révoquer immédiatement la clé via la Google Cloud Console
2. Générer une nouvelle clé
3. Ajouter `.env` au fichier `.gitignore`
4. Utiliser des variables d'environnement systèmes ou un gestionnaire de secrets (HashiCorp Vault, AWS Secrets Manager)
5. Nettoyer l'historique Git (voir section 5)

---

### 4.2 Exécution de commandes système — Injection de commandes (PHPMailer)

**Règle déclenchée :** `php.lang.security.exec-use.exec-use`  
**Sévérité :** 🔴 CRITIQUE — BLOCKING  
**Fichier :** `app/Controllers/Admin/PHPMailer/src/PHPMailer.php`  
**Lignes :** 1761, 1787

**Code détecté :**
```php
$mail = @popen($sendmail, 'w');
```

**Analyse :**  
La fonction `popen()` exécute une commande système. Si la variable `$sendmail` contient une entrée utilisateur non filtrée, un attaquant peut injecter des commandes arbitraires sur le serveur (Command Injection / RCE — Remote Code Execution).

**Risques :**
- Exécution de commandes arbitraires sur le serveur
- Prise de contrôle complète du serveur (RCE)
- Exfiltration de données, installation de backdoors

**Remédiation :**
1. Vérifier que `$sendmail` est uniquement alimenté par une valeur de configuration serveur (jamais par une entrée utilisateur)
2. Si PHPMailer est une bibliothèque tierce, mettre à jour vers la dernière version stable
3. Appliquer le principe du moindre privilège sur le processus PHP

---

### 4.3 Suppression de fichiers non sécurisée — `unlink()` (PHPMailer)

**Règle déclenchée :** `php.lang.security.unlink-use.unlink-use`  
**Sévérité :** 🔴 HAUTE — BLOCKING  
**Fichier :** `app/Controllers/Admin/PHPMailer/src/PHPMailer.php`  
**Lignes :** 3062, 3065, 3071

**Code détecté :**
```php
@unlink($file);
@unlink($signed);
```

**Analyse :**  
L'utilisation de `unlink()` avec des variables potentiellement influencées par l'utilisateur peut permettre à un attaquant de supprimer des fichiers arbitraires sur le serveur (Path Traversal + File Deletion). L'opérateur `@` qui supprime les erreurs masque également les problèmes réels.

**Risques :**
- Suppression de fichiers critiques du serveur
- Déni de service (suppression de fichiers de configuration)
- Contournement de mécanismes de sécurité

**Remédiation :**
1. S'assurer que `$file` et `$signed` sont des chemins construits entièrement côté serveur
2. Valider que les chemins sont dans un répertoire temporaire autorisé (ex. : `realpath()` + vérification du préfixe)
3. Supprimer l'opérateur `@` et traiter les erreurs correctement

---

### 4.4 Attribut `integrity` manquant sur les ressources CDN (SRI)

**Règle déclenchée :** `html.security.audit.missing-integrity.missing-integrity`  
**Sévérité :** 🟠 MOYENNE — BLOCKING  
**Fichiers concernés :**

| Fichier | Ressources concernées |
|---|---|
| `assets/lib/contact.html` | boxicons CDN, ionicons (ESM + nomodule) |
| `assets/lib/mentions-legales.html` | boxicons CDN, ionicons (ESM + nomodule) |
| `assets/lib/tarifs.html` | boxicons CDN, fonts.cdnfonts.com, ionicons (ESM + nomodule) |
| `public/orientation/institut.html` | boxicons unpkg, Font Awesome, ionicons |
| `public/orientation/orientation.html` | boxicons unpkg, Font Awesome, ionicons |
| `public/orientation/privés.html` | boxicons unpkg, Font Awesome, ionicons |
| `public/orientation/universités-publics.html` | boxicons unpkg, Font Awesome, ionicons |

**Exemples de code détecté :**
```html
<link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'>

<script type="module"
  src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>

<script nomodule
  src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
```

**Analyse :**  
L'absence de l'attribut `integrity` (SRI — Subresource Integrity) sur les balises `<link>` et `<script>` chargeant des ressources externes signifie que le navigateur acceptera n'importe quelle version du fichier retournée par le CDN, même si elle a été falsifiée. Un attaquant capable de compromettre le CDN peut injecter du code malveillant dans tous les utilisateurs de l'application.

**Risques :**
- Attaque de la chaîne d'approvisionnement (Supply Chain Attack)
- Injection de scripts XSS via un CDN compromis
- Vol de cookies, de sessions, ou de données utilisateur

**Remédiation :**  
Ajouter l'attribut `integrity` avec le hash SHA-384 de chaque ressource externe, ainsi que l'attribut `crossorigin="anonymous"` :

```html
<!-- Exemple corrigé -->
<link 
  href="https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css" 
  rel="stylesheet"
  integrity="sha384-[HASH_A_CALCULER]"
  crossorigin="anonymous">

<script 
  type="module"
  src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"
  integrity="sha384-[HASH_A_CALCULER]"
  crossorigin="anonymous"></script>
```

> Utiliser l'outil en ligne [https://www.srihash.org/](https://www.srihash.org/) ou la commande `openssl dgst -sha384 -binary fichier.js | openssl base64 -A` pour générer les hashes.

---

### 4.5 Hash bcrypt dans les fichiers SQL

**Règle déclenchée :** `generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash`  
**Sévérité :** 🟡 FAIBLE (selon contexte) — BLOCKING  
**Fichiers concernés :**

| Fichier | Ligne |
|---|---|
| `database/003_fix_schema.sql` | 28 |
| `database/005_seed_connect_academia1.sql` | 17 |

**Code détecté :**
```sql
password_hash = '$2y$12$TJNN0vjNDMZb7FBumZhaSO0inonKn2B8hzThxoPDy6ZzHjCqoeS4G'
```

**Analyse :**  
Des hashes bcrypt (mots de passe chiffrés) sont présents dans des fichiers SQL de migration ou de seed. Bien que bcrypt soit un algorithme sûr, la présence de hashes dans le dépôt Git représente un risque : un attaquant peut tenter des attaques par force brute hors ligne pour retrouver les mots de passe en clair, surtout si ce sont des comptes administrateurs avec des mots de passe faibles.

**Risques :**
- Exposition de comptes (potentiellement administrateurs) à des attaques offline
- Si le mot de passe en clair est faible, il peut être retrouvé en quelques secondes

**Remédiation :**
1. Ne jamais versionner de données de seed contenant des mots de passe dans Git
2. Utiliser des scripts de seed qui génèrent les hashes dynamiquement à l'exécution
3. S'assurer que les comptes de test ont des mots de passe forts (minimum 16 caractères, aléatoires)

---

## 5. Résultats de l'analyse — TruffleHog (Détection de secrets)

### Capture des résultats TruffleHog

```
🐷🔑🐷  TruffleHog. Unearth your secrets. 🐷🔑🐷

Chunks analysés : 32 080
Taille analysée : 421 Mo
Secrets vérifiés (actifs) : 4
Secrets non vérifiés : 3
Durée du scan : 7.26 secondes
Version : TruffleHog v3.95.3
```

> 🔴 **ALERTE CRITIQUE** : TruffleHog a identifié **4 secrets actifs et vérifiés** dans le dépôt. Ces secrets sont en ce moment même potentiellement exploitables par quiconque y accède.

---

### 5.1 Clé API Google Gemini — Active et vérifiée

**Type détecté :** `GoogleGeminiAPIKey`  
**Statut :** ✅ Vérifiée et ACTIVE  
**Valeur exposée :** `[REDACTED-GEMINI-KEY-1]`

**Occurrences détectées :**

| Fichier | Ligne | Statut |
|---|---|---|
| `.env` | 38 | ✅ Vérifiée — Active |
| `.git/objects/89/4614f2...` | 38 | ✅ Vérifiée — Active (historique Git) |
| `.git/objects/87/a6a5d2...` | 38 | ✅ Vérifiée — Active (historique Git) |
| `.git/objects/3a/e2cfd4...` | 39 | ❓ Non vérifiée — Ancienne clé possible |
| `.git/objects/f6/5f3659...` | 38 | ❓ Non vérifiée — Ancienne clé possible |

**Analyse approfondie :**  
La clé est présente non seulement dans le fichier `.env` actuel, mais également dans **l'historique des objets Git**. Cela signifie que même si le fichier `.env` est retiré du prochain commit, la clé restera accessible dans l'historique Git. Une simple suppression du fichier est **insuffisante**.

**⚠️ Action immédiate requise :**
1. **Révoquer immédiatement** la clé `[REDACTED-GEMINI-KEY-1]` depuis la Google Cloud Console
2. **Nettoyer l'historique Git** avec `git filter-repo` ou BFG Repo Cleaner :
   ```bash
   # Avec BFG Repo Cleaner
   bfg --replace-text secrets.txt
   git reflog expire --expire=now --all
   git gc --prune=now --aggressive
   git push --force
   ```
3. Générer une nouvelle clé et la stocker dans un gestionnaire de secrets

---

### 5.2 Token GitHub Personnel — Actif avec permissions maximales

**Type détecté :** `Github` (Personal Access Token)  
**Statut :** ✅ Vérifié et ACTIF  
**Valeur exposée :** `[REDACTED — token révoqué]`  
**Fichier :** `.git/config` — Ligne 15  
**Utilisateur GitHub :** `leroy8889`  
**Expiration du token :** 29 juin 2026

**Permissions accordées à ce token :**
```
admin:enterprise, admin:gpg_key, admin:org, admin:org_hook,
admin:public_key, admin:repo_hook, admin:ssh_signing_key,
audit_log, codespace, copilot, delete:packages, gist,
notifications, project, repo, user, workflow,
write:discussion, write:network_configurations, write:packages
```

**Analyse :**  
Ce token GitHub dispose de **permissions quasiment illimitées** sur le compte `leroy8889`. Il est présent dans le fichier `.git/config`, ce qui signifie qu'il est inclus dans l'URL de remote (authentification embarquée). Toute personne ayant accès au dépôt Git peut l'extraire et l'utiliser pour :

- Accéder à tous les dépôts (publics et privés)
- Modifier, supprimer ou exfiltrer le code source
- Accéder aux logs d'audit de l'organisation
- Gérer les membres, les webhooks, les clés SSH
- Déclencher des workflows GitHub Actions

**⚠️ Action immédiate requise :**
1. **Révoquer immédiatement** ce token depuis GitHub > Settings > Developer settings > Personal access tokens
2. Modifier l'URL du remote Git pour supprimer les credentials embarqués :
   ```bash
   # Supprimer les credentials de l'URL
   git remote set-url origin https://github.com/leroy8889/connect-academia.git
   
   # Utiliser SSH ou un credential manager à la place
   git remote set-url origin git@github.com:leroy8889/connect-academia.git
   ```
3. Ne **jamais** utiliser de tokens dans les URLs Git
4. Créer un nouveau token avec des permissions minimales (principe du moindre privilège)

---

### 5.3 Ancienne clé API Google Gemini — Non vérifiée

**Type détecté :** `GoogleGeminiAPIKey`  
**Statut :** ❓ Non vérifiée (potentiellement expirée)  
**Valeur exposée :** `[REDACTED-GEMINI-KEY-2]`

**Occurrences :**

| Fichier | Ligne | Statut |
|---|---|---|
| `.git/objects/3a/e2cfd4...` | 39 | ❓ Non vérifiée |
| `.git/objects/f6/5f3659...` | 38 | ❓ Non vérifiée |

**Analyse :**  
Cette ancienne clé est présente uniquement dans l'historique Git. Elle n'est pas vérifiable en temps réel par TruffleHog, ce qui peut signifier qu'elle a été révoquée — mais cela doit être confirmé. Elle doit être considérée comme compromise.

**Remédiation :**
1. Vérifier manuellement si cette clé est toujours active dans la Google Cloud Console
2. La révoquer si elle ne l'est pas déjà
3. Nettoyer l'historique Git (même procédure que 5.1)

---

### 5.4 Token GitHub dans l'URL — Non vérifié

**Type détecté :** `URI` (URL avec credentials embarqués)  
**Statut :** ❓ Non vérifié (redondant avec 5.2)  
**Valeur exposée :** `https://leroy8889:[REDACTED]@github.com`  
**Fichier :** `.git/config` — Ligne 15

**Analyse :**  
Il s'agit de la même fuite que le token GitHub (5.2), détectée cette fois via le pattern URI complet avec credentials embarqués. La présence du nom d'utilisateur ET du token dans l'URL est une pratique particulièrement dangereuse.

---

## 6. Tableau de synthèse des vulnérabilités

| # | Vulnérabilité | Fichier(s) | Sévérité | Outil | Statut |
|---|---|---|---|---|---|
| 1 | Clé API Gemini active dans `.env` | `.env` | 🔴 CRITIQUE | Semgrep + TruffleHog | Actif — Action immédiate |
| 2 | Token GitHub avec permissions maximales | `.git/config` | 🔴 CRITIQUE | TruffleHog | Actif — Action immédiate |
| 3 | Clé API Gemini dans historique Git | `.git/objects/...` (3 occurrences) | 🔴 CRITIQUE | TruffleHog | Actif + historique à nettoyer |
| 4 | Exécution de commandes (RCE potentiel) | `PHPMailer.php` L.1761, 1787 | 🔴 CRITIQUE | Semgrep | À corriger |
| 5 | Suppression de fichiers non sécurisée | `PHPMailer.php` L.3062, 3065, 3071 | 🔴 HAUTE | Semgrep | À corriger |
| 6 | SRI manquante sur ressources CDN | 7 fichiers HTML | 🟠 MOYENNE | Semgrep | À corriger |
| 7 | Hash bcrypt en base de données SQL versionnés | `003_fix_schema.sql`, `005_seed_...sql` | 🟡 FAIBLE | Semgrep | À corriger |
| 8 | Ancienne clé Gemini dans historique Git | `.git/objects/...` (2 occurrences) | 🟠 MOYENNE | TruffleHog | Historique à nettoyer |

---

## 7. Plan de remédiation prioritaire

### 🔴 Priorité 1 — Immédiat (dans les 24 heures)

Ces actions doivent être réalisées **avant toute autre tâche de développement**.

1. **Révoquer la clé API Google Gemini** `[REDACTED]` depuis la Google Cloud Console
2. **Révoquer le token GitHub** `[REDACTED]` depuis les paramètres GitHub
3. **Nettoyer l'historique Git** avec BFG Repo Cleaner ou `git filter-repo` pour supprimer toute trace des secrets
4. **Ajouter `.env` au `.gitignore`** et vérifier qu'il n'est plus suivi par Git
5. **Corriger l'URL du remote Git** pour supprimer les credentials embarqués

### 🔴 Priorité 2 — Court terme (dans les 7 jours)

6. **Analyser et sécuriser** l'utilisation de `popen()` dans PHPMailer (vérifier si la variable est contrôlable par l'utilisateur)
7. **Mettre à jour PHPMailer** vers la dernière version disponible
8. **Sécuriser les appels à `unlink()`** avec validation de chemin

### 🟠 Priorité 3 — Moyen terme (dans les 30 jours)

9. **Ajouter les attributs SRI** (`integrity` + `crossorigin`) sur toutes les ressources CDN externes dans les 7 fichiers HTML concernés
10. **Supprimer les hashes bcrypt** des fichiers SQL versionnés et les remplacer par une génération dynamique

### 🟡 Recommandations générales

- Mettre en place un **pre-commit hook** avec Semgrep et TruffleHog pour bloquer les secrets avant tout commit
- Intégrer une analyse SAST dans la **pipeline CI/CD** (GitHub Actions)
- Former l'équipe aux bonnes pratiques de gestion des secrets (variables d'environnement, gestionnaire de secrets)
- Réaliser une **Phase 2 : DAST** (Dynamic Application Security Testing) une fois les corrections de la Phase 1 appliquées

---

## 8. Conclusion

L'analyse statique de la Phase 1 a révélé **34 vulnérabilités bloquantes** via Semgrep et **7 secrets exposés** (dont 4 actifs et vérifiés) via TruffleHog sur le dépôt Connect'Academia.

Les points les plus critiques sont l'exposition d'une **clé API Google Gemini active** et d'un **token GitHub avec des permissions maximales**, tous deux présents non seulement dans les fichiers courants mais également dans **l'historique Git**. Ces secrets peuvent être exploités immédiatement par tout acteur malveillant ayant accès au dépôt.

La correction de ces vulnérabilités, en commençant par les actions immédiates de révocation des credentials, est **indispensable avant toute mise en production** de l'application.

Une fois les corrections effectuées, il est recommandé de procéder à la **Phase 2 — Analyse Dynamique (DAST)** pour couvrir les vulnérabilités qui ne sont détectables qu'à l'exécution (injections SQL, XSS, CSRF, authentification brisée, etc.).

---

*Rapport généré le 18 mai 2026 — Connect'Academia Security Audit — Phase 1 SAST*  
*Ce document est confidentiel et destiné exclusivement à l'équipe de développement et aux responsables sécurité du projet.*
