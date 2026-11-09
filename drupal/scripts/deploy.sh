#!/usr/bin/env bash
# Despliegue estándar de Drupal tras actualizar el código:
# dependencias de producción, actualizaciones de BD, importación de
# configuración y vaciado de cachés (drush deploy).
#
#   docker compose exec drupal bash scripts/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "▸ Copia de seguridad previa"
bash scripts/backup.sh

echo "▸ Dependencias (sin paquetes de desarrollo)"
composer install --no-dev --no-interaction --no-progress --optimize-autoloader

echo "▸ drush deploy (updatedb + config:import + cache:rebuild + deploy hooks)"
drush deploy --yes

echo "▸ Comprobación"
drush status --fields=drupal-version,db-status,bootstrap
