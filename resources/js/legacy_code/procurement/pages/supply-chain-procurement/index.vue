<script setup>
/* eslint-disable camelcase */
import { WpKpiCard, WpPageHeader } from '@/components/shared'
import { useCurrency } from '@/composables/use-currency'
import { useApi } from '@/composables/useApi'
import { usePoStore } from '@/stores/usePoStore'
import ProcurementTrackerTable from '@/views/supply-chain-procurement/dashboard/ProcurementTrackerTable.vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  name: 'supply-chain-procurement',
  meta: {
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const router = useRouter()
const poStore = usePoStore()
const { data: dashboardResponse, execute: fetchDashboard } = useApi('/procurement/dashboard', {}, { immediate: false })
const { formatCurrency, formatPrice, formatPercentage, formatInteger } = useCurrency()

const isLoading = ref(true)
const dashboardData = ref(null)

const stats = computed(() => ({
  openIprs: dashboardData.value?.ipr?.pending_approval || 0,
  pendingPos: dashboardData.value?.po?.pending_approval || 0,
  grnsAwaitingQc: dashboardData.value?.grn?.under_qc || 0,
  supplierCount: dashboardData.value?.supplier?.active || 0,

  // Additional stats for attention
  iprsAwaitingApproval: dashboardData.value?.ipr?.pending_approval || 0,
  rfqsAwaitingResponses: dashboardData.value?.rfq?.awaiting_responses || 0,
  rfqsUnderEvaluation: dashboardData.value?.rfq?.under_evaluation || 0,
  posInDelivery: dashboardData.value?.po?.in_delivery || 0,
  posPartiallyReceived: dashboardData.value?.po?.partially_received || 0,
  grnsPartialDeliveries: dashboardData.value?.grn?.partial_deliveries || 0,
  grnsLateDeliveries: dashboardData.value?.grn?.late_deliveries || 0,
  totalPoValue: dashboardData.value?.po?.total_value_ht || 0,
  openPoValue: dashboardData.value?.po?.open_pos_value_ht || 0,
}))

const monthlyTrends = computed(() => dashboardData.value?.monthly_trends || [])
const supplierPerformance = computed(() => dashboardData.value?.supplier_performance || [])
const attentionItems = computed(() => dashboardData.value?.attention_items || {})

const recentPos = ref([])

const quickActions = [
  { label: 'New IPR', icon: 'tabler-file-plus', color: 'primary', to: { name: 'procurement-ipr-edit-id', params: { id: 'new' } } },
  { label: 'New RFQ', icon: 'tabler-clipboard-plus', color: 'info', to: { name: 'procurement-rfq-edit-id', params: { id: 'new' } } },
  { label: 'New PO', icon: 'tabler-shopping-cart-plus', color: 'success', to: { name: 'procurement-po-edit-id', params: { id: 'new' } } },
]

const resolveStatusColor = status => {
  const map = {
    draft: 'secondary',
    pending_approval: 'warning',
    approved: 'success',
    rejected: 'error',
    sent: 'primary',
    acknowledged: 'info',
    in_delivery: 'info',
    partially_received: 'warning',
    fully_received: 'success',
    closed: 'secondary',
  }

  return map[status] || 'secondary'
}

const getScoreColor = score => {
  if (score >= 80) {
    return 'success'
  }

  if (score >= 60) {
    return 'warning'
  }

  return 'error'
}

onMounted(async () => {
  isLoading.value = true

  try {
    // Fetch dashboard data from single aggregated endpoint
    await fetchDashboard()

    dashboardData.value = dashboardResponse.value?.data || null

    // Also fetch recent POs for the table
    const posResponse = await poStore.fetchList({ itemsPerPage: 5, include: 'supplier' })

    recentPos.value = posResponse?.data || []
  } catch (error) {
    console.error('Failed to fetch dashboard data', error)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="procurement-dashboard">
    <WpPageHeader
      title="Supply Chain & Procurement"
      subtitle="Overview of procurement activities and performance"
      icon="tabler-shopping-cart"
    >
      <template #actions>
        <VBtn
          v-for="action in quickActions"
          :key="action.label"
          :color="action.color"
          variant="tonal"
          size="small"
          class="d-none d-sm-inline-flex"
          @click="router.push(action.to)"
        >
          <VIcon
            :icon="action.icon"
            class="me-1"
          />
          {{ action.label }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- KPI Cards Row -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Open IPRs"
          :value="formatInteger(stats.openIprs)"
          icon="tabler-file-invoice"
          color="primary"
          trend="Total active requisitions"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'procurement-ipr-list' })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Pending POs"
          :value="formatInteger(stats.pendingPos)"
          icon="tabler-shopping-cart"
          color="warning"
          trend="Purchase orders awaiting action"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'procurement-po-list' })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="GRNs Awaiting QC"
          :value="formatInteger(stats.grnsAwaitingQc)"
          icon="tabler-package-import"
          color="error"
          trend="Items pending quality check"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'procurement-grn-list' })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Active Suppliers"
          :value="formatInteger(stats.supplierCount)"
          icon="tabler-building-store"
          color="info"
          trend="Registered vendors"
          :loading="isLoading"
        />
      </VCol>
    </VRow>

    <!-- Procurement Value Cards Row -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard class="glass-panel border h-100">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold d-flex align-center">
              <VIcon
                icon="tabler-coin"
                class="me-2"
                color="success"
              />
              Total PO Value
            </VCardTitle>
            <VCardSubtitle class="mt-2">
              All purchase orders (HT)
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="text-h4 font-weight-bold text-success">
              {{ formatPrice(stats.totalPoValue) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              TND (Excluding VAT)
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard class="glass-panel border h-100">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold d-flex align-center">
              <VIcon
                icon="tabler-wallet"
                class="me-2"
                color="warning"
              />
              Open PO Value
            </VCardTitle>
            <VCardSubtitle class="mt-2">
              Pending and in-delivery (HT)
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="text-h4 font-weight-bold text-warning">
              {{ formatPrice(stats.openPoValue) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              TND (Excluding VAT)
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard class="glass-panel border h-100">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold d-flex align-center">
              <VIcon
                icon="tabler-alert-triangle"
                class="me-2"
                color="error"
              />
              Attention Required
            </VCardTitle>
            <VCardSubtitle class="mt-2">
              Items requiring action
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex flex-column gap-2">
              <div
                v-if="stats.grnsLateDeliveries > 0"
                class="d-flex justify-space-between"
              >
                <span class="text-body-2">Late Deliveries</span>
                <VChip
                  size="x-small"
                  color="error"
                  variant="tonal"
                >
                  {{ formatInteger(stats.grnsLateDeliveries) }}
                </VChip>
              </div>
              <div
                v-if="stats.posPartiallyReceived > 0"
                class="d-flex justify-space-between"
              >
                <span class="text-body-2">Partial Received</span>
                <VChip
                  size="x-small"
                  color="warning"
                  variant="tonal"
                >
                  {{ formatInteger(stats.posPartiallyReceived) }}
                </VChip>
              </div>
              <div
                v-if="stats.rfqsUnderEvaluation > 0"
                class="d-flex justify-space-between"
              >
                <span class="text-body-2">RFQs to Evaluate</span>
                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                >
                  {{ formatInteger(stats.rfqsUnderEvaluation) }}
                </VChip>
              </div>
              <div
                v-if="!stats.grnsLateDeliveries && !stats.posPartiallyReceived && !stats.rfqsUnderEvaluation"
                class="text-center py-2 text-success"
              >
                <VIcon
                  icon="tabler-check"
                  class="me-1"
                />
                All clear
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Recent POs and Quick Actions Row -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="glass-panel border h-100">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold">
              Recent Purchase Orders
            </VCardTitle>
            <template #append>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                @click="router.push({ name: 'procurement-po-list' })"
              >
                View All
              </VBtn>
            </template>
          </VCardItem>
          
          <VCardText>
            <VTable class="bg-transparent">
              <thead>
                <tr>
                  <th class="text-uppercase text-caption font-weight-bold inter-font">
                    Reference
                  </th>
                  <th class="text-uppercase text-caption font-weight-bold inter-font">
                    Supplier
                  </th>
                  <th class="text-uppercase text-caption font-weight-bold inter-font text-end">
                    Amount (HT)
                  </th>
                  <th class="text-uppercase text-caption font-weight-bold inter-font text-center">
                    Status
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="po in recentPos"
                  :key="po.id"
                  class="cursor-pointer"
                  @click="router.push({ name: 'procurement-po-edit-id', params: { id: po.id } })"
                >
                  <td class="inter-font font-weight-medium">
                    {{ po.reference }}
                  </td>
                  <td class="inter-font text-body-2">
                    {{ po.supplier?.legal_name || '—' }}
                  </td>
                  <td class="inter-font text-body-2 font-weight-bold text-end">
                    {{ formatPrice(po.total_ht || po.total_amount || 0) }}
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="resolveStatusColor(po.status)"
                      size="x-small"
                      label
                      class="text-capitalize"
                    >
                      {{ po.status?.replace(/_/g, ' ') }}
                    </VChip>
                  </td>
                </tr>
                <tr v-if="recentPos.length === 0 && !isLoading">
                  <td
                    colspan="4"
                    class="text-center py-4 text-medium-emphasis inter-font"
                  >
                    No recent orders found
                  </td>
                </tr>
                <tr v-if="isLoading">
                  <td
                    colspan="4"
                    class="text-center py-4"
                  >
                    <VProgressCircular
                      indeterminate
                      size="24"
                      color="primary"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard class="glass-panel border h-100 d-flex flex-column">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold">
              Quick Actions
            </VCardTitle>
            <VCardSubtitle>Create new procurement documents</VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-3 grow">
            <VBtn
              v-for="action in quickActions"
              :key="action.label"
              block
              variant="tonal"
              :color="action.color"
              :prepend-icon="action.icon"
              @click="router.push(action.to)"
            >
              {{ action.label }}
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Supplier Scorecard -->
    <VRow class="mb-6">
      <VCol cols="12">
        <VCard class="glass-panel border">
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2 inter-font font-weight-bold">
              <VIcon
                icon="tabler-award"
                color="warning"
              />
              Supplier Performance Scorecard
            </VCardTitle>
            <VCardSubtitle>Performance metrics based on delivery and fulfillment</VCardSubtitle>
          </VCardItem>
          <VDivider />
          
          <VCardText v-if="isLoading">
            <div class="d-flex justify-center align-center py-10">
              <VProgressCircular
                indeterminate
                size="48"
                color="primary"
              />
            </div>
          </VCardText>

          <VCardText
            v-else-if="supplierPerformance.length === 0"
            class="text-center py-10 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-chart-bar-off"
              size="48"
              class="mb-4 text-disabled"
            />
            <p class="mb-0">
              No supplier performance data available
            </p>
          </VCardText>

          <div
            v-else
            class="table-responsive"
          >
            <VTable class="text-no-wrap">
              <thead>
                <tr>
                  <th class="text-uppercase text-caption font-weight-bold">
                    Supplier
                  </th>
                  <th class="text-center text-uppercase text-caption font-weight-bold">
                    PO Count
                  </th>
                  <th class="text-end text-uppercase text-caption font-weight-bold">
                    Total Value (HT)
                  </th>
                  <th class="text-center text-uppercase text-caption font-weight-bold">
                    Fulfillment Rate
                  </th>
                  <th class="text-center text-uppercase text-caption font-weight-bold">
                    Partial Rate
                  </th>
                  <th class="text-center text-uppercase text-caption font-weight-bold">
                    Avg. Days Late
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="supplier in supplierPerformance"
                  :key="supplier.supplier_id"
                >
                  <td class="font-weight-medium">
                    {{ supplier.supplier_name }}
                  </td>
                  <td class="text-center">
                    {{ formatInteger(supplier.po_count) }}
                  </td>
                  <td class="text-end font-weight-bold">
                    {{ formatPrice(supplier.total_value_ht) }}
                  </td>
                  <td class="text-center">
                    <VChip
                      size="small"
                      :color="getScoreColor(supplier.fulfillment_rate)"
                      variant="tonal"
                    >
                      {{ formatPercentage(supplier.fulfillment_rate) }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VChip
                      size="small"
                      :color="supplier.partial_rate > 20 ? 'warning' : 'success'"
                      variant="tonal"
                    >
                      {{ formatPercentage(supplier.partial_rate) }}
                    </VChip>
                  </td>
                  <td class="text-center text-body-2">
                    {{ formatInteger(supplier.avg_days_late) }} days
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Procurement Tracker Table -->
    <VRow>
      <VCol cols="12">
        <ProcurementTrackerTable />
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.inter-font {
  font-family: Inter, sans-serif !important;
}

.glass-panel {
  border-color: rgba(var(--v-theme-on-surface), 0.08) !important;
  backdrop-filter: blur(12px);
  background: rgba(var(--v-theme-surface), 0.7) !important;
}

.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }

.cursor-pointer {
  cursor: pointer;
}

.cursor-pointer:hover {
  background: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
