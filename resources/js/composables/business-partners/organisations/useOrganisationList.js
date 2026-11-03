import { useBusinessPartnerListPage } from '@/composables/business-partners/useBusinessPartnerListPage'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import {
  getOrganisationScopes,
  getOrganisationStatuses,
  getOrganisationTableHeaders,
  getOrganisationTypes,
} from '@/utils/bp-constants'
import { computed, onMounted, ref } from 'vue'

/**
 * useOrganisationList
 * ─────────────────────────────────────────────────────────────────────────────
 * Organisation list orchestration composable following the Wave Plus reference pattern.
 */
export function useOrganisationList() {
  const organisationStore = useOrganisationStore()

  // ── Options / lookup maps ────────────────────────────────────────────────
  const statusOptions = computed(() => getOrganisationStatuses())
  const typeOptions = computed(() => getOrganisationTypes())
  const scopeOptions = computed(() => getOrganisationScopes())
  const headers = computed(() => getOrganisationTableHeaders())

  const getStatusTitle = val => statusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
  const getTypeTitle = val => typeOptions.value.find(t => t.value === (val || '').toLowerCase())?.title || val
  const getScopeTitle = val => scopeOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val

  // ── Color / variant resolvers ────────────────────────────────────────────
  const resolveStatusVariant = stat => ({
    prospect: 'info',
    active: 'success',
    inactive: 'secondary',
    archived: 'default',
  }[stat?.toLowerCase()] || 'primary')

  const resolveTypeVariant = type => ({
    'government_agency': 'primary',
    municipality: 'info',
    'public_institution': 'success',
    ngo: 'warning',
    association: 'secondary',
    'professional_body': 'primary',
    'trade_union': 'warning',
    'international_org': 'success',
    other: 'default',
  }[type?.toLowerCase()] || 'secondary')

  // ── List page orchestration ──────────────────────────────────────────────
  const listPage = useBusinessPartnerListPage({
    fetchFn: query => organisationStore.fetchOrganisations(query),
    filterKeys: ['status', 'organisation_type', 'scope'],
    sortFields: ['id', 'legal_name', 'organisation_id', 'status', 'organisation_type', 'created_at'],
    sortFieldMap: { organisation: 'legal_name' },
  })

  const { selected: selectedOrganisations, clearSelection } = listPage

  // ── KPI / Statistics ─────────────────────────────────────────────────────

  const metaLoading = ref(false)

  const statistics = ref({
    totalOrganisations: 0,
    totalActiveOrganisations: 0,
    totalInactiveOrganisations: 0,
    totalProspectOrganisations: 0,
  })

  const fetchStatistics = async () => {
    metaLoading.value = true
    try {
      const response = await organisationStore.fetchMetaOrganisations()
      if (response?.data) {
        statistics.value = {
          totalOrganisations: response.data.total_organisations || response.data.total || 0,
          totalActiveOrganisations: response.data.total_active_organisations || response.data.active || 0,
          totalInactiveOrganisations: response.data.total_inactive_organisations || response.data.inactive || 0,
          totalProspectOrganisations: response.data.total_prospect_organisations || response.data.prospect || 0,
        }
      }
    }
    catch (err) {
      console.warn('[useOrganisationList] Failed to fetch statistics:', err?.message)
    }
    finally {
      metaLoading.value = false
    }
  }

  const kpiCards = computed(() => [
    {
      titleKey: 'organisations.list.stats.total',
      value: statistics.value.totalOrganisations,
      icon: 'tabler-building-community',
      filterValue: null,
      iconColor: 'primary',
    },
    {
      titleKey: 'organisations.list.stats.active',
      value: statistics.value.totalActiveOrganisations,
      icon: 'tabler-check',
      filterValue: 'active',
      iconColor: 'success',
    },
    {
      titleKey: 'organisations.list.stats.prospect',
      value: statistics.value.totalProspectOrganisations,
      icon: 'tabler-target-arrow',
      filterValue: 'prospect',
      iconColor: 'info',
    },
    {
      titleKey: 'organisations.list.stats.inactive',
      value: statistics.value.totalInactiveOrganisations,
      icon: 'tabler-user-exclamation',
      filterValue: 'inactive',
      iconColor: 'secondary',
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
    organisations: listPage.rows,
    headers,
    statusOptions,
    typeOptions,
    scopeOptions,
    getStatusTitle,
    getTypeTitle,
    getScopeTitle,
    resolveStatusVariant,
    resolveTypeVariant,
    selectedOrganisations,
    clearSelection,
    statistics,
    metaLoading,
    kpiCards,
    handleKpiClick,
    fetchStatistics,
  }
}
