<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit\Double;

use Drupal\sa_assistant\Llm\LlmClientInterface;
use Drupal\sa_assistant\Llm\LlmResponse;

/**
 * Modelo que devuelve respuestas preparadas y guarda lo que recibe.
 */
final class ScriptedLlm implements LlmClientInterface {

  /**
   * Cada llamada: [system, tools, último mensaje].
   *
   * @var list<array{0: string, 1: array, 2: array}>
   */
  public array $received = [];

  /**
   * Crea el modelo con sus respuestas, en orden.
   *
   * @param list<\Drupal\sa_assistant\Llm\LlmResponse> $script
   *   Una respuesta por llamada.
   */
  public function __construct(private array $script) {}

  /**
   * {@inheritdoc}
   */
  public function complete(string $system, array $messages, array $tools): LlmResponse {
    $this->received[] = [$system, $tools, end($messages)];
    return array_shift($this->script);
  }

  /**
   * {@inheritdoc}
   */
  public function isSimulated(): bool {
    return TRUE;
  }

}
