<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit\Double;

use Drupal\sa_assistant\Tool\AssistantToolInterface;
use Drupal\sa_assistant\Tool\ToolResult;

/**
 * Herramienta que devuelve un resultado fijo (o lanza una excepción).
 */
final class FakeTool implements AssistantToolInterface {

  /**
   * Argumentos de cada ejecución.
   *
   * @var list<array<string, mixed>>
   */
  public array $calls = [];

  public function __construct(
    private readonly string $toolName,
    private readonly ToolResult|\Throwable $result,
    private readonly bool $confirm = FALSE,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return $this->toolName;
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function inputSchema(): array {
    return ['type' => 'object'];
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(): string {
    return 'Trabajando…';
  }

  /**
   * {@inheritdoc}
   */
  public function requiresConfirmation(): bool {
    return $this->confirm;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): ToolResult {
    $this->calls[] = $input;
    if ($this->result instanceof \Throwable) {
      throw $this->result;
    }
    return $this->result;
  }

}
