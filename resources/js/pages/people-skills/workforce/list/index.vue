<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpDeleteDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useHrPlannerStore } from '@/stores/useHrPlannerStore'
import ShiftFormDialog from '@/views/people-skills/workforce/ShiftFormDialog.vue'
import WorkforceAvailabilityTab from '@/views/people-skills/workforce/WorkforceAvailabilityTab.vue'
import WorkforcePlanner from '@/views/people-skills/workforce/WorkforcePlanner.vue'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/workforce/list',
  name: 'people-skills-workforce-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'workforce',
  },
})

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const ability = useAbility()
const { showMessage } = useAppSnackbar()
const hrStore = useHrStore()
const plannerStore = useHrPlannerStore()
const canCreate = computed(() => ability.can('create', 'workforce') || ability.can('create', 'PeopleSkills'))

// ── Tabs: Shifts / Schedules ─────────────────────────────────────────────
const activeTab = ref('planner')
const plannerRef = ref(null)
const availabilityRef = ref(null)
const isMobileFabOpen = ref(false)

const workforceTabs = computed(() => [
  { value: 'planner', title: t('workforce.tab_planner', 'Planner'), icon: 'tabler-calendar-week' },
  { value: 'availability', title: t('workforce.tab_availability', 'Availability'), icon: 'tabler-user-search' },
  { value: 'shifts', title: t('workforce.tab_shifts', 'Shifts'), icon: 'tabler-clock-2' },
  { value: 'schedules', title: t('workforce.tab_schedules', 'Schedules'), icon: 'tabler-calendar-month' },
])

const mobileNavItems = computed(() => workforceTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
  badge: tab.badge,
})))

const workforceKpis = computed(() => {
  let scheduled = 0
  let drafts = 0
  let leave = 0
  let hours = 0

  plannerStore.employees.forEach(employee => Object.values(employee.days || {}).forEach(cell => {
    if (cell.schedule) {
      scheduled++
      hours += Number(cell.schedule.scheduled_hours) || 8
      if (cell.schedule.lifecycle_status === 'draft')
        drafts++
    }
    if (cell.leave)
      leave++
  }))

  return [
    { title: t('workforce.kpi.planned_hours', 'Planned hours'), value: Math.round(hours), icon: 'tabler-clock-hour-4', color: 'primary' },
    { title: t('workforce.kpi.scheduled_slots', 'Scheduled slots'), value: scheduled, icon: 'tabler-calendar-check', color: 'success' },
    { title: t('workforce.kpi.approved_leave_slots', 'Approved leave slots'), value: leave, icon: 'tabler-beach', color: 'info' },
    { title: t('workforce.kpi.drafts', 'Draft schedules'), value: drafts, icon: 'tabler-edit', color: 'warning' },
    { title: t('workforce.kpi.shift_types', 'Shift types'), value: hrStore.shifts.length, icon: 'tabler-clock-2', color: 'secondary' },
  ]
})

// ── Shifts ────────────────────────────────────────────────────────────────
const shiftListPage = useListPage({
  fetchFn: () => hrStore.fetchShifts(true).then(data => ({ data })),
  immediate: true,
})

const shiftSearch = ref('')

const filteredShifts = computed(() => {
  const query = shiftSearch.value.trim().toLowerCase()
  if (!query)
    return hrStore.shifts

  return hrStore.shifts.filter(shift => [
    localizedValue(shift.name),
    shift.code,
    shift.start_time,
    shift.end_time,
  ].some(value => String(value || '').toLowerCase().includes(query)))
})

const shiftDialog  = useDialog()

// Backend contract: Shift model uses code + break_duration_minutes (see hr_shifts table).
const slugifyCode = name => localizedValue(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 50)

const shiftForm    = reactive({ name: ensureTranslatable(), code: '', start_time: '', end_time: '', break_duration_minutes: 60, is_overnight: false, is_active: true })
const shiftSaving  = ref(false)
const shiftDelDialog = useDialog()
const shiftDeleting  = ref(false)

const shiftDeleteDescription = computed(() => {
  const shift = hrStore.shifts.find(item => String(item.id) === String(shiftDelDialog.item.value))

  return shift
    ? `${localizedValue(shift.name)}. ${t('workforce.delete_shift_confirm')}`
    : t('workforce.delete_shift_confirm')
})

const openShiftDialog = (shift = null) => {
  Object.assign(shiftForm, {
    name: ensureTranslatable(shift?.name),
    code: shift?.code            || '',
    start_time: shift?.start_time    || '',
    end_time: shift?.end_time      || '',
    break_duration_minutes: shift?.break_duration_minutes ?? 60,
    is_overnight: shift?.is_overnight  ?? false,
    is_active: shift?.is_active      ?? true,
  })
  shiftDialog.open(shift?.id || null)
}

const saveShift = async formData => {
  shiftSaving.value = true
  try {
    // Auto-derive the unique shift code from the name when left blank.
    const payload = {
      ...formData,
      code: String(formData.code || '').trim() || slugifyCode(formData.name),
      break_duration_minutes: formData.break_duration_minutes === '' || formData.break_duration_minutes == null
        ? null
        : Number(formData.break_duration_minutes),
    }

    if (shiftDialog.item.value) {
      await hrStore.updateShift(shiftDialog.item.value, payload)
      showMessage(t('workforce.shift_saved'), 'success')
    } else {
      await hrStore.createShift(payload)
      showMessage(t('workforce.shift_created'), 'success')
    }
    shiftDialog.close()
    shiftListPage.refresh()
  }
  catch (err) {
    showMessage(err?.data?.message || t('workforce.shift_error'), 'error')
  }
  finally { shiftSaving.value = false }
}

const deleteShift = async () => {
  shiftDeleting.value = true
  try {
    await hrStore.deleteShift(shiftDelDialog.item.value)
    showMessage(t('workforce.shift_deleted'), 'success')
    shiftDelDialog.close()
    shiftListPage.refresh()
    
    return true
  }
  catch (err) {
    showMessage(err?.data?.message || t('workforce.shift_delete_error'), 'error')

    return false
  }
  finally { shiftDeleting.value = false }
}

const closeShiftDeleteDialog = value => {
  if (!value)
    shiftDelDialog.close()
}

// ── Schedules ─────────────────────────────────────────────────────────────
const scheduleListPage = useListPage({
  fetchFn: () => hrStore.fetchSchedules().then(data => ({ data })),
  immediate: false,
})

const scheduleSearch = ref('')

const scheduleEmployeeName = schedule => {
  const employee = schedule.employee
  const fullName = localizedValue(employee?.full_name, '')
  if (fullName)
    return fullName

  const name = [employee?.first_name, employee?.middle_name, employee?.last_name]
    .map(value => localizedValue(value, '').trim())
    .filter(Boolean)
    .join(' ')

  return name || employee?.employee_number || '—'
}

const formatScheduleDate = value => {
  if (!value)
    return '—'

  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)

  const date = dateOnly
    ? new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
    : new Date(value)

  return Number.isNaN(date.getTime())
    ? '—'
    : new Intl.DateTimeFormat(locale.value || undefined, { dateStyle: 'medium' }).format(date)
}

const filteredSchedules = computed(() => {
  const query = scheduleSearch.value.trim().toLowerCase()
  if (!query)
    return hrStore.schedules

  return hrStore.schedules.filter(schedule => [
    scheduleEmployeeName(schedule),
    schedule.employee_id,
    schedule.date,
    localizedValue(schedule.shift?.name),
    schedule.status,
  ].some(value => String(value || '').toLowerCase().includes(query)))
})


watch(activeTab, tab => {
  if (tab === 'schedules' && hrStore.schedules.length === 0) {
    scheduleListPage.fetchData()
  }
})

// ── Computed ──────────────────────────────────────────────────────────────
const shiftHeaders = computed(() => [
  { title: t('workforce.col.shift_name'), key: 'name', sortable: true },
  { title: t('workforce.col.start'), key: 'start_time', sortable: false },
  { title: t('workforce.col.end'), key: 'end_time', sortable: false },
  { title: t('workforce.col.break'), key: 'break_duration_minutes', sortable: false },
  { title: t('workforce.col.status'), key: 'is_active', sortable: false, width: '100px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const scheduleHeaders = computed(() => [
  { title: t('workforce.col.employee'), key: 'employee', sortable: false, minWidth: '180px' },
  { title: t('workforce.col.date'), key: 'date', sortable: true },
  { title: t('workforce.col.shift'), key: 'shift', sortable: false },
  { title: t('workforce.col.sch_status'), key: 'status', sortable: false, width: '120px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const getShiftActions = () => [
  { label: t('Edit'),   icon: 'tabler-edit',  emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

const schStatusColor = s => ({
  scheduled: 'info', worked: 'success', missed: 'error', modified: 'warning',
}[s] || 'default')

const mobileFabActions = computed(() => {
  if (activeTab.value === 'planner') {
    const actions = [
      { id: 'bulk-schedule', icon: 'tabler-calendar-plus', title: t('workforce.bulk_schedule', 'Bulk schedule'), onClick: () => plannerRef.value?.openBulk() },
      { id: 'planner-history', icon: 'tabler-history', title: t('workforce.history', 'History'), onClick: () => plannerRef.value?.openHistory() },
      { id: 'planner-filters', icon: 'tabler-filter', title: t('wp.filters.label', 'Filters'), onClick: () => plannerRef.value?.openFilters() },
      { id: 'planner-reload', icon: 'tabler-refresh', title: t('workforce.planner.reload', 'Reload planner'), onClick: () => plannerRef.value?.load() },
    ]

    if (plannerRef.value?.draftCount) {
      actions.unshift({
        id: 'planner-publish',
        icon: 'tabler-upload',
        title: `${t('workforce.planner.publish', 'Publish drafts')} (${plannerRef.value.draftCount})`,
        onClick: () => plannerRef.value?.publish(),
      })
    }

    actions.push({
      id: 'planner-clear',
      icon: 'tabler-trash',
      title: t('workforce.planner.clear_week', 'Clear uncompleted week'),
      onClick: () => plannerRef.value?.clear(),
    })

    return actions
  }
  if (activeTab.value === 'availability')
    return [{ id: 'availability-filters', icon: 'tabler-filter', title: t('wp.filters.label'), onClick: () => availabilityRef.value?.openFilters() }]
  if (activeTab.value === 'shifts')
    return [{
      id: 'add-shift',
      icon: 'tabler-plus',
      title: t('workforce.add_shift'),
      disabled: !canCreate.value,
      onClick: openShiftDialog,
    }]

  return [{
    id: 'bulk-schedule',
    icon: 'tabler-calendar-plus',
    title: t('workforce.bulk_schedule', 'Bulk schedule'),
    onClick: () => {
      activeTab.value = 'planner'
      plannerRef.value?.openBulk()
    },
  }]
})

onMounted(() => hrStore.fetchMeta())
</script>

<template>
  <div class="people-skills-workforce-list d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('workforce.list.title')"
      :subtitle="$t('workforce.list.subtitle')"
      icon="tabler-calendar-stats"
    />

    <WpKpiGroup
      :item-count="workforceKpis.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
      class="workforce-list__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in workforceKpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="plannerStore.isLoading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <AppHybridTabs
      v-model="activeTab"
      :tabs="workforceTabs"
      class="workforce-list__tabs"
      header-class="d-none d-md-block"
      :swipeable="false"
      crossfade
    >
      <template #tab-planner>
        <WorkforcePlanner ref="plannerRef" />
      </template>

      <template #tab-availability>
        <WorkforceAvailabilityTab ref="availabilityRef" />
      </template>

      <template #tab-shifts>
        <WpPageToolbar
          v-model:search="shiftSearch"
          :create-label="canCreate ? $t('workforce.add_shift') : ''"
          hide-create-on-mobile
          @create="openShiftDialog"
          @refresh="shiftListPage.refresh()"
        />

        <WpResponsiveList
          :headers="shiftHeaders"
          :items="filteredShifts"
          :total-items="filteredShifts.length"
          :loading="hrStore.isLoadingMeta"
          hide-pagination
          @retry="shiftListPage.refresh()"
        >
          <template #item.name="{ item }">
            {{ localizedValue(item.name) }}
          </template>
          <template #item.break_duration_minutes="{ item }">
            {{ item.break_duration_minutes ? `${item.break_duration_minutes}m` : '—' }}
          </template>

          <template #item.is_active="{ item }">
            <VChip
              :color="item.is_active ? 'success' : 'secondary'"
              size="small"
              label
            >
              {{ item.is_active ? $t('Active') : $t('Inactive') }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <WpRowActions
              :actions="getShiftActions()"
              @edit="openShiftDialog(item)"
              @delete="shiftDelDialog.open(item.id)"
            />
          </template>

          <template #mobile-card="{ item }">
            <WpMobileCard
              :title="localizedValue(item.name)"
              :subtitle="`${item.start_time} – ${item.end_time}`"
            >
              <template #actions>
                <WpRowActions
                  :actions="getShiftActions()"
                  @edit="openShiftDialog(item)"
                  @delete="shiftDelDialog.open(item.id)"
                />
              </template>
            </WpMobileCard>
          </template>
        </WpResponsiveList>
      </template>

      <template #tab-schedules>
        <WpPageToolbar
          v-model:search="scheduleSearch"
          @refresh="scheduleListPage.refresh()"
        />

        <WpResponsiveList
          v-model:page="scheduleListPage.page.value"
          v-model:items-per-page="scheduleListPage.itemsPerPage.value"
          v-model:sort-by="scheduleListPage.sortBy.value"
          :headers="scheduleHeaders"
          :items="filteredSchedules"
          :total-items="filteredSchedules.length"
          :loading="hrStore.isLoadingMeta"
          @retry="scheduleListPage.refresh()"
        >
          <template #item.employee="{ item }">
            <span class="text-body-2">{{ scheduleEmployeeName(item) }}</span>
          </template>

          <template #item.date="{ item }">
            <span class="text-body-2">{{ formatScheduleDate(item.date) }}</span>
          </template>

          <template #item.shift="{ item }">
            <div
              v-if="item.shift"
              class="d-flex flex-column"
            >
              <span class="text-body-2 font-weight-medium">{{ localizedValue(item.shift.name) }}</span>
              <span class="text-caption text-medium-emphasis">{{ item.shift.start_time }}–{{ item.shift.end_time }}</span>
            </div>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </template>

          <template #item.status="{ item }">
            <VChip
              :color="schStatusColor(item.status)"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.status }}
            </VChip>
          </template>

          <template #item.actions />

          <template #mobile-card="{ item }">
            <WpMobileCard
              :title="scheduleEmployeeName(item)"
              :subtitle="formatScheduleDate(item.date)"
              avatar-icon="tabler-user"
            >
              <template #content>
                <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                  <div
                    v-if="item.shift"
                    class="d-flex flex-column"
                  >
                    <span class="text-body-2 font-weight-medium">{{ localizedValue(item.shift.name) }}</span>
                    <span class="text-caption text-medium-emphasis">{{ item.shift.start_time }}–{{ item.shift.end_time }}</span>
                  </div>
                  <span
                    v-else
                    class="text-body-2 text-disabled"
                  >—</span>
                  <VChip
                    :color="schStatusColor(item.status)"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ item.status }}
                  </VChip>
                </div>
              </template>
            </WpMobileCard>
          </template>
        </WpResponsiveList>
      </template>
    </AppHybridTabs>

    <AppBottomNav
      v-if="smAndDown"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      aria-label="Workforce sections"
    />

    <ShiftFormDialog
      :model-value="shiftDialog.isOpen.value"
      :item-id="shiftDialog.item.value"
      :form="shiftForm"
      :saving="shiftSaving"
      @update:model-value="value => !value && shiftDialog.close()"
      @submit="saveShift"
    />

    <WpDeleteDialog
      v-model="shiftDelDialog.isOpen.value"
      :title="$t('Delete')"
      :description="shiftDeleteDescription"
      :loading="shiftDeleting"
      :confirm-action="deleteShift"
      @update:model-value="closeShiftDeleteDialog"
    />

    <AppFabSpeedDial
      v-if="mobileFabActions.length"
      v-model="isMobileFabOpen"
      class="d-md-none workforce-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.workforce-list__tabs {
  margin-block-start: 0.5rem;
}

.workforce-list__kpis {
  margin-block-end: 0;
}

.workforce-list__tabs :deep(.app-hybrid-tabs__panel) {
  padding: 0;
}

.workforce-list-quick-actions {
  inset-inline-end: 25px;
}

@media (max-width: 959px) {
  .people-skills-workforce-list {
    padding-block-end: 7rem;
  }

  .workforce-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
