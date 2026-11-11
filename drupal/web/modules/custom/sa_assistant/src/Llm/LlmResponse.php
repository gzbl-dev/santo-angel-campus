<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Llm;

/**
 * Respuesta de un turno del modelo: bloques de texto y llamadas a herramientas.
 */
final class LlmResponse {

  /**
   * Crea la respuesta a partir de sus bloques.
   *
   * @param list<array<string, mixed>> $content
   *   Bloques `text` ({type, text}) y `tool_use` ({type, id, name, input}).
   */
  public function __construct(
    public readonly array $content,
  ) {}

  /**
   * Respuesta de solo texto.
   */
  public static function text(string $text): self {
    return new self([['type' => 'text', 'text' => $text]]);
  }

  /**
   * Llamadas a herramientas que pide el modelo.
   *
   * @return list<array{type: string, id: string, name: string, input: array<string, mixed>}>
   *   Bloques `tool_use`.
   */
  public function toolUses(): array {
    return array_values(array_filter(
      $this->content,
      static fn (array $block): bool => $block['type'] === 'tool_use',
    ));
  }

}
