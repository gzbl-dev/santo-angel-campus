<?php

declare(strict_types=1);

namespace Drupal\sa_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\sa_core\CampusRepository;
use Drupal\sa_core\IcsGenerator;
use Drupal\sa_core\WidgetBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas públicas del campus.
 */
final class CampusController extends ControllerBase {

  public function __construct(
    private readonly CampusRepository $repository,
    private readonly WidgetBuilder $widgets,
    private readonly IcsGenerator $ics,
  ) {}

  /**
   * Portada: buscador compacto y próximos eventos.
   */
  public function home(): array {
    return [
      '#theme' => 'sa_home',
      '#buscador' => $this->widgets->buscador(compact: TRUE),
      '#eventos' => $this->widgets->eventos(limit: 3, with_more_link: TRUE),
    ];
  }

  /**
   * Listado completo de titulaciones con el buscador.
   */
  public function titulaciones(): array {
    return $this->widgets->buscador();
  }

  /**
   * Agenda con todos los próximos eventos.
   */
  public function eventos(): array {
    return $this->widgets->eventos();
  }

  /**
   * Preguntas frecuentes agrupadas, con <details> accesibles.
   */
  public function faq(): array {
    $groups = [];
    foreach ($this->repository->faqsByCategory() as $key => $group) {
      $groups[$key] = [
        'label' => $group['label'],
        'items' => array_map(static fn (NodeInterface $faq): array => [
          'id' => 'faq-' . $faq->id(),
          'question' => $faq->label(),
          'answer' => [
            '#type' => 'processed_text',
            '#text' => $faq->get('field_descripcion')->value ?? '',
            '#format' => $faq->get('field_descripcion')->format ?? 'basic_html',
          ],
        ], $group['items']),
      ];
    }

    $build = ['#theme' => 'sa_faq', '#groups' => $groups];
    (new CacheableMetadata())->addCacheTags(['node_list:faq'])->applyTo($build);

    return $build;
  }

  /**
   * Descarga del evento en formato .ics.
   */
  public function eventoIcs(NodeInterface $node): Response {
    return new Response($this->ics->fromEvent($node), 200, [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="' . $this->ics->filename($node) . '"',
    ]);
  }

}
