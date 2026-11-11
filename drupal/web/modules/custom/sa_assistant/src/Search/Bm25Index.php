<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Search;

/**
 * Índice invertido con puntuación BM25, la «R» (retrieval) del RAG.
 *
 * Para cada término de la consulta, BM25 suma más puntos cuando:
 * - el término es raro en la colección (IDF): «convalidación» pesa más que
 *   «universidad», que sale en casi todos los documentos;
 * - aparece varias veces en el documento, pero con rendimientos decrecientes
 *   (k1): la décima aparición aporta mucho menos que la primera;
 * - el documento es corto (b): una mención en una FAQ de dos líneas cuenta
 *   más que la misma mención en una ficha larga.
 *
 * El título cuenta doble: si la consulta coincide con el título, ese
 * documento casi seguro es la respuesta.
 */
final class Bm25Index {

  private const TITLE_WEIGHT = 2;

  /**
   * Frecuencia de cada término por documento.
   *
   * @var array<string, array<string, int>>
   */
  private array $termFrequencies = [];

  /**
   * Número de términos de cada documento.
   *
   * @var array<string, int>
   */
  private array $lengths = [];

  /**
   * En cuántos documentos aparece cada término.
   *
   * @var array<string, int>
   */
  private array $documentFrequencies = [];

  public function __construct(
    private readonly Tokenizer $tokenizer = new Tokenizer(),
    private readonly float $k1 = 1.2,
    private readonly float $b = 0.75,
  ) {}

  /**
   * Añade (o sustituye) un documento.
   */
  public function add(string $id, string $title, string $body): void {
    if (isset($this->termFrequencies[$id])) {
      $this->remove($id);
    }

    $terms = $this->tokenizer->tokenize($body);
    $titleTerms = $this->tokenizer->tokenize($title);
    for ($i = 0; $i < self::TITLE_WEIGHT; $i++) {
      $terms = array_merge($terms, $titleTerms);
    }
    $frequencies = array_count_values($terms);

    $this->termFrequencies[$id] = $frequencies;
    $this->lengths[$id] = count($terms);
    foreach (array_keys($frequencies) as $term) {
      $this->documentFrequencies[$term] = ($this->documentFrequencies[$term] ?? 0) + 1;
    }
  }

  /**
   * Documentos que mejor responden a la consulta, de más a menos relevante.
   *
   * Además de la puntuación devuelve la cobertura: qué parte de los términos
   * de la consulta aparece en el documento. Una FAQ que solo comparte
   * «cuesta» con «¿cuánto cuesta un piso en Murcia?» puntúa, pero cubre un
   * tercio de la pregunta: no es la respuesta.
   *
   * @return list<array{id: string, score: float, coverage: float}>
   *   Solo documentos con puntuación mayor que cero.
   */
  public function search(string $query, int $limit = 3): array {
    $count = count($this->lengths);
    $terms = array_unique($this->tokenizer->tokenize($query));
    if ($count === 0 || $terms === []) {
      return [];
    }

    $averageLength = array_sum($this->lengths) / $count;
    $scores = [];
    $matched = [];
    foreach ($terms as $term) {
      $df = $this->documentFrequencies[$term] ?? 0;
      if ($df === 0) {
        continue;
      }
      // IDF con +1 dentro del logaritmo para que nunca sea negativo.
      $idf = log(1 + ($count - $df + 0.5) / ($df + 0.5));

      foreach ($this->termFrequencies as $id => $frequencies) {
        $tf = $frequencies[$term] ?? 0;
        if ($tf === 0) {
          continue;
        }
        $norm = 1 - $this->b + $this->b * $this->lengths[$id] / $averageLength;
        $scores[$id] = ($scores[$id] ?? 0) + $idf * ($tf * ($this->k1 + 1)) / ($tf + $this->k1 * $norm);
        $matched[$id] = ($matched[$id] ?? 0) + 1;
      }
    }

    arsort($scores);
    $results = [];
    foreach (array_slice($scores, 0, $limit, TRUE) as $id => $score) {
      $results[] = [
        'id' => (string) $id,
        'score' => round($score, 4),
        'coverage' => round($matched[$id] / count($terms), 2),
      ];
    }
    return $results;
  }

  /**
   * Número de documentos indexados.
   */
  public function count(): int {
    return count($this->lengths);
  }

  /**
   * Número de términos distintos del vocabulario.
   */
  public function vocabularySize(): int {
    return count($this->documentFrequencies);
  }

  /**
   * Quita un documento y descuenta sus términos.
   */
  private function remove(string $id): void {
    foreach (array_keys($this->termFrequencies[$id]) as $term) {
      if (--$this->documentFrequencies[$term] === 0) {
        unset($this->documentFrequencies[$term]);
      }
    }
    unset($this->termFrequencies[$id], $this->lengths[$id]);
  }

}
