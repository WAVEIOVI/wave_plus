<script setup>
/* eslint-disable camelcase */
import {
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { $api } from '@/utils/api'
import AddNewEmployeeDialog from '@/views/client-assets/workwear/employees/list/AddNewEmployeeDialog.vue'
import BatchCreateEmployeesDialog from '@/views/client-assets/workwear/employees/list/BatchCreateEmployeesDialog.vue'
import DeleteEmployeeDialog from '@/views/client-assets/workwear/employees/list/DeleteEmployeeDialog.vue'
import ViewEmployeeDialog from '@/views/client-assets/workwear/employees/list/ViewEmployeeDialog.vue'
import { refDebounced } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const router = useRouter()

// ── Search & Filter ────────────────────────────────────────────────────────
const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 500)
const selectedStatus = ref()
const selectedClientId = ref()
const clientOptions = ref([])

const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()

const headers = computed(() => [
  { title: t('Employee Name'), key: 'full_name', sortable: false },
  { title: t('Ref'), key: 'employee_ref', sortable: true },
  { title: t('Client'), key: 'client', sortable: false },
  { title: t('Department / Role'), key: 'department', sortable: true },
  { title: t('Assigned Models'), key: 'model_assignments_count', sortable: false, align: 'center' },
  { title: t('Issued Garments'), key: 'assignments_count', sortable: false, align: 'center' },
  { title: t('Status'), key: 'status', sortable: true },
  { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
])

const statusMap = {
  active: 'success',
  inactive: 'secondary',
  terminated: 'error',
}

const statusOptions = Object.keys(statusMap).map(s => ({
  title: s.replace(/\b\w/g, l => l.toUpperCase()),
  value: s,
}))

onMounted(async () => {
  try {
    const res = await $api('/bp/clients/options', { params: { limit: 150 } })

    clientOptions.value = (res.data || []).map(c => ({ title: c.legal_name, value: c.id }))
  } catch (err) {
    console.error('Failed to fetch clients filter:', err)
  }
})

const fetchData = async () => {
  await store.fetchWorkwearEmployees({
    q: debouncedSearch.value,
    'filter[status]': selectedStatus.value || undefined,
    'filter[client_id]': selectedClientId.value || undefined,
    itemsPerPage: itemsPerPage.value,
    page: page.value,
    sortBy: sortBy.value,
  })
}

watch([debouncedSearch, selectedStatus, selectedClientId, itemsPerPage, page, sortBy], () => fetchData(), { immediate: true })

// ── Dialogs ────────────────────────────────────────────────────────────────
const isAddNewDialogOpen = ref(false)
const isBatchCreateDialogOpen = ref(false)
const isViewDialogOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const selectedEmployee = ref(null)

const handleView = emp => {
  selectedEmployee.value = emp
  isViewDialogOpen.value = true
}

const handleEdit = emp => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: emp.id }, query: { type: 'employee' } })
}

const handleDelete = emp => {
  selectedEmployee.value = emp
  isDeleteDialogOpen.value = true
}

const confirmDelete = async () => {
  if (!selectedEmployee.value?.id) return
  try {
    await store.deleteWorkwearEmployee(selectedEmployee.value.id)
    snackbarStore.showMessage(t('Workwear employee deleted successfully'), 'success')
    fetchData()
  } catch (e) {
    snackbarStore.showMessage(e?.data?.message || t('Failed to delete employee'), 'error')
  }
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Profile'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

defineExpose({
  openAdd: () => { isAddNewDialogOpen.value = true },
  openBatch: () => { isBatchCreateDialogOpen.value = true },
  refresh: fetchData,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="searchQuery"
    :search-placeholder="$t('Search employees by name, ref, role...')"
    :create-label="$t('New Employee')"
    hide-create-on-mobile
    :total-items="store.totalWorkwearEmployees"
    :loading="store.loading"
    @create="isAddNewDialogOpen = true"
    @refresh="fetchData"
  >
    <template #prepend>
      <VAutocomplete
        v-model="selectedClientId"
        :label="$t('Filter by Client')"
        :items="clientOptions"
        density="compact"
        clearable
        style="inline-size: 210px;"
        class="me-2"
      />
      <VSelect
        v-model="selectedStatus"
        :label="$t('Filter by Status')"
        :items="statusOptions"
        density="compact"
        clearable
        style="inline-size: 170px;"
        class="me-2"
      />
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-users-plus"
        class="me-2 d-none d-sm-inline-flex"
        @click="isBatchCreateDialogOpen = true"
      >
        {{ $t('Batch Enroll') }}
      </VBtn>
    </template>
  </WpPageToolbar>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    :headers="headers"
    :items="store.workwearEmployees"
    :total-items="store.totalWorkwearEmployees"
    :loading="store.loading"
    item-value="id"
    @retry="fetchData"
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
            {{ item.first_name }} {{ item.last_name }}
          </span>
          <span class="text-caption text-disabled">
            {{ item.email || item.phone || $t('No Contact Info') }}
          </span>
        </div>
      </div>
    </template>

    <!-- Employee Ref column -->
    <template #item.employee_ref="{ item }">
      <span class="font-weight-medium py-1 d-block">
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
      <div class="d-flex flex-column py-1">
        <span>{{ item.department || '—' }}</span>
        <span
          v-if="item.role || item.position"
          class="text-caption text-medium-emphasis"
        >
          {{ item.role || item.position }}
        </span>
      </div>
    </template>

    <!-- Assigned Models (Entitlements) column -->
    <template #item.model_assignments_count="{ item }">
      <div class="text-center">
        <VChip
          size="small"
          variant="tonal"
          color="info"
        >
          {{ item.active_model_assignments_count ?? item.model_assignments?.length ?? 0 }}
        </VChip>
      </div>
    </template>

    <!-- Active Issued Garments column -->
    <template #item.assignments_count="{ item }">
      <div class="text-center">
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ item.assignments_count ?? item.active_assignments_count ?? 0 }}
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
        :title="`${item.first_name || ''} ${item.last_name || ''}`"
        :subtitle="item.employee_ref"
        icon="tabler-user"
        :status="item.status"
        :status-color="statusMap[item.status] || 'secondary'"
        @click="handleView(item)"
      >
        <template #badges>
          <VChip
            size="x-small"
            variant="tonal"
          >
            {{ item.assignments_count ?? 0 }} {{ $t('assigned') }}
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

  <!-- 👉 Dialogs -->
  <AddNewEmployeeDialog
    v-model:is-dialog-visible="isAddNewDialogOpen"
    @employee-created="fetchData"
    @add-employee="fetchData"
  />
  <BatchCreateEmployeesDialog
    v-model:is-dialog-visible="isBatchCreateDialogOpen"
    @employees-created="fetchData"
  />
  <ViewEmployeeDialog
    v-model:is-dialog-visible="isViewDialogOpen"
    :employee="selectedEmployee"
  />
  <DeleteEmployeeDialog
    v-model:is-dialog-visible="isDeleteDialogOpen"
    @delete-employee="confirmDelete"
  />
</template>
