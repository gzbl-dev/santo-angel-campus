import { readFileSync } from 'node:fs';
import type { Plugin } from 'vite';

interface SeedTitulacion {
  slug: string;
  title: string;
  tipo: string;
  rama: string;
  modalidad: string;
  creditos: number;
  resumen: string;
}

const normalize = (text: string): string =>
  text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

/**
 * API JSON:API simulada para desarrollar el widget sin Drupal.
 *
 * Sirve /jsonapi/node/titulacion a partir de los mismos JSON con los que
 * `drush sa:seed` llena Drupal, y aplica los filtros igual que la API real
 * (CONTAINS insensible a mayúsculas y tildes, como la colación de MariaDB).
 */
export function mockJsonApi(seedPath: string): Plugin {
  return {
    name: 'sa-mock-jsonapi',
    configureServer(server) {
      server.middlewares.use('/jsonapi/node/titulacion', (req, res) => {
        const params = new URL(req.url ?? '/', 'http://localhost').searchParams;
        const seed = JSON.parse(readFileSync(seedPath, 'utf-8')) as SeedTitulacion[];
        const text = normalize(params.get('filter[texto][condition][value]') ?? '');

        const data = seed
          .filter((t) => !text || normalize(t.title).includes(text))
          .filter((t) => ['tipo', 'rama', 'modalidad'].every((key) => {
            const wanted = params.get(`filter[field_${key}]`);
            return !wanted || t[key as keyof SeedTitulacion] === wanted;
          }))
          .sort((a, b) => a.title.localeCompare(b.title, 'es'))
          .map((t, index) => ({
            type: 'node--titulacion',
            id: `mock-${t.slug}`,
            attributes: {
              title: t.title,
              drupal_internal__nid: index + 1,
              path: { alias: `/titulaciones/${t.slug}` },
              field_tipo: t.tipo,
              field_rama: t.rama,
              field_modalidad: t.modalidad,
              field_creditos: t.creditos,
              field_resumen: t.resumen,
            },
          }));

        res.setHeader('Content-Type', 'application/vnd.api+json');
        // Pequeño retardo para ver los estados de carga.
        setTimeout(() => res.end(JSON.stringify({ data, meta: { count: data.length } })), 350);
      });
    },
  };
}
