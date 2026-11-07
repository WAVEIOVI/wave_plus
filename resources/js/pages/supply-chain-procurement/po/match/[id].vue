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
} = useProcurementHelpers()

const poId = computed(() => route.params.id)
const po = ref(null)
const loading = ref(false)

const loadPo = async () => {
  loading.value = true
  try {
    po.value = await poStore.fetchPo(poId.value)
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
          grnLines.push({ ...gl, grn_number: g.grn_number, grn_status: g.status, grn_id: g.id })
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

const matchedCount = computed(() => matchRows.value.filter(r => r.matchStatus === 'matched').length)
const varianceCount = computed(() => matchRows.value.filter(r => ['over_received', 'under_received', 'variance'].includes(r.matchStatus)).length)
</script>

<template>
  <div>
    <WpPageHeader
      :title="`${t('scp.grn.match.title')} — ${po?.po_number || ''}`"
      :subtitle="t('scp.grn.match.subtitle')"
      icon="tabler-git-compare"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po'), to: '/supply-chain-procurement/po/list' },
        { title: po?.po_number || 'PO', to: `/supply-chain-procurement/po/edit/${poId}` },
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
          color="success"
          prepend-icon="tabler-package-import"
          min-height="44"
          @click="router.push({ path: '/supply-chain-procurement/grn/list', query: { from_po: poId } })"
        >
          {{ t('scp.po.actions.create_grn') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpKpiGroup class="mb-6">
      <WpKpiCard
        :title="t('scp.common.lines')"
        :value="matchRows.length"
        icon="tabler-list-numbers"
        color="primary"
        :loading="loading"
      />
      <WpKpiCard
        :title="t('scp.grn.variance.on_target')"
        :value="matchedCount"
        icon="tabler-circle-check"
        color="success"
        :loading="loading"
      />
      <WpKpiCard
        :title="t('scp.po.match.variances')"
        :value="varianceCount"
        icon="tabler-alert-triangle"
        color="warning"
        :loading="loading"
      />
      <WpKpiCard
        :title="t('scp.nav.grn_short')"
        :value="grns.length"
        icon="tabler-truck-delivery"
        color="info"
        :loading="loading"
      />
    </WpKpiGroup>

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
          <strong>{{ g.grn_number }}</strong>
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
        >
          <template #header>
            <div>
              <div class="font-weight-bold text-body-2">
                {{ row.item_name }}
              </div>
              <div
                v-if="row.sku"
                class="text-caption text-medium-emphasis"
              >
                {{ row.sku }}
              </div>
            </div>
            <VChip
              size="small"
              label
              variant="tonal"
              :color="row.matchColor"
            >
              {{ t(`scp.po.match.status_${row.matchStatus}`) }}
            </VChip>
          </template>

          <div class="d-flex flex-wrap justify-space-between gap-2 mt-2 text-caption">
            <span>{{ t('scp.grn.qty.ordered') }}: <strong>{{ row.orderedQty }}</strong></span>
            <span>{{ t('scp.grn.qty.received') }}: <strong>{{ row.receivedQty }}</strong></span>
            <span>{{ t('scp.grn.qty.accepted') }}: <strong class="text-success">{{ row.acceptedQty }}</strong></span>
            <span v-if="row.rejectedQty > 0">{{ t('scp.grn.qty.rejected') }}: <strong class="text-error">{{ row.rejectedQty }}</strong></span>
          </div>
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
