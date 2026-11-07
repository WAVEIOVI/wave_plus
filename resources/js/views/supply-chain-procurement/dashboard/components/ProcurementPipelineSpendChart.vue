<script setup>
/* eslint-disable camelcase */
import { WpEmptyState } from '@/components/shared'
import { useCurrency } from '@/composables/useCurrency'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

const props = defineProps({
  monthlyTrends: {
    type: Array,
    default: () => [],
  },
  loading: Boolean,
})

const { t } = useI18n()
const theme = useTheme()
const { format: formatMoney } = useCurrency()

const normalizedTrends = computed(() =>
  (props.monthlyTrends || []).map(item => ({
    month: item.month || item.label || '',
    iprs: Number(item.iprs ?? 0),
    rfqs: Number(item.rfqs ?? 0),
    pos: Number(item.pos ?? item.po_count ?? 0),
    grns: Number(item.grns ?? item.grn_count ?? 0),
    po_value: Number(item.po_value ?? item.spend ?? 0),
  })),
)

const totalSpend = computed(() =>
  normalizedTrends.value.reduce((sum, item) => sum + item.po_value, 0),
)

const hasData = computed(() =>
  normalizedTrends.value.some(
    item => item.po_value > 0 || item.iprs > 0 || item.rfqs > 0 || item.pos > 0 || item.grns > 0,
  ),
)

const series = computed(() => [
  {
    name: t('scp.dashboard.kpi.committed_spend', 'Committed Spend (HT)'),
    type: 'area',
    data: normalizedTrends.value.map(item => item.po_value),
  },
  {
    name: t('scp.nav.ipr_short', 'IPR'),
    type: 'column',
    data: normalizedTrends.value.map(item => item.iprs),
  },
  {
    name: t('scp.nav.rfq_short', 'RFQ'),
    type: 'column',
    data: normalizedTrends.value.map(item => item.rfqs),
  },
  {
    name: t('scp.nav.po_short', 'PO'),
    type: 'column',
    data: normalizedTrends.value.map(item => item.pos),
  },
  {
    name: t('scp.nav.grn_short', 'GRN'),
    type: 'column',
    data: normalizedTrends.value.map(item => item.grns),
  },
])

const chartOptions = computed(() => {
  const colors = theme.current.value.colors
  const onSurface = colors['on-surface']

  return {
    chart: {
      type: 'line',
      toolbar: { show: false },
      parentHeightOffset: 0,
      zoom: { enabled: false },
      stacked: false,
    },
    colors: [
      colors.success,
      colors.primary,
      colors.info,
      colors.warning,
      colors.secondary,
    ],
    dataLabels: { enabled: false },
    stroke: {
      curve: 'smooth',
      width: [3, 0, 0, 0, 0],
    },
    plotOptions: {
      bar: {
        columnWidth: '48%',
        borderRadius: 3,
      },
    },
    fill: {
      type: ['gradient', 'solid', 'solid', 'solid', 'solid'],
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.32,
        opacityTo: 0.04,
        stops: [0, 90, 100],
      },
    },
    grid: {
      borderColor: 'rgba(var(--v-theme-on-surface), 0.12)',
      strokeDashArray: 4,
      padding: { left: 4, right: 8 },
    },
    legend: {
      position: 'top',
      horizontalAlign: 'left',
      labels: { colors: onSurface },
    },
    xaxis: {
      categories: normalizedTrends.value.map(item => item.month),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: onSurface } },
    },
    yaxis: [
      {
        min: 0,
        forceNiceScale: true,
        labels: {
          style: { colors: onSurface },
          formatter: value => Math.round(Number(value || 0)).toLocaleString(),
        },
      },
      {
        opposite: true,
        min: 0,
        forceNiceScale: true,
        labels: {
          style: { colors: onSurface },
          formatter: value => String(Math.round(Number(value || 0))),
        },
      },
      { show: false, seriesName: t('scp.nav.rfq_short', 'RFQ') },
      { show: false, seriesName: t('scp.nav.po_short', 'PO') },
      { show: false, seriesName: t('scp.nav.grn_short', 'GRN') },
    ],
    tooltip: {
      shared: true,
      intersect: false,
      theme: theme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: (value, { seriesIndex }) => {
          if (seriesIndex === 0) return formatMoney(value || 0)

          return String(value ?? 0)
        },
      },
    },
  }
})
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="h-100"
  >
    <VCardItem class="pb-0">
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-chart-area-line" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('scp.dashboard.pipeline.monthly_trends') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('scp.dashboard.pipeline.subtitle') }}</VCardSubtitle>
      <template #append>
        <div class="text-end">
          <p class="text-h6 font-weight-bold mb-0">
            {{ formatMoney(totalSpend) }}
          </p>
          <span class="text-caption text-medium-emphasis">{{ $t('bp_dashboard.charts.last_six_months', 'Last 6 months') }}</span>
        </div>
      </template>
    </VCardItem>

    <VCardText class="pt-4">
      <VSkeletonLoader
        v-if="loading"
        type="image"
        height="278"
      />
      <WpEmptyState
        v-else-if="!hasData"
        icon="tabler-chart-bar"
        :title="$t('scp.common.no_data')"
        class="procurement-pipeline-spend-chart__empty"
      />
      <VueApexCharts
        v-else
        type="line"
        height="278"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.procurement-pipeline-spend-chart__empty {
  min-block-size: 278px;
}
</style>
