import { readNdjson } from './ndjson';
import type { AssistantEvent, AssistantSettings, HistoryTurn } from './types';

/** Error con un mensaje apto para enseñar al usuario. */
export class AssistantError extends Error {}

const NETWORK_ERROR = 'No he podido conectar con el asistente. Comprueba tu conexión e inténtalo de nuevo.';

let csrfToken: Promise<string> | null = null;

/**
 * Token CSRF de Drupal (/session/token), pedido una sola vez por página.
 * Drupal lo exige en la cabecera X-CSRF-Token a los usuarios con sesión.
 */
function getCsrfToken(endpoint: string): Promise<string> {
  csrfToken ??= fetch(endpoint, { credentials: 'same-origin' })
    .then((response) => (response.ok ? response.text() : ''))
    .catch(() => '');
  return csrfToken;
}

/** Solo para los tests: olvida el token cacheado. */
export function resetCsrfToken(): void {
  csrfToken = null;
}

async function post(url: string, tokenEndpoint: string, body: unknown, signal?: AbortSignal): Promise<Response> {
  let response: Response;
  try {
    response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': await getCsrfToken(tokenEndpoint),
      },
      body: JSON.stringify(body),
      signal,
    });
  } catch (error) {
    if (signal?.aborted) {
      throw error;
    }
    throw new AssistantError(NETWORK_ERROR);
  }

  if (!response.ok) {
    // El servidor explica el error en {"error": "..."} (429, 400, 410…).
    const data = (await response.json().catch(() => null)) as { error?: string } | null;
    throw new AssistantError(data?.error ?? NETWORK_ERROR);
  }
  return response;
}

/** Envía un mensaje y devuelve los eventos de la respuesta según llegan. */
export async function* sendMessage(
  settings: AssistantSettings,
  message: string,
  history: HistoryTurn[],
  signal?: AbortSignal,
): AsyncGenerator<AssistantEvent> {
  const response = await post(settings.endpoint, settings.tokenEndpoint, { message, history }, signal);
  if (!response.body) {
    throw new AssistantError(NETWORK_ERROR);
  }
  for await (const event of readNdjson(response.body)) {
    yield event as AssistantEvent;
  }
}

/** Confirma una acción pendiente y devuelve el mensaje del servidor. */
export async function confirmAction(settings: AssistantSettings, token: string): Promise<string> {
  const response = await post(settings.confirmEndpoint, settings.tokenEndpoint, { token });
  const data = (await response.json()) as { message: string };
  return data.message;
}
