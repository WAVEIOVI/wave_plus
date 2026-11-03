import { useHrStore } from '@/stores/hrStore'
import { $api } from '@/utils/api'
import { computed, onMounted, ref } from 'vue'

/**
 * usePeopleSkillsDashboard
 * ─────────────────────────────────────────────────────────────────────────────
 * Composable managing data fetching, reactive stats, and state for the
 * People & Skills / HR module command center dashboard.
 */
export function usePeopleSkillsDashboard() {
  const hrStore = useHrStore()

  const loading = ref(false)
  const error = ref(null)
  const updatedAt = ref(null)
  const recentEmployees = ref([])
  const upcomingSession = ref(null)
  let requestId = 0

  const fetchDashboard = async () => {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null

    try {
      const [, employeesResponse, sessionsResponse] = await Promise.all([
        hrStore.fetchDashboardStats(),
        $api('/hr/employees', {
          method: 'GET',
          // eslint-disable-next-line camelcase
          params: { per_page: 6, status: 'active' },
        }),
        $api('/hr/training/sessions', {
          method: 'GET',
          // eslint-disable-next-line camelcase
          params: { status: 'scheduled', per_page: 1 },
        }),
      ])

      if (currentRequest !== requestId) return

      recentEmployees.value = employeesResponse?.data?.data || employeesResponse?.data || []
      upcomingSession.value = sessionsResponse?.data?.data?.[0] || sessionsResponse?.data?.[0] || null
      updatedAt.value = new Date()
    }
    catch (err) {
      if (currentRequest !== requestId) return
      console.error('Failed to load HR dashboard:', err)
      error.value = err?.data?.message || err?.message || 'Failed to load HR dashboard data.'
    }
    finally {
      if (currentRequest === requestId) {
        loading.value = false
      }
    }
  }

  onMounted(fetchDashboard)

  // ─── Stats Selectors ──────────────────────────────────────────
  const stats = computed(() => hrStore.dashboardStats || {})
  const headcount = computed(() => stats.value.headcount || {})
  const attendance = computed(() => stats.value.today_attendance || {})
  const leave = computed(() => stats.value.leave || stats.value.leaves || {})
  const recruitment = computed(() => stats.value.recruitment || {})
  const training = computed(() => stats.value.training || {})
  const certifications = computed(() => stats.value.certifications || {})

  return {
    loading,
    error,
    updatedAt,
    recentEmployees,
    upcomingSession,
    stats,
    headcount,
    attendance,
    leave,
    recruitment,
    training,
    certifications,
    fetchDashboard,
  }
}
