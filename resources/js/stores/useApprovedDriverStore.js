/* eslint-disable camelcase */
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

export const useApprovedDriverStore = defineStore('approvedDriver', () => {
  // ── State ──────────────────────────────────────────────
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)
  const activityLog = ref([])
  const licenseRenewalAlerts = ref([])
  const reviewAlerts = ref([])
  const driverScore = ref(null)

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

      const response = await $api('/hr/approved-drivers', {
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
      const response = await $api(`/hr/approved-drivers/${id}`, {
        method: 'GET',
      })

      const result = response.data ?? response

      currentItem.value = result

      return result
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
      const response = await $api('/hr/approved-drivers', {
        method: 'POST',
        body: deepClone(payload),
      })

      const result = response.data ?? response

      currentItem.value = result

      return result
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
      const response = await $api(`/hr/approved-drivers/${id}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      const result = response.data ?? response

      currentItem.value = result

      return result
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
      const response = await $api(`/hr/approved-drivers/${id}`, {
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

  // ── Driver Authorization Operations ────────────────────

  const suspend = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/suspend`, {
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

  const reinstate = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/reinstate`, {
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

  const revoke = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api(`/hr/approved-drivers/${id}/revoke`, { method: 'POST', body: deepClone(payload) })
      
      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally { loading.value = false }
  }

  const renew = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/renew`, {
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

  // ── Incident Management ────────────────────────────────

  const recordIncident = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/incidents`, {
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

  // ── Score Management ───────────────────────────────────

  const calculateScore = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/calculate-score`, {
        method: 'GET',
      })

      driverScore.value = response.data?.score ?? null

      return response.data
    } catch (err) {
      if (is401(err)) {
        driverScore.value = null
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const adjustScore = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/adjust-score`, {
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

  // ── Activity & Alerts ──────────────────────────────────

  const fetchActivityLog = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/approved-drivers/${id}/activity-log`, {
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

  const fetchLicenseRenewalAlerts = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/hr/approved-drivers/requires-license-renewal', {
        method: 'GET',
      })

      licenseRenewalAlerts.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        licenseRenewalAlerts.value = []
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchReviewAlerts = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/hr/approved-drivers/requires-review', {
        method: 'GET',
      })

      reviewAlerts.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        reviewAlerts.value = []
        
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
    driverScore.value = null
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
    licenseRenewalAlerts,
    reviewAlerts,
    driverScore,

    // Actions
    fetchList,
    fetchById,
    create,
    update,
    remove,
    suspend,
    reinstate,
    revoke,
    renew,
    recordIncident,
    calculateScore,
    adjustScore,
    fetchActivityLog,
    fetchLicenseRenewalAlerts,
    fetchReviewAlerts,
    resetItem,
    resetList,
  }
})
