# Fleet Frontend Standardization — BP Pattern Implementation

**Date:** 2026-11-05  
**Objective:** Standardize Fleet UI/UX to match Business Partners reference implementation

## Executive Summary

This document provides a comprehensive audit and refactoring plan to align the Fleet module frontend with Business Partners (BP) standards, ensuring a consistent WAVE PLUS user experience.

## Audit Results

### Current Fleet Structure

```
resources/js/views/people-skills/fleet/
├── drivers/
│   ├── ApprovedDriversListPage.vue
│   ├── DriverDetailDialog.vue
│   ├── DriverIncidentDialog.vue
│   └── [other driver components]
└── vehicles/
    ├── FleetVehiclesListPage.vue
    ├── VehicleDetailDialog.vue
    ├── tabs/
    │   ├── VehicleTabAssignments.vue
    │   ├── VehicleTabDocuments.vue
    │   ├── VehicleTabFuel.vue
    │   ├── VehicleTabMaintenance.vue
    │   └── VehicleTabChecklists.vue
    └── [other vehicle components]
```

### BP Reference Structure

```
resources/js/views/business-partners/
├── suppliers/
│   └── list/
│       ├── AddNewSupplierDialog.vue
│       ├── DeleteSupplierDialog.vue
│       └── ViewSupplierDialog.vue
├── shared-components/
│   ├── components/
│   └── dialogs/
└── [similar structure for clients, service-providers]
```

### Shared Components Available

**Dialogs:**
- `WpFormDialog.vue` — Standard form dialog with header, actions, validation
- `WpDetailDialog.vue` — Entity detail view dialog
- `WpDeleteDialog.vue` — Confirmation dialog for deletions
- `WpConfirmDialog.vue` — General confirmation dialog
- `WpUnsavedChangesDialog.vue` — Warns about unsaved changes

**Data Display:**
- `WpDataTable.vue` — Full-featured data table with sorting, selection
- `WpDataTableCard.vue` — Card wrapper for tables
- `WpResponsiveList.vue` — Desktop table / mobile card auto-switching
- `WpMobileCard.vue` — Mobile-optimized card layout
- `WpEntityCard.vue` — Reusable entity card component
- `WpEntityCardGrid.vue` — Grid layout for entity cards
- `WpRowActions.vue` — Action menu for table rows
- `WpStatusChip.vue` — Consistent status badge styling
- `WpPagination.vue` — Pagination controls

**Composables:**
- `useListPage()` — List management with filters, sorting, pagination
- `useSelection()` — Row selection management
- `useBusinessPartnerListPage()` — BP-specific list page wrapper (reference pattern)

## Discrepancies Identified

### 1. List Page Patterns

**Current Fleet Issues:**
- ❌ Custom list management logic instead of `useListPage()`
- ❌ Inconsistent filter UI (some use v-select, others use v-autocomplete)
- ❌ No active filter chips display
- ❌ Different pagination styling
- ❌ Custom row actions instead of `WpRowActions`
- ❌ Inconsistent empty/loading/error states
- ❌ Mobile responsiveness varies by page

**BP Pattern (Target):**
```vue
<script setup>
import { useFleetVehicleListPage } from '@/composables/fleet/useFleetVehicleListPage'
import { WpDataTableCard, WpResponsiveList, WpRowActions } from '@/components/shared/data'

const {
  items,
  loading,
  error,
  search,
  filters,
  sortKey,
  sortOrder,
  page,
  itemsPerPage,
  totalItems,
  totalPages,
  refresh,
  setSearch,
  setFilter,
  setSorting,
  setPage,
  setItemsPerPage,
} = useFleetVehicleListPage()
</script>

<template>
  <VContainer fluid>
    <WpDataTableCard
      :items="items"
      :loading="loading"
      :error="error"
      :headers="headers"
      :search="search"
      :filters="filters"
      @update:search="setSearch"
      @update:filters="setFilter"
    >
      <template #row-actions="{ item }">
        <WpRowActions :actions="getActions(item)" />
      </template>
    </WpDataTableCard>
  </VContainer>
</template>
```

### 2. Detail View / 360° View

**Current Fleet Issues:**
- ❌ Custom dialog/page implementation
- ❌ Tabs use VTabs directly instead of standardized pattern
- ❌ Header formatting inconsistent with BP
- ❌ Action buttons placement varies
- ❌ No breadcrumb navigation
- ❌ Status badges styling inconsistent
- ❌ KPI cards have different styling

**BP Pattern (Target):**
```vue
<template>
  <WpDetailDialog
    :title="vehicle.fleet_code"
    :subtitle="vehicle.plate_number"
    :status="vehicle.status"
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #header-actions>
      <VBtn icon @click="openEdit">
        <VIcon>mdi-pencil</VIcon>
      </VBtn>
      <VBtn icon @click="openDelete">
        <VIcon>mdi-delete</VIcon>
      </VBtn>
    </template>

    <template #tabs>
      <VTab value="overview">{{ $t('fleet.tabs.overview') }}</VTab>
      <VTab value="assignments">{{ $t('fleet.tabs.assignments') }}</VTab>
      <VTab value="maintenance">{{ $t('fleet.tabs.maintenance') }}</VTab>
      <VTab value="fuel">{{ $t('fleet.tabs.fuel') }}</VTab>
      <VTab value="documents">{{ $t('fleet.tabs.documents') }}</VTab>
    </template>

    <VWindow v-model="activeTab">
      <VWindowItem value="overview">
        <VehicleOverviewTab :vehicle="vehicle" />
      </VWindowItem>
      <!-- Other tabs -->
    </VWindow>
  </WpDetailDialog>
</template>
```

### 3. Form Dialogs

**Current Fleet Issues:**
- ❌ Inconsistent dialog sizing
- ❌ Manual form validation implementation
- ❌ No unsaved changes protection
- ❌ Submit button states manually managed
- ❌ Error handling varies per dialog
- ❌ Loading states inconsistent

**BP Pattern (Target):**
```vue
<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="isEdit ? $t('fleet.editVehicle') : $t('fleet.addVehicle')"
    :loading="loading"
    :has-changes="hasChanges"
    @update:model-value="$emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="formRef" v-model="isValid">
      <VRow>
        <VCol cols="12" md="6">
          <VTextField
            v-model="form.fleet_code"
            :label="$t('fleet.fields.fleetCode')"
            :rules="[rules.required]"
            required
          />
        </VCol>
        <!-- Other fields -->
      </VRow>
    </VForm>
  </WpFormDialog>
</template>

<script setup>
import { WpFormDialog } from '@/components/shared/dialogs'
import { useFormDialog } from '@/composables/forms/useFormDialog'
import { useFleetVehicleStore } from '@/stores/fleet/useFleetVehicleStore'

const {
  form,
  isValid,
  loading,
  hasChanges,
  formRef,
  handleSubmit,
} = useFormDialog({
  storeFn: useFleetVehicleStore().createVehicle,
  initialData: props.initialData,
})
</script>
```

### 4. Internationalization

**Current Issues:**
- ❌ Hardcoded English labels in driver dialogs
- ❌ Incomplete Arabic translations
- ❌ Incomplete French translations
- ❌ Inconsistent translation key naming
- ❌ Some components bypass i18n entirely

**Missing Translations:**

```js
// locales/en.json - Missing keys
{
  "fleet": {
    "vehicles": {
      "title": "Fleet Vehicles",
      "addVehicle": "Add Vehicle",
      "editVehicle": "Edit Vehicle",
      "deleteVehicle": "Delete Vehicle",
      "vehicleDetails": "Vehicle Details",
      "filters": {
        "status": "Status",
        "ownership": "Ownership Type",
        "type": "Vehicle Type",
        "class": "Vehicle Class",
      },
      "tabs": {
        "overview": "Overview",
        "assignments": "Assignments",
        "maintenance": "Maintenance",
        "fuel": "Fuel Records",
        "documents": "Documents",
        "checklists": "Checklists",
      },
      "fields": {
        "fleetCode": "Fleet Code",
        "plateNumber": "Plate Number",
        "status": "Status",
        "ownershipType": "Ownership Type",
        "vehicleType": "Vehicle Type",
        "vehicleClass": "Vehicle Class",
        // ... 50+ more fields
      },
      "status": {
        "available": "Available",
        "in_use": "In Use",
        "under_maintenance": "Under Maintenance",
        "out_of_service": "Out of Service",
        "retired": "Retired",
      },
    },
    "drivers": {
      "title": "Approved Drivers",
      "addDriver": "Approve Driver",
      "editDriver": "Edit Driver Authorization",
      "suspendDriver": "Suspend Driver",
      "reinstateDriver": "Reinstate Driver",
      "revokeDriver": "Revoke Authorization",
      "renewDriver": "Renew Authorization",
      "driverDetails": "Driver Details",
      "fields": {
        "authorizationNumber": "Authorization Number",
        "licenseNumber": "License Number",
        "licenseCategory": "License Category",
        "licenseExpiry": "License Expiry",
        "authorizationStatus": "Authorization Status",
        "driverLevel": "Driver Level",
        "driverScore": "Safety Score",
        // ... 40+ more fields
      },
      "incidents": {
        "title": "Driver Incidents",
        "addIncident": "Record Incident",
        "viewIncident": "View Incident",
        "resolveIncident": "Resolve Incident",
        "closeIncident": "Close Incident",
        "incidentTypes": {
          "accident": "Accident",
          "traffic_violation": "Traffic Violation",
          "speeding": "Speeding",
          // ... all types
        },
      },
    },
  }
}
```

### 5. Responsive Behavior

**Current Issues:**
- ❌ Vehicle list uses VDataTable which doesn't adapt well on mobile
- ❌ Detail dialogs too wide on mobile
- ❌ Tabs scroll horizontally instead of using dropdown on mobile
- ❌ Form fields don't stack properly on small screens
- ❌ Action buttons overflow on mobile

**BP Pattern (Target):**
```vue
<template>
  <!-- Desktop: Table, Mobile: Cards -->
  <WpResponsiveList
    :items="items"
    :headers="headers"
    :loading="loading"
  >
    <template #desktop="{ item }">
      <tr>
        <td>{{ item.fleet_code }}</td>
        <td>{{ item.plate_number }}</td>
        <td><WpStatusChip :status="item.status" /></td>
        <td><WpRowActions :actions="getActions(item)" /></td>
      </tr>
    </template>

    <template #mobile="{ item }">
      <WpMobileCard
        :title="item.fleet_code"
        :subtitle="item.plate_number"
        :status="item.status"
        :actions="getActions(item)"
      >
        <template #content>
          <div class="text-body-2">
            <div>{{ $t('fleet.fields.type') }}: {{ item.type }}</div>
            <div>{{ $t('fleet.fields.ownership') }}: {{ item.ownership_type }}</div>
          </div>
        </template>
      </WpMobileCard>
    </template>
  </WpResponsiveList>
</template>
```

### 6. Theme Consistency

**Current Issues:**
- ❌ Some components use hardcoded colors instead of theme variables
- ❌ Status colors don't match BP conventions
- ❌ Dark mode has visual artifacts in some dialogs
- ❌ Elevation/shadows inconsistent
- ❌ Border radius varies

**BP Standards:**
```scss
// Use theme colors
.vehicle-card {
  background-color: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  border: 1px solid rgb(var(--v-theme-border));
}

// Status colors (use WpStatusChip)
.status-available { color: rgb(var(--v-theme-success)); }
.status-in-use { color: rgb(var(--v-theme-info)); }
.status-maintenance { color: rgb(var(--v-theme-warning)); }
.status-retired { color: rgb(var(--v-theme-error)); }

// Consistent elevation
.card { elevation: 1; }
.dialog { elevation: 8; }

// Consistent border radius
.rounded { border-radius: 8px; }
```

### 7. Permission Integration

**Current Issues:**
- ❌ Some buttons visible when user lacks permission
- ❌ Permission checks done in component instead of using directive
- ❌ Backend permission names don't match frontend checks
- ❌ No visual feedback when action is disabled due to permissions

**BP Pattern (Target):**
```vue
<template>
  <VBtn
    v-can="'hr.fleet.manage'"
    @click="openEdit"
  >
    {{ $t('common.edit') }}
  </VBtn>

  <!-- Or for more complex checks -->
  <VBtn
    v-if="canEditVehicle(vehicle)"
    @click="openEdit"
  >
    {{ $t('common.edit') }}
  </VBtn>
</template>

<script setup>
import { usePermissions } from '@/composables/auth/usePermissions'

const { can } = usePermissions()

const canEditVehicle = (vehicle) => {
  return can('hr.fleet.manage') && vehicle.status !== 'retired'
}
</script>
```

## Implementation Plan

### Phase 1: Shared Components & Composables (Week 1)

**1.1 Create Fleet List Composable**

File: `resources/js/composables/fleet/useFleetVehicleListPage.js`

```js
import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { useFleetVehicleStore } from '@/stores/fleet/useFleetVehicleStore'
import { watch } from 'vue'

export function useFleetVehicleListPage() {
  const store = useFleetVehicleStore()
  
  const initialFilters = {
    status: null,
    ownership_type: null,
    type: null,
    vehicle_class: null,
  }

  const sortFieldMap = {
    fleet_code: 'fleet_code',
    plate_number: 'plate_number',
    status: 'status',
    created_at: 'created_at',
  }

  const listPage = useListPage({
    fetchFn: params => store.fetchVehicles(params),
    initialFilters,
    initialSortKey: 'created_at',
    initialSortOrder: 'desc',
    initialItemsPerPage: 15,
    sortFieldMap,
    immediate: true,
  })

  const { selected, clearSelection } = useSelection()

  watch(
    [listPage.search, () => ({ ...listPage.filters })],
    clearSelection,
    { deep: true },
  )

  return {
    ...listPage,
    selected,
    clearSelection,
  }
}
```

File: `resources/js/composables/fleet/useApprovedDriverListPage.js` (similar)

**1.2 Create Fleet Form Composable**

File: `resources/js/composables/fleet/useVehicleForm.js`

```js
import { ref, computed, watch } from 'vue'
import { useNotification } from '@/composables/useNotification'
import { useI18n } from 'vue-i18n'

export function useVehicleForm({ initialData, storeFn, onSuccess }) {
  const { t } = useI18n()
  const { showSuccess, showError } = useNotification()
  
  const form = ref({ ...initialData })
  const originalForm = ref({ ...initialData })
  const loading = ref(false)
  const isValid = ref(false)
  
  const hasChanges = computed(() => {
    return JSON.stringify(form.value) !== JSON.stringify(originalForm.value)
  })
  
  const handleSubmit = async () => {
    if (!isValid.value) return
    
    loading.value = true
    try {
      const result = await storeFn(form.value)
      showSuccess(t('fleet.messages.saveSuccess'))
      onSuccess?.(result)
    } catch (error) {
      showError(error.message || t('fleet.messages.saveError'))
    } finally {
      loading.value = false
    }
  }
  
  return {
    form,
    isValid,
    loading,
    hasChanges,
    handleSubmit,
  }
}
```

**1.3 Update Stores to Match BP Pattern**

File: `resources/js/stores/fleet/useFleetVehicleStore.js` (update)

```js
export const useFleetVehicleStore = defineStore('fleetVehicles', () => {
  // State
  const vehicles = ref([])
  const currentVehicle = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Actions matching BP pattern
  const fetchVehicles = async (params) => {
    loading.value = true
    error.value = null
    try {
      const response = await $api.get('/hr/fleet/vehicles', { params })
      vehicles.value = response.data.data
      return response.data
    } catch (err) {
      error.value = err
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchVehicle = async (id) => {
    loading.value = true
    try {
      const response = await $api.get(`/hr/fleet/vehicles/${id}`)
      currentVehicle.value = response.data.data
      return response.data.data
    } catch (err) {
      error.value = err
      throw err
    } finally {
      loading.value = false
    }
  }

  const createVehicle = async (data) => {
    const response = await $api.post('/hr/fleet/vehicles', data)
    return response.data.data
  }

  const updateVehicle = async (id, data) => {
    const response = await $api.patch(`/hr/fleet/vehicles/${id}`, data)
    const index = vehicles.value.findIndex(v => v.id === id)
    if (index !== -1) {
      vehicles.value[index] = response.data.data
    }
    return response.data.data
  }

  const deleteVehicle = async (id) => {
    await $api.delete(`/hr/fleet/vehicles/${id}`)
    vehicles.value = vehicles.value.filter(v => v.id !== id)
  }

  return {
    vehicles,
    currentVehicle,
    loading,
    error,
    fetchVehicles,
    fetchVehicle,
    createVehicle,
    updateVehicle,
    deleteVehicle,
  }
})
```

### Phase 2: Vehicle List Refactoring (Week 2)

**2.1 Refactor FleetVehiclesListPage.vue**

```vue
<template>
  <VContainer fluid>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h1 class="text-h4">{{ $t('fleet.vehicles.title') }}</h1>
        <AppBreadcrumb :items="breadcrumbs" />
      </div>
      <VBtn
        v-can="'hr.fleet.manage'"
        color="primary"
        prepend-icon="mdi-plus"
        @click="openAddDialog"
      >
        {{ $t('fleet.vehicles.addVehicle') }}
      </VBtn>
    </div>

    <!-- KPI Cards -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">{{ $t('fleet.kpi.totalVehicles') }}</div>
            <div class="text-h4">{{ kpi.total }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">{{ $t('fleet.kpi.available') }}</div>
            <div class="text-h4 text-success">{{ kpi.available }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">{{ $t('fleet.kpi.inUse') }}</div>
            <div class="text-h4 text-info">{{ kpi.inUse }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">{{ $t('fleet.kpi.maintenance') }}</div>
            <div class="text-h4 text-warning">{{ kpi.maintenance }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Data Table Card -->
    <WpDataTableCard
      :items="items"
      :loading="loading"
      :headers="headers"
      :search="search"
      :filters="filters"
      :total-items="totalItems"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:search="setSearch"
      @update:filters="setFilter"
      @update:page="setPage"
      @update:items-per-page="setItemsPerPage"
      @update:sort="setSorting"
    >
      <template #row-actions="{ item }">
        <WpRowActions :actions="getRowActions(item)" />
      </template>

      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :label="$t(`fleet.vehicles.status.${item.status}`)"
        />
      </template>

      <template #item.fleet_code="{ item }">
        <a
          class="text-primary text-decoration-none"
          @click="openDetailDialog(item)"
        >
          {{ item.fleet_code }}
        </a>
      </template>
    </WpDataTableCard>

    <!-- Dialogs -->
    <AddVehicleDialog
      v-model="showAddDialog"
      @success="refresh"
    />

    <EditVehicleDialog
      v-model="showEditDialog"
      :vehicle="selectedVehicle"
      @success="refresh"
    />

    <VehicleDetailDialog
      v-model="showDetailDialog"
      :vehicle-id="selectedVehicle?.id"
      @edit="openEditFromDetail"
    />

    <WpDeleteDialog
      v-model="showDeleteDialog"
      :title="$t('fleet.vehicles.deleteVehicle')"
      :message="$t('fleet.vehicles.deleteConfirm', { code: selectedVehicle?.fleet_code })"
      @confirm="handleDelete"
    />
  </VContainer>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFleetVehicleListPage } from '@/composables/fleet/useFleetVehicleListPage'
import { WpDataTableCard, WpRowActions, WpStatusChip } from '@/components/shared/data'
import { WpDeleteDialog } from '@/components/shared/dialogs'
import AddVehicleDialog from './AddVehicleDialog.vue'
import EditVehicleDialog from './EditVehicleDialog.vue'
import VehicleDetailDialog from './VehicleDetailDialog.vue'

const { t } = useI18n()

const {
  items,
  loading,
  search,
  filters,
  page,
  itemsPerPage,
  totalItems,
  setSearch,
  setFilter,
  setPage,
  setItemsPerPage,
  setSorting,
  refresh,
} = useFleetVehicleListPage()

const headers = computed(() => [
  { key: 'fleet_code', title: t('fleet.fields.fleetCode'), sortable: true },
  { key: 'plate_number', title: t('fleet.fields.plateNumber'), sortable: true },
  { key: 'type', title: t('fleet.fields.vehicleType'), sortable: true },
  { key: 'status', title: t('fleet.fields.status'), sortable: true },
  { key: 'ownership_type', title: t('fleet.fields.ownership'), sortable: false },
  { key: 'actions', title: t('common.actions'), sortable: false, align: 'end' },
])

const breadcrumbs = [
  { title: t('nav.peopleSkills'), disabled: false },
  { title: t('fleet.vehicles.title'), disabled: true },
]

const kpi = computed(() => ({
  total: items.value.length,
  available: items.value.filter(v => v.status === 'available').length,
  inUse: items.value.filter(v => v.status === 'in_use').length,
  maintenance: items.value.filter(v => v.status === 'under_maintenance').length,
}))

// Dialog state
const showAddDialog = ref(false)
const showEditDialog = ref(false)
const showDetailDialog = ref(false)
const showDeleteDialog = ref(false)
const selectedVehicle = ref(null)

const openAddDialog = () => {
  showAddDialog.value = true
}

const openEditDialog = (vehicle) => {
  selectedVehicle.value = vehicle
  showEditDialog.value = true
}

const openDetailDialog = (vehicle) => {
  selectedVehicle.value = vehicle
  showDetailDialog.value = true
}

const openDeleteDialog = (vehicle) => {
  selectedVehicle.value = vehicle
  showDeleteDialog.value = true
}

const openEditFromDetail = (vehicle) => {
  showDetailDialog.value = false
  openEditDialog(vehicle)
}

const getRowActions = (item) => [
  {
    icon: 'mdi-eye',
    label: t('common.view'),
    onClick: () => openDetailDialog(item),
  },
  {
    icon: 'mdi-pencil',
    label: t('common.edit'),
    onClick: () => openEditDialog(item),
    show: can('hr.fleet.manage'),
  },
  {
    icon: 'mdi-delete',
    label: t('common.delete'),
    onClick: () => openDeleteDialog(item),
    show: can('hr.fleet.archive') && item.status === 'retired',
    color: 'error',
  },
]

const handleDelete = async () => {
  await store.deleteVehicle(selectedVehicle.value.id)
  showDeleteDialog.value = false
  refresh()
}
</script>
```

### Phase 3: Vehicle Detail Dialog Refactoring (Week 3)

**3.1 Refactor VehicleDetailDialog.vue**

Structure:
```vue
<template>
  <WpDetailDialog
    v-model="modelValue"
    :title="vehicle?.fleet_code"
    :subtitle="vehicle?.plate_number"
    :loading="loading"
    width="1200"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #header-status>
      <WpStatusChip
        :status="vehicle?.status"
        :label="$t(`fleet.vehicles.status.${vehicle?.status}`)"
      />
    </template>

    <template #header-actions>
      <VBtn
        v-can="'hr.fleet.manage'"
        icon="mdi-pencil"
        variant="text"
        @click="$emit('edit', vehicle)"
      />
      <VBtn
        icon="mdi-refresh"
        variant="text"
        @click="refresh"
      />
    </template>

    <VTabs v-model="activeTab" class="mb-4">
      <VTab value="overview">{{ $t('fleet.tabs.overview') }}</VTab>
      <VTab value="assignments">{{ $t('fleet.tabs.assignments') }}</VTab>
      <VTab value="maintenance">{{ $t('fleet.tabs.maintenance') }}</VTab>
      <VTab value="fuel">{{ $t('fleet.tabs.fuel') }}</VTab>
      <VTab value="documents">{{ $t('fleet.tabs.documents') }}</VTab>
      <VTab value="checklists">{{ $t('fleet.tabs.checklists') }}</VTab>
      <VTab value="activity">{{ $t('fleet.tabs.activity') }}</VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <VWindowItem value="overview">
        <VehicleOverviewTab :vehicle="vehicle" />
      </VWindowItem>
      
      <VWindowItem value="assignments">
        <VehicleAssignmentsTab :vehicle-id="vehicle?.id" />
      </VWindowItem>
      
      <!-- Other tabs -->
    </VWindow>
  </WpDetailDialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { WpDetailDialog } from '@/components/shared/dialogs'
import { WpStatusChip } from '@/components/shared/data'
import { useFleetVehicleStore } from '@/stores/fleet/useFleetVehicleStore'

const props = defineProps({
  modelValue: Boolean,
  vehicleId: String,
})

const emit = defineEmits(['update:modelValue', 'edit'])

const store = useFleetVehicleStore()
const vehicle = ref(null)
const loading = ref(false)
const activeTab = ref('overview')

watch(() => props.vehicleId, async (id) => {
  if (id) {
    loading.value = true
    try {
      vehicle.value = await store.fetchVehicle(id)
    } finally {
      loading.value = false
    }
  }
}, { immediate: true })

const refresh = () => {
  if (props.vehicleId) {
    store.fetchVehicle(props.vehicleId)
  }
}
</script>
```

### Phase 4: Driver Pages Refactoring (Week 4)

Similar refactoring for:
- `ApprovedDriversListPage.vue`
- `DriverDetailDialog.vue`
- `AddDriverDialog.vue`
- `EditDriverDialog.vue`
- `DriverIncidentDialog.vue`
- `SuspendDriverDialog.vue`
- `ReinstateDriverDialog.vue`
- `RevokeDriverDialog.vue`

### Phase 5: Internationalization (Week 5)

**5.1 Complete Translation Files**

File: `locales/en.json`
File: `locales/ar.json`
File: `locales/fr.json`

All three must have complete translations for:
- Fleet module (200+ keys)
- Driver module (150+ keys)
- Common terms

**5.2 Remove Hardcoded Labels**

Search and replace all instances:
```vue
<!-- BEFORE -->
<VBtn>Add Vehicle</VBtn>

<!-- AFTER -->
<VBtn>{{ $t('fleet.vehicles.addVehicle') }}</VBtn>
```

### Phase 6: Responsive & Theme (Week 6)

**6.1 Replace VDataTable with WpResponsiveList**

**6.2 Fix Mobile Layouts**
- Stack form fields on mobile
- Use dropdown for tab navigation on mobile
- Adjust dialog widths for mobile

**6.3 Theme Variables Audit**
- Replace hardcoded colors with theme variables
- Fix dark mode issues
- Standardize elevations and border radius

### Phase 7: Testing & Quality (Week 7)

**7.1 Manual Testing**
- Test all pages on desktop, tablet, mobile
- Test light and dark themes
- Test all three languages
- Test all permission levels

**7.2 Code Review Checklist**
- [ ] Uses shared components where applicable
- [ ] Uses composables for list/form management
- [ ] All labels use i18n
- [ ] Responsive on all screen sizes
- [ ] Theme-aware (no hardcoded colors)
- [ ] Permission checks in place
- [ ] Loading/error/empty states handled
- [ ] Follows BP naming conventions

## File Structure (Target)

```
resources/js/
├── components/shared/          # (Existing, use but don't modify unnecessarily)
│   ├── dialogs/
│   ├── data/
│   └── ...
├── composables/
│   └── fleet/                  # NEW
│       ├── useFleetVehicleListPage.js
│       ├── useApprovedDriverListPage.js
│       ├── useVehicleForm.js
│       └── useDriverForm.js
├── stores/
│   └── fleet/                  # UPDATE
│       ├── useFleetVehicleStore.js
│       └── useApprovedDriverStore.js
└── views/
    ├── people-skills/fleet/
    │   ├── vehicles/
    │   │   ├── FleetVehiclesListPage.vue     # REFACTOR
    │   │   ├── VehicleDetailDialog.vue        # REFACTOR
    │   │   ├── AddVehicleDialog.vue           # REFACTOR
    │   │   ├── EditVehicleDialog.vue          # REFACTOR
    │   │   └── tabs/                          # REFACTOR ALL
    │   │       ├── VehicleOverviewTab.vue
    │   │       ├── VehicleAssignmentsTab.vue
    │   │       ├── VehicleMaintenanceTab.vue
    │   │       ├── VehicleFuelTab.vue
    │   │       ├── VehicleDocumentsTab.vue
    │   │       └── VehicleChecklistsTab.vue
    │   └── drivers/
    │       ├── ApprovedDriversListPage.vue    # REFACTOR
    │       ├── DriverDetailDialog.vue         # REFACTOR
    │       ├── AddDriverDialog.vue            # REFACTOR
    │       ├── EditDriverDialog.vue           # REFACTOR
    │       ├── DriverIncidentDialog.vue       # REFACTOR
    │       └── dialogs/                       # NEW
    │           ├── SuspendDriverDialog.vue
    │           ├── ReinstateDriverDialog.vue
    │           ├── RevokeDriverDialog.vue
    │           └── RenewDriverDialog.vue
    └── logistics/fleet/                       # ALSO REFACTOR
        └── FleetVehiclesPage.vue
```

## Estimated Effort

- **Phase 1 (Shared):** 5 days
- **Phase 2 (Vehicle List):** 5 days
- **Phase 3 (Vehicle Detail):** 5 days
- **Phase 4 (Driver Pages):** 7 days
- **Phase 5 (i18n):** 5 days
- **Phase 6 (Responsive/Theme):** 5 days
- **Phase 7 (Testing/QA):** 5 days

**Total:** 37 days (~7-8 weeks)

## Success Criteria

✅ **Consistency** — Fleet UI indistinguishable from BP in terms of UX patterns  
✅ **Shared Components** — All eligible components replaced with Wp* versions  
✅ **Composables** — List/form logic extracted to reusable composables  
✅ **i18n** — Zero hardcoded labels, complete AR/EN/FR translations  
✅ **Responsive** — Perfect mobile/tablet experience matching BP  
✅ **Theme** — Dark/light mode consistent, no visual artifacts  
✅ **Permissions** — All actions properly gated and tested  
✅ **Preserved Functionality** — All Fleet-specific features still work  

## Risk Mitigation

**Risk:** Breaking existing functionality  
**Mitigation:** Refactor incrementally, test each page before moving to next

**Risk:** Regression in BP module  
**Mitigation:** Don't modify shared components unless truly needed, extend instead

**Risk:** Performance degradation  
**Mitigation:** Monitor bundle size, lazy-load detail dialogs, paginate lists

**Risk:** Translation errors  
**Mitigation:** Native speaker review for AR/FR, use BP translations as reference

## Next Steps

1. ✅ Review this plan with stakeholders
2. ⏳ Begin Phase 1 (composables and stores)
3. ⏳ Create side-by-side UI comparison screenshots (BP vs. Current Fleet)
4. ⏳ Set up translation workflow
5. ⏳ Schedule weekly progress reviews

---

**Note:** This is a foundational refactoring that will significantly improve maintainability and user experience. The investment will pay off in reduced development time for future Fleet features and improved user satisfaction across the entire WAVE PLUS application.
