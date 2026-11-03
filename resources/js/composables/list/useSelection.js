import { computed, ref } from 'vue'

/**
 * useSelection
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic row-selection state for data tables.
 * Tracks selected item IDs (or objects) independently of pagination.
 *
 * Usage:
 *   const { selected, selectionCount, hasSelection, clearSelection } = useSelection()
 */
export function useSelection() {
  /** @type {import('vue').Ref<any[]>} Array of selected row values (usually IDs). */
  const selected = ref([])

  const selectionCount = computed(() => selected.value.length)

  const hasSelection = computed(() => selected.value.length > 0)

  const clearSelection = () => {
    selected.value = []
  }

  /**
   * Select all items from a given array.
   * @param {any[]} items
   * @param {string} [key='id']
   */
  const selectAll = (items, key = 'id') => {
    selected.value = items.map(item => item[key])
  }

  return {
    selected,
    selectionCount,
    hasSelection,
    clearSelection,
    selectAll,
  }
}
