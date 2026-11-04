import { useHrStore } from '@/stores/hrStore'
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
  let requestId = 0

  const fetchDashboard = async () => {
    const currentRequest = ++requestId

    loading.value = true
    error.value = null

    try {
      await hrStore.fetchDashboardStats()

      if (currentRequest !== requestId) return

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
  const kpis = computed(() => stats.value.kpis || {})
  const headcount = computed(() => stats.value.headcount || {})
  const attendance = computed(() => stats.value.today_attendance || {})
  const leave = computed(() => stats.value.leave || stats.value.leaves || {})
  const workforce = computed(() => stats.value.workforce || {})
  const recruitment = computed(() => stats.value.recruitment || {})
  const training = computed(() => stats.value.training || {})
  const certifications = computed(() => stats.value.certifications || {})
  const fleet = computed(() => stats.value.fleet || {})
  const appraisals = computed(() => stats.value.appraisals || {})
  const recentActivity = computed(() => stats.value.recent_activity || [])

  return {
    loading,
    error,
    updatedAt,
    stats,
    kpis,
    headcount,
    attendance,
    leave,
    workforce,
    recruitment,
    training,
    certifications,
    fleet,
    appraisals,
    recentActivity,
    fetchDashboard,
  }
}
