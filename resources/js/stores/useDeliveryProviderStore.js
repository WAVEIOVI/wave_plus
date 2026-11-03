import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

const deepClone = data => JSON.parse(JSON.stringify(toRaw(data)))

const extractApiError = err => {
  const errors = err?.data?.errors || null
  const message = err?.data?.errors 
    ? (err?.data?.message || 'Validation error') 
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

const is401 = err => {
  return err?.response?.status === 401 || err?.statusCode === 401
}

export const useDeliveryProviderStore = defineStore('deliveryProvider', () => {
  // ── State ──────────────────────────────────────────────
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)
  const activityLog = ref([])
  const performanceReport = ref(null)
  const availableProviders = ref([])

  // ── Actions ────────────────────────────────────────────

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/logistics/delivery-providers', {
        method: 'GET',
        params: apiParams,
      })

      list.value = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        list.value = []
        totalItems.value = 0
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const fetchById = async id => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}`, {
        method: 'GET',
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        currentItem.value = null
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const create = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/logistics/delivery-providers', {
        method: 'POST',
        body: deepClone(payload),
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async id => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}`, {
        method: 'DELETE',
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Provider Operations ────────────────────────────────

  const updateStatus = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/status`, {
        method: 'PATCH',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateCapacity = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/capacity`, {
        method: 'PATCH',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const recordIncident = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/incidents`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const resetDailyLoad = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/reset-daily-load`, {
        method: 'POST',
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)
      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Performance & Availability ─────────────────────────

  const fetchPerformanceReport = async (id, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/performance`, {
        method: 'GET',
        params,
      })

      performanceReport.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        performanceReport.value = null
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchAvailableProviders = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/logistics/delivery-providers/available', {
        method: 'GET',
        params,
      })

      availableProviders.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        availableProviders.value = []
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchActivityLog = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/logistics/delivery-providers/${id}/activity-log`, {
        method: 'GET',
      })

      activityLog.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        activityLog.value = []
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  // ── Reset & Utilities ──────────────────────────────────

  const resetItem = () => {
    currentItem.value = null
    error.value = null
    validationErrors.value = null
    performanceReport.value = null
  }

  const resetList = () => {
    list.value = []
    totalItems.value = 0
    error.value = null
    validationErrors.value = null
  }

  return {
    // State
    list,
    currentItem,
    totalItems,
    loading,
    error,
    validationErrors,
    activityLog,
    performanceReport,
    availableProviders,

    // Actions
    fetchList,
    fetchById,
    create,
    update,
    remove,
    updateStatus,
    updateCapacity,
    recordIncident,
    resetDailyLoad,
    fetchPerformanceReport,
    fetchAvailableProviders,
    fetchActivityLog,
    resetItem,
    resetList,
  }
})
