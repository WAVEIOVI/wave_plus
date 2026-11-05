import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useDriverForm
 * ─────────────────────────────────────────────────────────────────────────────
 * Driver authorization form state management composable.
 *
 * Owns:
 *   - Form data reactive state
 *   - Validation state
 *   - Loading state
 *   - Change detection (hasChanges)
 *   - Form submission logic with error handling
 *   - Success/error notifications
 *
 * Usage:
 *   const {
 *     form,
 *     isValid,
 *     loading,
 *     hasChanges,
 *     handleSubmit,
 *     resetForm,
 *   } = useDriverForm({
 *     initialData: driverData,
 *     onSubmit: async (data) => await driverStore.create(data),
 *     onSuccess: () => { dialog.close(); refresh() },
 *   })
 *
 * @param {object} options
 * @param {object} options.initialData - Initial form data
 * @param {(data: object) => Promise<any>} options.onSubmit - Submit handler (store method)
 * @param {() => void} [options.onSuccess] - Success callback
 * @param {(error: any) => void} [options.onError] - Error callback
 * @returns Form state and handlers
 */
export function useDriverForm({
  initialData = {},
  onSubmit,
  onSuccess,
  onError,
}) {
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()

  // ── Form State ───────────────────────────────────────────────────────────

  const form = ref({ ...initialData })
  const originalForm = ref({ ...initialData })
  const loading = ref(false)
  const isValid = ref(false)
  const validationErrors = ref(null)

  // ── Change Detection ─────────────────────────────────────────────────────

  const hasChanges = computed(() => {
    return JSON.stringify(form.value) !== JSON.stringify(originalForm.value)
  })

  // ── Form Handlers ────────────────────────────────────────────────────────

  /**
   * Handle form submission
   */
  const handleSubmit = async () => {
    if (!isValid.value) {
      showMessage(t('fleet.messages.validationError'), 'error')
      
      return false
    }

    loading.value = true
    validationErrors.value = null

    try {
      const result = await onSubmit(form.value)
      showMessage(t('fleet.drivers.messages.approveSuccess'), 'success')

      // Update original form to reset change detection
      originalForm.value = { ...form.value }

      if (onSuccess) {
        onSuccess(result)
      }

      return true
    }
    catch (error) {
      // Extract validation errors if present
      if (error?.data?.errors) {
        validationErrors.value = error.data.errors
      }

      const message = error?.data?.message || error?.message || t('fleet.drivers.messages.approveError')

      showMessage(message, 'error')

      if (onError) {
        onError(error)
      }

      return false
    }
    finally {
      loading.value = false
    }
  }

  /**
   * Reset form to initial data
   */
  const resetForm = () => {
    form.value = { ...initialData }
    originalForm.value = { ...initialData }
    isValid.value = false
    validationErrors.value = null
  }

  /**
   * Update form with new data (e.g., when switching from create to edit)
   */
  const setFormData = newData => {
    form.value = { ...newData }
    originalForm.value = { ...newData }
    validationErrors.value = null
  }

  // Watch for external changes to initialData
  watch(
    () => initialData,
    newData => {
      if (newData && Object.keys(newData).length > 0) {
        setFormData(newData)
      }
    },
    { deep: true },
  )

  return {
    form,
    isValid,
    loading,
    hasChanges,
    validationErrors,
    handleSubmit,
    resetForm,
    setFormData,
  }
}
