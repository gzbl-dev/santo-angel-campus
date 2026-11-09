export const FILTER_KEYS = ['tipo', 'rama', 'modalidad'] as const;

export type FilterKey = (typeof FILTER_KEYS)[number];

export interface Filters {
  q: string;
  tipo: string;
  rama: string;
  modalidad: string;
}

/** Valores de cada filtro (clave => etiqueta), tal como los define Drupal. */
export type FilterOptions = Record<FilterKey, Record<string, string>>;

export interface Titulacion {
  id: string;
  title: string;
  url: string;
  tipo: string;
  rama: string;
  modalidad: string;
  creditos: number;
  resumen: string;
}

/** Recurso node--titulacion de la API JSON:API de Drupal (solo los campos pedidos). */
export interface TitulacionResource {
  type: 'node--titulacion';
  id: string;
  attributes: {
    title: string;
    drupal_internal__nid: number;
    path?: { alias: string | null } | null;
    field_tipo: string;
    field_rama: string;
    field_modalidad: string;
    field_creditos: number;
    field_resumen: string | null;
  };
}

export interface JsonApiCollection<T> {
  data: T[];
  meta?: { count?: number | string };
}

export const EMPTY_FILTERS: Filters = { q: '', tipo: '', rama: '', modalidad: '' };
