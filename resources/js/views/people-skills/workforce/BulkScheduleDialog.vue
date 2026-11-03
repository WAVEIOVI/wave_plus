<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, reactive, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, required: true },
  employeeOptions: { type: Array, default: () => [] },
  shiftOptions: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const formId = `workforce-bulk-schedule-${useId()}`
const formEl = ref(null)

const formState = reactive({})

const daysError = ref(false)

// The preview count is calculated arithmetically, so long ranges stay accurate and fast.
const VISIBLE_EMPLOYEE_CHIPS = 2

// ── Options ───────────────────────────────────────────────────────────────
const statusOptions = computed(() => [
  { title: t('workforce.status.draft', 'Draft'), value: 'draft' },
  { title: t('workforce.status.planned', 'Planned'), value: 'planned' },
])

// Values 1–7 = Monday–Sunday (same mapping the form already submits).
const dayOptions = computed(() => [
  { value: 1, title: t('workforce.days.mon', 'Mon') },
  { value: 2, title: t('workforce.days.tue', 'Tue') },
  { value: 3, title: t('workforce.days.wed', 'Wed') },
  { value: 4, title: t('workforce.days.thu', 'Thu') },
  { value: 5, title: t('workforce.days.fri', 'Fri') },
  { value: 6, title: t('workforce.days.sat', 'Sat') },
  { value: 7, title: t('workforce.days.sun', 'Sun') },
])

const rangePresets = computed(() => [
  { key: 'rest_of_week', title: t('workforce.preset.rest_of_week', 'Rest of this week') },
  { key: 'next_week', title: t('workforce.preset.next_week', 'Next week') },
  { key: 'rest_of_month', title: t('workforce.preset.rest_of_month', 'Rest of this month') },
])

const dayPresets = computed(() => [
  { key: 'weekdays', title: t('workforce.preset.weekdays', 'Mon–Fri'), days: [1, 2, 3, 4, 5] },
  { key: 'every_day', title: t('workforce.preset.every_day', 'Every day'), days: [1, 2, 3, 4, 5, 6, 7] },
  { key: 'weekends', title: t('workforce.preset.weekends', 'Weekends (Sat–Sun)'), days: [6, 7] },
])

// ── Date helpers (local calendar, never UTC) ──────────────────────────────
const pad = value => String(value).padStart(2, '0')

const toLocalDate = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

const parseLocalDate = value => {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value))
    return null

  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day)

  // Date silently normalizes impossible dates (e.g. February 31); reject those.
  if (
    Number.isNaN(date.getTime())
    || date.getFullYear() !== year
    || date.getMonth() !== month - 1
    || date.getDate() !== day
  )
    return null

  date.setHours(0, 0, 0, 0)

  return date
}

const addDays = (date, amount) => {
  const next = new Date(date)

  next.setDate(next.getDate() + amount)

  return next
}

const applyRangePreset = key => {
  const today = new Date()

  today.setHours(0, 0, 0, 0)

  // Monday-based week, consistent with the weekday toggle.
  const monday = addDays(today, -((today.getDay() + 6) % 7))
  let start = today
  let end = today

  if (key === 'rest_of_week') {
    end = addDays(monday, 6)
  }
  else if (key === 'next_week') {
    start = addDays(monday, 7)
    end = addDays(monday, 13)
  }
  else if (key === 'rest_of_month') {
    end = new Date(today.getFullYear(), today.getMonth() + 1, 0)
  }

  formState.start_date = toLocalDate(start)
  formState.end_date = toLocalDate(end)
}

// ── Employee helpers ──────────────────────────────────────────────────────
const employeeCount = computed(() => Array.isArray(formState.employee_ids) ? formState.employee_ids.length : 0)

const optionValue = option => {
  if (option === null || typeof option !== 'object')
    return option

  return option.value ?? option.id ?? option.employee_id ?? option
}

const selectAllEmployees = () => {
  const values = props.employeeOptions
    .map(optionValue)
    .filter(value => value !== null && value !== undefined)

  formState.employee_ids = [...new Set(values)]
}

const clearEmployees = () => {
  formState.employee_ids = []
}

const removeEmployee = id => {
  formState.employee_ids = (formState.employee_ids || []).filter(value => value !== id)
}

// ── Weekday helpers ───────────────────────────────────────────────────────
const setDays = days => {
  formState.days_of_week = [...days]
  daysError.value = false
  if (days.includes(6) || days.includes(7)) {
    formState.include_weekends = true
  }
}

// ── Live summary ──────────────────────────────────────────────────────────
const matchingDayCount = computed(() => {
  const start = parseLocalDate(formState.start_date)
  const end = parseLocalDate(formState.end_date)

  const days = Array.isArray(formState.days_of_week)
    ? [...new Set(formState.days_of_week.filter(day => Number.isInteger(day) && day >= 1 && day <= 7))]
    : []

  if (!start || !end || end < start || !days.length)
    return 0

  // Use UTC calendar-day arithmetic to avoid DST changing the day count.
  const startUtc = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate())
  const endUtc = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate())
  const totalDays = Math.floor((endUtc - startUtc) / 86_400_000) + 1
  const fullWeeks = Math.floor(totalDays / 7)
  const remainingDays = totalDays % 7

  let count = fullWeeks * new Set(days).size

  for (let offset = 0; offset < remainingDays; offset++) {
    const weekday = (start.getDay() + offset) % 7 || 7

    if (days.includes(weekday))
      count++
  }

  return count
})

const totalSchedules = computed(() => employeeCount.value * matchingDayCount.value)

const hasValidRange = computed(() => {
  const start = parseLocalDate(formState.start_date)
  const end = parseLocalDate(formState.end_date)

  return Boolean(start && end && end >= start)
})

// 'idle' → nothing to preview yet, 'empty' → inputs set but no day matches, 'ready' → show total
const summaryState = computed(() => {
  if (!employeeCount.value || !hasValidRange.value || !(formState.days_of_week || []).length)
    return 'idle'

  return matchingDayCount.value ? 'ready' : 'empty'
})

// ── Validation ────────────────────────────────────────────────────────────
const requiredRule = value => {
  if (Array.isArray(value))
    return value.length > 0 || t('workforce.validation.select_one', 'Select at least one option.')

  return (value !== null && value !== undefined && value !== '') || t('workforce.validation.required', 'This field is required.')
}

const endDateRule = value =>
  !formState.start_date || !value || value >= formState.start_date || t('workforce.validation.end_after_start', 'End date must be on or after the start date.')

// Keep the range coherent while the user edits the start date.
watch(() => formState.start_date, start => {
  if (start && formState.end_date && formState.end_date < start)
    formState.end_date = start
})

// ── Actions ───────────────────────────────────────────────────────────────
const close = () => {
  if (!props.saving)
    emit('update:modelValue', false)
}

const submit = async () => {
  if (props.saving)
    return

  const result = await formEl.value?.validate?.()
  if (!result?.valid)
    return

  if (!(formState.days_of_week || []).length) {
    daysError.value = true

    return
  }

  emit('submit', {
    ...formState,
    employee_ids: [...(formState.employee_ids || [])],
    days_of_week: [...(formState.days_of_week || [])],
  })
}

watch(() => props.modelValue, async isOpen => {
  if (isOpen) {
    daysError.value = false

    // Remove keys from a previous opening before copying the latest form values.
    Object.keys(formState).forEach(key => delete formState[key])
    Object.assign(formState, Object.fromEntries(
      Object.entries(props.form || {}).map(([key, value]) => [
        key,
        Array.isArray(value) ? [...value] : value,
      ]),
    ))

    formState.employee_ids = Array.isArray(formState.employee_ids) ? formState.employee_ids : []
    formState.days_of_week = Array.isArray(formState.days_of_week) ? formState.days_of_week : []
    if (formState.skip_holidays === undefined)
      formState.skip_holidays = true
    if (formState.include_weekends === undefined)
      formState.include_weekends = formState.days_of_week.includes(6) || formState.days_of_week.includes(7)
  }

  await nextTick()
  formEl.value?.resetValidation?.()
}, { immediate: true })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="720"
    :fullscreen="smAndDown"
    :persistent="saving"
    @update:model-value="value => !value && close()"
  >
    <DialogCloseBtn
      :disabled="saving"
      @click="close"
    />
    <VCard class="dialog-card d-flex flex-column h-100 rounded-0 rounded-sm">
      <VCardItem class="dialog-header">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="44"
          >
            <VIcon
              icon="tabler-calendar-plus"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ $t('workforce.bulk_schedule', 'Bulk schedule') }}
        </VCardTitle>
        <VCardSubtitle class="text-wrap">
          {{ $t('workforce.bulk_schedule_subtitle', 'Create schedules for selected employees and dates.') }}
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="dialog-body">
        <PerfectScrollbar
          class="dialog-scroll"
          :options="{ wheelPropagation: false }"
        >
          <div class="pa-4 pa-sm-6 pb-8">
            <VForm
              :id="formId"
              ref="formEl"
              validate-on="blur"
              @submit.prevent="submit"
            >
              <div class="form-sections">
                <!-- Who -->
                <section>
                  <div class="form-section__header">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="28"
                      class="form-section__avatar"
                    >
                      <VIcon
                        icon="tabler-users"
                        size="16"
                      />
                    </VAvatar>
                    <span class="form-section__title">{{ $t('workforce.form.sections.employees', 'Employees') }}</span>
                  </div>

                  <AppSelect
                    v-model="formState.employee_ids"
                    :disabled="saving"
                    :items="employeeOptions"
                    :label="$t('workforce.col.employee', 'Employees')"
                    :placeholder="$t('workforce.select_employees', 'Select employees')"
                    :rules="[requiredRule]"
                    multiple
                    chips
                    closable-chips
                  >
                    <template #selection="{ item, index }">
                      <VChip
                        v-if="index < VISIBLE_EMPLOYEE_CHIPS"
                        size="small"
                        closable
                        @click:close="removeEmployee(item.value)"
                      >
                        {{ item.title }}
                      </VChip>
                      <span
                        v-else-if="index === VISIBLE_EMPLOYEE_CHIPS"
                        class="text-caption text-medium-emphasis ms-1"
                      >
                        +{{ employeeCount - VISIBLE_EMPLOYEE_CHIPS }}
                      </span>
                    </template>
                  </AppSelect>

                  <div class="quick-actions">
                    <VBtn
                      v-if="employeeOptions.length > 1"
                      size="small"
                      variant="text"
                      color="primary"
                      prepend-icon="tabler-checks"
                      :disabled="saving || employeeCount === employeeOptions.length"
                      @click="selectAllEmployees"
                    >
                      {{ $t('workforce.select_all', 'Select all') }}
                    </VBtn>
                    <VBtn
                      v-if="employeeCount"
                      size="small"
                      variant="text"
                      color="secondary"
                      :disabled="saving"
                      @click="clearEmployees"
                    >
                      {{ $t('Clear') }}
                    </VBtn>
                    <span class="quick-actions__count text-caption text-medium-emphasis">
                      {{ $t('workforce.employees_selected', { count: employeeCount }, '{count} selected') }}
                    </span>
                  </div>
                </section>

                <VDivider />

                <!-- When -->
                <section>
                  <div class="form-section__header">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="28"
                      class="form-section__avatar"
                    >
                      <VIcon
                        icon="tabler-calendar-event"
                        size="16"
                      />
                    </VAvatar>
                    <span class="form-section__title">{{ $t('workforce.form.sections.period', 'Dates and weekdays') }}</span>
                  </div>

                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="formState.start_date"
                        :disabled="saving"
                        :label="$t('workforce.start_date', 'Start date')"
                        type="date"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="formState.end_date"
                        :disabled="saving"
                        :label="$t('workforce.end_date', 'End date')"
                        type="date"
                        :min="formState.start_date || undefined"
                        :rules="[requiredRule, endDateRule]"
                      />
                    </VCol>
                  </VRow>

                  <div class="chip-row">
                    <VChip
                      v-for="preset in rangePresets"
                      :key="preset.key"
                      size="small"
                      variant="tonal"
                      color="primary"
                      :disabled="saving"
                      @click="applyRangePreset(preset.key)"
                    >
                      {{ preset.title }}
                    </VChip>
                  </div>

                  <div class="weekday-field">
                    <div class="weekday-field__header">
                      <VLabel class="text-body-2 text-wrap text-high-emphasis">
                        {{ $t('workforce.col.days_of_week', 'Weekdays') }}
                      </VLabel>
                      <div class="weekday-field__presets">
                        <VBtn
                          v-for="preset in dayPresets"
                          :key="preset.key"
                          size="small"
                          variant="text"
                          color="primary"
                          :disabled="saving"
                          @click="setDays(preset.days)"
                        >
                          {{ preset.title }}
                        </VBtn>
                      </div>
                    </div>

                    <VBtnToggle
                      v-model="formState.days_of_week"
                      :disabled="saving"
                      multiple
                      divided
                      color="primary"
                      class="day-toggle"
                      :class="{ 'day-toggle--error': daysError }"
                      :aria-label="$t('workforce.col.days_of_week', 'Weekdays')"
                      @update:model-value="daysError = false"
                    >
                      <VBtn
                        v-for="day in dayOptions"
                        :key="day.value"
                        :value="day.value"
                        :disabled="saving"
                      >
                        {{ day.title }}
                      </VBtn>
                    </VBtnToggle>

                    <p
                      v-if="daysError"
                      class="text-caption text-error mt-2 mb-0"
                      role="alert"
                    >
                      {{ $t('workforce.validation.select_weekday', 'Select at least one weekday.') }}
                    </p>
                  </div>
                </section>

                <VDivider />

                <!-- What -->
                <section>
                  <div class="form-section__header">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="28"
                      class="form-section__avatar"
                    >
                      <VIcon
                        icon="tabler-calendar-clock"
                        size="16"
                      />
                    </VAvatar>
                    <span class="form-section__title">{{ $t('workforce.form.sections.timing', 'Schedule details') }}</span>
                  </div>

                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppSelect
                        v-model="formState.shift_id"
                        :disabled="saving"
                        :items="shiftOptions"
                        item-title="title"
                        item-value="id"
                        :label="$t('workforce.col.shift', 'Shift')"
                        :placeholder="$t('workforce.select_shift', 'Select a shift')"
                        prepend-inner-icon="tabler-clock"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppSelect
                        v-model="formState.lifecycle_status"
                        :disabled="saving"
                        :items="statusOptions"
                        :label="$t('workforce.col.sch_status', 'Initial status')"
                        :rules="[requiredRule]"
                      />
                    </VCol>

                    <VCol cols="12">
                      <div class="option-group">
                        <VSwitch
                          v-model="formState.skip_approved_leaves"
                          :disabled="saving"
                          :label="$t('workforce.skip_approved_leaves', 'Skip approved leave')"
                          :hint="$t('workforce.skip_approved_leaves_hint', 'Days where an employee is on approved leave are left untouched.')"
                          persistent-hint
                          color="success"
                        />
                        <VSwitch
                          v-model="formState.skip_holidays"
                          :disabled="saving"
                          :label="$t('workforce.skip_holidays', 'Skip public holidays')"
                          :hint="$t('workforce.skip_holidays_hint', 'Do not assign shifts on recognized public holidays.')"
                          persistent-hint
                          color="info"
                        />
                        <VSwitch
                          v-model="formState.include_weekends"
                          :disabled="saving"
                          :label="$t('workforce.include_weekends', 'Include weekends')"
                          :hint="$t('workforce.include_weekends_hint', 'Allow scheduling Saturday and Sunday when selected in days.')"
                          persistent-hint
                          color="primary"
                        />
                        <VSwitch
                          v-model="formState.overwrite_existing"
                          :disabled="saving"
                          :label="$t('workforce.overwrite_existing', 'Overwrite existing')"
                          :hint="$t('workforce.overwrite_existing_hint', 'Replace schedules that already exist on these days.')"
                          persistent-hint
                          color="warning"
                        />
                      </div>
                    </VCol>

                    <VCol cols="12">
                      <AppTextField
                        v-model="formState.notes"
                        :disabled="saving"
                        :label="$t('workforce.col.notes', 'Batch note')"
                        :placeholder="$t('workforce.notes_placeholder_optional', 'Optional')"
                      />
                    </VCol>
                  </VRow>
                </section>
              </div>
            </VForm>
          </div>
        </PerfectScrollbar>
      </VCardText>

      <!-- Live summary: always visible so the impact is clear before generating -->
      <VDivider />
      <div
        class="dialog-summary"
        :class="{
          'dialog-summary--warning': summaryState === 'empty' || (summaryState === 'ready' && formState.overwrite_existing),
        }"
        role="status"
        aria-live="polite"
      >
        <VAvatar
          size="36"
          variant="tonal"
          :color="summaryState === 'idle' ? 'secondary' : (summaryState === 'empty' || formState.overwrite_existing) ? 'warning' : 'primary'"
        >
          <VIcon
            :icon="summaryState === 'empty' ? 'tabler-calendar-off' : 'tabler-calendar-stats'"
            size="20"
          />
        </VAvatar>

        <div class="dialog-summary__text">
          <template v-if="summaryState === 'ready'">
            <div class="dialog-summary__title">
              {{ $t('workforce.bulk_summary_total', { count: totalSchedules }, 'Up to {count} schedules') }}
            </div>
            <div class="dialog-summary__detail">
              {{ $t('workforce.bulk_summary_detail', { employees: employeeCount, days: matchingDayCount }, '{employees} employees × {days} days') }}
            </div>
            <div
              v-if="formState.overwrite_existing"
              class="dialog-summary__detail text-warning"
            >
              {{ $t('workforce.bulk_summary_overwrite', 'Existing schedules on these days will be replaced.') }}
            </div>
          </template>
          <div
            v-else-if="summaryState === 'empty'"
            class="dialog-summary__detail"
          >
            {{ $t('workforce.bulk_summary_empty', 'None of the selected weekdays fall inside this date range.') }}
          </div>
          <div
            v-else
            class="dialog-summary__detail"
          >
            {{ $t('workforce.bulk_summary_idle', 'Choose employees, dates and weekdays to preview how many schedules will be created.') }}
          </div>
        </div>
      </div>

      <VDivider />

      <VCardActions class="dialog-footer">
        <VBtn
          type="button"
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          :disabled="saving"
          @click="close"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          type="submit"
          :form="formId"
          color="primary"
          class="flex-grow-1 flex-sm-grow-0"
          prepend-icon="tabler-calendar-plus"
          :loading="saving"
        >
          {{ $t('workforce.generate_schedules', 'Generate schedules') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.dialog-header {
  flex-shrink: 0;
  padding-block: 1rem;
  padding-inline: 1rem 3.5rem;

  @media (min-width: 600px) {
    padding-block: 1.25rem;
    padding-inline: 1.5rem 4rem;
  }
}

.dialog-body {
  overflow: hidden;
  flex: 1 1 auto;
  padding: 0;
  min-block-size: 0;
}

.dialog-card {
  max-block-size: 90vh;
}

.dialog-scroll {
  block-size: 100%;
  max-block-size: 50vh;
}

// Fullscreen on mobile is driven by the `fullscreen` dialog prop (useDisplay),
// so on small screens the scroll area simply fills the remaining card height.
@media (max-width: 600px) {
  .dialog-scroll {
    max-block-size: none;
  }
}

.dialog-footer {
  flex-shrink: 0;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;

  @media (min-width: 600px) {
    padding-block: 1rem;
    padding-inline: 1.5rem;
  }

  .v-btn + .v-btn {
    margin-inline-start: 0;
  }
}

.form-sections {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-section__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.75rem;
}

.form-section__avatar {
  flex-shrink: 0;
}

.form-section__title {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.375rem;
}

/* Employee quick actions */
.quick-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  margin-block-start: 0.25rem;

  &__count {
    margin-inline-start: auto;
  }
}

/* Date presets */
.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-block-start: 0.25rem;
}

/* Weekdays: 7 equal tiles that fit a 360px screen */
.weekday-field {
  margin-block-start: 1.25rem;

  &__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem 0.5rem;
    margin-block-end: 0.25rem;
  }

  &__presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
  }
}

.day-toggle {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  inline-size: 100%;

  :deep(.v-btn) {
    min-inline-size: 0;
    padding-inline: 0;
  }

  &--error {
    outline: 1px solid rgb(var(--v-theme-error));
    outline-offset: 2px;
  }
}

/* Options */
.option-group {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  gap: 0.5rem;
  padding-block: 0.5rem;
  padding-inline: 1rem;
}

/* Live summary */
.dialog-summary {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  background: rgba(var(--v-theme-primary), 0.06);
  gap: 0.75rem;
  margin-block-start: 0.5rem;
  min-block-size: 4.5rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;

  @media (min-width: 600px) {
    padding-inline: 1.5rem;
  }

  &--warning {
    background: rgba(var(--v-theme-warning), 0.08);
  }

  &__text {
    min-inline-size: 0;
  }

  &__title {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1.375rem;
  }

  &__detail {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    line-height: 1.25rem;
  }
}
</style>
