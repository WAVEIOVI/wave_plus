<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

const props = defineProps({
  attendance: {
    type: Object,
    default: () => ({
      present: 0,
      late: 0,
      absent: 0,
      // eslint-disable-next-line camelcase
      on_leave: 0,
      rate: 0,
    }),
  },
  loading: { type: Boolean, default: false },
})

const { t } = useI18n()
const vuetifyTheme = useTheme()

const segments = computed(() => [
  { key: 'present', value: props.attendance.present || 0, color: 'success', label: t('hr.dashboard.present_today') },
  { key: 'late', value: props.attendance.late || 0, color: 'warning', label: t('hr.dashboard.late_arrivals') },
  { key: 'on_leave', value: props.attendance.on_leave || 0, color: 'info', label: t('hr.dashboard.approved_today') },
  { key: 'absent', value: props.attendance.absent || 0, color: 'error', label: t('hr.dashboard.absent_today') },
])

const series = computed(() => segments.value.map(segment => segment.value))
const totalLogged = computed(() => series.value.reduce((sum, value) => sum + value, 0))

const chartOptions = computed(() => {
  const colors = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
    },
    labels: segments.value.map(segment => segment.label),
    colors: segments.value.map(segment => colors[segment.color] || segment.color),
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    legend: { show: false },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            name: { show: false },
            value: { show: false },
            total: {
              show: true,
              showAlways: true,
              label: t('hr.dashboard.logged_today'),
              fontSize: '0.8125rem',
              color: colors['on-surface'],
              formatter: w => String(w.globals.seriesTotals.reduce((sum, value) => sum + value, 0)),
            },
          },
        },
      },
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
    },
  }
})
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-clock" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.today_attendance') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.attendance_rate') }}:
        <span class="text-success font-weight-bold">{{ attendance.rate ?? 0 }}%</span>
      </VCardSubtitle>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="image, list-item-two-line"
        class="bg-transparent"
      />

      <div
        v-else
        class="d-flex align-center justify-space-between flex-wrap gap-4"
      >
        <div
          v-if="totalLogged === 0"
          class="d-flex flex-column align-center justify-center py-6 mx-auto text-medium-emphasis"
          style="inline-size: 180px;"
        >
          <VIcon
            icon="tabler-clock-off"
            size="36"
            class="mb-2 opacity-50"
          />
          <span class="text-body-2">{{ $t('hr.dashboard.no_attendance_data') }}</span>
        </div>

        <div
          v-else
          class="mx-auto mx-sm-0"
        >
          <VueApexCharts
            type="donut"
            height="180"
            width="180"
            :options="chartOptions"
            :series="series"
          />
        </div>

        <div class="d-flex flex-column gap-y-3 flex-grow-1">
          <div
            v-for="segment in segments"
            :key="segment.key"
            class="d-flex align-center justify-space-between"
          >
            <span class="d-flex align-center gap-2">
              <VBadge
                dot
                :color="segment.color"
              />
              <span class="text-body-2">{{ segment.label }}</span>
            </span>
            <span class="font-weight-bold">{{ segment.value }}</span>
          </div>

          <VDivider />

          <div class="d-flex align-center justify-space-between">
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('hr.dashboard.total_logged') }}
            </span>
            <span class="font-weight-bold">{{ totalLogged }}</span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
