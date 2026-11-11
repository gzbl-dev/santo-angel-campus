<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit;

use Drupal\sa_assistant\Llm\LlmResponse;
use Drupal\sa_assistant\Llm\SimulatedLlmClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Decisiones y respuestas del proveedor simulado.
 */
#[CoversClass(SimulatedLlmClient::class)]
final class SimulatedLlmClientTest extends TestCase {

  private const TOOLS = [
    ['name' => 'buscar_en_portal', 'description' => '', 'input_schema' => []],
    ['name' => 'buscar_titulaciones', 'description' => '', 'input_schema' => []],
    ['name' => 'proximos_eventos', 'description' => '', 'input_schema' => []],
    ['name' => 'abrir_incidencia', 'description' => '', 'input_schema' => []],
  ];

  /**
   * Pregunta → herramienta y argumentos que elige el modelo simulado.
   */
  public static function routingCases(): array {
    $wifi = 'Quiero abrir una incidencia: no puedo entrar en el campus virtual';
    $aula = 'Abre una incidencia: el proyector del aula 2.04 no enciende. Lo he probado dos veces.';

    return [
      'másteres online' => [
        '¿Qué másteres hay online?',
        'buscar_titulaciones',
        ['tipo' => 'master', 'modalidad' => 'online'],
      ],
      'rama' => [
        'Quiero estudiar algo de informática',
        'buscar_titulaciones',
        ['rama' => 'ingenieria'],
      ],
      'semipresencial no es presencial' => [
        '¿Qué grados semipresenciales tenéis?',
        'buscar_titulaciones',
        ['tipo' => 'grado', 'modalidad' => 'semipresencial'],
      ],
      'FAQ aunque nombre un estudio' => [
        '¿Hay becas para el máster?',
        'buscar_en_portal',
        ['consulta' => '¿Hay becas para el máster?'],
      ],
      'duda general' => [
        '¿Cuándo es el plazo de matrícula?',
        'buscar_en_portal',
        ['consulta' => '¿Cuándo es el plazo de matrícula?'],
      ],
      'agenda' => [
        '¿Qué eventos hay esta semana?',
        'proximos_eventos',
        ['limite' => 5],
      ],
      'agenda por categoría' => [
        '¿Hay algún evento deportivo?',
        'proximos_eventos',
        ['limite' => 5, 'categoria' => 'deportivo'],
      ],
      'incidencia con detalle' => [
        $wifi,
        'abrir_incidencia',
        ['asunto' => 'No puedo entrar en el campus virtual', 'descripcion' => $wifi],
      ],
      'el asunto no se corta en un número con punto' => [
        $aula,
        'abrir_incidencia',
        ['asunto' => 'El proyector del aula 2.04 no enciende', 'descripcion' => $aula],
      ],
    ];
  }

  /**
   * Elige la herramienta y los argumentos adecuados para cada pregunta.
   */
  #[DataProvider('routingCases')]
  public function testChoosesTheRightTool(string $question, string $tool, array $input): void {
    $uses = $this->ask($question)->toolUses();

    $this->assertCount(1, $uses);
    $this->assertSame($tool, $uses[0]['name']);
    $this->assertSame($input, $uses[0]['input']);
  }

  /**
   * Sin detalles pide qué ocurre; con la respuesta, prepara la incidencia.
   */
  public function testAsksForDetailsAndThenOpensTheIncidencia(): void {
    $client = new SimulatedLlmClient();
    $first = $client->complete('', [self::user('Quiero abrir una incidencia')], self::TOOLS);
    $this->assertSame([], $first->toolUses());
    $this->assertStringContainsString(SimulatedLlmClient::ASK_INCIDENCIA, $first->content[0]['text']);

    $second = $client->complete('', [
      self::user('Quiero abrir una incidencia'),
      ['role' => 'assistant', 'content' => $first->content],
      self::user('La impresora de la biblioteca no funciona'),
    ], self::TOOLS);
    $this->assertSame('abrir_incidencia', $second->toolUses()[0]['name']);
    $this->assertSame('La impresora de la biblioteca no funciona', $second->toolUses()[0]['input']['asunto']);
  }

  /**
   * Un saludo se contesta sin herramientas.
   */
  public function testGreetingsAreAnsweredWithoutTools(): void {
    $this->assertSame([], $this->ask('Hola')->toolUses());
  }

  /**
   * La respuesta sale solo de los datos de la herramienta.
   */
  public function testComposesAnAnswerOnlyFromToolResults(): void {
    $answer = $this->afterTool('buscar_titulaciones', [
      'total' => 1,
      'filtros' => ['tipo' => 'master', 'modalidad' => 'online'],
      'titulaciones' => [['titulo' => 'Máster en IA', 'modalidad' => 'Online', 'creditos' => 60]],
    ]);
    $this->assertSame("Tenemos 1 máster online:\n- Máster en IA · Online · 60 créditos", $answer);
  }

  /**
   * Si el RAG no encuentra nada relevante, lo reconoce en vez de inventar.
   */
  public function testAdmitsWhenTheSearchFindsNothingRelevant(): void {
    $answer = $this->afterTool('buscar_en_portal', ['resultados' => []]);
    $this->assertStringStartsWith('No he encontrado información', $answer);
  }

  /**
   * Primer turno del modelo ante una pregunta.
   */
  private function ask(string $question): LlmResponse {
    return (new SimulatedLlmClient())->complete('', [self::user($question)], self::TOOLS);
  }

  /**
   * Texto que redacta el modelo tras recibir el resultado de una herramienta.
   */
  private function afterTool(string $tool, array $data): string {
    $response = (new SimulatedLlmClient())->complete('', [
      self::user('pregunta'),
      [
        'role' => 'assistant',
        'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => $tool, 'input' => []]],
      ],
      [
        'role' => 'user',
        'content' => [['type' => 'tool_result', 'tool_use_id' => 't1', 'content' => json_encode($data)]],
      ],
    ], self::TOOLS);
    return $response->content[0]['text'];
  }

  /**
   * Mensaje de texto del usuario.
   */
  private static function user(string $text): array {
    return ['role' => 'user', 'content' => [['type' => 'text', 'text' => $text]]];
  }

}
