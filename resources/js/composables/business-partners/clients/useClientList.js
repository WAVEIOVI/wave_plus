import { useBusinessPartnerListPage } from '@/composables/business-partners/useBusinessPartnerListPage'
import { useClientStore } from '@/stores/useClientStore'
import {
  getBpIndustries,
  getClientCategories,
  getClientStatuses,
  getClientTableHeaders,
} from '@/utils/bp-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useClientList
 * ─────────────────────────────────────────────────────────────────────────────
 * Client-specific list orchestration composable.
 *
 * Owns:
 *   - client list fetching (via useListPage + useClientStore)
 *   - API parameter mapping (sort field mapping, filter formatting)
 *   - KPI / statistics fetching (independent of list)
 *   - Client table column configuration
 *   - Status / category / industry option lookups
 *   - Row selection state
 *
 * Does NOT own:
 *   - Dialog state (that's useClientDialogs)
 *   - Delete/create/email actions (that's useClientActions)
 *   - Generic list pagination/sorting/filter behavior (that's useListPage)
 *
 * Architecture:
 *   Page → useClientList → useListPage → useClientStore → API
 *
 * Usage:
 *   const { clients, loading, ... } = useClientList()
 */
export function useClientList() {
  const clientStore = useClientStore()

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getClientStatuses())
  const categoryOptions = computed(() => getClientCategories())
  const industryOptions = computed(() => getBpIndustries())
  const headers = computed(() => getClientTableHeaders())

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
    unclassified: 'secondary',
    individual: 'info',
    standard: 'primary',
    enterprise: 'success',
    premium: 'warning',
    group: 'error',
    vip: 'warning',
    wholesale: 'info',
    retail: 'success',
  }[cat?.toLowerCase()] || 'primary')

  // ── List page orchestration ──────────────────────────────────────────────
  // The sortFieldMap tells useListPage how to translate UI column keys to
  // backend-accepted sort parameters. This solves the "sort=client" error.
  const listPage = useBusinessPartnerListPage({
    fetchFn: query => clientStore.fetchClients(query),
    filterKeys: ['status', 'client_category', 'industry'],
    sortFields: ['id', 'legal_name', 'client_id', 'status', 'client_category', 'created_at'],
    sortFieldMap: { client: 'legal_name' },
  })

  const { selected: selectedClients, clearSelection } = listPage

  // ── KPI / Statistics ─────────────────────────────────────────────────────

  const metaLoading = ref(false)

  const statistics = ref({
    totalClients: 0,
    totalActiveClients: 0,
    totalInactiveClients: 0,
    totalProspectClients: 0,
    totalSuspendedClients: 0,
  })

  /**
   * Fetch KPI statistics independently from the list fetch.
   * This runs in parallel with the list so neither blocks the other.
   */
  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      const response = await clientStore.fetchMetaClients()
      if (response?.data) {
        statistics.value = {
          totalClients: response.data.total_clients || 0,
          totalActiveClients: response.data.total_active_clients || 0,
          totalInactiveClients: response.data.total_inactive_clients || 0,
          totalProspectClients: response.data.total_prospect_clients || 0,
          totalSuspendedClients: response.data.total_suspended_clients || 0,
        }
      }
    }
    catch (err) {
      // Statistics are non-critical — failure should not block the list
      console.warn('[useClientList] Failed to fetch statistics:', err?.message)
    }
    finally {
      metaLoading.value = false
    }
  }

  // KPI cards configuration (reactive to statistics + active filter)
  const kpiCards = computed(() => [
    {
      title: 'Total',   // translated in page via $t
      titleKey: 'clients.list.stats.total',
      value: statistics.value.totalClients,
      icon: 'tabler-building-store',
      filterValue: null, // null = remove status filter (show all)
      iconColor: 'primary',
    },
    {
      titleKey: 'clients.list.stats.active',
      value: statistics.value.totalActiveClients,
      icon: 'tabler-user-check',
      filterValue: 'active',
      iconColor: 'success',
    },
    {
      titleKey: 'clients.list.stats.prospect',
      value: statistics.value.totalProspectClients,
      icon: 'tabler-target-arrow',
      filterValue: 'prospect',
      iconColor: 'info',
    },
    {
      titleKey: 'clients.list.stats.inactive',
      value: statistics.value.totalInactiveClients,
      icon: 'tabler-user-exclamation',
      filterValue: 'inactive',
      iconColor: 'secondary',
    },
    {
      titleKey: 'clients.list.stats.suspended',
      value: statistics.value.totalSuspendedClients,
      icon: 'tabler-user-pause',
      filterValue: 'suspended',
      iconColor: 'warning',
    },
  ])

  /**
   * Handle KPI card click: set or toggle the status filter.
   * KPI cards are fast-filter shortcuts, NOT the mechanism for loading the table.
   *
   * @param {string|null} filterValue - The status value to filter by, or null to clear
   */
  const handleKpiClick = filterValue => {
    if (filterValue === null) {
      // "Total" card: clear the status filter
      listPage.setFilter('status', null)
    }
    else {
      // Toggle: clicking active KPI again clears the filter
      listPage.setFilter(
        'status',
        listPage.filters.status === filterValue ? null : filterValue,
      )
    }
  }

  // ── Initialize statistics on mount ───────────────────────────────────────
  // Statistics are fetched independently from the list.
  // Both happen in parallel on mount — neither depends on the other.
  onMounted(() => fetchStatistics())

  return {
    // Spread the full list page API for convenience
    ...listPage,

    // Rename rows for clarity
    clients: listPage.rows,

    // Options
    headers,
    statusOptions,
    categoryOptions,
    industryOptions,

    // Resolvers
    getStatusTitle,
    getCategoryTitle,
    getIndustryTitle,
    resolveStatusVariant,
    resolveCategoryVariant,

    // Selection
    selectedClients,
    clearSelection,

    // KPIs
    statistics,
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
