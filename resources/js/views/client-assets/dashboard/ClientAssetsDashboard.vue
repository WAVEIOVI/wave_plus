<script setup>
/* eslint-disable camelcase */
import { WpErrorBanner, WpKpiCard, WpKpiGroup, WpPageHeader } from '@/components/shared'
import { useClientAssetsDashboard } from '@/composables/client-assets/dashboard/useClientAssetsDashboard'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay, useTheme } from 'vuetify'

const { t, locale } = useI18n()
const router = useRouter()
const store = useClientAssetStore()
const vuetifyTheme = useTheme()
const { smAndDown } = useDisplay()

const {
  loading,
  error,
  updatedAt,
  kpis,
  equipmentMeta,
  workwearStats,
  recentEquipment,
  fetchDashboardData,
} = useClientAssetsDashboard()

onMounted(() => {
  fetchDashboardData()
})

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')

// ─── Workspace Shortcuts ─────────────────────────────────────
const quickLinks = computed(() => [
  {
    title: t('client_assets.nav.sites', 'Client Sites'),
    description: t('client_assets.dashboard.workspaces.client_sites_desc', 'Explore client buildings, geographic locations, and hierarchical zones.'),
    icon: 'tabler-building-estate',
    color: 'primary',
    to: { name: 'client-assets-sites-list' },
    badge: store.totalSites,
  },
  {
    title: t('client_assets.nav.equipment', 'Equipment Fleet'),
    description: t('client_assets.dashboard.workspaces.equipment_fleet_desc', 'Track operational machines, extinguishers, scheduled tests, and QR codes.'),
    icon: 'tabler-fire-extinguisher',
    color: 'success',
    to: { name: 'client-assets-equipment-list' },
    badge: store.totalEquipment,
  },
  {
    title: t('client_assets.nav.workwear', 'Workwear & PPE'),
    description: t('client_assets.dashboard.workspaces.workwear_desc', 'Manage uniform models, assigned barcodes, sizes, and staff safety gear.'),
    icon: 'tabler-hanger',
    color: 'info',
    to: { name: 'client-assets-workwear-list' },
    badge: store.totalWorkwearItems,
  },
])

// ─── Donut Chart — Equipment Status & Compliance ─────────────
const equipmentDonutSeries = computed(() => {
  const op = equipmentMeta.value?.operational_count ?? 0
  const maint = equipmentMeta.value?.maintenance_count ?? 0
  const overdue = equipmentMeta.value?.overdue_count ?? 0
  const unassigned = equipmentMeta.value?.without_site ?? 0

  return [op, maint, overdue, unassigned]
})

const equipmentDonutTotal = computed(() =>
  equipmentDonutSeries.value.reduce((a, b) => a + Number(b || 0), 0),
)

const equipmentDonutOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
    },
    labels: [
      t('client_assets.dashboard.status_operational', 'Operational'),
      t('client_assets.dashboard.status_maintenance', 'Maintenance'),
      t('client_assets.dashboard.status_overdue', 'Overdue Compliance'),
      t('client_assets.dashboard.status_unassigned', 'Unassigned'),
    ],
    colors: [
      currentTheme.success,
      currentTheme.warning,
      currentTheme.error,
      currentTheme.info,
    ],
    legend: {
      position: 'bottom',
      labels: { colors: currentTheme['on-surface'] },
      markers: { offsetX: -3 },
      itemMargin: { horizontal: 10, vertical: 5 },
    },
    dataLabels: {
      enabled: true,
      formatter: val => (isNaN(val) ? '0%' : `${Math.round(val)}%`),
      style: { fontSize: '12px' },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            total: {
              show: true,
              label: t('client_assets.dashboard.total_equipment', 'Total Equipment'),
              color: currentTheme['on-surface'],
              formatter: () => `${equipmentDonutTotal.value}`,
            },
          },
        },
      },
    },
    stroke: { width: 0 },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
    },
  }
})

// ─── Bar Chart — Workwear Stock & Fleet Distribution ─────────
const workwearBarCategories = computed(() => [
  t('client_assets.dashboard.stock_in_stock', 'In Stock'),
  t('client_assets.dashboard.stock_assigned', 'Assigned'),
  t('client_assets.dashboard.stock_available', 'Available'),
  t('client_assets.dashboard.stock_repair', 'Repair Needed'),
  t('client_assets.dashboard.stock_retired', 'Retired / Lost'),
])

const workwearBarSeries = computed(() => {
  const ws = workwearStats.value

  const dataPoints = [
    Number(ws?.in_stock ?? 0),
    Number(ws?.assigned ?? 0),
    Number(ws?.available ?? 0),
    Number(ws?.repair_needed ?? 0),
    Number((ws?.retired ?? 0) + (ws?.lost_or_stolen ?? 0)),
  ]

  return [{
    name: t('client_assets.dashboard.total_workwear', 'Workwear Items'),
    data: dataPoints,
  }]
})

const workwearBarTotal = computed(() => {
  const seriesData = workwearBarSeries.value[0]?.data || []

  return seriesData.reduce((acc, val) => acc + Number(val || 0), 0)
})

const workwearBarOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'bar',
      parentHeightOffset: 0,
      toolbar: { show: false },
    },
    plotOptions: {
      bar: {
        borderRadius: 6,
        columnWidth: '45%',
        distributed: true,
      },
    },
    colors: [
      currentTheme.primary,
      currentTheme.success,
      currentTheme.info,
      currentTheme.warning,
      currentTheme.error,
    ],
    dataLabels: { enabled: false },
    legend: { show: false },
    xaxis: {
      categories: workwearBarCategories.value,
      labels: {
        style: {
          colors: currentTheme['on-surface'],
          fontSize: '12px',
        },
        rotate: -20,
        rotateAlways: false,
        trim: true,
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: { colors: currentTheme['on-surface'], fontSize: '13px' },
      },
    },
    grid: {
      borderColor: currentTheme['grey-200'] ?? 'rgba(var(--v-theme-on-surface), 0.12)',
      strokeDashArray: 4,
      padding: { top: -10, bottom: 0 },
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
    },
  }
})

// ─── Status Color Resolvers ──────────────────────────────────
const resolveComplianceColor = status => {
  if (status === 'compliant') return 'success'
  if (status === 'overdue') return 'error'
  if (status === 'pending') return 'warning'

  return 'secondary'
}

const resolveLifecycleColor = status => {
  if (status === 'operational' || status === 'active') return 'success'
  if (status === 'under_maintenance' || status === 'in_service') return 'warning'
  if (status === 'decommissioned' || status === 'retired') return 'secondary'

  return 'primary'
}

const formatDate = val => {
  if (!val) return '—'

  try {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(val))
  } catch {
    return val
  }
}
</script>

<template>
  <div class="client-assets-dashboard d-flex flex-column gap-6">
    <!-- ─── Page Header ─────────────────────────────────────── -->
    <WpPageHeader
      :title="$t('client_assets.dashboard.title', 'Client Assets Dashboard')"
      :subtitle="$t('client_assets.dashboard.subtitle', 'Unified management workspace for client sites, field equipment, compliance, and staff workwear.')"
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <span
          v-if="lastUpdated"
          class="text-caption text-medium-emphasis me-sm-2"
        >
          {{ $t('bp_dashboard.last_updated', { date: lastUpdated }) }}
        </span>
        <VBtn
          color="primary"
          variant="tonal"
          :loading="loading"
          prepend-icon="tabler-refresh"
          @click="fetchDashboardData"
        >
          {{ $t('common.refresh', 'Refresh') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- ─── Error Banner ────────────────────────────────────── -->
    <WpErrorBanner
      :error="error"
      @retry="fetchDashboardData"
    />

    <!-- ─── KPI Cards Row ───────────────────────────────────── -->
    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.key"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :subtitle="kpi.subtitle"
            :loading="loading"
            clickable
            :to="kpi.to"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- ─── Charts Row ──────────────────────────────────────── -->
    <VRow>
      <!-- Donut Chart: Equipment Status & Compliance -->
      <VCol
        cols="12"
        md="5"
      >
        <VCard
          border
          elevation="0"
          rounded="lg"
          class="h-100 dashboard-card-hover"
        >
          <VCardItem class="pa-5 pb-2">
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <VIcon icon="tabler-chart-donut-2" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              {{ $t('client_assets.dashboard.equipment_status_title', 'Equipment Status & Compliance') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('client_assets.dashboard.equipment_status_sub', 'Operational readiness and compliance health') }}
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VSkeletonLoader
              v-if="loading"
              type="image"
              height="320"
              class="rounded-lg"
            />
            <div
              v-else-if="equipmentDonutTotal === 0"
              class="d-flex flex-column align-center justify-center py-10"
              style="block-size: 320px;"
            >
              <VIcon
                icon="tabler-fire-extinguisher"
                size="48"
                color="disabled"
                class="mb-2 opacity-50"
              />
              <span class="text-disabled">{{ $t('client_assets.dashboard.no_equipment', 'No equipment units recorded yet') }}</span>
            </div>
            <VueApexCharts
              v-else
              type="donut"
              :options="equipmentDonutOptions"
              :series="equipmentDonutSeries"
              height="320"
            />
          </VCardText>
        </VCard>
      </VCol>

      <!-- Bar Chart: Workwear Inventory & Lifecycle -->
      <VCol
        cols="12"
        md="7"
      >
        <VCard
          border
          elevation="0"
          rounded="lg"
          class="h-100 dashboard-card-hover"
        >
          <VCardItem class="pa-5 pb-2">
            <template #prepend>
              <VAvatar
                color="info"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <VIcon icon="tabler-chart-bar" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              {{ $t('client_assets.dashboard.workwear_distribution_title', 'Workwear Inventory & Lifecycle') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('client_assets.dashboard.workwear_distribution_sub', 'Distribution across stock, active assignments, and service') }}
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VSkeletonLoader
              v-if="loading"
              type="image"
              height="320"
              class="rounded-lg"
            />
            <div
              v-else-if="workwearBarTotal === 0"
              class="d-flex flex-column align-center justify-center py-10"
              style="block-size: 320px;"
            >
              <VIcon
                icon="tabler-hanger"
                size="48"
                color="disabled"
                class="mb-2 opacity-50"
              />
              <span class="text-disabled">{{ $t('client_assets.dashboard.no_workwear', 'No workwear items recorded yet') }}</span>
            </div>
            <VueApexCharts
              v-else
              type="bar"
              :options="workwearBarOptions"
              :series="workwearBarSeries"
              height="320"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Operations Workspaces Grid ──────────────────────── -->
    <VRow>
      <VCol
        v-for="item in quickLinks"
        :key="item.title"
        cols="12"
        md="4"
      >
        <VCard
          border
          elevation="0"
          rounded="lg"
          class="h-100 dashboard-card-hover cursor-pointer"
          @click="router.push(item.to)"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                :color="item.color"
                variant="tonal"
                rounded="lg"
                size="44"
              >
                <VIcon
                  :icon="item.icon"
                  size="24"
                />
              </VAvatar>
            </template>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span class="font-weight-bold">{{ item.title }}</span>
              <VChip
                v-if="item.badge !== undefined"
                :color="item.color"
                size="small"
                variant="tonal"
                rounded="pill"
              >
                {{ item.badge }}
              </VChip>
            </VCardTitle>
            <VCardSubtitle class="text-wrap mt-1">
              {{ item.description }}
            </VCardSubtitle>
          </VCardItem>

          <VCardActions class="pt-0 px-4 pb-4">
            <VBtn
              variant="text"
              :color="item.color"
              append-icon="tabler-arrow-right"
              class="px-0 font-weight-semibold"
            >
              {{ $t('client_assets.dashboard.workspaces.open_workspace', 'Open Workspace') }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Recent Equipment Activity ───────────────────────── -->
    <VRow>
      <VCol cols="12">
        <VCard
          border
          elevation="0"
          rounded="lg"
          class="dashboard-card-hover"
        >
          <VCardItem class="pa-5 pb-2">
            <template #prepend>
              <VAvatar
                color="secondary"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <VIcon icon="tabler-clock" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              {{ $t('client_assets.dashboard.recent_equipment_title', 'Recent Equipment Activity') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('client_assets.dashboard.recent_equipment_sub', 'Latest field assets deployed, tested, or inspected') }}
            </VCardSubtitle>
            <template #append>
              <VBtn
                variant="text"
                size="small"
                :to="{ name: 'client-assets-equipment-list' }"
              >
                {{ $t('client_assets.dashboard.view_all', 'View All') }}
              </VBtn>
            </template>
          </VCardItem>

          <VDivider />

          <!-- Loading Skeleton -->
          <div
            v-if="loading"
            class="pa-4"
          >
            <VSkeletonLoader type="list-item-avatar-two-line@4" />
          </div>

          <!-- Empty State -->
          <div
            v-else-if="recentEquipment.length === 0"
            class="d-flex flex-column align-center py-8"
          >
            <VIcon
              icon="tabler-database-off"
              size="48"
              color="disabled"
              class="mb-3 opacity-50"
            />
            <span class="text-body-1 text-disabled">{{ $t('client_assets.dashboard.no_equipment', 'No equipment units recorded yet') }}</span>
          </div>

          <!-- Mobile View -->
          <VList
            v-else-if="smAndDown"
            lines="two"
            class="py-0"
          >
            <template
              v-for="(item, idx) in recentEquipment"
              :key="item.id"
            >
              <VListItem class="py-3 px-4">
                <template #prepend>
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="40"
                    rounded="lg"
                    class="me-3"
                  >
                    <VIcon
                      icon="tabler-fire-extinguisher"
                      size="22"
                    />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-medium text-high-emphasis text-truncate mb-1">
                  {{ item.equipment_code || item.name || '—' }}
                </VListItemTitle>

                <VListItemSubtitle class="d-flex align-center gap-2 flex-wrap text-caption">
                  <span class="text-medium-emphasis">{{ item.site?.name || item.client?.name || '—' }}</span>
                  <VChip
                    :color="resolveComplianceColor(item.compliance_status)"
                    size="x-small"
                    variant="tonal"
                    label
                  >
                    {{ item.compliance_status || 'unknown' }}
                  </VChip>
                </VListItemSubtitle>

                <template #append>
                  <VBtn
                    icon="tabler-chevron-right"
                    variant="text"
                    size="small"
                    color="medium-emphasis"
                    :to="{ name: 'client-assets-equipment-edit-id', params: { id: item.id } }"
                  />
                </template>
              </VListItem>
              <VDivider v-if="idx < recentEquipment.length - 1" />
            </template>
          </VList>

          <!-- Desktop Table View -->
          <VTable
            v-else
            class="text-no-wrap"
          >
            <thead>
              <tr>
                <th class="text-uppercase text-caption font-weight-bold">
                  {{ $t('client_assets.common.equipment', 'Equipment') }}
                </th>
                <th class="text-uppercase text-caption font-weight-bold">
                  {{ $t('client_assets.common.site', 'Site & Location') }}
                </th>
                <th class="text-uppercase text-caption font-weight-bold">
                  {{ $t('client_assets.common.compliance_status', 'Compliance') }}
                </th>
                <th class="text-uppercase text-caption font-weight-bold">
                  {{ $t('client_assets.common.lifecycle_status', 'Lifecycle') }}
                </th>
                <th class="text-uppercase text-caption font-weight-bold">
                  {{ $t('client_assets.common.updated_at', 'Updated At') }}
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-end">
                  {{ $t('client_assets.common.actions', 'Action') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in recentEquipment"
                :key="item.id"
              >
                <!-- Equipment Code & Blueprint -->
                <td>
                  <div class="d-flex align-center py-2">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="36"
                      rounded="lg"
                      class="me-3"
                    >
                      <VIcon
                        icon="tabler-fire-extinguisher"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <RouterLink
                        :to="{ name: 'client-assets-equipment-edit-id', params: { id: item.id } }"
                        class="text-body-2 font-weight-semibold text-high-emphasis d-block"
                      >
                        {{ item.equipment_code || '—' }}
                      </RouterLink>
                      <span class="text-caption text-medium-emphasis">
                        {{ localizedValue(item.blueprint?.name) || localizedValue(item.equipment_class?.name) || localizedValue(item.equipmentClass?.name) || $t('client_assets.common.standard_unit', 'Standard Unit') }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Site / Client -->
                <td>
                  <div class="text-body-2 font-weight-medium">
                    {{ item.site?.name || '—' }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ item.client?.name || (item.area ? item.area.name : '') }}
                  </div>
                </td>

                <!-- Compliance -->
                <td>
                  <VChip
                    :color="resolveComplianceColor(item.compliance_status)"
                    size="small"
                    variant="tonal"
                    label
                    class="text-capitalize"
                  >
                    {{ item.compliance_status || 'unknown' }}
                  </VChip>
                </td>

                <!-- Lifecycle -->
                <td>
                  <VChip
                    :color="resolveLifecycleColor(item.lifecycle_status)"
                    size="small"
                    variant="tonal"
                    label
                    class="text-capitalize"
                  >
                    {{ (item.lifecycle_status || 'operational').replace('_', ' ') }}
                  </VChip>
                </td>

                <!-- Updated At -->
                <td class="text-body-2 text-medium-emphasis">
                  {{ formatDate(item.updated_at) }}
                </td>

                <!-- Actions -->
                <td class="text-end">
                  <VBtn
                    icon="tabler-pencil"
                    variant="text"
                    size="small"
                    color="primary"
                    :to="{ name: 'client-assets-equipment-edit-id', params: { id: item.id } }"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-card-hover {
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 8%);
    transform: translateY(-2px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .dashboard-card-hover {
    transform: none !important;
    transition: none !important;
  }
}
</style>
