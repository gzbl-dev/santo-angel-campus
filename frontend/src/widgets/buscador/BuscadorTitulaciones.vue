<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { filtersFromSearch, filtersToSearch } from './jsonapi';
import { EMPTY_FILTERS, type FilterKey, type FilterOptions, type Filters } from './types';
import { useTitulaciones } from './useTitulaciones';

const props = withDefaults(
  defineProps<{
    endpoint: string;
    options: FilterOptions;
    /** Versión de portada: solo texto y tipo, y como mucho 6 resultados. */
    compact?: boolean;
    /** Página con el listado completo (enlace "Ver todas"). */
    listUrl?: string;
  }>(),
  { compact: false, listUrl: '/titulaciones' },
);

const COMPACT_LIMIT = 6;
const uid = useId();

// En la página completa los filtros viven en la URL: se pueden compartir y
// sobreviven a recargar o volver atrás.
const filters = ref<Filters>(
  props.compact ? { ...EMPTY_FILTERS } : filtersFromSearch(window.location.search, EMPTY_FILTERS),
);

const { items, loading, error, reload } = useTitulaciones(props.endpoint, filters);

const visible = computed(() => (props.compact ? items.value.slice(0, COMPACT_LIMIT) : items.value));
const hasFilters = computed(() => Object.values(filters.value).some((value) => value.trim() !== ''));
const headingTag = computed(() => (props.compact ? 'h3' : 'h2'));
const fullListUrl = computed(() => props.listUrl + filtersToSearch(filters.value));

const statusText = computed(() => {
  if (loading.value) {
    return 'Buscando…';
  }
  const total = items.value.length;
  if (props.compact && total > COMPACT_LIMIT) {
    return `Mostrando ${COMPACT_LIMIT} de ${total} titulaciones`;
  }
  return total === 1 ? '1 titulación' : `${total} titulaciones`;
});

if (!props.compact) {
  watch(
    filters,
    (current) => {
      const { pathname, hash } = window.location;
      window.history.replaceState(window.history.state, '', pathname + filtersToSearch(current) + hash);
    },
    { deep: true },
  );
}

function label(key: FilterKey, value: string): string {
  return props.options[key]?.[value] ?? value;
}

function clearFilters(): void {
  filters.value = { ...EMPTY_FILTERS };
}
</script>

<template>
  <div class="sab" :class="{ 'sab--compact': compact }">
    <form class="sab__toolbar" role="search" @submit.prevent>
      <div class="sab__field sab__field--search">
        <label class="sab__label" :for="`${uid}-q`">Buscar titulación</label>
        <div class="sab__input-wrap">
          <svg class="sab__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24-1.42 1.42-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
          </svg>
          <input
            :id="`${uid}-q`"
            v-model="filters.q"
            class="sab__input"
            type="search"
            placeholder="Informática, enfermería, máster…"
            autocomplete="off"
          />
        </div>
      </div>

      <fieldset class="sab__field sab__chips">
        <legend class="sab__label">Tipo de estudio</legend>
        <label class="sab__chip">
          <input v-model="filters.tipo" type="radio" :name="`${uid}-tipo`" value="" />
          <span>Todos</span>
        </label>
        <label v-for="(text, value) in options.tipo" :key="value" class="sab__chip">
          <input v-model="filters.tipo" type="radio" :name="`${uid}-tipo`" :value="value" />
          <span>{{ text }}</span>
        </label>
      </fieldset>

      <template v-if="!compact">
        <div class="sab__field">
          <label class="sab__label" :for="`${uid}-rama`">Rama de conocimiento</label>
          <select :id="`${uid}-rama`" v-model="filters.rama" class="sab__select">
            <option value="">Todas</option>
            <option v-for="(text, value) in options.rama" :key="value" :value="value">{{ text }}</option>
          </select>
        </div>
        <div class="sab__field">
          <label class="sab__label" :for="`${uid}-modalidad`">Modalidad</label>
          <select :id="`${uid}-modalidad`" v-model="filters.modalidad" class="sab__select">
            <option value="">Todas</option>
            <option v-for="(text, value) in options.modalidad" :key="value" :value="value">{{ text }}</option>
          </select>
        </div>
      </template>
    </form>

    <div class="sab__status">
      <p class="sab__count" role="status" aria-live="polite">{{ statusText }}</p>
      <button v-if="hasFilters" type="button" class="sab__clear" @click="clearFilters">Limpiar filtros</button>
    </div>

    <div v-if="error" class="sab__message" role="alert">
      <p>No hemos podido cargar las titulaciones. {{ error }}</p>
      <button type="button" class="button" @click="reload">Reintentar</button>
    </div>

    <ul v-else-if="loading && items.length === 0" class="card-grid sab__list" aria-hidden="true">
      <li v-for="n in 3" :key="n"><div class="card sab__skeleton" /></li>
    </ul>

    <div v-else-if="items.length === 0" class="sab__message">
      <p>Ninguna titulación coincide con tu búsqueda.</p>
      <button type="button" class="button button--ghost" @click="clearFilters">Ver todas</button>
    </div>

    <ul v-else class="card-grid sab__list" :class="{ 'is-loading': loading }">
      <li v-for="t in visible" :key="t.id">
        <article class="card">
          <div class="card__body">
            <span class="badge" :class="`badge--${t.rama}`">{{ label('tipo', t.tipo) }}</span>
            <component :is="headingTag" class="card__title">
              <a class="card__link" :href="t.url">{{ t.title }}</a>
            </component>
            <p v-if="t.resumen" class="card__summary">{{ t.resumen }}</p>
            <p class="card__meta">{{ label('modalidad', t.modalidad) }} · {{ t.creditos }} ECTS</p>
          </div>
        </article>
      </li>
    </ul>

    <p v-if="compact && !error" class="sab__more">
      <a class="button button--ghost" :href="fullListUrl">Ver todas las titulaciones</a>
    </p>
  </div>
</template>

<style>
/* Usa los tokens del tema (custom properties) con valores de reserva. */
.sab {
  --_line: var(--sa-line, #e7dfd4);
  --_accent: var(--sa-granate, #7a1f2b);
  --_soft: var(--sa-ink-soft, #5b524c);
  --_surface: var(--sa-surface, #fff);
}

.sab__toolbar {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
  border: 1px solid var(--_line);
  border-radius: var(--sa-radius, 14px);
  background: var(--_surface);
}

@media (min-width: 48rem) {
  .sab__toolbar {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr);
    grid-auto-flow: row dense;
    align-items: end;
  }

  .sab__chips {
    grid-column: 1 / -1;
  }

  .sab--compact .sab__toolbar {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .sab--compact .sab__chips {
    grid-column: auto;
  }
}

.sab__field {
  display: grid;
  gap: 0.4rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.sab__label {
  padding: 0;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--_soft);
}

.sab__input-wrap {
  position: relative;
}

.sab__icon {
  position: absolute;
  top: 50%;
  left: 0.9rem;
  width: 1.15rem;
  height: 1.15rem;
  fill: var(--_soft);
  transform: translateY(-50%);
  pointer-events: none;
}

.sab__input,
.sab__select {
  width: 100%;
  min-height: 2.875rem;
  padding: 0.6rem 0.9rem;
  border: 1px solid var(--_line);
  border-radius: var(--sa-radius-sm, 8px);
  background: var(--sa-paper, #faf7f2);
  color: inherit;
  font: inherit;
}

.sab__input {
  padding-left: 2.6rem;
}

.sab__input:focus,
.sab__select:focus {
  border-color: var(--_accent);
  outline: 3px solid color-mix(in srgb, var(--_accent) 20%, transparent);
  outline-offset: 0;
}

.sab__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.sab__chips .sab__label {
  flex-basis: 100%;
}

.sab__chip input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.sab__chip span {
  display: inline-block;
  padding: 0.5rem 1rem;
  border: 1px solid var(--_line);
  border-radius: 999px;
  background: var(--_surface);
  font-size: 0.92rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.sab__chip span:hover {
  border-color: var(--_accent);
}

.sab__chip input:checked + span {
  background: var(--_accent);
  border-color: var(--_accent);
  color: #fff;
}

.sab__chip input:focus-visible + span {
  outline: 3px solid var(--sa-ocre, #d99a2b);
  outline-offset: 2px;
}

.sab__status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin: 1.25rem 0 1rem;
}

.sab__count {
  margin: 0;
  color: var(--_soft);
  font-weight: 500;
}

.sab__clear {
  padding: 0;
  border: 0;
  background: none;
  color: var(--_accent);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 0.18em;
  cursor: pointer;
}

.sab__list {
  margin: 0;
  padding: 0;
  list-style: none;
  transition: opacity 0.2s ease;
}

.sab__list.is-loading {
  opacity: 0.55;
}

.sab__list > li {
  display: flex;
}

.sab__list .card {
  flex: 1;
}

.sab__skeleton {
  min-height: 11rem;
  background: linear-gradient(90deg, var(--_surface) 25%, var(--sa-cream, #f3ece1) 50%, var(--_surface) 75%);
  background-size: 200% 100%;
  animation: sab-shimmer 1.2s linear infinite;
}

@keyframes sab-shimmer {
  to {
    background-position: -200% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sab__skeleton {
    animation: none;
  }
}

.sab__message {
  padding: 2rem;
  border: 1px dashed var(--_line);
  border-radius: var(--sa-radius, 14px);
  text-align: center;
}

.sab__message p {
  margin: 0 0 1rem;
}

.sab__more {
  margin: 1.75rem 0 0;
}
</style>
