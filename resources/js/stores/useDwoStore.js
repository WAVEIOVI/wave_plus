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

/**
 * Workflow-bucket → backend status mapping.
 * Used by the Deliveries page filter tabs so the UI speaks
 * "business language" while sending the correct status values to the API.
 *
 * A bucket that maps to multiple statuses sends a comma-separated
 * `filter[status]` value; the backend controller already supports it
 * via `whereIn`.  If only one status maps, it sends a plain string.
 */
export const WORKFLOW_BUCKET_STATUSES = {
  all:                null,                                    // no filter
  to_plan:            'draft',
  to_prepare:         'planned,ready_to_pick',
  picking:            'picking',
  ready_to_dispatch:  'ready_to_load,loaded',
  in_transit:         'dispatched,in_transit',
  completed:          'delivered,partially_delivered,closed',
  exception:          'exception,delivery_failed',
}

export const useDwoStore = defineStore('dwo', () => {
  // ── State ──────────────────────────────────────────────
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const kanbanBoard = ref({})
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

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

      const response = await $api('/logistics/dwos', {
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

  const fetchKanban = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/logistics/dwos/kanban', { method: 'GET' })

      kanbanBoard.value = response.data || {}
      
      return kanbanBoard.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchMeta = async () => {
    try {
      const response = await $api('/logistics/dwos/meta', { method: 'GET' })
      
      return response.data || response
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
      }
      throw err
    }
  }

  const fetchDetails = async id => {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/logistics/dwos/${id}`, { method: 'GET' })

      currentItem.value = response.data ?? response
      
      return currentItem.value
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchEligibleSoLines = async soId => {
    try {
      const response = await $api(`/logistics/dwos/eligible-so-lines/${soId}`, { method: 'GET' })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const createFromSalesOrder = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api('/logistics/dwos/from-so', {
        method: 'POST',
        body: deepClone(payload),
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api(`/logistics/dwos/${id}`, {
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

  const updateStatus = async (id, payload) => {
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api(`/logistics/dwos/${id}/transition`, {
        method: 'PATCH',
        body: deepClone(payload),
      })

      currentItem.value = response.data || response
      
      return currentItem.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const recordPick = async (dwoId, lineId, data) => {
    try {
      const response = await $api(`/logistics/dwos/${dwoId}/lines/${lineId}/pick`, {
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

  const batchConfirmPicks = async (dwoId, picks) => {
    try {
      const response = await $api(`/logistics/dwos/${dwoId}/picks`, {
        method: 'POST',
        body: { picks: deepClone(picks) },
      })

      currentItem.value = response.data || response
      
      return currentItem.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const fetchPod = async dwoId => {
    try {
      const response = await $api(`/logistics/dwos/${dwoId}/pod`, { method: 'GET' })
      
      return response.data || response
    } catch (err) {
      if (err?.statusCode === 404) return null
      throw err
    }
  }

  const recordPod = async (dwoId, podData) => {
    try {
      const response = await $api(`/logistics/dwos/${dwoId}/pod`, {
        method: 'POST',
        body: deepClone(podData),
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const recordException = async (dwoId, exceptionData) => {
    try {
      const response = await $api(`/logistics/dwos/${dwoId}/exceptions`, {
        method: 'POST',
        body: deepClone(exceptionData),
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const resolveException = async (exceptionId, data) => {
    try {
      const response = await $api(`/logistics/exceptions/${exceptionId}/resolve`, {
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

  const deleteItem = async id => {
    error.value = null
    try {
      await $api(`/logistics/dwos/${id}`, { method: 'DELETE' })
      list.value = list.value.filter(item => item.id !== id)
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  // ── Domain-oriented workflow actions ────────────────────────────────────────
  //
  // These wrap the generic updateStatus() with named business actions so
  // components and pages can express intent rather than raw status values.
  // They do NOT duplicate backend logic — they simply route to the correct
  // transition endpoint with the appropriate target status.

  /**
   * Generic transition — primary method called by the workspace.
   * @param {string|number} id
   * @param {string} targetStatus - one of the DwoStatus enum values
   */
  const performTransition = (id, targetStatus) => updateStatus(id, { status: targetStatus })

  /** Draft → Planned */
  const planDelivery = id => performTransition(id, 'planned')

  /** Planned → ReadyToPick */
  const startPreparation = id => performTransition(id, 'ready_to_pick')

  /** ReadyToPick → Picking */
  const startPicking = id => performTransition(id, 'picking')

  /** Picking → ReadyToLoad */
  const completePicking = id => performTransition(id, 'ready_to_load')

  /** ReadyToLoad → Loaded */
  const loadVehicle = id => performTransition(id, 'loaded')

  /** Loaded → Dispatched */
  const dispatchDelivery = id => performTransition(id, 'dispatched')

  /** Dispatched → InTransit */
  const startDelivery = id => performTransition(id, 'in_transit')

  /** InTransit → Delivered */
  const confirmDelivery = id => performTransition(id, 'delivered')

  /** InTransit → PartiallyDelivered */
  const confirmPartialDelivery = id => performTransition(id, 'partially_delivered')

  /** InTransit|Delivered|Dispatched → Exception */
  const reportException = id => performTransition(id, 'exception')

  /** DeliveryFailed → Planned (reschedule) */
  const reschedule = id => performTransition(id, 'planned')

  /** Delivered|PartiallyDelivered|DeliveryFailed → Closed */
  const closeDelivery = id => performTransition(id, 'closed')

  return {
    list,
    currentItem,
    totalItems,
    kanbanBoard,
    loading,
    error,
    validationErrors,
    // Core CRUD
    fetchList,
    fetchKanban,
    fetchMeta,
    fetchDetails,
    fetchEligibleSoLines,
    createFromSalesOrder,
    update,
    updateStatus,
    deleteItem,
    // Picking
    recordPick,
    batchConfirmPicks,
    // POD
    fetchPod,
    recordPod,
    // Exceptions
    recordException,
    resolveException,
    // Domain workflow actions
    performTransition,
    planDelivery,
    startPreparation,
    startPicking,
    completePicking,
    loadVehicle,
    dispatchDelivery,
    startDelivery,
    confirmDelivery,
    confirmPartialDelivery,
    reportException,
    reschedule,
    closeDelivery,
  }
})
