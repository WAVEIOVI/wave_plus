<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpActiveFilters,
  WpDeleteDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useLeaveRequestStore } from '@/stores/useLeaveRequestStore'
import ApproveLeaveDialog from '@/views/people-skills/time-off/ApproveLeaveDialog.vue'
import LeaveRequestFormDialog from '@/views/people-skills/time-off/LeaveRequestFormDialog.vue'
import RejectLeaveDialog from '@/views/people-skills/time-off/RejectLeaveDialog.vue'
import TimeOffBalancesTab from '@/views/people-skills/time-off/TimeOffBalancesTab.vue'
import TimeOffCalendarTab from '@/views/people-skills/time-off/TimeOffCalendarTab.vue'
import TimeOffConfigurationTab from '@/views/people-skills/time-off/TimeOffConfigurationTab.vue'
import ViewLeaveRequestDialog from '@/views/people-skills/time-off/ViewLeaveRequestDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/time-off/list',
  name: 'people-skills-time-off-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'leave',
  },
})

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const route = useRoute()
const router = useRouter()
const ability = useAbility()
const { showMessage } = useAppSnackbar()
const leaveStore = useLeaveRequestStore()
const hrStore = useHrStore()

const canCreate = computed(() => ability.can('create', 'leave') || ability.can('request', 'leave') || ability.can('manage', 'leave') || ability.can('create', 'PeopleSkills'))
const canApprove = computed(() => ability.can('approve', 'leave') || ability.can('manage', 'leave') || ability.can('create', 'PeopleSkills'))
const canManage = computed(() => ability.can('manage', 'leave') || ability.can('create', 'PeopleSkills'))

// ── Tabs Navigation ──────────────────────────────────────────────────────────
const initialTab = ['calendar', 'balances', 'config'].includes(route.query.tab) ? route.query.tab : 'requests'
const activeTab = ref(initialTab)
const isMobileFabOpen = ref(false)
const isFilterDialogOpen = ref(false)

const hybridTabs = computed(() => {
  const tabs = [
    {
      value: 'requests',
      title: t('time_off.tabs.requests'),
      icon: 'tabler-list-details',
      badge: leaveStore.stats?.pending_approval || undefined,
      color: 'warning',
    },
    {
      value: 'calendar',
      title: t('time_off.tabs.calendar'),
      icon: 'tabler-calendar',
    },
    {
      value: 'balances',
      title: t('time_off.tabs.balances'),
      icon: 'tabler-wallet',
    },
  ]

  if (canManage.value) {
    tabs.push({
      value: 'config',
      title: t('time_off.tabs.config', 'Configuration'),
      icon: 'tabler-settings',
    })
  }

  return tabs
})

const mobileNavItems = computed(() => hybridTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
  badge: tab.badge,
})))

watch(activeTab, tab => {
  if (route.query.tab !== tab) {
    router.replace({ query: { ...route.query, tab } })
  }
})

// ── List Page Orchestrator ──────────────────────────────────────────────────
const listPage = useListPage({
  fetchFn: params => leaveStore.fetchList(params),
  initialFilters: {
    status: null,
    leave_type_id: null,
    department_id: null,
    employee_id: null,
    start_date_from: null,
    start_date_to: null,
  },
  initialSortKey: 'start_date',
  initialSortOrder: 'desc',
  sortFieldMap: {
    employee: 'employee_id',
    leave_type: 'leave_type_id',
    from: 'start_date',
    to: 'end_date',
    days: 'total_days',
  },
  immediate: true,
})

// ── KPI Cards ───────────────────────────────────────────────────────────────
const kpiCards = computed(() => {
  const stats = leaveStore.stats || {}

  return [
    {
      title: t('time_off.kpi.pending'),
      value: stats.pending_approval ?? '—',
      icon: 'tabler-clock-pause',
      color: 'warning',
    },
    {
      title: t('time_off.kpi.approved'),
      value: stats.approved ?? '—',
      icon: 'tabler-circle-check',
      color: 'success',
    },
    {
      title: t('time_off.kpi.on_leave_today'),
      value: stats.on_leave_today ?? '—',
      icon: 'tabler-beach',
      color: 'info',
    },
    {
      title: t('time_off.kpi.days_taken'),
      value: stats.total_days_approved != null ? `${stats.total_days_approved}d` : '—',
      icon: 'tabler-calendar-event',
      color: 'primary',
    },
    {
      title: t('time_off.kpi.total'),
      value: stats.total ?? leaveStore.totalItems ?? '—',
      icon: 'tabler-calendar-stats',
      color: 'secondary',
    },
  ]
})

// ── Filter Options ──────────────────────────────────────────────────────────
const statusOptions = computed(() => [
  { title: t('time_off.status.submitted'), value: 'submitted' },
  { title: t('time_off.status.approved'), value: 'approved' },
  { title: t('time_off.status.rejected'), value: 'rejected' },
  { title: t('time_off.status.cancelled'), value: 'cancelled' },
])

const leaveTypeOptions = computed(() =>
  hrStore.leaveTypes.map(lt => ({ title: localizedValue(lt.name), value: lt.id })),
)

const departmentOptions = computed(() =>
  hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
)

const employees = ref([])

const loadEmployees = async () => {
  if (employees.value.length > 0) return
  try {
    const res = await leaveStore.fetchList({ per_page: 1 })

    // employees loaded from hrStore or API if needed
  } catch (e) {
    console.warn(e)
  }
}

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

// ── Table Headers ───────────────────────────────────────────────────────────
const headers = computed(() => [
  { title: t('time_off.col.reference'), key: 'reference', sortable: true, width: '110px' },
  { title: t('time_off.col.employee'), key: 'employee', sortable: false, minWidth: '200px' },
  { title: t('time_off.col.type'), key: 'leave_type', sortable: false, minWidth: '130px' },
  { title: t('time_off.col.from'), key: 'start_date', sortable: true, width: '130px' },
  { title: t('time_off.col.to'), key: 'end_date', sortable: true, width: '130px' },
  { title: t('time_off.col.duration'), key: 'total_days', sortable: true, width: '90px' },
  { title: t('time_off.col.status'), key: 'status', sortable: true, width: '130px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

// ── Row Actions ─────────────────────────────────────────────────────────────
const getRowActions = item => {
  const acts = [{ label: t('View'), icon: 'tabler-eye', emit: 'view' }]

  if (['submitted', 'pending'].includes(item.status) && canApprove.value) {
    acts.push(
      { label: t('time_off.approve'), icon: 'tabler-check', emit: 'approve', color: 'success', dividerBefore: true },
      { label: t('time_off.reject'), icon: 'tabler-x', emit: 'reject', color: 'error' },
    )
  }
  if (['submitted', 'pending', 'approved'].includes(item.status)) {
    acts.push({ label: t('time_off.cancel'), icon: 'tabler-ban', emit: 'cancel', color: 'warning' })
  }

  return acts
}

// ── Component Refs ──────────────────────────────────────────────────────────
const balancesRef = ref(null)
const configRef = ref(null)

// ── Mobile Speed Dial ───────────────────────────────────────────────────────
const mobileFabActions = computed(() => {
  const actions = []

  if (activeTab.value === 'requests') {
    if (canCreate.value) {
      actions.push({
        id: 'create-leave',
        icon: 'tabler-calendar-plus',
        title: t('time_off.add', 'New Request'),
        onClick: openCreateDialog,
      })
    }
    actions.push({
      id: 'filter-leaves',
      icon: 'tabler-filter',
      title: t('time_off.filter.title', 'Filters'),
      onClick: openFilterDialog,
    })
  } else if (activeTab.value === 'balances') {
    actions.push({
      id: 'filter-balances',
      icon: 'tabler-filter',
      title: t('wp.filters.label', 'Filters'),
      onClick: () => balancesRef.value?.openFilters(),
    })
  } else if (activeTab.value === 'config' && canCreate.value) {
    const sub = configRef.value?.currentConfigTab
    if (sub === 'leave-types') {
      actions.push({
        id: 'add-type',
        icon: 'tabler-plus',
        title: t('time_off.add_type', 'New Leave Type'),
        onClick: () => configRef.value?.openType(),
      })
    } else if (sub === 'leave-policies') {
      actions.push({
        id: 'add-policy',
        icon: 'tabler-plus',
        title: t('time_off.add_policy', 'New Accrual Policy'),
        onClick: () => configRef.value?.openPolicy(),
      })
    } else if (sub === 'public-holidays') {
      actions.push({
        id: 'add-holiday',
        icon: 'tabler-plus',
        title: t('time_off.add_holiday', 'New Public Holiday'),
        onClick: () => configRef.value?.openHoliday(),
      })
    }
  }

  actions.push({
    id: 'refresh-leaves',
    icon: 'tabler-refresh',
    title: t('Refresh', 'Refresh'),
    onClick: () => {
      if (activeTab.value === 'requests') listPage.refresh()
      else if (activeTab.value === 'balances') balancesRef.value?.loadBalances()
      else if (activeTab.value === 'config') configRef.value?.loadAllConfig()
    },
  })

  return actions
})

// ── View Dialog ─────────────────────────────────────────────────────────────
const viewDialog = useDialog()
const viewRecord = ref(null)
const viewLoading = ref(false)

const openView = async id => {
  viewRecord.value = null
  viewLoading.value = true
  viewDialog.open(id)
  try {
    viewRecord.value = await leaveStore.fetchOne(id)
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.load_error'), 'error')
    viewDialog.close()
  } finally {
    viewLoading.value = false
  }
}

// ── Create Dialog ───────────────────────────────────────────────────────────
const isCreateDialogOpen = ref(false)
const editingRequest = ref(null)

const openCreateDialog = () => {
  editingRequest.value = null
  isCreateDialogOpen.value = true
}

const openCreateFromBalance = balanceItem => {
  editingRequest.value = {
    employee_id: balanceItem.employee_id,
    leave_type_id: balanceItem.leave_type_id,
  }
  isCreateDialogOpen.value = true
}

const onLeaveSaved = () => {
  listPage.refresh()
}

// ── Approve / Reject / Cancel ───────────────────────────────────────────────
const approveDialog = useDialog()
const approveLoading = ref(false)
const rejectDialog = useDialog()
const rejectLoading = ref(false)
const cancelDialog = useDialog()
const cancelLoading = ref(false)
const pendingActionId = ref(null)

const openApprove = id => {
  pendingActionId.value = id
  approveDialog.open(id)
}

const handleApproveConfirm = async decisionNote => {
  approveLoading.value = true
  try {
    await leaveStore.approve(pendingActionId.value, decisionNote)
    showMessage(t('time_off.approve_success'), 'success')
    approveDialog.close()
    viewDialog.close()
    listPage.refresh()
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.approve_error'), 'error')
  } finally {
    approveLoading.value = false
  }
}

const openReject = id => {
  pendingActionId.value = id
  rejectDialog.open(id)
}

const handleRejectConfirm = async reason => {
  rejectLoading.value = true
  try {
    await leaveStore.reject(pendingActionId.value, reason)
    showMessage(t('time_off.reject_success'), 'success')
    rejectDialog.close()
    viewDialog.close()
    listPage.refresh()
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.reject_error'), 'error')
  } finally {
    rejectLoading.value = false
  }
}

const openCancel = id => {
  pendingActionId.value = id
  cancelDialog.open(id)
}

const handleCancelConfirm = async () => {
  cancelLoading.value = true
  try {
    await leaveStore.cancel(pendingActionId.value)
    showMessage(t('time_off.cancel_success'), 'success')
    cancelDialog.close()
    viewDialog.close()
    listPage.refresh()
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.cancel_error'), 'error')
  } finally {
    cancelLoading.value = false
  }
}

// ── Formatters & Helpers ────────────────────────────────────────────────────
const formatDate = d => d
  ? new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(d))
  : '—'

const statusLabel = s => {
  if (!s) return '—'

  const map = {
    pending: t('time_off.status.pending'),
    submitted: t('time_off.status.submitted'),
    approved: t('time_off.status.approved'),
    rejected: t('time_off.status.rejected'),
    cancelled: t('time_off.status.cancelled'),
    draft: t('time_off.status.draft'),
    taken: t('time_off.status.taken'),
  }

  return map[s] || t(`time_off.status.${s}`, s.replaceAll('_', ' '))
}

onMounted(() => {
  hrStore.fetchMeta()
  hrStore.fetchLeaveTypes()
  loadEmployees()
})
</script>

<template>
  <div class="people-skills-time-off-list d-flex flex-column gap-6">
    <!-- Page Header -->
    <WpPageHeader
      :title="$t('time_off.list.title')"
      :subtitle="$t('time_off.list.subtitle')"
      icon="tabler-calendar-time"
    >
      <template #actions>
        <VBtn
          v-if="canCreate"
          class="d-none d-md-inline-flex"
          color="primary"
          prepend-icon="tabler-calendar-plus"
          @click="openCreateDialog"
        >
          {{ $t('time_off.add') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- KPI Metric Cards -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
      class="time-off-list__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="leaveStore.loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- Hybrid Tabs Navigation -->
    <AppHybridTabs
      v-model="activeTab"
      :tabs="hybridTabs"
      class="time-off-list__tabs"
      header-class="d-none d-md-block"
      :swipeable="false"
      crossfade
    >
      <!-- ── Tab: Requests ────────────────────────────────────────────────── -->
      <template #tab-requests>
        <VCard elevation="1">
          <!-- Toolbar with Search and Filters -->
          <WpPageToolbar
            v-model:search="listPage.search.value"
            :create-label="canCreate ? $t('time_off.add') : ''"
            hide-create-on-mobile
            show-filter
            hide-filter-on-mobile
            :active-filter-count="listPage.activeFilterCount.value"
            :loading="leaveStore.loading"
            @create="openCreateDialog"
            @refresh="listPage.refresh()"
            @open-filters="openFilterDialog"
          >
            <!-- Active Filters Chips Bar -->
            <template #filter-chips>
              <WpActiveFilters
                v-if="listPage.activeFilterCount.value > 0"
                :filters="listPage.activeFilters.value"
                :label-map="{
                  status: $t('time_off.filter.status'),
                  leave_type_id: $t('time_off.filter.type'),
                  department_id: $t('time_off.filter.department'),
                  start_date_from: $t('time_off.filter.from_date'),
                  start_date_to: $t('time_off.filter.to_date'),
                }"
                :value-formatters="{
                  status: statusLabel,
                  leave_type_id: val => leaveTypeOptions.find(o => String(o.value) === String(val))?.title || val,
                  department_id: val => departmentOptions.find(o => String(o.value) === String(val))?.title || val,
                  start_date_from: formatDate,
                  start_date_to: formatDate,
                }"
                @remove="listPage.removeFilter($event.key)"
                @clear-all="listPage.resetFilters"
              />
            </template>
          </WpPageToolbar>

          <!-- Responsive Data Table & Mobile Cards -->
          <WpResponsiveList
            v-model:page="listPage.page.value"
            v-model:items-per-page="listPage.itemsPerPage.value"
            v-model:sort-by="listPage.sortBy.value"
            :headers="headers"
            :items="leaveStore.list"
            :total-items="leaveStore.totalItems"
            :loading="leaveStore.loading"
            :error="leaveStore.error"
            @retry="listPage.refresh()"
          >
            <!-- Reference Column -->
            <template #item.reference="{ item }">
              <span
                class="font-weight-medium text-body-2 cursor-pointer text-primary"
                @click="openView(item.id)"
              >
                {{ item.reference || '—' }}
              </span>
            </template>

            <!-- Employee Column -->
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
                    class="font-weight-medium text-caption"
                  >
                    {{ avatarText(item.employee?.full_name || '?') }}
                  </span>
                </VAvatar>
                <div>
                  <div
                    class="text-body-2 font-weight-medium cursor-pointer"
                    @click="openView(item.id)"
                  >
                    {{ item.employee?.full_name || '—' }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    #{{ item.employee?.employee_number || '—' }}
                    <span v-if="item.employee?.department">
                      · {{ localizedValue(item.employee.department) }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <!-- Leave Type Column -->
            <template #item.leave_type="{ item }">
              <VChip
                size="small"
                variant="tonal"
                color="primary"
                class="font-weight-medium"
              >
                {{ localizedValue(item.leave_type?.name || leaveTypeOptions.find(l => l.value === item.leave_type_id)?.title || '—') }}
              </VChip>
            </template>

            <!-- Start Date Column -->
            <template #item.start_date="{ item }">
              <div class="d-flex align-center gap-1">
                <VIcon
                  icon="tabler-calendar"
                  size="15"
                  class="text-disabled"
                />
                <span class="text-body-2">{{ formatDate(item.start_date) }}</span>
              </div>
            </template>

            <!-- End Date Column -->
            <template #item.end_date="{ item }">
              <div class="d-flex align-center gap-1">
                <VIcon
                  icon="tabler-calendar"
                  size="15"
                  class="text-disabled"
                />
                <span class="text-body-2">{{ formatDate(item.end_date) }}</span>
              </div>
            </template>

            <!-- Total Days Column -->
            <template #item.total_days="{ item }">
              <VChip
                size="small"
                color="secondary"
                variant="tonal"
                class="font-weight-medium"
              >
                {{ item.total_days }}d
              </VChip>
            </template>

            <!-- Status Column -->
            <template #item.status="{ item }">
              <WpStatusChip
                type="leave"
                :status="item.status"
                :label="statusLabel(item.status)"
                show-icon
              />
            </template>

            <!-- Row Actions Column -->
            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getRowActions(item)"
                @view="openView(item.id)"
                @approve="openApprove(item.id)"
                @reject="openReject(item.id)"
                @cancel="openCancel(item.id)"
              />
            </template>

            <!-- Mobile Card Layout -->
            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="item.employee?.full_name || '—'"
                :subtitle="`${localizedValue(item.leave_type?.name || '—')} · ${formatDate(item.start_date)} → ${formatDate(item.end_date)}`"
                @click="openView(item.id)"
              >
                <template #avatar>
                  <VAvatar
                    size="38"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="item.employee?.avatar_url"
                      :src="item.employee.avatar_url"
                    />
                    <span
                      v-else
                      class="text-caption font-weight-medium"
                    >
                      {{ avatarText(item.employee?.full_name || '?') }}
                    </span>
                  </VAvatar>
                </template>

                <template #actions>
                  <div class="d-flex align-center gap-2">
                    <VChip
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ item.total_days }}d
                    </VChip>
                    <WpStatusChip
                      type="leave"
                      :status="item.status"
                      :label="statusLabel(item.status)"
                    />
                    <WpRowActions
                      :actions="getRowActions(item)"
                      @view="openView(item.id)"
                      @approve="openApprove(item.id)"
                      @reject="openReject(item.id)"
                      @cancel="openCancel(item.id)"
                    />
                  </div>
                </template>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── Tab: Calendar ────────────────────────────────────────────────── -->
      <template #tab-calendar>
        <TimeOffCalendarTab
          @view="openView"
          @create="openCreateDialog"
        />
      </template>

      <!-- ── Tab: Balances ────────────────────────────────────────────────── -->
      <template #tab-balances>
        <TimeOffBalancesTab
          ref="balancesRef"
          @create-request="openCreateFromBalance"
        />
      </template>

      <!-- ── Tab: Configuration ───────────────────────────────────────────── -->
      <template #tab-config>
        <TimeOffConfigurationTab ref="configRef" />
      </template>
    </AppHybridTabs>

    <!-- Mobile Devices Bottom Navigation -->
    <AppBottomNav
      v-if="smAndDown"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      :aria-label="$t('time_off.tabs.navigation')"
    />

    <!-- Mobile Speed Dial -->
    <AppFabSpeedDial
      v-if="smAndDown && mobileFabActions.length"
      v-model="isMobileFabOpen"
      class="d-md-none time-off-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />

    <!-- Filter Dialog (Standard Drawer / Modal) -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="listPage.activeFilterCount.value"
      :title="$t('time_off.filter.title')"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.status"
            :label="$t('time_off.filter.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.leave_type_id"
            :label="$t('time_off.filter.type')"
            :items="leaveTypeOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.department_id"
            :label="$t('time_off.filter.department')"
            :items="departmentOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppDateTimePicker
            v-model="listPage.stagedFilters.start_date_from"
            :label="$t('time_off.filter.from_date')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppDateTimePicker
            v-model="listPage.stagedFilters.start_date_to"
            :label="$t('time_off.filter.to_date')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- View Detail Dialog -->
    <ViewLeaveRequestDialog
      v-model="viewDialog.isOpen.value"
      :leave-request="viewRecord"
      :loading="viewLoading"
      :can-approve="canApprove"
      @approve="openApprove"
      @reject="openReject"
      @cancel="openCancel"
    />

    <!-- Create Request Dialog -->
    <LeaveRequestFormDialog
      v-model="isCreateDialogOpen"
      :leave-request="editingRequest"
      @saved="onLeaveSaved"
    />

    <!-- Approve Decision Dialog -->
    <ApproveLeaveDialog
      v-model="approveDialog.isOpen.value"
      :loading="approveLoading"
      @confirm="handleApproveConfirm"
    />

    <!-- Reject Reason Dialog -->
    <RejectLeaveDialog
      v-model="rejectDialog.isOpen.value"
      :loading="rejectLoading"
      @confirm="handleRejectConfirm"
    />

    <!-- Cancel Confirmation Dialog -->
    <WpDeleteDialog
      v-model="cancelDialog.isOpen.value"
      :title="$t('time_off.cancel')"
      :description="$t('time_off.cancel_confirm')"
      :loading="cancelLoading"
      :confirm-action="handleCancelConfirm"
      @update:model-value="cancelDialog.close()"
    />
  </div>
</template>

<style scoped>
.time-off-list__tabs {
  margin-block-start: 0.5rem;
}

.time-off-list__kpis {
  margin-block-end: 0;
}

.time-off-list-quick-actions {
  inset-inline-end: 25px;
}

@media (max-width: 959px) {
  .people-skills-time-off-list {
    padding-block-end: 7rem;
  }

  .time-off-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
