import { describe, expect, it } from 'vitest';
import {
  buildQuery,
  filtersFromSearch,
  filtersToSearch,
  toTitulacion,
} from '../src/widgets/buscador/jsonapi';
import { EMPTY_FILTERS, type TitulacionResource } from '../src/widgets/buscador/types';

describe('buildQuery', () => {
  it('sin filtros pide solo titulaciones publicadas, ordenadas y con sparse fieldsets', () => {
    const params = buildQuery(EMPTY_FILTERS);

    expect(params.get('filter[status]')).toBe('1');
    expect(params.get('sort')).toBe('title');
    expect(params.get('fields[node--titulacion]')).toContain('field_rama');
    expect([...params.keys()].some((key) => key.startsWith('filter[texto]'))).toBe(false);
  });

  it('convierte el texto en una condición CONTAINS sobre el título, sin espacios sobrantes', () => {
    const params = buildQuery({ ...EMPTY_FILTERS, q: '  informática ' });

    expect(params.get('filter[texto][condition][path]')).toBe('title');
    expect(params.get('filter[texto][condition][operator]')).toBe('CONTAINS');
    expect(params.get('filter[texto][condition][value]')).toBe('informática');
  });

  it('ignora un texto formado solo por espacios', () => {
    expect(buildQuery({ ...EMPTY_FILTERS, q: '   ' }).has('filter[texto][condition][value]')).toBe(false);
  });

  it('añade un filtro de igualdad por cada lista seleccionada', () => {
    const params = buildQuery({ ...EMPTY_FILTERS, tipo: 'master', modalidad: 'online' });

    expect(params.get('filter[field_tipo]')).toBe('master');
    expect(params.get('filter[field_modalidad]')).toBe('online');
    expect(params.has('filter[field_rama]')).toBe(false);
  });
});

describe('toTitulacion', () => {
  const resource: TitulacionResource = {
    type: 'node--titulacion',
    id: 'uuid-1',
    attributes: {
      title: 'Grado en Enfermería',
      drupal_internal__nid: 7,
      path: { alias: '/titulaciones/grado-enfermeria' },
      field_tipo: 'grado',
      field_rama: 'salud',
      field_modalidad: 'presencial',
      field_creditos: 240,
      field_resumen: null,
    },
  };

  it('usa el alias de URL cuando existe', () => {
    expect(toTitulacion(resource).url).toBe('/titulaciones/grado-enfermeria');
  });

  it('cae a /node/{nid} si no hay alias y convierte el resumen nulo en texto vacío', () => {
    const titulacion = toTitulacion({
      ...resource,
      attributes: { ...resource.attributes, path: { alias: null } },
    });

    expect(titulacion.url).toBe('/node/7');
    expect(titulacion.resumen).toBe('');
  });
});

describe('filtros en la URL', () => {
  it('ida y vuelta: solo serializa los filtros activos', () => {
    const filters = { ...EMPTY_FILTERS, q: 'máster', rama: 'deporte' };
    const search = filtersToSearch(filters);

    expect(search).toBe('?q=m%C3%A1ster&rama=deporte');
    expect(filtersFromSearch(search, EMPTY_FILTERS)).toEqual(filters);
  });

  it('sin filtros activos devuelve una cadena vacía', () => {
    expect(filtersToSearch(EMPTY_FILTERS)).toBe('');
  });
});
