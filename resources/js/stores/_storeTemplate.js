/**
 * HOW TO USE THIS TEMPLATE
 * 1. Rename 'useTemplateStore' and 'TEMPLATE_ID' to your specific store name and id (e.g., 'useProductStore' / 'products')
 * 2. Update the API base path in all actions (e.g., '/api/v1/products')
 * 3. Rename `list` ref to your specific entity array (e.g., `products`)
 * 4. Rename `totalItems` ref to match your entity (e.g., `totalProducts`)
 * 5. Remove this instructional comment block and adapt imports as needed.
 */

import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

// ──────────────────────────────────────────────────
// PRIVATE HELPERS
// ──────────────────────────────────────────────────

/**
 * Extracts error message and validation errors from an API response.
 * @param {any} err - The error object
 * @returns {{ message: string, errors: object|null }}
 */
const extractApiError = err => {
  // Fix || precedence bug: check err?.data?.errors FIRST, then fall back to err?.data?.message
  const errors = err?.data?.errors || null
  const message = err?.data?.message || err?.message || 'An error occurred'

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

export const useTemplateStore = defineStore('TEMPLATE_ID', () => {
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

      const response = await $api('/api/path', {
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
   * Fetch a single item by ID.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const fetchOne = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/api/path/${id}`, { method: 'GET' })
      
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
   * @param {object} payload - The data to create
   * @returns {Promise<any>}
   */
  const create = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/api/path', {
        method: 'POST',
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
   * Update an existing item.
   * @param {number|string} id - The ID of the item
   * @param {object} payload - The data to update
   * @returns {Promise<any>}
   */
  const update = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/api/path/${id}`, {
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
   * Delete an item by ID.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const remove = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/api/path/${id}`, {
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

  return {
    list,
    totalItems,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchOne,
    create,
    update,
    remove,
  }
})
