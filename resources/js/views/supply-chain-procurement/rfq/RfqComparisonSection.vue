<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  rfq: {
    type: Object,
    default: () => ({}),
  },
  lines: {
    type: Array,
    default: () => [],
  },
  responses: {
    type: Array,
    default: () => [],
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  submittingAward: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['record-response', 'edit-response', 'toggle-award', 'convert-to-po'])

const { t } = useI18n()
const router = useRouter()
const { smAndDown } = useDisplay()
const { format: formatMoney } = useCurrency()

const {
  formatDate,
  paymentTermLabel,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const awardDialogOpen = ref(false)
const supplierToAward = ref(null)

const isAwarded = computed(() => props.rfq?.status === 'awarded')
const canRecordResponse = computed(() => !props.readonly && ['sent', 'awaiting_responses', 'under_evaluation'].includes(props.rfq?.status))
const canAward = computed(() => !props.readonly && ['sent', 'awaiting_responses', 'under_evaluation'].includes(props.rfq?.status))

const getResponseLine = (response, rfqLineId) => {
  const list = response?.response_lines || response?.lines || []

  return list.find(rl => (rl.rfq_line_id || rl.rfq_line?.id) === rfqLineId) || null
}

const getLinePriceStats = rfqLineId => {
  const prices = []

  ;(props.responses || []).forEach(resp => {
    const rl = getResponseLine(resp, rfqLineId)
    const p = Number(rl?.offered_unit_price_ht)
    if (p > 0) prices.push(p)
  })

  if (!prices.length) return { min: null, max: null }

  return {
    min: Math.min(...prices),
    max: Math.max(...prices),
  }
}

const getPriceCellClass = (response, rfqLineId) => {
  if ((props.responses || []).length < 2) return ''
  const rl = getResponseLine(response, rfqLineId)
  const price = Number(rl?.offered_unit_price_ht)
  if (!price || price <= 0) return ''

  const { min, max } = getLinePriceStats(rfqLineId)
  if (min !== null && price === min) return 'price-best'
  if (max !== null && min !== max && price === max) return 'price-worst'

  return ''
}

const isLowestTotal = totalHt => {
  if ((props.responses || []).length < 2) return false

  const totals = (props.responses || [])
    .map(r => Number(r.total_ht))
    .filter(v => v > 0)

  if (!totals.length) return false

  return Number(totalHt) === Math.min(...totals)
}

const deliveryComparison = canDeliverBy => {
  if (!canDeliverBy || !props.rfq?.required_delivery_date) return null
  const reqDate = new Date(props.rfq.required_delivery_date)
  const offDate = new Date(canDeliverBy)
  const diffDays = Math.round((offDate - reqDate) / (1000 * 60 * 60 * 24))

  if (diffDays <= 0) {
    return {
      color: 'success',
      label: diffDays === 0
        ? t('scp.rfq.comparison.on_time', 'On time')
        : `${Math.abs(diffDays)}d ${t('scp.rfq.comparison.early', 'early')}`,
    }
  }

  return {
    color: 'error',
    label: `${diffDays}d ${t('scp.rfq.comparison.late', 'late')}`,
  }
}

const resolveScoreColor = score => {
  const num = Number(score) || 0
  if (num >= 80) return 'success'
  if (num >= 60) return 'warning'

  return 'error'
}

const resolveVsTargetColor = pct => {
  const num = Number(pct)
  if (isNaN(num)) return 'secondary'
  if (num <= 0) return 'success'
  if (num <= 10) return 'warning'

  return 'error'
}

const promptAward = resp => {
  supplierToAward.value = resp.supplier || { id: resp.supplier_id, legal_name: resolveSupplierName(resp.supplier) }
  awardDialogOpen.value = true
}

const confirmAward = () => {
  if (!supplierToAward.value) return
  const sid = supplierToAward.value.id || supplierToAward.value.supplier_id

  awardDialogOpen.value = false
  emit('toggle-award', sid)
}

const handleRevokeAward = () => {
  const sid = props.rfq?.awarded_to_supplier_id || props.rfq?.awarded_to?.id

  emit('toggle-award', sid)
}

const handleConvertToPo = () => {
  if (props.rfq?.converted_to_po_id) {
    router.push(`/supply-chain-procurement/po/edit/${props.rfq.converted_to_po_id}`)

    return
  }
  emit('convert-to-po')
}

const responsesWithNotes = computed(() =>
  (props.responses || []).filter(r => r.supplier_notes),
)
</script>

<template>
  <div>
    <!-- Awarded Banner Card -->
    <VCard
      v-if="isAwarded"
      variant="tonal"
      color="success"
      class="mb-4 border-success"
    >
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="success"
            variant="flat"
            size="44"
          >
            <VIcon
              icon="tabler-trophy"
              size="24"
              color="white"
            />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
              {{ t('scp.rfq.comparison.awarded_to', 'RFQ Awarded to') }}
              <span class="text-success">{{ resolveSupplierName(rfq?.awarded_to) }}</span>
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('scp.rfq.comparison.awarded_on', 'Awarded on') }} {{ formatDate(rfq?.awarded_at) }}
              <span v-if="rfq?.awarded_by_user_name">
                · {{ t('scp.rfq.comparison.approved_by', 'Approved by') }} <strong>{{ rfq.awarded_by_user_name }}</strong>
              </span>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-center gap-2">
          <VBtn
            color="success"
            variant="flat"
            size="small"
            :prepend-icon="rfq?.converted_to_po_id ? 'tabler-external-link' : 'tabler-shopping-cart-plus'"
            @click="handleConvertToPo"
          >
            {{ rfq?.converted_to_po_id ? t('scp.rfq.actions.view_po', 'View Purchase Order') : t('scp.rfq.actions.convert_to_po', 'Convert to PO') }}
          </VBtn>

          <VBtn
            v-if="!readonly && !rfq?.converted_to_po_id"
            color="warning"
            variant="tonal"
            size="small"
            prepend-icon="tabler-rotate-2"
            :loading="submittingAward"
            @click="handleRevokeAward"
          >
            {{ t('scp.rfq.actions.revoke_award', 'Revoke Award') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <WpSectionCard
      :title="t('scp.rfq.sections.comparison', 'Supplier Bid Comparison')"
      icon="tabler-scale"
    >
      <template #actions>
        <VBtn
          v-if="canRecordResponse"
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="emit('record-response')"
        >
          {{ t('scp.rfq.actions.record_response', 'Record Supplier Bid') }}
        </VBtn>
      </template>

      <WpEmptyState
        v-if="!responses || responses.length === 0"
        icon="tabler-scale-off"
        :title="t('scp.rfq.empty.no_responses_title', 'No supplier bids recorded yet')"
        :subtitle="t('scp.rfq.empty.no_responses_subtitle', 'Record supplier quotations to compare unit prices, lead times, certifications, and weighted evaluation scores side by side.')"
      >
        <template
          v-if="canRecordResponse"
          #action
        >
          <VBtn
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="emit('record-response')"
          >
            {{ t('scp.rfq.actions.record_response', 'Record Supplier Bid') }}
          </VBtn>
        </template>
      </WpEmptyState>

      <!-- Desktop Side-by-Side Comparison Matrix -->
      <div
        v-else-if="!smAndDown"
        class="comparison-table-wrapper border rounded-lg"
      >
        <table class="comparison-table w-100">
          <thead>
            <tr>
              <th class="sticky-col bg-surface text-start pa-4 border-b border-e">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                  {{ t('scp.rfq.comparison.criteria_items', 'Criteria / Requested Items') }}
                </span>
              </th>

              <th
                v-for="resp in responses"
                :key="resp.id"
                class="supplier-col text-center pa-4 border-b border-e"
                :class="{ 'awarded-col': resp.is_awarded || rfq?.awarded_to_supplier_id === resp.supplier_id }"
              >
                <div class="d-flex flex-column align-center gap-1">
                  <div class="d-flex align-center gap-1">
                    <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                      {{ resolveSupplierName(resp.supplier) }}
                    </span>
                    <VBtn
                      v-if="!readonly && !isAwarded"
                      icon="tabler-pencil"
                      size="x-small"
                      variant="text"
                      color="secondary"
                      :aria-label="t('scp.action.edit')"
                      @click="emit('edit-response', resp)"
                    />
                  </div>

                  <span class="text-caption text-medium-emphasis">
                    {{ t('scp.rfq.comparison.submitted', 'Submitted') }}: {{ formatDate(resp.submitted_at || resp.created_at) }}
                  </span>

                  <div class="mt-2">
                    <VChip
                      v-if="resp.is_awarded || rfq?.awarded_to_supplier_id === resp.supplier_id"
                      color="success"
                      variant="flat"
                      size="small"
                      prepend-icon="tabler-trophy"
                      class="font-weight-bold"
                    >
                      {{ t('scp.rfq.comparison.awarded_badge', 'AWARDED') }}
                    </VChip>

                    <VBtn
                      v-else-if="canAward"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      prepend-icon="tabler-award"
                      :loading="submittingAward"
                      @click="promptAward(resp)"
                    >
                      {{ t('scp.rfq.actions.award_supplier', 'Award') }}
                    </VBtn>
                  </div>
                </div>
              </th>
            </tr>
          </thead>

          <tbody>
            <!-- Delivery Date Row -->
            <tr class="meta-row">
              <td class="sticky-col bg-surface pa-3 border-b border-e font-weight-medium text-body-2">
                <VIcon
                  icon="tabler-truck-delivery"
                  size="16"
                  class="me-2 text-medium-emphasis"
                />
                {{ t('scp.rfq.fields.can_deliver_by', 'Delivery Date') }}
                <div
                  v-if="rfq?.required_delivery_date"
                  class="text-caption text-medium-emphasis"
                >
                  {{ t('scp.rfq.fields.required_delivery_date') }}: {{ formatDate(rfq.required_delivery_date) }}
                </div>
              </td>
              <td
                v-for="resp in responses"
                :key="resp.id"
                class="text-center pa-3 border-b border-e"
              >
                <div class="font-weight-semibold text-body-2">
                  {{ formatDate(resp.can_deliver_by) }}
                </div>
                <VChip
                  v-if="deliveryComparison(resp.can_deliver_by)"
                  size="x-small"
                  :color="deliveryComparison(resp.can_deliver_by).color"
                  variant="tonal"
                  label
                  class="mt-1"
                >
                  {{ deliveryComparison(resp.can_deliver_by).label }}
                </VChip>
              </td>
            </tr>

            <!-- Payment Terms Row -->
            <tr class="meta-row">
              <td class="sticky-col bg-surface pa-3 border-b border-e font-weight-medium text-body-2">
                <VIcon
                  icon="tabler-credit-card"
                  size="16"
                  class="me-2 text-medium-emphasis"
                />
                {{ t('scp.rfq.fields.payment_terms_offered', 'Payment Terms') }}
              </td>
              <td
                v-for="resp in responses"
                :key="resp.id"
                class="text-center pa-3 border-b border-e"
              >
                <VChip
                  v-if="resp.payment_terms_offered"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  label
                  class="font-weight-semibold"
                >
                  {{ paymentTermLabel(resp.payment_terms_offered) }}
                </VChip>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </td>
            </tr>

            <!-- Evaluation Score Row -->
            <tr class="meta-row">
              <td class="sticky-col bg-surface pa-3 border-b border-e font-weight-medium text-body-2">
                <VIcon
                  icon="tabler-chart-radar"
                  size="16"
                  class="me-2 text-medium-emphasis"
                />
                {{ t('scp.rfq.fields.evaluation_score', 'Evaluation Score') }}
              </td>
              <td
                v-for="resp in responses"
                :key="resp.id"
                class="text-center pa-3 border-b border-e"
              >
                <VTooltip
                  v-if="resp.evaluation_score !== null && resp.evaluation_score !== undefined"
                  location="top"
                >
                  <template #activator="{ props: tp }">
                    <VChip
                      v-bind="tp"
                      size="small"
                      :color="resolveScoreColor(resp.evaluation_score)"
                      variant="tonal"
                      class="font-weight-bold cursor-pointer"
                    >
                      {{ Number(resp.evaluation_score).toFixed(1) }} / 100
                      <VIcon
                        icon="tabler-info-circle"
                        size="14"
                        class="ms-1"
                      />
                    </VChip>
                  </template>
                  <div
                    v-if="resp.evaluation_details"
                    class="pa-1 text-caption"
                  >
                    <div class="font-weight-bold mb-1 border-b pb-1">
                      {{ t('scp.rfq.comparison.score_breakdown', 'Score Breakdown') }}
                    </div>
                    <div>Price: <strong>{{ resp.evaluation_details.price_score ?? 0 }}</strong> ({{ rfq?.evaluation_criteria?.price_weight ?? 0 }}%)</div>
                    <div>Lead Time: <strong>{{ resp.evaluation_details.lead_time_score ?? 0 }}</strong> ({{ rfq?.evaluation_criteria?.lead_time_weight ?? 0 }}%)</div>
                    <div>Quality: <strong>{{ resp.evaluation_details.quality_score ?? 0 }}</strong> ({{ rfq?.evaluation_criteria?.quality_weight ?? 0 }}%)</div>
                    <div>Technical: <strong>{{ resp.evaluation_details.technical_score ?? 0 }}</strong> ({{ rfq?.evaluation_criteria?.technical_weight ?? 0 }}%)</div>
                    <div>Payment: <strong>{{ resp.evaluation_details.payment_score ?? 0 }}</strong> ({{ rfq?.evaluation_criteria?.payment_weight ?? 0 }}%)</div>
                  </div>
                  <span v-else>{{ t('scp.rfq.fields.evaluation_score') }}</span>
                </VTooltip>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </td>
            </tr>

            <!-- Requested Item Rows -->
            <tr
              v-for="line in lines"
              :key="line.id"
            >
              <td class="sticky-col bg-surface pa-3 border-b border-e">
                <div class="font-weight-semibold text-body-2 text-high-emphasis">
                  {{ resolveLineItemName(line) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ resolveLineItemSku(line) }} · {{ t('scp.common.qty') }}: <strong>{{ line.qty_requested ?? line.quantity }}</strong>
                </div>
                <div
                  v-if="line.target_price_ht"
                  class="text-caption text-primary font-weight-medium"
                >
                  {{ t('scp.rfq.fields.target_price_ht') }}: {{ formatMoney(line.target_price_ht) }}
                </div>
              </td>

              <td
                v-for="resp in responses"
                :key="resp.id"
                class="text-center pa-3 border-b border-e"
                :class="getPriceCellClass(resp, line.id)"
              >
                <template v-if="getResponseLine(resp, line.id)">
                  <div class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ formatMoney(getResponseLine(resp, line.id).offered_unit_price_ht) }}
                    <span class="text-caption text-medium-emphasis font-weight-regular">/ u</span>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-1">
                    {{ t('scp.common.total') }}: {{ formatMoney((Number(getResponseLine(resp, line.id).offered_unit_price_ht) || 0) * (Number(getResponseLine(resp, line.id).offered_qty) || 0)) }}
                  </div>

                  <div class="d-flex flex-wrap justify-center gap-1 mt-1">
                    <VChip
                      v-if="getResponseLine(resp, line.id).offered_lead_time_days"
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                      label
                    >
                      <VIcon
                        icon="tabler-clock"
                        size="11"
                        class="me-1"
                      />
                      {{ getResponseLine(resp, line.id).offered_lead_time_days }}d
                    </VChip>

                    <VChip
                      v-if="getResponseLine(resp, line.id).certifications_confirmed?.length"
                      size="x-small"
                      color="info"
                      variant="tonal"
                      label
                    >
                      <VIcon
                        icon="tabler-certificate"
                        size="11"
                        class="me-1"
                      />
                      {{ getResponseLine(resp, line.id).certifications_confirmed.length }}
                    </VChip>

                    <VChip
                      v-if="getResponseLine(resp, line.id).vs_target_pct !== null && getResponseLine(resp, line.id).vs_target_pct !== undefined"
                      size="x-small"
                      :color="resolveVsTargetColor(getResponseLine(resp, line.id).vs_target_pct)"
                      variant="tonal"
                      label
                      class="font-weight-bold"
                    >
                      {{ Number(getResponseLine(resp, line.id).vs_target_pct) > 0 ? `+${getResponseLine(resp, line.id).vs_target_pct}%` : `${getResponseLine(resp, line.id).vs_target_pct}%` }}
                    </VChip>
                  </div>
                </template>
                <span
                  v-else
                  class="text-caption text-disabled"
                >—</span>
              </td>
            </tr>
          </tbody>

          <tfoot>
            <tr class="total-row">
              <td class="sticky-col bg-surface pa-4 border-e font-weight-bold text-subtitle-2">
                {{ t('scp.common.total_ht', 'Total Bid (HT)') }}
              </td>
              <td
                v-for="resp in responses"
                :key="resp.id"
                class="text-center pa-4 border-e"
              >
                <span
                  class="text-subtitle-1 font-weight-bold"
                  :class="isLowestTotal(resp.total_ht) ? 'text-success' : 'text-high-emphasis'"
                >
                  {{ formatMoney(resp.total_ht) }}
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Mobile Card Comparison List -->
      <div
        v-else
        class="d-flex flex-column gap-3"
      >
        <WpMobileCard
          v-for="resp in responses"
          :key="resp.id"
        >
          <div class="d-flex align-start justify-space-between gap-2 mb-2">
            <div>
              <div class="text-subtitle-2 font-weight-bold text-high-emphasis">
                {{ resolveSupplierName(resp.supplier) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(resp.submitted_at || resp.created_at) }}
              </div>
            </div>

            <div class="d-flex align-center gap-1">
              <VChip
                v-if="resp.evaluation_score !== null && resp.evaluation_score !== undefined"
                size="x-small"
                :color="resolveScoreColor(resp.evaluation_score)"
                variant="tonal"
                label
                class="font-weight-bold"
              >
                {{ Number(resp.evaluation_score).toFixed(1) }} pts
              </VChip>
              <VBtn
                v-if="!readonly && !isAwarded"
                icon="tabler-pencil"
                size="x-small"
                variant="text"
                color="secondary"
                @click="emit('edit-response', resp)"
              />
            </div>
          </div>

          <div class="d-flex flex-wrap align-center justify-space-between gap-2 py-2 border-t border-b mb-2">
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ t('scp.common.total_ht') }}
              </div>
              <div
                class="text-subtitle-1 font-weight-bold"
                :class="isLowestTotal(resp.total_ht) ? 'text-success' : 'text-primary'"
              >
                {{ formatMoney(resp.total_ht) }}
              </div>
            </div>

            <div class="text-end">
              <div class="text-caption text-medium-emphasis">
                {{ t('scp.rfq.fields.can_deliver_by') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ formatDate(resp.can_deliver_by) }}
              </div>
            </div>
          </div>

          <div class="d-flex justify-end">
            <VChip
              v-if="resp.is_awarded || rfq?.awarded_to_supplier_id === resp.supplier_id"
              color="success"
              variant="flat"
              size="small"
              prepend-icon="tabler-trophy"
              class="font-weight-bold"
            >
              {{ t('scp.rfq.comparison.awarded_badge', 'AWARDED') }}
            </VChip>
            <VBtn
              v-else-if="canAward"
              size="small"
              color="success"
              variant="tonal"
              prepend-icon="tabler-award"
              :loading="submittingAward"
              @click="promptAward(resp)"
            >
              {{ t('scp.rfq.actions.award_supplier', 'Award') }}
            </VBtn>
          </div>
        </WpMobileCard>
      </div>

      <!-- Supplier Notes Cards -->
      <div
        v-if="responsesWithNotes.length"
        class="mt-4"
      >
        <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
          {{ t('scp.rfq.fields.supplier_notes', 'Supplier Notes & Remarks') }}
        </div>
        <VRow dense>
          <VCol
            v-for="resp in responsesWithNotes"
            :key="resp.id"
            cols="12"
            md="6"
          >
            <VCard
              variant="tonal"
              color="secondary"
            >
              <VCardText class="py-3 px-4">
                <div class="text-caption font-weight-bold text-high-emphasis mb-1">
                  {{ resolveSupplierName(resp.supplier) }}
                </div>
                <div class="text-body-2">
                  {{ resp.supplier_notes }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </WpSectionCard>

    <!-- Award Confirmation Dialog -->
    <WpConfirmDialog
      v-model="awardDialogOpen"
      :title="t('scp.rfq.actions.award_supplier', 'Award RFQ to Supplier')"
      :message="t('scp.rfq.comparison.confirm_award_message', { supplier: resolveSupplierName(supplierToAward) }, `Are you sure you want to award this RFQ to ${resolveSupplierName(supplierToAward)}?`)"
      type="success"
      :confirm-text="t('scp.rfq.actions.award_supplier', 'Confirm Award')"
      :loading="submittingAward"
      @confirm="confirmAward"
    />
  </div>
</template>

<style scoped lang="scss">
.comparison-table-wrapper {
  overflow-x: auto;
  max-inline-size: 100%;
}

.comparison-table {
  border-collapse: collapse;
  min-inline-size: 640px;

  .sticky-col {
    position: sticky;
    inset-inline-start: 0;
    z-index: 2;
    min-inline-size: 240px;
    max-inline-size: 280px;
  }

  .supplier-col {
    min-inline-size: 210px;
  }

  .awarded-col {
    background-color: rgba(var(--v-theme-success), 0.08);
  }

  .meta-row td:not(.sticky-col) {
    background-color: rgba(var(--v-theme-on-surface), 0.02);
  }

  .price-best {
    background-color: rgba(var(--v-theme-success), 0.1) !important;
  }

  .price-worst {
    background-color: rgba(var(--v-theme-error), 0.06) !important;
  }

  .total-row td {
    background-color: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
