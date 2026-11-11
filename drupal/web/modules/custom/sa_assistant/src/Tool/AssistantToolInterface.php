<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

/**
 * Herramienta que el asistente puede usar (tool use).
 *
 * Cada herramienta es un servicio con la etiqueta `sa_assistant.tool`; el
 * ToolRegistry las recoge todas, así que añadir una nueva es crear la clase
 * y declararla en sa_assistant.services.yml.
 */
interface AssistantToolInterface {

  /**
   * Nombre que ve el modelo (snake_case).
   */
  public function name(): string;

  /**
   * Qué hace y cuándo usarla. El modelo decide a partir de este texto.
   */
  public function description(): string;

  /**
   * JSON Schema de los argumentos.
   *
   * @return array<string, mixed>
   *   Esquema de tipo `object`.
   */
  public function inputSchema(): array;

  /**
   * Texto de estado para el usuario mientras se ejecuta («Buscando…»).
   */
  public function statusLabel(): string;

  /**
   * TRUE si cambia datos y el usuario debe confirmarla antes de ejecutarse.
   */
  public function requiresConfirmation(): bool;

  /**
   * Ejecuta la herramienta. Debe validar sus argumentos: vienen del modelo.
   *
   * @param array<string, mixed> $input
   *   Argumentos según inputSchema().
   *
   * @throws \InvalidArgumentException
   *   Si los argumentos no son válidos.
   */
  public function execute(array $input): ToolResult;

}
