<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { usePoStore } from '@/stores/usePoStore'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

definePage({
  path: '/supply-chain-procurement/po/match/:id',
  name: 'procurement-match-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-po-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { smAndDown } = useDisplay()
const poStore = usePoStore()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  resolveStatusVariant,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const poId = computed(() => route.params.id)
const po = ref(null)
const loading = ref(false)

const loadPo = async () => {
  loading.value = true
  try {
    po.value = await poStore.fetchDetails(poId.value)
  }
  finally {
    loading.value = false
  }
}

onMounted(loadPo)

const grns = computed(() => po.value?.goods_receipt_notes || [])

const matchRows = computed(() => {
  const lines = po.value?.lines || []

  return lines.map(poLine => {
    const grnLines = []

    grns.value.forEach(g => {
      ;(g.lines || []).forEach(gl => {
        if (gl.po_line_id === poLine.id) {
          grnLines.push({ ...gl, reference: g.reference, grn_status: g.status, grn_id: g.id })
        }
      })
    })

    const orderedQty = Number(poLine.qty_ordered) || 0
    const receivedQty = grnLines.reduce((s, gl) => s + (Number(gl.qty_received) || 0), 0) || Number(poLine.qty_received) || 0
    const acceptedQty = grnLines.reduce((s, gl) => s + (Number(gl.qty_accepted) || 0), 0)
    const rejectedQty = grnLines.reduce((s, gl) => s + (Number(gl.qty_rejected) || 0), 0)
    const poPrice = Number(poLine.unit_price_ht) || 0

    const avgGrnCost = grnLines.length
      ? grnLines.reduce((s, gl) => s + (Number(gl.unit_cost_ht) || 0), 0) / grnLines.length
      : poPrice

    const qtyDelta = receivedQty - orderedQty
    const priceDelta = avgGrnCost - poPrice

    let matchStatus = 'matched'
    let matchColor = 'success'
    if (receivedQty === 0) {
      matchStatus = 'pending_receipt'
      matchColor = 'secondary'
    }
    else if (Math.abs(qtyDelta) > 0.001 || Math.abs(priceDelta) > 0.01 || rejectedQty > 0) {
      matchStatus = qtyDelta > 0 ? 'over_received' : qtyDelta < 0 ? 'under_received' : 'variance'
      matchColor = 'warning'
    }

    return {
      id: poLine.id,
      item_name: resolveLineItemName(poLine),
      sku: resolveLineItemSku(poLine),
      orderedQty,
      receivedQty,
      acceptedQty,
      rejectedQty,
      poPrice,
      avgGrnCost,
      qtyDelta,
      priceDelta,
      matchStatus,
      matchColor,
      grnLines,
    }
  })
})

// Matching policy (mirrors the row classification above)
const QTY_TOLERANCE = 0.001
const PRICE_TOLERANCE = 0.01

const matchedCount = computed(() => matchRows.value.filter(r => r.matchStatus === 'matched').length)
const varianceCount = computed(() => matchRows.value.filter(r => ['over_received', 'under_received', 'variance'].includes(r.matchStatus)).length)

const totalOrdered = computed(() => matchRows.value.reduce((s, r) => s + r.orderedQty, 0))
const totalReceived = computed(() => matchRows.value.reduce((s, r) => s + r.receivedQty, 0))
const totalRejected = computed(() => matchRows.value.reduce((s, r) => s + r.rejectedQty, 0))
const receivedPct = computed(() => (totalOrdered.value ? Math.min(100, Math.round((totalReceived.value / totalOrdered.value) * 100)) : 0))

const isReceivable = computed(() => ['approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(po.value?.status))

const goCreateGrn = () => {
  router.push({ path: '/supply-chain-procurement/grn/edit/new', query: { from_po: poId.value } })
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="`${t('scp.grn.match.title')} — ${po?.reference || ''}`"
      :subtitle="po?.supplier ? `${resolveSupplierName(po.supplier)} · ${t('scp.grn.match.subtitle')}` : t('scp.grn.match.subtitle')"
      icon="tabler-git-compare"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po'), to: '/supply-chain-procurement/po/list' },
        { title: po?.reference || 'PO', to: `/supply-chain-procurement/po/edit/${poId}` },
        { title: t('scp.grn.match.title') },
      ]"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          min-height="44"
          @click="router.push(`/supply-chain-procurement/po/edit/${poId}`)"
        >
          {{ t('scp.po.actions.back_to_po') }}
        </VBtn>
        <VBtn
          v-if="isReceivable"
          color="success"
          prepend-icon="tabler-package-import"
          min-height="44"
          @click="goCreateGrn"
        >
          {{ t('scp.po.actions.create_grn') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpKpiGroup
      :item-count="4"
      :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
      class="mb-6"
    >
      <template #default="{ getColProps }">
        <VCol v-bind="getColProps(0)">
          <WpKpiCard
            :title="t('scp.common.lines')"
            :value="matchRows.length"
            icon="tabler-list-numbers"
            icon-color="primary"
            :loading="loading"
          />
        </VCol>
        <VCol v-bind="getColProps(1)">
          <WpKpiCard
            :title="t('scp.grn.variance.on_target')"
            :value="matchedCount"
            icon="tabler-circle-check"
            icon-color="success"
            :loading="loading"
          />
        </VCol>
        <VCol v-bind="getColProps(2)">
          <WpKpiCard
            :title="t('scp.po.match.variances')"
            :value="varianceCount"
            icon="tabler-alert-triangle"
            icon-color="warning"
            :loading="loading"
          />
        </VCol>
        <VCol v-bind="getColProps(3)">
          <WpKpiCard
            :title="t('scp.nav.grn_short')"
            :value="grns.length"
            icon="tabler-truck-delivery"
            icon-color="info"
            :loading="loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- Reconciliation Summary: PO vs GRN vs Supplier Invoice -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                <VIcon
                  icon="tabler-shopping-cart"
                  size="14"
                  class="me-1"
                />
                {{ t('scp.po.title') }}
              </span>
              <WpStatusChip
                v-if="po"
                :status="po.status"
                :label="statusLabel(po.status)"
                :color="resolveStatusVariant(po.status)"
              />
            </div>
            <div class="text-h6 font-weight-bold">
              {{ formatMoney(po?.total_ttc || 0, { currency: po?.currency }) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('scp.grn.qty.ordered', 'Ordered') }}: {{ totalOrdered }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                <VIcon
                  icon="tabler-package-import"
                  size="14"
                  class="me-1"
                />
                {{ t('scp.nav.grn') }}
              </span>
              <VChip
                size="x-small"
                label
                variant="tonal"
                :color="grns.length ? 'success' : 'secondary'"
              >
                {{ grns.length }}
              </VChip>
            </div>
            <div class="text-h6 font-weight-bold">
              {{ totalReceived }} / {{ totalOrdered }}
            </div>
            <VProgressLinear
              :model-value="receivedPct"
              color="primary"
              rounded
              height="6"
              class="my-2"
            />
            <div class="d-flex justify-space-between text-caption">
              <span class="text-medium-emphasis">{{ receivedPct }}%</span>
              <span :class="totalRejected > 0 ? 'text-error' : 'text-medium-emphasis'">
                {{ t('scp.grn.qty.rejected') }}: {{ totalRejected }}
              </span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                <VIcon
                  icon="tabler-file-invoice"
                  size="14"
                  class="me-1"
                />
                {{ t('scp.po.match.supplier_invoice', 'Supplier Invoice') }}
              </span>
              <VChip
                size="x-small"
                label
                variant="tonal"
                color="secondary"
              >
                {{ t('scp.po.match.not_linked', 'Not linked') }}
              </VChip>
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ t('scp.po.match.invoice_pending', 'Invoice matching becomes available once supplier invoices are registered for this purchase order.') }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Matching Policy -->
    <WpSectionCard
      :title="t('scp.po.match.policy_title', 'Matching Policy')"
      icon="tabler-adjustments"
      class="mb-6"
    >
      <VRow>
        <VCol
          cols="12"
          sm="4"
        >
          <div class="text-caption text-medium-emphasis">
            {{ t('scp.po.match.qty_tolerance', 'Quantity tolerance') }}
          </div>
          <div class="font-weight-semibold">
            ±{{ QTY_TOLERANCE }}
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <div class="text-caption text-medium-emphasis">
            {{ t('scp.po.match.price_tolerance', 'Price tolerance (HT)') }}
          </div>
          <div class="font-weight-semibold">
            ±{{ PRICE_TOLERANCE }} {{ po?.currency || 'TND' }}
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <div class="text-caption text-medium-emphasis">
            {{ t('scp.po.match.rejections_policy', 'Rejected quantities') }}
          </div>
          <div class="font-weight-semibold">
            {{ t('scp.po.match.rejections_flag', 'Flagged as variance') }}
          </div>
        </VCol>
      </VRow>

      <VAlert
        v-if="isReceivable && totalReceived < totalOrdered"
        type="info"
        variant="tonal"
        density="comfortable"
        class="mt-4"
      >
        <div class="d-flex flex-wrap align-center justify-space-between gap-2">
          <span>{{ t('scp.po.match.pending_receipt_hint', 'Some ordered quantities have not been received yet.') }}</span>
          <VBtn
            color="success"
            size="small"
            prepend-icon="tabler-package-import"
            @click="goCreateGrn"
          >
            {{ t('scp.po.actions.create_grn') }}
          </VBtn>
        </div>
      </VAlert>
    </WpSectionCard>

    <!-- Linked GRNs Strip -->
    <WpSectionCard
      v-if="grns.length"
      :title="t('scp.nav.grn')"
      icon="tabler-package-import"
      class="mb-6"
    >
      <div class="d-flex flex-wrap gap-2">
        <VChip
          v-for="g in grns"
          :key="g.id"
          label
          variant="tonal"
          :color="resolveStatusVariant(g.status)"
          class="cursor-pointer"
          @click="router.push(`/supply-chain-procurement/grn/edit/${g.id}`)"
        >
          <strong>{{ g.reference }}</strong>
          <span class="ms-1">({{ statusLabel(g.status) }} · {{ formatDate(g.received_at) }})</span>
        </VChip>
      </div>
    </WpSectionCard>

    <!-- Line-by-Line Match Table / Mobile Cards -->
    <WpSectionCard
      :title="t('scp.po.match.comparison_table')"
      icon="tabler-scale"
    >
      <div
        v-if="smAndDown"
        class="d-flex flex-column gap-3"
      >
        <WpMobileCard
          v-for="row in matchRows"
          :key="row.id"
          :title="row.item_name"
          :subtitle="row.sku"
          :status="row.matchStatus"
          :status-color="row.matchColor"
          :status-label="t(`scp.po.match.status_${row.matchStatus}`)"
        >
          <template #content>
            <div class="d-flex flex-wrap justify-space-between gap-2 text-caption">
              <span>{{ t('scp.grn.qty.ordered') }}: <strong>{{ row.orderedQty }}</strong></span>
              <span>{{ t('scp.grn.qty.received') }}: <strong>{{ row.receivedQty }}</strong></span>
              <span>{{ t('scp.grn.qty.accepted') }}: <strong class="text-success">{{ row.acceptedQty }}</strong></span>
              <span v-if="row.rejectedQty > 0">{{ t('scp.grn.qty.rejected') }}: <strong class="text-error">{{ row.rejectedQty }}</strong></span>
            </div>
          </template>
        </WpMobileCard>
      </div>

      <VTable
        v-else
        density="comfortable"
      >
        <thead>
          <tr>
            <th>{{ t('scp.common.item') }}</th>
            <th class="text-end">
              {{ t('scp.grn.qty.ordered') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.qty.received') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.qty.accepted') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.qty.rejected') }}
            </th>
            <th class="text-end">
              {{ t('scp.po.fields.unit_price_ht') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.fields.unit_cost_ht') }}
            </th>
            <th class="text-end">
              {{ t('scp.common.status') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in matchRows"
            :key="row.id"
          >
            <td>
              <div class="font-weight-medium">
                {{ row.item_name }}
              </div>
              <div
                v-if="row.sku"
                class="text-caption text-medium-emphasis"
              >
                {{ row.sku }}
              </div>
            </td>
            <td class="text-end font-weight-medium">
              {{ row.orderedQty }}
            </td>
            <td class="text-end font-weight-bold">
              {{ row.receivedQty }}
            </td>
            <td class="text-end text-success font-weight-medium">
              {{ row.acceptedQty }}
            </td>
            <td class="text-end">
              <span :class="row.rejectedQty > 0 ? 'text-error font-weight-bold' : 'text-disabled'">
                {{ row.rejectedQty }}
              </span>
            </td>
            <td class="text-end">
              {{ formatMoney(row.poPrice, { currency: po?.currency }) }}
            </td>
            <td class="text-end">
              {{ formatMoney(row.avgGrnCost, { currency: po?.currency }) }}
            </td>
            <td class="text-end">
              <VChip
                size="small"
                label
                variant="tonal"
                :color="row.matchColor"
              >
                {{ t(`scp.po.match.status_${row.matchStatus}`) }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </WpSectionCard>
  </div>
</template>

<style lang="scss" scoped>
.po-match-page {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(24px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
