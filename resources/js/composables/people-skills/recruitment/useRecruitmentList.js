/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useRecruitmentList() {
  const recruitmentStore = useRecruitmentStore()
  const { t } = useI18n()
  const openings = ref([])
  const openingsLoading = ref(false)

  const stageOptions = computed(() => [
    { title: t('recruitment.stage.applied', 'Applied'), value: 'applied' },
    { title: t('recruitment.stage.screening', 'Screening'), value: 'screening' },
    { title: t('recruitment.stage.interview', 'Interview'), value: 'interview' },
    { title: t('recruitment.stage.evaluated', 'Evaluated'), value: 'evaluated' },
    { title: t('recruitment.stage.selected', 'Selected'), value: 'selected' },
    { title: t('recruitment.stage.offer', 'Offer'), value: 'offer' },
    { title: t('recruitment.stage.rejected', 'Rejected'), value: 'rejected' },
  ])

  const stageColor = s => ({
    applied: 'info',
    screening: 'primary',
    interview: 'warning',
    offer: 'success',
    selected: 'success',
    evaluated: 'primary',
    rejected: 'error',
  }[s] || 'default')

  const openingOptions = computed(() =>
    openings.value.map(opening => ({ title: opening.title, value: opening.id })),
  )

  const loadOpenings = async () => {
    openingsLoading.value = true
    try {
      openings.value = await recruitmentStore.fetchOpenings()
    } catch (err) {
      console.warn('[useRecruitmentList] Failed to load job openings:', err?.message)
    } finally {
      openingsLoading.value = false
    }
  }

  const listPage = useListPage({
    fetchFn: params => recruitmentStore.fetchList(params),
    initialFilters: { stage: null, opening_id: null },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
    immediate: true,
  })

  const headers = computed(() => [
    { title: t('recruitment.col.applicant', 'Applicant'), key: 'applicant', sortable: false, minWidth: '200px' },
    { title: t('recruitment.col.position', 'Position'), key: 'position', sortable: false, minWidth: '160px' },
    { title: t('recruitment.col.applied', 'Applied'), key: 'created_at', sortable: true, minWidth: '140px' },
    { title: t('recruitment.col.stage', 'Stage'), key: 'status', sortable: true, width: '140px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  // KPIs computation
  const kpis = computed(() => {
    const items = recruitmentStore.list || []
    const total = recruitmentStore.totalItems || items.length
    const screeningCount = items.filter(c => c.status === 'screening').length
    const interviewCount = items.filter(c => c.status === 'interview').length
    const offerCount = items.filter(c => ['offer', 'selected'].includes(c.status)).length

    return {
      total,
      screeningCount,
      interviewCount,
      offerCount,
    }
  })

  onMounted(() => {
    loadOpenings()
  })

  return {
    recruitmentStore,
    listPage,
    headers,
    stageOptions,
    stageColor,
    openings,
    openingsLoading,
    openingOptions,
    kpis,
    loadOpenings,
  }
}
