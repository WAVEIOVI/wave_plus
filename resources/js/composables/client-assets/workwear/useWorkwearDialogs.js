import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useWorkwearDialogs() {
  const viewDialog = useDialog()
  const deleteDialog = useDialog()
  const addDialog = useDialog()

  const activeEntity = ref(null)
  const activeType = ref('item') // 'item' | 'model' | 'employee'

  const openView = (entity, type = 'item') => {
    activeEntity.value = entity
    activeType.value = type
    viewDialog.open(entity?.id || entity)
  }

  const closeView = () => {
    viewDialog.close()
    activeEntity.value = null
  }

  const openDelete = (entity, type = 'item') => {
    activeEntity.value = entity
    activeType.value = type
    deleteDialog.open(entity?.id || entity)
  }

  const closeDelete = () => {
    deleteDialog.close()
    activeEntity.value = null
  }

  const openAdd = (type = 'item') => {
    activeType.value = type
    addDialog.open()
  }

  const closeAdd = () => {
    addDialog.close()
  }

  return {
    viewDialog,
    deleteDialog,
    addDialog,
    activeEntity,
    activeType,
    openView,
    closeView,
    openDelete,
    closeDelete,
    openAdd,
    closeAdd,
  }
}
