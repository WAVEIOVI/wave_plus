<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const { t } = useI18n()
const router = useRouter()
const { smAndDown } = useDisplay()
const { format: formatMoney } = useCurrency()
const { statusLabel, resolveStatusVariant } = useProcurementHelpers()

const loading = ref(false)
const loadError = ref(false)
const dashboard = ref(null)

const fetchDashboard = async () => {
  loading.value = true
  loadError.value = false
  try {
    const res = await $api('/procurement/dashboard')

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

const kpis = computed(() => dashboard.value?.kpis || {})

const kpiCards = computed(() => [
  {
    key: 'pending_iprs',
    title: t('scp.dashboard.kpi.open_iprs'),
    value: kpis.value.open_iprs ?? 0,
    subtitle: t('scp.dashboard.kpi.open_iprs_desc'),
    icon: 'tabler-file-Import',
    color: 'primary',
    to: '/supply-chain-procurement/ipr/list',
  },
  {
    key: 'open_rfqs',
    title: t('scp.dashboard.kpi.pending_rfqs'),
    value: kpis.value.pending_rfqs ?? 0,
    subtitle: t('scp.dashboard.kpi.pending_rfqs_desc'),
    icon: 'tabler-messages',
    color: 'info',
    to: '/supply-chain-procurement/rfq/list',
  },
  {
    key: 'active_pos',
    title: t('scp.dashboard.kpi.active_pos'),
    value: kpis.value.active_pos ?? 0,
    subtitle: t('scp.dashboard.kpi.active_pos_desc'),
    icon: 'tabler-shopping-cart',
    color: 'warning',
    to: '/supply-chain-procurement/po/list',
  },
  {
    key: 'pending_grns',
    title: t('scp.dashboard.kpi.pending_grns'),
    value: kpis.value.pending_grns ?? 0,
    subtitle: t('scp.dashboard.kpi.pending_grns_desc'),
    icon: 'tabler-truck-delivery',
    color: 'secondary',
    to: '/supply-chain-procurement/grn/list',
  },
  {
    key: 'committed_spend',
    title: t('scp.dashboard.kpi.committed_spend'),
    value: formatMoney(kpis.value.total_spend_year ?? 0),
    subtitle: `${t('scp.dashboard.kpi.this_month')}: ${formatMoney(kpis.value.total_spend_month ?? 0)}`,
    icon: 'tabler-coins',
    color: 'success',
    to: '/supply-chain-procurement/po/list',
  },
])

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
    stats: dashboard.value?.pipeline?.iprs || {},
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
    stats: dashboard.value?.pipeline?.rfqs || {},
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
    stats: dashboard.value?.pipeline?.pos || {},
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
    stats: dashboard.value?.pipeline?.grns || {},
  },
])

const attentionItems = computed(() => dashboard.value?.attention_items || [])
const supplierPerformance = computed(() => dashboard.value?.supplier_performance || [])
const monthlyTrends = computed(() => dashboard.value?.monthly_trends || [])
</script>

<template>
  <div class="procurement-dashboard">
    <WpPageHeader
      :title="t('scp.dashboard.title')"
      :subtitle="t('scp.dashboard.subtitle')"
      icon="tabler-Building-warehouse"
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
        v-for="(item, idx) in attentionItems"
        :key="idx"
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
    <VRow>
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
            >
              <template #header>
                <div>
                  <div class="font-weight-bold text-body-2">
                    {{ sup.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ sup.code }}
                  </div>
                </div>
                <VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ formatMoney(sup.total_spend) }}
                </VChip>
              </template>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <VChip
                  size="x-small"
                  color="success"
                  variant="tonal"
                  label
                >
                  {{ t('scp.dashboard.scorecard.on_time_delivery') }}: {{ sup.on_time_rate }}%
                </VChip>
                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ t('scp.dashboard.scorecard.delivery_quality') }}: {{ sup.quality_pass_rate }}%
                </VChip>
              </div>
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
                  {{ t('scp.dashboard.scorecard.delivery_quality') }}
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
                    {{ sup.code }}
                  </div>
                </td>
                <td class="text-end">
                  {{ sup.po_count }}
                </td>
                <td class="text-end font-weight-bold">
                  {{ formatMoney(sup.total_spend) }}
                </td>
                <td class="text-end">
                  <VChip
                    size="x-small"
                    :color="sup.on_time_rate >= 80 ? 'success' : 'warning'"
                    variant="tonal"
                    label
                  >
                    {{ sup.on_time_rate }}%
                  </VChip>
                </td>
                <td class="text-end">
                  <VChip
                    size="x-small"
                    :color="sup.quality_pass_rate >= 90 ? 'success' : 'warning'"
                    variant="tonal"
                    label
                  >
                    {{ sup.quality_pass_rate }}%
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
          <div class="d-flex flex-column gap-3">
            <div
              v-for="m in monthlyTrends"
              :key="m.month"
              class="d-flex align-center justify-space-between py-2 border-b"
            >
              <div>
                <div class="text-body-2 font-weight-bold">
                  {{ m.label }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ m.po_count }} {{ t('scp.nav.po_short') }} · {{ m.grn_count }} {{ t('scp.nav.grn_short') }}
                </div>
              </div>
              <div class="text-body-1 font-weight-bold text-primary">
                {{ formatMoney(m.spend) }}
              </div>
            </div>
          </div>
        </WpSectionCard>
      </VCol>
    </VRow>
  </div>
</template>

