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
 * Leave Request Store
 * Maps to /hr/leave-requests and /hr/leave-balances endpoints.
 */
export const useLeaveRequestStore = defineStore('leaveRequest', () => {
  const list        = ref([])
  const currentItem = ref(null)
  const totalItems  = ref(0)
  const loading     = ref(false)
  const error       = ref(null)
  const validationErrors = ref(null)
  const myBalances  = ref([])
  const balances    = ref([])
  const stats       = ref({})

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

      const response = await $api('/hr/leave-requests', { method: 'GET', params: apiParams })

      list.value       = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length
      stats.value      = response.meta?.stats || response.stats || {}

      return response
    }
    catch (err) {
      if (is401(err)) {
        list.value = []
        totalItems.value = 0
        stats.value = {}

        return
      }
      const e = extractApiError(err)

      error.value = e.message
      validationErrors.value = e.errors
    }
    finally { loading.value = false }
  }

  const fetchOne = async id => {
    error.value = null
    try {
      const r = await $api(`/hr/leave-requests/${id}`, { method: 'GET' })

      currentItem.value = r.data || r

      return currentItem.value
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message
      throw err
    }
  }

  const fetchMyBalances = async () => {
    try {
      const r = await $api('/hr/leave-balances', { method: 'GET' })

      myBalances.value = r.data || r

      return myBalances.value
    }
    catch (err) {
      console.warn('[leaveRequest] fetchMyBalances failed:', err?.message)
    }
  }

  const fetchBalances = async (params = {}) => {
    try {
      const r = await $api('/hr/leave-balances', { method: 'GET', params })

      balances.value = r.data || r

      return balances.value
    }
    catch (err) {
      console.warn('[leaveRequest] fetchBalances failed:', err?.message)
      throw err
    }
  }

  const fetchCalendar = async (params = {}) => {
    try {
      const r = await $api('/hr/leave-calendar', { method: 'GET', params })

      return r.data || r
    }
    catch (err) {
      console.warn('[leaveRequest] fetchCalendar failed:', err?.message)
      throw err
    }
  }

  const create = async payload => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api('/hr/leave-requests', { method: 'POST', body: payload })

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
      const r = await $api(`/hr/leave-requests/${id}`, { method: 'PUT', body: payload })

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
      return await $api(`/hr/leave-requests/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const approve = async (id, notes = null) => {
    error.value = null
    try {
      return await $api(`/hr/leave-requests/${id}/approve`, {
        method: 'POST',
        body: notes ? { decision_note: notes, notes } : {},
      })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const reject = async (id, reason) => {
    error.value = null
    try {
      return await $api(`/hr/leave-requests/${id}/reject`, {
        method: 'POST',
        body: { rejection_reason: reason, reason },
      })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const cancel = async id => {
    error.value = null
    try {
      return await $api(`/hr/leave-requests/${id}/cancel`, { method: 'POST' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const leaveTypes = ref([])
  const leavePolicies = ref([])

  const fetchLeaveTypes = async (params = {}) => {
    try {
      const r = await $api('/hr/leave-types', { method: 'GET', params })

      leaveTypes.value = r.data || r

      return leaveTypes.value
    } catch (err) {
      console.warn('[leaveRequest] fetchLeaveTypes failed:', err?.message)
      throw err
    }
  }

  const createLeaveType = async payload => {
    const r = await $api('/hr/leave-types', { method: 'POST', body: payload })

    await fetchLeaveTypes({ all: true })

    return r.data || r
  }

  const updateLeaveType = async (id, payload) => {
    const r = await $api(`/hr/leave-types/${id}`, { method: 'PUT', body: payload })

    await fetchLeaveTypes({ all: true })

    return r.data || r
  }

  const destroyLeaveType = async id => {
    await $api(`/hr/leave-types/${id}`, { method: 'DELETE' })
    await fetchLeaveTypes({ all: true })
  }

  const fetchLeavePolicies = async (params = {}) => {
    try {
      const r = await $api('/hr/leave-policies', { method: 'GET', params })

      leavePolicies.value = r.data || r

      return leavePolicies.value
    } catch (err) {
      console.warn('[leaveRequest] fetchLeavePolicies failed:', err?.message)
      throw err
    }
  }

  const createLeavePolicy = async payload => {
    const r = await $api('/hr/leave-policies', { method: 'POST', body: payload })

    await fetchLeavePolicies()

    return r.data || r
  }

  const updateLeavePolicy = async (id, payload) => {
    const r = await $api(`/hr/leave-policies/${id}`, { method: 'PUT', body: payload })

    await fetchLeavePolicies()

    return r.data || r
  }

  const destroyLeavePolicy = async id => {
    await $api(`/hr/leave-policies/${id}`, { method: 'DELETE' })
    await fetchLeavePolicies()
  }

  const adjustBalance = async payload => {
    const r = await $api('/hr/leave-balances/adjust', { method: 'POST', body: payload })

    return r.data || r
  }

  return {
    list, currentItem, totalItems, loading, error, validationErrors, myBalances,
    balances, stats, leaveTypes, leavePolicies,
    fetchList, fetchOne, fetchMyBalances, fetchBalances, fetchCalendar,
    create, update, remove, approve, reject, cancel,
    fetchLeaveTypes, createLeaveType, updateLeaveType, destroyLeaveType,
    fetchLeavePolicies, createLeavePolicy, updateLeavePolicy, destroyLeavePolicy,
    adjustBalance,
  }
})
