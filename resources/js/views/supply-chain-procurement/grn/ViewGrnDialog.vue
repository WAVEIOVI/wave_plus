<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  grn: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit'])

const { t } = useI18n()

const {
  statusLabel,
  resolveStatusVariant,
  formatDate,
  resolveLineItemName,
} = useProcurementHelpers()

const lines = computed(() => props.grn?.lines || [])
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="grn?.grn_number || t('scp.grn.title')"
    :subtitle="grn?.supplier?.legal_name || grn?.supplier?.trade_name || t('scp.grn.subtitle')"
    :loading="loading"
    size="large"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <VBtn
        v-if="grn"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-edit"
        @click="emit('edit', grn)"
      >
        {{ t('scp.action.edit') }}
      </VBtn>
    </template>

    <template v-if="grn">
      <div class="d-flex flex-wrap align-center gap-2 mb-4">
        <WpStatusChip
          :status="grn.status"
          :label="statusLabel(grn.status)"
          :color="resolveStatusVariant(grn.status)"
          dot
        />
        <VChip
          v-if="grn.purchase_order?.po_number"
          size="small"
          label
          variant="tonal"
          color="primary"
        >
          {{ grn.purchase_order.po_number }}
        </VChip>
        <VChip
          v-if="grn.supplier_delivery_note_ref"
          size="small"
          label
          variant="outlined"
          color="secondary"
        >
          {{ grn.supplier_delivery_note_ref }}
        </VChip>
      </div>

      <VRow class="mb-4">
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.common.supplier')"
            :value="grn.supplier?.legal_name || grn.supplier?.trade_name || '—'"
            icon="tabler-building-store"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.grn.fields.received_at')"
            :value="formatDate(grn.received_at)"
            icon="tabler-calendar"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <WpInfoItem
            :label="t('scp.grn.fields.received_by')"
            :value="grn.receiver?.name || '—'"
            icon="tabler-user-check"
          />
        </VCol>
      </VRow>

      <WpSectionCard
        :title="t('scp.grn.tabs.lines')"
        icon="tabler-package-import"
      >
        <VTable density="comfortable">
          <thead>
            <tr>
              <th>{{ t('scp.common.item') }}</th>
              <th class="text-end">
                {{ t('scp.grn.qty.received') }}
              </th>
              <th class="text-end">
                {{ t('scp.grn.qty.accepted') }}
              </th>
              <th class="text-end">
                {{ t('scp.grn.qty.rejected') }}
              </th>
              <th>{{ t('scp.grn.fields.batch_number') }}</th>
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
                {{ line.qty_received }}
              </td>
              <td class="text-end text-success font-weight-bold">
                {{ line.qty_accepted }}
              </td>
              <td class="text-end text-error">
                {{ line.qty_rejected }}
              </td>
              <td>{{ line.lot_number || '—' }}</td>
            </tr>
          </tbody>
        </VTable>
      </WpSectionCard>
    </template>
  </WpDetailDialog>
</template>

