import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { localizedValue } from '@/composables/useLocaleField'

/**
 * useEmployeeActions
 * ─────────────────────────────────────────────────────────────────────────────
 * Employee CRUD and status action handlers.
 *
 * Owns:
 *   - Loading state per action
 *   - Success / error snackbar feedback
 *   - Post-action side effects (navigate to edit page, refresh list)
 *
 * Does NOT own:
 *   - Dialog state (useEmployeeDialogs)
 *   - List state (useEmployeeList)
 *   - API calls (useEmployeeStore)
 *
 * Architecture:
 *   Page → useEmployeeActions → useEmployeeStore → API
 *
 * @param {{ onRefresh?: Function, onMetaRefresh?: Function }} opts
 */
export function useEmployeeActions({ onRefresh, onMetaRefresh } = {}) {
  const store          = useEmployeeStore()
  const router         = useRouter()
  const { t }          = useI18n()
  const { showMessage } = useAppSnackbar()

  // ── Loading states ────────────────────────────────────────────────────────
  const isCreating       = ref(false)
  const isDeleting       = ref(false)
  const isChangingStatus = ref(false)

  // ── Create ────────────────────────────────────────────────────────────────
  /**
   * Create a new employee and navigate to the edit page on success.
   * @param {object} employeeData
   */
  const createEmployee = async employeeData => {
    isCreating.value = true
    try {
      const response = await store.create(employeeData)
      const newId    = response?.id || response?.data?.id

      const name     = [localizedValue(response?.first_name), localizedValue(response?.last_name)].filter(Boolean).join(' ')
        || response?.data?.full_name
        || t('employees.actions.create_success_fallback')

      showMessage(t('employees.actions.create_success', { name }), 'success')

      if (newId) {
        router.push({
          name: 'people-skills-employees-edit-id',
          params: { id: newId },
        })
      }
      else {
        onRefresh?.()
        onMetaRefresh?.()
      }

      return response
    }
    catch (err) {
      const apiError
        = err?.data?.message
        || (err?.data?.errors ? Object.values(err.data.errors)[0]?.[0] : null)
        || t('employees.actions.create_error')

      showMessage(apiError, 'error')

      return null
    }
    finally {
      isCreating.value = false
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────
  /**
   * Delete an employee and refresh the list.
   * @param {string} employeeId
   * @returns {boolean}
   */
  const deleteEmployee = async employeeId => {
    if (!employeeId) return false
    isDeleting.value = true
    try {
      await store.remove(employeeId)
      showMessage(t('employees.actions.delete_success'), 'success')
      await onRefresh?.()
      onMetaRefresh?.()

      return true
    }
    catch (err) {
      showMessage(err?.data?.message || t('employees.actions.delete_error'), 'error')

      return false
    }
    finally {
      isDeleting.value = false
    }
  }

  // ── Status change ─────────────────────────────────────────────────────────
  /**
   * Change employee status (activate / suspend / terminate / on_leave).
   * @param {string} employeeId
   * @param {string} status
   * @param {string|null} reason
   * @param {string|null} effectiveDate
   */
  const changeStatus = async (employeeId, status, reason = null, effectiveDate = null) => {
    if (!employeeId) return false
    isChangingStatus.value = true
    try {
      await store.changeStatus(employeeId, status, reason, effectiveDate)
      showMessage(t('employees.actions.status_success', { status }), 'success')
      onRefresh?.()
      onMetaRefresh?.()

      return true
    }
    catch (err) {
      showMessage(err?.data?.message || t('employees.actions.status_error'), 'error')

      return false
    }
    finally {
      isChangingStatus.value = false
    }
  }

  return {
    // States
    isCreating,
    isDeleting,
    isChangingStatus,

    // Actions
    createEmployee,
    deleteEmployee,
    changeStatus,
  }
}
