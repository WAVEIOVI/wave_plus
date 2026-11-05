import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useFleetVehicleListPage
 * ─────────────────────────────────────────────────────────────────────────────
 * Vehicle-specific list orchestration composable following BP patterns.
 *
 * Owns:
 *   - Vehicle list fetching (via useListPage + useVehicleStore)
 *   - API parameter mapping (sort field mapping, filter formatting)
 *   - KPI / alert statistics fetching (independent of list)
 *   - Vehicle table column configuration
 *   - Status / type / ownership option lookups
 *   - Row selection state
 *
 * Does NOT own:
 *   - Dialog state (that's useVehicleDialogs)
 *   - CRUD actions (that's useVehicleActions)
 *   - Generic list pagination/sorting/filter behavior (that's useListPage)
 *
 * Architecture:
 *   Page → useFleetVehicleListPage → useListPage → useVehicleStore → API
 *
 * Usage:
 *   const { vehicles, loading, refresh, ... } = useFleetVehicleListPage()
 */
export function useFleetVehicleListPage() {
  const { t } = useI18n()
  const vehicleStore = useVehicleStore()

  // ── Options / lookup maps ────────────────────────────────────────────────
  
  const statusOptions = computed(() => [
    { title: t('fleet.vehicles.status.available'), value: 'available' },
    { title: t('fleet.vehicles.status.in_use'), value: 'in_use' },
    { title: t('fleet.vehicles.status.maintenance'), value: 'maintenance' },
    { title: t('fleet.vehicles.status.out_of_service'), value: 'out_of_service' },
    { title: t('fleet.vehicles.status.rental_active'), value: 'rental_active' },
    { title: t('fleet.vehicles.status.client_use'), value: 'client_use' },
    { title: t('fleet.vehicles.status.retired'), value: 'retired' },
    { title: t('fleet.vehicles.status.sold'), value: 'sold' },
  ])

  const ownershipTypeOptions = computed(() => [
    { title: t('fleet.vehicles.ownership.company'), value: 'company' },
    { title: t('fleet.vehicles.ownership.rental'), value: 'rental' },
    { title: t('fleet.vehicles.ownership.client'), value: 'client' },
  ])

  const vehicleTypeOptions = computed(() => [
    { title: t('fleet.vehicles.types.sedan'), value: 'sedan' },
    { title: t('fleet.vehicles.types.suv'), value: 'suv' },
    { title: t('fleet.vehicles.types.van'), value: 'van' },
    { title: t('fleet.vehicles.types.truck'), value: 'truck' },
    { title: t('fleet.vehicles.types.pickup'), value: 'pickup' },
    { title: t('fleet.vehicles.types.bus'), value: 'bus' },
    { title: t('fleet.vehicles.types.motorcycle'), value: 'motorcycle' },
    { title: t('fleet.vehicles.types.other'), value: 'other' },
  ])

  const vehicleClassOptions = computed(() => [
    { title: t('fleet.vehicles.classes.light'), value: 'light' },
    { title: t('fleet.vehicles.classes.medium'), value: 'medium' },
    { title: t('fleet.vehicles.classes.heavy'), value: 'heavy' },
  ])

  const headers = computed(() => [
    {
      title: t('fleet.vehicles.fields.fleetCode'),
      key: 'fleet_code',
      sortable: true,
      minWidth: '140px',
    },
    {
      title: t('fleet.vehicles.fields.plateNumber'),
      key: 'plate_number',
      sortable: true,
      minWidth: '140px',
    },
    {
      title: t('fleet.vehicles.fields.type'),
      key: 'type',
      sortable: true,
    },
    {
      title: t('fleet.vehicles.fields.vehicleClass'),
      key: 'vehicle_class',
      sortable: true,
    },
    {
      title: t('fleet.vehicles.fields.ownership'),
      key: 'ownership_type',
      sortable: true,
    },
    {
      title: t('fleet.vehicles.fields.status'),
      key: 'status',
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
  const getOwnershipTitle = val => ownershipTypeOptions.value.find(o => o.value === val)?.title || val
  const getTypeTitle = val => vehicleTypeOptions.value.find(v => v.value === val)?.title || val
  const getClassTitle = val => vehicleClassOptions.value.find(c => c.value === val)?.title || val

  // ── Color / variant resolvers ────────────────────────────────────────────
  
  const resolveStatusVariant = status => {
    const statusMap = {
      available: 'success',
      in_use: 'info',
      maintenance: 'warning',
      out_of_service: 'error',
      rental_active: 'primary',
      client_use: 'secondary',
      retired: 'default',
      sold: 'default',
    }
    
    return statusMap[status?.toLowerCase()] || 'primary'
  }

  const resolveOwnershipVariant = ownership => {
    const ownershipMap = {
      company: 'success',
      rental: 'warning',
      client: 'info',
    }
    
    return ownershipMap[ownership?.toLowerCase()] || 'primary'
  }

  // ── List page orchestration ──────────────────────────────────────────────
  
  const sortFieldMap = {
    vehicle: 'fleet_code', // Map UI column key to API field
  }

  const listPage = useListPage({
    fetchFn: params => vehicleStore.fetchList(params),
    initialFilters: {
      status: null,
      ownership_type: null,
      type: null,
      vehicle_class: null,
    },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
    initialItemsPerPage: 15,
    sortFieldMap,
    immediate: true,
  })

  // Selection management
  const { selected: selectedVehicles, clearSelection } = useSelection()

  // Clear selection when search/filters change
  watch(
    [listPage.search, () => ({ ...listPage.filters })],
    clearSelection,
    { deep: true },
  )

  // ── Alerts / Statistics ──────────────────────────────────────────────────
  
  const alertsLoading = ref(false)

  const alerts = ref({
    maintenance: [],
    documents: [],
    insurance: [],
    registration: [],
  })

  /**
   * Fetch vehicle alerts independently from the list fetch.
   * Runs in parallel with the list so neither blocks the other.
   */
  const fetchAlerts = async () => {
    alertsLoading.value = true
    try {
      const response = await vehicleStore.fetchAlerts()
      if (response) {
        alerts.value = {
          maintenance: Array.isArray(response.maintenance) ? response.maintenance : [],
          documents: Array.isArray(response.documents) ? response.documents : [],
          insurance: Array.isArray(response.insurance) ? response.insurance : [],
          registration: Array.isArray(response.registration) ? response.registration : [],
        }
      }
    }
    catch (err) {
      // Alerts are non-critical — failure should not block the list
      console.warn('[useFleetVehicleListPage] Failed to fetch alerts:', err?.message)
    }
    finally {
      alertsLoading.value = false
    }
  }

  // ── KPI Cards ────────────────────────────────────────────────────────────
  
  const kpiCards = computed(() => {
    const total = listPage.totalItems.value || 0
    const vehicles = listPage.rows.value || []

    const available = vehicles.filter(v => v.status === 'available').length
    const inUse = vehicles.filter(v => v.status === 'in_use').length
    const maintenance = alerts.value.maintenance.length
    const expired = alerts.value.insurance.length + alerts.value.registration.length

    return [
      {
        titleKey: 'fleet.vehicles.kpis.total',
        value: total,
        icon: 'tabler-car',
        filterValue: null, // null = clear status filter
        iconColor: 'primary',
      },
      {
        titleKey: 'fleet.vehicles.kpis.available',
        value: available,
        icon: 'tabler-circle-check',
        filterValue: 'available',
        iconColor: 'success',
      },
      {
        titleKey: 'fleet.vehicles.kpis.in_use',
        value: inUse,
        icon: 'tabler-steering-wheel',
        filterValue: 'in_use',
        iconColor: 'info',
      },
      {
        titleKey: 'fleet.vehicles.kpis.maintenance',
        value: maintenance,
        icon: 'tabler-tool',
        filterValue: 'maintenance',
        iconColor: 'warning',
      },
      {
        titleKey: 'fleet.vehicles.kpis.alerts',
        value: expired,
        icon: 'tabler-alert-circle',
        filterValue: null,
        iconColor: 'error',
      },
    ]
  })

  /**
   * Handle KPI card click: set or toggle the status filter.
   * 
   * @param {string|null} filterValue - The status value to filter by, or null to clear
   */
  const handleKpiClick = filterValue => {
    if (filterValue === null) {
      // "Total" or "Alerts" card: clear the status filter
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

  // ── Initialize alerts on mount ───────────────────────────────────────────
  // Alerts are fetched independently from the list.
  // Both happen in parallel on mount — neither depends on the other.
  onMounted(() => fetchAlerts())

  return {
    // Spread the full list page API
    ...listPage,

    // Rename rows for clarity
    vehicles: listPage.rows,

    // Options
    headers,
    statusOptions,
    ownershipTypeOptions,
    vehicleTypeOptions,
    vehicleClassOptions,

    // Resolvers
    getStatusTitle,
    getOwnershipTitle,
    getTypeTitle,
    getClassTitle,
    resolveStatusVariant,
    resolveOwnershipVariant,

    // Selection
    selectedVehicles,
    clearSelection,

    // Alerts & KPIs
    alerts,
    alertsLoading,
    kpiCards,
    handleKpiClick,
    fetchAlerts,
  }
}
