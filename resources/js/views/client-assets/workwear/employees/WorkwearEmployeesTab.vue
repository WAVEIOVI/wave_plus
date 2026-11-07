<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
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
import { computed, onMounted, ref } from 'vue'
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
  search,
  stagedFilters,
  activeFilters,
  activeFilterCount,
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
  loadFilterOptions,
} = useWorkwearEmployeeList()

const isFilterDialogOpen = ref(false)

const filterLabels = computed(() => ({
  client_id: t('Client'),
  status: t('Status'),
}))

const filterFormatters = computed(() => ({
  client_id: val => clientOptions.value.find(c => c.value === val)?.title || val,
  status: val => statusOptions.find(s => s.value === val)?.title || val,
}))

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

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Profile & Issue Items'), icon: 'tabler-pencil', emit: 'edit' },
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
    v-model:search="search"
    :search-placeholder="$t('Search employees by name, ref, department, role...')"
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
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-users-plus"
        class="me-2"
        @click="isBatchCreateDialogOpen = true"
      >
        {{ $t('Batch Create Employees') }}
      </VBtn>
    </template>

    <template #filter-chips>
      <WpActiveFilters
        v-if="activeFilters.length"
        :filters="activeFilters"
        :labels="filterLabels"
        :formatters="filterFormatters"
        @remove="f => removeFilter(f.key || f)"
        @clear-all="resetFilters"
      />
    </template>
  </WpPageToolbar>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    :headers="headers"
    :items="items"
    :total-items="totalItems"
    :loading="loading"
    :error="error"
    :show-select="false"
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
            {{ item.full_name || `${item.first_name || ''} ${item.last_name || ''}` }}
          </span>
          <span
            v-if="item.position || item.role"
            class="text-caption text-disabled"
          >
            {{ item.position || item.role }}
          </span>
        </div>
      </div>
    </template>

    <!-- Ref column -->
    <template #item.employee_ref="{ item }">
      <span class="font-mono text-caption font-weight-medium">
        {{ item.employee_ref || '—' }}
      </span>
    </template>

    <!-- Client column -->
    <template #item.client="{ item }">
      <span class="text-medium-emphasis font-weight-medium">
        {{ item.client?.legal_name || item.client?.name || '—' }}
      </span>
    </template>

    <!-- Department & Contact column -->
    <template #item.department="{ item }">
      <div class="d-flex flex-column gap-1 py-1">
        <span
          v-if="item.department"
          class="text-body-2 font-weight-medium"
        >
          {{ item.department }}
        </span>
        <div
          v-if="item.email || item.phone"
          class="d-flex align-center gap-2 text-caption text-medium-emphasis"
        >
          <span v-if="item.email">{{ item.email }}</span>
          <span v-if="item.phone">{{ item.phone }}</span>
        </div>
        <span
          v-if="!item.department && !item.email && !item.phone"
          class="text-disabled"
        >—</span>
      </div>
    </template>

    <!-- Sizes Summary column -->
    <template #item.sizes="{ item }">
      <div
        v-if="item.sizes && Object.values(item.sizes).some(Boolean)"
        class="d-flex flex-wrap gap-1"
      >
        <VChip
          v-if="item.sizes.top"
          size="x-small"
          variant="tonal"
          color="primary"
          class="font-mono"
        >
          {{ $t('Top:') }} {{ item.sizes.top }}
        </VChip>
        <VChip
          v-if="item.sizes.bottom"
          size="x-small"
          variant="tonal"
          color="info"
          class="font-mono"
        >
          {{ $t('Bottom:') }} {{ item.sizes.bottom }}
        </VChip>
        <VChip
          v-if="item.sizes.shoes"
          size="x-small"
          variant="tonal"
          color="secondary"
          class="font-mono"
        >
          {{ $t('Shoes:') }} {{ item.sizes.shoes }}
        </VChip>
      </div>
      <span
        v-else
        class="text-caption text-disabled"
      >—</span>
    </template>

    <!-- Issued Garments count -->
    <template #item.assignments_count="{ item }">
      <div class="text-center">
        <VChip
          size="small"
          variant="tonal"
          :color="(item.assignments_count || item.active_assignments?.length || 0) > 0 ? 'success' : 'secondary'"
        >
          {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }} {{ $t('items') }}
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
        {{ $t(item.status || 'active') }}
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
        :title="item.full_name || `${item.first_name || ''} ${item.last_name || ''}`"
        :subtitle="item.employee_ref || item.position || $t('Employee')"
        icon="tabler-user"
        :status="item.status || 'active'"
        :status-color="statusMap[item.status] || 'secondary'"
        @click="handleView(item)"
      >
        <template #badges>
          <VChip
            size="x-small"
            variant="tonal"
            color="success"
          >
            {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }} {{ $t('items') }}
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
