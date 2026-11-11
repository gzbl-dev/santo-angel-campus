<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import { toBlocks } from './format';
import type { AssistantSettings, ChatMessage } from './types';
import { useAsistente } from './useAsistente';

const props = defineProps<{ settings: AssistantSettings }>();

const MAX_LENGTH = 500;
const uid = useId();

const { messages, open, busy, status, announcement, send, confirm, cancel, reset } = useAsistente(props.settings);

const draft = ref('');
const launcher = ref<HTMLButtonElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);
const log = ref<HTMLElement | null>(null);

async function toggle(value = !open.value): Promise<void> {
  open.value = value;
  await nextTick();
  (value ? input.value : launcher.value)?.focus();
}

async function submit(text = draft.value): Promise<void> {
  if (text.trim() === '' || busy.value) {
    return;
  }
  draft.value = '';
  await send(text);
  input.value?.focus();
}

/** Intro envía; Mayúsculas + Intro hace salto de línea. */
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault();
    void submit();
  }
}

function field(message: ChatMessage, key: string): string {
  const value = message.action?.input[key];
  return typeof value === 'string' ? value : '';
}

// Baja al final cuando llega texto nuevo.
watch(
  () => [messages.value.length, messages.value.at(-1)?.text, status.value],
  async () => {
    await nextTick();
    if (log.value) {
      log.value.scrollTop = log.value.scrollHeight;
    }
  },
);
</script>

<template>
  <div class="saa" @keydown.esc="toggle(false)">
    <button
      v-show="!open"
      ref="launcher"
      class="saa__launcher"
      type="button"
      :aria-expanded="open"
      :aria-controls="`${uid}-panel`"
      @click="toggle(true)"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v12h2v.84L8.3 16H20V6H4Zm3 4h10v2H7v-2Z" />
      </svg>
      <span>Pregunta al asistente</span>
    </button>

    <section
      v-show="open"
      :id="`${uid}-panel`"
      class="saa__panel"
      role="dialog"
      aria-modal="false"
      :aria-labelledby="`${uid}-title`"
    >
      <header class="saa__header">
        <div>
          <h2 :id="`${uid}-title`" class="saa__title">Asistente Santo Ángel</h2>
          <p v-if="settings.simulated" class="saa__demo">
            Modo demo: búsqueda y herramientas reales, respuestas redactadas sin modelo de lenguaje.
          </p>
        </div>
        <div class="saa__header-actions">
          <button v-if="messages.length" class="saa__icon-button" type="button" title="Nueva conversación" @click="reset">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 5a7 7 0 1 1-6.93 8h2.02A5 5 0 1 0 12 7v3L7.5 6 12 2v3Z" />
            </svg>
            <span class="saa__sr">Nueva conversación</span>
          </button>
          <button class="saa__icon-button" type="button" title="Cerrar" @click="toggle(false)">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m6.4 5 5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6l5.6-5.6L5 6.4 6.4 5Z" />
            </svg>
            <span class="saa__sr">Cerrar el asistente</span>
          </button>
        </div>
      </header>

      <div ref="log" class="saa__log">
        <div class="saa__message saa__message--assistant">
          <p>{{ settings.welcome }}</p>
        </div>

        <ul v-if="!messages.length && settings.suggestions.length" class="saa__suggestions" aria-label="Preguntas sugeridas">
          <li v-for="suggestion in settings.suggestions" :key="suggestion">
            <button class="saa__suggestion" type="button" @click="submit(suggestion)">{{ suggestion }}</button>
          </li>
        </ul>

        <div
          v-for="message in messages"
          :key="message.id"
          class="saa__message"
          :class="[`saa__message--${message.role}`, { 'saa__message--error': message.status === 'error' }]"
        >
          <p v-if="message.role === 'user'">{{ message.text }}</p>
          <template v-else>
            <template v-for="(block, i) in toBlocks(message.text)" :key="i">
              <p v-if="block.type === 'p'">{{ block.text }}</p>
              <ul v-else>
                <li v-for="item in block.items" :key="item">{{ item }}</li>
              </ul>
            </template>

            <div v-if="message.action" class="saa__action">
              <p class="saa__action-title">Incidencia</p>
              <dl>
                <dt>Asunto</dt>
                <dd>{{ field(message, 'asunto') }}</dd>
                <dt>Descripción</dt>
                <dd>{{ field(message, 'descripcion') }}</dd>
              </dl>
              <div v-if="message.action.state === 'pending' || message.action.state === 'sending'" class="saa__action-buttons">
                <button
                  class="saa__button"
                  type="button"
                  :disabled="message.action.state === 'sending'"
                  @click="confirm(message)"
                >
                  {{ message.action.state === 'sending' ? 'Enviando…' : 'Enviar incidencia' }}
                </button>
                <button
                  class="saa__button saa__button--ghost"
                  type="button"
                  :disabled="message.action.state === 'sending'"
                  @click="cancel(message)"
                >
                  Cancelar
                </button>
              </div>
              <p v-else-if="message.action.state === 'cancelled'" class="saa__action-result">No se ha enviado.</p>
              <p v-else class="saa__action-result" :class="{ 'saa__action-result--error': message.action.state === 'error' }">
                {{ message.action.result }}
              </p>
            </div>

            <div v-if="message.sources.length && message.status !== 'error'" class="saa__sources">
              <p class="saa__sources-title">Fuentes</p>
              <ul>
                <li v-for="source in message.sources" :key="source.url">
                  <a :href="source.url">{{ source.titulo }}</a>
                  <span class="saa__source-type">{{ source.tipo }}</span>
                </li>
              </ul>
            </div>
          </template>
        </div>

        <p v-if="busy" class="saa__status">
          <span class="saa__dots" aria-hidden="true"><span></span><span></span><span></span></span>
          {{ status || 'Pensando…' }}
        </p>
      </div>

      <!-- Solo para lectores de pantalla: la respuesta entera, una vez terminada. -->
      <p class="saa__sr" role="status" aria-live="polite">{{ announcement }}</p>

      <form class="saa__form" @submit.prevent="submit()">
        <label :for="`${uid}-input`" class="saa__sr">Tu pregunta</label>
        <textarea
          :id="`${uid}-input`"
          ref="input"
          v-model="draft"
          class="saa__input"
          rows="1"
          :maxlength="MAX_LENGTH"
          placeholder="Escribe tu pregunta…"
          @keydown="onKeydown"
        ></textarea>
        <button class="saa__send" type="submit" :disabled="busy || draft.trim() === ''">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M3 20.5v-6l8-2.5-8-2.5v-6L22 12 3 20.5Z" />
          </svg>
          <span class="saa__sr">Enviar</span>
        </button>
      </form>
    </section>
  </div>
</template>

<style>
/* Tokens del tema (custom properties) con valores de reserva. */
.saa {
  --_accent: var(--sa-granate, #7a1f2b);
  --_accent-deep: var(--sa-granate-deep, #4f111a);
  --_line: var(--sa-line, #e7dfd4);
  --_soft: var(--sa-ink-soft, #5b524c);
  --_paper: var(--sa-paper, #faf7f2);
  --_surface: var(--sa-surface, #fff);
  --_highlight: var(--sa-ocre-soft, #fbf0dc);

  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 500;
  font-family: var(--sa-font-sans, system-ui, sans-serif);
  color: var(--sa-ink, #1f1a17);
}

.saa__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.saa svg {
  width: 1.25rem;
  height: 1.25rem;
  fill: currentColor;
}

.saa button {
  font: inherit;
  cursor: pointer;
}

.saa :focus-visible {
  outline: 3px solid var(--sa-ocre, #d99a2b);
  outline-offset: 2px;
}

.saa__launcher {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.85rem 1.2rem;
  border: 0;
  border-radius: 999px;
  background: var(--_accent);
  box-shadow: var(--sa-shadow, 0 10px 30px rgb(0 0 0 / 15%));
  color: #fff;
  font-weight: 600;
}

.saa__launcher:hover {
  background: var(--_accent-deep);
}

.saa__panel {
  display: flex;
  flex-direction: column;
  width: min(25rem, calc(100vw - 2rem));
  height: min(38rem, calc(100vh - 2rem));
  height: min(38rem, calc(100dvh - 2rem));
  overflow: hidden;
  border: 1px solid var(--_line);
  border-radius: var(--sa-radius, 14px);
  background: var(--_surface);
  box-shadow: var(--sa-shadow, 0 10px 30px rgb(0 0 0 / 15%));
}

.saa__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  background: var(--_accent);
  color: #fff;
}

.saa__title {
  margin: 0;
  font-family: var(--sa-font-serif, Georgia, serif);
  font-size: 1.1rem;
  line-height: 1.3;
  color: inherit;
}

.saa__demo {
  margin: 0.2rem 0 0;
  font-size: 0.75rem;
  line-height: 1.35;
  opacity: 0.85;
}

.saa__header-actions {
  display: flex;
  gap: 0.25rem;
}

.saa__icon-button {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: inherit;
}

.saa__icon-button:hover {
  background: rgb(255 255 255 / 15%);
}

.saa__log {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  overflow-y: auto;
  background: var(--_paper);
  overscroll-behavior: contain;
}

.saa__message {
  max-width: 88%;
  padding: 0.65rem 0.85rem;
  border-radius: 12px;
  font-size: 0.925rem;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.saa__message p,
.saa__message ul {
  margin: 0;
}

/* :not() suma especificidad para ganar al margin: 0 de los párrafos. */
.saa__message > :not(:first-child) {
  margin-top: 0.6rem;
}

.saa__message ul {
  padding-left: 1.1rem;
}

.saa__message--assistant {
  align-self: flex-start;
  border: 1px solid var(--_line);
  border-bottom-left-radius: 4px;
  background: var(--_surface);
}

.saa__message--user {
  align-self: flex-end;
  border-bottom-right-radius: 4px;
  background: var(--_accent);
  color: #fff;
  white-space: pre-wrap;
}

.saa__message--error {
  border-color: #e0b4b4;
  background: #fdf0f0;
}

.saa__suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.saa__suggestion {
  padding: 0.4rem 0.75rem;
  border: 1px solid var(--_accent);
  border-radius: 999px;
  background: var(--_surface);
  color: var(--_accent);
  font-size: 0.85rem;
}

.saa__suggestion:hover {
  background: var(--_highlight);
}

.saa__sources {
  padding-top: 0.6rem;
  border-top: 1px solid var(--_line);
  font-size: 0.85rem;
}

.saa__sources-title,
.saa__action-title {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--_soft);
}

.saa__sources ul {
  display: grid;
  gap: 0.2rem;
  margin-top: 0.3rem;
  padding: 0;
  list-style: none;
}

.saa__sources a {
  color: var(--_accent);
  font-weight: 600;
}

.saa__source-type {
  margin-left: 0.4rem;
  color: var(--_soft);
  font-size: 0.75rem;
}

.saa__action {
  padding: 0.75rem;
  border: 1px solid var(--_line);
  border-radius: var(--sa-radius-sm, 8px);
  background: var(--_highlight);
}

.saa__action dl {
  display: grid;
  gap: 0.15rem;
  margin: 0.4rem 0 0;
  font-size: 0.875rem;
}

.saa__action dt {
  font-weight: 600;
}

.saa__action dd {
  margin: 0 0 0.35rem;
}

.saa__action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.saa__button {
  padding: 0.5rem 0.9rem;
  border: 1px solid var(--_accent);
  border-radius: var(--sa-radius-sm, 8px);
  background: var(--_accent);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
}

.saa__button--ghost {
  background: transparent;
  color: var(--_accent);
}

.saa__button:disabled {
  opacity: 0.6;
  cursor: progress;
}

.saa__action-result {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.saa__action-result--error {
  color: #a12828;
}

.saa__status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: 0.85rem;
  color: var(--_soft);
}

.saa__dots {
  display: inline-flex;
  gap: 3px;
}

.saa__dots span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: saa-blink 1.2s infinite ease-in-out;
}

.saa__dots span:nth-child(2) {
  animation-delay: 0.15s;
}

.saa__dots span:nth-child(3) {
  animation-delay: 0.3s;
}

@keyframes saa-blink {
  0%,
  80%,
  100% {
    opacity: 0.25;
  }
  40% {
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .saa__dots span {
    animation: none;
  }
}

.saa__form {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  padding: 0.75rem;
  border-top: 1px solid var(--_line);
  background: var(--_surface);
}

.saa__input {
  flex: 1;
  min-height: 2.75rem;
  max-height: 8rem;
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--_line);
  border-radius: var(--sa-radius-sm, 8px);
  background: var(--_paper);
  color: inherit;
  font: inherit;
  resize: none;
  field-sizing: content;
}

.saa__send {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: var(--sa-radius-sm, 8px);
  background: var(--_accent);
  color: #fff;
}

.saa__send:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* En móvil el panel ocupa todo el ancho, como una hoja inferior. */
@media (max-width: 30rem) {
  .saa {
    right: 0.75rem;
    bottom: 0.75rem;
  }

  .saa__panel {
    position: fixed;
    inset: auto 0 0;
    width: 100%;
    height: 85dvh;
    border-radius: var(--sa-radius, 14px) var(--sa-radius, 14px) 0 0;
  }
}
</style>
