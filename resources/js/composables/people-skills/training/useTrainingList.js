import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { useTrainingStore } from '@/stores/useTrainingStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useTrainingList() {
  const store = useTrainingStore()
  const employees = useEmployeeStore()
  const { t, locale } = useI18n()
  const currentTab = ref('sessions')
  const search = ref('')

  const tabs = computed(() => [
    { title: t('training.tabs.sessions', 'Sessions'), value: 'sessions', icon: 'tabler-calendar-event' },
    { title: t('training.tabs.programs', 'Programs'), value: 'programs', icon: 'tabler-school' },
  ])

  const formatDate = value => {
    if (!value) return '—'
    return new Intl.DateTimeFormat(locale.value || undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(new Date(value))
  }

  const statusColor = value => ({
    scheduled: 'info',
    in_progress: 'primary',
    completed: 'success',
    cancelled: 'secondary',
    enrolled: 'info',
    attended: 'success',
    failed: 'error',
  }[value] || 'default')

  const sessionHeaders = computed(() => [
    { title: t('training.col.session', 'Session Code'), key: 'session', sortable: false, minWidth: '160px' },
    { title: t('training.col.program', 'Program'), key: 'program', sortable: false, minWidth: '180px' },
    { title: t('training.col.dates', 'Dates'), key: 'dates', sortable: false, minWidth: '180px' },
    { title: t('training.col.participants', 'Participants'), key: 'participants', sortable: false, width: '130px' },
    { title: t('training.col.status', 'Status'), key: 'status', sortable: false, width: '130px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  const programHeaders = computed(() => [
    { title: t('training.col.program', 'Program'), key: 'name', sortable: false, minWidth: '200px' },
    { title: t('training.col.provider', 'Provider'), key: 'provider', sortable: false, minWidth: '150px' },
    { title: t('training.col.duration', 'Duration'), key: 'duration_hours', sortable: false, width: '120px' },
    { title: t('training.col.sessions_count', 'Sessions'), key: 'sessions_count', sortable: false, width: '120px' },
    { title: t('training.col.status', 'Status'), key: 'is_active', sortable: false, width: '120px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  const kpis = computed(() => {
    const totalPrograms = store.programs.length
    const scheduledSessions = store.sessions.filter(s => s.status === 'scheduled').length
    const totalParticipants = store.sessions.reduce((sum, s) => sum + (s.participants?.length || 0), 0)
    const completedSessions = store.sessions.filter(s => s.status === 'completed').length

    return [
      { title: t('training.kpi.programs', 'Programs'), value: totalPrograms, icon: 'tabler-school', color: 'primary' },
      { title: t('training.kpi.scheduled_sessions', 'Scheduled Sessions'), value: scheduledSessions, icon: 'tabler-calendar-event', color: 'info' },
      { title: t('training.kpi.participants', 'Participants'), value: totalParticipants, icon: 'tabler-users', color: 'warning' },
      { title: t('training.kpi.completed', 'Completed'), value: completedSessions, icon: 'tabler-circle-check', color: 'success' },
    ]
  })

  const filteredSessions = computed(() => {
    if (!search.value) return store.sessions
    const q = search.value.toLowerCase()
    return store.sessions.filter(s =>
      s.session_code?.toLowerCase().includes(q) ||
      (typeof s.trainer === 'string' && s.trainer.toLowerCase().includes(q)),
    )
  })

  const filteredPrograms = computed(() => {
    if (!search.value) return store.programs
    const q = search.value.toLowerCase()
    return store.programs.filter(p =>
      p.code?.toLowerCase().includes(q) ||
      p.category?.toLowerCase().includes(q),
    )
  })

  const loadData = async () => {
    await store.load()
    if (employees.list.length === 0) {
      await employees.fetchList({ per_page: 100, status: 'active' })
    }
  }

  onMounted(() => {
    loadData()
  })

  return {
    store,
    employees,
    currentTab,
    tabs,
    search,
    sessionHeaders,
    programHeaders,
    filteredSessions,
    filteredPrograms,
    kpis,
    formatDate,
    statusColor,
    loadData,
  }
}
