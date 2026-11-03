import { $api } from '@/utils/api'
import { ref } from 'vue'

/**
 * Composable for fetching Catalog dashboard statistics.
 * Returns reactive `data`, `loading`, and `error` refs.
 * Mirrors the useBpDashboard composable pattern.
 */
export function useCatalogDashboard() {
  const data = ref(null)
  const loading = ref(true)
  const error = ref(null)
  const updatedAt = ref(null)
  let requestId = 0

  async function fetchDashboard() {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null

    try {
      const response = await $api('/catalog/dashboard-stats', { method: 'GET' })

      if (currentRequest !== requestId) return

      data.value = response.data
      updatedAt.value = new Date()
    }
    catch (err) {
      if (currentRequest !== requestId) return
      console.error('Error fetching Catalog dashboard stats:', err)
      error.value = err?.data?.message || err?.message || 'Error fetching Catalog dashboard stats'
    }
    finally {
      if (currentRequest === requestId) {
        loading.value = false
      }
    }
  }

  return {
    data,
    loading,
    error,
    updatedAt,
    fetchDashboard,
  }
}
