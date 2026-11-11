<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit\Double;

use Drupal\sa_assistant\Agent\PendingActionStoreInterface;

/**
 * Acciones pendientes en memoria.
 */
final class MemoryPendingActions implements PendingActionStoreInterface {

  /**
   * Acciones indexadas por token.
   *
   * @var array<string, array{tool: string, input: array<string, mixed>}>
   */
  private array $actions = [];

  /**
   * {@inheritdoc}
   */
  public function create(string $tool, array $input): string {
    $token = 'token-' . count($this->actions);
    $this->actions[$token] = ['tool' => $tool, 'input' => $input];
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function take(string $token): ?array {
    $action = $this->actions[$token] ?? NULL;
    unset($this->actions[$token]);
    return $action;
  }

}
