/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useWorkwearEmployeeList() {
  const store = useClientAssetStore()
  const { t } = useI18n()

  const allowedSortFields = new Set(['id', 'employee_ref', 'department', 'status', 'created_at'])

  const clientOptions = ref([])

  const statusMap = {
    active: 'success',
    inactive: 'secondary',
    terminated: 'error',
  }

  const statusOptions = [
    { title: t('Active'), value: 'active' },
    { title: t('Inactive'), value: 'inactive' },
    { title: t('Terminated'), value: 'terminated' },
  ]

  const headers = computed(() => [
    { title: t('Employee Name'), key: 'full_name', sortable: false },
    { title: t('Badge / Ref'), key: 'employee_ref', sortable: true },
    { title: t('Client'), key: 'client', sortable: false },
    { title: t('Department & Contact'), key: 'department', sortable: true },
    { title: t('Sizes Summary'), key: 'sizes', sortable: false },
    { title: t('Issued Workwear Items'), key: 'assignments_count', sortable: false, align: 'center' },
    { title: t('Status'), key: 'status', sortable: true },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'created_at'

      const sort = sortField
        ? `${params.sort_order === 'desc' ? '-' : ''}${sortField}`
        : '-created_at'

      const query = {
        page: params.page,
        itemsPerPage: params.per_page,
        q: params.search,
        sort,
        'filter[status]': params.status || undefined,
        'filter[client_id]': params.client_id || undefined,
      }

      return await store.fetchWorkwearEmployees(query)
    },
    initialFilters: {
      status: null,
      client_id: null,
    },
    debounceMs: 400,
  })

  const selection = useSelection()

  const loadFilterOptions = async () => {
    try {
      clientOptions.value = await store.fetchClientOptions({ limit: 150 })
    } catch (err) {
      console.error('Failed to load employee filter options:', err)
    }
  }

  onMounted(() => {
    loadFilterOptions()
  })

  return {
    ...listPage,
    items: listPage.rows,
    employees: listPage.rows,
    totalItems: listPage.totalItems,
    totalEmployees: listPage.totalItems,
    headers,
    statusMap,
    statusOptions,
    clientOptions,
    selectedIds: selection.selected,
    selectedEmployees: selection.selected,
    selectionCount: selection.selectionCount,
    hasSelection: selection.hasSelection,
    clearSelection: selection.clearSelection,
    loadFilterOptions,
  }
}
