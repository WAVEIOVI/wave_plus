import { useDialog } from '@/composables/dialog/useDialog'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { ref } from 'vue'

/**
 * useVehicleDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Manages all vehicle dialog states following BP pattern.
 *
 * Owns:
 *   - Dialog open/close state for each dialog type
 *   - Current selected vehicle for dialogs
 *   - Async loading state when opening detail dialogs
 *
 * Does NOT own:
 *   - Actual CRUD operations (that's useVehicleActions)
 *   - Form state (that's useVehicleForm)
 *   - List state (that's useFleetVehicleListPage)
 *
 * Usage:
 *   const dialogs = useVehicleDialogs()
 *   dialogs.viewDialog.open(vehicleId)
 *   dialogs.deleteDialog.open(vehicle)
 */
export function useVehicleDialogs() {
  const vehicleStore = useVehicleStore()

  // ── Dialog States ────────────────────────────────────────────────────────

  const viewDialog = useDialog()
  const createDialog = useDialog()
  const editDialog = useDialog()
  const deleteDialog = useDialog()
  const assignDialog = useDialog()
  const returnDialog = useDialog()
  const statusDialog = useDialog()
  const odometerDialog = useDialog()
  const rentalDialog = useDialog()
  const clientUsageDialog = useDialog()
  const telemetryDialog = useDialog()

  // ── View Dialog with Async Loading ──────────────────────────────────────

  const viewLoading = ref(false)
  const viewData = ref(null)
  const viewError = ref(null)

  /**
   * Open view dialog with async vehicle fetch
   * @param {string} vehicleId
   */
  const openViewDialog = async vehicleId => {
    viewDialog.open(vehicleId)
    viewLoading.value = true
    viewError.value = null
    viewData.value = null

    try {
      const vehicle = await vehicleStore.fetchById(vehicleId)

      viewData.value = vehicle
    }
    catch (error) {
      viewError.value = error?.data?.message || error?.message || 'Failed to load vehicle'
      console.error('[useVehicleDialogs] Error loading vehicle:', error)
    }
    finally {
      viewLoading.value = false
    }
  }

  const closeViewDialog = () => {
    viewDialog.close()
    viewData.value = null
    viewError.value = null
  }

  // ── Workflow Dialogs ─────────────────────────────────────────────────────

  /**
   * Open assign dialog
   * @param {object} vehicle
   */
  const openAssignDialog = vehicle => {
    assignDialog.open(vehicle)
  }

  /**
   * Open return dialog
   * @param {object} vehicle
   */
  const openReturnDialog = vehicle => {
    returnDialog.open(vehicle)
  }

  /**
   * Open status change dialog
   * @param {object} vehicle
   */
  const openStatusDialog = vehicle => {
    statusDialog.open(vehicle)
  }

  /**
   * Open odometer update dialog
   * @param {object} vehicle
   */
  const openOdometerDialog = vehicle => {
    odometerDialog.open(vehicle)
  }

  /**
   * Open rental dialog
   * @param {object} vehicle
   */
  const openRentalDialog = vehicle => {
    rentalDialog.open(vehicle)
  }

  /**
   * Open client usage dialog
   * @param {object} vehicle
   */
  const openClientUsageDialog = vehicle => {
    clientUsageDialog.open(vehicle)
  }

  /**
   * Open telemetry dialog
   * @param {object} vehicle
   */
  const openTelemetryDialog = vehicle => {
    telemetryDialog.open(vehicle)
  }

  return {
    // Dialog states
    viewDialog,
    createDialog,
    editDialog,
    deleteDialog,
    assignDialog,
    returnDialog,
    statusDialog,
    odometerDialog,
    rentalDialog,
    clientUsageDialog,
    telemetryDialog,

    // View dialog specific
    viewLoading,
    viewData,
    viewError,
    openViewDialog,
    closeViewDialog,

    // Workflow dialogs
    openAssignDialog,
    openReturnDialog,
    openStatusDialog,
    openOdometerDialog,
    openRentalDialog,
    openClientUsageDialog,
    openTelemetryDialog,
  }
}
