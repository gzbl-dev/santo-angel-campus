<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

use Drupal\sa_assistant\Search\PortalIndex;

/**
 * RAG: busca en las FAQ, titulaciones y eventos los fragmentos más relevantes.
 */
final class BuscarEnPortalTool implements AssistantToolInterface {

  /**
   * Puntuación BM25 mínima de un fragmento.
   */
  public const MIN_SCORE = 2.0;

  /**
   * Parte mínima de los términos de la pregunta que debe cubrir un fragmento.
   */
  public const MIN_COVERAGE = 0.5;

  /**
   * Los demás fragmentos deben puntuar al menos esta fracción del mejor.
   */
  public const MIN_RELATIVE = 0.5;

  public function __construct(
    private readonly PortalIndex $index,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return 'buscar_en_portal';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return 'Busca en el contenido del portal (preguntas frecuentes, fichas de titulaciones y eventos) los fragmentos más relevantes para una consulta. Úsala para dudas sobre matrícula, becas, trámites, servicios o vida en el campus. Responde solo con lo que digan los fragmentos y cita su título.';
  }

  /**
   * {@inheritdoc}
   */
  public function inputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'consulta' => [
          'type' => 'string',
          'description' => 'La duda del usuario, con sus palabras clave.',
        ],
      ],
      'required' => ['consulta'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(): string {
    return 'Buscando en el portal…';
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
    $query = trim((string) ($input['consulta'] ?? ''));
    if ($query === '') {
      throw new \InvalidArgumentException('La consulta está vacía.');
    }

    $results = self::relevant($this->index->search(mb_substr($query, 0, 300)));
    $sources = array_map(static fn (array $r): array => [
      'titulo' => $r['titulo'],
      'url' => $r['url'],
      'tipo' => $r['tipo'],
    ], $results);

    return new ToolResult(['resultados' => $results], $sources);
  }

  /**
   * Descarta los fragmentos que no responden a la pregunta.
   *
   * Mejor no devolver nada que darle al modelo (y enseñar como fuente) una
   * página que solo comparte una palabra con la pregunta.
   *
   * @param list<array{puntuacion: float, cobertura: float}> $results
   *   Resultados de PortalIndex::search(), de más a menos relevante.
   *
   * @return list<array{puntuacion: float, cobertura: float}>
   *   Los que superan los umbrales.
   */
  public static function relevant(array $results): array {
    $best = $results[0]['puntuacion'] ?? 0;
    return array_values(array_filter($results, static fn (array $r): bool =>
      $r['puntuacion'] >= self::MIN_SCORE
      && $r['cobertura'] >= self::MIN_COVERAGE
      && $r['puntuacion'] >= self::MIN_RELATIVE * $best
    ));
  }

}
