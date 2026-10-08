#!/bin/sh
# Suite de tests du module inscription boursiers (spec Connect-Academia_Spec_Inscription_Boursiers.md)
#
#   sh tests/orientation/lancer.sh
#
# Chaque fichier crée ses propres données et les nettoie : la suite est
# rejouable indéfiniment sans dériver. Elle tourne sur la base de DÉVELOPPEMENT
# — ne jamais la lancer sur la production, elle écrit et supprime.
set -e
PHP="${PHP:-php}"
RACINE="$(cd "$(dirname "$0")/../.." && pwd)"
TOTAL=0
ECHECS=0

for LOT in 0 1 2 3 4 5 6 7 8 9 10 11_inscription_exclusive 12; do
  printf '\n═══ LOT %s ═══\n' "$LOT"
  if "$PHP" "$RACINE/tests/orientation/lot$LOT.php"; then
    :
  else
    ECHECS=$((ECHECS + 1))
  fi
done

printf '\n'
if [ "$ECHECS" -eq 0 ]; then
  printf 'Toutes les suites sont vertes.\n'
  exit 0
fi
printf '%s suite(s) en échec.\n' "$ECHECS"
exit 1
