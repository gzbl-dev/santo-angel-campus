import { describe, expect, it } from 'vitest';
import { toBlocks, uniqueSources } from '../src/widgets/asistente/format';
import { readNdjson } from '../src/widgets/asistente/ndjson';

/** Cuerpo de respuesta que llega en los trozos indicados. */
function streamOf(chunks: string[]): ReadableStream<Uint8Array> {
  const encoder = new TextEncoder();
  return new ReadableStream({
    start(controller) {
      chunks.forEach((chunk) => controller.enqueue(encoder.encode(chunk)));
      controller.close();
    },
  });
}

async function collect(stream: ReadableStream<Uint8Array>): Promise<unknown[]> {
  const events: unknown[] = [];
  for await (const event of readNdjson(stream)) {
    events.push(event);
  }
  return events;
}

describe('readNdjson', () => {
  it('rebuilds lines split across network chunks', async () => {
    const events = await collect(streamOf(['{"type":"del', 'ta","text":"Ho"}\n{"type":"de', 'lta","text":"la"}\n{"type":"done"}']));

    expect(events).toEqual([
      { type: 'delta', text: 'Ho' },
      { type: 'delta', text: 'la' },
      { type: 'done' },
    ]);
  });

  it('keeps multibyte characters split between chunks', async () => {
    const bytes = new TextEncoder().encode('{"text":"matrícula"}\n');
    const cut = bytes.indexOf(0xc3) + 1; // A mitad de la «í».
    const stream = new ReadableStream<Uint8Array>({
      start(controller) {
        controller.enqueue(bytes.slice(0, cut));
        controller.enqueue(bytes.slice(cut));
        controller.close();
      },
    });

    expect(await collect(stream)).toEqual([{ text: 'matrícula' }]);
  });
});

describe('toBlocks', () => {
  it('splits paragraphs and lists', () => {
    expect(toBlocks('Tenemos 2 másteres:\n- IA · Online\n- Esports · Semipresencial\n\nFin.')).toEqual([
      { type: 'p', text: 'Tenemos 2 másteres:' },
      { type: 'ul', items: ['IA · Online', 'Esports · Semipresencial'] },
      { type: 'p', text: 'Fin.' },
    ]);
  });

  it('never interprets HTML (it stays as text)', () => {
    expect(toBlocks('<img src=x onerror=alert(1)>')).toEqual([{ type: 'p', text: '<img src=x onerror=alert(1)>' }]);
  });
});

describe('uniqueSources', () => {
  it('drops sources repeated by url', () => {
    const a = { titulo: 'A', url: '/a', tipo: 'FAQ' };
    expect(uniqueSources([a, { ...a, titulo: 'A bis' }, { titulo: 'B', url: '/b', tipo: 'FAQ' }])).toHaveLength(2);
  });
});
