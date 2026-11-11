import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { loadEnv } from 'vite';
import { defineConfig } from 'vitest/config';
import { mockJsonApi } from './dev/mock-jsonapi.ts';

const drupalDir = fileURLToPath(new URL('../drupal', import.meta.url));
const moduleDir = `${drupalDir}/web/modules/custom/sa_core`;

export default defineConfig(({ mode }) => {
  // DRUPAL_URL=http://localhost:8080 npm run dev  → usa el Drupal real.
  // Sin DRUPAL_URL                                → API simulada con los datos de sa_core/data.
  const drupalUrl = loadEnv(mode, process.cwd(), '').DRUPAL_URL;

  return {
    plugins: [vue(), ...(drupalUrl ? [] : [mockJsonApi(`${moduleDir}/data/titulaciones.json`)])],
    server: {
      // El asistente necesita el Drupal real (/api/asistente y /session/token).
      proxy: drupalUrl ? { '/jsonapi': drupalUrl, '/api': drupalUrl, '/session': drupalUrl } : undefined,
      fs: { allow: ['..'] },
    },
    build: {
      // Los widgets compilados se publican como librería del módulo de Drupal.
      outDir: `${moduleDir}/js/dist`,
      emptyOutDir: true,
      rollupOptions: {
        input: {
          buscador: fileURLToPath(new URL('src/widgets/buscador/main.ts', import.meta.url)),
          asistente: fileURLToPath(new URL('src/widgets/asistente/main.ts', import.meta.url)),
        },
        output: {
          entryFileNames: '[name].js',
          chunkFileNames: 'chunks/[name]-[hash].js',
          assetFileNames: '[name][extname]',
        },
      },
    },
    test: {
      environment: 'jsdom',
      include: ['tests/**/*.spec.ts'],
    },
  };
});
