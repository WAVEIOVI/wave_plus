import { useBusinessPartnerListPage } from '@/composables/business-partners/useBusinessPartnerListPage'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import {
  getAvailabilityStatuses,
  getBpIndustries,
  getClientStatuses,
  getProviderTiers,
  getServiceProviderTableHeaders,
} from '@/utils/bp-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useServiceProviderList
 * ─────────────────────────────────────────────────────────────────────────────
 * Service Provider list orchestration composable following the Wave Plus reference pattern.
 */
export function useServiceProviderList() {
  const serviceProviderStore = useServiceProviderStore()

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getClientStatuses())
  const tierOptions = computed(() => getProviderTiers())
  const availabilityOptions = computed(() => getAvailabilityStatuses())
  const industryOptions = computed(() => getBpIndustries())
  const headers = computed(() => getServiceProviderTableHeaders())

  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
  const getTierTitle = val => tierOptions.value.find(t => t.value === val)?.title || val
  const getAvailabilityTitle = val => availabilityOptions.value.find(a => a.value === val)?.title || val
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

  const resolveTierVariant = tier => ({
    preferred: 'success',
    standard: 'info',
    certified_partner: 'primary',
    'tier_1': 'primary',
    'tier_2': 'info',
    'tier_3': 'secondary',
    backup: 'warning',
  }[tier?.toLowerCase()] || 'primary')

  const resolveAvailabilityVariant = avail => ({
    available: 'success',
    partially_available: 'warning',
    busy: 'warning',
    unavailable: 'error',
    'on_leave': 'secondary',
  }[avail?.toLowerCase()] || 'primary')

  // ── List page orchestration ──────────────────────────────────────────────
  const listPage = useBusinessPartnerListPage({
    fetchFn: query => serviceProviderStore.fetchServiceProviders(query),
    filterKeys: ['status', 'provider_tier', 'industry'],
    sortFields: ['id', 'legal_name', 'service_provider_id', 'status', 'provider_tier', 'created_at'],
    sortFieldMap: { 'service_provider': 'legal_name' },
  })

  const { selected: selectedServiceProviders, clearSelection } = listPage

  // ── KPI / Statistics ─────────────────────────────────────────────────────

  const metaLoading = ref(false)

  const statistics = ref({
    totalServiceProviders: 0,
    totalActiveServiceProviders: 0,
    totalInactiveServiceProviders: 0,
    totalProspectServiceProviders: 0,
    totalSuspendedServiceProviders: 0,
  })

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      const response = await serviceProviderStore.fetchMetaServiceProviders()
      if (response?.data) {
        statistics.value = {
          totalServiceProviders: response.data.totalServiceProviders ?? response.data.total ?? 0,
          totalActiveServiceProviders: response.data.totalActiveServiceProviders ?? response.data.active ?? 0,
          totalInactiveServiceProviders: response.data.totalInactiveServiceProviders ?? response.data.inactive ?? 0,
          totalProspectServiceProviders: response.data.totalProspectServiceProviders ?? response.data.prospect ?? 0,
          totalSuspendedServiceProviders: response.data.totalSuspendedServiceProviders ?? response.data.suspended ?? 0,
        }
      }
    }
    catch (err) {
      console.error('Error fetching service provider metadata:', err)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'service_providers.list.stats.total',
      value: statistics.value.totalServiceProviders,
      icon: 'tabler-tool',
      filterValue: null,
      iconColor: 'primary',
    },
    {
      titleKey: 'service_providers.list.stats.active',
      value: statistics.value.totalActiveServiceProviders,
      icon: 'tabler-user-check',
      filterValue: 'active',
      iconColor: 'success',
    },
    {
      titleKey: 'service_providers.list.stats.prospect',
      value: statistics.value.totalProspectServiceProviders,
      icon: 'tabler-target-arrow',
      filterValue: 'prospect',
      iconColor: 'info',
    },
    {
      titleKey: 'service_providers.list.stats.inactive',
      value: statistics.value.totalInactiveServiceProviders,
      icon: 'tabler-user-exclamation',
      filterValue: 'inactive',
      iconColor: 'secondary',
    },
    {
      titleKey: 'service_providers.list.stats.suspended',
      value: statistics.value.totalSuspendedServiceProviders,
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

  onMounted(() => {
    fetchStatistics()
  })

  return {
    ...listPage,
    serviceProviders: listPage.rows,
    headers,
    statusOptions,
    tierOptions,
    availabilityOptions,
    industryOptions,
    getStatusTitle,
    getTierTitle,
    getAvailabilityTitle,
    getIndustryTitle,
    resolveStatusVariant,
    resolveTierVariant,
    resolveAvailabilityVariant,
    selectedServiceProviders,
    clearSelection,
    statistics,
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
