<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useLeaveRequestStore } from '@/stores/useLeaveRequestStore'
import { $api } from '@/utils/api'
import { avatarText } from '@core/utils/formatters'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  leaveRequest: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const hrStore = useHrStore()
const leaveStore = useLeaveRequestStore()

const isSubmitting = ref(false)
const employees = ref([])
const employeesLoading = ref(false)
const balanceInfo = ref(null)
const balanceLoading = ref(false)

const form = ref({
  employee_id: null,
  leave_type_id: null,
  duration_type: 'full_day',
  start_date: '',
  end_date: '',
  start_time: '08:00',
  end_time: '12:00',
  reason: '',
  submit: true,
})

const isVisible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isHourlyLeave = computed(() => ['hourly', 'partial_day'].includes(form.value.duration_type))

const durationTypeOptions = computed(() => [
  { title: t('time_off.duration_type.full_day'), value: 'full_day' },
  { title: t('time_off.duration_type.partial_day'), value: 'partial_day' },
  { title: t('time_off.duration_type.hourly'), value: 'hourly' },
])

const leaveTypeOptions = computed(() =>
  (hrStore.leaveTypes || []).map(lt => ({
    title: localizedValue(lt.name),
    subtitle: `${lt.code || ''}${lt.is_paid ? ' · ' + t('Paid') : ''}`,
    value: lt.id,
    allows_hourly: lt.allows_hourly,
  })),
)

const employeeOptions = computed(() =>
  employees.value.map(emp => ({
    title: emp.full_name,
    subtitle: `#${emp.employee_number || emp.id.slice(0, 6)} · ${localizedValue(emp.department?.name || emp.department || '—')}`,
    value: emp.id,
    avatar_url: emp.avatar_url,
    department: localizedValue(emp.department?.name || emp.department || ''),
  })),
)

// Dynamic calculation of total days
const calculationSummary = computed(() => {
  if (form.value.duration_type === 'hourly') {
    if (!form.value.start_date || !form.value.start_time || !form.value.end_time) {
      return { days: 0, hours: 0, isValid: false }
    }
    const [sh, sm] = form.value.start_time.split(':').map(Number)
    const [eh, em] = form.value.end_time.split(':').map(Number)
    const startMin = (sh || 0) * 60 + (sm || 0)
    const endMin = (eh || 0) * 60 + (em || 0)
    if (endMin <= startMin) return { days: 0, hours: 0, isValid: false }
    const totalMinutes = endMin - startMin
    const hours = Math.round((totalMinutes / 60) * 100) / 100
    const days = Math.max(0.05, Math.round((hours / 8.0) * 100) / 100)

    return { days, hours, isValid: true }
  }

  if (!form.value.start_date || !form.value.end_date) {
    return { days: 0, hours: 0, isValid: false }
  }

  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  if (end < start) return { days: 0, hours: 0, isValid: false }
  const diffTime = Math.abs(end - start)
  const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1

  return { days, hours: days * 8, isValid: true }
})

const isBalanceExceeded = computed(() => {
  if (!balanceInfo.value || !calculationSummary.value.isValid) return false
  const available = Number(balanceInfo.value.available_days ?? balanceInfo.value.remaining_days ?? 0)

  return calculationSummary.value.days > available
})

const loadEmployees = async () => {
  if (employees.value.length > 0) return
  employeesLoading.value = true
  try {
    const res = await $api('/hr/employees', { method: 'GET', params: { status: 'active', per_page: 500 } })

    employees.value = (res.data || []).map(emp => ({
      id: emp.id,
      full_name: emp.full_name,
      employee_number: emp.employee_number,
      avatar_url: emp.avatar_url,
      department: emp.department?.name || emp.department || null,
    }))
  } catch (err) {
    console.error('Failed to load employees for leave request form', err)
  } finally {
    employeesLoading.value = false
  }
}

const checkBalance = async () => {
  if (!form.value.employee_id || !form.value.leave_type_id) {
    balanceInfo.value = null

    return
  }

  balanceLoading.value = true
  try {
    const year = form.value.start_date
      ? new Date(form.value.start_date).getFullYear()
      : new Date().getFullYear()

    const res = await $api('/hr/leave-balances', {
      method: 'GET',
      params: { employee_id: form.value.employee_id, year },
    })

    const list = res.data || res || []

    balanceInfo.value = list.find(b => String(b.leave_type_id) === String(form.value.leave_type_id)) || null
  } catch (err) {
    console.warn('Failed to check leave balance', err)
    balanceInfo.value = null
  } finally {
    balanceLoading.value = false
  }
}

watch(() => [form.value.employee_id, form.value.leave_type_id, form.value.start_date], () => {
  checkBalance()
})

const resetForm = () => {
  form.value = {
    employee_id: employees.value.length === 1 ? employees.value[0].id : null,
    leave_type_id: hrStore.leaveTypes?.[0]?.id || null,
    duration_type: 'full_day',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    start_time: '08:00',
    end_time: '12:00',
    reason: '',
    submit: true,
  }
  balanceInfo.value = null
}

watch(isVisible, val => {
  if (val) {
    loadEmployees()
    if (props.leaveRequest) {
      form.value = {
        employee_id: props.leaveRequest.employee_id,
        leave_type_id: props.leaveRequest.leave_type_id,
        duration_type: props.leaveRequest.duration_type || 'full_day',
        start_date: props.leaveRequest.start_date,
        end_date: props.leaveRequest.end_date,
        start_time: props.leaveRequest.start_time || '08:00',
        end_time: props.leaveRequest.end_time || '12:00',
        reason: props.leaveRequest.reason || '',
        submit: true,
      }
    } else {
      resetForm()
    }
  }
})

const handleSubmit = async (submitNow = true) => {
  if (!form.value.employee_id) {
    showMessage(t('time_off.form.select_employee'), 'warning')

    return
  }
  if (!form.value.leave_type_id) {
    showMessage(t('time_off.form.select_type'), 'warning')

    return
  }
  if (!form.value.start_date) {
    showMessage(t('time_off.form.from') + ' ' + t('is required'), 'warning')

    return
  }
  if (form.value.duration_type !== 'hourly' && !form.value.end_date) {
    showMessage(t('time_off.form.to') + ' ' + t('is required'), 'warning')

    return
  }
  if (calculationSummary.value.days <= 0) {
    showMessage(t('Invalid date range or duration'), 'warning')

    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      employee_id: form.value.employee_id,
      leave_type_id: form.value.leave_type_id,
      duration_type: form.value.duration_type,
      start_date: form.value.start_date,
      end_date: form.value.duration_type === 'hourly' ? form.value.start_date : form.value.end_date,
      total_days: calculationSummary.value.days,
      reason: form.value.reason || null,
      submit: submitNow,
    }

    if (isHourlyLeave.value) {
      payload.start_time = form.value.start_time
      payload.end_time = form.value.end_time
    }

    await leaveStore.create(payload)
    showMessage(t('time_off.create_success'), 'success')
    isVisible.value = false
    emit('saved')
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.create_error'), 'error')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="640"
    persistent
  >
    <VCard class="pa-2">
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
          >
            <VIcon
              icon="tabler-calendar-plus"
              size="20"
            />
          </VAvatar>
          <span class="text-h6 font-weight-bold">{{ $t('time_off.form.title') }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          density="comfortable"
          @click="isVisible = false"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <VRow>
          <!-- Employee Selector -->
          <VCol cols="12">
            <AppSelect
              v-model="form.employee_id"
              :items="employeeOptions"
              item-title="title"
              item-value="value"
              :label="$t('time_off.form.employee')"
              :placeholder="$t('time_off.form.select_employee')"
              :loading="employeesLoading"
              clearable
            >
              <template #selection="{ item }">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    size="24"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="item.raw.avatar_url"
                      :src="item.raw.avatar_url"
                    />
                    <span
                      v-else
                      class="text-caption"
                    >{{ avatarText(item.raw.title) }}</span>
                  </VAvatar>
                  <span class="text-body-2">{{ item.raw.title }}</span>
                </div>
              </template>
              <template #item="{ item, props: itemProps }">
                <VListItem
                  v-bind="itemProps"
                  :subtitle="item.raw.subtitle"
                >
                  <template #prepend>
                    <VAvatar
                      size="32"
                      color="primary"
                      variant="tonal"
                      class="me-2"
                    >
                      <VImg
                        v-if="item.raw.avatar_url"
                        :src="item.raw.avatar_url"
                      />
                      <span
                        v-else
                        class="text-caption"
                      >{{ avatarText(item.raw.title) }}</span>
                    </VAvatar>
                  </template>
                </VListItem>
              </template>
            </AppSelect>
          </VCol>

          <!-- Leave Type Selector -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.leave_type_id"
              :items="leaveTypeOptions"
              item-title="title"
              item-value="value"
              :label="$t('time_off.form.type')"
              :placeholder="$t('time_off.form.select_type')"
            />
          </VCol>

          <!-- Duration Type -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.duration_type"
              :items="durationTypeOptions"
              :label="$t('time_off.form.duration_type')"
            />
          </VCol>

          <!-- Balance Badge / Preview -->
          <VCol
            v-if="form.employee_id && form.leave_type_id"
            cols="12"
          >
            <div
              class="d-flex align-center justify-space-between pa-3 rounded-lg border bg-surface"
              :class="isBalanceExceeded ? 'border-error' : ''"
            >
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-wallet"
                  size="20"
                  :color="isBalanceExceeded ? 'error' : 'primary'"
                />
                <div>
                  <div class="text-caption text-medium-emphasis">
                    {{ $t('time_off.form.available_balance') }}
                  </div>
                  <div class="text-body-2 font-weight-bold">
                    {{ balanceLoading ? '...' : (balanceInfo ? `${balanceInfo.available_days ?? balanceInfo.remaining_days ?? 0} ${$t('time_off.balances.days')}` : '—') }}
                  </div>
                </div>
              </div>

              <div class="text-end">
                <div class="text-caption text-medium-emphasis">
                  {{ $t('time_off.form.days_requested') }}
                </div>
                <VChip
                  size="small"
                  :color="isBalanceExceeded ? 'error' : 'primary'"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ calculationSummary.days }}d
                  <span
                    v-if="calculationSummary.hours && isHourlyLeave"
                    class="ms-1"
                  >({{ calculationSummary.hours }}h)</span>
                </VChip>
              </div>
            </div>

            <VAlert
              v-if="isBalanceExceeded"
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-2"
            >
              {{ $t('The requested leave days exceed the currently available balance.') }}
            </VAlert>
          </VCol>

          <!-- Start Date -->
          <VCol
            cols="12"
            :sm="form.duration_type === 'hourly' ? 12 : 6"
          >
            <AppDateTimePicker
              v-model="form.start_date"
              :label="$t('time_off.form.from')"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
            />
          </VCol>

          <!-- End Date (Full / Partial day) -->
          <VCol
            v-if="form.duration_type !== 'hourly'"
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.end_date"
              :label="$t('time_off.form.to')"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', minDate: form.start_date }"
            />
          </VCol>

          <!-- Start & End Time (Hourly / Partial day) -->
          <VCol
            v-if="isHourlyLeave"
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.start_time"
              :label="$t('time_off.form.start_time')"
              :config="{ enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true }"
            />
          </VCol>

          <VCol
            v-if="isHourlyLeave"
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.end_time"
              :label="$t('time_off.form.end_time')"
              :config="{ enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true }"
            />
          </VCol>

          <!-- Hourly info note -->
          <VCol
            v-if="isHourlyLeave"
            cols="12"
          >
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
            >
              {{ $t('time_off.form.hourly_hint') }}
            </VAlert>
          </VCol>

          <!-- Reason Field -->
          <VCol cols="12">
            <AppTextarea
              v-model="form.reason"
              :label="$t('time_off.form.reason')"
              :placeholder="$t('time_off.form.reason_placeholder')"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-end gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="isVisible = false"
        >
          {{ $t('Cancel') }}
        </VBtn>

        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="handleSubmit(true)"
        >
          <VIcon
            icon="tabler-send"
            start
          />
          {{ $t('time_off.form.submit_approval') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
