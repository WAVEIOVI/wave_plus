<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  rfq: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit'])

const { t } = useI18n()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  resolveStatusVariant,
  formatDate,
  resolveLineItemName,
  resolveSupplierName,
} = useProcurementHelpers()

const lines = computed(() => props.rfq?.lines || [])
const invitations = computed(() => props.rfq?.invitations || [])
const responses = computed(() => props.rfq?.responses || [])
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="rfq?.reference || t('scp.rfq.title')"
    :subtitle="rfq?.title || t('scp.rfq.subtitle')"
    :loading="loading"
    size="large"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <VBtn
        v-if="rfq"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-edit"
        @click="emit('edit', rfq)"
      >
        {{ t('scp.action.edit') }}
      </VBtn>
    </template>

    <template v-if="rfq">
      <div class="d-flex flex-wrap align-center gap-2 mb-4">
        <WpStatusChip
          :status="rfq.status"
          :label="statusLabel(rfq.status)"
          :color="resolveStatusVariant(rfq.status)"
          dot
        />
        <VChip
          size="small"
          label
          variant="tonal"
          color="info"
          prepend-icon="tabler-users"
        >
          {{ invitations.length }} {{ t('scp.rfq.fields.invited_suppliers') }}
        </VChip>
        <VChip
          size="small"
          label
          variant="tonal"
          color="success"
          prepend-icon="tabler-messages"
        >
          {{ responses.length }} {{ t('scp.rfq.sections.responses') }}
        </VChip>
      </div>

      <VRow class="mb-4">
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.rfq.fields.response_deadline')"
            :value="formatDate(rfq.response_deadline)"
            icon="tabler-calendar-due"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.rfq.fields.required_delivery_date')"
            :value="formatDate(rfq.required_delivery_date)"
            icon="tabler-truck-delivery"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.rfq.fields.awarded_supplier')"
            :value="rfq.awarded_to ? resolveSupplierName(rfq.awarded_to) : '—'"
            icon="tabler-award"
          />
        </VCol>
      </VRow>

      <WpSectionCard
        :title="t('scp.rfq.sections.lines')"
        icon="tabler-list-details"
      >
        <VTable density="comfortable">
          <thead>
            <tr>
              <th>{{ t('scp.common.item') }}</th>
              <th class="text-end">
                {{ t('scp.ipr.fields.qty_requested') }}
              </th>
              <th class="text-end">
                {{ t('scp.rfq.fields.target_price_ht') }}
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
                {{ line.qty_requested }}
              </td>
              <td class="text-end">
                {{ formatMoney(line.target_price_ht || 0) }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </WpSectionCard>
    </template>
  </WpDetailDialog>
</template>

