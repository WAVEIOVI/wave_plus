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

export const useWorkshopExitPassStore = defineStore('workshopExitPass', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const transferStats = ref(null)
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

      const response = await $api('/operational-hub/exit-passes', {
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

  const fetchForMission = async missionId => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/missions/${missionId}/exit-passes`, {
        method: 'GET',
      })

      list.value = response.data || []
      return list.value
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

  const fetchDetails = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/exit-passes/${id}`, {
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

  const create = async (payload, missionId = null) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const url = missionId 
        ? `/operational-hub/missions/${missionId}/exit-passes`
        : '/operational-hub/exit-passes'

      const response = await $api(url, {
        method: 'POST',
        body: payload,
      })

      if (response.data) {
        list.value.unshift(response.data)
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

  const updateStatus = async (id, status) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/exit-passes/${id}/status`, {
        method: 'PATCH',
        body: { status },
      })

      if (currentItem.value && currentItem.value.id === id) {
        currentItem.value = response.data
      }

      const idx = list.value.findIndex(p => p.id === id)
      if (idx !== -1 && response.data) {
        list.value[idx] = response.data
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

  const fetchTransferStats = async missionId => {
    try {
      const response = await $api(`/operational-hub/missions/${missionId}/transfer-stats`, {
        method: 'GET',
      })

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
    transferStats,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchForMission,
    fetchDetails,
    create,
    updateStatus,
    fetchTransferStats,
  }
})
