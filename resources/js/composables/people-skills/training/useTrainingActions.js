import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useTrainingStore } from '@/stores/useTrainingStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useTrainingActions({ dialogs }) {
  const store = useTrainingStore()
  const { showMessage } = useAppSnackbar()
  const { t } = useI18n()

  const isSaving = ref(false)
  const isDeleting = ref(false)

  const handleSaveProgram = async payload => {
    isSaving.value = true
    try {
      if (dialogs.selectedItem.value?.id) {
        await store.updateProgram(dialogs.selectedItem.value.id, payload)
        showMessage(t('training.program_updated', 'Training program updated.'), 'success')
      } else {
        await store.createProgram(payload)
        showMessage(t('training.program_created', 'Training program created.'), 'success')
      }
      dialogs.closeProgram()
    } catch (err) {
      showMessage(err?.data?.message || t('training.program_error', 'Failed to save training program.'), 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  const handleSaveSession = async payload => {
    isSaving.value = true
    try {
      if (dialogs.selectedItem.value?.id) {
        await store.updateSession(dialogs.selectedItem.value.id, payload)
        showMessage(t('training.session_updated', 'Training session updated.'), 'success')
      } else {
        await store.createSession(payload)
        showMessage(t('training.session_scheduled', 'Training session scheduled.'), 'success')
      }
      dialogs.closeSession()
    } catch (err) {
      showMessage(err?.data?.message || t('training.session_error', 'Failed to save training session.'), 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  const handleEnroll = async (sessionId, employeeId) => {
    isSaving.value = true
    try {
      await store.enroll(sessionId, employeeId)
      showMessage(t('training.employee_enrolled', 'Employee enrolled successfully.'), 'success')
      dialogs.closeEnroll()
      if (dialogs.selectedItem.value?.id === sessionId) {
        dialogs.selectedItem.value = store.sessions.find(s => s.id === sessionId)
      }
    } catch (err) {
      showMessage(err?.data?.message || t('training.enroll_error', 'Failed to enroll employee.'), 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  const handleCompleteParticipant = async (participantId, payload) => {
    isSaving.value = true
    try {
      await store.complete(participantId, payload)
      showMessage(t('training.qualifications_synced', 'Training completed; qualifications were synchronized.'), 'success')
      if (dialogs.selectedItem.value?.id) {
        dialogs.selectedItem.value = store.sessions.find(s => s.id === dialogs.selectedItem.value.id)
      }
    } catch (err) {
      showMessage(err?.data?.message || t('training.complete_error', 'Failed to complete training.'), 'error')
    } finally {
      isSaving.value = false
    }
  }

  const handleDelete = async () => {
    const item = dialogs.selectedItem.value
    if (!item) return

    isDeleting.value = true
    try {
      if (dialogs.deleteType.value === 'program') {
        await store.destroyProgram(item.id)
        showMessage(t('training.program_deleted', 'Training program deleted.'), 'success')
      } else if (dialogs.deleteType.value === 'session') {
        await store.destroySession(item.id)
        showMessage(t('training.session_deleted', 'Training session deleted.'), 'success')
      } else if (dialogs.deleteType.value === 'participant') {
        await store.removeParticipant(item.id)
        showMessage(t('training.participant_removed', 'Participant removed from session.'), 'success')
        if (dialogs.detailDialog.isOpen.value && dialogs.selectedItem.value) {
          dialogs.selectedItem.value = store.sessions.find(s => s.id === dialogs.selectedItem.value.id)
        }
      }
      dialogs.closeDelete()
    } catch (err) {
      showMessage(err?.data?.message || t('training.delete_error', 'Failed to delete record.'), 'error')
    } finally {
      isDeleting.value = false
    }
  }

  return {
    isSaving,
    isDeleting,
    handleSaveProgram,
    handleSaveSession,
    handleEnroll,
    handleCompleteParticipant,
    handleDelete,
  }
}
