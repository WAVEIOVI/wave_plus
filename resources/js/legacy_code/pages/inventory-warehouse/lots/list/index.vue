<script setup>
import {
  WpDataTableCard,
  WpErrorBanner,
  WpMobileCard,
  WpPageHeader,
  WpStatusChip,
} from '@/components/shared'
import { useListPage } from '@/composables/use-list-page'

import { useInventoryStore } from '@/stores/useInventoryStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/inventory-warehouse/lots/list',
  name: 'inventory-lots-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryLots',
  },
})

const store = useInventoryStore()
const snackbar = useSnackbarStore()
const router = useRouter()
const display = useDisplay()

// ── Status Options ───────────────────────────────────────────────────────────
const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Quarantine', value: 'quarantine' },
  { title: 'Expired', value: 'expired' },
  { title: 'Consumed', value: 'consumed' },
]

const getStatusChip = status => {
  const map = {
    active: { label: 'Active', color: 'success' },
    quarantine: { label: 'Quarantine', color: 'warning' },
    expired: { label: 'Expired', color: 'error' },
    consumed: { label: 'Consumed', color: 'secondary' },
  }
  
  return map[status] || { label: status, color: 'default' }
}

// ── KPI Stats ────────────────────────────────────────────────────────────────
const metaLoading = ref(true)

const metaData = ref({
  total: 0,
  expiring_soon: 0,
  expired: 0,
})

const statsCards = computed(() => [
  { label: 'Total Lots', value: metaData.value.total, color: 'primary', icon: 'tabler-barcode' },
  { label: 'Expiring Soon', value: metaData.value.expiring_soon, color: 'warning', icon: 'tabler-clock' },
  { label: 'Expired', value: metaData.value.expired, color: 'error', icon: 'tabler-x' },
])

const fetchMeta = async () => {
  metaLoading.value = true
  try {
    const res = await store.fetchDashboardStats()
    if (res?.data) {
      metaData.value = {
        total: res.data.total_lots || 0,
        expiring_soon: res.data.expiring_soon_count || 0,
        expired: res.data.expired_count || 0,
      }
    } else {
      metaData.value = {
        total: store.totalLots || 0,
        expiring_soon: store.lots.filter(i => isExpiringSoon(i.expiry_date)).length || 0,
        expired: store.lots.filter(i => i.status === 'expired').length || 0,
      }
    }
  } catch (err) {
    console.error('Meta error:', err)
  } finally {
    metaLoading.value = false
  }
}

// ── Filters ──────────────────────────────────────────────────────────────────
const selectedStatus = ref(null)
const isMobileFilterOpen = ref(false)

// ── Shared List Composable ───────────────────────────────────────────────────
const fetchItems = () =>
  store.fetchLots({
    q: debouncedSearch.value,
    'filter[status]': selectedStatus.value || undefined,
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
  total: computed(() => store.totalLots),
  fetchFn: fetchItems,
  filters: [selectedStatus],
})

const apiError = computed(() => (store.error ? handleError(store).message : null))

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Lot Number', key: 'lot_number', sortable: true },
  { title: 'Item', key: 'item.name', sortable: true },
  { title: 'Supplier', key: 'supplier.name', sortable: true },
  { title: 'Expiry Date', key: 'expiry_date', sortable: true },
  { title: 'Qty In Stock', key: 'qty_in_stock', sortable: true, align: 'end' },
  { title: 'Status', key: 'status', sortable: true, align: 'center' },
]

const formatDate = isoString => {
  if (!isoString) return '—'
  const d = new Date(isoString)
  
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const isExpiringSoon = isoString => {
  if (!isoString) return false
  const expiry = new Date(isoString)
  const now = new Date()
  const diffTime = expiry.getTime() - now.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  
  return diffDays > 0 && diffDays <= 30
}

const isExpired = isoString => {
  if (!isoString) return false
  
  return new Date(isoString) < new Date()
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchMeta()
})
</script>

<template>
  <div>
    <WpPageHeader 
      title="Lots & Batches" 
      subtitle="Manage inventory lots and traceablility."
      icon="tabler-barcode"
    >
      <template #actions>
        <!-- Read only view as per requirements -->
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
            sm="4"
          >
            <VTextField
              v-model="searchQuery"
              placeholder="Search lot number..."
              prepend-inner-icon="tabler-search"
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="6"
            sm="3"
            class="d-none d-sm-block"
          >
            <VSelect
              v-model="selectedStatus"
              :items="statusOptions"
              placeholder="Status"
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            class="d-sm-none mt-2"
          >
            <VBtn
              block
              variant="tonal"
              color="primary"
              prepend-icon="tabler-filter"
              @click="isMobileFilterOpen = true"
            >
              Filtres Avancés
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VBottomSheet
      v-model="isMobileFilterOpen"
      inset
    >
      <VCard class="rounded-t-xl pb-4">
        <VCardTitle class="px-5 pt-5 pb-3 d-flex justify-space-between align-center">
          <span class="font-weight-bold">Filtres</span>
          <VBtn
            icon="tabler-x"
            variant="text"
            density="comfortable"
            @click="isMobileFilterOpen = false"
          />
        </VCardTitle>
        <VCardText class="px-5">
          <VSelect
            v-model="selectedStatus"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            class="mb-4"
            clearable
          />
        </VCardText>
      </VCard>
    </VBottomSheet>

    <div v-if="display.smAndDown.value && store.lots.length > 0">
      <WpMobileCard
        v-for="item in store.lots"
        :key="item.id"
        :title="item.lot_number"
        :subtitle="item.item?.name || '—'"
        :status="item.status"
        :status-map="getStatusChip" 
        :accent-color="getStatusChip(item.status).color"
        :meta="[
          { label: 'Stock', value: item.qty_in_stock }
        ]"
      />
      
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
      :items="store.lots"
      :total-items="store.totalLots"
      :loading="store.loading"
      @update:options="updateOptions"
    >
      <template #item.lot_number="{ item }">
        <span class="font-weight-semibold text-primary">
          {{ item.lot_number }}
        </span>
      </template>

      <template #item.supplier.name="{ item }">
        {{ item.supplier?.name || '—' }}
      </template>

      <template #item.expiry_date="{ item }">
        <span :class="{ 'text-error font-weight-bold': isExpired(item.expiry_date), 'text-warning font-weight-bold': isExpiringSoon(item.expiry_date) }">
          {{ formatDate(item.expiry_date) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :status-map="getStatusChip"
        />
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
