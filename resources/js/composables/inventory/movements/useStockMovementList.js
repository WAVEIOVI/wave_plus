/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useStockMovementList
 * ─────────────────────────────────────────────────────────────────────────────
 * Stock movements list orchestration composable following WAVE PLUS BP standards.
 *
 * Owns:
 *   - Movement list fetching (via useListPage + useInventoryStore)
 *   - API parameter mapping and filter chip configuration
 *   - Table column configuration
 *
 * Does NOT own:
 *   - Dialog state (useStockMovementDialogs)
 *   - Mutation actions (useStockMovementActions)
 *
 * Architecture:
 *   Page → useStockMovementList → useListPage → useInventoryStore → API
 */
export function useStockMovementList() {
  const store = useInventoryStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'movement_type',
    'quantity',
    'created_at',
  ])

  // ── Options ───────────────────────────────────────────────────────────────
  const movementTypeOptions = computed(() => [
    { title: t('inventory.movements.types.adjustment'), value: 'adjustment' },
    { title: t('inventory.movements.types.transfer'), value: 'transfer' },
    { title: t('inventory.movements.types.initial_load'), value: 'initial_load' },
    { title: t('inventory.movements.types.grn_receipt'), value: 'grn_receipt' },
    { title: t('inventory.movements.types.consumption'), value: 'consumption' },
    { title: t('inventory.movements.types.return'), value: 'return' },
  ])

  // ── Headers ───────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('inventory.movements.columns.type'), key: 'movement_type', sortable: true },
    { title: t('inventory.movements.columns.item'), key: 'catalog_item', sortable: false },
    { title: t('inventory.movements.columns.quantity'), key: 'quantity', sortable: true, align: 'end' },
    { title: t('inventory.movements.columns.from_location'), key: 'from_location', sortable: false },
    { title: t('inventory.movements.columns.to_location'), key: 'to_location', sortable: false },
    { title: t('inventory.movements.columns.reference'), key: 'reference', sortable: false },
    { title: t('inventory.movements.columns.created_at'), key: 'created_at', sortable: true },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ─────────────────────────────────────────────────────────────
  const resolveMovementTypeVariant = type => ({
    adjustment: 'warning',
    transfer: 'info',
    initial_load: 'success',
    grn_receipt: 'primary',
    consumption: 'secondary',
    return: 'error',
  }[type?.toLowerCase()] || 'primary')

  const resolveMovementTypeIcon = type => ({
    adjustment: 'tabler-adjustments',
    transfer: 'tabler-transfer',
    initial_load: 'tabler-database-import',
    grn_receipt: 'tabler-truck-delivery',
    consumption: 'tabler-minus',
    return: 'tabler-corner-up-left',
  }[type?.toLowerCase()] || 'tabler-activity')

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
        'filter[movement_type]': params.movement_type || undefined,
        'filter[catalog_item_id]': params.catalog_item_id || undefined,
        'filter[date_from]': params.date_from || undefined,
        'filter[date_to]': params.date_to || undefined,
      }

      return await store.fetchMovements(query)
    },
    initialFilters: {
      movement_type: null,
      catalog_item_id: null,
      date_from: null,
      date_to: null,
    },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
  })

  // ── Active filters (for WpActiveFilters chip display) ────────────────────
  const activeFilters = computed(() => {
    const chips = []

    if (listPage.filters.movement_type) {
      chips.push({
        key: 'movement_type',
        label: t('inventory.movements.filters.type'),
        value: movementTypeOptions.value.find(o => o.value === listPage.filters.movement_type)?.title ?? listPage.filters.movement_type,
      })
    }
    if (listPage.filters.catalog_item_id) {
      chips.push({
        key: 'catalog_item_id',
        label: t('inventory.movements.filters.item'),
        value: listPage.filters.catalog_item_id,
      })
    }
    if (listPage.filters.date_from) {
      chips.push({
        key: 'date_from',
        label: t('inventory.movements.filters.date_from'),
        value: listPage.filters.date_from,
      })
    }
    if (listPage.filters.date_to) {
      chips.push({
        key: 'date_to',
        label: t('inventory.movements.filters.date_to'),
        value: listPage.filters.date_to,
      })
    }

    return chips
  })

  return {
    ...listPage,

    // Rename rows
    movements: listPage.rows,

    // Options
    headers,
    movementTypeOptions,

    // Resolvers
    resolveMovementTypeVariant,
    resolveMovementTypeIcon,

    // Active filter chips
    activeFilters,
  }
}
