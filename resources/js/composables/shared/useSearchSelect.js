import { computed, ref } from 'vue'

/**
 * useSearchSelect
 * ─────────────────────────────────────────────────────────────────────────────
 * Entity-agnostic state machine behind `WpSearchSelectDialog`.
 *
 * The caller supplies a `fetcher`; the composable owns query state, server-side
 * pagination/sorting/filtering, debounce, selection rules and — importantly —
 * race protection: every request gets an id + AbortController, and a response
 * is applied only if it belongs to the latest request.
 *
 * fetcher({ page, perPage, search, filters, sortBy, sortDir, signal })
 *   → Promise< T[] | { data|items: T[], total?: number, meta?: { total } } >
 *
 * Pass `signal` on to `$api(url, { signal })` to cancel the network call too.
 */

export const normalizeSearchResponse = response => {
  if (Array.isArray(response)) return { items: response, total: response.length }

  const items = response?.items ?? response?.data ?? []
  const total = response?.total ?? response?.meta?.total ?? response?.pagination?.total ?? items.length

  return { items: Array.isArray(items) ? items : [], total: Number(total) || 0 }
}

const isEmptyFilter = v => v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length) || v === false

/**
 * Read a display value from an item. `spec` may be a (dotted) property path or
 * a function `(item) => any`. Missing paths resolve to `undefined`.
 */
export const resolveAccessor = (spec, item) => {
  if (typeof spec === 'function') return spec(item)
  if (typeof spec !== 'string' || !spec) return undefined

  return spec.split('.').reduce((acc, part) => (acc === null || acc === undefined ? undefined : acc[part]), item)
}

export function useSearchSelect(options = {}) {
  const {
    fetcher,
    itemKey = 'id',
    multiple = false,
    maxSelection = Infinity,
    perPage: initialPerPage = 12,
    debounce = 300,
    view: initialView = 'list',
    sort: initialSort = {},
    filters: initialFilters = {},
    filterLabels = {},
  } = options

  const search = ref('')
  const filters = ref({ ...initialFilters })
  const sortBy = ref(initialSort.key || null)
  const sortDir = ref(initialSort.dir || 'asc')
  const page = ref(1)
  const perPage = ref(initialPerPage)
  const view = ref(initialView)

  const items = ref([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const loaded = ref(false)

  /** key → item. Kept across pages so multi-select survives pagination. */
  const selection = ref(new Map())
  const limitReached = ref(false)

  let requestId = 0
  let controller = null
  let timer = null

  const keyOf = item => item?.[itemKey]

  // ── Derived ──────────────────────────────────────────────────────────────
  const lastPage = computed(() => Math.max(1, Math.ceil(total.value / Math.max(1, perPage.value))))

  const activeFilters = computed(() => Object.entries(filters.value)
    .filter(([, v]) => !isEmptyFilter(v))
    .map(([key, value]) => ({ key, value, label: filterLabels[key] || key })))

  const activeFilterCount = computed(() => activeFilters.value.length)
  const hasCriteria = computed(() => Boolean(search.value.trim()) || activeFilterCount.value > 0)
  const isEmpty = computed(() => loaded.value && !loading.value && !error.value && items.value.length === 0)
  const selectedItems = computed(() => [...selection.value.values()])
  const selectedKeys = computed(() => [...selection.value.keys()])
  const canSelectMore = computed(() => multiple && selection.value.size < maxSelection)

  // ── Loading (race-safe) ──────────────────────────────────────────────────
  async function load() {
    if (typeof fetcher !== 'function') return

    clearTimeout(timer)
    controller?.abort()
    controller = typeof AbortController !== 'undefined' ? new AbortController() : null

    const myId = ++requestId

    loading.value = true
    error.value = null

    try {
      const response = await fetcher({
        page: page.value,
        perPage: perPage.value,
        search: search.value.trim(),
        filters: { ...filters.value },
        sortBy: sortBy.value,
        sortDir: sortDir.value,
        signal: controller?.signal,
      })

      if (myId !== requestId) return // a newer request superseded this one

      const normalised = normalizeSearchResponse(response)

      total.value = normalised.total
      items.value = normalised.items
      loaded.value = true

      // The dataset shrank (e.g. filter applied while on page 5): move back.
      if (!normalised.items.length && normalised.total > 0 && page.value > 1) {
        page.value = lastPage.value
        await load()
      }
    }
    catch (err) {
      if (myId !== requestId || err?.name === 'AbortError' || err?.code === 'ERR_CANCELED') return
      error.value = err
      items.value = []
      total.value = 0
      loaded.value = true
    }
    finally {
      if (myId === requestId) loading.value = false
    }
  }

  const reload = () => load()

  /** Reset to page 1 and load now. */
  const applyCriteria = () => {
    page.value = 1

    return load()
  }

  // ── Criteria mutations ───────────────────────────────────────────────────
  function setSearch(value) {
    search.value = value ?? ''
    clearTimeout(timer)
    timer = setTimeout(applyCriteria, debounce)
  }

  /** Skip the debounce (Enter key, clear button). */
  function flushSearch() {
    clearTimeout(timer)

    return applyCriteria()
  }

  function clearSearch() {
    search.value = ''

    return flushSearch()
  }

  function setFilter(key, value) {
    filters.value = { ...filters.value, [key]: value }

    return applyCriteria()
  }

  function setFilters(next) {
    filters.value = { ...next }

    return applyCriteria()
  }

  function removeFilter(key) {
    const next = { ...filters.value }

    delete next[key]
    filters.value = next

    return applyCriteria()
  }

  function resetFilters() {
    filters.value = { ...initialFilters }

    return applyCriteria()
  }

  function resetAll() {
    search.value = ''
    filters.value = { ...initialFilters }
    sortBy.value = initialSort.key || null
    sortDir.value = initialSort.dir || 'asc'

    return applyCriteria()
  }

  function setSort(key, dir) {
    if (dir) {
      sortBy.value = key
      sortDir.value = dir
    }
    else if (sortBy.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    }
    else {
      sortBy.value = key
      sortDir.value = 'asc'
    }

    return applyCriteria()
  }

  function setPage(value) {
    const next = Math.min(Math.max(1, Number(value) || 1), lastPage.value)

    if (next === page.value && loaded.value) return Promise.resolve()
    page.value = next

    return load()
  }

  function setPerPage(value) {
    perPage.value = Number(value) || initialPerPage

    return applyCriteria()
  }

  const setView = value => {
    view.value = value
  }

  // ── Selection ────────────────────────────────────────────────────────────
  const isSelected = item => selection.value.has(keyOf(item))

  function select(item) {
    limitReached.value = false

    if (!multiple) {
      selection.value = new Map([[keyOf(item), item]])

      return true
    }

    if (selection.value.has(keyOf(item))) return true
    if (selection.value.size >= maxSelection) {
      limitReached.value = true

      return false
    }

    const next = new Map(selection.value)

    next.set(keyOf(item), item)
    selection.value = next

    return true
  }

  function deselect(item) {
    const next = new Map(selection.value)

    next.delete(keyOf(item))
    selection.value = next
    limitReached.value = false
  }

  function toggle(item) {
    if (isSelected(item)) {
      // Single mode keeps the pick (radio behaviour); multiple mode toggles off.
      if (multiple) deselect(item)

      return true
    }

    return select(item)
  }

  function setSelection(list = []) {
    const entries = [].concat(list).filter(Boolean).slice(0, multiple ? maxSelection : 1)

    selection.value = new Map(entries.map(item => [keyOf(item), item]))
    limitReached.value = false
  }

  function clearSelection() {
    selection.value = new Map()
    limitReached.value = false
  }

  function dispose() {
    clearTimeout(timer)
    controller?.abort()
    requestId += 1 // invalidate any in-flight response
  }

  return {
    // criteria
    search, filters, sortBy, sortDir, page, perPage, view,

    // results
    items, total, loading, error, loaded, lastPage, isEmpty,

    // derived
    activeFilters, activeFilterCount, hasCriteria,

    // selection
    selection, selectedItems, selectedKeys, canSelectMore, limitReached,

    // actions
    load, reload, setSearch, flushSearch, clearSearch, setFilter, setFilters, removeFilter, resetFilters,
    resetAll, setSort, setPage, setPerPage, setView, isSelected, select, deselect, toggle, setSelection,
    clearSelection, dispose,
  }
}
