<script setup>
import {
  AppFabSpeedDial,
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
import { useEmployeeActions } from '@/composables/people-skills/employees/useEmployeeActions'
import { useEmployeeDialogs } from '@/composables/people-skills/employees/useEmployeeDialogs'
import { useEmployeeList } from '@/composables/people-skills/employees/useEmployeeList'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import AddNewEmployeeDialog from '@/views/people-skills/employees/list/AddNewEmployeeDialog.vue'
import EmployeeStatusDialog from '@/views/people-skills/employees/list/EmployeeStatusDialog.vue'
import ViewEmployeeDialog from '@/views/people-skills/employees/list/ViewEmployeeDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/people-skills/employees/list',
  name: 'people-skills-employees-list',
  alias: ['/people-skills/employees'],
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t, locale } = useI18n()
const router = useRouter()
const ability = useAbility()
const hrStore = useHrStore()
const isMobileFabOpen = ref(false)

// ── Employee List Orchestration ─────────────────────────────────────────────
const {
  employees,
  totalItems,
  loading,
  error,
  search,
  filters,
  stagedFilters,
  activeFilters,
  activeFilterCount,
  setFilter,
  removeFilter,
  resetFilters,
  stageFilters,
  applyStaged,
  resetStaged,
  page,
  itemsPerPage,
  sortBy,
  refresh,

  headers,
  statusOptions,
  getStatusTitle,
  resolveStatusColor,
  resolveStatusLabel,

  selectedEmployees,
  clearSelection,

  kpiCards,
  statsLoading,
  handleKpiClick,
  fetchStatistics,
} = useEmployeeList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useEmployeeDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useEmployeeActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteEmployee = () => actions.deleteEmployee(dialogs.deleteDialog.item.value)

// ── Filter Dialog ────────────────────────────────────────────────────────────
const isFilterDialogOpen = ref(false)

const openFilterDialog = () => {
  stageFilters()
  isFilterDialogOpen.value = true
}

const handleApplyFilters = () => {
  applyStaged()
  isFilterDialogOpen.value = false
}

const handleResetFilters = () => {
  resetStaged()
  applyStaged()
  isFilterDialogOpen.value = false
}

// ── Department options for filter ────────────────────────────────────────────
const departmentOptions = computed(() =>
  hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
)

const availablePositionOptions = computed(() =>
  hrStore.positions
    .filter(position => !stagedFilters.department_id || position.department_id === stagedFilters.department_id)
    .map(position => ({ title: localizedValue(position.title), value: position.id })),
)

const availableTeamOptions = computed(() =>
  hrStore.teams
    .filter(team => !stagedFilters.department_id || team.department_id === stagedFilters.department_id)
    .map(team => ({ title: localizedValue(team.name), value: team.id })),
)

const getEmployeeName = item => {
  const localizedName = [localizedValue(item?.first_name), localizedValue(item?.last_name)]
    .filter(Boolean)
    .join(' ')

  return localizedName || localizedValue(item?.full_name, '—')
}

const formatHireDate = date => new Intl.DateTimeFormat(locale.value || undefined, {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
}).format(new Date(date))

const selectedEmployeeEmails = computed(() =>
  employees.value
    .filter(employee => selectedEmployees.value.includes(employee.id))
    .map(employee => employee.work_email)
    .filter(Boolean),
)

const emailSelectedEmployees = () => {
  const recipients = [...new Set(selectedEmployeeEmails.value)]
  if (recipients.length)
    window.location.href = `mailto:${recipients.map(encodeURIComponent).join(',')}`
}

const mobileFabActions = computed(() => [
  {
    id: 'add-employee',
    icon: 'tabler-user-plus',
    title: t('employees.list.add_employee'),
    disabled: !ability.can('create', 'PeopleSkills'),
    onClick: () => dialogs.addDialog.open(),
  },
  {
    id: 'print-employees',
    icon: 'tabler-printer',
    title: t('employees.list.actions.print'),
    onClick: () => window.print(),
  },
  {
    id: 'filter-employees',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
  {
    id: 'email-employees',
    icon: 'tabler-mail',
    title: t('employees.list.actions.email'),
    disabled: selectedEmployeeEmails.value.length === 0,
    onClick: emailSelectedEmployees,
  },
])

// ── Row Actions ───────────────────────────────────────────────────────────────
const getRowActions = item => {
  const status = item?.status
  const statusActions = []

  if (status === 'active') {
    statusActions.push(
      { label: t('employees.actions.suspend'), icon: 'tabler-user-pause', emit: 'suspend', color: 'warning' },
      { label: t('employees.actions.terminate'), icon: 'tabler-user-x', emit: 'terminate', color: 'error', dividerBefore: true },
    )
  } else if (status === 'suspended') {
    statusActions.push(
      { label: t('employees.actions.activate'), icon: 'tabler-user-check', emit: 'activate', color: 'success' },
      { label: t('employees.actions.terminate'), icon: 'tabler-user-x', emit: 'terminate', color: 'error', dividerBefore: true },
    )
  } else if (status === 'on_leave') {
    statusActions.push(
      { label: t('employees.actions.activate'), icon: 'tabler-user-check', emit: 'activate', color: 'success' },
      { label: t('employees.actions.suspend'), icon: 'tabler-user-pause', emit: 'suspend', color: 'warning' },
      { label: t('employees.actions.terminate'), icon: 'tabler-user-x', emit: 'terminate', color: 'error', dividerBefore: true },
    )
  } else {
    statusActions.push(
      { label: t('employees.actions.activate'), icon: 'tabler-user-check', emit: 'activate', color: 'success' },
    )
  }

  return [
    { label: t('View'), icon: 'tabler-eye', emit: 'view' },
    { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
    ...statusActions,
    { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
  ]
}

// ── Status handler from dialogs ───────────────────────────────────────────────
const handleStatusChange = async ({ status, reason, effectiveDate }) => {
  const employeeId = dialogs.statusDialog.item.value
  const success = await actions.changeStatus(employeeId, status, reason, effectiveDate)

  if (success) dialogs.statusDialog.close()
}

// ── Load HR meta for department filter dropdown ───────────────────────────────
onMounted(() => hrStore.fetchMeta())
</script>

<template>
  <div class="people-skills-employees-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('employees.list.title')"
      :subtitle="$t('employees.list.subtitle')"
      icon="tabler-address-book"
    />

    <!-- 👉 KPI Cards -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.titleKey"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="$t(kpi.titleKey)"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.iconColor"
            :loading="statsLoading"
            clickable
            :active="kpi.filterValue !== null ? filters.status === kpi.filterValue : !filters.status"
            @click="handleKpiClick(kpi.filterValue)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card -->
    <VCard>
      <!-- Toolbar -->
      <WpPageToolbar
        v-model:search="search"
        class="employees-list-toolbar"
        :create-label="$can('create', 'PeopleSkills') ? $t('employees.list.add_employee') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="dialogs.addDialog.open()"
        @refresh="() => { refresh(); fetchStatistics() }"
        @open-filters="openFilterDialog"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterCount > 0"
            :filters="activeFilters"
            :label-map="{
              status: $t('employees.list.filter.status'),
              department_id: $t('employees.list.filter.department'),
              position_id: $t('employees.list.col.position'),
              team_id: $t('employees.edit.fields.team'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              department_id: val => departmentOptions.find(d => d.value === val)?.title || val,
              position_id: val => localizedValue(hrStore.positions.find(p => p.id === val)?.title, val),
              team_id: val => localizedValue(hrStore.teams.find(team => team.id === val)?.name, val),
            }"
            @remove="removeFilter($event.key)"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- Responsive List -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedEmployees"
        :headers="headers"
        :items="employees"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        show-select
        item-value="id"
        @retry="refresh"
      >
        <!-- ── Desktop Column: Employee identity ── -->
        <template #item.employee="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              :color="resolveStatusColor(item.status)"
              variant="tonal"
            >
              <VImg
                v-if="item.avatar_url"
                :src="item.avatar_url"
              />
              <span
                v-else
                class="font-weight-medium text-body-2"
              >
                {{ avatarText(getEmployeeName(item)) }}
              </span>
            </VAvatar>
            <div class="d-flex flex-column">
              <RouterLink
                :to="{ name: 'people-skills-employees-edit-id', params: { id: item.id } }"
                class="text-base font-weight-medium text-link text-truncate"
                style="max-inline-size: 220px;"
              >
                {{ getEmployeeName(item) }}
              </RouterLink>
              <span class="text-caption text-medium-emphasis d-flex align-center gap-1">
                #{{ item.employee_number }}
              </span>
            </div>
          </div>
        </template>

        <!-- Department column -->
        <template #item.department="{ item }">
          <span
            v-if="localizedValue(item.department?.name)"
            class="text-body-2"
          >{{ localizedValue(item.department.name) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Position column -->
        <template #item.position="{ item }">
          <span
            v-if="localizedValue(item.position?.title)"
            class="text-body-2"
          >{{ localizedValue(item.position.title) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Contact column -->
        <template #item.contact="{ item }">
          <div class="d-flex flex-column gap-1 py-1">
            <div
              v-if="item.work_email"
              class="d-flex align-center gap-1"
            >
              <VIcon
                icon="tabler-mail"
                size="12"
                color="primary"
              />
              <span
                class="text-caption text-truncate"
                style="max-inline-size: 180px;"
              >{{ item.work_email }}</span>
            </div>
            <div
              v-if="item.work_phone"
              class="d-flex align-center gap-1"
            >
              <VIcon
                icon="tabler-phone"
                size="12"
                color="success"
              />
              <span class="text-caption">{{ item.work_phone }}</span>
            </div>
          </div>
        </template>

        <!-- Hire Date column -->
        <template #item.hire_date="{ item }">
          <span
            v-if="item.hire_date"
            class="text-body-2"
          >
            {{ formatHireDate(item.hire_date) }}
          </span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Status column -->
        <template #item.status="{ item }">
          <WpStatusChip
            type="employee"
            :status="item.status"
            :label="resolveStatusLabel(item.status)"
          />
        </template>

        <!-- Row Actions -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions(item)"
            @view="dialogs.openView(item.id)"
            @edit="router.push({ name: 'people-skills-employees-edit-id', params: { id: item.id } })"
            @activate="dialogs.openStatusChange(item.id, 'activate')"
            @suspend="dialogs.openStatusChange(item.id, 'suspend')"
            @terminate="dialogs.openStatusChange(item.id, 'terminate')"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="employee-mobile-card"
            :title="getEmployeeName(item)"
            :subtitle="`#${item.employee_number || '—'}`"
            :status="resolveStatusLabel(item.status)"
            :status-color="resolveStatusColor(item.status)"
            show-select
            :selected="selected"
            clickable
            @update:selected="updateSelected"
            @click="dialogs.openView(item.id)"
          >
            <template #avatar>
              <VAvatar
                size="42"
                :color="resolveStatusColor(item.status)"
                variant="tonal"
              >
                <VImg
                  v-if="item.avatar_url"
                  :src="item.avatar_url"
                />
                <span
                  v-else
                  class="font-weight-medium text-body-2"
                >
                  {{ avatarText(getEmployeeName(item)) }}
                </span>
              </VAvatar>
            </template>

            <template #badges>
              <VChip
                v-if="localizedValue(item.department?.name)"
                size="x-small"
                variant="tonal"
                color="secondary"
                label
              >
                {{ localizedValue(item.department.name) }}
              </VChip>
              <VChip
                v-if="localizedValue(item.position?.title)"
                size="x-small"
                variant="outlined"
              >
                {{ localizedValue(item.position.title) }}
              </VChip>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="dialogs.openView(item.id)"
                @edit="router.push({ name: 'people-skills-employees-edit-id', params: { id: item.id } })"
                @activate="dialogs.openStatusChange(item.id, 'activate')"
                @suspend="dialogs.openStatusChange(item.id, 'suspend')"
                @terminate="dialogs.openStatusChange(item.id, 'terminate')"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #details>
              <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                <div
                  v-if="item.work_email"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-mail"
                    color="primary"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.email', 'Email') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ item.work_email }}</span>
                </div>
                <div
                  v-if="item.work_phone"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-phone"
                    color="success"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.phone', 'Phone') }}</span>
                  <span class="wp-mobile-row__value">{{ item.work_phone }}</span>
                </div>
              </div>
            </template>

            <template
              v-if="item.hire_date"
              #footer
            >
              <span class="text-caption text-medium-emphasis">
                {{ $t('employees.list.col.hire_date') }}
              </span>
              <span class="text-caption font-weight-medium">
                {{ formatHireDate(item.hire_date) }}
              </span>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Filter Dialog -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="activeFilterCount"
      :title="$t('employees.list.filter.title')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('employees.list.filter.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.department_id"
            :label="$t('employees.list.filter.department')"
            :items="departmentOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.position_id"
            :label="$t('employees.list.col.position')"
            :items="availablePositionOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.team_id"
            :label="$t('employees.edit.fields.team')"
            :items="availableTeamOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Employee Dialog -->
    <ViewEmployeeDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :employee="dialogs.viewEmployee.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @edit="router.push({ name: 'people-skills-employees-edit-id', params: { id: $event.id } })"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Add New Employee Dialog -->
    <AddNewEmployeeDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      :loading="actions.isCreating.value"
      @add-employee="actions.createEmployee"
    />

    <!-- 👉 Delete Confirmation -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('employees.delete.title')"
      :description="$t('employees.delete.description')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteEmployee"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <!-- 👉 Status Change Dialog -->
    <EmployeeStatusDialog
      v-model:is-dialog-visible="dialogs.statusDialog.isOpen.value"
      :action="dialogs.statusAction.value"
      :loading="actions.isChangingStatus.value"
      @change-status="handleStatusChange"
    />
    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none employees-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.employees-list-quick-actions {
  inset-inline-end: 25px;
}

@media print {
  .employees-list-toolbar,
  .employees-list-quick-actions {
    display: none !important;
  }
}
</style>
