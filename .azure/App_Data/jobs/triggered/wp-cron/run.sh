#!/bin/bash
set -euo pipefail

# Optionnel : PATH "sûr"
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

# Dossier Bedrock (ajuste si besoin)
ROOT="/home/site/wwwroot"

# Log minimal côté Kudu (stdout/stderr)
echo "[`date -Is`] Starting wp-cron..."

# Exécuter depuis la racine (pour autoload/composer, etc.)
cd "$ROOT"

# Verrou pour éviter les chevauchements
LOCK="/tmp/wp-cron.lock"

# Binaire PHP (ajuste si différent)
PHP="/usr/local/bin/php"

# Script à lancer
SCRIPT="$ROOT/public/wp-cron-multisite.php"

# Exécute le cron
(
  flock -n 9 || { echo "Another run is in progress, skipping"; exit 0; }
  "$PHP" "$SCRIPT"
) 9>"$LOCK"

echo "[`date -Is`] Done."