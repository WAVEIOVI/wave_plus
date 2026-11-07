<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppBreadcrumb,
  AppFabSpeedDial,
  AppHybridTabs,
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
import {
  operatingStatusOptions,
  resolveRiskVariant,
  resolveSiteStatusVariant,
  resolveSiteTypeVariant,
  riskClassOptions,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
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
import SiteAreaSearchDialog from '@/views/client-assets/sites/edit/SiteAreaSearchDialog.vue'
import SiteEquipmentAnalyticsPanel from '@/views/client-assets/sites/edit/SiteEquipmentAnalyticsPanel.vue'
import SiteLocationExplorer from '@/views/client-assets/sites/edit/SiteLocationExplorer.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  siteId: { type: [Number, String], default: null },
  readonly: { type: Boolean, default: false },
})

const route = useRoute()
const router = useRouter()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { lgAndDown, smAndDown } = useDisplay()
const { optionTitle } = useSiteConstants()

const targetSiteId = computed(() => props.siteId || route.params.id)

// ── Core Data ────────────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref(null)
const fetchedSite = ref(null)
const filterBarRef = ref(null)

const {
  loadingAreas,
  siteAreasFlat,
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
const isZoneSearchDialogOpen = ref(false)
const isSidebarCollapsed = ref(false)
const isMobileFabOpen = ref(false)

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

const isDeleteAreaDialogOpen = ref(false)
const selectedAreaForDelete = ref(null)
const isDeletingArea = ref(false)

// ── Navigation ───────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Client Assets'), icon: 'tabler-building-estate' },
  { title: t('Sites'), to: '/client-assets/sites/list', icon: 'tabler-map-pin' },
  {
    title: fetchedSite.value ? localizedValue(fetchedSite.value.name) : t('Inventory Explorer'),
    to: targetSiteId.value ? { name: 'client-assets-sites-edit-id', params: { id: targetSiteId.value } } : undefined,
    icon: 'tabler-building-estate',
  },
  {
    title: t('Inventory Explorer'),
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

  return siteAreasFlat.value.find(a => String(a.id) === String(selectedLocationAreaId.value)) || null
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
      const targetIds = [selectedLocationAreaId.value, ...descendants].map(String)

      list = list.filter(eq => targetIds.includes(String(eq.area_id)) || (eq.area?.id && targetIds.includes(String(eq.area.id))))
    }
    else {
      list = list.filter(eq => String(eq.area_id) === String(selectedLocationAreaId.value) || String(eq.area?.id) === String(selectedLocationAreaId.value))
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
    list = list.filter(eq => String(eq.equipment_class_id) === String(filterClassId.value))

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

// ── Standard Hybrid Tabs & Mobile Bottom Nav ─────────────────────────────────
const viewTabs = computed(() => [
  {
    value: 'cards',
    title: t('Cards'),
    label: t('Cards'),
    icon: 'tabler-layout-grid',
    badge: filteredEquipment.value.length,
  },
  {
    value: 'table',
    title: t('Table'),
    label: t('Table'),
    icon: 'tabler-table',
    badge: filteredEquipment.value.length,
  },
  {
    value: 'analytics',
    title: t('Analytics'),
    label: t('Analytics'),
    icon: 'tabler-chart-pie',
  },
])

const mobileNavItems = computed(() => viewTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
})))

const mobileFabActions = computed(() => {
  const actions = [
    {
      id: 'zone-picker',
      icon: 'tabler-hierarchy',
      title: t('Filter by Zone'),
      color: 'info',
      onClick: () => { isZoneSearchDialogOpen.value = true },
    },
    {
      id: 'filter-equipment',
      icon: 'tabler-filter',
      title: t('Filter Equipment'),
      color: 'primary',
      onClick: () => filterBarRef.value?.openFilterDialog(),
    },
  ]

  if (!props.readonly) {
    actions.unshift(
      {
        id: 'add-equipment',
        icon: 'tabler-plus',
        title: t('Add Equipment'),
        color: 'primary',
        onClick: () => openAddEquipment(selectedLocationAreaId.value),
      },
      {
        id: 'add-zone',
        icon: 'tabler-sitemap',
        title: t('Add Zone / Area'),
        color: 'info',
        onClick: () => openAreaDialog(null, selectedLocationAreaId.value),
      },
    )
  }

  if (targetSiteId.value) {
    actions.push({
      id: 'site-journal',
      icon: 'tabler-building-estate',
      title: t('Site Journal'),
      color: 'secondary',
      onClick: () => router.push({ name: 'client-assets-sites-edit-id', params: { id: targetSiteId.value } }),
    })
  }

  return actions
})

// ── Actions ──────────────────────────────────────────────────────────────────
const onSelectLocation = id => {
  selectedLocationAreaId.value = id ?? null
  isZoneSearchDialogOpen.value = false
}

const openAddEquipment = (areaId = null) => {
  if (areaId !== undefined && areaId !== null)
    selectedLocationAreaId.value = areaId
  isAddEquipmentDialogOpen.value = true
}

const openEditEquipment = eq => {
  selectedEquipmentForEditId.value = typeof eq === 'object' ? eq.id : eq
  isEditEquipmentDialogOpen.value = true
}

const openViewEquipment = eqId => {
  selectedViewEquipmentId.value = typeof eqId === 'object' ? eqId.id : eqId
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
    await store.deleteEquipment(selectedEquipmentItem.value.id)
    snackbarStore.showMessage(t('Equipment deleted successfully'), 'success')
    isDeleteEquipmentDialogOpen.value = false
    selectedEquipmentItem.value = null
    await Promise.allSettled([fetchEquipment(), fetchAreas()])
  }
  catch (err) {
    snackbarStore.showMessage(err?.data?.message || err?.message || t('Failed to delete equipment'), 'error')
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

const handleDeleteArea = area => {
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

  selectedAreaForDelete.value = area
  isDeleteAreaDialogOpen.value = true
}

const confirmDeleteArea = async () => {
  if (!selectedAreaForDelete.value?.id) return
  isDeletingArea.value = true
  try {
    await store.deleteSiteArea(selectedAreaForDelete.value.id)
    snackbarStore.showMessage(t('Area deleted successfully'), 'success')
    if (String(selectedLocationAreaId.value) === String(selectedAreaForDelete.value.id))
      selectedLocationAreaId.value = null
    isDeleteAreaDialogOpen.value = false
    selectedAreaForDelete.value = null
    await Promise.allSettled([fetchAreas(), fetchEquipment()])
  }
  catch (err) {
    console.error(err)
    snackbarStore.showMessage(err?.data?.message || t('Failed to delete area'), 'error')
  }
  finally {
    isDeletingArea.value = false
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
      class="explorer-container d-flex flex-column gap-5"
    >
      <!-- Responsive Entity Header Card (Mobile-First) -->
      <VCard
        class="explorer-header-card border"
        variant="outlined"
      >
        <VCardText class="pa-4 pa-sm-5">
          <div class="d-flex flex-column flex-md-row align-start align-md-center justify-space-between gap-4">
            <!-- Site Identity & Metadata -->
            <div class="d-flex align-start align-sm-center gap-3 min-w-0 w-100">
              <VAvatar
                :size="smAndDown ? 48 : 56"
                :color="resolveSiteTypeVariant(fetchedSite.site_type)"
                variant="tonal"
                rounded="lg"
                class="shrink-0 border"
              >
                <VImg
                  v-if="fetchedSite.main_photo_url || fetchedSite.photos?.[0]?.url"
                  :src="fetchedSite.main_photo_url || fetchedSite.photos?.[0]?.url"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-building-estate"
                  :size="smAndDown ? 24 : 28"
                />
              </VAvatar>

              <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-center flex-wrap gap-2 mb-1">
                  <h1 class="text-h6 text-sm-h5 font-weight-bold mb-0 text-truncate">
                    {{ localizedValue(fetchedSite.name) }}
                  </h1>
                  <VChip
                    v-if="fetchedSite.site_code"
                    size="x-small"
                    color="secondary"
                    variant="flat"
                    label
                    class="font-weight-bold"
                  >
                    {{ fetchedSite.site_code }}
                  </VChip>
                  <VChip
                    v-if="fetchedSite.status"
                    size="x-small"
                    :color="resolveSiteStatusVariant(fetchedSite.status)"
                    label
                    class="text-capitalize"
                  >
                    {{ optionTitle(operatingStatusOptions, fetchedSite.status) }}
                  </VChip>
                  <VChip
                    v-if="fetchedSite.risk_class"
                    size="x-small"
                    :color="resolveRiskVariant(fetchedSite.risk_class)"
                    variant="tonal"
                    label
                    class="text-capitalize"
                  >
                    {{ optionTitle(riskClassOptions, fetchedSite.risk_class) }}
                  </VChip>
                </div>

                <div class="d-flex align-center flex-wrap gap-x-3 gap-y-1 text-body-2 text-medium-emphasis">
                  <span
                    v-if="fetchedSite.client"
                    class="d-inline-flex align-center gap-1 font-weight-medium text-high-emphasis"
                  >
                    <VIcon
                      icon="tabler-building-skyscraper"
                      size="15"
                      color="primary"
                    />
                    {{ localizedValue(fetchedSite.client.commercial_name, localizedValue(fetchedSite.client.legal_name)) }}
                  </span>
                  <span
                    v-if="fetchedSite.address?.city"
                    class="d-inline-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-map-pin"
                      size="15"
                      color="error"
                    />
                    {{ localizedValue(fetchedSite.address.city) }}
                  </span>
                  <span class="d-inline-flex align-center gap-1">
                    <VIcon
                      icon="tabler-packages"
                      size="15"
                      color="info"
                    />
                    {{ $t('Physical Asset & Zone Explorer') }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Action Buttons (Responsive: Full toolbar on md+, compact quick bar on mobile) -->
            <div class="d-flex align-center gap-2 flex-wrap shrink-0 w-100 w-md-auto justify-end">
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-building-estate"
                :to="{ name: 'client-assets-sites-edit-id', params: { id: targetSiteId } }"
              >
                {{ $t('Site Journal') }}
              </VBtn>

              <VBtn
                variant="tonal"
                color="info"
                size="small"
                prepend-icon="tabler-hierarchy"
                @click="isZoneSearchDialogOpen = true"
              >
                {{ $t('Browse Zones') }}
              </VBtn>

              <VBtn
                v-if="!readonly && !smAndDown"
                variant="tonal"
                color="info"
                size="small"
                prepend-icon="tabler-sitemap"
                @click="openAreaDialog(null, selectedLocationAreaId)"
              >
                {{ $t('Add Zone') }}
              </VBtn>

              <VBtn
                v-if="!readonly"
                color="primary"
                size="small"
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
        </VCardText>
      </VCard>

      <!-- KPI Cards -->
      <WpKpiGroup
        v-if="equipmentViewMode !== 'analytics'"
        :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
      >
        <template #default="{ getColProps }">
          <VCol v-bind="getColProps(0)">
            <WpKpiCard
              :title="$t('Zones & Areas')"
              :value="siteAreasFlat.length"
              icon="tabler-hierarchy"
              icon-color="info"
              :loading="loadingAreas"
              clickable
              @click="isZoneSearchDialogOpen = true"
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

      <!-- Mobile / Tablet Active Zone Filter Banner (Opens Standard SiteAreaSearchDialog) -->
      <VCard
        v-if="lgAndDown"
        variant="outlined"
        class="pa-3 border bg-surface"
      >
        <div class="d-flex align-center justify-space-between flex-wrap gap-2">
          <div
            class="d-flex align-center gap-2 min-w-0 flex-grow-1 cursor-pointer"
            @click="isZoneSearchDialogOpen = true"
          >
            <VAvatar
              size="34"
              :color="selectedLocationAreaId ? 'primary' : 'info'"
              variant="tonal"
              rounded
            >
              <VIcon
                icon="tabler-hierarchy"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-caption text-medium-emphasis">
                {{ $t('Active Zone Filter') }}
              </div>
              <div class="text-subtitle-2 font-weight-bold text-truncate">
                {{ selectedAreaLabel }}
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-1">
            <VBtn
              v-if="selectedLocationAreaId"
              size="x-small"
              variant="text"
              color="secondary"
              icon="tabler-x"
              :title="$t('Reset to All Site Inventory')"
              @click="selectedLocationAreaId = null"
            />
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-search"
              @click="isZoneSearchDialogOpen = true"
            >
              {{ $t('Change Zone') }}
            </VBtn>
          </div>
        </div>
      </VCard>

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
                color="primary"
                :title="$t('Search Zones Dialog')"
                @click="isZoneSearchDialogOpen = true"
              >
                <VIcon
                  icon="tabler-search"
                  size="16"
                />
              </IconBtn>
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

        <!-- Main Workbench with Standard AppHybridTabs -->
        <div class="explorer-workbench min-w-0">
          <AppHybridTabs
            v-model="equipmentViewMode"
            :tabs="viewTabs"
            :swipeable="false"
            crossfade
          >
            <template #append>
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

                <VChip
                  :color="selectedLocationAreaId ? 'primary' : 'info'"
                  variant="tonal"
                  size="small"
                  :closable="Boolean(selectedLocationAreaId)"
                  class="cursor-pointer font-weight-medium"
                  @click="isZoneSearchDialogOpen = true"
                  @click:close="selectedLocationAreaId = null"
                >
                  <VIcon
                    start
                    icon="tabler-hierarchy"
                    size="14"
                  />
                  {{ selectedAreaLabel }}
                </VChip>
              </div>
            </template>

            <!-- Cards Tab -->
            <template #tab-cards>
              <SiteEquipmentFilterBar
                ref="filterBarRef"
                v-model:search-query="searchQuery"
                v-model:lifecycle-status="filterLifecycleStatus"
                v-model:compliance-status="filterComplianceStatus"
                v-model:criticality="filterCriticality"
                v-model:class-id="filterClassId"
                :class-options="availableEquipmentClasses"
                :selected-area-label="selectedAreaLabel"
                :has-selected-area="Boolean(selectedLocationAreaId)"
                show-class-filter
                class="mb-4"
                @open-zone-picker="isZoneSearchDialogOpen = true"
                @clear-zone="selectedLocationAreaId = null"
                @reset="resetFilters"
              />

              <WpLoadingState
                v-if="loadingEquipment"
                :text="$t('Loading equipment...')"
                min-height="200px"
              />

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

              <template v-else>
                <div class="equipment-grid">
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

                <WpPagination
                  v-model:page="page"
                  v-model:items-per-page="itemsPerPage"
                  :total-items="filteredEquipment.length"
                  :page-size-options="[10, 12, 25, 50]"
                  class="mt-4"
                />
              </template>
            </template>

            <!-- Table Tab -->
            <template #tab-table>
              <SiteEquipmentFilterBar
                v-model:search-query="searchQuery"
                v-model:lifecycle-status="filterLifecycleStatus"
                v-model:compliance-status="filterComplianceStatus"
                v-model:criticality="filterCriticality"
                v-model:class-id="filterClassId"
                :class-options="availableEquipmentClasses"
                :selected-area-label="selectedAreaLabel"
                :has-selected-area="Boolean(selectedLocationAreaId)"
                show-class-filter
                class="mb-4"
                @open-zone-picker="isZoneSearchDialogOpen = true"
                @clear-zone="selectedLocationAreaId = null"
                @reset="resetFilters"
              />

              <WpLoadingState
                v-if="loadingEquipment"
                :text="$t('Loading equipment...')"
                min-height="200px"
              />

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

              <template v-else>
                <SiteEquipmentTable
                  :items="paginatedEquipment"
                  :readonly="readonly"
                  :resolve-area-label="areaLabel"
                  @view="openViewEquipment"
                  @edit="openEditEquipment"
                  @delete="openDeleteEquipment"
                />

                <WpPagination
                  v-model:page="page"
                  v-model:items-per-page="itemsPerPage"
                  :total-items="filteredEquipment.length"
                  :page-size-options="[10, 12, 25, 50]"
                  class="mt-4"
                />
              </template>
            </template>

            <!-- Analytics Tab -->
            <template #tab-analytics>
              <SiteEquipmentAnalyticsPanel
                :equipment="filteredEquipment"
                :areas-flat="siteAreasFlat"
                :show-open-inventory="false"
              />
            </template>
          </AppHybridTabs>
        </div>
      </div>
    </div>

    <!-- Standard Zone Filter Dialog (Replaces Mobile Navigation Drawer) -->
    <SiteAreaSearchDialog
      v-model="isZoneSearchDialogOpen"
      :site-id="targetSiteId"
      :areas-list="siteAreasFlat"
      :current-area-id="selectedLocationAreaId"
      allow-root
      :root-label="$t('All Site Inventory')"
      :title="$t('Filter by Zone / Building Area')"
      @select="area => onSelectLocation(area?.id ?? null)"
    />

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

    <WpDeleteDialog
      v-model:is-open="isDeleteAreaDialogOpen"
      :title="$t('Delete Zone / Area')"
      :message="$t('Are you sure you want to delete zone {name}? This action cannot be undone.', { name: localizedValue(selectedAreaForDelete?.name) || '' })"
      :loading="isDeletingArea"
      @confirm="confirmDeleteArea"
    />

    <!-- Mobile Bottom Navigation -->
    <AppBottomNav
      v-if="smAndDown && fetchedSite"
      v-model="equipmentViewMode"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      :aria-label="$t('Inventory Explorer views')"
    />

    <!-- Mobile Speed Dial FAB -->
    <AppFabSpeedDial
      v-if="smAndDown && fetchedSite"
      v-model="isMobileFabOpen"
      class="d-md-none explorer-mobile-fab"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.explorer-container {
  max-inline-size: 100%;
  min-inline-size: 0;
}

.explorer-header-card {
  background: rgb(var(--v-theme-surface));
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

@media (max-width: 959px) {
  .site-inventory-explorer {
    padding-block-end: 7rem !important;
  }

  .explorer-mobile-fab {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
