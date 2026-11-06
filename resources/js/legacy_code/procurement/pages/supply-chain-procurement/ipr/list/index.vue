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

import { useIprStore } from '@/stores/useIprStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import IprViewModal from '@/views/supply-chain-procurement/ipr/list/IprViewModal.vue'

import { getIprStatusChip, getUrgencyChip, iprStatusOptions, urgencyOptions } from '@/utils/procurement-constants'

import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/supply-chain-procurement/ipr/list',
  name: 'procurement-ipr-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const store = useIprStore()
const snackbar = useSnackbarStore()
const router = useRouter()
const display = useDisplay()

// ── KPI Stats (Derived from List) ─────────────────────────────────────────────
// The IPR store doesn't have a fetchMeta endpoint, so we derive stats from the list.
const statsCards = computed(() => [
  { 
    label: 'Total IPRs', 
    value: store.totalItems || 0, 
    color: 'primary', 
    icon: 'tabler-file-invoice', 
  },
  { 
    label: 'Pending Approval', 
    value: store.list.filter(i => i.status === 'pending_approval').length || 0, 
    color: 'warning', 
    icon: 'tabler-clock-pause', 
  },
  { 
    label: 'Urgent Requests', 
    value: store.list.filter(i => ['urgent', 'critical'].includes(i.urgency)).length || 0, 
    color: 'error', 
    icon: 'tabler-urgent', 
  },
  { 
    label: 'Converted to PO', 
    value: store.list.filter(i => i.status === 'converted_to_po').length || 0, 
    color: 'success', 
    icon: 'tabler-transform', 
  },
])

// ── Filters ──────────────────────────────────────────────────────────────────
const selectedStatus = ref(null)
const selectedUrgency = ref(null)
const selectedSource = ref(null)

const isMobileFilterOpen = ref(false)

// ── Shared List Composable ───────────────────────────────────────────────────
const fetchItems = () =>
  store.fetchList({
    q: debouncedSearch.value,
    'filter[status]': selectedStatus.value || undefined,
    'filter[urgency]': selectedUrgency.value || undefined,
    'filter[trigger_source]': selectedSource.value || undefined,
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
    snackbar.showMessage('IPR deleted successfully.', 'success')
  },
  filters: [selectedStatus, selectedUrgency, selectedSource],
})

const apiError = computed(() => (store.error ? handleError(store).message : null))
const getAssignedToInitials = user => user?.name?.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || user?.email?.[0]?.toUpperCase() || '?'

// ── Table Configuration ──────────────────────────────────────────────────────
const headers = [
  { title: 'Reference', key: 'reference', sortable: true },
  { title: 'Source', key: 'trigger_source', sortable: true },
  { title: 'Lines', key: 'lines', sortable: false, align: 'center', class: 'd-none d-sm-table-cell' },
  { title: 'Needed By', key: 'needed_by_date', sortable: true, class: 'd-none d-md-table-cell' },
  { title: 'Assigned To', key: 'assigned_to', sortable: false },
  { title: 'Urgency', key: 'urgency', sortable: true },
  { title: 'Status', key: 'status', sortable: true, align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

// ── Lifecycle ────────────────────────────────────────────────────────────────
</script>

<template>
  <div>
    <WpPageHeader 
      title="Internal Purchase Requisitions" 
      subtitle="Manage and track IPRs."
      icon="tabler-file-invoice"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push({ name: 'procurement-ipr-edit-id', params: { id: 'new' } })"
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
              :items="iprStatusOptions"
              placeholder="Status"
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
              v-model="selectedUrgency"
              :items="urgencyOptions"
              placeholder="Urgency"
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
            :items="iprStatusOptions"
            label="Status"
            variant="outlined"
            class="mb-4"
            clearable
          />
          <VSelect
            v-model="selectedUrgency"
            :items="urgencyOptions"
            label="Urgency"
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
        :title="item.reference"
        :subtitle="item.trigger_source"
        :status="item.status"
        :status-map="getIprStatusChip" 
        :accent-color="getIprStatusChip(item.status).color"
        :meta="[
          { label: 'Lines', value: item.lines?.length || item.lines_count || 0 },
          { label: 'Needed By', value: item.needed_by_date || '—' }
        ]"
        @click="router.push({ name: 'procurement-ipr-edit-id', params: { id: item.id } })"
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
            @click="router.push({ name: 'procurement-ipr-edit-id', params: { id: item.id } })"
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
      @row-click="(item) => router.push({ name: 'procurement-ipr-edit-id', params: { id: item.id } })"
    >
      <template #item.reference="{ item }">
        <span class="font-weight-semibold text-primary cursor-pointer text-decoration-underline-hover">
          {{ item.reference }}
        </span>
      </template>
      
      <template #item.trigger_source="{ item }">
        <div class="d-flex flex-column">
          <span class="text-capitalize text-body-2 font-weight-medium">
            {{ (item.trigger_source || '—').replace(/_/g, ' ') }}
          </span>
          <span
            v-if="item.trigger_reference"
            class="text-xs text-disabled"
          >
            {{ item.trigger_reference }}
          </span>
        </div>
      </template>

      <template #item.lines="{ item }">
        <span class="font-weight-medium">{{ item.lines?.length || item.lines_count || 0 }}</span>
      </template>

      <template #item.assigned_to="{ item }">
        <div
          v-if="item.assigned_to_user"
          class="d-flex align-center gap-2"
        >
          <VAvatar
            size="24"
            color="primary"
            variant="tonal"
          >
            <span class="text-xs">{{ getAssignedToInitials(item.assigned_to_user) }}</span>
          </VAvatar>
          <span class="text-body-2">{{ item.assigned_to_user.name || item.assigned_to_user.email }}</span>
        </div>
        <span
          v-else
          class="text-disabled"
        >—</span>
      </template>

      <template #item.urgency="{ item }">
        <WpStatusChip
          :status="item.urgency"
          :status-map="getUrgencyChip"
        />
      </template>

      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :status-map="getIprStatusChip"
        />
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
          <IconBtn @click="router.push({ name: 'procurement-ipr-edit-id', params: { id: item.id } })">
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
      item-name="cette IPR"
      :loading="deleteLoading"
      @confirm="handleDeleteConfirm"
      @cancel="cancelDelete"
    />

    <IprViewModal
      v-model:is-dialog-visible="isViewModalVisible"
      :ipr-id="selectedItemId"
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
