# Imagen oficial de Drupal (PHP + Apache + extensiones + Composer).
# El código del proyecto se monta en /opt/drupal desde docker-compose.
FROM drupal:11-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip mariadb-client \
  && rm -rf /var/lib/apt/lists/*

# Drush y el resto de binarios de Composer disponibles en el PATH.
ENV PATH="/opt/drupal/vendor/bin:${PATH}" \
    COMPOSER_MEMORY_LIMIT=-1

WORKDIR /opt/drupal
