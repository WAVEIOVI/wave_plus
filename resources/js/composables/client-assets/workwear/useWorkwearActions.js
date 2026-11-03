import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useWorkwearActions({ onRefresh } = {}) {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()

  const isDeleting = ref(false)

  const deleteItem = async id => {
    if (!id) return false
    isDeleting.value = true
    try {
      await store.deleteWorkwearItem(id)
      showMessage(t('Workwear item deleted successfully'), 'success')
      await onRefresh?.()

      return true
    } catch (err) {
      showMessage(err?.data?.message || err?.message || t('Failed to delete item'), 'error')

      return false
    } finally {
      isDeleting.value = false
    }
  }

  const deleteModel = async id => {
    if (!id) return false
    isDeleting.value = true
    try {
      await store.deleteWorkwearModel(id)
      showMessage(t('Workwear model deleted successfully'), 'success')
      await onRefresh?.()

      return true
    } catch (err) {
      showMessage(err?.data?.message || err?.message || t('Failed to delete model'), 'error')

      return false
    } finally {
      isDeleting.value = false
    }
  }

  const deleteEmployee = async id => {
    if (!id) return false
    isDeleting.value = true
    try {
      await store.deleteWorkwearEmployee(id)
      showMessage(t('Workwear employee deleted successfully'), 'success')
      await onRefresh?.()

      return true
    } catch (err) {
      showMessage(err?.data?.message || err?.message || t('Failed to delete employee'), 'error')

      return false
    } finally {
      isDeleting.value = false
    }
  }

  return {
    isDeleting,
    deleteItem,
    deleteModel,
    deleteEmployee,
  }
}
