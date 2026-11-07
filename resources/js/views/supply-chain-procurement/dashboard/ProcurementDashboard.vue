<script setup>
import { WpErrorBanner, WpPageHeader } from '@/components/shared'
import { useProcurementDashboard } from '@/composables/supply-chain-procurement'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ProcurementAttentionCard from './components/ProcurementAttentionCard.vue'
import ProcurementKpiGrid from './components/ProcurementKpiGrid.vue'
import ProcurementPipelineSpendChart from './components/ProcurementPipelineSpendChart.vue'
import ProcurementStatusOverviewCard from './components/ProcurementStatusOverviewCard.vue'
import ProcurementSupplierScorecard from './components/ProcurementSupplierScorecard.vue'
import ProcurementTrackerTable from './components/ProcurementTrackerTable.vue'

const { t, locale } = useI18n()
const { data, recentPos, loading, error, updatedAt, fetchDashboard } = useProcurementDashboard()

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')

const quickCreateActions = computed(() => [
  {
    key: 'ipr',
    label: t('scp.dashboard.quick_actions.new_ipr'),
    icon: 'tabler-clipboard-plus',
    color: 'primary',
    to: '/supply-chain-procurement/ipr/edit/new',
  },
  {
    key: 'rfq',
    label: t('scp.dashboard.quick_actions.new_rfq'),
    icon: 'tabler-scale',
    color: 'info',
    to: '/supply-chain-procurement/rfq/edit/new',
  },
  {
    key: 'po',
    label: t('scp.dashboard.quick_actions.new_po'),
    icon: 'tabler-shopping-cart-plus',
    color: 'warning',
    to: '/supply-chain-procurement/po/edit/new',
  },
  {
    key: 'grn',
    label: t('scp.dashboard.quick_actions.new_grn'),
    icon: 'tabler-package-import',
    color: 'success',
    to: '/supply-chain-procurement/grn/edit/new',
  },
])
</script>

<template>
  <div class="procurement-dashboard d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('scp.dashboard.title')"
      :subtitle="$t('scp.dashboard.subtitle')"
      icon="tabler-shopping-cart"
    >
      <template #actions>
        <span
          v-if="lastUpdated"
          class="text-caption text-medium-emphasis me-sm-2"
        >{{ $t('bp_dashboard.last_updated', { date: lastUpdated }) }}</span>
        <VBtn
          color="secondary"
          variant="tonal"
          :loading="loading"
          prepend-icon="tabler-refresh"
          @click="fetchDashboard"
        >
          {{ $t('bp_dashboard.refresh') }}
        </VBtn>
        <VBtn
          v-for="action in quickCreateActions"
          :key="action.key"
          :to="action.to"
          :color="action.color"
          variant="tonal"
          :prepend-icon="action.icon"
        >
          {{ action.label }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpErrorBanner
      :error="error"
      @retry="fetchDashboard"
    />

    <ProcurementKpiGrid
      :data="data"
      :loading="loading"
    />

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <ProcurementPipelineSpendChart
          :monthly-trends="data?.monthly_trends"
          :loading="loading"
        />
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <ProcurementAttentionCard
          :attention-items="data?.attention_items"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="5"
      >
        <ProcurementStatusOverviewCard
          :data="data"
          :loading="loading"
        />
      </VCol>
      <VCol
        cols="12"
        lg="7"
      >
        <ProcurementSupplierScorecard
          :suppliers="data?.supplier_performance"
          :loading="loading"
          @retry="fetchDashboard"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12">
        <ProcurementTrackerTable
          :items="recentPos"
          :loading="loading"
          @retry="fetchDashboard"
        />
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.scp-dashboard {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(24px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
