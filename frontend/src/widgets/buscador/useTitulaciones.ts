import { onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { fetchTitulaciones } from './jsonapi';
import type { Filters, Titulacion } from './types';

const TEXT_DEBOUNCE_MS = 250;

/**
 * Estado del buscador: consulta la API cuando cambian los filtros.
 *
 * - El texto se espera 250 ms (debounce) para no lanzar una petición por tecla.
 * - Cada petición nueva cancela la anterior (AbortController), así una
 *   respuesta lenta nunca pisa a una más reciente.
 */
export function useTitulaciones(endpoint: string, filters: Ref<Filters>) {
  const items = ref<Titulacion[]>([]);
  const loading = ref(true);
  const error = ref<string | null>(null);

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | undefined;

  async function load(): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    const { signal } = controller;

    loading.value = true;
    error.value = null;
    try {
      items.value = await fetchTitulaciones(endpoint, filters.value, signal);
    } catch (e) {
      if (signal.aborted) {
        return;
      }
      error.value = e instanceof Error ? e.message : String(e);
      items.value = [];
    }
    if (!signal.aborted) {
      loading.value = false;
    }
  }

  // Se vigila una copia: con v-model los filtros mutan el mismo objeto y
  // `prev` y `next` serían la misma referencia.
  watch(
    () => ({ ...filters.value }),
    (next, prev) => {
      clearTimeout(timer);
      const onlyTextChanged =
        prev !== undefined &&
        next.q !== prev.q &&
        next.tipo === prev.tipo &&
        next.rama === prev.rama &&
        next.modalidad === prev.modalidad;

      if (onlyTextChanged) {
        loading.value = true;
        timer = setTimeout(load, TEXT_DEBOUNCE_MS);
      } else {
        void load();
      }
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
  });

  return { items, loading, error, reload: load };
}
