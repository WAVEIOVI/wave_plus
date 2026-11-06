<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */
import { localizedValue } from '@/composables/useLocaleField'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, onMounted, ref, watch } from 'vue'
import RfqResponseEntryModal from '../edit/RfqResponseEntryModal.vue'

const props = defineProps({
  rfqId: {
    type: [String, Number],
    required: true,
  },
  rfqStatus: {
    type: String,
    default: 'draft',
  },
  formAwardedSupplierId: {
    type: [String, Number],
    default: null,
  },
  viewOnlyMode: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['updated', 'toggle-award'])

const rfqStore = useRfqStore()
const snackbarStore = useSnackbarStore()

// ── State ───────────────────────────────────────────────────────────────
const isLoading = ref(true)
const rfq = ref(null)

// ── Status Visual Map ───────────────────────────────────────────────────
const statusColorMap = {
  draft: 'secondary',
  sent: 'info',
  awaiting_responses: 'warning',
  under_evaluation: 'primary',
  awarded: 'success',
  cancelled: 'default',
}

// ── Fetch ───────────────────────────────────────────────────────────────
const fetchCompareData = async () => {
  isLoading.value = true
  try {
    const data = await rfqStore.fetchDetails(props.rfqId)

    rfq.value = data
  }
  catch (e) {
    snackbarStore.showMessage('Failed to load RFQ comparison data', 'error')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchCompareData()
})

watch(() => [props.rfqId, props.formAwardedSupplierId, props.rfqStatus], () => {
  fetchCompareData()
}, { deep: true })

// ── Modals ──────────────────────────────────────────────────────────────
const isEntryModalVisible = ref(false)
const responseToEdit = ref(null)

const openEntryModal = (response = null) => {
  // If the event object from the button click is passed by mistake, check if it's a real response
  responseToEdit.value = response && response.id ? response : null
  isEntryModalVisible.value = true
}

const handleBidSubmitted = () => {
  fetchCompareData()
  emit('updated')
}

// ── Computed Helpers ────────────────────────────────────────────────────
const lines = computed(() => rfq.value?.lines || [])
const responses = computed(() => rfq.value?.responses || [])
const invitations = computed(() => rfq.value?.invitations || [])
const criteria = computed(() => rfq.value?.evaluation_criteria || {})

// Get supplier name from a response
const getSupplierName = response => {
  return response?.supplier?.legal_name || `Supplier #${response?.supplier_id}`
}

const getSupplierCode = response => {
  return response?.supplier?.supplier_id || '—'
}

// Get line item display name
const getLineName = line => {
  if (line.is_ad_hoc) return line.ad_hoc_name || 'Ad-hoc Item'
  if (line.catalog_item?.name) return localizedValue(line.catalog_item.name)

  return '—'
}

const getLineCode = line => {
  if (line.is_ad_hoc) return 'AD-HOC'

  return line.catalog_item?.code || '—'
}

// ── Price Analysis ──────────────────────────────────────────────────────
const lowestTotal = computed(() => {
  if (!responses.value.length) return 0

  return Math.min(...responses.value.map(r => parseFloat(r.total_ht) || Infinity))
})

const highestTotal = computed(() => {
  if (!responses.value.length) return 0

  return Math.max(...responses.value.map(r => parseFloat(r.total_ht) || 0))
})

const getResponsePriceClass = response => {
  const total = parseFloat(response.total_ht) || 0
  if (total === lowestTotal.value) return 'price-best'
  if (total === highestTotal.value) return 'price-worst'

  return ''
}

// ── Evaluation Score Badge ──────────────────────────────────────────────
const getScoreColor = score => {
  if (score === null || score === undefined) return 'secondary'
  if (score >= 80) return 'success'
  if (score >= 60) return 'primary'
  if (score >= 40) return 'warning'

  return 'error'
}

// ── Payment Terms Display ───────────────────────────────────────────────
const formatPaymentTerms = terms => {
  if (!terms) return '—'

  return terms.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

// ── Date Formatters ─────────────────────────────────────────────────────
const formatDate = isoString => {
  if (!isoString) return '—'
  const d = new Date(isoString)

  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const isAwardDialogVisible = ref(false)
const selectedResponseForAward = ref(null)

const toggleAward = response => {
  if (props.formAwardedSupplierId === response.supplier_id) {
    emit('toggle-award', response.supplier_id)
  } else {
    selectedResponseForAward.value = response
    isAwardDialogVisible.value = true
  }
}

const confirmAward = () => {
  if (selectedResponseForAward.value) {
    emit('toggle-award', selectedResponseForAward.value.supplier_id)
  }
  isAwardDialogVisible.value = false
  selectedResponseForAward.value = null
}
</script>

<template>
  <RfqResponseEntryModal
    v-model:is-dialog-visible="isEntryModalVisible"
    :rfq="rfq"
    :response-to-edit="responseToEdit"
    @submitted="handleBidSubmitted"
  />

  <!-- ── Loading Skeleton ── -->
  <div v-if="isLoading">
    <VCard class="pa-5">
      <VSkeletonLoader type="article, table-heading, table-row-divider@4" />
    </VCard>
  </div>

  <div v-else-if="rfq">
    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 👉 Header Bar                                                -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <VCard
      v-if="!viewOnlyMode"
      class="comparison-header-card mb-6"
      elevation="0"
      border
    >
      <VCardText class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between gap-4 py-4 px-5">
        <div class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="56"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-chart-bar"
              size="28"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <h4 class="text-h4 font-weight-bold">
              Bid Comparison & Evaluation
            </h4>
            <span class="text-body-2 text-medium-emphasis d-flex align-center gap-2 mt-1">
              {{ rfq.reference }}
              <VChip
                :color="statusColorMap[rfq.status]"
                size="small"
                label
                class="text-capitalize font-weight-medium"
              >
                {{ (rfq.status || '').replace(/_/g, ' ') }}
              </VChip>
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-3 flex-wrap">
          <VBtn
            v-if="rfq.status === 'awaiting_responses' || rfq.status === 'under_evaluation' || rfq.status === 'sent' || rfq.status === 'awarded'"
            variant="elevated"
            color="primary"
            prepend-icon="tabler-plus"
            @click="openEntryModal"
          >
            Enter Bid
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 👉 Awarding Info Header Card                                 -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <VCard
      v-if="rfq.status === 'awarded' && !viewOnlyMode"
      class="glass-panel border-success mb-6 bg-success-lighten-5"
    >
      <VCardText class="d-flex flex-column flex-md-row align-center justify-space-between gap-4 pa-5">
        <div class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="56"
            color="success"
            variant="elevated"
          >
            <VIcon
              icon="tabler-trophy"
              size="30"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <h4 class="text-h5 font-weight-bold text-success mb-1">
              Awarded to {{ responses.find(r => r.is_awarded)?.supplier?.legal_name || 'Supplier' }}
            </h4>
            <div class="d-flex align-center gap-3 text-body-2 text-medium-emphasis">
              <span class="d-flex align-center gap-1"><VIcon
                icon="tabler-calendar"
                size="16"
              /> {{ formatDate(rfq.awarded_at) }}</span>
              <span
                v-if="rfq.awarded_by"
                class="d-flex align-center gap-1"
              ><VIcon
                icon="tabler-user"
                size="16"
              /> Approved by {{ rfq.awarded_by_user ? `${rfq.awarded_by_user.first_name} ${rfq.awarded_by_user.last_name}` : rfq.awarded_by }}</span>
            </div>
          </div>
        </div>
        <VBtn
          color="error"
          variant="outlined"
          prepend-icon="tabler-arrow-back-up"
          @click="toggleAward({ supplier_id: formAwardedSupplierId })"
        >
          Revoke Award
        </VBtn>
      </VCardText>
    </VCard>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 👉 Evaluation Criteria Summary                               -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <VRow
      v-if="!viewOnlyMode"
      class="mb-6"
    >
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="primary"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-currency-dollar"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Price Weight</span>
            <h5 class="text-h4 font-weight-black text-primary">
              {{ criteria.price_weight || 0 }}<span class="text-h6">%</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="info"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-truck-delivery"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Lead Time</span>
            <h5 class="text-h4 font-weight-black text-info">
              {{ criteria.lead_time_weight || 0 }}<span class="text-h6">%</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="warning"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-star"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Quality</span>
            <h5 class="text-h4 font-weight-black text-warning">
              {{ criteria.quality_weight || 0 }}<span class="text-h6">%</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="error"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-shield-check"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Technical</span>
            <h5 class="text-h4 font-weight-black text-error">
              {{ criteria.technical_weight || 0 }}<span class="text-h6">%</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="success"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-credit-card"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Payment</span>
            <h5 class="text-h4 font-weight-black text-success">
              {{ criteria.payment_weight || 0 }}<span class="text-h6">%</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="2"
      >
        <VCard
          class="comparison-criteria-card glass-panel h-100"
          elevation="0"
          border
        >
          <VCardText class="d-flex flex-column align-center text-center pa-4">
            <VAvatar
              rounded
              size="48"
              color="secondary"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-users"
                size="26"
              />
            </VAvatar>
            <span class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Responses</span>
            <h5 class="text-h4 font-weight-black text-secondary">
              {{ responses.length }}<span class="text-h6 text-disabled">/{{ invitations.length }}</span>
            </h5>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 👉 Comparison Matrix                                         -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <VCard
      class="comparison-matrix-card"
      elevation="0"
      border
    >
      <VCardItem
        v-if="!viewOnlyMode"
        class="pa-4"
      >
        <VCardTitle class="d-flex align-center gap-2 text-h5 font-weight-bold">
          <VIcon
            icon="tabler-table"
            size="24"
            color="primary"
          />
          Bid Comparison Matrix
        </VCardTitle>
      </VCardItem>
      <VDivider v-if="!viewOnlyMode" />

      <!-- Empty state if no responses -->
      <div
        v-if="responses.length === 0"
        class="d-flex flex-column align-center justify-center pa-12"
      >
        <VAvatar
          rounded
          size="80"
          variant="tonal"
          color="warning"
          class="mb-4"
        >
          <VIcon
            icon="tabler-inbox"
            size="40"
          />
        </VAvatar>
        <h5 class="text-h5 font-weight-bold mb-2">
          No Responses Yet
        </h5>
        <p class="text-body-1 text-medium-emphasis">
          No supplier bids have been received for this RFQ.
        </p>
      </div>

      <!-- Matrix Table -->
      <div
        v-else
        class="comparison-table-wrapper"
      >
        <table class="comparison-table">
          <thead>
            <!-- Row 1: Supplier Headers -->
            <tr class="comparison-table__supplier-header">
              <th class="comparison-table__line-header sticky-col">
                <div class="d-flex flex-column">
                  <span class="text-caption text-disabled text-uppercase">Line Items</span>
                  <span class="text-body-2 font-weight-bold">{{ lines.length }} items</span>
                </div>
              </th>
              <th class="comparison-table__target-col">
                <div class="d-flex flex-column align-center">
                  <VAvatar
                    rounded
                    size="36"
                    variant="tonal"
                    color="secondary"
                    class="mb-1"
                  >
                    <VIcon
                      icon="tabler-target"
                      size="20"
                    />
                  </VAvatar>
                  <span class="text-caption font-weight-bold">Target</span>
                </div>
              </th>
              <th
                v-for="response in responses"
                :key="response.id"
                class="comparison-table__supplier-col"
                :class="{ 'comparison-table__supplier-col--awarded': response.is_awarded }"
              >
                <div class="d-flex flex-column align-center gap-1">
                  <VAvatar
                    rounded
                    size="36"
                    variant="tonal"
                    :color="formAwardedSupplierId === response.supplier_id ? 'success' : 'primary'"
                    class="mb-1"
                  >
                    <VIcon
                      :icon="formAwardedSupplierId === response.supplier_id ? 'tabler-trophy' : 'tabler-building-store'"
                      size="20"
                    />
                  </VAvatar>
                  <span class="text-body-2 font-weight-bold">{{ getSupplierCode(response) }}</span>
                  <span class="text-caption text-medium-emphasis text-truncate supplier-name">{{ getSupplierName(response) }}</span>
                  <div class="d-flex align-center gap-2 mt-1">
                    <VChip
                      v-if="formAwardedSupplierId === response.supplier_id"
                      size="x-small"
                      color="success"
                      label
                    >
                      Awarded
                    </VChip>
                    <IconBtn
                      v-if="formAwardedSupplierId !== response.supplier_id && (rfq.status === 'awaiting_responses' || rfq.status === 'under_evaluation') && !viewOnlyMode"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      @click="openEntryModal(response)"
                    >
                      <VIcon
                        icon="tabler-edit"
                        size="14"
                      />
                    </IconBtn>
                  </div>
                </div>
              </th>
            </tr>

            <!-- Row 2: Supplier Meta (delivery, payment terms, score) -->
            <tr class="comparison-table__meta-row">
              <td class="sticky-col text-caption font-weight-bold">
                Delivery Date
              </td>
              <td class="text-center text-caption text-disabled">
                {{ formatDate(rfq.required_delivery_date) }}
              </td>
              <td
                v-for="response in responses"
                :key="`delivery-${response.id}`"
                class="text-center"
              >
                <VChip
                  size="x-small"
                  :color="response.can_deliver_by && rfq.required_delivery_date && new Date(response.can_deliver_by) <= new Date(rfq.required_delivery_date) ? 'success' : 'warning'"
                  variant="tonal"
                  label
                >
                  {{ formatDate(response.can_deliver_by) }}
                </VChip>
              </td>
            </tr>
            <tr class="comparison-table__meta-row">
              <td class="sticky-col text-caption font-weight-bold">
                Payment Terms
              </td>
              <td class="text-center text-caption text-disabled">
                —
              </td>
              <td
                v-for="response in responses"
                :key="`terms-${response.id}`"
                class="text-center"
              >
                <span class="text-caption">{{ formatPaymentTerms(response.payment_terms_offered) }}</span>
              </td>
            </tr>
            <tr class="comparison-table__meta-row">
              <td class="sticky-col text-caption font-weight-bold">
                Eval Score
              </td>
              <td class="text-center text-caption text-disabled">
                —
              </td>
              <td
                v-for="response in responses"
                :key="`score-${response.id}`"
                class="text-center"
              >
                <VTooltip
                  v-if="response.evaluation_details"
                  location="bottom"
                >
                  <template #activator="{ props: tp }">
                    <VChip
                      v-bind="tp"
                      :color="getScoreColor(response.evaluation_score)"
                      size="small"
                      label
                      variant="flat"
                    >
                      {{ response.evaluation_score !== null ? `${response.evaluation_score}%` : 'N/A' }}
                    </VChip>
                  </template>
                  <div class="d-flex flex-column gap-1 pa-1">
                    <div
                      v-if="response.evaluation_details.price_score"
                      class="d-flex justify-space-between gap-4"
                    >
                      <span>Price:</span>
                      <span class="font-weight-bold">{{ response.evaluation_details.price_score }}%</span>
                    </div>
                    <div
                      v-if="response.evaluation_details.lead_time_score"
                      class="d-flex justify-space-between gap-4"
                    >
                      <span>Lead Time:</span>
                      <span class="font-weight-bold">{{ response.evaluation_details.lead_time_score }}%</span>
                    </div>
                    <div
                      v-if="response.evaluation_details.quality_score"
                      class="d-flex justify-space-between gap-4"
                    >
                      <span>Quality:</span>
                      <span class="font-weight-bold">{{ response.evaluation_details.quality_score }}%</span>
                    </div>
                    <div
                      v-if="response.evaluation_details.technical_score"
                      class="d-flex justify-space-between gap-4"
                    >
                      <span>Technical:</span>
                      <span class="font-weight-bold">{{ response.evaluation_details.technical_score }}%</span>
                    </div>
                    <div
                      v-if="response.evaluation_details.payment_score"
                      class="d-flex justify-space-between gap-4"
                    >
                      <span>Payment:</span>
                      <span class="font-weight-bold">{{ response.evaluation_details.payment_score }}%</span>
                    </div>
                  </div>
                </VTooltip>
                <VChip
                  v-else
                  :color="getScoreColor(response.evaluation_score)"
                  size="small"
                  label
                  variant="flat"
                >
                  {{ response.evaluation_score !== null ? `${response.evaluation_score}%` : 'N/A' }}
                </VChip>
              </td>
            </tr>
          </thead>
          <tbody>
            <!-- Line Items Rows -->
            <tr
              v-for="line in lines"
              :key="line.id"
              class="comparison-table__line-row"
            >
              <td class="sticky-col">
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-bold">{{ getLineCode(line) }}</span>
                  <span class="text-caption text-medium-emphasis text-truncate line-name">{{ getLineName(line) }}</span>
                  <div class="d-flex align-center gap-1 mt-1">
                    <span class="text-caption text-disabled">Qty: {{ line.qty_requested }}</span>
                    <template v-if="line.required_certifications?.length">
                      <VDivider
                        vertical
                        length="10"
                        class="mx-1"
                      />
                      <VTooltip>
                        <template #activator="{ props: tp }">
                          <VIcon
                            v-bind="tp"
                            icon="tabler-certificate"
                            size="14"
                            color="warning"
                          />
                        </template>
                        Required: {{ line.required_certifications.join(', ') }}
                      </VTooltip>
                    </template>
                  </div>
                </div>
              </td>
              <!-- Target Price -->
              <td class="text-center">
                <span class="text-body-2 font-weight-medium text-disabled">
                  {{ line.target_price_ht ? parseFloat(line.target_price_ht).toFixed(3) : '—' }}
                </span>
              </td>
              <!-- Supplier Prices -->
              <td
                v-for="response in responses"
                :key="`line-${line.id}-${response.id}`"
                class="text-center supplier-line-cell"
              >
                <template v-if="response.lines?.find(l => l.rfq_line_id === line.id)">
                  <div class="d-flex flex-column align-center gap-1">
                    <span
                      class="text-body-2 font-weight-bold"
                      :class="getResponsePriceClass(response) ? 'text-success' : 'text-primary'"
                    >
                      {{ parseFloat(response.lines.find(l => l.rfq_line_id === line.id).offered_unit_price_ht).toFixed(3) }}
                    </span>
                    <div class="d-flex align-center justify-center gap-1 mt-1">
                      <VTooltip
                        v-if="response.lines.find(l => l.rfq_line_id === line.id).offered_lead_time_days"
                        text="Lead Time"
                      >
                        <template #activator="{ props: tp }">
                          <VChip
                            v-bind="tp"
                            size="x-small"
                            variant="tonal"
                            color="info"
                            prepend-icon="tabler-clock"
                          >
                            {{ response.lines.find(l => l.rfq_line_id === line.id).offered_lead_time_days }}d
                          </VChip>
                        </template>
                      </VTooltip>
                      <VTooltip
                        v-if="response.lines.find(l => l.rfq_line_id === line.id).certifications_confirmed?.length"
                        text="Certifications Confirmed"
                      >
                        <template #activator="{ props: tp }">
                          <VChip
                            v-bind="tp"
                            size="x-small"
                            variant="tonal"
                            color="success"
                            prepend-icon="tabler-certificate"
                          >
                            {{ response.lines.find(l => l.rfq_line_id === line.id).certifications_confirmed.length }}
                          </VChip>
                        </template>
                      </VTooltip>
                    </div>
                  </div>
                </template>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <!-- Total Row -->
            <tr class="comparison-table__total-row">
              <td class="sticky-col">
                <span class="text-body-1 font-weight-bold">TOTAL HT</span>
              </td>
              <td class="text-center text-disabled">
                —
              </td>
              <td
                v-for="response in responses"
                :key="`total-${response.id}`"
                class="text-center"
                :class="getResponsePriceClass(response)"
              >
                <span class="text-body-1 font-weight-bold">
                  {{ parseFloat(response.total_ht).toFixed(3) }}
                </span>
              </td>
            </tr>
            <!-- Award Action Row -->
            <tr
              v-if="(rfq.status === 'under_evaluation' || rfq.status === 'awaiting_responses' || rfq.status === 'awarded') && !viewOnlyMode"
              class="comparison-table__award-row"
            >
              <td class="sticky-col">
                <span class="text-caption text-disabled text-uppercase">Award</span>
              </td>
              <td />
              <td
                v-for="response in responses"
                :key="`award-${response.id}`"
                class="text-center"
              >
                <VBtn
                  v-if="formAwardedSupplierId !== response.supplier_id"
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-trophy"
                  @click="toggleAward(response)"
                >
                  Award
                </VBtn>
                <VChip
                  v-else
                  color="success"
                  label
                  size="small"
                  style="cursor: pointer;"
                  @click="toggleAward(response)"
                >
                  <VIcon
                    icon="tabler-check"
                    size="14"
                    start
                  />
                  Awarded (Click to Revoke)
                </VChip>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </VCard>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 👉 Supplier Notes Section                                    -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <VCard
      v-if="responses.some(r => r.supplier_notes)"
      class="mt-6"
    >
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-notes"
            size="22"
          />
          Supplier Notes
        </VCardTitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol
            v-for="response in responses.filter(r => r.supplier_notes)"
            :key="`note-${response.id}`"
            cols="12"
            md="6"
          >
            <VCard
              variant="outlined"
              class="pa-4"
            >
              <div class="d-flex align-center gap-2 mb-2">
                <VAvatar
                  rounded
                  size="28"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-building-store"
                    size="16"
                  />
                </VAvatar>
                <span class="text-body-2 font-weight-bold">{{ getSupplierName(response) }}</span>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ response.supplier_notes }}
              </p>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>

  <!-- 👉 Award Confirmation Dialog -->
  <VDialog
    v-model="isAwardDialogVisible"
    max-width="500px"
  >
    <VCard class="award-dialog">
      <VCardText class="text-center pt-8 pb-2">
        <VAvatar
          rounded
          size="64"
          color="success"
          variant="tonal"
          class="mb-4"
        >
          <VIcon
            icon="tabler-trophy"
            size="36"
          />
        </VAvatar>
        <h5 class="text-h5 font-weight-bold mb-2">
          Award this RFQ?
        </h5>
        <p class="text-body-1 text-medium-emphasis">
          You are about to award <strong>{{ rfq?.reference }}</strong> to
          <strong>{{ selectedResponseForAward ? getSupplierName(selectedResponseForAward) : '' }}</strong>
          for a total of <strong>{{ selectedResponseForAward ? parseFloat(selectedResponseForAward.total_ht).toFixed(3) : '—' }}</strong>.
        </p>
      </VCardText>
      <VCardActions class="justify-center pb-6 gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          min-width="120"
          @click="isAwardDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="success"
          variant="elevated"
          min-width="120"
          prepend-icon="tabler-trophy"
          @click="confirmAward"
        >
          Confirm Award
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
/* ─── Header Card ──────────────────────────────────────────────── */
.comparison-header-card {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  backdrop-filter: blur(8px);
}

/* ─── Criteria Cards ───────────────────────────────────────────── */
.comparison-criteria-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 4px 24px rgba(0, 0, 0, 8%);
    transform: translateY(-2px);
  }
}

.supplier-line-cell {
  padding-block: 8px !important;
}

/* ─── Matrix Card ──────────────────────────────────────────────── */
.comparison-matrix-card {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}

/* ─── Comparison Table ─────────────────────────────────────────── */
.comparison-table-wrapper {
  overflow-x: auto;
}

.comparison-table {
  border-collapse: separate;
  border-spacing: 0;
  inline-size: 100%;
  min-inline-size: 800px;
  table-layout: auto;

  th,
  td {
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    padding-block: 16px;
    padding-inline: 16px;
    white-space: nowrap;
  }
}

/* Sticky first column */
.sticky-col {
  position: sticky;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
  border-inline-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  inset-inline-start: 0;
  max-inline-size: 260px;
  min-inline-size: 200px;
}

.comparison-table__line-header {
  vertical-align: bottom;
}

.comparison-table__target-col,
.comparison-table__supplier-col {
  min-inline-size: 140px;
  text-align: center;
  vertical-align: bottom;
}

.comparison-table__supplier-col--awarded {
  background: rgba(var(--v-theme-success), 0.04) !important;
}

.supplier-name {
  display: inline-block;
  max-inline-size: 120px;
}

.line-name {
  display: inline-block;
  max-inline-size: 180px;
}

/* Meta rows */
.comparison-table__meta-row {
  td {
    background: rgba(var(--v-theme-on-surface), 0.02);
    padding-block: 8px;
  }
}

/* Line rows hover */
.comparison-table__line-row {
  transition: background-color 0.15s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.03) !important;
  }
}

/* Total row */
.comparison-table__total-row {
  td {
    background: rgba(var(--v-theme-on-surface), 0.02);
    border-block-start: 2px solid rgba(var(--v-theme-on-surface), 0.12);
    padding-block: 16px;
  }
}

/* Award row */
.comparison-table__award-row {
  td {
    background: rgba(var(--v-theme-success), 0.02);
    padding-block: 12px;
  }
}

/* Price highlighting */
.price-best {
  background: rgba(var(--v-theme-success), 0.06) !important;
  color: rgb(var(--v-theme-success)) !important;

  span {
    color: rgb(var(--v-theme-success)) !important;
  }
}

.price-worst {
  background: rgba(var(--v-theme-error), 0.04) !important;
  color: rgb(var(--v-theme-error)) !important;

  span {
    color: rgb(var(--v-theme-error)) !important;
  }
}

/* Award dialog */
.award-dialog {
  border-radius: 16px !important;
}
</style>
