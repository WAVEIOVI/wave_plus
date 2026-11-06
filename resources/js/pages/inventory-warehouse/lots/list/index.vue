<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpDeleteDialog,
  WpErrorBanner,
  WpFilterDialog,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useStockLotActions } from '@/composables/inventory/lots/useStockLotActions'
import { useStockLotDialogs } from '@/composables/inventory/lots/useStockLotDialogs'
import { useStockLotList } from '@/composables/inventory/lots/useStockLotList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import AddEditStockLotDialog from '@/views/inventory/lots/AddEditStockLotDialog.vue'
import ViewStockLotDialog from '@/views/inventory/lots/ViewStockLotDialog.vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/inventory-warehouse/lots/list',
  name: 'inventory-lots-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryLots',
  },
})

const { t } = useI18n()
const ability = useAbility()

const {
  lots,
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
  resolveStatusVariant,
  resolveStatusIcon,
} = useStockLotList()

const {
  createDialog,
  editDialog,
  deleteDialog,
  detailDialog,
  activeLot,
  openCreate,
  closeCreate,
  openEdit,
  closeEdit,
  openDelete,
  closeDelete,
  openDetail,
  closeDetail,
} = useStockLotDialogs()

const actions = useStockLotActions({ onRefresh: refresh })

// Filter dialog
const filterDialogOpen = ref(false)

const openFilters = () => {
  stageFilters()
  filterDialogOpen.value = true
}

const applyFilters = () => {
  applyStaged()
  filterDialogOpen.value = false
}

const resetFilterDialog = () => {
  resetStaged()
  applyStaged()
  filterDialogOpen.value = false
}

// Submit handlers
const handleCreateSubmit = async payload => {
  const ok = await actions.createLot(payload)
  if (ok) closeCreate()
}

const handleEditSubmit = async payload => {
  if (!activeLot.value?.id) return
  const ok = await actions.updateLot(activeLot.value.id, payload)
  if (ok) closeEdit()
}

const handleDelete = async () => {
  if (!activeLot.value?.id) return
  const ok = await actions.deleteLot(activeLot.value.id)
  if (ok) closeDelete()
}

// Row actions
const rowActions = lot => {
  const acts = [
    {
      label: t('inventory.lots.actions.view'),
      icon: 'tabler-eye',
      action: () => openDetail(lot),
    },
  ]

  if (ability.can('update', 'InventoryLots')) {
    acts.push({
      label: t('inventory.lots.actions.edit'),
      icon: 'tabler-edit',
      action: () => openEdit(lot),
    })
  }
  if (ability.can('delete', 'InventoryLots')) {
    acts.push({
      label: t('inventory.lots.actions.delete'),
      icon: 'tabler-trash',
      color: 'error',
      action: () => openDelete(lot),
    })
  }

  return acts
}

const formatDate = value => {
  if (!value) return '—'

  return new Date(value).toLocaleDateString()
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('inventory.lots.title')"
      :subtitle="t('inventory.lots.subtitle')"
      icon="tabler-tag"
    >
      <template #actions>
        <VBtn
          v-if="ability.can('create', 'InventoryLots')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ t('inventory.lots.add') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpErrorBanner
      v-if="error"
      :error="error"
      class="mb-4"
      @retry="refresh"
    />

    <!-- Toolbar -->
    <WpPageToolbar
      v-model:search="search"
      :active-filter-count="activeFilterCount"
      :loading="loading"
      @refresh="refresh"
      @open-filters="openFilters"
    >
      <template #filter-chips>
        <WpActiveFilters
          :filters="activeFilters"
          @remove="key => removeFilter(key)"
          @clear-all="resetFilters"
        />
      </template>
    </WpPageToolbar>

    <!-- List -->
    <WpResponsiveList
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="lots"
      :total-items="totalItems"
      :loading="loading"
      :error="error"
      @retry="refresh"
    >
      <!-- Lot Number -->
      <template #item.lot_number="{ item }">
        <span class="font-monospace font-weight-medium">{{ item.lot_number }}</span>
      </template>

      <!-- Catalog Item -->
      <template #item.catalog_item="{ item }">
        <div>
          <div class="font-weight-medium">
            {{ localizedValue(item.catalog_item?.name) || item.catalog_item?.code }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.catalog_item?.code }}
          </div>
        </div>
      </template>

      <!-- Supplier -->
      <template #item.supplier="{ item }">
        <span class="text-body-2">{{ item.supplier?.name || '—' }}</span>
      </template>

      <!-- Manufacture Date -->
      <template #item.manufacture_date="{ item }">
        <span class="text-body-2 text-medium-emphasis">{{ formatDate(item.manufacture_date) }}</span>
      </template>

      <!-- Expiry Date -->
      <template #item.expiry_date="{ item }">
        <span
          class="text-body-2"
          :class="item.expiry_date && new Date(item.expiry_date) < new Date() ? 'text-error' : ''"
        >
          {{ formatDate(item.expiry_date) }}
        </span>
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :color="resolveStatusVariant(item.status)"
          :label="t(`inventory.lots.statuses.${item.status}`)"
          size="x-small"
        />
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <WpRowActions :actions="rowActions(item)" />
      </template>

      <!-- Mobile Card -->
      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="item.lot_number"
          :subtitle="localizedValue(item.catalog_item?.name) || item.catalog_item?.code"
          :status="item.status"
          :status-label="t(`inventory.lots.statuses.${item.status}`)"
          :status-color="resolveStatusVariant(item.status)"
          clickable
          @click="openDetail(item)"
        >
          <template #details>
            <div class="d-flex gap-4">
              <div v-if="item.expiry_date">
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.lots.columns.expiry_date') }}
                </div>
                <div
                  class="font-weight-medium text-body-2"
                  :class="new Date(item.expiry_date) < new Date() ? 'text-error' : ''"
                >
                  {{ formatDate(item.expiry_date) }}
                </div>
              </div>
            </div>
          </template>
          <template #actions>
            <WpRowActions :actions="rowActions(item)" />
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>

    <!-- Filter Dialog -->
    <WpFilterDialog
      v-model="filterDialogOpen"
      @apply="applyFilters"
      @reset="resetFilterDialog"
    >
      <VRow>
        <VCol cols="12">
          <VSelect
            v-model="stagedFilters.status"
            :items="statusOptions"
            :label="t('inventory.lots.filters.status')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VTextField
            v-model="stagedFilters.catalog_item_id"
            :label="t('inventory.lots.filters.item')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VTextField
            v-model.number="stagedFilters.expiry_within_days"
            :label="t('inventory.lots.filters.expiry_within_days')"
            type="number"
            min="1"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- Detail Dialog -->
    <ViewStockLotDialog
      v-model="detailDialog.isOpen.value"
      :lot="activeLot"
    />

    <!-- Create Dialog -->
    <AddEditStockLotDialog
      v-model="createDialog.isOpen.value"
      :lot="null"
      :loading="actions.saving.value"
      @submit="handleCreateSubmit"
    />

    <!-- Edit Dialog -->
    <AddEditStockLotDialog
      v-model="editDialog.isOpen.value"
      :lot="activeLot"
      :loading="actions.saving.value"
      @submit="handleEditSubmit"
    />

    <!-- Delete Dialog -->
    <WpDeleteDialog
      :is-open="deleteDialog.isOpen.value"
      :title="t('inventory.lots.delete_title')"
      :description="t('inventory.lots.delete_confirm', { lot: activeLot?.lot_number })"
      :loading="actions.isDeleting.value"
      :confirm-action="handleDelete"
      @update:is-open="v => { if (!v) closeDelete() }"
    />
  </div>
</template>

