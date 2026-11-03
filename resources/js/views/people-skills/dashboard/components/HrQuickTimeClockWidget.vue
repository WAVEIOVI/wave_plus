<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { useHrStore } from '@/stores/hrStore'
import { $api } from '@/utils/api'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['clockEvent'])

const { t } = useI18n()
const attendanceStore = useAttendanceStore()
const hrStore = useHrStore()
const { success, error: showSnackbarError } = useAppSnackbar()

const currentTime = ref('')
const selectedEmployeeId = ref(null)
const employeeOptions = ref([])
const isClocking = ref(false)
const isInitializing = ref(true)
let timer = null

// Deviation Confirmation Dialog state
const isDeviationDialogOpen = ref(false)
const deviationContext = ref(null)
const clockNote = ref('')
const isPendingCheckout = ref(false)

const updateClock = () => {
  const now = new Date()

  currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

const today = computed(() => new Date().toLocaleDateString(undefined, {
  weekday: 'long',
  year: 'numeric',
  month: 'short',
  day: 'numeric',
}))

const clockStatus = computed(() => attendanceStore.todayStatus || {})
const isLinked = computed(() => Boolean(clockStatus.value?.is_linked))
const isClockedIn = computed(() => Boolean(clockStatus.value?.is_clocked_in))
const employeeName = computed(() => clockStatus.value?.employee?.full_name || null)
const employeeTeam = computed(() => clockStatus.value?.employee?.team || null)
const todaySchedule = computed(() => clockStatus.value?.schedule || null)
const todayLeave = computed(() => clockStatus.value?.covering_leave || null)

const loadEmployees = async () => {
  try {
    const res = await $api('/hr/employees', {
      method: 'GET',
      params: { status: 'active', per_page: 100 },
    })

    const employees = res.data?.data || res.data || []

    employeeOptions.value = employees.map(emp => ({
      id: emp.id,
      title: `${emp.full_name} (${emp.employee_number || emp.id})`,
    }))
  }
  catch (err) {
    showSnackbarError(err?.data?.message || err?.message || t('hr.dashboard.employees_load_failed', 'Failed to load employees for the time clock.'))
  }
}

const refreshStatus = async () => {
  try {
    await attendanceStore.fetchTodayStatus(selectedEmployeeId.value)
    if (clockStatus.value?.employee?.id && !selectedEmployeeId.value) {
      selectedEmployeeId.value = clockStatus.value.employee.id
    }
  }
  catch (err) {
    showSnackbarError(err?.data?.message || err?.message || t('hr.dashboard.status_load_failed', 'Failed to load status'))
  }
}

watch(selectedEmployeeId, async (id, previous) => {
  if (id && id !== previous) {
    await refreshStatus()
  }
})

onMounted(async () => {
  updateClock()
  timer = setInterval(updateClock, 1000)
  try {
    await Promise.all([
      hrStore.fetchMeta(),
      loadEmployees(),
      refreshStatus(),
    ])
  }
  finally {
    isInitializing.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const handleToggleClock = async () => {
  const checkingOut = isClockedIn.value

  isPendingCheckout.value = checkingOut

  // Check preview context for deviations
  try {
    const payload = selectedEmployeeId.value ? { employee_id: selectedEmployeeId.value } : {}

    const previewRes = await $api('/hr/time-clock/preview-context', {
      method: 'POST',
      body: {
        ...payload,
        event_type: checkingOut ? 'out' : 'in',
      },
    })

    if (previewRes?.requires_reason) {
      deviationContext.value = previewRes
      clockNote.value = ''
      isDeviationDialogOpen.value = true

      return
    }
  }
  catch {
    // If preview endpoint fails or optional, proceed directly
  }

  // Execute directly if no deviation note required
  await executeClockAction()
}

const executeClockAction = async () => {
  const checkingOut = isPendingCheckout.value

  isClocking.value = true
  try {
    const payload = selectedEmployeeId.value ? { employee_id: selectedEmployeeId.value } : {}

    if (clockNote.value) {
      payload.notes = clockNote.value
    }

    if (checkingOut) {
      await attendanceStore.clockOut(payload)
    }
    else {
      await attendanceStore.clockIn(payload)
    }

    success(t(checkingOut ? 'hr.dashboard.clock_out_recorded' : 'hr.dashboard.clock_in_recorded', 'Punch recorded.'))
    emit('clockEvent')
    isDeviationDialogOpen.value = false
    await refreshStatus()
  }
  catch (err) {
    showSnackbarError(err?.data?.message || err?.message || t('hr.dashboard.clock_failed', 'Clock event failed'))
    await refreshStatus()
  }
  finally {
    isClocking.value = false
  }
}
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-fingerprint" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.quick_clock', 'Quick Time Clock') }}
      </VCardTitle>
      <template #append>
        <VChip
          :color="isClockedIn ? 'success' : 'secondary'"
          size="small"
          label
        >
          {{ $t(isClockedIn ? 'hr.dashboard.on_duty' : 'hr.dashboard.off_duty', isClockedIn ? 'On Duty' : 'Off Duty') }}
        </VChip>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText class="text-center">
      <VSkeletonLoader
        v-if="props.loading || isInitializing"
        type="text, paragraph, paragraph"
        class="bg-transparent"
      />

      <template v-else>
        <div
          class="text-primary mb-1 font-weight-bold"
          style="font-size: 1.8rem; font-variant-numeric: tabular-nums;"
        >
          {{ currentTime }}
        </div>
        <div class="text-caption text-medium-emphasis mb-3">
          {{ today }}
        </div>

        <!-- Employee Selector -->
        <VSelect
          v-model="selectedEmployeeId"
          :items="employeeOptions"
          item-title="title"
          item-value="id"
          :label="$t('hr.dashboard.clock_employee_label', 'Clock for Employee')"
          density="comfortable"
          class="mb-3 text-start"
          clearable
        />

        <!-- Contextual Shift & Leave Information Badge -->
        <div
          v-if="todayLeave"
          class="pa-2 rounded border border-info mb-3 text-start d-flex align-center gap-2"
          style="background-color: rgba(var(--v-theme-info), 0.08);"
        >
          <VIcon
            icon="tabler-beach"
            color="info"
            size="18"
          />
          <div class="text-caption">
            <strong>Authorized Leave:</strong> {{ todayLeave.leave_type_name }}
            <span v-if="todayLeave.duration_type === 'hourly'">({{ todayLeave.start_time }} - {{ todayLeave.end_time }})</span>
          </div>
        </div>

        <div
          v-else-if="todaySchedule"
          class="pa-2 rounded border border-primary mb-3 text-start d-flex align-center justify-space-between"
          style="background-color: rgba(var(--v-theme-primary), 0.08);"
        >
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-calendar-time"
              color="primary"
              size="18"
            />
            <div class="text-caption">
              <strong>Shift:</strong> {{ todaySchedule.shift_name }} ({{ todaySchedule.start_time?.substring(0, 5) }} - {{ todaySchedule.end_time?.substring(0, 5) }})
            </div>
          </div>
          <VIcon
            v-if="todaySchedule.is_overnight"
            icon="tabler-moon"
            color="secondary"
            size="16"
          />
        </div>

        <VAlert
          v-if="!selectedEmployeeId && !isLinked"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4 text-start"
        >
          {{ $t('hr.dashboard.clock_not_linked', 'Please select an employee to record clock event.') }}
        </VAlert>

        <!-- Main Punch Button -->
        <VBtn
          size="large"
          block
          :color="isClockedIn ? 'error' : 'success'"
          :loading="isClocking"
          :disabled="!selectedEmployeeId && !isLinked"
          class="mb-4"
          elevation="1"
          @click="handleToggleClock"
        >
          <VIcon
            :icon="isClockedIn ? 'tabler-door-exit' : 'tabler-door-enter'"
            start
          />
          {{ $t(isClockedIn ? 'hr.dashboard.clock_out' : 'hr.dashboard.clock_in', isClockedIn ? 'Clock Out' : 'Clock In') }}
        </VBtn>

        <!-- Meta Footer -->
        <div class="d-flex justify-space-between align-center text-body-2 gap-3 pt-2 border-t">
          <div class="text-start">
            <div class="text-caption text-medium-emphasis">
              {{ $t('hr.dashboard.employee_label', 'Staff Member') }}
            </div>
            <div class="font-weight-medium text-truncate">
              {{ employeeName || $t('hr.dashboard.not_selected', 'Not selected') }}
            </div>
          </div>
          <div class="text-end">
            <div class="text-caption text-medium-emphasis">
              {{ $t('hr.dashboard.team_label', 'Team') }}
            </div>
            <div class="font-weight-medium text-truncate">
              {{ employeeTeam || '—' }}
            </div>
          </div>
        </div>
      </template>
    </VCardText>

    <!-- Deviation / Reason Modal -->
    <VDialog
      v-model="isDeviationDialogOpen"
      max-width="480"
      persistent
    >
      <VCard class="pa-4">
        <VCardTitle class="px-0 pt-0 d-flex align-center gap-2 text-warning">
          <VIcon icon="tabler-alert-triangle" />
          <span>{{ isPendingCheckout ? 'Early Departure Notice' : 'Late Arrival Notice' }}</span>
        </VCardTitle>

        <VCardText class="px-0 py-3">
          <VAlert
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mb-3"
          >
            {{ deviationContext?.message || 'A deviation from scheduled work hours has been detected.' }}
          </VAlert>

          <VTextarea
            v-model="clockNote"
            label="Reason / Note for Supervisor"
            rows="3"
            density="comfortable"
            placeholder="e.g. Traffic delay, doctor appointment, approved early finish..."
            autofocus
          />
        </VCardText>

        <VCardActions class="px-0 pb-0 d-flex justify-end gap-2">
          <VBtn
            variant="text"
            color="secondary"
            @click="isDeviationDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :loading="isClocking"
            @click="executeClockAction"
          >
            Confirm & Punch
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
