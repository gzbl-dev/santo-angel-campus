<?php

declare(strict_types=1);

namespace Drupal\sa_core;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Construye los arrays de render compartidos por páginas y bloques.
 */
final class WidgetBuilder {

  use StringTranslationTrait;

  public function __construct(
    private readonly CampusRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Buscador de titulaciones (Vue) con un listado HTML como alternativa.
   *
   * El servidor pinta la lista completa de enlaces; si JavaScript está
   * disponible, el widget Vue la sustituye por el buscador con filtros.
   */
  public function buscador(bool $compact = FALSE): array {
    $items = [];
    foreach ($this->repository->titulaciones() as $node) {
      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
      ];
    }

    $build = [
      '#theme' => 'sa_buscador',
      '#endpoint' => Url::fromRoute('jsonapi.node--titulacion.collection')->toString(),
      '#options' => [
        'tipo' => $this->repository->allowedValues('field_tipo'),
        'rama' => $this->repository->allowedValues('field_rama'),
        'modalidad' => $this->repository->allowedValues('field_modalidad'),
      ],
      '#items' => $items,
      '#compact' => $compact,
      '#attached' => ['library' => ['sa_core/buscador']],
    ];

    (new CacheableMetadata())
      ->addCacheTags(['node_list:titulacion', 'config:field.storage.node.field_rama'])
      ->applyTo($build);

    return $build;
  }

  /**
   * Listado de próximos eventos en modo teaser.
   */
  public function eventos(?int $limit = NULL, bool $with_more_link = FALSE): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $events = $this->repository->upcomingEvents($limit);

    $build = [
      '#theme' => 'sa_eventos',
      '#items' => $events ? $view_builder->viewMultiple($events, 'teaser') : [],
      '#empty' => $this->t('No hay eventos programados ahora mismo. ¡Vuelve pronto!'),
      '#more_link' => $with_more_link ? Url::fromRoute('sa_core.eventos')->toString() : NULL,
    ];

    // La lista cambia cuando pasa la fecha de un evento: caducamos cada hora.
    (new CacheableMetadata())
      ->addCacheTags(['node_list:evento'])
      ->setCacheMaxAge(3600)
      ->applyTo($build);

    return $build;
  }

}
