<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
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
import { useCurrency } from '@/composables/useCurrency'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useDepartmentStore, usePositionStore } from '@/stores/useDepartmentStore'
import DepartmentFormDialog from '@/views/people-skills/departments/list/DepartmentFormDialog.vue'
import PositionFormDialog from '@/views/people-skills/departments/list/PositionFormDialog.vue'
import ViewDepartmentDialog from '@/views/people-skills/departments/list/ViewDepartmentDialog.vue'
import ViewPositionDialog from '@/views/people-skills/departments/list/ViewPositionDialog.vue'
import { computed, getCurrentInstance, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/departments/list',
  name: 'people-skills-departments-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const { showMessage } = useAppSnackbar()
const { format: formatCurrency } = useCurrency()
const deptStore = useDepartmentStore()
const posStore = usePositionStore()
const hrStore = useHrStore()

const can = action => !!proxy?.$can?.(action, 'PeopleSkills')

const sameId = (a, b) => a != null && b != null && String(a) === String(b)

const isActiveStatus = item => {
  if (!item)
    return false
  if (typeof item.is_active === 'boolean')
    return item.is_active
  if (item.is_active === 1 || item.is_active === '1' || item.is_active === 'true')
    return true
  if (item.is_active === 0 || item.is_active === '0' || item.is_active === 'false')
    return false
  if (item.status)
    return item.status === 'active'

  return true
}

const statusFrom = item => (item && !isActiveStatus(item) ? 'inactive' : 'active')

const stripEmpty = params => Object.fromEntries(
  Object.entries(params || {}).filter(([, value]) => value !== null && value !== undefined && value !== ''),
)

const countIfComplete = (store, predicate, summaryKeys = []) => {
  for (const key of summaryKeys) {
    const summary = store?.stats?.[key] ?? store?.[key]
    if (typeof summary === 'number')
      return summary
  }

  const list = store.list || []
  const total = Number(store.totalItems ?? list.length)

  return list.length === total ? list.filter(predicate).length : '—'
}

const sumIfComplete = (store, selector, summaryKeys = []) => {
  for (const key of summaryKeys) {
    const summary = store?.stats?.[key] ?? store?.[key]
    if (typeof summary === 'number')
      return summary
  }

  const list = store.list || []
  const total = Number(store.totalItems ?? list.length)

  if (list.length !== total)
    return '—'

  return list.reduce((sum, item) => sum + (Number(selector(item)) || 0), 0)
}

const rules = {
  required: value => !!String(value ?? '').trim() || t('departments.validation.required'),
  nonNegative: value => value === null || value === undefined || value === '' || Number(value) >= 0 || t('departments.validation.non_negative'),
}

const serverMessage = (err, fallback) => {
  const bag = err?.data?.errors
  const flat = bag ? Object.values(bag).flat().filter(Boolean).join('\n') : ''

  return err?.data?.message || flat || fallback
}

const activeTab = ref(route.query.tab === 'positions' ? 'positions' : 'departments')

const hybridTabs = computed(() => [
  {
    value: 'departments',
    title: t('departments.list.tab_departments'),
    icon: 'tabler-building-community',
    badge: deptStore.totalItems,
    color: 'primary',
  },
  {
    value: 'positions',
    title: t('departments.list.tab_positions'),
    icon: 'tabler-briefcase',
    badge: positionsLoaded.value || posStore.totalItems ? posStore.totalItems : undefined,
    color: 'primary',
  },
])

const bottomNavItems = computed(() => hybridTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
  badge: tab.badge,
})))

const orgViewMode = ref('table')
try {
  const savedViewMode = localStorage.getItem('wp.people-skills.departments-positions.view')
    || localStorage.getItem('wp.people-skills.departments.view')
    || localStorage.getItem('wp.people-skills.positions.view')

  orgViewMode.value = savedViewMode === 'grid' ? 'grid' : 'table'
}
catch {
  orgViewMode.value = 'table'
}

watch(orgViewMode, value => {
  try {
    localStorage.setItem('wp.people-skills.departments-positions.view', value)
  }
  catch {
    // Browser storage can be unavailable in private contexts.
  }
})

const positionsLoaded = ref(activeTab.value === 'positions')
const isFilterDialogOpen = ref(false)
const isMobileFabOpen = ref(false)

const deptListPage = useListPage({
  fetchFn: params => deptStore.fetchList(stripEmpty(params)),
  initialFilters: { status: null, parent_department_id: null },
  initialSortKey: 'name',
  initialSortOrder: 'asc',
  immediate: true,
})

const posListPage = useListPage({
  fetchFn: params => posStore.fetchList(stripEmpty(params)),
  initialFilters: { status: null, department_id: null },
  initialSortKey: 'title',
  initialSortOrder: 'asc',
  immediate: activeTab.value === 'positions',
})

const activeListPage = computed(() => activeTab.value === 'departments' ? deptListPage : posListPage)
const activeFilters = computed(() => activeListPage.value.activeFilters.value)
const activeFilterCount = computed(() => activeListPage.value.activeFilterCount.value)

const filterDialogTitle = computed(() => t(
  activeTab.value === 'departments' ? 'departments.filter.title' : 'positions.filter.title',
))

const filterLabelMap = computed(() => activeTab.value === 'departments'
  ? {
    status: t('departments.col.status'),
    parent_department_id: t('departments.col.parent'),
  }
  : {
    status: t('positions.col.status'),
    department_id: t('positions.col.department'),
  })

const filterValueFormatters = computed(() => ({
  status: value => statusOptions.value.find(option => option.value === value)?.title || value,
  ...(activeTab.value === 'departments'
    ? { parent_department_id: value => deptOptions.value.find(option => sameId(option.value, value))?.title || value }
    : { department_id: value => deptOptions.value.find(option => sameId(option.value, value))?.title || value }),
}))

const openFilterDialog = () => {
  activeListPage.value.stageFilters()
  isFilterDialogOpen.value = true
}

const applyStagedFilters = () => {
  activeListPage.value.applyStaged()
  isFilterDialogOpen.value = false
}

const resetStagedFilters = () => {
  activeListPage.value.resetStaged()
  activeListPage.value.applyStaged()
  isFilterDialogOpen.value = false
}

const removeActiveFilter = key => activeListPage.value.removeFilter(key)
const resetActiveFilters = () => activeListPage.value.resetFilters()

const refreshActiveList = () => {
  activeListPage.value.refresh()
}

const mobileFabActions = computed(() => [
  {
    id: 'add-entity',
    icon: activeTab.value === 'departments' ? 'tabler-building-community' : 'tabler-briefcase',
    title: t(activeTab.value === 'departments' ? 'departments.add' : 'positions.add'),
    disabled: !can('create'),
    onClick: () => activeTab.value === 'departments' ? openDeptDialog() : openPosDialog(),
  },
  {
    id: 'filter-entities',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])

watch(activeTab, tab => {
  if (route.query.tab !== tab)
    router.replace({ query: { ...route.query, tab } })

  if (tab === 'positions' && !positionsLoaded.value) {
    positionsLoaded.value = true
    posListPage.fetchData()
  }
})

watch(() => route.query.tab, tab => {
  const next = tab === 'positions' ? 'positions' : 'departments'
  if (activeTab.value !== next)
    activeTab.value = next
})

const positionsKpiValue = computed(() => {
  if (positionsLoaded.value || posStore.totalItems)
    return posStore.totalItems

  return sumIfComplete(deptStore, dept => dept.positions_count, ['positionsCount', 'positions_count'])
})

const deptKpis = computed(() => [
  {
    key: 'total',
    title: t('departments.kpi.total'),
    value: deptStore.stats.total_count ?? deptStore.totalItems,
    icon: 'tabler-building-community',
    color: 'primary',
    clickable: true,
  },
  {
    key: 'active',
    title: t('departments.kpi.active'),
    value: countIfComplete(deptStore, isActiveStatus, ['active_count', 'activeCount']),
    icon: 'tabler-circle-check',
    color: 'success',
    clickable: true,
  },
  {
    key: 'with_manager',
    title: t('departments.kpi.with_manager'),
    value: countIfComplete(deptStore, dept => !!dept.manager_id, ['with_manager_count', 'withManagerCount']),
    icon: 'tabler-user-star',
    color: 'info',
  },
  {
    key: 'positions',
    title: t('departments.kpi.positions'),
    value: positionsKpiValue.value,
    icon: 'tabler-briefcase',
    color: 'secondary',
    clickable: true,
  },
])

const posKpis = computed(() => [
  {
    key: 'total',
    title: t('positions.kpi.total'),
    value: posStore.stats.total_count ?? posStore.totalItems,
    icon: 'tabler-briefcase',
    color: 'primary',
    clickable: true,
  },
  {
    key: 'active',
    title: t('positions.kpi.active'),
    value: countIfComplete(posStore, isActiveStatus, ['active_count', 'activeCount']),
    icon: 'tabler-circle-check',
    color: 'success',
    clickable: true,
  },
  {
    key: 'with_rate',
    title: t('positions.kpi.with_rate'),
    value: countIfComplete(
      posStore,
      pos => pos.standard_hourly_rate !== null && pos.standard_hourly_rate !== undefined && pos.standard_hourly_rate !== '',
      ['with_rate_count', 'withRateCount'],
    ),
    icon: 'tabler-cash',
    color: 'warning',
  },
  {
    key: 'employees',
    title: t('positions.kpi.employees'),
    value: posStore.stats.employees_count ?? sumIfComplete(posStore, pos => pos.employees_count, ['employees_count', 'employeesCount']),
    icon: 'tabler-users',
    color: 'info',
  },
])

const onDeptKpiClick = key => {
  if (key === 'total')
    deptListPage.resetFilters()
  if (key === 'active') {
    deptListPage.setFilter('status', deptListPage.filters.status === 'active' ? null : 'active')
  }
  if (key === 'positions')
    activeTab.value = 'positions'
}

const onPosKpiClick = key => {
  if (key === 'total')
    posListPage.resetFilters()
  if (key === 'active') {
    posListPage.setFilter('status', posListPage.filters.status === 'active' ? null : 'active')
  }
}

const statusOptions = computed(() => [
  { title: t('Active'), value: 'active' },
  { title: t('Inactive'), value: 'inactive' },
])

const optionTitle = item => {
  const label = localizedValue(item?.name ?? item?.title, '')

  return isActiveStatus(item) ? label : `${label} · ${t('Inactive')}`
}

const deptOptions = computed(() =>
  [...(hrStore.departments || [])]
    .map(dept => ({ title: optionTitle(dept), value: dept.id }))
    .sort((a, b) => String(a.title).localeCompare(String(b.title))),
)

const parentOptions = computed(() => {
  const currentId = deptDialog.item.value

  return (hrStore.departments || [])
    .filter(dept => !sameId(dept.id, currentId) && !sameId(dept.parent_department_id, currentId))
    .map(dept => ({ title: optionTitle(dept), value: dept.id }))
    .sort((a, b) => String(a.title).localeCompare(String(b.title)))
})

const managerOptions = computed(() =>
  [...(hrStore.managers || [])]
    .map(manager => ({
      title: manager.full_name || `${localizedValue(manager.first_name, '')} ${localizedValue(manager.last_name, '')}`.trim(),
      value: manager.id,
    }))
    .filter(option => option.title)
    .sort((a, b) => a.title.localeCompare(b.title)),
)

const extraPositionOptions = ref([])

const positionOptions = computed(() => {
  const map = new Map(extraPositionOptions.value.map(option => [option.value, option]))

  posStore.list.forEach(pos => {
    map.set(pos.id, { title: localizedValue(pos.title), value: pos.id })
  })

  return [...map.values()].sort((a, b) => String(a.title).localeCompare(String(b.title)))
})

const getDeptName = deptId => {
  if (!deptId)
    return '—'

  const dept = (hrStore.departments || []).find(item => sameId(item.id, deptId))

  return localizedValue(dept?.name, '—')
}

const getManagerName = managerId => {
  if (!managerId)
    return '—'

  const manager = (hrStore.managers || []).find(item => sameId(item.id, managerId))
  if (!manager)
    return '—'

  return manager.full_name
    || `${localizedValue(manager.first_name, '')} ${localizedValue(manager.last_name, '')}`.trim()
    || '—'
}

const formatRate = value => {
  if (value === null || value === undefined || value === '')
    return '—'

  return formatCurrency(value)
}

const formatDate = value => {
  if (!value)
    return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  return new Intl.DateTimeFormat(locale.value || undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(date)
}

const rowActions = computed(() => {
  const actions = [{ label: t('View'), icon: 'tabler-eye', emit: 'view' }]

  if (can('update'))
    actions.push({ label: t('Edit'), icon: 'tabler-edit', emit: 'edit' })

  if (can('delete'))
    actions.push({ label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true })

  return actions
})

const deptHeaders = computed(() => [
  { title: t('departments.col.name'), key: 'name', sortable: true },
  { title: t('departments.col.parent'), key: 'parent_department_id', sortable: false },
  { title: t('departments.col.manager'), key: 'manager_id', sortable: false },
  { title: t('departments.kpi.positions'), key: 'positions_count', sortable: false, width: '120px' },
  { title: t('departments.col.employees'), key: 'employees_count', sortable: false, width: '120px' },
  { title: t('departments.col.teams'), key: 'teams_count', sortable: false, width: '100px' },
  { title: t('departments.col.created_at'), key: 'created_at', sortable: false, width: '140px' },
  { title: t('departments.col.status'), key: 'status', sortable: false, width: '130px' },
  { title: '', key: 'actions', sortable: false, align: 'end', width: '56px' },
])

const posHeaders = computed(() => [
  { title: t('positions.col.title'), key: 'title', sortable: true },
  { title: t('positions.col.department'), key: 'department_id', sortable: false },
  { title: t('positions.col.rate'), key: 'standard_hourly_rate', sortable: false, align: 'end', width: '140px' },
  { title: t('positions.kpi.employees'), key: 'employees_count', sortable: false, width: '120px' },
  { title: t('positions.col.created_at'), key: 'created_at', sortable: false, width: '140px' },
  { title: t('positions.col.status'), key: 'status', sortable: false, width: '130px' },
  { title: '', key: 'actions', sortable: false, align: 'end', width: '56px' },
])

// ── Department detail ────────────────────────────────────────────────────────
const deptViewDialog = useDialog()
const viewingDept = ref(null)
const deptViewLoading = ref(false)
const deptViewError = ref(null)

const openDeptView = async id => {
  viewingDept.value = null
  deptViewError.value = null
  deptViewLoading.value = true
  deptViewDialog.open(id)

  try {
    viewingDept.value = await deptStore.fetchOne(id)
  }
  catch (err) {
    deptViewError.value = serverMessage(err, t('departments.load_error'))
  }
  finally {
    deptViewLoading.value = false
  }
}

const retryDeptView = () => {
  const id = deptViewDialog.item.value || viewingDept.value?.id
  if (id)
    openDeptView(id)
}

const deptDetailFields = computed(() => {
  const dept = viewingDept.value
  if (!dept)
    return []

  return [
    { label: t('departments.col.name'), value: localizedValue(dept.name, '—') },
    { label: t('departments.col.code'), value: dept.code || '—' },
    { label: t('departments.col.parent'), value: getDeptName(dept.parent_department_id) },
    { label: t('departments.col.manager'), value: getManagerName(dept.manager_id) },
    { label: t('departments.form.sort_order'), value: dept.sort_order ?? 0 },
    { label: t('departments.col.employees'), value: dept.employees_count ?? 0 },
    { label: t('departments.col.teams'), value: dept.teams_count ?? 0 },
    { label: t('departments.col.created_at'), value: formatDate(dept.created_at) },
  ]
})

const deptChildren = computed(() =>
  viewingDept.value?.children || viewingDept.value?.child_departments || [],
)

// ── Position detail ──────────────────────────────────────────────────────────
const posViewDialog = useDialog()
const viewingPos = ref(null)
const posViewLoading = ref(false)
const posViewError = ref(null)

const openPosView = async id => {
  viewingPos.value = null
  posViewError.value = null
  posViewLoading.value = true
  posViewDialog.open(id)

  try {
    viewingPos.value = await posStore.fetchOne(id)
  }
  catch (err) {
    posViewError.value = serverMessage(err, t('positions.load_error'))
  }
  finally {
    posViewLoading.value = false
  }
}

const retryPosView = () => {
  const id = posViewDialog.item.value || viewingPos.value?.id
  if (id)
    openPosView(id)
}

const posDetailFields = computed(() => {
  const pos = viewingPos.value
  if (!pos)
    return []

  return [
    { label: t('positions.col.title'), value: localizedValue(pos.title, '—') },
    { label: t('positions.col.code'), value: pos.code || '—' },
    { label: t('positions.col.department'), value: getDeptName(pos.department_id || pos.department?.id) },
    { label: t('positions.col.rate'), value: formatRate(pos.standard_hourly_rate) },
    { label: t('positions.col.employees'), value: pos.employees_count ?? pos.employees?.length ?? 0 },
    { label: t('positions.col.created_at'), value: formatDate(pos.created_at) },
  ]
})

// ── Department form ──────────────────────────────────────────────────────────
const deptDialog = useDialog()
const deptSaving = ref(false)
const deptFormLoading = ref(false)
const deptDelDialog = useDialog()
const deptDeleting = ref(false)
const deptDeleteDescription = ref('')

const deptForm = reactive({
  name: ensureTranslatable(),
  code: '',
  description: ensureTranslatable(),
  parent_department_id: null,
  manager_id: null,
  status: 'active',
  sort_order: 0,
  position_ids: [],
})

const closeDeptDialog = () => {
  if (deptSaving.value)
    return

  deptDialog.close()
}

const ensureHrMeta = async () => {
  if (hrStore.metaLoaded)
    return

  if (hrStore.isLoadingMeta) {
    await new Promise(resolve => {
      const stop = watch(() => hrStore.isLoadingMeta, isLoading => {
        if (!isLoading) {
          stop()
          resolve()
        }
      })
    })

    return
  }

  await hrStore.fetchMeta()
}

const ensurePositionOptions = async () => {
  if (posStore.list.length)
    return

  if (posStore.loading) {
    await new Promise(resolve => {
      const stop = watch(() => posStore.loading, isLoading => {
        if (!isLoading) {
          stop()
          resolve()
        }
      })
    })
    if (posStore.list.length)
      return
  }

  await posListPage.fetchData()
  positionsLoaded.value = true
}

const openDeptDialog = async (dept = null) => {
  extraPositionOptions.value = (dept?.positions || []).map(pos => ({
    title: localizedValue(pos.title),
    value: pos.id,
  }))

  Object.assign(deptForm, {
    name: ensureTranslatable(dept?.name),
    code: dept?.code || '',
    description: ensureTranslatable(dept?.description),
    parent_department_id: dept?.parent_department_id || dept?.parent_department?.id || null,
    manager_id: dept?.manager_id || dept?.manager?.id || null,
    status: statusFrom(dept),
    sort_order: dept?.sort_order ?? 0,
    position_ids: [...(dept?.position_ids || dept?.positions?.map(pos => pos.id) || [])],
  })

  deptDialog.open(dept?.id || null)
  deptFormLoading.value = true
  try {
    await Promise.all([ensureHrMeta(), ensurePositionOptions()])
  }
  finally {
    deptFormLoading.value = false
  }
}

const saveDept = async formData => {
  deptSaving.value = true

  try {
    const payload = {
      ...formData,
      name: { ...formData.name },
      description: { ...formData.description },
      code: String(formData.code || '').trim().toUpperCase(),
      sort_order: Math.max(0, Number(formData.sort_order) || 0),
      position_ids: [...formData.position_ids],
    }

    if (deptDialog.item.value) {
      await deptStore.update(deptDialog.item.value, payload)
      showMessage(t('departments.save_success'), 'success')
    }
    else {
      await deptStore.create(payload)
      showMessage(t('departments.create_success'), 'success')
    }

    deptDialog.close()
    await deptListPage.refresh()
    if (positionsLoaded.value)
      posListPage.refresh()
    hrStore.fetchMeta(true)
  }
  catch (err) {
    showMessage(serverMessage(err, t('departments.save_error')), 'error')
  }
  finally {
    deptSaving.value = false
  }
}

const askDeleteDept = item => {
  const name = localizedValue(item?.name, '')

  deptDeleteDescription.value = name
    ? `${name}. ${t('departments.delete_confirm')}`
    : t('departments.delete_confirm')
  deptDelDialog.open(item.id)
}

const deleteDept = async () => {
  deptDeleting.value = true

  try {
    await deptStore.remove(deptDelDialog.item.value)
    showMessage(t('departments.delete_success'), 'success')
    await deptListPage.refresh()
    hrStore.fetchMeta(true)

    return true
  }
  catch (err) {
    showMessage(serverMessage(err, t('departments.delete_error')), 'error')

    return false
  }
  finally {
    deptDeleting.value = false
  }
}

const editDeptFromView = () => {
  const dept = viewingDept.value
  if (!dept)
    return

  deptViewDialog.close()
  openDeptDialog(dept)
}

const showDepartmentPositions = dept => {
  if (!dept?.id)
    return

  posListPage.setFilter('department_id', dept.id)
  posListPage.setFilter('status', null)
  positionsLoaded.value = true
  deptViewDialog.close()
  activeTab.value = 'positions'
}

const addPositionForDepartment = departmentId => {
  positionsLoaded.value = true
  deptViewDialog.close()
  activeTab.value = 'positions'
  if (!posStore.list.length)
    posListPage.fetchData()
  openPosDialog({ department_id: departmentId })
}

// ── Position form ────────────────────────────────────────────────────────────
const posDialog = useDialog()
const posSaving = ref(false)
const posFormLoading = ref(false)
const posDelDialog = useDialog()
const posDeleting = ref(false)
const posDeleteDescription = ref('')

const posForm = reactive({
  title: ensureTranslatable(),
  code: '',
  department_id: null,
  description: ensureTranslatable(),
  standard_hourly_rate: null,
  requirements: ensureTranslatable(),
  status: 'active',
})

const closePosDialog = () => {
  if (posSaving.value)
    return

  posDialog.close()
}

const openPosDialog = async (pos = null) => {
  Object.assign(posForm, {
    title: ensureTranslatable(pos?.title),
    code: pos?.code || '',
    department_id: pos?.department_id || pos?.department?.id || null,
    description: ensureTranslatable(pos?.description),
    standard_hourly_rate: pos?.standard_hourly_rate ?? null,
    requirements: ensureTranslatable(pos?.requirements),
    status: statusFrom(pos),
  })

  posDialog.open(pos?.id || null)
  posFormLoading.value = true
  try {
    await ensureHrMeta()
  }
  finally {
    posFormLoading.value = false
  }
}

const savePos = async formData => {
  posSaving.value = true

  try {
    const rate = formData.standard_hourly_rate

    const payload = {
      ...formData,
      title: { ...formData.title },
      description: { ...formData.description },
      requirements: { ...formData.requirements },
      code: String(formData.code || '').trim().toUpperCase(),
      standard_hourly_rate: rate === null || rate === '' ? null : Number(rate),
    }

    if (posDialog.item.value) {
      await posStore.update(posDialog.item.value, payload)
      showMessage(t('positions.save_success'), 'success')
    }
    else {
      await posStore.create(payload)
      showMessage(t('positions.create_success'), 'success')
    }

    posDialog.close()
    positionsLoaded.value = true
    await posListPage.refresh()
    deptListPage.refresh()
    hrStore.fetchMeta(true)
  }
  catch (err) {
    showMessage(serverMessage(err, t('positions.save_error')), 'error')
  }
  finally {
    posSaving.value = false
  }
}

const askDeletePos = item => {
  const name = localizedValue(item?.title, '')

  posDeleteDescription.value = name
    ? `${name}. ${t('positions.delete_confirm')}`
    : t('positions.delete_confirm')
  posDelDialog.open(item.id)
}

const deletePos = async () => {
  posDeleting.value = true

  try {
    await posStore.remove(posDelDialog.item.value)
    showMessage(t('positions.delete_success'), 'success')
    await posListPage.refresh()
    deptListPage.refresh()
    hrStore.fetchMeta(true)

    return true
  }
  catch (err) {
    showMessage(serverMessage(err, t('positions.delete_error')), 'error')

    return false
  }
  finally {
    posDeleting.value = false
  }
}

const editPosFromView = () => {
  const pos = viewingPos.value
  if (!pos)
    return

  posViewDialog.close()
  openPosDialog(pos)
}

onMounted(() => hrStore.fetchMeta())
</script>

<template>
  <div class="people-skills-departments-list d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('departments.list.title')"
      :subtitle="$t('departments.list.subtitle')"
      icon="tabler-building-community"
    >
      <template #actions>
        <VBtnToggle
          v-model="orgViewMode"
          mandatory
          density="compact"
          variant="outlined"
          divided
          rounded="lg"
          :aria-label="$t('departments.list.view_mode')"
        >
          <VBtn
            value="table"
            :title="$t('departments.list.view_table')"
            :aria-label="$t('departments.list.view_table')"
          >
            <VIcon icon="tabler-list" />
          </VBtn>
          <VBtn
            value="grid"
            :title="$t('departments.list.view_grid')"
            :aria-label="$t('departments.list.view_grid')"
          >
            <VIcon icon="tabler-layout-grid" />
          </VBtn>
        </VBtnToggle>
      </template>
    </WpPageHeader>

    <WpKpiGroup
      :item-count="activeTab === 'departments' ? deptKpis.length : posKpis.length"
      :cols="{ xs: 1, sm: 2, md: 4 }"
      class="departments-list__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in activeTab === 'departments' ? deptKpis : posKpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <div
            class="h-100"
            :class="{ 'kpi-clickable': kpi.clickable }"
            @click="kpi.clickable && (activeTab === 'departments' ? onDeptKpiClick(kpi.key) : onPosKpiClick(kpi.key))"
          >
            <WpKpiCard
              :title="kpi.title"
              :value="kpi.value"
              :icon="kpi.icon"
              :icon-color="kpi.color"
              :loading="activeTab === 'departments' ? deptStore.loading : posStore.loading"
            />
          </div>
        </VCol>
      </template>
    </WpKpiGroup>

    <WpPageToolbar
      v-model:search="activeListPage.search.value"
      class="departments-list__toolbar"
      :create-label="$can('create', 'PeopleSkills') ? $t(activeTab === 'departments' ? 'departments.add' : 'positions.add') : ''"
      hide-create-on-mobile
      show-filter
      hide-filter-on-mobile
      :active-filter-count="activeFilterCount"
      :loading="activeTab === 'departments' ? deptStore.loading : posStore.loading"
      @create="activeTab === 'departments' ? openDeptDialog() : openPosDialog()"
      @refresh="refreshActiveList"
      @open-filters="openFilterDialog"
    >
      <template #filter-chips>
        <WpActiveFilters
          :filters="activeFilters"
          :label-map="filterLabelMap"
          :value-formatters="filterValueFormatters"
          @remove="removeActiveFilter($event.key)"
          @clear-all="resetActiveFilters"
        />
      </template>
    </WpPageToolbar>

    <AppHybridTabs
      v-model="activeTab"
      :tabs="hybridTabs"
      class="departments-list__tabs"
      header-class="d-none d-md-block"
      :swipeable="false"
      crossfade
    >
      <template #tab-departments>
        <WpEntityCollection
          v-model:view-mode="orgViewMode"
          v-model:page="deptListPage.page.value"
          v-model:items-per-page="deptListPage.itemsPerPage.value"
          v-model:sort-by="deptListPage.sortBy.value"
          :headers="deptHeaders"
          :items="deptStore.list"
          :total-items="deptStore.totalItems"
          :loading="deptStore.loading"
          :error="deptStore.error"
          hide-view-mode-toggle
          :grid-cols="{ cols: 12, sm: 6, md: 4, lg: 4 }"
          table-density="compact"
          mobile-breakpoint="sm"
          empty-icon="tabler-building-community"
          :empty-title="$t('departments.list.tab_departments')"
          :empty-subtitle="$t('departments.list.subtitle')"
          @retry="deptListPage.refresh()"
        >
          <template #empty-action>
            <VBtn
              v-if="$can('create', 'PeopleSkills')"
              color="primary"
              prepend-icon="tabler-plus"
              @click="openDeptDialog"
            >
              {{ $t('departments.add') }}
            </VBtn>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex flex-column">
              <button
                type="button"
                class="entity-link"
                @click="openDeptView(item.id)"
              >
                {{ localizedValue(item.name) }}
              </button>
              <span
                v-if="item.code"
                class="text-caption text-medium-emphasis"
              >
                {{ item.code }}
              </span>
            </div>
          </template>

          <template #item.parent_department_id="{ item }">
            {{ getDeptName(item.parent_department_id) }}
          </template>

          <template #item.manager_id="{ item }">
            <div
              v-if="item.manager_id"
              class="d-flex align-center gap-2"
            >
              <VAvatar
                size="26"
                color="info"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-user"
                  size="14"
                />
              </VAvatar>
              <span>{{ getManagerName(item.manager_id) }}</span>
            </div>
            <span
              v-else
              class="text-medium-emphasis"
            >—</span>
          </template>

          <template #item.positions_count="{ item }">
            <VChip
              v-if="item.positions_count"
              size="x-small"
              variant="tonal"
              color="primary"
              class="cursor-pointer"
              @click="showDepartmentPositions(item)"
            >
              {{ item.positions_count }}
            </VChip>
            <span
              v-else
              class="text-medium-emphasis"
            >—</span>
          </template>

          <template #item.employees_count="{ item }">
            {{ item.employees_count ?? 0 }}
          </template>

          <template #item.teams_count="{ item }">
            {{ item.teams_count ?? 0 }}
          </template>

          <template #item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template #item.status="{ item }">
            <WpStatusChip
              type="department"
              :status="isActiveStatus(item) ? 'active' : 'inactive'"
              :label="isActiveStatus(item) ? $t('Active') : $t('Inactive')"
              dot
              :pulse="isActiveStatus(item)"
            />
          </template>

          <template #item.actions="{ item }">
            <WpRowActions
              :actions="rowActions"
              @view="openDeptView(item.id)"
              @edit="openDeptDialog(item)"
              @delete="askDeleteDept(item)"
            />
          </template>

          <template #mobile-card="{ item }">
            <WpMobileCard
              class="department-position-mobile-card"
              :title="localizedValue(item.name)"
              :subtitle="item.code"
              avatar-icon="tabler-building-community"
              avatar-color="primary"
              :status="isActiveStatus(item) ? $t('Active') : $t('Inactive')"
              :status-color="isActiveStatus(item) ? 'success' : 'secondary'"
              clickable
              @click="openDeptView(item.id)"
            >
              <template #badges>
                <VChip
                  v-if="item.positions_count"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="cursor-pointer"
                  @click.stop="showDepartmentPositions(item)"
                >
                  {{ item.positions_count }} {{ $t('departments.kpi.positions') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="info"
                >
                  {{ item.employees_count ?? 0 }} {{ $t('departments.col.employees') }}
                </VChip>
              </template>

              <template #actions>
                <WpRowActions
                  :actions="rowActions"
                  @view="openDeptView(item.id)"
                  @edit="openDeptDialog(item)"
                  @delete="askDeleteDept(item)"
                />
              </template>

              <template #details>
                <div
                  v-if="item.manager_id"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-user-star"
                    color="warning"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('departments.col.manager') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ getManagerName(item.manager_id) }}</span>
                </div>
                <div
                  v-if="item.parent_department_id"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-building"
                    color="primary"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('departments.col.parent') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ getDeptName(item.parent_department_id) }}</span>
                </div>
              </template>

              <template
                v-if="item.created_at"
                #footer
              >
                <span class="text-caption text-medium-emphasis">
                  {{ $t('departments.col.created_at') }}
                </span>
                <span class="text-caption font-weight-medium">
                  {{ formatDate(item.created_at) }}
                </span>
              </template>
            </WpMobileCard>
          </template>

          <template #card="{ entity }">
            <WpEntityCard
              class="department-position-entity-card"
              :entity="entity"
              :title="localizedValue(entity.name)"
              :subtitle="entity.code"
              avatar-icon="tabler-building-community"
              avatar-color="primary"
              :status="isActiveStatus(entity) ? $t('Active') : $t('Inactive')"
              :status-color="isActiveStatus(entity) ? 'success' : 'secondary'"
              clickable
              @click="openDeptView(entity.id)"
            >
              <template #badges>
                <VChip
                  v-if="entity.positions_count"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="cursor-pointer"
                  @click.stop="showDepartmentPositions(entity)"
                >
                  {{ entity.positions_count }} {{ $t('departments.kpi.positions') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="info"
                >
                  {{ entity.employees_count ?? 0 }} {{ $t('departments.col.employees') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ entity.teams_count ?? 0 }} {{ $t('departments.col.teams') }}
                </VChip>
              </template>

              <template #content>
                <div class="d-flex flex-column gap-1 text-caption">
                  <div
                    v-if="entity.manager_id"
                    class="d-flex align-center gap-1 min-w-0"
                  >
                    <VIcon
                      icon="tabler-user-star"
                      size="14"
                      class="text-medium-emphasis shrink-0"
                    />
                    <span class="text-medium-emphasis shrink-0">{{ $t('departments.col.manager') }}:</span>
                    <span class="font-weight-medium text-truncate">{{ getManagerName(entity.manager_id) }}</span>
                  </div>
                  <div
                    v-if="entity.parent_department_id"
                    class="d-flex align-center gap-1 min-w-0"
                  >
                    <VIcon
                      icon="tabler-building"
                      size="14"
                      class="text-medium-emphasis shrink-0"
                    />
                    <span class="text-medium-emphasis shrink-0">{{ $t('departments.col.parent') }}:</span>
                    <span class="text-truncate">{{ getDeptName(entity.parent_department_id) }}</span>
                  </div>
                  <div
                    v-if="localizedValue(entity.description, '')"
                    class="line-clamp-2 text-medium-emphasis mt-1"
                  >
                    {{ localizedValue(entity.description, '') }}
                  </div>
                </div>
              </template>

              <template #footer>
                <VBtn
                  variant="text"
                  size="small"
                  prepend-icon="tabler-eye"
                  @click.stop="openDeptView(entity.id)"
                >
                  {{ $t('View') }}
                </VBtn>
                <VBtn
                  v-if="$can('update', 'PeopleSkills')"
                  variant="text"
                  size="small"
                  prepend-icon="tabler-edit"
                  @click.stop="openDeptDialog(entity)"
                >
                  {{ $t('Edit') }}
                </VBtn>
              </template>
            </WpEntityCard>
          </template>
        </WpEntityCollection>
      </template>

      <template #tab-positions>
        <WpEntityCollection
          v-model:view-mode="orgViewMode"
          v-model:page="posListPage.page.value"
          v-model:items-per-page="posListPage.itemsPerPage.value"
          v-model:sort-by="posListPage.sortBy.value"
          :headers="posHeaders"
          :items="posStore.list"
          :total-items="posStore.totalItems"
          :loading="posStore.loading"
          :error="posStore.error"
          hide-view-mode-toggle
          :grid-cols="{ cols: 12, sm: 6, md: 4, lg: 4 }"
          table-density="compact"
          mobile-breakpoint="sm"
          empty-icon="tabler-briefcase"
          :empty-title="$t('departments.list.tab_positions')"
          :empty-subtitle="$t('departments.list.subtitle')"
          @retry="posListPage.refresh()"
        >
          <template #empty-action>
            <VBtn
              v-if="$can('create', 'PeopleSkills')"
              color="primary"
              prepend-icon="tabler-plus"
              @click="openPosDialog"
            >
              {{ $t('positions.add') }}
            </VBtn>
          </template>

          <template #item.title="{ item }">
            <div class="d-flex flex-column">
              <button
                type="button"
                class="entity-link"
                @click="openPosView(item.id)"
              >
                {{ localizedValue(item.title) }}
              </button>
              <span
                v-if="item.code"
                class="text-caption text-medium-emphasis"
              >
                {{ item.code }}
              </span>
            </div>
          </template>

          <template #item.department_id="{ item }">
            {{ getDeptName(item.department_id) }}
          </template>

          <template #item.standard_hourly_rate="{ item }">
            {{ formatRate(item.standard_hourly_rate) }}
          </template>

          <template #item.employees_count="{ item }">
            <VChip
              v-if="item.employees_count"
              size="x-small"
              variant="tonal"
              color="info"
            >
              {{ item.employees_count }}
            </VChip>
            <span
              v-else
              class="text-medium-emphasis"
            >—</span>
          </template>

          <template #item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template #item.status="{ item }">
            <WpStatusChip
              type="position"
              :status="isActiveStatus(item) ? 'active' : 'inactive'"
              :label="isActiveStatus(item) ? $t('Active') : $t('Inactive')"
              dot
              :pulse="isActiveStatus(item)"
            />
          </template>

          <template #item.actions="{ item }">
            <WpRowActions
              :actions="rowActions"
              @view="openPosView(item.id)"
              @edit="openPosDialog(item)"
              @delete="askDeletePos(item)"
            />
          </template>

          <template #mobile-card="{ item }">
            <WpMobileCard
              class="department-position-mobile-card"
              :title="localizedValue(item.title)"
              :subtitle="item.code"
              avatar-icon="tabler-briefcase"
              avatar-color="primary"
              :status="isActiveStatus(item) ? $t('Active') : $t('Inactive')"
              :status-color="isActiveStatus(item) ? 'success' : 'secondary'"
              clickable
              @click="openPosView(item.id)"
            >
              <template #badges>
                <VChip
                  v-if="item.employees_count"
                  size="x-small"
                  variant="tonal"
                  color="info"
                >
                  {{ item.employees_count }} {{ $t('positions.kpi.employees') }}
                </VChip>
              </template>

              <template #actions>
                <WpRowActions
                  :actions="rowActions"
                  @view="openPosView(item.id)"
                  @edit="openPosDialog(item)"
                  @delete="askDeletePos(item)"
                />
              </template>

              <template #details>
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
                  <span class="wp-mobile-row__label">{{ $t('positions.col.department') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ getDeptName(item.department_id) }}</span>
                </div>
                <div
                  v-if="item.standard_hourly_rate"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-currency-euro"
                    color="success"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('positions.col.rate') }}</span>
                  <span class="wp-mobile-row__value font-weight-medium">{{ formatRate(item.standard_hourly_rate) }}</span>
                </div>
              </template>

              <template
                v-if="item.created_at"
                #footer
              >
                <span class="text-caption text-medium-emphasis">
                  {{ $t('positions.col.created_at') }}
                </span>
                <span class="text-caption font-weight-medium">
                  {{ formatDate(item.created_at) }}
                </span>
              </template>
            </WpMobileCard>
          </template>

          <template #card="{ entity }">
            <WpEntityCard
              class="department-position-entity-card"
              :entity="entity"
              :title="localizedValue(entity.title)"
              :subtitle="entity.code"
              avatar-icon="tabler-briefcase"
              avatar-color="primary"
              :status="isActiveStatus(entity) ? $t('Active') : $t('Inactive')"
              :status-color="isActiveStatus(entity) ? 'success' : 'secondary'"
              clickable
              @click="openPosView(entity.id)"
            >
              <template #badges>
                <VChip
                  v-if="entity.employees_count"
                  size="x-small"
                  variant="tonal"
                  color="info"
                >
                  {{ entity.employees_count }} {{ $t('positions.kpi.employees') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ $t('positions.col.created_at') }}: {{ formatDate(entity.created_at) }}
                </VChip>
              </template>

              <template #content>
                <div class="d-flex flex-column gap-1 text-caption">
                  <div
                    v-if="entity.department_id"
                    class="d-flex align-center gap-1 min-w-0"
                  >
                    <VIcon
                      icon="tabler-building-community"
                      size="14"
                      class="text-medium-emphasis shrink-0"
                    />
                    <span class="text-medium-emphasis shrink-0">{{ $t('positions.col.department') }}:</span>
                    <span class="font-weight-medium text-truncate">{{ getDeptName(entity.department_id) }}</span>
                  </div>
                  <div
                    v-if="entity.standard_hourly_rate"
                    class="d-flex align-center gap-1 min-w-0"
                  >
                    <VIcon
                      icon="tabler-currency-euro"
                      size="14"
                      color="success"
                      class="shrink-0"
                    />
                    <span class="text-medium-emphasis shrink-0">{{ $t('positions.col.rate') }}:</span>
                    <span class="font-weight-medium text-truncate">{{ formatRate(entity.standard_hourly_rate) }}</span>
                  </div>
                  <div
                    v-if="localizedValue(entity.description, '')"
                    class="line-clamp-2 text-medium-emphasis mt-1"
                  >
                    {{ localizedValue(entity.description, '') }}
                  </div>
                </div>
              </template>

              <template #footer>
                <VBtn
                  variant="text"
                  size="small"
                  prepend-icon="tabler-eye"
                  @click.stop="openPosView(entity.id)"
                >
                  {{ $t('View') }}
                </VBtn>
                <VBtn
                  v-if="$can('update', 'PeopleSkills')"
                  variant="text"
                  size="small"
                  prepend-icon="tabler-edit"
                  @click.stop="openPosDialog(entity)"
                >
                  {{ $t('Edit') }}
                </VBtn>
              </template>
            </WpEntityCard>
          </template>
        </WpEntityCollection>
      </template>
    </AppHybridTabs>

    <AppBottomNav
      v-if="smAndDown"
      v-model="activeTab"
      :items="bottomNavItems"
      fit-content
      :show-badges="false"
      aria-label="Departments and positions"
    />

    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="activeFilterCount"
      :title="filterDialogTitle"
      @apply="applyStagedFilters"
      @reset="resetStagedFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="activeListPage.stagedFilters.value.status"
            :label="$t(activeTab === 'departments' ? 'departments.col.status' : 'positions.col.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-if="activeTab === 'departments'"
            v-model="deptListPage.stagedFilters.value.parent_department_id"
            :label="$t('departments.col.parent')"
            :items="deptOptions"
            variant="outlined"
            clearable
          />
          <AppSelect
            v-else
            v-model="posListPage.stagedFilters.value.department_id"
            :label="$t('positions.col.department')"
            :items="deptOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <ViewDepartmentDialog
      :model-value="deptViewDialog.isOpen.value"
      :department="viewingDept"
      :fields="deptDetailFields"
      :children="deptChildren"
      :loading="deptViewLoading"
      :error="deptViewError"
      :active="isActiveStatus(viewingDept)"
      :can-edit="$can('update', 'PeopleSkills')"
      :can-create="$can('create', 'PeopleSkills')"
      @update:model-value="value => !value && deptViewDialog.close()"
      @retry="retryDeptView"
      @edit="editDeptFromView"
      @show-positions="showDepartmentPositions"
      @add-position="addPositionForDepartment"
      @open-position="openPosView"
      @open-department="openDeptView"
    />

    <ViewPositionDialog
      :model-value="posViewDialog.isOpen.value"
      :position="viewingPos"
      :fields="posDetailFields"
      :loading="posViewLoading"
      :error="posViewError"
      :active="isActiveStatus(viewingPos)"
      :can-edit="$can('update', 'PeopleSkills')"
      @update:model-value="value => !value && posViewDialog.close()"
      @retry="retryPosView"
      @edit="editPosFromView"
    />

    <DepartmentFormDialog
      :model-value="deptDialog.isOpen.value"
      :item-id="deptDialog.item.value"
      :form="deptForm"
      :parent-options="parentOptions"
      :manager-options="managerOptions"
      :position-options="positionOptions"
      :status-options="statusOptions"
      :rules="rules"
      :loading="deptFormLoading"
      :saving="deptSaving"
      @update:model-value="value => !value && closeDeptDialog()"
      @submit="saveDept"
    />

    <WpDeleteDialog
      v-model="deptDelDialog.isOpen.value"
      :title="$t('Delete')"
      :description="deptDeleteDescription"
      :loading="deptDeleting"
      :confirm-action="deleteDept"
      @update:model-value="value => !value && deptDelDialog.close()"
    />

    <PositionFormDialog
      :model-value="posDialog.isOpen.value"
      :item-id="posDialog.item.value"
      :form="posForm"
      :department-options="deptOptions"
      :status-options="statusOptions"
      :rules="rules"
      :loading="posFormLoading"
      :saving="posSaving"
      @update:model-value="value => !value && closePosDialog()"
      @submit="savePos"
    />

    <WpDeleteDialog
      v-model="posDelDialog.isOpen.value"
      :title="$t('Delete')"
      :description="posDeleteDescription"
      :loading="posDeleting"
      :confirm-action="deletePos"
      @update:model-value="value => !value && posDelDialog.close()"
    />

    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none departments-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.departments-list-quick-actions {
  inset-inline-end: 25px;
}

.departments-list__kpis {
  margin-block-end: 0.5rem;
}

.departments-list__toolbar {
  padding-block: 0.5rem;
}

.departments-list__tabs {
  margin-block-start: 0.5rem;
}

.entity-link {
  padding: 0;
  border: 0;
  background: none;
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  font: inherit;
  font-weight: 600;
  text-align: start;
}

.entity-link:hover {
  text-decoration: underline;
}

.kpi-clickable {
  cursor: pointer;
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

@media (max-width: 959px) {
  .people-skills-departments-list {
    padding-block-end: 7rem;
  }

  .departments-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
