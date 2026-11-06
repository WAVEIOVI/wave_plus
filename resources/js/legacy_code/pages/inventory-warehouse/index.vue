<script setup>
import { WpKpiCard, WpPageHeader } from '@/components/shared'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  name: 'inventory-warehouse',
  meta: {
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
})

const router = useRouter()
const inventoryStore = useInventoryStore()

const isLoading = ref(true)

const stats = ref({
  totalSkus: 0,
  lowStockItems: 0,
  pendingMovements: 0,
  warehouseSites: 0,
})

const recentMovements = ref([])

onMounted(async () => {
  isLoading.value = true
  
  try {
    await inventoryStore.fetchDashboardStats()

    const s = inventoryStore.dashboardStats?.kpis
    
    stats.value.totalSkus = s?.total_stock_items?.count || 0
    stats.value.lowStockItems = s?.below_reorder?.count || 0
    stats.value.pendingMovements = s?.qty_summary?.reserved || 0 // Or pending movements count if available
    stats.value.warehouseSites = s?.active_sites?.count || 0
    
    recentMovements.value = inventoryStore.dashboardStats?.recent_movements || []
  } catch (error) {
    console.error('Failed to fetch dashboard data', error)
  } finally {
    isLoading.value = false
  }
})

const quickActions = [
  { label: 'Adjustment', icon: 'tabler-adjustments', color: 'primary', to: { name: 'inventory-movements-list' } }, // Assuming these go to lists or create dialogs
  { label: 'Transfer', icon: 'tabler-replace', color: 'info', to: { name: 'inventory-movements-list' } },
  { label: 'Initial Load', icon: 'tabler-database-import', color: 'success', to: { name: 'inventory-stock-items-list' } },
]

const movementTypeMap = {
  GRN_RECEIPT: { color: 'success', icon: 'tabler-package-import', label: 'GRN Receipt' },
  SALES_DISPATCH: { color: 'primary', icon: 'tabler-package-export', label: 'Sales Dispatch' },
  TRANSFER_IN: { color: 'info', icon: 'tabler-transfer-in', label: 'Transfer In' },
  TRANSFER_OUT: { color: 'info', icon: 'tabler-transfer-out', label: 'Transfer Out' },
}

const resolveMovementType = type => movementTypeMap[type] || { color: 'secondary', icon: 'tabler-activity', label: type }
</script>

<template>
  <div class="inventory-dashboard">
    <WpPageHeader
      title="Inventory & Warehouse"
      subtitle="Real-time stock levels and warehouse operations"
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <VBtn
          v-for="action in quickActions"
          :key="action.label"
          :color="action.color"
          variant="tonal"
          :prepend-icon="action.icon"
          @click="router.push(action.to)"
        >
          {{ action.label }}
        </VBtn>
      </template>
    </WpPageHeader>

    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Total SKUs"
          :value="stats.totalSkus"
          icon="tabler-packages"
          color="primary"
          trend="Unique catalog items"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'inventory-stock-items-list' })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Low Stock Items"
          :value="stats.lowStockItems"
          icon="tabler-alert-triangle"
          color="warning"
          trend="Below reorder point"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'inventory-stock-items-list', query: { belowReorder: 1 } })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Pending Movements"
          :value="stats.pendingMovements"
          icon="tabler-transfer"
          color="info"
          trend="Stock in transit or reserved"
          :loading="isLoading"
          clickable
          @click="router.push({ name: 'inventory-movements-list' })"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <WpKpiCard
          label="Warehouse Sites"
          :value="stats.warehouseSites"
          icon="tabler-map-pin"
          color="success"
          trend="Active storage facilities"
          :loading="isLoading"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="glass-panel border h-100">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold">
              Recent Stock Movements
            </VCardTitle>
            <template #append>
              <VBtn
                variant="text"
                color="primary"
                @click="router.push({ name: 'inventory-movements-list' })"
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
                    Item
                  </th>
                  <th class="text-uppercase text-caption font-weight-bold inter-font">
                    Quantity
                  </th>
                  <th class="text-uppercase text-caption font-weight-bold inter-font">
                    Type
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="mov in recentMovements"
                  :key="mov.id"
                >
                  <td class="inter-font font-weight-medium">
                    {{ mov.reference }}
                  </td>
                  <td class="inter-font text-body-2">
                    {{ mov.item_name }}
                    <div class="text-caption text-disabled">
                      {{ mov.item_code }}
                    </div>
                  </td>
                  <td
                    class="inter-font text-body-2 font-weight-bold"
                    :class="mov.quantity > 0 ? 'text-success' : 'text-error'"
                  >
                    {{ mov.quantity > 0 ? '+' : '' }}{{ mov.quantity }}
                  </td>
                  <td>
                    <VChip
                      :color="resolveMovementType(mov.movement_type).color"
                      size="x-small"
                      label
                      class="text-capitalize"
                    >
                      {{ resolveMovementType(mov.movement_type).label }}
                    </VChip>
                  </td>
                </tr>
                <tr v-if="recentMovements.length === 0">
                  <td
                    colspan="4"
                    class="text-center py-4 text-medium-emphasis inter-font"
                  >
                    No recent movements found
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
        <VCard class="glass-panel border mb-6">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold">
              Quick Actions
            </VCardTitle>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-3">
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
        
        <VCard class="glass-panel border">
          <VCardItem>
            <VCardTitle class="inter-font font-weight-bold">
              Stock Composition
            </VCardTitle>
          </VCardItem>
          <VCardText class="pa-0">
            <div class="d-flex align-center justify-center py-10">
              <VIcon
                icon="tabler-chart-donut"
                size="64"
                class="text-disabled"
              />
              <p class="text-caption text-disabled inter-font ml-2">
                Analytics loading...
              </p>
            </div>
          </VCardText>
        </VCard>
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

.gap-3 { gap: 12px; }
</style>
