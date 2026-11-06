import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

/**
 * useStockMovementDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Dialog state orchestration for the Stock Movements page.
 * Owns dialog open/close lifecycle and the active movement reference.
 */
export function useStockMovementDialogs() {
  const adjustmentDialog = useDialog()
  const transferDialog = useDialog()
  const initialLoadDialog = useDialog()
  const detailDialog = useDialog()

  const activeMovement = ref(null)

  // ── Detail View ───────────────────────────────────────────────────────────
  const openDetail = movement => {
    activeMovement.value = movement
    detailDialog.open(movement?.id ?? movement)
  }

  const closeDetail = () => {
    detailDialog.close()
    activeMovement.value = null
  }

  // ── Adjustment ────────────────────────────────────────────────────────────
  const openAdjustment = (prefill = null) => {
    activeMovement.value = prefill
    adjustmentDialog.open()
  }

  const closeAdjustment = () => {
    adjustmentDialog.close()
    activeMovement.value = null
  }

  // ── Transfer ──────────────────────────────────────────────────────────────
  const openTransfer = (prefill = null) => {
    activeMovement.value = prefill
    transferDialog.open()
  }

  const closeTransfer = () => {
    transferDialog.close()
    activeMovement.value = null
  }

  // ── Initial Load ──────────────────────────────────────────────────────────
  const openInitialLoad = () => {
    initialLoadDialog.open()
  }

  const closeInitialLoad = () => {
    initialLoadDialog.close()
    activeMovement.value = null
  }

  return {
    // Dialogs
    adjustmentDialog,
    transferDialog,
    initialLoadDialog,
    detailDialog,

    // Active movement
    activeMovement,

    // Detail
    openDetail,
    closeDetail,

    // Adjustment
    openAdjustment,
    closeAdjustment,

    // Transfer
    openTransfer,
    closeTransfer,

    // Initial Load
    openInitialLoad,
    closeInitialLoad,
  }
}
