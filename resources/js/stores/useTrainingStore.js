import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useTrainingStore = defineStore('training', () => {
  const programs = ref([])
  const sessions = ref([])
  const loading = ref(false)
  const error = ref(null)

  const load = async () => {
    loading.value = true
    error.value = null
    try {
      const [programResponse, sessionResponse] = await Promise.all([
        $api('/hr/training/programs', { method: 'GET' }),
        $api('/hr/training/sessions', { method: 'GET' }),
      ])

      programs.value = programResponse.data || []
      sessions.value = sessionResponse.data || []
    } catch (err) { error.value = err?.data?.message || err?.message || 'Unable to load training.' } finally { loading.value = false }
  }

  const createProgram = async payload => {
    const response = await $api('/hr/training/programs', { method: 'POST', body: payload })

    await load()
    
    return response.data
  }

  const createSession = async payload => {
    const response = await $api('/hr/training/sessions', { method: 'POST', body: payload })

    await load()
    
    return response.data
  }

  const enroll = async (sessionId, employeeId) => {
    const response = await $api(`/hr/training/sessions/${sessionId}/enroll`, { method: 'POST', body: { ['employee_id']: employeeId } })

    await load()
    
    return response.data
  }

  const complete = async (participantId, payload) => {
    const response = await $api(`/hr/training/participants/${participantId}/complete`, { method: 'POST', body: payload })

    await load()
    
    return response.data
  }

  const batchAttendance = async (sessionId, participants) => {
    const response = await $api(`/hr/training/sessions/${sessionId}/batch-attendance`, {
      method: 'POST',
      body: { participants },
    })

    await load()

    return response.data
  }

  const updateProgram = async (id, payload) => {
    const response = await $api(`/hr/training/programs/${id}`, { method: 'PUT', body: payload })

    await load()
    
    return response.data
  }

  const destroyProgram = async id => {
    await $api(`/hr/training/programs/${id}`, { method: 'DELETE' })
    await load()
  }

  const updateSession = async (id, payload) => {
    const response = await $api(`/hr/training/sessions/${id}`, { method: 'PUT', body: payload })

    await load()
    
    return response.data
  }

  const destroySession = async id => {
    await $api(`/hr/training/sessions/${id}`, { method: 'DELETE' })
    await load()
  }

  const removeParticipant = async participantId => {
    await $api(`/hr/training/participants/${participantId}`, { method: 'DELETE' })
    await load()
  }

  return {
    programs, sessions, loading, error, load,
    createProgram, updateProgram, destroyProgram,
    createSession, updateSession, destroySession,
    enroll, complete, batchAttendance, removeParticipant,
  }
})
