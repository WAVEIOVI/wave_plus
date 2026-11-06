import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useWarehouseSiteActions
 * ─────────────────────────────────────────────────────────────────────────────
 * CRUD actions for warehouse sites, zones, locations, and mobile vehicle linking.
 * Surfaces useAppSnackbar notifications on success and error.
 *
 * Usage:
 *   const { createSite, deleteSite, linkVehicle, ... } = useWarehouseSiteActions({ onRefresh })
 */
export function useWarehouseSiteActions({ onRefresh } = {}) {
  const store = useInventoryStore()
  const { t } = useI18n()
  const { success: showSuccess, error: showError } = useAppSnackbar()

  const saving = ref(false)
  const isDeleting = ref(false)
  const isLinking = ref(false)

  // ── Site CRUD ─────────────────────────────────────────────────────────────
  const createSite = async payload => {
    saving.value = true
    try {
      const result = await store.createSite(payload)

      showSuccess(t('inventory.sites.messages.created'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.create_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const updateSite = async (id, payload) => {
    saving.value = true
    try {
      const result = await store.updateSite(id, payload)

      showSuccess(t('inventory.sites.messages.updated'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.update_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const deleteSite = async id => {
    if (!id) return false

    isDeleting.value = true
    try {
      await store.deleteSite(id)
      showSuccess(t('inventory.sites.messages.deleted'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.delete_failed')

      showError(msg)

      return false
    } finally {
      isDeleting.value = false
    }
  }

  // ── Zone CRUD ─────────────────────────────────────────────────────────────
  const createZone = async payload => {
    saving.value = true
    try {
      const result = await store.createZone(payload)

      showSuccess(t('inventory.zones.messages.created'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.zones.messages.create_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const updateZone = async (id, payload) => {
    saving.value = true
    try {
      const result = await store.updateZone(id, payload)

      showSuccess(t('inventory.zones.messages.updated'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.zones.messages.update_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const deleteZone = async id => {
    if (!id) return false

    isDeleting.value = true
    try {
      await store.deleteZone(id)
      showSuccess(t('inventory.zones.messages.deleted'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.zones.messages.delete_failed')

      showError(msg)

      return false
    } finally {
      isDeleting.value = false
    }
  }

  // ── Location CRUD ─────────────────────────────────────────────────────────
  const createLocation = async payload => {
    saving.value = true
    try {
      const result = await store.createLocation(payload)

      showSuccess(t('inventory.locations.messages.created'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.locations.messages.create_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const updateLocation = async (id, payload) => {
    saving.value = true
    try {
      const result = await store.updateLocation(id, payload)

      showSuccess(t('inventory.locations.messages.updated'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.locations.messages.update_failed')

      showError(msg)

      return null
    } finally {
      saving.value = false
    }
  }

  const deleteLocation = async id => {
    if (!id) return false

    isDeleting.value = true
    try {
      await store.deleteLocation(id)
      showSuccess(t('inventory.locations.messages.deleted'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.locations.messages.delete_failed')

      showError(msg)

      return false
    } finally {
      isDeleting.value = false
    }
  }

  const toggleLocationActive = async id => {
    if (!id) return false

    try {
      await store.toggleLocationActive(id)
      showSuccess(t('inventory.locations.messages.toggled'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.locations.messages.toggle_failed')

      showError(msg)

      return false
    }
  }

  // ── Mobile Vehicle Linking ────────────────────────────────────────────────
  const linkVehicle = async (siteId, payload) => {
    isLinking.value = true
    try {
      const result = await store.linkVehicle(siteId, payload)

      showSuccess(t('inventory.sites.messages.vehicle_linked'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.vehicle_link_failed')

      showError(msg)

      return null
    } finally {
      isLinking.value = false
    }
  }

  const unlinkVehicle = async siteId => {
    isLinking.value = true
    try {
      const result = await store.unlinkVehicle(siteId)

      showSuccess(t('inventory.sites.messages.vehicle_unlinked'))
      await onRefresh?.()

      return result
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.vehicle_unlink_failed')

      showError(msg)

      return null
    } finally {
      isLinking.value = false
    }
  }

  const fetchSiteAssignmentHistory = async (siteId, params = {}) => {
    try {
      return await store.fetchSiteAssignmentHistory(siteId, params)
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.sites.messages.history_failed')

      showError(msg)

      return null
    }
  }

  return {
    saving,
    isDeleting,
    isLinking,

    // Site
    createSite,
    updateSite,
    deleteSite,

    // Zone
    createZone,
    updateZone,
    deleteZone,

    // Location
    createLocation,
    updateLocation,
    deleteLocation,
    toggleLocationActive,

    // Vehicle
    linkVehicle,
    unlinkVehicle,
    fetchSiteAssignmentHistory,
  }
}
