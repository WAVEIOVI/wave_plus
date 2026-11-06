<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: false },
  rfq: { type: Object, default: null },
  responseToEdit: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'submitted'])

const rfqStore = useRfqStore()
const snackbarStore = useSnackbarStore()

// ── Form State ──────────────────────────────────────────────────────────
const isSubmitting = ref(false)
const selectedSupplierId = ref(null)
const validityUntil = ref(null)
const canDeliverBy = ref(null)
const paymentTermsOffered = ref(null)
const supplierNotes = ref('')

const responseLines = ref([])

// ── Options ─────────────────────────────────────────────────────────────
const paymentTermsOptions = [
  { title: 'Net 15', value: 'net_15' },
  { title: 'Net 30', value: 'net_30' },
  { title: 'Net 60', value: 'net_60' },
  { title: 'Net 90', value: 'net_90' },
  { title: 'Immediate', value: 'immediate' },
  { title: 'Other', value: 'other' },
]

// ── Computed ─────────────────────────────────────────────────────────────
const invitedSuppliers = computed(() => {
  if (!props.rfq?.invitations) return []

  const submittedSupplierIds = (props.rfq?.responses || []).map(r => r.supplier_id)

  return props.rfq.invitations
    .filter(inv => {
      if (props.responseToEdit) return inv.supplier_id === props.responseToEdit.supplier_id
      
      return !submittedSupplierIds.includes(inv.supplier_id)
    })
    .map(inv => ({
      title: `${inv.supplier?.supplier_id || ''} — ${inv.supplier?.legal_name || `Supplier #${inv.supplier_id}`}`,
      value: inv.supplier_id,
      raw: inv,
    }))
})

const computedTotalHt = computed(() => {
  return responseLines.value.reduce((sum, line) => {
    const price = parseFloat(line.offered_unit_price_ht) || 0
    const qty = parseFloat(line.offered_qty) || 0

    return sum + (price * qty)
  }, 0)
})

// ── Reset form when dialog opens with new RFQ ───────────────────────────
watch(
  () => props.isDialogVisible,
  visible => {
    if (visible && props.rfq) {
      populateForm(props.responseToEdit)
    }
  },
)

const populateForm = (response = null) => {
  selectedSupplierId.value = response ? response.supplier_id : null
  validityUntil.value = response?.validity_until ? response.validity_until.substring(0, 10) : null
  canDeliverBy.value = response?.can_deliver_by ? response.can_deliver_by.substring(0, 10) : null
  paymentTermsOffered.value = response?.payment_terms_offered || null
  supplierNotes.value = response?.supplier_notes || ''

  responseLines.value = (props.rfq?.lines || []).map(line => {
    const existingLine = response?.lines?.find(l => l.rfq_line_id === line.id)
    
    return {
      rfq_line_id: line.id,
      _code: line.is_ad_hoc ? 'AD-HOC' : (line.catalog_item?.code || '—'),
      _sku: line.is_ad_hoc ? (line.ad_hoc_sku || 'NEW') : (line.variant?.sku || line.catalog_item?.sku || '—'),
      _name: line.is_ad_hoc
        ? (line.ad_hoc_name || 'Ad-hoc Item')
        : (line.catalog_item?.name ? localizedValue(line.catalog_item.name) : '—'),
      _qty_requested: line.qty_requested,
      _target_price_ht: line.target_price_ht,
      _unit: line.unit || line.catalog_item?.uom || null,
      offered_unit_price_ht: existingLine ? existingLine.offered_unit_price_ht : (line.target_price_ht || null),
      offered_qty: existingLine ? existingLine.offered_qty : (line.qty_requested || 1),
      offered_lead_time_days: existingLine ? existingLine.offered_lead_time_days : null,
      certifications_confirmed: existingLine?.certifications_confirmed || [],
    }
  })
}

// ── Line Display Helper ─────────────────────────────────────────────────
const getLineSubtotal = line => {
  const price = parseFloat(line.offered_unit_price_ht) || 0
  const qty = parseFloat(line.offered_qty) || 0

  return (price * qty).toFixed(3)
}

const getVsTargetPct = line => {
  if (!line._target_price_ht || !line.offered_unit_price_ht) return null
  const target = parseFloat(line._target_price_ht)
  const offered = parseFloat(line.offered_unit_price_ht)
  if (target <= 0) return null

  return (((offered - target) / target) * 100).toFixed(1)
}

const getVsTargetColor = pct => {
  if (pct === null) return 'secondary'
  const val = parseFloat(pct)
  if (val <= 0) return 'success'
  if (val <= 10) return 'warning'

  return 'error'
}

// ── Submit ───────────────────────────────────────────────────────────────
const submitBid = async () => {
  if (!selectedSupplierId.value) {
    snackbarStore.showMessage('Please select a supplier.', 'error')

    return
  }

  const invalidLine = responseLines.value.find(l => !l.offered_unit_price_ht || !l.offered_qty)
  if (invalidLine) {
    snackbarStore.showMessage('Please fill in price and quantity for all lines.', 'error')

    return
  }

  isSubmitting.value = true
  try {
    await rfqStore.submitResponse(props.rfq.id, {
      supplier_id: selectedSupplierId.value,
      validity_until: validityUntil.value || null,
      can_deliver_by: canDeliverBy.value || null,
      payment_terms_offered: paymentTermsOffered.value || null,
      total_ht: computedTotalHt.value,
      supplier_notes: supplierNotes.value || null,
      lines: responseLines.value.map(l => ({
        rfq_line_id: l.rfq_line_id,
        offered_unit_price_ht: l.offered_unit_price_ht,
        offered_qty: l.offered_qty,
        offered_lead_time_days: l.offered_lead_time_days || null,
        certifications_confirmed: l.certifications_confirmed?.length ? l.certifications_confirmed : null,
      })),
    })

    snackbarStore.showMessage('Supplier bid submitted successfully.', 'success')
    emit('update:isDialogVisible', false)
    emit('submitted')
  }
  catch (error) {
    const msg = error?.data?.message || error?.message || 'Failed to submit bid.'

    snackbarStore.showMessage(msg, 'error')
    console.error(error)
  }
  finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="1100"
    persistent
    scrollable
    @update:model-value="$emit('update:isDialogVisible', $event)"
  >
    <VCard class="bid-entry-dialog">
      <!-- ── Header ── -->
      <VCardItem class="bid-entry-dialog__header pa-5">
        <VCardTitle class="d-flex align-center gap-4">
          <VAvatar
            rounded="lg"
            size="54"
            class="elevation-2"
            style="background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.2), rgba(var(--v-theme-primary), 0.05));"
          >
            <VIcon
              icon="tabler-receipt-2"
              size="28"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h4 font-weight-black text-primary">{{ responseToEdit ? 'Edit Supplier Bid' : 'Enter Supplier Bid' }}</span>
            <span class="text-body-2 text-medium-emphasis d-flex align-center gap-2 mt-1 font-weight-medium">
              {{ rfq?.reference }}
              <VChip
                v-if="rfq?.title"
                size="x-small"
                label
                class="text-capitalize"
              >{{ rfq?.title }}</VChip>
            </span>
          </div>
        </VCardTitle>

        <template #append>
          <IconBtn @click="closeDialog">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <!-- ── Body ── -->
      <VCardText class="bid-entry-dialog__body pa-6 bg-var-theme-surface">
        <!-- ── Supplier & Dates ── -->
        <VRow class="mb-4">
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="selectedSupplierId"
              label="Responding Supplier *"
              :items="invitedSuppliers"
              placeholder="Select supplier…"
              :disabled="!!responseToEdit"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="validityUntil"
              label="Bid Valid Until"
              type="date"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="canDeliverBy"
              label="Can Deliver By"
              type="date"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="paymentTermsOffered"
              label="Payment Terms"
              :items="paymentTermsOptions"
              clearable
            />
          </VCol>
        </VRow>

        <!-- ── Lines Table ── -->
        <VCard
          variant="outlined"
          class="bid-lines-card border rounded-lg"
          elevation="0"
        >
          <VCardItem class="pa-4 bg-var-theme-background">
            <VCardTitle class="d-flex align-center gap-2 text-h6 font-weight-bold">
              <VIcon
                icon="tabler-list-details"
                size="22"
                color="primary"
              />
              Line Items
              <VChip
                size="x-small"
                label
                color="primary"
                variant="tonal"
                class="ms-1"
              >
                {{ responseLines.length }}
              </VChip>
            </VCardTitle>
          </VCardItem>
          <VDivider />

          <VTable
            density="comfortable"
            class="bid-lines-table"
          >
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th class="text-center">
                  Req. Qty
                </th>
                <th class="text-center">
                  Target HT
                </th>
                <th class="text-center">
                  Offered Price HT *
                </th>
                <th class="text-center">
                  Offered Qty *
                </th>
                <th class="text-center">
                  Lead Time (days)
                </th>
                <th class="text-center">
                  Certs Confirmed
                </th>
                <th class="text-end">
                  Subtotal
                </th>
                <th class="text-center">
                  vs Target
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, index) in responseLines"
                :key="index"
              >
                <!-- Code -->
                <td>
                  <VChip
                    v-if="line._code === 'AD-HOC'"
                    size="small"
                    color="warning"
                    label
                    variant="flat"
                  >
                    AD-HOC
                  </VChip>
                  <span
                    v-else
                    class="text-body-2 font-weight-bold text-primary"
                  >
                    {{ line._code }}
                  </span>
                  <div class="text-xs text-disabled">
                    {{ line._sku }}
                  </div>
                </td>

                <!-- Name -->
                <td>
                  <span
                    class="text-body-2 d-inline-block text-truncate"
                    style="max-inline-size: 180px;"
                    :title="line._name"
                  >
                    {{ line._name }}
                  </span>
                </td>

                <!-- Requested Qty -->
                <td class="text-center">
                  <span class="text-body-2 text-medium-emphasis">
                    {{ line._qty_requested }}
                    <span
                      v-if="line._unit?.abbreviation"
                      class="text-caption text-disabled"
                    >{{ line._unit.abbreviation }}</span>
                  </span>
                </td>

                <!-- Target Price -->
                <td class="text-center">
                  <span class="text-body-2 text-medium-emphasis">
                    {{ line._target_price_ht ? parseFloat(line._target_price_ht).toFixed(3) : '—' }}
                  </span>
                </td>

                <!-- Offered Price -->
                <td>
                  <AppTextField
                    v-model="line.offered_unit_price_ht"
                    type="number"
                    min="0"
                    step="0.001"
                    density="compact"
                    variant="outlined"
                    hide-details
                    class="bid-input bid-input--price"
                  />
                </td>

                <!-- Offered Qty -->
                <td>
                  <AppTextField
                    v-model="line.offered_qty"
                    type="number"
                    min="0.01"
                    step="0.01"
                    density="compact"
                    variant="outlined"
                    hide-details
                    class="bid-input bid-input--qty"
                  />
                </td>

                <!-- Lead Time -->
                <td>
                  <AppTextField
                    v-model="line.offered_lead_time_days"
                    type="number"
                    min="0"
                    step="1"
                    density="compact"
                    variant="outlined"
                    hide-details
                    placeholder="—"
                    class="bid-input bid-input--lead"
                  />
                </td>

                <!-- Certs -->
                <td>
                  <AppCombobox
                    v-model="line.certifications_confirmed"
                    chips
                    multiple
                    placeholder="Confirmed..."
                    density="compact"
                    variant="outlined"
                    hide-details
                    class="bid-input bid-input--certs"
                  />
                </td>

                <!-- Subtotal -->
                <td class="text-end">
                  <span class="text-body-2 font-weight-bold">
                    {{ getLineSubtotal(line) }}
                  </span>
                </td>

                <!-- vs Target -->
                <td class="text-center">
                  <VChip
                    v-if="getVsTargetPct(line) !== null"
                    :color="getVsTargetColor(getVsTargetPct(line))"
                    size="x-small"
                    label
                    variant="flat"
                  >
                    {{ getVsTargetPct(line) > 0 ? '+' : '' }}{{ getVsTargetPct(line) }}%
                  </VChip>
                  <span
                    v-else
                    class="text-caption text-disabled"
                  >—</span>
                </td>
              </tr>
            </tbody>

            <!-- Total Footer -->
            <tfoot>
              <tr class="bid-total-row">
                <td colspan="8">
                  <div class="d-flex justify-end align-center w-100">
                    <span class="text-body-1 font-weight-bold text-uppercase text-medium-emphasis me-4">Total HT</span>
                  </div>
                </td>
                <td class="text-end">
                  <VChip
                    color="primary"
                    variant="tonal"
                    size="large"
                    class="px-4 text-h6 font-weight-black"
                  >
                    {{ computedTotalHt.toFixed(3) }}
                  </VChip>
                </td>
                <td />
              </tr>
            </tfoot>
          </VTable>
        </VCard>

        <!-- ── Notes ── -->
        <VRow class="mt-4">
          <VCol cols="12">
            <AppTextarea
              v-model="supplierNotes"
              label="Supplier Notes"
              rows="2"
              placeholder="Any notes or remarks from the supplier…"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- ── Actions ── -->
      <VCardActions class="bid-entry-dialog__actions pa-5">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-x"
          class="px-5"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          :loading="isSubmitting"
          color="primary"
          variant="elevated"
          prepend-icon="tabler-send"
          class="px-5"
          @click="submitBid"
        >
          Submit Bid
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
/* ── Dialog ──────────────────────────────────────────────────── */
.bid-entry-dialog {
  border-radius: 16px !important;
}

.bid-entry-dialog__header {
  background: linear-gradient(135deg, rgba(var(--v-theme-surface), 1) 0%, rgba(var(--v-theme-primary), 0.05) 100%);
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.bid-entry-dialog__body {
  max-block-size: 70vh;
  overflow-y: auto;
}

/* ── Lines Table ─────────────────────────────────────────────── */
.bid-lines-card {
  border-color: rgba(var(--v-theme-on-surface), 0.12) !important;
}

.bid-lines-table {
  :deep(thead th) {
    background: rgba(var(--v-theme-on-surface), 0.02) !important;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    opacity: 0.9;
    padding-block: 14px !important;
    text-transform: uppercase;
  }

  :deep(tbody tr) {
    transition: background-color 0.15s ease;

    &:hover {
      background: rgba(var(--v-theme-primary), 0.03) !important;
    }
  }
}

/* ── Inputs ──────────────────────────────────────────────────── */
.bid-input {
  min-inline-size: 90px;
}

.bid-input--price {
  max-inline-size: 130px;
}

.bid-input--qty {
  max-inline-size: 100px;
}

.bid-input--lead {
  max-inline-size: 90px;
}

.bid-input--certs {
  min-inline-size: 160px;
}

/* ── Total Row ───────────────────────────────────────────────── */
.bid-total-row {
  td {
    background: rgba(var(--v-theme-on-surface), 0.02);
    border-block-start: 2px solid rgba(var(--v-theme-on-surface), 0.12);
    padding-block: 16px !important;
  }
}

/* ── Actions ─────────────────────────────────────────────────── */
.bid-entry-dialog__actions {
  background: rgba(var(--v-theme-surface), 1);
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 2%);
}
</style>
