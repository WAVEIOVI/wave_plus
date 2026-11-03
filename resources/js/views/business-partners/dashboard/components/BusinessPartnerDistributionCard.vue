<script setup>
import { WpEmptyState } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

const props = defineProps({
  kpis: { type: Object, default: () => ({}) },
  distribution: { type: Object, default: () => ({}) },
  loading: Boolean,
})

const { t } = useI18n()
const theme = useTheme()

const definitions = [
  { key: 'clients', color: 'primary' },
  { key: 'suppliers', color: 'success' },
  { key: 'service_providers', color: 'warning' },
  { key: 'organisations', color: 'info' },
]

const items = computed(() => definitions.map(item => ({
  ...item,
  label: t(`bp_dashboard.kpi.${item.key}`),
  count: Number(props.kpis?.[item.key]?.count ?? 0),
  percent: Number(props.distribution?.[item.key] ?? 0),
})))

const total = computed(() => items.value.reduce((sum, item) => sum + item.count, 0))
const hasData = computed(() => total.value > 0)
const series = computed(() => items.value.map(item => item.count))

const chartOptions = computed(() => {
  const colors = theme.current.value.colors

  return {
    chart: { type: 'donut', sparkline: { enabled: true } },
    labels: items.value.map(item => item.label),
    colors: items.value.map(item => colors[item.color]),
    stroke: { width: 0 },
    legend: { show: false },
    dataLabels: { enabled: false },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            total: {
              show: true,
              label: t('bp_dashboard.charts.total'),
              color: colors['on-surface'],
              formatter: () => total.value,
            },
          },
        },
      },
    },
    tooltip: { theme: theme.current.value.dark ? 'dark' : 'light', y: { formatter: value => String(value) } },
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
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-chart-donut" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('bp_dashboard.charts.distribution_title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('bp_dashboard.charts.distribution_subtitle') }}</VCardSubtitle>
    </VCardItem>
    <VCardText class="pt-0">
      <VSkeletonLoader
        v-if="loading"
        type="image"
        height="190"
      />
      <WpEmptyState
        v-else-if="!hasData"
        icon="tabler-chart-donut"
        :title="$t('bp_dashboard.charts.no_data')"
      />
      <template v-else>
        <VueApexCharts
          type="donut"
          height="190"
          :options="chartOptions"
          :series="series"
        />
        <VList
          density="compact"
          class="pa-0 mt-2 bg-transparent"
        >
          <VListItem
            v-for="item in items"
            :key="item.key"
            class="px-0"
          >
            <template #prepend>
              <span
                class="distribution-dot me-3"
                :style="{ backgroundColor: `rgb(var(--v-theme-${item.color}))` }"
              />
            </template>
            <VListItemTitle class="text-body-2">
              {{ item.label }}
            </VListItemTitle>
            <template #append>
              <span class="text-caption font-weight-bold">{{ item.count }} · {{ item.percent }}%</span>
            </template>
          </VListItem>
        </VList>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.distribution-dot { display: inline-block; border-radius: 50%; block-size: 8px; inline-size: 8px; }
</style>
