/* eslint-disable camelcase */
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useWorkwearItemList() {
  const store = useClientAssetStore()
  const { t } = useI18n()

  const allowedSortFields = new Set(['id', 'item_code', 'sku', 'status', 'created_at'])

  const clientOptions = ref([])
  const modelOptions = ref([])
  const showStockMatrix = ref(false)
  const stockMatrix = ref([])
  const loadingStockMatrix = ref(false)

  const statusMap = {
    in_stock: 'success',
    available: 'success',
    assigned: 'info',
    returned: 'secondary',
    repair_needed: 'warning',
    retired: 'secondary',
    lost: 'error',
    stolen: 'error',
  }

  const conditionMap = {
    new: 'success',
    good: 'info',
    fair: 'warning',
    worn: 'secondary',
    damaged: 'error',
  }

  const statusOptions = [
    { title: t('In Stock'), value: 'in_stock' },
    { title: t('Available'), value: 'available' },
    { title: t('Assigned'), value: 'assigned' },
    { title: t('Returned'), value: 'returned' },
    { title: t('Repair Needed'), value: 'repair_needed' },
    { title: t('Retired'), value: 'retired' },
    { title: t('Stolen / Lost'), value: 'stolen' },
  ]

  const conditionOptions = [
    { title: t('New'), value: 'new' },
    { title: t('Good'), value: 'good' },
    { title: t('Fair'), value: 'fair' },
    { title: t('Worn'), value: 'worn' },
    { title: t('Damaged'), value: 'damaged' },
  ]

  const headers = computed(() => [
    { title: t('Item Code / SKU'), key: 'item_code', sortable: true },
    { title: t('Model & Client'), key: 'workwear_model', sortable: false },
    { title: t('Variant (Color / Size)'), key: 'size', sortable: false },
    { title: t('Supplier / Cost'), key: 'supplier', sortable: false },
    { title: t('Status'), key: 'status', sortable: true },
    { title: t('Condition'), key: 'item_condition', sortable: false },
    { title: t('Issued Staff'), key: 'current_assignment', sortable: false },
    { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  const fetchStockSummary = async () => {
    if (!showStockMatrix.value) return
    loadingStockMatrix.value = true
    try {
      stockMatrix.value = await store.fetchWorkwearStock({
        client_id: listPage.filters.client_id || undefined,
        model_id: listPage.filters.workwear_model_id || listPage.filters.model_id || undefined,
      })
    } catch (err) {
      console.error('Failed to load stock matrix:', err)
    } finally {
      loadingStockMatrix.value = false
    }
  }

  const listPage = useListPage({
    fetchFn: async params => {
      const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'created_at'

      const sort = sortField
        ? `${params.sort_order === 'desc' ? '-' : ''}${sortField}`
        : '-created_at'

      const modelId = params.workwear_model_id || params.model_id

      const query = {
        page: params.page,
        itemsPerPage: params.per_page,
        q: params.search,
        sort,
        'filter[status]': params.status || undefined,
        'filter[item_condition]': params.item_condition || undefined,
        'filter[client_id]': params.client_id || undefined,
        'filter[model_id]': modelId || undefined,
      }

      const res = await store.fetchWorkwearItems(query)

      if (showStockMatrix.value) {
        fetchStockSummary()
      }

      return res
    },
    initialFilters: {
      status: null,
      item_condition: null,
      client_id: null,
      workwear_model_id: null,
    },
    debounceMs: 400,
  })

  const selection = useSelection()

  const loadFilterOptions = async () => {
    try {
      const [clientsRes, modelsRes] = await Promise.all([
        store.fetchClientOptions({ limit: 150 }),
        store.fetchWorkwearModels({ itemsPerPage: -1 }),
      ])

      clientOptions.value = clientsRes
      modelOptions.value = (modelsRes.data || []).map(m => ({
        title: `${m.model_ref} — ${m.name}`,
        value: m.id,
      }))
    } catch (err) {
      console.error('Failed to load item filter options:', err)
    }
  }

  onMounted(() => {
    loadFilterOptions()
  })

  return {
    ...listPage,
    items: listPage.rows,
    totalItems: listPage.totalItems,
    headers,
    statusMap,
    conditionMap,
    statusOptions,
    conditionOptions,
    clientOptions,
    modelOptions,
    showStockMatrix,
    stockMatrix,
    loadingStockMatrix,
    fetchStockSummary,
    selectedIds: selection.selected,
    selectedItems: selection.selected,
    selectionCount: selection.selectionCount,
    hasSelection: selection.hasSelection,
    clearSelection: selection.clearSelection,
    loadFilterOptions,
  }
}
