import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { ref } from 'vue'

// Normalise API errors into { message, errors }, consistent with sibling stores.
const extractApiError = err => {
  const errors = err?.data?.errors || null

  const message = err?.data?.errors
    ? (err?.data?.message || 'Validation error')
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

export const useAppraisalStore = defineStore('appraisalStore', () => {
  // ── State ─────────────────────────────────────────────────────────────────
  const cycles = ref([])
  const appraisals = ref([])
  const kpis = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref({})

  // ── KPIs ──────────────────────────────────────────────────────────────────
  const fetchKpis = async (params = {}) => {
    try {
      const response = await $api('/hr/appraisals/kpis', {
        method: 'GET',
        params,
      })

      kpis.value = response.data || null

      return response
    } catch (err) {
      // Don't overwrite general error for background KPI fetch
      console.error('Failed to fetch appraisal KPIs', err)
    }
  }

  // ── Cycles ────────────────────────────────────────────────────────────────
  const fetchCycles = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/hr/appraisals/cycles', { method: 'GET' })

      cycles.value = response.data || []

      return response
    } catch (err) {
      error.value = extractApiError(err).message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createCycle = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = {}
    try {
      const response = await $api('/hr/appraisals/cycles', {
        method: 'POST',
        body: payload,
      })

      const newCycle = response.data || response

      cycles.value.unshift(newCycle)

      return response
    } catch (err) {
      const apiError = extractApiError(err)

      error.value = apiError.message
      validationErrors.value = apiError.errors || {}
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateCycle = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = {}
    try {
      const response = await $api(`/hr/appraisals/cycles/${id}`, {
        method: 'PUT',
        body: payload,
      })

      const updated = response.data || response
      const idx = cycles.value.findIndex(c => c.id === id)
      if (idx > -1) cycles.value[idx] = updated

      return response
    } catch (err) {
      const apiError = extractApiError(err)

      error.value = apiError.message
      validationErrors.value = apiError.errors || {}
      throw err
    } finally {
      loading.value = false
    }
  }

  const destroyCycle = async id => {
    loading.value = true
    error.value = null
    try {
      await $api(`/hr/appraisals/cycles/${id}`, { method: 'DELETE' })
      cycles.value = cycles.value.filter(c => c.id !== id)
    } catch (err) {
      error.value = extractApiError(err).message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Appraisals ────────────────────────────────────────────────────────────
  const fetchAppraisals = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/hr/appraisals', {
        method: 'GET',
        params,
      })

      appraisals.value = response.data || []

      return response
    } catch (err) {
      error.value = extractApiError(err).message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createAppraisal = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = {}
    try {
      const response = await $api('/hr/appraisals', {
        method: 'POST',
        body: payload,
      })

      const newAppraisal = response.data || response

      // Update or add to appraisals list
      const existingIndex = appraisals.value.findIndex(
        a => a.appraisal_cycle_id === newAppraisal.appraisal_cycle_id
          && a.employee_id === newAppraisal.employee_id,
      )

      if (existingIndex > -1) {
        appraisals.value[existingIndex] = newAppraisal
      } else {
        appraisals.value.unshift(newAppraisal)
      }

      return response
    } catch (err) {
      const apiError = extractApiError(err)

      error.value = apiError.message
      validationErrors.value = apiError.errors || {}
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateAppraisal = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = {}
    try {
      const response = await $api(`/hr/appraisals/${id}`, {
        method: 'PUT',
        body: payload,
      })

      const updated = response.data || response
      const idx = appraisals.value.findIndex(a => a.id === id)
      if (idx > -1) appraisals.value[idx] = updated

      return response
    } catch (err) {
      const apiError = extractApiError(err)

      error.value = apiError.message
      validationErrors.value = apiError.errors || {}
      throw err
    } finally {
      loading.value = false
    }
  }

  const acknowledgeAppraisal = async (id, payload = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = {}
    try {
      const response = await $api(`/hr/appraisals/${id}/acknowledge`, {
        method: 'POST',
        body: payload,
      })

      const updated = response.data || response
      const idx = appraisals.value.findIndex(a => a.id === id)
      if (idx > -1) appraisals.value[idx] = updated

      return response
    } catch (err) {
      const apiError = extractApiError(err)

      error.value = apiError.message
      validationErrors.value = apiError.errors || {}
      throw err
    } finally {
      loading.value = false
    }
  }

  const destroyAppraisal = async id => {
    loading.value = true
    error.value = null
    try {
      await $api(`/hr/appraisals/${id}`, { method: 'DELETE' })
      appraisals.value = appraisals.value.filter(a => a.id !== id)
    } catch (err) {
      error.value = extractApiError(err).message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Reset ─────────────────────────────────────────────────────────────────
  const resetErrors = () => {
    error.value = null
    validationErrors.value = {}
  }

  return {
    cycles,
    appraisals,
    kpis,
    loading,
    error,
    validationErrors,
    fetchKpis,
    fetchCycles,
    createCycle,
    updateCycle,
    destroyCycle,
    fetchAppraisals,
    createAppraisal,
    updateAppraisal,
    acknowledgeAppraisal,
    destroyAppraisal,
    resetErrors,
  }
})
