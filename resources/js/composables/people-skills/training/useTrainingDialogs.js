import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useTrainingDialogs() {
  const programDialog = useDialog()
  const sessionDialog = useDialog()
  const enrollDialog = useDialog()
  const detailDialog = useDialog()
  const deleteDialog = useDialog()

  const selectedItem = ref(null)
  const deleteType = ref('program') // 'program' | 'session' | 'participant'

  const openProgram = (item = null) => {
    selectedItem.value = item
    programDialog.open(item?.id)
  }

  const closeProgram = () => {
    programDialog.close()
    selectedItem.value = null
  }

  const openSession = (item = null) => {
    selectedItem.value = item
    sessionDialog.open(item?.id)
  }

  const closeSession = () => {
    sessionDialog.close()
    selectedItem.value = null
  }

  const openEnroll = session => {
    selectedItem.value = session
    enrollDialog.open(session?.id)
  }

  const closeEnroll = () => {
    enrollDialog.close()
    selectedItem.value = null
  }

  const openDetail = session => {
    selectedItem.value = session
    detailDialog.open(session?.id)
  }

  const closeDetail = () => {
    detailDialog.close()
    selectedItem.value = null
  }

  const openDelete = (item, type = 'program') => {
    selectedItem.value = item
    deleteType.value = type
    deleteDialog.open(item?.id)
  }

  const closeDelete = () => {
    deleteDialog.close()
    selectedItem.value = null
  }

  return {
    programDialog,
    sessionDialog,
    enrollDialog,
    detailDialog,
    deleteDialog,
    selectedItem,
    deleteType,
    openProgram,
    closeProgram,
    openSession,
    closeSession,
    openEnroll,
    closeEnroll,
    openDetail,
    closeDetail,
    openDelete,
    closeDelete,
  }
}
