<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Llm;

use Drupal\sa_assistant\Search\Tokenizer;

/**
 * Proveedor simulado para la demo: hace de «modelo» sin llamar a ninguna API.
 *
 * Respeta el mismo contrato que un modelo real con tool use:
 * 1. Ante un mensaje del usuario decide, con reglas, qué herramienta usar y
 *    con qué argumentos (lo que un LLM haría leyendo las descripciones).
 * 2. Ante los resultados de las herramientas redacta la respuesta a partir
 *    de ellos, sin inventar nada que no venga en los datos.
 *
 * Las herramientas, el RAG y el bucle del agente son reales; lo único
 * simulado es el razonamiento en lenguaje natural.
 */
final class SimulatedLlmClient implements LlmClientInterface {

  /**
   * Frase con la que el asistente pide los datos de una incidencia.
   *
   * Si el último mensaje del asistente la contiene, el siguiente mensaje
   * del usuario es la descripción del problema.
   */
  public const ASK_INCIDENCIA = 'Cuéntame en una frase qué ocurre';

  private const ERROR = 'No he podido completar la consulta ahora mismo. Inténtalo de nuevo en un momento.';

  /*
   * Los patrones se aplican al texto normalizado (minúsculas, sin tildes).
   */

  private const GREETING = '/^(hola|buenas|buenos dias|buenas tardes|buenas noches|hey|saludos)\W*$/';

  private const THANKS = '/^(muchas |mil )?gracias\b/';

  private const HELP = '/\b(que puedes hacer|en que me puedes ayudar|como funcionas|ayuda)\b/';

  private const INCIDENCIA = '/\b(incidencias?|averias?|reportar|queja|quejas|reclamacion\w*|ticket|abre una|abrir una)\b/';

  private const PROBLEM = '/\b(no puedo|no funciona|no me deja|no carga|no va|error|errores|problema|problemas)\b/';

  private const EVENTOS = '/\b(eventos?|agenda|actividades|jornadas?|talleres?|charlas?|conferencias?|puertas abiertas|hackathon|concierto)\b/';

  private const LISTING = '/\b(que|cuales|hay|ofreceis|ofrecen|ofreces|teneis|tienen|lista\w*|estudiar|estudio|titulacion\w*|carreras?|opciones|oferta|busco|interesa\w*)\b/';

  /**
   * Temas que son de preguntas frecuentes aunque mencionen un tipo de estudio.
   */
  private const FAQ_TOPIC = '/\b(matricul\w*|becas?|precios?|cuesta|cuestan|plazos?|convalid\w*|pagar|pago|horarios?|requisitos?|acceso|nota)\b/';

  private const TIPOS = [
    'fp' => '/\b(fp|ciclos?|formacion profesional|grado superior|cfgs)\b/',
    'master' => '/\b(master|masteres|posgrados?)\b/',
    'grado' => '/\b(grados?|carreras?)\b/',
  ];

  private const MODALIDADES = [
    'semipresencial' => '/\bsemipresencial(es)?\b/',
    'online' => '/\b(online|on line|a distancia)\b/',
    'presencial' => '/\bpresencial(es)?\b/',
  ];

  private const RAMAS = [
    'salud' => '/\b(salud|medicina|enfermeria|sanitari\w*|fisioterapia|psicologia)\b/',
    'ingenieria' => '/\b(ingenieria\w*|informatica|tecnologia\w*|programacion|software|inteligencia artificial)\b/',
    'sociales' => '/\b(sociales|derecho|empresas?|administracion|economia|periodismo|profesorado|educacion)\b/',
    'artes' => '/\b(artes?|humanidades|diseno|videojuegos)\b/',
    'deporte' => '/\b(deportes?|deportiv\w*|actividad fisica|esports)\b/',
  ];

  private const RAMA_LABELS = [
    'salud' => 'Ciencias de la Salud',
    'ingenieria' => 'Ingeniería y Tecnología',
    'sociales' => 'Ciencias Sociales y Jurídicas',
    'artes' => 'Artes y Humanidades',
    'deporte' => 'Deporte',
  ];

  private const CATEGORIAS_EVENTO = [
    'deportivo' => ['/\b(deport\w*|esports|senderismo)\b/', 'deportivos'],
    'cultural' => ['/\b(cultura\w*|concierto\w*|teatro|musica)\b/', 'culturales'],
    'academico' => ['/\b(academic\w*|talleres?|formacion)\b/', 'académicos'],
    'institucional' => ['/\b(institucional\w*|puertas abiertas)\b/', 'institucionales'],
  ];

  /**
   * Singular y plural de cada tipo de estudio.
   */
  private const NOUNS = [
    'grado' => ['grado', 'grados'],
    'master' => ['máster', 'másteres'],
    'fp' => ['ciclo formativo', 'ciclos formativos'],
    '' => ['titulación', 'titulaciones'],
  ];

  private const MODALIDAD_WORDS = [
    'presencial' => ['presencial', 'presenciales'],
    'semipresencial' => ['semipresencial', 'semipresenciales'],
    'online' => ['online', 'online'],
  ];

  /**
   * {@inheritdoc}
   */
  public function complete(string $system, array $messages, array $tools): LlmResponse {
    $last = end($messages);
    $available = array_column($tools, 'name');

    if ($last && ($last['content'][0]['type'] ?? '') === 'tool_result') {
      return $this->compose($messages);
    }
    return $this->decide($messages, $available);
  }

  /**
   * {@inheritdoc}
   */
  public function isSimulated(): bool {
    return TRUE;
  }

  /**
   * Primer paso: contestar directamente o pedir una herramienta.
   *
   * @param list<array{role: string, content: list<array<string, mixed>>}> $messages
   *   Conversación; el último mensaje es del usuario.
   * @param list<string> $available
   *   Nombres de las herramientas disponibles.
   */
  private function decide(array $messages, array $available): LlmResponse {
    $original = trim(self::lastText($messages, 'user'));
    $text = Tokenizer::normalize($original);
    $call = fn (string $name, array $input): LlmResponse => self::toolCall($name, $input, $available);

    // Respuesta a «Cuéntame en una frase qué ocurre»: es la incidencia.
    if (str_contains(self::lastText($messages, 'assistant'), self::ASK_INCIDENCIA) && mb_strlen($original) >= 6) {
      return $call('abrir_incidencia', self::incidencia($original));
    }

    if (preg_match(self::GREETING, $text)) {
      return LlmResponse::text('¡Hola! Pregúntame por titulaciones, plazos de matrícula, becas, servicios del campus o próximos eventos.');
    }
    if (preg_match(self::THANKS, $text) && str_word_count($text) <= 4) {
      return LlmResponse::text('¡De nada! Si necesitas algo más, aquí estoy.');
    }
    if (preg_match(self::HELP, $text)) {
      return LlmResponse::text("Puedo ayudarte con:\n- Buscar titulaciones por tipo, rama o modalidad.\n- Responder dudas de matrícula, becas, gestión académica y servicios.\n- Contarte los próximos eventos del campus.\n- Abrir una incidencia para que te atienda el equipo de la universidad.");
    }

    if (preg_match(self::INCIDENCIA, $text)) {
      $draft = self::incidencia($original);
      return $draft['asunto'] === ''
        ? LlmResponse::text('Claro. ' . self::ASK_INCIDENCIA . ' (por ejemplo: «No puedo entrar en el campus virtual») y preparo la incidencia.')
        : $call('abrir_incidencia', $draft);
    }

    if (preg_match(self::EVENTOS, $text)) {
      $input = ['limite' => 5];
      foreach (self::CATEGORIAS_EVENTO as $categoria => [$pattern]) {
        if (preg_match($pattern, $text)) {
          $input['categoria'] = $categoria;
          break;
        }
      }
      return $call('proximos_eventos', $input);
    }

    $filters = self::filters($text);
    $asksForList = preg_match(self::LISTING, $text) && !preg_match(self::FAQ_TOPIC, $text);
    if ($asksForList && ($filters !== [] || preg_match('/\b(titulacion\w*|carreras?|estudios)\b/', $text))) {
      return $call('buscar_titulaciones', $filters);
    }

    return $call('buscar_en_portal', ['consulta' => $original]);
  }

  /**
   * Respuesta que pide una herramienta (si está disponible).
   *
   * @param string $name
   *   Nombre de la herramienta.
   * @param array<string, mixed> $input
   *   Argumentos.
   * @param list<string> $available
   *   Herramientas que ofrece el agente.
   */
  private static function toolCall(string $name, array $input, array $available): LlmResponse {
    if (!in_array($name, $available, TRUE)) {
      return LlmResponse::text('Ahora mismo no puedo consultar eso.');
    }
    return new LlmResponse([
      [
        'type' => 'tool_use',
        'id' => 'toolu_sim_' . bin2hex(random_bytes(6)),
        'name' => $name,
        'input' => $input,
      ],
    ]);
  }

  /**
   * Segundo paso: redactar la respuesta con lo que dieron las herramientas.
   *
   * @param list<array{role: string, content: list<array<string, mixed>>}> $messages
   *   Conversación; el último mensaje lleva los bloques `tool_result`.
   */
  private function compose(array $messages): LlmResponse {
    $names = [];
    foreach ($messages[count($messages) - 2]['content'] as $block) {
      if ($block['type'] === 'tool_use') {
        $names[$block['id']] = $block['name'];
      }
    }
    $question = Tokenizer::normalize(self::lastText($messages, 'user'));

    $parts = [];
    foreach (end($messages)['content'] as $result) {
      $data = json_decode((string) $result['content'], TRUE);
      if (!empty($result['is_error']) || !is_array($data)) {
        $parts[] = self::ERROR;
        continue;
      }
      $parts[] = match ($names[$result['tool_use_id']] ?? '') {
        'buscar_en_portal' => $this->answerPortal($data, (bool) preg_match(self::PROBLEM, $question)),
        'buscar_titulaciones' => $this->answerTitulaciones($data),
        'proximos_eventos' => $this->answerEventos($data),
        'abrir_incidencia' => 'He preparado la incidencia con estos datos. Revísala y pulsa «Enviar incidencia» para registrarla.',
        default => self::ERROR,
      };
    }
    return LlmResponse::text(implode("\n\n", $parts));
  }

  /**
   * Respuesta a partir de los fragmentos que encontró el RAG.
   *
   * @param array{resultados?: list<array{titulo: string, tipo: string, extracto: string, puntuacion: float}>} $data
   *   Resultado de `buscar_en_portal` (ya filtrado por relevancia).
   * @param bool $isProblem
   *   TRUE si el usuario describía un problema: se le ofrece una incidencia.
   */
  private function answerPortal(array $data, bool $isProblem): string {
    $results = $data['resultados'] ?? [];
    $best = $results[0] ?? NULL;

    if (!$best) {
      return 'No he encontrado información sobre eso en el portal. Prueba a preguntarlo de otra forma o, si es un problema concreto, dime «abre una incidencia» y la preparo para que te responda el equipo de la universidad.';
    }

    $answer = $best['tipo'] === 'Pregunta frecuente'
      ? "Según nuestras preguntas frecuentes («{$best['titulo']}»):\n\n{$best['extracto']}"
      : "{$best['titulo']}: {$best['extracto']}";

    $second = $results[1] ?? NULL;
    if ($second && $second['puntuacion'] >= 0.6 * $best['puntuacion']) {
      $answer .= "\n\nTambién puede interesarte: «{$second['titulo']}».";
    }
    if ($isProblem) {
      $answer .= "\n\nSi con esto no se resuelve, dime «abre una incidencia» y la preparo.";
    }
    return $answer;
  }

  /**
   * Lista de titulaciones que cumplen los filtros.
   *
   * @param array{total?: int, filtros?: array<string, string>, titulaciones?: list<array<string, mixed>>} $data
   *   Resultado de `buscar_titulaciones`.
   */
  private function answerTitulaciones(array $data): string {
    $total = (int) ($data['total'] ?? 0);
    $filters = $data['filtros'] ?? [];
    $form = $total === 1 ? 0 : 1;

    $phrase = self::NOUNS[$filters['tipo'] ?? ''][$form];
    if (isset($filters['modalidad'])) {
      $phrase .= ' ' . self::MODALIDAD_WORDS[$filters['modalidad']][$form];
    }
    if (isset($filters['rama'])) {
      $phrase .= ' de ' . self::RAMA_LABELS[$filters['rama']];
    }

    if ($total === 0) {
      return "Ahora mismo no tenemos {$phrase}. Puedes ver toda la oferta en la página de titulaciones.";
    }

    $lines = array_map(
      static fn (array $t): string => "- {$t['titulo']} · {$t['modalidad']} · {$t['creditos']} créditos",
      $data['titulaciones'],
    );
    return "Tenemos {$total} {$phrase}:\n" . implode("\n", $lines);
  }

  /**
   * Agenda de próximos eventos.
   *
   * @param array{categoria?: string, eventos?: list<array{titulo: string, fecha: string, lugar: string}>} $data
   *   Resultado de `proximos_eventos`.
   */
  private function answerEventos(array $data): string {
    $adjective = isset($data['categoria']) ? ' ' . self::CATEGORIAS_EVENTO[$data['categoria']][1] : '';
    $events = $data['eventos'] ?? [];

    if ($events === []) {
      return "Ahora mismo no hay eventos{$adjective} programados.";
    }

    $lines = array_map(
      static fn (array $e): string => "- {$e['titulo']}: {$e['fecha']}, {$e['lugar']}",
      $events,
    );
    return "Estos son los próximos eventos{$adjective}:\n" . implode("\n", $lines);
  }

  /**
   * Filtros de titulación que se mencionan en el texto.
   *
   * @return array<string, string>
   *   Claves `tipo`, `modalidad` y `rama` que se hayan detectado.
   */
  private static function filters(string $text): array {
    $filters = [];
    foreach (['tipo' => self::TIPOS, 'modalidad' => self::MODALIDADES, 'rama' => self::RAMAS] as $field => $patterns) {
      foreach ($patterns as $value => $pattern) {
        if (preg_match($pattern, $text)) {
          $filters[$field] = $value;
          break;
        }
      }
    }
    return $filters;
  }

  /**
   * Borrador de incidencia: asunto corto y la descripción completa.
   *
   * @return array{asunto: string, descripcion: string}
   *   El asunto queda vacío si el mensaje no describe ningún problema.
   */
  private static function incidencia(string $message): array {
    $subject = preg_replace(
      '/^(hola\W*)?((quiero|quisiera|me gustaria|me gustaría|necesito|puedes|podrías|podrias)\s+)?((abrir|abre|crear|poner|reportar|registrar)\s+)?((una|un)\s+)?(incidencias?|avería|averia|queja|reclamación|reclamacion|ticket)?\W*((de|sobre|porque|con|por)\s+)?/iu',
      '',
      $message,
    );
    // Primera frase: un punto solo cierra frase si le sigue un espacio o el
    // final («aula 2.04» no se corta).
    $subject = trim((string) preg_split('/[.!?](?=\s|$)|\n/u', (string) $subject)[0]);

    if (mb_strlen($subject) < 6) {
      return ['asunto' => '', 'descripcion' => $message];
    }
    if (mb_strlen($subject) > 90) {
      $subject = rtrim(mb_substr($subject, 0, 87)) . '…';
    }
    return [
      'asunto' => mb_strtoupper(mb_substr($subject, 0, 1)) . mb_substr($subject, 1),
      'descripcion' => $message,
    ];
  }

  /**
   * Texto del último mensaje de un rol (ignora los bloques que no son texto).
   *
   * @param list<array{role: string, content: list<array<string, mixed>>}> $messages
   *   Conversación.
   * @param string $role
   *   Rol del mensaje: `user` o `assistant`.
   */
  private static function lastText(array $messages, string $role): string {
    foreach (array_reverse($messages) as $message) {
      if ($message['role'] !== $role) {
        continue;
      }
      foreach ($message['content'] as $block) {
        if ($block['type'] === 'text') {
          return $block['text'];
        }
      }
    }
    return '';
  }

}
