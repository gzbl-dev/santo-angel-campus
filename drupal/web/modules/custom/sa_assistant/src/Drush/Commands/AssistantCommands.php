<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Drush\Commands;

use Drupal\sa_assistant\Agent\AssistantAgent;
use Drupal\sa_assistant\Search\PortalIndex;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Comandos Drush del asistente.
 */
final class AssistantCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly PortalIndex $index,
    private readonly AssistantAgent $agent,
  ) {
    parent::__construct();
  }

  /**
   * Reconstruye el índice BM25 y muestra sus cifras.
   */
  #[CLI\Command(name: 'sa:index')]
  #[CLI\Usage(name: 'drush sa:index', description: 'Reindexa FAQ, titulaciones y eventos publicados.')]
  public function index(): void {
    $stats = $this->index->rebuild();
    $this->io()->success(sprintf('%d documentos y %d términos distintos.', $stats['documentos'], $stats['terminos']));
    foreach ($stats['por_tipo'] as $type => $count) {
      $this->io()->writeln(sprintf('  %s: %d', $type, $count));
    }
  }

  /**
   * Hace una pregunta al asistente y muestra cada paso del agente.
   */
  #[CLI\Command(name: 'sa:ask')]
  #[CLI\Argument(name: 'question', description: 'La pregunta, entre comillas.')]
  #[CLI\Usage(name: 'drush sa:ask "¿Qué másteres hay online?"', description: 'Muestra herramientas, fuentes y respuesta.')]
  public function askAssistant(string $question): void {
    $text = '';
    foreach ($this->agent->run([], $question) as $event) {
      match ($event['type']) {
        'tool' => $this->io()->writeln("<comment>→ {$event['name']}</comment>"),
        'sources' => $this->io()->writeln('<comment>  fuentes: ' . implode(' | ', array_column($event['items'], 'titulo')) . '</comment>'),
        'confirm' => $this->io()->writeln('<comment>  pendiente de confirmar: ' . json_encode($event['input'], JSON_UNESCAPED_UNICODE) . '</comment>'),
        'text' => $text .= $event['text'],
        default => NULL,
      };
    }
    $this->io()->newLine();
    $this->io()->writeln($text);
  }

}
