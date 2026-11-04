/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { $api } from '@/utils/api'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useRecruitmentActions({ listPage, dialogs }) {
  const recruitmentStore = useRecruitmentStore()
  const { showMessage } = useAppSnackbar()
  const { t } = useI18n()

  const stageUpdatingId = ref(null)
  const isSubmitting = ref(false)
  const isDeleting = ref(false)
  const isSendingEmail = ref(false)

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

  const handleInterviewSubmit = async payload => {
    isSubmitting.value = true
    try {
      if (dialogs.selectedInterview?.value?.id) {
        await recruitmentStore.updateInterview(dialogs.selectedInterview.value.id, payload)
        showMessage(t('recruitment.interview_evaluated', 'Interview evaluation saved.'), 'success')
      } else if (dialogs.selectedCandidate?.value?.id) {
        await recruitmentStore.scheduleInterview(dialogs.selectedCandidate.value.id, payload)
        showMessage(t('recruitment.interview_scheduled', 'Interview scheduled and candidate advanced.'), 'success')
      }
      dialogs.closeInterview()
      listPage.refresh()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.interview_schedule_error', 'Failed to record interview.'), 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const handleSendCandidateEmail = async ({ to, cc, subject, message }) => {
    isSendingEmail.value = true
    try {
      await $api('/communications/email/messages', {
        method: 'POST',
        body: {
          to: Array.isArray(to) ? to : [to],
          cc: Array.isArray(cc) ? cc : (cc ? [cc] : []),
          subject,
          body: message,
          business_record_type: 'hr_candidate',
          business_record_id: dialogs.candidateToEmail.value?.id,
        },
      })
      showMessage(t('recruitment.email_sent', 'Email dispatched successfully.'), 'success')
      dialogs.closeEmail()
    } catch (err) {
      showMessage(err?.data?.message || t('recruitment.email_failed', 'Failed to send email.'), 'error')
    } finally {
      isSendingEmail.value = false
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
    isSendingEmail,
    handleStageChange,
    handleCreateCandidate,
    handleInterviewSubmit,
    handleSendCandidateEmail,
    handleDeleteCandidate,
  }
}
