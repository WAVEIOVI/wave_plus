<script setup>
import { WpDetailDialog, WpInfoItem, WpJournalNav, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
})

const emit = defineEmits(['update:modelValue', 'retry'])

const { t } = useI18n()

const sections = [
  { key: 'overview', label: t('inventory.stock_items.sections.overview'), icon: 'tabler-info-circle' },
  { key: 'quantities', label: t('inventory.stock_items.sections.quantities'), icon: 'tabler-stack' },
  { key: 'location', label: t('inventory.stock_items.sections.location'), icon: 'tabler-map-pin' },
  { key: 'lot', label: t('inventory.stock_items.sections.lot'), icon: 'tabler-tag' },
]

const activeSection = ref('overview')

const formatCurrency = (value, currency = 'USD') => {
  if (value == null) return '—'

  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value)
}

const formatDate = value => {
  if (!value) return '—'

  return new Date(value).toLocaleDateString()
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="localizedValue(item?.catalog_item?.name) || item?.catalog_item?.code || t('inventory.stock_items.detail_title')"
    :subtitle="item?.catalog_item?.code"
    size="large"
    :loading="loading"
    :error="error"
    show-close-footer
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template v-if="item">
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        is-dialog
      />

      <!-- Overview -->
      <div
        v-show="activeSection === 'overview'"
        class="pa-4"
      >
        <VRow dense>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-barcode"
              :label="t('inventory.stock_items.columns.sku')"
              :value="item.catalog_variant?.sku || item.catalog_item?.code"
              mono
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-category"
              :label="t('inventory.stock_items.columns.category')"
              :value="localizedValue(item.catalog_item?.category?.name)"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-circle"
              :label="t('inventory.stock_items.columns.condition')"
            >
              <WpStatusChip
                :status="item.condition"
                :label="t(`inventory.conditions.${item.condition}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-toggle-left"
              :label="t('inventory.stock_items.columns.status')"
            >
              <WpStatusChip
                :status="item.status"
                :label="t(`inventory.stock_items.statuses.${item.status}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-clock"
              :label="t('Updated At')"
              :value="item.updated_at ? new Date(item.updated_at).toLocaleString() : '—'"
            />
          </VCol>
        </VRow>
      </div>

      <!-- Quantities & Valuation -->
      <div
        v-show="activeSection === 'quantities'"
        class="pa-4"
      >
        <VRow dense>
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-stack"
              :label="t('inventory.stock_items.columns.qty_on_hand')"
              :value="item.qty_on_hand"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-stack-2"
              :label="t('inventory.stock_items.columns.qty_available')"
              :value="item.qty_available"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <WpInfoItem
              icon="tabler-lock"
              :label="t('inventory.stock_items.columns.qty_reserved')"
              :value="item.qty_reserved"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-currency-dollar"
              :label="t('inventory.stock_items.columns.unit_cost')"
              :value="formatCurrency(item.unit_cost, item.currency)"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-report-money"
              :label="t('inventory.stock_items.columns.total_value')"
              :value="formatCurrency(item.total_value, item.currency)"
            />
          </VCol>
        </VRow>
      </div>

      <!-- Location -->
      <div
        v-show="activeSection === 'location'"
        class="pa-4"
      >
        <VRow dense>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-building-warehouse"
              :label="t('inventory.sites.title_singular')"
              :value="localizedValue(item.location?.zone?.site?.name)"
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-layers"
              :label="t('inventory.sites.zone')"
              :value="localizedValue(item.location?.zone?.name)"
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-map-pin"
              :label="t('inventory.sites.location')"
              :value="item.location?.name"
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-route"
              :label="t('inventory.stock_items.columns.full_path')"
              :value="item.location?.full_path"
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-barcode"
              :label="t('inventory.stock_items.columns.barcode')"
              :value="item.location?.barcode"
              mono
            />
          </VCol>
        </VRow>
      </div>

      <!-- Lot Details -->
      <div
        v-show="activeSection === 'lot'"
        class="pa-4"
      >
        <template v-if="item.stock_lot">
          <VRow dense>
            <VCol cols="12">
              <WpInfoItem
                icon="tabler-tag"
                :label="t('inventory.lots.columns.lot_number')"
                :value="item.stock_lot.lot_number"
                mono
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-calendar"
                :label="t('inventory.lots.columns.manufacture_date')"
                :value="formatDate(item.stock_lot.manufacture_date)"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-calendar-off"
                :label="t('inventory.lots.columns.expiry_date')"
                :value="formatDate(item.stock_lot.expiry_date)"
              />
            </VCol>
            <VCol cols="12">
              <WpInfoItem
                icon="tabler-circle"
                :label="t('inventory.lots.columns.status')"
              >
                <WpStatusChip
                  :status="item.stock_lot.status"
                  :label="t(`inventory.lots.statuses.${item.stock_lot.status}`)"
                  size="small"
                />
              </WpInfoItem>
            </VCol>
          </VRow>
        </template>
        <template v-else>
          <p class="text-medium-emphasis text-body-2 pa-2">
            {{ t('inventory.stock_items.no_lot') }}
          </p>
        </template>
      </div>
    </template>
  </WpDetailDialog>
</template>
