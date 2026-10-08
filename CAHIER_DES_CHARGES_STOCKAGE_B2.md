# Cahier des charges — Intégration d'un stockage objet Backblaze B2

**Projet :** Connect'Academia — plateforme d'orientation et d'inscription universitaire
**Émetteur :** Département Informatique, Connect'Academia
**Destinataire :** Stagiaire — travail de mémoire de licence
**Version :** 1.0
**Date de remise :** _______________
**Échéance :** _______________

---

## Avertissement

Ce document décrit **un travail à réaliser**, pas un existant à documenter.
La plateforme stocke aujourd'hui ses fichiers sur le disque du serveur applicatif.
Il vous revient de concevoir, implémenter, tester et documenter le passage à un
stockage objet Backblaze B2.

Le cahier des charges fixe **le quoi** et **les contraintes**. Le **comment** est
votre part du travail : vous proposez l'architecture, vous justifiez vos choix,
vous les défendez en revue. Toute exigence portant un identifiant (`EX-xx`) est
contractuelle : elle sera vérifiée une par une à la recette.

---

## Sommaire

1. [Contexte et problème à résoudre](#1-contexte-et-problème-à-résoudre)
2. [Périmètre](#2-périmètre)
3. [Exigences fonctionnelles](#3-exigences-fonctionnelles)
4. [Exigences de sécurité](#4-exigences-de-sécurité)
5. [Exigences de résilience et de coût](#5-exigences-de-résilience-et-de-coût)
6. [Contraintes d'architecture imposées](#6-contraintes-darchitecture-imposées)
7. [Découpage en lots](#7-découpage-en-lots)
8. [Livrables](#8-livrables)
9. [Recette : procédure de vérification](#9-recette--procédure-de-vérification)
10. [Points de vigilance signalés](#10-points-de-vigilance-signalés)
11. [Grille d'évaluation](#11-grille-dévaluation)
12. [Glossaire](#12-glossaire)

---

## 1. Contexte et problème à résoudre

Connect'Academia manipule trois familles de fichiers lourds :

| Famille | Exemples | Volume unitaire | Sensibilité |
|---|---|---|---|
| Pièces justificatives d'inscription | acte de naissance, CNI, photo d'identité, relevés de notes | 0,5 à 3 Mo | **Données personnelles** |
| Médias d'établissement | logo, photos de campus, vidéo de présentation | 50 Ko à 500 Mo | Public |
| Documents générés | attestation, récépissé, reçu, facture proforma (PDF) | 100 à 800 Ko | Nominatif |

Le stockage sur disque local pose trois problèmes que le stage doit résoudre :

1. **Volume** — une seule vidéo de présentation par établissement suffit à saturer
   le disque du VPS.
2. **Durabilité** — le disque du serveur n'est pas répliqué. Une pièce d'identité
   perdue est une inscription perdue, et un candidat lésé.
3. **Couplage** — changer de serveur impose aujourd'hui de déplacer des gigaoctets
   de données utilisateurs.

Le fournisseur retenu par la direction technique est **Backblaze B2**, pour son coût
et parce qu'il expose une **API compatible S3**. Ce choix n'est pas à rediscuter ;
en revanche, vous devrez expliquer dans votre mémoire ce que « compatible S3 »
implique concrètement pour le code.

---

## 2. Périmètre

### 2.1 Dans le périmètre

- Tous les fichiers téléversés par un utilisateur (candidat, gestionnaire, admin).
- Tous les PDF produits par l'application.
- Les médias éditoriaux (actualités, formations, médias d'établissement).
- La migration des colonnes de base de données nécessaires.
- La documentation d'exploitation.

### 2.2 Hors périmètre

- Les fichiers statiques du site (CSS, JS, images de gabarit) : ils restent servis
  par le serveur web.
- Les sauvegardes de la base de données.
- Le choix du fournisseur.

### 2.3 Contraintes de non-régression

La plateforme est **en production**. Aucune interruption de service n'est acceptée,
et **aucun fichier déjà déposé ne doit devenir illisible**. Toute rupture de
compatibilité est un échec de recette, quelle que soit la qualité du reste.

---

## 3. Exigences fonctionnelles

### 3.1 Dépôt de fichiers

| ID | Exigence |
|---|---|
| **EX-01** | L'application doit pouvoir déposer sur B2 un fichier reçu par formulaire HTTP (`$_FILES`). |
| **EX-02** | L'application doit pouvoir déposer un contenu **généré en mémoire** (un PDF produit par dompdf) sans écrire de fichier temporaire sur le disque. |
| **EX-03** | Le dépôt d'un fichier volumineux — **jusqu'à 500 Mo** pour une vidéo — ne doit provoquer **aucune erreur 500** ni aucun dépassement du délai d'attente du serveur web. |
| **EX-04** | L'emplacement de destination (la clé de l'objet) est **calculé par le serveur**. Le client ne le choisit jamais, ni directement, ni par le nom de fichier qu'il envoie. |
| **EX-05** | Si le stockage distant est injoignable, l'échec doit être **explicite et rapide** : message d'erreur à l'utilisateur, trace dans les journaux. Jamais de page blanche, jamais d'attente silencieuse de plusieurs minutes. |

> **EX-03 — précision d'usage.** Sur une connexion gabonaise à ~1 Mo/s, 500 Mo
> demandent plus de huit minutes. Toute conception qui fait transiter ces octets par
> le processus PHP sera refusée en revue : à vous de proposer une solution où ce
> n'est pas le cas, et de l'argumenter.

### 3.2 Consultation de fichiers

| ID | Exigence |
|---|---|
| **EX-06** | Un document non sensible (reçu de paiement) doit pouvoir être ouvert par le navigateur **sans transiter par le serveur applicatif**, et l'autorisation d'accès doit être **limitée dans le temps**. |
| **EX-07** | Une pièce justificative d'identité ne doit **jamais** être accessible par une URL qui resterait valable après avoir été copiée, transmise ou mise en favori. |
| **EX-08** | À chaque ouverture d'une pièce justificative, l'appartenance du dossier à l'établissement demandeur doit être **revérifiée côté serveur**. |
| **EX-09** | Il doit être possible de connaître la taille et le type réel d'un objet **sans le télécharger**. |
| **EX-10** | La suppression d'un dossier doit supprimer les objets associés sur B2. |

> EX-06 et EX-07 se contredisent en apparence. Ils ne visent pas les mêmes fichiers.
> Vous devez donc livrer **deux chemins de lecture distincts** et écrire, dans votre
> documentation, la règle qui dit lequel s'applique à quel fichier.

### 3.3 Base de données

| ID | Exigence |
|---|---|
| **EX-11** | La base de données ne stocke **ni le fichier, ni une URL** : uniquement l'identifiant de l'objet dans le stockage. |
| **EX-12** | Le type MIME réel de chaque pièce justificative est conservé en base. |
| **EX-13** | Toute colonne ajoutée fait l'objet d'un script de migration SQL rejouable, déposé dans `Database/`, idempotent (rejouable deux fois sans erreur). |

> **EX-11 est une exigence de fond, pas une préférence de style.** Il vous sera
> demandé en soutenance d'expliquer pourquoi stocker une URL en base est une faute
> de conception ici.

---

## 4. Exigences de sécurité

Cette section est **bloquante**. Une seule exigence non satisfaite invalide la recette.

| ID | Exigence |
|---|---|
| **SEC-01** | Le conteneur de stockage est **privé**. Aucun objet ne doit être lisible sans authentification. Aucune exception, y compris pour les logos. |
| **SEC-02** | Les identifiants du stockage ne figurent **jamais** dans le code source ni dans l'historique Git. Ils sont fournis par configuration d'environnement. |
| **SEC-03** | Le fichier de configuration contenant les secrets n'est ni versionné, ni déployé, ni servi par le serveur web. Vous devez démontrer les trois. |
| **SEC-04** | Le type de fichier est validé **par inspection du contenu réel**, jamais sur la foi de ce que déclare le navigateur ni de l'extension du fichier. |
| **SEC-05** | Les types acceptés sont définis par **liste blanche**, avec une limite de taille par famille. Tout ce qui n'est pas explicitement autorisé est refusé. |
| **SEC-06** | Le format **SVG est interdit** au téléversement. Vous justifierez cette interdiction en une phrase technique dans la documentation. |
| **SEC-07** | Les pièces justificatives (documents d'identité, relevés) sont **chiffrées par l'application avant dépôt**. Un vol intégral du conteneur ne doit rendre aucun document lisible. |
| **SEC-08** | Le chiffrement doit **détecter toute altération** du contenu : un objet modifié dans le conteneur doit provoquer un échec de lecture, jamais un document corrompu affiché comme valide. |
| **SEC-09** | Un objet chiffré déplacé d'un emplacement à un autre à l'intérieur du conteneur ne doit **plus pouvoir être déchiffré**. |
| **SEC-10** | Les fichiers déposés **avant** l'activation du chiffrement doivent rester lisibles, sans migration et sans perte. |
| **SEC-11** | La clé de chiffrement doit pouvoir être **changée** sans réécrire le code, et ne doit pas être la même que les secrets servant à d'autres usages (jetons, identifiants d'URL). |

> **SEC-09 est l'exigence la plus subtile du lot.** Le scénario redouté : une
> personne ayant accès à la console du fournisseur intervertit les pièces de deux
> candidats. Les documents restent chiffrés, personne ne les lit — mais un dossier
> se voit refusé pour la pièce d'un autre. Votre conception doit rendre cette
> manipulation inopérante. Cherchez du côté des modes de chiffrement qui acceptent
> une **donnée associée**.

---

## 5. Exigences de résilience et de coût

Backblaze facture **les transactions d'API**, pas seulement le volume stocké. Un
plafond de transactions atteint coupe les lectures — donc **toutes les images du
site**, en production comme en local.

| ID | Exigence |
|---|---|
| **RES-01** | Une même image demandée plusieurs fois dans une même page ne doit provoquer **qu'une seule** lecture distante. |
| **RES-02** | Les images récurrentes (logos, cachets) doivent rester affichables **même quand le stockage distant ne répond plus du tout**. Un cache qui expire ne satisfait pas cette exigence : il vous faut une copie locale qui persiste. |
| **RES-03** | La copie locale ne contient que des **dérivés** (images redimensionnées et ré-encodées), pas les originaux, et est bornée en taille par fichier. |
| **RES-04** | L'écriture de la copie locale est **atomique** : aucune lecture ne doit jamais voir un fichier à moitié écrit. |
| **RES-05** | Le chemin de la copie locale est **calculé**, jamais construit à partir de données utilisateur : aucune traversée de répertoire ne doit être possible. |
| **RES-06** | Le mécanisme de secours ne doit **jamais** propager d'exception : un cache en panne est un cache absent, pas une page d'erreur. |
| **RES-07** | La mémorisation d'un **échec** distant doit être de courte durée (ordre de la minute). Justifiez la valeur choisie. |
| **RES-08** | Deux exécutions simultanées des tâches planifiées ne doivent pas régénérer les mêmes fichiers — donc ne pas consommer deux fois le quota. |
| **RES-09** | Un script en ligne de commande doit permettre de **préremplir** la copie locale de tous les médias, et d'en afficher l'état sans rien écrire. |
| **RES-10** | La copie locale n'est **pas une sauvegarde** et ne doit jamais être présentée comme telle. Le stockage distant reste la source de vérité. Écrivez-le dans le code, en commentaire d'en-tête.

> **RES-02 — cadrage.** Vous proposerez une chaîne de repli à plusieurs étages et
> vous la représenterez par un schéma dans le mémoire. Un seul étage de cache sera
> considéré comme insuffisant.

---

## 6. Contraintes d'architecture imposées

Ces points ne sont pas négociables ; ils garantissent que votre travail restera
maintenable après votre départ.

| ID | Contrainte |
|---|---|
| **ARC-01** | **Une seule classe** du projet a le droit d'instancier un client de stockage. Tout accès au stockage passe par elle. Aucun contrôleur ne parle directement au fournisseur. |
| **ARC-02** | Cette classe est le **point unique** où sont écrites les règles par type de média (types acceptés, tailles limites, extensions). Elles ne doivent pas être dupliquées entre la validation à l'entrée et la vérification après coup. |
| **ARC-03** | Emplacement imposé : `app/Services/Orientation/`. Nom imposé : `DocumentStorage`. |
| **ARC-04** | Le chiffrement vit dans une classe séparée, sous `app/Core/`, sans dépendance au stockage. |
| **ARC-05** | La dépendance externe est déclarée dans `composer.json`. Aucun téléchargement manuel de bibliothèque, aucun fichier `vendor/` modifié à la main. |
| **ARC-06** | Le code suit les conventions du projet : PHP 8.3, typage strict des paramètres et retours, commentaires **en français**, noms de méthodes en français quand le domaine métier est français. |
| **ARC-07** | Aucune variable d'environnement nouvelle ne doit être obligatoire pour faire tourner le projet en local sans stockage distant : l'application doit démarrer, même dégradée. |

---

## 7. Découpage en lots

Le travail est découpé en six lots. **Chaque lot est recetté avant de passer au
suivant.** Ne commencez pas le lot 4 si le lot 2 n'est pas validé.

### Lot 1 — Étude préalable et compte de service *(≈ 3 jours)*

- Créer le conteneur de stockage et la clé d'application (accompagnée par le CTO).
- Vérifier que le conteneur est privé.
- Établir la liste des variables de configuration nécessaires et leur mode de chargement.
- **Livrable :** note d'étude de 2 à 3 pages — principe du stockage objet, ce
  qu'implique la compatibilité S3, schéma du modèle cible.

### Lot 2 — Client de stockage et dépôt simple *(≈ 1 semaine)*

- Ajouter la dépendance, écrire la classe imposée par ARC-03.
- Implémenter : dépôt d'un fichier reçu, dépôt d'un contenu en mémoire, lecture,
  consultation des métadonnées, suppression, test de connexion.
- Implémenter la validation (SEC-04, SEC-05, SEC-06).
- Brancher **un seul** cas d'usage réel de bout en bout : le logo d'établissement.
- **Couvre :** EX-01, EX-02, EX-09, EX-10, EX-11, SEC-01 à SEC-06, ARC-01 à ARC-03.

### Lot 3 — Chiffrement des pièces justificatives *(≈ 1 semaine)*

- Écrire la classe de chiffrement (ARC-04).
- Brancher le dépôt et la lecture des pièces de dossier.
- Traiter explicitement la compatibilité ascendante (SEC-10).
- **Couvre :** SEC-07 à SEC-11, EX-12.

### Lot 4 — Les deux chemins de lecture *(≈ 4 jours)*

- Autorisation temporaire pour les documents non sensibles.
- Consultation contrôlée par l'application pour les pièces sensibles.
- Documenter la règle de choix entre les deux.
- **Couvre :** EX-06, EX-07, EX-08.

### Lot 5 — Fichiers volumineux *(≈ 1 semaine)*

- Concevoir et implémenter le dépôt d'une vidéo de 500 Mo sans erreur 500.
- Vérification **côté serveur** du fichier effectivement déposé (taille, type réel).
- Prévoir un fonctionnement dégradé si le JavaScript échoue.
- Adapter la politique de sécurité de contenu (CSP) de l'application et la
  configuration CORS du conteneur.
- **Couvre :** EX-03, EX-04, EX-05.

### Lot 6 — Résilience, exploitation, documentation *(≈ 1 semaine)*

- Chaîne de repli complète (section 5).
- Script de préremplissage en ligne de commande (RES-09).
- Verrou sur les tâches planifiées (RES-08).
- Mise à jour du script de déploiement et des règles d'exclusion.
- Documentation finale.
- **Couvre :** RES-01 à RES-10.

---

## 8. Livrables

| # | Livrable | Format |
|---|---|---|
| L1 | Note d'étude préalable | PDF, 2-3 pages |
| L2 | Code source, une branche Git par lot, messages de commit en français | Dépôt Git |
| L3 | Scripts de migration SQL, rejouables, dans `Database/` | `.sql` |
| L4 | Jeu de tests d'intégration couvrant dépôt, lecture, chiffrement, refus | PHP, dans `tests/orientation/` |
| L5 | Documentation technique : inventaire des fichiers touchés, rôle de chacun | Markdown, racine du projet |
| L6 | Procédure d'exploitation : que faire si le stockage tombe, si le quota est atteint, comment prérremplir la copie locale | Markdown |
| L7 | Journal de bord : décisions prises, alternatives écartées et **pourquoi** | Markdown, tenu au fil de l'eau |
| L8 | Mémoire de licence | Selon les règles de votre établissement |

> **L7 est évalué autant que L2.** Un choix technique sans justification écrite vaut
> zéro, même si le code fonctionne. Notez aussi vos **erreurs** : ce sont les pages
> les plus utiles d'un mémoire d'ingénierie.

---

## 9. Recette : procédure de vérification

Chaque ligne sera exécutée devant vous. Préparez-les : ce sont aussi vos tests.

### 9.1 Fonctionnel

| # | Test | Attendu |
|---|---|---|
| R01 | Déposer un PDF de 2 Mo comme pièce de dossier | Dépôt accepté, clé en base, fichier relisible |
| R02 | Déposer une vidéo de 400 Mo | Aucune erreur 500, aucun dépassement de délai |
| R03 | Supprimer un dossier | Objets associés disparus du conteneur |
| R04 | Couper le réseau, puis ouvrir une page à logos | Logos affichés, aucune erreur visible |
| R05 | Couper le réseau, puis déposer un fichier | Message d'erreur clair en moins de 15 secondes |

### 9.2 Sécurité

| # | Test | Attendu |
|---|---|---|
| R06 | Renommer `script.php` en `photo.jpg` et le téléverser | **Refusé** |
| R07 | Téléverser un SVG | **Refusé** |
| R08 | Construire à la main l'URL publique d'un objet du conteneur | **403 / accès refusé** |
| R09 | Télécharger une pièce justificative depuis la console du fournisseur | Contenu **illisible** |
| R10 | Altérer un octet d'un objet chiffré, puis le lire depuis l'application | **Échec de lecture**, pas un document corrompu |
| R11 | Déplacer un objet chiffré vers l'emplacement d'un autre dossier | **Échec de déchiffrement** |
| R12 | Copier l'URL d'une pièce justificative, la rouvrir depuis une session tierce | **Refusé** |
| R13 | Tenter de faire écrire l'application à un emplacement choisi par le client | **Impossible** |
| R14 | `git log -p` et analyse de secrets sur tout l'historique | **Aucun identifiant** trouvé |
| R15 | Déployer, puis chercher le fichier de configuration sur le serveur cible | **Non déployé** |

### 9.3 Non-régression

| # | Test | Attendu |
|---|---|---|
| R16 | Ouvrir un fichier déposé **avant** le stage | Lisible |
| R17 | Suite de tests existante du projet | **Verte à 100 %** |

---

## 10. Points de vigilance signalés

Signalés, pas résolus. Vous êtes prévenue ; le travail reste le vôtre.

1. **Le protocole S3 a deux façons d'écrire l'adresse d'un conteneur.** Les deux
   existent, une seule fonctionne ici. Si toutes vos requêtes échouent dès la
   première ligne, c'est là qu'il faut regarder.
2. **Les délais d'attente par défaut d'un client HTTP sont trop longs.** Un stockage
   qui ne répond pas doit faire échouer la requête **avant** la limite d'exécution de
   PHP. Sinon : erreur fatale, page entièrement blanche chez le candidat, et rien du
   tout dans les journaux si vous n'avez pas prévu de filet.
3. **Validez avant de chiffrer, jamais l'inverse.** Après chiffrement, l'inspection
   du contenu ne voit plus qu'un binaire opaque : vous accepteriez n'importe quoi.
4. **Une politique de sécurité de contenu distingue les types de requêtes.**
   Autoriser l'affichage d'un média et autoriser une requête sortante ne relèvent pas
   de la même directive. Se tromper de directive donne un blocage **silencieux** :
   aucune erreur visible, l'envoi ne part simplement jamais.
5. **Une URL temporaire signe aussi certains en-têtes.** Si le client n'envoie pas
   exactement ce qui a été signé, le fournisseur refuse la requête. Lisez le message
   d'erreur : il nomme le problème.
6. **Le CORS du conteneur se configure dans la console du fournisseur, pas dans le
   code.** Une erreur de navigateur peut donc venir d'un réglage hors du dépôt.
7. **Un logo de plusieurs mégaoctets répété trois fois dans un PDF donne un PDF de
   plusieurs dizaines de mégaoctets.** Pensez aux dérivés redimensionnés.
8. **Testez avec le quota en tête.** Une boucle de test mal écrite peut consommer
   des milliers de transactions en une nuit. Le plafond du compte est réel, et sa
   saturation coupe le site de production.

---

## 11. Grille d'évaluation

| Critère | Poids |
|---|---|
| Exigences de sécurité satisfaites (section 4) | **30 %** |
| Exigences fonctionnelles satisfaites (section 3) | 20 % |
| Résilience et maîtrise du coût (section 5) | 15 % |
| Respect des contraintes d'architecture (section 6) | 10 % |
| Qualité et couverture des tests (L4) | 10 % |
| Documentation et journal de bord (L5, L6, L7) | 10 % |
| Qualité du code : lisibilité, nommage, commentaires | 5 % |

**Éliminatoire :** un secret retrouvé dans l'historique Git (R14), un objet du
conteneur lisible sans authentification (R08), ou un fichier antérieur au stage
devenu illisible (R16).

---

## 12. Glossaire

**Stockage objet** — Système où les données sont manipulées comme des objets
indivisibles identifiés par une clé, sans hiérarchie réelle de répertoires.

**Bucket (conteneur)** — L'espace de stockage nommé qui contient les objets.

**Clé (key)** — Le chemin identifiant un objet dans le conteneur. C'est cela, et
cela seul, que la base de données conserve.

**Endpoint** — L'adresse du serveur de stockage.

**API compatible S3** — Interface réimplémentant le protocole d'Amazon S3, ce qui
permet de réutiliser les bibliothèques de l'écosystème S3 avec un autre fournisseur.

**URL présignée** — URL contenant une signature cryptographique qui autorise une
opération précise, sur un objet précis, pendant une durée limitée.

**Chiffrement authentifié** — Mode de chiffrement produisant, en plus du texte
chiffré, une étiquette qui permet de détecter toute altération.

**Donnée associée (AAD)** — Donnée non chiffrée mais scellée par l'étiquette
d'authentification. Utile pour lier un contenu chiffré à son contexte.

**CSP (Content Security Policy)** — En-tête HTTP énumérant les origines et les types
de requêtes qu'une page est autorisée à effectuer.

**CORS** — Mécanisme par lequel un serveur autorise un navigateur venant d'une autre
origine à lui adresser des requêtes.

**Transaction** — Unité de facturation des appels d'API, distincte du volume stocké.

---

## Validation

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Émetteur (CTO) | | | |
| Stagiaire — prise de connaissance | | | |

*Document interne — Connect'Academia, Département Informatique.*
