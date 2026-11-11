import type { Source, TextBlock } from './types';

/**
 * Trocea la respuesta en párrafos y listas («- » al principio de línea).
 *
 * Se pinta con plantillas de Vue, nunca con v-html: el texto lo genera el
 * modelo y podría contener HTML, que así siempre se muestra como texto.
 */
export function toBlocks(text: string): TextBlock[] {
  const blocks: TextBlock[] = [];
  let paragraph: string[] = [];
  let list: string[] = [];

  const flush = () => {
    if (paragraph.length) {
      blocks.push({ type: 'p', text: paragraph.join(' ') });
      paragraph = [];
    }
    if (list.length) {
      blocks.push({ type: 'ul', items: list });
      list = [];
    }
  };

  for (const raw of text.split('\n')) {
    const line = raw.trim();
    if (line === '') {
      flush();
    } else if (line.startsWith('- ')) {
      if (paragraph.length) {
        flush();
      }
      list.push(line.slice(2));
    } else {
      if (list.length) {
        flush();
      }
      paragraph.push(line);
    }
  }
  flush();
  return blocks;
}

/** Fuentes sin repetir (varias herramientas pueden citar la misma página). */
export function uniqueSources(sources: Source[]): Source[] {
  const seen = new Set<string>();
  return sources.filter((source) => !seen.has(source.url) && seen.add(source.url));
}
