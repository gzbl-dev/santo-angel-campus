<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Llm;

/**
 * Un turno del modelo de lenguaje: recibe la conversación y decide qué hacer.
 *
 * Los mensajes y las herramientas siguen el formato de la Messages API de
 * Anthropic (bloques `text`, `tool_use` y `tool_result`), así que un adaptador
 * real solo tiene que reenviarlos. El agente no sabe qué implementación usa.
 */
interface LlmClientInterface {

  /**
   * Genera la siguiente respuesta del asistente.
   *
   * @param string $system
   *   Instrucciones de sistema.
   * @param list<array{role: string, content: list<array<string, mixed>>}> $messages
   *   Conversación, alternando `user` y `assistant`.
   * @param list<array{name: string, description: string, input_schema: array<string, mixed>}> $tools
   *   Herramientas disponibles.
   */
  public function complete(string $system, array $messages, array $tools): LlmResponse;

  /**
   * TRUE si las respuestas no salen de un modelo real (modo demo).
   */
  public function isSimulated(): bool;

}
