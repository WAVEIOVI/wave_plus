import { useDialog } from '@/composables/dialog/useDialog'
import { useApprovedDriverStore } from '@/stores/useApprovedDriverStore'
import { ref } from 'vue'

/**
 * useDriverDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Manages all approved driver dialog states following BP pattern.
 *
 * Owns:
 *   - Dialog open/close state for each dialog type
 *   - Current selected driver for dialogs
 *   - Async loading state when opening detail dialogs
 *
 * Usage:
 *   const dialogs = useDriverDialogs()
 *   dialogs.viewDialog.open(driverId)
 *   dialogs.suspendDialog.open(driver)
 */
export function useDriverDialogs() {
  const driverStore = useApprovedDriverStore()

  // ── Dialog States ────────────────────────────────────────────────────────

  const viewDialog = useDialog()
  const createDialog = useDialog()
  const editDialog = useDialog()
  const deleteDialog = useDialog()
  const suspendDialog = useDialog()
  const reinstateDialog = useDialog()
  const revokeDialog = useDialog()
  const renewDialog = useDialog()
  const incidentDialog = useDialog()

  // ── View Dialog with Async Loading ──────────────────────────────────────

  const viewLoading = ref(false)
  const viewData = ref(null)
  const viewError = ref(null)

  /**
   * Open view dialog with async driver fetch
   * @param {string} driverId
   */
  const openViewDialog = async driverId => {
    viewDialog.open(driverId)
    viewLoading.value = true
    viewError.value = null
    viewData.value = null

    try {
      const driver = await driverStore.fetchById(driverId)

      viewData.value = driver
    }
    catch (error) {
      viewError.value = error?.data?.message || error?.message || 'Failed to load driver'
      console.error('[useDriverDialogs] Error loading driver:', error)
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
   * Open suspend dialog
   * @param {object} driver
   */
  const openSuspendDialog = driver => {
    suspendDialog.open(driver)
  }

  /**
   * Open reinstate dialog
   * @param {object} driver
   */
  const openReinstateDialog = driver => {
    reinstateDialog.open(driver)
  }

  /**
   * Open revoke dialog
   * @param {object} driver
   */
  const openRevokeDialog = driver => {
    revokeDialog.open(driver)
  }

  /**
   * Open renew dialog
   * @param {object} driver
   */
  const openRenewDialog = driver => {
    renewDialog.open(driver)
  }

  /**
   * Open incident dialog
   * @param {object} driver
   */
  const openIncidentDialog = driver => {
    incidentDialog.open(driver)
  }

  return {
    // Dialog states
    viewDialog,
    createDialog,
    editDialog,
    deleteDialog,
    suspendDialog,
    reinstateDialog,
    revokeDialog,
    renewDialog,
    incidentDialog,

    // View dialog specific
    viewLoading,
    viewData,
    viewError,
    openViewDialog,
    closeViewDialog,

    // Workflow dialogs
    openSuspendDialog,
    openReinstateDialog,
    openRevokeDialog,
    openRenewDialog,
    openIncidentDialog,
  }
}
