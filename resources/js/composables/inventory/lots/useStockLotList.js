/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useStockLotList
 * ─────────────────────────────────────────────────────────────────────────────
 * Stock lots list orchestration composable following WAVE PLUS BP standards.
 *
 * Owns:
 *   - Lot list fetching (via useListPage + useInventoryStore)
 *   - API parameter mapping and filter chip configuration
 *   - Table column configuration
 *
 * Does NOT own:
 *   - Dialog state (useStockLotDialogs)
 *   - CRUD actions (useStockLotActions)
 *
 * Architecture:
 *   Page → useStockLotList → useListPage → useInventoryStore → API
 */
export function useStockLotList() {
  const store = useInventoryStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'lot_number',
    'manufacture_date',
    'expiry_date',
    'status',
    'created_at',
  ])

  // ── Options ───────────────────────────────────────────────────────────────
  const statusOptions = computed(() => [
    { title: t('inventory.lots.statuses.active'), value: 'active' },
    { title: t('inventory.lots.statuses.expired'), value: 'expired' },
    { title: t('inventory.lots.statuses.quarantined'), value: 'quarantined' },
    { title: t('inventory.lots.statuses.recalled'), value: 'recalled' },
  ])

  // ── Headers ───────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('inventory.lots.columns.lot_number'), key: 'lot_number', sortable: true },
    { title: t('inventory.lots.columns.catalog_item'), key: 'catalog_item', sortable: false },
    { title: t('inventory.lots.columns.supplier'), key: 'supplier', sortable: false },
    { title: t('inventory.lots.columns.manufacture_date'), key: 'manufacture_date', sortable: true },
    { title: t('inventory.lots.columns.expiry_date'), key: 'expiry_date', sortable: true },
    { title: t('inventory.lots.columns.status'), key: 'status', sortable: true },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ─────────────────────────────────────────────────────────────
  const resolveStatusVariant = status => ({
    active: 'success',
    expired: 'error',
    quarantined: 'warning',
    recalled: 'error',
  }[status?.toLowerCase()] || 'secondary')

  const resolveStatusIcon = status => ({
    active: 'tabler-circle-check',
    expired: 'tabler-clock-off',
    quarantined: 'tabler-shield-lock',
    recalled: 'tabler-alert-circle',
  }[status?.toLowerCase()] || 'tabler-circle')

  // ── List orchestration ────────────────────────────────────────────────────
  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'created_at'

      const query = {
        page: params.page,
        per_page: params.per_page,
        q: params.search || undefined,
        sort_by: sortField,
        sort_order: params.sort_order,
        'filter[status]': params.status || undefined,
        'filter[catalog_item_id]': params.catalog_item_id || undefined,
        'filter[expiry_within_days]': params.expiry_within_days || undefined,
      }

      return await store.fetchLots(query)
    },
    initialFilters: {
      status: null,
      catalog_item_id: null,
      expiry_within_days: null,
    },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
  })

  // ── Active filters (for WpActiveFilters chip display) ────────────────────
  const activeFilters = computed(() => {
    const chips = []

    if (listPage.filters.status) {
      chips.push({
        key: 'status',
        label: t('inventory.lots.filters.status'),
        value: statusOptions.value.find(o => o.value === listPage.filters.status)?.title ?? listPage.filters.status,
      })
    }
    if (listPage.filters.catalog_item_id) {
      chips.push({
        key: 'catalog_item_id',
        label: t('inventory.lots.filters.item'),
        value: listPage.filters.catalog_item_id,
      })
    }
    if (listPage.filters.expiry_within_days) {
      chips.push({
        key: 'expiry_within_days',
        label: t('inventory.lots.filters.expiry_within_days'),
        value: `${listPage.filters.expiry_within_days} ${t('days')}`,
      })
    }

    return chips
  })

  return {
    ...listPage,

    // Rename rows
    lots: listPage.rows,

    // Options
    headers,
    statusOptions,

    // Resolvers
    resolveStatusVariant,
    resolveStatusIcon,

    // Active filter chips
    activeFilters,
  }
}
