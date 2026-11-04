import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAppraisalStore } from '@/stores/useAppraisalStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useAppraisalActions({ dialogs }) {
  const store = useAppraisalStore()
  const { showMessage } = useAppSnackbar()
  const { t } = useI18n()

  const isSaving = ref(false)
  const isDeleting = ref(false)
  const isAcknowledging = ref(false)

  const handleSaveCycle = async payload => {
    isSaving.value = true
    try {
      if (dialogs.selectedItem.value?.id) {
        await store.updateCycle(dialogs.selectedItem.value.id, payload)
        showMessage(t('appraisals.cycle_updated', 'Appraisal cycle updated.'), 'success')
      } else {
        await store.createCycle(payload)
        showMessage(t('appraisals.cycle_created', 'Appraisal cycle created.'), 'success')
      }
      dialogs.closeCycle()
    } catch (err) {
      showMessage(err?.data?.message || t('appraisals.cycle_error', 'Failed to save cycle.'), 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  const handleSaveAppraisal = async payload => {
    isSaving.value = true
    try {
      if (dialogs.selectedItem.value?.id) {
        await store.updateAppraisal(dialogs.selectedItem.value.id, payload)
        showMessage(t('appraisals.review_updated', 'Appraisal review updated.'), 'success')
      } else {
        await store.createAppraisal(payload)
        showMessage(t('appraisals.review_created', 'Appraisal review recorded.'), 'success')
      }
      dialogs.closeReview()
    } catch (err) {
      showMessage(err?.data?.message || t('appraisals.review_error', 'Failed to save appraisal.'), 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  const handleAcknowledgeAppraisal = async (id, payload = {}) => {
    isAcknowledging.value = true
    try {
      await store.acknowledgeAppraisal(id, payload)
      showMessage(t('appraisals.acknowledged_success', 'Appraisal acknowledged successfully.'), 'success')
      if (dialogs.closeDetail) dialogs.closeDetail()
    } catch (err) {
      showMessage(err?.data?.message || t('appraisals.acknowledge_error', 'Failed to acknowledge appraisal.'), 'error')
      throw err
    } finally {
      isAcknowledging.value = false
    }
  }

  const handleDelete = async () => {
    const item = dialogs.selectedItem.value
    if (!item) return

    isDeleting.value = true
    try {
      if (dialogs.deleteType.value === 'cycle') {
        await store.destroyCycle(item.id)
        showMessage(t('appraisals.cycle_deleted', 'Appraisal cycle deleted.'), 'success')
      } else if (dialogs.deleteType.value === 'appraisal') {
        await store.destroyAppraisal(item.id)
        showMessage(t('appraisals.review_deleted', 'Appraisal record deleted.'), 'success')
      }
      dialogs.closeDelete()
    } catch (err) {
      showMessage(err?.data?.message || t('appraisals.delete_error', 'Failed to delete record.'), 'error')
    } finally {
      isDeleting.value = false
    }
  }

  return {
    isSaving,
    isDeleting,
    isAcknowledging,
    handleSaveCycle,
    handleSaveAppraisal,
    handleAcknowledgeAppraisal,
    handleDelete,
  }
}
