import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useEquipmentDialogs() {
  const viewDialog = useDialog()
  const deleteDialog = useDialog()
  const addDialog = useDialog()
  const transferDialog = useDialog()
  const replaceQrDialog = useDialog()

  const isFilterDialogOpen = ref(false)

  // Selected equipment data for modals
  const activeEquipment = ref(null)

  const openView = eq => {
    activeEquipment.value = eq
    viewDialog.open(eq?.id || eq)
  }

  const closeView = () => {
    viewDialog.close()
    activeEquipment.value = null
  }

  const openDelete = eq => {
    activeEquipment.value = eq
    deleteDialog.open(eq?.id || eq)
  }

  const closeDelete = () => {
    deleteDialog.close()
    activeEquipment.value = null
  }

  const openTransfer = eq => {
    activeEquipment.value = eq
    transferDialog.open(eq?.id || eq)
  }

  const closeTransfer = () => {
    transferDialog.close()
    activeEquipment.value = null
  }

  const openReplaceQr = eq => {
    activeEquipment.value = eq
    replaceQrDialog.open(eq?.id || eq)
  }

  const closeReplaceQr = () => {
    replaceQrDialog.close()
    activeEquipment.value = null
  }

  const openAdd = () => {
    addDialog.open()
  }

  const closeAdd = () => {
    addDialog.close()
  }

  return {
    viewDialog,
    deleteDialog,
    addDialog,
    transferDialog,
    replaceQrDialog,
    isFilterDialogOpen,
    activeEquipment,
    openView,
    closeView,
    openDelete,
    closeDelete,
    openTransfer,
    closeTransfer,
    openReplaceQr,
    closeReplaceQr,
    openAdd,
    closeAdd,
  }
}
