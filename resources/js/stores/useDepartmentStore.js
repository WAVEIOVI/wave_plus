/* eslint-disable camelcase */
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

const is401 = err =>
  err?.response?.status === 401 || err?.statusCode === 401

// ─────────────────────────────────────────────────────────────────────────────
// Department Store
// ─────────────────────────────────────────────────────────────────────────────
export const useDepartmentStore = defineStore('department', () => {
  const list       = ref([])
  const totalItems = ref(0)
  const stats      = ref({})
  const loading    = ref(false)
  const error      = ref(null)
  const validationErrors = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value   = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }

      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/hr/departments', { method: 'GET', params: apiParams })

      list.value       = response.data || response || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length
      stats.value      = response.stats || {}

      return response
    }
    catch (err) {
      if (is401(err)) { list.value = []; totalItems.value = 0; stats.value = {}

        return }
      const e = extractApiError(err)

      error.value = e.message
      validationErrors.value = e.errors
    }
    finally { loading.value = false }
  }

  const fetchOne = async id => {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/hr/departments/${id}`, { method: 'GET' })

      return response.data || response
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message
      throw err
    }
    finally { loading.value = false }
  }

  const create = async payload => {
    error.value = null
    validationErrors.value = null
    try {
      const r = await $api('/hr/departments', { method: 'POST', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const update = async (id, payload) => {
    error.value = null
    validationErrors.value = null
    try {
      const r = await $api(`/hr/departments/${id}`, { method: 'PUT', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const remove = async id => {
    error.value = null
    try {
      return await $api(`/hr/departments/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  return { list, totalItems, stats, loading, error, validationErrors, fetchList, fetchOne, create, update, remove }
})

// ─────────────────────────────────────────────────────────────────────────────
// Position Store
// ─────────────────────────────────────────────────────────────────────────────
export const usePositionStore = defineStore('position', () => {
  const list       = ref([])
  const totalItems = ref(0)
  const stats      = ref({})
  const loading    = ref(false)
  const error      = ref(null)
  const validationErrors = ref(null)

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value   = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }

      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/hr/positions', { method: 'GET', params: apiParams })

      list.value       = response.data || response || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length
      stats.value      = response.stats || {}

      return response
    }
    catch (err) {
      if (is401(err)) { list.value = []; totalItems.value = 0; stats.value = {}

        return }
      const e = extractApiError(err)

      error.value = e.message
      validationErrors.value = e.errors
    }
    finally { loading.value = false }
  }

  const fetchOne = async id => {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/hr/positions/${id}`, { method: 'GET' })

      return response.data || response
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message
      throw err
    }
    finally { loading.value = false }
  }

  const create = async payload => {
    error.value = null
    validationErrors.value = null
    try {
      const r = await $api('/hr/positions', { method: 'POST', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const update = async (id, payload) => {
    error.value = null
    validationErrors.value = null
    try {
      const r = await $api(`/hr/positions/${id}`, { method: 'PUT', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const remove = async id => {
    error.value = null
    try {
      return await $api(`/hr/positions/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  return { list, totalItems, stats, loading, error, validationErrors, fetchList, fetchOne, create, update, remove }
})
