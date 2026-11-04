import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useRecruitmentDialogs() {
  const createDialog = useDialog()
  const detailDialog = useDialog()
  const interviewDialog = useDialog()
  const deleteDialog = useDialog()
  const filterDialog = useDialog()
  const openingDialog = useDialog()
  const emailDialog = useDialog()
  const hireDialog = useDialog()

  const selectedCandidate = ref(null)
  const selectedInterview = ref(null)
  const candidateToEmail = ref(null)
  const candidateToHire = ref(null)

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
    selectedInterview.value = null
    interviewDialog.open(candidate?.id)
  }

  const openEvaluate = (candidate, interview) => {
    selectedCandidate.value = candidate
    selectedInterview.value = interview
    interviewDialog.open(candidate?.id)
  }

  const closeInterview = () => {
    interviewDialog.close()
    selectedInterview.value = null
  }

  const openEmail = candidate => {
    candidateToEmail.value = candidate
    emailDialog.open(candidate?.id)
  }

  const closeEmail = () => {
    emailDialog.close()
    candidateToEmail.value = null
  }

  const openHire = candidate => {
    candidateToHire.value = candidate
    hireDialog.open(candidate?.id)
  }

  const closeHire = () => {
    hireDialog.close()
    candidateToHire.value = null
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
    emailDialog,
    hireDialog,
    selectedCandidate,
    selectedInterview,
    candidateToEmail,
    candidateToHire,
    openDetail,
    closeDetail,
    openInterview,
    openEvaluate,
    closeInterview,
    openEmail,
    closeEmail,
    openHire,
    closeHire,
    openDelete,
    closeDelete,
  }
}
