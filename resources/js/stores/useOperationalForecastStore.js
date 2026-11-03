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

export const useOperationalForecastStore = defineStore('operationalForecast', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const summary = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const fetchSummary = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/operational-hub/forecasts/summary', { method: 'GET', params })
      summary.value = response.data || null
      return summary.value
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/forecasts', {
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
      const response = await $api(`/operational-hub/forecasts/${id}`, {
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

  const create = async payload => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/forecasts', {
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
      const response = await $api(`/operational-hub/forecasts/${id}`, {
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

  return {
    list,
    currentItem,
    totalItems,
    summary,
    loading,
    error,
    fetchList,
    fetchSummary,
    fetchDetails,
    create,
    update,
  }
})
