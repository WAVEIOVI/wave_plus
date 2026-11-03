<script setup>
import { WpEmptyState } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

const props = defineProps({
  timeSeries: {
    type: Object,
    default: () => ({ labels: [], series: [] }),
  },
  loading: Boolean,
})

const { t } = useI18n()
const theme = useTheme()

const values = computed(() => props.timeSeries?.series ?? [])
const total = computed(() => values.value.reduce((sum, value) => sum + Number(value || 0), 0))
const hasData = computed(() => values.value.some(value => Number(value) > 0))
const series = computed(() => [{ name: t('bp_dashboard.charts.partners'), data: values.value }])

const chartOptions = computed(() => {
  const colors = theme.current.value.colors
  const onSurface = colors['on-surface']

  return {
    chart: { type: 'area', toolbar: { show: false }, parentHeightOffset: 0, zoom: { enabled: false } },
    colors: [colors.primary],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.04, stops: [0, 90, 100] } },
    grid: { borderColor: 'rgba(var(--v-theme-on-surface), 0.12)', strokeDashArray: 4, padding: { left: 4, right: 8 } },
    xaxis: {
      categories: props.timeSeries?.labels ?? [],
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: onSurface } },
    },
    yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: onSurface } } },
    tooltip: {
      theme: theme.current.value.dark ? 'dark' : 'light',
      y: { formatter: value => `${value} ${t('bp_dashboard.charts.partners')}` },
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
          <VIcon icon="tabler-chart-line" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('bp_dashboard.charts.onboarding_series_title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('bp_dashboard.charts.onboarding_subtitle') }}</VCardSubtitle>
      <template #append>
        <div class="text-end">
          <p class="text-h6 font-weight-bold mb-0">
            {{ total }}
          </p>
          <span class="text-caption text-medium-emphasis">{{ $t('bp_dashboard.charts.last_six_months') }}</span>
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
        icon="tabler-chart-line"
        :title="$t('bp_dashboard.charts.no_data')"
        class="business-partner-growth-card__empty"
      />
      <VueApexCharts
        v-else
        type="area"
        height="278"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.business-partner-growth-card__empty {
  min-block-size: 278px;
}
</style>
