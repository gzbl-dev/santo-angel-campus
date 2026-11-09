# Universidad Santo Ángel · Portal web + IA

Portal de una universidad **ficticia** (Santo Ángel es mi pueblo, en Murcia) construido sobre **Drupal 11**, con un tema a medida en **Twig + Sass**, widgets en **Vue 3 + TypeScript** que consumen la API **JSON:API** de Drupal, y todo el entorno en **Docker**.

El proyecto crece por fases. Esta primera fase es la **base del portal**. Las siguientes añaden un **asistente de IA con RAG y tool use**, un **servidor MCP** que expone el contenido del portal a agentes, y **automatizaciones** con n8n y Bash (ver [Hoja de ruta](#hoja-de-ruta)).


https://github.com/user-attachments/assets/df4bee5a-b186-4d0a-a73a-77ea7ec6eb7b


> **Demo en vídeo (1:35):** portada, buscador con filtros, fichas, agenda, preguntas frecuentes y el panel de Drupal creando una titulación que aparece al momento en el buscador.

## Qué incluye

| Área | Implementación |
|---|---|
| **Drupal 11** | Módulo propio `sa_core`: tipos de contenido (Titulación, Evento, FAQ) definidos como configuración YAML, rutas y controladores, servicios con inyección de dependencias, dos bloques configurables, metadatos de caché (tags y max-age) y un comando Drush propio (`drush sa:seed`). |
| **Theming** | Tema `santoangel` sin tema base: plantillas Twig (página, hero, tarjetas, fichas), preprocess en `santoangel.theme`, colocación de bloques por configuración, Sass modular con mapas, mixins, funciones y bucles `@each`. Los tokens de diseño se exponen como custom properties para que Vue use los mismos valores. |
| **Vue 3 + TS** | Buscador de titulaciones montado sobre un bloque de Drupal: filtros por texto, tipo, rama y modalidad traducidos a filtros JSON:API, *debounce*, cancelación de peticiones con `AbortController`, filtros sincronizados con la URL, y estados de carga, vacío y error. |
| **Accesibilidad** | Mejora progresiva (sin JS se ve la lista de titulaciones), `role="status"` con `aria-live` para el número de resultados, `<details>` nativos en las FAQ, foco visible, `prefers-reduced-motion` y navegación móvil con `aria-expanded` y cierre con Escape. |
| **Extras** | Descarga de eventos en formato iCalendar (`.ics`, RFC 5545) para añadirlos al calendario. |
| **Calidad** | 14 tests con Vitest y Vue Test Utils, `vue-tsc` en modo estricto, `phpcs` con los estándares Drupal y DrupalPractice, y un CI en GitHub Actions que instala el sitio completo y hace pruebas de humo por HTTP. |
| **DevOps** | Docker Compose (Drupal + MariaDB), `settings.php` sin secretos (todo por variables de entorno) y scripts Bash de instalación, copia de seguridad con rotación y despliegue (`drush deploy`). |

<table>
  <tr>
    <td colspan="2"><img src="docs/capturas/portada.png" alt="Portada"></td>
  </tr>
  <tr>
    <td><img src="docs/capturas/titulaciones.png" alt="Buscador de titulaciones con filtros"></td>
    <td><img src="docs/capturas/ficha-titulacion.png" alt="Ficha de una titulación"></td>
  </tr>
  <tr>
    <td><img src="docs/capturas/preguntas-frecuentes.png" alt="Preguntas frecuentes"></td>
    <td><img src="docs/capturas/movil.png" alt="Portada en móvil" width="260"></td>
  </tr>
</table>

## Puesta en marcha

Requisitos: Docker Desktop y Node.js 20 o superior.

```bash
cp .env.example .env

# 1. Compilar el tema (Sass) y los widgets (Vue)
cd frontend && npm install && npm run build && cd ..

# 2. Levantar Drupal + MariaDB
docker compose up -d --build

# 3. Instalar el sitio: Drupal en español, módulo, tema y contenido de demostración
docker compose exec drupal bash scripts/install.sh
```

Abre <http://localhost:8080>. El panel de administración está en `/user/login` (admin / admin).

| Comando | Para qué |
|---|---|
| `docker compose exec drupal drush sa:seed --reset` | Regenera el contenido de demostración. Las fechas de los eventos se recalculan desde hoy. |
| `docker compose exec drupal bash scripts/backup.sh` | Copia de la base de datos y los ficheros, conservando las 7 últimas. |
| `docker compose exec drupal bash scripts/deploy.sh` | Copia de seguridad, `composer install --no-dev` y `drush deploy`. |
| `cd frontend && npm run dev` | Entorno de desarrollo del widget con una API simulada (no necesita Drupal). |
| `cd frontend && npm test` | Tests del widget. |
| `cd drupal && composer lint` | Estándares de código de Drupal. |

## Estructura

```
├── docker-compose.yml
├── drupal/                          Proyecto Composer (drupal/recommended-project)
│   ├── Dockerfile
│   ├── scripts/                     install.sh · backup.sh · deploy.sh
│   └── web/
│       ├── modules/custom/sa_core/
│       │   ├── config/install/      Tipos de contenido y campos (YAML)
│       │   ├── data/                Contenido de demostración (JSON)
│       │   ├── src/                 Controlador, servicios, bloques, comando Drush
│       │   └── templates/           Plantillas Twig del módulo
│       └── themes/custom/santoangel/
│           ├── scss/                abstracts · base · layout · components
│           └── templates/           page, hero, node, tarjetas, marca
├── frontend/                        Vue 3 + TypeScript + Vite + Vitest
│   ├── src/widgets/buscador/
│   ├── tests/
│   └── dev/                         API JSON:API simulada para desarrollo
└── .github/workflows/ci.yml
```

## Decisiones técnicas

- **Configuración como código.** Los tipos de contenido y los campos se definen en YAML dentro del módulo. Las pantallas de formulario y visualización se configuran en `hook_install()`, y la colocación de bloques viene en el tema. Una instalación limpia deja el sitio idéntico siempre.
- **JSON:API del core en vez de endpoints a medida.** El widget pide solo los campos que necesita (*sparse fieldsets*) y filtra en el servidor. El comportamiento se puede probar con `curl` sin tocar código PHP.
- **Vue como isla, no como SPA.** Drupal sigue sirviendo el HTML (SEO, caché de página, accesibilidad sin JS) y Vue se monta solo donde aporta interactividad. Así es como se suele integrar JavaScript moderno en portales grandes ya existentes.
- **Una sola fuente de datos de demostración.** Los JSON de `sa_core/data` alimentan `drush sa:seed`, la API simulada del entorno de desarrollo y, en la fase 2, el índice del asistente de IA.

## Cómo he usado la IA en este proyecto

He desarrollado el proyecto con un asistente de programación con IA (Claude Code). Mi forma de trabajar es la misma que aplicaría en un equipo: la IA propone, y yo reviso, ejecuto y valido antes de dar algo por bueno. Algunos fallos que salieron al **probar de verdad** el código generado, y que no se veían leyéndolo:

- La inyección de dependencias por tipo (*autowiring*) fallaba con el servicio de transliteración, porque el core no registra un alias para su interfaz. Se resolvió enlazando el argumento de forma explícita en `sa_core.services.yml`.
- Drupal 11.4 arranca con `symfony/runtime`. Desactivar ese plugin de Composer rompía `index.php`, aunque Drush seguía funcionando.
- El instalador de Drupal añade credenciales fijas a `settings.php`. El script de instalación ahora lo protege y lo restaura.
- Sin tema base, el core no pone la clase `.menu` en los menús, así que el CSS previsto no se aplicaba. Lo detecté con capturas reales del navegador.

Por eso el CI no solo pasa linters: **instala el sitio completo y comprueba las páginas por HTTP**.

## Hoja de ruta

- [x] **Fase 1 · Portal.** Drupal 11, tema Twig + Sass, widget Vue con JSON:API, Docker, scripts Bash y CI.
- [ ] **Fase 2 · Asistente IA.** Chat integrado en el portal que responde con RAG sobre las FAQ y las titulaciones, citando la fuente, y usa herramientas (*tool use*) para buscar titulaciones, consultar la agenda o abrir una incidencia.
- [ ] **Fase 3 · Agentes y automatización.** Servidor MCP que expone el contenido de Drupal a cualquier agente, flujo n8n que clasifica las consultas del formulario de contacto con un LLM y las envía al servicio que corresponde, y despliegue continuo.

---

Proyecto personal de [Gonzalo Bernal López](https://github.com/gzbl-dev). Universidad, datos, correos y personas son ficticios.
