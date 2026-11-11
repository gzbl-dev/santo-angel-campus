<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit;

use Drupal\sa_assistant\Agent\AssistantAgent;
use Drupal\sa_assistant\Llm\LlmResponse;
use Drupal\sa_assistant\Tool\ToolRegistry;
use Drupal\sa_assistant\Tool\ToolResult;
use Drupal\Tests\sa_assistant\Unit\Double\FakeTool;
use Drupal\Tests\sa_assistant\Unit\Double\MemoryPendingActions;
use Drupal\Tests\sa_assistant\Unit\Double\ScriptedLlm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El bucle del agente con un modelo guionizado y herramientas falsas.
 */
#[CoversClass(AssistantAgent::class)]
#[CoversClass(ToolRegistry::class)]
final class AssistantAgentTest extends TestCase {

  /**
   * Ejecuta las herramientas y devuelve sus resultados al modelo.
   */
  public function testRunsToolsAndSendsTheResultsBackToTheModel(): void {
    $llm = new ScriptedLlm([
      self::toolUse('t1', 'buscar', ['q' => 'becas']),
      LlmResponse::text('Hay becas de excelencia.'),
    ]);
    $sources = [['titulo' => 'Becas', 'url' => '/becas', 'tipo' => 'FAQ']];
    $tool = new FakeTool('buscar', new ToolResult(['ok' => TRUE], $sources));

    $events = $this->runAgent($llm, [$tool]);

    $this->assertSame(['tool', 'sources', 'text', 'done'], array_column($events, 'type'));
    $this->assertSame([['q' => 'becas']], $tool->calls);
    // La segunda llamada al modelo lleva el tool_result con el id correcto.
    $result = end($llm->received)[2]['content'][0];
    $this->assertSame([
      'type' => 'tool_result',
      'tool_use_id' => 't1',
      'content' => '{"ok":true}',
    ], $result);
  }

  /**
   * Las herramientas que escriben datos esperan a la confirmación.
   */
  public function testToolsThatWriteDataWaitForConfirmation(): void {
    $llm = new ScriptedLlm([
      self::toolUse('t1', 'escribir', ['asunto' => 'Wifi']),
      LlmResponse::text('Revisa la incidencia.'),
    ]);
    $tool = new FakeTool('escribir', new ToolResult([]), confirm: TRUE);
    $pending = new MemoryPendingActions();

    $events = $this->runAgent($llm, [$tool], $pending);

    $this->assertSame([], $tool->calls, 'No se ejecuta sin confirmación.');
    $confirm = $events[1];
    $this->assertSame('confirm', $confirm['type']);
    $this->assertSame(['tool' => 'escribir', 'input' => ['asunto' => 'Wifi']], $pending->take($confirm['token']));
  }

  /**
   * Una herramienta inexistente o con argumentos malos llega como error.
   */
  public function testUnknownToolsAndInvalidInputReachTheModelAsErrors(): void {
    $llm = new ScriptedLlm([
      new LlmResponse([
        ['type' => 'tool_use', 'id' => 't1', 'name' => 'no_existe', 'input' => []],
        ['type' => 'tool_use', 'id' => 't2', 'name' => 'falla', 'input' => []],
      ]),
      LlmResponse::text('Lo siento.'),
    ]);
    $tool = new FakeTool('falla', new \InvalidArgumentException('Falta la consulta.'));

    $this->runAgent($llm, [$tool]);

    $results = end($llm->received)[2]['content'];
    $this->assertCount(2, $results, 'Los dos resultados van en el mismo mensaje.');
    $this->assertTrue($results[0]['is_error']);
    $this->assertSame('{"error":"Falta la consulta."}', $results[1]['content']);
  }

  /**
   * Un modelo que nunca deja de pedir herramientas no bloquea el agente.
   */
  public function testStopsAfterMaxSteps(): void {
    $llm = new ScriptedLlm(array_fill(0, 10, self::toolUse('t', 'buscar', [])));

    $events = $this->runAgent($llm, [new FakeTool('buscar', new ToolResult([]))]);

    $this->assertCount(AssistantAgent::MAX_STEPS, $llm->received);
    $this->assertSame('done', end($events)['type']);
  }

  /**
   * Ejecuta el agente y devuelve todos sus eventos.
   */
  private function runAgent(ScriptedLlm $llm, array $tools, ?MemoryPendingActions $pending = NULL): array {
    $agent = new AssistantAgent($llm, new ToolRegistry($tools), $pending ?? new MemoryPendingActions());
    return iterator_to_array($agent->run([], 'pregunta'), FALSE);
  }

  /**
   * Respuesta del modelo que pide una herramienta.
   */
  private static function toolUse(string $id, string $name, array $input): LlmResponse {
    return new LlmResponse([['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]]);
  }

}
