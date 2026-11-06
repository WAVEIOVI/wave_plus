import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useStockMovementActions
 * ─────────────────────────────────────────────────────────────────────────────
 * Mutation actions for stock movements: adjustment, transfer, and initial load.
 * Surfaces useAppSnackbar notifications on success and error.
 *
 * Usage:
 *   const { createAdjustment, creating } = useStockMovementActions({ onRefresh })
 */
export function useStockMovementActions({ onRefresh } = {}) {
  const store = useInventoryStore()
  const { t } = useI18n()
  const { success: showSuccess, error: showError } = useAppSnackbar()

  const creating = ref(false)

  // ── Create Adjustment ─────────────────────────────────────────────────────
  const createAdjustment = async payload => {
    creating.value = true
    try {
      await store.createAdjustment(payload)
      showSuccess(t('inventory.movements.messages.adjustment_created'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.movements.messages.adjustment_failed')

      showError(msg)

      return false
    } finally {
      creating.value = false
    }
  }

  // ── Create Transfer ───────────────────────────────────────────────────────
  const createTransfer = async payload => {
    creating.value = true
    try {
      await store.createTransfer(payload)
      showSuccess(t('inventory.movements.messages.transfer_created'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.movements.messages.transfer_failed')

      showError(msg)

      return false
    } finally {
      creating.value = false
    }
  }

  // ── Create Initial Load ───────────────────────────────────────────────────
  const createInitialLoad = async payload => {
    creating.value = true
    try {
      await store.createInitialLoad(payload)
      showSuccess(t('inventory.movements.messages.initial_load_created'))
      await onRefresh?.()

      return true
    } catch (err) {
      const msg = err?.data?.message || err?.message || t('inventory.movements.messages.initial_load_failed')

      showError(msg)

      return false
    } finally {
      creating.value = false
    }
  }

  return {
    creating,
    createAdjustment,
    createTransfer,
    createInitialLoad,
  }
}
