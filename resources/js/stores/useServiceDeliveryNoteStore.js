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

export const useServiceDeliveryNoteStore = defineStore('serviceDeliveryNote', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/delivery-notes', {
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
      const response = await $api(`/operational-hub/delivery-notes/${id}`, {
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

  const generate = async (swoId, options = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/swos/${swoId}/delivery-note`, {
        method: 'POST',
        body: options,
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

  const fetchDocumentableStats = async swoId => {
    try {
      const response = await $api(`/operational-hub/swos/${swoId}/documentable-stats`, {
        method: 'GET',
      })

      return response.data
    } catch (err) {
      const { message } = extractApiError(err)
      error.value = message
      throw err
    }
  }

  const sign = async (id, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/delivery-notes/${id}/sign`, {
        method: 'PATCH',
        body: payload,
      })

      if (currentItem.value && currentItem.value.id === id) {
        currentItem.value = response.data
      }

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
    loading,
    error,
    fetchList,
    fetchDetails,
    generate,
    sign,
    fetchDocumentableStats,
  }
})
