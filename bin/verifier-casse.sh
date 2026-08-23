#!/usr/bin/env bash
#
# Détecte les références d'images dont la casse ne correspond pas au fichier réel.
#
# macOS ne distingue pas majuscules et minuscules dans les noms de fichiers ;
# Linux si. Une image écrite `icones-Univ.png` alors que le fichier s'appelle
# `Icones-Univ.png` s'affiche donc parfaitement en local et renvoie 404 en
# production. C'est arrivé le 4 août 2026 sur la section « consolidation » de la
# page d'accueil.
#
# Sortie 1 si au moins une référence est fautive → deploy.sh s'arrête.

set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

fautes=0

# Toutes les références à assets/images/... dans les vues, feuilles et scripts.
refs=$(grep -rhoE "assets/images/[A-Za-z0-9._%()' -]+\.(png|jpg|jpeg|webp|svg|gif|ico)" \
        app/Views assets public 2>/dev/null \
        | sed 's|.*assets/images/||' \
        | sort -u)

while IFS= read -r nom; do
    [ -z "$nom" ] && continue

    # Les références passent parfois par un encodage d'URL (accents).
    decode=$(printf '%b' "${nom//%/\\x}" 2>/dev/null || echo "$nom")

    for candidat in "$nom" "$decode"; do
        # Comparaison de CHAÎNES contre le contenu réel du dossier : `test -f`
        # serait insensible à la casse sur macOS et ne verrait donc rien.
        if [ -e "assets/images/$candidat" ] && ls assets/images | grep -qxF "$candidat"; then
            continue 2
        fi
    done

    # Le fichier existe-t-il avec une casse différente ? Alors c'est LE piège.
    reel=$(ls assets/images | grep -ixF "$decode" | head -1)
    if [ -n "$reel" ]; then
        echo "  CASSE  assets/images/$nom  →  le fichier réel est « $reel »"
    else
        echo "  ABSENT assets/images/$nom  →  aucun fichier de ce nom"
    fi
    fautes=$((fautes + 1))
done <<< "$refs"

if [ "$fautes" -gt 0 ]; then
    echo
    echo "$fautes référence(s) d'image fautive(s) — déploiement interrompu."
    echo "Ces images s'affichent en local (macOS ignore la casse) mais renvoient 404 en production."
    exit 1
fi

echo "Références d'images : OK (casse et existence vérifiées)."
exit 0
