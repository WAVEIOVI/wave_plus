/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useEquipmentList
 * ─────────────────────────────────────────────────────────────────────────────
 * Equipment list orchestration composable following WAVE PLUS standards.
 */
export function useEquipmentList() {
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'equipment_code',
    'serial_number',
    'model_name',
    'lifecycle_status',
    'compliance_status',
    'criticality',
    'created_at',
  ])

  // ── Master options ───────────────────────────────────────────────────────
  const clientsList = ref([])
  const sitesList = ref([])
  const areasList = ref([])
  const classesList = ref([])
  const blueprintsList = ref([])

  const lifecycleOptions = [
    { title: t('Operational'), value: 'operational' },
    { title: t('Under Maintenance'), value: 'under_maintenance' },
    { title: t('In Storage'), value: 'stored' },
    { title: t('Draft'), value: 'draft' },
    { title: t('Decommissioned'), value: 'decommissioned' },
  ]

  const complianceOptions = [
    { title: t('Compliant'), value: 'compliant' },
    { title: t('Due Soon'), value: 'due_soon' },
    { title: t('Overdue'), value: 'overdue' },
    { title: t('Non Compliant'), value: 'non_compliant' },
  ]

  const criticalityOptions = [
    { title: t('Low'), value: 'low' },
    { title: t('Medium'), value: 'medium' },
    { title: t('High'), value: 'high' },
    { title: t('Critical'), value: 'critical' },
  ]

  // ── Headers ──────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('Equipment & Model'), key: 'equipment_code', sortable: true },
    { title: t('Client & Location'), key: 'client', sortable: false },
    { title: t('Status'), key: 'lifecycle_status', sortable: true },
    { title: t('Compliance'), key: 'compliance_status', sortable: true },
    { title: t('Criticality'), key: 'criticality', sortable: true },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ────────────────────────────────────────────────────────────
  const resolveLifecycleVariant = status => {
    const map = {
      operational: 'success',
      under_maintenance: 'warning',
      stored: 'info',
      draft: 'secondary',
      decommissioned: 'error',
    }

    return map[status?.toLowerCase()] || 'secondary'
  }

  const resolveComplianceVariant = status => {
    const map = {
      compliant: 'success',
      due_soon: 'warning',
      overdue: 'error',
      non_compliant: 'error',
    }

    return map[status?.toLowerCase()] || 'secondary'
  }

  const resolveCriticalityVariant = crit => {
    const map = {
      low: 'secondary',
      medium: 'info',
      high: 'warning',
      critical: 'error',
    }

    return map[crit?.toLowerCase()] || 'secondary'
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
        'filter[site_id]': params.site_id || undefined,
        'filter[area_id]': params.area_id || undefined,
        'filter[equipment_class_id]': params.equipment_class_id || undefined,
        'filter[blueprint_id]': params.blueprint_id || undefined,
        'filter[lifecycle_status]': params.lifecycle_status || undefined,
        'filter[compliance_status]': params.compliance_status || undefined,
        'filter[criticality]': params.criticality || undefined,
      }

      return await store.fetchEquipment(query)
    },
    initialFilters: {
      client_id: null,
      site_id: null,
      area_id: null,
      equipment_class_id: null,
      blueprint_id: null,
      lifecycle_status: null,
      compliance_status: null,
      criticality: null,
    },
    debounceMs: 400,
  })

  // ── Selection ────────────────────────────────────────────────────────────
  const {
    selected: selectedEquipment,
    allSelected,
    someSelected,
    toggleSelectAll,
    toggleItem: toggleEquipmentSelection,
    clearSelection,
  } = useSelection({
    items: computed(() => store.equipment || []),
    idKey: 'id',
  })

  // ── KPIs ─────────────────────────────────────────────────────────────────
  const kpiCards = computed(() => {
    const eqList = store.equipment || []
    const total = store.totalEquipment || eqList.length

    return [
      {
        id: 'total',
        title: t('Total Equipment'),
        value: total,
        icon: 'tabler-fire-extinguisher',
        color: 'primary',
      },
      {
        id: 'operational',
        title: t('Operational'),
        value: eqList.filter(e => e.lifecycle_status === 'operational').length,
        icon: 'tabler-circle-check',
        color: 'success',
      },
      {
        id: 'maintenance',
        title: t('In Maintenance'),
        value: eqList.filter(e => e.lifecycle_status === 'under_maintenance').length,
        icon: 'tabler-tool',
        color: 'warning',
      },
      {
        id: 'overdue',
        title: t('Overdue Inspections'),
        value: eqList.filter(e => e.compliance_status === 'overdue').length,
        icon: 'tabler-alert-triangle',
        color: 'error',
      },
    ]
  })

  const loadSupportingData = async () => {
    try {
      const [clientsRes, classesRes, blueprintsRes, sitesRes] = await Promise.all([
        clientStore.fetchClients({ itemsPerPage: -1 }).catch(() => []),
        store.fetchEquipmentClasses({ itemsPerPage: -1 }).catch(() => []),
        store.fetchBlueprints({ itemsPerPage: -1 }).catch(() => []),
        store.fetchSites({ itemsPerPage: -1 }).catch(() => []),
      ])

      clientsList.value = clientsRes.data || clientsRes || []
      classesList.value = classesRes.data || classesRes || []
      blueprintsList.value = blueprintsRes.data || blueprintsRes || []
      sitesList.value = sitesRes.data || sitesRes || []
    } catch (e) {
      console.error('Error loading equipment master filters:', e)
    }
  }

  onMounted(() => {
    loadSupportingData()
  })

  return {
    ...listPage,
    equipment: computed(() => store.equipment || []),
    totalEquipment: computed(() => store.totalEquipment || 0),
    headers,
    lifecycleOptions,
    complianceOptions,
    criticalityOptions,
    clientsList,
    sitesList,
    areasList,
    classesList,
    blueprintsList,
    resolveLifecycleVariant,
    resolveComplianceVariant,
    resolveCriticalityVariant,
    selectedEquipment,
    allSelected,
    someSelected,
    toggleSelectAll,
    toggleEquipmentSelection,
    clearSelection,
    kpiCards,
    loadSupportingData,
  }
}
