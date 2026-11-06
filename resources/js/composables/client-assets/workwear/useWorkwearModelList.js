/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useWorkwearModelList() {
  const store = useClientAssetStore()
  const { t } = useI18n()

  const allowedSortFields = new Set(['id', 'model_ref', 'name', 'status', 'created_at'])

  const clientOptions = ref([])

  const statusMap = {
    draft: 'secondary',
    active: 'success',
    discontinued: 'error',
  }

  const statusOptions = [
    { title: t('Draft'), value: 'draft' },
    { title: t('Active'), value: 'active' },
    { title: t('Discontinued'), value: 'discontinued' },
  ]

  const headers = computed(() => [
    { title: t('Model Ref'), key: 'model_ref', sortable: true },
    { title: t('Name & Spec'), key: 'name', sortable: true },
    { title: t('Client'), key: 'client', sortable: false },
    { title: t('Silhouette & Colors'), key: 'garment_types', sortable: false },
    { title: t('Customizations'), key: 'placements', sortable: false, align: 'center' },
    { title: t('Stock / Items'), key: 'items_count', sortable: false, align: 'center' },
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

      return await store.fetchWorkwearModels(query)
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
      console.error('Failed to load model filter options:', err)
    }
  }

  onMounted(() => {
    loadFilterOptions()
  })

  return {
    ...listPage,
    items: listPage.rows,
    models: listPage.rows,
    totalItems: listPage.totalItems,
    totalModels: listPage.totalItems,
    headers,
    statusMap,
    statusOptions,
    clientOptions,
    selectedIds: selection.selected,
    selectedModels: selection.selected,
    selectionCount: selection.selectionCount,
    hasSelection: selection.hasSelection,
    clearSelection: selection.clearSelection,
    loadFilterOptions,
  }
}
