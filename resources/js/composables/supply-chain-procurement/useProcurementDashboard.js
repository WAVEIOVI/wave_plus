/* eslint-disable camelcase */
import { usePoStore } from '@/stores/usePoStore'
import { $api } from '@/utils/api'
import { onMounted, ref } from 'vue'

/**
 * Owns the Supply Chain & Procurement dashboard requests and lifecycle.
 */
export function useProcurementDashboard() {
  const poStore = usePoStore()
  const data = ref(null)
  const recentPos = ref([])
  const loading = ref(false)
  const error = ref(null)
  const updatedAt = ref(null)
  let requestId = 0

  const fetchDashboard = async () => {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null

    try {
      const [dashboardRes, poRes] = await Promise.all([
        $api('/procurement/dashboard'),
        poStore.fetchList({ per_page: 8 }).catch(() => null),
      ])

      if (currentRequest !== requestId) return

      data.value = dashboardRes?.data ?? dashboardRes ?? null
      recentPos.value = Array.isArray(poRes?.data) ? poRes.data : (poStore.list || [])
      updatedAt.value = new Date()
    }
    catch (requestError) {
      if (currentRequest !== requestId) return

      error.value = requestError?.data?.message
        || requestError?.response?._data?.message
        || requestError?.message
        || 'Unable to load the procurement dashboard.'
    }
    finally {
      if (currentRequest === requestId) loading.value = false
    }
  }

  onMounted(fetchDashboard)

  return {
    data,
    recentPos,
    loading,
    error,
    updatedAt,
    fetchDashboard,
  }
}
