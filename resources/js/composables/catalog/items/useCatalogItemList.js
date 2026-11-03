/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { flattenScopedCategories } from '@/composables/useCatalogSelection'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import {
  getCatalogItemStatuses,
  getCatalogItemTableHeaders,
  getCatalogItemTypes,
  getCategorySubtypeOptions,
} from '@/utils/catalog-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useCatalogItemList
 * ─────────────────────────────────────────────────────────────────────────────
 * Catalog Items list orchestration composable.
 *
 * Owns:
 *   - Catalog item list fetching (via useListPage + useCatalogItemStore)
 *   - API parameter mapping (whitelisted sort fields, scoped filters, query)
 *   - KPI / statistics fetching (independent parallel meta call)
 *   - Table column configuration and view mode toggle (Table vs Grid)
 *   - Status / type / subtype / category option lookups and resolvers
 *   - Row selection state (via useSelection)
 *
 * Architecture:
 *   Page → useCatalogItemList → useListPage → useCatalogItemStore → API
 */
export function useCatalogItemList() {
  const catalogStore = useCatalogItemStore()
  const supportStore = useCatalogSupportStore()

  const allowedSortFields = new Set(['id', 'code', 'item_type', 'status', 'created_at'])

  // ── View Mode (List vs Grid) ─────────────────────────────────────────────
  const isGridView = ref(false)

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getCatalogItemStatuses())
  const typeOptions = computed(() => getCatalogItemTypes())
  const headers = computed(() => getCatalogItemTableHeaders())
  const subtypeOptions = getCategorySubtypeOptions()

  const categoryItems = computed(() =>
    flattenScopedCategories(supportStore.categories, null, null).map(row => ({
      value: row.category.id,
      title: row.path,
    })),
  )

  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
  const getTypeTitle = val => typeOptions.value.find(t => t.value === val)?.title || val
  const getSubtypeTitle = val => subtypeOptions.find(s => s.value === val)?.title?.split(': ')[1] || val || '—'

  // ── Color / variant resolvers ────────────────────────────────────────────
  const resolveStatusVariant = stat => ({
    active: 'success',
    draft: 'warning',
    discontinued: 'error',
  }[stat?.toLowerCase()] || 'primary')

  const resolveTypeVariant = type => ({
    product: 'info',
    consumable: 'warning',
    eq_servicing: 'secondary',
  }[type?.toLowerCase()] || 'primary')

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
        'filter[item_type]': params.item_type || undefined,
        'filter[category_id]': params.category_id || undefined,
      }

      return await catalogStore.fetchItems(query)
    },
    initialFilters: {
      status: null,
      item_type: null,
      category_id: null,
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
    totalItems: 0,
    totalActive: 0,
    totalProducts: 0,
    totalConsumables: 0,
    totalServicing: 0,
  })

  const metaLoading = ref(false)

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      const response = await catalogStore.fetchMeta()
      if (response?.data) {
        statistics.value = {
          totalItems: response.data.total_items || 0,
          totalActive: response.data.total_active || 0,
          totalProducts: response.data.total_products || 0,
          totalConsumables: response.data.total_consumables || 0,
          totalServicing: response.data.total_servicing || 0,
        }
      }
    }
    catch (err) {
      console.error('Failed to load catalog metadata stats:', err)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'catalog.items.list.kpis.total_items',
      value: statistics.value.totalItems,
      icon: 'tabler-packages',
      iconColor: 'primary',
      filterType: null,
      filterValue: null,
    },
    {
      titleKey: 'catalog.items.list.kpis.active',
      value: statistics.value.totalActive,
      icon: 'tabler-circle-check',
      iconColor: 'success',
      filterType: 'status',
      filterValue: 'active',
    },
    {
      titleKey: 'catalog.items.list.kpis.products',
      value: statistics.value.totalProducts,
      icon: 'tabler-box',
      iconColor: 'info',
      filterType: 'item_type',
      filterValue: 'product',
    },
    {
      titleKey: 'catalog.items.list.kpis.consumables',
      value: statistics.value.totalConsumables,
      icon: 'tabler-flame',
      iconColor: 'warning',
      filterType: 'item_type',
      filterValue: 'consumable',
    },
    {
      titleKey: 'catalog.items.list.kpis.servicing',
      value: statistics.value.totalServicing,
      icon: 'tabler-tools',
      iconColor: 'secondary',
      filterType: 'item_type',
      filterValue: 'eq_servicing',
    },
  ])

  const handleKpiClick = kpi => {
    if (!kpi || !kpi.filterType) {
      // Clear filters
      listPage.setFilter('status', null)
      listPage.setFilter('item_type', null)

      return
    }

    const currentVal = listPage.filters[kpi.filterType]
    if (currentVal === kpi.filterValue) {
      // Toggle off
      listPage.setFilter(kpi.filterType, null)
    }
    else {
      // If clicking status KPI, clear item_type filter or vice-versa to keep focus
      if (kpi.filterType === 'status') {
        listPage.setFilter('status', kpi.filterValue)
      }
      else if (kpi.filterType === 'item_type') {
        listPage.setFilter('item_type', kpi.filterValue)
      }
    }
  }

  // ── Lifecycle ────────────────────────────────────────────────────────────
  onMounted(() => {
    fetchStatistics()
    supportStore.fetchCategories({ roots_only: false })
    supportStore.fetchBrands()
    supportStore.fetchUom()
  })

  return {
    // List state & items
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

    // Options & headers
    headers,
    statusOptions,
    typeOptions,
    categoryItems,
    getStatusTitle,
    getTypeTitle,
    getSubtypeTitle,
    resolveStatusVariant,
    resolveTypeVariant,

    // Selection
    selectedItems,
    clearSelection,

    // KPIs
    statistics,
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
