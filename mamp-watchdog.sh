#!/bin/bash
# Watchdog — relance Apache + MySQL si MAMP PRO les coupe
# Usage: bash mamp-watchdog.sh (laisser tourner en background)

APACHE_BIN="/Applications/MAMP/Library/bin/httpd"
MYSQL_BIN="/Applications/MAMP/Library/bin/mysql80/bin/mysqld_safe"
START_APACHE="/Applications/MAMP/bin/startApache.sh"
START_MYSQL="/Applications/MAMP/bin/startMysql.sh"

echo "[watchdog] Démarré — surveillance Apache + MySQL toutes les 30s"

while true; do
    # Vérifie Apache
    if ! pgrep -f "MAMP/Library/bin/httpd" > /dev/null 2>&1; then
        echo "[watchdog] $(date '+%H:%M:%S') Apache mort — redémarrage..."
        bash "$START_APACHE" > /dev/null 2>&1
        echo "[watchdog] Apache redémarré"
    fi

    # Vérifie MySQL
    if ! pgrep -f "mysql80/bin/mysqld" > /dev/null 2>&1; then
        echo "[watchdog] $(date '+%H:%M:%S') MySQL mort — redémarrage..."
        bash "$START_MYSQL" > /dev/null 2>&1
        echo "[watchdog] MySQL redémarré"
    fi

    sleep 30
done
