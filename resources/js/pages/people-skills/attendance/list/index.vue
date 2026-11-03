<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpActiveFilters,
  WpDetailDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpQrScannerDialog,
  WpResponsiveList,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useListPage } from '@/composables/list'
import { useEmployeeAttendanceScanner } from '@/composables/people-skills/attendance/useEmployeeAttendanceScanner'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useHrStore } from '@/stores/hrStore'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { $api } from '@/utils/api'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import AttendanceConfigTab from '@/views/people-skills/attendance/AttendanceConfigTab.vue'
import AttendanceTimelineTab from '@/views/people-skills/attendance/AttendanceTimelineTab.vue'
import AttendanceExceptionsTab from '@/views/people-skills/attendance/AttendanceExceptionsTab.vue'
import ManualPunchDialog from '@/views/people-skills/attendance/ManualPunchDialog.vue'
import BulkPunchDialog from '@/views/people-skills/attendance/BulkPunchDialog.vue'
import EmployeeBadgeDialog from '@/views/people-skills/attendance/EmployeeBadgeDialog.vue'

definePage({
  path: '/people-skills/attendance/list',
  name: 'people-skills-attendance-list',
  alias: ['/people-skills/attendance'],
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { showMessage } = useAppSnackbar()
const attendanceStore = useAttendanceStore()
const hrStore = useHrStore()
const attendanceScanner = useEmployeeAttendanceScanner()
const scanResultOpen = ref(false)
const isFilterDialogOpen = ref(false)

// ── Tab state ─────────────────────────────────────────────────────────────
const activeTab = ref('records')
const isMobileFabOpen = ref(false)

const attendanceTabs = computed(() => [
  { value: 'records', title: t('attendance.tabs.records', 'Records'), icon: 'tabler-list' },
  { value: 'timeline', title: t('attendance.tabs.timeline', 'Timeline'), icon: 'tabler-chart-histogram' },
  { value: 'exceptions', title: t('attendance.tabs.exceptions', 'Exceptions'), icon: 'tabler-alert-triangle' },
  { value: 'config', title: t('attendance.tabs.config', 'Configuration'), icon: 'tabler-adjustments-horizontal' },
])

const mobileNavItems = computed(() => attendanceTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
})))

const exceptionsRef = ref(null)
const configRef = ref(null)

const mobileFabActions = computed(() => {
  if (activeTab.value === 'config') {
    return [
      {
        id: 'save-config',
        icon: 'tabler-device-floppy',
        title: t('Save'),
        color: 'primary',
        disabled: !configRef.value?.isDirty || !configRef.value?.isFormValid || configRef.value?.isSaving,
        onClick: () => configRef.value?.saveConfig(),
      },
      ...(configRef.value?.isDirty ? [{
        id: 'reset-config',
        icon: 'tabler-rotate-clockwise',
        title: t('Reset'),
        color: 'secondary',
        onClick: () => configRef.value?.resetForm(),
      }] : []),
    ]
  }

  const actions = [
    {
      id: 'open-terminal',
      icon: 'tabler-device-desktop',
      title: t('attendance.open_terminal', 'Open Terminal'),
      to: { name: 'people-skills-attendance-kiosk' },
    },
    {
      id: 'punch-console',
      icon: 'tabler-clock-bolt',
      title: t('attendance.punch_console', 'Punch Console'),
      to: { name: 'people-skills-attendance-punch-console' },
    },
    {
      id: 'scan-badge',
      icon: 'tabler-qrcode',
      title: t('attendance.scan_badge', 'Scan Badge'),
      onClick: attendanceScanner.open,
    },
  ]

  if (activeTab.value === 'records') {
    actions.push(
      {
        id: 'attendance-filters',
        icon: 'tabler-filter',
        title: t('wp.filters.label', 'Filters'),
        onClick: openFilterDialog,
      },
      {
        id: 'manual-punch',
        icon: 'tabler-edit',
        title: t('attendance.manual_punch', 'Manual Punch'),
        onClick: openManualPunch,
      },
      {
        id: 'bulk-punch',
        icon: 'tabler-users-group',
        title: t('attendance.bulk_punch', 'Bulk Punch'),
        onClick: openBulkPunch,
      },
    )
  }

  if (activeTab.value === 'exceptions') {
    actions.push({
      id: 'exception-filters',
      icon: 'tabler-filter',
      title: t('wp.filters.label', 'Filters'),
      onClick: () => exceptionsRef.value?.openFilters(),
    })
  }

  return actions
})

const handleBadgeScan = async value => {
  const result = await attendanceScanner.processScan(value)
  if (attendanceScanner.resultRef.value) {
    scanResultOpen.value = true
  }
  if (result?.success) {
    listPage.refresh()
    loadKpis()
  }
}

// ── KPI Cards ─────────────────────────────────────────────────────────────
const kpiLoading = ref(false)

const kpiData = ref(null)

const loadKpis = async () => {
  kpiLoading.value = true
  try {
    const date = new Date().toISOString().split('T')[0]

    const res = await attendanceStore.fetchList({ date, per_page: 1 })

    if (!res?.meta?.stats) {
      throw new Error('Attendance statistics were not returned by the API.')
    }

    kpiData.value = res.meta.stats
  } catch (err) {
    showMessage(err?.data?.message || t('attendance.load_kpis_error'), 'error')
  } finally {
    kpiLoading.value = false
  }
}

const kpiCards = computed(() => [
  {
    titleKey: 'attendance.kpi.present',
    title: t('attendance.kpi.present', 'Present'),
    value: kpiData.value?.present ?? '—',
    icon: 'tabler-user-check',
    iconColor: 'success',
  },
  {
    titleKey: 'attendance.kpi.late',
    title: t('attendance.kpi.late', 'Late'),
    value: kpiData.value?.late ?? '—',
    icon: 'tabler-clock-exclamation',
    iconColor: 'warning',
  },
  {
    titleKey: 'attendance.kpi.absent',
    title: t('attendance.kpi.absent', 'Absent'),
    value: kpiData.value?.absent ?? '—',
    icon: 'tabler-user-x',
    iconColor: 'error',
  },
  {
    titleKey: 'attendance.kpi.on_leave',
    title: t('attendance.kpi.on_leave', 'On leave'),
    value: kpiData.value?.on_leave ?? '—',
    icon: 'tabler-calendar-off',
    iconColor: 'info',
  },
  {
    titleKey: 'attendance.kpi.on_duty',
    title: t('attendance.kpi.on_duty', 'On duty'),
    value: kpiData.value?.on_duty ?? '—',
    icon: 'tabler-user-bolt',
    iconColor: 'primary',
  },
])

// ── Active filters ─────────────────────────────────────────────────────────
const listPage = useListPage({
  fetchFn: params => attendanceStore.fetchList(params),
  initialFilters: {
    date: null,
    employee_id: null,
    department_id: null,
    status: null,
  },
  initialSortKey: 'date',
  initialSortOrder: 'desc',
  immediate: true,
})

const openFilterDialog = () => {
  listPage.stageFilters()
  isFilterDialogOpen.value = true
}

const applyFilters = () => {
  listPage.applyStaged()
  isFilterDialogOpen.value = false
}

const resetFilters = () => {
  listPage.resetStaged()
  listPage.applyStaged()
  isFilterDialogOpen.value = false
}

// ── View dialog ────────────────────────────────────────────────────────────
const viewDialog  = useDialog()
const viewRecord  = ref(null)
const viewLoading = ref(false)

const openView = async id => {
  viewRecord.value = null
  viewLoading.value = true
  viewDialog.open(id)
  try {
    viewRecord.value = await attendanceStore.fetchOne(id)
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.load_error'), 'error')
    viewDialog.close()
  }
  finally { viewLoading.value = false }
}

// ── Correction dialog ─────────────────────────────────────────────────────
const corrDialog = useDialog()

// Backend contract (AttendanceCorrectionRequest): requested_actual_in /
// requested_actual_out (H:i or H:i:s) + required reason.
const corrForm   = reactive({ requested_actual_in: '', requested_actual_out: '', reason: '' })
const corrSaving = ref(false)

const openCorrection = id => {
  Object.assign(corrForm, { requested_actual_in: '', requested_actual_out: '', reason: '' })
  corrDialog.open(id)
}

const submitCorrection = async () => {
  corrSaving.value = true
  try {
    await attendanceStore.requestCorrection(corrDialog.item.value, { ...corrForm })
    showMessage(t('attendance.correction_success'), 'success')
    corrDialog.close()
    listPage.refresh()
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.correction_error'), 'error')
  }
  finally { corrSaving.value = false }
}

// ── Status color helpers ───────────────────────────────────────────────────
const statusColor = s => ({
  present: 'success', absent: 'error', late: 'warning',
  partial: 'info', excused: 'secondary',
}[s] || 'default')

// Localised attendance status label with a humanised fallback.
const statusLabel = s => (s ? t(`attendance.status.${s}`, s.replaceAll('_', ' ')) : '—')

// ── Table headers ─────────────────────────────────────────────────────────
const headers = computed(() => [
  { title: t('attendance.col.employee'), key: 'employee', sortable: false, minWidth: '200px' },
  { title: t('attendance.col.date'),     key: 'date',     sortable: true },
  { title: t('attendance.col.in'),       key: 'actual_in', sortable: false },
  { title: t('attendance.col.out'),      key: 'actual_out', sortable: false },
  { title: t('attendance.col.hours'),    key: 'worked_hours', sortable: false },
  { title: t('attendance.col.status'),   key: 'status', sortable: true },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const statusOptions = computed(() => [
  { title: t('attendance.status.present'), value: 'present' },
  { title: t('attendance.status.absent'),  value: 'absent' },
  { title: t('attendance.status.late'),    value: 'late' },
  { title: t('attendance.status.partial'), value: 'partial' },
  { title: t('attendance.status.excused'), value: 'excused' },
])

const deptOptions = computed(() =>
  hrStore.departments.map(d => ({ title: d.name, value: d.id })),
)

const getRowActions = () => [
  { label: t('View'),                    icon: 'tabler-eye',   emit: 'view' },
  { label: t('attendance.view_badge', 'View Badge'),      icon: 'tabler-id-badge',   emit: 'badge' },
  { label: t('attendance.correct'),      icon: 'tabler-pencil-check', emit: 'correct' },
]

const formatHours = hours => {
  if (hours == null) return '—'
  const h = Math.floor(Number(hours))
  const m = Math.round((Number(hours) - h) * 60)

  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

// ── Manual Punch Dialog ───────────────────────────────────────────────────
const manualPunchOpen = ref(false)
const manualPunchBusy = ref(false)

const openManualPunch = () => {
  loadEmployees()
  manualPunchOpen.value = true
}

const submitManualPunch = async payload => {
  manualPunchBusy.value = true
  try {
    const result = await attendanceStore.scanBadge(payload.employee_id, {
      event_type: payload.event_type,
      event_time: payload.event_time,
      notes: payload.notes,
      source: 'manual',
    })

    showMessage(result.message || t('attendance.punch_recorded', 'Punch recorded successfully'), 'success')
    manualPunchOpen.value = false
    listPage.refresh()
    loadKpis()
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.punch_error', 'Failed to record punch'), 'error')
  }
  finally {
    manualPunchBusy.value = false
  }
}

// ── Bulk Punch Dialog ─────────────────────────────────────────────────────
const bulkPunchOpen = ref(false)
const bulkPunchBusy = ref(false)
const bulkPunchResults = ref([])

const openBulkPunch = () => {
  loadEmployees()
  bulkPunchResults.value = []
  bulkPunchOpen.value = true
}

const submitBulkPunch = async payload => {
  bulkPunchBusy.value = true
  bulkPunchResults.value = []
  try {
    const result = await attendanceStore.bulkPunch(payload)

    showMessage(result.message || t('attendance.bulk_success', 'Bulk punch recorded'), 'success')
    bulkPunchResults.value = result.results || []
    listPage.refresh()
    loadKpis()

    // Close dialog after short delay to show results
    setTimeout(() => {
      bulkPunchOpen.value = false
    }, 2000)
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.bulk_error', 'Bulk punch failed'), 'error')
  }
  finally {
    bulkPunchBusy.value = false
  }
}

// ── Employee Badge Dialog ─────────────────────────────────────────────────
const badgeDialogOpen = ref(false)
const badgeData = ref(null)
const badgeLoading = ref(false)
const selectedEmployeeId = ref(null)

const openEmployeeBadge = async employeeId => {
  if (!employeeId) {
    showMessage(t('attendance.select_employee_first', 'Please select an employee first'), 'warning')

    return
  }

  selectedEmployeeId.value = employeeId
  badgeData.value = null
  badgeLoading.value = true
  badgeDialogOpen.value = true

  try {
    badgeData.value = await attendanceStore.getBadge(employeeId)
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.badge_load_error', 'Failed to load badge'), 'error')
    badgeDialogOpen.value = false
  }
  finally {
    badgeLoading.value = false
  }
}

// ── Load Employees ────────────────────────────────────────────────────────
const employees = ref([])
const employeesLoading = ref(false)

const loadEmployees = async () => {
  if (employees.value.length > 0) return // Already loaded

  employeesLoading.value = true
  try {
    const res = await $api('/hr/employees', { method: 'GET', params: { status: 'active', per_page: 500 } })

    employees.value = (res.data || []).map(emp => ({
      id: emp.id,
      full_name: emp.full_name,
      title: emp.full_name,
      employee_number: emp.employee_number,
      avatar_url: emp.avatar_url,
      department_id: emp.department?.id || emp.department_id || null,
      department: emp.department?.name || null,
      team_id: emp.current_team?.id || emp.current_team_id || null,
      current_team_id: emp.current_team?.id || emp.current_team_id || null,
      team: emp.current_team?.name || null,
    }))
  }
  catch (err) {
    showMessage(err?.data?.message || t('attendance.load_employees_error'), 'error')
  }
  finally {
    employeesLoading.value = false
  }
}

onMounted(() => {
  hrStore.fetchMeta()
  loadKpis()
})
</script>

<template>
  <div class="people-skills-attendance-list d-flex flex-column gap-6">
    <!-- Header -->
    <WpPageHeader
      :title="$t('attendance.list.title')"
      :subtitle="$t('attendance.list.subtitle')"
      icon="tabler-clock-record"
    >
      <template #actions>
        <VBtn
          class="d-none d-md-inline-flex"
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-device-desktop"
          :to="{ name: 'people-skills-attendance-kiosk' }"
        >
          {{ $t('attendance.open_terminal', 'Open Terminal') }}
        </VBtn>
        <VBtn
          class="d-none d-md-inline-flex"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-clock-bolt"
          :to="{ name: 'people-skills-attendance-punch-console' }"
        >
          {{ $t('attendance.punch_console', 'Punch Console') }}
        </VBtn>
        <VBtn
          class="d-none d-md-inline-flex"
          color="primary"
          prepend-icon="tabler-qrcode"
          @click="attendanceScanner.open"
        >
          {{ $t('attendance.scan_badge', 'Scan Badge') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- KPI Cards -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
      class="attendance-list__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.titleKey"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.iconColor"
            :loading="kpiLoading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- Main Content with Tabs -->
    <AppHybridTabs
      v-model="activeTab"
      :tabs="attendanceTabs"
      class="attendance-list__tabs"
      header-class="d-none d-md-block"
      :swipeable="false"
      crossfade
    >
      <!-- Records Tab -->
      <template #tab-records>
        <WpPageToolbar
          v-model:search="listPage.search.value"
          show-filter
          hide-filter-on-mobile
          :active-filter-count="listPage.activeFilterCount.value"
          :loading="attendanceStore.loading"
          @open-filters="openFilterDialog"
          @refresh="() => { listPage.refresh(); loadKpis() }"
        >
          <!-- Action Buttons -->
          <template #prepend>
            <VBtn
              class="d-none d-md-inline-flex"
              variant="outlined"
              color="secondary"
              prepend-icon="tabler-edit"
              @click="openManualPunch"
            >
              {{ $t('attendance.manual_punch', 'Manual Punch') }}
            </VBtn>
            <VBtn
              class="d-none d-md-inline-flex"
              variant="outlined"
              color="secondary"
              prepend-icon="tabler-users-group"
              @click="openBulkPunch"
            >
              {{ $t('attendance.bulk_punch', 'Bulk Punch') }}
            </VBtn>
          </template>

          <template #filter-chips>
            <WpActiveFilters
              v-if="listPage.activeFilterCount.value > 0"
              :filters="listPage.activeFilters.value"
              :label-map="{
                date: $t('attendance.filter.date'),
                status: $t('attendance.filter.status'),
                department_id: $t('attendance.exception.filter.department', 'Department'),
              }"
              :value-formatters="{
                status: statusLabel,
                department_id: value => deptOptions.find(option => String(option.value) === String(value))?.title || value,
              }"
              @remove="listPage.removeFilter($event.key)"
              @clear-all="listPage.resetFilters"
            />
          </template>
        </WpPageToolbar>

        <WpResponsiveList
          v-model:page="listPage.page.value"
          v-model:items-per-page="listPage.itemsPerPage.value"
          v-model:sort-by="listPage.sortBy.value"
          :headers="headers"
          :items="attendanceStore.list"
          :total-items="attendanceStore.totalItems"
          :loading="attendanceStore.loading"
          :error="attendanceStore.error"
          @retry="listPage.refresh()"
        >
          <template #item.employee="{ item }">
            <div class="d-flex align-center gap-3 py-2">
              <VAvatar
                size="36"
                color="primary"
                variant="tonal"
              >
                <VImg
                  v-if="item.employee?.avatar_url"
                  :src="item.employee.avatar_url"
                />
                <span
                  v-else
                  class="text-caption"
                >
                  {{ avatarText(item.employee?.full_name || '?') }}
                </span>
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.employee?.full_name || '—' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  #{{ item.employee?.employee_number }}
                </div>
              </div>
            </div>
          </template>

          <template #item.date="{ item }">
            <span class="text-body-2">
              {{ new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date(item.date)) }}
            </span>
          </template>

          <template #item.actual_in="{ item }">
            <div
              v-if="item.sessions && item.sessions.length > 1"
              class="d-flex flex-column gap-1 py-1"
            >
              <div
                v-for="(session, sIdx) in item.sessions"
                :key="sIdx"
                class="d-flex align-center gap-1"
              >
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ session.in || '—' }}
                </VChip>
                <span class="text-caption text-medium-emphasis">#{{ sIdx + 1 }}</span>
              </div>
            </div>
            <div
              v-else
              class="d-flex align-center"
            >
              <span class="text-body-2">
                {{ item.actual_in ? item.actual_in.slice(0, 5) : '—' }}
              </span>
              <VIcon
                v-if="item.is_corrected"
                icon="tabler-pencil-check"
                size="12"
                color="info"
                class="ms-1"
              />
            </div>
          </template>

          <template #item.actual_out="{ item }">
            <div
              v-if="item.sessions && item.sessions.length > 1"
              class="d-flex flex-column gap-1 py-1"
            >
              <div
                v-for="(session, sIdx) in item.sessions"
                :key="sIdx"
                class="d-flex align-center gap-1"
              >
                <VChip
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  label
                >
                  {{ session.out || '—' }}
                </VChip>
              </div>
            </div>
            <span
              v-else
              class="text-body-2"
            >{{ item.actual_out ? item.actual_out.slice(0, 5) : '—' }}</span>
          </template>

          <template #item.worked_hours="{ item }">
            <span class="text-body-2">{{ formatHours(item.worked_hours) }}</span>
            <span
              v-if="item.overtime_hours"
              class="text-caption text-warning ms-1"
            >+{{ formatHours(item.overtime_hours) }}</span>
          </template>

          <template #item.status="{ item }">
            <WpStatusChip
              type="attendance"
              :status="item.status"
              :label="statusLabel(item.status)"
            />
          </template>

          <template #item.actions="{ item }">
            <WpRowActions
              :actions="getRowActions()"
              @view="openView(item.id)"
              @badge="openEmployeeBadge(item.employee?.id)"
              @correct="openCorrection(item.id)"
            />
          </template>

          <template #mobile-card="{ item }">
            <WpMobileCard
              :title="item.employee?.full_name || '—'"
              :subtitle="`${item.date} · ${item.actual_in ? item.actual_in.slice(0,5) : '–'} → ${item.actual_out ? item.actual_out.slice(0,5) : '–'}`"
            >
              <template #content>
                <div
                  v-if="item.sessions && item.sessions.length > 1"
                  class="mb-2 d-flex flex-wrap gap-1"
                >
                  <VChip
                    v-for="(session, sIdx) in item.sessions"
                    :key="sIdx"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    label
                  >
                    #{{ sIdx + 1 }}: {{ session.in || '—' }} → {{ session.out || '—' }}
                  </VChip>
                </div>
                <div class="d-flex flex-wrap align-center justify-space-between ga-2 text-caption text-medium-emphasis">
                  <span>{{ $t('attendance.col.hours') }}: {{ formatHours(item.worked_hours) }}</span>
                  <span v-if="item.employee?.department?.name">{{ item.employee.department.name }}</span>
                </div>
              </template>
              <template #actions>
                <div class="d-flex align-center gap-2">
                  <WpStatusChip
                    type="attendance"
                    :status="item.status"
                    :label="statusLabel(item.status)"
                  />
                  <WpRowActions
                    :actions="getRowActions()"
                    @view="openView(item.id)"
                    @badge="openEmployeeBadge(item.employee?.id)"
                    @correct="openCorrection(item.id)"
                  />
                </div>
              </template>
            </WpMobileCard>
          </template>
        </WpResponsiveList>
      </template>

      <!-- Timeline Tab -->
      <template #tab-timeline>
        <div class="attendance-list__tab-content">
          <AttendanceTimelineTab />
        </div>
      </template>

      <!-- Exceptions Tab -->
      <template #tab-exceptions>
        <div class="attendance-list__tab-content">
          <AttendanceExceptionsTab ref="exceptionsRef" />
        </div>
      </template>

      <!-- Configuration Tab -->
      <template #tab-config>
        <div class="attendance-list__tab-content">
          <AttendanceConfigTab ref="configRef" />
        </div>
      </template>
    </AppHybridTabs>

    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="listPage.activeFilterCount.value"
      :title="$t('attendance.filter.title', 'Filter Attendance')"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppDateTimePicker
            v-model="listPage.stagedFilters.value.date"
            :label="$t('attendance.filter.date')"
            variant="outlined"
            clearable
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.status"
            :label="$t('attendance.filter.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.department_id"
            :label="$t('attendance.exception.filter.department', 'Department')"
            :items="deptOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <AppBottomNav
      v-if="smAndDown"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      :aria-label="$t('attendance.list.navigation', 'Attendance sections')"
    />

    <!-- View Dialog -->
    <WpDetailDialog
      v-model="viewDialog.isOpen.value"
      :title="$t('attendance.view_title', 'Attendance Record')"
      :loading="viewLoading"
      max-width="600"
    >
      <template v-if="viewRecord">
        <VList lines="two">
          <VListItem>
            <VListItemTitle>{{ $t('Employee') }}</VListItemTitle>
            <VListItemSubtitle>{{ viewRecord.employee?.full_name }}</VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle>{{ $t('Date') }}</VListItemTitle>
            <VListItemSubtitle>{{ viewRecord.date }}</VListItemSubtitle>
          </VListItem>
          <VListItem v-if="viewRecord.sessions && viewRecord.sessions.length > 1">
            <VListItemTitle>{{ $t('attendance.col.punches', 'Clock In/Out Sessions') }}</VListItemTitle>
            <VListItemSubtitle class="mt-1">
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="(session, sIdx) in viewRecord.sessions"
                  :key="sIdx"
                  size="small"
                  variant="tonal"
                  color="primary"
                  label
                >
                  #{{ sIdx + 1 }}: {{ session.in || '—' }} → {{ session.out || '—' }}
                </VChip>
              </div>
            </VListItemSubtitle>
          </VListItem>
          <VListItem v-else>
            <VListItemTitle>{{ $t('attendance.col.in') }}</VListItemTitle>
            <VListItemSubtitle>{{ viewRecord.actual_in || '—' }}</VListItemSubtitle>
          </VListItem>
          <VListItem v-if="!viewRecord.sessions || viewRecord.sessions.length <= 1">
            <VListItemTitle>{{ $t('attendance.col.out') }}</VListItemTitle>
            <VListItemSubtitle>{{ viewRecord.actual_out || '—' }}</VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle>{{ $t('attendance.col.hours') }}</VListItemTitle>
            <VListItemSubtitle>{{ formatHours(viewRecord.worked_hours) }}</VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle>{{ $t('Status') }}</VListItemTitle>
            <VListItemSubtitle>
              <WpStatusChip
                type="attendance"
                :status="viewRecord.status"
                :label="statusLabel(viewRecord.status)"
              />
            </VListItemSubtitle>
          </VListItem>
        </VList>
      </template>
    </WpDetailDialog>

    <!-- Correction Dialog -->
    <VDialog
      v-model="corrDialog.isOpen.value"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h6">
          {{ $t('attendance.correction_title', 'Request Correction') }}
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow>
            <VCol cols="6">
              <VTextField
                v-model="corrForm.requested_actual_in"
                :label="$t('attendance.col.in')"
                type="time"
                density="comfortable"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="corrForm.requested_actual_out"
                :label="$t('attendance.col.out')"
                type="time"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="corrForm.reason"
                :label="$t('Reason')"
                :placeholder="$t('attendance.correction_reason_hint', 'Explain why this correction is needed')"
                rows="3"
                density="comfortable"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="corrDialog.close()"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="corrSaving"
            @click="submitCorrection"
          >
            {{ $t('Submit') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Scanner Dialog -->
    <WpQrScannerDialog
      v-model="attendanceScanner.scannerOpenRef.value"
      :title="$t('attendance.scan_badge', 'Scan Badge')"
      @scanned="handleBadgeScan"
    />

    <!-- Scan Result Dialog -->
    <VDialog
      v-model="scanResultOpen"
      max-width="400"
    >
      <VCard v-if="attendanceScanner.resultRef.value">
        <VCardText class="text-center pa-8">
          <VIcon
            :icon="attendanceScanner.resultRef.value.success ? 'tabler-circle-check' : 'tabler-circle-x'"
            :color="attendanceScanner.resultRef.value.success ? 'success' : 'error'"
            size="64"
            class="mb-4"
          />
          <div class="text-h6 mb-1">
            {{ attendanceScanner.resultRef.value.message }}
          </div>
          <div
            v-if="attendanceScanner.resultRef.value.employee?.full_name"
            class="text-body-2 text-medium-emphasis mb-4"
          >
            {{ attendanceScanner.resultRef.value.employee.full_name }}
            <span v-if="attendanceScanner.resultRef.value.employee.employee_number">
              ({{ attendanceScanner.resultRef.value.employee.employee_number }})
            </span>
          </div>
          <VBtn
            color="primary"
            variant="tonal"
            class="mt-2"
            @click="scanResultOpen = false"
          >
            {{ $t('Close') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Manual Punch Dialog -->
    <ManualPunchDialog
      v-model:is-dialog-visible="manualPunchOpen"
      :employees="employees"
      :busy="manualPunchBusy"
      @submit="submitManualPunch"
    />

    <!-- Bulk Punch Dialog -->
    <BulkPunchDialog
      v-model:is-dialog-visible="bulkPunchOpen"
      :employees="employees"
      :departments="hrStore.departments"
      :teams="hrStore.teams"
      :busy="bulkPunchBusy"
      :results="bulkPunchResults"
      @submit="submitBulkPunch"
    />

    <!-- Employee Badge Dialog -->
    <EmployeeBadgeDialog
      v-model:is-dialog-visible="badgeDialogOpen"
      :badge="badgeData"
      :loading="badgeLoading"
    />

    <AppFabSpeedDial
      v-if="smAndDown"
      v-model="isMobileFabOpen"
      class="d-md-none attendance-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.attendance-list__tabs {
  margin-block-start: 0.5rem;
}

.attendance-list__kpis {
  margin-block-end: 0;
}

@media (max-width: 599px) {
  .attendance-list__tab-content {
    padding: 0.75rem;
  }

  .attendance-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}

@media (max-width: 959px) {
  .people-skills-attendance-list {
    padding-block-end: 7rem;
  }

  .attendance-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
