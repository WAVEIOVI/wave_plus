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

export const useSalesOrderStore = defineStore('crm-sales-order', () => {
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
  // ACTIONS — LIST & FETCH
  // ──────────────────────────────────────────────────

  /**
   * Fetch a paginated/filtered list of Sales Orders.
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

      const response = await $api('/crm/sales-orders', {
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
   * Fetch meta stats (dashboard cards)
   * @returns {Promise<any>}
   */
  const fetchMeta = async () => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/crm/sales-orders/meta', { method: 'GET' })

      return response.data || response
    } catch (err) {
      if (!is401(err)) {
        console.warn('Meta endpoint not available for Sales Orders')
      }

      return null
    }
  }

  /**
   * Fetch single Sales Order by ID.
   * @param {number|string} id
   * @returns {Promise<any>}
   */
  const fetchDetails = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}`, { method: 'GET' })

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

  // ──────────────────────────────────────────────────
  // ACTIONS — CREATE / UPDATE / DELETE
  // ──────────────────────────────────────────────────

  /**
   * Create a new Sales Order.
   * @param {object} data
   * @returns {Promise<any>}
   */
  const create = async data => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/crm/sales-orders', {
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
   * Update a draft Sales Order.
   * @param {number|string} id
   * @param {object} data
   * @returns {Promise<any>}
   */
  const update = async (id, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}`, {
        method: 'PUT',
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
   * Delete a Sales Order.
   * @param {number|string} id
   * @returns {Promise<any>}
   */
  const deleteItem = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}`, {
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

  // ──────────────────────────────────────────────────
  // ACTIONS — STATE MACHINE TRANSITIONS
  // ──────────────────────────────────────────────────

  /**
   * Submit a draft SO for feasibility review.
   * Transitions: draft → submitted
   * @param {string} id
   * @returns {Promise<any>}
   */
  const submitForReview = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/submit`, { method: 'POST' })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  /**
   * Start feasibility review (reviewer picks up the SO).
   * Transitions: submitted → under_review
   * @param {string} id
   * @returns {Promise<any>}
   */
  const startFeasibilityReview = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/start-feasibility-review`, { method: 'POST' })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  /**
   * Record feasibility review results for an SO.
   * Transitions: submitted → under_review
   * @param {string} id
   * @param {object} data - { credit_check_result, stock_check_result|capacity_check_result, feasibility_decision, rejection_reason }
   * @returns {Promise<any>}
   */
  const reviewFeasibility = async (id, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/feasibility-review`, {
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
   * Accept an SO after feasibility approval.
   * Generates GCS/LSA. Does not reserve stock, create DWO/SWO, or change operational status.
   * @param {string} id
   * @returns {Promise<any>}
   */
  const acceptOrder = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/accept`, { method: 'POST' })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  /**
   * Reject an SO.
   * @param {string} id
   * @param {string} rejectionReason
   * @returns {Promise<any>}
   */
  const rejectOrder = async (id, rejectionReason) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/reject`, {
        method: 'POST',
        body: { rejection_reason: rejectionReason },
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
   * Cancel an SO (only allowed before dispatch or downstream work has started).
   * @param {string} id
   * @param {string|null} reason
   * @returns {Promise<any>}
   */
  const cancelOrder = async (id, reason = null) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/cancel`, {
        method: 'POST',
        body: { reason },
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
   * Create a new amendment/revision of an accepted SO.
   * @param {string} id
   * @param {object} data
   * @returns {Promise<any>}
   */
  const createRevision = async (id, data = {}) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/revisions`, {
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

  // ──────────────────────────────────────────────────
  // ACTIONS — CONVERSIONS
  // ──────────────────────────────────────────────────

  /**
   * Convert an accepted Quotation into a Sales Order.
   * Preserves all lines, pricing, client, variants without re-resolution.
   * @param {string} quotationId
   * @returns {Promise<any>}
   */
  const convertFromQuotation = async quotationId => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/convert-quotation/${quotationId}`, {
        method: 'POST',
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
   * Create a contract call-off SO from an active MSA/Contract.
   * @param {string} contractId
   * @returns {Promise<any>}
   */
  const convertFromContract = async contractId => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/convert-contract/${contractId}`, {
        method: 'POST',
      })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ──────────────────────────────────────────────────
  // ACTIONS — FEASIBILITY CHECKS
  // ──────────────────────────────────────────────────

  /**
   * Run a real-time stock check for all Sales SO lines.
   * Returns per-line stock availability (sufficient/partial/insufficient).
   * @param {string} id
   * @returns {Promise<any>}
   */
  const checkStockAvailability = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/stock-check`, { method: 'GET' })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  /**
   * Run a credit check for this SO against contract or client limit.
   * @param {string} id
   * @returns {Promise<any>}
   */
  const checkCredit = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/credit-check`, { method: 'GET' })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  /**
   * Add lines generated from an Equipment Blueprint.
   * @param {string} id
   * @param {object} data
   * @returns {Promise<any>}
   */
  const addBlueprintLines = async (id, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/lines/from-blueprint`, {
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
   * Add a delivery/service location to an SO.
   * @param {string} id
   * @param {object} data
   * @returns {Promise<any>}
   */
  const addLocation = async (id, data) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/locations`, {
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
   * Remove a location from an SO.
   * @param {string} id
   * @param {string} locationId
   * @returns {Promise<any>}
   */
  const removeLocation = async (id, locationId) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/crm/sales-orders/${id}/locations/${locationId}`, {
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

    // List & fetch
    fetchList,
    fetchMeta,
    fetchDetails,

    // CRUD
    create,
    update,
    deleteItem,

    // State machine transitions
    submitForReview,
    startFeasibilityReview,
    reviewFeasibility,
    acceptOrder,
    rejectOrder,
    cancelOrder,
    createRevision,

    // Conversions
    convertFromQuotation,
    convertFromContract,

    // Blueprint Lines & Locations
    addBlueprintLines,
    addLocation,
    removeLocation,

    // Feasibility checks
    checkStockAvailability,
    checkCredit,
  }
})
