# Mise en place du stockage objet Backblaze B2 dans Connect'Academia

**Document pédagogique — Département Informatique, Connect'Academia**
Destiné à un travail de mémoire de licence : comprendre, expliquer et documenter
l'intégration du service de stockage objet Backblaze B2 dans la plateforme.

> Ce document **décrit l'existant**. L'intégration est déjà en production ; rien
> n'est à modifier pour la rédaction du mémoire.

---

## Sommaire

1. [Le problème résolu par B2](#1-le-problème-résolu-par-b2)
2. [Notions préalables](#2-notions-préalables)
3. [La configuration : où vivent les identifiants](#3-la-configuration--où-vivent-les-identifiants)
4. [Le client S3 : `DocumentStorage`](#4-le-client-s3--documentstorage)
5. [Les quatre chemins d'écriture](#5-les-quatre-chemins-décriture)
6. [Les trois chemins de lecture](#6-les-trois-chemins-de-lecture)
7. [Chiffrement des pièces justificatives](#7-chiffrement-des-pièces-justificatives)
8. [Résilience : cache, miroir local, quota](#8-résilience--cache-miroir-local-quota)
9. [Le navigateur parle à B2 : CSP et upload direct](#9-le-navigateur-parle-à-b2--csp-et-upload-direct)
10. [La base de données : où sont rangées les clés](#10-la-base-de-données--où-sont-rangées-les-clés)
11. [Exploitation : scripts, tâches, déploiement](#11-exploitation--scripts-tâches-déploiement)
12. [Inventaire complet des fichiers](#12-inventaire-complet-des-fichiers)
13. [Glossaire](#13-glossaire)

---

## 1. Le problème résolu par B2

Connect'Academia manipule trois familles de fichiers lourds :

| Famille | Exemples | Volume unitaire |
|---|---|---|
| Pièces justificatives d'inscription | acte de naissance, CNI, photo, relevés de notes | 0,5 à 3 Mo |
| Médias d'établissement | logo, photos du campus, vidéo de présentation | 50 Ko à 500 Mo |
| Documents générés | attestation, récépissé, reçu, facture proforma (PDF) | 100 à 800 Ko |

Trois raisons interdisent de les garder sur le disque du serveur applicatif :

1. **Le volume** — une vidéo de présentation par établissement suffit à saturer
   le disque d'un VPS.
2. **La durabilité** — un disque serveur n'est pas répliqué ; une pièce d'identité
   perdue est une inscription perdue.
3. **Le couplage** — remplacer le serveur voudrait dire déplacer des giga-octets
   de données utilisateurs.

**Backblaze B2** est un service de *stockage objet* : on y dépose des fichiers
(des « objets »), identifiés par une **clé** (leur chemin), dans un **bucket**
(le conteneur). Il est facturé à l'usage, beaucoup moins cher qu'Amazon S3, et —
point décisif pour l'implémentation — il expose une **API compatible S3**.

**Conséquence pratique fondamentale :** on ne programme pas « pour Backblaze ».
On programme pour S3, avec le SDK officiel d'AWS, et on lui donne simplement une
autre adresse de serveur. C'est tout le principe de l'intégration.

---

## 2. Notions préalables

| Terme | Définition dans le contexte du projet |
|---|---|
| **Bucket** | Le conteneur unique du projet. Il est **privé** : aucun objet n'est lisible sans authentification. |
| **Clé (key)** | Le chemin de l'objet dans le bucket, ex. `dossiers/48/acte_naissance_a1b2.pdf`. C'est ce qui est stocké en base de données, jamais le fichier lui-même. |
| **Endpoint** | L'adresse du serveur B2, ex. `s3.us-west-004.backblazeb2.com`. |
| **Application Key** | Le couple identifiant / secret qui authentifie l'application. |
| **URL présignée** | Une URL temporaire, signée cryptographiquement, qui autorise **une** opération (lecture ou écriture) sur **un** objet, pendant une durée limitée. Elle permet au navigateur de dialoguer avec B2 sans jamais connaître les secrets. |
| **ACL** | Le régime de visibilité d'un objet (`private` ou `public-read`). Dans ce projet, **tout est `private`**. |

---

## 3. La configuration : où vivent les identifiants

### 3.1 Les cinq variables

Fichier : **`.env`** (racine du projet, **jamais versionné** — voir `.gitignore`)

```ini
# Backblaze B2 — Module Orientation (EXCLUSIF)
B2_KEY_ID=...
B2_APPLICATION_KEY=...
B2_BUCKET=...
B2_ENDPOINT=...
B2_REGION=...
```

| Variable | Rôle | Obligatoire |
|---|---|---|
| `B2_KEY_ID` | Identifiant de la clé d'application | oui |
| `B2_APPLICATION_KEY` | Secret associé | oui |
| `B2_BUCKET` | Nom du bucket | oui |
| `B2_ENDPOINT` | Hôte S3 de la région (sans `https://`) | oui |
| `B2_REGION` | Région ; valeur de repli `us-west-004` | non |

Deux variables supplémentaires interviennent dans le chiffrement des pièces
(section 7) : `APP_SECRET` et, optionnellement, `APP_ENCRYPTION_KEY`.

### 3.2 Comment ces variables arrivent dans PHP

Fichier : **`index.php`** (lignes ~37-70) — le point d'entrée unique de
l'application.

```php
// .env cherché HORS du webroot d'abord (prod sécurisée : nginx ne peut pas le servir)
foreach ([dirname(BASE_PATH) . '/.env', BASE_PATH . '/.env'] as $candidate) { ... }
```

Le chargeur est **écrit à la main** (pas de bibliothèque type Dotenv) : il lit le
fichier ligne à ligne et alimente `$_ENV` et `putenv()`.

**Point pédagogique à souligner dans le mémoire :** l'ordre de recherche n'est pas
anodin. Le `.env` est cherché **d'abord au-dessus de la racine web**. Si le serveur
web était mal configuré et servait les fichiers du projet en clair, un `.env` placé
hors du webroot resterait inaccessible depuis Internet. C'est une défense en
profondeur.

### 3.3 La dépendance

Fichier : **`composer.json`**

```json
"aws/aws-sdk-php": "^3.387"
```

Le SDK AWS officiel. Il fournit la classe `Aws\S3\S3Client` utilisée par tout le
projet pour parler à B2.

---

## 4. Le client S3 : `DocumentStorage`

Fichier : **`app/Services/Orientation/DocumentStorage.php`** (563 lignes)

C'est **le cœur de l'intégration** et le fichier central du mémoire. C'est la
**seule** classe du projet qui instancie un client S3 ; tout accès à B2 passe
obligatoirement par elle.

### 4.1 La configuration du client (constructeur)

```php
$this->s3 = new S3Client([
    'version'                 => 'latest',
    'region'                  => $region,
    'endpoint'                => 'https://' . $endpoint,   // ← Backblaze, pas AWS
    'use_path_style_endpoint' => true,                     // ← indispensable
    'credentials'             => ['key' => $keyId, 'secret' => $appKey],
    'http' => ['connect_timeout' => 8, 'timeout' => $timeoutTotal ?? 90],
    'retries' => 2,
]);
```

Quatre décisions à expliquer :

1. **`endpoint`** — c'est la seule ligne qui « transforme » un client AWS en client
   Backblaze. Le protocole reste S3.
2. **`use_path_style_endpoint => true`** — AWS adresse les buckets en *virtual-hosted
   style* (`https://mon-bucket.s3.amazonaws.com/cle`). B2 attend le *path style*
   (`https://endpoint/mon-bucket/cle`). Sans ce drapeau, toutes les requêtes échouent.
3. **Les délais d'attente** — `connect_timeout: 8` fait échouer vite si B2 est
   injoignable ; `timeout: 90` borne un appel qui « pend ». Le commentaire du code
   explique pourquoi cette borne a été ajoutée : sans elle, un B2 silencieux menait
   au `max_execution_time` de PHP, c'est-à-dire à une **erreur fatale** et à une
   **page entièrement blanche** chez le candidat. Un appelant peut passer `0` au
   constructeur pour lever la borne (`new DocumentStorage(0)` pour les gros uploads
   serveur).
4. **`retries: 2`** — deux nouvelles tentatives automatiques sur erreur réseau.

### 4.2 La validation en amont : les listes blanches

La classe ne fait pas confiance au navigateur. Trois listes blanches de types MIME,
chacune avec sa limite de taille :

| Constante | Contenu | Taille max |
|---|---|---|
| `MIME_WHITELIST` | PDF + images bitmap | 10 Mo |
| `IMAGE_MIME_WHITELIST` | JPEG, PNG, WebP, GIF, BMP, TIFF, AVIF, HEIC, ICO | 15 Mo (`IMAGE_MAX_SIZE_BYTES`) |
| `VIDEO_MIME_WHITELIST` | MP4, WebM, OGG, MOV, AVI, MKV, WMV, MPEG, 3GP | 500 Mo |

**Le SVG est explicitement exclu** de toutes les listes. Le commentaire du code
donne la raison : un SVG est un document XML **exécutable**, donc un vecteur XSS.

La méthode privée `validerFichier()` applique la règle décisive :

```php
$finfo    = new \finfo(FILEINFO_MIME_TYPE);
$mimeReel = $finfo->file($tmpPath);       // le type RÉEL, lu dans les octets
```

**Le type déclaré par le navigateur n'est jamais cru.** Seul `finfo`, qui inspecte
les octets du fichier, fait foi. Renommer `virus.php` en `photo.jpg` ne trompe pas
cette vérification.

### 4.3 L'API publique de la classe

| Méthode | Rôle |
|---|---|
| `upload()` | Dépose un fichier validé (PDF/image) — usage général |
| `uploadImage()` | Dépose une image éditoriale, extension déduite du MIME réel |
| `uploadVideo()` | Dépose une vidéo (validation + limite 500 Mo) |
| `uploadChiffre()` | Dépose une pièce de dossier **chiffrée** (section 7) |
| `uploadContent()` | Dépose une chaîne générée côté serveur (PDF dompdf, HTML) |
| `presignPut()` | Fabrique une URL PUT présignée (upload direct navigateur) |
| `signedUrl()` | Fabrique une URL GET présignée (lecture temporaire) |
| `getContent()` | Lit les octets bruts d'un objet |
| `getContentClair()` | Lit un objet en **déchiffrant si nécessaire** |
| `objectMeta()` | `headObject` : taille + type réel, sans télécharger |
| `delete()` | Supprime un objet |
| `ping()` | Test de connexion (liste 1 objet d'un préfixe inexistant) |
| `contraintesMedia()` / `extensionMedia()` | Source **unique** des règles par type de média (partagée entre l'endpoint de signature et la validation post-upload) |

---

## 5. Les quatre chemins d'écriture

### 5.1 Upload serveur classique

`navigateur → PHP ($_FILES) → validation finfo → putObject → B2`

Utilisé pour tout ce qui est petit : signature de gestionnaire, images
d'actualités, documents de formation.

Appelants principaux :
- `app/Controllers/Gestionnaire/ParametresController.php` — signature/cachet
- `app/Controllers/Gestionnaire/ActualiteController.php` — illustrations
- `app/Controllers/Gestionnaire/FormationController.php` — pièces de formation

### 5.2 Upload chiffré des pièces de dossier

`navigateur → PHP → validation → chiffrement AES-256-GCM → putObject → B2`

Fichier : **`app/Services/Orientation/PieceDossier.php`** (ligne ~156)

```php
(new DocumentStorage())->uploadChiffre($fichier['tmp_name'], $cle, $mimeReel);
```

Détail à ne pas manquer : **le fichier est validé AVANT d'être chiffré**. Chiffrer
d'abord reviendrait à accepter n'importe quoi, puisque `finfo` ne verrait plus
qu'un binaire opaque.

### 5.3 Upload direct navigateur → B2 (URL présignée)

C'est la partie la plus intéressante techniquement.

**Le problème :** une vidéo de 500 Mo, à environ 1 Mo/s sur une connexion gabonaise,
demande plus de huit minutes. Si ces octets transitent par PHP, la requête dépasse
le délai d'attente FastCGI/nginx → erreur 500, établissement jamais créé.

**La solution :** les octets ne passent plus par PHP du tout.

```
1. Navigateur → POST /admin/orientation/presign   { kind, contentType, filename }
2. PHP        → valide, fabrique la clé, appelle presignPut(), renvoie { url, key }
3. Navigateur → PUT <url>  (les 500 Mo vont DIRECTEMENT chez Backblaze)
4. Navigateur → soumet le formulaire avec la CLÉ seule (quelques octets)
5. PHP        → headObject() pour vérifier taille et type réels côté serveur
```

Fichiers concernés :

| Fichier | Rôle |
|---|---|
| `config/routes.php` (ligne 255) | `POST /admin/orientation/presign` — protégé par `['admin', 'csrf']` |
| `app/Controllers/Admin/OrientationController.php` (méthode `presign`, ~ligne 160) | Valide le type, génère une clé aléatoire `prefix/AAAA/MM/<16 hex>.ext`, renvoie l'URL signée |
| `public/assets/js/orientation-media-upload.js` | Côté navigateur : demande la signature, exécute le `PUT` avec barre de progression, injecte les clés dans le formulaire, **désactive les `<input file>`** pour que les octets ne repartent pas |

Trois garde-fous à souligner :

- **Seul le `Content-Type` est signé.** Le client doit le renvoyer à l'identique,
  sinon B2 répond `SignatureDoesNotMatch`.
- **La clé est générée par le serveur**, jamais par le client : aucun contrôle de
  l'attaquant sur l'emplacement d'écriture.
- **Repli sans JavaScript** : si le script échoue, le formulaire classique
  (upload serveur) reste fonctionnel côté PHP.

### 5.4 Dépôt de documents générés

Les PDF produits par dompdf (attestation, récépissé, reçu, proforma) sont poussés
en mémoire, sans passer par un fichier temporaire, via `uploadContent()` :

- `app/Services/Orientation/AttestationDossierService.php`
- `app/Services/Orientation/RecuService.php`
- `app/Services/Orientation/RecepisseDossierService.php`
- `app/Controllers/Gestionnaire/CandidatureGestionnaireController.php`

---

## 6. Les trois chemins de lecture

### 6.1 URL signée (documents non sensibles)

```php
$url = (new DocumentStorage())->signedUrl($cle, 10); // valable 10 minutes
```

Utilisée pour les reçus de paiement :
`app/Controllers/Gestionnaire/PaiementGestionnaireController.php`,
`app/Controllers/Admin/PaiementsOrientationController.php`.

Le navigateur télécharge directement depuis B2 ; le serveur ne fait que signer.

### 6.2 Proxy applicatif (pièces justificatives)

Route : `GET /gestionnaire/candidatures/{id}/document/{documentId}`
(`config/routes.php`, ligne ~369)

Le commentaire du fichier de routes explique le choix :

> *« Elle transite par l'application au lieu d'une URL signée Backblaze : les pièces
> sont chiffrées au repos (AES-256-GCM), et l'appartenance du dossier est
> revérifiée à chaque ouverture. »*

Deux raisons, donc :
1. une URL signée rendrait au navigateur un **binaire chiffré illisible** ;
2. une URL signée, une fois copiée, **échappe à tout contrôle de droits**.

Implémentation : `CandidatureGestionnaireController::document()` (ligne ~314) →
`getContentClair()`.

### 6.3 Data URI mis en cache (logos, cachets)

Voir section 8.

---

## 7. Chiffrement des pièces justificatives

Fichier : **`app/Core/Crypto.php`** (213 lignes)

### 7.1 La menace traitée

Le bucket est privé — mais « privé » signifie « protégé par une clé d'API ». Toute
personne obtenant cette clé (fuite du `.env`, sauvegarde mal rangée, accès console
B2, réquisition chez l'hébergeur) lirait **toutes les pièces d'identité de la
plateforme sans jamais toucher au serveur**.

Le chiffrement applicatif casse ce lien : un bucket intégralement exfiltré ne rend
plus rien de lisible.

### 7.2 Les trois décisions de conception

**AES-256-GCM, et non « AES tout court ».** GCM est un mode *authentifié* : il
produit une étiquette de 16 octets qui scelle le contenu. Un octet modifié dans le
bucket et le déchiffrement **échoue**, au lieu de rendre une bouillie qu'on
afficherait comme un document valide.

**La donnée associée (AAD) est la clé de stockage.** Chaque objet est chiffré en
liant son contenu à **son propre chemin B2**. Déplacer le fichier chiffré du
dossier n°12 vers le dossier n°48 depuis la console Backblaze ne suffit donc pas à
le faire lire : l'AAD ne correspond plus, le déchiffrement échoue. Sans ce détail,
quelqu'un ayant accès au bucket pourrait **intervertir les pièces de deux candidats**
et fausser une décision d'inscription.

**Compatibilité ascendante.** Toute enveloppe chiffrée commence par une signature
reconnaissable. À la lecture, `dechiffrerSiBesoin()` rend tel quel tout contenu qui
ne la porte pas. Les pièces déposées avant l'activation du chiffrement restent donc
lisibles : **aucune migration, aucune pièce perdue.**

### 7.3 La clé

Ordre de résolution (`Crypto`, ligne ~192) :
1. `APP_ENCRYPTION_KEY` (32 octets en base64) — permet une rotation sans toucher au reste ;
2. sinon, dérivation **HKDF-SHA256** de `APP_SECRET`, étiquetée par domaine.

La dérivation par domaine évite qu'une fuite sur les jetons ou les Hashid donne la
clé documentaire.

### 7.4 Effet de bord sur B2

Un objet chiffré est annoncé à B2 comme `application/octet-stream`, avec la
métadonnée `ca-chiffre: 1` (repère d'audit : `headObject` suffit à distinguer les
objets chiffrés sans les télécharger). Le vrai type reste en base, colonne
`documents_orientation.mime_type`, et c'est lui qui est servi au navigateur.

---

## 8. Résilience : cache, miroir local, quota

### 8.1 L'incident fondateur

Backblaze facture les **transactions** (classes A, B, C), pas seulement le stockage.
Le jour où le compte a atteint son plafond de transactions de classe B (lectures),
**plus une seule image ne s'est affichée**, en production comme en local. Cause
aggravante : une page de l'espace élève affichant dix dossiers du même établissement
redemandait dix fois le même logo.

### 8.2 La chaîne de défense en trois étages

```
1. Mémoire du process  (static $memoire)  → même requête, même logo = 1 seul calcul
2. Cache partagé       (Redis, repli disque) → 6 h
3. Miroir local        (storage/medias/)     → PERMANENT
          ↓ seulement si les trois échouent
      Backblaze B2
```

**Fichier `app/Services/Orientation/LogoDataUri.php`** — redimensionne le logo à
480 px via GD, le ré-encode, le rend en data URI, puis le met en cache. Sans ce
travail, un logo de plusieurs Mo répété en en-tête, filigrane et signature faisait
des PDF de 25 Mo.

Détail fin à citer : le **TTL d'échec est de 60 secondes**, pas 6 heures. Mémoriser
un échec six heures ferait survivre la panne longtemps après sa résolution — un
plafond relevé à 9 h laisserait la plateforme sans images jusqu'à 15 h.

**Fichier `app/Services/Orientation/MiroirMedia.php`** — l'idée-clé du dispositif,
résumée par son propre commentaire :

> *« Un cache ordinaire ne protège pas de cela : il expire, et Redis peut être vidé
> ou redémarré. Il faut une ARCHIVE, pas un cache — une copie qui reste, et qui
> répond quand le stockage distant ne répond plus. »*

Caractéristiques :
- stocke uniquement des **dérivés** (redimensionnés, ré-encodés) ≤ 1 Mio ;
- chemin calculé par `sha1()` de la clé → **aucune traversée de répertoire possible** ;
- écriture **atomique** (fichier temporaire + `rename`) : jamais de lecture partielle ;
- ne lève **jamais** d'exception — « un miroir en panne est un miroir absent » ;
- explicitement **pas une sauvegarde** : B2 reste la source de vérité.

**Fichier `app/Services/Orientation/SignatureDataUri.php`** — même mécanisme pour
les signatures/cachets des gestionnaires.

**Fichiers `app/Core/Cache.php` et `app/Core/CacheFichier.php`** — le cache était
initialement Redis-seul ; un repli disque a été ajouté pour qu'il existe réellement
même sans Redis.

### 8.3 Économiser les transactions côté tâches planifiées

`bin/taches-orientation.php` (lignes ~211-223) pose un **verrou fichier**
(`storage/locks`) : deux exécutions qui se chevauchent régénéreraient les mêmes PDF
et **doubleraient la consommation du quota B2**.

---

## 9. Le navigateur parle à B2 : CSP et upload direct

Fichier : **`index.php`** (bloc `$csp`, lignes ~295-310)

La Content Security Policy énumère les origines autorisées. L'upload direct et la
lecture des vidéos exigent **deux** déclarations distinctes :

```
connect-src ... https://*.backblazeb2.com ...   // requêtes fetch/XHR (le PUT)
media-src   ... https://*.backblazeb2.com ...   // balises <video>
```

**Piège classique, à documenter dans le mémoire :** déclarer `media-src` seul suffit
à lire les vidéos mais **bloque silencieusement l'upload**, car un `PUT` en XHR
relève de `connect-src`. Les deux sont nécessaires.

Côté B2, la **configuration CORS du bucket** doit autoriser la méthode `PUT` depuis
l'origine de la plateforme — configuration à faire dans la console Backblaze, hors
du code.

---

## 10. La base de données : où sont rangées les clés

**Règle absolue : la base ne stocke jamais un fichier, ni une URL — seulement une clé B2.**

Une URL expirerait ; une clé est éternelle et permet de re-signer à la demande.

| Table | Colonne | Contenu | Migration |
|---|---|---|---|
| `etablissements` | `logo_cle` | logo | `Database/migration_orientation_v1.sql` |
| `etablissements` | `signature_cle` | signature/cachet | `Database/migration_signature_attestation.sql` |
| `etablissements` | `video_cle` | vidéo de présentation | `Database/migration_media_localisation.sql` |
| `documents_orientation` | `cle_b2`, `mime_type` | pièces justificatives chiffrées | `Database/migration_orientation_v1.sql` |
| reçus / attestations | `cle_pdf` | PDF générés | `migration_orientation_v1.sql`, `migration_attestations.sql` |

---

## 11. Exploitation : scripts, tâches, déploiement

### `bin/medias-prechauffer.php`

```bash
php bin/medias-prechauffer.php            # archive les logos/cachets manquants
php bin/medias-prechauffer.php --forcer   # re-télécharge tout
php bin/medias-prechauffer.php --etat     # diagnostic, n'écrit rien
```

Télécharge une fois chaque logo et cachet et en archive le dérivé local. Après son
passage, la plateforme n'a **plus besoin de B2** pour afficher ces images. À lancer
après un premier déploiement, après le rétablissement du stockage, ou après l'ajout
d'un établissement. Coût : une transaction par média — moins de vingt au total.

Le script recharge lui-même le `.env` (il tourne en CLI, hors du cycle `index.php`)
et refuse de s'exécuter hors ligne de commande (`PHP_SAPI !== 'cli'` → 403).

### `bin/taches-orientation.php`

Tâches planifiées (cron) produisant des PDF téléversés sur B2 ; protégées par verrou
(section 8.3).

### `deploy.sh`

```bash
--exclude=.env        # secrets prod, différents du local
--exclude=storage/    # runtime : logs, sessions, miroir des médias
```

Deux exclusions majeures pour notre sujet :
- **`.env` n'est jamais déployé** — les identifiants B2 de production sont saisis une
  seule fois sur le serveur et ne quittent jamais la machine ;
- **`storage/` non plus** — le miroir local se reconstruit tout seul.

### Sécurité de l'historique Git

`.gitignore` exclut `.env` ; `.gitleaks.toml` configure l'analyseur de secrets qui
vérifie qu'aucune clé n'a été commise.

---

## 12. Inventaire complet des fichiers

### Configuration et dépendances

| Fichier | Rôle |
|---|---|
| `.env` | Les 5 variables `B2_*` + `APP_SECRET` / `APP_ENCRYPTION_KEY` (non versionné) |
| `.gitignore` | Exclut `.env` du dépôt |
| `.gitleaks.toml` | Détection de secrets commis par erreur |
| `composer.json` / `composer.lock` | Dépendance `aws/aws-sdk-php ^3.387` |
| `index.php` | Chargement du `.env` (l. 37-70) ; CSP `connect-src` / `media-src` (l. 295-310) ; filet anti-fatal (l. 367+) |

### Cœur de l'intégration

| Fichier | Rôle |
|---|---|
| **`app/Services/Orientation/DocumentStorage.php`** | **Client S3 unique. Toute la configuration B2, la validation MIME, les uploads, les URL signées.** |
| `app/Core/Crypto.php` | AES-256-GCM, AAD = clé de stockage, dérivation HKDF |

### Résilience et performance

| Fichier | Rôle |
|---|---|
| `app/Services/Orientation/MiroirMedia.php` | Archive locale permanente des dérivés |
| `app/Services/Orientation/LogoDataUri.php` | Logo → data URI, mémoire + cache + miroir |
| `app/Services/Orientation/SignatureDataUri.php` | Idem pour signatures/cachets |
| `app/Core/Cache.php` | Cache partagé (Redis) |
| `app/Core/CacheFichier.php` | Repli disque du cache |

### Écriture vers B2

| Fichier | Rôle |
|---|---|
| `app/Services/Orientation/PieceDossier.php` | Règles métier des pièces + `uploadChiffre()` |
| `app/Controllers/Admin/OrientationController.php` | `presign()` + validation `headObject` + médias établissement |
| `app/Controllers/Gestionnaire/ParametresController.php` | Signature/cachet |
| `app/Controllers/Gestionnaire/ActualiteController.php` | Images d'actualités (15 Mo) |
| `app/Controllers/Gestionnaire/FormationController.php` | Documents de formation |
| `app/Controllers/Orientation/CandidatureController.php` | Pièces du parcours candidat |
| `app/Services/Orientation/AttestationDossierService.php` | PDF d'attestation |
| `app/Services/Orientation/RecuService.php` | PDF de reçu |
| `app/Services/Orientation/RecepisseDossierService.php` | PDF de récépissé |
| `app/Services/Orientation/FactureProformaService.php` | PDF de proforma |
| `app/Services/Orientation/PdfStream.php` | Livraison HTTP des PDF |

### Lecture depuis B2

| Fichier | Rôle |
|---|---|
| `app/Controllers/Gestionnaire/CandidatureGestionnaireController.php` | Proxy de consultation des pièces chiffrées (l. ~314) |
| `app/Controllers/Gestionnaire/PaiementGestionnaireController.php` | URL signée du reçu (10 min) |
| `app/Controllers/Admin/PaiementsOrientationController.php` | Idem, côté admin |
| `app/Controllers/Orientation/PublicController.php` | Médias publics d'établissement |
| `app/Controllers/Orientation/MarketplaceController.php` | Médias du marketplace |
| `app/Controllers/Orientation/EspaceEleveOrientationController.php` | Pièces côté candidat |
| `app/Controllers/MediaController.php` | Route `/media/{type}/{fichier}` — repli **local** `storage/uploads/`, à ne pas confondre avec B2 |

### Navigateur

| Fichier | Rôle |
|---|---|
| `public/assets/js/orientation-media-upload.js` | Upload direct navigateur → B2 (presign + PUT + progression + repli) |

### Routage

| Fichier | Lignes | Rôle |
|---|---|---|
| `config/routes.php` | 255 | `POST /admin/orientation/presign` (`admin` + `csrf`) |
| `config/routes.php` | ~369 | Proxy de consultation d'une pièce |
| `config/routes.php` | 30 | `/media/{type}/{fichier}` (repli local) |

### Base de données

`Database/migration_orientation_v1.sql`, `migration_signature_attestation.sql`,
`migration_media_localisation.sql`, `migration_attestations.sql`,
`migration_colonnes_derive.sql`, `MISE_A_JOUR_PROD_2026-09-01.sql`

### Exploitation et tests

| Fichier | Rôle |
|---|---|
| `bin/medias-prechauffer.php` | Préchauffage du miroir local |
| `bin/taches-orientation.php` | Tâches planifiées + verrou anti-doublon de quota |
| `deploy.sh` | Exclut `.env` et `storage/` du déploiement |
| `tests/orientation/lot0.php` … `lot12.php` | Tests d'intégration touchant les flux B2 |

---

## 13. Glossaire

**Stockage objet** — Système où les données sont manipulées comme des objets
indivisibles identifiés par une clé, sans hiérarchie de répertoires réelle.

**API compatible S3** — Interface réimplémentant le protocole d'Amazon S3, ce qui
permet de réutiliser les outils et bibliothèques de l'écosystème S3.

**Path style / Virtual-hosted style** — Deux façons d'inscrire le nom du bucket dans
l'URL. B2 impose la première ; d'où `use_path_style_endpoint => true`.

**URL présignée** — URL contenant une signature cryptographique autorisant une
opération précise, sur un objet précis, pendant une durée limitée.

**AES-256-GCM** — Chiffrement symétrique authentifié : confidentialité **et**
détection de toute altération.

**AAD (Additional Authenticated Data)** — Donnée non chiffrée mais scellée par
l'étiquette d'authentification. Ici : la clé de stockage de l'objet.

**HKDF** — Fonction de dérivation de clé : produit plusieurs clés indépendantes à
partir d'un secret unique.

**CSP (Content Security Policy)** — En-tête HTTP énumérant les origines qu'une page
est autorisée à contacter.

**CORS** — Mécanisme par lequel un serveur (ici B2) autorise un navigateur venant
d'une autre origine à lui adresser des requêtes.

**Transaction B2** — Unité de facturation des appels d'API, distincte du volume
stocké. C'est son plafond qui a motivé le dispositif de la section 8.

---

*Document de référence interne — Connect'Academia, Département Informatique.*
