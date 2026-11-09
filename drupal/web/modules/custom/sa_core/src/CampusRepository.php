<?php

declare(strict_types=1);

namespace Drupal\sa_core;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Consultas de contenido del campus reutilizadas por páginas y bloques.
 */
final class CampusRepository {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Titulaciones publicadas, ordenadas alfabéticamente.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Titulaciones indexadas por ID de nodo.
   */
  public function titulaciones(): array {
    return $this->loadPublished('titulacion', 'title', 'ASC');
  }

  /**
   * Eventos publicados que aún no han empezado, del más cercano al más lejano.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Eventos indexados por ID de nodo.
   */
  public function upcomingEvents(?int $limit = NULL): array {
    $now = DrupalDateTime::createFromTimestamp(
      $this->time->getRequestTime(),
      DateTimeItemInterface::STORAGE_TIMEZONE,
    )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->storage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'evento')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_fecha', $now, '>=')
      ->sort('field_fecha', 'ASC');

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    return $this->storage()->loadMultiple($query->execute());
  }

  /**
   * Preguntas frecuentes agrupadas por categoría, en el orden de la lista.
   *
   * @return array<string, array{label: string, items: \Drupal\node\NodeInterface[]}>
   *   Grupos no vacíos indexados por la clave de la categoría.
   */
  public function faqsByCategory(): array {
    $definition = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.field_categoria_faq');
    $labels = $definition ? options_allowed_values($definition) : [];

    $groups = [];
    foreach ($labels as $key => $label) {
      $groups[$key] = ['label' => (string) $label, 'items' => []];
    }

    foreach ($this->loadPublished('faq', 'title', 'ASC') as $faq) {
      $key = $faq->get('field_categoria_faq')->value ?? '';
      $groups[$key] ??= ['label' => $key, 'items' => []];
      $groups[$key]['items'][] = $faq;
    }

    return array_filter($groups, static fn (array $group): bool => $group['items'] !== []);
  }

  /**
   * Valores permitidos (clave => etiqueta) de un campo de lista.
   *
   * @return array<string, string>
   *   Etiquetas indexadas por valor; vacío si el campo no existe.
   */
  public function allowedValues(string $field_name): array {
    $definition = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.' . $field_name);

    return $definition ? array_map('strval', options_allowed_values($definition)) : [];
  }

  /**
   * Nodos publicados de un tipo, ordenados.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Nodos indexados por ID.
   */
  private function loadPublished(string $bundle, string $sort, string $direction): array {
    $ids = $this->storage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort($sort, $direction)
      ->execute();

    return $this->storage()->loadMultiple($ids);
  }

  /**
   * Almacenamiento de nodos.
   */
  private function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

}
