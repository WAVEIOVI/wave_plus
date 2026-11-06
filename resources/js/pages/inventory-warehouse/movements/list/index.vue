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
import { useStockMovementActions } from '@/composables/inventory/movements/useStockMovementActions'
import { useStockMovementDialogs } from '@/composables/inventory/movements/useStockMovementDialogs'
import { useStockMovementList } from '@/composables/inventory/movements/useStockMovementList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import InitialStockLoadDialog from '@/views/inventory/movements/InitialStockLoadDialog.vue'
import StockAdjustmentDialog from '@/views/inventory/movements/StockAdjustmentDialog.vue'
import StockTransferDialog from '@/views/inventory/movements/StockTransferDialog.vue'
import ViewStockMovementDialog from '@/views/inventory/movements/ViewStockMovementDialog.vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/inventory-warehouse/movements/list',
  name: 'inventory-movements-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryMovements',
  },
})

const { t } = useI18n()
const ability = useAbility()

const {
  movements,
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
  movementTypeOptions,
  resolveMovementTypeVariant,
  resolveMovementTypeIcon,
} = useStockMovementList()

const {
  adjustmentDialog,
  transferDialog,
  initialLoadDialog,
  detailDialog,
  activeMovement,
  openDetail,
  closeDetail,
  openAdjustment,
  closeAdjustment,
  openTransfer,
  closeTransfer,
  openInitialLoad,
  closeInitialLoad,
} = useStockMovementDialogs()

const actions = useStockMovementActions({ onRefresh: refresh })

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
const handleAdjustmentSubmit = async payload => {
  const ok = await actions.createAdjustment(payload)
  if (ok) closeAdjustment()
}

const handleTransferSubmit = async payload => {
  const ok = await actions.createTransfer(payload)
  if (ok) closeTransfer()
}

const handleInitialLoadSubmit = async payload => {
  const ok = await actions.createInitialLoad(payload)
  if (ok) closeInitialLoad()
}

// Row actions
const rowActions = movement => [
  {
    label: t('inventory.movements.actions.view'),
    icon: 'tabler-eye',
    action: () => openDetail(movement),
  },
]
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('inventory.movements.title')"
      :subtitle="t('inventory.movements.subtitle')"
      icon="tabler-activity"
    >
      <template #actions>
        <template v-if="ability.can('create', 'InventoryMovements')">
          <VBtn
            variant="tonal"
            prepend-icon="tabler-database-import"
            @click="openInitialLoad"
          >
            {{ t('inventory.movements.add_initial_load') }}
          </VBtn>
          <VBtn
            variant="tonal"
            prepend-icon="tabler-transfer"
            @click="openTransfer"
          >
            {{ t('inventory.movements.add_transfer') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-adjustments"
            @click="openAdjustment"
          >
            {{ t('inventory.movements.add_adjustment') }}
          </VBtn>
        </template>
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
      :items="movements"
      :total-items="totalItems"
      :loading="loading"
      :error="error"
      @retry="refresh"
    >
      <!-- Movement Type -->
      <template #item.movement_type="{ item }">
        <WpStatusChip
          :status="item.movement_type"
          :color="resolveMovementTypeVariant(item.movement_type)"
          :label="t(`inventory.movements.types.${item.movement_type}`)"
          size="x-small"
        />
      </template>

      <!-- Catalog Item -->
      <template #item.catalog_item="{ item }">
        <div>
          <div class="font-weight-medium">
            {{ localizedValue(item.catalog_item?.name) || item.catalog_item?.code }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.catalog_variant?.sku }}
          </div>
        </div>
      </template>

      <!-- Quantity -->
      <template #item.quantity="{ item }">
        <span
          class="font-weight-medium font-monospace"
          :class="item.quantity < 0 ? 'text-error' : 'text-success'"
        >
          {{ item.quantity > 0 ? '+' : '' }}{{ item.quantity }}
        </span>
      </template>

      <!-- From Location -->
      <template #item.from_location="{ item }">
        <span class="text-body-2">{{ item.from_location?.full_path || item.from_location?.name || '—' }}</span>
      </template>

      <!-- To Location -->
      <template #item.to_location="{ item }">
        <span class="text-body-2">{{ item.to_location?.full_path || item.to_location?.name || '—' }}</span>
      </template>

      <!-- Reference -->
      <template #item.reference="{ item }">
        <span class="text-caption font-monospace">{{ item.reference || '—' }}</span>
      </template>

      <!-- Created At -->
      <template #item.created_at="{ item }">
        <span class="text-body-2 text-medium-emphasis">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '—' }}
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
          :subtitle="item.created_at ? new Date(item.created_at).toLocaleDateString() : ''"
          clickable
          @click="openDetail(item)"
        >
          <template #badges>
            <WpStatusChip
              :status="item.movement_type"
              :color="resolveMovementTypeVariant(item.movement_type)"
              :label="t(`inventory.movements.types.${item.movement_type}`)"
              size="x-small"
            />
          </template>
          <template #details>
            <span
              class="font-weight-medium font-monospace"
              :class="item.quantity < 0 ? 'text-error' : 'text-success'"
            >
              {{ item.quantity > 0 ? '+' : '' }}{{ item.quantity }}
            </span>
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
            v-model="stagedFilters.movement_type"
            :items="movementTypeOptions"
            :label="t('inventory.movements.filters.type')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VTextField
            v-model="stagedFilters.catalog_item_id"
            :label="t('inventory.movements.filters.item')"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="stagedFilters.date_from"
            :label="t('inventory.movements.filters.date_from')"
            type="date"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="stagedFilters.date_to"
            :label="t('inventory.movements.filters.date_to')"
            type="date"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- Detail Dialog -->
    <ViewStockMovementDialog
      v-model="detailDialog.isOpen.value"
      :movement="activeMovement"
    />

    <!-- Adjustment Dialog -->
    <StockAdjustmentDialog
      v-model="adjustmentDialog.isOpen.value"
      :loading="actions.creating.value"
      @submit="handleAdjustmentSubmit"
    />

    <!-- Transfer Dialog -->
    <StockTransferDialog
      v-model="transferDialog.isOpen.value"
      :loading="actions.creating.value"
      @submit="handleTransferSubmit"
    />

    <!-- Initial Load Dialog -->
    <InitialStockLoadDialog
      v-model="initialLoadDialog.isOpen.value"
      :loading="actions.creating.value"
      @submit="handleInitialLoadSubmit"
    />
  </div>
</template>

