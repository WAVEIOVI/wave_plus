<script setup>
/* eslint-disable camelcase */
import { WpSectionCard } from '@/components/shared'
import { buildAreaBreadcrumb } from '@/composables/client-assets/sites/areaHierarchyHelper'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  flattenedHierarchy: { type: Array, default: () => [] },
  siteAreasFlat: { type: Array, default: () => [] },
  siteEquipmentCount: { type: Number, default: 0 },
  selectedLocationAreaId: { type: [Number, String], default: null },
  includeSubAreas: { type: Boolean, default: true },
})

const emit = defineEmits([
  'update:selectedLocationAreaId',
  'update:includeSubAreas',
  'toggleExpand',
])

const { t } = useI18n()
const locationQuery = ref('')
const treeRef = ref(null)
const focusedAreaId = ref(null)

const isSearching = computed(() => locationQuery.value.trim().length > 0)

const resolveAreaTypeIcon = type => ({
  building: 'tabler-building',
  floor: 'tabler-stairs',
  zone: 'tabler-layout-dashboard',
  room: 'tabler-door',
  exterior: 'tabler-trees',
  roof: 'tabler-sun',
  basement: 'tabler-arrow-down-bar',
  other: 'tabler-map-pin',
}[type?.toLowerCase()] || 'tabler-hierarchy-2')

const matchesQuery = (area, q) => {
  const name = (localizedValue(area.name) || '').toLowerCase()
  const code = (area.code || '').toLowerCase()
  const type = (area.area_type || '').toLowerCase()

  return name.includes(q) || code.includes(q) || type.includes(q)
}

const parentPathLabel = areaId => {
  const crumbs = buildAreaBreadcrumb(areaId, props.siteAreasFlat)

  if (!Array.isArray(crumbs) || crumbs.length < 2)
    return ''

  return crumbs.slice(0, -1).map(c => localizedValue(c.name)).join(' › ')
}

const visibleAreas = computed(() => {
  const q = locationQuery.value.trim().toLowerCase()

  if (!q)
    return props.flattenedHierarchy

  // While searching, look through *every* area (collapsed branches included),
  // not only the rows that happen to be expanded in the tree.
  const source = props.siteAreasFlat.length ? props.siteAreasFlat : props.flattenedHierarchy
  const known = new Map(props.flattenedHierarchy.map(a => [a.id, a]))

  return source
    .filter(area => matchesQuery(area, q))
    .map(area => {
      const visible = known.get(area.id)

      return {
        ...area,
        depth: 0,
        hasChildren: false,
        isExpanded: false,
        equipment_count: visible?.equipment_count ?? area.equipment_count ?? visible?.totalEquipCount ?? area.totalEquipCount ?? 0,
        parentPath: parentPathLabel(area.id),
      }
    })
})

// Roving tabindex: exactly one node is in the tab order (focused → selected → first).
const tabStopId = computed(() => {
  const ids = visibleAreas.value.map(a => a.id)

  if (ids.includes(focusedAreaId.value))
    return focusedAreaId.value
  if (ids.includes(props.selectedLocationAreaId))
    return props.selectedLocationAreaId

  return ids[0] ?? null
})

const selectArea = areaId => {
  emit('update:selectedLocationAreaId', areaId)
}

const toggleSelect = area => {
  selectArea(props.selectedLocationAreaId === area.id ? null : area.id)
}

const areaCount = area => area.equipment_count ?? area.totalEquipCount ?? 0

const onNodeKeydown = (event, area) => {
  // Ignore keys coming from nested controls (chevron, actions menu).
  if (event.target !== event.currentTarget)
    return

  const items = Array.from(treeRef.value?.querySelectorAll('[role="treeitem"]') ?? [])
  const index = items.indexOf(event.currentTarget)
  const isRtl = getComputedStyle(event.currentTarget).direction === 'rtl'
  const expandKey = isRtl ? 'ArrowLeft' : 'ArrowRight'
  const collapseKey = isRtl ? 'ArrowRight' : 'ArrowLeft'
  const canToggle = area.hasChildren && !isSearching.value

  switch (event.key) {
  case 'Enter':
  case ' ':
    event.preventDefault()
    toggleSelect(area)
    break
  case 'ArrowDown':
    event.preventDefault()
    items[index + 1]?.focus()
    break
  case 'ArrowUp':
    event.preventDefault()
    items[index - 1]?.focus()
    break
  case 'Home':
    event.preventDefault()
    items[0]?.focus()
    break
  case 'End':
    event.preventDefault()
    items.at(-1)?.focus()
    break
  case expandKey:
    event.preventDefault()
    if (canToggle && !area.isExpanded)
      emit('toggleExpand', area.id)
    else if (canToggle)
      items[index + 1]?.focus()
    break
  case collapseKey:
    event.preventDefault()
    if (canToggle && area.isExpanded) {
      emit('toggleExpand', area.id)
    }
    else {
      const depth = Number(event.currentTarget.dataset.depth)
      const parent = items.slice(0, index).reverse().find(el => Number(el.dataset.depth) < depth)

      parent?.focus()
    }
    break
  }
}
</script>

<template>
  <div class="location-explorer-wrapper h-100">
    <WpSectionCard
      :title="$t('Locations')"
      :subtitle="$t('Filter inventory by zone')"
      icon="tabler-map-pin"
      icon-color="primary"
      class="h-100"
    >
      <div class="location-explorer">
        <div class="explorer-head">
          <AppTextField
            v-model="locationQuery"
            :placeholder="$t('Search zones...')"
            :aria-label="$t('Search zones...')"
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            class="mb-2"
          />

          <VSwitch
            :model-value="includeSubAreas"
            density="compact"
            hide-details
            color="primary"
            inset
            class="explorer-switch mb-2"
            :label="includeSubAreas ? $t('Including nested sub-areas') : $t('Exact location only')"
            :title="$t('Include Sub-areas')"
            @update:model-value="emit('update:includeSubAreas', $event)"
          />
        </div>

        <button
          type="button"
          class="explorer-facility"
          :class="{ 'is-active': !selectedLocationAreaId }"
          :aria-pressed="!selectedLocationAreaId"
          @click="selectArea(null)"
        >
          <VAvatar
            size="36"
            color="primary"
            variant="tonal"
            rounded
          >
            <VIcon
              icon="tabler-building-estate"
              size="18"
            />
          </VAvatar>
          <div class="min-w-0 flex-grow-1 text-start">
            <div class="text-body-2 font-weight-semibold">
              {{ $t('All Facility') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ siteEquipmentCount }} {{ $t('assets') }}
            </div>
          </div>
        </button>

        <div
          ref="treeRef"
          class="explorer-tree"
          role="tree"
          :aria-label="$t('Locations')"
        >
          <div
            v-for="area in visibleAreas"
            :key="`explorer-${area.id}`"
            role="treeitem"
            class="explorer-node"
            :class="{ 'is-active': selectedLocationAreaId === area.id }"
            :style="{ '--depth': area.depth }"
            :data-depth="area.depth"
            :tabindex="area.id === tabStopId ? 0 : -1"
            :aria-selected="selectedLocationAreaId === area.id"
            :aria-level="area.depth + 1"
            :aria-expanded="area.hasChildren && !isSearching ? area.isExpanded : undefined"
            @click="toggleSelect(area)"
            @focus="focusedAreaId = area.id"
            @keydown="onNodeKeydown($event, area)"
          >
            <span class="explorer-rail" />
            <IconBtn
              v-if="area.hasChildren && !isSearching"
              size="x-small"
              variant="text"
              class="explorer-chevron"
              tabindex="-1"
              :aria-label="area.isExpanded ? $t('Collapse') : $t('Expand')"
              @click.stop="emit('toggleExpand', area.id)"
            >
              <VIcon
                :icon="area.isExpanded ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                size="16"
                class="flip-in-rtl"
              />
            </IconBtn>
            <span
              v-else
              class="explorer-chevron-spacer"
            />
            <VIcon
              :icon="resolveAreaTypeIcon(area.area_type)"
              size="18"
              :color="selectedLocationAreaId === area.id ? 'primary' : 'medium-emphasis'"
            />
            <div class="min-w-0 flex-grow-1 text-start">
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ localizedValue(area.name) }}
              </div>
              <div class="text-caption text-medium-emphasis text-truncate text-capitalize">
                {{ area.area_type || t('Area') }}
                <template v-if="area.code">
                  · {{ area.code }}
                </template>
              </div>
              <div
                v-if="isSearching && area.parentPath"
                class="text-caption text-disabled text-truncate"
              >
                {{ area.parentPath }}
              </div>
            </div>
            <span
              class="explorer-count"
              :class="{ 'is-empty': !areaCount(area) }"
            >{{ areaCount(area) }}</span>
            <div
              v-if="$slots['node-actions']"
              class="explorer-node-actions"
              @click.stop
              @keydown.stop
            >
              <slot
                name="node-actions"
                :area="area"
              />
            </div>
          </div>

          <div
            v-if="!visibleAreas.length"
            class="text-center text-medium-emphasis py-6"
            role="status"
          >
            <VIcon
              icon="tabler-map-off"
              size="28"
              class="mb-2 opacity-50"
            />
            <div class="text-caption">
              {{ $t('No matching locations') }}
            </div>
          </div>
        </div>
      </div>
    </WpSectionCard>
  </div>
</template>

<style lang="scss" scoped>
.location-explorer {
  display: flex;
  flex-direction: column;
  block-size: 100%;
  min-block-size: 0;
}

.explorer-switch {
  :deep(.v-label) {
    font-size: 0.8125rem;
    opacity: 1;
  }
}

.explorer-facility,
.explorer-node {
  display: flex;
  position: relative;
  align-items: center;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: inherit;
  cursor: pointer;
  gap: 0.5rem;
  inline-size: 100%;
  padding-block: 0.55rem;
  padding-inline: 0.6rem;
  text-align: start;
  transition: background-color 0.15s ease, border-color 0.15s ease;

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
  }

  @media (prefers-reduced-motion: reduce) {
    transition: none;
  }
}

.explorer-facility {
  margin-block-end: 0.5rem;
  background: rgba(var(--v-theme-primary), 0.06);
}

.explorer-facility:hover,
.explorer-node:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.explorer-facility.is-active,
.explorer-node.is-active {
  border-color: rgba(var(--v-theme-primary), 0.35);
  background: rgba(var(--v-theme-primary), 0.14);
}

// Selected node gets a leading accent bar so state isn't conveyed by tint alone.
.explorer-node.is-active::before {
  position: absolute;
  border-radius: 3px;
  background: rgb(var(--v-theme-primary));
  content: "";
  inline-size: 3px;
  inset-block: 0.45rem;
  inset-inline-start: 0;
}

.explorer-tree {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.2rem;
  min-block-size: 0;
  overflow-y: auto;
  padding-block-end: 0.5rem;
}

.explorer-node {
  padding-inline-start: calc(0.4rem + (var(--depth, 0) * 1.05rem));
}

.explorer-rail {
  position: absolute;
  border-inline-start: 1px dashed rgba(var(--v-border-color), 0.35);
  inset-block: 0.35rem;
  inset-inline-start: calc(0.85rem + (var(--depth, 0) * 1.05rem));
  pointer-events: none;
}

.explorer-chevron {
  flex: 0 0 auto;
}

.explorer-chevron-spacer {
  display: inline-block;
  flex: 0 0 auto;
  inline-size: 28px;
}

// Neutral by default: warning-orange reads as "needs attention", not "count".
.explorer-count {
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  color: rgba(var(--v-theme-on-surface), 0.72);
  flex: 0 0 auto;
  font-size: 0.7rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  min-inline-size: 1.5rem;
  padding-block: 0.1rem;
  padding-inline: 0.4rem;
  text-align: center;

  &.is-empty {
    opacity: 0.55;
  }
}

.explorer-node.is-active .explorer-count {
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.explorer-node-actions {
  flex: 0 0 auto;
}

// Chevrons point the other way in RTL layouts.
[dir="rtl"] .flip-in-rtl,
.v-locale--is-rtl .flip-in-rtl {
  transform: scaleX(-1);
}
</style>
