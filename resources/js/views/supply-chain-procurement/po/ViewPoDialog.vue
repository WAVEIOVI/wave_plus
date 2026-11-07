<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  po: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit', 'receive', 'match'])

const { t } = useI18n()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  paymentTermsLabel,
  resolveStatusVariant,
  formatDate,
  resolveLineItemName,
} = useProcurementHelpers()

const lines = computed(() => props.po?.lines || [])

const isReceivable = computed(() =>
  ['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(props.po?.status),
)
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="po?.po_number || t('scp.po.title')"
    :subtitle="po?.supplier?.legal_name || po?.supplier?.trade_name || t('scp.po.subtitle')"
    :loading="loading"
    size="large"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <VBtn
        v-if="isReceivable"
        size="small"
        color="success"
        variant="tonal"
        prepend-icon="tabler-package-import"
        @click="emit('receive', po)"
      >
        {{ t('scp.po.actions.create_grn') }}
      </VBtn>
      <VBtn
        v-if="po"
        size="small"
        color="info"
        variant="tonal"
        prepend-icon="tabler-git-compare"
        @click="emit('match', po)"
      >
        {{ t('scp.action.open_match_page') }}
      </VBtn>
      <VBtn
        v-if="po"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-edit"
        @click="emit('edit', po)"
      >
        {{ t('scp.action.edit') }}
      </VBtn>
    </template>

    <template v-if="po">
      <div class="d-flex flex-wrap align-center gap-2 mb-4">
        <WpStatusChip
          :status="po.status"
          :label="statusLabel(po.status)"
          :color="resolveStatusVariant(po.status)"
          dot
        />
        <VChip
          size="small"
          label
          variant="tonal"
          color="primary"
        >
          {{ po.currency || 'TND' }}
        </VChip>
        <VChip
          v-if="po.payment_terms"
          size="small"
          label
          variant="outlined"
          color="secondary"
        >
          {{ paymentTermsLabel(po.payment_terms) }}
        </VChip>
      </div>

      <VRow class="mb-4">
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.common.supplier')"
            :value="po.supplier?.legal_name || po.supplier?.trade_name || '—'"
            icon="tabler-building-store"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.po.fields.committed_delivery_date')"
            :value="formatDate(po.committed_delivery_date)"
            icon="tabler-calendar"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('wp.docs.totals.subtotal')"
            :value="formatMoney(po.subtotal_ht || 0, { currency: po.currency })"
            icon="tabler-calculator"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('wp.docs.totals.grand_total')"
            :value="formatMoney(po.total_ttc || 0, { currency: po.currency })"
            icon="tabler-coins"
          />
        </VCol>
      </VRow>

      <WpSectionCard
        :title="t('scp.po.sections.lines')"
        icon="tabler-list-details"
      >
        <VTable density="comfortable">
          <thead>
            <tr>
              <th>{{ t('scp.common.item') }}</th>
              <th class="text-end">
                {{ t('scp.po.fields.qty_ordered') }}
              </th>
              <th class="text-end">
                {{ t('scp.po.fields.qty_received') }}
              </th>
              <th class="text-end">
                {{ t('scp.po.fields.unit_price_ht') }}
              </th>
              <th class="text-end">
                {{ t('scp.common.total') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="line in lines"
              :key="line.id"
            >
              <td class="font-weight-medium">
                {{ resolveLineItemName(line) }}
              </td>
              <td class="text-end">
                {{ line.qty_ordered }}
              </td>
              <td class="text-end">
                {{ line.qty_received ?? 0 }}
              </td>
              <td class="text-end">
                {{ formatMoney(line.unit_price_ht || 0, { currency: po.currency }) }}
              </td>
              <td class="text-end font-weight-bold">
                {{ formatMoney(line.line_total_ttc || line.line_total_ht || 0, { currency: po.currency }) }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </WpSectionCard>
    </template>
  </WpDetailDialog>
</template>
