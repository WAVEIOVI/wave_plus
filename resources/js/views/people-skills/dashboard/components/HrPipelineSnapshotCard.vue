<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  recruitment: {
    type: Object,
    default: () => ({
      open_positions: 0,
      candidates_count: 0,
      interviews_scheduled: 0,
      offers_extended: 0,
      by_stage: {},
    }),
  },
  training: {
    type: Object,
    default: () => ({
      upcoming_sessions: 0,
      in_progress: 0,
      active_programs: 0,
      enrolled_employees: 0,
    }),
  },
  appraisals: {
    type: Object,
    default: () => ({
      active_cycles: 0,
      pending_reviews: 0,
      pending_acknowledgement: 0,
      average_score: 0,
    }),
  },
  upcomingSession: {
    type: Object,
    default: null,
  },
  loading: { type: Boolean, default: false },
})

const { locale } = useI18n()
const router = useRouter()

const recruitmentRows = computed(() => [
  { key: 'open_positions', label: 'hr.dashboard.open_positions', icon: 'tabler-briefcase', color: 'info', value: props.recruitment.open_positions || 0, route: { name: 'people-skills-recruitment-list', query: { tab: 'openings' } } },
  { key: 'candidates_count', label: 'hr.dashboard.active_candidates', icon: 'tabler-users', color: 'primary', value: props.recruitment.candidates_count || 0, route: { name: 'people-skills-recruitment-list', query: { tab: 'candidates' } } },
  { key: 'interviews_scheduled', label: 'hr.dashboard.interviews_scheduled', icon: 'tabler-calendar-event', color: 'warning', value: props.recruitment.interviews_scheduled || 0, route: { name: 'people-skills-recruitment-list', query: { tab: 'interviews' } } },
  { key: 'offers_extended', label: 'hr.dashboard.offers_extended', icon: 'tabler-file-check', color: 'success', value: props.recruitment.offers_extended || 0, route: { name: 'people-skills-recruitment-list', query: { tab: 'candidates', stage: 'offer' } } },
])

const trainingRows = computed(() => [
  { key: 'upcoming_sessions', label: 'hr.dashboard.upcoming_sessions', icon: 'tabler-calendar-plus', color: 'info', value: props.training.upcoming_sessions || 0, route: { name: 'people-skills-training-list', query: { tab: 'sessions' } } },
  { key: 'in_progress', label: 'hr.dashboard.training_in_progress', icon: 'tabler-progress', color: 'warning', value: props.training.in_progress || 0, route: { name: 'people-skills-training-list', query: { tab: 'sessions', status: 'in_progress' } } },
])

const formatSessionDateValue = value => {
  if (!value) return null

  const rawValue = String(value)
  const date = new Date(rawValue)
  if (Number.isNaN(date.getTime())) return null

  const isDateOnly = !rawValue.includes('T') || /T00:00(?::00(?:\.0+)?)?(?:Z|\+00:00)?$/.test(rawValue)

  const options = isDateOnly
    ? { dateStyle: 'medium', timeZone: 'UTC' }
    : { dateStyle: 'medium', timeStyle: 'short' }

  return new Intl.DateTimeFormat(locale.value, options).format(date)
}

const formatSessionDate = session => {
  const startDate = formatSessionDateValue(session?.start_date)
  if (!startDate) return '—'

  const endDate = formatSessionDateValue(session.end_date)
  if (!endDate || endDate === startDate) return startDate

  return `${startDate} → ${endDate}`
}

const sessionName = computed(() =>
  localizedValue(
    props.upcomingSession?.training_program?.name
    || props.upcomingSession?.program?.name,
    '',
  ),
)

const sessionLocation = computed(() => localizedValue(props.upcomingSession?.location, ''))

const navigate = route => {
  if (route) {
    router.push(route)
  }
}
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem class="hr-dashboard-card-header">
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-user-search" />
        </VAvatar>
      </template>

      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.pipeline_title', 'Talent & Development') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.pipeline_subtitle', 'Recruitment pipeline, training, and appraisals') }}
      </VCardSubtitle>
      <template #append>
        <VBtn
          size="small"
          variant="text"
          class="hr-dashboard-card-action"
          :to="{ name: 'people-skills-recruitment-list' }"
        >
          {{ $t('hr.dashboard.view_all', 'Recruitment') }}
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@3"
        class="bg-transparent"
      />

      <template v-else>
        <!-- ─── Recruitment ─────────────────────────────── -->
        <div class="d-flex align-center justify-space-between mb-2">
          <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
            {{ $t('hr.dashboard.recruitment_pipeline', 'Recruitment Pipeline') }}
          </span>
          <span class="text-caption font-weight-semibold text-primary">
            {{ recruitment.open_positions }} {{ $t('hr.dashboard.openings', 'Openings') }}
          </span>
        </div>

        <div class="d-flex flex-column gap-y-2 mb-4">
          <div
            v-for="row in recruitmentRows"
            :key="row.key"
            class="d-flex align-center justify-space-between pa-1.5 rounded border cursor-pointer hover-item"
            @click="navigate(row.route)"
          >
            <span class="d-flex align-center gap-2">
              <VAvatar
                size="26"
                :color="row.color"
                variant="tonal"
              >
                <VIcon
                  :icon="row.icon"
                  size="15"
                />
              </VAvatar>
              <span class="text-body-2 font-weight-medium">{{ $t(row.label) }}</span>
            </span>
            <span class="font-weight-bold text-body-2">{{ row.value }}</span>
          </div>
        </div>

        <VDivider class="mb-4" />

        <!-- ─── Training ────────────────────────────────── -->
        <div class="d-flex align-center justify-space-between mb-2">
          <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
            {{ $t('hr.dashboard.training_snapshot', 'Training & Programs') }}
          </span>
          <VBtn
            size="x-small"
            variant="text"
            color="primary"
            :to="{ name: 'people-skills-training-list' }"
          >
            {{ $t('hr.nav.training', 'Training') }}
          </VBtn>
        </div>

        <div class="d-flex flex-column gap-y-2 mb-4">
          <div
            v-for="row in trainingRows"
            :key="row.key"
            class="d-flex align-center justify-space-between pa-1.5 rounded border cursor-pointer hover-item"
            @click="navigate(row.route)"
          >
            <span class="d-flex align-center gap-2">
              <VAvatar
                size="26"
                :color="row.color"
                variant="tonal"
              >
                <VIcon
                  :icon="row.icon"
                  size="15"
                />
              </VAvatar>
              <span class="text-body-2 font-weight-medium">{{ $t(row.label) }}</span>
            </span>
            <span class="font-weight-bold text-body-2">{{ row.value }}</span>
          </div>
        </div>

        <!-- ─── Next Scheduled Session ──────────────────── -->
        <div
          v-if="upcomingSession"
          class="pa-3 rounded border mb-4 cursor-pointer hover-item"
          @click="navigate({ name: 'people-skills-training-list', query: { tab: 'sessions' } })"
        >
          <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-1">
            {{ $t('hr.dashboard.next_training_session', 'Next Training Session') }}
          </div>
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ sessionName || $t('hr.dashboard.training_session', 'Scheduled Training Session') }}
          </div>
          <div class="text-caption text-medium-emphasis mb-2">
            {{ formatSessionDate(upcomingSession) }}
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ upcomingSession.status }}
            </VChip>
            <VChip
              v-if="sessionLocation"
              size="small"
              variant="outlined"
            >
              <VIcon
                icon="tabler-map-pin"
                start
                size="12"
              />
              {{ sessionLocation }}
            </VChip>
          </div>
        </div>

        <!-- ─── Appraisals Snapshot ──────────────────────── -->
        <VDivider class="mb-3" />
        <div
          class="pa-3 rounded border cursor-pointer hover-item"
          @click="navigate({ name: 'people-skills-performance-appraisals' })"
        >
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                color="warning"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-award"
                  size="16"
                />
              </VAvatar>
              <div>
                <div class="text-subtitle-2 font-weight-bold">
                  {{ $t('hr.dashboard.performance_reviews', 'Performance Reviews') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ (appraisals.pending_reviews ?? 0) + (appraisals.pending_acknowledgement ?? 0) }} {{ $t('hr.dashboard.pending_evaluations', 'pending evaluation / sign-off') }}
                </div>
              </div>
            </div>
            <VChip
              color="warning"
              size="small"
              label
              variant="tonal"
            >
              ★ {{ appraisals.average_score ?? 0 }} / 5
            </VChip>
          </div>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hover-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  transition: all 0.2s ease;
}
</style>
