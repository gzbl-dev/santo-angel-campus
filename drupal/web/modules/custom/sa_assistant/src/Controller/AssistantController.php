<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\sa_assistant\Agent\AssistantAgent;
use Drupal\sa_assistant\Agent\PendingActionStoreInterface;
use Drupal\sa_assistant\Tool\ToolRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints del asistente: conversación (en streaming) y confirmación.
 */
final class AssistantController extends ControllerBase {

  public const MESSAGE_MAX = 500;

  public const HISTORY_MAX = 10;

  /**
   * Incidencias que puede confirmar una misma IP por hora.
   */
  private const CONFIRM_LIMIT = 5;

  public function __construct(
    private readonly AssistantAgent $agent,
    private readonly ToolRegistry $tools,
    private readonly PendingActionStoreInterface $pending,
    private readonly FloodInterface $flood,
    #[Autowire(service: 'logger.channel.sa_assistant')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * POST /api/asistente: responde en NDJSON, un evento JSON por línea.
   *
   * Cuerpo: {"message": "...", "history": [{"role": "user", "text": "..."}]}.
   */
  public function message(Request $request): Response {
    $config = $this->config('sa_assistant.settings');
    if (!$config->get('enabled')) {
      return $this->error('El asistente está desactivado.', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    // Límite por IP con la Flood API de Drupal: protege la demo (y, con un
    // modelo real, la factura) frente a bucles o abusos.
    $limit = (int) $config->get('rate_limit');
    if (!$this->flood->isAllowed('sa_assistant.message', $limit, 3600)) {
      return $this->error('Has enviado muchas preguntas seguidas. Vuelve a intentarlo dentro de un rato.', Response::HTTP_TOO_MANY_REQUESTS);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      return $this->error('La petición no es un JSON válido (UTF-8).', Response::HTTP_BAD_REQUEST);
    }
    $message = trim((string) ($body['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > self::MESSAGE_MAX) {
      return $this->error(sprintf('El mensaje debe tener entre 1 y %d caracteres.', self::MESSAGE_MAX), Response::HTTP_BAD_REQUEST);
    }
    $this->flood->register('sa_assistant.message', 3600);

    $history = self::history($body['history'] ?? []);
    $delay = max(0, (int) $config->get('typing_delay_ms')) * 1000;

    $response = new StreamedResponse(function () use ($history, $message, $delay): void {
      try {
        foreach ($this->agent->run($history, $message) as $event) {
          if ($event['type'] === 'text') {
            // El texto sale por trozos para que el widget lo pinte según llega,
            // igual que haría un modelo real en streaming.
            foreach (preg_split('/(?<=\s)/u', $event['text']) as $chunk) {
              self::emit(['type' => 'delta', 'text' => $chunk]);
              usleep($delay);
            }
            continue;
          }
          self::emit($event);
        }
      }
      catch (\Throwable $e) {
        $this->logger->error('Error del asistente: @message', ['@message' => $e->getMessage()]);
        self::emit(['type' => 'error', 'message' => 'Ha ocurrido un error inesperado. Inténtalo de nuevo.']);
      }
    });

    $response->headers->set('Content-Type', 'application/x-ndjson; charset=utf-8');
    $response->headers->set('Cache-Control', 'no-store');
    // Evita que un proxy (nginx) acumule la respuesta y rompa el streaming.
    $response->headers->set('X-Accel-Buffering', 'no');
    return $response;
  }

  /**
   * POST /api/asistente/confirmar: ejecuta la acción que el usuario aprobó.
   *
   * Cuerpo: {"token": "..."}.
   */
  public function confirm(Request $request): JsonResponse {
    if (!$this->flood->isAllowed('sa_assistant.confirm', self::CONFIRM_LIMIT, 3600)) {
      return $this->error('Has registrado demasiadas incidencias seguidas. Inténtalo más tarde.', Response::HTTP_TOO_MANY_REQUESTS);
    }

    $body = json_decode($request->getContent(), TRUE);
    $action = $this->pending->take(is_array($body) ? (string) ($body['token'] ?? '') : '');
    $tool = $action ? $this->tools->get($action['tool']) : NULL;
    if ($tool === NULL) {
      return $this->error('La propuesta ha caducado o ya se usó. Vuelve a pedírsela al asistente.', Response::HTTP_GONE);
    }

    try {
      $result = $tool->execute($action['input']);
    }
    catch (\InvalidArgumentException $e) {
      return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    $this->flood->register('sa_assistant.confirm', 3600);
    $this->logger->notice('Acción confirmada desde el asistente: @tool', ['@tool' => $tool->name()]);

    return new JsonResponse(['message' => $result->data['mensaje'] ?? 'Hecho.']);
  }

  /**
   * Historial saneado: solo texto, roles válidos y los últimos turnos.
   *
   * @return list<array{role: 'user'|'assistant', text: string}>
   *   Turnos que empiezan por un mensaje del usuario.
   */
  private static function history(mixed $raw): array {
    if (!is_array($raw)) {
      return [];
    }
    $turns = [];
    foreach (array_slice(array_values($raw), -self::HISTORY_MAX) as $turn) {
      $role = $turn['role'] ?? NULL;
      $text = trim((string) ($turn['text'] ?? ''));
      if (in_array($role, ['user', 'assistant'], TRUE) && $text !== '') {
        $turns[] = ['role' => $role, 'text' => mb_substr($text, 0, 4000)];
      }
    }
    // La conversación tiene que empezar por el usuario.
    while ($turns !== [] && $turns[0]['role'] !== 'user') {
      array_shift($turns);
    }
    return $turns;
  }

  /**
   * Escribe un evento NDJSON y lo envía ya al navegador.
   *
   * @param array<string, mixed> $event
   *   Evento.
   */
  private static function emit(array $event): void {
    echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    if (ob_get_level() > 0) {
      ob_flush();
    }
    flush();
  }

  /**
   * Respuesta de error en JSON.
   */
  private function error(string $message, int $status): JsonResponse {
    return new JsonResponse(['error' => $message], $status);
  }

}
