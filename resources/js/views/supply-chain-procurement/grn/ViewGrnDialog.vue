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
  grn: {
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
])

const { t } = useI18n()

const {
  formatDate,
  formatQty,
  statusLabel,
  qcResultLabel,
  rejectionReasonLabel,
  lineSubtypeLabel,
  lineTypeColor,
  lineTypeIcon,
} = useProcurementHelpers()

const activeSection = ref('overview')

const lines = computed(() => props.grn?.lines || [])
const rejectedLines = computed(() => lines.value.filter(l => (Number(l.qty_rejected) || 0) > 0))

const dialogSections = computed(() => {
  const list = [
    { id: 'overview', title: t('Receipt & Delivery'), icon: 'tabler-truck-delivery' },
    { id: 'lines', title: t('Received Lines'), icon: 'tabler-package-import', badge: lines.value.length || undefined },
    { id: 'qc', title: t('Quality & Discrepancies'), icon: 'tabler-shield-check', badge: rejectedLines.value.length || undefined },
  ]

  if (props.grn?.notes || props.grn?.qc_remarks) {
    list.push({ id: 'notes', title: t('Notes & Remarks'), icon: 'tabler-notes' })
  }

  return list
})

watch(() => props.modelValue, open => {
  if (open) activeSection.value = 'overview'
})

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

const qcColor = result => {
  if (result === 'passed') return 'success'
  if (result === 'passed_with_remarks') return 'warning'
  if (result === 'rejected') return 'error'

  return 'secondary'
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="grn?.grn_number || $t('Goods Receipt Details')"
    :subtitle="grn?.supplier?.legal_name || grn?.purchase_order?.supplier?.legal_name || $t('Goods Receipt Note')"
    icon="tabler-package-import"
    size="xlarge"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #actions>
      <WpStatusChip
        v-if="grn?.status"
        :status="grn.status"
        :label="statusLabel(grn.status)"
        size="small"
      />
      <VChip
        v-if="grn?.delivery_on_time !== null && grn?.delivery_on_time !== undefined"
        size="small"
        variant="tonal"
        :color="grn.delivery_on_time ? 'success' : 'warning'"
        :prepend-icon="grn.delivery_on_time ? 'tabler-clock-check' : 'tabler-clock-exclamation'"
      >
        {{ grn.delivery_on_time ? $t('On Time') : $t('Late ({n}d)', { n: grn.days_late || 0 }) }}
      </VChip>
    </template>

    <div
      v-if="loading"
      class="py-6"
    >
      <VSkeletonLoader type="article, table-heading, table-tbody" />
    </div>

    <div
      v-else-if="grn"
      class="d-flex flex-column gap-4"
    >
      <WpJournalNav
        v-model="activeSection"
        :sections="dialogSections"
        is-dialog
        container=".wp-detail-dialog__scroll"
      />

      <!-- SECTION 1: RECEIPT & DELIVERY PERFORMANCE -->
      <div id="section-overview">
        <WpSectionCard
          :title="$t('Receipt & Delivery Performance')"
          icon="tabler-truck-delivery"
        >
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded border bg-var-theme-background h-100">
                <div class="text-caption text-disabled mb-1">
                  {{ $t('Source Purchase Order') }}
                </div>
                <div class="text-body-2 font-weight-bold text-primary font-monospace">
                  {{ grn.purchase_order?.po_number || grn.po_number || '—' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ grn.supplier?.legal_name || grn.purchase_order?.supplier?.legal_name || '—' }}
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
                  {{ $t('Destination Site') }}
                </div>
                <div class="text-body-2 font-weight-semibold">
                  {{ grn.site?.site_name || grn.site?.name || '—' }}
                </div>
                <div class="text-caption text-medium-emphasis font-monospace">
                  {{ $t('BL / Note') }}: {{ grn.supplier_delivery_note || grn.delivery_note_ref || '—' }}
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
                  {{ $t('Receipt vs Expected Date') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ formatDate(grn.receipt_date || grn.received_date) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Expected') }}: {{ formatDate(grn.expected_delivery_date || grn.purchase_order?.expected_delivery_date) }}
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
                  {{ $t('Delivery Performance') }}
                </div>
                <div class="d-flex align-center gap-2 mt-1">
                  <VChip
                    v-if="grn.delivery_on_time !== null && grn.delivery_on_time !== undefined"
                    size="x-small"
                    :color="grn.delivery_on_time ? 'success' : 'warning'"
                    variant="tonal"
                    label
                  >
                    {{ grn.delivery_on_time ? $t('On Time') : $t('Delayed') }}
                  </VChip>
                  <span
                    v-if="grn.days_late > 0"
                    class="text-caption font-weight-semibold text-warning"
                  >
                    +{{ grn.days_late }} {{ $t('days late') }}
                  </span>
                  <span
                    v-else-if="grn.delivery_on_time === null || grn.delivery_on_time === undefined"
                    class="text-body-2 text-medium-emphasis"
                  >
                    —
                  </span>
                </div>
                <div
                  v-if="grn.carrier_name || grn.vehicle_registration"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  {{ [grn.carrier_name, grn.vehicle_registration].filter(Boolean).join(' • ') }}
                </div>
              </div>
            </VCol>
          </VRow>
        </WpSectionCard>
      </div>

      <!-- SECTION 2: RECEIVED LINES -->
      <div id="section-lines">
        <WpSectionCard
          :title="$t('Received Lines')"
          icon="tabler-package-import"
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
                  {{ $t('Accepted') }}
                </th>
                <th class="text-end">
                  {{ $t('Rejected') }}
                </th>
                <th>{{ $t('QC Result') }}</th>
                <th>{{ $t('Storage Location') }}</th>
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
                      v-if="line.catalog_item?.is_stockable === false"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      label
                    >
                      {{ $t('Non-Stockable') }}
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
                    v-if="lineItemCode(line) || lineSku(line) || line.lot_number"
                    class="d-flex align-center gap-2 text-caption text-medium-emphasis font-monospace mt-1"
                  >
                    <span v-if="lineItemCode(line)">{{ lineItemCode(line) }}</span>
                    <span v-if="lineSku(line)">SKU: {{ lineSku(line) }}</span>
                    <span v-if="line.lot_number">Lot: {{ line.lot_number }}</span>
                  </div>
                </td>
                <td class="text-end font-monospace">
                  {{ formatQty(line.qty_expected ?? line.qty_ordered) }}
                  <span class="text-caption text-medium-emphasis">{{ lineUnit(line) }}</span>
                </td>
                <td class="text-end font-monospace font-weight-medium">
                  {{ formatQty(line.qty_received) }}
                </td>
                <td class="text-end font-monospace text-success font-weight-semibold">
                  {{ formatQty(line.qty_accepted) }}
                </td>
                <td class="text-end font-monospace">
                  <VChip
                    v-if="Number(line.qty_rejected) > 0"
                    size="x-small"
                    color="error"
                    variant="tonal"
                    label
                  >
                    {{ formatQty(line.qty_rejected) }}
                  </VChip>
                  <span
                    v-else
                    class="text-disabled"
                  >0</span>
                </td>
                <td>
                  <VChip
                    size="x-small"
                    :color="qcColor(line.qc_result)"
                    variant="tonal"
                    label
                  >
                    {{ qcResultLabel(line.qc_result) }}
                  </VChip>
                </td>
                <td class="text-caption">
                  {{ line.storage_location?.location_code || line.storage_location?.name || '—' }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <WpEmptyState
            v-else
            icon="tabler-package-off"
            :title="$t('No Receipt Lines')"
            :description="$t('No lines have been recorded on this goods receipt note.')"
          />
        </WpSectionCard>
      </div>

      <!-- SECTION 3: QUALITY & DISCREPANCIES -->
      <div id="section-qc">
        <WpSectionCard
          :title="$t('Quality Control & Rejected Items')"
          icon="tabler-shield-check"
          :badge="rejectedLines.length"
        >
          <VTable
            v-if="rejectedLines.length"
            density="compact"
            class="text-no-wrap border rounded"
          >
            <thead>
              <tr>
                <th>{{ $t('Item') }}</th>
                <th class="text-end">
                  {{ $t('Ordered') }}
                </th>
                <th class="text-end">
                  {{ $t('Received') }}
                </th>
                <th class="text-end">
                  {{ $t('Rejected') }}
                </th>
                <th>{{ $t('Rejection Reason') }}</th>
                <th>{{ $t('QC Notes') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in rejectedLines"
                :key="line.id || idx"
              >
                <td class="font-weight-medium">
                  {{ line.description || line.catalog_item?.name || '—' }}
                </td>
                <td class="text-end font-monospace">
                  {{ formatQty(line.qty_expected ?? line.qty_ordered) }}
                </td>
                <td class="text-end font-monospace">
                  {{ formatQty(line.qty_received) }}
                </td>
                <td class="text-end">
                  <VChip
                    size="x-small"
                    color="error"
                    variant="tonal"
                    label
                  >
                    {{ formatQty(line.qty_rejected) }}
                  </VChip>
                </td>
                <td>
                  <VChip
                    v-if="line.rejection_reason"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                    label
                  >
                    {{ rejectionReasonLabel(line.rejection_reason) }}
                  </VChip>
                  <span
                    v-else
                    class="text-disabled"
                  >—</span>
                </td>
                <td class="text-caption text-medium-emphasis">
                  {{ line.qc_notes || '—' }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <VAlert
            v-else
            type="success"
            variant="tonal"
            density="compact"
            icon="tabler-circle-check"
          >
            {{ $t('Zero Rejected Items — All received quantities passed quality inspection.') }}
          </VAlert>
        </WpSectionCard>
      </div>

      <!-- SECTION 4: NOTES -->
      <div
        v-if="grn.notes || grn.qc_remarks"
        id="section-notes"
      >
        <WpSectionCard
          :title="$t('Notes & Remarks')"
          icon="tabler-notes"
        >
          <div
            v-if="grn.notes"
            class="mb-3"
          >
            <div class="text-caption text-disabled mb-1">
              {{ $t('Receiving Notes') }}
            </div>
            <div class="text-body-2 pa-3 rounded border bg-var-theme-background">
              {{ grn.notes }}
            </div>
          </div>

          <div v-if="grn.qc_remarks">
            <div class="text-caption text-disabled mb-1">
              {{ $t('QC Summary Remarks') }}
            </div>
            <div class="text-body-2 pa-3 rounded border bg-var-theme-background">
              {{ grn.qc_remarks }}
            </div>
          </div>
        </WpSectionCard>
      </div>
    </div>

    <template #footer>
      <div class="d-flex align-center justify-end gap-2 w-100">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          v-if="grn"
          color="primary"
          prepend-icon="tabler-edit"
          @click="emit('edit', grn)"
        >
          {{ $t('Open Workspace') }}
        </VBtn>
      </div>
    </template>
  </WpDetailDialog>
</template>
