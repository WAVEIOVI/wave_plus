<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  itemData: { type: Object, required: true },
  supplierOptions: { type: Array, default: () => [] },
  salesOrderItems: { type: Array, default: () => [] },
  deliveryNoteItems: { type: Array, default: () => [] },
  documentsLoading: { type: Boolean, default: false },
  statusOptions: { type: Array, required: true },
  conditionOptions: { type: Array, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
  <section
    id="section-bio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-info-circle"
      :title="$t('General Information')"
      :subtitle="$t('Barcode SKU, physical variant properties, linked supplier, and financial documents.')"
    >
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="itemData.sku"
            :label="$t('SKU / Variant Code')"
            :error-messages="fieldErrors('sku')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="itemData.garment_type"
            :label="$t('Garment Type')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppTextField
            v-model="itemData.size"
            :label="$t('Size')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppTextField
            v-model="itemData.color"
            :label="$t('Color')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppAutocomplete
            v-model="itemData.supplier_id"
            :items="supplierOptions"
            :label="$t('Supplier / Manufacturer')"
            clearable
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model.number="itemData.unit_cost"
            type="number"
            step="0.001"
            min="0"
            prefix="TND"
            :label="$t('Unit Cost')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppSelect
            v-model="itemData.item_condition"
            :label="$t('Condition')"
            :items="conditionOptions"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppSelect
            v-model="itemData.status"
            :label="$t('Status')"
            :items="statusOptions"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="itemData.sales_order_id"
            :label="$t('Sales Order (optional)')"
            :items="salesOrderItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('sales_order_id')"
            :placeholder="$t('No sales order')"
            :no-data-text="$t('No sales orders for this client')"
            clearable
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="itemData.delivery_note_id"
            :label="$t('Delivery Note (optional)')"
            :items="deliveryNoteItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('delivery_note_id')"
            :placeholder="$t('No delivery note')"
            :no-data-text="$t('No delivery notes for this client')"
            clearable
            variant="outlined"
          />
        </VCol>
      </VRow>

      <VDivider class="my-4" />

      <VRow dense>
        <VCol
          v-for="item in [
            { label: $t('Client'), value: itemData.client?.legal_name || itemData.client?.name || '—' },
            { label: $t('Linked Model'), value: itemData.model?.name || itemData.workwear_model?.name || '—' },
            { label: $t('Assigned Staff'), value: itemData.current_assignment?.employee?.full_name || $t('In Stock / Unassigned') },
            { label: $t('Created'), value: itemData.created_at ? new Date(itemData.created_at).toLocaleDateString() : '—' },
          ]"
          :key="item.label"
          cols="12"
          sm="3"
        >
          <div class="detail-row">
            <div>
              <span class="text-caption text-disabled text-uppercase">{{ item.label }}</span>
              <div class="text-body-2 font-weight-medium">
                {{ item.value }}
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.detail-row {
  display: flex;
  align-items: center;
  border-radius: 10px;
  gap: 12px;
  padding: 8px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
