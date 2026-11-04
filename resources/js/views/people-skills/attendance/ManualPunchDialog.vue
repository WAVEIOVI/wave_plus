<script setup>
/* eslint-disable camelcase */
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  employees: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()
const { xs } = useDisplay()

const nowTime = () => {
  const d = new Date()

  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const today = () => {
  const d = new Date()

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const eventType = ref('in')

const form = reactive({
  employee_id: null,
  date: today(),
  time: nowTime(),
  notes: '',
})

const selectedEmployee = computed(() =>
  props.employees.find(e => e.id === form.employee_id) || null,
)

const employeeOptions = computed(() =>
  props.employees.map(emp => ({
    value: emp.id,
    title: emp.full_name || emp.title,
    subtitle: `#${emp.employee_number || ''}`,
    avatar: emp.avatar_url,
  })),
)

const punchPreview = computed(() => {
  const label = eventType.value === 'in' ? t('attendance.check_in', 'Check-in') : t('attendance.check_out', 'Check-out')
  const when = `${form.date} ${form.time}`

  return `${label} · ${when}`
})

// A punch more than a few minutes ahead of now is almost certainly a typo.
const isFuture = computed(() => {
  if (!form.date || !form.time) return false
  const at = new Date(`${form.date}T${form.time}:00`)

  return !Number.isNaN(at.getTime()) && at.getTime() > Date.now() + 5 * 60 * 1000
})

const canSubmit = computed(() => {
  return form.employee_id && form.date && form.time
})

const resetForm = () => {
  Object.assign(form, {
    employee_id: null,
    date: today(),
    time: nowTime(),
    notes: '',
  })
  eventType.value = 'in'
}

// Start fresh every time the dialog opens. The form is no longer wiped on submit,
// so a failed request keeps what the user typed.
watch(() => props.isDialogVisible, visible => {
  if (visible) resetForm()
})

const onClose = () => {
  emit('update:isDialogVisible', false)
}

const submit = () => {
  if (!canSubmit.value || props.busy) return

  emit('submit', {
    employee_id: form.employee_id,
    event_type: eventType.value,
    event_time: `${form.date} ${form.time}:00`,
    notes: form.notes || null,
    source: 'manual',
  })
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="600"
    scrollable
    :fullscreen="xs"
    @update:model-value="onClose"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          icon="tabler-edit"
          color="primary"
        />
        <span>{{ $t('attendance.manual_punch', 'Manual Punch Entry') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :aria-label="$t('Close')"
          @click="onClose"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <VRow>
          <!-- Employee Selection -->
          <VCol cols="12">
            <AppAutocomplete
              v-model="form.employee_id"
              :items="employeeOptions"
              :label="$t('attendance.select_employee', 'Select Employee')"
              :placeholder="$t('attendance.search_employee', 'Search employee...')"
              variant="outlined"
              density="comfortable"
              clearable
              :loading="loading"
              :disabled="busy"
            >
              <template #item="{ item, props: itemProps }">
                <VListItem v-bind="itemProps">
                  <template #prepend>
                    <VAvatar
                      size="32"
                      color="primary"
                      variant="tonal"
                    >
                      <VImg
                        v-if="item.raw.avatar"
                        :src="item.raw.avatar"
                      />
                      <span
                        v-else
                        class="text-caption"
                      >
                        {{ avatarText(item.raw.title) }}
                      </span>
                    </VAvatar>
                  </template>
                  <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                  <VListItemSubtitle>{{ item.raw.subtitle }}</VListItemSubtitle>
                </VListItem>
              </template>
            </AppAutocomplete>
          </VCol>

          <!-- Event Type -->
          <VCol cols="12">
            <label class="text-caption text-medium-emphasis">{{ $t('attendance.punch_type', 'Punch Type') }}</label>
            <VBtnToggle
              v-model="eventType"
              color="primary"
              variant="outlined"
              divided
              mandatory
              class="mt-1 w-100"
              :disabled="busy"
            >
              <VBtn
                value="in"
                size="large"
                class="flex-grow-1"
              >
                <VIcon
                  icon="tabler-login"
                  start
                />
                {{ $t('attendance.clock_in', 'Clock In') }}
              </VBtn>
              <VBtn
                value="out"
                size="large"
                class="flex-grow-1"
              >
                <VIcon
                  icon="tabler-logout"
                  start
                />
                {{ $t('attendance.clock_out', 'Clock Out') }}
              </VBtn>
            </VBtnToggle>
          </VCol>

          <!-- Date -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.date"
              :label="$t('attendance.date', 'Date')"
              variant="outlined"
              density="comfortable"
              :disabled="busy"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', maxDate: 'today' }"
            />
          </VCol>

          <!-- Time -->
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.time"
              :label="$t('attendance.time', 'Time')"
              type="time"
              variant="outlined"
              density="comfortable"
              :disabled="busy"
            />
          </VCol>

          <!-- Future time warning -->
          <VCol
            v-if="isFuture"
            cols="12"
          >
            <VAlert
              color="warning"
              variant="tonal"
              density="comfortable"
              icon="tabler-alert-triangle"
            >
              {{ $t('attendance.future_punch_warning', 'This time is in the future. Check the date and time before recording.') }}
            </VAlert>
          </VCol>

          <!-- Notes -->
          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              :label="$t('attendance.notes', 'Notes (optional)')"
              :placeholder="$t('attendance.notes_placeholder', 'Add any relevant notes...')"
              variant="outlined"
              density="comfortable"
              rows="2"
              :disabled="busy"
            />
          </VCol>

          <!-- Summary -->
          <VCol
            v-if="selectedEmployee"
            cols="12"
          >
            <VAlert
              color="info"
              variant="tonal"
              density="comfortable"
              icon="tabler-info-circle"
            >
              <div class="text-body-2 font-weight-medium">
                {{ punchPreview }}
              </div>
              <div class="text-caption">
                {{ $t('attendance.for_employee', 'For:') }} {{ selectedEmployee.full_name || selectedEmployee.title }}
              </div>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="onClose"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="busy"
          :disabled="!canSubmit || busy"
          @click="submit"
        >
          <VIcon
            icon="tabler-check"
            start
          />
          {{ $t('attendance.record_punch', 'Record Punch') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
