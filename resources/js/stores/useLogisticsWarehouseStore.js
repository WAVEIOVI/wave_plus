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

export const useLogisticsWarehouseStore = defineStore('logisticsWarehouse', () => {
  // ──────────────────────────────────────────────────
  // STATE
  // ──────────────────────────────────────────────────

  /** @type {import('vue').Ref<Array>} */
  const list = ref([])

  /** @type {import('vue').Ref<number>} */
  const totalItems = ref(0)

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
   * Fetch a paginated/filtered picking lists.
   * Normalizes pagination (accepts both `itemsPerPage` and `per_page`, always sends `per_page` to backend).
   * Handles 401 silently (clears state, returns).
   * Stores error and validationErrors from Laravel 422 payload.
   * @param {object} params - Query parameters
   * @returns {Promise<any>}
   */
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

      const response = await $api('/logistics/picking-lists', {
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

  /**
   * Fetch single item by ID.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const fetchDetails = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/picking-lists/${id}`, { method: 'GET' })
      
      return response.data ?? response
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  /**
   * Mark a DWO item as picked
   * @param {number|string} itemId - The ID of the item
   * @param {object} data - The data
   * @returns {Promise<any>}
   */
  const markItemPicked = async (itemId, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/picking-lists/items/${itemId}/mark-picked`, {
        method: 'PATCH',
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

  /**
   * Complete a picking list
   * @param {number|string} pickingListId - The ID of the picking list
   * @returns {Promise<any>}
   */
  const confirmLoading = async pickingListId => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/logistics/picking-lists/${pickingListId}/complete`, {
        method: 'PATCH',
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
    list,
    totalItems,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchDetails,
    markItemPicked,
    confirmLoading,
  }
})
