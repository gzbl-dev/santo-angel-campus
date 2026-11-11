#!/usr/bin/env bash
# Instala el sitio desde cero: dependencias, Drupal, módulo, tema y contenido.
#
#   docker compose exec drupal bash scripts/install.sh           # instala si no existe
#   docker compose exec drupal bash scripts/install.sh --force   # reinstala (borra todo)
set -euo pipefail
cd "$(dirname "$0")/.."

FORCE=false
[[ "${1:-}" == "--force" ]] && FORCE=true

log() { printf '\n\033[1;33m▸ %s\033[0m\n' "$*"; }

log "Dependencias de Composer"
composer install --no-interaction --no-progress

mkdir -p private config/sync
chown -R www-data:www-data private web/sites/default 2>/dev/null || true

if drush status --field=bootstrap 2>/dev/null | grep -q Successful && [[ "$FORCE" == false ]]; then
  echo "Drupal ya está instalado. Usa --force para reinstalar desde cero."
  exit 0
fi

log "Instalando Drupal (perfil standard, en español)"
# El instalador añade credenciales fijas al final de settings.php; como el
# nuestro ya las lee del entorno, lo restauramos al terminar.
cp web/sites/default/settings.php /tmp/settings.php.bak
restore_settings() {
  chmod u+w web/sites/default web/sites/default/settings.php
  cp /tmp/settings.php.bak web/sites/default/settings.php
}
trap restore_settings EXIT
drush site:install standard \
  --locale=es \
  --site-name="Universidad Santo Ángel" \
  --site-mail="web@santoangel.example" \
  --account-name=admin \
  --account-pass="${DRUPAL_ADMIN_PASSWORD:-admin}" \
  --yes

log "Zona horaria y país"
drush config:set system.date timezone.default Europe/Madrid --yes
drush config:set system.date country.default ES --yes

log "Módulos propios y tema"
drush pm:install sa_core sa_assistant --yes
drush theme:install santoangel --yes
drush config:set system.theme default santoangel --yes

log "Contenido de demostración"
drush sa:seed
drush sa:index

log "Exportando la configuración a config/sync (para versionarla y desplegar)"
drush config:export --yes

drush cache:rebuild
chown -R www-data:www-data web/sites/default/files private 2>/dev/null || true

log "Listo"
echo "Portal:      http://localhost:${DRUPAL_PORT:-8080}"
echo "Admin:       http://localhost:${DRUPAL_PORT:-8080}/user/login  (admin / ${DRUPAL_ADMIN_PASSWORD:-admin})"
echo "JSON:API:    http://localhost:${DRUPAL_PORT:-8080}/jsonapi/node/titulacion"
