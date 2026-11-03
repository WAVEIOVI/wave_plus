import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useEquipmentActions({ onRefresh } = {}) {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()

  const isDeleting = ref(false)

  const deleteEquipment = async equipmentId => {
    if (!equipmentId) return false
    isDeleting.value = true
    try {
      await store.deleteEquipment(equipmentId)
      showMessage(t('Equipment deleted successfully'), 'success')
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('Failed to delete equipment')

      showMessage(msg, 'error')

      return false
    } finally {
      isDeleting.value = false
    }
  }

  const downloadQrCode = async equipmentId => {
    if (!equipmentId) return
    try {
      await store.downloadEquipmentQr(equipmentId)
      showMessage(t('QR code downloaded'), 'success')
    } catch (err) {
      showMessage(t('Failed to download QR code'), 'error')
    }
  }

  return {
    isDeleting,
    deleteEquipment,
    downloadQrCode,
  }
}
