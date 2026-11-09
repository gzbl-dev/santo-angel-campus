<?php

/**
 * @file
 * Configuración del sitio. Todo lo sensible llega por variables de entorno
 * (docker-compose / .env), así que este fichero se versiona sin secretos.
 */

declare(strict_types=1);

$env = static fn (string $name, ?string $default = NULL): ?string =>
  getenv($name) !== FALSE && getenv($name) !== '' ? getenv($name) : $default;

// ── Base de datos ─────────────────────────────────────────────────────────
// DB_DRIVER=mysql (Docker, por defecto) o sqlite (desarrollo rápido / CI).
if ($env('DB_DRIVER', 'mysql') === 'sqlite') {
  $databases['default']['default'] = [
    'driver' => 'sqlite',
    'database' => $env('DB_SQLITE_PATH', $app_root . '/../private/db.sqlite'),
    'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  ];
}
else {
  $databases['default']['default'] = [
    'driver' => 'mysql',
    'host' => $env('DB_HOST', 'db'),
    'port' => $env('DB_PORT', '3306'),
    'database' => $env('DB_NAME', 'drupal'),
    'username' => $env('DB_USER', 'drupal'),
    'password' => $env('DB_PASSWORD', 'drupal'),
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
    'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
    'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  ];
}

// ── Seguridad ─────────────────────────────────────────────────────────────
$settings['hash_salt'] = $env('DRUPAL_HASH_SALT', 'solo-para-desarrollo-cambiar-en-produccion');
$settings['update_free_access'] = FALSE;
$settings['trusted_host_patterns'] = array_map(
  static fn (string $host): string => '^' . preg_quote(trim($host)) . '$',
  explode(',', $env('DRUPAL_TRUSTED_HOSTS', 'localhost,127.0.0.1')),
);

// ── Rutas ─────────────────────────────────────────────────────────────────
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private';

// ── Varios ────────────────────────────────────────────────────────────────
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['state_cache'] = TRUE;

// Ajustes locales que no se versionan (p. ej. desactivar cachés en desarrollo).
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
