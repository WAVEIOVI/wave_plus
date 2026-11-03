import { useDialog } from '@/composables/dialog/useDialog'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { ref } from 'vue'

/**
 * useEmployeeDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Centralises all dialog state for the Employees Directory list page.
 *
 * Owns:
 *   - View Employee dialog (with async loading of full employee record)
 *   - Add New Employee dialog
 *   - Delete Employee confirmation dialog
 *   - Status change dialog
 *
 * Does NOT own:
 *   - CRUD actions (useEmployeeActions)
 *   - List state (useEmployeeList)
 *
 * Architecture:
 *   Page → useEmployeeDialogs → useEmployeeStore → API
 */
export function useEmployeeDialogs() {
  const employeeStore = useEmployeeStore()

  // ── View Employee ────────────────────────────────────────────────────────
  const viewDialog  = useDialog()
  const viewLoading = ref(false)
  const viewError   = ref(null)
  const viewEmployee = ref(null)

  const openView = async employeeId => {
    viewEmployee.value = null
    viewError.value    = null
    viewLoading.value  = true
    viewDialog.open(employeeId)

    try {
      viewEmployee.value = await employeeStore.fetchOne(employeeId)
    }
    catch (err) {
      viewError.value = err?.data?.message || err?.message || 'Failed to load employee details'
    }
    finally {
      viewLoading.value = false
    }
  }

  const closeView = () => {
    viewDialog.close()
    viewEmployee.value = null
    viewError.value    = null
  }

  // ── Add New Employee ─────────────────────────────────────────────────────
  const addDialog = useDialog()

  // ── Delete Confirmation ──────────────────────────────────────────────────
  const deleteDialog = useDialog()

  const openDelete = employeeId => deleteDialog.open(employeeId)

  // ── Status Change ────────────────────────────────────────────────────────
  const statusDialog = useDialog()
  const statusAction = ref('activate') // 'activate' | 'suspend' | 'terminate'

  /**
   * @param {string} employeeId
   * @param {'activate'|'suspend'|'terminate'} action
   */
  const openStatusChange = (employeeId, action = 'activate') => {
    statusAction.value = action
    statusDialog.open(employeeId)
  }

  return {
    // View dialog
    viewDialog,
    viewLoading,
    viewError,
    viewEmployee,
    openView,
    closeView,

    // Add dialog
    addDialog,

    // Delete dialog
    deleteDialog,
    openDelete,

    // Status dialog
    statusDialog,
    statusAction,
    openStatusChange,
  }
}
