<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Search;

/**
 * Convierte texto en español en los términos que usa el índice BM25.
 *
 * Pasos: quitar HTML, minúsculas, quitar tildes, partir por lo que no sea
 * letra o número, descartar palabras vacías («de», «la», «que»…) y reducir
 * cada palabra a una raíz aproximada para que «becas» encuentre «beca» y
 * «aparcar» encuentre «aparcamiento».
 */
final class Tokenizer {

  /**
   * Palabras demasiado frecuentes para distinguir un documento de otro.
   *
   * Ya sin tildes, porque se comparan después de normalizar.
   */
  private const STOPWORDS = [
    'a', 'al', 'algo', 'algun', 'alguna', 'como', 'con', 'cual', 'cuales',
    'cuando', 'cuanto', 'cuanta', 'de', 'del', 'donde', 'el', 'ella', 'en',
    'es', 'esa', 'ese', 'eso', 'esta', 'estan', 'este', 'esto', 'estoy', 'fue',
    'ha', 'hay', 'hola', 'la', 'las', 'le', 'les', 'lo', 'los', 'me', 'mi',
    'mis', 'muy', 'no', 'nos', 'o', 'para', 'pero', 'por', 'puede', 'pueden',
    'puedo', 'que', 'quiero', 'se', 'ser', 'si', 'sin', 'sobre', 'son', 'su',
    'sus', 'te', 'tengo', 'tiene', 'tienen', 'tu', 'tus', 'un', 'una', 'unas',
    'uno', 'unos', 'y', 'ya', 'yo', 'vosotros', 'usted', 'saber', 'hacer',
  ];

  /**
   * Consonantes tras las que el plural en español añade «-es».
   *
   * Másteres → máster, ciudades → ciudad. En el resto basta con quitar la
   * «s» final: becas → beca, clases → clase.
   */
  private const PLURAL_ES_AFTER = ['r', 'l', 'n', 'd', 'z', 'j'];

  /**
   * Letras que se conservan de cada palabra (truncamiento).
   *
   * Es el lematizador más simple que existe: une familias de palabras
   * (conectar, conecto, conectarme → «conect») a cambio de alguna colisión
   * rara. Con un vocabulario pequeño compensa con creces.
   */
  private const STEM_LENGTH = 6;

  /**
   * Términos del texto, en orden y con repeticiones.
   *
   * @return list<string>
   *   Términos normalizados.
   */
  public function tokenize(string $text): array {
    $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // «Wi-Fi» y «wifi», «e-mail» y «email» deben ser la misma palabra.
    $text = preg_replace('/(?<=[a-z])-(?=[a-z])/', '', self::normalize($text));

    $terms = [];
    foreach (preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
      if (strlen($word) < 2 || in_array($word, self::STOPWORDS, TRUE)) {
        continue;
      }
      $terms[] = $this->stem($word);
    }
    return $terms;
  }

  /**
   * Minúsculas y sin tildes: «Matrícula» → «matricula».
   */
  public static function normalize(string $text): string {
    return strtr(mb_strtolower($text, 'UTF-8'), [
      'á' => 'a',
      'é' => 'e',
      'í' => 'i',
      'ó' => 'o',
      'ú' => 'u',
      'ü' => 'u',
      'ñ' => 'n',
    ]);
  }

  /**
   * Raíz aproximada: singular y, después, las primeras letras.
   */
  public function stem(string $word): string {
    $length = strlen($word);
    if ($length > 4 && str_ends_with($word, 'es') && in_array($word[$length - 3], self::PLURAL_ES_AFTER, TRUE)) {
      $word = substr($word, 0, -2);
    }
    elseif ($length > 3 && str_ends_with($word, 's')) {
      $word = substr($word, 0, -1);
    }
    return substr($word, 0, self::STEM_LENGTH);
  }

}
