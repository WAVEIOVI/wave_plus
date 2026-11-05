import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useApprovedDriverStore } from '@/stores/useApprovedDriverStore'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useApprovedDriverListPage
 * ─────────────────────────────────────────────────────────────────────────────
 * Approved driver-specific list orchestration composable following BP patterns.
 *
 * Owns:
 *   - Driver list fetching (via useListPage + useApprovedDriverStore)
 *   - API parameter mapping (sort field mapping, filter formatting)
 *   - KPI / alert statistics fetching (independent of list)
 *   - Driver table column configuration
 *   - Status / level option lookups
 *   - Row selection state
 *
 * Architecture:
 *   Page → useApprovedDriverListPage → useListPage → useApprovedDriverStore → API
 *
 * Usage:
 *   const { drivers, loading, refresh, ... } = useApprovedDriverListPage()
 */
export function useApprovedDriverListPage() {
  const { t } = useI18n()
  const driverStore = useApprovedDriverStore()

  // ── Options / lookup maps ────────────────────────────────────────────────

  const statusOptions = computed(() => [
    { title: t('fleet.drivers.status.pending'), value: 'pending' },
    { title: t('fleet.drivers.status.approved'), value: 'approved' },
    { title: t('fleet.drivers.status.suspended'), value: 'suspended' },
    { title: t('fleet.drivers.status.expired'), value: 'expired' },
    { title: t('fleet.drivers.status.revoked'), value: 'revoked' },
    { title: t('fleet.drivers.status.under_review'), value: 'under_review' },
  ])

  const levelOptions = computed(() => [
    { title: t('fleet.drivers.levels.trainee'), value: 'trainee' },
    { title: t('fleet.drivers.levels.junior'), value: 'junior' },
    { title: t('fleet.drivers.levels.standard'), value: 'standard' },
    { title: t('fleet.drivers.levels.senior'), value: 'senior' },
    { title: t('fleet.drivers.levels.expert'), value: 'expert' },
  ])

  const headers = computed(() => [
    {
      title: t('fleet.drivers.fields.employee'),
      key: 'employee',
      sortable: false,
      minWidth: '220px',
    },
    {
      title: t('fleet.drivers.fields.licenseNumber'),
      key: 'license_number',
      sortable: true,
      minWidth: '140px',
    },
    {
      title: t('fleet.drivers.fields.driverLevel'),
      key: 'driver_level',
      sortable: true,
    },
    {
      title: t('fleet.drivers.fields.driverScore'),
      key: 'driver_score',
      sortable: true,
      width: '100px',
    },
    {
      title: t('fleet.drivers.fields.expiryDate'),
      key: 'expiry_date',
      sortable: true,
    },
    {
      title: t('fleet.drivers.fields.authorizationStatus'),
      key: 'authorization_status',
      sortable: true,
    },
    {
      title: t('common.actions'),
      key: 'actions',
      sortable: false,
      width: '60px',
    },
  ])

  // Resolvers for display values
  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
  const getLevelTitle = val => levelOptions.value.find(l => l.value === val)?.title || val

  // ── Color / variant resolvers ────────────────────────────────────────────

  const resolveStatusVariant = status => {
    const statusMap = {
      pending: 'warning',
      approved: 'success',
      suspended: 'warning',
      expired: 'error',
      revoked: 'error',
      under_review: 'info',
    }

    return statusMap[status?.toLowerCase()] || 'secondary'
  }

  const resolveLevelVariant = level => {
    const levelMap = {
      trainee: 'info',
      junior: 'primary',
      standard: 'success',
      senior: 'success',
      expert: 'warning',
    }

    return levelMap[level?.toLowerCase()] || 'primary'
  }

  const resolveScoreVariant = score => {
    if (score >= 95) return 'success'      // Expert: 95-100
    if (score >= 85) return 'success'      // Senior: 85-94
    if (score >= 70) return 'info'         // Standard: 70-84
    if (score >= 50) return 'warning'      // Junior: 50-69
    
    return 'error'                         // Trainee: 0-49
  }

  // ── List page orchestration ──────────────────────────────────────────────

  const sortFieldMap = {
    driver: 'driver_authorization_number',
    employee: 'employee_id',
  }

  const listPage = useListPage({
    fetchFn: params => driverStore.fetchList(params),
    initialFilters: {
      authorization_status: null,
      driver_level: null,
    },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
    initialItemsPerPage: 15,
    sortFieldMap,
    immediate: true,
  })

  // Selection management
  const { selected: selectedDrivers, clearSelection } = useSelection()

  // Clear selection when search/filters change
  watch(
    [listPage.search, () => ({ ...listPage.filters })],
    clearSelection,
    { deep: true },
  )

  // ── Alerts / Statistics ──────────────────────────────────────────────────

  const alertsLoading = ref(false)

  const alerts = ref({
    renewals: [],
    reviews: [],
    expiring: [],
  })

  /**
   * Fetch driver alerts independently from the list fetch.
   */
  const fetchAlerts = async () => {
    alertsLoading.value = true
    try {
      const renewals = await driverStore.fetchLicenseRenewalAlerts() || []
      const reviews = await driverStore.fetchReviewAlerts() || []

      alerts.value = {
        renewals: Array.isArray(renewals) ? renewals : [],
        reviews: Array.isArray(reviews) ? reviews : [],
        expiring: [], // Can be computed from renewals
      }
    }
    catch (err) {
      console.warn('[useApprovedDriverListPage] Failed to fetch alerts:', err?.message)
    }
    finally {
      alertsLoading.value = false
    }
  }

  // ── KPI Cards ────────────────────────────────────────────────────────────

  const kpiCards = computed(() => {
    const total = listPage.totalItems.value || 0
    const drivers = listPage.rows.value || []

    const approved = drivers.filter(d => d.authorization_status === 'approved').length
    const suspended = drivers.filter(d => d.authorization_status === 'suspended').length
    const renewals = alerts.value.renewals.length
    const reviews = alerts.value.reviews.length

    return [
      {
        titleKey: 'fleet.drivers.kpis.total',
        value: total,
        icon: 'tabler-license',
        filterValue: null,
        iconColor: 'primary',
      },
      {
        titleKey: 'fleet.drivers.kpis.approved',
        value: approved,
        icon: 'tabler-user-check',
        filterValue: 'approved',
        iconColor: 'success',
      },
      {
        titleKey: 'fleet.drivers.kpis.suspended',
        value: suspended,
        icon: 'tabler-user-pause',
        filterValue: 'suspended',
        iconColor: 'warning',
      },
      {
        titleKey: 'fleet.drivers.kpis.renewals',
        value: renewals,
        icon: 'tabler-calendar-event',
        filterValue: null,
        iconColor: 'info',
      },
      {
        titleKey: 'fleet.drivers.kpis.reviews',
        value: reviews,
        icon: 'tabler-alert-circle',
        filterValue: 'under_review',
        iconColor: 'error',
      },
    ]
  })

  /**
   * Handle KPI card click: set or toggle the authorization_status filter.
   *
   * @param {string|null} filterValue - The status value to filter by, or null to clear
   */
  const handleKpiClick = filterValue => {
    if (filterValue === null) {
      listPage.setFilter('authorization_status', null)
    }
    else {
      listPage.setFilter(
        'authorization_status',
        listPage.filters.authorization_status === filterValue ? null : filterValue,
      )
    }
  }

  // ── Initialize alerts on mount ───────────────────────────────────────────
  onMounted(() => fetchAlerts())

  return {
    // Spread the full list page API
    ...listPage,

    // Rename rows for clarity
    drivers: listPage.rows,

    // Options
    headers,
    statusOptions,
    levelOptions,

    // Resolvers
    getStatusTitle,
    getLevelTitle,
    resolveStatusVariant,
    resolveLevelVariant,
    resolveScoreVariant,

    // Selection
    selectedDrivers,
    clearSelection,

    // Alerts & KPIs
    alerts,
    alertsLoading,
    kpiCards,
    handleKpiClick,
    fetchAlerts,
  }
}
