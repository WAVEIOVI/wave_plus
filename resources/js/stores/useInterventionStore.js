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

export const useInterventionStore = defineStore('intervention', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/interventions', {
        method: 'GET',
        params,
      })

      list.value = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length

      return response
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchDetails = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/interventions/${id}`, {
        method: 'GET',
      })

      currentItem.value = response.data
      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const create = async (missionId, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/missions/${missionId}/interventions`, {
        method: 'POST',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/interventions/${id}`, {
        method: 'PUT',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const complete = async (id, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/interventions/${id}/complete`, {
        method: 'PATCH',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const addMaterial = async (id, payload) => {
    try {
      const response = await $api(`/operational-hub/interventions/${id}/materials`, {
        method: 'POST',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  const addFinding = async (id, payload) => {
    try {
      const response = await $api(`/operational-hub/interventions/${id}/findings`, {
        method: 'POST',
        body: payload,
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  return {
    list,
    currentItem,
    totalItems,
    loading,
    error,
    fetchList,
    fetchDetails,
    create,
    update,
    complete,
    addMaterial,
    addFinding,
  }
})
