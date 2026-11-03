import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useRecruitmentActions({ listPage, dialogs }) {
  const recruitmentStore = useRecruitmentStore()
  const { showMessage } = useAppSnackbar()
  const { t } = useI18n()

  const stageUpdatingId = ref(null)
  const isSubmitting = ref(false)
  const isDeleting = ref(false)

  const handleStageChange = async (id, stage) => {
    stageUpdatingId.value = id
    try {
      await recruitmentStore.updateStage(id, stage)
      showMessage(t('recruitment.stage_updated', 'Candidate stage updated.'), 'success')
      listPage.refresh()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.stage_error', 'Failed to update candidate stage.'), 'error')
    } finally {
      stageUpdatingId.value = null
    }
  }

  const handleCreateCandidate = async payload => {
    isSubmitting.value = true
    try {
      await recruitmentStore.create(payload)
      showMessage(t('recruitment.candidate_created', 'Candidate registered successfully.'), 'success')
      dialogs.createDialog.close()
      listPage.refresh()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.candidate_create_error', 'Failed to create candidate.'), 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const handleScheduleInterview = async (candidateId, payload) => {
    isSubmitting.value = true
    try {
      await recruitmentStore.scheduleInterview(candidateId, payload)
      showMessage(t('recruitment.interview_scheduled', 'Interview scheduled and candidate advanced.'), 'success')
      dialogs.closeInterview()
      listPage.refresh()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.interview_schedule_error', 'Failed to schedule interview.'), 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const handleDeleteCandidate = async () => {
    const candidate = dialogs.selectedCandidate.value
    if (!candidate) return

    isDeleting.value = true
    try {
      await recruitmentStore.destroy(candidate.id)
      showMessage(t('recruitment.candidate_deleted', 'Candidate removed successfully.'), 'success')
      dialogs.closeDelete()
      listPage.refresh()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.candidate_delete_error', 'Failed to delete candidate.'), 'error')
    } finally {
      isDeleting.value = false
    }
  }

  return {
    stageUpdatingId,
    isSubmitting,
    isDeleting,
    handleStageChange,
    handleCreateCandidate,
    handleScheduleInterview,
    handleDeleteCandidate,
  }
}
