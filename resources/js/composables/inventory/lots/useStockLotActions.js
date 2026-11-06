import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useStockLotActions
 * ─────────────────────────────────────────────────────────────────────────────
 * CRUD actions for stock lots.
 * Surfaces useAppSnackbar notifications on success and error.
 *
 * Usage:
 *   const { createLot, updateLot, deleteLot, saving } = useStockLotActions({ onRefresh })
 */
export function useStockLotActions({ onRefresh } = {}) {
  const store = useInventoryStore()
  const { t } = useI18n()
  const { success: showSuccess, error: showError } = useAppSnackbar()

  const saving = ref(false)
  const isDeleting = ref(false)

  // ── Create Lot ────────────────────────────────────────────────────────────
  const createLot = async payload => {
    saving.value = true
    try {
      await store.createLot(payload)
      showSuccess(t('inventory.lots.messages.created'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.lots.messages.create_failed')

      showError(msg)

      return false
    } finally {
      saving.value = false
    }
  }

  // ── Update Lot ────────────────────────────────────────────────────────────
  const updateLot = async (id, payload) => {
    saving.value = true
    try {
      await store.updateLot(id, payload)
      showSuccess(t('inventory.lots.messages.updated'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.lots.messages.update_failed')

      showError(msg)

      return false
    } finally {
      saving.value = false
    }
  }

  // ── Delete Lot ────────────────────────────────────────────────────────────
  const deleteLot = async id => {
    if (!id) return false

    isDeleting.value = true
    try {
      await store.deleteLot(id)
      showSuccess(t('inventory.lots.messages.deleted'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.lots.messages.delete_failed')

      showError(msg)

      return false
    } finally {
      isDeleting.value = false
    }
  }

  return {
    saving,
    isDeleting,
    createLot,
    updateLot,
    deleteLot,
  }
}
