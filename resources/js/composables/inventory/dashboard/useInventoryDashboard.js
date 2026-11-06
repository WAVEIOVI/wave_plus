import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useInventoryDashboard
 * ─────────────────────────────────────────────────────────────────────────────
 * Owns the Inventory & Warehouse dashboard request and its derived UI state.
 *
 * Architecture:
 *   Page → useInventoryDashboard → useInventoryStore → API (/inventory/dashboard-stats)
 */
export function useInventoryDashboard() {
  const store = useInventoryStore()
  const { t } = useI18n()

  const updatedAt = ref(null)

  // ── Fetch ─────────────────────────────────────────────────────────────────
  const fetchDashboard = async () => {
    await store.fetchDashboardStats()
    updatedAt.value = new Date()
  }

  // ── KPI Cards ─────────────────────────────────────────────────────────────
  /**
   * Maps raw dashboard stats to WpKpiCard-compatible descriptors.
   * Returns an empty array while loading so the page can show skeletons.
   */
  const kpiCards = computed(() => {
    const stats = store.dashboardStats

    return [
      {
        id: 'stock_items',
        titleKey: 'inventory.dashboard.kpi.stock_items',
        title: t('inventory.dashboard.kpi.stock_items'),
        value: stats?.total_stock_items ?? 0,
        icon: 'tabler-packages',
        iconColor: 'primary',
        filterKey: null,
        filterValue: null,
      },
      {
        id: 'inventory_value',
        titleKey: 'inventory.dashboard.kpi.inventory_value',
        title: t('inventory.dashboard.kpi.inventory_value'),
        value: stats?.total_inventory_value ?? 0,
        icon: 'tabler-currency-dollar',
        iconColor: 'success',
        isCurrency: true,
        filterKey: null,
        filterValue: null,
      },
      {
        id: 'below_reorder',
        titleKey: 'inventory.dashboard.kpi.below_reorder',
        title: t('inventory.dashboard.kpi.below_reorder'),
        value: stats?.below_reorder ?? 0,
        icon: 'tabler-alert-triangle',
        iconColor: 'warning',
        filterKey: 'below_reorder',
        filterValue: true,
      },
      {
        id: 'active_locations',
        titleKey: 'inventory.dashboard.kpi.active_locations',
        title: t('inventory.dashboard.kpi.active_locations'),
        value: stats?.active_locations ?? 0,
        icon: 'tabler-map-pin',
        iconColor: 'info',
        filterKey: null,
        filterValue: null,
      },
      {
        id: 'active_sites',
        titleKey: 'inventory.dashboard.kpi.active_sites',
        title: t('inventory.dashboard.kpi.active_sites'),
        value: stats?.active_sites ?? 0,
        icon: 'tabler-building-warehouse',
        iconColor: 'secondary',
        filterKey: null,
        filterValue: null,
      },
    ]
  })

  // ── Condition Distribution ─────────────────────────────────────────────────
  const conditionDistribution = computed(() => {
    const dist = store.dashboardStats?.condition_distribution ?? {}

    return [
      { label: t('inventory.conditions.new'), key: 'new', value: dist.new ?? 0, color: 'success' },
      { label: t('inventory.conditions.good'), key: 'good', value: dist.good ?? 0, color: 'primary' },
      { label: t('inventory.conditions.fair'), key: 'fair', value: dist.fair ?? 0, color: 'info' },
      { label: t('inventory.conditions.poor'), key: 'poor', value: dist.poor ?? 0, color: 'warning' },
      { label: t('inventory.conditions.damaged'), key: 'damaged', value: dist.damaged ?? 0, color: 'error' },
    ]
  })

  // ── Warehouse Distribution ─────────────────────────────────────────────────
  const warehouseDistribution = computed(() => {
    return store.dashboardStats?.warehouse_distribution ?? []
  })

  // ── Recent Movements ──────────────────────────────────────────────────────
  const recentMovements = computed(() => {
    return store.dashboardStats?.recent_movements ?? []
  })

  // ── Low-Stock Alerts ──────────────────────────────────────────────────────
  const lowStockAlerts = computed(() => {
    return store.dashboardStats?.low_stock_alerts ?? []
  })

  // ── Auto-fetch on mount ───────────────────────────────────────────────────
  onMounted(fetchDashboard)

  return {
    // Store pass-through
    loading: computed(() => store.dashboardLoading),
    error: computed(() => store.error),
    stats: computed(() => store.dashboardStats),

    // Derived UI state
    kpiCards,
    conditionDistribution,
    warehouseDistribution,
    recentMovements,
    lowStockAlerts,
    updatedAt,

    // Actions
    fetchDashboard,
  }
}
