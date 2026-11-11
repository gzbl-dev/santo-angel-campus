<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Agent;

/**
 * Acciones propuestas por el modelo que esperan la confirmación del usuario.
 */
interface PendingActionStoreInterface {

  /**
   * Guarda la acción y devuelve un token de un solo uso.
   *
   * @param string $tool
   *   Nombre de la herramienta.
   * @param array<string, mixed> $input
   *   Argumentos de la herramienta.
   */
  public function create(string $tool, array $input): string;

  /**
   * Recupera y borra la acción; NULL si no existe o ha caducado.
   *
   * @return array{tool: string, input: array<string, mixed>}|null
   *   La acción pendiente.
   */
  public function take(string $token): ?array;

}
