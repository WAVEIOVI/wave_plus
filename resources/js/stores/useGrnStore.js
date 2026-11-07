/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

// ──────────────────────────────────────────────────
// PRIVATE HELPERS
// ──────────────────────────────────────────────────

/** Serialize reactive data for API payload — strips Vue proxies safely */
const deepClone = data => {
  const raw = toRaw(data)

  return raw instanceof FormData ? raw : JSON.parse(JSON.stringify(raw))
}

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

export const useGrnStore = defineStore('grn', () => {
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
   * Fetch a paginated/filtered list of items.
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

      const response = await $api('/procurement/grns', {
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
      const response = await $api(`/procurement/grns/${id}`, { method: 'GET' })
      
      return response.data
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
   * Create a new item.
   * @param {object} data - The data to create
   * @returns {Promise<any>}
   */
  const create = async data => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/procurement/grns', {
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

  /**
   * Update an existing item.
   * @param {number|string} id - The ID of the item
   * @param {object} data - The data to update
   * @returns {Promise<any>}
   */
  const update = async (id, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const body = deepClone(data)
      const isFormData = body instanceof FormData
      if (isFormData && !body.has('_method')) {
        body.append('_method', 'PUT')
      }

      const response = await $api(`/procurement/grns/${id}`, {
        method: isFormData ? 'POST' : 'PUT',
        body,
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
   * Delete an item.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const deleteItem = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/procurement/grns/${id}`, {
        method: 'DELETE',
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
   * Update item status specifically (if supported by GRN)
   * @param {number|string} id - The ID of the item
   * @param {object} payload - The status data
   * @returns {Promise<any>}
   */
  const updateStatus = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/procurement/grns/${id}/status`, {
        method: 'PATCH',
        body: payload,
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
   * Fetch summary metadata counts for GRNs.
   * @returns {Promise<object>}
   */
  const fetchMeta = async () => {
    try {
      const response = await $api('/procurement/grns/meta', { method: 'GET' })

      return response.data || response
    } catch (err) {
      if (is401(err)) return {}
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
    create,
    update,
    deleteItem,
    updateStatus,
    fetchMeta,
  }
})
