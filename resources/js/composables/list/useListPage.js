import { useDebounceFn } from '@vueuse/core'
import { onMounted, ref, watch } from 'vue'
import { useFilters } from './useFilters'
import { usePagination } from './usePagination'
import { useSorting } from './useSorting'

/**
 * useListPage
 * ─────────────────────────────────────────────────────────────────────────────
 * Central composable for all ERP server-side list pages.
 *
 * Orchestrates: search, filters, pagination, sorting, loading, error.
 * Automatically resets page to 1 whenever search / filters / sort change.
 * Debounces the search watcher (300 ms).
 *
 * IMPORTANT: This composable does NOT contain any API or store logic.
 * The caller must supply a `fetchFn` that accepts the resolved params
 * and returns a Promise<{ data: any[], total: number }>.
 *
 * Usage:
 *   const { rows, loading, error, search, filters, page, ... } = useListPage({
 *     fetchFn: (params) => store.fetchList(params),
 *     initialFilters: { status: null, industry: null },
 *     initialSortKey: 'created_at',
 *     initialSortOrder: 'desc',
 *     sortFieldMap: { client: 'legal_name' },
 *   })
 *
 * @param {object} opts
 * @param {(params: object) => Promise<{ data: any[], total: number }>} opts.fetchFn
 * @param {object}  [opts.initialFilters={}]
 * @param {string}  [opts.initialSortKey='id']
 * @param {'asc'|'desc'} [opts.initialSortOrder='desc']
 * @param {number}  [opts.initialPage=1]
 * @param {number}  [opts.initialItemsPerPage=15]
 * @param {number}  [opts.searchDebounce=300]
 * @param {boolean} [opts.immediate=true] - Fetch automatically on mount.
 * @param {object}  [opts.sortFieldMap={}] - Map UI column key -> API sort field.
 *   Example: { client: 'legal_name', supplier: 'legal_name', employee: 'last_name' }
 */
export function useListPage({
  fetchFn,
  initialFilters = {},
  initialSortKey = 'id',
  initialSortOrder = 'desc',
  initialPage = 1,
  initialItemsPerPage = 15,
  searchDebounce = 300,
  immediate = true,
  sortFieldMap = {},
}) {
  // ── Sub-composables ──────────────────────────────────────────────────────
  const { page, itemsPerPage, totalItems, pageCount, resetPage } = usePagination({
    initialPage,
    initialItemsPerPage,
  })

  const { sortBy, applySorting } = useSorting({
    key: initialSortKey,
    order: initialSortOrder,
  })

  const {
    filters,
    stagedFilters,
    activeFilters,
    activeFilterCount,
    hasActiveFilters,
    setFilter,
    removeFilter,
    resetFilters,
    stageFilters,
    applyStaged,
    resetStaged,
  } = useFilters(initialFilters)

  // ── Local state ──────────────────────────────────────────────────────────
  const rows = ref([])
  const loading = ref(false)
  const error = ref(null)
  const search = ref('')

  // Request sequencing: prevents stale responses from concurrent requests.
  // Each call to fetchData increments this counter and captures the value.
  // If the value no longer matches when the response arrives, it is discarded.
  let currentRequestId = 0

  // ── Sort field resolution ─────────────────────────────────────────────────

  /**
   * Resolve the backend sort field from the UI column key.
   * Checks sortFieldMap first, then falls back to the raw key.
   *
   * This decouples presentation column names (e.g. "client") from the
   * actual API sort parameter (e.g. "legal_name"), so the frontend can
   * have meaningful display keys while the backend receives valid sort fields.
   *
   * @param {string} uiKey
   * @returns {string}
   */
  const resolveSortField = uiKey => sortFieldMap[uiKey] ?? uiKey

  // ── Fetch logic ──────────────────────────────────────────────────────────

  /**
   * Builds the params object from current state and calls fetchFn.
   */
  const fetchData = async () => {
    const requestId = ++currentRequestId

    loading.value = true
    error.value = null

    try {
      const sort = sortBy.value[0] ?? { key: initialSortKey, order: initialSortOrder }

      // Resolve the UI column key to the actual backend sort field name.
      // Example: 'client' -> 'legal_name' when sortFieldMap = { client: 'legal_name' }
      const apiSortField = resolveSortField(sort.key)

      /* eslint-disable camelcase */
      const params = {
        page: page.value,
        per_page: itemsPerPage.value,
        sort_by: apiSortField,
        sort_order: sort.order,
        search: search.value || undefined,
        ...Object.fromEntries(
          Object.entries(filters).filter(([, v]) => v !== null && v !== undefined && v !== ''),
        ),
      }
      /* eslint-enable camelcase */

      const result = await fetchFn(params)

      // Discard stale responses that were superseded by a newer request
      if (requestId !== currentRequestId) return

      rows.value = result?.data ?? []
      totalItems.value = result?.meta?.total ?? result?.total ?? rows.value.length
    }
    catch (err) {
      // Discard stale error responses too
      if (requestId !== currentRequestId) return

      if (err?.response?.status === 401 || err?.statusCode === 401) {
        rows.value = []
        totalItems.value = 0

        return
      }

      error.value = err?.data?.message || err?.message || 'Failed to load data'
      rows.value = []
    }
    finally {
      // Only update loading state for the current (non-stale) request
      if (requestId === currentRequestId) {
        loading.value = false
      }
    }
  }

  // ── Debounced search ─────────────────────────────────────────────────────
  const debouncedFetch = useDebounceFn(fetchData, searchDebounce)

  // ── Watchers — reset page on search/filter/sort change ───────────────────
  watch(search, () => {
    resetPage()
    debouncedFetch()
  })

  watch(
    () => ({ ...filters }),
    () => {
      resetPage()
      fetchData()
    },
    { deep: true },
  )

  watch(sortBy, () => {
    resetPage()
    fetchData()
  })

  watch([page, itemsPerPage], fetchData)

  // ── Initial fetch on mount ───────────────────────────────────────────────
  // Fetch automatically when the component mounts so the table is populated
  // immediately without requiring any user interaction (no KPI click needed).
  // Set immediate: false to disable for cases requiring manual control.
  if (immediate) {
    onMounted(() => fetchData())
  }

  // ── Public helpers ───────────────────────────────────────────────────────

  /** Manually trigger a refresh from outside. */
  const refresh = () => fetchData()

  /** Reset everything: search, filters, sort, page — then re-fetch. */
  const reset = () => {
    search.value = ''
    resetFilters()
    resetPage()
    sortBy.value = [{ key: initialSortKey, order: initialSortOrder }]
    fetchData()
  }

  return {
    // Data
    rows,
    totalItems,
    loading,
    error,

    // Search
    search,

    // Pagination
    page,
    itemsPerPage,
    pageCount,
    resetPage,

    // Sorting
    sortBy,
    applySorting,

    // Filters
    filters,
    stagedFilters,
    activeFilters,
    activeFilterCount,
    hasActiveFilters,
    setFilter,
    removeFilter,
    resetFilters,
    stageFilters,
    applyStaged,
    resetStaged,

    // Actions
    refresh,
    reset,
    fetchData,
  }
}
