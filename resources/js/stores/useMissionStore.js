import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

const extractApiError = err => {
  const errors = err?.data?.errors || null
  const message = err?.data?.errors 
    ? (err?.data?.message || 'Validation error') 
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

const is401 = err => err?.response?.status === 401 || err?.statusCode === 401

export const useMissionStore = defineStore('mission', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/operational-hub/missions', {
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

  const fetchDetails = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/missions/${id}`, {
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

  const create = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/operational-hub/missions', {
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
      const response = await $api(`/operational-hub/missions/${id}`, {
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

  const transition = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/operational-hub/missions/${id}/transition`, {
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

  const recordAttendance = async (id, attendances) => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/attendance`, {
        method: 'POST',
        body: { attendances },
      })

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  const saveChecklist = async (id, payload) => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/checklist`, {
        method: 'POST',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message, errors } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  const workshopTransfer = async (id, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/missions/${id}/workshop-transfer`, {
        method: 'POST',
        body: payload,
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

  const missionStats = ref(null)
  const employees = ref([])
  const transferStats = ref(null)
  const operationalScope = ref(null)

  const fetchMissionStats = async id => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/stats`, { method: 'GET' })
      missionStats.value = response.data || null
      return response.data
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch mission stats:', err)
      }
      throw err
    }
  }

  const fetchEmployees = async id => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/employees`, { method: 'GET' })
      employees.value = response.data || []
      return employees.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch mission employees:', err)
      }
      throw err
    }
  }

  const updateEmployees = async (id, employeeIds, leadEmployeeId = null) => {
    loading.value = true
    try {
      const response = await $api(`/operational-hub/missions/${id}/employees`, {
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

  const fetchScope = async id => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/scope`, { method: 'GET' })
      operationalScope.value = response.data || null
      return operationalScope.value
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch mission operational scope:', err)
      }
      throw err
    }
  }

  const fetchTransferStats = async id => {
    try {
      const response = await $api(`/operational-hub/missions/${id}/transfer-stats`, { method: 'GET' })
      transferStats.value = response.data || null
      return response.data
    } catch (err) {
      if (!is401(err)) {
        console.error('Failed to fetch transfer stats:', err)
      }
      throw err
    }
  }

  return {
    list,
    currentItem,
    totalItems,
    missionStats,
    employees,
    transferStats,
    operationalScope,
    fetchScope,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchDetails,
    create,
    update,
    transition,
    recordAttendance,
    saveChecklist,
    workshopTransfer,
    fetchMissionStats,
    fetchEmployees,
    updateEmployees,
    fetchTransferStats,
  }
})
