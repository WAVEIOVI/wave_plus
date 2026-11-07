<script setup>
/* eslint-disable camelcase */
import { AppHybridTabs, WpActiveFilters,
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions, } from '@/components/shared'
import { useWorkwearEmployeeList } from '@/composables/client-assets/workwear/useWorkwearEmployeeList'
import { useWorkwearItemList } from '@/composables/client-assets/workwear/useWorkwearItemList'
import {
  resolveColorHex,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import IssueEmployeeGarmentsDialog from '@/views/client-assets/workwear/employees/components/IssueEmployeeGarmentsDialog.vue'
import ViewEmployeeDialog from '@/views/client-assets/workwear/employees/list/ViewEmployeeDialog.vue'
import AssignWorkwearDialog from '@/views/client-assets/workwear/items/list/AssignWorkwearDialog.vue'
import ReturnWorkwearDialog from '@/views/client-assets/workwear/items/list/ReturnWorkwearDialog.vue'
import ViewItemDialog from '@/views/client-assets/workwear/items/list/ViewItemDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t, locale } = useI18n()
const router = useRouter()
const store = useClientAssetStore()

// Two tracking modes inside Assignments & Tracking tab:
// 1. "items" — Track & Assign Workwear Items (Available vs Assigned to Employee)
// 2. "employees" — Track Client Employees & Issue Workwear Items
const trackingView = ref('items')

const trackingTabs = computed(() => [
  {
    value: 'items',
    title: t('Track & Assign Workwear Items'),
    icon: 'tabler-hanger',
    badge: itemList.totalItems.value || undefined,
  },
  {
    value: 'employees',
    title: t('Track Client Employees & Allocations'),
    icon: 'tabler-users',
    badge: employeeList.totalItems.value || undefined,
  },
])

// ── 1. Workwear Items Tracking List ─────────────────────────────────────────
const itemList = useWorkwearItemList()
const isItemFilterOpen = ref(false)
const isAssignItemDialogOpen = ref(false)
const isReturnItemDialogOpen = ref(false)
const isViewItemDialogOpen = ref(false)
const selectedItem = ref(null)
const itemsToAssign = ref([])

const itemFilterLabels = computed(() => ({
  client_id: t('Client'),
  workwear_model_id: t('Workwear Model'),
  status: t('Status'),
  item_condition: t('Condition'),
}))

const itemFilterFormatters = computed(() => ({
  client_id: val => itemList.clientOptions.value.find(c => c.value === val)?.title || val,
  workwear_model_id: val => itemList.modelOptions.value.find(m => m.value === val)?.title || val,
  status: val => itemList.statusOptions.find(s => s.value === val)?.title || val,
  item_condition: val => itemList.conditionOptions.find(c => c.value === val)?.title || val,
}))

const assignmentItemHeaders = computed(() => [
  { title: t('Workwear Item Code'), key: 'item_code', sortable: true },
  { title: t('Workwear Model & Client'), key: 'workwear_model', sortable: false },
  { title: t('Garment Type & Size'), key: 'size', sortable: false },
  { title: t('Assignment Status'), key: 'status', sortable: true },
  { title: t('Assigned Client Employee'), key: 'current_assignment', sortable: false },
  { title: t('Handover Action'), key: 'handover', sortable: false, align: 'center' },
  { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
])

const isAssignable = item => ['in_stock', 'available', 'returned'].includes(item?.status)

const resolveItemGarmentType = item => {
  const model = item.model || item.workwear_model
  if (model) {
    return resolveSilhouetteDisplay(model, { locale: locale.value, t }).itemTitle
  }

  return (item.garment_type || '').replace(/_/g, ' ') || '—'
}

const openQuickAssignDialog = () => {
  const available = (itemList.items.value || []).filter(isAssignable)

  itemsToAssign.value = available.slice(0, 1)
  isAssignItemDialogOpen.value = true
}

const handleAssignSingleItem = item => {
  itemsToAssign.value = [item]
  isAssignItemDialogOpen.value = true
}

const handleReturnSingleItem = item => {
  selectedItem.value = item
  isReturnItemDialogOpen.value = true
}

const handleViewItem = item => {
  selectedItem.value = item
  isViewItemDialogOpen.value = true
}

const getItemRowActions = item => {
  const actions = [
    { label: t('View Item Dossier'), icon: 'tabler-eye', emit: 'view' },
    { label: t('Edit Item'), icon: 'tabler-pencil', emit: 'edit' },
  ]
  if (isAssignable(item)) {
    actions.push({ label: t('Assign to Employee'), icon: 'tabler-user-plus', emit: 'assign', color: 'primary' })
  } else if (item?.status === 'assigned') {
    actions.push({ label: t('Return / Inspect'), icon: 'tabler-arrow-back-up', emit: 'return', color: 'warning' })
  }

  return actions
}

// ── 2. Client Employees Tracking List ───────────────────────────────────────
const employeeList = useWorkwearEmployeeList()
const isEmployeeFilterOpen = ref(false)
const isViewEmployeeDialogOpen = ref(false)
const selectedEmployee = ref(null)

// Direct issue dialog for an employee from the employee tracking table
const isEmployeeIssueOpen = ref(false)
const isIssuingToEmployee = ref(false)
const targetEmployeeForIssue = ref(null)
const employeeClientModels = ref([])
const employeeAvailableItems = ref([])
const employeeIssueForm = ref({
  model_id: null,
  item_ids: [],
  condition_at_assign: 'good',
  notes: '',
})

const filteredEmployeeAvailableItems = computed(() => {
  const list = employeeAvailableItems.value || []
  const filtered = employeeIssueForm.value.model_id
    ? list.filter(i => String(i.model_id) === String(employeeIssueForm.value.model_id))
    : list

  return filtered.map(i => ({
    title: `${i.item_code} — ${i.model?.name || i.workwear_model?.name || i.garment_type || ''} (${i.size || 'STD'}${i.color ? `, ${i.color}` : ''})`,
    value: i.id,
  }))
})

const openIssueForEmployee = async emp => {
  targetEmployeeForIssue.value = emp
  employeeIssueForm.value = {
    model_id: null,
    item_ids: [],
    condition_at_assign: 'good',
    notes: '',
  }
  isEmployeeIssueOpen.value = true

  try {
    const [modelsRes, itemsRes] = await Promise.all([
      store.fetchWorkwearModels({ 'filter[client_id]': emp.client_id, itemsPerPage: -1 }),
      store.fetchWorkwearItems({ 'filter[client_id]': emp.client_id, itemsPerPage: 100 }),
    ])

    employeeClientModels.value = modelsRes.data || []
    employeeAvailableItems.value = (itemsRes.data || []).filter(isAssignable)
  } catch (err) {
    console.error('Failed to load client items for employee assignment:', err)
  }
}

const confirmIssueToEmployee = async () => {
  if (!targetEmployeeForIssue.value?.id || !employeeIssueForm.value.item_ids?.length) return
  isIssuingToEmployee.value = true
  try {
    await store.assignWorkwearItems({
      employee_id: targetEmployeeForIssue.value.id,
      item_ids: employeeIssueForm.value.item_ids,
      condition_at_assign: employeeIssueForm.value.condition_at_assign,
      notes: employeeIssueForm.value.notes || null,
    })
    isEmployeeIssueOpen.value = false
    await Promise.all([employeeList.refresh(), itemList.refresh()])
  } finally {
    isIssuingToEmployee.value = false
  }
}

const refreshAll = async () => {
  await Promise.all([itemList.refresh(), employeeList.refresh()])
}

onMounted(() => {
  itemList.loadFilterOptions()
  employeeList.loadFilterOptions()
})

defineExpose({
  openAssign: openQuickAssignDialog,
  refresh: refreshAll,
})
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- 👉 Sub-navigation between Item Handover Tracking and Employee Allocation Tracking -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3">
      <AppHybridTabs
        v-model="trackingView"
        :tabs="trackingTabs"
        density="compact"
      />

      <div class="d-flex align-center gap-2">
        <VChip
          size="small"
          color="success"
          variant="tonal"
          prepend-icon="tabler-shield-check"
        >
          {{ $t('Workwear Item ↔ Workwear Model ↔ Client Employee') }}
        </VChip>
      </div>
    </div>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <!-- VIEW 1: TRACK & ASSIGN WORKWEAR ITEMS                                 -->
    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <template v-if="trackingView === 'items'">
      <WpPageToolbar
        v-model:search="itemList.search.value"
        :search-placeholder="$t('Search workwear items by code, size, color...')"
        :create-label="$t('Assign Workwear Item')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="itemList.activeFilterCount.value"
        :total-items="itemList.totalItems.value"
        :loading="itemList.loading.value"
        @create="openQuickAssignDialog"
        @open-filters="isItemFilterOpen = true"
        @refresh="itemList.refresh"
      >
        <template #prepend>
          <VBtnToggle
            :model-value="itemList.filters.status"
            density="compact"
            variant="outlined"
            color="primary"
            divided
            class="me-2 d-none d-sm-inline-flex"
            @update:model-value="val => itemList.setFilter('status', val || null)"
          >
            <VBtn
              :value="null"
              size="small"
            >
              {{ $t('All Items') }}
            </VBtn>
            <VBtn
              value="in_stock"
              size="small"
            >
              {{ $t('Available') }}
            </VBtn>
            <VBtn
              value="assigned"
              size="small"
            >
              {{ $t('Assigned') }}
            </VBtn>
          </VBtnToggle>
        </template>

        <template #filter-chips>
          <WpActiveFilters
            v-if="itemList.activeFilters.value.length"
            :filters="itemList.activeFilters.value"
            :labels="itemFilterLabels"
            :formatters="itemFilterFormatters"
            @remove="f => itemList.removeFilter(f.key || f)"
            @clear-all="itemList.resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        v-model:page="itemList.page.value"
        v-model:items-per-page="itemList.itemsPerPage.value"
        v-model:sort-by="itemList.sortBy.value"
        :headers="assignmentItemHeaders"
        :items="itemList.items.value"
        :total-items="itemList.totalItems.value"
        :loading="itemList.loading.value"
        :error="itemList.error.value"
        :show-select="false"
        item-value="id"
        @retry="itemList.refresh"
      >
        <!-- Item Code -->
        <template #item.item_code="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              :color="item.status === 'assigned' ? 'info' : 'success'"
              variant="tonal"
              rounded
              size="36"
            >
              <VIcon
                :icon="item.status === 'assigned' ? 'tabler-user-check' : 'tabler-hanger'"
                size="20"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span
                class="font-weight-bold font-mono text-high-emphasis cursor-pointer text-link"
                @click="handleViewItem(item)"
              >
                {{ item.item_code }}
              </span>
              <span class="text-caption text-disabled">
                {{ item.model?.model_ref || item.workwear_model?.model_ref || '—' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Workwear Model & Client -->
        <template #item.workwear_model="{ item }">
          <div class="py-1">
            <span class="font-weight-medium d-block">
              {{ item.model?.name || item.workwear_model?.name || item.model_name || '—' }}
            </span>
            <span class="text-caption text-disabled">
              {{ item.client?.legal_name || item.client?.name || '—' }}
            </span>
          </div>
        </template>

        <!-- Garment Type & Size -->
        <template #item.size="{ item }">
          <div class="d-flex align-center flex-wrap gap-1">
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ resolveItemGarmentType(item) }}
            </VChip>
            <VChip
              v-if="item.size"
              size="x-small"
              variant="outlined"
              class="font-mono font-weight-bold"
            >
              {{ item.size }}
            </VChip>
            <VChip
              v-if="item.color"
              size="x-small"
              variant="tonal"
              class="text-capitalize"
            >
              <span
                class="d-inline-block rounded-circle border me-1"
                :style="{ inlineSize: '10px', blockSize: '10px', backgroundColor: resolveColorHex(item.color) }"
              />
              {{ item.color }}
            </VChip>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="itemList.statusMap[item.status] || 'secondary'"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ (item.status || 'in_stock').replace(/_/g, ' ') }}
          </VChip>
        </template>

        <!-- Assigned Client Employee -->
        <template #item.current_assignment="{ item }">
          <div
            v-if="item.assigned_to_employee || item.current_assignment?.employee"
            class="d-flex align-center gap-2"
          >
            <VAvatar
              size="28"
              color="info"
              variant="tonal"
            >
              <VIcon
                icon="tabler-user"
                size="16"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <RouterLink
                :to="{ name: 'client-assets-workwear-edit-id', params: { id: (item.assigned_to_employee || item.current_assignment?.employee).id }, query: { type: 'employee' } }"
                class="font-weight-medium text-high-emphasis text-decoration-none text-link"
              >
                {{ item.assigned_to_employee?.full_name || item.current_assignment?.employee?.full_name }}
              </RouterLink>
              <span
                v-if="item.current_assignment?.assigned_at"
                class="text-caption text-disabled"
              >
                {{ new Date(item.current_assignment.assigned_at).toLocaleDateString(locale) }}
              </span>
            </div>
          </div>
          <span
            v-else
            class="text-caption text-medium-emphasis"
          >
            {{ $t('Available in Stock') }}
          </span>
        </template>

        <!-- Direct Handover Action Button -->
        <template #item.handover="{ item }">
          <VBtn
            v-if="isAssignable(item)"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-plus"
            @click="handleAssignSingleItem(item)"
          >
            {{ $t('Assign to Employee') }}
          </VBtn>
          <VBtn
            v-else-if="item.status === 'assigned'"
            size="small"
            color="warning"
            variant="tonal"
            prepend-icon="tabler-arrow-back-up"
            @click="handleReturnSingleItem(item)"
          >
            {{ $t('Return / Inspect') }}
          </VBtn>
          <span
            v-else
            class="text-caption text-disabled"
          >—</span>
        </template>

        <!-- Row Actions -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getItemRowActions(item)"
            @view="handleViewItem(item)"
            @edit="router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'item' } })"
            @assign="handleAssignSingleItem(item)"
            @return="handleReturnSingleItem(item)"
          />
        </template>

        <!-- Mobile Card -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.item_code"
            :subtitle="item.model?.name || item.workwear_model?.name || $t('Workwear Item')"
            icon="tabler-hanger"
            :status="(item.status || 'in_stock').replace(/_/g, ' ')"
            :status-color="itemList.statusMap[item.status] || 'secondary'"
            @click="handleViewItem(item)"
          >
            <template #badges>
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ resolveItemGarmentType(item) }}
              </VChip>
              <VChip
                v-if="item.size"
                size="x-small"
                variant="outlined"
                class="font-mono"
              >
                {{ item.size }}
              </VChip>
            </template>

            <template #details>
              <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
                <div v-if="item.assigned_to_employee || item.current_assignment?.employee">
                  <VIcon
                    icon="tabler-user-check"
                    size="14"
                    color="info"
                    class="me-1"
                  />
                  {{ item.assigned_to_employee?.full_name || item.current_assignment?.employee?.full_name }}
                </div>
                <div v-else>
                  <VIcon
                    icon="tabler-package"
                    size="14"
                    color="success"
                    class="me-1"
                  />
                  {{ $t('Available for assignment') }}
                </div>
              </div>
            </template>

            <template #actions>
              <VBtn
                v-if="isAssignable(item)"
                size="x-small"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-user-plus"
                @click.stop="handleAssignSingleItem(item)"
              >
                {{ $t('Assign') }}
              </VBtn>
              <VBtn
                v-else-if="item.status === 'assigned'"
                size="x-small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-arrow-back-up"
                @click.stop="handleReturnSingleItem(item)"
              >
                {{ $t('Return') }}
              </VBtn>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </template>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <!-- VIEW 2: TRACK CLIENT EMPLOYEES & WORKWEAR ALLOCATIONS                 -->
    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <template v-else>
      <WpPageToolbar
        v-model:search="employeeList.search.value"
        :search-placeholder="$t('Search employees by name, badge ref, department...')"
        show-filter
        hide-filter-on-mobile
        :active-filter-count="employeeList.activeFilterCount.value"
        :total-items="employeeList.totalItems.value"
        :loading="employeeList.loading.value"
        @open-filters="isEmployeeFilterOpen = true"
        @refresh="employeeList.refresh"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="employeeList.activeFilters.value.length"
            :filters="employeeList.activeFilters.value"
            @remove="f => employeeList.removeFilter(f.key || f)"
            @clear-all="employeeList.resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        v-model:page="employeeList.page.value"
        v-model:items-per-page="employeeList.itemsPerPage.value"
        v-model:sort-by="employeeList.sortBy.value"
        :headers="employeeList.headers.value"
        :items="employeeList.items.value"
        :total-items="employeeList.totalItems.value"
        :loading="employeeList.loading.value"
        :error="employeeList.error.value"
        :show-select="false"
        item-value="id"
        @retry="employeeList.refresh"
      >
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
                @click="selectedEmployee = item; isViewEmployeeDialogOpen = true"
              >
                {{ item.full_name || `${item.first_name || ''} ${item.last_name || ''}` }}
              </span>
              <span class="text-caption text-disabled">
                {{ item.position || item.department || '—' }}
              </span>
            </div>
          </div>
        </template>

        <template #item.employee_ref="{ item }">
          <span class="font-mono text-caption font-weight-medium">{{ item.employee_ref || '—' }}</span>
        </template>

        <template #item.client="{ item }">
          <span class="text-medium-emphasis font-weight-medium">
            {{ item.client?.legal_name || item.client?.name || '—' }}
          </span>
        </template>

        <template #item.department="{ item }">
          <span class="text-body-2">{{ item.department || '—' }}</span>
        </template>

        <template #item.sizes="{ item }">
          <div
            v-if="item.sizes && Object.values(item.sizes).some(Boolean)"
            class="d-flex flex-wrap gap-1"
          >
            <VChip
              v-if="item.sizes.top"
              size="x-small"
              color="primary"
              variant="tonal"
              class="font-mono"
            >
              {{ $t('Top:') }} {{ item.sizes.top }}
            </VChip>
            <VChip
              v-if="item.sizes.shoes"
              size="x-small"
              color="secondary"
              variant="tonal"
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

        <template #item.assignments_count="{ item }">
          <div class="d-flex align-center justify-center gap-2">
            <VChip
              size="small"
              variant="tonal"
              :color="(item.assignments_count || item.active_assignments?.length || 0) > 0 ? 'success' : 'secondary'"
            >
              {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }} {{ $t('items') }}
            </VChip>
            <VBtn
              size="x-small"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-plus"
              @click="openIssueForEmployee(item)"
            >
              {{ $t('Assign Item') }}
            </VBtn>
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="employeeList.statusMap[item.status] || 'secondary'"
            size="small"
            label
            class="text-capitalize"
          >
            {{ $t(item.status || 'active') }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <WpRowActions
            :actions="[
              { label: t('View Employee Dossier'), icon: 'tabler-eye', emit: 'view' },
              { label: t('Assign Workwear Item'), icon: 'tabler-package-export', emit: 'issue', color: 'primary' },
              { label: t('Edit Employee'), icon: 'tabler-pencil', emit: 'edit' },
            ]"
            @view="selectedEmployee = item; isViewEmployeeDialogOpen = true"
            @issue="openIssueForEmployee(item)"
            @edit="router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'employee' } })"
          />
        </template>

        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.full_name || `${item.first_name || ''} ${item.last_name || ''}`"
            :subtitle="item.employee_ref || item.department || $t('Employee')"
            icon="tabler-user"
            :status="item.status || 'active'"
            :status-color="employeeList.statusMap[item.status] || 'secondary'"
            @click="selectedEmployee = item; isViewEmployeeDialogOpen = true"
          >
            <template #badges>
              <VChip
                size="x-small"
                color="success"
                variant="tonal"
              >
                {{ item.assignments_count ?? item.active_assignments?.length ?? 0 }} {{ $t('items') }}
              </VChip>
            </template>
            <template #actions>
              <VBtn
                size="x-small"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click.stop="openIssueForEmployee(item)"
              >
                {{ $t('Assign Item') }}
              </VBtn>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </template>

    <!-- 👉 Item Filter Dialog -->
    <WpFilterDialog
      v-model:is-open="isItemFilterOpen"
      :active-count="itemList.activeFilterCount.value"
      @apply="itemList.applyStaged"
      @reset="itemList.resetStaged"
    >
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            v-model="itemList.stagedFilters.client_id"
            :label="$t('Client')"
            :items="itemList.clientOptions.value"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            v-model="itemList.stagedFilters.workwear_model_id"
            :label="$t('Workwear Model')"
            :items="itemList.modelOptions.value"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="itemList.stagedFilters.status"
            :label="$t('Status')"
            :items="itemList.statusOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="itemList.stagedFilters.item_condition"
            :label="$t('Condition')"
            :items="itemList.conditionOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Employee Filter Dialog -->
    <WpFilterDialog
      v-model:is-open="isEmployeeFilterOpen"
      :active-count="employeeList.activeFilterCount.value"
      @apply="employeeList.applyStaged"
      @reset="employeeList.resetStaged"
    >
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            v-model="employeeList.stagedFilters.client_id"
            :label="$t('Client')"
            :items="employeeList.clientOptions.value"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="employeeList.stagedFilters.status"
            :label="$t('Status')"
            :items="employeeList.statusOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Handover & Dossier Dialogs -->
    <AssignWorkwearDialog
      v-model:is-dialog-visible="isAssignItemDialogOpen"
      :items="itemsToAssign"
      @assigned="refreshAll"
    />

    <ReturnWorkwearDialog
      v-model:is-dialog-visible="isReturnItemDialogOpen"
      :item="selectedItem"
      @returned="refreshAll"
    />

    <IssueEmployeeGarmentsDialog
      v-model="isEmployeeIssueOpen"
      :issue-form="employeeIssueForm"
      :client-models="employeeClientModels"
      :filtered-available-items="filteredEmployeeAvailableItems"
      :loading="isIssuingToEmployee"
      @submit="confirmIssueToEmployee"
    />

    <ViewItemDialog
      v-model:is-dialog-visible="isViewItemDialogOpen"
      :item="selectedItem"
      :item-id="selectedItem?.id"
    />

    <ViewEmployeeDialog
      v-model:is-dialog-visible="isViewEmployeeDialogOpen"
      :employee="selectedEmployee"
      :employee-id="selectedEmployee?.id"
    />
  </div>
</template>
