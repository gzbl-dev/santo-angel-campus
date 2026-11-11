<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Registra una incidencia para el equipo de la universidad.
 *
 * Es la única herramienta que escribe datos, así que requiere confirmación:
 * el agente no la ejecuta, guarda el borrador y el usuario decide.
 */
final class AbrirIncidenciaTool implements AssistantToolInterface {

  public const SUBJECT_MAX = 120;

  public const DESCRIPTION_MAX = 2000;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return 'abrir_incidencia';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return 'Abre una incidencia para que la atienda el personal de la universidad. Úsala solo cuando el usuario lo pida o cuando su problema no se resuelva con la información del portal. El usuario verá el borrador y tendrá que confirmarlo.';
  }

  /**
   * {@inheritdoc}
   */
  public function inputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'asunto' => [
          'type' => 'string',
          'maxLength' => self::SUBJECT_MAX,
          'description' => 'Resumen del problema en una línea.',
        ],
        'descripcion' => [
          'type' => 'string',
          'maxLength' => self::DESCRIPTION_MAX,
          'description' => 'Qué ocurre, con los detalles que haya dado el usuario.',
        ],
      ],
      'required' => ['asunto', 'descripcion'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(): string {
    return 'Preparando la incidencia…';
  }

  /**
   * {@inheritdoc}
   */
  public function requiresConfirmation(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): ToolResult {
    $subject = trim((string) ($input['asunto'] ?? ''));
    $description = trim((string) ($input['descripcion'] ?? ''));
    if ($subject === '' || $description === '') {
      throw new \InvalidArgumentException('La incidencia necesita asunto y descripción.');
    }

    // Se guarda sin publicar: solo la ve el personal desde /admin/content.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'incidencia',
      'title' => mb_substr($subject, 0, self::SUBJECT_MAX),
      'field_descripcion' => [
        'value' => mb_substr($description, 0, self::DESCRIPTION_MAX),
        'format' => 'plain_text',
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
      'uid' => 0,
    ]);
    $node->save();

    return new ToolResult([
      'numero' => (int) $node->id(),
      'mensaje' => sprintf('Incidencia n.º %d registrada. El equipo de la universidad la revisará; si necesitas hacer seguimiento, menciona este número.', $node->id()),
    ]);
  }

}
