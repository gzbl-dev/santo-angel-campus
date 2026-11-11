<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Search;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Índice BM25 del contenido publicado del portal (FAQ, titulaciones, eventos).
 *
 * Se construye la primera vez que se necesita y se guarda en caché con las
 * etiquetas `node_list:<tipo>`. Cuando un editor crea, modifica o borra uno
 * de esos contenidos, Drupal invalida la etiqueta y el siguiente mensaje al
 * asistente reconstruye el índice: no hace falta reindexar a mano.
 */
final class PortalIndex {

  public const CACHE_ID = 'sa_assistant:portal_index';

  /**
   * Tipos de contenido indexados y cómo se presentan al usuario.
   */
  private const BUNDLES = [
    'faq' => 'Pregunta frecuente',
    'titulacion' => 'Titulación',
    'evento' => 'Evento',
  ];

  private const EXCERPT_LENGTH = 520;

  /**
   * Índice y metadatos de los documentos, una vez cargados.
   *
   * @var array{index: \Drupal\sa_assistant\Search\Bm25Index, documents: array<string, array{titulo: string, tipo: string, url: string, texto: string}>}|null
   */
  private ?array $loaded = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'cache.default')]
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Fragmentos del portal más relevantes para la consulta.
   *
   * @return list<array{titulo: string, tipo: string, url: string, extracto: string, puntuacion: float, cobertura: float}>
   *   De más a menos relevante.
   */
  public function search(string $query, int $limit = 3): array {
    ['index' => $index, 'documents' => $documents] = $this->load();

    $results = [];
    foreach ($index->search($query, $limit) as ['id' => $id, 'score' => $score, 'coverage' => $coverage]) {
      $document = $documents[$id];
      $results[] = [
        'titulo' => $document['titulo'],
        'tipo' => $document['tipo'],
        'url' => $document['url'],
        'extracto' => self::excerpt($document['texto']),
        'puntuacion' => $score,
        'cobertura' => $coverage,
      ];
    }
    return $results;
  }

  /**
   * Vuelve a construir el índice y devuelve sus cifras.
   *
   * @return array{documentos: int, terminos: int, por_tipo: array<string, int>}
   *   Resumen para el comando Drush.
   */
  public function rebuild(): array {
    $this->cache->delete(self::CACHE_ID);
    $this->loaded = NULL;
    ['index' => $index, 'documents' => $documents] = $this->load();

    return [
      'documentos' => $index->count(),
      'terminos' => $index->vocabularySize(),
      'por_tipo' => array_count_values(array_column($documents, 'tipo')),
    ];
  }

  /**
   * El índice desde la memoria, la caché o construyéndolo.
   *
   * @return array{index: \Drupal\sa_assistant\Search\Bm25Index, documents: array<string, array{titulo: string, tipo: string, url: string, texto: string}>}
   *   Índice y documentos.
   */
  private function load(): array {
    if ($this->loaded !== NULL) {
      return $this->loaded;
    }

    $cached = $this->cache->get(self::CACHE_ID);
    if ($cached) {
      return $this->loaded = $cached->data;
    }

    $this->loaded = $this->build();
    $tags = array_map(static fn (string $bundle): string => 'node_list:' . $bundle, array_keys(self::BUNDLES));
    $this->cache->set(self::CACHE_ID, $this->loaded, Cache::PERMANENT, $tags);
    return $this->loaded;
  }

  /**
   * Lee los nodos publicados y los añade al índice.
   *
   * @return array{index: \Drupal\sa_assistant\Search\Bm25Index, documents: array<string, array{titulo: string, tipo: string, url: string, texto: string}>}
   *   Índice y documentos.
   */
  private function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $index = new Bm25Index();
    $documents = [];

    // Sin comprobar acceso: el índice se comparte entre todos los usuarios,
    // así que solo entra contenido publicado, que es público en este portal.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', array_keys(self::BUNDLES), 'IN')
      ->condition('status', NodeInterface::PUBLISHED)
      ->execute();

    foreach ($storage->loadMultiple($ids) as $node) {
      assert($node instanceof NodeInterface);
      $text = self::plainText(implode("\n\n", array_filter([
        $node->hasField('field_resumen') ? $node->get('field_resumen')->value : NULL,
        $node->get('field_descripcion')->value,
      ])));

      $id = 'node:' . $node->id();
      $index->add($id, (string) $node->label(), $text);
      $documents[$id] = [
        'titulo' => (string) $node->label(),
        'tipo' => self::BUNDLES[$node->bundle()],
        'url' => $node->toUrl()->toString(),
        'texto' => $text,
      ];
    }

    return ['index' => $index, 'documents' => $documents];
  }

  /**
   * HTML a texto plano conservando los párrafos y las listas.
   */
  private static function plainText(string $html): string {
    $html = preg_replace(['#</(p|h\d|ul|ol)>#i', '#<li[^>]*>#i'], ["\n\n", "\n- "], $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace(['/[ \t]+/', '/ *\n */', '/\n{3,}/'], [' ', "\n", "\n\n"], $text);
    return trim((string) $text);
  }

  /**
   * Principio del texto, cortado al final de una frase.
   */
  private static function excerpt(string $text): string {
    if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
      return $text;
    }
    $cut = mb_substr($text, 0, self::EXCERPT_LENGTH);
    $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, ".\n") ?: 0);
    return $end > 0 ? mb_substr($cut, 0, $end + 1) : rtrim($cut) . '…';
  }

}
