<script setup>
import { WpDetailDialog, WpInfoItem, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  movement: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
})

const emit = defineEmits(['update:modelValue', 'retry'])

const { t } = useI18n()

/* eslint-disable camelcase */
const movementTypeVariants = {
  adjustment: 'warning',
  transfer: 'info',
  initial_load: 'success',
  grn_receipt: 'primary',
  consumption: 'secondary',
  return: 'error',
}
/* eslint-enable camelcase */

const formatCurrency = (value, currency = 'USD') => {
  if (value == null) return '—'

  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value)
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="t('inventory.movements.detail_title')"
    :subtitle="movement?.reference"
    size="medium"
    :loading="loading"
    :error="error"
    show-close-footer
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template v-if="movement">
      <div class="pa-4">
        <VRow dense>
          <!-- Movement Type -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-activity"
              :label="t('inventory.movements.columns.type')"
            >
              <WpStatusChip
                :status="movement.movement_type"
                :color="movementTypeVariants[movement.movement_type] || 'primary'"
                :label="t(`inventory.movements.types.${movement.movement_type}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>

          <!-- Catalog Item -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-packages"
              :label="t('inventory.movements.columns.item')"
              :value="localizedValue(movement.catalog_item?.name) || movement.catalog_item?.code"
            />
          </VCol>

          <!-- SKU -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-barcode"
              :label="t('inventory.stock_items.columns.sku')"
              :value="movement.catalog_variant?.sku"
              mono
              hide-when-empty
            />
          </VCol>

          <!-- Quantity -->
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-stack"
              :label="t('inventory.movements.columns.quantity')"
            >
              <span
                class="font-weight-medium"
                :class="movement.quantity < 0 ? 'text-error' : 'text-success'"
              >
                {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
              </span>
            </WpInfoItem>
          </VCol>

          <!-- Unit Cost -->
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-currency-dollar"
              :label="t('inventory.movements.columns.unit_cost')"
              :value="formatCurrency(movement.unit_cost, movement.currency)"
              hide-when-empty
            />
          </VCol>

          <!-- Total Cost -->
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-report-money"
              :label="t('inventory.movements.columns.total_cost')"
              :value="formatCurrency(movement.total_cost, movement.currency)"
              hide-when-empty
            />
          </VCol>

          <!-- From Location -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-map-pin-off"
              :label="t('inventory.movements.columns.from_location')"
              :value="movement.from_location?.full_path || movement.from_location?.name"
              hide-when-empty
            />
          </VCol>

          <!-- To Location -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-map-pin"
              :label="t('inventory.movements.columns.to_location')"
              :value="movement.to_location?.full_path || movement.to_location?.name"
              hide-when-empty
            />
          </VCol>

          <!-- Lot -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-tag"
              :label="t('inventory.lots.columns.lot_number')"
              :value="movement.stock_lot?.lot_number"
              mono
              hide-when-empty
            />
          </VCol>

          <!-- Reason -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-message"
              :label="t('inventory.movements.columns.reason')"
              :value="movement.reason"
              hide-when-empty
            />
          </VCol>

          <VDivider class="my-2" />

          <!-- Performed By -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-user"
              :label="t('inventory.movements.columns.performed_by')"
              :value="movement.performer?.name || movement.performed_by?.name"
              hide-when-empty
            />
          </VCol>

          <!-- Performed At -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-clock"
              :label="t('inventory.movements.columns.performed_at')"
              :value="movement.created_at ? new Date(movement.created_at).toLocaleString() : '—'"
            />
          </VCol>
        </VRow>
      </div>
    </template>
  </WpDetailDialog>
</template>
