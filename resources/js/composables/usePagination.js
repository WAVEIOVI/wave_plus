import { computed, ref, unref, watch } from 'vue'

/**
 * usePagination
 * ─────────────────────────────────────────────────────────────────────────────
 * Client-side array pagination composable.
 *
 * Usage:
 *   const { page, totalPages, currentItems } = usePagination(itemsRef, itemsPerPage)
 *
 * @param {import('vue').Ref<any[]> | any[]} items - Array or ref to array
 * @param {import('vue').Ref<number> | number} [itemsPerPage=10]
 * @returns {{
 *   page: import('vue').Ref<number>,
 *   totalPages: import('vue').ComputedRef<number>,
 *   currentItems: import('vue').ComputedRef<any[]>,
 *   nextPage: () => void,
 *   prevPage: () => void,
 *   setPage: (p: number) => void,
 * }}
 */
export function usePagination(items, itemsPerPage = 10) {
  // Support both array slicing mode and option object mode
  if (items && !Array.isArray(unref(items)) && typeof items === 'object' && ('initialPage' in items || 'initialItemsPerPage' in items)) {
    const page = ref(items.initialPage || 1)
    const perPage = ref(items.initialItemsPerPage || 15)
    const totalItems = ref(0)
    const pageCount = computed(() => (perPage.value > 0 ? Math.ceil(totalItems.value / perPage.value) : 0))
    const resetPage = () => { page.value = 1 }

    return {
      page,
      itemsPerPage: perPage,
      totalItems,
      pageCount,
      resetPage,
    }
  }

  const page = ref(1)

  const rawItems = computed(() => {
    const val = unref(items)
    return Array.isArray(val) ? val : []
  })

  const perPageNumber = computed(() => {
    const val = Number(unref(itemsPerPage))
    return val > 0 ? val : 10
  })

  const totalPages = computed(() => {
    const total = rawItems.value.length
    return total === 0 ? 0 : Math.ceil(total / perPageNumber.value)
  })

  const currentItems = computed(() => {
    const start = (page.value - 1) * perPageNumber.value
    const end = start + perPageNumber.value
    return rawItems.value.slice(start, end)
  })

  // Auto-clamp page if data shrinks
  watch(totalPages, newTotal => {
    if (newTotal > 0 && page.value > newTotal) {
      page.value = newTotal
    } else if (newTotal === 0) {
      page.value = 1
    }
  })

  const nextPage = () => {
    if (page.value < totalPages.value) page.value++
  }

  const prevPage = () => {
    if (page.value > 1) page.value--
  }

  const setPage = p => {
    const target = Number(p)
    if (!Number.isNaN(target) && target >= 1 && target <= Math.max(1, totalPages.value)) {
      page.value = target
    }
  }

  return {
    page,
    totalPages,
    currentItems,
    nextPage,
    prevPage,
    setPage,
  }
}
