import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref, toRaw } from 'vue'

const deepClone = data => JSON.parse(JSON.stringify(toRaw(data)))

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

export const useResourceValidationStore = defineStore('resourceValidation', () => {
  // ── State ──────────────────────────────────────────────
  const loading = ref(false)
  const error = ref(null)
  const validationErrors = ref(null)
  const dwoValidationResult = ref(null)
  const swoValidationResult = ref(null)
  const vehicleValidationResult = ref(null)
  const driverValidationResult = ref(null)
  const compatibilityResult = ref(null)
  const availableDriversForVehicle = ref([])
  const availableVehiclesForDriver = ref([])

  // ── Actions ────────────────────────────────────────────

  /**
   * Validate DWO resources (vehicle, driver, mobile warehouse, delivery provider)
   * @param {Object} payload - DWO resource data to validate
   * @returns {Promise<Object>} Validation result with valid flag and issues
   */
  const validateDwo = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/resource-validation/validate-dwo', {
        method: 'POST',
        body: deepClone(payload),
      })

      dwoValidationResult.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        dwoValidationResult.value = null
        
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

  /**
   * Validate SWO resources (vehicle, driver, mobile warehouse, workshop unit)
   * @param {Object} payload - SWO resource data to validate
   * @returns {Promise<Object>} Validation result with valid flag and issues
   */
  const validateSwo = async payload => {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await $api('/resource-validation/validate-swo', {
        method: 'POST',
        body: deepClone(payload),
      })

      swoValidationResult.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        swoValidationResult.value = null
        
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

  /**
   * Validate vehicle availability for a specific date
   * @param {String} vehicleId - Vehicle ULID
   * @param {Object} params - Validation parameters (date, check_maintenance, check_documents)
   * @returns {Promise<Object>} Vehicle validation result
   */
  const validateVehicle = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/resource-validation/vehicles/${vehicleId}/validate`, {
        method: 'GET',
        params,
      })

      vehicleValidationResult.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        vehicleValidationResult.value = null
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Validate driver authorization status
   * @param {String} driverId - Approved Driver ULID
   * @param {Object} params - Validation parameters (vehicle_type, weight_class)
   * @returns {Promise<Object>} Driver validation result
   */
  const validateDriver = async (driverId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/resource-validation/drivers/${driverId}/validate`, {
        method: 'GET',
        params,
      })

      driverValidationResult.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        driverValidationResult.value = null
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Validate vehicle-driver compatibility
   * @param {String} vehicleId - Vehicle ULID
   * @param {String} driverId - Approved Driver ULID
   * @returns {Promise<Object>} Compatibility result with compatible flag and issues
   */
  const validateCompatibility = async (vehicleId, driverId) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/resource-validation/compatibility/${vehicleId}/${driverId}`, {
        method: 'GET',
      })

      compatibilityResult.value = response.data || null

      return response.data
    } catch (err) {
      if (is401(err)) {
        compatibilityResult.value = null
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Get available drivers for a specific vehicle
   * @param {String} vehicleId - Vehicle ULID
   * @param {Object} params - Filter parameters (date, min_score)
   * @returns {Promise<Array>} List of available drivers
   */
  const fetchAvailableDriversForVehicle = async (vehicleId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/resource-validation/vehicles/${vehicleId}/available-drivers`, {
        method: 'GET',
        params,
      })

      availableDriversForVehicle.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        availableDriversForVehicle.value = []
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Get available vehicles for a specific driver
   * @param {String} driverId - Approved Driver ULID
   * @param {Object} params - Filter parameters (date, vehicle_type)
   * @returns {Promise<Array>} List of available vehicles
   */
  const fetchAvailableVehiclesForDriver = async (driverId, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await $api(`/resource-validation/drivers/${driverId}/available-vehicles`, {
        method: 'GET',
        params,
      })

      availableVehiclesForDriver.value = response.data || []

      return response.data
    } catch (err) {
      if (is401(err)) {
        availableVehiclesForDriver.value = []
        
        return
      }
      const extracted = extractApiError(err)
      error.value = extracted.message
    } finally {
      loading.value = false
    }
  }

  // ── Utility Helpers ────────────────────────────────────

  /**
   * Check if validation result has any errors
   * @param {Object} result - Validation result object
   * @returns {Boolean} True if validation has errors
   */
  const hasValidationErrors = result => {
    if (!result) return false
    
    return !result.valid || 
           (result.issues && result.issues.length > 0) ||
           (result.errors && Object.keys(result.errors).length > 0)
  }

  /**
   * Get formatted error messages from validation result
   * @param {Object} result - Validation result object
   * @returns {Array<String>} Array of error messages
   */
  const getValidationMessages = result => {
    if (!result) return []
    
    const messages = []
    
    if (result.issues && Array.isArray(result.issues)) {
      messages.push(...result.issues)
    }
    
    if (result.errors && typeof result.errors === 'object') {
      Object.entries(result.errors).forEach(([field, fieldErrors]) => {
        if (Array.isArray(fieldErrors)) {
          messages.push(...fieldErrors)
        } else {
          messages.push(fieldErrors)
        }
      })
    }
    
    if (result.message) {
      messages.push(result.message)
    }
    
    return messages
  }

  // ── Reset & Utilities ──────────────────────────────────

  const resetValidation = () => {
    error.value = null
    validationErrors.value = null
    dwoValidationResult.value = null
    swoValidationResult.value = null
    vehicleValidationResult.value = null
    driverValidationResult.value = null
    compatibilityResult.value = null
  }

  const resetMatching = () => {
    availableDriversForVehicle.value = []
    availableVehiclesForDriver.value = []
    error.value = null
  }

  return {
    // State
    loading,
    error,
    validationErrors,
    dwoValidationResult,
    swoValidationResult,
    vehicleValidationResult,
    driverValidationResult,
    compatibilityResult,
    availableDriversForVehicle,
    availableVehiclesForDriver,

    // Actions
    validateDwo,
    validateSwo,
    validateVehicle,
    validateDriver,
    validateCompatibility,
    fetchAvailableDriversForVehicle,
    fetchAvailableVehiclesForDriver,

    // Helpers
    hasValidationErrors,
    getValidationMessages,
    resetValidation,
    resetMatching,
  }
})
