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

export const useWorkshopUnitStore = defineStore('workshopUnit', () => {
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/workshop-units', {
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
      const response = await $api(`/operational-hub/workshop-units/${id}`, {
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
      const response = await $api('/operational-hub/workshop-units', {
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

  const update = async (id, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/operational-hub/workshop-units/${id}`, {
        method: 'PUT',
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

  const deleteItem = async id => {
    loading.value = true
    error.value = null

    try {
      await $api(`/operational-hub/workshop-units/${id}`, {
        method: 'DELETE',
      })

      list.value = list.value.filter(item => item.id !== id)
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
    create,
    update,
    deleteItem,
  }
})
