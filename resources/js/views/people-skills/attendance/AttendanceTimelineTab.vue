<script setup>
/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { avatarText } from '@core/utils/formatters'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const { t, locale } = useI18n()
const { xs } = useDisplay()
const { showMessage } = useAppSnackbar()

// Local calendar date (toISOString() is UTC and returns "yesterday" just after midnight in UTC+1).
const localDate = (d = new Date()) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

// ── State ─────────────────────────────────────────────────────────────────
const selectedDate = ref(localDate())
const timelineData = ref([])
const isLoading = ref(false)
const search = ref('')
const nowMinutes = ref(0)

let nowTimer = null

// ── Load timeline ─────────────────────────────────────────────────────────
const loadTimeline = async () => {
  isLoading.value = true
  try {
    const res = await $api('/hr/attendances/timeline', {
      method: 'GET',
      params: { date: selectedDate.value },
    })

    timelineData.value = res.data || []
  } catch (err) {
    showMessage(err?.data?.message || t('attendance.timeline.load_error', 'Failed to load attendance timeline'), 'error')
  } finally {
    isLoading.value = false
  }
}

const updateNow = () => {
  const now = new Date()

  nowMinutes.value = now.getHours() * 60 + now.getMinutes()
}

onMounted(() => {
  loadTimeline()
  updateNow()
  nowTimer = setInterval(updateNow, 30000)
})

onBeforeUnmount(() => {
  if (nowTimer) clearInterval(nowTimer)
})

watch(selectedDate, value => {
  if (!value) {
    selectedDate.value = localDate()

    return
  }
  loadTimeline()
})

// ── Day navigation ────────────────────────────────────────────────────────
const isToday = computed(() => selectedDate.value === localDate())

const shiftDay = delta => {
  const d = new Date(`${selectedDate.value}T12:00:00`)

  d.setDate(d.getDate() + delta)
  selectedDate.value = localDate(d)
}

// ── Timeline visual range: 06:00 to 22:00 (16 hours) ──────────────────────
const START_MIN = 6 * 60 // 06:00 in minutes
const TOTAL_SPAN = 16 * 60 // 16 hours
const hourMarkers = [6, 8, 10, 12, 14, 16, 18, 20, 22]

// Phones only have room for a label every four hours.
const labelHours = computed(() => (xs.value ? [6, 10, 14, 18, 22] : hourMarkers))
const gridHours = computed(() => (xs.value ? labelHours.value : hourMarkers))

const toPercent = hour => ((hour * 60 - START_MIN) / TOTAL_SPAN) * 100

const timeToMinutes = timeStr => {
  if (!timeStr) return 0
  const [h, m] = timeStr.split(':').map(Number)

  return h * 60 + m
}

const shortTime = value => (value ? String(value).slice(0, 5) : '—')

const getSegmentStyle = seg => {
  const inMin = timeToMinutes(seg.in_time)
  let outMin = seg.out_time ? timeToMinutes(seg.out_time) : 22 * 60

  if (!seg.out_time && isToday.value) outMin = nowMinutes.value

  const clampedStart = Math.max(START_MIN, inMin)
  const clampedEnd = Math.min(START_MIN + TOTAL_SPAN, outMin)

  const startPercent = Math.max(0, ((clampedStart - START_MIN) / TOTAL_SPAN) * 100)
  const widthPercent = Math.max(2, ((clampedEnd - clampedStart) / TOTAL_SPAN) * 100)

  return {
    insetInlineStart: `${startPercent}%`,
    inlineSize: `${widthPercent}%`,
  }
}

// "Now" marker, only while looking at today and inside the visible range.
const nowPercent = computed(() => {
  if (!isToday.value) return null
  if (nowMinutes.value < START_MIN || nowMinutes.value > START_MIN + TOTAL_SPAN) return null

  return ((nowMinutes.value - START_MIN) / TOTAL_SPAN) * 100
})

// ── Rows ──────────────────────────────────────────────────────────────────
const isOnSite = row => (row.segments || []).some(seg => seg.is_ongoing)

const filteredRows = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) return timelineData.value

  return timelineData.value.filter(row =>
    `${row.employee?.full_name || ''} ${row.employee?.employee_number || ''}`.toLowerCase().includes(term),
  )
})

const onSiteCount = computed(() => timelineData.value.filter(isOnSite).length)

const formatMinutes = minutes => {
  if (minutes == null) return ''
  const h = Math.floor(minutes / 60)
  const m = Math.round(minutes % 60)

  if (!h) return `${m}m`

  return m ? `${h}h ${m}m` : `${h}h`
}

const segmentLabel = seg =>
  `${shortTime(seg.in_time)} - ${seg.out_time ? shortTime(seg.out_time) : t('attendance.timeline.active', 'Active')}`

const segmentTooltip = seg => {
  if (seg.is_ongoing) return t('attendance.timeline.active_session', 'Active Session')

  return formatMinutes(seg.duration_minutes)
    ? `${segmentLabel(seg)} · ${formatMinutes(seg.duration_minutes)}`
    : segmentLabel(seg)
}

const formatDateDisplay = dateStr => {
  const date = new Date(`${dateStr}T00:00:00`)

  return new Intl.DateTimeFormat(locale.value, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
}
</script>

<template>
  <div class="attendance-timeline-tab d-flex flex-column gap-4">
    <!-- Action bar -->
    <div class="attendance-timeline-tab__toolbar d-flex flex-wrap justify-space-between align-center gap-3">
      <div>
        <div class="text-h6">
          {{ formatDateDisplay(selectedDate) }}
        </div>
        <div class="d-flex flex-wrap align-center gap-2 mt-1">
          <VChip
            size="small"
            variant="tonal"
            prepend-icon="tabler-users"
          >
            {{ timelineData.length }} {{ $t('attendance.timeline.employees', 'employees') }}
          </VChip>
          <VChip
            v-if="isToday"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-map-pin"
          >
            {{ onSiteCount }} {{ $t('attendance.timeline.on_site', 'on site now') }}
          </VChip>
        </div>
      </div>

      <div class="attendance-timeline-tab__controls d-flex align-center flex-wrap gap-2">
        <div class="timeline-date-control d-flex align-center border rounded-lg bg-surface pa-1 gap-1">
          <VBtn
            icon="tabler-chevron-left"
            variant="text"
            density="compact"
            size="small"
            class="flip-in-rtl"
            :aria-label="$t('attendance.timeline.prev_day', 'Previous day')"
            @click="shiftDay(-1)"
          />
          <VBtn
            v-if="!isToday"
            variant="tonal"
            color="primary"
            size="small"
            class="px-2"
            @click="selectedDate = localDate()"
          >
            {{ $t('attendance.filter.today', 'Today') }}
          </VBtn>
          <div class="timeline-date-picker-wrap">
            <AppDateTimePicker
              v-model="selectedDate"
              :placeholder="$t('attendance.filter.date')"
              variant="plain"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-calendar"
              class="timeline-date-picker"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
            />
          </div>
          <VBtn
            icon="tabler-chevron-right"
            variant="text"
            density="compact"
            size="small"
            class="flip-in-rtl"
            :disabled="isToday"
            :aria-label="$t('attendance.timeline.next_day', 'Next day')"
            @click="shiftDay(1)"
          />
        </div>

        <VBtn
          icon="tabler-refresh"
          variant="tonal"
          color="secondary"
          density="comfortable"
          :loading="isLoading"
          :aria-label="$t('Refresh', 'Refresh')"
          @click="loadTimeline"
        />
      </div>
    </div>

    <VTextField
      v-if="timelineData.length > 4"
      v-model="search"
      :placeholder="$t('attendance.search_employees', 'Search employees...')"
      prepend-inner-icon="tabler-search"
      variant="outlined"
      density="compact"
      clearable
      hide-details
      class="attendance-timeline-tab__search"
      style="inline-size: 320px;"
    />

    <VCard variant="outlined">
      <VProgressLinear
        v-if="isLoading"
        indeterminate
        color="primary"
      />

      <VCardText class="pa-0">
        <div
          v-if="isLoading && timelineData.length === 0"
          class="pa-4"
        >
          <VSkeletonLoader type="list-item-avatar-two-line@4" />
        </div>
        <template v-else>
          <!-- Time ruler header (hidden on phones: each row carries its own) -->
          <div class="timeline-grid timeline-ruler border-b">
            <div class="text-caption font-weight-medium text-medium-emphasis">
              {{ $t('Employee') }}
            </div>
            <div class="timeline-track timeline-track--ruler">
              <span
                v-for="h in labelHours"
                :key="h"
                class="hour-label text-caption text-medium-emphasis"
                :style="{ insetInlineStart: `${toPercent(h)}%` }"
              >
                {{ String(h).padStart(2, '0') }}:00
              </span>
            </div>
          </div>

          <!-- Employee timeline rows -->
          <div v-if="filteredRows.length > 0">
            <div
              v-for="row in filteredRows"
              :key="row.employee?.id"
              class="timeline-grid timeline-row"
            >
              <!-- Staff Info -->
              <div class="d-flex align-center gap-3 overflow-hidden">
                <VBadge
                  dot
                  bordered
                  location="bottom end"
                  :color="isOnSite(row) ? 'success' : 'secondary'"
                  :model-value="isOnSite(row)"
                >
                  <VAvatar
                    size="36"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="row.employee?.avatar_url"
                      :src="row.employee.avatar_url"
                    />
                    <span
                      v-else
                      class="text-caption"
                    >
                      {{ avatarText(row.employee?.full_name || '?') }}
                    </span>
                  </VAvatar>
                </VBadge>
                <div class="overflow-hidden">
                  <div class="font-weight-semibold text-body-2 text-truncate">
                    {{ row.employee?.full_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis text-truncate">
                    {{ row.employee?.department || $t('attendance.timeline.staff', 'Staff') }} · {{ row.segments?.length || 0 }} {{ $t('attendance.timeline.sessions', 'sessions') }}
                  </div>
                </div>
              </div>

              <!-- Time Track -->
              <div class="timeline-track">
                <!-- Mobile-only hour labels -->
                <div
                  v-if="xs"
                  class="timeline-track__labels"
                >
                  <span
                    v-for="h in labelHours"
                    :key="h"
                    class="hour-label text-caption text-medium-emphasis"
                    :style="{ insetInlineStart: `${toPercent(h)}%` }"
                  >
                    {{ String(h).padStart(2, '0') }}
                  </span>
                </div>

                <div class="timeline-track__lane">
                  <span
                    v-for="h in gridHours"
                    :key="h"
                    class="track-grid-line"
                    :style="{ insetInlineStart: `${toPercent(h)}%` }"
                  />

                  <span
                    v-if="nowPercent !== null"
                    class="timeline-now"
                    :style="{ insetInlineStart: `${nowPercent}%` }"
                  />

                  <div
                    v-for="(seg, idx) in row.segments"
                    :key="idx"
                    v-tooltip="segmentTooltip(seg)"
                    class="timeline-segment text-caption font-weight-semibold"
                    :class="seg.is_ongoing ? 'segment-ongoing' : 'segment-completed'"
                    :style="getSegmentStyle(seg)"
                  >
                    <span class="segment-label text-truncate px-2">
                      {{ segmentLabel(seg) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!isLoading"
            class="text-center py-12 text-disabled"
          >
            <VIcon
              :icon="search ? 'tabler-search-off' : 'tabler-clock-off'"
              size="48"
              class="mb-3"
            />
            <div class="text-body-1">
              {{ search ? $t('attendance.no_employees', 'No employees found') : $t('attendance.timeline_empty', { date: formatDateDisplay(selectedDate) }) }}
            </div>
          </div>
        </template>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Ruler and rows share the same two-column grid so hour labels sit exactly over the grid lines. */
.timeline-grid {
  display: grid;
  align-items: center;
  gap: 0.5rem 1rem;
  grid-template-columns: minmax(0, 1fr);
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.timeline-ruler {
  display: none;
}

.timeline-row {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: background-color 0.2s ease;
}

.timeline-row:last-child {
  border-block-end: none;
}

.timeline-row:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.timeline-track {
  position: relative;
  min-inline-size: 0;
}

.timeline-track--ruler {
  block-size: 20px;
}

.timeline-track__labels {
  position: relative;
  block-size: 16px;
}

.timeline-track__lane {
  position: relative;
  block-size: 32px;
}

.hour-label {
  position: absolute;
  inline-size: 3rem;
  margin-inline-start: -1.5rem;
  text-align: center;
}

.track-grid-line {
  position: absolute;
  background: rgba(var(--v-border-color), 0.14);
  block-size: 100%;
  inline-size: 1px;
}

.timeline-now {
  position: absolute;
  z-index: 2;
  background: rgb(var(--v-theme-error));
  block-size: calc(100% + 8px);
  inline-size: 2px;
  inset-block-start: -4px;
}

.timeline-segment {
  position: absolute;
  z-index: 1;
  display: flex;
  overflow: hidden;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  block-size: 32px;
  color: rgb(var(--v-theme-on-surface));
}

.segment-completed {
  border-inline-start: 3px solid rgb(var(--v-theme-success));
  background: rgba(var(--v-theme-success), 0.22);
}

.segment-ongoing {
  border-inline-start: 3px solid rgb(var(--v-theme-primary));
  animation: segment-pulse 2.4s ease-in-out infinite;
  background: rgba(var(--v-theme-primary), 0.24);
}

@keyframes segment-pulse {
  0%,
  100% { opacity: 1; }
  50% { opacity: 0.8; }
}

.segment-label {
  font-size: 0.7rem;
  line-height: 1;
}

@media (min-width: 600px) {
  .timeline-ruler {
    display: grid;
  }

  .timeline-grid {
    grid-template-columns: 200px minmax(0, 1fr);
  }
}

@media (min-width: 960px) {
  .timeline-grid {
    grid-template-columns: 240px minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .segment-ongoing {
    animation: none;
  }
}

.timeline-date-control {
  min-inline-size: 240px;
}

.timeline-date-picker-wrap {
  flex: 1;
  min-inline-size: 130px;
}

.timeline-date-picker :deep(.v-field__input) {
  padding-block: 2px !important;
  font-size: 0.875rem;
}

@media (max-width: 599px) {
  .attendance-timeline-tab__toolbar {
    align-items: stretch !important;
  }

  .attendance-timeline-tab__controls {
    display: flex !important;
    flex-wrap: nowrap;
    align-items: center;
    inline-size: 100%;
    gap: 0.5rem;
  }

  .timeline-date-control {
    flex: 1;
    min-inline-size: 0;
  }

  .timeline-date-picker-wrap {
    min-inline-size: 0;
  }

  .attendance-timeline-tab__search {
    inline-size: 100% !important;
    max-inline-size: none !important;
  }

  .timeline-grid {
    padding-block: 0.625rem;
    padding-inline: 0.75rem;
  }

  .timeline-row {
    gap: 0.625rem;
  }
}
</style>
