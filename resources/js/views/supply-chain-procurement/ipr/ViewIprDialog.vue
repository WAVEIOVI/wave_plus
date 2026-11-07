<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  ipr: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit', 'convert'])

const { t } = useI18n()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  urgencyLabel,
  triggerSourceLabel,
  resolveStatusVariant,
  resolveUrgencyVariant,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const lines = computed(() => props.ipr?.lines || [])
const canConvert = computed(() => props.ipr?.status === 'approved')
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="ipr?.ipr_number || t('scp.ipr.title')"
    :subtitle="t('scp.ipr.subtitle')"
    :loading="loading"
    size="large"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <VBtn
        v-if="canConvert"
        size="small"
        color="success"
        variant="tonal"
        prepend-icon="tabler-transform"
        @click="emit('convert', ipr)"
      >
        {{ t('scp.ipr.actions.convert') }}
      </VBtn>
      <VBtn
        v-if="ipr"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-edit"
        @click="emit('edit', ipr)"
      >
        {{ t('scp.action.edit') }}
      </VBtn>
    </template>

    <template v-if="ipr">
      <div class="d-flex flex-wrap align-center gap-2 mb-4">
        <WpStatusChip
          :status="ipr.status"
          :label="statusLabel(ipr.status)"
          :color="resolveStatusVariant(ipr.status)"
          dot
        />
        <VChip
          size="small"
          label
          variant="tonal"
          :color="resolveUrgencyVariant(ipr.urgency)"
          prepend-icon="tabler-flag"
        >
          {{ urgencyLabel(ipr.urgency) }}
        </VChip>
        <VChip
          v-if="ipr.trigger_source"
          size="small"
          label
          variant="outlined"
          color="secondary"
        >
          {{ triggerSourceLabel(ipr.trigger_source) }}
        </VChip>
      </div>

      <VRow class="mb-4">
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.ipr.fields.requested_by')"
            :value="ipr.requester?.name || '—'"
            icon="tabler-user"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.ipr.fields.needed_by_date')"
            :value="formatDate(ipr.needed_by_date)"
            icon="tabler-calendar"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.ipr.fields.estimated_total')"
            :value="formatMoney(ipr.total_estimated_cost || 0)"
            icon="tabler-coins"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpInfoItem
            :label="t('scp.common.created_at')"
            :value="formatDate(ipr.created_at)"
            icon="tabler-clock"
          />
        </VCol>
      </VRow>

      <VAlert
        v-if="ipr.internal_justification"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <strong>{{ t('scp.ipr.fields.internal_justification') }}:</strong>
        {{ ipr.internal_justification }}
      </VAlert>

      <WpSectionCard
        :title="t('scp.ipr.sections.lines')"
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
                {{ t('scp.ipr.fields.estimated_unit_cost') }}
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
              <td>
                <div class="font-weight-medium">
                  {{ resolveLineItemName(line) }}
                </div>
                <div
                  v-if="resolveLineItemSku(line)"
                  class="text-caption text-medium-emphasis"
                >
                  {{ resolveLineItemSku(line) }}
                </div>
              </td>
              <td class="text-end">
                {{ line.qty_requested }}
              </td>
              <td class="text-end">
                {{ formatMoney(line.estimated_unit_cost || 0) }}
              </td>
              <td class="text-end font-weight-bold">
                {{ formatMoney((Number(line.qty_requested) || 0) * (Number(line.estimated_unit_cost) || 0)) }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </WpSectionCard>
    </template>
  </WpDetailDialog>
</template>

