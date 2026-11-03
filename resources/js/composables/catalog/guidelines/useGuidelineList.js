/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { getGuidelineTableHeaders } from '@/utils/catalog-constants'
import { computed, ref } from 'vue'

/**
 * useGuidelineList
 * ─────────────────────────────────────────────────────────────────────────────
 * Guidelines list orchestration composable.
 */
export function useGuidelineList() {
  const guidelineStore = useGuidelineStore()

  const allowedSortFields = new Set(['id', 'code', 'steps_count', 'is_active', 'created_at'])

  // ── View Mode ────────────────────────────────────────────────────────────
  const isGridView = ref(false)

  // ── Headers & Options ────────────────────────────────────────────────────
  const headers = computed(() => getGuidelineTableHeaders())

  const statusOptions = [
    { value: '1', title: 'Active' },
    { value: '0', title: 'Inactive' },
  ]

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
      }

      if (params.is_active !== null && params.is_active !== undefined) {
        query['filter[is_active]'] = params.is_active
      }

      const res = await guidelineStore.fetchGuidelines(query)
      updateKpisFromList(guidelineStore.guidelines)

      return res
    },
    initialFilters: {
      is_active: null,
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
    inactive: 0,
    totalSteps: 0,
  })

  const metaLoading = ref(false)

  const updateKpisFromList = items => {
    if (!Array.isArray(items)) return
    const active = items.filter(g => g.is_active).length
    const inactive = items.filter(g => !g.is_active).length
    const totalSteps = items.reduce((acc, g) => acc + (g.steps_count || g.steps?.length || 0), 0)

    statistics.value = {
      total: guidelineStore.totalGuidelines,
      active,
      inactive,
      totalSteps,
    }
  }

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      updateKpisFromList(guidelineStore.guidelines)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'catalog.guidelines.list.kpis.total_guidelines',
      value: guidelineStore.totalGuidelines,
      icon: 'tabler-checklist',
      iconColor: 'primary',
      filterType: null,
      filterValue: null,
    },
    {
      titleKey: 'catalog.guidelines.list.kpis.active',
      value: statistics.value.active,
      icon: 'tabler-circle-check',
      iconColor: 'success',
      filterType: 'is_active',
      filterValue: '1',
    },
    {
      titleKey: 'catalog.guidelines.list.kpis.inactive',
      value: statistics.value.inactive,
      icon: 'tabler-alert-circle',
      iconColor: 'warning',
      filterType: 'is_active',
      filterValue: '0',
    },
    {
      titleKey: 'catalog.guidelines.list.kpis.total_steps',
      value: statistics.value.totalSteps,
      icon: 'tabler-list-numbers',
      iconColor: 'info',
      filterType: null,
      filterValue: null,
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

  return {
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

    // Headers & Options
    headers,
    statusOptions,

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
