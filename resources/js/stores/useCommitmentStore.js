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

export const useCommitmentStore = defineStore('crm-commitment', () => {
  // ──────────────────────────────────────────────────
  // STATE
  // ──────────────────────────────────────────────────

  // ── LSA State ──────────────────────────────────────────────────────────
  /** @type {import('vue').Ref<Array>} */
  const lsaDocuments = ref([])

  /** @type {import('vue').Ref<number>} */
  const totalLsa = ref(0)

  /** @type {import('vue').Ref<boolean>} */
  const lsaLoading = ref(false)

  /** @type {import('vue').Ref<string|null>} */
  const lsaError = ref(null)

  /** @type {import('vue').Ref<object|null>} */
  const lsaValidationErrors = ref(null)

  // ── GCS State ──────────────────────────────────────────────────────────
  /** @type {import('vue').Ref<Array>} */
  const gcsDocuments = ref([])

  /** @type {import('vue').Ref<number>} */
  const totalGcs = ref(0)

  /** @type {import('vue').Ref<boolean>} */
  const gcsLoading = ref(false)

  /** @type {import('vue').Ref<string|null>} */
  const gcsError = ref(null)

  /** @type {import('vue').Ref<object|null>} */
  const gcsValidationErrors = ref(null)

  // ──────────────────────────────────────────────────
  // ACTIONS
  // ──────────────────────────────────────────────────

  // ── LSA Actions ────────────────────────────────────────────────────────

  /**
   * Fetch a paginated/filtered list of LSA items.
   * Normalizes pagination (accepts both `itemsPerPage` and `per_page`, always sends `per_page` to backend).
   * Handles 401 silently (clears state, returns).
   * Stores error and validationErrors from Laravel 422 payload.
   * @param {object} params - Query parameters
   * @returns {Promise<any>}
   */
  const fetchLsaDocuments = async (params = {}) => {
    lsaLoading.value = true
    lsaError.value = null
    lsaValidationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/crm/lsa-documents', {
        method: 'GET',
        params: apiParams,
      })

      lsaDocuments.value = response.data || []
      totalLsa.value = response.meta?.total ?? response.total ?? lsaDocuments.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        lsaDocuments.value = []
        totalLsa.value = 0
        
        return
      }

      const extracted = extractApiError(err)

      lsaError.value = extracted.message
      lsaValidationErrors.value = extracted.errors
    } finally {
      lsaLoading.value = false
    }
  }

  /**
   * Fetch single LSA item by ID.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const fetchLsaById = async id => {
    lsaError.value = null
    lsaValidationErrors.value = null

    try {
      const response = await $api(`/crm/lsa-documents/${id}`, { method: 'GET' })
      
      return response.data
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        lsaError.value = extracted.message
        lsaValidationErrors.value = extracted.errors
      }
      throw err
    }
  }

  // ── GCS Actions ────────────────────────────────────────────────────────

  /**
   * Fetch a paginated/filtered list of GCS items.
   * Normalizes pagination (accepts both `itemsPerPage` and `per_page`, always sends `per_page` to backend).
   * Handles 401 silently (clears state, returns).
   * Stores error and validationErrors from Laravel 422 payload.
   * @param {object} params - Query parameters
   * @returns {Promise<any>}
   */
  const fetchGcsDocuments = async (params = {}) => {
    gcsLoading.value = true
    gcsError.value = null
    gcsValidationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/crm/gcs-documents', {
        method: 'GET',
        params: apiParams,
      })

      gcsDocuments.value = response.data || []
      totalGcs.value = response.meta?.total ?? response.total ?? gcsDocuments.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        gcsDocuments.value = []
        totalGcs.value = 0
        
        return
      }

      const extracted = extractApiError(err)

      gcsError.value = extracted.message
      gcsValidationErrors.value = extracted.errors
    } finally {
      gcsLoading.value = false
    }
  }

  /**
   * Fetch single GCS item by ID.
   * @param {number|string} id - The ID of the item
   * @returns {Promise<any>}
   */
  const fetchGcsById = async id => {
    gcsError.value = null
    gcsValidationErrors.value = null

    try {
      const response = await $api(`/crm/gcs-documents/${id}`, { method: 'GET' })
      
      return response.data
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        gcsError.value = extracted.message
        gcsValidationErrors.value = extracted.errors
      }
      throw err
    }
  }

  return {
    // LSA
    lsaDocuments,
    totalLsa,
    lsaLoading,
    lsaError,
    lsaValidationErrors,
    fetchLsaDocuments,
    fetchLsaById,

    // GCS
    gcsDocuments,
    totalGcs,
    gcsLoading,
    gcsError,
    gcsValidationErrors,
    fetchGcsDocuments,
    fetchGcsById,
  }
})
