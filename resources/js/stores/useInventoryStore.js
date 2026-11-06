import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

// ──────────────────────────────────────────────────
// PRIVATE HELPERS
// ──────────────────────────────────────────────────

/**
 * Extracts error message and validation errors from an API response.
 * @param {any} err - The error object
 * @returns {{ message: string, errors: object|null }}
 */
const extractApiError = err => {
  // Fix || precedence bug: check err?.data?.errors FIRST, then fall back to err?.data?.message
  const errors = err?.data?.errors || null

  const message = err?.data?.errors 
    ? (err?.data?.message || 'Validation error') 
    : (err?.data?.message || err?.message || 'An error occurred')

  return { message, errors }
}

/**
 * Checks if the error is a 401 Unauthorized.
 * @param {any} err - The error object
 * @returns {boolean}
 */
const is401 = err => {
  return err?.response?.status === 401 || err?.statusCode === 401
}

export const useInventoryStore = defineStore('inventory', () => {
  // ──────────────────────────────────────────────────
  // STATE
  // ──────────────────────────────────────────────────

  // ── Stock Items ──────────────────────────────────────────────
  const stockItems = ref([])
  const totalStockItems = ref(0)
  const stockItem = ref(null)

  // ── Dashboard KPIs ───────────────────────────────────────────
  const dashboardStats = ref(null)
  const dashboardLoading = ref(false)

  // ── Stock Movements ─────────────────────────────────────────
  const movements = ref([])
  const totalMovements = ref(0)

  // ── Warehouse Sites ─────────────────────────────────────────
  const sites = ref([])
  const totalSites = ref(0)
  const site = ref(null)

  // ── Warehouse Zones ─────────────────────────────────────────
  const zones = ref([])
  const totalZones = ref(0)

  // ── Warehouse Locations ─────────────────────────────────────
  const locations = ref([])
  const totalLocations = ref(0)

  // ── Stock Lots ──────────────────────────────────────────────
  const lots = ref([])
  const totalLots = ref(0)

  // ── UI State ────────────────────────────────────────────────
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)

  // ──────────────────────────────────────────────────
  // ACTIONS
  // ──────────────────────────────────────────────────

  // ─── Stock Items ─────────────────────────────────────────────
  const fetchStockItems = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/stock-items', { method: 'GET', params: apiParams })

      stockItems.value = response.data || []
      totalStockItems.value = response.meta?.total ?? response.total ?? stockItems.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        stockItems.value = []
        totalStockItems.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const fetchStockItem = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/stock-items/${id}`, { method: 'GET' })

      stockItem.value = response.data
      
      return response.data
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  // ─── Dashboard Stats ────────────────────────────────────────
  const fetchDashboardStats = async () => {
    dashboardLoading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/dashboard-stats', { method: 'GET' })

      dashboardStats.value = response.data || null
      
      return response
    } catch (err) {
      if (is401(err)) {
        dashboardStats.value = null
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      dashboardLoading.value = false
    }
  }

  // ─── Stock Movements ────────────────────────────────────────
  const fetchMovements = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/movements', { method: 'GET', params: apiParams })

      movements.value = response.data || []
      totalMovements.value = response.meta?.total ?? response.total ?? movements.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        movements.value = []
        totalMovements.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const createAdjustment = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/movements/adjustment', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const createTransfer = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/movements/transfer', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const createInitialLoad = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/movements/initial-load', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ─── Warehouse Sites ────────────────────────────────────────
  const fetchSites = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/sites', { method: 'GET', params: apiParams })

      sites.value = response.data || []
      totalSites.value = response.meta?.total ?? response.total ?? sites.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        sites.value = []
        totalSites.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const fetchSite = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/sites/${id}`, { method: 'GET' })

      site.value = response.data
      
      return response.data
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  const createSite = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/sites', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const updateSite = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/sites/${id}`, {
        method: 'PUT',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const deleteSite = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/sites/${id}`, { method: 'DELETE' })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ─── Warehouse Zones ────────────────────────────────────────
  const fetchZones = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/zones', { method: 'GET', params: apiParams })

      zones.value = response.data || []
      totalZones.value = response.meta?.total ?? response.total ?? zones.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        zones.value = []
        totalZones.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const createZone = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/zones', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const updateZone = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/zones/${id}`, {
        method: 'PUT',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const deleteZone = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/zones/${id}`, { method: 'DELETE' })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ─── Warehouse Locations ────────────────────────────────────
  const fetchLocations = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/locations', { method: 'GET', params: apiParams })

      locations.value = response.data || []
      totalLocations.value = response.meta?.total ?? response.total ?? locations.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        locations.value = []
        totalLocations.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const createLocation = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/locations', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const updateLocation = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/locations/${id}`, {
        method: 'PUT',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const deleteLocation = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/locations/${id}`, { method: 'DELETE' })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const toggleLocationActive = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/locations/${id}/toggle-active`, {
        method: 'PATCH',
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ─── Stock Lots ─────────────────────────────────────────────
  const fetchLots = async (params = {}) => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }

      const response = await $api('/inventory/lots', { method: 'GET', params: apiParams })

      lots.value = response.data || []
      totalLots.value = response.meta?.total ?? response.total ?? lots.value.length

      return response
    } catch (err) {
      if (is401(err)) {
        lots.value = []
        totalLots.value = 0
        
        return
      }
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
    } finally {
      loading.value = false
    }
  }

  const fetchLot = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/lots/${id}`, { method: 'GET' })

      return response.data
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  const createLot = async payload => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/inventory/lots', {
        method: 'POST',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const updateLot = async (id, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/lots/${id}`, {
        method: 'PUT',
        body: payload,
      })

      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const deleteLot = async id => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/lots/${id}`, { method: 'DELETE' })
      
      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  // ─── Mobile Warehouse Actions ────────────────────────────────
  const linkVehicle = async (siteId, payload) => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/sites/${siteId}/link-vehicle`, {
        method: 'POST',
        body: payload,
      })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const unlinkVehicle = async siteId => {
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api(`/inventory/sites/${siteId}/unlink-vehicle`, {
        method: 'POST',
      })

      return response.data || response
    } catch (err) {
      const extracted = extractApiError(err)

      error.value = extracted.message
      validationErrors.value = extracted.errors
      throw err
    }
  }

  const fetchSiteAssignmentHistory = async (siteId, params = {}) => {
    error.value = null
    validationErrors.value = null

    try {
      /* eslint-disable camelcase */
      const apiParams = { ...params }
      if (apiParams.itemsPerPage !== undefined) {
        apiParams.per_page = apiParams.itemsPerPage
        delete apiParams.itemsPerPage
      }
      /* eslint-enable camelcase */

      return await $api(`/inventory/sites/${siteId}/assignment-history`, {
        method: 'GET',
        params: apiParams,
      })
    } catch (err) {
      if (!is401(err)) {
        const extracted = extractApiError(err)

        error.value = extracted.message
        validationErrors.value = extracted.errors
      }
      throw err
    }
  }

  return {
    stockItems,
    totalStockItems,
    stockItem,
    dashboardStats,
    dashboardLoading,
    movements,
    totalMovements,
    sites,
    totalSites,
    site,
    zones,
    totalZones,
    locations,
    totalLocations,
    lots,
    totalLots,
    loading,
    error,
    validationErrors,
    fetchStockItems,
    fetchStockItem,
    fetchDashboardStats,
    fetchMovements,
    createAdjustment,
    createTransfer,
    createInitialLoad,
    fetchSites,
    fetchSite,
    createSite,
    updateSite,
    deleteSite,
    fetchZones,
    createZone,
    updateZone,
    deleteZone,
    fetchLocations,
    createLocation,
    updateLocation,
    deleteLocation,
    toggleLocationActive,
    fetchLots,
    fetchLot,
    createLot,
    updateLot,
    deleteLot,
    linkVehicle,
    unlinkVehicle,
    fetchSiteAssignmentHistory,
  }
})
