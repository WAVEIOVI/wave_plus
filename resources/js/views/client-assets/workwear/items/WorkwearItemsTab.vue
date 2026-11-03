<script setup>
/* eslint-disable camelcase */
import {
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { resolveColorHex } from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { $api } from '@/utils/api'
import AddNewItemDialog from '@/views/client-assets/workwear/items/list/AddNewItemDialog.vue'
import AssignWorkwearDialog from '@/views/client-assets/workwear/items/list/AssignWorkwearDialog.vue'
import BatchCreateItemsDialog from '@/views/client-assets/workwear/items/list/BatchCreateItemsDialog.vue'
import DeleteItemDialog from '@/views/client-assets/workwear/items/list/DeleteItemDialog.vue'
import ReturnWorkwearDialog from '@/views/client-assets/workwear/items/list/ReturnWorkwearDialog.vue'
import ViewItemDialog from '@/views/client-assets/workwear/items/list/ViewItemDialog.vue'
import { refDebounced } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const router = useRouter()

// ── Search & Filter ────────────────────────────────────────────────────────
const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 500)
const selectedStatus = ref()
const selectedClientId = ref()
const selectedModelId = ref()
const clientOptions = ref([])
const modelOptions = ref([])
const showStockMatrix = ref(false)
const stockMatrix = ref([])
const loadingStockMatrix = ref(false)

const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()

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

onMounted(async () => {
  try {
    const [clientsRes, modelsRes] = await Promise.all([
      $api('/bp/clients/options', { params: { limit: 150 } }),
      store.fetchWorkwearModels({ itemsPerPage: -1 }),
    ])

    clientOptions.value = (clientsRes.data || []).map(c => ({ title: c.legal_name, value: c.id }))
    modelOptions.value = (modelsRes.data || []).map(m => ({ title: `${m.model_ref} — ${m.name}`, value: m.id }))
  } catch (err) {
    console.error('Failed to load filter options:', err)
  }
})

const fetchStockSummary = async () => {
  if (!showStockMatrix.value) return
  loadingStockMatrix.value = true
  try {
    stockMatrix.value = await store.fetchWorkwearStock({
      client_id: selectedClientId.value || undefined,
      model_id: selectedModelId.value || undefined,
    })
  } catch (err) {
    console.error('Failed to load stock matrix:', err)
  } finally {
    loadingStockMatrix.value = false
  }
}

const fetchData = async () => {
  await store.fetchWorkwearItems({
    q: debouncedSearch.value,
    'filter[status]': selectedStatus.value || undefined,
    'filter[client_id]': selectedClientId.value || undefined,
    'filter[model_id]': selectedModelId.value || undefined,
    itemsPerPage: itemsPerPage.value,
    page: page.value,
    sortBy: sortBy.value,
  })
  if (showStockMatrix.value) {
    fetchStockSummary()
  }
}

watch([debouncedSearch, selectedStatus, selectedClientId, selectedModelId, itemsPerPage, page, sortBy], () => fetchData(), { immediate: true })
watch(showStockMatrix, val => {
  if (val) fetchStockSummary()
})

// ── Dialogs ────────────────────────────────────────────────────────────────
const isAddNewDialogOpen = ref(false)
const isBatchCreateDialogOpen = ref(false)
const isAssignDialogOpen = ref(false)
const isReturnDialogOpen = ref(false)
const isViewDialogOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const selectedItem = ref(null)
const itemsToAssign = ref([])

const handleView = item => {
  selectedItem.value = item
  isViewDialogOpen.value = true
}

const handleEdit = item => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'item' } })
}

const handleAssign = item => {
  selectedItem.value = item
  itemsToAssign.value = [item]
  isAssignDialogOpen.value = true
}

const handleReturn = item => {
  selectedItem.value = item
  isReturnDialogOpen.value = true
}

const handleDelete = item => {
  selectedItem.value = item
  isDeleteDialogOpen.value = true
}

const confirmDelete = async () => {
  if (!selectedItem.value?.id) return
  try {
    await store.deleteWorkwearItem(selectedItem.value.id)
    snackbarStore.showMessage(t('Workwear item deleted successfully'), 'success')
    fetchData()
  } catch (e) {
    snackbarStore.showMessage(e?.data?.message || t('Failed to delete item'), 'error')
  }
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = item => {
  const actions = [
    { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
    { label: t('Edit Item'), icon: 'tabler-pencil', emit: 'edit' },
  ]

  if (item?.status === 'in_stock' || item?.status === 'available' || item?.status === 'returned') {
    actions.push({ label: t('Assign to Staff'), icon: 'tabler-user-plus', emit: 'assign' })
  }

  if (item?.status === 'assigned') {
    actions.push({ label: t('Return / Inspect'), icon: 'tabler-clipboard-check', emit: 'return' })
  }

  actions.push(
    { label: t('Download QR'), icon: 'tabler-qrcode', emit: 'download-qr' },
    { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
  )

  return actions
}

defineExpose({
  openAdd: () => { isAddNewDialogOpen.value = true },
  openBatch: () => { isBatchCreateDialogOpen.value = true },
  refresh: fetchData,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="searchQuery"
    :search-placeholder="$t('Search items by barcode, serial, size...')"
    :create-label="$t('New Item')"
    hide-create-on-mobile
    :total-items="store.totalWorkwearItems"
    :loading="store.loading"
    @create="isAddNewDialogOpen = true"
    @refresh="fetchData"
  >
    <template #prepend>
      <VAutocomplete
        v-model="selectedClientId"
        :label="$t('Filter by Client')"
        :items="clientOptions"
        density="compact"
        clearable
        style="inline-size: 195px;"
        class="me-2"
      />
      <VAutocomplete
        v-model="selectedModelId"
        :label="$t('Filter by Model')"
        :items="modelOptions"
        density="compact"
        clearable
        style="inline-size: 210px;"
        class="me-2"
      />
      <VSelect
        v-model="selectedStatus"
        :label="$t('Filter by Status')"
        :items="statusOptions"
        density="compact"
        clearable
        style="inline-size: 165px;"
        class="me-2"
      />
      <VBtn
        :color="showStockMatrix ? 'info' : 'secondary'"
        :variant="showStockMatrix ? 'flat' : 'tonal'"
        prepend-icon="tabler-chart-grid-dots"
        class="me-2 d-none d-md-inline-flex"
        @click="showStockMatrix = !showStockMatrix"
      >
        {{ $t('Stock Matrix') }}
      </VBtn>
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-packages"
        class="me-2 d-none d-sm-inline-flex"
        @click="isBatchCreateDialogOpen = true"
      >
        {{ $t('Batch Intake') }}
      </VBtn>
    </template>
  </WpPageToolbar>

  <!-- 👉 Collapsible Variant Stock Availability Matrix -->
  <VExpandTransition>
    <VCard
      v-if="showStockMatrix"
      variant="outlined"
      class="pa-4"
    >
      <div class="d-flex justify-space-between align-center mb-3">
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            <VIcon
              icon="tabler-chart-grid-dots"
              size="18"
              class="me-1 text-primary"
            />
            {{ $t('Variant Stock Availability Matrix (By Model, Size & Color)') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ $t('Live breakdown of in-stock vs assigned garments across active variants.') }}
          </span>
        </div>
        <VBtn
          icon="tabler-x"
          size="x-small"
          variant="text"
          @click="showStockMatrix = false"
        />
      </div>
      <div
        v-if="loadingStockMatrix"
        class="py-4 text-center"
      >
        <VProgressCircular
          indeterminate
          size="28"
          color="primary"
        />
      </div>
      <VTable
        v-else-if="stockMatrix.length"
        density="compact"
        class="border rounded"
      >
        <thead>
          <tr>
            <th>{{ $t('SKU') }}</th>
            <th>{{ $t('Color') }}</th>
            <th>{{ $t('Size') }}</th>
            <th class="text-center">
              {{ $t('In Stock (Available)') }}
            </th>
            <th class="text-center">
              {{ $t('Issued (Assigned)') }}
            </th>
            <th class="text-center">
              {{ $t('Total') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, idx) in stockMatrix.slice(0, 20)"
            :key="idx"
          >
            <td class="font-mono text-caption font-weight-bold">
              {{ row.sku || '—' }}
            </td>
            <td class="text-capitalize">
              {{ row.color || '—' }}
            </td>
            <td>
              <VChip
                size="x-small"
                variant="outlined"
              >
                {{ row.size || '—' }}
              </VChip>
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                color="success"
                variant="tonal"
              >
                {{ row.available }}
              </VChip>
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ row.assigned }}
              </VChip>
            </td>
            <td class="text-center font-weight-bold">
              {{ row.total }}
            </td>
          </tr>
        </tbody>
      </VTable>
      <div
        v-else
        class="text-caption text-disabled text-center py-3"
      >
        {{ $t('No stock variants match the current filters.') }}
      </div>
    </VCard>
  </VExpandTransition>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    :headers="headers"
    :items="store.workwearItems"
    :total-items="store.totalWorkwearItems"
    :loading="store.loading"
    item-value="id"
    @retry="fetchData"
  >
    <!-- Item Code & SKU column -->
    <template #item.item_code="{ item }">
      <div class="d-flex align-center gap-3 py-2">
        <VAvatar
          color="info"
          variant="tonal"
          rounded
          size="36"
        >
          <VImg
            v-if="item.photos?.[0]?.thumb || item.photos?.[0]?.url"
            :src="item.photos[0].thumb || item.photos[0].url"
            cover
          />
          <VIcon
            v-else
            icon="tabler-hanger"
            size="20"
          />
        </VAvatar>
        <div class="d-flex flex-column">
          <span
            class="font-weight-medium text-high-emphasis cursor-pointer text-link font-mono"
            @click="handleView(item)"
          >
            {{ item.item_code }}
          </span>
          <span class="text-caption text-disabled font-mono">
            {{ item.sku ? `SKU: ${item.sku}` : (item.serial_number ? `S/N: ${item.serial_number}` : $t('Standard Variant')) }}
          </span>
        </div>
      </div>
    </template>

    <!-- Model & Client column -->
    <template #item.workwear_model="{ item }">
      <div class="py-1">
        <span class="font-weight-medium d-block">
          {{ item.model?.name || item.workwear_model?.name || item.model_name || '—' }}
        </span>
        <span class="text-caption text-disabled">
          {{ item.client?.legal_name || item.model?.model_ref || '—' }}
        </span>
      </div>
    </template>

    <!-- Variant (Color / Size / Garment Type) column -->
    <template #item.size="{ item }">
      <div class="d-flex align-center flex-wrap gap-1">
        <span
          v-if="item.color"
          class="d-inline-block rounded-circle border me-1"
          :style="{
            inlineSize: '12px',
            blockSize: '12px',
            backgroundColor: resolveColorHex(item.color),
          }"
          :title="item.color"
        />
        <VChip
          v-if="item.size"
          size="x-small"
          variant="outlined"
          class="font-weight-bold"
        >
          {{ item.size }}
        </VChip>
        <VChip
          v-if="item.color"
          size="x-small"
          variant="tonal"
          class="text-capitalize"
        >
          {{ item.color }}
        </VChip>
        <VChip
          v-if="item.garment_type"
          size="x-small"
          variant="tonal"
          color="secondary"
          class="text-capitalize"
        >
          {{ item.garment_type.replace(/_/g, ' ') }}
        </VChip>
      </div>
    </template>

    <!-- Supplier / Cost column -->
    <template #item.supplier="{ item }">
      <div class="d-flex flex-column">
        <span class="text-body-2">
          {{ item.supplier?.legal_name || item.model?.supplier?.legal_name || '—' }}
        </span>
        <span
          v-if="item.unit_cost !== null && item.unit_cost !== undefined"
          class="text-caption text-medium-emphasis font-mono"
        >
          {{ Number(item.unit_cost).toFixed(3) }} TND
        </span>
      </div>
    </template>

    <!-- Status column -->
    <template #item.status="{ item }">
      <VChip
        :color="statusMap[item.status] || 'secondary'"
        size="small"
        label
        class="text-capitalize font-weight-medium"
      >
        {{ (item.status || 'draft').replace(/_/g, ' ') }}
      </VChip>
    </template>

    <!-- Condition column -->
    <template #item.item_condition="{ item }">
      <VChip
        :color="conditionMap[item.item_condition] || 'secondary'"
        size="small"
        variant="tonal"
        class="text-capitalize"
      >
        {{ item.item_condition || 'good' }}
      </VChip>
    </template>

    <!-- Assigned Staff column -->
    <template #item.current_assignment="{ item }">
      <span v-if="item.assigned_to_employee || item.current_assignment?.employee">
        {{ item.assigned_to_employee?.full_name || item.current_assignment?.employee?.full_name }}
      </span>
      <span
        v-else
        class="text-medium-emphasis"
      >
        {{ $t('Unassigned') }}
      </span>
    </template>

    <!-- Actions column -->
    <template #item.actions="{ item }">
      <WpRowActions
        :actions="getRowActions(item)"
        @view="handleView(item)"
        @edit="handleEdit(item)"
        @assign="handleAssign(item)"
        @return="handleReturn(item)"
        @download-qr="store.downloadItemQr(item.id)"
        @delete="handleDelete(item)"
      />
    </template>

    <!-- Mobile Card slot -->
    <template #mobile-card="{ item }">
      <WpMobileCard
        :title="item.item_code"
        :subtitle="item.model?.name || item.workwear_model?.name || ''"
        icon="tabler-hanger"
        :status="item.status"
        :status-color="statusMap[item.status] || 'secondary'"
        @click="handleView(item)"
      >
        <template #badges>
          <VChip
            v-if="item.size"
            size="x-small"
            variant="outlined"
          >
            {{ item.size }}
          </VChip>
          <VChip
            :color="conditionMap[item.item_condition] || 'secondary'"
            size="x-small"
            variant="tonal"
            class="text-capitalize"
          >
            {{ item.item_condition || 'good' }}
          </VChip>
        </template>

        <template #details>
          <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
            <div v-if="item.serial_number">
              <VIcon
                icon="tabler-barcode"
                size="14"
                class="me-1"
              />
              S/N: {{ item.serial_number }}
            </div>
            <div v-if="item.garment_type">
              <VIcon
                icon="tabler-tag"
                size="14"
                class="me-1"
              />
              {{ (item.garment_type || '').replace(/_/g, ' ') }}
            </div>
            <div v-if="item.assigned_to_employee || item.current_assignment?.employee">
              <VIcon
                icon="tabler-user"
                size="14"
                class="me-1"
              />
              {{ item.assigned_to_employee?.full_name || item.current_assignment?.employee?.full_name }}
            </div>
          </div>
        </template>

        <template #actions>
          <WpRowActions
            :actions="getRowActions(item)"
            @view="handleView(item)"
            @edit="handleEdit(item)"
            @assign="handleAssign(item)"
            @return="handleReturn(item)"
            @download-qr="store.downloadItemQr(item.id)"
            @delete="handleDelete(item)"
          />
        </template>
      </WpMobileCard>
    </template>
  </WpResponsiveList>

  <!-- 👉 Dialogs -->
  <AddNewItemDialog
    v-model:is-dialog-visible="isAddNewDialogOpen"
    @item-created="fetchData"
    @add-item="fetchData"
  />

  <BatchCreateItemsDialog
    v-model:is-dialog-visible="isBatchCreateDialogOpen"
    @items-created="fetchData"
  />

  <AssignWorkwearDialog
    v-model:is-dialog-visible="isAssignDialogOpen"
    :items="itemsToAssign"
    @assigned="fetchData"
  />

  <ReturnWorkwearDialog
    v-model:is-dialog-visible="isReturnDialogOpen"
    :item="selectedItem"
    @returned="fetchData"
  />

  <ViewItemDialog
    v-model:is-dialog-visible="isViewDialogOpen"
    :item="selectedItem"
  />

  <DeleteItemDialog
    v-model:is-dialog-visible="isDeleteDialogOpen"
    @confirm-delete="confirmDelete"
  />
</template>
