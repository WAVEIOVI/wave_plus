/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { getBlueprintEquipmentClassLabel } from '@/composables/useCatalogSelection'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import {
  getBlueprintStatuses,
  getBlueprintTableHeaders,
  getEquipmentClasses,
} from '@/utils/catalog-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useEquipmentBlueprintList
 * ─────────────────────────────────────────────────────────────────────────────
 * Equipment Blueprints list orchestration composable.
 *
 * Architecture:
 *   Page → useEquipmentBlueprintList → useListPage → useEquipmentBlueprintStore → API
 */
export function useEquipmentBlueprintList() {
  const blueprintStore = useEquipmentBlueprintStore()
  const supportStore = useCatalogSupportStore()

  const allowedSortFields = new Set(['id', 'code', 'equipment_class_id', 'status', 'created_at'])

  // ── View Mode (List vs Grid) ─────────────────────────────────────────────
  const isGridView = ref(false)

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getBlueprintStatuses())
  const classOptions = computed(() => getEquipmentClasses())
  const headers = computed(() => getBlueprintTableHeaders())

  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
  const getClassTitle = blueprint => getBlueprintEquipmentClassLabel(blueprint)

  // ── Color / variant resolvers ────────────────────────────────────────────
  const resolveStatusVariant = stat => ({
    active: 'success',
    draft: 'warning',
    inactive: 'error',
  }[stat?.toLowerCase()] || 'primary')

  const resolveClassVariant = cls => {
    const map = {
      portableExtinguisher: 'info',
      portable_extinguisher: 'info',
      wheeledExtinguisher: 'warning',
      wheeled_extinguisher: 'warning',
      fixedSuppressionSystem: 'error',
      fixed_suppression_system: 'error',
      detectionPanel: 'success',
      detection_panel: 'success',
      detector: 'primary',
      hoseReel: 'secondary',
      hose_reel: 'secondary',
      fireBlanket: 'info',
      fire_blanket: 'info',
      other: 'default',
    }

    const key = typeof cls === 'object' && cls !== null ? (cls.code || '') : (cls || '')

    return map[String(key).toLowerCase()] || 'primary'
  }

  // ── List page orchestration ──────────────────────────────────────────────
  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'created_at'

      const sort = sortField
        ? `${params.sort_order === 'desc' ? '-' : ''}${sortField}`
        : '-created_at'

      const query = {
        page: params.page,
        itemsPerPage: params.per_page,
        q: params.search,
        sort,
        'filter[status]': params.status || undefined,
        'filter[equipment_class_id]': params.equipment_class_id || undefined,
      }

      const res = await blueprintStore.fetchBlueprints(query)
      updateKpisFromList(blueprintStore.blueprints)

      return res
    },
    initialFilters: {
      status: null,
      equipment_class_id: null,
    },
    debounceMs: 400,
  })

  // ── Row selection ────────────────────────────────────────────────────────
  const {
    selected: selectedItems,
    clearSelection,
  } = useSelection()

  // ── Statistics / KPIs ────────────────────────────────────────────────────
  const statistics = ref({
    total: 0,
    active: 0,
    draft: 0,
    inactive: 0,
  })

  const metaLoading = ref(false)

  const updateKpisFromList = items => {
    if (!Array.isArray(items)) return
    const active = items.filter(b => b.status === 'active').length
    const draft = items.filter(b => b.status === 'draft').length
    const inactive = items.filter(b => b.status === 'inactive').length

    statistics.value = {
      total: blueprintStore.totalBlueprints,
      active,
      draft,
      inactive,
    }
  }

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      // Re-trigger fetch to get latest counts
      updateKpisFromList(blueprintStore.blueprints)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'catalog.blueprints.list.kpis.total_blueprints',
      value: blueprintStore.totalBlueprints,
      icon: 'tabler-file-settings',
      iconColor: 'primary',
      filterType: null,
      filterValue: null,
    },
    {
      titleKey: 'catalog.blueprints.list.kpis.active',
      value: statistics.value.active,
      icon: 'tabler-circle-check',
      iconColor: 'success',
      filterType: 'status',
      filterValue: 'active',
    },
    {
      titleKey: 'catalog.blueprints.list.kpis.draft',
      value: statistics.value.draft,
      icon: 'tabler-file-pencil',
      iconColor: 'warning',
      filterType: 'status',
      filterValue: 'draft',
    },
    {
      titleKey: 'catalog.blueprints.list.kpis.inactive',
      value: statistics.value.inactive,
      icon: 'tabler-alert-circle',
      iconColor: 'error',
      filterType: 'status',
      filterValue: 'inactive',
    },
  ])

  const handleKpiClick = kpi => {
    if (!kpi || !kpi.filterType) {
      listPage.resetFilters()

      return
    }

    const currentVal = listPage.filters[kpi.filterType]

    if (currentVal === kpi.filterValue) {
      listPage.setFilter(kpi.filterType, null)
    }
    else {
      listPage.setFilter(kpi.filterType, kpi.filterValue)
    }
  }

  onMounted(async () => {
    if (!supportStore.equipmentClasses.length) {
      await supportStore.fetchEquipmentClasses()
    }
  })

  return {
    // List state forwarded from useListPage
    items: listPage.rows,
    totalItems: listPage.totalItems,
    loading: listPage.loading,
    error: listPage.error,
    search: listPage.search,
    filters: listPage.filters,
    stagedFilters: listPage.stagedFilters,
    activeFilters: listPage.activeFilters,
    activeFilterCount: listPage.activeFilterCount,
    setFilter: listPage.setFilter,
    removeFilter: listPage.removeFilter,
    resetFilters: listPage.resetFilters,
    stageFilters: listPage.stageFilters,
    applyStaged: listPage.applyStaged,
    resetStaged: listPage.resetStaged,
    page: listPage.page,
    itemsPerPage: listPage.itemsPerPage,
    sortBy: listPage.sortBy,
    refresh: listPage.refresh,

    // View mode
    isGridView,

    // Options & resolvers
    headers,
    statusOptions,
    classOptions,
    getStatusTitle,
    getClassTitle,
    resolveStatusVariant,
    resolveClassVariant,

    // Selection
    selectedItems,
    clearSelection,

    // KPIs
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
