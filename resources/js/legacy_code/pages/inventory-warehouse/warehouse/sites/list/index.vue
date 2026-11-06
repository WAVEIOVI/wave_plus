<script setup>
import {
  WpDataTableCard,
  WpErrorBanner,
  WpMobileCard,
  WpPageHeader,
} from '@/components/shared'
import { useListPage } from '@/composables/use-list-page'
import { localizedValue } from '@/composables/useLocaleField'

import { useInventoryStore } from '@/stores/useInventoryStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import MobileWarehouseLinkDialog from '@/views/inventory-warehouse/warehouse/sites/list/MobileWarehouseLinkDialog.vue'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/inventory-warehouse/warehouse/sites/list',
  name: 'inventory-warehouse-sites-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouseSites',
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
  active: 0,
})

const statsCards = computed(() => [
  { label: 'Total Sites', value: metaData.value.total, color: 'primary', icon: 'tabler-building-warehouse' },
  { label: 'Active Sites', value: metaData.value.active, color: 'success', icon: 'tabler-building-warehouse' },
])

const fetchMeta = async () => {
  metaLoading.value = true
  try {
    const res = await store.fetchDashboardStats()
    if (res?.data) {
      metaData.value = {
        total: res.data.total_sites || 0,
        active: res.data.active_sites_count || 0,
      }
    } else {
      metaData.value = {
        total: store.totalSites || 0,
        active: store.sites.filter(i => i.is_active).length || 0,
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
  store.fetchSites({
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
  total: computed(() => store.totalSites),
  fetchFn: fetchItems,
})

const apiError = computed(() => (store.error ? handleError(store).message : null))

// ── Address Formatting ───────────────────────────────────────────────────────
// address is a JSON blob { line1, line2, zip } (or a plain string from the edit form)
const formatAddress = item => {
  const a = item?.address

  const fromBlob = a && typeof a === 'object'
    ? [a.line1, a.line2, a.zip].filter(Boolean).join(', ')
    : (a || '')

  return fromBlob || [item?.city, item?.country].filter(Boolean).join(', ') || '—'
}

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Type', key: 'site_type', sortable: true },
  { title: 'Address', key: 'address', sortable: false },
  { title: 'Zones', key: 'zones_count', sortable: true, align: 'end' },
  { title: 'Locations', key: 'locations_count', sortable: true, align: 'end' },
  { title: 'Active', key: 'is_active', sortable: true, align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

// ── Actions ──────────────────────────────────────────────────────────────────
const isToggling = ref(false)

const toggleActive = async site => {
  if (isToggling.value) return
  isToggling.value = true
  try {
    await store.toggleSiteActive(site.id)
    snackbar.showMessage('Site status updated.', 'success')
    fetchItems()
    fetchMeta()
  } catch (e) {
    snackbar.showMessage(handleError(store).message, 'error')
  } finally {
    isToggling.value = false
  }
}

// ── Mobile Warehouse Linking ─────────────────────────────────────────────────
const mobileWarehouseLinkDialog = ref(false)
const selectedSite = ref(null)

const openMobileWarehouseDialog = site => {
  selectedSite.value = site
  mobileWarehouseLinkDialog.value = true
}

const handleVehicleLinked = () => {
  mobileWarehouseLinkDialog.value = false
  selectedSite.value = null
  fetchItems()
  fetchMeta()
  snackbar.showMessage('Vehicle linked successfully', 'success')
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchMeta()
})
</script>

<template>
  <div>
    <WpPageHeader 
      title="Warehouse Sites" 
      subtitle="Manage warehouse facilities."
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: 'new' } })"
        >
          Créer Nouveau
        </VBtn>
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
        sm="6"
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
              placeholder="Search site..."
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

    <div v-if="display.smAndDown.value && store.sites.length > 0">
      <WpMobileCard
        v-for="item in store.sites"
        :key="item.id"
        :title="localizedValue(item.name)"
        :subtitle="item.code"
        :accent-color="item.is_active ? 'success' : 'default'"
        :meta="[
          { label: 'Active', value: item.is_active ? 'Yes' : 'No' }
        ]"
        @click="router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: item.id } })"
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
      :items="store.sites"
      :total-items="store.totalSites"
      :loading="store.loading"
      @update:options="updateOptions"
      @row-click="(item) => router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: item.id } })"
    >
      <template #item.name="{ item }">
        <span class="font-weight-semibold text-primary cursor-pointer text-decoration-underline-hover">
          {{ localizedValue(item.name) }}
        </span>
      </template>

      <template #item.site_type="{ item }">
        <VChip
          :color="item.site_type === 'mobile' ? 'info' : 'default'"
          size="small"
          variant="tonal"
        >
          <VIcon
            :icon="item.site_type === 'mobile' ? 'tabler-truck' : 'tabler-building-warehouse'"
            start
            size="16"
          />
          {{ item.site_type === 'mobile' ? 'Mobile' : 'Fixed' }}
        </VChip>
      </template>

      <template #item.address="{ item }">
        <span class="text-sm text-medium-emphasis">
          {{ formatAddress(item) }}
        </span>
      </template>

      <template #item.is_active="{ item }">
        <VSwitch
          :model-value="item.is_active"
          color="success"
          density="compact"
          hide-details
          class="d-inline-flex align-center justify-center"
          @change="toggleActive(item)"
          @click.stop
        />
      </template>

      <template #item.actions="{ item }">
        <div
          class="d-flex justify-end gap-2"
          @click.stop
        >
          <IconBtn
            v-if="item.site_type === 'mobile'"
            v-tooltip="item.vehicle_id ? 'Change Vehicle' : 'Link Vehicle'"
            @click="openMobileWarehouseDialog(item)"
          >
            <VIcon
              :icon="item.vehicle_id ? 'tabler-refresh' : 'tabler-link'"
              size="20"
            />
          </IconBtn>
          <IconBtn @click="router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: item.id } })">
            <VIcon
              icon="tabler-pencil"
              size="20"
            />
          </IconBtn>
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

    <MobileWarehouseLinkDialog
      v-model="mobileWarehouseLinkDialog"
      :site="selectedSite"
      @vehicle-linked="handleVehicleLinked"
    />
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

.text-decoration-underline-hover:hover {
  text-decoration: underline;
}
</style>
