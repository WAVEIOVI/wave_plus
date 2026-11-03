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

export const useWorkTeamStore = defineStore('workTeam', () => {
  const list       = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const stats = ref({})
  const teamTypes = ref([])
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

      const response = await $api('/hr/teams', { method: 'GET', params: apiParams })

      list.value       = response.data || response || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length
      stats.value = response.stats || {}
      teamTypes.value = response.team_types || []

      return response
    }
    catch (err) {
      if (is401(err)) { list.value = []; totalItems.value = 0; stats.value = {}; teamTypes.value = []

        return }
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors
    }
    finally { loading.value = false }
  }

  const fetchOne = async id => {
    error.value = null
    try {
      const r = await $api(`/hr/teams/${id}`, { method: 'GET' })

      currentItem.value = r.data || r

      return currentItem.value
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const create = async payload => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api('/hr/teams', { method: 'POST', body: payload })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; validationErrors.value = e.errors; throw err
    }
  }

  const update = async (id, payload) => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api(`/hr/teams/${id}`, { method: 'PUT', body: payload })

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
      return await $api(`/hr/teams/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const addMember = async (teamId, payload) => {
    try {
      return await $api(`/hr/teams/${teamId}/members`, { method: 'POST', body: payload })
    }
    catch (err) {
      const e = extractApiError(err)

      throw Object.assign(err, { message: e.message })
    }
  }

  const removeMember = async (teamId, memberId) => {
    try {
      return await $api(`/hr/teams/${teamId}/members/${memberId}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      throw Object.assign(err, { message: e.message })
    }
  }

  return {
    list, currentItem, totalItems, stats, teamTypes, loading, error, validationErrors,
    fetchList, fetchOne, create, update, remove, addMember, removeMember,
  }
})
