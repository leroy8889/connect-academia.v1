# Mise en production — Refonte du parcours d'inscription

Spécification : `Connect-Academia_Spec_Inscription_Boursiers.md`
Suite de tests : `sh tests/orientation/lancer.sh` (311 assertions)

---

## 1. Avant de commencer

```bash
# Sauvegarde de la base — obligatoire, la migration touche dossiers_inscription
mysqldump --single-transaction --routines connect_academia > backup_avant_refonte.sql
```

La migration **modifie les statuts des dossiers existants** (section 13 du
fichier SQL). Sans sauvegarde, un retour arrière est impossible.

---

## 2. Migration de base de données

```bash
mysql connect_academia < Database/migration_inscription_boursiers.sql
```

Elle est **idempotente** : la rejouer ne produit ni erreur ni doublon.

### Ce qu'elle change sur les données existantes

| Ancien statut | Nouveau statut | Raison |
| --- | --- | --- |
| `paye` | `soumis` | Frais réglés en ligne, pièces pas encore analysées |
| `accepte` | `valide_50` | Dossier recevable, présentiel à venir |
| `inscrit` | `valide_100` | Inscription définitive |
| `refuse` | `rejete` | Vocabulaire de la spec |

Les anciennes valeurs restent dans l'ENUM MySQL : aucune lecture existante ne
casse. Plus aucune écriture ne les produit.

### Tables créées

`jetons_verification`, `scans_qr`, `journal_activite`,
`historique_statut_dossier`, `historique_statut_bourse`, `sequences_dossier`.

Les tables de la spec §12 qui existaient déjà sous un autre nom ont été
**étendues**, pas dupliquées — voir l'en-tête du fichier SQL.

---

## 3. Droits MySQL du journal (spec §11.3)

Le journal d'activité est en **ajout seul**. Aucune route applicative ne le
modifie, mais la garantie doit aussi être portée par la base :

```sql
REVOKE UPDATE, DELETE ON connect_academia.journal_activite FROM 'app'@'%';
GRANT  INSERT, SELECT ON connect_academia.journal_activite TO   'app'@'%';
FLUSH PRIVILEGES;
```

Une purge éventuelle se fait par un compte d'administration séparé, jamais
depuis l'application.

---

## 4. Codes courts d'établissement

Les références sont de la forme `CA-2026-ESM-000147`. Le `ESM` vient de
`etablissements.code_court`, vide sur les établissements existants.

Il se génère **tout seul** à la première référence émise, dérivé du nom. Pour
le choisir plutôt que le subir, le poser à la main avant la première
soumission :

```sql
UPDATE etablissements SET code_court = 'AFRAM' WHERE id = 3;
```

Une fois qu'une référence a été émise avec un code, **ne plus le changer** :
les documents déjà remis le contiennent.

---

## 5. Mode de règlement

Par défaut, `etablissements.mode_paiement_inscription = 'presentiel'` : le
wizard s'arrête sur l'écran de confirmation, la scolarité se règle au guichet.

Pour un établissement qui continue d'encaisser en ligne :

```sql
UPDATE etablissements SET mode_paiement_inscription = 'en_ligne' WHERE id = 7;
```

MoneyFusion n'a pas été retiré du code : ce seul champ le rallume.

---

## 5 bis. Passe « corrections guichet » (août 2026)

Six ajouts livrés après la refonte. **Aucune migration SQL** : ils n'utilisent
que des colonnes déjà en place.

| Ajout | Point d'entrée | À vérifier après déploiement |
|---|---|---|
| E-mail du dossier verrouillé sur le compte | `CandidatureController::erreurEmailCompte()` | Le champ e-mail est en creux et non modifiable sur `/orientation/{slug}/inscription` quand on est connecté |
| Retour arrière sur une décision | `POST /gestionnaire/candidatures/{id}/annuler-decision` | Le repli « Annuler … » apparaît dans la zone décision d'un dossier à 50 %, 100 %, rejeté ou en pièce manquante |
| Délai d'analyse de 3 jours | tâche `retard-analyse` (voir §6) | `/admin/tableaux-de-bord`, section « Délai d'analyse dépassé » |
| Reçu de scolarité en PDF | `GET /eleve/orientation/dossier/{hash}/recu` et `GET /gestionnaire/candidatures/{id}/recu` | Le bouton « Reçu de scolarité » apparaît sur un dossier à 100 % avec un montant saisi |
| Refonte visuelle étapes 1 et 3 | `public/assets/orientation/candidature.css` | Purger le cache d'assets : la feuille est versionnée par `filemtime`, un CDN intermédiaire peut la retenir |
| Refonte de l'écran de scan | `public/assets/orientation/scanner.css` | Idem |

Le retour arrière sur une inscription à 100 % **annule la commission et
supprime l'attestation** : c'est voulu, une attestation qui certifie une
inscription annulée est un faux en circulation. Il est refusé si la commission
est déjà réglée.

---

## 5 ter. Passe « cohérence du parcours » (août 2026)

### Migration à rejouer

```bash
mysql -u <user> -p <base> < Database/migration_frais_inscription_dossier.sql
```

Ajoute `dossiers_inscription.montant_frais_inscription`. Idempotente. Un filet
applicatif (`Core\DossierFinanceSchema`) détecte l'absence de la colonne et
saute son écriture au lieu de faire échouer la validation à 100 % — mais les
frais d'inscription ne sont alors pas enregistrés. **Rejouer la migration
avant de déployer le code.**

### Ce qui change

| Ajout | Où | À vérifier après déploiement |
|---|---|---|
| Une inscription en cours est classée dans « Inscriptions » | `Dossier::estInscription()` / `Dossier::SQL_EST_INSCRIPTION` | Un dossier d'inscription soumis mais non validé apparaît dans « Mes inscriptions », plus dans « Mes préinscriptions » |
| Les reçus apparaissent dans l'onglet « Reçus » | `EspaceEleveOrientationController::recus()` | `/eleve/orientation/recus` liste les règlements de guichet en plus des paiements en ligne |
| Frais d'inscription séparés de la scolarité | formulaire de validation à 100 % | Le champ « Frais d'inscription encaissés » est prérempli au tarif public de l'établissement |

### Le classement inscription / préinscription

Une inscription est un **processus**, pas un état final. La règle vit désormais
à un seul endroit, dans `Models\Orientation\Dossier`, sous deux formes qui
disent la même chose : une constante SQL pour les requêtes, une méthode PHP
pour le code qui tient déjà la ligne en mémoire. Elle était auparavant
réécrite à la main dans sept endroits, et le marqueur décisif manquait partout.

Un dossier relève du parcours d'inscription s'il remplit **l'une** de ces
conditions :

1. une ligne dans `inscriptions` (validation à 100 %, saisie au guichet, ancien
   parcours de paiement en ligne) ;
2. une `reference` `CA-AAAA-XXX-NNNNNN`, réservée à la soumission du dossier ;
3. le statut `brouillon` (étape 1 enregistrée).

Une préinscription n'obtient **jamais** de référence : elle ne porte qu'un
numéro de récépissé, dans la table `recepisses`. Attention au piège de schéma :
la table `preinscriptions` porte une ligne pour CHAQUE dossier dès la
réservation du récépissé, elle ne discrimine donc rien.

**Effet sur les compteurs** : les dossiers d'inscription non encore validés à
100 % basculent de la colonne « préinscriptions » vers « inscriptions », dans
l'espace élève comme dans `/admin/utilisateurs/{id}` et l'entonnoir de
conversion. C'est la correction d'un classement faux, mais les chiffres bougent.

### Frais d'inscription et scolarité : deux sommes distinctes

Le guichet encaisse deux choses de nature différente le même jour :

- les **frais d'inscription**, dus une fois, non remboursables, acquis à
  l'établissement — colonne `montant_frais_inscription` ;
- un **versement sur la scolarité**, imputé sur le montant annuel — colonne
  `montant_encaisse_initial`, inchangée.

Les mêler dans une seule colonne minorait le « reste à régler » du candidat sur
son reçu et lui faisait perdre la trace écrite de ses frais.

**La commission reste calculée sur la SEULE scolarité annuelle.** Les frais
d'inscription n'entrent pas dans sa base de calcul, et les commissions déjà
émises ne sont pas touchées.

**Aucune reprise de données** : la colonne reste à NULL sur les dossiers
existants. Impossible de deviner rétroactivement quelle part d'un montant déjà
saisi correspondait aux frais ; inventer une répartition fausserait des reçus
déjà remis. Le reçu masque alors la ligne au lieu d'afficher un zéro trompeur.

### Le reçu tient sur une page A4

Le gabarit a un budget de hauteur fixe. Les chaînes venant de la saisie d'un
établissement (adresse, intitulé de formation) sont donc **bornées** avec une
ellipse, et les noms longs descendent d'un cran de corps plutôt que d'être
coupés — un nom tronqué sur un justificatif est inacceptable. Le test
`lot7.php` vérifie la contrainte avec toutes les entrées au maximum. Toute
addition de contenu au gabarit doit être compensée ailleurs.

---

## 5 quater. Cache et consommation Backblaze

### Le répertoire `storage/cache` doit être inscriptible

```bash
mkdir -p storage/cache
chown administrator:www-data storage/cache
chmod 2775 storage/cache
```

`Core\Cache` utilise Redis en premier et **le disque en repli**. Le chemin
Redis est inchangé : le repli ne s'active que si Redis ne répond pas. Si le
répertoire n'est pas inscriptible, le comportement redevient celui d'avant
(aucun cache) et une ligne `[CacheFichier]` apparaît dans les logs.

Le répertoire est ignoré par git : tout y est reconstructible, le vider ne perd
aucune donnée.

### Pourquoi ce repli existe

`Core\Cache` était exclusivement Redis. Sans Redis, `remember()` rappelait son
callback à chaque appel **sans jamais rien mémoriser** — pas un cache dégradé,
une absence totale de cache, invisible depuis les appelants.

Le prix se payait sur les médias : `LogoDataUri`, `SignatureDataUri` et la photo
d'identité du récépissé retéléchargeaient leur fichier depuis Backblaze à chaque
rendu de document et, pour le logo, **à chaque dossier affiché dans l'espace
élève** (`acct_etab_logo()`). Une série de générations a épuisé le plafond de
transactions Classe B du compte B2, qui a alors refusé TOUTES les lectures :

```xml
<Code>AccessDenied</Code>
<Message>Cannot download file, download bandwidth or transaction (Class B) cap
         exceeded. See the Caps &amp; Alerts page to increase your cap.</Message>
```

Symptôme : plus aucune image ni document sur la plateforme, alors que les clés,
l'horloge et le code sont sains.

### Si le plafond est de nouveau atteint

1. Console Backblaze → **Caps & Alerts** → relever le plafond de téléchargement
   et de transactions Classe B. Le plafond journalier se réinitialise seul à
   minuit UTC.
2. Purger les échecs mémorisés :

```bash
php bin/cache-vider.php medias
```

Un fichier illisible n'est mémorisé que **60 secondes** (contre 6 heures pour un
succès), précisément pour qu'un rétablissement se voie tout de suite. La purge
ne fait qu'accélérer les choses.

`php bin/cache-vider.php --etat` affiche l'état sans rien effacer.

### Poser une alerte

Configurer une alerte de cap dans la console Backblaze : la panne est totale et
silencieuse côté application (les gabarits basculent proprement sur l'initiale
de l'établissement), donc rien ne la signale sans alerte externe.

---

## 6. Tâches planifiées (spec §14)

Vérifier d'abord ce qui serait fait, sans rien modifier :

```bash
php bin/taches-orientation.php quotidien    --dry-run
php bin/taches-orientation.php hebdomadaire --dry-run
```

Puis installer la crontab :

```cron
# Rattrapage des documents — toutes les 15 minutes
*/15 * * * *  cd /var/www/connectacademia && php bin/taches-orientation.php rattrapage --quiet

# Expiration, relance des bourses ANBG, délai d'analyse dépassé — 6 h,
# avant l'ouverture des guichets
0 6 * * *     cd /var/www/connectacademia && php bin/taches-orientation.php quotidien --quiet

# Dossiers dormants + alertes — lundi 7 h
0 7 * * 1     cd /var/www/connectacademia && php bin/taches-orientation.php hebdomadaire --quiet
```

`--quiet` n'affiche que les erreurs : sans lui, cron enverrait un e-mail
quatre-vingt-seize fois par jour et personne ne le lirait.

Le script sort en code 1 s'il y a eu une erreur : un superviseur peut alerter
dessus sans analyser la sortie.

### Délai d'analyse : alerte au super administrateur

La tâche `retard-analyse`, incluse dans le groupe `quotidien`, signale les
dossiers qu'un établissement laisse sans décision au-delà du délai accordé.
Elle écrit une entrée `alerte_retard_analyse` dans le journal et envoie une
synthèse par e-mail aux comptes `admins` de rôle `super_admin` actifs. Le
détail dossier par dossier s'affiche dans `/admin/tableaux-de-bord`, section
« Délai d'analyse dépassé ».

Le seuil est de **3 jours** par défaut, surchargeable sans redéploiement :

```sql
INSERT INTO settings (setting_key, setting_value)
VALUES ('delai_analyse_dossier_jours', '3')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
```

Le compteur repart à chaque retour du dossier en état `soumis` : un dossier
renvoyé après une demande de pièce redonne un délai plein à l'établissement.
Chaque dossier n'est signalé qu'une fois par quinzaine, sans quoi un dossier
oublié deux mois produirait soixante entrées identiques.

**Avant le premier passage en production**, vérifier le volume :

```bash
php bin/taches-orientation.php retard-analyse --dry-run
```

Sur une base reprise, l'antériorité peut faire remonter plusieurs centaines de
dossiers d'un coup. Le lot est borné à 200 par passage, mais la première
synthèse restera volumineuse : la lancer un jour où quelqu'un peut la lire.

### Coupe-circuit e-mail hors production

`MAIL_DISABLED=true` dans le `.env` journalise les envois sans rien émettre.
À poser sur tout environnement dont la base est une copie de production : les
tâches planifiées écrivent aux adresses réelles contenues dans `admins`, et la
suite de tests fait tourner ces mêmes tâches.

### Premier passage : attention à l'expiration

Les dossiers repris en `valide_50` par la migration n'ont pas de `expire_le`,
donc rien n'expire au premier passage. Mais si la date est posée plus tard à la
main, vérifier le volume avec `--dry-run` avant : une expiration de masse mal
calibrée n'est pas rattrapable en un geste.

---

## 7. Worker de file d'attente

Le récépissé est généré hors requête HTTP, via `Core\Queue`. Le worker doit
tourner :

```bash
php worker.php          # boucle, sous systemd/supervisor
php worker.php once     # vide la file puis s'arrête
```

S'il ne tourne pas, `Queue::push` bascule en exécution immédiate, et la tâche
de rattrapage repasse de toute façon toutes les 15 minutes. Rien n'est perdu,
mais le candidat attend plus longtemps sur l'écran de confirmation.

---

## 8. Stockage B2

Les pièces justificatives partent dans un bucket **privé**, sous
`dossiers/{annee}/{etablissement}/{reference}/`. Vérifier que `B2_BUCKET`
n'a aucune permission de lecture publique : ces fichiers sont des actes de
naissance et des pièces d'identité.

Aucun fichier téléversé n'est écrit dans un répertoire servi par nginx.

---

## 9. Vérification après déploiement

```bash
# La suite doit être verte sur l'environnement de recette, JAMAIS en production
# (elle écrit et supprime des données)
sh tests/orientation/lancer.sh
```

Parcours à vérifier à la main :

1. Déposer un dossier en se déclarant **boursier** → l'attestation ANBG doit
   être exigée à l'étape 2.
2. Écran de confirmation → la référence s'affiche, avec la mention
   « vous n'êtes pas encore inscrit ».
3. Espace étudiant → le récépissé se télécharge, une page, QR lisible.
4. Scanner le QR depuis un téléphone → page de vérification, bandeau orange
   « en cours d'analyse ».
5. Back-office → valider à 50 % → rescanner : bandeau **vert**, bouton
   « Valider à 100 % ».
6. Valider à 100 % sans montant → refusé. Avec montant → commission créée,
   attestation délivrée.
7. `/admin/commissions` → la commission apparaît, taux gelé.

---

## 10. Retour arrière

La migration n'est pas réversible automatiquement (elle réécrit des statuts).
En cas de problème :

```bash
mysql connect_academia < backup_avant_refonte.sql
```

Le code, lui, se retire par un `git revert` : les anciennes valeurs de statut
sont toujours acceptées par l'ENUM, et l'ancien parcours reste fonctionnel.
