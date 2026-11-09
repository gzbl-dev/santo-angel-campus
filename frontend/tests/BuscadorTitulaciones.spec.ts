import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import BuscadorTitulaciones from '../src/widgets/buscador/BuscadorTitulaciones.vue';
import type { FilterOptions, TitulacionResource } from '../src/widgets/buscador/types';

const options: FilterOptions = {
  tipo: { grado: 'Grado', master: 'Máster' },
  rama: { salud: 'Ciencias de la Salud', ingenieria: 'Ingeniería y Tecnología' },
  modalidad: { presencial: 'Presencial', online: 'Online' },
};

function resource(title: string, overrides: Partial<TitulacionResource['attributes']> = {}): TitulacionResource {
  return {
    type: 'node--titulacion',
    id: title,
    attributes: {
      title,
      drupal_internal__nid: 1,
      path: { alias: `/titulaciones/${title.toLowerCase().replace(/\s+/g, '-')}` },
      field_tipo: 'grado',
      field_rama: 'salud',
      field_modalidad: 'presencial',
      field_creditos: 240,
      field_resumen: 'Resumen',
      ...overrides,
    },
  };
}

function respondWith(data: TitulacionResource[]) {
  return vi.fn().mockResolvedValue(
    new Response(JSON.stringify({ data }), { status: 200, headers: { 'Content-Type': 'application/vnd.api+json' } }),
  );
}

describe('BuscadorTitulaciones', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/titulaciones');
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
  });

  it('pinta las titulaciones que devuelve la API con su etiqueta legible', async () => {
    vi.stubGlobal('fetch', respondWith([resource('Grado en Enfermería'), resource('Máster en IA', { field_tipo: 'master', field_modalidad: 'online' })]));

    const wrapper = mount(BuscadorTitulaciones, { props: { endpoint: '/jsonapi/node/titulacion', options } });
    await flushPromises();

    const cards = wrapper.findAll('.card');
    expect(cards).toHaveLength(2);
    expect(cards[1]!.find('.badge').text()).toBe('Máster');
    expect(cards[1]!.find('.card__meta').text()).toContain('Online');
    expect(wrapper.find('[role="status"]').text()).toBe('2 titulaciones');
  });

  it('al elegir un tipo vuelve a consultar la API con ese filtro y lo refleja en la URL', async () => {
    const fetchMock = respondWith([]);
    vi.stubGlobal('fetch', fetchMock);

    const wrapper = mount(BuscadorTitulaciones, { props: { endpoint: '/jsonapi/node/titulacion', options } });
    await flushPromises();
    await wrapper.find('input[type="radio"][value="master"]').setValue();
    await flushPromises();

    const lastUrl = String(fetchMock.mock.calls.at(-1)![0]);
    expect(decodeURIComponent(lastUrl)).toContain('filter[field_tipo]=master');
    expect(window.location.search).toBe('?tipo=master');
  });

  it('espera a que se deje de escribir antes de buscar por texto (debounce)', async () => {
    vi.useFakeTimers();
    const fetchMock = respondWith([]);
    vi.stubGlobal('fetch', fetchMock);

    const wrapper = mount(BuscadorTitulaciones, { props: { endpoint: '/jsonapi/node/titulacion', options } });
    await flushPromises();
    const input = wrapper.find('input[type="search"]');
    await input.setValue('inf');
    await input.setValue('info');
    await input.setValue('informática');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(300);
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('muestra un estado vacío y permite limpiar los filtros', async () => {
    vi.stubGlobal('fetch', respondWith([]));
    window.history.replaceState(null, '', '/titulaciones?q=astrofisica');

    const wrapper = mount(BuscadorTitulaciones, { props: { endpoint: '/jsonapi/node/titulacion', options } });
    await flushPromises();

    expect(wrapper.text()).toContain('Ninguna titulación coincide');
    await wrapper.find('.sab__clear').trigger('click');
    expect((wrapper.find('input[type="search"]').element as HTMLInputElement).value).toBe('');
  });

  it('si la API falla muestra un aviso con opción de reintentar', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 500 })));

    const wrapper = mount(BuscadorTitulaciones, { props: { endpoint: '/jsonapi/node/titulacion', options } });
    await flushPromises();

    expect(wrapper.find('[role="alert"]').text()).toContain('500');
    expect(wrapper.find('[role="alert"] button').text()).toBe('Reintentar');
  });

  it('en modo compacto limita a 6 resultados y enlaza al listado completo con los filtros', async () => {
    const many = Array.from({ length: 9 }, (_, i) => resource(`Titulación ${i + 1}`));
    vi.stubGlobal('fetch', respondWith(many));

    const wrapper = mount(BuscadorTitulaciones, {
      props: { endpoint: '/jsonapi/node/titulacion', options, compact: true, listUrl: '/titulaciones' },
    });
    await flushPromises();

    expect(wrapper.findAll('.card')).toHaveLength(6);
    expect(wrapper.find('[role="status"]').text()).toBe('Mostrando 6 de 9 titulaciones');
    expect(wrapper.find('select').exists()).toBe(false);
    await wrapper.find('input[type="radio"][value="grado"]').setValue();
    expect(wrapper.find('.sab__more a').attributes('href')).toBe('/titulaciones?tipo=grado');
  });
});
