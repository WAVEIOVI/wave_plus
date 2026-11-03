import { ref, computed, watch } from 'vue'
import { useDisplay } from 'vuetify'

/**
 * useEntityCollection
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic composable for managing entity collections (list/grid views).
 * Provides: view mode, search, selection, sorting, pagination, responsive behavior.
 *
 * Usage:
 *   const {
 *     viewMode,
 *     search,
 *     filters,
 *     selected,
 *     page,
 *     itemsPerPage,
 *     sortBy,
 *     isMobileView,
 *     toggleViewMode,
 *     setViewMode,
 *     clearSelection,
 *     setSearch,
 *     setFilter,
 *     removeFilter,
 *     resetFilters,
 *     setPage,
 *     setItemsPerPage,
 *     setSortBy,
 *     toggleSelection,
 *     selectAll,
 *     deselectAll,
 *   } = useEntityCollection({
 *     initialViewMode: 'table',
 *     initialSortBy: [{ key: 'name', order: 'asc' }],
 *     itemsPerPageOptions: [10, 15, 25, 50, 100],
 *     mobileBreakpoint: 'md',
 *   })
 */
export function useEntityCollection(options = {}) {
  const {
    initialViewMode = 'table',
    initialSortBy = [],
    itemsPerPageOptions = [10, 15, 25, 50, 100],
    mobileBreakpoint = 'sm',
    defaultPage = 1,
    defaultItemsPerPage = 15,
  } = options

  const display = useDisplay()

  // View mode: 'table' | 'grid'
  const viewMode = ref(initialViewMode)

  // Search
  const search = ref('')

  // Filters (reactive object for v-model compatibility)
  const filters = ref({})
  const stagedFilters = ref({})

  // Selection
  const selected = ref([])

  // Pagination
  const page = ref(defaultPage)
  const itemsPerPage = ref(defaultItemsPerPage)

  // Sorting
  const sortBy = ref(initialSortBy)

  // Loading/Error states (to be bound by consumer)
  const loading = ref(false)
  const error = ref(null)
  const totalItems = ref(0)
  const items = ref([])

  // Responsive
  const isMobileView = computed(() => {
    if (mobileBreakpoint === 'xs') return display.xs.value
    if (mobileBreakpoint === 'sm') return display.smAndDown.value
    if (mobileBreakpoint === 'md') return display.width.value < 1024
    
    return display.smAndDown.value
  })

  const activeFilterCount = computed(() => Object.values(filters.value).filter(v => v !== null && v !== '' && v !== undefined).length)

  // Actions
  const toggleViewMode = () => {
    viewMode.value = viewMode.value === 'table' ? 'grid' : 'table'
  }

  const setViewMode = mode => {
    viewMode.value = mode
  }

  const clearSelection = () => {
    selected.value = []
  }

  const setSearch = value => {
    search.value = value
    page.value = 1
  }

  const setFilter = (key, value) => {
    filters.value = { ...filters.value, [key]: value }
    page.value = 1
  }

  const removeFilter = key => {
    const newFilters = { ...filters.value }

    delete newFilters[key]
    filters.value = newFilters
    page.value = 1
  }

  const resetFilters = () => {
    filters.value = {}
    page.value = 1
  }

  const setPage = value => {
    page.value = value
  }

  const setItemsPerPage = value => {
    itemsPerPage.value = value
    page.value = 1
  }

  const setSortBy = value => {
    sortBy.value = value
  }

  const toggleSelection = itemValue => {
    const idx = selected.value.indexOf(itemValue)
    if (idx > -1) {
      selected.value.splice(idx, 1)
    } else {
      selected.value.push(itemValue)
    }
  }

  const selectAll = itemValues => {
    selected.value = [...new Set([...selected.value, ...itemValues])]
  }

  const deselectAll = itemValues => {
    const toRemove = new Set(itemValues)

    selected.value = selected.value.filter(v => !toRemove.has(v))
  }

  // Staged filters for filter dialog
  const stageFilters = () => {
    stagedFilters.value = { ...filters.value }
  }

  const applyStaged = () => {
    filters.value = { ...stagedFilters.value }
    page.value = 1
  }

  const resetStaged = () => {
    stagedFilters.value = { ...filters.value }
  }

  return {
    // State
    viewMode,
    search,
    filters,
    stagedFilters,
    selected,
    page,
    itemsPerPage,
    sortBy,
    loading,
    error,
    totalItems,
    items,
    isMobileView,
    activeFilterCount,
    itemsPerPageOptions,
    mobileBreakpoint,

    // Actions
    toggleViewMode,
    setViewMode,
    clearSelection,
    setSearch,
    setFilter,
    removeFilter,
    resetFilters,
    setPage,
    setItemsPerPage,
    setSortBy,
    toggleSelection,
    selectAll,
    deselectAll,
    stageFilters,
    applyStaged,
    resetStaged,
  }
}