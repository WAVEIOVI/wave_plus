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

export const useSwoStore = defineStore('swo', () => {
  // ── State ──────────────────────────────────────────────
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const kanbanBoard = ref({})
  const metaCounters = ref({})
  const eligibleLines = ref([])
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  const swoStats = ref(null)
  const operationalScope = ref(null)
  const locations = ref([])
  const employees = ref([])
  const operationalCosts = ref([])

  // ── Actions ────────────────────────────────────────────

  const fetchSwoStats = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/stats`, { method: 'GET' })
      swoStats.value = response.data || null
      return response.data
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch SWO stats:', err)
      }
      throw err
    }
  }

  const fetchScope = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/scope`, { method: 'GET' })
      operationalScope.value = response.data || null
      return operationalScope.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch SWO operational scope:', err)
      }
      throw err
    }
  }

  const fetchLocations = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/locations`, { method: 'GET' })
      locations.value = response.data || []
      return locations.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch SWO locations:', err)
      }
      throw err
    }
  }

  const updateLocations = async (id, siteIds) => {
    loading.value = true
    try {
      const response = await $api(`/operational-hub/swos/${id}/locations`, {
        method: 'POST',
        body: { site_ids: siteIds },
      })
      locations.value = response.data || []
      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchEmployees = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/employees`, { method: 'GET' })
      employees.value = response.data || []
      return employees.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch SWO employees:', err)
      }
      throw err
    }
  }

  const updateEmployees = async (id, employeeIds, leadEmployeeId = null) => {
    loading.value = true
    try {
      const response = await $api(`/operational-hub/swos/${id}/employees`, {
        method: 'POST',
        body: { employee_ids: employeeIds, lead_employee_id: leadEmployeeId },
      })
      employees.value = response.data || []
      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchOperationalCosts = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/operational-costs`, { method: 'GET' })
      operationalCosts.value = response.data || []
      return operationalCosts.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch operational costs:', err)
      }
      throw err
    }
  }

  const addOperationalCost = async (id, payload) => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/operational-costs`, {
        method: 'POST',
        body: payload,
      })
      if (response.data) {
        operationalCosts.value.unshift(response.data)
      }
      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    }
  }

  const deleteOperationalCost = async (swoId, costId) => {
    try {
      await $api(`/operational-hub/swos/${swoId}/operational-costs/${costId}`, { method: 'DELETE' })
      operationalCosts.value = operationalCosts.value.filter(c => c.id !== costId)
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  const fetchDocumentableStats = async id => {
    try {
      const response = await $api(`/operational-hub/swos/${id}/documentable-stats`, { method: 'GET' })
      return response.data
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch documentable stats:', err)
      }
      throw err
    }
  }

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

      const response = await $api('/operational-hub/swos', {
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
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchKanban = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/swos/kanban', {
        method: 'GET',
        params,
      })

      kanbanBoard.value = response.data || {}
      return response
    } catch (err) {
      if (!is401(err)) {
        const { message, errors } = extractApiError(err)
        error.value = message
        validationErrors.value = errors
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchMeta = async () => {
    try {
      const response = await $api('/operational-hub/swos/meta', {
        method: 'GET',
      })

      metaCounters.value = response.data || {}
      return response.data
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch SWO meta:', err)
      }
    }
  }

  const fetchDetails = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/swos/${id}`, {
        method: 'GET',
      })

      currentItem.value = response.data
      return response.data
    } catch (err) {
      if (!is401(err)) {
        const { message, errors } = extractApiError(err)
        error.value = message
        validationErrors.value = errors
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchEligibleSoLines = async salesOrderId => {
    try {
      const response = await $api(`/operational-hub/swos/eligible-so-lines/${salesOrderId}`, {
        method: 'GET',
      })

      eligibleLines.value = response.data || []
      return eligibleLines.value
    } catch (err) {
      if (!is401(err)) {
        const { message, errors } = extractApiError(err)
        error.value = message
      }
      throw err
    }
  }

  const createFromSalesOrder = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/operational-hub/swos/from-so', {
        method: 'POST',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
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
      const response = await $api(`/operational-hub/swos/${id}`, {
        method: 'PUT',
        body: payload,
      })

      if (currentItem.value && currentItem.value.id === id) {
        currentItem.value = response.data
      }

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateStatus = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/operational-hub/swos/${id}/transition`, {
        method: 'PATCH',
        body: payload,
      })

      if (currentItem.value && currentItem.value.id === id) {
        currentItem.value = response.data
      }

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      validationErrors.value = errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteItem = async id => {
    loading.value = true
    error.value = null

    try {
      await $api(`/operational-hub/swos/${id}`, {
        method: 'DELETE',
      })

      list.value = list.value.filter(item => item.id !== id)
      totalItems.value = Math.max(0, totalItems.value - 1)
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const generateDeliveryNote = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/swos/${id}/delivery-note`, {
        method: 'POST',
      })

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    list,
    currentItem,
    totalItems,
    kanbanBoard,
    metaCounters,
    eligibleLines,
    swoStats,
    locations,
    employees,
    operationalCosts,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchKanban,
    fetchMeta,
    fetchDetails,
    fetchEligibleSoLines,
    createFromSalesOrder,
    update,
    updateStatus,
    deleteItem,
    generateDeliveryNote,
    fetchSwoStats,
    operationalScope,
    fetchScope,
    fetchLocations,
    updateLocations,
    fetchEmployees,
    updateEmployees,
    fetchOperationalCosts,
    addOperationalCost,
    deleteOperationalCost,
    fetchDocumentableStats,
  }
})
