<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpErrorBanner,
  WpFilterDialog,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useStockItemDialogs } from '@/composables/inventory/stock-items/useStockItemDialogs'
import { useStockItemList } from '@/composables/inventory/stock-items/useStockItemList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import ViewStockItemDialog from '@/views/inventory/stock-items/ViewStockItemDialog.vue'
import StockAdjustmentDialog from '@/views/inventory/movements/StockAdjustmentDialog.vue'
import StockTransferDialog from '@/views/inventory/movements/StockTransferDialog.vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/inventory-warehouse/stock-items/list',
  name: 'inventory-stock-items-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryStockItems',
  },
})

const { t } = useI18n()
const ability = useAbility()

const {
  stockItems,
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
  conditionOptions,
  resolveStatusVariant,
  resolveConditionVariant,
} = useStockItemList()

const {
  quickViewDialog,
  adjustmentDialog,
  transferDialog,
  activeItem,
  openQuickView,
  closeQuickView,
  openAdjustment,
  closeAdjustment,
  openTransfer,
  closeTransfer,
} = useStockItemDialogs()

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

// Row actions
const rowActions = item => {
  const actions = [
    {
      label: t('inventory.stock_items.actions.view'),
      icon: 'tabler-eye',
      action: () => openQuickView(item),
    },
  ]

  if (ability.can('create', 'InventoryMovements')) {
    actions.push(
      {
        label: t('inventory.stock_items.actions.adjust'),
        icon: 'tabler-adjustments',
        action: () => openAdjustment(item),
      },
      {
        label: t('inventory.stock_items.actions.transfer'),
        icon: 'tabler-transfer',
        action: () => openTransfer(item),
      },
    )
  }

  return actions
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('inventory.stock_items.title')"
      :subtitle="t('inventory.stock_items.subtitle')"
      icon="tabler-packages"
    />

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
      :items="stockItems"
      :total-items="totalItems"
      :loading="loading"
      :error="error"
      @retry="refresh"
    >
      <!-- Catalog Item -->
      <template #item.catalog_item="{ item }">
        <div>
          <div class="font-weight-medium text-body-1">
            {{ localizedValue(item.catalog_item?.name) || item.catalog_item?.code }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.catalog_variant?.sku || item.catalog_item?.code }}
          </div>
        </div>
      </template>

      <!-- Location -->
      <template #item.location="{ item }">
        <div class="text-body-2">
          {{ item.location?.full_path || '—' }}
        </div>
        <div
          v-if="item.location?.barcode"
          class="text-caption text-medium-emphasis font-monospace"
        >
          {{ item.location.barcode }}
        </div>
      </template>

      <!-- Quantity columns -->
      <template #item.qty_on_hand="{ item }">
        <span class="font-weight-medium">{{ item.qty_on_hand }}</span>
      </template>
      <template #item.qty_available="{ item }">
        <span :class="item.qty_available <= 0 ? 'text-error' : 'text-success'">
          {{ item.qty_available }}
        </span>
      </template>
      <template #item.qty_reserved="{ item }">
        <span class="text-warning">{{ item.qty_reserved }}</span>
      </template>

      <!-- Unit Cost -->
      <template #item.unit_cost="{ item }">
        <span class="font-monospace text-body-2">
          {{ item.unit_cost != null ? item.unit_cost : '—' }}
        </span>
      </template>

      <!-- Condition -->
      <template #item.condition="{ item }">
        <WpStatusChip
          :status="item.condition"
          :color="resolveConditionVariant(item.condition)"
          :label="t(`inventory.conditions.${item.condition}`)"
          size="x-small"
        />
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :color="resolveStatusVariant(item.status)"
          :label="t(`inventory.stock_items.statuses.${item.status}`)"
          size="x-small"
        />
      </template>

      <!-- Updated At -->
      <template #item.updated_at="{ item }">
        <span class="text-body-2 text-medium-emphasis">
          {{ item.updated_at ? new Date(item.updated_at).toLocaleDateString() : '—' }}
        </span>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <WpRowActions :actions="rowActions(item)" />
      </template>

      <!-- Mobile Card -->
      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="localizedValue(item.catalog_item?.name) || item.catalog_item?.code"
          :subtitle="item.location?.full_path"
          :status="item.status"
          :status-label="t(`inventory.stock_items.statuses.${item.status}`)"
          :status-color="resolveStatusVariant(item.status)"
          clickable
          @click="openQuickView(item)"
        >
          <template #badges>
            <WpStatusChip
              :status="item.condition"
              :color="resolveConditionVariant(item.condition)"
              :label="t(`inventory.conditions.${item.condition}`)"
              size="x-small"
            />
          </template>
          <template #details>
            <div class="d-flex gap-4">
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.stock_items.columns.qty_on_hand') }}
                </div>
                <div class="font-weight-medium">
                  {{ item.qty_on_hand }}
                </div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.stock_items.columns.qty_available') }}
                </div>
                <div
                  class="font-weight-medium"
                  :class="item.qty_available <= 0 ? 'text-error' : 'text-success'"
                >
                  {{ item.qty_available }}
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
            :label="t('inventory.stock_items.filters.status')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VSelect
            v-model="stagedFilters.condition"
            :items="conditionOptions"
            :label="t('inventory.stock_items.filters.condition')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VTextField
            v-model="stagedFilters.site_id"
            :label="t('inventory.stock_items.filters.site')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VSwitch
            v-model="stagedFilters.below_reorder"
            :label="t('inventory.stock_items.filters.below_reorder')"
            color="warning"
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- Detail Dialog -->
    <ViewStockItemDialog
      v-model="quickViewDialog.isOpen.value"
      :item="activeItem"
      @retry="refresh"
    />

    <!-- Adjustment Dialog -->
    <StockAdjustmentDialog
      v-model="adjustmentDialog.isOpen.value"
      :prefill="activeItem ? { catalog_item_id: activeItem.catalog_item_id, catalog_variant_id: activeItem.catalog_variant_id, location_id: activeItem.location_id } : null"
      @submit="payload => { closeAdjustment(); refresh() }"
    />

    <!-- Transfer Dialog -->
    <StockTransferDialog
      v-model="transferDialog.isOpen.value"
      :prefill="activeItem ? { catalog_item_id: activeItem.catalog_item_id, catalog_variant_id: activeItem.catalog_variant_id, location_id: activeItem.location_id } : null"
      @submit="payload => { closeTransfer(); refresh() }"
    />
  </div>
</template>

