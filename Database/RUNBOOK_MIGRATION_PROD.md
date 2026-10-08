# Runbook — mise à jour de la base de PRODUCTION

Généré le 2026-09-01. Cible : passer la prod du dernier état déployé
à l'état actuel du local (branche `sauvegarde-local-2026-08-24`).

**11 migrations**, toutes idempotentes (rejouables sans erreur), plus une
douzième purge destructive à lancer séparément.

## Le plus simple : un seul fichier

`Database/MISE_A_JOUR_PROD_2026-09-01.sql` contient les 11 migrations
concaténées dans le bon ordre. C'est le fichier à importer dans phpMyAdmin.
Le détail étape par étape ci-dessous n'est utile que pour rejouer une
migration isolée ou comprendre ce qui est posé.

### phpMyAdmin

1. Onglet **Exporter** sur la base de production → méthode « personnalisée »,
   compression gzip → télécharger. C'est la sauvegarde, elle est obligatoire.
2. Sélectionner la base dans le menu de gauche → onglet **Importer**.
3. Choisir `MISE_A_JOUR_PROD_2026-09-01.sql`, format SQL, délimiteur `;`.
4. **Exécuter.** Aucun message d'erreur attendu.
5. Onglet **Importer** à nouveau avec `verification_schema_prod.sql`
   (lecture seule) : aucune ligne ne doit afficher `### MANQUANT ###`.

Le fichier fait 96 Ko — sous la limite d'upload par défaut de phpMyAdmin.
Si l'import expire quand même, passer par SSH :

```bash
mysql -u <user> -p <base> < Database/MISE_A_JOUR_PROD_2026-09-01.sql
```

**Privilège requis : `CREATE ROUTINE`.** Les gardes d'idempotence sont des
procédures stockées, créées puis supprimées à la fin de chaque étape.

---

## 0. Avant tout — sauvegarde

```bash
ssh administrator@85.190.96.109
cd /var/www/connectacademia    # adapter si le chemin diffère
mysqldump --single-transaction --routines --triggers \
  -u <user> -p <base> | gzip > ~/backup-avant-migration-$(date +%F-%H%M).sql.gz
ls -lh ~/backup-avant-migration-*.sql.gz
```

Ne rien lancer tant que ce fichier n'existe pas et n'est pas non vide.

## 1. État actuel de la prod

```bash
mysql -u <user> -p <base> --table < Database/verification_schema_prod.sql
```

Toute ligne `### MANQUANT ###` désigne une migration non rejouée.
Si des migrations ANCIENNES ressortent manquantes (colonne `migration`
autre que celles listées §2), les rejouer d'abord — elles sont toutes
dans `Database/` et toutes idempotentes.

## 2. Les migrations à jouer, DANS CET ORDRE

L'ordre compte : chaque étape s'appuie sur les colonnes créées par la précédente.

| # | Fichier | Ce qu'elle fait |
|---|---------|-----------------|
| 1 | `migration_colonnes_derive.sql` | Rattrapage : 9 colonnes présentes en dev, utilisées par le code, jamais migrées (`serie_bac`, `annee_bac`, `cachet_cle`, tarifs de filière, contenu éditorial des formations) |
| 2 | `migration_inscription_boursiers.sql` | Socle. 6 tables + ~40 colonnes + 11 index + 4 settings + reprise des données |
| 3 | `migration_tarifs_formation.sql` | Table `tarifs_formation`, 4 frais d'inscription par cycle/bourse, base de commission |
| 4 | `migration_tranches_abandon.sql` | Statuts commission `partiellement_reglee`/`abandonnee`, traçabilité des tranches, abandon d'année, chaînage des pièces |
| 5 | `migration_commission_boursier_tranches.sql` | `etablissements.base_commission_boursier`, refonte des échéanciers 40/30/30 → 60/40 (seulement ceux intacts) |
| 6 | `migration_composition_dossier.sql` | `etablissements.composition_dossier` (JSON) |
| 7 | `migration_frais_inscription_dossier.sql` | `dossiers_inscription.montant_frais_inscription` |
| 8 | `migration_accepte_boursiers.sql` | `etablissements.accepte_boursiers` |
| 9 | `migration_suppression_dossier_en_cours.sql` | Retrait du statut de bourse « dossier ANBG en cours » (2 statuts restants) |
| 10 | `migration_unicite_inscription.sql` | Backfill `annee_academique` + 2 index d'unicité d'inscription |
| 11 | `migration_sequences_dossier_backfill.sql` | Alimente `sequences_dossier` depuis les références existantes |
| 12 | `migration_preinscriptions_nettoyage.sql` | **DESTRUCTIVE** — purge les lignes `preinscriptions` polluées par `reserveRecepisse` |

### Commande

```bash
cd /var/www/connectacademia
for f in \
  migration_colonnes_derive \
  migration_inscription_boursiers \
  migration_tarifs_formation \
  migration_tranches_abandon \
  migration_commission_boursier_tranches \
  migration_composition_dossier \
  migration_frais_inscription_dossier \
  migration_accepte_boursiers \
  migration_suppression_dossier_en_cours \
  migration_unicite_inscription \
  migration_sequences_dossier_backfill
do
  echo "=== $f"
  mysql -u <user> -p<mdp> <base> < "Database/$f.sql" || { echo "ECHEC sur $f"; break; }
done
```

L'étape 12 se lance à part, après vérification (voir §3).

## 3. Étape 12 — purge des préinscriptions (à valider AVANT exécution)

Le fichier commence par un `SELECT` de contrôle, puis le `DELETE`.
Lancer d'abord le SELECT seul :

```sql
SELECT p.id, p.dossier_id, d.reference, d.statut, d.date_creation
  FROM preinscriptions p
  JOIN dossiers_inscription d ON d.id = p.dossier_id
 WHERE (EXISTS (SELECT 1 FROM inscriptions i WHERE i.dossier_id = d.id)
        OR d.reference IS NOT NULL
        OR d.statut = 'brouillon');
```

Si les lignes retournées sont bien des faux positifs (préinscriptions créées
par la réservation de récépissé, pas de vraies préinscriptions), alors :

```bash
mysql -u <user> -p <base> < Database/migration_preinscriptions_nettoyage.sql
```

## 4. Après migration — vérifications

```bash
mysql -u <user> -p <base> --table < Database/verification_schema_prod.sql
```

Attendu : **zéro `### MANQUANT ###`**, et sur les contrôles de données :

- `dossiers sans annee_academique` → 0
- `dossiers statut_bourse = dossier_en_cours` → 0
- `sequences_dossier alimentees` → > 0 s'il existe déjà des références `CA-...`
- `preinscriptions polluees` → 0 (après l'étape 12)

ENUM attendus :

- `dossiers_inscription.statut` contient `brouillon,soumis,piece_manquante,valide_50,rejete,valide_100,expire,annule` (+ les héritées `paye,accepte,inscrit,refuse`, conservées)
- `commissions_scolarite.statut` contient `partiellement_reglee` et `abandonnee`
- `documents_orientation.eleve_id` doit être `IS_NULLABLE=YES`

## 5. Hors base — à ne pas oublier

- **Vider le cache** après migration : `php bin/cache-vider.php`
  (les fiches établissement sont cachées sous la clé `orientation:etab:v2`,
  préfixe Redis `ca:`).
- **Crontab** (nouveau script `bin/taches-orientation.php`) :
  ```cron
  */15 * * * *  cd /var/www/connectacademia && php bin/taches-orientation.php rattrapage --quiet
  0 6 * * *     cd /var/www/connectacademia && php bin/taches-orientation.php quotidien --quiet
  0 7 * * 1     cd /var/www/connectacademia && php bin/taches-orientation.php hebdomadaire --quiet
  ```
- **Aucune donnée à semer.** Ne JAMAIS pré-remplir `tarifs_formation` :
  une grille semée fige les champs de la fiche établissement. Les 4 colonnes
  `frais_inscription_*` naissent à 0 = inactives, la résolution retombe sur
  `prix_inscription` / `frais_scolarite*` tant que le super admin n'a rien saisi.
- **`etablissements.code_court`** naît NULL et se remplit à la volée
  (`Services\Orientation\CodeEtablissement`). Rien à faire.

## 6. Retour arrière

Aucune de ces migrations ne supprime de colonne ni de table.
Le seul retrait de données est l'étape 12 (`preinscriptions`) et le
`DELETE` du setting `relance_bourse_en_cours_jours` (étape 9).
En cas de problème, restaurer le dump du §0.
