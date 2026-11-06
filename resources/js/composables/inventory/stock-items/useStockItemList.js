/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useStockItemList
 * ─────────────────────────────────────────────────────────────────────────────
 * Stock items list orchestration composable following WAVE PLUS BP standards.
 *
 * Owns:
 *   - Stock item list fetching (via useListPage + useInventoryStore)
 *   - API parameter mapping (sort field mapping, filter formatting)
 *   - Table column configuration
 *   - Status / condition / active-filter chip configuration
 *
 * Does NOT own:
 *   - Dialog state (useStockItemDialogs)
 *   - Create/update/delete actions (store actions called by dialogs)
 *
 * Architecture:
 *   Page → useStockItemList → useListPage → useInventoryStore → API
 */
export function useStockItemList() {
  const store = useInventoryStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'qty_on_hand',
    'qty_available',
    'qty_reserved',
    'unit_cost',
    'updated_at',
    'created_at',
  ])

  // ── Options ───────────────────────────────────────────────────────────────
  const statusOptions = computed(() => [
    { title: t('inventory.stock_items.statuses.active'), value: 'active' },
    { title: t('inventory.stock_items.statuses.inactive'), value: 'inactive' },
    { title: t('inventory.stock_items.statuses.reserved'), value: 'reserved' },
    { title: t('inventory.stock_items.statuses.quarantined'), value: 'quarantined' },
  ])

  const conditionOptions = computed(() => [
    { title: t('inventory.conditions.new'), value: 'new' },
    { title: t('inventory.conditions.good'), value: 'good' },
    { title: t('inventory.conditions.fair'), value: 'fair' },
    { title: t('inventory.conditions.poor'), value: 'poor' },
    { title: t('inventory.conditions.damaged'), value: 'damaged' },
  ])

  // ── Headers ───────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('inventory.stock_items.columns.item'), key: 'catalog_item', sortable: false },
    { title: t('inventory.stock_items.columns.location'), key: 'location', sortable: false },
    { title: t('inventory.stock_items.columns.qty_on_hand'), key: 'qty_on_hand', sortable: true, align: 'end' },
    { title: t('inventory.stock_items.columns.qty_available'), key: 'qty_available', sortable: true, align: 'end' },
    { title: t('inventory.stock_items.columns.qty_reserved'), key: 'qty_reserved', sortable: true, align: 'end' },
    { title: t('inventory.stock_items.columns.unit_cost'), key: 'unit_cost', sortable: true, align: 'end' },
    { title: t('inventory.stock_items.columns.condition'), key: 'condition', sortable: false },
    { title: t('inventory.stock_items.columns.status'), key: 'status', sortable: false },
    { title: t('inventory.stock_items.columns.updated_at'), key: 'updated_at', sortable: true },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ─────────────────────────────────────────────────────────────
  const resolveStatusVariant = status => ({
    active: 'success',
    inactive: 'secondary',
    reserved: 'warning',
    quarantined: 'error',
  }[status?.toLowerCase()] || 'primary')

  const resolveConditionVariant = condition => ({
    new: 'success',
    good: 'primary',
    fair: 'info',
    poor: 'warning',
    damaged: 'error',
  }[condition?.toLowerCase()] || 'secondary')

  // ── List orchestration ────────────────────────────────────────────────────
  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'updated_at'

      const query = {
        page: params.page,
        per_page: params.per_page,
        q: params.search || undefined,
        sort_by: sortField,
        sort_order: params.sort_order,
        'filter[status]': params.status || undefined,
        'filter[condition]': params.condition || undefined,
        'filter[site_id]': params.site_id || undefined,
        'filter[below_reorder]': params.below_reorder || undefined,
      }

      return await store.fetchStockItems(query)
    },
    initialFilters: {
      status: null,
      condition: null,
      site_id: null,
      below_reorder: null,
    },
    initialSortKey: 'updated_at',
    initialSortOrder: 'desc',
  })

  // ── Active filters (for WpActiveFilters chip display) ────────────────────
  const activeFilters = computed(() => {
    const chips = []

    if (listPage.filters.status) {
      chips.push({
        key: 'status',
        label: t('inventory.stock_items.filters.status'),
        value: statusOptions.value.find(o => o.value === listPage.filters.status)?.title ?? listPage.filters.status,
      })
    }
    if (listPage.filters.condition) {
      chips.push({
        key: 'condition',
        label: t('inventory.stock_items.filters.condition'),
        value: conditionOptions.value.find(o => o.value === listPage.filters.condition)?.title ?? listPage.filters.condition,
      })
    }
    if (listPage.filters.site_id) {
      chips.push({
        key: 'site_id',
        label: t('inventory.stock_items.filters.site'),
        value: listPage.filters.site_id,
      })
    }
    if (listPage.filters.below_reorder) {
      chips.push({
        key: 'below_reorder',
        label: t('inventory.stock_items.filters.below_reorder'),
        value: t('Yes'),
      })
    }

    return chips
  })

  return {
    ...listPage,

    // Rename rows
    stockItems: listPage.rows,

    // Options
    headers,
    statusOptions,
    conditionOptions,

    // Resolvers
    resolveStatusVariant,
    resolveConditionVariant,

    // Active filter chips
    activeFilters,
  }
}
