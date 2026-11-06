import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

/**
 * useStockItemDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Dialog state orchestration for the Stock Items page.
 * Owns dialog open/close lifecycle and the active stock item reference.
 *
 * Does NOT own:
 *   - List data or fetching (useStockItemList)
 *   - Create/update/delete mutations (triggered via store from dialog internals)
 */
export function useStockItemDialogs() {
  const quickViewDialog = useDialog()
  const adjustmentDialog = useDialog()
  const transferDialog = useDialog()

  // Active item reference shared between all dialogs
  const activeItem = ref(null)

  // ── Quick View ────────────────────────────────────────────────────────────
  const openQuickView = item => {
    activeItem.value = item
    quickViewDialog.open(item?.id ?? item)
  }

  const closeQuickView = () => {
    quickViewDialog.close()
    activeItem.value = null
  }

  // ── Stock Adjustment ──────────────────────────────────────────────────────
  const openAdjustment = item => {
    activeItem.value = item
    adjustmentDialog.open(item?.id ?? item)
  }

  const closeAdjustment = () => {
    adjustmentDialog.close()
    activeItem.value = null
  }

  // ── Stock Transfer ────────────────────────────────────────────────────────
  const openTransfer = item => {
    activeItem.value = item
    transferDialog.open(item?.id ?? item)
  }

  const closeTransfer = () => {
    transferDialog.close()
    activeItem.value = null
  }

  return {
    // Dialogs
    quickViewDialog,
    adjustmentDialog,
    transferDialog,

    // Shared active item
    activeItem,

    // Quick View
    openQuickView,
    closeQuickView,

    // Adjustment
    openAdjustment,
    closeAdjustment,

    // Transfer
    openTransfer,
    closeTransfer,
  }
}
