#!/usr/bin/env bash
# ============================================================================
# Connect'Academia — application des migrations SQL en production
#
#   bash deploy/run-migrations.sh            # simulation : liste l'ordre, ne touche à rien
#   bash deploy/run-migrations.sh --go       # application réelle (sauvegarde automatique avant)
#
# POURQUOI UN ORDRE
# -----------------
# Les migrations ne sont pas numérotées : elles ont été écrites au fil de l'eau.
# L'ordre ci-dessous respecte les dépendances :
#   ① tables de base (orientation_v1 crée etablissements, dossiers_inscription…)
#   ② tables satellites qui référencent ①
#   ③ ALTER TABLE (colonnes ajoutées sur les tables de ① et ②)
#   ④ contraintes de clés étrangères en cascade — en dernier, toutes les tables
#     doivent exister et être peuplées de colonnes avant de poser les FK.
#
# IDEMPOTENCE
# -----------
# Les CREATE TABLE sont tous en IF NOT EXISTS : rejouables sans risque.
# Les ALTER TABLE ADD COLUMN, eux, échouent si la colonne existe déjà
# (« Duplicate column name »). C'est attendu et sans gravité : le script
# utilise `mysql --force` pour continuer malgré ces erreurs-là.
# Toute AUTRE erreur est réelle et doit être lue dans le récapitulatif final.
#
# CE QUE LE SCRIPT NE FAIT PAS
#   Aucune suppression de données. Aucun DROP TABLE. Aucun DELETE.
# ============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GO=0
[ "${1:-}" = "--go" ] && GO=1

# ── Identifiants lus dans .env (jamais en dur dans ce fichier) ───────────────
ENV_FILE="$ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "Fichier .env introuvable dans $ROOT" >&2
  exit 1
fi

envval() { grep -E "^\s*$1\s*=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"'"'"' \r'; }

DB_HOST="$(envval DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="$(envval DB_NAME)"
DB_USER="$(envval DB_USER)"
DB_PASS="$(envval DB_PASS)"

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
  echo "DB_NAME ou DB_USER absent de .env" >&2
  exit 1
fi

MYSQL=(mysql --host="$DB_HOST" --user="$DB_USER" --database="$DB_NAME")
[ -n "$DB_PASS" ] && MYSQL+=(--password="$DB_PASS")

# ── Ordre d'application ─────────────────────────────────────────────────────
MIGRATIONS=(
  # ① Socle : crée les 18 tables du module Orientation
  "Database/migration_orientation_v1.sql"
  "Database/migration_phase9.sql"

  # ② Tables satellites
  "Database/migration_attestations.sql"
  "Database/migration_avis_etablissements.sql"
  "Database/migration_sms_orientation.sql"
  "Database/migration_whatsapp_orientation.sql"
  "Database/migration_filieres_specialites.sql"
  "Database/migration_telephone_commissions.sql"
  "migrations/migration_security_alerts.sql"

  # ③ Colonnes ajoutées sur les tables existantes
  "Database/migration_frais_scolarite.sql"
  "Database/migration_frais_scolarite_master.sql"
  "Database/migration_frais_boursier_uniforme.sql"
  "Database/migration_genere_attestation.sql"
  "Database/migration_media_localisation.sql"
  "Database/migration_signature_attestation.sql"
  "Database/migration_taux_commission_etablissement.sql"
  "Database/migration_role_gestionnaire.sql"
  "Database/migration_statut_inscrit.sql"
  "Database/migration_comptes_visiteurs.sql"
  "Database/migration_admin_reset_token.sql"
  "Database/migration_password_resets_token_hash.sql"
  "Database/migration_qrcode_verification.sql"

  # ④ Clés étrangères en cascade — en dernier
  "Database/migration_cascade_suppression.sql"
)

# ── Vérification de présence avant toute écriture ───────────────────────────
MISSING=0
for m in "${MIGRATIONS[@]}"; do
  [ -f "$ROOT/$m" ] || { echo "MANQUANT  $m"; MISSING=1; }
done
[ "$MISSING" = 1 ] && { echo; echo "Fichiers manquants : uploader Database/ et migrations/ avant de relancer." >&2; exit 1; }

echo "Base    : $DB_NAME sur $DB_HOST (utilisateur $DB_USER)"
echo "Fichiers: ${#MIGRATIONS[@]} migrations"
echo

if [ "$GO" = 0 ]; then
  echo "── SIMULATION — ordre d'application ─────────────────────────────"
  i=1
  for m in "${MIGRATIONS[@]}"; do printf "%2d. %s\n" "$i" "$m"; i=$((i+1)); done
  echo
  echo "Rien n'a été modifié. Pour appliquer :  bash deploy/run-migrations.sh --go"
  exit 0
fi

# ── Sauvegarde complète avant toute modification de schéma ──────────────────
BACKUP="$ROOT/../backup-${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
echo "Sauvegarde → $BACKUP"
DUMP=(mysqldump --host="$DB_HOST" --user="$DB_USER" --single-transaction --routines --triggers "$DB_NAME")
[ -n "$DB_PASS" ] && DUMP=(mysqldump --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" --single-transaction --routines --triggers "$DB_NAME")
if ! "${DUMP[@]}" | gzip > "$BACKUP"; then
  echo "Échec de la sauvegarde — aucune migration appliquée." >&2
  rm -f "$BACKUP"
  exit 1
fi
echo "Sauvegarde OK ($(du -h "$BACKUP" | cut -f1))"
echo

# ── Application ─────────────────────────────────────────────────────────────
LOG="$ROOT/storage/logs/migrations-$(date +%Y%m%d-%H%M%S).log"
mkdir -p "$(dirname "$LOG")"
FAILED=()

for m in "${MIGRATIONS[@]}"; do
  printf '%-58s' "$m"
  if "${MYSQL[@]}" --force < "$ROOT/$m" >> "$LOG" 2>&1; then
    echo "ok"
  else
    echo "ERREUR"
    FAILED+=("$m")
  fi
done

echo
echo "Journal détaillé : $LOG"

# Les « Duplicate column » / « Duplicate key » signalent une migration déjà
# appliquée : bruit normal sur une base partiellement à jour.
DUPES=$(grep -ci 'duplicate' "$LOG" 2>/dev/null || echo 0)
echo "Migrations déjà appliquées (avertissements « Duplicate ») : $DUPES"

REAL=$(grep -i 'ERROR' "$LOG" 2>/dev/null | grep -vi 'duplicate' | head -20)
if [ -n "$REAL" ]; then
  echo
  echo "── ERREURS À EXAMINER ───────────────────────────────────────────"
  echo "$REAL"
  echo
  echo "Restauration si besoin :  gunzip < $BACKUP | mysql -u $DB_USER -p $DB_NAME"
  exit 1
fi

echo
echo "Terminé sans erreur bloquante. Sauvegarde conservée : $BACKUP"
echo "Recharger ensuite l'opcache :  sudo systemctl reload php8.3-fpm"
