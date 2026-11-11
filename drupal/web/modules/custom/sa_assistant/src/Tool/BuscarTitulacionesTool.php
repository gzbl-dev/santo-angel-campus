<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

use Drupal\node\NodeInterface;
use Drupal\sa_core\CampusRepository;

/**
 * Filtra la oferta de titulaciones por tipo, rama y modalidad.
 */
final class BuscarTitulacionesTool implements AssistantToolInterface {

  private const FILTERS = [
    'tipo' => 'field_tipo',
    'rama' => 'field_rama',
    'modalidad' => 'field_modalidad',
  ];

  public function __construct(
    private readonly CampusRepository $repository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return 'buscar_titulaciones';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return 'Lista las titulaciones de la universidad que cumplen los filtros indicados (todos opcionales). Úsala cuando el usuario quiera saber qué puede estudiar o qué grados, másteres o ciclos hay.';
  }

  /**
   * {@inheritdoc}
   */
  public function inputSchema(): array {
    $properties = [];
    foreach (self::FILTERS as $key => $field) {
      $values = $this->repository->allowedValues($field);
      $properties[$key] = [
        'type' => 'string',
        'enum' => array_keys($values),
        'description' => implode(', ', array_map(
          static fn (string $value, string $label): string => "$value = $label",
          array_keys($values),
          $values,
        )),
      ];
    }

    return [
      'type' => 'object',
      'properties' => $properties,
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(): string {
    return 'Consultando la oferta de titulaciones…';
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
    $filters = [];
    foreach (self::FILTERS as $key => $field) {
      if (!isset($input[$key]) || $input[$key] === '') {
        continue;
      }
      if (!array_key_exists($input[$key], $this->repository->allowedValues($field))) {
        throw new \InvalidArgumentException("Valor no válido para «{$key}».");
      }
      $filters[$key] = $input[$key];
    }

    $matches = array_filter(
      $this->repository->titulaciones(),
      static function (NodeInterface $node) use ($filters): bool {
        foreach ($filters as $key => $value) {
          if ($node->get(self::FILTERS[$key])->value !== $value) {
            return FALSE;
          }
        }
        return TRUE;
      },
    );

    $labels = array_map(fn (string $field): array => $this->repository->allowedValues($field), self::FILTERS);
    $items = [];
    $sources = [];
    foreach ($matches as $node) {
      $url = $node->toUrl()->toString();
      $items[] = [
        'titulo' => (string) $node->label(),
        'tipo' => $labels['tipo'][$node->get('field_tipo')->value] ?? '',
        'rama' => $labels['rama'][$node->get('field_rama')->value] ?? '',
        'modalidad' => $labels['modalidad'][$node->get('field_modalidad')->value] ?? '',
        'creditos' => (int) $node->get('field_creditos')->value,
        'url' => $url,
      ];
      $sources[] = ['titulo' => (string) $node->label(), 'url' => $url, 'tipo' => 'Titulación'];
    }

    return new ToolResult([
      'total' => count($items),
      'filtros' => $filters,
      'titulaciones' => $items,
    ], $sources);
  }

}
