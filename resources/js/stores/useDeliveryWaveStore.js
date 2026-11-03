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

export const useDeliveryWaveStore = defineStore('deliveryWave', () => {
  const list = ref([])
  const currentWave = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/logistics/waves', { method: 'GET', params })

      list.value = response.data || []
      totalItems.value = response.meta?.total ?? list.value.length
      
      return response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchDetails = async id => {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/logistics/waves/${id}`, { method: 'GET' })

      currentWave.value = response.data ?? response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const create = async data => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/logistics/waves', {
        method: 'POST',
        body: deepClone(data),
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
    try {
      const response = await $api(`/logistics/waves/${id}`, {
        method: 'PUT',
        body: deepClone(data),
      })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const addDwo = async (waveId, dwoId, sequence = null) => {
    try {
      const response = await $api(`/logistics/waves/${waveId}/dwos`, {
        method: 'POST',
        body: { dwo_id: dwoId, sequence },
      })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const removeDwo = async (waveId, dwoId) => {
    try {
      const response = await $api(`/logistics/waves/${waveId}/dwos/${dwoId}`, {
        method: 'DELETE',
      })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const reorderStops = async (waveId, stops) => {
    try {
      const response = await $api(`/logistics/waves/${waveId}/reorder-stops`, {
        method: 'PATCH',
        body: { stops: deepClone(stops) },
      })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const dispatch = async waveId => {
    try {
      const response = await $api(`/logistics/waves/${waveId}/dispatch`, { method: 'POST' })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const complete = async waveId => {
    try {
      const response = await $api(`/logistics/waves/${waveId}/complete`, { method: 'POST' })

      currentWave.value = response.data || response
      
      return currentWave.value
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const deleteItem = async id => {
    try {
      await $api(`/logistics/waves/${id}`, { method: 'DELETE' })
      list.value = list.value.filter(item => item.id !== id)
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  return {
    list,
    currentWave,
    totalItems,
    loading,
    error,
    validationErrors,
    fetchList,
    fetchDetails,
    create,
    update,
    addDwo,
    removeDwo,
    reorderStops,
    dispatch,
    complete,
    deleteItem,
  }
})
