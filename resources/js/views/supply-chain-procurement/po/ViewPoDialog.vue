<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  po: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:modelValue',
  'edit',
  'downloadPdf',
  'openMatch',
])

const { t } = useI18n()

const {
  formatAmount,
  formatDate,
  formatDateTime,
  formatQty,
  statusLabel,
  paymentTermLabel,
  incotermLabel,
  lineSubtypeLabel,
  lineTypeColor,
  lineTypeIcon,
} = useProcurementHelpers()

const activeSection = ref('overview')

const dialogSections = computed(() => {
  const list = [
    { id: 'overview', title: t('Overview & Supplier'), icon: 'tabler-info-circle' },
    { id: 'lines', title: t('Order Lines'), icon: 'tabler-list-details', badge: lines.value.length || undefined },
    { id: 'terms', title: t('Terms & Delivery'), icon: 'tabler-truck-delivery' },
  ]

  if (grns.value.length > 0) {
    list.push({ id: 'grns', title: t('Goods Receipts'), icon: 'tabler-package-import', badge: grns.value.length })
  }

  if (props.po?.notes || props.po?.terms_conditions || props.po?.cancellation_reason) {
    list.push({ id: 'notes', title: t('Notes & Audit'), icon: 'tabler-notes' })
  }

  return list
})

watch(() => props.modelValue, open => {
  if (open) activeSection.value = 'overview'
})

const lines = computed(() => props.po?.lines || [])
const grns = computed(() => props.po?.grns || props.po?.goods_receipt_notes || [])

const isAdHoc = line => !line.catalog_item_id && !line.catalog_item

const lineItemCode = line =>
  line.catalog_item?.code
  || line.catalog_item?.item_code
  || line.item_code
  || ''

const lineSku = line =>
  line.variant?.sku
  || line.variant_sku
  || line.sku
  || ''

const lineUnit = line =>
  line.unit
  || line.uom
  || line.catalog_item?.unit
  || 'pcs'

const receivingProgress = computed(() => {
  if (!lines.value.length) return 0
  const totalOrdered = lines.value.reduce((acc, l) => acc + (Number(l.quantity) || 0), 0)
  const totalReceived = lines.value.reduce((acc, l) => acc + (Number(l.qty_received) || 0), 0)
  if (!totalOrdered) return 0

  return Math.min(100, Math.round((totalReceived / totalOrdered) * 100))
})
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="po?.po_number || $t('Purchase Order Details')"
    :subtitle="po?.supplier?.legal_name || po?.supplier?.name || $t('Procurement Order')"
    icon="tabler-file-invoice"
    size="xlarge"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <WpStatusChip
        v-if="po?.status"
        :status="po.status"
        :label="statusLabel(po.status)"
        size="small"
      />
      <VChip
        v-if="po?.three_way_match_status"
        size="small"
        variant="tonal"
        :color="po.three_way_match_status === 'matched' ? 'success' : po.three_way_match_status === 'discrepancy' ? 'error' : 'warning'"
        prepend-icon="tabler-GitCompare"
      >
        {{ statusLabel(po.three_way_match_status) }}
      </VChip>
    </template>

    <div
      v-if="loading"
      class="py-6"
    >
      <VSkeletonLoader type="article, table-heading, table-tbody" />
    </div>

    <div
      v-else-if="po"
      class="d-flex flex-column gap-4"
    >
      <WpJournalNav
        v-model="activeSection"
        :sections="dialogSections"
        is-dialog
        container=".wp-detail-dialog__scroll"
      />

      <!-- SECTION 1: OVERVIEW & SUPPLIER -->
      <div id="section-overview">
        <WpSectionCard
          :title="$t('Overview & Supplier')"
          icon="tabler-info-circle"
        >
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Supplier') }}
                </div>
                <div class="text-body-2 font-weight-semibold">
                  {{ po.supplier?.legal_name || po.supplier?.name || '—' }}
                </div>
                <div
                  v-if="po.supplier?.supplier_code || po.supplier?.bp_code"
                  class="text-caption text-medium-emphasis font-monospace"
                >
                  {{ po.supplier?.supplier_code || po.supplier?.bp_code }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Order & Expected Delivery') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ formatDate(po.order_date || po.created_at) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Expected') }}: {{ formatDate(po.expected_delivery_date) }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Source Documents') }}
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <VChip
                    v-if="po.rfq?.rfq_number"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    label
                  >
                    {{ po.rfq.rfq_number }}
                  </VChip>
                  <VChip
                    v-if="po.ipr?.ipr_number"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    {{ po.ipr.ipr_number }}
                  </VChip>
                  <span
                    v-if="!po.rfq?.rfq_number && !po.ipr?.ipr_number"
                    class="text-body-2 text-medium-emphasis"
                  >
                    {{ $t('Direct Order') }}
                  </span>
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Total TTC') }}
                </div>
                <div class="text-subtitle-1 font-weight-bold text-primary font-monospace">
                  {{ formatAmount(po.total_ttc, po.currency) }}
                </div>
                <div class="text-caption text-medium-emphasis font-monospace">
                  {{ $t('HT') }}: {{ formatAmount(po.subtotal_ht, po.currency) }}
                </div>
              </div>
            </VCol>
          </VRow>

          <div class="mt-4 pa-3 rounded border bg-var-theme-background">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption font-weight-semibold text-medium-emphasis">
                {{ $t('Overall Receiving Progress') }}
              </span>
              <span class="text-caption font-weight-bold text-primary">
                {{ receivingProgress }}%
              </span>
            </div>
            <VProgressLinear
              :model-value="receivingProgress"
              :color="receivingProgress >= 100 ? 'success' : 'primary'"
              height="8"
              rounded
            />
          </div>
        </WpSectionCard>
      </div>

      <!-- SECTION 2: ORDER LINES -->
      <div id="section-lines">
        <WpSectionCard
          :title="$t('Order Lines')"
          icon="tabler-list-details"
          :badge="lines.length"
        >
          <VTable
            v-if="lines.length"
            density="compact"
            class="text-no-wrap border rounded"
          >
            <thead>
              <tr>
                <th style="width: 44px;">
                  #
                </th>
                <th>{{ $t('Item / Description') }}</th>
                <th class="text-end">
                  {{ $t('Ordered') }}
                </th>
                <th class="text-end">
                  {{ $t('Received') }}
                </th>
                <th class="text-end">
                  {{ $t('Unit Price HT') }}
                </th>
                <th class="text-end">
                  {{ $t('Tax') }}
                </th>
                <th class="text-end">
                  {{ $t('Line Total HT') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in lines"
                :key="line.id || idx"
              >
                <td class="text-medium-emphasis font-monospace">
                  {{ idx + 1 }}
                </td>
                <td class="py-2">
                  <div class="d-flex align-center flex-wrap gap-1">
                    <span class="font-weight-medium text-high-emphasis">
                      {{ line.description || line.catalog_item?.name || '—' }}
                    </span>
                    <VChip
                      v-if="isAdHoc(line)"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      label
                    >
                      {{ $t('AD-HOC') }}
                    </VChip>
                    <VChip
                      v-else-if="line.catalog_item?.item_type || line.catalog_item?.subtype"
                      size="x-small"
                      :color="lineTypeColor(line.catalog_item?.item_type)"
                      variant="tonal"
                      label
                    >
                      <VIcon
                        :icon="lineTypeIcon(line.catalog_item?.item_type)"
                        size="12"
                        start
                      />
                      {{ lineSubtypeLabel(line.catalog_item?.subtype || line.catalog_item?.item_type) }}
                    </VChip>
                  </div>
                  <div
                    v-if="lineItemCode(line) || lineSku(line)"
                    class="d-flex align-center gap-2 text-caption text-medium-emphasis font-monospace mt-1"
                  >
                    <span v-if="lineItemCode(line)">{{ lineItemCode(line) }}</span>
                    <span v-if="lineSku(line)">SKU: {{ lineSku(line) }}</span>
                  </div>
                </td>
                <td class="text-end font-monospace font-weight-medium">
                  {{ formatQty(line.quantity) }}
                  <span class="text-caption text-medium-emphasis">{{ lineUnit(line) }}</span>
                </td>
                <td class="text-end font-monospace">
                  <VChip
                    size="x-small"
                    :color="Number(line.qty_received) >= Number(line.quantity) ? 'success' : Number(line.qty_received) > 0 ? 'warning' : 'secondary'"
                    variant="tonal"
                    label
                  >
                    {{ formatQty(line.qty_received || 0) }}
                  </VChip>
                </td>
                <td class="text-end font-monospace">
                  {{ formatAmount(line.unit_price_ht, po.currency) }}
                </td>
                <td class="text-end font-monospace text-medium-emphasis">
                  {{ line.tax_rate ?? 19 }}%
                </td>
                <td class="text-end font-monospace font-weight-semibold">
                  {{ formatAmount(line.line_total_ht ?? ((Number(line.quantity) || 0) * (Number(line.unit_price_ht) || 0)), po.currency) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-var-theme-background">
                <td
                  colspan="6"
                  class="text-end font-weight-medium"
                >
                  {{ $t('Subtotal HT') }}
                </td>
                <td class="text-end font-monospace font-weight-semibold">
                  {{ formatAmount(po.subtotal_ht, po.currency) }}
                </td>
              </tr>
              <tr class="bg-var-theme-background">
                <td
                  colspan="6"
                  class="text-end font-weight-medium"
                >
                  {{ $t('Tax Total') }}
                </td>
                <td class="text-end font-monospace">
                  {{ formatAmount(po.tax_amount, po.currency) }}
                </td>
              </tr>
              <tr class="bg-var-theme-background">
                <td
                  colspan="6"
                  class="text-end font-weight-bold text-high-emphasis"
                >
                  {{ $t('Total TTC') }}
                </td>
                <td class="text-end font-monospace font-weight-bold text-primary">
                  {{ formatAmount(po.total_ttc, po.currency) }}
                </td>
              </tr>
            </tfoot>
          </VTable>

          <WpEmptyState
            v-else
            icon="tabler-list-Check"
            :title="$t('No Order Lines')"
            :description="$t('This purchase order has no line items yet.')"
          />
        </WpSectionCard>
      </div>

      <!-- SECTION 3: TERMS & DELIVERY -->
      <div id="section-terms">
        <WpSectionCard
          :title="$t('Terms & Delivery')"
          icon="tabler-truck-delivery"
        >
          <VRow dense>
            <VCol
              cols="12"
              sm="4"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Payment Terms') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ paymentTermLabel(po.payment_terms) }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Incoterm & Shipping') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ incotermLabel(po.incoterm) }}
                </div>
                <div
                  v-if="po.shipping_method"
                  class="text-caption text-medium-emphasis"
                >
                  {{ po.shipping_method }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Destination Site') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ po.site?.site_name || po.site?.name || '—' }}
                </div>
                <div
                  v-if="po.delivery_address"
                  class="text-caption text-medium-emphasis"
                >
                  {{ po.delivery_address }}
                </div>
              </div>
            </VCol>

            <VCol
              v-if="po.sent_at || po.acknowledged_at"
              cols="12"
              sm="6"
            >
              <div class="pa-3 rounded border bg-var-theme-background">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Dispatch & Acknowledgement') }}
                </div>
                <div class="text-body-2">
                  <span class="text-medium-emphasis">{{ $t('Sent') }}:</span> {{ formatDateTime(po.sent_at) }}
                </div>
                <div
                  v-if="po.acknowledged_at"
                  class="text-body-2"
                >
                  <span class="text-medium-emphasis">{{ $t('Acknowledged') }}:</span> {{ formatDateTime(po.acknowledged_at) }}
                </div>
              </div>
            </VCol>
          </VRow>
        </WpSectionCard>
      </div>

      <!-- SECTION 4: LINKED GRNs -->
      <div
        v-if="grns.length > 0"
        id="section-grns"
      >
        <WpSectionCard
          :title="$t('Goods Receipts')"
          icon="tabler-package-import"
          :badge="grns.length"
        >
          <VTable
            density="compact"
            class="text-no-wrap border rounded"
          >
            <thead>
              <tr>
                <th>{{ $t('GRN Number') }}</th>
                <th>{{ $t('Receipt Date') }}</th>
                <th>{{ $t('Delivery Note #') }}</th>
                <th>{{ $t('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="grn in grns"
                :key="grn.id"
              >
                <td class="font-weight-semibold font-monospace text-primary">
                  {{ grn.grn_number }}
                </td>
                <td>{{ formatDate(grn.receipt_date || grn.received_date) }}</td>
                <td class="font-monospace">
                  {{ grn.supplier_delivery_note || grn.delivery_note_ref || '—' }}
                </td>
                <td>
                  <WpStatusChip
                    :status="grn.status"
                    :label="statusLabel(grn.status)"
                    size="x-small"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </WpSectionCard>
      </div>

      <!-- SECTION 5: NOTES & AUDIT -->
      <div
        v-if="po.notes || po.terms_conditions || po.cancellation_reason"
        id="section-notes"
      >
        <WpSectionCard
          :title="$t('Notes & Audit')"
          icon="tabler-notes"
        >
          <VAlert
            v-if="po.cancellation_reason"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            <strong>{{ $t('Cancellation Reason') }}:</strong> {{ po.cancellation_reason }}
          </VAlert>

          <div
            v-if="po.notes"
            class="mb-3"
          >
            <div class="text-caption text-disabled mb-1">
              {{ $t('Internal Notes') }}
            </div>
            <div class="text-body-2 pa-3 rounded border bg-var-theme-background">
              {{ po.notes }}
            </div>
          </div>

          <div v-if="po.terms_conditions">
            <div class="text-caption text-disabled mb-1">
              {{ $t('Terms & Conditions') }}
            </div>
            <div class="text-body-2 pa-3 rounded border bg-var-theme-background">
              {{ po.terms_conditions }}
            </div>
          </div>
        </WpSectionCard>
      </div>
    </div>

    <template #footer>
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100">
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="po"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-file-type-pdf"
            @click="emit('downloadPdf', po)"
          >
            {{ $t('PDF') }}
          </VBtn>
          <VBtn
            v-if="po"
            variant="tonal"
            color="info"
            prepend-icon="tabler-git-compare"
            @click="emit('openMatch', po)"
          >
            {{ $t('3-Way Match') }}
          </VBtn>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            v-if="po"
            color="primary"
            prepend-icon="tabler-edit"
            @click="emit('edit', po)"
          >
            {{ $t('Open Workspace') }}
          </VBtn>
        </div>
      </div>
    </template>
  </WpDetailDialog>
</template>
