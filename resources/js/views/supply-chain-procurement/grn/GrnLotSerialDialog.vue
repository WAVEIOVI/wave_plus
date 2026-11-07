<script setup>
/* eslint-disable camelcase */
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  line: { type: Object, default: null },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const { warehouseOptions, qcResultOptions } = useProcurementHelpers()

const form = reactive({
  qty_received: 0,
  qty_accepted: 0,
  qty_rejected: 0,
  unit_cost_ht: 0,
  storage_location_id: null,
  lot_number: '',
  expiry_date: '',
  qc_result: 'passed',
  qc_notes: '',
})

watch(
  () => props.modelValue,
  open => {
    if (!open || !props.line) return
    form.qty_received = Number(props.line.qty_received) || 0
    form.qty_accepted = Number(props.line.qty_accepted ?? props.line.qty_received) || 0
    form.qty_rejected = Number(props.line.qty_rejected) || 0
    form.unit_cost_ht = Number(props.line.unit_cost_ht) || 0
    form.storage_location_id = props.line.storage_location_id || null
    form.lot_number = props.line.lot_number || ''
    form.expiry_date = props.line.expiry_date ? String(props.line.expiry_date).slice(0, 10) : ''
    form.qc_result = props.line.qc_result || 'passed'
    form.qc_notes = props.line.qc_notes || ''
  },
  { immediate: true },
)

const onReceivedChange = val => {
  const rec = Math.max(0, Number(val) || 0)

  form.qty_received = rec
  form.qty_accepted = Math.max(0, rec - (Number(form.qty_rejected) || 0))
}

const onRejectedChange = val => {
  const rej = Math.max(0, Number(val) || 0)

  form.qty_rejected = rej
  form.qty_accepted = Math.max(0, (Number(form.qty_received) || 0) - rej)
}

const handleSave = () => {
  emit('save', {
    ...props.line,
    qty_received: Number(form.qty_received) || 0,
    qty_accepted: Number(form.qty_accepted) || 0,
    qty_rejected: Number(form.qty_rejected) || 0,
    unit_cost_ht: Number(form.unit_cost_ht) || 0,
    storage_location_id: form.storage_location_id || null,
    lot_number: form.lot_number || null,
    expiry_date: form.expiry_date || null,
    qc_result: form.qc_result || 'passed',
    qc_notes: form.qc_notes || null,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.grn.modal.title_receive_qc')"
    :subtitle="line?.item_name"
    icon="tabler-package-import"
    size="medium"
    :submit-label="t('scp.grn.action.done')"
    :submit-disabled="readonly"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSave"
  >
    <div class="pa-4">
      <VRow>
        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            :model-value="form.qty_received"
            type="number"
            min="0.01"
            step="any"
            :label="t('scp.grn.qty.received')"
            :readonly="readonly"
            @update:model-value="onReceivedChange"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            :model-value="form.qty_rejected"
            type="number"
            min="0"
            step="any"
            :label="t('scp.grn.qty.rejected')"
            :readonly="readonly"
            @update:model-value="onRejectedChange"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            :model-value="form.qty_accepted"
            type="number"
            :label="t('scp.grn.qty.accepted')"
            readonly
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="form.storage_location_id"
            :items="warehouseOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.storage_location')"
            :readonly="readonly"
            clearable
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="form.qc_result"
            :items="qcResultOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.qc_result')"
            :readonly="readonly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.lot_number"
            :label="t('scp.grn.fields.batch_number')"
            prepend-inner-icon="tabler-barcode"
            :readonly="readonly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.expiry_date"
            type="date"
            :label="t('scp.grn.fields.expiry_date')"
            :readonly="readonly"
          />
        </VCol>

        <VCol cols="12">
          <VTextarea
            v-model="form.qc_notes"
            :label="t('scp.grn.fields.line_notes')"
            rows="2"
            auto-grow
            :readonly="readonly"
          />
        </VCol>
      </VRow>
    </div>
  </WpFormDialog>
</template>
