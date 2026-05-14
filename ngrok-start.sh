#!/usr/bin/env bash
# Lance ngrok sur le port MAMP et met à jour APP_URL dans .env automatiquement
# Usage: bash ngrok-start.sh

set -e

PORT=8888
ENV_FILE="$(dirname "$0")/.env"

if ! command -v ngrok &>/dev/null; then
  echo "ngrok non installé."
  echo "Installe-le sur https://ngrok.com/download ou via Homebrew:"
  echo "  brew install ngrok/ngrok/ngrok"
  exit 1
fi

echo "Démarrage ngrok sur le port ${PORT}..."
ngrok http ${PORT} --log=stdout &
NGROK_PID=$!

# Attendre que ngrok soit prêt
sleep 3

# Récupérer l'URL publique via l'API locale ngrok
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels | python3 -c "
import sys, json
data = json.load(sys.stdin)
tunnels = data.get('tunnels', [])
https = next((t['public_url'] for t in tunnels if t['proto'] == 'https'), None)
http  = next((t['public_url'] for t in tunnels if t['proto'] == 'http'),  None)
print(https or http or '')
" 2>/dev/null)

if [ -z "$NGROK_URL" ]; then
  echo "Impossible de récupérer l'URL ngrok. Vérifie que ngrok est bien lancé."
  kill $NGROK_PID 2>/dev/null
  exit 1
fi

# Construire APP_URL avec le sous-chemin du projet
APP_URL="${NGROK_URL}/connect'academia.v1"

echo ""
echo "URL ngrok détectée : ${NGROK_URL}"
echo "APP_URL configurée : ${APP_URL}"
echo ""

# Mettre à jour APP_URL dans .env
if [ -f "$ENV_FILE" ]; then
  # macOS sed nécessite -i ''
  sed -i '' "s|^APP_URL=.*|APP_URL=${APP_URL}|" "$ENV_FILE"
  echo ".env mis à jour."
else
  echo "Fichier .env introuvable : ${ENV_FILE}"
fi

echo ""
echo "Webhook MoneyFusion : ${APP_URL}/api/paiement/callback"
echo ""
echo "ngrok actif (PID: ${NGROK_PID}). Ctrl+C pour arrêter."
echo ""

# Attendre la fin de ngrok
wait $NGROK_PID
