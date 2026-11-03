<script setup>
/* eslint-disable camelcase */
import { WpErrorBanner, WpKpiCard, WpKpiGroup, WpPageHeader } from '@/components/shared'
import { useCatalogDashboard } from '@/views/catalog/dashboard/useCatalogDashboard'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay, useTheme } from 'vuetify'

const { t, locale } = useI18n()
const vuetifyTheme = useTheme()
const { smAndDown } = useDisplay()
const router = useRouter()
const { data, loading, error, updatedAt, fetchDashboard } = useCatalogDashboard()

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')

onMounted(() => {
  fetchDashboard()
})

// ─── KPI Cards Config ────────────────────────────────────────
const kpiCards = computed(() => {
  const k = data.value?.kpis || {}

  const definitions = [
    {
      key: 'catalog_items',
      title: t('catalog.items.list.kpis.total_items', 'Catalog Items'),
      value: k.catalog_items?.count ?? 0,
      growth: Number(k.catalog_items?.growth ?? 0),
      trend: k.catalog_items?.trend ?? 'up',
      icon: 'tabler-box',
      color: 'primary',
      route: { name: 'catalog-items-list' },
    },
    {
      key: 'blueprints',
      title: t('catalog.nav.blueprints', 'Equipment Blueprints'),
      value: k.blueprints?.count ?? 0,
      growth: Number(k.blueprints?.growth ?? 0),
      trend: k.blueprints?.trend ?? 'up',
      icon: 'tabler-file-settings',
      color: 'info',
      route: { name: 'catalog-blueprints-list' },
    },
    {
      key: 'guidelines',
      title: t('catalog.nav.guidelines', 'Guidelines'),
      value: k.guidelines?.count ?? 0,
      growth: Number(k.guidelines?.growth ?? 0),
      trend: k.guidelines?.trend ?? 'up',
      icon: 'tabler-list-check',
      color: 'success',
      route: { name: 'catalog-guidelines-list' },
    },
    {
      key: 'categories',
      title: t('catalog.items.list.filter.category', 'Categories'),
      value: k.categories?.count ?? 0,
      growth: Number(k.categories?.growth ?? 0),
      trend: k.categories?.trend ?? 'up',
      icon: 'tabler-category',
      color: 'warning',
      route: { name: 'settings-administration-catalog' },
    },
  ]

  return definitions.map(def => ({
    ...def,
    trend: {
      direction: def.trend === 'down' ? 'down' : 'up',
      value: `${def.growth > 0 ? '+' : ''}${def.growth}%`,
      label: 'vs last month',
    },
  }))
})

// ─── Donut Chart — Items by Type ─────────────────────────────
const donutSeries = computed(() => {
  return data.value?.items_by_type?.series || []
})

const donutTotal = computed(() => donutSeries.value.reduce((a, b) => a + b, 0))

const donutChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
    },
    labels: data.value?.items_by_type?.labels || [],
    colors: [
      currentTheme.primary,
      currentTheme.warning,
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
      formatter: val => {
        if (isNaN(val)) return '0%'

        return `${Math.round(val)}%`
      },
      style: {
        fontSize: '12px',
      },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'Total Items',
              color: currentTheme['on-surface'],
            },
          },
        },
      },
    },
    stroke: { width: 0 },
    responsive: [
      {
        breakpoint: 600,
        options: {
          chart: { height: 300 },
          legend: { position: 'bottom' },
        },
      },
    ],
  }
})

// ─── Bar Chart — Blueprints by Category ──────────────────────
const barSeries = computed(() => {
  return [{
    name: 'Blueprints',
    data: data.value?.blueprints_by_category?.series || [],
  }]
})

const barChartOptions = computed(() => {
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
      currentTheme.info,
      currentTheme.success,
      currentTheme.warning,
      currentTheme.error,
      '#9c27b0',
      '#00bcd4',
      '#795548',
    ],
    dataLabels: { enabled: false },
    legend: { show: false },
    xaxis: {
      categories: data.value?.blueprints_by_category?.labels || [],
      labels: {
        style: {
          colors: currentTheme['on-surface'],
          fontSize: '12px',
        },
        rotate: -30,
        rotateAlways: false,
        trim: true,
        maxHeight: 80,
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
      borderColor: currentTheme['grey-200'] ?? '#EEEEEE',
      strokeDashArray: 4,
      padding: { top: -10, bottom: 0 },
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
    },
    noData: {
      text: 'No blueprint data',
      align: 'center',
      verticalAlign: 'middle',
    },
  }
})

// ─── Recent Activity Table ───────────────────────────────────
const recentHeaders = [
  { title: 'Name', key: 'name', minWidth: '200px' },
  { title: 'Type', key: 'source', width: '130px' },
  { title: 'Subtype', key: 'sub_type', width: '130px' },
  { title: 'Status', key: 'status', width: '120px' },
  { title: 'Updated', key: 'updated_at', width: '140px' },
  { title: 'Action', key: 'actions', sortable: false, width: '80px' },
]

const recentItems = computed(() => {
  return (data.value?.recent_activity || []).map(item => ({
    ...item,
    formattedDate: item.updated_at
      ? new Date(item.updated_at).toLocaleDateString()
      : '—',
  }))
})

const sourceChipColor = source => {
  return source === 'blueprint' ? 'info' : 'primary'
}

const sourceLabel = source => {
  return source === 'blueprint' ? 'Blueprint' : 'Catalog Item'
}

const subtypeLabel = subType => {
  const map = {
    product: 'Product',
    consumable: 'Consumable',
    eq_servicing: 'Servicing',
  }

  return map[subType] ?? subType ?? '—'
}

const subtypeChipColor = subType => {
  const map = {
    product: 'primary',
    consumable: 'warning',
    eq_servicing: 'info',
  }

  return map[subType] ?? 'secondary'
}

const statusChipColor = status => {
  const map = {
    active: 'success',
    draft: 'warning',
    inactive: 'secondary',
    discontinued: 'error',
    archived: 'secondary',
  }

  return map[status] ?? 'secondary'
}

const editItem = item => {
  if (item.source === 'blueprint') {
    router.push({ name: 'catalog-blueprints-edit-id', params: { id: item.id } })
  }
  else {
    router.push({ name: 'catalog-items-edit-id', params: { id: item.id } })
  }
}

// ─── Quick Actions ───────────────────────────────────────────
const isFilterDialogOpen = ref(false)

const navigateToList = type => {
  isFilterDialogOpen.value = false

  const query = type === 'all' ? {} : { item_type: type }

  router.push({ name: 'catalog-items-list', query })
}
</script>

<template>
  <div class="catalog-dashboard d-flex flex-column gap-6">
    <!-- ─── Page Title & Actions ─────────────────────────────── -->
    <WpPageHeader
      :title="$t('catalog.dashboard.title', 'Catalog Dashboard')"
      :subtitle="$t('catalog.dashboard.subtitle', 'Overview of your products, blueprints, and guidelines.')"
      icon="tabler-box"
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
          @click="fetchDashboard"
        >
          {{ $t('common.refresh', 'Refresh') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- ─── Error Banner ────────────────────────────────────── -->
    <WpErrorBanner
      :error="error"
      @retry="fetchDashboard"
    />

    <!-- ─── KPI Cards ───────────────────────────────────────── -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(card, index) in kpiCards"
          :key="card.key"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="card.title"
            :value="card.value"
            :icon="card.icon"
            :icon-color="card.color"
            :trend="card.trend"
            :loading="loading"
            clickable
            :to="card.route"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- ─── Charts Row ──────────────────────────────────────── -->
    <VRow>
      <!-- Donut Chart (Items by Type) -->
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
                <VIcon icon="tabler-chart-pie-2" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              {{ $t('catalog.dashboard.items_by_type', 'Catalog Items by Type') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('catalog.dashboard.items_by_type_sub', 'Distribution of your catalog inventory') }}
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
              v-else-if="donutTotal === 0"
              class="d-flex flex-column align-center justify-center py-10"
              style="block-size: 320px;"
            >
              <VIcon
                icon="tabler-chart-pie-2"
                size="48"
                color="disabled"
                class="mb-2 opacity-50"
              />
              <span class="text-disabled">{{ $t('catalog.dashboard.no_data', 'No catalog items yet') }}</span>
            </div>
            <VueApexCharts
              v-else
              type="donut"
              :options="donutChartOptions"
              :series="donutSeries"
              height="320"
            />
          </VCardText>
        </VCard>
      </VCol>

      <!-- Bar Chart (Blueprints by Category) -->
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
              {{ $t('catalog.dashboard.blueprints_by_category', 'Equipment Blueprints by Category') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('catalog.dashboard.blueprints_by_category_sub', 'Blueprint distribution across categories') }}
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VSkeletonLoader
              v-if="loading"
              type="image"
              height="320"
              class="rounded-lg"
            />
            <VueApexCharts
              v-else
              type="bar"
              :options="barChartOptions"
              :series="barSeries"
              height="320"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Recent Activity Card ────────────────────────────── -->
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
              {{ $t('catalog.dashboard.recent_activity', 'Recent Activity') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('catalog.dashboard.recent_activity_sub', 'Last 5 updated catalog items and equipment blueprints') }}
            </VCardSubtitle>
            <template #append>
              <VBtn
                variant="text"
                size="small"
                :to="{ name: 'catalog-items-list' }"
              >
                {{ $t('hr.dashboard.view_all', 'View All') }}
              </VBtn>
            </template>
          </VCardItem>

          <VDivider />

          <!-- Loading Skeleton inside card -->
          <div
            v-if="loading"
            class="pa-4"
          >
            <VSkeletonLoader type="list-item-avatar-two-line@4" />
          </div>

          <!-- Empty state -->
          <div
            v-else-if="recentItems.length === 0"
            class="d-flex flex-column align-center py-8"
          >
            <VIcon
              icon="tabler-database-off"
              size="48"
              color="disabled"
              class="mb-3"
            />
            <span class="text-body-1 text-disabled">No recent activity</span>
          </div>

          <!-- Mobile view: List layout (smAndDown) -->
          <VList
            v-else-if="smAndDown"
            lines="two"
            class="py-0"
          >
            <template
              v-for="(item, idx) in recentItems"
              :key="item.id"
            >
              <VListItem class="py-3 px-4">
                <template #prepend>
                  <VAvatar
                    :color="sourceChipColor(item.source)"
                    variant="tonal"
                    size="40"
                    rounded
                    class="me-3"
                  >
                    <VIcon
                      :icon="item.source === 'blueprint' ? 'tabler-file-settings' : 'tabler-box'"
                      size="22"
                    />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-medium text-high-emphasis text-truncate mb-1">
                  {{ item.name || '—' }}
                </VListItemTitle>

                <VListItemSubtitle class="d-flex align-center gap-2 flex-wrap text-caption">
                  <span class="font-mono text-medium-emphasis">{{ item.code || '—' }}</span>
                  <VChip
                    :color="sourceChipColor(item.source)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ sourceLabel(item.source) }}
                  </VChip>
                  <VChip
                    :color="subtypeChipColor(item.sub_type)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ subtypeLabel(item.sub_type) }}
                  </VChip>
                  <VChip
                    :color="statusChipColor(item.status)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : '—' }}
                  </VChip>
                  <span class="text-disabled ms-auto">{{ item.formattedDate }}</span>
                </VListItemSubtitle>

                <template #append>
                  <VBtn
                    icon="tabler-pencil"
                    variant="text"
                    size="small"
                    color="primary"
                    :aria-label="`Edit ${item.name || ''}`"
                    @click="editItem(item)"
                  />
                </template>
              </VListItem>
              <VDivider v-if="idx < recentItems.length - 1" />
            </template>
          </VList>

          <!-- Desktop view: Data Table (mdAndUp) -->
          <VDataTable
            v-else
            :headers="recentHeaders"
            :items="recentItems"
            :items-per-page="-1"
            hide-default-footer
            density="comfortable"
            class="text-no-wrap"
          >
            <!-- Name Column -->
            <template #item.name="{ item }">
              <div class="d-flex align-center py-2">
                <VAvatar
                  :color="sourceChipColor(item.source)"
                  variant="tonal"
                  size="38"
                  class="me-3"
                >
                  <VIcon
                    :icon="item.source === 'blueprint' ? 'tabler-file-settings' : 'tabler-box'"
                    size="20"
                  />
                </VAvatar>

                <div class="d-flex flex-column">
                  <span class="d-block font-weight-medium text-truncate text-high-emphasis">
                    {{ item.name || '—' }}
                  </span>
                  <span class="text-xs text-medium-emphasis">{{ item.code || '—' }}</span>
                </div>
              </div>
            </template>

            <!-- Type Column -->
            <template #item.source="{ item }">
              <VChip
                :color="sourceChipColor(item.source)"
                size="small"
                variant="tonal"
              >
                {{ sourceLabel(item.source) }}
              </VChip>
            </template>

            <!-- Subtype Column -->
            <template #item.sub_type="{ item }">
              <VChip
                :color="subtypeChipColor(item.sub_type)"
                size="small"
                variant="tonal"
              >
                {{ subtypeLabel(item.sub_type) }}
              </VChip>
            </template>

            <!-- Status Column -->
            <template #item.status="{ item }">
              <VChip
                :color="statusChipColor(item.status)"
                size="small"
                variant="tonal"
              >
                {{ item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : '—' }}
              </VChip>
            </template>

            <!-- Date Column -->
            <template #item.updated_at="{ item }">
              <span class="text-medium-emphasis">
                {{ item.formattedDate }}
              </span>
            </template>

            <!-- Actions Column -->
            <template #item.actions="{ item }">
              <VBtn
                icon
                variant="text"
                size="small"
                color="primary"
                @click="editItem(item)"
              >
                <VIcon
                  icon="tabler-pencil"
                  size="18"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Edit
                </VTooltip>
              </VBtn>
            </template>
          </VDataTable>
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 Catalog Filter Dialog (Quick Add) -->
    <VDialog
      v-model="isFilterDialogOpen"
      max-width="800"
    >
      <VCard>
        <VCardItem class="pb-1 text-center">
          <template #title>
            <h5 class="text-h5 font-weight-medium mb-1">
              Select Item Type
            </h5>
          </template>
          <template #subtitle>
            What type of item would you like to create?
          </template>

          <VBtn
            icon
            variant="text"
            size="small"
            class="dialog-close-btn bg-surface"
            @click="isFilterDialogOpen = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardItem>

        <VCardText class="pt-6">
          <VRow>
            <!-- All Items option -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="h-100 text-center cursor-pointer border rounded elevation-0 hover-card"
                @click="navigateToList('all')"
              >
                <VCardText class="d-flex flex-column align-center justify-center pa-6">
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="64"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-packages"
                      size="32"
                    />
                  </VAvatar>
                  <h6 class="text-h6 font-weight-medium mb-1">
                    All Items
                  </h6>
                  <span class="text-body-2 text-disabled text-center">View entire catalog collection.</span>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Product option -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="h-100 text-center cursor-pointer border rounded elevation-0 hover-card"
                @click="navigateToList('product')"
              >
                <VCardText class="d-flex flex-column align-center justify-center pa-6">
                  <VAvatar
                    color="info"
                    variant="tonal"
                    size="64"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-box"
                      size="32"
                    />
                  </VAvatar>
                  <h6 class="text-h6 font-weight-medium mb-1">
                    Product
                  </h6>
                  <span class="text-body-2 text-disabled text-center">Physical goods that are bought and sold.</span>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Consumable option -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="h-100 text-center cursor-pointer border rounded elevation-0 hover-card"
                @click="navigateToList('consumable')"
              >
                <VCardText class="d-flex flex-column align-center justify-center pa-6">
                  <VAvatar
                    color="warning"
                    variant="tonal"
                    size="64"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-flame"
                      size="32"
                    />
                  </VAvatar>
                  <h6 class="text-h6 font-weight-medium mb-1">
                    Consumable
                  </h6>
                  <span class="text-body-2 text-disabled text-center">Items consumed during service delivery.</span>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Eq Servicing option -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="h-100 text-center cursor-pointer border rounded elevation-0 hover-card"
                @click="navigateToList('eq_servicing')"
              >
                <VCardText class="d-flex flex-column align-center justify-center pa-6">
                  <VAvatar
                    color="secondary"
                    variant="tonal"
                    size="64"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-tools"
                      size="32"
                    />
                  </VAvatar>
                  <h6 class="text-h6 font-weight-medium mb-1">
                    Eq Servicing
                  </h6>
                  <span class="text-body-2 text-disabled text-center">Maintenance procedures and repairs.</span>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>
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

.hover-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  transition: all 0.2s ease-in-out;

  &:hover {
    border-color: rgb(var(--v-theme-primary)) !important;
    background-color: rgba(var(--v-theme-primary), 0.04) !important;
    transform: translateY(-2px);
  }
}

.dialog-close-btn {
  position: absolute;
  z-index: 10;
  inset-block-start: 1rem;
  inset-inline-end: 1rem;
}

@media (prefers-reduced-motion: reduce) {
  .dashboard-card-hover,
  .hover-card {
    transform: none !important;
    transition: none !important;
  }
}
</style>
