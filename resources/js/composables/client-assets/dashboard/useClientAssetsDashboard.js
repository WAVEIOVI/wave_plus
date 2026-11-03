import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useClientAssetsDashboard() {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const loading = ref(false)
  const error = ref(null)
  const updatedAt = ref(null)
  const workwearKpis = ref(null)
  let requestId = 0

  const fetchDashboardData = async () => {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null
    try {
      const [,,,,, workwearRes] = await Promise.all([
        store.fetchSites({ itemsPerPage: 10 }),
        store.fetchEquipment({ itemsPerPage: 5, sortBy: [{ key: 'updated_at', order: 'desc' }] }),
        store.fetchWorkwearItems({ itemsPerPage: 10 }),
        store.fetchSitesMeta().catch(() => null),
        store.fetchEquipmentMeta().catch(() => null),
        store.fetchWorkwearKpis().catch(() => null),
      ])

      if (currentRequest !== requestId) return

      if (workwearRes) {
        workwearKpis.value = workwearRes.data || workwearRes
      }
      updatedAt.value = new Date()
    } catch (err) {
      if (currentRequest !== requestId) return
      console.error('Failed to load client assets dashboard:', err)
      error.value = err?.data?.message || err?.message || 'Failed to load dashboard data'
    } finally {
      if (currentRequest === requestId) {
        loading.value = false
      }
    }
  }

  const kpis = computed(() => {
    const overdueCount = store.equipmentMeta?.overdue_count
      ?? store.equipment.filter(e => e.compliance_status === 'overdue').length

    return [
      {
        key: 'client_sites',
        title: t('client_assets.dashboard.kpi.client_sites', 'Client Sites'),
        value: store.totalSites,
        icon: 'tabler-building-estate',
        color: 'primary',
        to: { name: 'client-assets-sites-list' },
        subtitle: t('client_assets.dashboard.kpi.client_sites_sub', 'Managed client locations'),
      },
      {
        key: 'equipment_units',
        title: t('client_assets.dashboard.kpi.equipment_units', 'Equipment Units'),
        value: store.totalEquipment,
        icon: 'tabler-fire-extinguisher',
        color: 'success',
        to: { name: 'client-assets-equipment-list' },
        subtitle: t('client_assets.dashboard.kpi.equipment_units_sub', 'Deployed and active assets'),
      },
      {
        key: 'workwear_items',
        title: t('client_assets.dashboard.kpi.workwear_items', 'Workwear Items'),
        value: store.totalWorkwearItems,
        icon: 'tabler-hanger',
        color: 'info',
        to: { name: 'client-assets-workwear-list' },
        subtitle: t('client_assets.dashboard.kpi.workwear_items_sub', 'Tracked PPE & apparel units'),
      },
      {
        key: 'overdue_compliance',
        title: t('client_assets.dashboard.kpi.overdue_compliance', 'Overdue Compliance'),
        value: overdueCount,
        icon: 'tabler-alert-triangle',
        color: 'error',
        to: { name: 'client-assets-equipment-list' },
        subtitle: t('client_assets.dashboard.kpi.overdue_compliance_sub', 'Action required immediately'),
      },
    ]
  })

  const recentEquipment = computed(() => store.equipment.slice(0, 5))
  const equipmentMeta = computed(() => store.equipmentMeta || {})
  const sitesMeta = computed(() => store.sitesMeta || {})
  const workwearStats = computed(() => workwearKpis.value || {})

  return {
    loading,
    error,
    updatedAt,
    kpis,
    equipmentMeta,
    sitesMeta,
    workwearStats,
    recentEquipment,
    fetchDashboardData,
  }
}

