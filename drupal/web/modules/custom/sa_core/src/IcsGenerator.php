<?php

declare(strict_types=1);

namespace Drupal\sa_core;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\node\NodeInterface;

/**
 * Genera un fichero iCalendar (RFC 5545) para añadir un evento al calendario.
 */
final class IcsGenerator {

  private const DEFAULT_DURATION = 'PT2H';

  public function __construct(
    private readonly TimeInterface $time,
    private readonly TransliterationInterface $transliteration,
  ) {}

  /**
   * Nombre de fichero seguro a partir del título, p. ej. "jornada-x.ics".
   */
  public function filename(NodeInterface $event): string {
    $ascii = $this->transliteration->transliterate((string) $event->label(), 'es');
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
    return ($slug ?: 'evento-' . $event->id()) . '.ics';
  }

  /**
   * Contenido .ics de un evento.
   */
  public function fromEvent(NodeInterface $event): string {
    $start = $event->get('field_fecha')->date;
    $summary = $event->get('field_resumen')->value ?? '';

    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Universidad Santo Angel//Campus//ES',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'BEGIN:VEVENT',
      'UID:evento-' . $event->id() . '@santoangel.example',
      'DTSTAMP:' . gmdate('Ymd\THis\Z', $this->time->getRequestTime()),
      'DTSTART:' . $start->format('Ymd\THis\Z', ['timezone' => 'UTC']),
      'DURATION:' . self::DEFAULT_DURATION,
      'SUMMARY:' . $this->escape((string) $event->label()),
      'LOCATION:' . $this->escape($event->get('field_lugar')->value ?? ''),
      'DESCRIPTION:' . $this->escape($summary),
      'URL:' . $event->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'END:VEVENT',
      'END:VCALENDAR',
    ];

    return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
  }

  /**
   * Escapa los caracteres reservados de un valor de texto.
   */
  private function escape(string $value): string {
    return str_replace(
      ['\\', ';', ',', "\r\n", "\n"],
      ['\\\\', '\\;', '\\,', '\\n', '\\n'],
      $value,
    );
  }

  /**
   * Parte las líneas de más de 75 octetos, como exige la especificación.
   */
  private function fold(string $line): string {
    $folded = '';
    while (strlen($line) > 75) {
      $chunk = mb_strcut($line, 0, 75, 'UTF-8');
      $folded .= $chunk . "\r\n ";
      $line = substr($line, strlen($chunk));
    }
    return $folded . $line;
  }

}
