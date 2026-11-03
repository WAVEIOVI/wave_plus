/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import {
  getEmployeeStatusOptions,
  resolveEmployeeStatusColor,
  resolveEmployeeStatusLabel,
} from '@/utils/hr-constants'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useEmployeeList
 * ─────────────────────────────────────────────────────────────────────────────
 * Employees Directory list orchestration composable.
 *
 * Architecture:
 *   Page → useEmployeeList → useListPage → useEmployeeStore → API → Laravel
 *
 * Owns:
 *   - Employee list fetching with server-side search / filter / sort / pagination
 *   - KPI statistics (headcount by status)
 *   - Table headers
 *   - Status / department / team option lookups
 *   - Row selection
 *
 * Does NOT own:
 *   - Dialog state (useEmployeeDialogs)
 *   - CRUD actions (useEmployeeActions)
 */
export function useEmployeeList() {
  const employeeStore = useEmployeeStore()
  const { t } = useI18n()

  // Allowed sort fields (must match backend allowedSorts)
  const allowedSortFields = new Set([
    'first_name',
    'last_name',
    'employee_number',
    'hire_date',
    'status',
    'created_at',
  ])

  // ── Status options ───────────────────────────────────────────────────────
  const statusOptions = computed(() => getEmployeeStatusOptions())

  const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val

  // ── Department / Team options ────────────────────────────────────────────
  // Resolved from hrStore meta (loaded by useEmployeeForm or page on mount)
  const departmentOptions = ref([])
  const teamOptions = ref([])
  const positionOptions = ref([])

  // ── Table headers ────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('employees.list.col.employee'), key: 'employee', sortable: true, minWidth: '220px' },
    { title: t('employees.list.col.department'), key: 'department', sortable: false, minWidth: '160px' },
    { title: t('employees.list.col.position'), key: 'position', sortable: false, minWidth: '160px' },
    { title: t('employees.list.col.contact'), key: 'contact', sortable: false, minWidth: '160px' },
    { title: t('employees.list.col.hire_date'), key: 'hire_date', sortable: true, minWidth: '120px' },
    { title: t('employees.list.col.status'), key: 'status', sortable: true, minWidth: '120px' },
    { title: '', key: 'actions', sortable: false, width: '60px' },
  ])

  // ── List page orchestration ──────────────────────────────────────────────
  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'created_at'
      const sort = `${params.sort_order === 'desc' ? '-' : ''}${sortField}`

      const query = {
        page: params.page,
        per_page: params.per_page,
        search: params.search || undefined,
        sort_by: sortField,
        sort_order: params.sort_order,
        status: params.status || undefined,
        department_id: params.department_id || undefined,
        position_id: params.position_id || undefined,
        team_id: params.team_id || undefined,
      }

      return await employeeStore.fetchList(query)
    },
    initialFilters: {
      status: null,
      department_id: null,
      position_id: null,
      team_id: null,
    },
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
    initialItemsPerPage: 15,

    // Map the composite 'employee' column to a valid sort field
    sortFieldMap: {
      employee: 'last_name',
    },
    immediate: true,
  })

  // ── Row selection ────────────────────────────────────────────────────────
  const { selected: selectedEmployees, clearSelection } = useSelection()

  watch(listPage.rows, () => clearSelection())

  // ── KPI / Statistics ─────────────────────────────────────────────────────
  const kpiCards = computed(() => [
    {
      titleKey: 'employees.list.stats.total',
      value: employeeStore.statistics.total,
      icon: 'tabler-users',
      filterValue: null,
      iconColor: 'primary',
    },
    {
      titleKey: 'employees.list.stats.active',
      value: employeeStore.statistics.active,
      icon: 'tabler-user-check',
      filterValue: 'active',
      iconColor: 'success',
    },
    {
      titleKey: 'employees.list.stats.on_leave',
      value: employeeStore.statistics.on_leave,
      icon: 'tabler-calendar-time',
      filterValue: 'on_leave',
      iconColor: 'info',
    },
    {
      titleKey: 'employees.list.stats.suspended',
      value: employeeStore.statistics.suspended,
      icon: 'tabler-user-pause',
      filterValue: 'suspended',
      iconColor: 'warning',
    },
    {
      titleKey: 'employees.list.stats.terminated',
      value: employeeStore.statistics.terminated,
      icon: 'tabler-user-x',
      filterValue: 'terminated',
      iconColor: 'error',
    },
  ])

  const handleKpiClick = filterValue => {
    if (filterValue === null) {
      listPage.setFilter('status', null)
    }
    else {
      listPage.setFilter(
        'status',
        listPage.filters.status === filterValue ? null : filterValue,
      )
    }
  }

  // ── Initialize statistics on mount ───────────────────────────────────────
  onMounted(() => employeeStore.fetchStatistics())

  // ── Helpers ──────────────────────────────────────────────────────────────
  const resolveStatusColor = resolveEmployeeStatusColor
  const resolveStatusLabel = resolveEmployeeStatusLabel

  return {
    // Spread the full list page API
    ...listPage,

    // Rename rows to employees
    employees: listPage.rows,

    // Options
    headers,
    statusOptions,
    departmentOptions,
    positionOptions,
    teamOptions,
    getStatusTitle,

    // Resolvers
    resolveStatusColor,
    resolveStatusLabel,

    // Selection
    selectedEmployees,
    clearSelection,

    // KPIs
    statistics: employeeStore.statistics,
    statsLoading: computed(() => employeeStore.statsLoading),
    kpiCards,
    handleKpiClick,
    fetchStatistics: employeeStore.fetchStatistics,
  }
}
