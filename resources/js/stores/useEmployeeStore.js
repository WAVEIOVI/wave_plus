/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

// ─────────────────────────────────────────────────────────────────────────────
// Private helpers
// ─────────────────────────────────────────────────────────────────────────────

const deepClone = data => JSON.parse(JSON.stringify(toRaw(data)))

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
// Store
// ─────────────────────────────────────────────────────────────────────────────

export const useEmployeeStore = defineStore('employee', () => {
  // ── State ──────────────────────────────────────────────────────────────────
  const list         = ref([])
  const currentItem  = ref(null)
  const totalItems   = ref(0)
  const loading      = ref(false)
  const error        = ref(null)
  const validationErrors = ref(null)

  // KPI / statistics
  const statistics   = ref({
    total: 0,
    active: 0,
    on_leave: 0,
    suspended: 0,
    terminated: 0,
  })

  const statsLoading = ref(false)

  // ── List ───────────────────────────────────────────────────────────────────

  /**
   * Fetch a paginated, filtered, sorted employee list.
   * Accepts all params from useListPage (page, per_page, sort_by, sort_order,
   * search, status, department_id, position_id, team_id).
   */
  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }

      // Normalise itemsPerPage → per_page
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/hr/employees', {
        method: 'GET',
        params: apiParams,
      })

      list.value       = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length

      return response
    }
    catch (err) {
      if (is401(err)) {
        list.value = []
        totalItems.value = 0

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    }
    finally {
      loading.value = false
    }
  }

  // ── Detail ─────────────────────────────────────────────────────────────────

  /** Fetch full employee detail (includes skills, certs, attendance, leave, etc.) */
  const fetchOne = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/employees/${id}`, { method: 'GET' })

      currentItem.value = response.data

      return response.data
    }
    catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  // ── Statistics (KPIs) ──────────────────────────────────────────────────────

  /**
   * Fetch headcount KPIs from the HR dashboard stats endpoint.
   * Non-critical — silently fails, never blocks the list.
   */
  const fetchStatistics = async () => {
    statsLoading.value = true
    try {
      // The HR dashboard stats endpoint returns headcount breakdown
      const response = await $api('/hr/dashboard-stats', { method: 'GET' })
      const headcount = response.data?.headcount || {}

      statistics.value = {
        total: headcount.total      ?? 0,
        active: headcount.active     ?? 0,
        on_leave: headcount.on_leave   ?? 0,
        suspended: headcount.suspended  ?? 0,
        terminated: headcount.terminated ?? 0,
      }
    }
    catch (err) {
      console.warn('[useEmployeeStore] Failed to fetch statistics:', err?.message)
    }
    finally {
      statsLoading.value = false
    }
  }

  // ── CRUD ───────────────────────────────────────────────────────────────────

  const create = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/hr/employees', {
        method: 'POST',
        body: payload,
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const update = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/employees/${id}`, {
        method: 'PUT',
        body: payload,
      })

      if (currentItem.value?.id === id) {
        currentItem.value = { ...deepClone(currentItem.value), ...(response.data || {}) }
      }

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const remove = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/employees/${id}`, { method: 'DELETE' })

      if (currentItem.value?.id === id) {
        currentItem.value = null
      }

      return response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ── Status Actions ─────────────────────────────────────────────────────────

  const changeStatus = async (id, status, reason = null, effectiveDate = null) => {
    error.value = null
    try {
      const body = { status }

      if (reason) body.reason = reason
      if (effectiveDate) body.effective_date = effectiveDate

      const response = await $api(`/hr/employees/${id}/change-status`, {
        method: 'POST',
        body,
      })

      // Optimistically update the item in the list
      const idx = list.value.findIndex(e => e.id === id)
      if (idx !== -1) list.value[idx] = { ...list.value[idx], status }

      if (currentItem.value?.id === id) {
        currentItem.value = { ...deepClone(currentItem.value), status }
      }

      return response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const activate   = id => changeStatus(id, 'active')
  const suspend    = (id, reason) => changeStatus(id, 'suspended', reason)
  const terminate  = (id, reason, effectiveDate) => changeStatus(id, 'terminated', reason, effectiveDate)

  // ── Photo Upload ───────────────────────────────────────────────────────────

  /**
   * Upload employee avatar.
   * Uses the existing storeMedia endpoint with collection='avatar'.
   * @param {string} id
   * @param {File|Blob} file
   */
  const uploadPhoto = async (id, fileOrPayload) => {
    error.value = null

    const rawFile = fileOrPayload instanceof File || fileOrPayload instanceof Blob
      ? fileOrPayload
      : (fileOrPayload?.file || fileOrPayload)

    if (!rawFile || typeof rawFile === 'string' || !(rawFile instanceof Blob)) {
      throw new Error('Invalid file provided for photo upload')
    }

    const filename = rawFile.name || 'avatar.jpg'
    const formData = new FormData()

    formData.append('file', rawFile, filename)
    formData.append('collection', 'avatar')

    try {
      const response = await $api(`/hr/employees/${id}/media`, {
        method: 'POST',
        body: formData,
      })

      // Refresh the current item's avatar_url if we're viewing it
      if (currentItem.value?.id === id && response.media?.url) {
        currentItem.value = {
          ...deepClone(currentItem.value),
          avatar_url: response.media.url,
        }
      }

      return response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  // ── Document Upload ────────────────────────────────────────────────────────

  const uploadDocument = async (id, fileOrPayload) => {
    error.value = null

    const rawFile = fileOrPayload instanceof File || fileOrPayload instanceof Blob
      ? fileOrPayload
      : (fileOrPayload?.file || fileOrPayload)

    if (!rawFile || typeof rawFile === 'string' || !(rawFile instanceof Blob)) {
      throw new Error('Invalid file provided for document upload')
    }

    const filename = rawFile.name || (fileOrPayload?.title ? `${fileOrPayload.title}.pdf` : 'document.pdf')
    const formData = new FormData()

    formData.append('file', rawFile, filename)
    formData.append('collection', 'documents')

    try {
      return await $api(`/hr/employees/${id}/media`, {
        method: 'POST',
        body: formData,
      })
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  // ── Skills & Certifications Actions ────────────────────────────────────────

  const assignSkill = async (employeeId, payload) => {
    error.value = null
    try {
      const response = await $api(`/hr/employees/${employeeId}/skills`, {
        method: 'POST',
        body: payload,
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const verifySkill = async empSkillId => {
    error.value = null
    try {
      const response = await $api(`/hr/employee-skills/${empSkillId}/verify`, {
        method: 'PUT',
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const assignCertification = async (employeeId, formData) => {
    error.value = null
    try {
      const response = await $api(`/hr/employees/${employeeId}/certifications`, {
        method: 'POST',
        body: formData,
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const verifyCertification = async empCertId => {
    error.value = null
    try {
      const response = await $api(`/hr/employee-certifications/${empCertId}/verify`, {
        method: 'PUT',
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  const fetchBadge = async employeeId => {
    error.value = null
    try {
      const response = await $api(`/hr/time-clock/badge/${employeeId}`, {
        method: 'GET',
      })

      return response.data || response
    }
    catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    }
  }

  // ── Public API ─────────────────────────────────────────────────────────────
  return {
    // State
    list,
    currentItem,
    totalItems,
    loading,
    error,
    validationErrors,
    statistics,
    statsLoading,

    // Actions
    fetchList,
    fetchOne,
    fetchStatistics,
    create,
    update,
    remove,
    changeStatus,
    activate,
    suspend,
    terminate,
    uploadPhoto,
    uploadDocument,
    assignSkill,
    verifySkill,
    assignCertification,
    verifyCertification,
    fetchBadge,
  }
})
