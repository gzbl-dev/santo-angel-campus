import {
  FILTER_KEYS,
  type Filters,
  type JsonApiCollection,
  type Titulacion,
  type TitulacionResource,
} from './types';

/** Campos de Drupal que necesita el widget (sparse fieldsets: respuesta más ligera). */
export const SPARSE_FIELDS = [
  'title',
  'drupal_internal__nid',
  'path',
  'field_tipo',
  'field_rama',
  'field_modalidad',
  'field_creditos',
  'field_resumen',
] as const;

export const PAGE_LIMIT = 50;

/**
 * Traduce los filtros del buscador a parámetros JSON:API.
 *
 * - Texto: condición CONTAINS sobre el título.
 * - Listas: filtro abreviado `filter[field_x]=valor` (igualdad).
 */
export function buildQuery(filters: Filters): URLSearchParams {
  const params = new URLSearchParams();
  params.set('filter[status]', '1');
  params.set('sort', 'title');
  params.set('page[limit]', String(PAGE_LIMIT));
  params.set('fields[node--titulacion]', SPARSE_FIELDS.join(','));

  const text = filters.q.trim();
  if (text) {
    params.set('filter[texto][condition][path]', 'title');
    params.set('filter[texto][condition][operator]', 'CONTAINS');
    params.set('filter[texto][condition][value]', text);
  }

  for (const key of FILTER_KEYS) {
    if (filters[key]) {
      params.set(`filter[field_${key}]`, filters[key]);
    }
  }

  return params;
}

export function toTitulacion(resource: TitulacionResource): Titulacion {
  const a = resource.attributes;
  return {
    id: resource.id,
    title: a.title,
    url: a.path?.alias || `/node/${a.drupal_internal__nid}`,
    tipo: a.field_tipo,
    rama: a.field_rama,
    modalidad: a.field_modalidad,
    creditos: a.field_creditos,
    resumen: a.field_resumen ?? '',
  };
}

export async function fetchTitulaciones(
  endpoint: string,
  filters: Filters,
  signal?: AbortSignal,
): Promise<Titulacion[]> {
  const response = await fetch(`${endpoint}?${buildQuery(filters)}`, {
    headers: { Accept: 'application/vnd.api+json' },
    signal,
  });

  if (!response.ok) {
    throw new Error(`La API respondió ${response.status}`);
  }

  const body = (await response.json()) as JsonApiCollection<TitulacionResource>;
  return body.data.map(toTitulacion);
}

/** Lee los filtros iniciales de la URL (?q=&tipo=&rama=&modalidad=). */
export function filtersFromSearch(search: string, base: Filters): Filters {
  const params = new URLSearchParams(search);
  return {
    q: params.get('q') ?? base.q,
    tipo: params.get('tipo') ?? base.tipo,
    rama: params.get('rama') ?? base.rama,
    modalidad: params.get('modalidad') ?? base.modalidad,
  };
}

/** Serializa solo los filtros activos, para enlaces y para la barra de direcciones. */
export function filtersToSearch(filters: Filters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value.trim()) {
      params.set(key, value.trim());
    }
  }
  const query = params.toString();
  return query ? `?${query}` : '';
}
