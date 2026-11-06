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
        value: stats?.kpis?.total_stock_items?.count ?? stats?.total_stock_items ?? 0,
        icon: 'tabler-packages',
        iconColor: 'primary',
        filterKey: null,
        filterValue: null,
      },
      {
        id: 'inventory_value',
        titleKey: 'inventory.dashboard.kpi.inventory_value',
        title: t('inventory.dashboard.kpi.inventory_value'),
        value: stats?.kpis?.total_inventory_value?.value ?? stats?.total_inventory_value ?? 0,
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
        value: stats?.kpis?.below_reorder?.count ?? stats?.below_reorder ?? 0,
        icon: 'tabler-alert-triangle',
        iconColor: 'warning',
        filterKey: 'below_reorder',
        filterValue: true,
      },
      {
        id: 'active_locations',
        titleKey: 'inventory.dashboard.kpi.active_locations',
        title: t('inventory.dashboard.kpi.active_locations'),
        value: stats?.kpis?.active_locations?.count ?? stats?.active_locations ?? 0,
        icon: 'tabler-map-pin',
        iconColor: 'info',
        filterKey: null,
        filterValue: null,
      },
      {
        id: 'active_sites',
        titleKey: 'inventory.dashboard.kpi.active_sites',
        title: t('inventory.dashboard.kpi.active_sites'),
        value: stats?.kpis?.active_sites?.count ?? stats?.active_sites ?? 0,
        icon: 'tabler-building-warehouse',
        iconColor: 'secondary',
        filterKey: null,
        filterValue: null,
      },
    ]
  })

  // ── Condition Distribution ─────────────────────────────────────────────────
  const conditionDistribution = computed(() => {
    const rawData = store.dashboardStats?.items_by_condition ?? store.dashboardStats?.condition_distribution ?? {}
    const dist = Array.isArray(rawData) ? Object.fromEntries(rawData.map(d => [d.condition, d.count])) : rawData

    return [
      { label: t('inventory.conditions.new'), key: 'new', value: dist.new ?? 0, color: 'success' },
      { label: t('inventory.conditions.used'), key: 'used', value: dist.used ?? 0, color: 'primary' },
      { label: t('inventory.conditions.refurbished'), key: 'refurbished', value: dist.refurbished ?? 0, color: 'info' },
      { label: t('inventory.conditions.damaged'), key: 'damaged', value: dist.damaged ?? 0, color: 'error' },
    ]
  })

  // ── Warehouse Distribution ─────────────────────────────────────────────────
  const warehouseDistribution = computed(() => {
    const raw = store.dashboardStats?.warehouse_distribution ?? []

    return raw.map(s => ({
      id: s.site_id ?? s.id,
      name: s.site_name ?? s.name,
      stock_items_count: s.items_count ?? s.stock_items_count ?? 0,
    }))
  })

  // ── Recent Movements ──────────────────────────────────────────────────────
  const recentMovements = computed(() => {
    const raw = store.dashboardStats?.recent_movements ?? []

    return raw.map(m => ({
      ...m,
      item_name: m.item_name ?? m.catalog_item?.name,
      item_code: m.item_code ?? m.catalog_item?.code,
      catalog_item: m.catalog_item,
    }))
  })

  // ── Low-Stock Alerts ──────────────────────────────────────────────────────
  const lowStockAlerts = computed(() => {
    const raw = store.dashboardStats?.low_stock_alerts ?? []

    return raw.map(a => ({
      ...a,
      item_name: a.item_name ?? a.catalog_item?.name,
      item_code: a.item_code ?? a.catalog_item?.code,
      qty_on_hand: a.qty_available ?? a.qty_on_hand ?? 0,
      reorder_point: a.reorder_point,
    }))
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
