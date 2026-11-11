<?php

declare(strict_types=1);

namespace Drupal\Tests\sa_assistant\Unit;

use Drupal\sa_assistant\Search\Bm25Index;
use Drupal\sa_assistant\Search\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Búsqueda BM25 y normalización del texto.
 */
#[CoversClass(Bm25Index::class)]
#[CoversClass(Tokenizer::class)]
final class Bm25IndexTest extends TestCase {

  /**
   * Índice con cuatro preguntas frecuentes.
   */
  private Bm25Index $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->index = new Bm25Index();
    $this->index->add('plazo', '¿Cuándo es el plazo de matrícula?', 'La preinscripción está abierta del 1 de marzo al 15 de julio.');
    $this->index->add('pago', '¿Puedo pagar la matrícula a plazos?', 'Puedes fraccionar el pago en diez mensualidades sin intereses.');
    $this->index->add('becas', '¿Qué becas ofrece la Universidad?', 'Becas de excelencia académica, ayudas al estudio y becas para deportistas.');
    $this->index->add('wifi', '¿Cómo me conecto a la Wi-Fi del campus?', 'Conéctate a la red eduroam con tu usuario de la universidad.');
  }

  /**
   * Quita HTML, tildes, palabras vacías y plurales; une «Wi-Fi» y «wifi».
   */
  public function testTokenizerNormalizesTheText(): void {
    $terms = (new Tokenizer())->tokenize('¿La <b>matrícula</b> de los másteres, las becas y la Wi-Fi?');
    $this->assertSame(['matric', 'master', 'beca', 'wifi'], $terms);
  }

  /**
   * El documento más relevante sale el primero.
   */
  public function testFindsTheMostRelevantDocumentFirst(): void {
    $results = $this->index->search('¿Hasta cuándo puedo hacer la matrícula?');
    $this->assertSame('plazo', $results[0]['id']);
  }

  /**
   * «beca» encuentra «becas».
   */
  public function testMatchesPluralAndSingular(): void {
    $this->assertSame('becas', $this->index->search('beca')[0]['id']);
  }

  /**
   * «conectarme» encuentra «conecto».
   */
  public function testWordFamiliesShareTheirRoot(): void {
    $results = $this->index->search('No puedo conectarme a la wifi');
    $this->assertSame('wifi', $results[0]['id']);
    $this->assertSame(1.0, $results[0]['coverage']);
  }

  /**
   * La cobertura distingue una coincidencia parcial de una respuesta.
   */
  public function testCoverageTellsPartialMatchesApart(): void {
    // Solo «matrícula» está en el índice: un tercio de la pregunta.
    $this->assertSame(0.33, $this->index->search('matrícula piso playa')[0]['coverage']);
  }

  /**
   * Un término raro pesa más que uno frecuente (IDF).
   */
  public function testRareTermsWeighMoreThanCommonOnes(): void {
    // «matrícula» sale en dos documentos y «mensualidades» solo en uno.
    $results = $this->index->search('matrícula mensualidades');
    $this->assertSame('pago', $results[0]['id']);
    $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
  }

  /**
   * Sin términos en común no hay resultados.
   */
  public function testReturnsNothingWhenNoTermMatches(): void {
    $this->assertSame([], $this->index->search('aparcamiento de bicicletas'));
    $this->assertSame([], $this->index->search('¿de la que?'));
  }

  /**
   * Volver a añadir un documento sustituye sus términos.
   */
  public function testReplacingDocumentsUpdatesTheIndex(): void {
    $this->index->add('wifi', 'Horario de la biblioteca', 'Abierta de 8 a 21 h.');
    $this->assertSame(4, $this->index->count());
    $this->assertSame([], $this->index->search('eduroam'));
    $this->assertSame('wifi', $this->index->search('biblioteca')[0]['id']);
  }

}
