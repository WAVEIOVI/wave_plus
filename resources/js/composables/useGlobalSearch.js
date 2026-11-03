import { $api } from '@/utils/api'
import { useDebounceFn } from '@vueuse/core'

/**
 * Composable for the global search (Omnibar / Cmd+K).
 *
 * - Debounces input (300ms)
 * - Minimum 2-char threshold
 * - Returns grouped results matching Vuexy's { title, children } format
 */
export function useGlobalSearch() {
  const searchQuery = ref('')
  const searchResults = ref([])
  const isLoading = ref(false)

  const performSearch = useDebounceFn(async query => {
    const trimmed = query.trim()

    // Clear results if below threshold
    if (trimmed.length < 2) {
      searchResults.value = []
      isLoading.value = false

      return
    }

    isLoading.value = true

    try {
      const data = await $api('/search', {
        method: 'GET',
        params: { q: trimmed },
      })

      searchResults.value = data || []
    }
    catch (error) {
      console.error('Global search error:', error)
      searchResults.value = []
    }
    finally {
      isLoading.value = false
    }
  }, 300)

  // Watch for query changes and debounce the API call
  watch(searchQuery, newQuery => {
    if (!newQuery || newQuery.trim().length < 2) {
      searchResults.value = []
      isLoading.value = false

      return
    }

    // Show loading immediately for responsiveness
    isLoading.value = true
    performSearch(newQuery)
  })

  // Reset state
  const clearSearch = () => {
    searchQuery.value = ''
    searchResults.value = []
    isLoading.value = false
  }

  return {
    searchQuery,
    searchResults,
    isLoading,
    clearSearch,
  }
}
