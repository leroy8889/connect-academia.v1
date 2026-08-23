#!/usr/bin/env bash
# ============================================================================
# Connect'Academia — droits d'écriture des dossiers applicatifs (production)
#
# À LANCER EN ROOT SUR LE VPS APRÈS CHAQUE DÉPLOIEMENT qui recrée des dossiers
# (git clone / rsync exécutés par l'utilisateur de déploiement, pas par PHP).
#
# Symptôme corrigé : « Dossier de destination non accessible » à l'ajout d'une
# ressource dans la console admin — public/uploads/ressources appartenait à
# l'utilisateur de déploiement, PHP-FPM ne pouvait pas y écrire.
#
# L'application sait se replier sur storage/uploads/ressources (servi par la
# route /ressources/fichier/{id}), mais l'emplacement public reste préférable :
# le fichier est alors servi directement par nginx, sans passer par PHP.
#
#   sudo bash deploy/fix-permissions.sh /var/www/connectacademia.io www-data
# ============================================================================
set -euo pipefail

ROOT="${1:-/var/www/connectacademia.io}"
WEBUSER="${2:-www-data}"

if [ ! -d "$ROOT" ]; then
  echo "Racine introuvable : $ROOT" >&2
  exit 1
fi

WRITABLE=(
  "$ROOT/public/uploads"
  "$ROOT/public/uploads/ressources"
  "$ROOT/public/uploads/avatars"
  "$ROOT/public/uploads/posts"
  "$ROOT/storage"
  "$ROOT/storage/logs"
  "$ROOT/storage/sessions"
  "$ROOT/storage/uploads"
  "$ROOT/storage/uploads/ressources"
)

for dir in "${WRITABLE[@]}"; do
  mkdir -p "$dir"
  chown -R "$WEBUSER":"$WEBUSER" "$dir"
  # 2775 : le bit setgid propage le groupe aux fichiers créés ensuite.
  chmod 2775 "$dir"
  echo "ok  $dir"
done

# Les fichiers déposés restent lisibles par le serveur web, jamais exécutables.
find "$ROOT/public/uploads" "$ROOT/storage/uploads" -type f -exec chmod 0644 {} +

echo
echo "Terminé. Vérifier ensuite dans la console admin : Contenu → Ajouter une ressource."
