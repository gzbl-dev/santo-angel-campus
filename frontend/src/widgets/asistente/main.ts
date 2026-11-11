import { createApp } from 'vue';
import Asistente from './Asistente.vue';
import type { AssistantSettings } from './types';

function parseSettings(raw: string | undefined): AssistantSettings | null {
  try {
    const settings = JSON.parse(raw ?? '') as Partial<AssistantSettings>;
    if (!settings.endpoint || !settings.confirmEndpoint || !settings.tokenEndpoint) {
      return null;
    }
    return {
      welcome: '',
      suggestions: [],
      simulated: false,
      ...settings,
    } as AssistantSettings;
  } catch {
    return null;
  }
}

/** Monta el asistente en `[data-sa-widget="asistente"]` (lo pinta Drupal al final de la página). */
export function mountAsistente(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-sa-widget="asistente"]:not([data-v-app])').forEach((element) => {
    const settings = parseSettings(element.dataset.settings);
    if (settings) {
      createApp(Asistente, { settings }).mount(element);
    }
  });
}

mountAsistente();
