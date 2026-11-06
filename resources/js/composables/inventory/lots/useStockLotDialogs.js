import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

/**
 * useStockLotDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Dialog state orchestration for the Stock Lots page.
 * Owns dialog open/close lifecycle and the active lot reference.
 */
export function useStockLotDialogs() {
  const createDialog = useDialog()
  const editDialog = useDialog()
  const deleteDialog = useDialog()
  const detailDialog = useDialog()

  const activeLot = ref(null)

  // ── Create ────────────────────────────────────────────────────────────────
  const openCreate = () => {
    activeLot.value = null
    createDialog.open()
  }

  const closeCreate = () => {
    createDialog.close()
    activeLot.value = null
  }

  // ── Edit ──────────────────────────────────────────────────────────────────
  const openEdit = lot => {
    activeLot.value = lot
    editDialog.open(lot?.id ?? lot)
  }

  const closeEdit = () => {
    editDialog.close()
    activeLot.value = null
  }

  // ── Delete ────────────────────────────────────────────────────────────────
  const openDelete = lot => {
    activeLot.value = lot
    deleteDialog.open(lot?.id ?? lot)
  }

  const closeDelete = () => {
    deleteDialog.close()
    activeLot.value = null
  }

  // ── Detail ────────────────────────────────────────────────────────────────
  const openDetail = lot => {
    activeLot.value = lot
    detailDialog.open(lot?.id ?? lot)
  }

  const closeDetail = () => {
    detailDialog.close()
    activeLot.value = null
  }

  return {
    // Dialogs
    createDialog,
    editDialog,
    deleteDialog,
    detailDialog,

    // Active lot
    activeLot,

    // Create
    openCreate,
    closeCreate,

    // Edit
    openEdit,
    closeEdit,

    // Delete
    openDelete,
    closeDelete,

    // Detail
    openDetail,
    closeDetail,
  }
}
