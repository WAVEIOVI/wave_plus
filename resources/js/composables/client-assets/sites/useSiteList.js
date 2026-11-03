/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useSiteList
 * ─────────────────────────────────────────────────────────────────────────────
 * Sites list orchestration composable following WAVE PLUS standards.
 */
export function useSiteList() {
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'name',
    'site_type',
    'status',
    'risk_class',
    'created_at',
  ])

  // ── Master options ───────────────────────────────────────────────────────
  const clientsList = ref([])

  const siteTypeOptions = [
    { title: t('Commercial'), value: 'commercial' },
    { title: t('Industrial'), value: 'industrial' },
    { title: t('Residential'), value: 'residential' },
    { title: t('Institutional'), value: 'institutional' },
    { title: t('Other'), value: 'other' },
  ]

  const statusOptions = [
    { title: t('Active'), value: 'active' },
    { title: t('Inactive'), value: 'inactive' },
    { title: t('Under Construction'), value: 'under_construction' },
    { title: t('Decommissioned'), value: 'decommissioned' },
  ]

  const riskOptions = [
    { title: t('Low Risk'), value: 'low' },
    { title: t('Medium Risk'), value: 'medium' },
    { title: t('High Risk'), value: 'high' },
    { title: t('Critical Risk'), value: 'critical' },
  ]

  // ── Headers ──────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('Site Name / Ref'), key: 'name', sortable: true },
    { title: t('Client'), key: 'client', sortable: false },
    { title: t('Type'), key: 'site_type', sortable: true },
    { title: t('Status'), key: 'status', sortable: true },
    { title: t('Risk Class'), key: 'risk_class', sortable: true },
    { title: t('Equipment Count'), key: 'equipment_count', sortable: false, align: 'center' },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ────────────────────────────────────────────────────────────
  const resolveStatusVariant = status => {
    const map = {
      active: 'success',
      inactive: 'secondary',
      under_construction: 'warning',
      decommissioned: 'error',
    }

    return map[status?.toLowerCase()] || 'secondary'
  }

  const resolveRiskVariant = risk => {
    const map = {
      low: 'success',
      medium: 'info',
      high: 'warning',
      critical: 'error',
    }

    return map[risk?.toLowerCase()] || 'secondary'
  }

  const resolveTypeVariant = type => {
    const map = {
      commercial: 'info',
      industrial: 'warning',
      residential: 'primary',
      institutional: 'secondary',
      other: 'secondary',
    }

    return map[type?.toLowerCase()] || 'primary'
  }

  // ── List Orchestration ───────────────────────────────────────────────────
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
        'filter[client_id]': params.client_id || undefined,
        'filter[site_type]': params.site_type || undefined,
        'filter[status]': params.status || undefined,
        'filter[risk_class]': params.risk_class || undefined,
      }

      return await store.fetchSites(query)
    },
    initialFilters: {
      client_id: null,
      site_type: null,
      status: null,
      risk_class: null,
    },
    debounceMs: 400,
  })

  // ── Selection ────────────────────────────────────────────────────────────
  const {
    selected: selectedSites,
    allSelected,
    someSelected,
    toggleSelectAll,
    toggleItem: toggleSiteSelection,
    clearSelection,
  } = useSelection({
    items: computed(() => store.sites || []),
    idKey: 'id',
  })

  // ── KPIs ─────────────────────────────────────────────────────────────────
  const kpiCards = computed(() => {
    const siteList = store.sites || []
    const total = store.totalSites || siteList.length

    return [
      {
        id: 'total',
        title: t('Total Sites'),
        value: total,
        icon: 'tabler-building-estate',
        color: 'primary',
      },
      {
        id: 'active',
        title: t('Active Sites'),
        value: siteList.filter(s => s.status === 'active').length,
        icon: 'tabler-circle-check',
        color: 'success',
      },
      {
        id: 'high_risk',
        title: t('High / Critical Risk'),
        value: siteList.filter(s => ['high', 'critical'].includes(s.risk_class?.toLowerCase())).length,
        icon: 'tabler-shield-alert',
        color: 'error',
      },
      {
        id: 'construction',
        title: t('Under Construction'),
        value: siteList.filter(s => s.status === 'under_construction').length,
        icon: 'tabler-crane',
        color: 'warning',
      },
    ]
  })

  const loadSupportingData = async () => {
    try {
      const clientsRes = await clientStore.fetchClients({ itemsPerPage: -1 }).catch(() => [])

      clientsList.value = clientsRes.data || clientsRes || []
    } catch (e) {
      console.error('Error loading site master filters:', e)
    }
  }

  onMounted(() => {
    loadSupportingData()
  })

  return {
    ...listPage,
    sites: computed(() => store.sites || []),
    totalSites: computed(() => store.totalSites || 0),
    headers,
    siteTypeOptions,
    statusOptions,
    riskOptions,
    clientsList,
    resolveStatusVariant,
    resolveRiskVariant,
    resolveTypeVariant,
    selectedSites,
    allSelected,
    someSelected,
    toggleSelectAll,
    toggleSiteSelection,
    clearSelection,
    kpiCards,
    loadSupportingData,
  }
}
