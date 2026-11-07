<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useCurrency } from '@/composables/useCurrency'
import { localizedValue } from '@/composables/useLocaleField'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import { usePoStore } from '@/stores/usePoStore'

const { t } = useI18n()
const router = useRouter()
const { smAndDown } = useDisplay()
const { format: formatMoney } = useCurrency()
const { statusLabel, resolveStatusVariant, resolveSupplierName, formatDate } = useProcurementHelpers()
const poStore = usePoStore()

const loading = ref(false)
const loadError = ref(false)
const dashboard = ref(null)

const fetchDashboard = async () => {
  loading.value = true
  loadError.value = false
  try {
    const [res] = await Promise.all([
      $api('/procurement/dashboard'),
      poStore.fetchList({ per_page: 10 }).catch(() => null),
    ])

    dashboard.value = res?.data ?? res ?? {}
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)

const NON_STATUS_KEYS = new Set([
  'total',
  'total_value_ht',
  'open_pos_value_ht',
  'urgent',
  'partial_deliveries',
  'late_deliveries',
])

const filterStatusStats = obj => {
  if (!obj || typeof obj !== 'object') return {}

  return Object.fromEntries(
    Object.entries(obj).filter(([key, val]) => !NON_STATUS_KEYS.has(key) && typeof val === 'number'),
  )
}

const kpiCards = computed(() => {
  const ipr = dashboard.value?.ipr || {}
  const rfq = dashboard.value?.rfq || {}
  const po = dashboard.value?.po || {}
  const grn = dashboard.value?.grn || {}

  return [
    {
      key: 'pending_iprs',
      title: t('scp.dashboard.kpi.open_iprs'),
      value: ipr.pending_approval ?? 0,
      subtitle: `${t('scp.common.total', 'Total')}: ${ipr.total ?? 0}`,
      icon: 'tabler-file-import',
      color: 'primary',
      to: '/supply-chain-procurement/ipr/list',
    },
    {
      key: 'open_rfqs',
      title: t('scp.dashboard.kpi.pending_rfqs'),
      value: (rfq.awaiting_responses ?? 0) + (rfq.under_evaluation ?? 0),
      subtitle: `${t('scp.common.total', 'Total')}: ${rfq.total ?? 0}`,
      icon: 'tabler-messages',
      color: 'info',
      to: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'active_pos',
      title: t('scp.dashboard.kpi.active_pos'),
      value: (po.approved ?? 0) + (po.sent ?? 0) + (po.acknowledged ?? 0) + (po.in_delivery ?? 0) + (po.partially_received ?? 0),
      subtitle: `${t('scp.common.total', 'Total')}: ${po.total ?? 0}`,
      icon: 'tabler-shopping-cart',
      color: 'warning',
      to: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pending_grns',
      title: t('scp.dashboard.kpi.pending_grns'),
      value: grn.under_qc ?? 0,
      subtitle: `${t('scp.common.total', 'Total')}: ${grn.total ?? 0}`,
      icon: 'tabler-truck-delivery',
      color: 'secondary',
      to: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'committed_spend',
      title: t('scp.dashboard.kpi.committed_spend'),
      value: formatMoney(po.total_value_ht ?? 0),
      subtitle: `${t('scp.dashboard.kpi.open_pos_value', 'Open POs')}: ${formatMoney(po.open_pos_value_ht ?? 0)}`,
      icon: 'tabler-coins',
      color: 'success',
      to: '/supply-chain-procurement/po/list',
    },
  ]
})

const workspaces = computed(() => [
  {
    key: 'ipr',
    title: t('scp.nav.ipr'),
    short: t('scp.nav.ipr_short'),
    subtitle: t('scp.ipr.subtitle'),
    icon: 'tabler-clipboard-list',
    color: 'primary',
    listRoute: '/supply-chain-procurement/ipr/list',
    createRoute: '/supply-chain-procurement/ipr/list?create=1',
    createLabel: t('scp.dashboard.quick_actions.new_ipr'),
    stats: filterStatusStats(dashboard.value?.ipr),
  },
  {
    key: 'rfq',
    title: t('scp.nav.rfq'),
    short: t('scp.nav.rfq_short'),
    subtitle: t('scp.rfq.subtitle'),
    icon: 'tabler-scale',
    color: 'info',
    listRoute: '/supply-chain-procurement/rfq/list',
    createRoute: '/supply-chain-procurement/rfq/list?create=1',
    createLabel: t('scp.dashboard.quick_actions.new_rfq'),
    stats: filterStatusStats(dashboard.value?.rfq),
  },
  {
    key: 'po',
    title: t('scp.nav.po'),
    short: t('scp.nav.po_short'),
    subtitle: t('scp.po.subtitle'),
    icon: 'tabler-shopping-cart-check',
    color: 'warning',
    listRoute: '/supply-chain-procurement/po/list',
    createRoute: '/supply-chain-procurement/po/list?create=1',
    createLabel: t('scp.dashboard.quick_actions.new_po'),
    stats: filterStatusStats(dashboard.value?.po),
  },
  {
    key: 'grn',
    title: t('scp.nav.grn'),
    short: t('scp.nav.grn_short'),
    subtitle: t('scp.grn.subtitle'),
    icon: 'tabler-package-import',
    color: 'success',
    listRoute: '/supply-chain-procurement/grn/list',
    createRoute: '/supply-chain-procurement/grn/list?create=1',
    createLabel: t('scp.dashboard.quick_actions.new_grn'),
    stats: filterStatusStats(dashboard.value?.grn),
  },
])

const attentionItems = computed(() => {
  const raw = dashboard.value?.attention_items
  if (!raw) return []
  if (Array.isArray(raw)) return raw.filter(i => (i.count ?? 1) > 0)

  const definitions = [
    {
      key: 'iprs_awaiting_approval',
      title: t('scp.ipr.kpi.pending_approval', 'IPRs Awaiting Approval'),
      subtitle: t('scp.nav.ipr'),
      severity: 'warning',
      route: '/supply-chain-procurement/ipr/list',
    },
    {
      key: 'rfqs_awaiting_responses',
      title: t('scp.rfq.kpi.awaiting_responses', 'RFQs Awaiting Responses'),
      subtitle: t('scp.nav.rfq'),
      severity: 'info',
      route: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'rfqs_under_evaluation',
      title: t('scp.rfq.kpi.under_evaluation', 'RFQs Under Evaluation'),
      subtitle: t('scp.nav.rfq'),
      severity: 'info',
      route: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'pos_pending_approval',
      title: t('scp.po.kpi.pending_approval', 'POs Pending Approval'),
      subtitle: t('scp.nav.po'),
      severity: 'warning',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pos_in_delivery',
      title: t('scp.po.kpi.in_delivery', 'POs In Delivery'),
      subtitle: t('scp.nav.po'),
      severity: 'info',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pos_partially_received',
      title: t('scp.status.partially_received', 'POs Partially Received'),
      subtitle: t('scp.nav.po'),
      severity: 'warning',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'grns_under_qc',
      title: t('scp.grn.kpi.under_qc', 'GRNs Under QC'),
      subtitle: t('scp.nav.grn'),
      severity: 'warning',
      route: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'grns_partial_deliveries',
      title: t('scp.grn.fields.partial_delivery', 'Partial Deliveries'),
      subtitle: t('scp.nav.grn'),
      severity: 'info',
      route: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'grns_late_deliveries',
      title: t('scp.dashboard.attention.late_deliveries', 'Late Deliveries'),
      subtitle: t('scp.nav.grn'),
      severity: 'error',
      route: '/supply-chain-procurement/grn/list',
    },
  ]

  return definitions
    .map(def => ({
      ...def,
      count: Number(raw[def.key] || 0),
    }))
    .filter(item => item.count > 0)
})

const supplierPerformance = computed(() =>
  (dashboard.value?.supplier_performance || []).map(sup => ({
    id: sup.supplier_id || sup.id,
    supplier_id: sup.supplier_id || sup.code || '—',
    name: localizedValue(sup.supplier_name) || resolveSupplierName(sup),
    po_count: Number(sup.po_count ?? 0),
    total_value_ht: Number(sup.total_value_ht ?? sup.total_spend ?? 0),
    fulfillment_rate: Number(sup.fulfillment_rate ?? sup.on_time_rate ?? 0),
    partial_rate: Number(sup.partial_rate ?? 0),
  })),
)

const monthlyTrends = computed(() =>
  (dashboard.value?.monthly_trends || []).map(m => ({
    month: m.month || m.label,
    iprs: Number(m.iprs ?? 0),
    rfqs: Number(m.rfqs ?? 0),
    pos: Number(m.pos ?? m.po_count ?? 0),
    grns: Number(m.grns ?? m.grn_count ?? 0),
    po_value: Number(m.po_value ?? m.spend ?? 0),
  })),
)

const recentPos = computed(() => poStore.list || [])
</script>

<template>
  <div class="procurement-dashboard">
    <WpPageHeader
      :title="t('scp.dashboard.title')"
      :subtitle="t('scp.dashboard.subtitle')"
      icon="tabler-building-warehouse"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.dashboard') },
      ]"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          min-height="44"
          @click="fetchDashboard"
        >
          {{ t('common.refresh') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          min-height="44"
          @click="router.push('/supply-chain-procurement/ipr/list?create=1')"
        >
          {{ t('scp.dashboard.quick_actions.new_ipr') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <VAlert
      v-if="loadError"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ t('wp.states.error_text') }}
      <template #append>
        <VBtn
          size="small"
          variant="text"
          @click="fetchDashboard"
        >
          {{ t('wp.states.retry') }}
        </VBtn>
      </template>
    </VAlert>

    <!-- Top KPI Group -->
    <WpKpiGroup class="mb-6">
      <WpKpiCard
        v-for="kpi in kpiCards"
        :key="kpi.key"
        :title="kpi.title"
        :value="kpi.value"
        :subtitle="kpi.subtitle"
        :icon="kpi.icon"
        :color="kpi.color"
        :loading="loading"
        clickable
        @click="router.push(kpi.to)"
      />
    </WpKpiGroup>

    <!-- Attention Alerts -->
    <VRow
      v-if="attentionItems.length"
      class="mb-4"
    >
      <VCol
        v-for="item in attentionItems"
        :key="item.key || item.title"
        cols="12"
        md="6"
        lg="3"
      >
        <VAlert
          :type="item.severity || 'warning'"
          variant="tonal"
          density="comfortable"
          class="cursor-pointer h-100"
          @click="item.route && router.push(item.route)"
        >
          <div class="d-flex align-center justify-space-between gap-2">
            <div>
              <div class="font-weight-bold text-body-2">
                {{ item.title }}
              </div>
              <div class="text-caption">
                {{ item.subtitle }}
              </div>
            </div>
            <VChip
              size="small"
              label
              :color="item.severity || 'warning'"
            >
              {{ item.count }}
            </VChip>
          </div>
        </VAlert>
      </VCol>
    </VRow>

    <!-- Quick Workspaces -->
    <VRow class="mb-6">
      <VCol
        v-for="ws in workspaces"
        :key="ws.key"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard
          variant="flat"
          border
          class="h-100 d-flex flex-column justify-space-between"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar
                :color="ws.color"
                variant="tonal"
                size="44"
                rounded
              >
                <VIcon
                  :icon="ws.icon"
                  size="24"
                />
              </VAvatar>
              <VChip
                size="small"
                label
                :color="ws.color"
                variant="tonal"
              >
                {{ ws.short }}
              </VChip>
            </div>

            <h3 class="text-h6 font-weight-bold mb-1">
              {{ ws.title }}
            </h3>
            <p class="text-body-2 text-medium-emphasis mb-3">
              {{ ws.subtitle }}
            </p>

            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-for="(count, st) in ws.stats"
                :key="st"
                size="x-small"
                label
                variant="tonal"
                :color="resolveStatusVariant(st)"
              >
                {{ statusLabel(st) }}: {{ count }}
              </VChip>
            </div>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-3 d-flex justify-space-between gap-2">
            <VBtn
              variant="text"
              size="small"
              color="secondary"
              prepend-icon="tabler-list"
              min-height="40"
              @click="router.push(ws.listRoute)"
            >
              {{ t('scp.action.view_all') }}
            </VBtn>
            <VBtn
              variant="tonal"
              size="small"
              :color="ws.color"
              prepend-icon="tabler-plus"
              min-height="40"
              @click="router.push(ws.createRoute)"
            >
              {{ ws.createLabel }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Supplier Performance & Monthly Spend Trends -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        lg="7"
      >
        <WpSectionCard
          :title="t('scp.dashboard.scorecard.title')"
          :subtitle="t('scp.dashboard.scorecard.subtitle')"
          icon="tabler-award"
        >
          <WpEmptyState
            v-if="!loading && supplierPerformance.length === 0"
            :title="t('scp.dashboard.scorecard.no_suppliers')"
            icon="tabler-building-store"
            compact
          />

          <div
            v-else-if="smAndDown"
            class="d-flex flex-column gap-3"
          >
            <WpMobileCard
              v-for="sup in supplierPerformance"
              :key="sup.id"
              :title="sup.name"
              :subtitle="sup.supplier_id"
            >
              <template #badges>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ formatMoney(sup.total_value_ht) }}
                </VChip>
                <VChip
                  size="x-small"
                  :color="sup.fulfillment_rate >= 80 ? 'success' : 'warning'"
                  variant="tonal"
                  label
                >
                  {{ t('scp.dashboard.scorecard.on_time_delivery') }}: {{ sup.fulfillment_rate }}%
                </VChip>
                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ t('scp.grn.fields.partial_delivery') }}: {{ sup.partial_rate }}%
                </VChip>
              </template>
            </WpMobileCard>
          </div>

          <VTable
            v-else
            density="comfortable"
          >
            <thead>
              <tr>
                <th>{{ t('scp.dashboard.scorecard.supplier') }}</th>
                <th class="text-end">
                  {{ t('scp.nav.po_short') }}
                </th>
                <th class="text-end">
                  {{ t('scp.common.total') }}
                </th>
                <th class="text-end">
                  {{ t('scp.dashboard.scorecard.on_time_delivery') }}
                </th>
                <th class="text-end">
                  {{ t('scp.grn.fields.partial_delivery') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="sup in supplierPerformance"
                :key="sup.id"
              >
                <td>
                  <div class="font-weight-medium">
                    {{ sup.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ sup.supplier_id }}
                  </div>
                </td>
                <td class="text-end">
                  {{ sup.po_count }}
                </td>
                <td class="text-end font-weight-bold">
                  {{ formatMoney(sup.total_value_ht) }}
                </td>
                <td class="text-end">
                  <VChip
                    size="x-small"
                    :color="sup.fulfillment_rate >= 80 ? 'success' : 'warning'"
                    variant="tonal"
                    label
                  >
                    {{ sup.fulfillment_rate }}%
                  </VChip>
                </td>
                <td class="text-end">
                  <VChip
                    size="x-small"
                    :color="sup.partial_rate <= 20 ? 'success' : 'warning'"
                    variant="tonal"
                    label
                  >
                    {{ sup.partial_rate }}%
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </WpSectionCard>
      </VCol>

      <VCol
        cols="12"
        lg="5"
      >
        <WpSectionCard
          :title="t('scp.dashboard.pipeline.monthly_trends')"
          :subtitle="t('scp.dashboard.pipeline.subtitle')"
          icon="tabler-chart-bar"
        >
          <WpEmptyState
            v-if="!loading && monthlyTrends.length === 0"
            :title="t('wp.states.empty_title', 'No monthly trend data')"
            icon="tabler-chart-bar"
            compact
          />

          <div
            v-else
            class="d-flex flex-column gap-3"
          >
            <div
              v-for="m in monthlyTrends"
              :key="m.month"
              class="d-flex align-center justify-space-between py-2 border-b"
            >
              <div>
                <div class="text-body-2 font-weight-bold">
                  {{ m.month }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ m.iprs }} {{ t('scp.nav.ipr_short') }} · {{ m.rfqs }} {{ t('scp.nav.rfq_short') }} · {{ m.pos }} {{ t('scp.nav.po_short') }} · {{ m.grns }} {{ t('scp.nav.grn_short') }}
                </div>
              </div>
              <div class="text-body-1 font-weight-bold text-primary">
                {{ formatMoney(m.po_value) }}
              </div>
            </div>
          </div>
        </WpSectionCard>
      </VCol>
    </VRow>

    <!-- Recent Purchase Orders & Document Lifecycle Tracker -->
    <WpSectionCard
      :title="t('scp.po.list_title', 'Recent Purchase Orders & Lifecycle Tracker')"
      :subtitle="t('scp.po.subtitle')"
      icon="tabler-git-branch"
    >
      <template #actions>
        <VBtn
          variant="text"
          size="small"
          color="primary"
          append-icon="tabler-arrow-right"
          @click="router.push('/supply-chain-procurement/po/list')"
        >
          {{ t('scp.action.view_all') }}
        </VBtn>
      </template>

      <WpEmptyState
        v-if="!loading && recentPos.length === 0"
        :title="t('scp.po.empty.title')"
        :subtitle="t('scp.po.empty.subtitle')"
        icon="tabler-shopping-cart"
        compact
      />

      <div
        v-else-if="smAndDown"
        class="d-flex flex-column gap-3"
      >
        <WpMobileCard
          v-for="po in recentPos"
          :key="po.id"
          :title="po.reference"
          :subtitle="resolveSupplierName(po.supplier)"
          :status="po.status"
          :status-color="resolveStatusVariant(po.status)"
          :status-label="statusLabel(po.status)"
          clickable
          @click="router.push(`/supply-chain-procurement/po/edit/${po.id}`)"
        >
          <template #badges>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              label
            >
              {{ formatMoney(po.total_ht || 0, { currency: po.currency }) }}
            </VChip>
            <VChip
              v-if="(po.ipr_ids || []).length"
              size="x-small"
              color="info"
              variant="tonal"
              label
            >
              {{ (po.ipr_ids || []).length }} {{ t('scp.nav.ipr_short') }}
            </VChip>
            <VChip
              v-if="po.rfq_id"
              size="x-small"
              color="secondary"
              variant="tonal"
              label
            >
              {{ t('scp.nav.rfq_short') }}
            </VChip>
          </template>

          <template #actions>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              @click.stop="router.push(`/supply-chain-procurement/po/edit/${po.id}`)"
            >
              {{ t('scp.action.edit') }}
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-git-compare"
              @click.stop="router.push(`/supply-chain-procurement/po/match/${po.id}`)"
            >
              {{ t('scp.po.action.three_way_match') }}
            </VBtn>
          </template>
        </WpMobileCard>
      </div>

      <VTable
        v-else
        density="comfortable"
      >
        <thead>
          <tr>
            <th>{{ t('scp.common.reference') }}</th>
            <th>{{ t('scp.common.supplier') }}</th>
            <th>{{ t('scp.common.status') }}</th>
            <th>{{ t('scp.traceability.title', 'Traceability') }}</th>
            <th class="text-end">
              {{ t('scp.common.subtotal_ht') }}
            </th>
            <th>{{ t('scp.common.created_at') }}</th>
            <th class="text-end">
              {{ t('scp.common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="po in recentPos"
            :key="po.id"
          >
            <td>
              <RouterLink
                :to="`/supply-chain-procurement/po/edit/${po.id}`"
                class="font-weight-bold text-primary text-decoration-none"
              >
                {{ po.reference }}
              </RouterLink>
            </td>
            <td>{{ resolveSupplierName(po.supplier) }}</td>
            <td>
              <WpStatusChip
                :status="po.status"
                :label="statusLabel(po.status)"
                :color="resolveStatusVariant(po.status)"
              />
            </td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-if="(po.ipr_ids || []).length"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ (po.ipr_ids || []).length }} {{ t('scp.nav.ipr_short') }}
                </VChip>
                <VChip
                  v-if="po.rfq_id"
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ t('scp.nav.rfq_short') }}
                </VChip>
                <span
                  v-if="!(po.ipr_ids || []).length && !po.rfq_id"
                  class="text-caption text-medium-emphasis"
                >—</span>
              </div>
            </td>
            <td class="text-end font-weight-bold">
              {{ formatMoney(po.total_ht || 0, { currency: po.currency }) }}
            </td>
            <td>{{ formatDate(po.created_at) }}</td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  icon="tabler-edit"
                  :title="t('scp.action.edit')"
                  @click="router.push(`/supply-chain-procurement/po/edit/${po.id}`)"
                />
                <VBtn
                  size="small"
                  variant="tonal"
                  color="info"
                  icon="tabler-git-compare"
                  :title="t('scp.po.action.three_way_match')"
                  @click="router.push(`/supply-chain-procurement/po/match/${po.id}`)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </WpSectionCard>
  </div>
</template>

