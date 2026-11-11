/** Ajustes que Drupal pinta en `data-settings` del punto de montaje. */
export interface AssistantSettings {
  endpoint: string;
  confirmEndpoint: string;
  tokenEndpoint: string;
  welcome: string;
  suggestions: string[];
  /** El "modelo" es el proveedor simulado de la demo. */
  simulated: boolean;
}

export interface Source {
  titulo: string;
  url: string;
  tipo: string;
}

/** Eventos NDJSON que emite POST /api/asistente (uno por línea). */
export type AssistantEvent =
  | { type: 'tool'; name: string; label: string }
  | { type: 'sources'; items: Source[] }
  | { type: 'confirm'; token: string; tool: string; input: Record<string, unknown> }
  | { type: 'delta'; text: string }
  | { type: 'error'; message: string }
  | { type: 'done' };

export type ActionState = 'pending' | 'sending' | 'done' | 'cancelled' | 'error';

/** Acción propuesta por el asistente que el usuario debe confirmar. */
export interface PendingAction {
  token: string;
  tool: string;
  input: Record<string, unknown>;
  state: ActionState;
  /** Respuesta del servidor al confirmar (o el error). */
  result?: string;
}

export interface ChatMessage {
  id: number;
  role: 'user' | 'assistant';
  text: string;
  sources: Source[];
  action?: PendingAction;
  /** `streaming` mientras llega el texto; `error` si la petición falló. */
  status: 'streaming' | 'done' | 'error';
}

/** Turno del historial que se envía al servidor (solo texto). */
export interface HistoryTurn {
  role: 'user' | 'assistant';
  text: string;
}

/** Texto troceado en párrafos y listas para pintarlo sin v-html. */
export type TextBlock = { type: 'p'; text: string } | { type: 'ul'; items: string[] };
