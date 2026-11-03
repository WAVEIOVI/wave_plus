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
 * Attendance Store
 * Maps to /hr/attendances + /hr/time-clock endpoints.
 */
export const useAttendanceStore = defineStore('attendance', () => {
  const list        = ref([])
  const currentItem = ref(null)
  const totalItems  = ref(0)
  const loading     = ref(false)
  const error       = ref(null)
  const validationErrors = ref(null)
  const todayStatus = ref(null)

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

      const response = await $api('/hr/attendances', { method: 'GET', params: apiParams })

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

  const fetchOne = async id => {
    error.value = null
    try {
      const r = await $api(`/hr/attendances/${id}`, { method: 'GET' })

      currentItem.value = r.data || r

      return currentItem.value
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const fetchTodayStatus = async (employeeId = null) => {
    try {
      const params = employeeId ? { employee_id: employeeId } : {}
      const r = await $api('/hr/time-clock/today-status', { method: 'GET', params })

      todayStatus.value = r.data || r

      return todayStatus.value
    }
    catch (err) {
      if (!is401(err)) console.warn('[attendance] todayStatus failed:', err?.message)
    }
  }

  const clockIn = async payload => {
    error.value = null
    try {
      const r = await $api('/hr/time-clock/clock-in', { method: 'POST', body: payload || {} })

      await fetchTodayStatus()

      return r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const clockOut = async payload => {
    error.value = null
    try {
      const r = await $api('/hr/time-clock/clock-out', { method: 'POST', body: payload || {} })

      await fetchTodayStatus()

      return r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const scanBadge = async (badgeOrPayload, options = {}) => {
    error.value = null
    try {
      let rawBadge = badgeOrPayload
      let mergedOptions = { ...options }

      if (badgeOrPayload && typeof badgeOrPayload === 'object' && !Array.isArray(badgeOrPayload)) {
        if ('badge' in badgeOrPayload) {
          const { badge: nestedBadge, ...restPayload } = badgeOrPayload

          rawBadge = nestedBadge
          mergedOptions = { ...restPayload, ...mergedOptions }
        } else if ('value' in badgeOrPayload || 'rawValue' in badgeOrPayload || 'text' in badgeOrPayload) {
          rawBadge = badgeOrPayload.value ?? badgeOrPayload.rawValue ?? badgeOrPayload.text
        }
      }

      if (rawBadge && typeof rawBadge === 'object') {
        rawBadge = rawBadge.value ?? rawBadge.rawValue ?? rawBadge.text ?? ''
      }

      const normalizedBadge = rawBadge != null ? String(rawBadge).trim() : ''

      return await $api('/hr/time-clock/scan', {
        method: 'POST',
        body: { badge: normalizedBadge, source: 'qr', ...mergedOptions },
      })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const bulkPunch = async payload => {
    error.value = null
    try {
      return await $api('/hr/time-clock/bulk', { method: 'POST', body: payload })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const getBadge = async employeeId => {
    error.value = null
    try {
      const r = await $api(`/hr/time-clock/badge/${employeeId}`, { method: 'GET' })

      return r.data || r
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const requestCorrection = async (id, payload) => {
    error.value = null
    try {
      return await $api(`/hr/attendances/${id}/request-correction`, { method: 'POST', body: payload })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  const update = async (id, payload) => {
    error.value = null; validationErrors.value = null
    try {
      const r = await $api(`/hr/attendances/${id}`, { method: 'PUT', body: payload })

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
      return await $api(`/hr/attendances/${id}`, { method: 'DELETE' })
    }
    catch (err) {
      const e = extractApiError(err)

      error.value = e.message; throw err
    }
  }

  // ── Attendance Exceptions ──────────────────────────────────────────────
  const exceptionsList = ref([])
  const exceptionsTotal = ref(0)
  const exceptionsLoading = ref(false)
  const exceptionsError = ref(null)

  const fetchExceptions = async (params = {}) => {
    exceptionsLoading.value = true
    exceptionsError.value = null
    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }
      const response = await $api('/hr/attendance-exceptions', { method: 'GET', params: apiParams })

      exceptionsList.value = response.data || []
      exceptionsTotal.value = response.meta?.total ?? response.total ?? exceptionsList.value.length

      return response
    }
    catch (err) {
      const e = extractApiError(err)

      exceptionsError.value = e.message
    }
    finally { exceptionsLoading.value = false }
  }

  const acknowledgeException = async id => {
    return await $api(`/hr/attendance-exceptions/${id}/acknowledge`, { method: 'POST' })
  }

  const resolveException = async (id, notes = null) => {
    return await $api(`/hr/attendance-exceptions/${id}/resolve`, { method: 'POST', body: { notes } })
  }

  const dismissException = async (id, notes = null) => {
    return await $api(`/hr/attendance-exceptions/${id}/dismiss`, { method: 'POST', body: { notes } })
  }

  // ── Attendance Config ─────────────────────────────────────────────────────
  const config = ref(null)
  const configLoading = ref(false)
  const configError = ref(null)

  const fetchConfig = async () => {
    configLoading.value = true
    configError.value = null
    try {
      const response = await $api('/hr/attendance-config', { method: 'GET' })

      config.value = response.data || null

      return response
    }
    catch (err) {
      const e = extractApiError(err)

      configError.value = e.message
    }
    finally { configLoading.value = false }
  }

  const updateConfig = async payload => {
    configLoading.value = true
    configError.value = null
    try {
      const response = await $api('/hr/attendance-config', { method: 'PUT', body: payload })

      config.value = response.data || null

      return response
    }
    catch (err) {
      const e = extractApiError(err)

      configError.value = e.message
      throw err
    }
    finally { configLoading.value = false }
  }

  return {
    list, currentItem, totalItems, loading, error, validationErrors, todayStatus,
    fetchList, fetchOne, fetchTodayStatus, clockIn, clockOut, scanBadge, bulkPunch, getBadge,
    requestCorrection, update, remove,
    exceptionsList, exceptionsTotal, exceptionsLoading, exceptionsError,
    fetchExceptions, acknowledgeException, resolveException, dismissException,
    config, configLoading, configError,
    fetchConfig, updateConfig,
  }
})
