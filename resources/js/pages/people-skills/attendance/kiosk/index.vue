<script setup>
/* eslint-disable camelcase */
import { WpQrScannerDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { $api } from '@/utils/api'
import AttendanceKioskFeed from '@/views/people-skills/attendance/AttendanceKioskFeed.vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/attendance/kiosk',
  name: 'people-skills-attendance-kiosk',
  meta: {
    layout: 'blank',
    module: 'people-skills',
    action: 'create',
    subject: 'attendance',
  },
})

const { t } = useI18n()
const { lgAndUp } = useDisplay()
const attendanceStore = useAttendanceStore()

// ── Constants ─────────────────────────────────────────────────────────────
const PIN_MIN = 4
const PIN_MAX = 8
const PIN_IDLE_MS = 30000
const FEED_SIZE = 15
const RESULT_SUCCESS_MS = 12000
const RESULT_ERROR_MS = 4000

// ── State ─────────────────────────────────────────────────────────────────
const inputMode = ref('camera') // 'camera' or 'pin'
const soundOn = ref(true)
const clock = ref('')
const dateLabel = ref('')
const isFullscreen = ref(false)
const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
const result = ref(null)
const terminalLocked = ref(false)
const feed = ref([])
const punchStats = ref({ ins: 0, outs: 0 })
const isFeedLoading = ref(false)
const pinValue = ref('')
const qrScannerOpen = ref(false)
const activityOpen = ref(false) // mobile bottom-sheet toggle for the activity feed

let clockTimer = null
let feedTimer = null
let resultTimer = null
let pinIdleTimer = null
let wakeLock = null

// ── Helpers ───────────────────────────────────────────────────────────────
// Local calendar date (toISOString() is UTC and returns "yesterday" just after midnight in UTC+1).
const localDate = (d = new Date()) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

// ── Computed ──────────────────────────────────────────────────────────────
const isBusy = computed(() => terminalLocked.value || !isOnline.value)

const statusText = computed(() => {
  if (!isOnline.value) return t('kiosk.offline')
  if (terminalLocked.value) return t('kiosk.processing')
  if (inputMode.value === 'pin') return t('kiosk.pin_mode')

  return t('kiosk.ready')
})

// Show one empty slot ahead of the cursor, never fewer than PIN_MIN.
const pinSlots = computed(() => {
  const count = Math.max(PIN_MIN, Math.min(PIN_MAX, pinValue.value.length + 1))

  return Array.from({ length: count }, (_, i) => pinValue.value[i] ?? null)
})

const resultTitle = computed(() => {
  if (!result.value) return ''
  if (!result.value.success) return t('kiosk.scan_failed', 'Badge not accepted')

  return result.value.action === 'in' ? t('kiosk.clocked_in') : t('kiosk.clocked_out')
})

const resultMeta = computed(() => {
  if (!result.value?.success) return ''

  return [
    result.value.employeeNumber ? `#${result.value.employeeNumber}` : null,
    result.value.department,
  ].filter(Boolean).join(' · ')
})

// ── Clock & Date ──────────────────────────────────────────────────────────
const updateClock = () => {
  const now = new Date()

  clock.value = now.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
  dateLabel.value = now.toLocaleDateString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

const updateOnlineState = () => {
  isOnline.value = typeof navigator === 'undefined' ? true : navigator.onLine
}

// ── Sound Feedback ────────────────────────────────────────────────────────
const playTone = success => {
  if (!soundOn.value || typeof window === 'undefined' || !window.AudioContext) return
  try {
    const ctx = new window.AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()

    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.value = success ? 880 : 220
    gain.gain.setValueAtTime(0.0001, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.16, ctx.currentTime + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.32)
    osc.start()
    osc.stop(ctx.currentTime + 0.33)
    osc.onended = () => ctx.close()
  } catch {
    // Audio is optional
  }
}

// ── Feed/Timeline ─────────────────────────────────────────────────────────
const loadFeed = async () => {
  if (isFeedLoading.value) return
  isFeedLoading.value = true
  try {
    const res = await $api('/hr/attendances/timeline', {
      method: 'GET',
      params: { date: localDate() },
    })

    const events = (res.data || [])
      .flatMap(group => (group.events || []).map(evt => ({
        id: evt.id,
        employee: group.employee,
        type: evt.type,
        time: evt.time,
        source: evt.source,
      })))
      .sort((a, b) => String(b.time).localeCompare(String(a.time)))

    // Counters use every punch of the day, the feed only shows the latest ones.
    punchStats.value = {
      ins: events.filter(e => e.type === 'in').length,
      outs: events.filter(e => e.type === 'out').length,
    }
    feed.value = events.slice(0, FEED_SIZE)
  } catch (err) {
    console.error('Failed to load kiosk feed', err)
  } finally {
    isFeedLoading.value = false
  }
}

// ── PIN idle timeout ──────────────────────────────────────────────────────
const stopPinIdle = () => {
  if (pinIdleTimer) clearTimeout(pinIdleTimer)
  pinIdleTimer = null
}

const armPinIdle = () => {
  stopPinIdle()
  pinIdleTimer = setTimeout(() => {
    inputMode.value = 'camera'
    pinValue.value = ''
  }, PIN_IDLE_MS)
}

const switchMode = mode => {
  inputMode.value = mode
  pinValue.value = ''
  if (mode === 'pin') armPinIdle()
  else stopPinIdle()
}

// ── Punch Submission ──────────────────────────────────────────────────────
let resultRemainingMs = 0
let resultStartTime = 0

const pauseResultTimer = () => {
  if (resultTimer) {
    clearTimeout(resultTimer)
    resultTimer = null

    const elapsed = Date.now() - resultStartTime

    resultRemainingMs = Math.max(3000, (result.value?.duration || RESULT_SUCCESS_MS) - elapsed)
  }
}

const resumeResultTimer = () => {
  if (!resultTimer && result.value && !selfServiceOpen.value) {
    resultStartTime = Date.now()
    resultTimer = setTimeout(finishResult, resultRemainingMs || RESULT_SUCCESS_MS)
  }
}

const finishResult = () => {
  if (resultTimer) clearTimeout(resultTimer)
  resultTimer = null
  resultRemainingMs = 0

  // A wrong PIN stays on the keypad so it can be retyped; a successful one returns to the scanner.
  if (result.value?.success && inputMode.value === 'pin') inputMode.value = 'camera'

  pinValue.value = ''
  result.value = null
  terminalLocked.value = false
  if (inputMode.value === 'pin') armPinIdle()
}

const normalizeDepartment = dept => (typeof dept === 'string' ? dept : dept?.name || null)

const handleSubmit = async payload => {
  if (terminalLocked.value) return
  terminalLocked.value = true
  stopPinIdle()

  try {
    const res = await attendanceStore.scanBadge({
      badge: payload.badge,
      source: payload.source || 'qr',
      device_id: 'kiosk-terminal',
    })

    result.value = {
      success: true,
      action: res.action,
      employeeId: res.employee?.id,
      name: res.employee?.full_name,
      employeeNumber: res.employee?.employee_number,
      department: normalizeDepartment(res.employee?.department),
      time: res.event?.time?.substring(0, 5),
      message: res.message,
      duration: RESULT_SUCCESS_MS,
    }

    playTone(true)
    loadFeed()
  } catch (err) {
    result.value = {
      success: false,
      message: err?.data?.message || t('kiosk.badge_not_recognized'),
      duration: RESULT_ERROR_MS,
    }
    playTone(false)
  }

  if (resultTimer) clearTimeout(resultTimer)
  resultStartTime = Date.now()
  resultRemainingMs = result.value.duration
  resultTimer = setTimeout(finishResult, result.value.duration)
}

// ── PIN Pad ───────────────────────────────────────────────────────────────
const handlePinDigit = digit => {
  if (terminalLocked.value || pinValue.value.length >= PIN_MAX) return
  pinValue.value += String(digit)
  armPinIdle()
}

const handlePinBackspace = () => {
  pinValue.value = pinValue.value.slice(0, -1)
  armPinIdle()
}

const handlePinClear = () => {
  pinValue.value = ''
  armPinIdle()
}

const handlePinSubmit = () => {
  if (pinValue.value.length >= PIN_MIN) {
    handleSubmit({ badge: pinValue.value, source: 'pin' })
  }
}

// Physical keyboard support for terminals that have one.
const handleKeydown = e => {
  if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return

  if (result.value) {
    if (e.key === 'Enter' || e.key === 'Escape') {
      finishResult()
      e.preventDefault()
    }

    return
  }

  if (inputMode.value !== 'pin' || qrScannerOpen.value) return

  if (/^\d$/.test(e.key)) handlePinDigit(e.key)
  else if (e.key === 'Backspace') handlePinBackspace()
  else if (e.key === 'Enter') handlePinSubmit()
  else if (e.key === 'Escape') switchMode('camera')
  else return

  e.preventDefault()
}

// ── QR Scanner ────────────────────────────────────────────────────────────
const handleQrScan = scanResult => {
  qrScannerOpen.value = false

  const badgeCode = typeof scanResult === 'string'
    ? scanResult.trim()
    : String(scanResult?.value || scanResult?.rawValue || scanResult?.text || '').trim()

  if (badgeCode) {
    handleSubmit({ badge: badgeCode, source: 'qr' })
  }
}

// ── Fullscreen & wake lock ────────────────────────────────────────────────
const toggleFullscreen = async () => {
  if (typeof document === 'undefined') return
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen?.()
    } else {
      await document.documentElement?.requestFullscreen?.()
    }
  } catch {
    // Fullscreen can be blocked
  }
}

const updateFullscreenState = () => {
  isFullscreen.value = Boolean(document.fullscreenElement)
}

// Keep the terminal screen awake while the page is open.
const requestWakeLock = async () => {
  try {
    if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return
    wakeLock = await navigator.wakeLock.request('screen')
  } catch {
    // Wake lock is optional
  }
}

const handleVisibility = () => {
  if (document.visibilityState === 'visible') requestWakeLock()
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
const startSession = () => {
  updateClock()
  updateOnlineState()
  loadFeed()
  requestWakeLock()
  if (!clockTimer) clockTimer = setInterval(updateClock, 1000)
  if (!feedTimer) feedTimer = setInterval(loadFeed, 15000)
}

const stopSession = () => {
  if (clockTimer) clearInterval(clockTimer)
  if (feedTimer) clearInterval(feedTimer)
  if (resultTimer) clearTimeout(resultTimer)
  stopPinIdle()
  clockTimer = null
  feedTimer = null
  resultTimer = null
  result.value = null
  terminalLocked.value = false
  if (selfServiceTimer) clearTimeout(selfServiceTimer)
  selfServiceTimer = null
  wakeLock?.release?.().catch?.(() => {})
  wakeLock = null
}

// ── Employee Self-Service & Requests ──────────────────────────────────────
const selfServiceOpen = ref(false)
const selfServiceTab = ref('new-request')
const selfServiceLoading = ref(false)
const selfServiceEmployee = ref(null)
const selfServiceBalances = ref([])
const selfServiceSchedules = ref([])
const selfServiceRequests = ref([])
const selfServiceAttendances = ref([])
const selfServiceLeaveTypes = ref([])

// Form state for new leave request:
const newLeaveForm = ref({
  leave_type_id: null,
  start_date: localDate(),
  end_date: localDate(),
  duration_type: 'full_day',
  start_time: '08:00',
  end_time: '12:00',
  reason: '',
})

const newLeaveSubmitting = ref(false)
const newLeaveFeedback = ref(null)

// Form state for attendance regularization:
const correctionAttendanceId = ref(null)
const correctionActualIn = ref('08:00')
const correctionActualOut = ref('17:00')
const correctionReason = ref('')
const correctionSubmitting = ref(false)
const correctionFeedback = ref(null)

let selfServiceTimer = null

const armSelfServiceIdle = () => {
  if (selfServiceTimer) clearTimeout(selfServiceTimer)
  selfServiceTimer = setTimeout(() => {
    closeSelfService()
  }, 45000)
}

const openSelfService = async (employeeId, employeeName, initialTab = 'new-request') => {
  pauseResultTimer()
  selfServiceTab.value = initialTab
  selfServiceEmployee.value = { id: employeeId, name: employeeName }
  selfServiceOpen.value = true
  selfServiceLoading.value = true
  selfServiceBalances.value = []
  selfServiceSchedules.value = []
  selfServiceRequests.value = []
  selfServiceAttendances.value = []
  newLeaveFeedback.value = null
  correctionFeedback.value = null

  armSelfServiceIdle()

  try {
    const [balancesRes, schedulesRes, requestsRes, leaveTypesRes, attendancesRes] = await Promise.allSettled([
      $api(`/hr/leave-balances?employee_id=${employeeId}`),
      $api(`/hr/workforce/schedules?employee_id=${employeeId}&per_page=7`),
      $api(`/hr/leave-requests?employee_id=${employeeId}&per_page=10`),
      $api('/hr/leave-types'),
      $api(`/hr/attendances?employee_id=${employeeId}&per_page=7`),
    ])

    if (balancesRes.status === 'fulfilled') {
      const data = balancesRes.value?.data || balancesRes.value || []

      selfServiceBalances.value = Array.isArray(data) ? data : []
    }
    if (schedulesRes.status === 'fulfilled') {
      const data = schedulesRes.value?.data || schedulesRes.value || []

      selfServiceSchedules.value = Array.isArray(data) ? data : []
    }
    if (requestsRes.status === 'fulfilled') {
      const data = requestsRes.value?.data || requestsRes.value || []

      selfServiceRequests.value = Array.isArray(data) ? data : []
    }
    if (leaveTypesRes.status === 'fulfilled') {
      const data = leaveTypesRes.value?.data || leaveTypesRes.value || []
      const rawTypes = Array.isArray(data) ? data : []

      selfServiceLeaveTypes.value = rawTypes.map(lt => ({
        id: lt.id,
        label: localizedValue(lt.name, lt.code || 'Congé'),
      }))
      if (!newLeaveForm.value.leave_type_id && selfServiceLeaveTypes.value.length) {
        newLeaveForm.value.leave_type_id = selfServiceLeaveTypes.value[0].id
      }
    }
    if (attendancesRes.status === 'fulfilled') {
      const data = attendancesRes.value?.data || attendancesRes.value || []

      selfServiceAttendances.value = Array.isArray(data) ? data : []
    }
  } catch (err) {
    console.error('Failed to load self-service details', err)
  } finally {
    selfServiceLoading.value = false
  }
}

const submitLeaveRequest = async () => {
  if (!newLeaveForm.value.leave_type_id || !selfServiceEmployee.value?.id) return
  newLeaveSubmitting.value = true
  newLeaveFeedback.value = null
  armSelfServiceIdle()

  try {
    const payload = {
      employee_id: selfServiceEmployee.value.id,
      leave_type_id: newLeaveForm.value.leave_type_id,
      start_date: newLeaveForm.value.start_date,
      end_date: newLeaveForm.value.end_date,
      duration_type: newLeaveForm.value.duration_type,
      reason: newLeaveForm.value.reason || '',
      submit: true,
    }

    if (newLeaveForm.value.duration_type === 'hourly') {
      payload.start_time = newLeaveForm.value.start_time
      payload.end_time = newLeaveForm.value.end_time
    }

    const res = await $api('/hr/leave-requests', {
      method: 'POST',
      body: payload,
    })

    newLeaveFeedback.value = {
      type: 'success',
      text: res.message || 'Demande de congé transmise avec succès au responsable.',
    }

    // Refresh requests & balances
    const [reqs, bals] = await Promise.allSettled([
      $api(`/hr/leave-requests?employee_id=${selfServiceEmployee.value.id}&per_page=10`),
      $api(`/hr/leave-balances?employee_id=${selfServiceEmployee.value.id}`),
    ])

    if (reqs.status === 'fulfilled') selfServiceRequests.value = reqs.value?.data || []
    if (bals.status === 'fulfilled') selfServiceBalances.value = bals.value?.data || []

    newLeaveForm.value.reason = ''
  } catch (err) {
    newLeaveFeedback.value = {
      type: 'error',
      text: err?.data?.message || err?.message || 'Erreur lors de la soumission de la demande.',
    }
  } finally {
    newLeaveSubmitting.value = false
  }
}

const submitAttendanceCorrection = async attendanceId => {
  if (!attendanceId) return
  correctionSubmitting.value = true
  correctionFeedback.value = null
  armSelfServiceIdle()

  try {
    const res = await $api(`/hr/attendances/${attendanceId}/request-correction`, {
      method: 'POST',
      body: {
        requested_actual_in: correctionActualIn.value,
        requested_actual_out: correctionActualOut.value,
        reason: correctionReason.value,
      },
    })

    correctionFeedback.value = {
      type: 'success',
      text: res.message || 'Demande de régularisation enregistrée avec succès.',
    }
    correctionAttendanceId.value = null
    correctionReason.value = ''

    const attRes = await $api(`/hr/attendances?employee_id=${selfServiceEmployee.value.id}&per_page=7`)

    selfServiceAttendances.value = attRes?.data || []
  } catch (err) {
    correctionFeedback.value = {
      type: 'error',
      text: err?.data?.message || err?.message || 'Erreur lors de la demande de régularisation.',
    }
  } finally {
    correctionSubmitting.value = false
  }
}

const closeSelfService = () => {
  if (selfServiceTimer) {
    clearTimeout(selfServiceTimer)
    selfServiceTimer = null
  }
  selfServiceOpen.value = false
  finishResult()
}

onMounted(() => {
  startSession()
  window.addEventListener('online', updateOnlineState)
  window.addEventListener('offline', updateOnlineState)
  window.addEventListener('keydown', handleKeydown)
  document.addEventListener('fullscreenchange', updateFullscreenState)
  document.addEventListener('visibilitychange', handleVisibility)
})

onBeforeUnmount(() => {
  stopSession()
  window.removeEventListener('online', updateOnlineState)
  window.removeEventListener('offline', updateOnlineState)
  window.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('fullscreenchange', updateFullscreenState)
  document.removeEventListener('visibilitychange', handleVisibility)
  if (typeof document !== 'undefined' && document.fullscreenElement) {
    document.exitFullscreen?.().catch?.(() => {})
  }
})
</script>

<template>
  <div class="kiosk-terminal bg-surface">
    <!-- Header (fixed: the terminal never scrolls) -->
    <VSheet
      color="surface"
      elevation="1"
      class="kiosk-header"
    >
      <div class="kiosk-brand">
        <VAvatar
          size="44"
          rounded="lg"
          color="primary"
          variant="tonal"
          class="kiosk-brand-avatar"
        >
          <VIcon
            icon="tabler-fingerprint"
            size="26"
          />
        </VAvatar>
        <div class="kiosk-brand-text">
          <div class="kiosk-brand-title text-truncate">
            {{ $t('kiosk.title') }}
          </div>
          <div class="kiosk-brand-subtitle text-truncate">
            {{ $t('kiosk.subtitle') }}
          </div>
        </div>
      </div>

      <div class="kiosk-actions">
        <div
          class="kiosk-status"
          :class="{ 'kiosk-status--offline': !isOnline }"
        >
          <span class="kiosk-status-dot" />
          <span class="kiosk-status-label">{{ statusText }}</span>
        </div>

        <VBtn
          :icon="soundOn ? 'tabler-volume' : 'tabler-volume-off'"
          :aria-label="soundOn ? $t('kiosk.sound_off', 'Mute sounds') : $t('kiosk.sound_on', 'Unmute sounds')"
          variant="text"
          size="large"
          class="kiosk-action-btn"
          @click="soundOn = !soundOn"
        />
        <VBtn
          :icon="isFullscreen ? 'tabler-minimize' : 'tabler-maximize'"
          :aria-label="$t('kiosk.fullscreen', 'Toggle fullscreen')"
          variant="text"
          size="large"
          class="kiosk-action-btn"
          @click="toggleFullscreen"
        />
        <VBtn
          icon="tabler-x"
          :aria-label="$t('kiosk.exit', 'Exit terminal')"
          variant="tonal"
          size="large"
          class="kiosk-action-btn"
          to="/people-skills/attendance/list"
        />
      </div>
    </VSheet>

    <!-- Main Body -->
    <main class="kiosk-body">
      <!-- Stage: clock + input / result -->
      <section
        class="kiosk-stage"
        aria-live="polite"
      >
        <div class="kiosk-clock">
          <div class="kiosk-date">
            {{ dateLabel }}
          </div>
          <div class="kiosk-time">
            {{ clock }}
          </div>
        </div>

        <!-- Result Feedback -->
        <div
          v-if="result"
          class="kiosk-result"
          :class="result.success ? 'kiosk-result--success' : 'kiosk-result--error'"
          :style="{ '--dismiss-ms': `${result.duration}ms` }"
          role="status"
          @mouseenter="pauseResultTimer"
          @mouseleave="resumeResultTimer"
        >
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            class="kiosk-result-close"
            :aria-label="$t('kiosk.dismiss', 'Dismiss')"
            @click="finishResult"
          />

          <VIcon
            :icon="result.success ? 'tabler-circle-check' : 'tabler-alert-circle'"
            size="80"
            class="kiosk-result-icon"
          />
          <div class="kiosk-result-action">
            {{ resultTitle }}
          </div>
          <div
            v-if="result.success && result.name"
            class="kiosk-result-name"
          >
            {{ result.name }}
          </div>
          <div
            v-if="resultMeta"
            class="kiosk-result-meta"
          >
            {{ resultMeta }}
          </div>
          <div
            v-if="result.success && result.time"
            class="kiosk-result-time"
          >
            {{ result.time }}
          </div>
          <div
            v-if="!result.success"
            class="kiosk-result-message"
          >
            {{ result.message }}
          </div>

          <!-- Employee Requests & Self-Service Section -->
          <div
            v-if="result.success && result.employeeId"
            class="kiosk-services-panel w-100 mt-2 px-2"
          >
            <div class="text-caption font-weight-medium text-center mb-2 kiosk-services-title">
              <VIcon icon="tabler-sparkles" size="14" class="me-1 text-primary" />
              {{ $t('kiosk.services_title', 'Espace & Demandes de l’employé') }}
            </div>
            <div class="d-flex flex-wrap align-center justify-center gap-2">
              <VBtn
                size="small"
                variant="flat"
                color="primary"
                prepend-icon="tabler-file-plus"
                class="font-weight-bold"
                @click="openSelfService(result.employeeId, result.name, 'new-request')"
              >
                {{ $t('kiosk.make_request', 'Faire une demande') }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-files"
                @click="openSelfService(result.employeeId, result.name, 'my-requests')"
              >
                {{ $t('kiosk.my_requests', 'Mes demandes') }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-wallet"
                @click="openSelfService(result.employeeId, result.name, 'balance')"
              >
                {{ $t('kiosk.check_balance', 'Soldes congés') }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-calendar"
                @click="openSelfService(result.employeeId, result.name, 'schedule')"
              >
                {{ $t('kiosk.check_schedule', 'Mon planning') }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="warning"
                prepend-icon="tabler-clock-edit"
                @click="openSelfService(result.employeeId, result.name, 'corrections')"
              >
                {{ $t('kiosk.regularization', 'Régularisation') }}
              </VBtn>
            </div>
          </div>

          <div class="d-flex align-center justify-center gap-2 mt-3">
            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-check"
              @click="finishResult"
            >
              {{ $t('kiosk.finish_button', 'Terminer / Prêt pour le suivant') }}
            </VBtn>
          </div>

          <span class="kiosk-result-countdown" />
        </div>

        <!-- Input Mode: Camera -->
        <div
          v-else-if="inputMode === 'camera'"
          class="kiosk-prompt"
        >
          <h1 class="kiosk-title">
            {{ $t('kiosk.scan_badge') }}
          </h1>
          <p class="kiosk-help">
            {{ $t('kiosk.scan_help') }}
          </p>

          <div
            class="kiosk-qr-frame"
            aria-hidden="true"
            @click="!isBusy && (qrScannerOpen = true)"
          >
            <div class="kiosk-qr-corners">
              <div class="corner corner-tl" />
              <div class="corner corner-tr" />
              <div class="corner corner-bl" />
              <div class="corner corner-br" />
            </div>
            <VIcon
              icon="tabler-qrcode"
              size="96"
              class="kiosk-qr-icon"
            />
          </div>

          <VBtn
            size="x-large"
            color="primary"
            prepend-icon="tabler-scan"
            :disabled="isBusy"
            class="kiosk-primary-action"
            @click="qrScannerOpen = true"
          >
            {{ $t('kiosk.start_scan') }}
          </VBtn>

          <VBtn
            variant="text"
            size="large"
            prepend-icon="tabler-dialpad"
            :disabled="terminalLocked"
            @click="switchMode('pin')"
          >
            {{ $t('kiosk.use_pin') }}
          </VBtn>

          <p
            v-if="!isOnline"
            class="kiosk-offline-note"
          >
            {{ $t('kiosk.offline_hint', 'Connection lost. Punches cannot be recorded until it is back.') }}
          </p>
        </div>

        <!-- Input Mode: PIN -->
        <div
          v-else
          class="kiosk-prompt"
        >
          <h1 class="kiosk-title">
            {{ $t('kiosk.enter_pin') }}
          </h1>
          <p class="kiosk-help">
            {{ $t('kiosk.pin_help') }}
          </p>

          <div
            class="kiosk-pin-display"
            role="status"
          >
            <span
              v-for="(digit, index) in pinSlots"
              :key="index"
              class="kiosk-pin-slot"
              :class="{
                'kiosk-pin-slot--filled': digit !== null,
                'kiosk-pin-slot--active': index === pinValue.length,
              }"
            >{{ digit }}</span>
          </div>

          <div class="kiosk-pin-pad">
            <VBtn
              v-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
              :key="digit"
              size="x-large"
              variant="tonal"
              class="kiosk-key"
              :disabled="terminalLocked"
              @click="handlePinDigit(digit)"
            >
              {{ digit }}
            </VBtn>
            <VBtn
              size="x-large"
              variant="text"
              color="secondary"
              class="kiosk-key"
              :aria-label="$t('kiosk.backspace', 'Delete last digit')"
              :disabled="terminalLocked || !pinValue"
              @click="handlePinBackspace"
            >
              <VIcon icon="tabler-backspace" />
            </VBtn>
            <VBtn
              size="x-large"
              variant="tonal"
              class="kiosk-key"
              :disabled="terminalLocked"
              @click="handlePinDigit(0)"
            >
              0
            </VBtn>
            <VBtn
              size="x-large"
              variant="flat"
              color="success"
              class="kiosk-key"
              :aria-label="$t('kiosk.confirm', 'Confirm')"
              :disabled="pinValue.length < PIN_MIN || terminalLocked"
              @click="handlePinSubmit"
            >
              <VIcon icon="tabler-check" />
            </VBtn>
          </div>

          <div class="kiosk-pin-links">
            <VBtn
              variant="text"
              size="large"
              color="error"
              :disabled="!pinValue"
              @click="handlePinClear"
            >
              {{ $t('Clear') }}
            </VBtn>
            <VBtn
              variant="text"
              size="large"
              prepend-icon="tabler-scan"
              @click="switchMode('camera')"
            >
              {{ $t('kiosk.back_to_scanner') }}
            </VBtn>
          </div>
        </div>
      </section>

      <!-- Sidebar: today's counters + recent activity (bottom sheet on mobile) -->
      <aside class="kiosk-side">
        <div class="kiosk-counts">
          <div class="kiosk-count">
            <VAvatar
              size="40"
              color="success"
              variant="tonal"
              class="kiosk-count-avatar"
            >
              <VIcon icon="tabler-door-enter" />
            </VAvatar>
            <div>
              <div class="kiosk-count-value">
                {{ punchStats.ins }}
              </div>
              <div class="kiosk-count-label">
                {{ $t('kiosk.check_ins') }}
              </div>
            </div>
          </div>
          <div class="kiosk-count">
            <VAvatar
              size="40"
              color="warning"
              variant="tonal"
              class="kiosk-count-avatar"
            >
              <VIcon icon="tabler-door-exit" />
            </VAvatar>
            <div>
              <div class="kiosk-count-value">
                {{ punchStats.outs }}
              </div>
              <div class="kiosk-count-label">
                {{ $t('kiosk.check_outs') }}
              </div>
            </div>
          </div>
        </div>

        <VCard
          class="kiosk-feed-card"
          variant="flat"
          border
        >
          <div class="kiosk-feed-header">
            <VIcon
              icon="tabler-history"
              size="20"
            />
            <span>{{ $t('kiosk.recent_activity') }}</span>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="kiosk-feed-count"
            >
              {{ feed.length }}
            </VChip>
            <VBtn
              class="d-lg-none ms-auto"
              :icon="activityOpen ? 'tabler-chevron-down' : 'tabler-chevron-up'"
              :aria-label="activityOpen ? $t('kiosk.collapse_activity', 'Hide recent activity') : $t('kiosk.expand_activity', 'Show recent activity')"
              variant="text"
              size="small"
              density="comfortable"
              @click="activityOpen = !activityOpen"
            />
          </div>
          <VProgressLinear
            v-if="isFeedLoading && !feed.length"
            indeterminate
            color="primary"
          />
          <div
            v-show="activityOpen || lgAndUp"
            class="kiosk-feed-body"
          >
            <AttendanceKioskFeed
              :entries="feed"
              kiosk
            />
          </div>
        </VCard>
      </aside>
    </main>

    <!-- QR Scanner Dialog -->
    <WpQrScannerDialog
      v-model="qrScannerOpen"
      @scanned="handleQrScan"
    />

    <!-- Employee Quick Self-Service & Requests Dialog -->
    <VDialog
      v-model="selfServiceOpen"
      max-width="640"
      persistent
      @mousemove="armSelfServiceIdle"
      @keydown="armSelfServiceIdle"
      @touchstart="armSelfServiceIdle"
    >
      <VCard class="pa-4 kiosk-dialog-card">
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
            >
              <VIcon icon="tabler-user-check" size="24" />
            </VAvatar>
            <div>
              <div class="font-weight-bold text-subtitle-1">
                {{ selfServiceEmployee?.name || 'Espace Employé' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('kiosk.self_service_subtitle', 'Espace libre-service & demandes RH') }}
              </div>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="closeSelfService"
          />
        </div>

        <VTabs
          v-model="selfServiceTab"
          grow
          density="compact"
          class="mb-3"
        >
          <VTab value="new-request">
            <VIcon icon="tabler-file-plus" class="me-1" size="18" />
            {{ $t('kiosk.tab_new_request', 'Demande') }}
          </VTab>
          <VTab value="my-requests">
            <VIcon icon="tabler-files" class="me-1" size="18" />
            {{ $t('kiosk.tab_requests', 'Historique') }}
          </VTab>
          <VTab value="balance">
            <VIcon icon="tabler-wallet" class="me-1" size="18" />
            {{ $t('time_off.tabs.balances', 'Soldes') }}
          </VTab>
          <VTab value="schedule">
            <VIcon icon="tabler-calendar" class="me-1" size="18" />
            {{ $t('workforce.col.schedule', 'Planning') }}
          </VTab>
          <VTab value="corrections">
            <VIcon icon="tabler-clock-edit" class="me-1" size="18" />
            {{ $t('kiosk.tab_corrections', 'Pointages') }}
          </VTab>
        </VTabs>

        <VWindow v-model="selfServiceTab">
          <!-- ── TAB 1: Nouvelle Demande de Congé ──────────────── -->
          <VWindowItem value="new-request">
            <div class="pa-1">
              <VAlert
                v-if="newLeaveFeedback"
                :type="newLeaveFeedback.type"
                variant="tonal"
                density="compact"
                class="mb-3"
                closable
                @click:close="newLeaveFeedback = null"
              >
                {{ newLeaveFeedback.text }}
              </VAlert>

              <VForm @submit.prevent="submitLeaveRequest">
                <VRow dense>
                  <VCol cols="12">
                    <VSelect
                      v-model="newLeaveForm.leave_type_id"
                      :items="selfServiceLeaveTypes"
                      item-title="label"
                      item-value="id"
                      :label="$t('time_off.fields.leave_type', 'Type de Congé / Absence')"
                      density="compact"
                      variant="outlined"
                      required
                    />
                  </VCol>
                  <VCol cols="12" sm="6">
                    <VTextField
                      v-model="newLeaveForm.start_date"
                      type="date"
                      :label="$t('time_off.fields.start_date', 'Date début')"
                      density="compact"
                      variant="outlined"
                      required
                    />
                  </VCol>
                  <VCol cols="12" sm="6">
                    <VTextField
                      v-model="newLeaveForm.end_date"
                      type="date"
                      :label="$t('time_off.fields.end_date', 'Date fin')"
                      density="compact"
                      variant="outlined"
                      required
                    />
                  </VCol>
                  <VCol cols="12" sm="6">
                    <VSelect
                      v-model="newLeaveForm.duration_type"
                      :items="[
                        { title: 'Journée(s) entière(s)', value: 'full_day' },
                        { title: 'Demi-journée', value: 'partial_day' },
                        { title: 'Autorisation d\'heures (Sortie)', value: 'hourly' },
                      ]"
                      :label="$t('time_off.fields.duration_type', 'Type de durée')"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol v-if="newLeaveForm.duration_type === 'hourly'" cols="6" sm="3">
                    <VTextField
                      v-model="newLeaveForm.start_time"
                      type="time"
                      :label="$t('time_off.fields.start_time', 'De')"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol v-if="newLeaveForm.duration_type === 'hourly'" cols="6" sm="3">
                    <VTextField
                      v-model="newLeaveForm.end_time"
                      type="time"
                      :label="$t('time_off.fields.end_time', 'À')"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VTextarea
                      v-model="newLeaveForm.reason"
                      rows="2"
                      :label="$t('time_off.fields.reason', 'Motif / Justification')"
                      placeholder="Ex: Repos annuel, urgence familiale..."
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VBtn
                      block
                      color="primary"
                      prepend-icon="tabler-send"
                      :loading="newLeaveSubmitting"
                      :disabled="!newLeaveForm.leave_type_id || !newLeaveForm.start_date || !newLeaveForm.end_date"
                      type="submit"
                    >
                      {{ $t('kiosk.submit_request', 'Transmettre la demande au responsable') }}
                    </VBtn>
                  </VCol>
                </VRow>
              </VForm>
            </div>
          </VWindowItem>

          <!-- ── TAB 2: Mes Demandes (Historique) ──────────────── -->
          <VWindowItem value="my-requests">
            <div v-if="selfServiceLoading" class="py-6 text-center">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <div v-else-if="!selfServiceRequests.length" class="py-6 text-center text-medium-emphasis">
              <VIcon icon="tabler-inbox" size="36" class="mb-2 opacity-50" /><br>
              {{ $t('kiosk.no_requests', 'Aucune demande enregistrée.') }}
            </div>
            <VList v-else density="compact" lines="two">
              <VListItem
                v-for="r in selfServiceRequests"
                :key="r.id"
                class="px-2 mb-1 rounded border"
              >
                <template #prepend>
                  <VAvatar
                    size="32"
                    :color="r.status === 'approved' ? 'success' : r.status === 'rejected' ? 'error' : 'warning'"
                    variant="tonal"
                  >
                    <VIcon
                      :icon="r.status === 'approved' ? 'tabler-check' : r.status === 'rejected' ? 'tabler-x' : 'tabler-clock'"
                      size="18"
                    />
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-medium">
                  {{ localizedValue(r.leave_type?.name || r.leaveType?.name, 'Congé') }}
                  <span class="text-caption text-medium-emphasis ms-2">({{ r.total_days }}j)</span>
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ r.start_date }} → {{ r.end_date }}
                  <span v-if="r.reason" class="d-block text-truncate">« {{ r.reason }} »</span>
                </VListItemSubtitle>
                <template #append>
                  <VChip
                    size="small"
                    :color="r.status === 'approved' ? 'success' : r.status === 'rejected' ? 'error' : 'warning'"
                    variant="tonal"
                  >
                    {{ r.status }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VWindowItem>

          <!-- ── TAB 3: Soldes de Congés ───────────────────────── -->
          <VWindowItem value="balance">
            <div v-if="selfServiceLoading" class="py-6 text-center">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <div v-else-if="!selfServiceBalances.length" class="py-6 text-center text-medium-emphasis">
              {{ $t('kiosk.no_balances', 'Aucun solde de congé enregistré.') }}
            </div>
            <div v-else class="d-flex flex-column gap-2">
              <VCard
                v-for="b in selfServiceBalances"
                :key="b.id"
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between mb-1">
                  <div class="font-weight-medium">
                    {{ localizedValue(b.leave_type?.name || b.leaveType?.name || b.name, 'Congé') }}
                  </div>
                  <VChip
                    size="small"
                    color="success"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    {{ b.available_days ?? b.remaining_days ?? (b.accrued_days - b.approved_used_days) ?? 0 }} j restants
                  </VChip>
                </div>
                <VProgressLinear
                  :model-value="((b.approved_used_days ?? b.used_days ?? 0) / Math.max(1, (b.accrued_days ?? b.allocated_days ?? 21))) * 100"
                  color="primary"
                  height="6"
                  rounded
                  class="my-1"
                />
                <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                  <span>Acquis: {{ b.accrued_days ?? b.allocated_days ?? 21 }}j</span>
                  <span>Consommés: {{ b.approved_used_days ?? b.used_days ?? 0 }}j</span>
                  <span v-if="b.pending_reserved_days">Réservés: {{ b.pending_reserved_days }}j</span>
                </div>
              </VCard>
            </div>
          </VWindowItem>

          <!-- ── TAB 4: Mon Planning ───────────────────────────── -->
          <VWindowItem value="schedule">
            <div v-if="selfServiceLoading" class="py-6 text-center">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <div v-else-if="!selfServiceSchedules.length" class="py-6 text-center text-medium-emphasis">
              {{ $t('kiosk.no_schedules', 'Aucun horaire planifié.') }}
            </div>
            <VList v-else density="compact">
              <VListItem
                v-for="s in selfServiceSchedules"
                :key="s.id"
                class="px-2 mb-1 rounded border"
              >
                <template #prepend>
                  <VAvatar size="32" color="primary" variant="tonal">
                    <VIcon icon="tabler-clock" size="18" />
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-medium">
                  {{ s.date }} · {{ localizedValue(s.shift?.name || s.shift_name, 'Horaire') }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ s.shift?.start_time?.slice(0, 5) || s.start_time?.slice(0, 5) || '08:00' }} – {{ s.shift?.end_time?.slice(0, 5) || s.end_time?.slice(0, 5) || '17:00' }}
                  <span v-if="s.notes" class="ms-1 text-caption">({{ s.notes }})</span>
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VWindowItem>

          <!-- ── TAB 5: Pointages & Régularisations ─────────────── -->
          <VWindowItem value="corrections">
            <div class="pa-1">
              <VAlert
                v-if="correctionFeedback"
                :type="correctionFeedback.type"
                variant="tonal"
                density="compact"
                class="mb-3"
                closable
                @click:close="correctionFeedback = null"
              >
                {{ correctionFeedback.text }}
              </VAlert>

              <div class="text-caption text-medium-emphasis mb-2">
                Sélectionnez un pointage récent à régulariser (oubli, mission extérieure, retard justifié) :
              </div>

              <div v-if="selfServiceLoading" class="py-4 text-center">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else-if="!selfServiceAttendances.length" class="py-4 text-center text-medium-emphasis">
                Aucun pointage récent trouvé.
              </div>
              <div v-else class="d-flex flex-column gap-2 mb-3">
                <VCard
                  v-for="att in selfServiceAttendances"
                  :key="att.id"
                  variant="outlined"
                  class="pa-2"
                  :color="correctionAttendanceId === att.id ? 'primary' : undefined"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div>
                      <div class="font-weight-medium">{{ att.date }}</div>
                      <div class="text-caption text-medium-emphasis">
                        Planifié: {{ att.scheduled_in?.slice(0,5) || '08:00' }}-{{ att.scheduled_out?.slice(0,5) || '17:00' }} · Réel: {{ att.actual_in?.slice(0,5) || '—' }}-{{ att.actual_out?.slice(0,5) || '—' }}
                      </div>
                    </div>
                    <VBtn
                      size="x-small"
                      :variant="correctionAttendanceId === att.id ? 'flat' : 'tonal'"
                      color="warning"
                      @click="correctionAttendanceId = (correctionAttendanceId === att.id ? null : att.id)"
                    >
                      {{ correctionAttendanceId === att.id ? 'Annuler' : 'Régulariser' }}
                    </VBtn>
                  </div>

                  <!-- Inline correction form -->
                  <div v-if="correctionAttendanceId === att.id" class="mt-2 pt-2 border-t">
                    <VRow dense>
                      <VCol cols="6">
                        <VTextField
                          v-model="correctionActualIn"
                          type="time"
                          label="Entrée réelle"
                          density="compact"
                          variant="outlined"
                        />
                      </VCol>
                      <VCol cols="6">
                        <VTextField
                          v-model="correctionActualOut"
                          type="time"
                          label="Sortie réelle"
                          density="compact"
                          variant="outlined"
                        />
                      </VCol>
                      <VCol cols="12">
                        <VTextField
                          v-model="correctionReason"
                          label="Motif de la régularisation"
                          placeholder="Ex: Oubli de badge, intervention externe"
                          density="compact"
                          variant="outlined"
                        />
                      </VCol>
                      <VCol cols="12">
                        <VBtn
                          size="small"
                          block
                          color="warning"
                          :loading="correctionSubmitting"
                          @click="submitAttendanceCorrection(att.id)"
                        >
                          Envoyer la demande de régularisation
                        </VBtn>
                      </VCol>
                    </VRow>
                  </div>
                </VCard>
              </div>
            </div>
          </VWindowItem>
        </VWindow>

        <VCardActions class="mt-3 pa-0 justify-end">
          <VBtn
            color="primary"
            variant="tonal"
            block
            @click="closeSelfService"
          >
            {{ $t('kiosk.done', 'Fermer / Retour à la borne') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.kiosk-terminal {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  block-size: 100vh;
  block-size: 100dvh;
}

/* Header: fixed at the top, never scrolls away */
.kiosk-header {
  display: flex;
  flex: none;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.kiosk-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-inline-size: 0;
}

.kiosk-brand-avatar {
  flex-shrink: 0;
}

.kiosk-brand-text {
  min-inline-size: 0;
}

.kiosk-brand-title {
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.3;
}

.kiosk-brand-subtitle {
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.8125rem;
}

.kiosk-actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.kiosk-action-btn {
  flex-shrink: 0;
}

.kiosk-status {
  display: flex;
  align-items: center;
  border-radius: 9999px;
  background: rgba(var(--v-theme-success), 0.12);
  color: rgb(var(--v-theme-success));
  font-size: 0.875rem;
  font-weight: 600;
  gap: 0.5rem;
  margin-inline-end: 0.5rem;
  padding-block: 0.375rem;
  padding-inline: 0.875rem;
}

.kiosk-status-dot {
  flex-shrink: 0;
  border-radius: 50%;
  animation: pulse-dot 2s ease-in-out infinite;
  background: currentcolor;
  block-size: 8px;
  inline-size: 8px;
}

.kiosk-status--offline {
  background: rgba(var(--v-theme-error), 0.12);
  color: rgb(var(--v-theme-error));
}

.kiosk-status--offline .kiosk-status-dot {
  animation: none;
}

@keyframes pulse-dot {
  0%,
  100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* Body (mobile first: single column, locked to the viewport) */
.kiosk-body {
  display: flex;
  overflow: hidden;
  flex: 1;
  flex-direction: column;
  padding: 1rem;
  gap: 1rem;
  min-block-size: 0;
}

/* Stage: fills the free space, never overflows */
.kiosk-stage {
  display: flex;
  overflow: hidden;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: clamp(0.75rem, 2.5vh, 2rem);
  min-block-size: 0;
  min-inline-size: 0;
  text-align: center;
}

.kiosk-date {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 1rem;
}

.kiosk-time {
  font-size: clamp(2.25rem, 9vw, 5.5rem);
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1;
  margin-block-start: 0.25rem;
}

.kiosk-prompt {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  inline-size: 100%;
  max-block-size: 100%;
  max-inline-size: 560px;
  min-block-size: 0;
}

.kiosk-title {
  font-size: clamp(1.5rem, 4vw, 2.5rem);
  font-weight: 700;
  line-height: 1.15;
}

.kiosk-help {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 1.0625rem;
  margin-block-end: 0.5rem;
}

.kiosk-primary-action {
  min-inline-size: min(100%, 320px);
}

.kiosk-offline-note {
  margin: 0;
  color: rgb(var(--v-theme-error));
  font-size: 0.9375rem;
}

/* QR frame */
.kiosk-qr-frame {
  position: relative;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 1rem;
  background: rgba(var(--v-theme-primary), 0.06);
  block-size: clamp(140px, 22vh, 250px);
  cursor: pointer;
  inline-size: clamp(140px, 22vh, 250px);
  margin-block-end: 0.5rem;
}

.kiosk-qr-corners {
  position: absolute;
  inset: 0;
}

.corner {
  position: absolute;
  border: 4px solid rgb(var(--v-theme-primary));
  border-radius: 6px;
  block-size: 44px;
  inline-size: 44px;
}

.corner-tl { border-block-end: none; border-inline-end: none; inset-block-start: 0; inset-inline-start: 0; }
.corner-tr { border-block-end: none; border-inline-start: none; inset-block-start: 0; inset-inline-end: 0; }
.corner-bl { border-block-start: none; border-inline-end: none; inset-block-end: 0; inset-inline-start: 0; }
.corner-br { border-block-start: none; border-inline-start: none; inset-block-end: 0; inset-inline-end: 0; }

.kiosk-qr-icon {
  animation: pulse-icon 2.4s ease-in-out infinite;
  color: rgb(var(--v-theme-primary));
  opacity: 0.45;
}

@keyframes pulse-icon {
  0%,
  100% { opacity: 0.35; transform: scale(1); }
  50% { opacity: 0.6; transform: scale(1.05); }
}

/* PIN: sized so even 8 slots fit a 320px screen without horizontal scroll */
.kiosk-pin-display {
  display: flex;
  justify-content: center;
  gap: clamp(0.25rem, 1.5vw, 0.5rem);
  margin-block-end: 0.5rem;
  max-inline-size: 100%;
  padding-inline: 0.25rem;
}

.kiosk-pin-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  block-size: clamp(2.75rem, 12vh, 4.25rem);
  font-size: clamp(1.25rem, 6vw, 2rem);
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  inline-size: clamp(1.75rem, 9.5vw, 3.25rem);
}

.kiosk-pin-slot--filled {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(var(--v-theme-primary), 0.08);
}

.kiosk-pin-slot--active {
  border-color: rgb(var(--v-theme-primary));
}

.kiosk-pin-pad {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  inline-size: 100%;
  max-inline-size: 360px;
}

.kiosk-key {
  block-size: clamp(52px, 9vh, 76px);
  font-size: 1.75rem;
  font-weight: 600;
  min-inline-size: 0;
  padding-inline: 0;
}

.kiosk-pin-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
}

/* Result */
.kiosk-result {
  position: relative;
  display: flex;
  overflow: hidden;
  flex-direction: column;
  flex-shrink: 0;
  align-items: center;
  border-radius: 1.25rem;
  animation: result-appear 0.25s ease-out;
  cursor: default;
  gap: 0.5rem;
  inline-size: 100%;
  max-block-size: 100%;
  max-inline-size: 640px;
  padding-block: clamp(1rem, 3vh, 2.25rem) clamp(1.25rem, 3vh, 2.5rem);
  padding-inline: 1.25rem;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.15);
}

.kiosk-result--success {
  background: rgba(var(--v-theme-success), 0.08);
  border: 1px solid rgba(var(--v-theme-success), 0.28);
  color: rgb(var(--v-theme-success));
}

.kiosk-result--error {
  background: rgba(var(--v-theme-error), 0.08);
  border: 1px solid rgba(var(--v-theme-error), 0.28);
  color: rgb(var(--v-theme-error));
}

.kiosk-result-close {
  position: absolute;
  inset-block-start: 0.75rem;
  inset-inline-end: 0.75rem;
  opacity: 0.7;
}

.kiosk-result-close:hover {
  opacity: 1;
}

@keyframes result-appear {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

.kiosk-result-icon {
  flex-shrink: 0;
}

.kiosk-result-action {
  font-size: clamp(1.5rem, 4.5vw, 2.25rem);
  font-weight: 700;
  line-height: 1.15;
}

.kiosk-result-name {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.25rem, 3.5vw, 2rem);
  font-weight: 600;
  overflow-wrap: anywhere;
}

.kiosk-result-meta {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 1rem;
}

.kiosk-result-time {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(2rem, 6vw, 3.25rem);
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  line-height: 1.1;
}

.kiosk-result-message {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}

.kiosk-services-panel {
  background: rgba(var(--v-theme-surface), 0.88);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.875rem;
  padding: 0.75rem 0.5rem;
  backdrop-filter: blur(8px);
}

.kiosk-services-title {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: 0.02em;
}

.kiosk-result-dismiss {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.8125rem;
  margin-block-start: 0.5rem;
}

.kiosk-result-countdown {
  position: absolute;
  animation: result-countdown var(--dismiss-ms, 12000ms) linear forwards;
  background: currentcolor;
  block-size: 6px;
  inset-block-end: 0;
  inset-inline-start: 0;
  opacity: 0.55;
}

.kiosk-result:hover .kiosk-result-countdown {
  animation-play-state: paused;
}

@keyframes result-countdown {
  from { inline-size: 100%; }
  to { inline-size: 0; }
}

/* Sidebar */
.kiosk-side {
  display: flex;
  flex: none;
  flex-direction: column;
  gap: 1rem;
  min-inline-size: 0;
}

.kiosk-counts {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.kiosk-count {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  gap: 0.75rem;
  min-inline-size: 0;
}

.kiosk-count-avatar {
  flex-shrink: 0;
}

.kiosk-count-value {
  font-size: 1.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  line-height: 1;
}

.kiosk-count-label {
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.8125rem;
  margin-block-start: 0.25rem;
}

.kiosk-feed-card {
  display: flex;
  flex: none;
  flex-direction: column;
  min-block-size: 0;
  min-inline-size: 0;
}

.kiosk-feed-header {
  display: flex;
  align-items: center;
  padding: 1rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  font-weight: 600;
  gap: 0.5rem;
}

.kiosk-feed-count {
  flex-shrink: 0;
}

.kiosk-feed-body {
  flex: 1;
  padding: 0.75rem;
  min-block-size: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

/* Compact header on phones */
@media (max-width: 599.98px) {
  .kiosk-header {
    gap: 0.5rem;
    padding-block: 0.5rem;
    padding-inline: 0.75rem;
  }

  .kiosk-brand {
    gap: 0.5rem;
  }

  .kiosk-brand-avatar {
    border-radius: 0.625rem;
  }

  .kiosk-brand-title {
    font-size: 1rem;
  }

  .kiosk-brand-subtitle {
    font-size: 0.75rem;
  }

  .kiosk-status {
    font-size: 0.75rem;
    gap: 0.375rem;
    margin-inline-end: 0.25rem;
    padding-block: 0.25rem;
    padding-inline: 0.625rem;
  }

  .kiosk-body {
    padding: 0.75rem;
    gap: 0.75rem;
  }

  .kiosk-counts {
    gap: 0.5rem;
  }

  .kiosk-count {
    gap: 0.5rem;
    padding-block: 0.5rem;
    padding-inline: 0.625rem;
  }

  .kiosk-count-value {
    font-size: 1.375rem;
  }

  .kiosk-count-label {
    font-size: 0.75rem;
  }

  .kiosk-feed-header {
    padding-block: 0.5rem;
    padding-inline: 0.75rem;
  }

  .kiosk-feed-body {
    padding: 0.5rem;
    max-block-size: 34dvh;
  }
}

@media (max-width: 420px) {
  .kiosk-brand-subtitle {
    display: none;
  }

  .kiosk-status-label {
    overflow: hidden;
    max-inline-size: 5.5rem;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

/* Short screens (landscape phones, small terminals): shrink the stage so nothing scrolls */
@media (max-height: 740px) {
  .kiosk-stage {
    gap: clamp(0.5rem, 1.5vh, 1rem);
  }

  .kiosk-date {
    font-size: 0.875rem;
  }

  .kiosk-time {
    font-size: clamp(1.75rem, 7vh, 3.25rem);
    margin-block-start: 0.125rem;
  }

  .kiosk-title {
    font-size: 1.375rem;
  }

  .kiosk-help {
    font-size: 0.9375rem;
    margin-block-end: 0.25rem;
  }

  .kiosk-qr-frame {
    block-size: clamp(110px, 17vh, 200px);
    inline-size: clamp(110px, 17vh, 200px);
    margin-block-end: 0.25rem;
  }

  .kiosk-prompt {
    gap: 0.5rem;
  }

  .kiosk-pin-display {
    margin-block-end: 0.25rem;
  }

  .kiosk-pin-slot {
    block-size: clamp(2.25rem, 9vh, 3.25rem);
    font-size: 1.25rem;
  }

  .kiosk-key {
    block-size: clamp(44px, 7.5vh, 60px);
    font-size: 1.5rem;
  }

  .kiosk-pin-pad {
    gap: 0.5rem;
  }

  .kiosk-result-action {
    font-size: 1.5rem;
  }

  .kiosk-result-icon {
    font-size: 4rem;
  }
}

/* Tablet / desktop terminal: two-column layout with a scrollable feed */
@media (min-width: 1200px) {
  .kiosk-header {
    padding-inline: 2rem;
  }

  .kiosk-body {
    display: grid;
    gap: 2rem;
    grid-template-columns: minmax(0, 1fr) 380px;
    padding-block: 1.5rem;
    padding-inline: 2rem;
  }

  .kiosk-side {
    min-block-size: 0;
  }

  .kiosk-feed-card {
    flex: 1;
  }
}

/* Large displays & 4K Kiosk terminals */
@media (min-width: 1920px) {
  .kiosk-body {
    gap: 3rem;
    grid-template-columns: minmax(0, 1fr) 460px;
    padding-block: 2.5rem;
    padding-inline: 4rem;
  }

  .kiosk-time {
    font-size: clamp(4rem, 10vw, 7.5rem);
  }

  .kiosk-title {
    font-size: clamp(2.5rem, 5vw, 3.5rem);
  }

  .kiosk-help {
    font-size: 1.35rem;
  }

  .kiosk-qr-frame {
    block-size: clamp(240px, 28vh, 360px);
    inline-size: clamp(240px, 28vh, 360px);
  }

  .kiosk-primary-action {
    font-size: 1.25rem;
    min-block-size: 58px;
    min-inline-size: 380px;
  }

  .kiosk-pin-slot {
    block-size: 5rem;
    font-size: 2.5rem;
  }

  .kiosk-key {
    block-size: 80px;
    font-size: 2.25rem;
  }

  .kiosk-counts {
    gap: 1.5rem;
  }

  .kiosk-count-value {
    font-size: 2.25rem;
  }

  .kiosk-count-label {
    font-size: 1rem;
  }
}

@media (min-width: 2560px) {
  .kiosk-body {
    gap: 4rem;
    grid-template-columns: minmax(0, 1fr) 560px;
    padding-block: 3.5rem;
    padding-inline: 6rem;
  }

  .kiosk-time {
    font-size: clamp(5rem, 12vw, 9.5rem);
  }

  .kiosk-title {
    font-size: clamp(3rem, 6vw, 4.5rem);
  }

  .kiosk-qr-frame {
    block-size: clamp(320px, 32vh, 480px);
    inline-size: clamp(320px, 32vh, 480px);
  }

  .kiosk-primary-action {
    font-size: 1.5rem;
    min-block-size: 72px;
    min-inline-size: 460px;
  }

  .kiosk-key {
    block-size: 100px;
    font-size: 3rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .kiosk-status-dot,
  .kiosk-qr-icon,
  .kiosk-result {
    animation: none;
  }
}
</style>
