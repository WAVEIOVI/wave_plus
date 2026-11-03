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

const is401 = err => err?.response?.status === 401 || err?.statusCode === 401

/**
 * Recruitment Store
 * Maps to /hr/recruitment/* endpoints.
 * Candidate pipeline API. Candidate status is the authoritative pipeline stage.
 */
export const useRecruitmentStore = defineStore('recruitment', () => {
  const list        = ref([])
  const totalItems  = ref(0)
  const loading     = ref(false)
  const error       = ref(null)
  const validationErrors = ref(null)

  const fetchOpenings = async () => {
    const response = await $api('/hr/recruitment/openings', { method: 'GET' })

    return response.data || []
  }

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

      if (apiParams.stage) {
        apiParams.status = apiParams.stage
        delete apiParams.stage
      }
      delete apiParams.position_id

      const response = await $api('/hr/recruitment/candidates', { method: 'GET', params: apiParams })

      list.value       = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length

      return response
    }
    catch (err) {
      if (is401(err)) { list.value = []; totalItems.value = 0 

        return }
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors
    }
    finally { loading.value = false }
  }

  const create = async payload => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api('/hr/recruitment/candidates', { method: 'POST', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const updateStage = async (id, stage, notes = null) => {
    error.value = null
    try {
      return await $api(`/hr/recruitment/candidates/${id}/stage`, {
        method: 'PATCH',
        body: { status: stage, notes },
      })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const scheduleInterview = async (id, payload) => {
    const response = await $api(`/hr/recruitment/candidates/${id}/interviews`, {
      method: 'POST',
      body: payload,
    })

    return response.data || response
  }

  const hire = async (id, payload) => {
    const response = await $api(`/hr/recruitment/candidates/${id}/hire`, {
      method: 'POST',
      body: payload,
    })

    return response.data || response
  }

  const update = async (id, payload) => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api(`/hr/recruitment/candidates/${id}`, { method: 'PUT', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const destroy = async id => {
    error.value = null
    try {
      return await $api(`/hr/recruitment/candidates/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const createOpening = async payload => {
    error.value = null
    try {
      const r = await $api('/hr/recruitment/openings', { method: 'POST', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const updateOpening = async (id, payload) => {
    error.value = null
    try {
      const r = await $api(`/hr/recruitment/openings/${id}`, { method: 'PUT', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const destroyOpening = async id => {
    error.value = null
    try {
      return await $api(`/hr/recruitment/openings/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  return {
    list, totalItems, loading, error, validationErrors,
    fetchList, fetchOpenings, create, update, destroy, updateStage, scheduleInterview, hire,
    createOpening, updateOpening, destroyOpening,
  }
})
