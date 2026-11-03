import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

const deepClone = data => {
  if (data instanceof FormData) return data
  if (data === null || data === undefined) return data
  return JSON.parse(JSON.stringify(toRaw(data)))
}

const extractApiError = err => {
  const errors = err?.data?.errors || null

  const message = err?.data?.errors 
    ? (err?.data?.message || 'Validation error') 
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

const is401 = err => {
  return err?.response?.status === 401 || err?.statusCode === 401
}

export const useVehicleStore = defineStore('vehicle', () => {
  // ── State ──────────────────────────────────────────────
  const list = ref([])
  const currentItem = ref(null)
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)
  const activityLog = ref([])
  const utilizationReport = ref(null)
  const maintenanceAlerts = ref([])
  const documentAlerts = ref([])
  const documents = ref([])
  const documentsMeta = ref(null)
  const fuelRecords = ref([])
  const fuelRecordsMeta = ref(null)
  const maintenanceRecords = ref([])
  const maintenanceRecordsMeta = ref(null)
  const checklists = ref([])
  const checklistsMeta = ref(null)
  const assignments = ref([])
  const assignmentsMeta = ref(null)

  // ── Actions ────────────────────────────────────────────

  const fetchList = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/hr/fleet/vehicles', {
        method: 'GET',
        params: apiParams,
      })

      list.value = response.data || []
      totalItems.value = response.meta?.total ?? response.total ?? list.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        list.value = []
        totalItems.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const fetchById = async id => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}`, {
        method: 'GET',
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        currentItem.value = null
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
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
      const response = await $api('/hr/fleet/vehicles', {
        method: 'POST',
        body: deepClone(payload),
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
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
      const response = await $api(`/hr/fleet/vehicles/${id}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      currentItem.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async id => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}`, {
        method: 'DELETE',
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Vehicle Operations ─────────────────────────────────

  const assignVehicle = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/assign`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const returnVehicle = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/return`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateOdometer = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/odometer`, {
        method: 'PATCH',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateStatus = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/status`, {
        method: 'PATCH',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Rental Operations ──────────────────────────────────

  const startRental = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/rental/start`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const endRental = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/rental/end`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Client Vehicle Operations ──────────────────────────

  const startClientUsage = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/client-usage/start`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const endClientUsage = async (id, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/client-usage/end`, {
        method: 'POST',
        body: deepClone(payload),
      })

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Reporting & Analytics ──────────────────────────────

  const fetchActivityLog = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/activity-log`, {
        method: 'GET',
      })

      activityLog.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        activityLog.value = []
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchUtilization = async (id, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${id}/utilization`, {
        method: 'GET',
        params,
      })

      utilizationReport.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        utilizationReport.value = null
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  const fetchAlerts = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/hr/fleet/vehicles/alerts', {
        method: 'GET',
        params,
      })

      const rawData = response?.data || {}

      maintenanceAlerts.value = Array.isArray(rawData.maintenance) ? rawData.maintenance : []
      documentAlerts.value = Array.isArray(rawData.documents) ? rawData.documents : []

      return {
        maintenance: maintenanceAlerts.value,
        documents: documentAlerts.value,
      }
    } catch (err) {
      if (is401(err)) {
        maintenanceAlerts.value = []
        documentAlerts.value = []

        return { maintenance: [], documents: [] }
      }
      const extracted = extractApiError(err)

      error.value = extracted.message

      return { maintenance: [], documents: [] }
    } finally {
      loading.value = false
    }
  }

  const fetchMaintenanceAlerts = async (params = {}) => {
    const res = await fetchAlerts(params)

    return res.maintenance
  }

  const fetchDocumentAlerts = async (params = {}) => {
    const res = await fetchAlerts(params)

    return res.documents
  }

  // ── Child Resources: Documents ─────────────────────────

  const fetchDocuments = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/documents`, {
        method: 'GET',
        params: apiParams,
      })

      documents.value = response.data || []
      documentsMeta.value = response.meta || null

      return response
    } catch (err) {
      if (is401(err)) {
        documents.value = []

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createDocument = async (vehicleId, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/documents`, {
        method: 'POST',
        body: payload instanceof FormData ? payload : deepClone(payload),
      })

      await fetchDocuments(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateDocument = async (documentId, payload, vehicleId = null) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const isFormData = payload instanceof FormData
      let body = payload
      let method = 'PUT'

      if (isFormData) {
        if (!payload.has('_method')) {
          payload.append('_method', 'PUT')
        }
        method = 'POST'
      } else {
        body = deepClone(payload)
      }

      const response = await $api(`/hr/fleet/documents/${documentId}`, {
        method,
        body,
      })

      if (vehicleId) await fetchDocuments(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const downloadDocument = (documentId) => {
    const url = `/api/hr/fleet/documents/${documentId}/download`
    window.open(url, '_blank')
  }

  const deleteDocument = async (documentId, vehicleId = null) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/documents/${documentId}`, {
        method: 'DELETE',
      })

      if (vehicleId) await fetchDocuments(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Child Resources: Fuel Records ──────────────────────

  const fetchFuelRecords = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/fuel-records`, {
        method: 'GET',
        params: apiParams,
      })

      fuelRecords.value = response.data || []
      fuelRecordsMeta.value = response.meta || null

      return response
    } catch (err) {
      if (is401(err)) {
        fuelRecords.value = []

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createFuelRecord = async (vehicleId, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/fuel-records`, {
        method: 'POST',
        body: deepClone(payload),
      })

      await fetchFuelRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateFuelRecord = async (fuelRecordId, payload, vehicleId = null) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/fuel-records/${fuelRecordId}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      if (vehicleId) await fetchFuelRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteFuelRecord = async (fuelRecordId, vehicleId = null) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/fuel-records/${fuelRecordId}`, {
        method: 'DELETE',
      })

      if (vehicleId) await fetchFuelRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Child Resources: Maintenance Records ───────────────

  const fetchMaintenanceRecords = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/maintenance-records`, {
        method: 'GET',
        params: apiParams,
      })

      maintenanceRecords.value = response.data || []
      maintenanceRecordsMeta.value = response.meta || null

      return response
    } catch (err) {
      if (is401(err)) {
        maintenanceRecords.value = []

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createMaintenanceRecord = async (vehicleId, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/maintenance-records`, {
        method: 'POST',
        body: deepClone(payload),
      })

      await fetchMaintenanceRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateMaintenanceRecord = async (maintenanceRecordId, payload, vehicleId = null) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/maintenance-records/${maintenanceRecordId}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      if (vehicleId) await fetchMaintenanceRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteMaintenanceRecord = async (maintenanceRecordId, vehicleId = null) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/maintenance-records/${maintenanceRecordId}`, {
        method: 'DELETE',
      })

      if (vehicleId) await fetchMaintenanceRecords(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Child Resources: Checklists ────────────────────────

  const fetchChecklists = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/checklists`, {
        method: 'GET',
        params: apiParams,
      })

      checklists.value = response.data || []
      checklistsMeta.value = response.meta || null

      return response
    } catch (err) {
      if (is401(err)) {
        checklists.value = []

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  const createChecklist = async (vehicleId, payload) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/checklists`, {
        method: 'POST',
        body: deepClone(payload),
      })

      await fetchChecklists(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateChecklist = async (checklistId, payload, vehicleId = null) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/hr/fleet/checklists/${checklistId}`, {
        method: 'PUT',
        body: deepClone(payload),
      })

      if (vehicleId) await fetchChecklists(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteChecklist = async (checklistId, vehicleId = null) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/hr/fleet/checklists/${checklistId}`, {
        method: 'DELETE',
      })

      if (vehicleId) await fetchChecklists(vehicleId)

      return response.data
    } catch (err) {
      if (is401(err)) return
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Child Resources: Assignments ───────────────────────

  const fetchAssignments = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams['per_page'] = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api(`/hr/fleet/vehicles/${vehicleId}/assignments`, {
        method: 'GET',
        params: apiParams,
      })

      assignments.value = response.data || []
      assignmentsMeta.value = response.meta || null

      return response
    } catch (err) {
      if (is401(err)) {
        assignments.value = []

        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      throw err
    } finally {
      loading.value = false
    }
  }

  // ── Reset & Utilities ──────────────────────────────────

  const resetItem = () => {
    currentItem.value = null
    error.value = null
    validationErrors.value = null
  }

  const resetList = () => {
    list.value = []
    totalItems.value = 0
    error.value = null
    validationErrors.value = null
  }

  return {
    // State
    list,
    currentItem,
    totalItems,
    loading,
    error,
    validationErrors,
    activityLog,
    utilizationReport,
    maintenanceAlerts,
    documentAlerts,
    documents,
    documentsMeta,
    fuelRecords,
    fuelRecordsMeta,
    maintenanceRecords,
    maintenanceRecordsMeta,
    checklists,
    checklistsMeta,
    assignments,
    assignmentsMeta,

    // Actions
    fetchList,
    fetchById,
    create,
    update,
    remove,
    assignVehicle,
    returnVehicle,
    updateOdometer,
    updateStatus,
    startRental,
    endRental,
    startClientUsage,
    endClientUsage,
    fetchActivityLog,
    fetchUtilization,
    fetchAlerts,
    fetchMaintenanceAlerts,
    fetchDocumentAlerts,
    fetchDocuments,
    createDocument,
    updateDocument,
    deleteDocument,
    downloadDocument,
    fetchFuelRecords,
    createFuelRecord,
    updateFuelRecord,
    deleteFuelRecord,
    fetchMaintenanceRecords,
    createMaintenanceRecord,
    updateMaintenanceRecord,
    deleteMaintenanceRecord,
    fetchChecklists,
    createChecklist,
    updateChecklist,
    deleteChecklist,
    fetchAssignments,
    resetItem,
    resetList,
  }
})
