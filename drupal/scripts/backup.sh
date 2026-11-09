#!/usr/bin/env bash
# Copia de seguridad de base de datos y ficheros, con rotación.
#
#   docker compose exec drupal bash scripts/backup.sh
#   KEEP=14 docker compose exec drupal bash scripts/backup.sh
set -euo pipefail
cd "$(dirname "$0")/.."

KEEP="${KEEP:-7}"
DIR="backups"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DIR"

echo "▸ Base de datos"
drush sql:dump --gzip --structure-tables-key=common --result-file="../$DIR/db-$STAMP.sql"

echo "▸ Ficheros públicos"
tar -czf "$DIR/files-$STAMP.tar.gz" -C web/sites/default files

echo "▸ Rotación: se conservan las $KEEP copias más recientes de cada tipo"
for prefix in db files; do
  ls -1t "$DIR"/"$prefix"-* 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm --
done

ls -lh "$DIR"
