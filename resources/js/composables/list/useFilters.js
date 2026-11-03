import { computed, reactive, ref } from 'vue'

/**
 * useFilters
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic filter state management for list pages.
 *
 * Usage:
 *   const { filters, activeFilters, activeFilterCount, hasActiveFilters, setFilter, resetFilters } =
 *     useFilters({ status: null, industry: null })
 *
 * @template T
 * @param {T} initialFilters - Object with filter keys and their default (empty) values.
 * @returns {{
 *   filters: import('vue').UnwrapNestedRefs<T>,
 *   stagedFilters: import('vue').Ref<T>,
 *   activeFilters: import('vue').ComputedRef<Array<{key: string, value: any}>>,
 *   activeFilterCount: import('vue').ComputedRef<number>,
 *   hasActiveFilters: import('vue').ComputedRef<boolean>,
 *   setFilter: (key: string, value: any) => void,
 *   removeFilter: (key: string) => void,
 *   resetFilters: () => void,
 *   stageFilters: () => void,
 *   applyStaged: () => void,
 *   resetStaged: () => void,
 * }}
 */
export function useFilters(initialFilters = {}) {
  // Applied filters (what the data fetch actually uses)
  const filters = reactive({ ...initialFilters })

  // Staged filters (local copy while the filter dialog is open, before Apply is clicked)
  const stagedFilters = ref({ ...initialFilters })

  /**
   * Active filters: entries where the value is not null/undefined/empty string.
   */
  const activeFilters = computed(() =>
    Object.entries(filters)
      .filter(([, v]) => v !== null && v !== undefined && v !== '')
      .map(([key, value]) => ({ key, value })),
  )

  const activeFilterCount = computed(() => activeFilters.value.length)
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  /**
   * Directly set a single filter key.
   * @param {string} key
   * @param {any} value
   */
  const setFilter = (key, value) => {
    filters[key] = value
  }

  /**
   * Remove (clear) a single filter key back to its initial value.
   * @param {string} key
   */
  const removeFilter = key => {
    filters[key] = initialFilters[key] ?? null
  }

  /** Reset all applied filters back to initial values. */
  const resetFilters = () => {
    Object.keys(initialFilters).forEach(key => {
      filters[key] = initialFilters[key]
    })
  }

  /** Copy current applied filters into the staged copy (e.g. when opening the filter dialog). */
  const stageFilters = () => {
    stagedFilters.value = { ...filters }
  }

  /** Apply the staged filters to the real applied filters. */
  const applyStaged = () => {
    Object.keys(initialFilters).forEach(key => {
      filters[key] = stagedFilters.value[key]
    })
  }

  /** Reset only the staged filters back to initial (without touching applied). */
  const resetStaged = () => {
    stagedFilters.value = { ...initialFilters }
  }

  return {
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
  }
}
