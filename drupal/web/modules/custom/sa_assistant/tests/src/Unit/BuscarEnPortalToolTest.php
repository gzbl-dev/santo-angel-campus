<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit;

use Drupal\sa_assistant\Tool\BuscarEnPortalTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Corte de relevancia del RAG.
 */
#[CoversClass(BuscarEnPortalTool::class)]
final class BuscarEnPortalToolTest extends TestCase {

  /**
   * Se quedan los fragmentos que superan los tres umbrales.
   */
  public function testKeepsOnlyRelevantFragments(): void {
    $results = [
      ['titulo' => 'Plazo de matrícula', 'puntuacion' => 8.0, 'cobertura' => 1.0],
      ['titulo' => 'Pago a plazos', 'puntuacion' => 5.0, 'cobertura' => 0.5],
      ['titulo' => 'Convalidaciones', 'puntuacion' => 3.0, 'cobertura' => 0.5],
    ];

    $titles = array_column(BuscarEnPortalTool::relevant($results), 'titulo');

    // La tercera supera el mínimo, pero puntúa menos de la mitad que la mejor.
    $this->assertSame(['Plazo de matrícula', 'Pago a plazos'], $titles);
  }

  /**
   * Compartir una sola palabra con la pregunta no basta.
   */
  public function testDropsFragmentsThatOnlyShareOneWord(): void {
    $results = [['titulo' => 'Certificados', 'puntuacion' => 3.1, 'cobertura' => 0.33]];

    $this->assertSame([], BuscarEnPortalTool::relevant($results));
  }

}
