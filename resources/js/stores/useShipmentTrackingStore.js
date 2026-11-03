import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

// ──────────────────────────────────────────────────
// PRIVATE HELPERS
// ──────────────────────────────────────────────────

/** Serialize reactive data for API payload — strips Vue proxies safely */
const deepClone = data => JSON.parse(JSON.stringify(toRaw(data)))

/**
 * Extracts error message and validation errors from an API response.
 * @param {any} err - The error object
 * @returns {{ message: string, errors: object|null }}
 */
const extractApiError = err => {
  // Fix || precedence bug: check err?.data?.errors FIRST, then fall back to err?.data?.message
  const errors = err?.data?.errors || null

  const message = err?.data?.errors 
    ? (err?.data?.message || 'Validation error') 
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

/**
 * Checks if the error is a 401 Unauthorized.
 * @param {any} err - The error object
 * @returns {boolean}
 */
const is401 = err => {
  return err?.response?.status === 401 || err?.statusCode === 401
}

export const useShipmentTrackingStore = defineStore('shipmentTracking', () => {
  // ──────────────────────────────────────────────────
  // STATE
  // ──────────────────────────────────────────────────

  /** @type {import('vue').Ref<Array>} */
  const history = ref([])

  /** @type {import('vue').Ref<boolean>} */
  const loading = ref(false)

  /** @type {import('vue').Ref<string|null>} */
  const error = ref(null)

  /** @type {import('vue').Ref<object|null>} */
  const validationErrors = ref(null)

  // ──────────────────────────────────────────────────
  // ACTIONS
  // ──────────────────────────────────────────────────

  /**
   * Fetch tracking history for a DWO
   * @param {number|string} dwoId - The ID of the DWO
   * @returns {Promise<any>}
   */
  const fetchHistory = async dwoId => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/dwos/${dwoId}/tracking`, {
        method: 'GET',
      })

      history.value = response.data ?? response ?? []

      return history.value
    } catch (err) {
      if (is401(err)) {
        history.value = []
        
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

  /**
   * Add a tracking event
   * @param {number|string} dwoId - The ID of the DWO
   * @param {object} data - The event data
   * @returns {Promise<any>}
   */
  const addEvent = async (dwoId, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/dwos/${dwoId}/tracking`, {
        method: 'POST',
        body: deepClone(data),
      })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  return {
    history,
    loading,
    error,
    validationErrors,
    fetchHistory,
    addEvent,
  }
})
