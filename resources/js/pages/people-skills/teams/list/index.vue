<script setup>
/* eslint-disable camelcase */
import {
  AppFabSpeedDial,
  WpActiveFilters,
  WpDeleteDialog,
  WpEntityCard,
  WpEntityCollection,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { useWorkTeamStore } from '@/stores/useWorkTeamStore'
import TeamFormDialog from '@/views/people-skills/teams/list/TeamFormDialog.vue'
import ViewTeamDialog from '@/views/people-skills/teams/list/ViewTeamDialog.vue'
import { computed, getCurrentInstance, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/teams/list',
  name: 'people-skills-teams-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t } = useI18n()
const { proxy } = getCurrentInstance()
const { showMessage } = useAppSnackbar()
const teamStore = useWorkTeamStore()
const hrStore = useHrStore()
const employees = useEmployeeStore()

// ── List ─────────────────────────────────────────────────────────────────────
const listPage = useListPage({
  fetchFn: params => teamStore.fetchList(params),
  initialFilters: { status: null, team_type: null, department_id: null, has_leader: null },
  initialSortKey: 'name',
  initialSortOrder: 'asc',
  immediate: true,
})

// ── View Mode: Table / Grid toggle ───────────────────────────────────────────
const viewMode = ref('table')
const filterDialogOpen = ref(false)
const mobileFabOpen = ref(false)

try {
  viewMode.value = localStorage.getItem('wp.people-skills.teams.view') === 'grid' ? 'grid' : 'table'
}
catch {
  viewMode.value = 'table'
}

watch(viewMode, value => {
  try {
    localStorage.setItem('wp.people-skills.teams.view', value)
  }
  catch {
    // Storage may be unavailable in private browsing.
  }
})

const activeFilters = computed(() => listPage.activeFilters.value)
const activeFilterCount = computed(() => listPage.activeFilterCount.value)

const statusOptions = computed(() => [
  { title: t('Active'), value: 'active' },
  { title: t('Inactive'), value: 'inactive' },
])

const hasLeaderOptions = computed(() => [
  { title: t('teams.filter.with_leader'), value: 1 },
  { title: t('teams.filter.without_leader'), value: 0 },
])

const standardTeamTypes = [
  ['operational', 'teams.type.operational'],
  ['general', 'teams.type.general'],
  ['project', 'teams.type.project'],
  ['shift', 'teams.type.shift'],
  ['department', 'teams.type.department'],
  ['cross_functional', 'teams.type.cross_functional'],
]

const teamTypeOptions = computed(() => {
  const labels = new Map(standardTeamTypes.map(([value, key]) => [value, t(key)]))

  const values = new Set([
    ...standardTeamTypes.map(([value]) => value),
    ...(teamStore.teamTypes || []),
    ...teamStore.list.map(team => team.team_type).filter(Boolean),
    ...(editForm.team_type ? [editForm.team_type] : []),
  ])

  return [...values].map(value => ({
    title: labels.get(value) || value,
    value,
  }))
})

const deptOptions = computed(() =>
  hrStore.departments.map(department => ({
    title: localizedValue(department.name),
    value: department.id,
  })),
)

const filterLabelMap = computed(() => ({
  status: t('teams.col.status'),
  team_type: t('teams.col.type'),
  department_id: t('teams.col.department'),
  has_leader: t('teams.col.leader'),
}))

const filterValueFormatters = computed(() => ({
  status: value => statusOptions.value.find(option => option.value === value)?.title || value,
  team_type: value => getTeamTypeLabel(value),
  department_id: value => deptOptions.value.find(option => String(option.value) === String(value))?.title || value,
  has_leader: value => Number(value) === 1 ? t('teams.filter.with_leader') : t('teams.filter.without_leader'),
}))

const openFilterDialog = () => {
  listPage.stageFilters()
  filterDialogOpen.value = true
}

const applyFilters = () => {
  listPage.applyStaged()
  filterDialogOpen.value = false
}

const resetFilters = () => {
  listPage.resetStaged()
  listPage.applyStaged()
  filterDialogOpen.value = false
}

const removeActiveFilter = filter => listPage.removeFilter(filter.key)
const clearActiveFilters = () => listPage.resetFilters()

const mobileFabActions = computed(() => [
  {
    id: 'add-team',
    icon: 'tabler-users-group',
    title: t('teams.add'),
    disabled: !proxy?.$can?.('create', 'PeopleSkills'),
    onClick: () => openEdit(),
  },
  {
    id: 'filter-teams',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])

// ── KPIs ─────────────────────────────────────────────────────────────────────
const kpis = computed(() => {
  return [
    {
      key: 'total',
      title: t('teams.kpi.total'),
      value: teamStore.stats.total_count ?? teamStore.totalItems,
      icon: 'tabler-users-group',
      color: 'primary',
      clickable: true,
    },
    {
      key: 'active',
      title: t('teams.kpi.active'),
      value: teamStore.stats.active_count ?? '—',
      icon: 'tabler-circle-check',
      color: 'success',
      clickable: true,
    },
    {
      key: 'with_leader',
      title: t('teams.kpi.with_leader'),
      value: teamStore.stats.with_leader_count ?? '—',
      icon: 'tabler-user-star',
      color: 'info',
      clickable: true,
    },
    {
      key: 'total_members',
      title: t('teams.kpi.total_members'),
      value: teamStore.stats.total_members_count ?? '—',
      icon: 'tabler-users',
      color: 'secondary',
    },
  ]
})

const handleKpiClick = key => {
  if (key === 'total') {
    listPage.resetFilters()
  }
  else if (key === 'active') {
    listPage.setFilter('status', listPage.filters.status === 'active' ? null : 'active')
  }
  else if (key === 'with_leader') {
    listPage.setFilter('has_leader', Number(listPage.filters.has_leader) === 1 ? null : 1)
  }
}

// ── View Dialog ───────────────────────────────────────────────────────────────
const viewDialog = useDialog()
const viewTeam = ref(null)
const viewLoading = ref(false)
const viewError = ref(null)

const viewFields = computed(() => {
  const team = viewTeam.value
  if (!team)
    return []

  return [
    { label: t('teams.col.code'), value: team.code || '—' },
    { label: t('teams.col.type'), value: getTeamTypeLabel(team.team_type) },
    { label: t('teams.col.department'), value: getDeptName(team.department_id, team) },
    { label: t('teams.col.leader'), value: getEmployeeName(team.team_leader_id, team) },
    { label: t('teams.col.members'), value: team.members_count ?? team.members?.length ?? 0 },
    { label: t('teams.col.created_at'), value: formatDate(team.created_at) },
    { label: t('teams.col.updated_at'), value: formatDate(team.updated_at) },
  ]
})

const openView = async id => {
  viewTeam.value = null
  viewError.value = null
  viewLoading.value = true
  viewDialog.open(id)
  try {
    viewTeam.value = await teamStore.fetchOne(id)
  }
  catch (err) {
    viewError.value = err?.data?.message || err?.message || t('teams.load_error')
  }
  finally { viewLoading.value = false }
}

const retryView = () => {
  const id = viewDialog.item.value || viewTeam.value?.id
  if (id)
    openView(id)
}

// ── CRUD Dialog ───────────────────────────────────────────────────────────────
const editDialog = useDialog()

const editForm = reactive({
  name: ensureTranslatable(),
  code: '',
  description: ensureTranslatable(),
  department_id: null,
  team_leader_id: null,
  team_type: 'operational',
  is_active: true,
})

const saving = ref(false)
const formLoading = ref(false)
const delDialog = useDialog()
const deleting = ref(false)
const extraLeaderOption = ref(null)

const employeeOptions = computed(() =>
  [
    ...employees.list
      .filter(employee => employee.status === 'active')
      .map(employee => ({
        title: `${employee.full_name || `${localizedValue(employee.first_name, '')} ${localizedValue(employee.last_name, '')}`.trim()} · ${employee.employee_number}`,
        value: employee.id,
      })),
    ...(extraLeaderOption.value ? [extraLeaderOption.value] : []),
  ].filter((option, index, options) => options.findIndex(candidate => String(candidate.value) === String(option.value)) === index),
)

let metaRequest = null
let employeeRequest = null

const ensureOptions = async () => {
  const requests = []
  if (!hrStore.metaLoaded) {
    if (hrStore.isLoadingMeta) {
      requests.push(new Promise(resolve => {
        const stop = watch(() => hrStore.isLoadingMeta, loading => {
          if (!loading) {
            stop()
            resolve()
          }
        })
      }))
    }
    else {
      metaRequest ||= hrStore.fetchMeta().finally(() => { metaRequest = null })
      requests.push(metaRequest)
    }
  }
  if (!employees.list.length && !employees.loading) {
    employeeRequest ||= employees.fetchList({ per_page: 100, status: 'active' }).finally(() => { employeeRequest = null })
    requests.push(employeeRequest)
  }
  else if (!employees.list.length && employees.loading) {
    requests.push(new Promise(resolve => {
      const stop = watch(() => employees.loading, loading => {
        if (!loading) {
          stop()
          resolve()
        }
      })
    }))
  }

  await Promise.all(requests)
  if (!hrStore.metaLoaded)
    throw new Error(t('teams.options_error'))
  if (!employees.list.length && employees.error)
    throw new Error(employees.error)
}

const openEdit = async (team = null) => {
  Object.assign(editForm, {
    name: ensureTranslatable(team?.name),
    code: team?.code || '',
    description: ensureTranslatable(team?.description),
    department_id: team?.department_id || null,
    team_leader_id: team?.team_leader_id || null,
    team_type: team?.team_type || 'operational',
    is_active: team?.is_active ?? true,
  })
  extraLeaderOption.value = team?.team_leader_id
    ? { title: team.team_leader_name || team.leader?.full_name || t('teams.form.current_leader'), value: team.team_leader_id }
    : null
  editDialog.open(team?.id || null)
  formLoading.value = true
  try {
    await ensureOptions()
  }
  catch (err) {
    showMessage(err.message || t('teams.options_error'), 'error')
  }
  finally {
    formLoading.value = false
  }
}

const save = async formData => {
  saving.value = true
  try {
    const payload = {
      ...formData,
      name: { ...formData.name },
      description: { ...formData.description },
      code: String(formData.code || '').trim(),
      is_active: Boolean(formData.is_active),
    }

    if (editDialog.item.value) {
      await teamStore.update(editDialog.item.value, payload)
      showMessage(t('teams.save_success'), 'success')
    }
    else {
      await teamStore.create(payload)
      showMessage(t('teams.create_success'), 'success')
    }
    editDialog.close()
    await listPage.refresh()
    hrStore.fetchMeta(true)
  }
  catch (err) {
    showMessage(err?.data?.message || t('teams.save_error'), 'error')
  }
  finally { saving.value = false }
}

const doDelete = async () => {
  deleting.value = true
  try {
    await teamStore.remove(delDialog.item.value)
    showMessage(t('teams.delete_success'), 'success')
    await listPage.refresh()
    hrStore.fetchMeta(true)

    return true
  }
  catch (err) {
    showMessage(err?.data?.message || t('teams.delete_error'), 'error')

    return false
  }
  finally { deleting.value = false }
}

// ── Table headers ─────────────────────────────────────────────────────────────
const headers = computed(() => [
  { title: t('teams.col.name'), key: 'name', sortable: true },
  { title: t('teams.col.code'), key: 'code', sortable: true, width: '120px' },
  { title: t('teams.col.type'), key: 'team_type', sortable: true, width: '140px' },
  { title: t('teams.col.department'), key: 'department_id', sortable: false, width: '150px' },
  { title: t('teams.col.leader'), key: 'team_leader_id', sortable: false, width: '150px' },
  { title: t('teams.col.members'), key: 'members_count', sortable: false, width: '100px' },
  { title: t('teams.col.status'), key: 'is_active', sortable: false, width: '100px' },
  { title: t('teams.col.created_at'), key: 'created_at', sortable: true, width: '140px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const getRowActions = () => [
  { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

const getDeptName = (deptId, team = null) => {
  if (team?.department)
    return localizedValue(team.department.name, '—')
  const dept = hrStore.departments.find(d => d.id === deptId)

  return localizedValue(dept?.name, '—')
}

const getEmployeeName = (empId, team = null) => {
  if (team?.team_leader_name)
    return team.team_leader_name
  if (team?.leader?.full_name)
    return team.leader.full_name

  // Try to find in employees list first
  const emp = employees.list.find(e => e.id === empId)

  if (emp) return emp.full_name || `${localizedValue(emp.first_name, '')} ${localizedValue(emp.last_name, '')}`.trim()

  // Fallback to managers list
  const manager = hrStore.managers.find(m => m.id === empId)

  return manager ? (manager.full_name || `${localizedValue(manager.first_name, '')} ${localizedValue(manager.last_name, '')}`.trim()) : '—'
}

const getTeamTypeLabel = type => {
  const option = teamTypeOptions.value.find(o => o.value === type)

  return option?.title || type || '—'
}

const formatDate = value => {
  if (!value)
    return '—'

  const date = new Date(value)
  
  return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
}

const can = action => !!proxy?.$can?.(action, 'PeopleSkills')

const rules = {
  required: value => !!String(value ?? '').trim() || t('teams.validation.required'),
}

onMounted(() => {
  ensureOptions().catch(err => showMessage(err.message || t('teams.options_error'), 'error'))
})
</script>

<template>
  <div class="people-skills-teams-list d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('teams.list.title')"
      :subtitle="$t('teams.list.subtitle')"
      icon="tabler-users-group"
    >
      <template #actions>
        <VBtnToggle
          v-model="viewMode"
          mandatory
          density="compact"
          variant="outlined"
          divided
          rounded="lg"
          :aria-label="$t('teams.list.view_mode')"
        >
          <VBtn
            value="table"
            :title="$t('teams.list.view_table')"
            :aria-label="$t('teams.list.view_table')"
          >
            <VIcon icon="tabler-list" />
          </VBtn>
          <VBtn
            value="grid"
            :title="$t('teams.list.view_grid')"
            :aria-label="$t('teams.list.view_grid')"
          >
            <VIcon icon="tabler-layout-grid" />
          </VBtn>
        </VBtnToggle>
      </template>
    </WpPageHeader>

    <!-- KPIs -->
    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 1, sm: 2, md: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="teamStore.loading"
            :clickable="kpi.clickable"
            :active="(kpi.key === 'active' && listPage.filters.status === 'active') || (kpi.key === 'with_leader' && Number(listPage.filters.has_leader) === 1)"
            @click="handleKpiClick(kpi.key)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <VCard>
      <WpPageToolbar
        v-model:search="listPage.search.value"
        :create-label="$can('create', 'PeopleSkills') ? $t('teams.add') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="teamStore.loading"
        @create="openEdit"
        @refresh="listPage.refresh()"
        @open-filters="openFilterDialog"
      >
        <template #filter-chips>
          <WpActiveFilters
            :filters="activeFilters"
            :label-map="filterLabelMap"
            :value-formatters="filterValueFormatters"
            @remove="removeActiveFilter"
            @clear-all="clearActiveFilters"
          />
        </template>
      </WpPageToolbar>

      <WpEntityCollection
        v-model:view-mode="viewMode"
        v-model:page="listPage.page.value"
        v-model:items-per-page="listPage.itemsPerPage.value"
        v-model:sort-by="listPage.sortBy.value"
        :headers="headers"
        hide-view-mode-toggle
        :items="teamStore.list"
        :total-items="teamStore.totalItems"
        :loading="teamStore.loading"
        :error="teamStore.error"
        :grid-cols="{ cols: 12, sm: 6, md: 4, lg: 4 }"
        grid-gap="4"
        table-density="compact"
        mobile-breakpoint="sm"
        @retry="listPage.refresh()"
      >
        <template #item.name="{ item }">
          {{ localizedValue(item.name) }}
        </template>
        <template #item.team_type="{ item }">
          <VChip
            size="small"
            variant="tonal"
            class="text-capitalize"
          >
            {{ getTeamTypeLabel(item.team_type) }}
          </VChip>
        </template>

        <template #item.department_id="{ item }">
          {{ getDeptName(item.department_id) }}
        </template>

        <template #item.team_leader_id="{ item }">
          {{ getEmployeeName(item.team_leader_id, item) }}
        </template>

        <template #item.members_count="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ item.members_count ?? item.members?.length ?? 0 }}
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <WpStatusChip
            type="team"
            :status="item.is_active ? 'active' : 'inactive'"
            :label="item.is_active ? $t('Active') : $t('Inactive')"
          />
        </template>

        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions()"
            @view="openView(item.id)"
            @edit="openEdit(item)"
            @delete="delDialog.open(item.id)"
          />
        </template>

        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="localizedValue(item.name)"
            :subtitle="item.code"
            avatar-icon="tabler-users-group"
            avatar-color="primary"
            :status="item.is_active ? $t('Active') : $t('Inactive')"
            :status-color="item.is_active ? 'success' : 'secondary'"
            clickable
            @click="openView(item.id)"
          >
            <template #badges>
              <VChip
                v-if="item.team_type"
                size="x-small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ getTeamTypeLabel(item.team_type) }}
              </VChip>
              <VChip
                v-if="item.members_count"
                size="x-small"
                variant="tonal"
                color="primary"
              >
                {{ item.members_count }} {{ $t('teams.col.members') }}
              </VChip>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions()"
                @view="openView(item.id)"
                @edit="openEdit(item)"
                @delete="delDialog.open(item.id)"
              />
            </template>

            <template #details>
              <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                <div
                  v-if="item.team_leader_id"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-user-star"
                    color="warning"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('teams.col.leader') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ getEmployeeName(item.team_leader_id, item) }}</span>
                </div>
                <div
                  v-if="item.department_id"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-building-community"
                    color="primary"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('teams.col.department') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ getDeptName(item.department_id, item) }}</span>
                </div>
              </div>
            </template>
          </WpMobileCard>
        </template>

        <template #card="{ entity }">
          <WpEntityCard
            :entity="entity"
            :title="localizedValue(entity.name)"
            :subtitle="entity.code"
            avatar-icon="tabler-users-group"
            avatar-color="primary"
            :status="entity.is_active ? $t('Active') : $t('Inactive')"
            :status-color="entity.is_active ? 'success' : 'secondary'"
            clickable
            @click="openView(entity.id)"
          >
            <template #badges>
              <VChip
                v-if="entity.team_type"
                size="x-small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ getTeamTypeLabel(entity.team_type) }}
              </VChip>
              <VChip
                v-if="entity.members_count"
                size="x-small"
                variant="tonal"
                color="primary"
              >
                {{ entity.members_count }} {{ $t('teams.col.members') }}
              </VChip>
            </template>

            <template #content>
              <div class="d-flex flex-column gap-1 text-caption">
                <div
                  v-if="entity.team_leader_id"
                  class="d-flex align-center gap-1 min-w-0"
                >
                  <VIcon
                    icon="tabler-user-star"
                    size="14"
                    class="text-medium-emphasis shrink-0"
                  />
                  <span class="text-medium-emphasis shrink-0">{{ $t('teams.col.leader') }}:</span>
                  <span class="font-weight-medium text-truncate">{{ getEmployeeName(entity.team_leader_id, entity) }}</span>
                </div>
                <div
                  v-if="entity.department_id"
                  class="d-flex align-center gap-1 min-w-0"
                >
                  <VIcon
                    icon="tabler-building-community"
                    size="14"
                    class="text-medium-emphasis shrink-0"
                  />
                  <span class="text-medium-emphasis shrink-0">{{ $t('teams.col.department') }}:</span>
                  <span class="text-truncate">{{ getDeptName(entity.department_id, entity) }}</span>
                </div>
                <div
                  v-if="localizedValue(entity.description)"
                  class="text-medium-emphasis line-clamp-2 mt-1"
                >
                  {{ localizedValue(entity.description) }}
                </div>
              </div>
            </template>

            <template #footer>
              <VBtn
                variant="text"
                size="small"
                prepend-icon="tabler-eye"
                @click.stop="openView(entity.id)"
              >
                {{ $t('View') }}
              </VBtn>
              <VBtn
                v-if="$can('update', 'PeopleSkills')"
                variant="text"
                size="small"
                prepend-icon="tabler-edit"
                @click.stop="openEdit(entity)"
              >
                {{ $t('Edit') }}
              </VBtn>
            </template>
          </WpEntityCard>
        </template>
      </WpEntityCollection>
    </VCard>

    <ViewTeamDialog
      v-model="viewDialog.isOpen.value"
      :team="viewTeam"
      :fields="viewFields"
      :loading="viewLoading"
      :error="viewError"
      :active="!!viewTeam?.is_active"
      :can-edit="can('update')"
      @retry="retryView"
      @edit="openEdit(viewTeam); viewDialog.close()"
    />

    <TeamFormDialog
      v-model="editDialog.isOpen.value"
      :item-id="editDialog.item.value"
      :form="editForm"
      :department-options="deptOptions"
      :employee-options="employeeOptions"
      :team-type-options="teamTypeOptions"
      :rules="rules"
      :loading="formLoading"
      :saving="saving"
      @submit="save"
    />

    <WpFilterDialog
      v-model="filterDialogOpen"
      :active-count="activeFilterCount"
      :title="$t('teams.filter.title')"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.status"
            :label="$t('teams.col.status')"
            :items="statusOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.team_type"
            :label="$t('teams.col.type')"
            :items="teamTypeOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.department_id"
            :label="$t('teams.col.department')"
            :items="deptOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.has_leader"
            :label="$t('teams.col.leader')"
            :items="hasLeaderOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- Delete Dialog -->
    <WpDeleteDialog
      v-model="delDialog.isOpen.value"
      :title="$t('Delete')"
      :description="$t('teams.delete_confirm')"
      :loading="deleting"
      :confirm-action="doDelete"
      @update:model-value="value => !value && delDialog.close()"
    />

    <AppFabSpeedDial
      v-model="mobileFabOpen"
      class="d-md-none teams-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.teams-list-quick-actions {
  inset-inline-end: 25px;
}
</style>
