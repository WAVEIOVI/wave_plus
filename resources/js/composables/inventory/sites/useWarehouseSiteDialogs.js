import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

/**
 * useWarehouseSiteDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Dialog state orchestration for the Warehouse Sites page.
 * Owns dialog open/close lifecycle and the active site/zone/location references.
 */
export function useWarehouseSiteDialogs() {
  // ── Site dialogs ──────────────────────────────────────────────────────────
  const createSiteDialog = useDialog()
  const deleteSiteDialog = useDialog()
  const detailSiteDialog = useDialog()

  // ── Zone dialogs ──────────────────────────────────────────────────────────
  const createZoneDialog = useDialog()
  const editZoneDialog = useDialog()
  const deleteZoneDialog = useDialog()

  // ── Location dialogs ──────────────────────────────────────────────────────
  const createLocationDialog = useDialog()
  const editLocationDialog = useDialog()
  const deleteLocationDialog = useDialog()

  // ── Vehicle dialogs ───────────────────────────────────────────────────────
  const linkVehicleDialog = useDialog()
  const assignmentHistoryDialog = useDialog()

  // ── Active entity references ──────────────────────────────────────────────
  const activeSite = ref(null)
  const activeZone = ref(null)
  const activeLocation = ref(null)

  // ── Site ──────────────────────────────────────────────────────────────────
  const openCreateSite = () => {
    activeSite.value = null
    createSiteDialog.open()
  }

  const closeCreateSite = () => {
    createSiteDialog.close()
    activeSite.value = null
  }

  const openDeleteSite = site => {
    activeSite.value = site
    deleteSiteDialog.open(site?.id ?? site)
  }

  const closeDeleteSite = () => {
    deleteSiteDialog.close()
    activeSite.value = null
  }

  const openDetailSite = site => {
    activeSite.value = site
    detailSiteDialog.open(site?.id ?? site)
  }

  const closeDetailSite = () => {
    detailSiteDialog.close()
    activeSite.value = null
  }

  // ── Zone ──────────────────────────────────────────────────────────────────
  const openCreateZone = site => {
    activeSite.value = site
    activeZone.value = null
    createZoneDialog.open()
  }

  const closeCreateZone = () => {
    createZoneDialog.close()
    activeZone.value = null
  }

  const openEditZone = (zone, site = null) => {
    activeSite.value = site ?? activeSite.value
    activeZone.value = zone
    editZoneDialog.open(zone?.id ?? zone)
  }

  const closeEditZone = () => {
    editZoneDialog.close()
    activeZone.value = null
  }

  const openDeleteZone = zone => {
    activeZone.value = zone
    deleteZoneDialog.open(zone?.id ?? zone)
  }

  const closeDeleteZone = () => {
    deleteZoneDialog.close()
    activeZone.value = null
  }

  // ── Location ──────────────────────────────────────────────────────────────
  const openCreateLocation = zone => {
    activeZone.value = zone
    activeLocation.value = null
    createLocationDialog.open()
  }

  const closeCreateLocation = () => {
    createLocationDialog.close()
    activeLocation.value = null
  }

  const openEditLocation = location => {
    activeLocation.value = location
    editLocationDialog.open(location?.id ?? location)
  }

  const closeEditLocation = () => {
    editLocationDialog.close()
    activeLocation.value = null
  }

  const openDeleteLocation = location => {
    activeLocation.value = location
    deleteLocationDialog.open(location?.id ?? location)
  }

  const closeDeleteLocation = () => {
    deleteLocationDialog.close()
    activeLocation.value = null
  }

  // ── Vehicle ───────────────────────────────────────────────────────────────
  const openLinkVehicle = site => {
    activeSite.value = site
    linkVehicleDialog.open(site?.id ?? site)
  }

  const closeLinkVehicle = () => {
    linkVehicleDialog.close()
  }

  const openAssignmentHistory = site => {
    activeSite.value = site
    assignmentHistoryDialog.open(site?.id ?? site)
  }

  const closeAssignmentHistory = () => {
    assignmentHistoryDialog.close()
  }

  return {
    // Dialogs
    createSiteDialog,
    deleteSiteDialog,
    detailSiteDialog,
    createZoneDialog,
    editZoneDialog,
    deleteZoneDialog,
    createLocationDialog,
    editLocationDialog,
    deleteLocationDialog,
    linkVehicleDialog,
    assignmentHistoryDialog,

    // Active refs
    activeSite,
    activeZone,
    activeLocation,

    // Site
    openCreateSite,
    closeCreateSite,
    openDeleteSite,
    closeDeleteSite,
    openDetailSite,
    closeDetailSite,

    // Zone
    openCreateZone,
    closeCreateZone,
    openEditZone,
    closeEditZone,
    openDeleteZone,
    closeDeleteZone,

    // Location
    openCreateLocation,
    closeCreateLocation,
    openEditLocation,
    closeEditLocation,
    openDeleteLocation,
    closeDeleteLocation,

    // Vehicle
    openLinkVehicle,
    closeLinkVehicle,
    openAssignmentHistory,
    closeAssignmentHistory,
  }
}
