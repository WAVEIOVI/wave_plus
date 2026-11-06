/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useWarehouseSiteList
 * ─────────────────────────────────────────────────────────────────────────────
 * Warehouse sites list orchestration composable following WAVE PLUS BP standards.
 *
 * Owns:
 *   - Site list fetching (via useListPage + useInventoryStore)
 *   - API parameter mapping and filter chip configuration
 *   - Table column configuration and option lookups
 *
 * Does NOT own:
 *   - Dialog state (useWarehouseSiteDialogs)
 *   - CRUD/zone/location/vehicle actions (useWarehouseSiteActions)
 *   - Edit form state (useWarehouseSiteEdit)
 *
 * Architecture:
 *   Page → useWarehouseSiteList → useListPage → useInventoryStore → API
 */
export function useWarehouseSiteList() {
  const store = useInventoryStore()
  const { t } = useI18n()

  const allowedSortFields = new Set([
    'id',
    'code',
    'status',
    'site_type',
    'created_at',
  ])

  // ── Options ───────────────────────────────────────────────────────────────
  const statusOptions = computed(() => [
    { title: t('inventory.sites.statuses.active'), value: 'active' },
    { title: t('inventory.sites.statuses.inactive'), value: 'inactive' },
    { title: t('inventory.sites.statuses.maintenance'), value: 'maintenance' },
  ])

  const siteTypeOptions = computed(() => [
    { title: t('inventory.sites.types.fixed'), value: 'fixed' },
    { title: t('inventory.sites.types.mobile'), value: 'mobile' },
  ])

  // ── Headers ───────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('inventory.sites.columns.code'), key: 'code', sortable: true },
    { title: t('inventory.sites.columns.name'), key: 'name', sortable: false },
    { title: t('inventory.sites.columns.type'), key: 'site_type', sortable: true },
    { title: t('inventory.sites.columns.status'), key: 'status', sortable: true },
    { title: t('inventory.sites.columns.zones'), key: 'zones_count', sortable: false, align: 'center' },
    { title: t('inventory.sites.columns.locations'), key: 'locations_count', sortable: false, align: 'center' },
    { title: t('inventory.sites.columns.manager'), key: 'manager', sortable: false },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // ── Resolvers ─────────────────────────────────────────────────────────────
  const resolveStatusVariant = status => ({
    active: 'success',
    inactive: 'secondary',
    maintenance: 'warning',
  }[status?.toLowerCase()] || 'primary')

  const resolveSiteTypeVariant = type => ({
    fixed: 'primary',
    mobile: 'info',
  }[type?.toLowerCase()] || 'secondary')

  const resolveSiteTypeIcon = type => ({
    fixed: 'tabler-building-warehouse',
    mobile: 'tabler-truck',
  }[type?.toLowerCase()] || 'tabler-building-warehouse')

  // ── List orchestration ────────────────────────────────────────────────────
  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'code'

      const query = {
        page: params.page,
        per_page: params.per_page,
        q: params.search || undefined,
        sort_by: sortField,
        sort_order: params.sort_order,
        'filter[status]': params.status || undefined,
        'filter[site_type]': params.site_type || undefined,
      }

      return await store.fetchSites(query)
    },
    initialFilters: {
      status: null,
      site_type: null,
    },
    initialSortKey: 'code',
    initialSortOrder: 'asc',
  })

  // ── Active filters (for WpActiveFilters chip display) ────────────────────
  const activeFilters = computed(() => {
    const chips = []

    if (listPage.filters.status) {
      chips.push({
        key: 'status',
        label: t('inventory.sites.filters.status'),
        value: statusOptions.value.find(o => o.value === listPage.filters.status)?.title ?? listPage.filters.status,
      })
    }
    if (listPage.filters.site_type) {
      chips.push({
        key: 'site_type',
        label: t('inventory.sites.filters.type'),
        value: siteTypeOptions.value.find(o => o.value === listPage.filters.site_type)?.title ?? listPage.filters.site_type,
      })
    }

    return chips
  })

  return {
    ...listPage,

    // Rename rows
    sites: listPage.rows,

    // Store state
    totalSites: computed(() => store.totalSites),

    // Options
    headers,
    statusOptions,
    siteTypeOptions,

    // Resolvers
    resolveStatusVariant,
    resolveSiteTypeVariant,
    resolveSiteTypeIcon,

    // Active filter chips
    activeFilters,
  }
}
