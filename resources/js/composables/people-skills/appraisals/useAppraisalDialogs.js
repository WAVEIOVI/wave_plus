import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useAppraisalDialogs() {
  const cycleDialog = useDialog()
  const reviewDialog = useDialog()
  const detailDialog = useDialog()
  const deleteDialog = useDialog()

  const selectedItem = ref(null)
  const deleteType = ref('cycle') // 'cycle' | 'appraisal'

  const openCycle = (item = null) => {
    selectedItem.value = item
    cycleDialog.open(item?.id)
  }

  const closeCycle = () => {
    cycleDialog.close()
    selectedItem.value = null
  }

  const openReview = (item = null) => {
    selectedItem.value = item
    reviewDialog.open(item?.id)
  }

  const closeReview = () => {
    reviewDialog.close()
    selectedItem.value = null
  }

  const openDetail = item => {
    selectedItem.value = item
    detailDialog.open(item?.id)
  }

  const closeDetail = () => {
    detailDialog.close()
    selectedItem.value = null
  }

  const openDelete = (item, type = 'cycle') => {
    selectedItem.value = item
    deleteType.value = type
    deleteDialog.open(item?.id)
  }

  const closeDelete = () => {
    deleteDialog.close()
    selectedItem.value = null
  }

  return {
    cycleDialog,
    reviewDialog,
    detailDialog,
    deleteDialog,
    selectedItem,
    deleteType,
    openCycle,
    closeCycle,
    openReview,
    closeReview,
    openDetail,
    closeDetail,
    openDelete,
    closeDelete,
  }
}
