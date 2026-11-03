import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useRecruitmentDialogs() {
  const createDialog = useDialog()
  const detailDialog = useDialog()
  const interviewDialog = useDialog()
  const deleteDialog = useDialog()
  const filterDialog = useDialog()
  const openingDialog = useDialog()

  const selectedCandidate = ref(null)

  const openDetail = candidate => {
    selectedCandidate.value = candidate
    detailDialog.open(candidate?.id)
  }

  const closeDetail = () => {
    detailDialog.close()
    selectedCandidate.value = null
  }

  const openInterview = candidate => {
    selectedCandidate.value = candidate
    interviewDialog.open(candidate?.id)
  }

  const closeInterview = () => {
    interviewDialog.close()
    selectedCandidate.value = null
  }

  const openDelete = candidate => {
    selectedCandidate.value = candidate
    deleteDialog.open(candidate?.id)
  }

  const closeDelete = () => {
    deleteDialog.close()
    selectedCandidate.value = null
  }

  return {
    createDialog,
    detailDialog,
    interviewDialog,
    deleteDialog,
    filterDialog,
    openingDialog,
    selectedCandidate,
    openDetail,
    closeDetail,
    openInterview,
    closeInterview,
    openDelete,
    closeDelete,
  }
}
