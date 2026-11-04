<script setup>
/* eslint-disable camelcase */

import {
  WpActiveFilters,
  WpEmptyState,
  WpErrorBanner,
  WpFilterDialog,
  WpLoadingState,
} from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useHrPlannerStore } from '@/stores/useHrPlannerStore'
import BulkScheduleDialog from '@/views/people-skills/workforce/BulkScheduleDialog.vue'
import ScheduleFormDialog from '@/views/people-skills/workforce/ScheduleFormDialog.vue'
import ScheduleHistoryDialog from '@/views/people-skills/workforce/ScheduleHistoryDialog.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const planner = useHrPlannerStore()
const hrStore = useHrStore()
const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const { mdAndDown } = useDisplay()

// Keep the roster easy to use on phones and tablets. Larger screens retain the
// weekly grid, with horizontal overflow handled by the project's PerfectScrollbar.
const isCompactLayout = computed(() => mdAndDown.value)

const plannerScrollOptions = {
  suppressScrollY: true,
  wheelPropagation: true,
  swipeEasing: true,
  minScrollbarLength: 32,
}

const bulkOpen = ref(false)
const slotOpen = ref(false)
const filterOpen = ref(false)
const historyOpen = ref(false)
const activeCell = ref(null)
const activeEmployee = ref(null)
const actionLoading = ref(false)
const error = ref(null)

const bulk = reactive({
  employee_ids: [],
  start_date: '',
  end_date: '',
  shift_id: null,
  days_of_week: [1, 2, 3, 4, 5],
  lifecycle_status: 'draft',
  skip_approved_leaves: true,
  skip_holidays: true,
  include_weekends: false,
  overwrite_existing: false,
  notes: '',
})

const filterDraft = reactive({ department_id: null, team_id: null, search: '' })

const departmentOptions = computed(() =>
  hrStore.departments.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const teamOptions = computed(() =>
  hrStore.teams.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const employeeOptions = computed(() =>
  planner.employees.map(item => ({
    title: localizedValue(item.full_name),
    value: item.id,
  })),
)

const shiftOptions = computed(() =>
  planner.shifts.map(item => ({
    ...item,
    title: localizedValue(item.name),
  })),
)

const draftCount = computed(() =>
  planner.employees.reduce(
    (count, employee) =>
      count + Object.values(employee.days || {}).filter(
        cell => cell.schedule?.lifecycle_status === 'draft',
      ).length,
    0,
  ),
)

const activeFilters = computed(() => [
  ...(planner.departmentId ? [{ key: 'department_id', value: planner.departmentId }] : []),
  ...(planner.teamId ? [{ key: 'team_id', value: planner.teamId }] : []),
  ...(planner.search ? [{ key: 'search', value: planner.search }] : []),
])

const filterLabelMap = computed(() => ({
  department_id: t('workforce.availability.department', 'Department'),
  team_id: t('workforce.availability.team', 'Team'),
  search: t('wp.filters.search', 'Search'),
}))

const filterValueFormatters = computed(() => ({
  department_id: value =>
    departmentOptions.value.find(item => String(item.value) === String(value))?.title || value,
  team_id: value =>
    teamOptions.value.find(item => String(item.value) === String(value))?.title || value,
}))

const openFilters = () => {
  Object.assign(filterDraft, {
    department_id: planner.departmentId,
    team_id: planner.teamId,
    search: planner.search || '',
  })
  filterOpen.value = true
}

const applyFilters = () => {
  planner.departmentId = filterDraft.department_id
  planner.teamId = filterDraft.team_id

  // Clearable text fields may emit null; normalize it before trimming.
  planner.search = String(filterDraft.search || '').trim()
  filterOpen.value = false
  load()
}

const resetFilters = () => {
  Object.assign(filterDraft, { department_id: null, team_id: null, search: '' })
  planner.departmentId = null
  planner.teamId = null
  planner.search = ''
  filterOpen.value = false
  load()
}

const removeFilter = key => {
  if (key === 'department_id')
    planner.departmentId = null
  else if (key === 'team_id')
    planner.teamId = null
  else if (key === 'search')
    planner.search = ''

  load()
}

const formatRange = computed(() => `${planner.startDate} → ${planner.endDate}`)

const cellColor = cell => {
  if (cell.leave)
    return 'info'
  if (cell.schedule)
    return cell.schedule.lifecycle_status === 'draft' ? 'warning' : 'primary'
  if (cell.holiday)
    return 'warning'

  return undefined
}

const cellText = cell => {
  if (cell.leave)
    return localizedValue(cell.leave.leave_type_name, 'Leave')
  if (cell.schedule)
    return `${cell.schedule.shift_code || localizedValue(cell.schedule.shift_name, 'Shift')} ${cell.schedule.start_time?.slice(0, 5) || ''}`.trim()
  if (cell.holiday)
    return cell.holiday.name || 'Holiday'

  return 'Add shift'
}

const load = async () => {
  error.value = null

  try {
    await Promise.all([hrStore.fetchMeta(), planner.fetchPlannerGrid()])
  } catch (err) {
    error.value = err?.data?.message || 'Unable to load workforce planner.'
  }
}

const openSlot = (cell, employee) => {
  if (!cell || actionLoading.value)
    return

  activeCell.value = cell
  activeEmployee.value = employee
  slotOpen.value = true
}

const saveSlot = async form => {
  actionLoading.value = true

  try {
    await planner.assignCellSchedule(activeEmployee.value.id, activeCell.value.date, form)
    slotOpen.value = false
    showMessage('Schedule saved', 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to save schedule', 'error')
  } finally {
    actionLoading.value = false
  }
}

const deleteSlot = async () => {
  if (!activeCell.value?.schedule?.id || actionLoading.value)
    return

  actionLoading.value = true

  try {
    await planner.removeCellSchedule(activeCell.value.schedule.id)
    slotOpen.value = false
    showMessage('Schedule removed', 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to remove schedule', 'error')
  } finally {
    actionLoading.value = false
  }
}

const openBulk = () => {
  Object.assign(bulk, {
    employee_ids: planner.employees.map(item => item.id),
    start_date: planner.startDate,
    end_date: planner.endDate,
    shift_id: planner.shifts[0]?.id || null,
    days_of_week: [1, 2, 3, 4, 5],
    lifecycle_status: 'draft',
    skip_approved_leaves: true,
    skip_holidays: true,
    include_weekends: false,
    overwrite_existing: false,
    notes: '',
  })
  bulkOpen.value = true
}

const saveBulk = async form => {
  Object.assign(bulk, form)
  actionLoading.value = true

  try {
    const result = await planner.bulkSchedule({ ...bulk })

    bulkOpen.value = false
    showMessage(`${result?.data?.created ?? 0} schedule slots created`, 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to create schedules', 'error')
  } finally {
    actionLoading.value = false
  }
}

const publish = async () => {
  if (actionLoading.value)
    return

  actionLoading.value = true

  try {
    const result = await planner.publishSchedules()

    showMessage(`${result?.published_count ?? 0} schedules published`, 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to publish schedules', 'error')
  } finally {
    actionLoading.value = false
  }
}

const clear = async () => {
  if (actionLoading.value)
    return

  actionLoading.value = true

  try {
    const result = await planner.clearSchedules()

    showMessage(`${result?.deleted_count ?? 0} schedules removed`, 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to clear schedules', 'error')
  } finally {
    actionLoading.value = false
  }
}

defineExpose({ openBulk, openFilters, openHistory: () => { historyOpen.value = true }, publish, load, clear, draftCount })

onMounted(load)
</script>

<template>
  <div class="d-flex flex-column gap-4 workforce-planner">
    <VCard class="pa-3 pa-sm-4 planner-toolbar-card">
      <div class="planner-toolbar">
        <div class="planner-week-controls">
          <VBtnToggle
            :model-value="planner.rangeMode"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
            class="range-mode-toggle me-sm-2"
            @update:model-value="planner.setRangeMode($event)"
          >
            <VBtn
              value="week"
              size="small"
            >
              {{ t('workforce.planner.week', 'Week') }}
            </VBtn>
            <VBtn
              value="biweekly"
              size="small"
            >
              {{ t('workforce.planner.two_weeks', '2 Weeks') }}
            </VBtn>
            <VBtn
              value="month"
              size="small"
            >
              {{ t('workforce.planner.month', 'Month') }}
            </VBtn>
          </VBtnToggle>

          <div class="planner-week-nav">
            <VBtn
              icon="tabler-chevron-left"
              variant="tonal"
              density="comfortable"
              :disabled="planner.isLoading"
              :aria-label="t('workforce.planner.previous_period', 'Previous period')"
              @click="planner.prevPeriod()"
            />
            <VBtn
              variant="tonal"
              color="primary"
              class="today-button"
              :disabled="planner.isLoading"
              @click="planner.goToCurrentPeriod()"
            >
              Today
            </VBtn>
            <VBtn
              icon="tabler-chevron-right"
              variant="tonal"
              density="comfortable"
              :disabled="planner.isLoading"
              :aria-label="t('workforce.planner.next_period', 'Next period')"
              @click="planner.nextPeriod()"
            />
          </div>

          <div
            class="planner-date-range"
            aria-live="polite"
          >
            <VIcon
              icon="tabler-calendar-week"
              size="18"
              aria-hidden="true"
            />
            <span>{{ formatRange }}</span>
          </div>
        </div>

        <div class="planner-actions d-none d-md-flex">
          <VBtn
            variant="outlined"
            prepend-icon="tabler-history"
            :disabled="actionLoading"
            @click="historyOpen = true"
          >
            {{ t('workforce.history', 'History') }}
          </VBtn>

          <VBtn
            variant="outlined"
            :aria-label="t('wp.filters.label', 'Filters')"
            :title="t('wp.filters.label', 'Filters')"
            :disabled="actionLoading"
            @click="openFilters"
          >
            <VBadge
              v-if="activeFilters.length"
              :content="activeFilters.length"
              color="primary"
              floating
            >
              <VIcon icon="tabler-filter" />
            </VBadge>
            <VIcon
              v-else
              icon="tabler-filter"
            />
            <span class="ms-2">{{ t('wp.filters.label', 'Filters') }}</span>
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-calendar-plus"
            :disabled="actionLoading"
            @click="openBulk"
          >
            {{ t('workforce.bulk_schedule', 'Bulk schedule') }}
          </VBtn>

          <VBtn
            v-if="draftCount"
            color="warning"
            variant="tonal"
            :loading="actionLoading"
            :disabled="actionLoading"
            :aria-label="`Publish ${draftCount} drafts`"
            @click="publish"
          >
            <VIcon icon="tabler-upload" />
            <span class="d-none d-sm-inline ms-1">Publish</span>
            <span class="ms-1">{{ draftCount }}</span>
            <span class="d-none d-sm-inline ms-1">drafts</span>
          </VBtn>

          <VMenu>
            <template #activator="{ props }">
              <VBtn
                icon="tabler-dots-vertical"
                variant="text"
                :aria-label="t('workforce.planner.more_actions', 'More planner actions')"
                v-bind="props"
              />
            </template>
            <VList>
              <VListItem
                prepend-icon="tabler-refresh"
                title="Reload planner"
                :disabled="planner.isLoading || actionLoading"
                @click="load"
              />
              <VListItem
                prepend-icon="tabler-trash"
                class="text-error"
                title="Clear uncompleted period"
                :disabled="actionLoading || planner.isLoading"
                @click="clear"
              />
            </VList>
          </VMenu>
        </div>
      </div>
    </VCard>

    <WpActiveFilters
      :filters="activeFilters"
      :label-map="filterLabelMap"
      :value-formatters="filterValueFormatters"
      @remove="removeFilter($event.key)"
      @clear-all="resetFilters"
    />

    <WpErrorBanner
      v-if="error"
      :message="error"
      @retry="load"
    />

    <VCard
      v-else
      class="overflow-hidden planner-content-card"
    >
      <WpLoadingState v-if="planner.isLoading" />

      <template v-else>
        <WpEmptyState
          v-if="!planner.employees.length"
          icon="tabler-users-minus"
          title="No matching staff"
          description="Try a different department, team, or search."
        />

        <!-- Compact layout: larger, touch-friendly day controls instead of a tiny table. -->
        <div
          v-else-if="isCompactLayout"
          class="mobile-planner"
        >
          <VCard
            v-for="employee in planner.employees"
            :key="employee.id"
            variant="outlined"
            class="mobile-employee-card"
          >
            <div class="mobile-employee-heading">
              <VAvatar
                color="primary"
                variant="tonal"
                size="40"
                aria-hidden="true"
              >
                <VIcon icon="tabler-user" />
              </VAvatar>
              <div class="mobile-employee-details">
                <div class="mobile-employee-name">
                  {{ localizedValue(employee.full_name) }}
                </div>
                <div class="mobile-employee-role">
                  {{ localizedValue(employee.position?.title || employee.department?.name, 'Staff') }}
                </div>
              </div>
            </div>

            <div class="mobile-week-grid">
              <div
                v-for="day in planner.dates"
                :key="day.date"
                class="mobile-day-cell"
                :class="{
                  'mobile-day-cell--today': day.is_today,
                  'mobile-day-cell--weekend': day.is_weekend,
                  'mobile-day-cell--holiday': day.is_holiday,
                }"
              >
                <div class="mobile-day-heading">
                  <div class="d-flex align-center gap-1">
                    <span>{{ day.day_name }}</span>
                    <VIcon
                      v-if="day.is_holiday"
                      icon="tabler-confetti"
                      size="13"
                      color="warning"
                    />
                  </div>
                  <span class="mobile-day-number">{{ day.day_number }}</span>
                </div>

                <VBtn
                  v-if="employee.days?.[day.date]"
                  block
                  size="large"
                  :color="cellColor(employee.days[day.date])"
                  :variant="employee.days[day.date].schedule || employee.days[day.date].leave || employee.days[day.date].holiday ? 'tonal' : 'outlined'"
                  class="mobile-slot"
                  :class="{
                    'slot--conflict': employee.days[day.date].schedule?.is_conflict,
                    'slot--exceptional': employee.days[day.date].schedule?.is_exceptional,
                    'slot--draft': employee.days[day.date].schedule?.lifecycle_status === 'draft',
                  }"
                  :disabled="actionLoading"
                  :aria-label="`${localizedValue(employee.full_name)} — ${day.day_name} ${day.day_number}: ${cellText(employee.days[day.date])}`"
                  @click="openSlot(employee.days[day.date], employee)"
                >
                  <VIcon
                    v-if="employee.days[day.date].leave"
                    icon="tabler-beach"
                    size="16"
                    class="me-1"
                  />
                  <VIcon
                    v-else-if="employee.days[day.date].holiday"
                    icon="tabler-confetti"
                    size="16"
                    class="me-1 text-warning"
                  />
                  <span class="mobile-slot-label">{{ cellText(employee.days[day.date]) }}</span>
                  <VIcon
                    v-if="employee.days[day.date].schedule?.is_conflict"
                    icon="tabler-alert-triangle"
                    size="16"
                    color="error"
                    class="ms-1 flex-shrink-0"
                  />
                  <VIcon
                    v-if="employee.days[day.date].schedule?.is_exceptional"
                    icon="tabler-clock-edit"
                    size="14"
                    color="info"
                    class="ms-1 flex-shrink-0"
                  />
                </VBtn>
                <div
                  v-else
                  class="mobile-slot-unavailable"
                >
                  —
                </div>
              </div>
            </div>
          </VCard>
        </div>

        <!-- Desktop layout: preserve the weekly grid and sticky employee column. -->
        <PerfectScrollbar
          v-else
          class="planner-scroll"
          :options="plannerScrollOptions"
        >
          <table class="planner-table">
            <thead>
              <tr>
                <th
                  class="employee-column"
                  scope="col"
                >
                  Employee
                </th>
                <th
                  v-for="day in planner.dates"
                  :key="day.date"
                  scope="col"
                  :class="{
                    today: day.is_today,
                    'weekend-col': day.is_weekend,
                    'holiday-col': day.is_holiday,
                  }"
                >
                  <div class="d-flex align-center justify-center gap-1">
                    <span>{{ day.day_name }}</span>
                    <VTooltip
                      v-if="day.is_holiday"
                      location="top"
                    >
                      <template #activator="{ props: tipProps }">
                        <VIcon
                          v-bind="tipProps"
                          icon="tabler-confetti"
                          size="14"
                          color="warning"
                        />
                      </template>
                      <span>{{ day.holiday_name || 'Public Holiday' }}</span>
                    </VTooltip>
                  </div>
                  <small>{{ day.day_number }}</small>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="employee in planner.employees"
                :key="employee.id"
              >
                <td class="employee-column">
                  <div class="employee-name">
                    {{ localizedValue(employee.full_name) }}
                  </div>
                  <small class="employee-role">
                    {{ localizedValue(employee.position?.title || employee.department?.name, 'Staff') }}
                  </small>
                </td>
                <td
                  v-for="day in planner.dates"
                  :key="day.date"
                  :class="{
                    today: day.is_today,
                    'weekend-col': day.is_weekend,
                    'holiday-col': day.is_holiday,
                  }"
                >
                  <VBtn
                    v-if="employee.days?.[day.date]"
                    :color="cellColor(employee.days[day.date])"
                    :variant="employee.days[day.date].schedule || employee.days[day.date].leave || employee.days[day.date].holiday ? 'tonal' : 'text'"
                    size="small"
                    class="slot"
                    :class="{
                      'slot--conflict': employee.days[day.date].schedule?.is_conflict,
                      'slot--exceptional': employee.days[day.date].schedule?.is_exceptional,
                      'slot--draft': employee.days[day.date].schedule?.lifecycle_status === 'draft',
                    }"
                    :disabled="actionLoading"
                    :aria-label="`${localizedValue(employee.full_name)} — ${day.day_name} ${day.day_number}: ${cellText(employee.days[day.date])}`"
                    @click="openSlot(employee.days[day.date], employee)"
                  >
                    <VIcon
                      v-if="employee.days[day.date].leave"
                      icon="tabler-beach"
                      size="14"
                      class="me-1"
                    />
                    <VIcon
                      v-else-if="employee.days[day.date].holiday"
                      icon="tabler-confetti"
                      size="14"
                      class="me-1 text-warning"
                    />
                    <span class="text-truncate">{{ cellText(employee.days[day.date]) }}</span>

                    <VTooltip
                      v-if="employee.days[day.date].schedule?.is_conflict"
                      location="top"
                    >
                      <template #activator="{ props: tipProps }">
                        <VIcon
                          v-bind="tipProps"
                          icon="tabler-alert-triangle"
                          size="14"
                          color="error"
                          class="ms-1 flex-shrink-0"
                        />
                      </template>
                      <span>{{ employee.days[day.date].schedule.conflict_reason || t('workforce.conflict_leave_detected', 'Conflict: Approved leave overlap') }}</span>
                    </VTooltip>

                    <VTooltip
                      v-if="employee.days[day.date].schedule?.is_exceptional"
                      location="top"
                    >
                      <template #activator="{ props: tipProps }">
                        <VIcon
                          v-bind="tipProps"
                          icon="tabler-clock-edit"
                          size="13"
                          color="info"
                          class="ms-1 flex-shrink-0"
                        />
                      </template>
                      <span>{{ t('workforce.exceptional_schedule', 'Custom shift times / override') }}</span>
                    </VTooltip>
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </table>
        </PerfectScrollbar>
      </template>
    </VCard>

    <WpFilterDialog
      v-model="filterOpen"
      :active-count="activeFilters.length"
      title="Planner filters"
      :loading="planner.isLoading"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="filterDraft.department_id"
            :items="departmentOptions"
            label="Department"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="filterDraft.team_id"
            :items="teamOptions"
            label="Team"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            v-model="filterDraft.search"
            label="Search staff"
            prepend-inner-icon="tabler-search"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <ScheduleFormDialog
      v-model="slotOpen"
      :employee="activeEmployee"
      :date="activeCell?.date || ''"
      :schedule="activeCell?.schedule || null"
      :leave="activeCell?.leave || null"
      :attendance="activeCell?.attendance || null"
      :shift-options="shiftOptions"
      :default-shift-id="planner.shifts[0]?.id || null"
      :saving="actionLoading"
      @submit="saveSlot"
      @remove="deleteSlot"
    />

    <BulkScheduleDialog
      v-model="bulkOpen"
      :form="bulk"
      :employee-options="employeeOptions"
      :shift-options="shiftOptions"
      :saving="actionLoading"
      @submit="saveBulk"
    />

    <ScheduleHistoryDialog v-model="historyOpen" />
  </div>
</template>

<style scoped>
.workforce-planner {
  min-inline-size: 0;
}

.planner-toolbar-card {
  min-inline-size: 0;
}

.planner-toolbar {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 14px;
  min-inline-size: 0;
}

.planner-week-controls,
.planner-week-nav,
.planner-actions,
.planner-date-range {
  display: flex;
  align-items: center;
  min-inline-size: 0;
}

.planner-week-controls {
  flex-wrap: wrap;
  gap: 10px 14px;
}

.planner-week-nav {
  flex-shrink: 0;
  gap: 6px;
}

.planner-week-nav :deep(.v-btn--icon) {
  min-block-size: 44px;
  min-inline-size: 44px;
}

.today-button {
  min-block-size: 44px;
}

.planner-date-range {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.9rem;
  font-weight: 600;
  gap: 8px;
  overflow-wrap: anywhere;
}

.planner-date-range span {
  min-inline-size: 0;
}

.planner-actions {
  flex-wrap: wrap;
  gap: 8px;
}

.planner-actions :deep(.v-btn) {
  min-block-size: 44px;
}

.planner-content-card {
  min-inline-size: 0;
}

/* One custom horizontal scroller for the grid; vertical movement belongs to the page. */
.planner-scroll {
  inline-size: 100%;
  max-block-size: none;
  min-block-size: 0;
  min-inline-size: 0;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain auto;
}

.planner-table {
  border-collapse: separate;
  border-spacing: 0;
  inline-size: 100%;
  min-inline-size: 980px;
}

.planner-table th,
.planner-table td {
  padding: 10px;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  text-align: center;
  vertical-align: middle;
}

.planner-table thead th {
  background-color: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 0 rgba(var(--v-border-color), var(--v-border-opacity));
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
}

.planner-table .employee-column {
  position: sticky;
  z-index: 2;
  background-clip: padding-box;

  /* Opaque surface is essential: scrolling cells must never show through it. */
  background-color: rgb(var(--v-theme-surface)) !important;
  background-image: none !important;
  inset-inline-start: 0;
  isolation: isolate;
  min-inline-size: 220px;
  text-align: start;
}

.planner-table tbody .employee-column {
  z-index: 2;
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.planner-table thead .employee-column {
  z-index: 3;
}

.planner-table tbody tr:hover .employee-column {
  background-color: rgb(var(--v-theme-surface)) !important;
}

.planner-table tbody tr:hover td:not(.employee-column) {
  background-color: rgba(var(--v-theme-primary), 0.035);
}

.planner-table .today {
  background-color: rgba(var(--v-theme-primary), 0.07);
}

.planner-table thead .today {
  background-color: rgba(var(--v-theme-primary), 0.11);
}

.planner-table .weekend-col {
  background-color: rgba(var(--v-theme-on-surface), 0.025);
}

.planner-table thead .weekend-col {
  background-color: rgba(var(--v-theme-on-surface), 0.045);
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.planner-table .holiday-col {
  background-color: rgba(var(--v-theme-warning), 0.04);
}

.planner-table thead .holiday-col {
  background-color: rgba(var(--v-theme-warning), 0.08);
}

.employee-name {
  font-weight: 600;
  overflow-wrap: anywhere;
}

.employee-role {
  display: block;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  line-height: 1.4;
  margin-block-start: 3px;
  overflow-wrap: anywhere;
}

.slot {
  max-inline-size: 150px;
  min-block-size: 38px;
  min-inline-size: 100px;
  text-transform: none;
  white-space: normal;
}

.range-mode-toggle {
  min-block-size: 36px;
}

.slot--conflict {
  border: 1px solid rgb(var(--v-theme-error)) !important;
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}

.slot--draft {
  border-style: dashed !important;
  opacity: 0.9;
}

.slot--exceptional {
  position: relative;
}

.mobile-planner {
  display: flex;
  flex-direction: column;
  padding: 12px;
  gap: 12px;
  min-inline-size: 0;
}

.mobile-employee-card {
  padding: 12px;
  border-radius: 12px;
  min-inline-size: 0;
}

.mobile-employee-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-block-end: 14px;
  min-inline-size: 0;
}

.mobile-employee-details {
  min-inline-size: 0;
}

.mobile-employee-name {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.mobile-employee-role {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.78rem;
  line-height: 1.35;
  margin-block-start: 3px;
  overflow-wrap: anywhere;
}

.mobile-week-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  min-inline-size: 0;
}

.mobile-day-cell {
  padding: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.018);
  min-inline-size: 0;
}

.mobile-day-cell--today {
  border-color: rgba(var(--v-theme-primary), 0.65);
  background: rgba(var(--v-theme-primary), 0.055);
}

.mobile-day-cell--weekend {
  background: rgba(var(--v-theme-on-surface), 0.028);
  border-style: dashed;
}

.mobile-day-cell--holiday {
  border-color: rgba(var(--v-theme-warning), 0.45);
  background: rgba(var(--v-theme-warning), 0.05);
}

.mobile-day-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.75rem;
  font-weight: 700;
  gap: 6px;
  line-height: 1.2;
  margin-block-end: 7px;
}

.mobile-day-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.06);
  font-size: 0.75rem;
  min-block-size: 24px;
  min-inline-size: 24px;
}

.mobile-day-cell--today .mobile-day-number {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.mobile-slot {
  min-block-size: 48px;
  min-inline-size: 0;
  padding-inline: 8px;
  text-transform: none;
}

.mobile-slot :deep(.v-btn__content) {
  justify-content: center;
  inline-size: 100%;
  line-height: 1.25;
  overflow-wrap: anywhere;
  white-space: normal;
}

.mobile-slot-label {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  font-size: 0.75rem;
  -webkit-line-clamp: 2;
  max-inline-size: 100%;
  overflow-wrap: anywhere;
}

.mobile-slot-unavailable {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  min-block-size: 48px;
}

@media (min-width: 600px) and (max-width: 959.98px) {
  .mobile-week-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .mobile-employee-card {
    padding: 14px;
  }
}

@media (min-width: 960px) {
  .planner-toolbar {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }

  .planner-actions {
    justify-content: flex-end;
  }
}

@media (max-width: 359px) {
  .planner-week-controls {
    gap: 8px;
  }

  .planner-date-range {
    font-size: 0.8rem;
  }

  .mobile-employee-card {
    padding: 9px;
  }

  .mobile-day-cell {
    padding: 6px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .planner-scroll {
    scroll-behavior: auto;
  }
}
</style>
