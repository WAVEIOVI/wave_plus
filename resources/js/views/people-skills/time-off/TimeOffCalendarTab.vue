<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useLeaveRequestStore } from '@/stores/useLeaveRequestStore'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const emit = defineEmits(['view', 'create'])

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const leaveStore = useLeaveRequestStore()
const hrStore = useHrStore()

const currentDate = ref(new Date())
const selectedDepartment = ref(null)
const loading = ref(false)
const events = ref([])

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth())

const monthName = computed(() =>
  new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(currentDate.value),
)

const departmentOptions = computed(() => [
  { title: t('All Departments'), value: null },
  ...hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
])

const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  const startDay = new Date(firstDay)

  startDay.setDate(startDay.getDate() - firstDay.getDay())

  const endDay = new Date(lastDay)

  endDay.setDate(endDay.getDate() + (6 - lastDay.getDay()))

  const days = []
  const current = new Date(startDay)

  while (current <= endDay) {
    const y = current.getFullYear()
    const m = String(current.getMonth() + 1).padStart(2, '0')
    const d = String(current.getDate()).padStart(2, '0')
    const dateString = `${y}-${m}-${d}`

    days.push({
      date: new Date(current),
      dateString,
      isCurrentMonth: current.getMonth() === month,
      isToday: current.toDateString() === new Date().toDateString(),
      leaves: [],
    })
    current.setDate(current.getDate() + 1)
  }

  return days
})

const weeks = computed(() => {
  const result = []
  for (let i = 0; i < calendarDays.value.length; i += 7) {
    result.push(calendarDays.value.slice(i, i + 7))
  }

  return result
})

const loadCalendar = async () => {
  loading.value = true
  try {
    if (!calendarDays.value.length) return
    const startDate = calendarDays.value[0].dateString
    const endDate = calendarDays.value[calendarDays.value.length - 1].dateString

    const data = await leaveStore.fetchCalendar({
      start_date: startDate,
      end_date: endDate,
      department_id: selectedDepartment.value,
    })

    events.value = data || []

    calendarDays.value.forEach(day => {
      day.leaves = events.value.filter(item => {
        return day.dateString >= item.start && day.dateString <= item.end
      })
    })
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.load_error'), 'error')
  } finally {
    loading.value = false
  }
}

const previousMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

const nextMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

const today = () => {
  currentDate.value = new Date()
}

watch([currentDate, selectedDepartment], () => {
  loadCalendar()
})

onMounted(() => {
  loadCalendar()
})
</script>

<template>
  <div class="time-off-calendar-tab d-flex flex-column gap-4">
    <VCard elevation="1">
      <!-- Calendar Controls Toolbar -->
      <VCardTitle class="d-flex flex-wrap align-center justify-space-between gap-3 pa-4">
        <!-- Month Navigation -->
        <div class="d-flex align-center gap-2">
          <VBtn
            icon="tabler-chevron-left"
            variant="tonal"
            size="small"
            @click="previousMonth"
          />
          <span class="text-h6 font-weight-bold min-w-160 text-capitalize">{{ monthName }}</span>
          <VBtn
            icon="tabler-chevron-right"
            variant="tonal"
            size="small"
            @click="nextMonth"
          />
          <VBtn
            variant="tonal"
            size="small"
            color="primary"
            class="ms-2"
            @click="today"
          >
            {{ $t('Today') }}
          </VBtn>
        </div>

        <!-- Filter & Legend -->
        <div class="d-flex flex-wrap align-center gap-3">
          <AppSelect
            v-model="selectedDepartment"
            :items="departmentOptions"
            item-title="title"
            item-value="value"
            :placeholder="$t('Filter by Department')"
            variant="outlined"
            density="compact"
            style="min-inline-size: 190px;"
            clearable
          />

          <div class="d-none d-sm-flex align-center gap-2">
            <VChip
              color="success"
              size="small"
              variant="tonal"
            >
              <VIcon
                icon="tabler-circle-filled"
                size="8"
                start
              />
              {{ $t('time_off.approved') }}
            </VChip>
            <VChip
              color="warning"
              size="small"
              variant="tonal"
            >
              <VIcon
                icon="tabler-circle-filled"
                size="8"
                start
              />
              {{ $t('time_off.kpi.pending') }}
            </VChip>
          </div>
        </div>
      </VCardTitle>

      <VDivider />

      <VProgressLinear
        v-if="loading"
        indeterminate
        color="primary"
      />

      <!-- Calendar View -->
      <VCardText class="pa-0 overflow-x-auto">
        <div class="calendar-wrapper">
          <!-- Weekday Headers -->
          <div class="calendar-header d-flex">
            <div
              v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
              :key="day"
              class="calendar-weekday text-caption font-weight-bold text-center py-2"
            >
              {{ $t(day) }}
            </div>
          </div>

          <!-- Calendar Grid -->
          <div class="calendar-grid">
            <div
              v-for="(week, weekIndex) in weeks"
              :key="weekIndex"
              class="calendar-week d-flex"
            >
              <div
                v-for="day in week"
                :key="day.dateString"
                class="calendar-day flex-grow-1 border-e border-b"
                :class="{
                  'calendar-day--other-month bg-var-theme-background opacity-60': !day.isCurrentMonth,
                  'calendar-day--today bg-primary-lighten-5': day.isToday,
                }"
              >
                <!-- Day Number -->
                <div class="d-flex align-center justify-space-between pa-1">
                  <span
                    class="calendar-day-number text-caption font-weight-medium px-1 rounded"
                    :class="day.isToday ? 'bg-primary text-white font-weight-bold' : ''"
                  >
                    {{ day.date.getDate() }}
                  </span>
                  <span
                    v-if="day.leaves.length"
                    class="text-caption text-disabled"
                  >
                    {{ day.leaves.length }}
                  </span>
                </div>

                <!-- Leave Badges inside Day Cell -->
                <div class="calendar-day-content px-1 pb-1 d-flex flex-column gap-1">
                  <div
                    v-for="leave in day.leaves.slice(0, 3)"
                    :key="leave.id"
                    class="calendar-leave-item cursor-pointer text-caption px-2 py-1 rounded d-flex align-center justify-space-between transition-all"
                    :class="[
                      leave.status === 'approved' ? 'bg-success-lighten-4 text-success border border-success' : '',
                      ['submitted', 'pending'].includes(leave.status) ? 'bg-warning-lighten-4 text-warning border border-warning' : '',
                      leave.status === 'rejected' ? 'bg-error-lighten-4 text-error border border-error' : '',
                    ]"
                    @click="emit('view', leave.id)"
                  >
                    <div class="d-flex align-center gap-1 overflow-hidden">
                      <VAvatar
                        size="16"
                        color="surface"
                      >
                        <VImg
                          v-if="leave.avatar_url"
                          :src="leave.avatar_url"
                        />
                        <span
                          v-else
                          style="font-size: 8px;"
                        >{{ avatarText(leave.employee_name || '?') }}</span>
                      </VAvatar>
                      <span class="text-truncate font-weight-medium">{{ leave.employee_name }}</span>
                    </div>
                    <span class="text-caption text-truncate opacity-80 ms-1">{{ leave.leave_type }}</span>
                  </div>

                  <div
                    v-if="day.leaves.length > 3"
                    class="text-caption text-primary font-weight-medium px-1 cursor-pointer"
                  >
                    +{{ day.leaves.length - 3 }} {{ $t('more') }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Month Summary Metrics -->
    <VRow>
      <VCol
        cols="6"
        sm="3"
      >
        <VCard
          variant="tonal"
          color="success"
          class="pa-4 text-center"
        >
          <div class="text-h5 font-weight-bold">
            {{ events.filter(l => l.status === 'approved').length }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('time_off.kpi.approved') }}
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        sm="3"
      >
        <VCard
          variant="tonal"
          color="warning"
          class="pa-4 text-center"
        >
          <div class="text-h5 font-weight-bold">
            {{ events.filter(l => ['submitted', 'pending'].includes(l.status)).length }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('time_off.kpi.pending') }}
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        sm="3"
      >
        <VCard
          variant="tonal"
          color="primary"
          class="pa-4 text-center"
        >
          <div class="text-h5 font-weight-bold">
            {{ new Set(events.map(e => e.employee_id)).size }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('Employees on Leave') }}
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        sm="3"
      >
        <VCard
          variant="tonal"
          color="secondary"
          class="pa-4 text-center"
        >
          <div class="text-h5 font-weight-bold">
            {{ events.reduce((sum, e) => sum + (Number(e.days) || 0), 0) }}d
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('time_off.kpi.days_taken') }}
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.calendar-wrapper {
  min-inline-size: 720px;
}

.calendar-header {
  background-color: rgba(var(--v-theme-surface), 0.5);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.calendar-weekday {
  inline-size: 14.285%;
}

.calendar-day {
  inline-size: 14.285%;
  min-block-size: 105px;
  vertical-align: top;
  transition: background-color 0.2s ease;
}

.calendar-day:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.calendar-leave-item {
  font-size: 0.72rem;
  line-height: 1.2;
}

.calendar-leave-item:hover {
  transform: translateY(-1px);
  filter: brightness(0.96);
}
</style>
