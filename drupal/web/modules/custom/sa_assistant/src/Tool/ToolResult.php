<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

/**
 * Resultado de una herramienta: datos para el modelo y fuentes para el usuario.
 */
final class ToolResult {

  /**
   * Crea el resultado.
   *
   * @param array<string, mixed> $data
   *   Lo que lee el modelo para redactar la respuesta.
   * @param list<array{titulo: string, url: string, tipo: string}> $sources
   *   Páginas del portal de donde sale la información; el widget las enlaza.
   */
  public function __construct(
    public readonly array $data,
    public readonly array $sources = [],
  ) {}

}
