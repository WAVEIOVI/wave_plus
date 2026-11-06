<script setup>
import {
  WpConfirmDeleteDialog,
  WpDataTableCard,
  WpErrorBanner,
  WpMobileCard,
  WpPageHeader,
  WpStatusChip,
} from '@/components/shared'
import { useListPage } from '@/composables/use-list-page'

import { useGrnStore } from '@/stores/useGrnStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import GrnViewModal from '@/views/supply-chain-procurement/grn/list/GrnViewModal.vue'

import { getGrnStatusChip, grnStatusOptions } from '@/utils/procurement-constants'

import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/supply-chain-procurement/grn/list',
  name: 'procurement-grn-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const store = useGrnStore()
const snackbar = useSnackbarStore()
const router = useRouter()
const display = useDisplay()

// ── KPI Stats (Derived from List) ─────────────────────────────────────────────
// The GRN store doesn't have a fetchMeta endpoint, so we derive stats from the list.
const statsCards = computed(() => [
  { 
    label: 'Total GRNs', 
    value: store.totalItems || 0, 
    color: 'primary', 
    icon: 'tabler-clipboard-check', 
  },
  { 
    label: 'Under QC', 
    value: store.list.filter(g => g.status === 'under_qc').length || 0, 
    color: 'warning', 
    icon: 'tabler-microscope', 
  },
  { 
    label: 'Fully Rejected', 
    value: store.list.filter(g => g.status === 'fully_rejected').length || 0, 
    color: 'error', 
    icon: 'tabler-x', 
  },
  { 
    label: 'Stocked', 
    value: store.list.filter(g => g.status === 'stocked').length || 0, 
    color: 'success', 
    icon: 'tabler-building-warehouse', 
  },
])

// ── Filters ──────────────────────────────────────────────────────────────────
const selectedStatus = ref(null)
const isMobileFilterOpen = ref(false)

// ── Shared List Composable ───────────────────────────────────────────────────
const fetchItems = () =>
  store.fetchList({
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
  isViewModalVisible,
  selectedItemId,
  handleViewItem,
  isDeleteDialogVisible,
  openDeleteDialog,
  handleDeleteConfirm,
  cancelDelete,
  deleteLoading,
  handleError,
} = useListPage({
  total: computed(() => store.totalItems),
  fetchFn: fetchItems,
  deleteFn: id => store.deleteItem(id),
  onDeleted: () => {
    snackbar.showMessage('GRN deleted successfully.', 'success')
  },
  filters: [selectedStatus],
})

const apiError = computed(() => (store.error ? handleError(store).message : null))

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Reference', key: 'reference', sortable: true },
  { title: 'PO Reference', key: 'purchase_order.reference', sortable: false, class: 'd-none d-sm-table-cell' },
  { title: 'Supplier', key: 'supplier.legal_name', sortable: false, class: 'd-none d-md-table-cell' },
  { title: 'Received At', key: 'received_at', sortable: true },
  { title: 'Status', key: 'status', sortable: true, align: 'center' },
  { title: 'QC Result', key: 'overall_qc_result', sortable: true, align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

const formatDate = isoString => {
  if (!isoString) return '—'
  const d = new Date(isoString)
  
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div>
    <WpPageHeader 
      title="Goods Receipt Notes" 
      subtitle="Manage and track GRNs."
      icon="tabler-clipboard-check"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push({ name: 'procurement-grn-edit-id', params: { id: 'new' } })"
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
        md="3"
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
              <h3 class="text-h4 font-weight-bold inter-font">
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
              v-model="selectedStatus"
              :items="grnStatusOptions"
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
              color="secondary"
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
            :items="grnStatusOptions"
            label="Status"
            variant="outlined"
            class="mb-4"
            clearable
          />
        </VCardText>
      </VCard>
    </VBottomSheet>

    <div v-if="display.smAndDown.value && store.list.length > 0">
      <WpMobileCard
        v-for="item in store.list"
        :key="item.id"
        :title="item.reference || 'Draft'"
        :subtitle="item.purchase_order?.reference || '—'"
        :status="item.status"
        :status-map="getGrnStatusChip" 
        :accent-color="getGrnStatusChip(item.status).color"
        :meta="[
          { label: 'Received At', value: formatDate(item.received_at) }
        ]"
        @click="router.push({ name: 'procurement-grn-edit-id', params: { id: item.id } })"
      >
        <template #actions>
          <VBtn
            size="small"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-eye"
            class="rounded-lg"
            @click="handleViewItem(item.id)"
          >
            Voir
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-pencil"
            class="rounded-lg"
            @click="router.push({ name: 'procurement-grn-edit-id', params: { id: item.id } })"
          >
            Modifier
          </VBtn>
        </template>
      </WpMobileCard>
      
      <div class="d-flex justify-center mt-4">
        <VPagination
          v-model="page"
          :length="totalPage"
          :total-visible="3"
          show-first-last-page
        />
      </div>
    </div>

    <WpDataTableCard
      v-else
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="store.list"
      :total-items="store.totalItems"
      :loading="store.loading"
      @update:options="updateOptions"
      @row-click="(item) => router.push({ name: 'procurement-grn-edit-id', params: { id: item.id } })"
    >
      <template #item.reference="{ item }">
        <span class="font-weight-semibold text-primary cursor-pointer text-decoration-underline-hover">
          {{ item.reference || 'Draft' }}
        </span>
      </template>

      <template #item.purchase_order.reference="{ item }">
        <RouterLink
          v-if="item.purchase_order"
          :to="{ name: 'procurement-po-edit-id', params: { id: item.purchase_order.id } }"
          class="text-link text-decoration-underline-hover"
          @click.stop
        >
          {{ item.purchase_order.reference }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template #item.received_at="{ item }">
        {{ formatDate(item.received_at) }}
      </template>

      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :status-map="getGrnStatusChip"
        />
      </template>
      
      <template #item.overall_qc_result="{ item }">
        <VChip
          v-if="item.overall_qc_result"
          :color="item.overall_qc_result.includes('rejected') ? 'error' : 'success'"
          size="small"
          label
          class="text-capitalize"
        >
          {{ item.overall_qc_result.replace(/_/g, ' ') }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div
          class="d-flex justify-end gap-2"
          @click.stop
        >
          <IconBtn @click="handleViewItem(item.id)">
            <VIcon
              icon="tabler-eye"
              size="20"
            />
          </IconBtn>
          <IconBtn @click="router.push({ name: 'procurement-grn-edit-id', params: { id: item.id } })">
            <VIcon
              icon="tabler-pencil"
              size="20"
            />
          </IconBtn>
          <IconBtn
            color="error"
            @click="openDeleteDialog(item.id)"
          >
            <VIcon
              icon="tabler-trash"
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
            show-first-last-page
          />
        </div>
      </template>
    </WpDataTableCard>

    <WpConfirmDeleteDialog
      v-model="isDeleteDialogVisible"
      item-name="ce GRN"
      :loading="deleteLoading"
      @confirm="handleDeleteConfirm"
      @cancel="cancelDelete"
    />

    <GrnViewModal
      v-model:is-dialog-visible="isViewModalVisible"
      :grn-id="selectedItemId"
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

.text-link {
  color: rgb(var(--v-theme-primary));
}
</style>
