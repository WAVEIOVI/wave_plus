import { useBusinessPartnerListPage } from '@/composables/business-partners/useBusinessPartnerListPage'
import { useSupplierStore } from '@/stores/useSupplierStore'
import {
  getBpIndustries,
  getClientStatuses,
  getSupplierCategories,
  getSupplierTableHeaders,
} from '@/utils/bp-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useSupplierList
 * ─────────────────────────────────────────────────────────────────────────────
 * Supplier list orchestration composable following the Wave Plus reference pattern.
 */
export function useSupplierList() {
  const supplierStore = useSupplierStore()

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getClientStatuses())
  const categoryOptions = computed(() => getSupplierCategories())
  const industryOptions = computed(() => getBpIndustries())
  const headers = computed(() => getSupplierTableHeaders())

  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
  const getCategoryTitle = val => categoryOptions.value.find(c => c.value === val)?.title || val
  const getIndustryTitle = val => industryOptions.value.find(i => i.value === val)?.title || val

  // ── Color / variant resolvers ────────────────────────────────────────────
  const resolveStatusVariant = stat => ({
    prospect: 'info',
    active: 'success',
    inactive: 'secondary',
    suspended: 'warning',
    blacklisted: 'error',
    archived: 'default',
  }[stat?.toLowerCase()] || 'primary')

  const resolveCategoryVariant = cat => ({
    general: 'primary',
    manufacturer: 'success',
    wholesaler: 'info',
    distributor: 'warning',
  }[cat?.toLowerCase()] || 'secondary')

  // ── List page orchestration ──────────────────────────────────────────────
  const listPage = useBusinessPartnerListPage({
    fetchFn: query => supplierStore.fetchSuppliers(query),
    filterKeys: ['status', 'supplier_category', 'industry'],
    sortFields: ['id', 'legal_name', 'supplier_id', 'status', 'supplier_category', 'created_at'],
    sortFieldMap: { supplier: 'legal_name' },
  })

  const { selected: selectedSuppliers, clearSelection } = listPage

  // ── KPI / Statistics ─────────────────────────────────────────────────────

  const metaLoading = ref(false)

  const statistics = ref({
    totalSuppliers: 0,
    totalActiveSuppliers: 0,
    totalInactiveSuppliers: 0,
    totalProspectSuppliers: 0,
    totalSuspendedSuppliers: 0,
  })

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      const response = await supplierStore.fetchMetaSuppliers()
      if (response?.data) {
        statistics.value = {
          totalSuppliers: response.data.total_suppliers || response.data.total || 0,
          totalActiveSuppliers: response.data.total_active_suppliers || response.data.active || 0,
          totalInactiveSuppliers: response.data.total_inactive_suppliers || response.data.inactive || 0,
          totalProspectSuppliers: response.data.total_prospect_suppliers || response.data.prospect || 0,
          totalSuspendedSuppliers: response.data.total_suspended_suppliers || response.data.suspended || 0,
        }
      }
    }
    catch (err) {
      console.warn('[useSupplierList] Failed to fetch statistics:', err?.message)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'suppliers.list.stats.total',
      value: statistics.value.totalSuppliers,
      icon: 'tabler-building-warehouse',
      filterValue: null,
      iconColor: 'primary',
    },
    {
      titleKey: 'suppliers.list.stats.active',
      value: statistics.value.totalActiveSuppliers,
      icon: 'tabler-user-check',
      filterValue: 'active',
      iconColor: 'success',
    },
    {
      titleKey: 'suppliers.list.stats.prospect',
      value: statistics.value.totalProspectSuppliers,
      icon: 'tabler-target-arrow',
      filterValue: 'prospect',
      iconColor: 'info',
    },
    {
      titleKey: 'suppliers.list.stats.inactive',
      value: statistics.value.totalInactiveSuppliers,
      icon: 'tabler-user-exclamation',
      filterValue: 'inactive',
      iconColor: 'secondary',
    },
    {
      titleKey: 'suppliers.list.stats.suspended',
      value: statistics.value.totalSuspendedSuppliers,
      icon: 'tabler-user-pause',
      filterValue: 'suspended',
      iconColor: 'warning',
    },
  ])

  const handleKpiClick = filterValue => {
    if (filterValue === null) {
      listPage.setFilter('status', null)
    }
    else {
      listPage.setFilter(
        'status',
        listPage.filters.status === filterValue ? null : filterValue,
      )
    }
  }

  onMounted(() => fetchStatistics())

  return {
    ...listPage,
    suppliers: listPage.rows,
    headers,
    statusOptions,
    categoryOptions,
    industryOptions,
    getStatusTitle,
    getCategoryTitle,
    getIndustryTitle,
    resolveStatusVariant,
    resolveCategoryVariant,
    selectedSuppliers,
    clearSelection,
    statistics,
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
