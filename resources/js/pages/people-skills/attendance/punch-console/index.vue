<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpPageHeader,
} from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useHrStore } from '@/stores/hrStore'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { $api } from '@/utils/api'
import EmployeeBadgeDialog from '@/views/people-skills/attendance/EmployeeBadgeDialog.vue'
import BadgeScanPanel from '@/views/people-skills/attendance/punch-console/BadgeScanPanel.vue'
import BulkPunchPanel from '@/views/people-skills/attendance/punch-console/BulkPunchPanel.vue'
import HrQuickTimeClockWidget from '@/views/people-skills/attendance/punch-console/HrQuickTimeClockWidget.vue'
import ManualPunchPanel from '@/views/people-skills/attendance/punch-console/ManualPunchPanel.vue'
import PinPadPanel from '@/views/people-skills/attendance/punch-console/PinPadPanel.vue'
import PunchConsoleTabHeader from '@/views/people-skills/attendance/punch-console/PunchConsoleTabHeader.vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/attendance/punch-console',
  name: 'people-skills-attendance-punch-console',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'punch',
    subject: 'attendance',
  },
})

const { t, locale } = useI18n()
const { smAndDown, xs } = useDisplay()
const { showMessage } = useAppSnackbar()
const attendanceStore = useAttendanceStore()
const hrStore = useHrStore()

const getLocalDate = () => {
  const date = new Date()

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const mode = ref('badge')
const isMobileFabOpen = ref(false)
const geotag = ref(false)
const geoCoords = ref(null)
const isGeolocating = ref(false)
const employees = ref([])
const isLoadingEmployees = ref(false)
const isScanning = ref(false)
const result = ref(null)
const isBulkBusy = ref(false)
const bulkResults = ref([])
const badgeDialogOpen = ref(false)
const badgeLookupId = ref(null)
const badgeProfile = ref(null)
const isBadgeLoading = ref(false)
const recentPunches = ref([])
const isLoadingRecent = ref(false)
const todayStats = ref({ in: 0, out: 0 })
const now = ref(new Date())

let clockTimer = null

const clockTime = computed(() => now.value.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit', second: '2-digit' }))
const clockDate = computed(() => now.value.toLocaleDateString(locale.value, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))

const modes = computed(() => [
  { value: 'badge', label: t('attendance.punch_console_modes.badge_qr', 'Badge QR'), icon: 'tabler-qrcode', hint: t('hr.attendance.scan_badge_hint', 'Scan the employee badge') },
  { value: 'manual', label: t('attendance.punch_console_modes.manual', 'Manual'), icon: 'tabler-edit', hint: t('hr.attendance.manual_hint', 'Log a punch or full record') },
  { value: 'pin', label: t('attendance.punch_console_modes.pin_pad', 'PIN Pad'), icon: 'tabler-dialpad', hint: t('hr.attendance.pin_hint_short', 'Lost-badge fallback') },
  { value: 'team', label: t('attendance.punch_console_modes.crew', 'Crew'), icon: 'tabler-users-group', hint: t('hr.attendance.crew_hint', 'Punch a whole team') },
  { value: 'quick', label: t('attendance.punch_console_modes.quick_clock', 'Quick time clock'), icon: 'tabler-fingerprint', hint: t('hr.attendance.self_punch_hint', 'Clock yourself in/out') },
])

const mobileNavItems = computed(() => modes.value.map(tab => ({
  title: tab.label,
  icon: tab.icon,
  value: tab.value,
})))

const mobileFabActions = computed(() => [
  {
    id: 'open-terminal',
    icon: 'tabler-device-desktop',
    title: t('attendance.open_terminal', 'Open Terminal'),
    to: { name: 'people-skills-attendance-kiosk' },
  },
  {
    id: 'attendance-list',
    icon: 'tabler-list',
    title: t('attendance.punch_console.return_to_list', 'Attendance List'),
    to: { name: 'people-skills-attendance-list' },
  },
  {
    id: 'toggle-geotag',
    icon: 'tabler-map-pin',
    title: geotag.value
      ? t('hr.attendance.disable_geotag', 'Disable geotag')
      : t('hr.attendance.enable_geotag', 'Enable geotag'),
    onClick: () => { geotag.value = !geotag.value },
  },
])

const employeeOptions = computed(() => employees.value.map(employee => ({
  id: employee.id,
  title: `${employee.full_name} (${employee.employee_number})`,
})))

const loadEmployees = async () => {
  isLoadingEmployees.value = true
  try {
    const response = await $api('/hr/employees', {
      method: 'GET',
      params: { status: 'active', per_page: 200 },
    })

    employees.value = (response.data || []).map(employee => ({
      id: employee.id,
      full_name: employee.full_name,
      employee_number: employee.employee_number,
      department_id: employee.department?.id || employee.department_id || null,
      department: employee.department?.name || null,
      team_id: employee.current_team?.id || employee.current_team_id || null,
      team: employee.current_team?.name || null,
    }))
  }
  catch (error) {
    showMessage(error?.data?.message || t('attendance.load_employees_error', 'Failed to load employees'), 'error')
  }
  finally {
    isLoadingEmployees.value = false
  }
}

const loadRecent = async () => {
  isLoadingRecent.value = true
  try {
    const response = await $api('/hr/attendances/timeline', {
      method: 'GET',
      params: { date: getLocalDate() },
    })

    const events = (response.data || [])
      .flatMap(group => (group.events || []).map(event => ({
        id: event.id,
        full_name: group.employee?.full_name,
        employee_number: group.employee?.employee_number,
        type: event.type,
        time: event.time,
        source: event.source,
      })))
      .sort((first, second) => String(second.time).localeCompare(String(first.time)))

    todayStats.value = {
      in: events.filter(event => event.type === 'in').length,
      out: events.filter(event => event.type === 'out').length,
    }
    recentPunches.value = events.slice(0, 8)
  }
  catch (error) {
    showMessage(error?.data?.message || t('attendance.timeline_load_failed', 'Failed to load the attendance timeline'), 'error')
  }
  finally {
    isLoadingRecent.value = false
  }
}

onMounted(() => {
  loadEmployees()
  loadRecent()
  hrStore.fetchMeta()
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
})

onBeforeUnmount(() => {
  if (clockTimer) clearInterval(clockTimer)
})

const captureLocation = () => new Promise(resolve => {
  if (!geotag.value || typeof navigator === 'undefined' || !navigator.geolocation) {
    resolve(null)

    return
  }

  if (geoCoords.value && Date.now() - geoCoords.value.at < 60000) {
    resolve(geoCoords.value)

    return
  }

  isGeolocating.value = true
  navigator.geolocation.getCurrentPosition(
    position => {
      geoCoords.value = {
        latitude: Number(position.coords.latitude.toFixed(6)),
        longitude: Number(position.coords.longitude.toFixed(6)),
        at: Date.now(),
      }
      isGeolocating.value = false
      resolve(geoCoords.value)
    },
    () => {
      isGeolocating.value = false
      showMessage(t('hr.attendance.location_unavailable', 'Location unavailable — punch recorded without coordinates.'), 'warning')
      resolve(null)
    },
    { enableHighAccuracy: true, timeout: 6000, maximumAge: 30000 },
  )
})

const setResult = payload => {
  result.value = payload
}

const afterPunch = () => {
  loadRecent()
}

const handleScan = async payload => {
  if (isScanning.value) return

  isScanning.value = true

  const coords = await captureLocation()

  try {
    const response = await attendanceStore.scanBadge(payload.badge, {
      ...(payload.event_type ? { event_type: payload.event_type } : {}),
      ...(coords ? { latitude: coords.latitude, longitude: coords.longitude } : {}),
      device_id: payload.method === 'camera' ? 'webcam-scanner' : 'pin-pad',
    })

    setResult({
      ok: true,
      action: response.action,
      name: response.employee?.full_name,
      employeeNumber: response.employee?.employee_number,
      department: response.employee?.department,
      time: response.event?.time?.substring(0, 5),
      message: response.message,
    })
    showMessage(response.message || t('attendance.punch_recorded', 'Punch recorded successfully'), 'success')
    afterPunch()
  }
  catch (error) {
    setResult({
      ok: false,
      message: error?.data?.message || t('attendance.punch_error', 'The badge could not be processed.'),
    })
    showMessage(error?.data?.message || t('attendance.punch_error', 'Badge scan failed'), 'error')
  }
  finally {
    isScanning.value = false
  }
}

const handleManual = async payload => {
  if (isScanning.value) return

  isScanning.value = true
  if (payload.kind === 'record') {
    try {
      const response = await $api('/hr/attendances', {
        method: 'POST',
        body: {
          employee_id: payload.employee_id,
          date: payload.date,
          scheduled_in: payload.scheduled_in,
          scheduled_out: payload.scheduled_out,
          actual_in: payload.actual_in,
          actual_out: payload.actual_out,
          status: payload.status,
          notes: payload.notes,
        },
      })

      setResult({
        ok: true,
        action: 'record',
        name: payload.full_name,
        time: payload.actual_in || payload.actual_out || '—',
        message: response.message || t('attendance.record_saved', 'Attendance record saved.'),
      })
      showMessage(response.message || t('attendance.record_saved', 'Attendance record saved.'), 'success')
      afterPunch()
    }
    catch (error) {
      setResult({ ok: false, message: error?.data?.message || t('attendance.record_save_error', 'Could not save the record.') })
      showMessage(error?.data?.message || t('attendance.record_save_error', 'Could not save the attendance record'), 'error')
    }
    finally {
      isScanning.value = false
    }

    return
  }

  const coords = await captureLocation()

  try {
    const response = await $api(`/hr/time-clock/${payload.event_type === 'in' ? 'clock-in' : 'clock-out'}`, {
      method: 'POST',
      body: {
        employee_id: payload.employee_id,
        event_type: payload.event_type,
        event_time: payload.event_time,
        source: 'manual',
        notes: payload.notes,
        ...(coords ? { latitude: coords.latitude, longitude: coords.longitude } : {}),
      },
    })

    setResult({
      ok: true,
      action: payload.event_type,
      name: payload.full_name,
      time: response.event?.time?.substring(0, 5),
      message: response.message || t('attendance.punch_recorded', 'Manual punch recorded.'),
    })
    showMessage(response.message || t('attendance.punch_recorded', `Manual ${payload.event_type === 'in' ? 'check-in' : 'check-out'} recorded.`), 'success')
    afterPunch()
  }
  catch (error) {
    setResult({ ok: false, message: error?.data?.message || t('attendance.punch_error', 'Manual punch failed.') })
    showMessage(error?.data?.message || t('attendance.punch_error', 'Manual punch failed'), 'error')
  }
  finally {
    isScanning.value = false
  }
}

const handleBulk = async payload => {
  isBulkBusy.value = true
  bulkResults.value = []
  try {
    const response = await attendanceStore.bulkPunch(payload)

    bulkResults.value = response.results || []
    setResult({
      ok: (response.recorded || 0) > 0,
      action: payload.event_type,
      name: `${response.recorded} ${t('attendance.employees', 'employee(s)')}`,
      time: payload.event_time?.substring(11, 16),
      message: response.message,
    })
    showMessage(response.message || t('attendance.bulk_success', 'Bulk punch recorded'), 'success')
    afterPunch()
  }
  catch (error) {
    setResult({ ok: false, message: error?.data?.message || t('attendance.bulk_error', 'Bulk punch failed.') })
    showMessage(error?.data?.message || t('attendance.bulk_error', 'Bulk punch failed'), 'error')
  }
  finally {
    isBulkBusy.value = false
  }
}

const openBadge = async employeeId => {
  if (!employeeId) return

  badgeLookupId.value = employeeId
  badgeProfile.value = null
  badgeDialogOpen.value = true
  isBadgeLoading.value = true
  try {
    badgeProfile.value = await attendanceStore.getBadge(employeeId)
  }
  catch (error) {
    showMessage(error?.data?.message || t('attendance.badge_load_error', 'Could not load the badge'), 'error')
    badgeDialogOpen.value = false
  }
  finally {
    isBadgeLoading.value = false
  }
}

const initials = name => String(name || '')
  .trim()
  .split(/\s+/)
  .slice(0, 2)
  .map(part => part[0] || '')
  .join('')
  .toUpperCase() || 'HR'
</script>

<template>
  <div class="people-skills-attendance-punch-console d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('attendance.punch_console.title', 'Punch Console')"
      :subtitle="$t('attendance.punch_console.subtitle', 'Record attendance using a badge, PIN, manual entry, or team punch.')"
      icon="tabler-clock-bolt"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-list"
          :to="{ name: 'people-skills-attendance-list' }"
        >
          {{ $t('attendance.punch_console.return_to_list', 'Attendance List') }}
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-device-desktop"
          :to="{ name: 'people-skills-attendance-kiosk' }"
        >
          {{ $t('attendance.open_terminal', 'Open Terminal') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- Last punch result -->
    <Transition name="punch-result">
      <VCard
        v-if="result"
        :color="result.ok ? 'success' : 'error'"
        variant="tonal"
        role="status"
        aria-live="polite"
      >
        <VCardItem>
          <template #prepend>
            <VAvatar
              size="44"
              :color="result.ok ? 'success' : 'error'"
              variant="flat"
            >
              <VIcon
                :icon="result.ok
                  ? (result.action === 'in' ? 'tabler-door-enter' : result.action === 'out' ? 'tabler-door-exit' : 'tabler-check')
                  : 'tabler-alert-triangle'"
                color="white"
              />
            </VAvatar>
          </template>

          <VCardTitle class="font-weight-bold">
            {{ result.name || $t('hr.attendance.punch', 'Punch') }}
            <span
              v-if="result.time"
              class="font-weight-medium"
            > · {{ result.time }}</span>
          </VCardTitle>
          <VCardSubtitle class="text-wrap">
            {{ result.message }}
          </VCardSubtitle>

          <template #append>
            <VBtn
              icon="tabler-x"
              size="small"
              variant="text"
              :aria-label="$t('wp.close', 'Close')"
              @click="result = null"
            />
          </template>
        </VCardItem>
      </VCard>
    </Transition>

    <VRow>
      <!-- Console -->
      <VCol
        cols="12"
        lg="8"
      >
        <AppHybridTabs
          v-model="mode"
          :tabs="modes"
          header-class="d-none d-md-block"
          :swipeable="false"
          crossfade
        >
          <template #tab-badge>
            <PunchConsoleTabHeader
              :title="modes[0].label"
              :hint="modes[0].hint"
              :icon="modes[0].icon"
              :geotag="geotag"
              :geolocating="isGeolocating"
              class="mb-5"
              @toggle-geotag="geotag = !geotag"
            />
            <BadgeScanPanel
              :busy="isScanning"
              :geotag="geotag"
              @submit="handleScan"
            />

            <VDivider class="my-5" />

            <div class="text-overline text-medium-emphasis mb-2">
              {{ $t('hr.attendance.badges', 'Employee badges') }}
            </div>
            <div class="d-flex gap-2 align-center flex-wrap flex-sm-nowrap">
              <VAutocomplete
                v-model="badgeLookupId"
                :items="employeeOptions"
                item-title="title"
                item-value="id"
                :label="$t('hr.attendance.find_badge', 'Find an employee badge to view or print')"
                :loading="isLoadingEmployees"
                prepend-inner-icon="tabler-id-badge-2"
                density="comfortable"
                variant="outlined"
                hide-details
                class="grow"
                clearable
              />
              <VBtn
                variant="tonal"
                color="secondary"
                height="48"
                :block="xs"
                prepend-icon="tabler-qrcode"
                :disabled="!badgeLookupId"
                @click="openBadge(badgeLookupId)"
              >
                {{ $t('hr.attendance.show_badge', 'Show badge') }}
              </VBtn>
            </div>
          </template>

          <template #tab-manual>
            <PunchConsoleTabHeader
              :title="modes[1].label"
              :hint="modes[1].hint"
              :icon="modes[1].icon"
              :geotag="geotag"
              :geolocating="isGeolocating"
              class="mb-5"
              @toggle-geotag="geotag = !geotag"
            />
            <ManualPunchPanel
              :employees="employeeOptions"
              :loading-employees="isLoadingEmployees"
              :busy="isScanning"
              @submit="handleManual"
            />
          </template>

          <template #tab-pin>
            <PunchConsoleTabHeader
              :title="modes[2].label"
              :hint="modes[2].hint"
              :icon="modes[2].icon"
              :geotag="geotag"
              :geolocating="isGeolocating"
              class="mb-5"
              @toggle-geotag="geotag = !geotag"
            />
            <PinPadPanel
              :busy="isScanning"
              @submit="handleScan"
            />
          </template>

          <template #tab-team>
            <PunchConsoleTabHeader
              :title="modes[3].label"
              :hint="modes[3].hint"
              :icon="modes[3].icon"
              :geotag="geotag"
              :geolocating="isGeolocating"
              class="mb-5"
              @toggle-geotag="geotag = !geotag"
            />
            <BulkPunchPanel
              :employees="employees"
              :departments="hrStore.departments"
              :teams="hrStore.teams"
              :busy="isBulkBusy"
              :loading-employees="isLoadingEmployees"
              :results="bulkResults"
              @submit="handleBulk"
            />
          </template>

          <template #tab-quick>
            <PunchConsoleTabHeader
              :title="modes[4].label"
              :hint="modes[4].hint"
              :icon="modes[4].icon"
              :geotag="geotag"
              :geolocating="isGeolocating"
              class="mb-5"
              @toggle-geotag="geotag = !geotag"
            />
            <HrQuickTimeClockWidget @clock-event="afterPunch" />
          </template>
        </AppHybridTabs>
      </VCol>

      <!-- Live side rail -->
      <VCol
        cols="12"
        lg="4"
        class="d-flex flex-column gap-6"
      >
        <VCard>
          <VCardText class="d-flex flex-column align-center text-center gap-2">
            <VChip
              size="small"
              variant="tonal"
              :color="geotag ? 'info' : undefined"
              :prepend-icon="geotag ? 'tabler-map-pin' : 'tabler-map-pin-off'"
            >
              {{ geotag ? $t('hr.attendance.geotag_on', 'Geotag on') : $t('hr.attendance.geotag_off', 'Geotag off') }}
            </VChip>
            <div class="text-h3 text-md-h2 font-weight-bold text-primary">
              {{ clockTime }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ clockDate }}
            </div>
          </VCardText>

          <VDivider />

          <VCardText>
            <VRow dense>
              <VCol cols="6">
                <VCard
                  variant="tonal"
                  color="success"
                >
                  <VCardText class="d-flex align-center gap-3">
                    <VIcon
                      icon="tabler-door-enter"
                      size="28"
                    />
                    <div>
                      <div class="text-h5 font-weight-bold">
                        {{ todayStats.in }}
                      </div>
                      <div class="text-caption">
                        {{ $t('hr.attendance.check_ins', 'Check-ins') }}
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="6">
                <VCard
                  variant="tonal"
                  color="warning"
                >
                  <VCardText class="d-flex align-center gap-3">
                    <VIcon
                      icon="tabler-door-exit"
                      size="28"
                    />
                    <div>
                      <div class="text-h5 font-weight-bold">
                        {{ todayStats.out }}
                      </div>
                      <div class="text-caption">
                        {{ $t('hr.attendance.check_outs', 'Check-outs') }}
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard aria-live="polite">
          <VCardItem>
            <template #prepend>
              <VIcon
                icon="tabler-activity-heartbeat"
                color="primary"
              />
            </template>
            <VCardTitle>
              {{ $t('hr.attendance.today_punches', "Today's punches") }}
            </VCardTitle>
            <template #append>
              <VBtn
                icon="tabler-refresh"
                size="small"
                variant="text"
                :loading="isLoadingRecent"
                :aria-label="$t('wp.refresh', 'Refresh')"
                @click="loadRecent"
              />
            </template>
          </VCardItem>

          <VDivider />

          <VCardText v-if="!recentPunches.length && isLoadingRecent">
            <VSkeletonLoader type="list-item-avatar-two-line@4" />
          </VCardText>

          <VList
            v-else-if="recentPunches.length"
            lines="two"
            density="comfortable"
            class="py-0"
          >
            <TransitionGroup name="punch-feed-row">
              <VListItem
                v-for="punch in recentPunches"
                :key="punch.id"
              >
                <template #prepend>
                  <VAvatar
                    size="34"
                    :color="punch.type === 'in' ? 'success' : 'warning'"
                    variant="tonal"
                  >
                    <span class="text-caption font-weight-bold">{{ initials(punch.full_name) }}</span>
                  </VAvatar>
                </template>

                <VListItemTitle class="text-body-2 font-weight-medium">
                  {{ punch.full_name || $t('hr.employee', 'Employee') }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ punch.employee_number }}<span v-if="punch.source"> · {{ punch.source }}</span>
                </VListItemSubtitle>

                <template #append>
                  <VChip
                    size="small"
                    variant="tonal"
                    class="text-uppercase"
                    :color="punch.type === 'in' ? 'success' : 'warning'"
                  >
                    {{ punch.type === 'in' ? $t('hr.attendance.in_short', 'In') : $t('hr.attendance.out_short', 'Out') }}
                    {{ String(punch.time).substring(0, 5) }}
                  </VChip>
                </template>
              </VListItem>
            </TransitionGroup>
          </VList>

          <VCardText
            v-else
            class="text-center text-medium-emphasis text-body-2 py-8"
          >
            <VIcon
              icon="tabler-clock-off"
              size="30"
              class="mb-2 d-block mx-auto opacity-50"
            />
            {{ $t('hr.attendance.no_punches_short', 'No punches yet today.') }}
          </VCardText>
        </VCard>

        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          closable
          prepend-icon="tabler-bulb"
        >
          {{ $t('hr.attendance.console_tip', 'Tip: enable Geotag to stamp every punch with its GPS location, or launch Kiosk mode for a shared entrance screen.') }}
        </VAlert>
      </VCol>
    </VRow>

    <EmployeeBadgeDialog
      v-model:is-dialog-visible="badgeDialogOpen"
      :badge="badgeProfile"
      :loading="isBadgeLoading"
    />

    <AppBottomNav
      v-if="smAndDown"
      v-model="mode"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      :aria-label="$t('attendance.punch_console.navigation', 'Punch console modes')"
    />

    <AppFabSpeedDial
      v-if="smAndDown"
      v-model="isMobileFabOpen"
      class="d-md-none punch-console-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.people-skills-attendance-punch-console {
  padding-block-end: 1rem;
}

.punch-result-enter-active,
.punch-feed-row-enter-active {
  transition: all 0.3s ease;
}

.punch-result-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}

.punch-feed-row-enter-from {
  opacity: 0;
  transform: translateX(12px);
}

.punch-console-quick-actions {
  inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
}

@media (max-width: 959px) {
  .people-skills-attendance-punch-console {
    padding-block-end: 7rem;
  }
}
</style>
