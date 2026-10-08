#!/usr/bin/env bash
#
# Déploiement Connect'Academia — local → production.
#
# Remplace le glisser-déposer FileZilla, qui écrasait des dossiers contenant des
# données runtime (uploads utilisateurs, logs, .env) absentes du dépôt.
#
# Usage :
#   ./deploy.sh            # aperçu, n'écrit rien
#   ./deploy.sh --go       # transfert réel
#
set -euo pipefail

REMOTE_USER="administrator"
REMOTE_HOST="85.190.96.109"
REMOTE_PATH="/var/www/connectacademia.io"
LOCAL_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Dossiers et fichiers qui vivent EN PRODUCTION et ne doivent jamais être
# écrasés ni supprimés par un déploiement.
EXCLUDES=(
  --exclude=.git
  --exclude=.claude
  --exclude=.env                 # secrets prod, différents du local
  --exclude=public/uploads/      # avatars, posts, ressources : données utilisateurs
  --exclude=storage/             # logs, sessions, repli uploads : runtime prod
  --exclude=vendor/              # 128 Mo — reconstruit par composer install côté serveur
  --exclude=.DS_Store            # junk macOS
  --exclude=node_modules/
  --exclude=Database/*.sql
  --exclude=deploy.sh

  # Installé directement sur le serveur, absent du dépôt : sans cette ligne
  # --delete l'effacerait de la production à chaque déploiement.
  --exclude=phpmyadmin           # sans barre finale : fichier, lien ou dossier

  # Outils et documents de travail : n'ont rien à faire sur un serveur public.
  --exclude=ui-ux-pro-max-skill/
  --exclude=.vscode/
  --exclude=.agents/              # configuration d'agents locaux
  --exclude=.gitleaks.toml
  --exclude=.gitignore            # sans objet hors dépôt
  --exclude=docs/
  --exclude=deploy/
  --exclude=Database/             # migrations ET dumps : jouées à la main, jamais servies
  --exclude=*.md
  --exclude=*.sql
  --exclude=tests/                 # suites de test : écrivent en base, jamais en prod
  --exclude=psalm.xml
  --exclude=psalm-taint.json      # sortie d'analyse statique
  --exclude=skills-lock.json
  --exclude=package-lock.json
  --exclude=nginx.conf            # référence locale ; la vraie conf vit dans /etc/nginx
  --exclude=fatal.html            # gabarit mort : index.php génère sa page fatale en ligne

  # Filets génériques : rien de ces formes ne doit partir, quel que soit le dossier.
  --exclude=.env.*
  --exclude=*.bak
  --exclude=*.old
  --exclude=*.log
)

# Contrôle avant transfert : une image dont la casse ne correspond pas au fichier
# réel s'affiche en local (macOS ignore la casse) et renvoie 404 en production.
# Bloquant : mieux vaut ne pas déployer que livrer des images cassées.
echo "── CONTRÔLE DES RÉFÉRENCES D'IMAGES ─────────────────────"
if ! "${LOCAL_PATH}/bin/verifier-casse.sh"; then
  exit 1
fi
echo

# --delete supprime en prod les fichiers retirés du dépôt, mais jamais ce qui est
# listé ci-dessus : rsync ne touche pas aux chemins exclus.
RSYNC_OPTS=(-az --delete --human-readable --itemize-changes)

if [[ "${1:-}" != "--go" ]]; then
  echo "── APERÇU (aucune écriture) ─────────────────────────────"
  echo "   ${LOCAL_PATH}/  →  ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"
  echo "   Relancer avec --go pour appliquer."
  echo
  RSYNC_OPTS+=(--dry-run)
fi

rsync "${RSYNC_OPTS[@]}" "${EXCLUDES[@]}" \
  "${LOCAL_PATH}/" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"

if [[ "${1:-}" == "--go" ]]; then
  echo
  echo "── Post-déploiement ─────────────────────────────────────"
  ssh "${REMOTE_USER}@${REMOTE_HOST}" bash -s <<'REMOTE'
set -e
cd /var/www/connectacademia.io

composer install --no-dev --optimize-autoloader --no-interaction

# Les dossiers de données restent partagés administrator:www-data (setgid).
sudo chown -R administrator:www-data public/uploads storage
sudo find public/uploads storage -type d -exec chmod 2775 {} \;

sudo systemctl reload php8.3-fpm nginx
echo "OK — déploiement terminé."
REMOTE
fi
