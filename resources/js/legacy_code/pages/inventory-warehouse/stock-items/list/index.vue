<script setup>
import {
  WpDataTableCard,
  WpErrorBanner,
  WpMobileCard,
  WpPageHeader,
} from '@/components/shared'
import { useListPage } from '@/composables/use-list-page'

import { useInventoryStore } from '@/stores/useInventoryStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/inventory-warehouse/stock-items/list',
  name: 'inventory-stock-items-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryStockItems',
  },
})

const store = useInventoryStore()
const snackbar = useSnackbarStore()
const router = useRouter()
const display = useDisplay()

// ── KPI Stats ────────────────────────────────────────────────────────────────
const metaLoading = ref(true)

const metaData = ref({
  total_items: 0,
  low_stock: 0,
  out_of_stock: 0,
})

const statsCards = computed(() => [
  { label: 'Total Items', value: metaData.value.total_items, color: 'primary', icon: 'tabler-box' },
  { label: 'Low Stock', value: metaData.value.low_stock, color: 'warning', icon: 'tabler-alert-triangle' },
  { label: 'Out of Stock', value: metaData.value.out_of_stock, color: 'error', icon: 'tabler-x' },
])

const fetchMeta = async () => {
  metaLoading.value = true
  try {
    const res = await store.fetchDashboardStats()
    if (res?.data) {
      metaData.value = {
        total_items: res.data.total_items || 0,
        low_stock: res.data.low_stock || 0,
        out_of_stock: res.data.out_of_stock || 0,
      }
    } else {
      metaData.value = {
        total_items: store.totalStockItems || 0,
        low_stock: store.stockItems.filter(i => i.qty_available > 0 && i.qty_available <= 5).length || 0,
        out_of_stock: store.stockItems.filter(i => i.qty_available <= 0).length || 0,
      }
    }
  } catch (err) {
    console.error('Meta error:', err)
  } finally {
    metaLoading.value = false
  }
}

// ── Shared List Composable ───────────────────────────────────────────────────
const fetchItems = () =>
  store.fetchStockItems({
    q: debouncedSearch.value,
    itemsPerPage: itemsPerPage.value,
    page: page.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  })

const {
  searchQuery,
  debouncedSearch,
  itemsPerPage,
  page,
  sortBy,
  orderBy,
  totalPage,
  paginationMeta,
  updateOptions,
  handleError,
} = useListPage({
  total: computed(() => store.totalStockItems),
  fetchFn: fetchItems,
})

const apiError = computed(() => (store.error ? handleError(store).message : null))

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Item Ref', key: 'item_reference', sortable: true },
  { title: 'Item Name', key: 'item_name', sortable: true },
  { title: 'Variant SKU', key: 'variant_sku', sortable: true },
  { title: 'Location', key: 'location.name', sortable: true },
  { title: 'Lot', key: 'lot_number', sortable: true },
  { title: 'Qty on Hand', key: 'qty_on_hand', sortable: true, align: 'end' },
  { title: 'Qty Reserved', key: 'qty_reserved', sortable: true, align: 'end' },
  { title: 'Qty Available', key: 'qty_available', sortable: true, align: 'end' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

const getAvailableColor = qty => {
  if (qty <= 0) return 'error'
  if (qty <= 5) return 'warning'
  
  return 'success'
}

const getItemReference = (item) => {
  return item.catalog_item?.code || item.catalog_item_id || '—'
}

const getItemName = (item) => {
  if (item.catalog_item?.name) {
    const name = item.catalog_item.name
    return typeof name === 'object' ? (name.en || name.fr || Object.values(name)[0] || '—') : name
  }
  return '—'
}

const getVariantSku = (item) => {
  return item.catalog_variant?.sku || item.catalog_item?.sku || '—'
}

// ── Actions ──────────────────────────────────────────────────────────────────
const isAdjustmentDialogOpen = ref(false)
const isTransferDialogOpen = ref(false)
const selectedItem = ref(null)

const openAdjustment = item => {
  selectedItem.value = item
  isAdjustmentDialogOpen.value = true
}

const openTransfer = item => {
  selectedItem.value = item
  isTransferDialogOpen.value = true
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchMeta()
})
</script>

<template>
  <div>
    <WpPageHeader 
      title="Stock Items" 
      subtitle="Monitor warehouse stock levels."
      icon="tabler-box"
    >
      <template #actions>
        <!-- No create button as per requirements -->
      </template>
    </WpPageHeader>

    <WpErrorBanner
      :error="apiError"
      :validation-errors="store.validationErrors"
    />

    <VRow class="mb-4">
      <VCol
        v-for="card in statsCards"
        :key="card.label"
        cols="12"
        sm="4"
      >
        <VCard class="h-100 rounded-lg border elevation-0 wp-stat-card glass-panel position-relative overflow-hidden">
          <div
            class="wp-stat-card__accent"
            :style="{ backgroundColor: `rgb(var(--v-theme-${card.color}))` }"
          />
          <VCardText class="pa-4 d-flex justify-space-between align-center">
            <div>
              <p
                class="text-caption text-uppercase font-weight-semibold text-medium-emphasis mb-1"
                style="letter-spacing: 0.5px;"
              >
                {{ card.label }}
              </p>
              <VSkeletonLoader
                v-if="metaLoading"
                type="text"
                width="60"
              />
              <h3
                v-else
                class="text-h4 font-weight-bold inter-font"
              >
                {{ card.value }}
              </h3>
            </div>
            <VAvatar
              rounded
              size="48"
              :color="card.color"
              variant="tonal"
            >
              <VIcon
                :icon="card.icon"
                size="24"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-4 rounded-lg glass-panel elevation-0 border">
      <VCardText class="pa-4">
        <VRow
          align="center"
          dense
        >
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="searchQuery"
              placeholder="Search reference or name..."
              prepend-inner-icon="tabler-search"
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div v-if="display.smAndDown.value && store.stockItems.length > 0">
      <WpMobileCard
        v-for="item in store.stockItems"
        :key="item.id"
        :title="getItemName(item)"
        :subtitle="getItemReference(item)"
        :accent-color="getAvailableColor(item.qty_available)"
        :meta="[
          { label: 'Variant SKU', value: getVariantSku(item) },
          { label: 'Location', value: item.location?.name || '—' },
          { label: 'Available', value: item.qty_available, color: getAvailableColor(item.qty_available) }
        ]"
      >
        <template #actions>
          <VBtn
            size="small"
            variant="text"
            color="primary"
            @click="openAdjustment(item)"
          >
            Ajustement
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            color="info"
            @click="openTransfer(item)"
          >
            Transfert
          </VBtn>
        </template>
      </WpMobileCard>
      
      <div class="d-flex justify-center mt-4">
        <VPagination
          v-model="page"
          :length="totalPage"
          :total-visible="3"
        />
      </div>
    </div>

    <WpDataTableCard
      v-else
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="store.stockItems"
      :total-items="store.totalStockItems"
      :loading="store.loading"
      @update:options="updateOptions"
    >
      <template #item.item_reference="{ item }">
        <span class="font-weight-semibold text-primary">
          {{ getItemReference(item) }}
        </span>
      </template>

      <template #item.item_name="{ item }">
        <span class="text-truncate" style="max-width: 250px;">
          {{ getItemName(item) }}
        </span>
      </template>

      <template #item.variant_sku="{ item }">
        <span class="font-monospace text-caption">
          {{ getVariantSku(item) }}
        </span>
      </template>

      <template #item.qty_on_hand="{ item }">
        {{ item.qty_on_hand || 0 }}
      </template>

      <template #item.qty_reserved="{ item }">
        {{ item.qty_reserved || 0 }}
      </template>

      <template #item.qty_available="{ item }">
        <VChip
          size="small"
          :color="getAvailableColor(item.qty_available)"
          class="font-weight-medium"
        >
          {{ item.qty_available || 0 }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-2">
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            @click="openAdjustment(item)"
          >
            Ajustement
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="info"
            @click="openTransfer(item)"
          >
            Transfert
          </VBtn>
        </div>
      </template>
      
      <template #bottom>
        <VDivider />
        <div class="d-flex align-center justify-space-between flex-wrap gap-3 pa-5">
          <p class="text-sm text-medium-emphasis mb-0">
            {{ paginationMeta }}
          </p>
          <VPagination
            v-model="page"
            :length="totalPage"
            :total-visible="5"
            density="comfortable"
          />
        </div>
      </template>
    </WpDataTableCard>

    <!-- Placeholder for Dialogs -->
    <VDialog
      v-model="isAdjustmentDialogOpen"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Ajustement de Stock</VCardTitle>
        <VCardText>
          <p>Ajustement pour l'article : {{ selectedItem?.item_name }}</p>
          <!-- Additional fields would go here -->
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="isAdjustmentDialogOpen = false"
          >
            Annuler
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isTransferDialogOpen"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Transfert de Stock</VCardTitle>
        <VCardText>
          <p>Transfert pour l'article : {{ selectedItem?.item_name }}</p>
          <!-- Additional fields would go here -->
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="isTransferDialogOpen = false"
          >
            Annuler
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
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

.wp-stat-card {
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s;
}

.wp-stat-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 6%) !important;
  transform: translateY(-2px);
}

.wp-stat-card__accent {
  position: absolute;
  block-size: 3px;
  inset-block-start: 0;
  inset-inline: 0;
  opacity: 0.9;
}
</style>
