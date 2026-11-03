import { $api } from '@/utils/api'
import { onMounted, ref } from 'vue'

/**
 * Owns the single Business Partners dashboard request and its lifecycle.
 */
export function useBpDashboard() {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const updatedAt = ref(null)
  let requestId = 0

  const fetchDashboard = async () => {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null

    try {
      const response = await $api('/bp/dashboard-stats')

      if (currentRequest !== requestId) return

      data.value = response?.data ?? null
      updatedAt.value = new Date()
    }
    catch (requestError) {
      if (currentRequest !== requestId) return

      error.value = requestError?.data?.message
        || requestError?.response?._data?.message
        || requestError?.message
        || 'Unable to load the business partners dashboard.'
    }
    finally {
      if (currentRequest === requestId) loading.value = false
    }
  }

  onMounted(fetchDashboard)

  return {
    data,
    loading,
    error,
    updatedAt,
    fetchDashboard,
  }
}
