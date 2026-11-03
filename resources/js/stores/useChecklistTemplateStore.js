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

export const useChecklistTemplateStore = defineStore('checklistTemplate', () => {
  const list = ref([])
  const currentItem = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/operational-hub/checklist-templates', {
        method: 'GET',
        params,
      })

      list.value = response.data || []
      return list.value
    } catch (err) {
      if (is401(err)) {
        list.value = []
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
      const response = await $api(`/operational-hub/checklist-templates/${id}`, {
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
      const response = await $api('/operational-hub/checklist-templates', {
        method: 'POST',
        body: payload,
      })

      if (response.data) {
        list.value.push(response.data)
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

  const update = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/operational-hub/checklist-templates/${id}`, {
        method: 'PUT',
        body: payload,
      })

      if (currentItem.value && currentItem.value.id === id) {
        currentItem.value = response.data
      }

      const idx = list.value.findIndex(t => t.id === id)
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

  const deleteItem = async id => {
    loading.value = true
    try {
      await $api(`/operational-hub/checklist-templates/${id}`, { method: 'DELETE' })
      list.value = list.value.filter(t => t.id !== id)
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
    loading,
    error,
    validationErrors,
    fetchList,
    fetchDetails,
    create,
    update,
    deleteItem,
  }
})
