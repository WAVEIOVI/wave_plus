<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpBulkActions,
  WpDeleteDialog,
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useWorkwearActions } from '@/composables/client-assets/workwear/useWorkwearActions'
import { useWorkwearDialogs } from '@/composables/client-assets/workwear/useWorkwearDialogs'
import { useWorkwearItemList } from '@/composables/client-assets/workwear/useWorkwearItemList'
import { resolveColorHex } from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import AddNewItemDialog from '@/views/client-assets/workwear/items/list/AddNewItemDialog.vue'
import AssignWorkwearDialog from '@/views/client-assets/workwear/items/list/AssignWorkwearDialog.vue'
import BatchCreateItemsDialog from '@/views/client-assets/workwear/items/list/BatchCreateItemsDialog.vue'
import ReturnWorkwearDialog from '@/views/client-assets/workwear/items/list/ReturnWorkwearDialog.vue'
import ViewItemDialog from '@/views/client-assets/workwear/items/list/ViewItemDialog.vue'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()
const store = useClientAssetStore()

// ── Item List Orchestration ────────────────────────────────────────────────
const {
  items,
  totalItems,
  loading,
  error,
  searchQuery,
  filters,
  stagedFilters,
  activeFilters,
  activeFilterCount,
  setFilter,
  removeFilter,
  resetFilters,
  applyStaged,
  resetStaged,
  page,
  itemsPerPage,
  sortBy,
  refresh,
  headers,
  statusOptions,
  conditionOptions,
  clientOptions,
  modelOptions,
  statusMap,
  conditionMap,
  selectedIds,
  clearSelection,
  showStockMatrix,
  stockMatrix,
  loadingStockMatrix,
  toggleStockMatrix,
  loadFilterOptions,
} = useWorkwearItemList()

const isFilterDialogOpen = ref(false)

onMounted(() => {
  loadFilterOptions()
})

// ── Dialogs & Actions ──────────────────────────────────────────────────────
const dialogs = useWorkwearDialogs()

const actions = useWorkwearActions({
  onRefresh: refresh,
})

const isBatchCreateDialogOpen = ref(false)
const isAssignDialogOpen = ref(false)
const isReturnDialogOpen = ref(false)
const itemsToAssign = ref([])

const handleView = item => {
  dialogs.openView(item, 'item')
}

const handleEdit = item => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'item' } })
}

const handleAssign = item => {
  itemsToAssign.value = [item]
  isAssignDialogOpen.value = true
}

const handleReturn = item => {
  dialogs.openView(item, 'item')
  isReturnDialogOpen.value = true
}

const handleDelete = item => {
  dialogs.openDelete(item, 'item')
}

const handleConfirmDelete = async () => {
  if (dialogs.activeEntity.value?.id) {
    const success = await actions.deleteItem(dialogs.activeEntity.value.id)
    if (success) {
      dialogs.closeDelete()
    }
  }
}

const handleBulkDelete = async () => {
  for (const id of selectedIds.value) {
    await actions.deleteItem(id)
  }
  clearSelection()
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = item => {
  const rowActions = [
    { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
    { label: t('Edit Item'), icon: 'tabler-pencil', emit: 'edit' },
  ]

  if (item?.status === 'in_stock' || item?.status === 'available' || item?.status === 'returned') {
    rowActions.push({ label: t('Assign to Staff'), icon: 'tabler-user-plus', emit: 'assign' })
  }

  if (item?.status === 'assigned') {
    rowActions.push({ label: t('Return / Inspect'), icon: 'tabler-clipboard-check', emit: 'return' })
  }

  rowActions.push(
    { label: t('Download QR'), icon: 'tabler-qrcode', emit: 'download-qr' },
    { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
  )

  return rowActions
}

defineExpose({
  openAdd: () => dialogs.openAdd('item'),
  openBatch: () => { isBatchCreateDialogOpen.value = true },
  refresh,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="searchQuery"
    :search-placeholder="$t('Search items by barcode, serial, size...')"
    :create-label="$t('New Item')"
    hide-create-on-mobile
    show-filter
    hide-filter-on-mobile
    :active-filter-count="activeFilterCount"
    :total-items="totalItems"
    :loading="loading"
    @create="dialogs.openAdd('item')"
    @open-filters="isFilterDialogOpen = true"
    @refresh="refresh"
  >
    <template #prepend>
      <VAutocomplete
        :model-value="filters.client_id"
        :label="$t('Filter by Client')"
        :items="clientOptions"
        density="compact"
        clearable
        style="inline-size: 195px;"
        class="me-2"
        @update:model-value="val => setFilter('client_id', val)"
      />
      <VAutocomplete
        :model-value="filters.workwear_model_id"
        :label="$t('Filter by Model')"
        :items="modelOptions"
        density="compact"
        clearable
        style="inline-size: 210px;"
        class="me-2"
        @update:model-value="val => setFilter('workwear_model_id', val)"
      />
      <VSelect
        :model-value="filters.status"
        :label="$t('Filter by Status')"
        :items="statusOptions"
        density="compact"
        clearable
        style="inline-size: 165px;"
        class="me-2"
        @update:model-value="val => setFilter('status', val)"
      />
      <VBtn
        :color="showStockMatrix ? 'info' : 'secondary'"
        :variant="showStockMatrix ? 'flat' : 'tonal'"
        prepend-icon="tabler-chart-grid-dots"
        class="me-2 d-none d-md-inline-flex"
        @click="toggleStockMatrix"
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

    <template #filter-chips>
      <WpActiveFilters
        v-if="activeFilters.length"
        :filters="activeFilters"
        @remove="removeFilter"
        @clear-all="resetFilters"
      />
    </template>
  </WpPageToolbar>

  <!-- 👉 Collapsible Variant Stock Availability Matrix -->
  <VExpandTransition>
    <VCard
      v-if="showStockMatrix"
      variant="outlined"
      class="pa-4 mb-4"
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

  <!-- 👉 Bulk Actions Bar -->
  <div
    v-if="selectedIds?.length"
    class="px-4 pb-2"
  >
    <WpBulkActions
      :selected-count="selectedIds?.length || 0"
      @clear="clearSelection"
      @delete="handleBulkDelete"
    />
  </div>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    v-model:selected="selectedIds"
    :headers="headers"
    :items="items"
    :total-items="totalItems"
    :loading="loading"
    :error="error"
    show-select
    item-value="id"
    @retry="refresh"
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
        :subtitle="item.sku || item.model?.name || $t('Workwear Item')"
        icon="tabler-hanger"
        :status="(item.status || 'draft').replace(/_/g, ' ')"
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
            v-if="item.color"
            size="x-small"
            variant="tonal"
            class="text-capitalize"
          >
            {{ item.color }}
          </VChip>
        </template>

        <template #details>
          <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
            <div v-if="item.client || item.model?.client">
              <VIcon
                icon="tabler-building-store"
                size="14"
                class="me-1"
              />
              {{ item.client?.legal_name || item.model?.client?.legal_name }}
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

  <!-- 👉 Filter Dialog -->
  <WpFilterDialog
    v-model:is-open="isFilterDialogOpen"
    :active-count="activeFilterCount"
    @apply="applyStaged"
    @reset="resetStaged"
  >
    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <VAutocomplete
          v-model="stagedFilters.client_id"
          :label="$t('Client')"
          :items="clientOptions"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VAutocomplete
          v-model="stagedFilters.workwear_model_id"
          :label="$t('Model')"
          :items="modelOptions"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VSelect
          v-model="stagedFilters.status"
          :label="$t('Status')"
          :items="statusOptions"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VSelect
          v-model="stagedFilters.item_condition"
          :label="$t('Condition')"
          :items="conditionOptions"
          clearable
        />
      </VCol>
    </VRow>
  </WpFilterDialog>

  <!-- 👉 Dialogs -->
  <AddNewItemDialog
    v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
    @item-created="refresh"
    @add-item="refresh"
  />

  <BatchCreateItemsDialog
    v-model:is-dialog-visible="isBatchCreateDialogOpen"
    @items-created="refresh"
  />

  <AssignWorkwearDialog
    v-model:is-dialog-visible="isAssignDialogOpen"
    :items="itemsToAssign"
    @assigned="refresh"
  />

  <ReturnWorkwearDialog
    v-model:is-dialog-visible="isReturnDialogOpen"
    :item="dialogs.activeEntity.value"
    @returned="refresh"
  />

  <ViewItemDialog
    v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
    :item="dialogs.activeEntity.value"
    :item-id="dialogs.activeEntity.value?.id"
  />

  <WpDeleteDialog
    v-model:is-open="dialogs.deleteDialog.isOpen.value"
    :title="$t('Delete Workwear Item')"
    :message="$t('Are you sure you want to delete workwear item {code}? This action cannot be undone.', { code: dialogs.activeEntity.value?.item_code || '' })"
    :loading="actions.isDeleting.value"
    @confirm="handleConfirmDelete"
  />
</template>
