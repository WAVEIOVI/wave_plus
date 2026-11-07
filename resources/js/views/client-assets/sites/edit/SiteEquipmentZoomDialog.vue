<script setup>
/* eslint-disable camelcase */
/**
 * SiteEquipmentZoomDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * WAVE PLUS Standard Zoom Dialog for Equipment Inventory Exploration.
 * Uses WpZoomDialog as the standard fullscreen/large container.
 * Features collapsible zone hierarchy sidebar, standard SiteAreaSearchDialog,
 * multi-criteria filtering via SiteEquipmentFilterBar (WpFilterDialog),
 * cards/table view toggle, quick KPI badges, and full mobile-first support.
 */
import { AppHybridTabs, WpEmptyState,
  WpPagination,
  WpZoomDialog, } from '@/components/shared'
import {
  brandLabel,
  classLabel,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { getAllDescendantAreaIds } from '@/composables/client-assets/sites/areaHierarchyHelper'
import { localizedValue } from '@/composables/useLocaleField'
import SiteEquipmentCard from '@/views/client-assets/sites/components/SiteEquipmentCard.vue'
import SiteEquipmentFilterBar from '@/views/client-assets/sites/components/SiteEquipmentFilterBar.vue'
import SiteEquipmentTable from '@/views/client-assets/sites/components/SiteEquipmentTable.vue'
import SiteAreaSearchDialog from '@/views/client-assets/sites/edit/SiteAreaSearchDialog.vue'
import SiteLocationExplorer from '@/views/client-assets/sites/edit/SiteLocationExplorer.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  equipment: { type: Array, default: () => [] },
  flattenedHierarchy: { type: Array, default: () => [] },
  siteAreasFlat: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  selectedLocationAreaId: { type: [Number, String], default: null },
  includeSubAreas: { type: Boolean, default: true },
  viewMode: { type: String, default: 'cards' },
  initialViewMode: { type: String, default: null },
  siteTitle: { type: String, default: '' },
  site: { type: Object, default: null },
  siteId: { type: [Number, String], default: null },
  readonly: { type: Boolean, default: false },
  size: { type: String, default: 'full' },
})

const emit = defineEmits([
  'update:modelValue',
  'update:selectedLocationAreaId',
  'update:includeSubAreas',
  'update:viewMode',
  'toggleExpand',
  'viewEquipment',
  'editEquipment',
  'addEquipment',
  'refresh',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const { areaLabel: helperAreaLabel } = useEquipmentHelpers()

// ── State ────────────────────────────────────────────────────────────────────
const isZoneSearchDialogOpen = ref(false)
const isSidebarCollapsed = ref(false)
const isRefreshing = ref(false)
const searchQuery = ref('')
const filterLifecycleStatus = ref(null)
const filterComplianceStatus = ref(null)
const filterCriticality = ref(null)
const filterClassId = ref(null)
const page = ref(1)
const itemsPerPage = ref(12)

const currentViewMode = ref(props.initialViewMode || props.viewMode || 'cards')
const localExpandedAreaIds = ref(new Set())

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

watch(() => props.viewMode, val => {
  if (val) currentViewMode.value = val
})

watch(() => props.initialViewMode, val => {
  if (val) currentViewMode.value = val
})

watch(() => props.modelValue, open => {
  if (open) {
    isZoneSearchDialogOpen.value = false
  }
})

const setViewMode = mode => {
  currentViewMode.value = mode
  emit('update:viewMode', mode)
}

// ── Site & Area Data Normalization ───────────────────────────────────────────
const resolvedSiteId = computed(() => props.siteId || props.site?.id || null)

const effectiveFlatAreas = computed(() => {
  if (Array.isArray(props.siteAreasFlat) && props.siteAreasFlat.length)
    return props.siteAreasFlat
  if (Array.isArray(props.areas) && props.areas.length)
    return props.areas

  return []
})

const effectiveFlattenedHierarchy = computed(() => {
  if (Array.isArray(props.flattenedHierarchy) && props.flattenedHierarchy.length)
    return props.flattenedHierarchy

  const flat = effectiveFlatAreas.value
  if (!flat.length) return []

  const result = []

  function traverse(items, depth) {
    for (const item of items) {
      const directChildren = flat.filter(a => a.parent_area_id === item.id)
      const hasChildren = directChildren.length > 0
      const isExpanded = localExpandedAreaIds.value.has(item.id)

      result.push({
        ...item,
        depth,
        hasChildren,
        isExpanded,
        childCount: directChildren.length,
      })

      if (hasChildren && isExpanded) {
        traverse(directChildren, depth + 1)
      }
    }
  }

  const rootItems = flat.filter(a => !a.parent_area_id)

  traverse(rootItems, 0)

  return result
})

const areaLabel = eq => helperAreaLabel(eq, effectiveFlatAreas.value)

// ── Titles & Stats ───────────────────────────────────────────────────────────
const resolvedTitle = computed(() => {
  if (props.siteTitle) return props.siteTitle
  if (props.site?.name) return localizedValue(props.site.name)
  if (props.site?.site_code) return props.site.site_code

  return t('Equipment Inventory Explorer')
})

const operationalCount = computed(() => {
  const all = props.equipment || []

  return all.filter(e => ['operational', 'active'].includes((e.lifecycle_status || '').toLowerCase())).length
})

const resolvedSubtitle = computed(() => {
  const total = (props.equipment || []).length
  const matching = filteredEquipment.value.length

  return t('{count} of {total} assets · {operational} operational', {
    count: matching,
    total,
    operational: operationalCount.value,
  })
})

const selectedAreaLabel = computed(() => {
  if (!props.selectedLocationAreaId) return t('All Facility')
  const area = effectiveFlatAreas.value.find(a => String(a.id) === String(props.selectedLocationAreaId))

  return area ? localizedValue(area.name) : t('Selected Zone')
})

// ── Available Classes for Filter Bar ─────────────────────────────────────────
const availableEquipmentClasses = computed(() => {
  const map = new Map()

  ;(props.equipment || []).forEach(eq => {
    if (eq.equipment_class) {
      map.set(
        eq.equipment_class.id,
        localizedValue(eq.equipment_class.name) || eq.equipment_class.name_text || `Class #${eq.equipment_class.id}`,
      )
    }
  })

  return Array.from(map.entries()).map(([value, title]) => ({ title, value }))
})

// ── Filtering & Pagination ───────────────────────────────────────────────────
const filteredEquipment = computed(() => {
  let list = props.equipment || []

  if (props.selectedLocationAreaId) {
    if (props.includeSubAreas) {
      const descendants = getAllDescendantAreaIds(effectiveFlatAreas.value, props.selectedLocationAreaId)
      const targetIds = [props.selectedLocationAreaId, ...descendants].map(String)

      list = list.filter(eq =>
        targetIds.includes(String(eq.area_id)) || (eq.area?.id && targetIds.includes(String(eq.area.id))),
      )
    }
    else {
      list = list.filter(eq =>
        String(eq.area_id) === String(props.selectedLocationAreaId) || String(eq.area?.id) === String(props.selectedLocationAreaId),
      )
    }
  }

  if (searchQuery.value?.trim()) {
    const q = searchQuery.value.trim().toLowerCase()

    list = list.filter(eq => {
      const code = (eq.equipment_code || '').toLowerCase()
      const model = (localizedValue(eq.model_name) || localizedValue(eq.blueprint?.name) || '').toLowerCase()
      const serial = (eq.serial_number || '').toLowerCase()
      const tag = (eq.asset_tag || '').toLowerCase()
      const brand = brandLabel(eq).toLowerCase()
      const className = classLabel(eq).toLowerCase()

      return code.includes(q)
        || model.includes(q)
        || serial.includes(q)
        || tag.includes(q)
        || brand.includes(q)
        || className.includes(q)
    })
  }

  if (filterLifecycleStatus.value) {
    list = list.filter(eq => eq.lifecycle_status === filterLifecycleStatus.value)
  }
  if (filterComplianceStatus.value) {
    list = list.filter(eq => eq.compliance_status === filterComplianceStatus.value)
  }
  if (filterCriticality.value) {
    list = list.filter(eq => eq.criticality === filterCriticality.value)
  }
  if (filterClassId.value) {
    list = list.filter(eq => String(eq.equipment_class_id) === String(filterClassId.value))
  }

  return list
})

const paginatedEquipment = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return filteredEquipment.value.slice(start, start + itemsPerPage.value)
})

const hasActiveFilters = computed(() =>
  Boolean(
    searchQuery.value
    || filterLifecycleStatus.value
    || filterComplianceStatus.value
    || filterCriticality.value
    || filterClassId.value
    || props.selectedLocationAreaId,
  ),
)

watch(
  [
    () => props.selectedLocationAreaId,
    () => props.includeSubAreas,
    searchQuery,
    filterLifecycleStatus,
    filterComplianceStatus,
    filterCriticality,
    filterClassId,
    itemsPerPage,
  ],
  () => {
    page.value = 1
  },
)

// ── Actions ──────────────────────────────────────────────────────────────────
const resetFilters = () => {
  emit('update:selectedLocationAreaId', null)
  emit('update:includeSubAreas', true)
  searchQuery.value = ''
  filterLifecycleStatus.value = null
  filterComplianceStatus.value = null
  filterCriticality.value = null
  filterClassId.value = null
  page.value = 1
}

const onSelectLocation = id => {
  emit('update:selectedLocationAreaId', id ?? null)
  isZoneSearchDialogOpen.value = false
}

const onToggleExpand = areaId => {
  if (localExpandedAreaIds.value.has(areaId)) {
    localExpandedAreaIds.value.delete(areaId)
  }
  else {
    localExpandedAreaIds.value.add(areaId)
  }
  emit('toggleExpand', areaId)
}

const handleRefresh = async () => {
  isRefreshing.value = true
  try {
    emit('refresh')
  }
  finally {
    setTimeout(() => {
      isRefreshing.value = false
    }, 450)
  }
}

const router = useRouter()

const navigateToInventoryExplorer = () => {
  if (resolvedSiteId.value) {
    visible.value = false
    router.push({
      name: 'client-assets-sites-inventory-explorer-id',
      params: { id: resolvedSiteId.value },
    })
  }
}

const handleView = item => {
  const id = typeof item === 'object' && item !== null ? item.id : item

  emit('viewEquipment', id)
}

const handleEdit = item => {
  const id = typeof item === 'object' && item !== null ? item.id : item

  emit('editEquipment', id)
}
</script>

<template>
  <WpZoomDialog
    v-model="visible"
    :size="size"
    hide-activator
    fullscreen-toggle
    no-padding
    icon="tabler-fire-extinguisher"
    :title="resolvedTitle"
    :subtitle="resolvedSubtitle"
    class="site-equipment-zoom-dialog"
  >
    <template #header-actions>
      <div class="d-flex align-center gap-1 me-1">
        <!-- Refresh Button -->
        <IconBtn
          size="small"
          variant="text"
          :loading="isRefreshing"
          :title="$t('Refresh Data')"
          :aria-label="$t('Refresh Data')"
          @click="handleRefresh"
        >
          <VIcon icon="tabler-refresh" />
        </IconBtn>

        <!-- Zones Sidebar Toggle (Desktop) -->
        <IconBtn
          v-if="!smAndDown"
          size="small"
          variant="tonal"
          :color="isSidebarCollapsed ? 'primary' : 'secondary'"
          :title="isSidebarCollapsed ? $t('Show Zones Sidebar') : $t('Hide Zones Sidebar')"
          :aria-label="isSidebarCollapsed ? $t('Show Zones Sidebar') : $t('Hide Zones Sidebar')"
          @click="isSidebarCollapsed = !isSidebarCollapsed"
        >
          <VIcon :icon="isSidebarCollapsed ? 'tabler-layout-sidebar-left-expand' : 'tabler-layout-sidebar-left-collapse'" />
        </IconBtn>

        <!-- View Mode Toggle (Grid vs Table) -->
        <VBtnToggle
          :model-value="currentViewMode === 'list' && !smAndDown ? 'table' : currentViewMode"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          class="me-1"
          @update:model-value="setViewMode($event)"
        >
          <VBtn
            value="cards"
            size="small"
            icon="tabler-layout-grid"
            :aria-label="$t('Grid View')"
            :title="$t('Grid View')"
          />
          <VBtn
            :value="smAndDown ? 'list' : 'table'"
            size="small"
            :icon="smAndDown ? 'tabler-list' : 'tabler-table'"
            :aria-label="smAndDown ? $t('List View') : $t('Table View')"
            :title="smAndDown ? $t('List View') : $t('Table View')"
          />
        </VBtnToggle>

        <!-- Dedicated Inventory Explorer Page Link -->
        <VBtn
          v-if="!smAndDown && resolvedSiteId"
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-packages"
          @click="navigateToInventoryExplorer"
        >
          {{ $t('Inventory Explorer') }}
        </VBtn>

        <!-- Add Equipment Button -->
        <VBtn
          v-if="!readonly && !smAndDown"
          size="small"
          color="warning"
          prepend-icon="tabler-plus"
          @click="emit('addEquipment', selectedLocationAreaId)"
        >
          {{ $t('Add Equipment') }}
        </VBtn>
      </div>
    </template>

    <!-- Dialog Body: Flex Shell -->
    <div
      class="zoom-shell"
      :class="{ 'zoom-shell--mobile': smAndDown }"
    >
      <!-- Collapsible Desktop Zones Sidebar -->
      <aside
        v-if="!smAndDown && !isSidebarCollapsed"
        class="zoom-sidebar border-e d-flex flex-column"
      >
        <div class="d-flex align-center justify-space-between px-3 py-2 border-b bg-surface">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-flex align-center gap-1">
            <VIcon
              icon="tabler-hierarchy-2"
              size="15"
            />
            {{ $t('Filter by Zone') }}
          </span>
          <div class="d-flex align-center">
            <IconBtn
              size="x-small"
              variant="text"
              color="primary"
              :title="$t('Search Zones Dialog')"
              @click="isZoneSearchDialogOpen = true"
            >
              <VIcon
                icon="tabler-search"
                size="15"
              />
            </IconBtn>
            <IconBtn
              size="x-small"
              variant="text"
              :title="$t('Hide Sidebar')"
              :aria-label="$t('Hide Sidebar')"
              @click="isSidebarCollapsed = true"
            >
              <VIcon
                icon="tabler-layout-sidebar-left-collapse"
                size="16"
              />
            </IconBtn>
          </div>
        </div>
        <div class="pa-3 overflow-y-auto flex-grow-1">
          <SiteLocationExplorer
            :flattened-hierarchy="effectiveFlattenedHierarchy"
            :site-areas-flat="effectiveFlatAreas"
            :site-equipment-count="equipment.length"
            :selected-location-area-id="selectedLocationAreaId"
            :include-sub-areas="includeSubAreas"
            @update:selected-location-area-id="onSelectLocation"
            @update:include-sub-areas="emit('update:includeSubAreas', $event)"
            @toggle-expand="onToggleExpand"
          />
        </div>
      </aside>

      <!-- Main Results Region -->
      <div class="zoom-main d-flex flex-column">
        <!-- Controls & Filters Toolbar -->
        <div class="zoom-controls pa-3 pa-sm-4 border-b">
          <!-- Active Filter Location Chip (Desktop) -->
          <div
            v-if="!smAndDown && selectedLocationAreaId"
            class="d-flex align-center gap-2 mb-3"
          >
            <VChip
              color="primary"
              variant="tonal"
              closable
              size="small"
              class="cursor-pointer"
              @click="isZoneSearchDialogOpen = true"
              @click:close="emit('update:selectedLocationAreaId', null)"
            >
              <VIcon
                start
                icon="tabler-map-pin"
                size="14"
              />
              {{ selectedAreaLabel }}
            </VChip>
            <span
              v-if="includeSubAreas"
              class="text-caption text-medium-emphasis"
            >
              ({{ $t('including sub-areas') }})
            </span>
          </div>

          <!-- Standard Multi-criteria Filter Bar (WpSearchField + WpFilterDialog + WpActiveFilters) -->
          <SiteEquipmentFilterBar
            v-model:search-query="searchQuery"
            v-model:lifecycle-status="filterLifecycleStatus"
            v-model:compliance-status="filterComplianceStatus"
            v-model:criticality="filterCriticality"
            v-model:class-id="filterClassId"
            :class-options="availableEquipmentClasses"
            :selected-area-label="selectedAreaLabel"
            :has-selected-area="Boolean(selectedLocationAreaId)"
            :show-zone-button="smAndDown || isSidebarCollapsed"
            show-class-filter
            @open-zone-picker="isZoneSearchDialogOpen = true"
            @clear-zone="emit('update:selectedLocationAreaId', null)"
            @reset="resetFilters"
          />
        </div>

        <!-- Results List / Grid / Table Area -->
        <div class="zoom-results flex-grow-1 overflow-y-auto pa-3 pa-sm-4">
          <!-- Empty State -->
          <WpEmptyState
            v-if="!filteredEquipment.length"
            :icon="hasActiveFilters ? 'tabler-search-off' : 'tabler-packages'"
            :title="hasActiveFilters ? $t('No matching equipment assets') : $t('No equipment assets yet')"
            :subtitle="hasActiveFilters ? $t('Try adjusting your search criteria or resetting the zone filters.') : $t('Add equipment assets to start tracking lifecycle and inspections.')"
          >
            <template #action>
              <div class="d-flex align-center gap-2">
                <VBtn
                  v-if="hasActiveFilters"
                  size="small"
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-filter-off"
                  @click="resetFilters"
                >
                  {{ $t('Reset Filters') }}
                </VBtn>
                <VBtn
                  v-if="!readonly"
                  size="small"
                  color="warning"
                  prepend-icon="tabler-plus"
                  @click="emit('addEquipment', selectedLocationAreaId)"
                >
                  {{ $t('Add Equipment') }}
                </VBtn>
              </div>
            </template>
          </WpEmptyState>

          <!-- 1. CARDS VIEW -->
          <div
            v-else-if="currentViewMode === 'cards'"
            class="equipment-grid"
          >
            <SiteEquipmentCard
              v-for="eq in paginatedEquipment"
              :key="eq.id"
              :equipment="eq"
              :area-label="areaLabel(eq)"
              :readonly="readonly"
              @view="handleView"
              @edit="handleEdit"
              @replace-qr="handleEdit"
              @transfer="handleEdit"
            />
          </div>

          <!-- 2. TABLE & MOBILE LIST VIEW -->
          <SiteEquipmentTable
            v-else
            :items="paginatedEquipment"
            :readonly="readonly"
            :resolve-area-label="areaLabel"
            @view="handleView"
            @edit="handleEdit"
          />
        </div>

        <!-- Pagination Pinned to Footer -->
        <WpPagination
          v-if="filteredEquipment.length"
          v-model:page="page"
          v-model:items-per-page="itemsPerPage"
          :total-items="filteredEquipment.length"
          :page-size-options="[8, 12, 24, 48]"
          class="pa-3 border-t bg-surface"
        />
      </div>
    </div>

    <!-- Mobile Action Bar (Sticky at bottom for mobile) -->
    <div
      v-if="smAndDown"
      class="zoom-mobile-bar d-flex align-center gap-2 pa-3 border-t bg-surface"
    >
      <VBtn
        v-if="!readonly"
        class="flex-grow-1"
        color="warning"
        prepend-icon="tabler-plus"
        @click="emit('addEquipment', selectedLocationAreaId)"
      >
        {{ $t('Add Equipment') }}
      </VBtn>
      <IconBtn
        v-if="resolvedSiteId"
        variant="tonal"
        color="primary"
        :title="$t('Open Inventory Explorer')"
        @click="navigateToInventoryExplorer"
      >
        <VIcon
          icon="tabler-external-link"
          size="18"
        />
      </IconBtn>
    </div>
  </WpZoomDialog>

  <!-- Standard Zone Filter Dialog (Replaces Mobile Bottom Sheet) -->
  <SiteAreaSearchDialog
    v-if="visible"
    v-model="isZoneSearchDialogOpen"
    :site-id="resolvedSiteId"
    :areas-list="effectiveFlatAreas"
    :current-area-id="selectedLocationAreaId"
    allow-root
    :root-label="$t('All Facility')"
    :title="$t('Filter by Zone / Building Area')"
    @select="area => onSelectLocation(area?.id ?? null)"
  />
</template>

<style lang="scss" scoped>
.site-equipment-zoom-dialog {
  display: flex;
  flex-direction: column;
}

.zoom-shell {
  display: flex;
  overflow: hidden;
  flex: 1;
  block-size: 100%;
  min-block-size: 0;
}

.zoom-shell--mobile {
  flex-direction: column;
}

.zoom-sidebar {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  background: rgba(var(--v-theme-surface), 0.72);
  inline-size: 280px;
  max-inline-size: 320px;
  min-block-size: 0;
  min-inline-size: 260px;
  transition: all 0.2s ease;
}

.zoom-main {
  flex: 1;
  min-block-size: 0;
  min-inline-size: 0;
}

.zoom-controls {
  background: rgb(var(--v-theme-surface));
}

.zoom-results {
  min-block-size: 0;
}

.equipment-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
}

.zoom-mobile-bar {
  background: rgb(var(--v-theme-surface));
}

@media (max-width: 960px) {
  .equipment-grid {
    gap: 0.75rem;
    grid-template-columns: 1fr;
  }
}
</style>
