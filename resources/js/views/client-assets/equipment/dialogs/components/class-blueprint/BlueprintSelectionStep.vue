<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { idsEqual, unwrapList } from '@/composables/client-assets/sites/useSiteAreaTree'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  blueprints: {
    type: Array,
    default: () => [],
  },
  equipmentClasses: {
    type: Array,
    default: () => [],
  },
  selectedBlueprintId: {
    type: [String, Number],
    default: null,
  },
  pendingBlueprintId: {
    type: [String, Number],
    default: null,
  },
  activeClassId: {
    type: [String, Number],
    default: null,
  },
  activeClassObj: {
    type: Object,
    default: null,
  },
  effectiveFilterClassIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'selectBlueprint',
  'navigateBack',
  'resetClassFilter',
])

const { t } = useI18n()
const blueprintSearch = ref('')
const viewMode = ref('grid') // Responsive grid; list view remains available

const bpClass = bp => {
  if (!bp?.equipment_class_id) return null
  
  return props.equipmentClasses.find(c => c.id === bp.equipment_class_id) || null
}

const bpName = bp => localizedValue(bp?.name) || bp?.model_name || bp?.code || ''
const bpBrand = bp => (typeof bp?.brand === 'string' ? bp.brand : bp?.brand?.name || '')
const bpOptionCount = bp => unwrapList(bp?.blueprint_options || bp?.options).length

const filteredBlueprints = computed(() => {
  let list = props.blueprints

  if (props.effectiveFilterClassIds.length > 0) {
    const allowed = new Set(props.effectiveFilterClassIds)

    list = list.filter(b => allowed.has(b.equipment_class_id))
  }

  const q = (blueprintSearch.value || '').toLowerCase().trim()
  if (!q) return list

  return list.filter(bp => {
    const name = bpName(bp).toLowerCase()
    const code = String(bp.code || '').toLowerCase()
    const brand = bpBrand(bp).toLowerCase()
    const fireClass = String(bp.fire_class_rating || '').toLowerCase()
    const clsName = localizedValue(bpClass(bp)?.name || '').toLowerCase()

    return name.includes(q)
      || code.includes(q)
      || brand.includes(q)
      || fireClass.includes(q)
      || clsName.includes(q)
  })
})
</script>

<template>
  <div class="step-content">
    <div class="step-toolbar">
      <div class="blueprint-toolbar-row">
        <VTextField
          v-model="blueprintSearch"
          :placeholder="t('Search blueprints by model name, code, brand...')"
          prepend-inner-icon="tabler-search"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          class="flex-grow-1 toolbar-search"
        />

        <div class="blueprint-toolbar-actions">
          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            variant="outlined"
            color="warning"
          >
            <VBtn
              value="list"
              size="x-small"
              icon="tabler-list"
              :title="t('List View')"
              :aria-label="t('List View')"
            />
            <VBtn
              value="grid"
              size="x-small"
              icon="tabler-layout-grid"
              :title="t('Grid View')"
              :aria-label="t('Grid View')"
            />
          </VBtnToggle>

          <VChip
            size="small"
            :color="props.activeClassId ? 'warning' : 'secondary'"
            variant="tonal"
          >
            {{ filteredBlueprints.length }} {{ t('models found') }}
          </VChip>

          <VBtn
            size="small"
            variant="outlined"
            color="secondary"
            prepend-icon="tabler-filter"
            class="filter-btn"
            @click="emit('navigateBack')"
          >
            <span class="text-truncate">
              {{ props.activeClassObj ? (localizedValue(props.activeClassObj.name) || props.activeClassObj.code) : t('Filter by Class') }}
            </span>
          </VBtn>
        </div>
      </div>
    </div>

    <div class="blueprints-scroll-area"
      tabindex="0"
      :aria-label="t('Blueprint Model')">
      <div
        v-if="filteredBlueprints.length === 0"
        class="empty-state"
      >
        <VIcon
          icon="tabler-blueprint-off"
          size="42"
          class="mb-2 opacity-40"
        />
        <p class="text-body-2 mb-1">
          {{ props.activeClassId ? t('No blueprints available for the selected class.') : t('No blueprints match your search criteria.') }}
        </p>
        <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
          <VBtn
            v-if="blueprintSearch"
            size="small"
            variant="tonal"
            color="warning"
            @click="blueprintSearch = ''"
          >
            {{ t('Clear search') }}
          </VBtn>
          <VBtn
            v-if="props.activeClassId"
            size="small"
            variant="tonal"
            color="secondary"
            @click="emit('resetClassFilter')"
          >
            {{ t('Show all classes') }}
          </VBtn>
        </div>
      </div>

      <TransitionGroup
        v-else
        name="row"
        tag="div"
        class="blueprint-cards-grid"
        :class="viewMode === 'grid' ? 'blueprint-cards-grid--grid' : 'blueprint-cards-grid--list'"
      >
        <button
          v-for="bp in filteredBlueprints"
          :key="bp.id"
          type="button"
          class="blueprint-card"
          :class="{
            'is-selected': idsEqual(props.selectedBlueprintId, bp.id),
            'is-pending': idsEqual(props.pendingBlueprintId, bp.id),
          }"
          :disabled="Boolean(props.pendingBlueprintId) && !idsEqual(props.pendingBlueprintId, bp.id)"
          :aria-pressed="idsEqual(props.selectedBlueprintId, bp.id)"
          :aria-busy="idsEqual(props.pendingBlueprintId, bp.id)"
          @click="emit('selectBlueprint', bp, true)"
        >
          <div class="blueprint-card-content">
            <VAvatar
              :color="idsEqual(props.selectedBlueprintId, bp.id) ? 'warning' : 'surface-variant'"
              :variant="idsEqual(props.selectedBlueprintId, bp.id) ? 'elevated' : 'tonal'"
              size="34"
              rounded="lg"
              class="flex-shrink-0"
            >
              <VProgressCircular
                v-if="idsEqual(props.pendingBlueprintId, bp.id)"
                indeterminate
                size="18"
                width="2"
                color="warning"
              />
              <VIcon
                v-else
                icon="tabler-blueprint"
                size="18"
              />
            </VAvatar>

            <div class="flex-grow-1 min-w-0 text-start">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="blueprint-name font-weight-bold text-body-2">{{ bpName(bp) }}</span>
                <VChip
                  v-if="bp.code"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ bp.code }}
                </VChip>
              </div>

              <div class="text-caption text-medium-emphasis mb-1">
                <span v-if="bpClass(bp)">
                  {{ localizedValue(bpClass(bp).name) || bpClass(bp).code }}
                </span>
                <span v-if="bpBrand(bp)">
                  <template v-if="bpClass(bp)"> · </template><strong>{{ t('Brand') }}:</strong> {{ bpBrand(bp) }}
                </span>
                <span v-if="bp.shelf_life_years">
                  · {{ bp.shelf_life_years }} {{ t('years shelf life') }}
                </span>
              </div>

              <div class="d-flex align-center gap-2 flex-wrap">
                <VChip
                  v-if="bp.fire_class_rating"
                  size="x-small"
                  color="error"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-flame"
                    size="11"
                    start
                  />
                  {{ bp.fire_class_rating }}
                </VChip>
                <VChip
                  v-if="bp.working_pressure_bar"
                  size="x-small"
                  color="info"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-gauge"
                    size="11"
                    start
                  />
                  {{ bp.working_pressure_bar }} bar
                </VChip>
                <VChip
                  size="x-small"
                  :color="bpOptionCount(bp) ? 'primary' : 'secondary'"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-adjustments"
                    size="11"
                    start
                  />
                  {{ bpOptionCount(bp) }} {{ t('configurable options') }}
                </VChip>
              </div>
            </div>

            <div class="d-flex flex-column align-end justify-space-between gap-2 flex-shrink-0 card-trailing">
              <Transition name="pop">
                <VIcon
                  v-if="idsEqual(props.selectedBlueprintId, bp.id)"
                  icon="tabler-circle-check-filled"
                  color="warning"
                  size="24"
                />
              </Transition>
              <span class="card-cta">
                {{ idsEqual(props.selectedBlueprintId, bp.id) ? t('Configure') : t('Select') }}
                <VIcon
                  icon="tabler-chevron-right"
                  size="14"
                  class="flip-rtl"
                />
              </span>
            </div>
          </div>
        </button>
      </TransitionGroup>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.step-content {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-inline-size: 0;
  min-block-size: 0;
  block-size: clamp(16rem, 52vh, 36rem);
  block-size: clamp(16rem, 52dvh, 36rem);
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  container-type: inline-size;
}

.step-toolbar {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  gap: 0.5rem;
  padding: 0.625rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.min-w-0 { min-inline-size: 0; }

.empty-state {
  display: flex;
  align-items: center;
  flex-direction: column;
  justify-content: center;
  gap: 0.25rem;
  min-block-size: 10rem;
  padding: 1.25rem 0.75rem;
  border: 1px dashed rgba(var(--v-border-color), 0.35);
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  text-align: center;
}

.flip-rtl {
  .v-locale--is-rtl & { transform: scaleX(-1); }
}

@media (min-width: 960px) {
  .step-content {
    block-size: clamp(18rem, 54vh, 38rem);
    block-size: clamp(18rem, 54dvh, 38rem);
  }
}

@media (max-height: 500px) {
  .step-content {
    block-size: 58vh;
    block-size: 58dvh;
    min-block-size: 10rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}

.blueprint-toolbar-row,
.blueprint-toolbar-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-inline-size: 0;
}
.step-toolbar :deep(.v-btn-toggle) { flex: 0 0 auto; block-size: 36px; }
.step-toolbar :deep(.v-btn-toggle > .v-btn) { min-inline-size: 36px; inline-size: 36px; block-size: 36px; padding: 0; }
.toolbar-search { flex: 1 1 100%; min-inline-size: 0; }
.blueprint-toolbar-actions { flex: 1 1 auto; }
.filter-btn { min-inline-size: 0; max-inline-size: 100%; margin-inline-start: auto; }
.filter-btn :deep(.v-btn__content) { min-inline-size: 0; }
.blueprints-scroll-area {
  flex: 1 1 auto;
  min-block-size: 0;
  padding: 0.625rem;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
  scroll-padding-block: 0.75rem;
}
.blueprint-cards-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  &--grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-content: start;
    align-items: stretch;
  }
}
.blueprint-card {
  inline-size: 100%;
  min-inline-size: 0;
  padding: 0.75rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  text-align: start;
  cursor: pointer;
  transition: border-color 150ms ease, background-color 150ms ease;
  &:hover:not(:disabled) {
    border-color: rgba(var(--v-theme-warning), 0.6);
    background: rgba(var(--v-theme-warning), 0.035);
  }
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  &:disabled { cursor: wait; opacity: 0.55; }
  &.is-selected {
    border-color: rgb(var(--v-theme-warning));
    background: rgba(var(--v-theme-warning), 0.075);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-warning));
  }
  &.is-pending { cursor: progress; }
}
.blueprint-card-content { display: flex; align-items: flex-start; gap: 0.625rem; }
.blueprint-name { overflow-wrap: anywhere; }
.card-cta {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  color: rgb(var(--v-theme-warning));
  font-size: 0.75rem;
  font-weight: 600;
}
@container (min-width: 640px) {
  .blueprint-cards-grid--grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container (min-width: 840px) {
  .toolbar-search { flex-basis: 16rem; }
  .blueprint-toolbar-actions { flex: 0 1 auto; }
}
@container (min-width: 1120px) {
  .blueprint-cards-grid--grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@container (max-width: 380px) {
  .blueprint-card-content { flex-wrap: wrap; }
  .blueprint-card-content > .min-w-0 { flex-basis: calc(100% - 3rem); }
  .card-trailing { flex-direction: row !important; margin-inline-start: auto; }
  .filter-btn { flex: 1 1 100%; margin-inline-start: 0; }
}
</style>
