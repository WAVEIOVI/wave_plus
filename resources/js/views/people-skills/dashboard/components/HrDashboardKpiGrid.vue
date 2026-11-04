<script setup>
import { WpKpiCard, WpKpiGroup } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@/plugins/casl/composables/useAbility'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()
const ability = useAbility()

const headcount = computed(() => props.stats?.headcount ?? {})
const attendance = computed(() => props.stats?.today_attendance ?? {})
const leave = computed(() => props.stats?.leave ?? props.stats?.leaves ?? {})
const workforce = computed(() => props.stats?.workforce ?? {})
const recruitment = computed(() => props.stats?.recruitment ?? {})
const certifications = computed(() => props.stats?.certifications ?? {})
const fleet = computed(() => props.stats?.fleet ?? {})
const appraisals = computed(() => props.stats?.appraisals ?? {})

const definitions = computed(() => [
  {
    key: 'employees',
    subject: 'employees',
    title: t('hr.dashboard.active_employees', 'Active Employees'),
    value: headcount.value.active ?? 0,
    icon: 'tabler-users',
    color: 'primary',
    route: { name: 'people-skills-employees-list', query: { status: 'active' } },
    trend: {
      direction: 'up',
      value: `${headcount.value.total ?? 0}`,
      label: t('hr.dashboard.total_headcount', 'total headcount'),
    },
  },
  {
    key: 'attendance',
    subject: 'attendance',
    title: t('hr.dashboard.today_attendance', 'Today Attendance'),
    value: `${attendance.value.rate ?? 0}%`,
    icon: 'tabler-clock-check',
    color: 'success',
    route: { name: 'people-skills-attendance-list' },
    trend: {
      direction: 'up',
      value: `${attendance.value.present ?? 0}`,
      label: t('hr.dashboard.present_today', 'present today'),
    },
  },
  {
    key: 'leave',
    subject: 'leave',
    title: t('hr.dashboard.pending_leave_requests', 'Pending Leaves'),
    value: leave.value.pending_approval ?? 0,
    icon: 'tabler-calendar-time',
    color: 'warning',
    route: { name: 'people-skills-time-off-list', query: { status: 'submitted' } },
    trend: {
      direction: (leave.value.on_leave_today ?? 0) > 0 ? 'up' : 'down',
      value: `${leave.value.on_leave_today ?? 0}`,
      label: t('hr.dashboard.on_leave_today', 'on leave today'),
    },
  },
  {
    key: 'workforce',
    subject: 'workforce',
    title: t('hr.dashboard.workforce_scheduled', 'Workforce Scheduled'),
    value: workforce.value.total_scheduled ?? 0,
    icon: 'tabler-calendar-stats',
    color: 'info',
    route: { name: 'people-skills-workforce-list' },
    trend: {
      direction: 'up',
      value: `${workforce.value.coverage_rate ?? 0}%`,
      label: t('hr.dashboard.coverage_rate', 'coverage'),
    },
  },
  {
    key: 'recruitment',
    subject: 'recruitment',
    title: t('hr.dashboard.open_positions', 'Open Vacancies'),
    value: recruitment.value.open_positions ?? 0,
    icon: 'tabler-briefcase',
    color: 'primary',
    route: { name: 'people-skills-recruitment-list' },
    trend: {
      direction: 'up',
      value: `${recruitment.value.candidates_count ?? 0}`,
      label: t('hr.dashboard.active_candidates', 'candidates'),
    },
  },
  {
    key: 'skills',
    subject: 'skills',
    title: t('hr.dashboard.certifications_alerts', 'Expiring Certs'),
    value: (certifications.value.expiring_30_days ?? 0) + (certifications.value.expired ?? 0),
    icon: 'tabler-certificate',
    color: (certifications.value.expired ?? 0) > 0 ? 'error' : 'warning',
    route: { name: 'people-skills-skills-list' },
    trend: {
      direction: (certifications.value.expired ?? 0) > 0 ? 'down' : 'up',
      value: `${certifications.value.expired ?? 0}`,
      label: t('hr.dashboard.expired_certs', 'expired'),
    },
  },
  {
    key: 'fleet',
    subject: 'fleet',
    title: t('fleet.vehicles_title', 'Fleet Vehicles'),
    value: fleet.value.total_vehicles ?? 0,
    icon: 'tabler-car',
    color: 'secondary',
    route: { name: 'people-skills-vehicles-list' },
    trend: {
      direction: (fleet.value.total_alerts ?? 0) > 0 ? 'down' : 'up',
      value: `${fleet.value.total_alerts ?? 0}`,
      label: t('fleet.needs_review', 'alerts'),
    },
  },
  {
    key: 'appraisals',
    subject: 'appraisals',
    title: t('hr.dashboard.appraisals_pending', 'Pending Reviews'),
    value: (appraisals.value.pending_reviews ?? 0) + (appraisals.value.pending_acknowledgement ?? 0),
    icon: 'tabler-award',
    color: 'warning',
    route: { name: 'people-skills-performance-appraisals' },
    trend: {
      direction: 'up',
      value: `${appraisals.value.average_score ?? 0} / 5`,
      label: t('hr.dashboard.avg_score', 'avg score'),
    },
  },
])

const cards = computed(() => {
  return definitions.value.filter(def => {
    return ability.can('read', def.subject)
      || ability.can('read', 'PeopleSkills')
      || ability.can('view', 'PeopleSkills')
  })
})
</script>

<template>
  <WpKpiGroup
    :item-count="cards.length"
    :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
  >
    <template #default="{ getColProps }">
      <VCol
        v-for="(card, index) in cards"
        :key="card.key"
        v-bind="getColProps(index)"
      >
        <WpKpiCard
          :title="card.title"
          :value="card.value"
          :icon="card.icon"
          :icon-color="card.color"
          :trend="card.trend"
          :loading="loading"
          :to="card.route"
        />
      </VCol>
    </template>
  </WpKpiGroup>
</template>
