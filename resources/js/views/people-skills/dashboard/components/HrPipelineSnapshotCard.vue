<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/* eslint-disable camelcase */
const props = defineProps({
  recruitment: {
    type: Object,
    default: () => ({
      open_positions: 0,
      candidates_count: 0,
      interviews_scheduled: 0,
      offers_extended: 0,
    }),
  },
  training: {
    type: Object,
    default: () => ({
      upcoming_sessions: 0,
      in_progress: 0,
    }),
  },
  upcomingSession: {
    type: Object,
    default: null,
  },
  loading: { type: Boolean, default: false },
})
/* eslint-enable camelcase */

const { locale } = useI18n()

const recruitmentRows = computed(() => [
  { key: 'open_positions', label: 'hr.dashboard.open_positions', icon: 'tabler-briefcase', color: 'info', value: props.recruitment.open_positions || 0 },
  { key: 'candidates_count', label: 'hr.dashboard.active_candidates', icon: 'tabler-users', color: 'primary', value: props.recruitment.candidates_count || 0 },
  { key: 'interviews_scheduled', label: 'hr.dashboard.interviews_scheduled', icon: 'tabler-calendar-event', color: 'warning', value: props.recruitment.interviews_scheduled || 0 },
  { key: 'offers_extended', label: 'hr.dashboard.offers_extended', icon: 'tabler-file-check', color: 'success', value: props.recruitment.offers_extended || 0 },
])

const trainingRows = computed(() => [
  { key: 'upcoming_sessions', label: 'hr.dashboard.upcoming_sessions', icon: 'tabler-calendar-plus', color: 'info', value: props.training.upcoming_sessions || 0 },
  { key: 'in_progress', label: 'hr.dashboard.training_in_progress', icon: 'tabler-progress', color: 'warning', value: props.training.in_progress || 0 },
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
        {{ $t('hr.dashboard.pipeline_title') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.pipeline_subtitle') }}
      </VCardSubtitle>
      <template #append>
        <VBtn
          size="small"
          variant="text"
          class="hr-dashboard-card-action"
          :to="{ name: 'people-skills-recruitment-list' }"
        >
          {{ $t('hr.dashboard.view_all') }}
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
        <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-3">
          {{ $t('hr.dashboard.recruitment_pipeline') }}
        </div>

        <div class="d-flex flex-column gap-y-3 mb-5">
          <div
            v-for="row in recruitmentRows"
            :key="row.key"
            class="d-flex align-center justify-space-between"
          >
            <span class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                :color="row.color"
                variant="tonal"
              >
                <VIcon
                  :icon="row.icon"
                  size="16"
                />
              </VAvatar>
              <span class="text-body-2">{{ $t(row.label) }}</span>
            </span>
            <span class="font-weight-bold">{{ row.value }}</span>
          </div>
        </div>

        <VDivider class="mb-4" />

        <!-- ─── Training ────────────────────────────────── -->
        <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-3">
          {{ $t('hr.dashboard.training_snapshot') }}
        </div>

        <div class="d-flex flex-column gap-y-3">
          <div
            v-for="row in trainingRows"
            :key="row.key"
            class="d-flex align-center justify-space-between"
          >
            <span class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                :color="row.color"
                variant="tonal"
              >
                <VIcon
                  :icon="row.icon"
                  size="16"
                />
              </VAvatar>
              <span class="text-body-2">{{ $t(row.label) }}</span>
            </span>
            <span class="font-weight-bold">{{ row.value }}</span>
          </div>
        </div>

        <!-- ─── Next Scheduled Session ──────────────────── -->
        <div
          v-if="upcomingSession"
          class="pa-3 rounded bg-var-theme-background border mt-4"
        >
          <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-2">
            {{ $t('hr.dashboard.next_training_session') }}
          </div>
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ sessionName || $t('hr.dashboard.training_session') }}
          </div>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ formatSessionDate(upcomingSession) }}
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ sessionLocation || $t('hr.dashboard.location_tbc') }}
            </VChip>
            <VChip
              size="small"
              variant="tonal"
              color="secondary"
            >
              {{ upcomingSession.participants?.length || 0 }} /
              {{ upcomingSession.max_participants || '—' }}
            </VChip>
          </div>
        </div>

        <VAlert
          v-else
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          {{ $t('hr.dashboard.no_upcoming_training') }}
        </VAlert>

        <VBtn
          block
          variant="tonal"
          color="primary"
          size="small"
          class="mt-4"
          :to="{ name: 'people-skills-training-list' }"
        >
          {{ $t('hr.dashboard.open_training') }}
        </VBtn>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hr-dashboard-card-header :deep(.v-card-item__content) {
  min-inline-size: 0;
}

.hr-dashboard-card-action {
  max-inline-size: 100%;
  white-space: normal;
}

.hr-dashboard-card-action :deep(.v-btn__content) {
  white-space: normal;
  overflow-wrap: anywhere;
  text-align: end;
}
</style>
