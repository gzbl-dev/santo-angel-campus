<?php

declare(strict_types=1);

namespace Drupal\sa_core\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Comandos Drush de la Universidad Santo Ángel.
 */
final class SaCoreCommands extends DrushCommands {

  use AutowireTrait;

  private const BUNDLES = ['titulacion', 'evento', 'faq'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Carga el contenido de demostración desde los JSON de sa_core/data.
   */
  #[CLI\Command(name: 'sa:seed', aliases: ['sa-seed'])]
  #[CLI\Option(name: 'reset', description: 'Borra antes las titulaciones, eventos y FAQ existentes.')]
  #[CLI\Usage(name: 'drush sa:seed', description: 'Crea el contenido que falte (idempotente).')]
  #[CLI\Usage(name: 'drush sa:seed --reset', description: 'Recrea todo el contenido; las fechas de los eventos se recalculan desde hoy.')]
  public function seed(array $options = ['reset' => FALSE]): void {
    if ($options['reset']) {
      $this->deleteDemoContent();
    }

    $created = 0;
    foreach ($this->read('titulaciones') as $item) {
      $created += (int) $this->createIfMissing('titulacion', $item['title'], [
        'field_tipo' => $item['tipo'],
        'field_rama' => $item['rama'],
        'field_modalidad' => $item['modalidad'],
        'field_creditos' => $item['creditos'],
        'field_resumen' => $item['resumen'],
        'field_descripcion' => ['value' => $item['descripcion'], 'format' => 'basic_html'],
        'path' => ['alias' => '/titulaciones/' . $item['slug']],
      ]);
    }

    foreach ($this->read('eventos') as $item) {
      $created += (int) $this->createIfMissing('evento', $item['title'], [
        'field_fecha' => $this->relativeDate((int) $item['dias'], $item['hora']),
        'field_lugar' => $item['lugar'],
        'field_categoria_evento' => $item['categoria'],
        'field_resumen' => $item['resumen'],
        'field_descripcion' => ['value' => $item['descripcion'], 'format' => 'basic_html'],
        'path' => ['alias' => '/eventos/' . $item['slug']],
      ]);
    }

    foreach ($this->read('faqs') as $item) {
      $created += (int) $this->createIfMissing('faq', $item['title'], [
        'field_categoria_faq' => $item['categoria'],
        'field_descripcion' => ['value' => $item['respuesta'], 'format' => 'basic_html'],
      ]);
    }

    $this->logger()->success(dt('Contenido de demostración listo: @count nodos nuevos.', ['@count' => $created]));
  }

  /**
   * Crea un nodo salvo que ya exista otro del mismo tipo y título.
   */
  private function createIfMissing(string $bundle, string $title, array $fields): bool {
    $storage = $this->entityTypeManager->getStorage('node');
    if ($storage->loadByProperties(['type' => $bundle, 'title' => $title])) {
      return FALSE;
    }

    $storage->create([
      'type' => $bundle,
      'title' => $title,
      'status' => 1,
      'uid' => 1,
      'langcode' => 'es',
    ] + $fields)->save();

    return TRUE;
  }

  /**
   * Borra todos los nodos de los tipos de demostración.
   */
  private function deleteDemoContent(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLES, 'IN')
      ->execute();

    $storage->delete($storage->loadMultiple($ids));
    $this->logger()->notice(dt('Borrados @count nodos de demostración.', ['@count' => count($ids)]));
  }

  /**
   * Fecha "dentro de N días a las HH:MM" (hora local) en formato UTC.
   */
  private function relativeDate(int $days, string $time): string {
    $timezone = $this->configFactory->get('system.date')->get('timezone.default') ?: 'Europe/Madrid';
    $date = new DrupalDateTime('today ' . $time, $timezone);
    $date->modify(sprintf('+%d days', $days));
    $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

  /**
   * Lee un fichero de datos de demostración de sa_core/data.
   *
   * @return array<int, array<string, mixed>>
   *   Elementos del fichero JSON.
   */
  private function read(string $name): array {
    $path = dirname(__DIR__, 3) . '/data/' . $name . '.json';
    return json_decode((string) file_get_contents($path), TRUE, flags: JSON_THROW_ON_ERROR);
  }

}
