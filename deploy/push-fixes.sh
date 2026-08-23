#!/usr/bin/env bash
# ============================================================================
# Connect'Academia — envoi des correctifs du 31 juillet 2026 vers la production
#
#   Verre/curseur console admin · mot de passe oublié · upload de ressources ·
#   tarifs boursier/non boursier + uniforme
#
# POURQUOI CE SCRIPT ET PAS `git push`
# ------------------------------------
# Le module Orientation (et une bonne partie du code actuel) n'a JAMAIS été
# commité : `origin/main` est identique à `main` en local et ne contient pas
# app/Controllers/Orientation, app/Models/Orientation, Database/, etc.
# La production n'a donc pas été déployée par git. On synchronise donc
# explicitement la liste FERMÉE des fichiers modifiés, rien d'autre.
#
# USAGE
#   bash deploy/push-fixes.sh            # simulation (dry-run) — ne modifie rien
#   bash deploy/push-fixes.sh --diff     # affiche le diff prod ↔ local, fichier par fichier
#   bash deploy/push-fixes.sh --go       # envoi réel (sauvegarde distante automatique)
#
# CE QUE LE SCRIPT NE TOUCHE JAMAIS
#   .env · storage/ · public/uploads/ · vendor/ · la conf nginx · la base
#   (les migrations SQL restent à lancer à la main, voir la fin du script)
# ============================================================================
set -euo pipefail

REMOTE="${REMOTE:-administrator@85.190.96.109}"
RPATH="${RPATH:-/var/www/connectacademia.io}"
LOCAL="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"

# Multiplexage SSH : le mot de passe n'est demandé QU'UNE FOIS, la connexion
# est ensuite réutilisée par rsync et par tous les scp (sinon `--diff` en
# réclamerait un par fichier). La socket est fermée à la fin du script.
CTL="${TMPDIR:-/tmp}/ca-deploy-$$.sock"
SSHOPTS=(-o ControlMaster=auto -o "ControlPath=$CTL" -o ControlPersist=600)
SSH=(ssh "${SSHOPTS[@]}")
SCP=(scp "${SSHOPTS[@]}")
export RSYNC_RSH="ssh -o ControlMaster=auto -o ControlPath=$CTL -o ControlPersist=600"
cleanup() { ssh -O exit -o "ControlPath=$CTL" "$REMOTE" 2>/dev/null || true; }
trap cleanup EXIT

FILES=(
  # ── Correctif 1 : verre / curseur console admin ────────────────────────
  public/assets/css/admin-claude.css
  public/assets/liquid-glass/container.js
  public/assets/liquid-glass/sidebar-glass.js
  # ── Correctif 2 : mot de passe oublié ──────────────────────────────────
  app/Core/PasswordResetSchema.php
  app/Controllers/AuthController.php
  app/Controllers/Orientation/CandidatureController.php
  app/Views/auth/mot-de-passe-oublie.php
  app/Views/auth/reinitialiser.php
  # ── Correctif 3 : upload de ressources ─────────────────────────────────
  app/Core/RessourceStorage.php
  app/Controllers/Admin/ContenuController.php
  app/Controllers/Apprentissage/RessourceController.php
  app/Controllers/Apprentissage/IaController.php
  app/Views/apprentissage/viewer.php
  config/routes.php
  # ── Correctif 4 : tarifs boursiers + uniforme ──────────────────────────
  app/Models/Orientation/Etablissement.php
  app/Controllers/Admin/OrientationController.php
  app/Views/admin/orientation/create.php
  app/Views/admin/orientation/edit.php
  app/Views/admin/orientation/show.php
  app/Views/orientation/etablissement.php
  public/assets/orientation/site.css
)

MODE="${1:-dry}"

cd "$LOCAL"

# Garde-fou : on ne pousse pas un fichier qui ne compile pas.
for f in "${FILES[@]}"; do
  [ -f "$f" ] || { echo "Fichier local introuvable : $f" >&2; exit 1; }
  case "$f" in
    *.php) php -l "$f" > /dev/null || { echo "Erreur de syntaxe : $f" >&2; exit 1; } ;;
  esac
done
echo "✓ ${#FILES[@]} fichiers présents, PHP lint OK"

# ── Mode diff : compare chaque fichier avec sa version en production ────────
if [ "$MODE" = "--diff" ]; then
  tmp="$(mktemp -d)"
  trap 'rm -rf "$tmp"; cleanup' EXIT
  for f in "${FILES[@]}"; do
    if "${SSH[@]}" "$REMOTE" "test -f '$RPATH/$f'" 2>/dev/null; then
      mkdir -p "$tmp/$(dirname "$f")"
      "${SCP[@]}" -q "$REMOTE:$RPATH/$f" "$tmp/$f"
      if diff -q "$tmp/$f" "$f" > /dev/null; then
        echo "= identique      $f"
      else
        echo "≠ DIFFÉRENT      $f"
        diff -u "$tmp/$f" "$f" | sed 's/^/    /' | head -40
      fi
    else
      echo "+ nouveau        $f"
    fi
  done
  exit 0
fi

# ── Sauvegarde distante des fichiers qui vont être écrasés ─────────────────
if [ "$MODE" = "--go" ]; then
  echo "→ sauvegarde distante dans ~/backup-$STAMP/"
  printf '%s\n' "${FILES[@]}" | "${SSH[@]}" "$REMOTE" "
    set -e
    mkdir -p ~/backup-$STAMP
    while read -r f; do
      if [ -f '$RPATH'/\"\$f\" ]; then
        mkdir -p ~/backup-$STAMP/\$(dirname \"\$f\")
        cp -p '$RPATH'/\"\$f\" ~/backup-$STAMP/\"\$f\"
      fi
    done
    echo '   sauvegarde terminée'
  "
  RSYNC_FLAGS=(-av)
else
  echo "→ SIMULATION (aucune écriture). Relancer avec --go pour appliquer."
  RSYNC_FLAGS=(-av --dry-run)
fi

# --relative conserve l'arborescence ; PAS de --delete, jamais.
# (rsync d'Apple ne connaît pas --chmod : les droits sont posés côté serveur
#  juste après, dans le bloc post-déploiement.)
rsync "${RSYNC_FLAGS[@]}" --relative \
  "${FILES[@]/#/./}" "$REMOTE:$RPATH/"

if [ "$MODE" != "--go" ]; then
  exit 0
fi

# ── Post-déploiement ───────────────────────────────────────────────────────
printf '%s\n' "${FILES[@]}" | "${SSH[@]}" "$REMOTE" "
  set -e
  cd '$RPATH'
  while read -r f; do
    [ -f \"\$f\" ] && chmod 0644 \"\$f\" && chown www-data:www-data \"\$f\" 2>/dev/null || true
  done
  # Vide l'opcache PHP-FPM : sans ça l'ancien bytecode reste servi.
  sudo systemctl reload php8.3-fpm
  echo '   droits posés + PHP-FPM rechargé'
"

cat <<'NEXT'

──────────────────────────────────────────────────────────────────────────
Fichiers déployés. Il reste TROIS étapes à faire à la main sur le serveur :

  1. Sauvegarder la base AVANT les migrations
       mysqldump -u <user> -p <base> > ~/dump-avant-migrations.sql

  2. Jouer les deux migrations (idempotentes, rejouables sans risque)
       scp Database/migration_password_resets_token_hash.sql \
           Database/migration_frais_boursier_uniforme.sql \
           administrator@85.190.96.109:~/
       # puis, sur le serveur :
       mysql -u <user> -p <base> < ~/migration_password_resets_token_hash.sql
       mysql -u <user> -p <base> < ~/migration_frais_boursier_uniforme.sql

  3. Remettre les droits d'écriture (corrige « Dossier de destination
     non accessible » et remet les uploads dans public/, servis par nginx)
       scp deploy/fix-permissions.sh administrator@85.190.96.109:~/
       sudo bash ~/fix-permissions.sh /var/www/connectacademia.io www-data

VÉRIFICATIONS ENSUITE
  · /auth/mot-de-passe-oublie → saisir un email → le message de confirmation
    doit s'afficher, et l'email arriver
  · Admin → Contenu → Ajouter une ressource (PDF)
  · Admin → Orientation → Modifier un établissement → les champs boursier et
    uniforme, puis la page publique de l'établissement
  · Console admin : scroller /admin/contenu, plus de clignotement du verre
  · Erreurs éventuelles : tail -f /var/www/connectacademia.io/storage/logs/app.log

RETOUR ARRIÈRE
  Les fichiers d'origine sont dans ~/backup-<horodatage>/ sur le serveur.
──────────────────────────────────────────────────────────────────────────
NEXT
