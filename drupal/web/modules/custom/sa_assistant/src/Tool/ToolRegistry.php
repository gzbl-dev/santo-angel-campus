<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Tool;

/**
 * Las herramientas disponibles, indexadas por nombre.
 */
final class ToolRegistry {

  /**
   * Herramientas indexadas por nombre.
   *
   * @var array<string, \Drupal\sa_assistant\Tool\AssistantToolInterface>
   */
  private array $tools = [];

  /**
   * Recibe las herramientas del contenedor de servicios.
   *
   * @param iterable<\Drupal\sa_assistant\Tool\AssistantToolInterface> $tools
   *   Servicios con la etiqueta `sa_assistant.tool`.
   */
  public function __construct(iterable $tools) {
    foreach ($tools as $tool) {
      $this->tools[$tool->name()] = $tool;
    }
  }

  /**
   * La herramienta con ese nombre, o NULL si el modelo pidió una que no existe.
   */
  public function get(string $name): ?AssistantToolInterface {
    return $this->tools[$name] ?? NULL;
  }

  /**
   * Definiciones en el formato de herramientas de la Messages API.
   *
   * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
   *   Una entrada por herramienta.
   */
  public function definitions(): array {
    return array_values(array_map(static fn (AssistantToolInterface $tool): array => [
      'name' => $tool->name(),
      'description' => $tool->description(),
      'input_schema' => $tool->inputSchema(),
    ], $this->tools));
  }

}
