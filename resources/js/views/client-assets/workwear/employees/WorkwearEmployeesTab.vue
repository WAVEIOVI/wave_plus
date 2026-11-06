<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpBulkActions,
  WpDeleteDialog,
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useWorkwearActions } from '@/composables/client-assets/workwear/useWorkwearActions'
import { useWorkwearDialogs } from '@/composables/client-assets/workwear/useWorkwearDialogs'
import { useWorkwearEmployeeList } from '@/composables/client-assets/workwear/useWorkwearEmployeeList'
import AddNewEmployeeDialog from '@/views/client-assets/workwear/employees/list/AddNewEmployeeDialog.vue'
import BatchCreateEmployeesDialog from '@/views/client-assets/workwear/employees/list/BatchCreateEmployeesDialog.vue'
import ViewEmployeeDialog from '@/views/client-assets/workwear/employees/list/ViewEmployeeDialog.vue'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

// ── Employee List Orchestration ────────────────────────────────────────────
const {
  items,
  totalItems,
  loading,
  error,
  searchQuery,
  filters,
  stagedFilters,
  activeFilters,
  activeFilterCount,
  setFilter,
  removeFilter,
  resetFilters,
  applyStaged,
  resetStaged,
  page,
  itemsPerPage,
  sortBy,
  refresh,
  headers,
  clientOptions,
  statusOptions,
  statusMap,
  selectedIds,
  clearSelection,
  loadFilterOptions,
} = useWorkwearEmployeeList()

const isFilterDialogOpen = ref(false)

onMounted(() => {
  loadFilterOptions()
})

// ── Dialogs & Actions ──────────────────────────────────────────────────────
const dialogs = useWorkwearDialogs()

const actions = useWorkwearActions({
  onRefresh: refresh,
})

const isBatchCreateDialogOpen = ref(false)

const handleView = emp => {
  dialogs.openView(emp, 'employee')
}

const handleEdit = emp => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: emp.id }, query: { type: 'employee' } })
}

const handleDelete = emp => {
  dialogs.openDelete(emp, 'employee')
}

const handleConfirmDelete = async () => {
  if (dialogs.activeEntity.value?.id) {
    const success = await actions.deleteEmployee(dialogs.activeEntity.value.id)
    if (success) {
      dialogs.closeDelete()
    }
  }
}

const handleBulkDelete = async () => {
  for (const id of selectedIds.value) {
    await actions.deleteEmployee(id)
  }
  clearSelection()
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Profile'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

defineExpose({
  openAdd: () => dialogs.openAdd('employee'),
  openBatch: () => { isBatchCreateDialogOpen.value = true },
  refresh,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="searchQuery"
    :search-placeholder="$t('Search employees by name, ref, role...')"
    :create-label="$t('New Employee')"
    hide-create-on-mobile
    show-filter
    hide-filter-on-mobile
    :active-filter-count="activeFilterCount"
    :total-items="totalItems"
    :loading="loading"
    @create="dialogs.openAdd('employee')"
    @open-filters="isFilterDialogOpen = true"
    @refresh="refresh"
  >
    <template #prepend>
      <VAutocomplete
        :model-value="filters.client_id"
        :label="$t('Filter by Client')"
        :items="clientOptions"
        density="compact"
        clearable
        style="inline-size: 210px;"
        class="me-2"
        @update:model-value="val => setFilter('client_id', val)"
      />
      <VSelect
        :model-value="filters.status"
        :label="$t('Filter by Status')"
        :items="statusOptions"
        density="compact"
        clearable
        style="inline-size: 170px;"
        class="me-2"
        @update:model-value="val => setFilter('status', val)"
      />
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-users-plus"
        class="me-2 d-none d-sm-inline-flex"
        @click="isBatchCreateDialogOpen = true"
      >
        {{ $t('Batch Import') }}
      </VBtn>
    </template>

    <template #filter-chips>
      <WpActiveFilters
        v-if="activeFilters.length"
        :filters="activeFilters"
        @remove="removeFilter"
        @clear-all="resetFilters"
      />
    </template>
  </WpPageToolbar>

  <!-- 👉 Bulk Actions Bar -->
  <div
    v-if="selectedIds?.length"
    class="px-4 pb-2"
  >
    <WpBulkActions
      :selected-count="selectedIds?.length || 0"
      @clear="clearSelection"
      @delete="handleBulkDelete"
    />
  </div>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    v-model:selected="selectedIds"
    :headers="headers"
    :items="items"
    :total-items="totalItems"
    :loading="loading"
    :error="error"
    show-select
    item-value="id"
    @retry="refresh"
  >
    <!-- Employee Name column -->
    <template #item.full_name="{ item }">
      <div class="d-flex align-center gap-3 py-2">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
          size="36"
        >
          <VIcon
            icon="tabler-user"
            size="20"
          />
        </VAvatar>
        <div class="d-flex flex-column">
          <span
            class="font-weight-medium text-high-emphasis cursor-pointer text-link"
            @click="handleView(item)"
          >
            {{ item.full_name }}
          </span>
          <span
            v-if="item.job_title"
            class="text-caption text-disabled"
          >
            {{ item.job_title }}
          </span>
        </div>
      </div>
    </template>

    <!-- Ref column -->
    <template #item.employee_ref="{ item }">
      <span class="font-mono text-caption">
        {{ item.employee_ref || '—' }}
      </span>
    </template>

    <!-- Client column -->
    <template #item.client="{ item }">
      <span class="text-medium-emphasis">
        {{ item.client?.legal_name || item.client?.name || '—' }}
      </span>
    </template>

    <!-- Department / Role column -->
    <template #item.department="{ item }">
      <span class="text-caption text-medium-emphasis">
        {{ item.department || '—' }}
      </span>
    </template>

    <!-- Assigned Models count -->
    <template #item.model_assignments_count="{ item }">
      <div class="text-center">
        <VChip
          size="x-small"
          variant="tonal"
          color="info"
        >
          {{ item.model_assignments_count ?? item.assigned_models?.length ?? 0 }}
        </VChip>
      </div>
    </template>

    <!-- Issued Garments count -->
    <template #item.assignments_count="{ item }">
      <div class="text-center">
        <VChip
          size="x-small"
          variant="tonal"
          :color="(item.assignments_count || item.active_assignments?.length || 0) > 0 ? 'success' : 'secondary'"
        >
          {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }}
        </VChip>
      </div>
    </template>

    <!-- Status column -->
    <template #item.status="{ item }">
      <VChip
        :color="statusMap[item.status] || 'secondary'"
        size="small"
        label
        class="text-capitalize font-weight-medium"
      >
        {{ item.status || 'active' }}
      </VChip>
    </template>

    <!-- Actions column -->
    <template #item.actions="{ item }">
      <WpRowActions
        :actions="getRowActions()"
        @view="handleView(item)"
        @edit="handleEdit(item)"
        @delete="handleDelete(item)"
      />
    </template>

    <!-- Mobile Card slot -->
    <template #mobile-card="{ item }">
      <WpMobileCard
        :title="item.full_name"
        :subtitle="item.employee_ref || item.job_title || $t('Employee')"
        icon="tabler-user"
        :status="item.status || 'active'"
        :status-color="statusMap[item.status] || 'secondary'"
        @click="handleView(item)"
      >
        <template #badges>
          <VChip
            size="x-small"
            variant="tonal"
          >
            {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }} {{ $t('garments') }}
          </VChip>
        </template>

        <template #details>
          <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
            <div v-if="item.client">
              <VIcon
                icon="tabler-building-store"
                size="14"
                class="me-1"
              />
              {{ item.client.legal_name || item.client.name }}
            </div>
            <div v-if="item.department">
              <VIcon
                icon="tabler-briefcase"
                size="14"
                class="me-1"
              />
              {{ item.department }}
            </div>
          </div>
        </template>

        <template #actions>
          <WpRowActions
            :actions="getRowActions()"
            @view="handleView(item)"
            @edit="handleEdit(item)"
            @delete="handleDelete(item)"
          />
        </template>
      </WpMobileCard>
    </template>
  </WpResponsiveList>

  <!-- 👉 Filter Dialog -->
  <WpFilterDialog
    v-model:is-open="isFilterDialogOpen"
    :active-count="activeFilterCount"
    @apply="applyStaged"
    @reset="resetStaged"
  >
    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <VAutocomplete
          v-model="stagedFilters.client_id"
          :label="$t('Client')"
          :items="clientOptions"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VSelect
          v-model="stagedFilters.status"
          :label="$t('Status')"
          :items="statusOptions"
          clearable
        />
      </VCol>
    </VRow>
  </WpFilterDialog>

  <!-- 👉 Dialogs -->
  <AddNewEmployeeDialog
    v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
    @employee-created="refresh"
    @add-employee="refresh"
  />

  <BatchCreateEmployeesDialog
    v-model:is-dialog-visible="isBatchCreateDialogOpen"
    @employees-created="refresh"
  />

  <ViewEmployeeDialog
    v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
    :employee="dialogs.activeEntity.value"
    :employee-id="dialogs.activeEntity.value?.id"
  />

  <WpDeleteDialog
    v-model:is-open="dialogs.deleteDialog.isOpen.value"
    :title="$t('Delete Workwear Employee')"
    :message="$t('Are you sure you want to delete employee {name}? All issued garments and assignment history will be unlinked.', { name: dialogs.activeEntity.value?.full_name || '' })"
    :loading="actions.isDeleting.value"
    @confirm="handleConfirmDelete"
  />
</template>
