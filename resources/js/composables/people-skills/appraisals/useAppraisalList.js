/* eslint-disable camelcase */
import { useAppraisalStore } from '@/stores/useAppraisalStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { useHrStore } from '@/stores/hrStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useAppraisalList() {
  const store = useAppraisalStore()
  const hrStore = useHrStore()
  const employeeStore = useEmployeeStore()
  const { t, locale } = useI18n()

  const currentTab = ref('cycles')
  const search = ref('')
  const selectedCycleFilter = ref(null)

  const tabs = computed(() => [
    { title: t('appraisals.cycles', 'Review Cycles'), value: 'cycles', icon: 'tabler-calendar-event' },
    { title: t('appraisals.appraisals_list', 'Employee Appraisals'), value: 'appraisals', icon: 'tabler-file-analytics' },
  ])

  const formatDate = value => {
    if (!value) return '—'
    
    return new Intl.DateTimeFormat(locale.value || undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(new Date(value))
  }

  const cycleColor = status => ({
    draft: 'secondary',
    active: 'success',
    review: 'warning',
    completed: 'info',
  }[status] || 'default')

  const appraisalColor = status => ({
    draft: 'secondary',
    submitted: 'info',
    reviewed: 'warning',
    acknowledged: 'success',
  }[status] || 'default')

  const cycleHeaders = computed(() => [
    { title: t('common.name', 'Cycle Name'), key: 'name', sortable: false, minWidth: '200px' },
    { title: t('appraisals.type', 'Cycle Type'), key: 'cycle_type', sortable: false, minWidth: '120px' },
    { title: t('appraisals.period', 'Period'), key: 'period', sortable: false, minWidth: '180px' },
    { title: t('appraisals.reviews', 'Reviews'), key: 'appraisals_count', sortable: false, width: '100px' },
    { title: t('common.status', 'Status'), key: 'status', sortable: false, width: '130px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  const appraisalHeaders = computed(() => [
    { title: t('employees.employee', 'Employee'), key: 'employee', sortable: false, minWidth: '200px' },
    { title: t('appraisals.cycle', 'Cycle'), key: 'cycle', sortable: false, minWidth: '160px' },
    { title: t('appraisals.reviewer', 'Reviewer'), key: 'reviewer', sortable: false, minWidth: '160px' },
    { title: t('appraisals.score', 'Score'), key: 'overall_score', sortable: false, width: '120px' },
    { title: t('common.status', 'Status'), key: 'status', sortable: false, width: '140px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  const kpis = computed(() => {
    if (store.kpis) {
      return [
        { title: t('appraisals.kpi.total_cycles', 'Total Cycles'), value: store.kpis.total_cycles, icon: 'tabler-calendar', color: 'primary' },
        { title: t('appraisals.kpi.active_cycles', 'Active Cycles'), value: store.kpis.active_cycles, icon: 'tabler-bolt', color: 'success' },
        { title: t('appraisals.kpi.total_reviews', 'Evaluations'), value: store.kpis.total_appraisals, icon: 'tabler-file-analytics', color: 'info' },
        { title: t('appraisals.kpi.completed_reviews', 'Completed'), value: store.kpis.completed_appraisals, icon: 'tabler-circle-check', color: 'warning' },
      ]
    }

    const totalCycles = store.cycles.length
    const activeCycles = store.cycles.filter(c => c.status === 'active').length
    const totalAppraisals = store.appraisals.length
    const completedAppraisals = store.appraisals.filter(a => a.status === 'acknowledged').length

    return [
      { title: t('appraisals.kpi.total_cycles', 'Total Cycles'), value: totalCycles, icon: 'tabler-calendar', color: 'primary' },
      { title: t('appraisals.kpi.active_cycles', 'Active Cycles'), value: activeCycles, icon: 'tabler-bolt', color: 'success' },
      { title: t('appraisals.kpi.total_reviews', 'Evaluations'), value: totalAppraisals, icon: 'tabler-file-analytics', color: 'info' },
      { title: t('appraisals.kpi.completed_reviews', 'Completed'), value: completedAppraisals, icon: 'tabler-circle-check', color: 'warning' },
    ]
  })

  const filteredCycles = computed(() => {
    if (!search.value) return store.cycles
    const q = search.value.toLowerCase()

    return store.cycles.filter(c =>
      c.name?.toLowerCase().includes(q) ||
      c.cycle_type?.toLowerCase().includes(q),
    )
  })

  const filteredAppraisals = computed(() => {
    let list = store.appraisals
    if (selectedCycleFilter.value) {
      list = list.filter(a => a.appraisal_cycle_id === selectedCycleFilter.value)
    }
    if (search.value) {
      const q = search.value.toLowerCase()

      list = list.filter(a =>
        a.employee?.full_name?.toLowerCase().includes(q) ||
        a.reviewer?.name?.toLowerCase().includes(q),
      )
    }

    return list
  })

  const loadData = async () => {
    await Promise.allSettled([
      store.fetchCycles(),
      store.fetchAppraisals(),
      store.fetchKpis(),
      hrStore.fetchEmployeesMeta(),
      employeeStore.fetchList({ per_page: 100 }),
    ])
  }

  onMounted(() => {
    loadData()
  })

  return {
    store,
    hrStore,
    employeeStore,
    currentTab,
    tabs,
    search,
    selectedCycleFilter,
    cycleHeaders,
    appraisalHeaders,
    filteredCycles,
    filteredAppraisals,
    kpis,
    formatDate,
    cycleColor,
    appraisalColor,
    loadData,
  }
}
