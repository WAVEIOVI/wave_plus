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
import { getMovementTypeChip, MOVEMENT_TYPE_MAP, movementTypeOptions } from '@/utils/procurement-constants'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/inventory-warehouse/movements/list',
  name: 'inventory-movements-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryMovements',
  },
})

const store = useInventoryStore()
const snackbar = useSnackbarStore()
const router = useRouter()
const display = useDisplay()

// ── KPI Stats ────────────────────────────────────────────────────────────────
const metaLoading = ref(true)

const metaData = ref({
  total: 0,
  inbound: 0,
  outbound: 0,
})

const statsCards = computed(() => [
  { label: 'Total Movements', value: metaData.value.total, color: 'primary', icon: 'tabler-arrows-right-left' },
  { label: 'Inbound', value: metaData.value.inbound, color: 'success', icon: 'tabler-arrow-down-left' },
  { label: 'Outbound', value: metaData.value.outbound, color: 'warning', icon: 'tabler-arrow-up-right' },
])

const fetchMeta = async () => {
  metaLoading.value = true
  try {
    const res = await store.fetchDashboardStats()
    if (res?.data) {
      metaData.value = {
        total: res.data.total_movements || 0,
        inbound: res.data.inbound_count || 0,
        outbound: res.data.outbound_count || 0,
      }
    } else {
      metaData.value = {
        total: store.totalMovements || 0,
        inbound: store.movements.filter(i => i.qty > 0).length || 0,
        outbound: store.movements.filter(i => i.qty < 0).length || 0,
      }
    }
  } catch (err) {
    console.error('Meta error:', err)
  } finally {
    metaLoading.value = false
  }
}

// ── Filters ──────────────────────────────────────────────────────────────────
const selectedType = ref(null)
const selectedDate = ref(null)
const isMobileFilterOpen = ref(false)

// ── Shared List Composable ───────────────────────────────────────────────────
const fetchItems = () =>
  store.fetchMovements({
    q: debouncedSearch.value,
    'filter[type]': selectedType.value || undefined,
    'filter[date]': selectedDate.value || undefined,
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
  total: computed(() => store.totalMovements),
  fetchFn: fetchItems,
  filters: [selectedType, selectedDate],
})

const apiError = computed(() => (store.error ? handleError(store).message : null))

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Reference', key: 'reference', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Item', key: 'item_name', sortable: true },
  { title: 'Variant SKU', key: 'variant_sku', sortable: true },
  { title: 'Qty', key: 'qty', sortable: true, align: 'end' },
  { title: 'From', key: 'from_location.name', sortable: false },
  { title: 'To', key: 'to_location.name', sortable: false },
  { title: 'Date', key: 'performed_at', sortable: true },
]

const formatDate = isoString => {
  if (!isoString) return '—'
  const d = new Date(isoString)
  
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
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

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchMeta()
})
</script>

<template>
  <div>
    <WpPageHeader 
      title="Stock Movements" 
      subtitle="Track all inventory transactions."
      icon="tabler-arrows-right-left"
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
            sm="4"
          >
            <VTextField
              v-model="searchQuery"
              placeholder="Search reference..."
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
              v-model="selectedType"
              :items="movementTypeOptions"
              placeholder="Type"
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
            v-model="selectedType"
            :items="movementTypeOptions"
            label="Type"
            variant="outlined"
            class="mb-4"
            clearable
          />
        </VCardText>
      </VCard>
    </VBottomSheet>

    <div v-if="display.smAndDown.value && store.movements.length > 0">
      <WpMobileCard
        v-for="item in store.movements"
        :key="item.id"
        :title="getItemName(item)"
        :subtitle="getItemReference(item)"
        :status="item.type"
        :status-map="getMovementTypeChip" 
        :accent-color="getMovementTypeChip(item.type).color"
        :meta="[
          { label: 'Variant SKU', value: getVariantSku(item) },
          { label: 'Qty', value: item.qty, color: item.qty > 0 ? 'success' : 'error' }
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
      :items="store.movements"
      :total-items="store.totalMovements"
      :loading="store.loading"
      @update:options="updateOptions"
    >
      <template #item.reference="{ item }">
        <span class="font-weight-semibold text-primary">
          {{ item.reference }}
        </span>
      </template>

      <template #item.type="{ item }">
        <VChip
          size="small"
          :color="getMovementTypeChip(item.type).color"
          class="font-weight-medium"
        >
          <VIcon
            start
            :icon="MOVEMENT_TYPE_MAP[item.type]?.icon || 'tabler-help'"
            size="14"
          />
          {{ getMovementTypeChip(item.type).text }}
        </VChip>
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

      <template #item.qty="{ item }">
        <span
          :class="item.qty > 0 ? 'text-success' : 'text-error'"
          class="font-weight-bold"
        >
          {{ item.qty > 0 ? '+' : '' }}{{ item.qty }}
        </span>
      </template>

      <template #item.from_location.name="{ item }">
        {{ item.from_location?.name || '—' }}
      </template>

      <template #item.to_location.name="{ item }">
        {{ item.to_location?.name || '—' }}
      </template>

      <template #item.performed_at="{ item }">
        {{ formatDate(item.performed_at) }}
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
