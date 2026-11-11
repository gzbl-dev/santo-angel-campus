/**
 * Lee un cuerpo NDJSON (un JSON por línea) según llega por la red.
 *
 * Los trozos de red no coinciden con las líneas: un trozo puede traer media
 * línea o tres. Se acumula en `buffer` y solo se procesan líneas completas;
 * lo que queda a medias espera al siguiente trozo.
 */
export async function* readNdjson(body: ReadableStream<Uint8Array>): AsyncGenerator<unknown> {
  const reader = body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  try {
    for (;;) {
      const { value, done } = await reader.read();
      // stream: true evita romper una tilde partida entre dos trozos.
      buffer += done ? decoder.decode() : decoder.decode(value, { stream: true });

      const lines = buffer.split('\n');
      buffer = done ? '' : (lines.pop() ?? '');
      for (const line of lines) {
        if (line.trim() !== '') {
          yield JSON.parse(line);
        }
      }

      if (done) {
        return;
      }
    }
  } finally {
    reader.releaseLock();
  }
}
