import { onBeforeUnmount, ref, watch } from 'vue';
import { AssistantError, confirmAction, sendMessage } from './api';
import { uniqueSources } from './format';
import type { AssistantSettings, ChatMessage, HistoryTurn } from './types';

export const STORAGE_KEY = 'sa-asistente';

/** Turnos que se envían como contexto (el servidor también recorta). */
const HISTORY_TURNS = 10;

interface Stored {
  messages: ChatMessage[];
  open: boolean;
}

/**
 * La conversación se guarda en sessionStorage: al pulsar una fuente se
 * cambia de página y el chat sigue ahí. Se borra al cerrar la pestaña.
 */
function load(): Stored {
  try {
    const stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? 'null') as Stored | null;
    if (stored && Array.isArray(stored.messages)) {
      // Una respuesta cortada por la navegación ya no va a terminar.
      for (const message of stored.messages) {
        if (message.status === 'streaming') {
          message.status = 'done';
        }
      }
      return stored;
    }
  } catch {
    // Almacenamiento bloqueado o datos corruptos: se empieza de cero.
  }
  return { messages: [], open: false };
}

/** Estado y acciones del asistente. */
export function useAsistente(settings: AssistantSettings) {
  const initial = load();
  const messages = ref<ChatMessage[]>(initial.messages);
  const open = ref(initial.open);
  const busy = ref(false);
  /** Lo que está haciendo el agente ahora («Buscando en el portal…»). */
  const status = ref('');
  /** Último mensaje completo, para el lector de pantalla. */
  const announcement = ref('');

  let nextId = Math.max(0, ...messages.value.map((message) => message.id)) + 1;
  let controller: AbortController | null = null;

  watch(
    [messages, open],
    () => {
      try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ messages: messages.value, open: open.value }));
      } catch {
        // Sin almacenamiento el chat funciona igual, solo no sobrevive a la navegación.
      }
    },
    { deep: true },
  );

  function history(): HistoryTurn[] {
    return messages.value
      .filter((message) => message.status === 'done' && message.text.trim() !== '')
      .slice(-HISTORY_TURNS)
      .map(({ role, text }) => ({ role, text }));
  }

  async function send(text: string): Promise<void> {
    const question = text.trim();
    if (question === '' || busy.value) {
      return;
    }

    const turns = history();
    messages.value.push({ id: nextId++, role: 'user', text: question, sources: [], status: 'done' });
    messages.value.push({ id: nextId++, role: 'assistant', text: '', sources: [], status: 'streaming' });
    // El objeto reactivo (proxy), para que cada cambio se pinte al momento.
    const reply = messages.value[messages.value.length - 1]!;

    busy.value = true;
    controller = new AbortController();
    try {
      for await (const event of sendMessage(settings, question, turns, controller.signal)) {
        switch (event.type) {
          case 'tool':
            status.value = event.label;
            break;
          case 'sources':
            reply.sources = uniqueSources([...reply.sources, ...event.items]);
            break;
          case 'confirm':
            reply.action = { token: event.token, tool: event.tool, input: event.input, state: 'pending' };
            break;
          case 'delta':
            status.value = '';
            reply.text += event.text;
            break;
          case 'error':
            throw new AssistantError(event.message);
          case 'done':
            break;
        }
      }
      reply.status = 'done';
      announcement.value = reply.text;
    } catch (error) {
      if (controller.signal.aborted) {
        return;
      }
      reply.status = 'error';
      reply.text = error instanceof AssistantError ? error.message : 'Ha ocurrido un error inesperado.';
      announcement.value = reply.text;
    } finally {
      busy.value = false;
      status.value = '';
      controller = null;
    }
  }

  async function confirm(message: ChatMessage): Promise<void> {
    const action = message.action;
    if (!action || action.state !== 'pending') {
      return;
    }
    // Se marca antes de la petición: un doble clic no registra dos incidencias.
    action.state = 'sending';
    try {
      action.result = await confirmAction(settings, action.token);
      action.state = 'done';
    } catch (error) {
      action.result = error instanceof AssistantError ? error.message : 'No se ha podido enviar.';
      action.state = 'error';
    }
    announcement.value = action.result;
  }

  function cancel(message: ChatMessage): void {
    if (message.action?.state === 'pending') {
      message.action.state = 'cancelled';
    }
  }

  function reset(): void {
    controller?.abort();
    messages.value = [];
  }

  onBeforeUnmount(() => controller?.abort());

  return { messages, open, busy, status, announcement, send, confirm, cancel, reset };
}
