import { computed, ref } from 'vue'

/**
 * usePagination
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic server-side pagination state.
 *
 * Usage:
 *   const { page, itemsPerPage, totalItems, pageCount, resetPage } = usePagination()
 *
 * @param {object} [opts]
 * @param {number} [opts.initialPage=1]
 * @param {number} [opts.initialItemsPerPage=15]
 */
export function usePagination({
  initialPage = 1,
  initialItemsPerPage = 15,
} = {}) {
  const page = ref(initialPage)
  const itemsPerPage = ref(initialItemsPerPage)
  const totalItems = ref(0)

  const pageCount = computed(() =>
    itemsPerPage.value > 0
      ? Math.ceil(totalItems.value / itemsPerPage.value)
      : 0,
  )

  /** Reset to the first page without altering itemsPerPage or totalItems. */
  const resetPage = () => {
    page.value = 1
  }

  return {
    page,
    itemsPerPage,
    totalItems,
    pageCount,
    resetPage,
  }
}
