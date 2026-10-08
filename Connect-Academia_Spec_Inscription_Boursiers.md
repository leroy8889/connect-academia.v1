# Connect'Academia — Refonte du parcours d'inscription

**Spécification technique fonctionnelle et de conception**
Version 1 — Août 2026 — Document destiné à l'implémentation

---

## 1. Objet du document

Ce document décrit les modifications à apporter à la plateforme Connect'Academia pour permettre l'inscription des étudiants boursiers et pour basculer le paiement de la scolarité vers le présentiel.

Il est écrit pour être exécutable : chaque section décrit un comportement attendu, les données à stocker, les écrans concernés et les critères permettant de vérifier que la fonctionnalité marche.

Trois changements structurants sont introduits :

- **La situation de bourse devient une donnée déclarée par l'étudiant**, avec trois états possibles dont un état transitoire.
- **Le paiement de la scolarité sort de la plateforme.** L'étudiant paie sur place, à l'établissement. La plateforme ne collecte plus l'argent de la scolarité, mais elle doit continuer à en connaître le montant.
- **L'inscription devient un processus en deux temps.** Le dossier déposé en ligne ne vaut pas inscription. Il est validé à 50 % par l'établissement sur pièces, puis à 100 % après passage en présentiel et paiement.

---

## 2. Vocabulaire

Les termes suivants sont utilisés de manière stricte dans tout le document et doivent l'être dans le code.

| Terme | Définition |
| --- | --- |
| Utilisateur | Toute personne disposant d'un compte : élève, visiteur, agent d'établissement, administrateur |
| Candidat | Un utilisateur ayant déposé au moins un dossier d'inscription non encore validé à 100 % |
| Inscrit | Un utilisateur dont un dossier est passé à 100 % |
| Préinscription | Manifestation d'intérêt sur une filière. Acte léger, sans pièce justificative |
| Dossier d'inscription | L'objet central. Créé au démarrage du formulaire, il porte tout le cycle de vie |
| Récépissé | Document PDF remis à l'étudiant après dépôt du dossier, porteur du QR code |
| Attestation d'inscription | Document PDF remis après validation à 100 %. Preuve d'inscription définitive |
| Validation à 50 % | Décision de l'établissement après analyse des pièces. Le dossier est recevable |
| Validation à 100 % | Décision de l'établissement après passage en présentiel et paiement. Inscription définitive |

**Point d'attention.** Un dossier déposé n'est pas une inscription. Toute l'interface doit le dire explicitement à l'étudiant, sinon la plateforme génère de la frustration et des litiges avec les établissements.

---

## 3. Vue d'ensemble du parcours

```
ÉTUDIANT                          SYSTÈME                        ÉTABLISSEMENT

Étape 1 — Informations
personnelles + situation
de bourse
        |
Étape 2 — Téléversement
des pièces (liste adaptée
au statut de bourse)
        |
Étape 3 — Confirmation
« Dossier envoyé »
        |                    Génère la référence
        |                    Génère le jeton + QR
        |                    Génère le RÉCÉPISSÉ (PDF)
        |                    Statut = SOUMIS (0 %)
        |                    Notifie l'établissement
        |                                                    Reçoit le dossier
Retour dans son espace                                       dans son back-office
Télécharge le récépissé                                              |
        |                                                    Analyse les pièces
        |                                                             |
        |                    <--- décision ------------------- 50 % / pièce manquante
        |                                                             / rejet
Reçoit le message                                                    |
« Dossier validé à 50 % »                                            |
        |                                                             |
Se rend à l'établissement
avec le récépissé imprimé
ou sur téléphone
        |                                                    Scanne le QR code
        |                    Vérifie le jeton en direct  --->  Affiche le dossier
        |                    Journalise le scan                et son statut réel
        |                                                             |
Paie la scolarité sur place                                          |
        |                                                    Saisit montant,
        |                                                    mode de paiement,
        |                                                    référence
        |                                                             |
        |                    Statut = VALIDÉ 100 %  <--------- Valide à 100 %
        |                    Génère l'ATTESTATION
        |                    Génère la COMMISSION
        |                    Journalise tout
        |
Reçoit l'attestation
dans son espace
```

---

## 4. Machine à états du dossier

C'est le cœur du système. Aucun autre chemin que ceux décrits ici ne doit être possible.

### 4.1 Les états

| État | Progression affichée | Signification |
| --- | --- | --- |
| `brouillon` | 0 % | Formulaire commencé, pas encore soumis. Reprenable |
| `soumis` | 0 % | Dossier déposé, en attente d'analyse par l'établissement |
| `piece_manquante` | 0 % | L'établissement demande une ou plusieurs pièces complémentaires |
| `valide_50` | 50 % | Dossier recevable sur pièces. L'étudiant doit se présenter |
| `rejete` | — | Dossier refusé, avec motif obligatoire. État terminal |
| `valide_100` | 100 % | Inscription définitive. État terminal |
| `expire` | — | L'étudiant ne s'est pas présenté dans le délai. État terminal |
| `annule` | — | Annulé par l'étudiant ou l'administration. État terminal |

### 4.2 Les transitions autorisées

| Depuis | Vers | Déclencheur | Acteur |
| --- | --- | --- | --- |
| `brouillon` | `soumis` | Soumission de l'étape 2 | Étudiant |
| `brouillon` | `annule` | Abandon explicite | Étudiant |
| `soumis` | `valide_50` | Validation sur pièces | Établissement |
| `soumis` | `piece_manquante` | Demande de complément | Établissement |
| `soumis` | `rejete` | Refus motivé | Établissement |
| `piece_manquante` | `soumis` | Nouveau téléversement | Étudiant |
| `piece_manquante` | `rejete` | Refus après relance | Établissement |
| `valide_50` | `valide_100` | Passage en présentiel + paiement | Établissement |
| `valide_50` | `expire` | Dépassement du délai | Système (tâche planifiée) |
| `valide_50` | `annule` | Désistement | Étudiant ou établissement |

**Règles absolues :**

- Toute transition passe par une méthode unique du modèle. Aucune écriture directe de la colonne `statut` ailleurs dans le code.
- Toute transition écrit une ligne dans `historique_statut_dossier` et une ligne dans `journal_activite`.
- Un état terminal ne peut plus être quitté, sauf par un super administrateur, et l'action est journalisée avec son identité.
- Le passage à `valide_100` est **impossible** si les champs financiers ne sont pas renseignés.

---

## 5. Le formulaire d'inscription en trois étapes

Le formulaire conserve sa structure en trois étapes. La situation de bourse s'insère dans les deux premières.

### 5.1 Pourquoi ce découpage

La question de la bourse est une **déclaration**, elle appartient donc à l'étape 1 avec les autres informations déclaratives. L'attestation de bourse est une **pièce justificative**, elle appartient donc à l'étape 2 avec les autres fichiers.

Ce découpage a un avantage technique important : la réponse donnée à l'étape 1 détermine la liste des pièces demandées à l'étape 2. La liste des documents à téléverser devient dynamique au lieu d'être figée.

### 5.2 Étape 1 — Informations personnelles

Contenu existant conservé. On ajoute en fin de formulaire un bloc distinct.

**Bloc « Votre situation de bourse »**

Deux boutons radio, un seul choix possible, réponse obligatoire.

| Libellé affiché | Valeur stockée | Champs conditionnels |
| --- | --- | --- |
| Je ne suis pas boursier | `non_boursier` | Aucun |
| Je suis boursier | `boursier` | Numéro d'attestation ANBG (texte, optionnel) |

> **Arbitrage du 31/08/2026 — retrait de « Mon dossier est en cours de traitement à l'ANBG ».**
> Après discussion avec les établissements partenaires, la troisième situation
> (`dossier_en_cours`, avec sa date de dépôt facultative arbitrée le 28/08/2026)
> est SUPPRIMÉE. Elle décrivait une attente, pas une situation : elle n'avait ni
> tarif propre, ni pièce exigible, ni décision possible, et laissait le candidat
> dans un entre-deux qu'aucun écran ne savait résoudre.
> Règle retenue : tant que la bourse n'est pas accordée, le candidat est **non
> boursier** ; le jour où l'ANBG répond, il se déclare boursier lui-même depuis
> son espace (§9.4), à tout moment — y compris après l'inscription définitive.
> Le retrait est financièrement neutre : `dossier_en_cours` était tarifé et
> commissionné exactement comme `non_boursier`. Les dossiers concernés ont été
> basculés par `Database/migration_suppression_dossier_en_cours.sql`, qui trace
> chaque bascule dans `historique_statut_bourse`.

Sous le bloc, un texte court explique pourquoi l'information est demandée. Formulation proposée :

> Cette information nous permet d'adapter votre dossier. Elle n'a aucune incidence sur l'acceptation de votre candidature. Vous pourrez la modifier à tout moment depuis votre espace si votre situation évolue.

**Comportements attendus :**

- Les champs conditionnels s'affichent et se masquent en JavaScript, mais la validation est refaite côté serveur. Ne jamais faire confiance au client.
- L'horodatage de la déclaration est posé par le serveur, jamais transmis par le formulaire.
- La déclaration écrit une ligne dans `historique_statut_bourse` avec `declare_par = 'etudiant'`.

### 5.2 bis Une seule inscription par établissement et par année

**Arbitrage du 31/08/2026.** Un même candidat ne peut ouvrir qu'UNE inscription
par établissement et par année académique.

- **Identité du candidat** : le compte (`eleve_id`) OU l'adresse e-mail du
  dossier. Les deux, parce qu'un dossier ouvert au guichet n'a pas toujours de
  compte rattaché et qu'un candidat peut créer son espace après coup.
- **Ce qui bloque** : un dossier d'inscription VIVANT — `brouillon`, `soumis`,
  `piece_manquante`, `valide_50`, `valide_100` (et les écritures héritées
  `paye`, `accepte`, `inscrit`).
- **Ce qui ne bloque pas** : `rejete`, `expire`, `annule`, `refuse`. Un refus,
  parfois dû à une simple pièce illisible, ne doit pas enfermer le candidat
  pour l'année entière.
- **Périmètre** : les INSCRIPTIONS seulement, au sens de
  `Dossier::SQL_EST_INSCRIPTION`. Les préinscriptions restent libres : ce sont
  des manifestations d'intérêt, et un candidat peut en déposer pour plusieurs
  filières du même établissement.
- **Où c'est appliqué** : `Services\Orientation\UniciteInscription`, seule
  définition de la règle, branchée sur les deux points de création — l'étape 1
  du parcours public et le guichet.
- **Reprise plutôt que refus** : si le dossier bloquant est le PROPRE brouillon
  du candidat (session perdue, autre poste), l'étape 1 le reprend et redirige
  vers l'étape 2 au lieu d'afficher un refus.
- **Pas d'index unique** : la contrainte est partielle (statuts + nature du
  dossier + identité double) et MySQL ne sait pas l'exprimer. La migration
  `migration_unicite_inscription.sql` pose ce qui manque : l'année académique
  sur les dossiers guichet qui n'en portaient pas, et les index de recherche.

### 5.3 Étape 2 — Téléversement des pièces

La liste des pièces est construite dynamiquement selon la réponse de l'étape 1.

**Pièces communes à tous les candidats** (à ajuster selon les besoins réels des établissements) :

| Type | Code interne | Obligatoire | Formats |
| --- | --- | --- | --- |
| Acte de naissance | `acte_naissance` | Oui | PDF, JPG, PNG |
| Relevé de notes du baccalauréat | `releve_notes` | Oui | PDF, JPG, PNG |
| Diplôme ou attestation de réussite | `diplome` | Oui | PDF, JPG, PNG |
| Photo d'identité | `photo_identite` | Oui | JPG, PNG |
| Pièce d'identité | `piece_identite` | Oui | PDF, JPG, PNG |

**Pièce conditionnelle :**

| Statut déclaré | Pièce ajoutée | Obligatoire |
| --- | --- | --- |
| `boursier` | Attestation de bourse ANBG (`attestation_bourse`) | **Oui** |
| `non_boursier` | Aucune | — |

> La pièce `preuve_depot_anbg`, qui n'existait que pour `dossier_en_cours`, a
> été retirée du référentiel par la même migration. Les documents déjà déposés
> sous ce code restent consultables : seule la ligne qui les réclamait disparaît.

**Contraintes techniques sur les fichiers :**

- Taille maximale : 5 Mo par fichier.
- Types acceptés : contrôle du MIME réel côté serveur (`finfo`), pas seulement de l'extension.
- Renommage systématique : le nom original n'est jamais utilisé comme nom de fichier sur le disque ni sur B2.
- Chemin de stockage : `dossiers/{annee}/{etablissement_id}/{dossier_reference}/{type}_{uuid}.{ext}`
- Empreinte SHA-256 calculée et stockée à la réception. Elle sert de preuve d'intégrité et permet de détecter les doublons.
- Stockage sur Backblaze B2 en bucket privé. Aucun accès direct : les téléchargements passent par des URL signées à durée courte, générées par la plateforme après contrôle des droits.
- Aucun fichier téléversé ne doit être servi depuis un répertoire accessible par nginx.

### 5.4 Étape 3 — Confirmation

Écran de confirmation, sans action de saisie.

**Contenu :**

- Un message de félicitations et la confirmation que le dossier a bien été envoyé.
- La référence du dossier, affichée en évidence, au format `CA-2026-ESM-000147`.
- Un rappel explicite : le dossier est en cours d'analyse, l'étudiant n'est pas encore inscrit.
- Une indication des prochaines étapes en trois lignes.
- Un bouton unique : **« Accéder à mon espace »**.

**Ce que le système fait au moment de la soumission**, dans une transaction unique :

1. Passage du dossier de `brouillon` à `soumis`.
2. Attribution de la référence définitive (voir 5.5).
3. Génération du jeton de vérification et du code court.
4. Génération du récépissé PDF et dépôt sur B2.
5. Création de la notification côté étudiant.
6. Création de la notification côté établissement.
7. Écriture dans `historique_statut_dossier` et `journal_activite`.

Si la génération du PDF échoue, elle ne doit pas faire échouer la soumission. Le dossier reste soumis, la génération est rejouée par une tâche de rattrapage, et l'espace étudiant affiche « Récépissé en cours de génération » plutôt qu'une erreur.

### 5.5 Génération de la référence

Format : `CA-{ANNEE}-{CODE_ETAB}-{SEQUENCE}`

- `ANNEE` : année d'entrée de l'année académique, sur 4 chiffres.
- `CODE_ETAB` : code court de l'établissement, 3 à 5 lettres majuscules, unique, stocké en base.
- `SEQUENCE` : compteur par établissement et par année, sur 6 chiffres avec zéros à gauche.

La séquence est incrémentée à l'intérieur d'une transaction avec verrouillage de ligne (`SELECT ... FOR UPDATE`) pour éviter les collisions en cas de soumissions simultanées. Ne pas utiliser un simple `MAX(id) + 1`.

---

## 6. Le récépissé de dépôt de dossier

### 6.1 Nom et rôle

Le document remis à l'étudiant après le dépôt s'appelle **Récépissé de dépôt de dossier**. Ce nom est retenu parce qu'il est juridiquement neutre : il atteste d'un dépôt, pas d'une inscription. Un nom comme « attestation » ou « certificat » laisserait croire à l'étudiant qu'il est inscrit, ce qui est précisément l'erreur à éviter.

Il remplit trois fonctions :

1. Rassurer l'étudiant : son dossier existe et il en a la preuve.
2. Servir de support physique au QR code lors du passage en présentiel.
3. Donner à l'établissement un moyen de retrouver instantanément le dossier.

### 6.2 Disponibilité

Le récépissé est disponible dans l'espace de l'étudiant dès la soumission du dossier, c'est-à-dire à 0 %. Il n'est pas régénéré à chaque changement de statut : c'est le QR code qui porte l'information à jour, pas le papier.

### 6.3 Contenu

**En-tête**

- Logo de l'établissement, à gauche.
- Logo Connect'Academia, à droite.
- Titre : « Récépissé de dépôt de dossier ».
- Année académique concernée.

**Bloc identification du dossier**

- Référence du dossier, en gros caractères.
- Date et heure de dépôt.
- Nom de l'établissement et de la filière demandée.

**Bloc identité du candidat**

- Nom, prénoms, date et lieu de naissance.
- Numéro de téléphone et adresse électronique.
- Photo d'identité si téléversée.

**Bloc situation**

- Situation de bourse déclarée, en toutes lettres.
- Liste des pièces reçues, avec pour chacune la date de réception.

**Bloc vérification**

- Le QR code, taille minimale 30 mm de côté pour rester lisible après impression sur une imprimante bas de gamme ou photographié depuis un écran de téléphone.
- Le code court à 8 caractères, imprimé sous le QR code en caractères espacés.
- Une phrase d'instruction pour l'agent : « Ce document doit être vérifié par scan du code ci-dessus. »

**Mention obligatoire, encadrée et visible**

> Ce récépissé atteste du dépôt d'un dossier de candidature. Il ne constitue pas une attestation d'inscription. L'inscription ne devient définitive qu'après validation par l'établissement et règlement des frais de scolarité.

**Pied de page**

- Généré le, date et heure.
- Empreinte courte du document (12 premiers caractères du SHA-256) pour vérification manuelle.
- Adresse du site de vérification.

### 6.4 Génération technique

Réutiliser la chaîne déjà en place pour le manuel partenaire : gabarit HTML et CSS aux couleurs de la charte, rendu par Playwright vers PDF. Cela évite d'introduire une seconde technologie de génération et réutilise le système de design existant.

Pour le QR code, la bibliothèque `endroid/qr-code` en PHP produit une image PNG que l'on injecte en base64 dans le gabarit HTML. Niveau de correction d'erreur : **Q (25 %)**, afin que le code reste lisible même sur un document froissé, sali ou photocopié. C'est un choix important dans un contexte où le document circulera sur papier.

Le PDF généré est déposé sur B2 et une ligne est écrite dans `documents_generes` avec son empreinte.

---

## 7. Le QR code et la vérification

### 7.1 Principe fondamental

**Le QR code ne contient aucune donnée du dossier.** Il contient une URL portant un jeton opaque :

```
https://connectacademia.io/v/{token}
```

Le jeton est une chaîne aléatoire de 48 caractères issue d'un générateur cryptographiquement sûr (`random_bytes` puis encodage base62 ou hexadécimal). Il n'est ni prévisible ni dérivable de l'identifiant du dossier.

La conséquence est décisive : le récépissé imprimé à 0 % reste valable et exact à 50 %, à 100 %, et même après un rejet. C'est la base de données qui répond, pas le papier. Un document ne peut jamais être « périmé » ni mentir.

### 7.2 Le code court

Le QR code seul ne suffit pas. Il faut prévoir le cas où l'agent n'a pas de téléphone en état de scanner, où le document est trop abîmé, ou où la connexion est mauvaise.

Un **code court de 8 caractères** est donc généré en parallèle et imprimé sous le QR code. Il est saisissable manuellement dans le back-office et donne exactement le même résultat.

Composition : alphabet réduit sans caractères ambigus (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`, sans I, O, 0, 1). Affiché par groupes de 4 caractères séparés par un tiret pour faciliter la lecture à voix haute.

### 7.3 Le parcours de vérification

1. L'agent scanne le QR code avec l'appareil photo de son téléphone ou depuis le back-office.
2. L'URL ouvre la page de vérification.
3. **Si l'agent n'est pas authentifié**, il est redirigé vers la connexion, puis ramené sur la page de vérification. Aucune donnée personnelle n'est affichée à un visiteur anonyme.
4. Le système contrôle, dans cet ordre :
   - Le jeton existe-t-il ?
   - Est-il actif, non révoqué ?
   - L'agent connecté appartient-il bien à l'établissement du dossier ?
   - Quel est le statut réel du dossier ?
5. Le résultat est affiché et **le scan est journalisé dans tous les cas**, y compris en cas d'échec.

### 7.4 Les réponses possibles

| Situation | Affichage | Action possible |
| --- | --- | --- |
| Jeton inconnu | Bandeau rouge, « Document non reconnu » | Aucune |
| Jeton révoqué | Bandeau rouge, « Document annulé » | Aucune |
| Agent d'un autre établissement | Bandeau rouge, « Ce dossier ne concerne pas votre établissement » | Aucune, incident remonté au super admin |
| Dossier `soumis` | Bandeau orange, « Dossier en cours d'analyse, pas encore validé » | Consulter le dossier |
| Dossier `piece_manquante` | Bandeau orange, avec la liste des pièces attendues | Consulter le dossier |
| Dossier `rejete` | Bandeau rouge, avec le motif | Consulter le dossier |
| Dossier `expire` | Bandeau rouge, « Dossier expiré » | Réactiver, si le droit est accordé |
| Dossier `valide_50` | **Bandeau vert**, identité et photo affichées | **Bouton « Valider à 100 % »** |
| Dossier `valide_100` | Bandeau bleu, « Déjà inscrit », date d'inscription | Consulter, imprimer l'attestation |

La couleur du bandeau doit être immédiatement lisible : un agent de guichet ne lit pas un paragraphe, il regarde une couleur.

### 7.5 Sécurité de la vérification

- Limitation de débit sur la route de vérification : au maximum 30 tentatives par minute et par adresse IP, pour empêcher l'énumération de jetons.
- Toute tentative sur un jeton inexistant est journalisée avec l'IP. Au-delà de 20 échecs consécutifs depuis la même IP, alerte au super administrateur.
- Le jeton peut être révoqué et régénéré par le super administrateur, ce qui invalide le récépissé précédent et en génère un nouveau.
- La page de vérification n'affiche jamais les pièces justificatives elles-mêmes, uniquement leur liste et leur statut.

---

## 8. Back-office établissement

### 8.1 Liste des dossiers

Une vue unique avec filtres persistants. Colonnes : référence, nom du candidat, filière, situation de bourse, statut, date de dépôt, ancienneté en jours.

Filtres : statut, filière, situation de bourse, période, recherche libre sur nom et référence.

Un compteur en haut de page par statut, cliquable, agissant comme raccourci de filtre. L'ancienneté doit être visible d'un coup d'œil : au-delà de 7 jours sans traitement, la ligne est signalée visuellement.

### 8.2 Fiche dossier

Trois zones :

**Zone identité** — Toutes les informations de l'étape 1, la situation de bourse déclarée avec son horodatage, et l'historique des changements de statut de bourse s'il y en a eu.

**Zone pièces** — Chaque pièce avec sa vignette ou son aperçu, un bouton de consultation en URL signée, et trois actions individuelles : accepter, refuser avec motif, demander un remplacement.

**Zone décision** — Les actions possibles selon le statut courant :

| Statut courant | Actions proposées |
| --- | --- |
| `soumis` | Valider à 50 % · Demander une pièce · Rejeter |
| `piece_manquante` | Valider à 50 % · Rejeter · Relancer l'étudiant |
| `valide_50` | Valider à 100 % · Annuler · Prolonger le délai |
| `valide_100` | Consulter · Télécharger l'attestation |

### 8.3 La validation à 50 %

Un simple bouton avec confirmation. Un commentaire libre facultatif est transmis à l'étudiant dans sa notification.

Effets :
- Statut puis `valide_50`, `valide_50_le` et `valide_50_par` renseignés.
- Calcul et enregistrement de `expire_le` = date du jour + `delai_expiration_dossier_jours` de l'établissement.
- Notification à l'étudiant : « Votre dossier a été validé à 50 %. Présentez-vous à l'établissement avec votre récépissé pour finaliser votre inscription. »
- Journalisation.

### 8.4 La validation à 100 %

C'est le point le plus sensible du système. C'est le seul endroit où la plateforme capte encore l'information financière, puisque l'argent ne transite plus par elle.

Le formulaire de validation à 100 % impose les champs suivants :

| Champ | Type | Obligatoire | Remarque |
| --- | --- | --- | --- |
| Montant de la scolarité annuelle | Décimal | **Oui** | Base de calcul de la commission |
| Montant encaissé ce jour | Décimal | **Oui** | Peut être un premier versement |
| Mode de paiement | Liste | **Oui** | Espèces, mobile money, virement, chèque |
| Référence du paiement | Texte | Non | Numéro de reçu de l'établissement |
| Date de paiement | Date | **Oui** | Par défaut, la date du jour |

Le montant de la scolarité peut être prérempli depuis la fiche de la filière, mais il reste modifiable, car les situations réelles varient (réductions, fratrie, redoublement).

**Le bouton de validation à 100 % est désactivé tant que les champs obligatoires ne sont pas remplis.** Ce n'est pas une préférence d'interface, c'est la condition qui garantit que la commission pourra être calculée.

Effets de la validation :
- Statut puis `valide_100`.
- Copie du taux de commission de l'établissement dans `taux_commission_applique` du dossier.
- Génération de la ligne dans `commissions`.
- Génération de l'attestation d'inscription en PDF.
- Notification à l'étudiant avec le lien de téléchargement.
- Journalisation complète.

### 8.5 Le scanner intégré

Une page dédiée du back-office ouvre la caméra du téléphone ou de l'ordinateur et lit le QR code, sans passer par une application externe. Bibliothèque JavaScript recommandée : `html5-qrcode`, qui fonctionne sur navigateur mobile sans installation.

Sur la même page, un champ de saisie du code court, en solution de repli.

C'est l'écran qu'un agent de guichet utilisera toute la journée en période de rentrée. Il doit être rapide, tolérant à une mauvaise connexion, et afficher le résultat en une seule vue sans défilement.

---

## 9. Espace étudiant

### 9.1 Tableau de bord

Une carte par dossier d'inscription, affichant :

- Le nom de l'établissement et de la filière.
- La référence du dossier.
- **Une barre de progression à trois paliers : Déposé — Validé à 50 % — Inscrit.** Le palier atteint est mis en évidence.
- Le statut en langage clair, jamais le nom technique de l'état.
- L'action attendue de l'étudiant, s'il y en a une.

### 9.2 Correspondance statut / message affiché

| Statut | Message affiché à l'étudiant |
| --- | --- |
| `soumis` | Votre dossier a été reçu. L'établissement l'analyse. Vous serez notifié. |
| `piece_manquante` | L'établissement demande une pièce complémentaire. Téléversez-la pour poursuivre. |
| `valide_50` | Dossier validé à 50 %. Présentez-vous à l'établissement avec votre récépissé pour finaliser et régler votre scolarité. |
| `rejete` | Votre dossier n'a pas été retenu. Motif : {motif}. |
| `valide_100` | Vous êtes inscrit. Votre attestation d'inscription est disponible. |
| `expire` | Le délai de finalisation est dépassé. Contactez l'établissement. |

### 9.3 Documents

Une section « Mes documents » par dossier :

- Récépissé de dépôt, téléchargeable dès la soumission.
- Attestation d'inscription, apparaissant à 100 %.

Chaque téléchargement passe par une URL signée à durée limitée et est journalisé.

### 9.4 Mise à jour de la situation de bourse

Un étudiant doit pouvoir basculer entre `non_boursier` et `boursier` à tout moment, y compris après la validation à 100 %. C'est le chemin normal du candidat dont la demande ANBG finit par aboutir, parfois des mois après son inscription (cf. l'arbitrage du 31/08/2026, §5.2).

Le formulaire de mise à jour demande la nouvelle situation, l'attestation en pièce jointe si le nouveau statut est `boursier`, et enregistre :

- Une ligne dans `historique_statut_bourse`.
- Une notification à l'établissement.
- Une entrée dans le journal d'activité.

**Le changement de statut de bourse ne modifie jamais la commission ni le statut du dossier.** C'est une information de suivi, pas un paramètre de facturation. Cette règle est essentielle : elle supprime toute incitation à manipuler le statut.

### 9.5 Notifications

Une cloche avec compteur de non-lus. Chaque notification porte un titre, un corps, un lien vers l'objet concerné, et une date. Les notifications sont conservées, jamais supprimées automatiquement.

---

## 10. Commission

### 10.1 Règles

- **La commission est due quel que soit le statut de bourse de l'étudiant.** Aucune exception, aucun traitement particulier.
- Elle est calculée à la validation à 100 %, sur le montant de la scolarité annuelle déclaré.
- Le taux est **modifiable** à trois niveaux, du plus général au plus spécifique :

| Niveau | Emplacement | Usage |
| --- | --- | --- |
| Global | Table `parametres`, clé `taux_commission_defaut` | Valeur par défaut de la plateforme |
| Établissement | `etablissements.taux_commission` | Négocié par partenaire |
| Dossier | `dossiers_inscription.taux_commission_applique` | Copie gelée au moment des 100 % |

Le calcul retient le taux le plus spécifique disponible au moment de la validation.

### 10.2 Le gel du taux

**Point critique.** Une fois le taux copié dans le dossier, il ne change plus jamais, même si le taux de l'établissement est modifié ensuite.

Sans cette règle, modifier le taux d'un établissement de 10 % à 12 % recalculerait toutes les commissions historiques, y compris celles déjà facturées et réglées. Le passé deviendrait mouvant et la comptabilité impossible à défendre.

Une modification du taux d'un établissement ne s'applique donc qu'aux dossiers validés **après** la modification. Le changement lui-même est journalisé : qui, quand, ancienne valeur, nouvelle valeur.

### 10.3 Suivi des commissions

Un écran super admin listant les commissions avec filtres par établissement, période et statut de règlement. Statuts : `a_facturer`, `facturee`, `reglee`, `annulee`.

Une commission est automatiquement annulée si le dossier passe à `annule` par décision du super administrateur.

---

## 11. Espace super administrateur — la traçabilité

C'est le cœur de la valeur de Connect'Academia. La plateforme ne collecte plus l'argent ; elle doit donc être irréprochable sur la donnée.

### 11.1 Fiche utilisateur enrichie

La fiche d'un utilisateur affiche désormais, en plus de ses informations de compte :

**Bloc « Activité sur la plateforme »**

| Indicateur | Contenu |
| --- | --- |
| Compte créé le | Date, heure, canal d'inscription |
| Dernière connexion | Date et heure |
| Préinscriptions | Nombre total, avec la liste |
| Dossiers d'inscription | Nombre total, avec la liste |
| Statut actuel | Visiteur, candidat, ou inscrit |

**Tableau des préinscriptions**

Établissement · Filière · Date · A donné lieu à un dossier d'inscription (oui/non).

**Tableau des dossiers d'inscription**

Référence · Établissement · Filière · Situation de bourse · **Statut avec pourcentage de progression** · Date de dépôt · Date de validation à 50 % · Date de validation à 100 %.

**Frise chronologique complète**

Tous les événements de l'utilisateur dans l'ordre, du plus récent au plus ancien, avec pour chacun : la date et l'heure à la seconde, le type d'événement, l'auteur de l'action, et l'établissement concerné.

### 11.2 Types d'événements à journaliser

| Code | Déclencheur |
| --- | --- |
| `compte_cree` | Création du compte |
| `connexion` | Connexion réussie |
| `preinscription_creee` | Préinscription sur une filière |
| `dossier_demarre` | Ouverture du formulaire, création du brouillon |
| `dossier_soumis` | Soumission de l'étape 2 |
| `recepisse_genere` | Génération du récépissé |
| `recepisse_telecharge` | Téléchargement par l'étudiant |
| `piece_demandee` | L'établissement demande une pièce |
| `piece_televersee` | Ajout ou remplacement d'une pièce |
| `dossier_valide_50` | Validation sur pièces |
| `dossier_rejete` | Rejet, avec le motif |
| `qr_scanne` | Scan du QR code, avec le résultat |
| `dossier_valide_100` | Inscription définitive |
| `attestation_generee` | Génération de l'attestation |
| `commission_generee` | Création de la ligne de commission |
| `statut_bourse_modifie` | Changement de situation de bourse |
| `dossier_expire` | Expiration automatique |
| `taux_commission_modifie` | Modification du taux d'un établissement |

Chaque entrée stocke : l'utilisateur concerné, l'acteur ayant réalisé l'action, son rôle, le type d'objet et son identifiant, l'établissement, un bloc JSON de données contextuelles, l'adresse IP et l'horodatage.

### 11.3 Nature du journal

Le journal d'activité est **en ajout seul**. Aucune route de l'application ne doit permettre de modifier ou de supprimer une entrée. Les droits MySQL du compte applicatif ne doivent autoriser que `INSERT` et `SELECT` sur cette table.

Une purge ou un archivage éventuel se fait par un script d'administration séparé, jamais depuis l'application.

### 11.4 Tableaux de bord

- Entonnoir de conversion : comptes créés puis préinscriptions puis dossiers déposés puis validés à 50 % puis validés à 100 %, global et par établissement.
- Répartition des situations de bourse, globale et par établissement.
- Délai moyen de traitement d'un dossier par établissement, du dépôt à la validation à 50 %.
- Dossiers validés à 50 % en attente depuis plus de 15 jours.
- **Alerte sur les établissements dont le taux de boursiers déclarés s'écarte fortement de la moyenne de la plateforme.**

---

## 12. Schéma de base de données

### 12.1 Modifications de tables existantes

```sql
ALTER TABLE etablissements
  ADD COLUMN code_court VARCHAR(5) NOT NULL UNIQUE AFTER nom,
  ADD COLUMN logo_url VARCHAR(500) NULL,
  ADD COLUMN taux_commission DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  ADD COLUMN mode_paiement_inscription
      ENUM('presentiel','en_ligne','mixte') NOT NULL DEFAULT 'presentiel',
  ADD COLUMN delai_expiration_dossier_jours SMALLINT UNSIGNED NOT NULL DEFAULT 30;

ALTER TABLE utilisateurs
  ADD COLUMN derniere_connexion_le DATETIME NULL,
  ADD COLUMN statut_parcours
      ENUM('visiteur','candidat','inscrit') NOT NULL DEFAULT 'visiteur';
```

### 12.2 Table centrale des dossiers

```sql
CREATE TABLE dossiers_inscription (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference                   VARCHAR(32)  NULL UNIQUE,
  utilisateur_id              BIGINT UNSIGNED NOT NULL,
  etablissement_id            BIGINT UNSIGNED NOT NULL,
  filiere_id                  BIGINT UNSIGNED NOT NULL,
  annee_academique            VARCHAR(9)   NOT NULL,

  statut ENUM('brouillon','soumis','piece_manquante','valide_50',
              'rejete','valide_100','expire','annule')
                              NOT NULL DEFAULT 'brouillon',
  progression                 TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- 'dossier_en_cours' est CONSERVÉ dans l'ENUM mais n'est plus jamais écrit
  -- (arbitrage du 31/08/2026, §5.2) : rétrécir l'ENUM transformerait en erreur
  -- silencieuse toute ligne qu'un import ou une restauration ramènerait.
  statut_bourse ENUM('non_boursier','boursier','dossier_en_cours') NULL,
  numero_attestation_anbg     VARCHAR(50)  NULL,
  date_depot_anbg             DATE         NULL,
  bourse_declaree_le          DATETIME     NULL,
  bourse_declaree_par ENUM('etudiant','etablissement','admin') NULL,

  soumis_le                   DATETIME     NULL,
  valide_50_le                DATETIME     NULL,
  valide_50_par               BIGINT UNSIGNED NULL,
  valide_100_le               DATETIME     NULL,
  valide_100_par              BIGINT UNSIGNED NULL,
  expire_le                   DATETIME     NULL,
  motif_rejet                 TEXT         NULL,

  montant_scolarite_annuelle  DECIMAL(12,2) NULL,
  montant_encaisse_initial    DECIMAL(12,2) NULL,
  mode_paiement ENUM('especes','mobile_money','virement','cheque','autre') NULL,
  reference_paiement          VARCHAR(80)  NULL,
  date_paiement               DATE         NULL,
  taux_commission_applique    DECIMAL(5,2) NULL,

  cree_le                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  maj_le                      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_utilisateur (utilisateur_id),
  INDEX idx_etablissement_statut (etablissement_id, statut),
  INDEX idx_statut_expire (statut, expire_le),
  INDEX idx_annee (annee_academique),
  FOREIGN KEY (utilisateur_id)   REFERENCES utilisateurs(id),
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id),
  FOREIGN KEY (filiere_id)       REFERENCES filieres(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.3 Pièces justificatives

```sql
CREATE TABLE documents_dossier (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id       BIGINT UNSIGNED NOT NULL,
  type_document    VARCHAR(50)  NOT NULL,
  nom_original     VARCHAR(255) NOT NULL,
  chemin_b2        VARCHAR(500) NOT NULL,
  mime_type        VARCHAR(100) NOT NULL,
  taille_octets    INT UNSIGNED NOT NULL,
  hash_sha256      CHAR(64)     NOT NULL,
  statut ENUM('en_attente','accepte','refuse') NOT NULL DEFAULT 'en_attente',
  motif_refus      TEXT         NULL,
  televerse_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verifie_le       DATETIME     NULL,
  verifie_par      BIGINT UNSIGNED NULL,
  INDEX idx_dossier (dossier_id),
  INDEX idx_hash (hash_sha256),
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.4 Jetons de vérification et scans

```sql
CREATE TABLE jetons_verification (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id   BIGINT UNSIGNED NOT NULL,
  token        CHAR(48) NOT NULL UNIQUE,
  code_court   CHAR(8)  NOT NULL UNIQUE,
  actif        TINYINT(1) NOT NULL DEFAULT 1,
  cree_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoque_le   DATETIME NULL,
  revoque_par  BIGINT UNSIGNED NULL,
  INDEX idx_dossier (dossier_id),
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scans_qr (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jeton_id          BIGINT UNSIGNED NULL,
  dossier_id        BIGINT UNSIGNED NULL,
  token_tente       VARCHAR(64)  NULL,
  scanne_par        BIGINT UNSIGNED NULL,
  etablissement_id  BIGINT UNSIGNED NULL,
  resultat ENUM('valide','non_valide_50','etablissement_incorrect',
                'jeton_revoque','introuvable','deja_inscrit') NOT NULL,
  adresse_ip        VARCHAR(45)  NULL,
  user_agent        VARCHAR(255) NULL,
  scanne_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dossier (dossier_id),
  INDEX idx_date (scanne_le),
  INDEX idx_ip_date (adresse_ip, scanne_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.5 Documents générés

```sql
CREATE TABLE documents_generes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id    BIGINT UNSIGNED NOT NULL,
  type ENUM('recepisse','attestation_inscription') NOT NULL,
  version       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  chemin_b2     VARCHAR(500) NOT NULL,
  hash_sha256   CHAR(64)     NOT NULL,
  genere_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nb_telechargements INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_dossier_type (dossier_id, type),
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.6 Commissions

```sql
CREATE TABLE commissions (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id          BIGINT UNSIGNED NOT NULL UNIQUE,
  etablissement_id    BIGINT UNSIGNED NOT NULL,
  base_calcul         DECIMAL(12,2) NOT NULL,
  taux                DECIMAL(5,2)  NOT NULL,
  montant             DECIMAL(12,2) NOT NULL,
  statut ENUM('a_facturer','facturee','reglee','annulee')
                      NOT NULL DEFAULT 'a_facturer',
  periode_facturation CHAR(7) NOT NULL,
  genere_le           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  regle_le            DATETIME NULL,
  reference_reglement VARCHAR(80) NULL,
  INDEX idx_etablissement_statut (etablissement_id, statut),
  INDEX idx_periode (periode_facturation),
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.7 Historiques et journal

```sql
CREATE TABLE historique_statut_dossier (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id     BIGINT UNSIGNED NOT NULL,
  ancien_statut  VARCHAR(20) NULL,
  nouveau_statut VARCHAR(20) NOT NULL,
  acteur_id      BIGINT UNSIGNED NULL,
  acteur_role    VARCHAR(30) NULL,
  commentaire    TEXT NULL,
  cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE historique_statut_bourse (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dossier_id        BIGINT UNSIGNED NOT NULL,
  ancien_statut     VARCHAR(20) NULL,
  nouveau_statut    VARCHAR(20) NOT NULL,
  justificatif_url  VARCHAR(500) NULL,
  modifie_par       BIGINT UNSIGNED NULL,
  role_modificateur ENUM('etudiant','etablissement','admin') NOT NULL,
  commentaire       TEXT NULL,
  modifie_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE journal_activite (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id   BIGINT UNSIGNED NULL,
  acteur_id        BIGINT UNSIGNED NULL,
  acteur_role      VARCHAR(30) NULL,
  type_evenement   VARCHAR(60) NOT NULL,
  objet_type       VARCHAR(40) NULL,
  objet_id         BIGINT UNSIGNED NULL,
  etablissement_id BIGINT UNSIGNED NULL,
  donnees          JSON NULL,
  adresse_ip       VARCHAR(45) NULL,
  cree_le          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_utilisateur_date (utilisateur_id, cree_le),
  INDEX idx_type_date (type_evenement, cree_le),
  INDEX idx_objet (objet_type, objet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 12.8 Notifications et paramètres

```sql
CREATE TABLE notifications (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id BIGINT UNSIGNED NOT NULL,
  type           VARCHAR(50)  NOT NULL,
  titre          VARCHAR(150) NOT NULL,
  corps          TEXT         NOT NULL,
  lien           VARCHAR(500) NULL,
  lu_le          DATETIME     NULL,
  cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_utilisateur_lu (utilisateur_id, lu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parametres (
  cle        VARCHAR(80) PRIMARY KEY,
  valeur     TEXT NOT NULL,
  type       ENUM('texte','entier','decimal','booleen','json') NOT NULL,
  maj_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
  maj_par    BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametres (cle, valeur, type) VALUES
  ('taux_commission_defaut', '10.00', 'decimal'),
  ('delai_expiration_defaut_jours', '30', 'entier'),
  ('relance_bourse_en_cours_jours', '90', 'entier');
```

---

## 13. Routes de l'application

### 13.1 Parcours étudiant

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/inscription/{etablissement}/{filiere}` | Démarre ou reprend le brouillon |
| POST | `/inscription/{dossier}/etape-1` | Enregistre les informations et la situation de bourse |
| GET | `/inscription/{dossier}/etape-2` | Affiche la liste dynamique des pièces |
| POST | `/inscription/{dossier}/piece` | Téléverse une pièce |
| DELETE | `/inscription/{dossier}/piece/{id}` | Supprime une pièce non validée |
| POST | `/inscription/{dossier}/soumettre` | Soumission finale, transaction complète |
| GET | `/inscription/{dossier}/confirmation` | Étape 3 |
| GET | `/espace/dossiers` | Liste des dossiers |
| GET | `/espace/dossiers/{dossier}` | Détail et progression |
| GET | `/espace/dossiers/{dossier}/recepisse` | Téléchargement via URL signée |
| GET | `/espace/dossiers/{dossier}/attestation` | Téléchargement, si 100 % |
| POST | `/espace/dossiers/{dossier}/bourse` | Mise à jour de la situation de bourse |
| GET | `/espace/notifications` | Liste |

### 13.2 Vérification

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/v/{token}` | Page de vérification. Authentification requise |
| POST | `/verification/code-court` | Vérification par saisie manuelle |

### 13.3 Back-office établissement

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/etablissement/dossiers` | Liste filtrable |
| GET | `/etablissement/dossiers/{dossier}` | Fiche complète |
| POST | `/etablissement/dossiers/{dossier}/valider-50` | Validation sur pièces |
| POST | `/etablissement/dossiers/{dossier}/demander-piece` | Demande de complément |
| POST | `/etablissement/dossiers/{dossier}/rejeter` | Rejet motivé |
| POST | `/etablissement/dossiers/{dossier}/valider-100` | Validation finale avec données financières |
| POST | `/etablissement/dossiers/{dossier}/prolonger` | Prolongation du délai |
| GET | `/etablissement/scanner` | Écran de scan |

### 13.4 Super administration

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/admin/utilisateurs/{id}` | Fiche enrichie avec la frise |
| GET | `/admin/dossiers` | Vue transversale tous établissements |
| GET | `/admin/commissions` | Suivi des commissions |
| POST | `/admin/etablissements/{id}/taux` | Modification du taux, journalisée |
| POST | `/admin/dossiers/{dossier}/revoquer-jeton` | Révocation et régénération |
| GET | `/admin/journal` | Consultation du journal d'activité |
| GET | `/admin/tableaux-de-bord` | Entonnoir et indicateurs |

---

## 14. Tâches planifiées

| Tâche | Fréquence | Action |
| --- | --- | --- |
| Expiration des dossiers | Quotidienne | Passe à `expire` les dossiers `valide_50` dont `expire_le` est dépassé. Notifie l'étudiant et l'établissement |
| Rattrapage des PDF | Toutes les 15 minutes | Régénère les récépissés et attestations manquants |
| Relance dossiers dormants | Hebdomadaire | Signale aux établissements les dossiers `soumis` depuis plus de 7 jours |
| Alerte taux de boursiers | Hebdomadaire | Compare le taux par établissement à la moyenne de la plateforme |

---

## 15. Sécurité

### 15.1 Contrôle d'accès

Quatre rôles : `super_admin`, `admin_etablissement`, `agent_etablissement`, `etudiant`.

**Règle de cloisonnement, à appliquer sans exception :** tout accès à un dossier vérifie que l'acteur en a le droit. Un agent ne voit que les dossiers de son établissement. Un étudiant ne voit que les siens. Cette vérification se fait côté serveur, à chaque requête, y compris sur les routes de téléchargement.

Ne jamais se reposer sur le fait qu'un identifiant est difficile à deviner. Les identifiants numériques séquentiels sont énumérables.

### 15.2 Fichiers

- Bucket B2 privé, aucun accès public.
- URL signées d'une durée de vie de 5 minutes maximum.
- Vérification du MIME réel côté serveur.
- Extensions autorisées en liste blanche, jamais en liste noire.
- Aucun fichier téléversé dans un répertoire servi par nginx.

### 15.3 Journalisation

Chaque téléchargement de document, chaque scan, chaque changement de statut, chaque modification de taux est journalisé avec l'acteur, la date et l'adresse IP.

### 15.4 Divers

- Jeton CSRF sur chaque formulaire.
- Requêtes préparées systématiquement.
- Échappement en sortie sur toute donnée saisie par un utilisateur, y compris les motifs de rejet et les commentaires.
- Limitation de débit sur la vérification de jeton et sur les tentatives de connexion.

---

## 16. Découpage en lots de développement

### Lot 0 — Fondations

Migrations de base de données, table `parametres`, rôles et droits, service de journalisation, service d'envoi de notifications. Aucun changement visible pour l'utilisateur.

*Critères d'acceptation :* toutes les tables créées, le service de journal écrit correctement, les droits MySQL du compte applicatif sont restreints à `INSERT` et `SELECT` sur `journal_activite`.

### Lot 1 — Formulaire et situation de bourse

Le bloc bourse à l'étape 1, la liste dynamique des pièces à l'étape 2, la nouvelle étape 3.

*Critères d'acceptation :* les trois options fonctionnent, l'attestation est obligatoire pour un boursier confirmé, la validation serveur rejette une soumission falsifiée côté client, l'historique de bourse est écrit.

### Lot 2 — Récépissé et vérification

Génération de la référence, du jeton, du code court, du PDF. Page de vérification et écran de scan.

*Critères d'acceptation :* le récépissé est téléchargeable dès la soumission, le QR scanné affiche le statut réel en direct, un agent d'un autre établissement est refusé, tous les scans sont journalisés, la limitation de débit fonctionne.

### Lot 3 — Back-office établissement

Liste, fiche dossier, validation à 50 %, demande de pièce, rejet, validation à 100 % avec données financières.

*Critères d'acceptation :* la machine à états refuse toute transition non autorisée, la validation à 100 % est impossible sans les champs financiers, chaque décision génère une notification.

### Lot 4 — Attestation et commissions

Génération de l'attestation, création de la commission avec gel du taux, écran de suivi.

*Critères d'acceptation :* modifier le taux d'un établissement ne change aucune commission existante, l'attestation apparaît dans les deux espaces, la commission est générée quel que soit le statut de bourse.

### Lot 5 — Traçabilité super admin

Fiche utilisateur enrichie, frise chronologique, vue transversale des dossiers, tableaux de bord.

*Critères d'acceptation :* la fiche d'un utilisateur montre toutes ses préinscriptions et tous ses dossiers avec leur progression et leurs dates, la frise est complète et ordonnée.

### Lot 6 — Automatisation

Tâches planifiées, relances, alertes.

*Critères d'acceptation :* un dossier dépassant son délai bascule automatiquement, les relances partent, l'alerte sur les taux de boursiers anormaux se déclenche.

---

## 17. Points restant à trancher

Ces questions ne bloquent pas le démarrage mais devront être arbitrées avant la mise en production.

**Inscriptions multiples.** Un étudiant peut-il déposer des dossiers dans plusieurs établissements simultanément ? Recommandation : oui pour les dépôts, mais une alerte au super administrateur si un même étudiant atteint 100 % dans deux établissements pour la même année. C'est un indicateur de traçabilité précieux.

**Paiement échelonné.** La scolarité se paie souvent mensuellement. Le passage à 100 % doit-il exiger le paiement intégral, ou un premier versement suffit-il ? Recommandation : un premier versement suffit, la commission étant calculée sur la scolarité annuelle contractuelle. Prévoir une table `versements` en phase 2 si le suivi des encaissements successifs devient nécessaire.

**Délai d'expiration.** 30 jours est une valeur de départ. À calibrer avec les établissements après la première rentrée.

**MoneyFusion.** Le paiement en ligne ne disparaît pas du code, il est désactivé par le champ `mode_paiement_inscription`. Un établissement pourra le réactiver plus tard sans redéveloppement.

**Montant de la scolarité.** Faut-il l'imposer depuis la fiche filière ou laisser l'établissement le saisir librement ? Recommandation : préremplir depuis la filière et permettre la modification, tout écart supérieur à 20 % étant signalé au super administrateur.

---

## 18. Ce qu'il ne faut surtout pas faire

Ces erreurs sont faciles à commettre et coûteuses à corriger après coup.

- **Encoder les données du dossier dans le QR code.** Le document deviendrait périmé dès le premier changement de statut.
- **Recalculer les commissions à partir du taux courant de l'établissement.** Toute modification de taux réécrirait l'histoire comptable.
- **Autoriser l'établissement à déclarer seul la situation de bourse.** C'est précisément le conflit d'intérêts que la déclaration par l'étudiant élimine.
- **Permettre la validation à 100 % sans les données financières.** La plateforme perdrait définitivement la trace du montant, puisque l'argent ne transite plus par elle.
- **Écrire directement dans la colonne `statut`.** Toute transition doit passer par la machine à états, sinon les historiques deviennent incomplets et les litiges indéfendables.
- **Servir les fichiers téléversés depuis un répertoire public.** Les pièces d'identité et actes de naissance sont des données personnelles sensibles.
- **Supprimer ou modifier des lignes du journal d'activité.** Un journal modifiable ne prouve rien.
