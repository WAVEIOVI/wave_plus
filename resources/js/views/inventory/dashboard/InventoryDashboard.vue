<script setup>
import {
  WpEmptyState,
  WpErrorBanner,
  WpKpiCard,
  WpKpiGroup,
  WpPageHeader,
  WpSectionCard,
  WpStatusChip,
} from '@/components/shared'
import { useInventoryDashboard } from '@/composables/inventory/dashboard/useInventoryDashboard'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

const {
  loading,
  error,
  kpiCards,
  conditionDistribution,
  warehouseDistribution,
  recentMovements,
  lowStockAlerts,
  fetchDashboard,
} = useInventoryDashboard()

const navigateToStockItems = () => router.push({ name: 'inventory-stock-items-list' })
const navigateToMovements = () => router.push({ name: 'inventory-movements-list' })
const navigateToSites = () => router.push({ name: 'inventory-warehouse-sites-list' })
</script>

<template>
  <div>
    <!-- Page Header -->
    <WpPageHeader
      :title="t('inventory.dashboard.title')"
      :subtitle="t('inventory.dashboard.subtitle')"
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="fetchDashboard"
        >
          {{ t('Refresh') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-packages"
          @click="navigateToStockItems"
        >
          {{ t('inventory.stock_items.title') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- Error -->
    <WpErrorBanner
      v-if="error"
      :error="error"
      class="mb-6"
      @retry="fetchDashboard"
    />

    <!-- KPI Cards -->
    <WpKpiGroup
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
      :item-count="kpiCards.length"
      class="mb-6"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="card in kpiCards"
          :key="card.id"
          v-bind="getColProps()"
        >
          <WpKpiCard
            :title="card.title"
            :value="card.value"
            :icon="card.icon"
            :loading="loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <VRow>
      <!-- Low Stock Alerts -->
      <VCol
        cols="12"
        md="6"
      >
        <WpSectionCard
          :title="t('inventory.dashboard.low_stock_alerts')"
          icon="tabler-alert-triangle"
          icon-color="warning"
        >
          <template #actions>
            <VBtn
              variant="text"
              size="small"
              @click="navigateToStockItems"
            >
              {{ t('View All') }}
            </VBtn>
          </template>

          <WpEmptyState
            v-if="!loading && lowStockAlerts.length === 0"
            icon="tabler-circle-check"
            :title="t('inventory.dashboard.no_low_stock')"
            color="success"
            size="small"
          />

          <VList
            v-else
            density="compact"
            lines="two"
          >
            <VListItem
              v-for="alert in lowStockAlerts"
              :key="alert.id"
              :title="localizedValue(alert.catalog_item?.name) || alert.catalog_item?.code"
              :subtitle="alert.location?.full_path"
            >
              <template #append>
                <VChip
                  size="x-small"
                  color="warning"
                >
                  {{ alert.qty_on_hand }} / {{ alert.reorder_point }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </WpSectionCard>
      </VCol>

      <!-- Warehouse Distribution -->
      <VCol
        cols="12"
        md="6"
      >
        <WpSectionCard
          :title="t('inventory.dashboard.warehouse_distribution')"
          icon="tabler-chart-pie"
          icon-color="primary"
        >
          <template #actions>
            <VBtn
              variant="text"
              size="small"
              @click="navigateToSites"
            >
              {{ t('View All') }}
            </VBtn>
          </template>

          <WpEmptyState
            v-if="!loading && warehouseDistribution.length === 0"
            icon="tabler-building-warehouse"
            :title="t('inventory.dashboard.no_warehouses')"
            size="small"
          />

          <VList
            v-else
            density="compact"
          >
            <VListItem
              v-for="site in warehouseDistribution"
              :key="site.id"
            >
              <VListItemTitle>{{ localizedValue(site.name) || site.name }}</VListItemTitle>
              <template #append>
                <VChip
                  size="x-small"
                  color="primary"
                >
                  {{ site.stock_items_count }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </WpSectionCard>
      </VCol>

      <!-- Items by Condition -->
      <VCol
        cols="12"
        md="6"
      >
        <WpSectionCard
          :title="t('inventory.dashboard.condition_distribution')"
          icon="tabler-chart-bar"
          icon-color="info"
        >
          <VList density="compact">
            <VListItem
              v-for="cond in conditionDistribution"
              :key="cond.key"
            >
              <VListItemTitle class="d-flex align-center gap-2">
                <WpStatusChip
                  :status="cond.key"
                  :label="cond.label"
                  :color="cond.color"
                  size="x-small"
                />
              </VListItemTitle>
              <template #append>
                <span class="text-body-2 text-medium-emphasis">{{ cond.value }}</span>
              </template>
            </VListItem>
          </VList>
        </WpSectionCard>
      </VCol>

      <!-- Recent Movements -->
      <VCol
        cols="12"
        md="6"
      >
        <WpSectionCard
          :title="t('inventory.dashboard.recent_movements')"
          icon="tabler-activity"
          icon-color="secondary"
        >
          <template #actions>
            <VBtn
              variant="text"
              size="small"
              @click="navigateToMovements"
            >
              {{ t('View All') }}
            </VBtn>
          </template>

          <WpEmptyState
            v-if="!loading && recentMovements.length === 0"
            icon="tabler-activity"
            :title="t('inventory.dashboard.no_recent_movements')"
            size="small"
          />

          <VList
            v-else
            density="compact"
            lines="two"
          >
            <VListItem
              v-for="movement in recentMovements"
              :key="movement.id"
              :subtitle="movement.performed_at"
            >
              <VListItemTitle class="d-flex align-center gap-2">
                <VChip
                  size="x-small"
                  :color="movement.movement_type === 'adjustment' ? 'warning' : movement.movement_type === 'transfer' ? 'info' : 'success'"
                >
                  {{ movement.movement_type }}
                </VChip>
                {{ localizedValue(movement.catalog_item?.name) || movement.catalog_item?.code }}
              </VListItemTitle>
              <template #append>
                <span
                  class="text-body-2 font-weight-medium"
                  :class="movement.quantity < 0 ? 'text-error' : 'text-success'"
                >
                  {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                </span>
              </template>
            </VListItem>
          </VList>
        </WpSectionCard>
      </VCol>
    </VRow>
  </div>
</template>
