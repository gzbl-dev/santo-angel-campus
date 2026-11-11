<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\node\NodeInterface;
use Drupal\sa_core\CampusRepository;

/**
 * Agenda: próximos eventos del campus, opcionalmente de una categoría.
 */
final class ProximosEventosTool implements AssistantToolInterface {

  private const MAX = 10;

  public function __construct(
    private readonly CampusRepository $repository,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return 'proximos_eventos';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return 'Devuelve los próximos eventos del campus (fecha, hora y lugar), del más cercano al más lejano. Úsala para preguntas sobre la agenda, jornadas, talleres o actividades.';
  }

  /**
   * {@inheritdoc}
   */
  public function inputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'limite' => [
          'type' => 'integer',
          'minimum' => 1,
          'maximum' => self::MAX,
          'description' => 'Cuántos eventos devolver (5 por defecto).',
        ],
        'categoria' => [
          'type' => 'string',
          'enum' => array_keys($this->repository->allowedValues('field_categoria_evento')),
        ],
      ],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(): string {
    return 'Mirando la agenda del campus…';
  }

  /**
   * {@inheritdoc}
   */
  public function requiresConfirmation(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): ToolResult {
    $limit = max(1, min(self::MAX, (int) ($input['limite'] ?? 5)));
    $category = $input['categoria'] ?? NULL;
    if ($category !== NULL && !array_key_exists($category, $this->repository->allowedValues('field_categoria_evento'))) {
      throw new \InvalidArgumentException('Categoría de evento no válida.');
    }

    $events = $this->repository->upcomingEvents();
    if ($category !== NULL) {
      $events = array_filter(
        $events,
        static fn (NodeInterface $node): bool => $node->get('field_categoria_evento')->value === $category,
      );
    }

    $items = [];
    $sources = [];
    foreach (array_slice($events, 0, $limit) as $node) {
      $url = $node->toUrl()->toString();
      $date = $node->get('field_fecha')->date;
      $items[] = [
        'titulo' => (string) $node->label(),
        // «jueves 26 de noviembre, 08:30»: días y meses van en minúscula.
        'fecha' => $date ? mb_strtolower($this->dateFormatter->format($date->getTimestamp(), 'custom', 'l j \d\e F, H:i')) : '',
        'lugar' => (string) $node->get('field_lugar')->value,
        'url' => $url,
      ];
      $sources[] = ['titulo' => (string) $node->label(), 'url' => $url, 'tipo' => 'Evento'];
    }

    return new ToolResult(array_filter([
      'categoria' => $category,
      'eventos' => $items,
    ], static fn ($value): bool => $value !== NULL), $sources);
  }

}
