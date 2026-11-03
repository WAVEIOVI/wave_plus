import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useSiteActions({ onRefresh } = {}) {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()

  const isDeleting = ref(false)

  const deleteSite = async siteId => {
    if (!siteId) return false
    isDeleting.value = true
    try {
      await store.deleteSite(siteId)
      showMessage(t('Site deleted successfully'), 'success')
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('Failed to delete site')

      showMessage(msg, 'error')

      return false
    } finally {
      isDeleting.value = false
    }
  }

  return {
    isDeleting,
    deleteSite,
  }
}
