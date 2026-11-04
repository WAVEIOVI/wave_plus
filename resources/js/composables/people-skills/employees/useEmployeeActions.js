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
  const isExportingList  = ref(false)

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

  // ── Batch Export ──────────────────────────────────────────────────────────
  /**
   * Export employee list records as CSV or printable PDF.
   * @param {Array<object>} items
   * @param {'csv'|'pdf'} format
   * @returns {boolean}
   */
  const exportEmployees = async (items, format = 'csv') => {
    if (!items || items.length === 0) {
      showMessage(t('common.noRecords', 'No records to export'), 'warning')

      return false
    }

    isExportingList.value = true
    try {
      if (format === 'csv') {
        const escapeCsv = val => `"${String(val ?? '').replace(/"/g, '""')}"`

        const headers = ['Employee #', 'First Name', 'Last Name', 'Department', 'Position', 'Team', 'Work Email', 'Work Phone', 'Hire Date', 'Status']

        const rows = items.map(e => [
          escapeCsv(e.employee_number),
          escapeCsv(localizedValue(e.first_name)),
          escapeCsv(localizedValue(e.last_name)),
          escapeCsv(localizedValue(e.department?.name)),
          escapeCsv(localizedValue(e.position?.title)),
          escapeCsv(localizedValue(e.current_team?.name)),
          escapeCsv(e.work_email),
          escapeCsv(e.work_phone),
          escapeCsv(e.hire_date),
          escapeCsv(e.status),
        ].join(','))

        const csvContent = '\uFEFF' + [headers.join(','), ...rows].join('\r\n')
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')

        link.setAttribute('href', url)
        link.setAttribute('download', `employees_export_${new Date().toISOString().slice(0, 10)}.csv`)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)

        showMessage(t('common.exportSuccess', 'Export completed successfully'), 'success')

        return true
      }

      if (format === 'pdf') {
        window.print()
        showMessage(t('common.exportSuccess', 'Print layout generated'), 'success')

        return true
      }

      return false
    }
    catch (err) {
      showMessage(err?.message || t('common.exportError', 'Failed to export records'), 'error')

      return false
    }
    finally {
      isExportingList.value = false
    }
  }

  return {
    // States
    isCreating,
    isDeleting,
    isChangingStatus,
    isExportingList,

    // Actions
    createEmployee,
    deleteEmployee,
    changeStatus,
    exportEmployees,
  }
}
