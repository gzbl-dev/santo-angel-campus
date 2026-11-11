import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Asistente from '../src/widgets/asistente/Asistente.vue';
import { resetCsrfToken } from '../src/widgets/asistente/api';
import type { AssistantEvent, AssistantSettings } from '../src/widgets/asistente/types';
import { STORAGE_KEY } from '../src/widgets/asistente/useAsistente';

const settings: AssistantSettings = {
  endpoint: '/api/asistente',
  confirmEndpoint: '/api/asistente/confirmar',
  tokenEndpoint: '/session/token',
  welcome: '¡Hola! Soy el asistente.',
  suggestions: ['¿Qué másteres hay online?'],
  simulated: true,
};

function ndjson(events: AssistantEvent[]): Response {
  return new Response(events.map((event) => JSON.stringify(event)).join('\n') + '\n', {
    headers: { 'Content-Type': 'application/x-ndjson' },
  });
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

/** fetch falso: /session/token y después las respuestas indicadas, en orden. */
function mockFetch(...responses: Response[]) {
  const fetchMock = vi.fn((url: string) =>
    Promise.resolve(url === settings.tokenEndpoint ? new Response('csrf-123') : (responses.shift() ?? json({}, 500))),
  );
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

async function openAndAsk(question: string) {
  const wrapper = mount(Asistente, { props: { settings }, attachTo: document.body });
  await wrapper.get('.saa__launcher').trigger('click');
  await wrapper.get('textarea').setValue(question);
  await wrapper.get('form').trigger('submit');
  await flushPromises();
  return wrapper;
}

describe('Asistente', () => {
  beforeEach(() => {
    sessionStorage.clear();
    resetCsrfToken();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
  });

  it('streams the answer and links its sources', async () => {
    const fetchMock = mockFetch(
      ndjson([
        { type: 'tool', name: 'buscar_titulaciones', label: 'Consultando…' },
        { type: 'sources', items: [{ titulo: 'Máster en IA', url: '/titulaciones/master-ia', tipo: 'Titulación' }] },
        { type: 'delta', text: 'Tenemos 1 máster online:\n' },
        { type: 'delta', text: '- Máster en IA' },
        { type: 'done' },
      ]),
    );

    const wrapper = await openAndAsk('¿Qué másteres hay online?');

    const reply = wrapper.findAll('.saa__message--assistant').at(-1)!;
    expect(reply.find('p').text()).toBe('Tenemos 1 máster online:');
    expect(reply.find('li').text()).toBe('Máster en IA');
    expect(reply.get('.saa__sources a').attributes('href')).toBe('/titulaciones/master-ia');

    // Petición JSON con el token CSRF; el historial va vacío en el primer mensaje.
    const [, init] = fetchMock.mock.calls[1] as unknown as [string, RequestInit];
    expect((init.headers as Record<string, string>)['X-CSRF-Token']).toBe('csrf-123');
    expect(JSON.parse(init.body as string)).toEqual({ message: '¿Qué másteres hay online?', history: [] });
  });

  it('sends the previous turns as history', async () => {
    const fetchMock = mockFetch(
      ndjson([{ type: 'delta', text: 'Primera respuesta' }, { type: 'done' }]),
      ndjson([{ type: 'delta', text: 'Segunda' }, { type: 'done' }]),
    );
    const wrapper = await openAndAsk('Hola');
    await wrapper.get('textarea').setValue('¿Y becas?');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    const [, init] = fetchMock.mock.calls[2] as unknown as [string, RequestInit];
    expect(JSON.parse(init.body as string).history).toEqual([
      { role: 'user', text: 'Hola' },
      { role: 'assistant', text: 'Primera respuesta' },
    ]);
  });

  it('asks before opening an incidencia and sends it only on confirmation', async () => {
    const fetchMock = mockFetch(
      ndjson([
        { type: 'confirm', token: 'tok', tool: 'abrir_incidencia', input: { asunto: 'Wifi caída', descripcion: 'No conecta' } },
        { type: 'delta', text: 'Revísala.' },
        { type: 'done' },
      ]),
      json({ message: 'Incidencia n.º 7 registrada.' }),
    );
    const wrapper = await openAndAsk('Abre una incidencia: la wifi no va');

    expect(wrapper.get('.saa__action dd').text()).toBe('Wifi caída');
    expect(fetchMock).toHaveBeenCalledTimes(2);

    await wrapper.get('.saa__action .saa__button').trigger('click');
    await flushPromises();

    expect(fetchMock.mock.calls[2]![0]).toBe('/api/asistente/confirmar');
    expect(wrapper.get('.saa__action-result').text()).toBe('Incidencia n.º 7 registrada.');
    expect(wrapper.find('.saa__action-buttons').exists()).toBe(false);
  });

  it('shows the server error message (rate limit)', async () => {
    mockFetch(json({ error: 'Has enviado muchas preguntas seguidas.' }, 429));

    const wrapper = await openAndAsk('Hola');

    expect(wrapper.get('.saa__message--error').text()).toBe('Has enviado muchas preguntas seguidas.');
    expect(wrapper.get('[role="status"]').text()).toBe('Has enviado muchas preguntas seguidas.');
  });

  it('keeps the conversation when the page changes', async () => {
    mockFetch(ndjson([{ type: 'delta', text: 'Respuesta' }, { type: 'done' }]));
    (await openAndAsk('Hola')).unmount();

    const again = mount(Asistente, { props: { settings } });

    expect(JSON.parse(sessionStorage.getItem(STORAGE_KEY)!).open).toBe(true);
    expect(again.findAll('.saa__message--user').map((m) => m.text())).toEqual(['Hola']);
  });

  it('shows the waiting indicator only until the text starts arriving', async () => {
    let push!: (line: string) => void;
    let close!: () => void;
    const body = new ReadableStream<Uint8Array>({
      start(controller) {
        const encoder = new TextEncoder();
        push = (line) => controller.enqueue(encoder.encode(line + '\n'));
        close = () => controller.close();
      },
    });
    mockFetch(new Response(body, { headers: { 'Content-Type': 'application/x-ndjson' } }));
    const wrapper = await openAndAsk('¿Qué eventos hay?');

    push(JSON.stringify({ type: 'tool', name: 'proximos_eventos', label: 'Mirando la agenda…' }));
    await flushPromises();
    expect(wrapper.get('.saa__status').text()).toBe('Mirando la agenda…');

    push(JSON.stringify({ type: 'delta', text: 'Estos son ' }));
    await flushPromises();
    expect(wrapper.find('.saa__status').exists()).toBe(false);

    push(JSON.stringify({ type: 'done' }));
    close();
    await flushPromises();
  });

  it('labels the demo mode', async () => {
    const wrapper = mount(Asistente, { props: { settings } });
    await wrapper.get('.saa__launcher').trigger('click');

    expect(wrapper.get('[role="dialog"]').text()).toContain('Modo demo');
    expect(wrapper.get('.saa__launcher').attributes('aria-expanded')).toBe('true');
  });
});
