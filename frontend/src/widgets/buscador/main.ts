import { createApp } from 'vue';
import BuscadorTitulaciones from './BuscadorTitulaciones.vue';
import type { FilterOptions } from './types';

const EMPTY_OPTIONS: FilterOptions = { tipo: {}, rama: {}, modalidad: {} };

function parseOptions(raw: string | undefined): FilterOptions {
  if (!raw) {
    return EMPTY_OPTIONS;
  }
  try {
    return { ...EMPTY_OPTIONS, ...(JSON.parse(raw) as Partial<FilterOptions>) };
  } catch {
    return EMPTY_OPTIONS;
  }
}

/**
 * Monta un buscador en cada `[data-sa-widget="buscador"]` que pinta Drupal.
 * Vue marca el contenedor con `data-v-app`, así que llamar dos veces no duplica.
 */
export function mountBuscadores(root: ParentNode = document): void {
  root
    .querySelectorAll<HTMLElement>('[data-sa-widget="buscador"]:not([data-v-app])')
    .forEach((element) => {
      const { endpoint, options, compact, listUrl } = element.dataset;
      if (!endpoint) {
        return;
      }
      createApp(BuscadorTitulaciones, {
        endpoint,
        options: parseOptions(options),
        compact: compact === 'true',
        listUrl,
      }).mount(element);
    });
}

mountBuscadores();
