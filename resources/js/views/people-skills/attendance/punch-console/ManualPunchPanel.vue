<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employees: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  loadingEmployees: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const { t } = useI18n()

const nowTime = () => {
  const date = new Date()

  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const shiftedDate = (offsetDays = 0) => {
  const date = new Date()

  date.setDate(date.getDate() + offsetDays)

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const today = () => shiftedDate(0)
const yesterday = () => shiftedDate(-1)

const mode = ref('punch')
const eventType = ref('in')

const form = ref({
  employee_id: null,
  date: today(),
  time: nowTime(),
  scheduled_in: '',
  scheduled_out: '',
  actual_in: '',
  actual_out: '',
  status: 'present',
  notes: '',
})

const statusOptions = computed(() => [
  { title: t('hr.attendance.status_present', 'Present'), value: 'present' },
  { title: t('hr.attendance.status_late', 'Late'), value: 'late' },
  { title: t('hr.attendance.status_half_day', 'Half day'), value: 'half_day' },
  { title: t('hr.attendance.status_on_leave', 'On leave'), value: 'on_leave' },
  { title: t('hr.attendance.status_absent', 'Absent'), value: 'absent' },
  { title: t('hr.attendance.status_partial', 'Partial'), value: 'partial' },
])

const scheduleFields = computed(() => [
  { key: 'scheduled_in', label: t('hr.attendance.scheduled_in', 'Scheduled in') },
  { key: 'scheduled_out', label: t('hr.attendance.scheduled_out', 'Scheduled out') },
])

const actualFields = computed(() => [
  { key: 'actual_in', label: t('hr.attendance.actual_in', 'Actual in') },
  { key: 'actual_out', label: t('hr.attendance.actual_out', 'Actual out') },
])

const selectedEmployee = computed(() =>
  props.employees.find(employee => String(employee.id) === String(form.value.employee_id)) || null,
)

watch(() => selectedEmployee.value?.id, () => {
  form.value.scheduled_in = ''
  form.value.scheduled_out = ''
  form.value.actual_in = ''
  form.value.actual_out = ''
  form.value.notes = ''
})

const punchPreview = computed(() => {
  const label = eventType.value === 'in'
    ? t('hr.attendance.check_in', 'Check In')
    : t('hr.attendance.check_out', 'Check Out')

  return `${label} · ${form.value.date} ${form.value.time}`
})

const toMinutes = value => {
  if (!value) return null

  const [hours, minutes] = String(value).split(':').map(Number)

  return Number.isNaN(hours) || Number.isNaN(minutes) ? null : (hours * 60) + minutes
}

const workedMinutes = computed(() => {
  const start = toMinutes(form.value.actual_in)
  const end = toMinutes(form.value.actual_out)

  return start === null || end === null ? null : end - start
})

const hasInvalidRange = computed(() => workedMinutes.value !== null && workedMinutes.value <= 0)

const workedLabel = computed(() => {
  if (workedMinutes.value === null || workedMinutes.value <= 0) return ''

  return `${Math.floor(workedMinutes.value / 60)}h ${String(workedMinutes.value % 60).padStart(2, '0')}m`
})

const canSubmit = computed(() => {
  if (!form.value.employee_id) return false
  if (mode.value === 'punch') return Boolean(form.value.date && form.value.time)

  return Boolean(form.value.actual_in || form.value.actual_out || form.value.scheduled_in || form.value.scheduled_out)
})

const submit = () => {
  if (!canSubmit.value || props.busy) return

  if (mode.value === 'punch') {
    emit('submit', {
      kind: 'punch',
      employee_id: form.value.employee_id,
      event_type: eventType.value,
      event_time: `${form.value.date} ${form.value.time}:00`,
      notes: form.value.notes || null,
      full_name: selectedEmployee.value?.title,
    })

    return
  }

  emit('submit', {
    kind: 'record',
    employee_id: form.value.employee_id,
    date: form.value.date,
    scheduled_in: form.value.scheduled_in || null,
    scheduled_out: form.value.scheduled_out || null,
    actual_in: form.value.actual_in || null,
    actual_out: form.value.actual_out || null,
    status: form.value.status,
    notes: form.value.notes || null,
    full_name: selectedEmployee.value?.title,
  })
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-edit"
          color="primary"
        />
        <span class="text-body-2 font-weight-semibold">
          {{ $t('hr.attendance.manual_entry', 'Manual entry') }}
        </span>
      </div>

      <VBtnToggle
        v-model="mode"
        mandatory
        density="comfortable"
        color="primary"
        variant="outlined"
        divided
      >
        <VBtn
          value="punch"
          size="small"
          prepend-icon="tabler-hand-finger"
        >
          {{ $t('hr.attendance.log_punch', 'Log a punch') }}
        </VBtn>
        <VBtn
          value="record"
          size="small"
          prepend-icon="tabler-clipboard-text"
        >
          {{ $t('hr.attendance.full_record', 'Full record') }}
        </VBtn>
      </VBtnToggle>
    </div>

    <VRow dense>
      <VCol
        cols="12"
        sm="6"
      >
        <VAutocomplete
          v-model="form.employee_id"
          :items="employees"
          item-title="title"
          item-value="id"
          :label="$t('hr.attendance.employee', 'Employee')"
          :no-data-text="$t('hr.attendance.no_employees', 'No employees match these filters.')"
          :loading="loadingEmployees"
          prepend-inner-icon="tabler-user-search"
          density="comfortable"
          variant="outlined"
          hide-details
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.date"
          type="date"
          :label="$t('hr.attendance.date', 'Date')"
          prepend-inner-icon="tabler-calendar"
          density="comfortable"
          variant="outlined"
          hide-details
        />
      </VCol>
    </VRow>

    <div class="d-flex align-center flex-wrap gap-2 mt-2 mb-4">
      <VChip
        size="small"
        color="primary"
        :variant="form.date === today() ? 'flat' : 'tonal'"
        @click="form.date = today()"
      >
        {{ $t('hr.attendance.today', 'Today') }}
      </VChip>
      <VChip
        size="small"
        color="primary"
        :variant="form.date === yesterday() ? 'flat' : 'tonal'"
        @click="form.date = yesterday()"
      >
        {{ $t('hr.attendance.yesterday', 'Yesterday') }}
      </VChip>
    </div>

    <template v-if="mode === 'punch'">
      <div class="mb-4">
        <div class="text-overline text-medium-emphasis mb-2">
          {{ $t('hr.attendance.event_type', 'Punch type') }}
        </div>
        <VRow dense>
          <VCol cols="6">
            <VBtn
              block
              size="large"
              color="success"
              :variant="eventType === 'in' ? 'flat' : 'tonal'"
              :aria-pressed="eventType === 'in'"
              prepend-icon="tabler-door-enter"
              @click="eventType = 'in'"
            >
              {{ $t('hr.attendance.check_in', 'Check In') }}
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              block
              size="large"
              color="warning"
              :variant="eventType === 'out' ? 'flat' : 'tonal'"
              :aria-pressed="eventType === 'out'"
              prepend-icon="tabler-door-exit"
              @click="eventType = 'out'"
            >
              {{ $t('hr.attendance.check_out', 'Check Out') }}
            </VBtn>
          </VCol>
        </VRow>
      </div>

      <VRow dense>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.time"
            type="time"
            :label="$t('hr.attendance.time', 'Time')"
            prepend-inner-icon="tabler-clock"
            append-inner-icon="tabler-clock-play"
            density="comfortable"
            variant="outlined"
            hide-details
            @click:append-inner="form.time = nowTime()"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.notes"
            :label="$t('hr.attendance.notes', 'Reason / note')"
            prepend-inner-icon="tabler-note"
            density="comfortable"
            variant="outlined"
            hide-details
          />
        </VCol>
      </VRow>

      <VAlert
        v-if="form.employee_id"
        type="info"
        variant="tonal"
        density="compact"
        class="mt-4"
        prepend-icon="tabler-bolt"
      >
        <div class="font-weight-medium">
          {{ selectedEmployee?.title }}
        </div>
        <div class="text-caption">
          {{ punchPreview }}
        </div>
      </VAlert>
    </template>

    <template v-else>
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <div class="text-overline text-medium-emphasis mb-2">
            {{ $t('hr.attendance.schedule', 'Schedule') }}
          </div>
          <VRow dense>
            <VCol
              v-for="field in scheduleFields"
              :key="field.key"
              cols="6"
            >
              <VTextField
                v-model="form[field.key]"
                type="time"
                :label="field.label"
                density="comfortable"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <div class="text-overline text-medium-emphasis mb-2">
            {{ $t('hr.attendance.actual', 'Actual') }}
          </div>
          <VRow dense>
            <VCol
              v-for="field in actualFields"
              :key="field.key"
              cols="6"
            >
              <VTextField
                v-model="form[field.key]"
                type="time"
                :label="field.label"
                density="comfortable"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
        </VCol>
      </VRow>

      <VAlert
        v-if="hasInvalidRange"
        type="warning"
        variant="tonal"
        density="compact"
        class="mt-4"
      >
        {{ $t('hr.attendance.range_warning', 'Check-out is earlier than check-in. Please review the times.') }}
      </VAlert>
      <VChip
        v-else-if="workedLabel"
        color="success"
        variant="tonal"
        class="mt-4"
        prepend-icon="tabler-hourglass"
      >
        {{ $t('hr.attendance.worked', 'Worked') }} · {{ workedLabel }}
      </VChip>

      <VRow
        dense
        class="mt-2"
      >
        <VCol
          cols="12"
          sm="5"
        >
          <VSelect
            v-model="form.status"
            :items="statusOptions"
            :label="$t('hr.attendance.status', 'Status')"
            density="comfortable"
            variant="outlined"
            hide-details
          />
        </VCol>
        <VCol
          cols="12"
          sm="7"
        >
          <VTextField
            v-model="form.notes"
            :label="$t('hr.attendance.notes_label', 'Notes')"
            density="comfortable"
            variant="outlined"
            hide-details
          />
        </VCol>
      </VRow>
    </template>

    <VBtn
      block
      size="large"
      color="primary"
      :loading="busy"
      :disabled="!canSubmit"
      prepend-icon="tabler-device-floppy"
      class="mt-5"
      @click="submit"
    >
      {{ mode === 'punch'
        ? $t('hr.attendance.record_punch', 'Record punch')
        : $t('hr.attendance.save_record', 'Save attendance record') }}
    </VBtn>
  </div>
</template>
