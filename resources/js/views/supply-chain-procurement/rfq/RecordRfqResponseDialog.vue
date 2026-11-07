<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useRfqStore } from '@/stores/useRfqStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  rfq: {
    type: Object,
    default: null,
  },
  responseToEdit: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'submitted'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const rfqStore = useRfqStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

const {
  paymentTermsOptions,
  supplierOptions,
  ensureSuppliers,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const submitting = ref(false)
const errors = ref({})

const form = ref({
  supplier_id: null,
  validity_until: '',
  can_deliver_by: '',
  payment_terms_offered: 'net_30',
  supplier_notes: '',
  lines: [],
})

const isEditMode = computed(() => Boolean(props.responseToEdit))

const availableSupplierChoices = computed(() => {
  const invitations = props.rfq?.invitations || []
  if (invitations.length) {
    return invitations.map(inv => ({
      value: inv.supplier_id || inv.supplier?.id,
      title: resolveSupplierName(inv.supplier) || inv.supplier_id,
    }))
  }

  return supplierOptions.value
})

const initForm = async () => {
  errors.value = {}
  await ensureSuppliers()

  const rfqLines = props.rfq?.lines || []
  const editResp = props.responseToEdit

  if (editResp) {
    const existingLines = editResp.response_lines || editResp.lines || []

    form.value = {
      supplier_id: editResp.supplier_id || editResp.supplier?.id || null,
      validity_until: editResp.validity_until ? String(editResp.validity_until).slice(0, 10) : '',
      can_deliver_by: editResp.can_deliver_by ? String(editResp.can_deliver_by).slice(0, 10) : '',
      payment_terms_offered: editResp.payment_terms_offered || 'net_30',
      supplier_notes: editResp.supplier_notes || '',
      lines: rfqLines.map(line => {
        const match = existingLines.find(rl => (rl.rfq_line_id || rl.rfq_line?.id) === line.id)

        return {
          rfq_line_id: line.id,
          item_name: resolveLineItemName(line),
          sku: resolveLineItemSku(line),
          qty_requested: Number(line.qty_requested) || 1,
          target_price_ht: Number(line.target_price_ht) || 0,
          required_certifications: Array.isArray(line.required_certifications) ? line.required_certifications : [],
          offered_unit_price_ht: match ? Number(match.offered_unit_price_ht) || 0 : Number(line.target_price_ht) || 0,
          offered_qty: match ? Number(match.offered_qty) || 1 : Number(line.qty_requested) || 1,
          offered_lead_time_days: match?.offered_lead_time_days ?? 7,
          certifications_confirmed: Array.isArray(match?.certifications_confirmed)
            ? [...match.certifications_confirmed]
            : (Array.isArray(line.required_certifications) ? [...line.required_certifications] : []),
        }
      }),
    }
  }
  else {
    form.value = {
      supplier_id: availableSupplierChoices.value[0]?.value || null,
      validity_until: '',
      can_deliver_by: props.rfq?.required_delivery_date ? String(props.rfq.required_delivery_date).slice(0, 10) : '',
      payment_terms_offered: 'net_30',
      supplier_notes: '',
      lines: rfqLines.map(line => ({
        rfq_line_id: line.id,
        item_name: resolveLineItemName(line),
        sku: resolveLineItemSku(line),
        qty_requested: Number(line.qty_requested) || 1,
        target_price_ht: Number(line.target_price_ht) || 0,
        required_certifications: Array.isArray(line.required_certifications) ? line.required_certifications : [],
        offered_unit_price_ht: Number(line.target_price_ht) || 0,
        offered_qty: Number(line.qty_requested) || 1,
        offered_lead_time_days: 7,
        certifications_confirmed: Array.isArray(line.required_certifications) ? [...line.required_certifications] : [],
      })),
    }
  }
}

watch(
  () => [props.modelValue, props.responseToEdit],
  ([open]) => {
    if (open) initForm()
  },
)

const lineTotal = line => (Number(line.offered_unit_price_ht) || 0) * (Number(line.offered_qty) || 0)

const calcVsTargetPct = line => {
  const target = Number(line.target_price_ht) || 0
  const offered = Number(line.offered_unit_price_ht) || 0
  if (target <= 0 || offered <= 0) return null

  return Number((((offered - target) / target) * 100).toFixed(1))
}

const resolveVsTargetColor = pct => {
  if (pct === null || pct === undefined) return 'secondary'
  if (pct <= 0) return 'success'
  if (pct <= 10) return 'warning'

  return 'error'
}

const totalHt = computed(() =>
  form.value.lines.reduce((sum, l) => sum + lineTotal(l), 0),
)

const fieldError = key => errors.value?.[key]?.[0] || ''

const close = () => {
  emit('update:modelValue', false)
}

const handleSubmit = async () => {
  if (!props.rfq?.id) return
  submitting.value = true
  errors.value = {}

  try {
    const payload = {
      supplier_id: form.value.supplier_id,
      validity_until: form.value.validity_until || null,
      can_deliver_by: form.value.can_deliver_by || null,
      payment_terms_offered: form.value.payment_terms_offered || null,
      total_ht: Number(totalHt.value.toFixed(2)),
      supplier_notes: form.value.supplier_notes || null,
      lines: form.value.lines.map(l => ({
        rfq_line_id: l.rfq_line_id,
        offered_unit_price_ht: Number(l.offered_unit_price_ht) || 0,
        offered_qty: Number(l.offered_qty) || 1,
        offered_lead_time_days: l.offered_lead_time_days ? Number(l.offered_lead_time_days) : null,
        certifications_confirmed: Array.isArray(l.certifications_confirmed) ? l.certifications_confirmed : [],
      })),
    }

    const res = await rfqStore.submitResponse(props.rfq.id, payload)

    success(
      isEditMode.value
        ? t('scp.rfq.snackbar.response_updated', 'Supplier bid updated successfully.')
        : t('scp.rfq.snackbar.response_recorded'),
    )
    emit('submitted', res)
    close()
  }
  catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors
    }
    showApiError(err, t('scp.rfq.snackbar.error_response'))
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="isEditMode ? t('scp.rfq.actions.edit_response', 'Edit Supplier Response') : t('scp.rfq.actions.record_response', 'Record Supplier Response')"
    :subtitle="rfq?.reference ? `${rfq.reference} — ${rfq.title || ''}` : ''"
    icon="tabler-Receipt"
    size="large"
    :max-width="1100"
    :loading="submitting"
    :submit-label="isEditMode ? t('scp.action.save', 'Save Changes') : t('scp.rfq.actions.record_response', 'Submit Response')"
    :cancel-label="t('scp.action.cancel')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
    @cancel="close"
  >
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VAutocomplete
          v-model="form.supplier_id"
          :items="availableSupplierChoices"
          item-title="title"
          item-value="value"
          :label="t('scp.common.supplier')"
          :disabled="isEditMode"
          :error-messages="fieldError('supplier_id')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VSelect
          v-model="form.payment_terms_offered"
          :items="paymentTermsOptions"
          item-title="title"
          item-value="value"
          :label="t('scp.rfq.fields.payment_terms_offered')"
          :error-messages="fieldError('payment_terms_offered')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.can_deliver_by"
          type="date"
          :label="t('scp.rfq.fields.can_deliver_by')"
          :error-messages="fieldError('can_deliver_by')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.validity_until"
          type="date"
          :label="t('scp.rfq.fields.validity_until')"
          :error-messages="fieldError('validity_until')"
        />
      </VCol>

      <VCol cols="12">
        <VDivider class="my-2" />
        <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
          <div class="text-subtitle-2 font-weight-semibold">
            {{ t('scp.rfq.sections.lines') }}
          </div>
          <div class="text-body-2 font-weight-bold text-primary">
            {{ t('scp.common.total_ht') }}: {{ formatMoney(totalHt) }}
          </div>
        </div>

        <!-- Desktop Table -->
        <VTable
          v-if="!smAndDown"
          density="compact"
          class="border rounded-lg"
        >
          <thead>
            <tr>
              <th>{{ t('scp.common.item') }}</th>
              <th
                class="text-end"
                style="inline-size: 110px;"
              >
                {{ t('scp.rfq.fields.offered_qty') }}
              </th>
              <th
                class="text-end"
                style="inline-size: 145px;"
              >
                {{ t('scp.rfq.fields.offered_unit_price_ht') }}
              </th>
              <th
                class="text-center"
                style="inline-size: 105px;"
              >
                {{ t('scp.rfq.fields.vs_target', 'vs Target') }}
              </th>
              <th
                class="text-end"
                style="inline-size: 110px;"
              >
                {{ t('scp.rfq.fields.offered_lead_time_days') }}
              </th>
              <th style="inline-size: 210px;">
                {{ t('scp.rfq.fields.certifications_confirmed', 'Confirmed Certifications') }}
              </th>
              <th
                class="text-end"
                style="inline-size: 125px;"
              >
                {{ t('scp.common.total_ht') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(line, idx) in form.lines"
              :key="line.rfq_line_id || idx"
            >
              <td class="py-2">
                <div class="font-weight-semibold text-body-2">
                  {{ line.item_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ line.sku }} · {{ t('scp.rfq.fields.target_price_ht') }}: {{ formatMoney(line.target_price_ht) }}
                </div>
                <div
                  v-if="line.required_certifications?.length"
                  class="d-flex flex-wrap gap-1 mt-1"
                >
                  <VChip
                    v-for="cert in line.required_certifications"
                    :key="cert"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    label
                  >
                    {{ cert }}
                  </VChip>
                </div>
              </td>

              <td>
                <VTextField
                  v-model.number="line.offered_qty"
                  type="number"
                  min="0.01"
                  step="any"
                  density="compact"
                  hide-details
                />
              </td>

              <td>
                <VTextField
                  v-model.number="line.offered_unit_price_ht"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  hide-details
                />
              </td>

              <td class="text-center">
                <VChip
                  v-if="calcVsTargetPct(line) !== null"
                  size="x-small"
                  :color="resolveVsTargetColor(calcVsTargetPct(line))"
                  variant="tonal"
                  label
                  class="font-weight-bold"
                >
                  {{ calcVsTargetPct(line) > 0 ? `+${calcVsTargetPct(line)}%` : `${calcVsTargetPct(line)}%` }}
                </VChip>
                <span
                  v-else
                  class="text-caption text-disabled"
                >N/A</span>
              </td>

              <td>
                <VTextField
                  v-model.number="line.offered_lead_time_days"
                  type="number"
                  min="0"
                  step="1"
                  density="compact"
                  hide-details
                />
              </td>

              <td>
                <VCombobox
                  v-model="line.certifications_confirmed"
                  :items="line.required_certifications"
                  density="compact"
                  multiple
                  chips
                  closable-chips
                  hide-details
                  :placeholder="t('scp.rfq.fields.certifications_confirmed', 'Certs...')"
                />
              </td>

              <td class="text-end font-weight-semibold">
                {{ formatMoney(lineTotal(line)) }}
              </td>
            </tr>
          </tbody>
        </VTable>

        <!-- Mobile Card List -->
        <div
          v-else
          class="d-flex flex-column gap-3"
        >
          <WpMobileCard
            v-for="(line, idx) in form.lines"
            :key="line.rfq_line_id || idx"
          >
            <div class="d-flex align-start justify-space-between gap-2 mb-2">
              <div class="min-w-0">
                <div class="text-body-2 font-weight-bold text-high-emphasis">
                  {{ line.item_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ line.sku }} · {{ t('scp.rfq.fields.target_price_ht') }}: {{ formatMoney(line.target_price_ht) }}
                </div>
              </div>
              <VChip
                v-if="calcVsTargetPct(line) !== null"
                size="x-small"
                :color="resolveVsTargetColor(calcVsTargetPct(line))"
                variant="tonal"
                label
                class="font-weight-bold flex-shrink-0"
              >
                {{ calcVsTargetPct(line) > 0 ? `+${calcVsTargetPct(line)}%` : `${calcVsTargetPct(line)}%` }}
              </VChip>
            </div>

            <VRow dense>
              <VCol cols="6">
                <VTextField
                  v-model.number="line.offered_qty"
                  type="number"
                  min="0.01"
                  step="any"
                  density="compact"
                  :label="t('scp.rfq.fields.offered_qty')"
                  hide-details
                />
              </VCol>
              <VCol cols="6">
                <VTextField
                  v-model.number="line.offered_unit_price_ht"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  :label="t('scp.rfq.fields.offered_unit_price_ht')"
                  hide-details
                />
              </VCol>
              <VCol cols="6">
                <VTextField
                  v-model.number="line.offered_lead_time_days"
                  type="number"
                  min="0"
                  step="1"
                  density="compact"
                  :label="t('scp.rfq.fields.offered_lead_time_days')"
                  hide-details
                />
              </VCol>
              <VCol cols="6">
                <div class="text-caption text-medium-emphasis">
                  {{ t('scp.common.total_ht') }}
                </div>
                <div class="text-body-2 font-weight-bold text-primary">
                  {{ formatMoney(lineTotal(line)) }}
                </div>
              </VCol>
              <VCol cols="12">
                <VCombobox
                  v-model="line.certifications_confirmed"
                  :items="line.required_certifications"
                  density="compact"
                  multiple
                  chips
                  closable-chips
                  hide-details
                  :label="t('scp.rfq.fields.certifications_confirmed', 'Confirmed Certifications')"
                />
              </VCol>
            </VRow>
          </WpMobileCard>
        </div>
      </VCol>

      <VCol cols="12">
        <VTextarea
          v-model="form.supplier_notes"
          :label="t('scp.rfq.fields.supplier_notes')"
          rows="2"
          auto-grow
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
