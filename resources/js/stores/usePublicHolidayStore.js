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

export const usePublicHolidayStore = defineStore('publicHoliday', () => {
  const holidays = ref([])
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  const fetchHolidays = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/hr/public-holidays', {
        method: 'GET',
        params,
      })

      holidays.value = response.data || []

      return holidays.value
    } catch (err) {
      const e = extractApiError(err)
      error.value = e.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createHoliday = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api('/hr/public-holidays', {
        method: 'POST',
        body: payload,
      })

      await fetchHolidays()

      return response.data || response
    } catch (err) {
      const e = extractApiError(err)
      error.value = e.message
      validationErrors.value = e.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateHoliday = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null
    try {
      const response = await $api(`/hr/public-holidays/${id}`, {
        method: 'PUT',
        body: payload,
      })

      await fetchHolidays()

      return response.data || response
    } catch (err) {
      const e = extractApiError(err)
      error.value = e.message
      validationErrors.value = e.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const destroyHoliday = async id => {
    loading.value = true
    error.value = null
    try {
      await $api(`/hr/public-holidays/${id}`, {
        method: 'DELETE',
      })

      await fetchHolidays()
    } catch (err) {
      const e = extractApiError(err)
      error.value = e.message
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    holidays,
    loading,
    error,
    validationErrors,
    fetchHolidays,
    createHoliday,
    updateHoliday,
    destroyHoliday,
  }
})
