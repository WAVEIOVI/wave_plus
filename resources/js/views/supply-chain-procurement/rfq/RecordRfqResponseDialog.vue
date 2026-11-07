<script setup>
/* eslint-disable camelcase */
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useCurrency } from '@/composables/useCurrency'
import { useRfqStore } from '@/stores/useRfqStore'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  rfq: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'submitted'])

const { t } = useI18n()
const rfqStore = useRfqStore()
const { format: formatMoney } = useCurrency()
const { showSuccess, showError } = useAppSnackbar()

const {
  supplierOptions,
  ensureSuppliers,
  rfqPaymentTermsOptions,
  resolveLineItemName,
} = useProcurementHelpers()

const submitting = ref(false)

const form = reactive({
  supplier_id: null,
  validity_until: '',
  can_deliver_by: '',
  payment_terms_offered: 'net_30',
  supplier_notes: '',
  lines: [],
})

const availableSupplierOptions = computed(() => {
  const invited = (props.rfq?.invitations || []).map(inv => inv.supplier).filter(Boolean)
  if (invited.length) {
    return invited.map(s => ({
      value: s.id,
      title: s.legal_name || s.trade_name || s.supplier_code || String(s.id),
    }))
  }

  return supplierOptions.value
})

watch(
  () => props.modelValue,
  async open => {
    if (!open) return
    await ensureSuppliers()
    form.supplier_id = availableSupplierOptions.value[0]?.value || null
    form.validity_until = ''
    form.can_deliver_by = props.rfq?.required_delivery_date ? String(props.rfq.required_delivery_date).slice(0, 10) : ''
    form.payment_terms_offered = 'net_30'
    form.supplier_notes = ''
    form.lines = (props.rfq?.lines || []).map(l => ({
      rfq_line_id: l.id,
      item_name: resolveLineItemName(l),
      qty_requested: Number(l.qty_requested) || 1,
      offered_qty: Number(l.qty_requested) || 1,
      offered_unit_price_ht: Number(l.target_price_ht) || 0,
      offered_lead_time_days: 7,
      certifications_confirmed: true,
    }))
  },
  { immediate: true },
)

const totalHt = computed(() =>
  form.lines.reduce((sum, l) => sum + (Number(l.offered_qty) || 0) * (Number(l.offered_unit_price_ht) || 0), 0),
)

const handleSubmit = async () => {
  if (!props.rfq?.id || !form.supplier_id) return
  submitting.value = true
  try {
    const payload = {
      supplier_id: form.supplier_id,
      validity_until: form.validity_until || null,
      can_deliver_by: form.can_deliver_by || null,
      payment_terms_offered: form.payment_terms_offered || null,
      total_ht: totalHt.value,
      supplier_notes: form.supplier_notes || null,
      lines: form.lines.map(l => ({
        rfq_line_id: l.rfq_line_id,
        offered_unit_price_ht: Number(l.offered_unit_price_ht) || 0,
        offered_qty: Number(l.offered_qty) || 1,
        offered_lead_time_days: Number(l.offered_lead_time_days) || null,
        certifications_confirmed: Boolean(l.certifications_confirmed),
      })),
    }

    const res = await rfqStore.submitResponse(props.rfq.id, payload)

    showSuccess(t('scp.rfq.snackbar.response_submitted'))
    emit('submitted', res)
    emit('update:modelValue', false)
  }
  catch (err) {
    showError(err?.data?.message || t('scp.rfq.snackbar.error_generic'))
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.rfq.response_dialog.title')"
    :subtitle="rfq?.rfq_number"
    icon="tabler-file-dollar"
    size="large"
    :loading="submitting"
    :submit-label="t('scp.rfq.response_dialog.submit')"
    :submit-disabled="!form.supplier_id || form.lines.length === 0"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <div class="pa-4">
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            v-model="form.supplier_id"
            :items="availableSupplierOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.supplier')"
            prepend-inner-icon="tabler-building-store"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="form.payment_terms_offered"
            :items="rfqPaymentTermsOptions"
            item-title="title"
            item-value="value"
            :label="t('wp.docs.terms.payment_terms')"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.can_deliver_by"
            type="date"
            :label="t('scp.rfq.response_dialog.can_deliver_by')"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.validity_until"
            type="date"
            :label="t('scp.rfq.response_dialog.validity_until')"
          />
        </VCol>
      </VRow>

      <h4 class="text-subtitle-2 font-weight-bold mt-4 mb-2">
        {{ t('scp.rfq.response_dialog.quoted_lines') }}
      </h4>

      <VTable density="comfortable">
        <thead>
          <tr>
            <th>{{ t('scp.common.item') }}</th>
            <th class="text-end">
              {{ t('scp.rfq.response_dialog.offered_qty') }}
            </th>
            <th class="text-end">
              {{ t('scp.rfq.response_dialog.offered_price') }}
            </th>
            <th class="text-end">
              {{ t('scp.rfq.response_dialog.lead_time_days') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in form.lines"
            :key="line.rfq_line_id"
          >
            <td class="font-weight-medium">
              {{ line.item_name }}
            </td>
            <td style="inline-size: 130px;">
              <VTextField
                v-model.number="line.offered_qty"
                type="number"
                min="0.01"
                step="any"
                density="compact"
                hide-details
              />
            </td>
            <td style="inline-size: 150px;">
              <VTextField
                v-model.number="line.offered_unit_price_ht"
                type="number"
                min="0"
                step="any"
                density="compact"
                hide-details
              />
            </td>
            <td style="inline-size: 130px;">
              <VTextField
                v-model.number="line.offered_lead_time_days"
                type="number"
                min="0"
                density="compact"
                hide-details
              />
            </td>
          </tr>
        </tbody>
      </VTable>

      <VRow class="mt-3">
        <VCol cols="12">
          <VTextarea
            v-model="form.supplier_notes"
            :label="t('scp.common.notes')"
            rows="2"
            auto-grow
          />
        </VCol>
        <VCol cols="12">
          <div class="d-flex justify-space-between align-center pa-3 rounded bg-surface">
            <span class="text-body-2 font-weight-medium">{{ t('scp.rfq.response_dialog.total_quoted_ht') }}</span>
            <span class="text-h6 font-weight-bold text-primary">{{ formatMoney(totalHt) }}</span>
          </div>
        </VCol>
      </VRow>
    </div>
  </WpFormDialog>
</template>
