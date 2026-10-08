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
# DEUX RACINES D'IMAGES coexistent, et une référence n'est fautive que si elle
# manque dans LA SIENNE :
#   · `assets/images/…`         — chemins écrits en dur dans les vues
#   · `public/assets/images/…`  — résolution du helper `asset()` de index.php
# Ne vérifier que la première produisait un faux positif bloquant sur
# `logo-removebg.png` (présent côté public), et laissait surtout les 114 appels
# `asset('images/…')` sans aucun contrôle.
#
# Sortie 1 si au moins une référence est fautive → deploy.sh s'arrête.

set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

fautes=0

# Le fichier `$2` existe-t-il dans le dossier `$1`, à la casse EXACTE ?
# `ls | grep -qxF` compare des chaînes : `test -f` serait insensible à la casse
# sur macOS et ne verrait donc jamais le piège qu'on cherche.
existe_exact() {
    [ -d "$1" ] || return 1
    ls "$1" 2>/dev/null | grep -qxF "$2"
}

# Signale une référence fautive en distinguant les deux cas : le fichier existe
# à une autre casse (le vrai piège Linux) ou il n'existe pas du tout.
signaler() {
    local dossier="$1" nom="$2" decode="$3"
    local reel
    reel=$(ls "$dossier" 2>/dev/null | grep -ixF "$decode" | head -1)
    if [ -n "$reel" ]; then
        echo "  CASSE  $dossier/$nom  →  le fichier réel est « $reel »"
    else
        echo "  ABSENT $dossier/$nom  →  aucun fichier de ce nom"
    fi
    fautes=$((fautes + 1))
}

# Une référence est valide si son nom existe, tel quel ou après décodage d'URL
# (les noms accentués voyagent parfois encodés dans les feuilles de style).
verifier() {
    local dossier="$1" nom="$2" decode candidat
    decode=$(printf '%b' "${nom//%/\\x}" 2>/dev/null || echo "$nom")

    for candidat in "$nom" "$decode"; do
        existe_exact "$dossier" "$candidat" && return 0
    done

    signaler "$dossier" "$nom" "$decode"
}

# ── 1. Chemins écrits en dur : « assets/images/… » et « public/assets/images/… »
# Le préfixe est conservé pour interroger la bonne racine.
while IFS= read -r ref; do
    [ -z "$ref" ] && continue
    case "$ref" in
        public/assets/images/*) verifier "public/assets/images" "${ref#public/assets/images/}" ;;
        assets/images/*)        verifier "assets/images"        "${ref#assets/images/}" ;;
    esac
done <<< "$(grep -rhoE "(public/)?assets/images/[A-Za-z0-9._%()' -]+\.(png|jpg|jpeg|webp|svg|gif|ico)" \
             app/Views assets public 2>/dev/null | sort -u)"

# ── 2. Appels du helper : asset('images/…') → toujours public/assets/images/ ──
while IFS= read -r nom; do
    [ -z "$nom" ] && continue
    verifier "public/assets/images" "$nom"
done <<< "$(grep -rhoE "asset\(\s*'images/[A-Za-z0-9._%()' -]+\.(png|jpg|jpeg|webp|svg|gif|ico)'" \
             app/Views 2>/dev/null \
             | sed -E "s|.*asset\(\s*'images/||; s|'$||" | sort -u)"

if [ "$fautes" -gt 0 ]; then
    echo
    echo "$fautes référence(s) d'image fautive(s) — déploiement interrompu."
    echo "Ces images s'affichent en local (macOS ignore la casse) mais renvoient 404 en production."
    exit 1
fi

echo "Références d'images : OK (casse et existence vérifiées, deux racines)."
exit 0
