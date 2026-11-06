<script setup>
/* eslint-disable camelcase */
import {
  AppBreadcrumb,
  WpDeleteDialog,
  WpEmptyState,
  WpErrorBanner,
  WpKpiCard,
  WpKpiGroup,
  WpLoadingState,
  WpPagination,
} from '@/components/shared'
import {
  brandLabel,
  classLabel,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { getAllDescendantAreaIds } from '@/composables/client-assets/sites/areaHierarchyHelper'
import { useSiteAreas } from '@/composables/client-assets/sites/useSiteAreas'
import { useSiteEquipment } from '@/composables/client-assets/sites/useSiteEquipment'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import EquipmentWizardDialog from '@/views/client-assets/equipment/dialogs/EquipmentWizardDialog.vue'
import EquipmentReplaceQrDialog from '@/views/client-assets/equipment/EquipmentReplaceQrDialog.vue'
import EquipmentTransferDialog from '@/views/client-assets/equipment/EquipmentTransferDialog.vue'
import EquipmentViewModal from '@/views/client-assets/equipment/EquipmentViewModal.vue'
import SiteEquipmentCard from '@/views/client-assets/sites/components/SiteEquipmentCard.vue'
import SiteEquipmentFilterBar from '@/views/client-assets/sites/components/SiteEquipmentFilterBar.vue'
import SiteEquipmentTable from '@/views/client-assets/sites/components/SiteEquipmentTable.vue'
import AreaManageDialog from '@/views/client-assets/sites/edit/AreaManageDialog.vue'
import SiteEquipmentAnalyticsPanel from '@/views/client-assets/sites/edit/SiteEquipmentAnalyticsPanel.vue'
import SiteLocationExplorer from '@/views/client-assets/sites/edit/SiteLocationExplorer.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  siteId: { type: [Number, String], default: null },
  readonly: { type: Boolean, default: false },
})

const route = useRoute()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { lgAndDown, smAndDown } = useDisplay()

const targetSiteId = computed(() => props.siteId || route.params.id)

// ── Core Data ────────────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref(null)
const fetchedSite = ref(null)

const {
  loadingAreas,
  siteAreasFlat,
  expandedAreaIds,
  flattenedHierarchy,
  fetchAreas,
  toggleAreaExpand,
  expandAllAreas,
  collapseAllAreas,
} = useSiteAreas(targetSiteId)

const {
  loadingEquipment,
  siteEquipment,
  equipmentKPIs,
  fetchEquipment,
} = useSiteEquipment(targetSiteId)

const {
  areaLabel: areaLabelHelper,
} = useEquipmentHelpers()

const areaLabel = eq => areaLabelHelper(eq, siteAreasFlat.value)

// ── UI State ─────────────────────────────────────────────────────────────────
const selectedLocationAreaId = ref(null)
const includeSubAreas = ref(true)
const isMobileDrawerOpen = ref(false)
const isSidebarCollapsed = ref(false)

const equipmentViewMode = ref('cards')
const searchQuery = ref('')
const filterLifecycleStatus = ref(null)
const filterComplianceStatus = ref(null)
const filterCriticality = ref(null)
const filterClassId = ref(null)
const page = ref(1)
const itemsPerPage = ref(12)

// ── Dialog States ────────────────────────────────────────────────────────────
const isAddEquipmentDialogOpen = ref(false)
const isEditEquipmentDialogOpen = ref(false)
const selectedEquipmentForEditId = ref(null)

const isViewEquipmentModalOpen = ref(false)
const selectedViewEquipmentId = ref(null)

const isAreaDialogOpen = ref(false)
const selectedAreaForEdit = ref(null)
const selectedParentAreaId = ref(null)

const isReplaceQrDialogOpen = ref(false)
const isTransferDialogOpen = ref(false)
const isDeleteEquipmentDialogOpen = ref(false)
const selectedEquipmentItem = ref(null)

// ── Navigation ───────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Client Assets'), icon: 'tabler-building-estate' },
  { title: t('Sites'), to: '/client-assets/sites/list', icon: 'tabler-map-pin' },
  {
    title: fetchedSite.value ? localizedValue(fetchedSite.value.name) : t('Inventory Explorer'),
    icon: 'tabler-packages',
    disabled: true,
  },
])

// ── Data Fetching ────────────────────────────────────────────────────────────
const fetchAll = async () => {
  if (!targetSiteId.value) return
  loading.value = true
  error.value = null

  try {
    const site = await store.fetchSite(targetSiteId.value)

    fetchedSite.value = site

    await Promise.allSettled([
      fetchAreas(),
      fetchEquipment(),
    ])
  }
  catch (err) {
    console.error('Failed to load site inventory explorer:', err)
    error.value = err?.data?.message || t('Failed to load site inventory data.')
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchAll)

watch(() => targetSiteId.value, () => {
  fetchAll()
})

// ── Selected Area Resolution ─────────────────────────────────────────────────
const selectedLocationAreaObj = computed(() => {
  if (!selectedLocationAreaId.value) return null

  return siteAreasFlat.value.find(a => a.id === selectedLocationAreaId.value) || null
})

const selectedAreaLabel = computed(() => {
  if (!selectedLocationAreaId.value) return t('All Site Inventory')

  return localizedValue(selectedLocationAreaObj.value?.name) || `Area #${selectedLocationAreaId.value}`
})

// ── Filtered & Paginated Equipment ───────────────────────────────────────────
const filteredEquipment = computed(() => {
  let list = siteEquipment.value || []

  if (selectedLocationAreaId.value) {
    if (includeSubAreas.value) {
      const descendants = getAllDescendantAreaIds(siteAreasFlat.value, selectedLocationAreaId.value)
      const targetIds = [selectedLocationAreaId.value, ...descendants]

      list = list.filter(eq => targetIds.includes(eq.area_id) || (eq.area?.id && targetIds.includes(eq.area.id)))
    }
    else {
      list = list.filter(eq => eq.area_id === selectedLocationAreaId.value || eq.area?.id === selectedLocationAreaId.value)
    }
  }

  if (searchQuery.value?.trim()) {
    const q = searchQuery.value.trim().toLowerCase()

    list = list.filter(eq => {
      const code = (eq.equipment_code || '').toLowerCase()
      const model = (eq.model_name || localizedValue(eq.blueprint?.name) || '').toLowerCase()
      const serial = (eq.serial_number || '').toLowerCase()
      const tag = (eq.asset_tag || '').toLowerCase()
      const brand = brandLabel(eq).toLowerCase()
      const className = classLabel(eq).toLowerCase()

      return code.includes(q) || model.includes(q) || serial.includes(q)
        || tag.includes(q) || brand.includes(q) || className.includes(q)
    })
  }

  if (filterLifecycleStatus.value)
    list = list.filter(eq => eq.lifecycle_status === filterLifecycleStatus.value)
  if (filterComplianceStatus.value)
    list = list.filter(eq => eq.compliance_status === filterComplianceStatus.value)
  if (filterCriticality.value)
    list = list.filter(eq => eq.criticality === filterCriticality.value)
  if (filterClassId.value)
    list = list.filter(eq => eq.equipment_class_id === filterClassId.value)

  return list
})

const paginatedEquipment = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return filteredEquipment.value.slice(start, start + itemsPerPage.value)
})

const availableEquipmentClasses = computed(() => {
  const map = new Map()

  siteEquipment.value.forEach(eq => {
    if (eq.equipment_class) {
      map.set(
        eq.equipment_class.id,
        localizedValue(eq.equipment_class.name) || eq.equipment_class.name_text || `Class #${eq.equipment_class.id}`,
      )
    }
  })

  return Array.from(map.entries()).map(([value, title]) => ({ title, value }))
})

const resetFilters = () => {
  searchQuery.value = ''
  filterLifecycleStatus.value = null
  filterComplianceStatus.value = null
  filterCriticality.value = null
  filterClassId.value = null
  page.value = 1
}

watch(
  [
    selectedLocationAreaId,
    includeSubAreas,
    searchQuery,
    filterLifecycleStatus,
    filterComplianceStatus,
    filterCriticality,
    filterClassId,
    itemsPerPage,
  ],
  () => { page.value = 1 },
)

watch(lgAndDown, isNarrow => {
  if (isNarrow)
    isSidebarCollapsed.value = false
})

const viewTabs = computed(() => [
  { value: 'cards', icon: 'tabler-layout-grid', label: t('Cards') },
  { value: 'table', icon: 'tabler-table', label: t('Table') },
  { value: 'analytics', icon: 'tabler-chart-pie', label: t('Analytics') },
])

// ── Actions ──────────────────────────────────────────────────────────────────
const onSelectLocation = id => {
  selectedLocationAreaId.value = id
  if (lgAndDown.value)
    isMobileDrawerOpen.value = false
}

const openAddEquipment = (areaId = null) => {
  if (areaId) selectedLocationAreaId.value = areaId
  isAddEquipmentDialogOpen.value = true
}

const openEditEquipment = eq => {
  selectedEquipmentForEditId.value = eq.id
  isEditEquipmentDialogOpen.value = true
}

const openViewEquipment = eqId => {
  selectedViewEquipmentId.value = eqId
  isViewEquipmentModalOpen.value = true
}

const openReplaceQr = eq => {
  selectedEquipmentItem.value = eq
  isReplaceQrDialogOpen.value = true
}

const openTransfer = eq => {
  selectedEquipmentItem.value = eq
  isTransferDialogOpen.value = true
}

const isDeletingEquipment = ref(false)

const openDeleteEquipment = eq => {
  selectedEquipmentItem.value = eq
  isDeleteEquipmentDialogOpen.value = true
}

const confirmDeleteEquipment = async () => {
  if (!selectedEquipmentItem.value?.id) return
  isDeletingEquipment.value = true
  try {
    await clientAssetStore.deleteEquipment(selectedEquipmentItem.value.id)
    snackbarStore.showMessage(t('Equipment deleted successfully'), 'success')
    isDeleteEquipmentDialogOpen.value = false
    selectedEquipmentItem.value = null
    fetchEquipment()
    fetchAreas()
  }
  catch (err) {
    snackbarStore.showMessage(err?.message || t('Failed to delete equipment'), 'error')
  }
  finally {
    isDeletingEquipment.value = false
  }
}

const openAreaDialog = (area = null, parentId = null) => {
  selectedAreaForEdit.value = area
  selectedParentAreaId.value = parentId
  isAreaDialogOpen.value = true
}

const handleDeleteArea = async area => {
  if (!area?.id) return

  const childCount = siteAreasFlat.value.filter(a => a.parent_area_id === area.id).length
  const equipCount = area.equipment_count || 0

  if (childCount > 0) {
    snackbarStore.showMessage(t('Cannot delete area: it contains child sub-areas. Delete or move sub-areas first.'), 'error')

    return
  }

  if (equipCount > 0) {
    snackbarStore.showMessage(t('Cannot delete area: it contains installed equipment assets. Move equipment first.'), 'error')

    return
  }

  if (!confirm(t('Are you sure you want to delete this zone area?')))
    return

  try {
    await store.deleteSiteArea(area.id)
    snackbarStore.showMessage(t('Area deleted successfully'), 'success')
    if (selectedLocationAreaId.value === area.id)
      selectedLocationAreaId.value = null
    await fetchAreas()
    await fetchEquipment()
  }
  catch (err) {
    console.error(err)
    snackbarStore.showMessage(err?.data?.message || t('Failed to delete area'), 'error')
  }
}
</script>

<template>
  <div class="site-inventory-explorer pb-8">
    <!-- Breadcrumb -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- Loading State -->
    <VSkeletonLoader
      v-if="loading && !fetchedSite"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- Error State -->
    <WpErrorBanner
      v-else-if="error"
      :error="error"
      @retry="fetchAll"
    />

    <!-- Main Content -->
    <div
      v-else-if="fetchedSite"
      class="explorer-container"
    >
      <!-- Page Title -->
      <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between gap-3 mb-5">
        <div class="min-w-0">
          <h1 class="text-h5 font-weight-bold mb-1 text-truncate">
            {{ localizedValue(fetchedSite.name) }} · {{ fetchedSite.site_code }}
          </h1>
          <div class="text-body-2 text-medium-emphasis text-truncate">
            <template v-if="fetchedSite.client">
              {{ localizedValue(fetchedSite.client.legal_name || fetchedSite.client.trade_name) }} —
            </template>
            {{ $t('Physical Asset & Zone Explorer') }}
          </div>
        </div>
        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            v-if="!smAndDown"
            variant="tonal"
            color="secondary"
            size="small"
            prepend-icon="tabler-building-estate"
            :to="{ name: 'client-assets-sites-edit-id', params: { id: targetSiteId } }"
          >
            {{ $t('Site Journal') }}
          </VBtn>
          <IconBtn
            v-else
            variant="tonal"
            color="secondary"
            size="small"
            :title="$t('Site Journal')"
            :to="{ name: 'client-assets-sites-edit-id', params: { id: targetSiteId } }"
          >
            <VIcon icon="tabler-building-estate" />
          </IconBtn>

          <VBtn
            v-if="!readonly && !smAndDown"
            variant="tonal"
            color="info"
            size="small"
            prepend-icon="tabler-hierarchy"
            @click="openAreaDialog(null, selectedLocationAreaId)"
          >
            {{ $t('Add Zone') }}
          </VBtn>

          <VBtn
            v-if="!readonly"
            color="primary"
            :size="smAndDown ? 'small' : 'default'"
            prepend-icon="tabler-plus"
            @click="openAddEquipment(selectedLocationAreaId)"
          >
            {{ $t('Add Equipment') }}
          </VBtn>

          <IconBtn
            variant="tonal"
            color="secondary"
            size="small"
            :title="$t('Refresh Data')"
            :loading="loadingEquipment || loadingAreas"
            @click="fetchAll"
          >
            <VIcon icon="tabler-refresh" />
          </IconBtn>
        </div>
      </div>

      <!-- KPI Cards -->
      <WpKpiGroup
        v-if="equipmentViewMode !== 'analytics'"
        :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
        class="mb-5"
      >
        <template #default="{ getColProps }">
          <VCol v-bind="getColProps(0)">
            <WpKpiCard
              :title="$t('Zones & Areas')"
              :value="siteAreasFlat.length"
              icon="tabler-hierarchy"
              icon-color="info"
              :loading="loadingAreas"
            />
          </VCol>
          <VCol v-bind="getColProps(1)">
            <WpKpiCard
              :title="$t('Installed Assets')"
              :value="equipmentKPIs.total"
              icon="tabler-packages"
              icon-color="primary"
              :loading="loadingEquipment"
            />
          </VCol>
          <VCol v-bind="getColProps(2)">
            <WpKpiCard
              :title="$t('Operational')"
              :value="equipmentKPIs.operational"
              :subtitle="equipmentKPIs.total > 0
                ? `${Math.round((equipmentKPIs.operational / equipmentKPIs.total) * 100)}%`
                : '100%'"
              icon="tabler-circle-check"
              icon-color="success"
              :loading="loadingEquipment"
            />
          </VCol>
          <VCol v-bind="getColProps(3)">
            <WpKpiCard
              :title="$t('Compliance')"
              :value="equipmentKPIs.overdue > 0 ? equipmentKPIs.overdue : `${equipmentKPIs.compliancePct}%`"
              :subtitle="equipmentKPIs.overdue > 0 ? $t('Overdue') : $t('Compliant')"
              icon="tabler-shield-check"
              :icon-color="equipmentKPIs.overdue > 0 ? 'error' : 'success'"
              :loading="loadingEquipment"
            />
          </VCol>
        </template>
      </WpKpiGroup>

      <!-- Mobile Zone Selector -->
      <VBtn
        v-if="lgAndDown"
        block
        variant="tonal"
        color="primary"
        prepend-icon="tabler-hierarchy"
        append-icon="tabler-chevron-right"
        class="mb-4"
        @click="isMobileDrawerOpen = true"
      >
        <span class="text-truncate">
          {{ $t('Zone:') }} <strong>{{ selectedAreaLabel }}</strong>
        </span>
      </VBtn>

      <!-- Two-Column Explorer Layout -->
      <div
        class="explorer-workspace"
        :class="{ 'explorer-workspace--collapsed': isSidebarCollapsed || lgAndDown }"
      >
        <!-- Desktop Sidebar: Zone Tree -->
        <VCard
          v-if="!lgAndDown && !isSidebarCollapsed"
          class="explorer-sidebar border"
          variant="outlined"
        >
          <div class="d-flex align-center justify-space-between gap-2 pa-4 pb-2">
            <div class="min-w-0">
              <div class="text-subtitle-2 font-weight-bold text-truncate">
                {{ $t('Zones & Building Areas') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ siteAreasFlat.length }} {{ $t('areas') }} · {{ siteEquipment.length }} {{ $t('assets') }}
              </div>
            </div>
            <div class="d-flex align-center">
              <IconBtn
                size="small"
                :title="$t('Expand All')"
                @click="expandAllAreas"
              >
                <VIcon
                  icon="tabler-arrows-maximize"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                size="small"
                :title="$t('Collapse All')"
                @click="collapseAllAreas"
              >
                <VIcon
                  icon="tabler-arrows-minimize"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                size="small"
                :title="$t('Hide Sidebar')"
                @click="isSidebarCollapsed = true"
              >
                <VIcon
                  icon="tabler-layout-sidebar-left-collapse"
                  size="16"
                />
              </IconBtn>
            </div>
          </div>

          <VDivider />

          <div class="explorer-sidebar-body pa-3">
            <SiteLocationExplorer
              :flattened-hierarchy="flattenedHierarchy"
              :site-areas-flat="siteAreasFlat"
              :site-equipment-count="siteEquipment.length"
              :selected-location-area-id="selectedLocationAreaId"
              :include-sub-areas="includeSubAreas"
              @update:selected-location-area-id="onSelectLocation"
              @update:include-sub-areas="includeSubAreas = $event"
              @toggle-expand="toggleAreaExpand"
            >
              <template
                v-if="!readonly"
                #node-actions="{ area }"
              >
                <VMenu location="bottom end">
                  <template #activator="{ props: menuProps }">
                    <IconBtn
                      v-bind="menuProps"
                      size="x-small"
                      variant="text"
                    >
                      <VIcon
                        icon="tabler-dots-vertical"
                        size="16"
                      />
                    </IconBtn>
                  </template>
                  <VList density="compact">
                    <VListItem @click="openAreaDialog(null, area.id)">
                      <template #prepend>
                        <VIcon
                          icon="tabler-plus"
                          size="16"
                          class="me-2"
                        />
                      </template>
                      <VListItemTitle>{{ $t('Add Sub-area') }}</VListItemTitle>
                    </VListItem>
                    <VListItem @click="openAddEquipment(area.id)">
                      <template #prepend>
                        <VIcon
                          icon="tabler-fire-extinguisher"
                          size="16"
                          class="me-2"
                        />
                      </template>
                      <VListItemTitle>{{ $t('Add Equipment') }}</VListItemTitle>
                    </VListItem>
                    <VListItem @click="openAreaDialog(area, area.parent_area_id)">
                      <template #prepend>
                        <VIcon
                          icon="tabler-edit"
                          size="16"
                          class="me-2"
                        />
                      </template>
                      <VListItemTitle>{{ $t('Edit Area') }}</VListItemTitle>
                    </VListItem>
                    <VListItem @click="handleDeleteArea(area)">
                      <template #prepend>
                        <VIcon
                          icon="tabler-trash"
                          size="16"
                          class="me-2 text-error"
                        />
                      </template>
                      <VListItemTitle class="text-error">
                        {{ $t('Delete Area') }}
                      </VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </template>
            </SiteLocationExplorer>

            <VBtn
              v-if="!readonly && !siteAreasFlat.length"
              class="mt-3"
              block
              size="small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-plus"
              @click="openAreaDialog(null, null)"
            >
              {{ $t('Create First Zone') }}
            </VBtn>
          </div>
        </VCard>

        <!-- Main Workbench -->
        <VCard
          class="explorer-workbench border"
          variant="outlined"
        >
          <!-- Workbench Header -->
          <div class="pa-4 pb-0">
            <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between gap-3 mb-3">
              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <VBtn
                    v-if="isSidebarCollapsed && !lgAndDown"
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-layout-sidebar-left-expand"
                    @click="isSidebarCollapsed = false"
                  >
                    {{ $t('Show Zones') }}
                  </VBtn>
                  <h2 class="text-subtitle-1 font-weight-bold mb-0">
                    {{ selectedLocationAreaObj ? localizedValue(selectedLocationAreaObj.name) : $t('All Site Inventory') }}
                  </h2>
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ filteredEquipment.length }} {{ $t('Assets') }}
                  </VChip>
                </div>
              </div>
            </div>

            <!-- View Mode Tabs -->
            <VTabs
              v-model="equipmentViewMode"
              show-arrows
              color="primary"
              :density="smAndDown ? 'compact' : 'default'"
              class="explorer-tabs"
            >
              <VTab
                v-for="tab in viewTabs"
                :key="tab.value"
                :value="tab.value"
              >
                <VIcon
                  :icon="tab.icon"
                  size="18"
                  class="me-2"
                />
                {{ tab.label }}
                <VChip
                  v-if="tab.value !== 'analytics'"
                  size="x-small"
                  class="ms-2 d-none d-sm-inline-flex"
                  variant="tonal"
                  color="primary"
                >
                  {{ filteredEquipment.length }}
                </VChip>
              </VTab>
            </VTabs>
          </div>

          <VDivider />

          <!-- Filters & Search -->
          <div
            v-if="equipmentViewMode !== 'analytics'"
            class="pa-4 pb-0"
          >
            <SiteEquipmentFilterBar
              v-model:search-query="searchQuery"
              v-model:lifecycle-status="filterLifecycleStatus"
              v-model:compliance-status="filterComplianceStatus"
              v-model:criticality="filterCriticality"
              v-model:class-id="filterClassId"
              :class-options="availableEquipmentClasses"
              show-class-filter
              @reset="resetFilters"
            />
          </div>

          <!-- Content Area -->
          <VCardText class="explorer-content pa-4">
            <!-- Analytics Tab -->
            <div v-if="equipmentViewMode === 'analytics'">
              <SiteEquipmentAnalyticsPanel
                :equipment="filteredEquipment"
                :areas-flat="siteAreasFlat"
                :show-open-inventory="false"
              />
            </div>

            <!-- Loading -->
            <WpLoadingState
              v-else-if="loadingEquipment"
              :text="$t('Loading equipment...')"
              min-height="200px"
            />

            <!-- Empty State -->
            <WpEmptyState
              v-else-if="!filteredEquipment.length"
              icon="tabler-packages"
              :title="$t('No equipment assets found')"
              :subtitle="$t('Try changing the search query, selecting another zone, or adding a new asset.')"
            >
              <template #action>
                <VBtn
                  v-if="!readonly"
                  color="primary"
                  prepend-icon="tabler-plus"
                  @click="openAddEquipment(selectedLocationAreaId)"
                >
                  {{ $t('Add Equipment Asset') }}
                </VBtn>
              </template>
            </WpEmptyState>

            <!-- Cards View -->
            <div
              v-else-if="equipmentViewMode === 'cards'"
              class="equipment-grid"
            >
              <SiteEquipmentCard
                v-for="eq in paginatedEquipment"
                :key="eq.id"
                :equipment="eq"
                :area-label="areaLabel(eq)"
                :readonly="readonly"
                @view="openViewEquipment"
                @edit="openEditEquipment"
                @replace-qr="openReplaceQr"
                @transfer="openTransfer"
                @delete="openDeleteEquipment"
              />
            </div>

            <!-- Table View -->
            <SiteEquipmentTable
              v-else-if="equipmentViewMode === 'table'"
              :items="paginatedEquipment"
              :readonly="readonly"
              :resolve-area-label="areaLabel"
              @view="openViewEquipment"
              @edit="openEditEquipment"
              @delete="openDeleteEquipment"
            />

            <!-- Pagination -->
            <WpPagination
              v-if="equipmentViewMode !== 'analytics' && !loadingEquipment && filteredEquipment.length"
              v-model:page="page"
              v-model:items-per-page="itemsPerPage"
              :total-items="filteredEquipment.length"
              :page-size-options="[10, 12, 25, 50]"
              class="mt-4"
            />
          </VCardText>
        </VCard>
      </div>
    </div>

    <!-- Mobile Zone Drawer -->
    <VNavigationDrawer
      v-model="isMobileDrawerOpen"
      location="start"
      temporary
      width="340"
    >
      <div class="pa-4 border-b d-flex align-center justify-space-between">
        <span class="text-subtitle-1 font-weight-bold">{{ $t('Select Zone Area') }}</span>
        <IconBtn
          size="small"
          @click="isMobileDrawerOpen = false"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </div>
      <div class="pa-3">
        <SiteLocationExplorer
          :flattened-hierarchy="flattenedHierarchy"
          :site-areas-flat="siteAreasFlat"
          :site-equipment-count="siteEquipment.length"
          :selected-location-area-id="selectedLocationAreaId"
          :include-sub-areas="includeSubAreas"
          @update:selected-location-area-id="onSelectLocation"
          @update:include-sub-areas="includeSubAreas = $event"
          @toggle-expand="toggleAreaExpand"
        />
      </div>
    </VNavigationDrawer>

    <!-- Dialogs -->
    <EquipmentWizardDialog
      v-model:is-dialog-visible="isAddEquipmentDialogOpen"
      :prefilled-client="fetchedSite?.client"
      :prefilled-site="fetchedSite"
      :prefilled-area="selectedLocationAreaObj"
      is-new
      @created="() => { isAddEquipmentDialogOpen = false; fetchEquipment(); fetchAreas(); }"
    />

    <EquipmentWizardDialog
      v-if="selectedEquipmentForEditId"
      v-model:is-dialog-visible="isEditEquipmentDialogOpen"
      :equipment-id="selectedEquipmentForEditId"
      :is-new="false"
      :prefilled-client="fetchedSite?.client"
      :prefilled-site="fetchedSite"
      @saved="() => { isEditEquipmentDialogOpen = false; fetchEquipment(); fetchAreas(); }"
    />

    <EquipmentViewModal
      v-if="selectedViewEquipmentId"
      v-model:is-dialog-visible="isViewEquipmentModalOpen"
      :equipment-id="selectedViewEquipmentId"
      @edit="eqId => { isViewEquipmentModalOpen = false; selectedEquipmentForEditId = eqId; isEditEquipmentDialogOpen = true; }"
    />

    <AreaManageDialog
      v-if="targetSiteId"
      v-model="isAreaDialogOpen"
      :site-id="String(targetSiteId)"
      :area="selectedAreaForEdit"
      :parent-area-id="selectedParentAreaId ? String(selectedParentAreaId) : null"
      :areas-list="siteAreasFlat"
      @saved="() => { isAreaDialogOpen = false; fetchAreas(); fetchEquipment(); }"
    />

    <EquipmentReplaceQrDialog
      v-if="selectedEquipmentItem"
      v-model:is-dialog-visible="isReplaceQrDialogOpen"
      :equipment="selectedEquipmentItem"
      @saved="() => { isReplaceQrDialogOpen = false; fetchEquipment(); }"
    />

    <EquipmentTransferDialog
      v-if="selectedEquipmentItem"
      v-model:is-dialog-visible="isTransferDialogOpen"
      :equipment="selectedEquipmentItem"
      @saved="() => { isTransferDialogOpen = false; fetchEquipment(); fetchAreas(); }"
    />

    <WpDeleteDialog
      v-if="selectedEquipmentItem"
      v-model:is-open="isDeleteEquipmentDialogOpen"
      :title="$t('Delete Equipment')"
      :message="$t('Are you sure you want to delete equipment {code}? This action cannot be undone.', { code: selectedEquipmentItem.equipment_code || localizedValue(selectedEquipmentItem.model_name) || '' })"
      :loading="isDeletingEquipment"
      @confirm="confirmDeleteEquipment"
    />
  </div>
</template>

<style lang="scss" scoped>
.explorer-container {
  max-inline-size: 100%;
  min-inline-size: 0;
}

.explorer-workspace {
  display: grid;
  align-items: start;
  gap: 1.25rem;
  grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
}

.explorer-workspace--collapsed {
  grid-template-columns: minmax(0, 1fr);
}

.explorer-sidebar {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  min-block-size: 0;
  min-inline-size: 0;
}

.explorer-sidebar-body {
  overflow-y: auto;
  max-block-size: calc(100vh - 280px);
}

.explorer-workbench {
  min-inline-size: 0;
}

.equipment-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
}
</style>
