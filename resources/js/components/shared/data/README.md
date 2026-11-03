# WAVE PLUS Entity Collection Architecture

## Overview

The WAVE PLUS application uses a standardized set of reusable components for displaying and managing entity lists across all HR modules (departments, positions, teams, skills, certifications, training programs, approved drivers, etc.).

This architecture ensures:
- **Consistency** across all entity list pages
- **Responsive behavior** with automatic mobile/desktop views
- **Maintainability** by avoiding code duplication
- **Accessibility** with proper ARIA attributes
- **Theme support** for light and dark modes using Vuexy tokens

---

## Core Components

### 1. WpResponsiveList

The primary component for entity lists. Automatically switches between table view (desktop) and card view (mobile).

**Usage:**
```vue
<WpResponsiveList
  v-model:page="page"
  v-model:items-per-page="itemsPerPage"
  v-model:sort-by="sortBy"
  :headers="headers"
  :items="items"
  :total-items="totalItems"
  :loading="loading"
  :error="error"
  @retry="refresh"
>
  <!-- Desktop table column customization -->
  <template #item.status="{ item }">
    <VChip
      :color="item.is_active ? 'success' : 'secondary'"
      size="small"
      label
    >
      {{ item.is_active ? $t('Active') : $t('Inactive') }}
    </VChip>
  </template>

  <template #item.actions="{ item }">
    <WpRowActions
      :actions="getRowActions()"
      @edit="openEdit(item)"
      @delete="openDelete(item.id)"
    />
  </template>

  <!-- Mobile card view -->
  <template #mobile-card="{ item }">
    <WpMobileCard
      :title="item.name"
      :subtitle="item.code"
    >
      <template #actions>
        <VChip
          :color="item.is_active ? 'success' : 'secondary'"
          size="small"
          label
        >
          {{ item.is_active ? $t('Active') : $t('Inactive') }}
        </VChip>
        <WpRowActions
          :actions="getRowActions()"
          @edit="openEdit(item)"
          @delete="openDelete(item.id)"
        />
      </template>
    </WpMobileCard>
  </template>
</WpResponsiveList>
```

**Key Props:**
- `headers` - Table column definitions (title, key, sortable, width)
- `items` - Array of entity objects to display
- `total-items` - Total count for pagination
- `loading` - Show loading state
- `error` - Display error banner
- `page`, `items-per-page`, `sort-by` - Pagination and sorting (v-model)

**Breakpoint:** Switches to mobile view at `md` and below (configurable via `mobile-breakpoint` prop)

---

### 2. WpPageToolbar

Standard toolbar component for entity list pages.

**Usage:**
```vue
<WpPageToolbar
  v-model:search="search"
  :create-label="$can('create', 'PeopleSkills') ? $t('teams.add') : ''"
  :show-filter="true"
  :active-filter-count="activeFilterCount"
  :loading="loading"
  @create="openCreate"
  @refresh="refresh"
  @open-filters="filterDialogOpen = true"
>
  <!-- Optional: Filter chips row -->
  <template #filter-chips>
    <WpActiveFilters
      v-if="activeFilterCount > 0"
      :filters="filters"
      :label-map="filterLabelMap"
      @remove="removeFilter"
      @clear-all="resetFilters"
    />
  </template>

  <!-- Optional: Extra action buttons -->
  <template #extra-actions>
    <VBtn variant="tonal" prepend-icon="tabler-download">
      Export
    </VBtn>
  </template>
</WpPageToolbar>
```

**Features:**
- Search field with debouncing
- Create button (conditionally shown based on permissions)
- Refresh button with loading spinner
- Filter button with active count badge
- Responsive layout (stacks on mobile)

---

### 3. WpMobileCard

Compact card for displaying entities in mobile view.

**Usage:**
```vue
<WpMobileCard
  :title="item.name"
  :subtitle="item.code"
  :avatar-icon="tabler-building"
  avatar-color="primary"
>
  <template #actions>
    <VChip size="small" color="success">Active</VChip>
    <WpRowActions :actions="actions" @edit="onEdit" />
  </template>

  <template #content>
    <div class="text-caption mt-2">
      {{ item.description }}
    </div>
  </template>
</WpMobileCard>
```

---

### 4. WpEntityCollection (Alternative)

Higher-level component that includes view mode toggle (table/grid).

**Usage:**
```vue
<WpEntityCollection
  v-model:view-mode="viewMode"
  v-model:page="page"
  v-model:items-per-page="itemsPerPage"
  :headers="headers"
  :items="items"
  :total-items="totalItems"
  :loading="loading"
  :grid-cols="{ xs: 1, sm: 2, md: 3, lg: 4 }"
>
  <!-- Table view slots -->
  <template #item.status="{ item }">...</template>

  <!-- Grid view slot -->
  <template #card="{ entity }">
    <WpEntityCard
      :entity="entity"
      :title="entity.name"
      :subtitle="entity.code"
      :status="entity.status"
      status-type="department"
      clickable
    />
  </template>
</WpEntityCollection>
```

**When to use:**
- Use `WpResponsiveList` for most entity lists (default, simpler)
- Use `WpEntityCollection` when you need user-selectable grid view

---

### 5. WpEntityCard

Generic card component for grid views.

**Usage:**
```vue
<WpEntityCard
  :entity="item"
  :title="item.name"
  :subtitle="item.code"
  :status="item.status"
  status-type="employee"
  :avatar-icon="tabler-user"
  avatar-color="primary"
  clickable
  @click="onCardClick(item)"
>
  <template #meta>
    <div class="d-flex flex-wrap gap-1">
      <VChip size="x-small" variant="tonal">{{ item.department }}</VChip>
    </div>
  </template>

  <template #content>
    <div class="text-caption">
      {{ item.description }}
    </div>
  </template>

  <template #actions>
    <WpRowActions :actions="getActions(item)" />
  </template>
</WpEntityCard>
```

---

## Supporting Components

### WpPageHeader
Page title with icon, subtitle, and action buttons.

```vue
<WpPageHeader
  :title="$t('teams.list.title')"
  :subtitle="$t('teams.list.subtitle')"
  icon="tabler-users-group"
>
  <template #actions>
    <VBtn color="primary" prepend-icon="tabler-plus" @click="onCreate">
      {{ $t('teams.add') }}
    </VBtn>
  </template>
</WpPageHeader>
```

### WpKpiGroup & WpKpiCard
Display key metrics at the top of list pages.

```vue
<WpKpiGroup
  :item-count="kpis.length"
  :cols="{ xs: 2, sm: 2, md: 4 }"
>
  <template #default="{ getColProps }">
    <VCol
      v-for="(kpi, index) in kpis"
      :key="kpi.title"
      v-bind="getColProps(index)"
    >
      <WpKpiCard
        :title="kpi.title"
        :value="kpi.value"
        :icon="kpi.icon"
        :icon-color="kpi.color"
      />
    </VCol>
  </template>
</WpKpiGroup>
```

### WpDetailDialog
Modal for viewing entity details.

```vue
<WpDetailDialog
  v-model="dialogOpen"
  :title="selectedItem?.name"
  :subtitle="selectedItem?.code"
  max-width="700"
>
  <WpLoadingState v-if="loading" />
  <template v-else>
    <!-- Detail content -->
  </template>
</WpDetailDialog>
```

### WpFilterDialog
Modal for filtering entities.

```vue
<WpFilterDialog
  v-model="filterOpen"
  :active-count="activeFilterCount"
  @apply="applyFilters"
  @reset="resetFilters"
>
  <VRow>
    <VCol cols="12">
      <AppSelect
        v-model="filters.status"
        :label="$t('Status')"
        :items="statusOptions"
        clearable
      />
    </VCol>
  </VRow>
</WpFilterDialog>
```

### WpActiveFilters
Display active filter chips below toolbar.

```vue
<WpActiveFilters
  :filters="activeFilters"
  :label-map="{ status: $t('Status'), department_id: $t('Department') }"
  @remove="removeFilter"
  @clear-all="resetFilters"
/>
```

### WpRowActions
Three-dot menu for row/card actions.

```vue
<WpRowActions
  :actions="[
    { label: $t('View'), icon: 'tabler-eye', emit: 'view' },
    { label: $t('Edit'), icon: 'tabler-edit', emit: 'edit' },
    { label: $t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true }
  ]"
  @view="onView(item)"
  @edit="onEdit(item)"
  @delete="onDelete(item.id)"
/>
```

### WpStatusChip
Standardized status chip using theme registry.

```vue
<WpStatusChip
  type="employee"
  :status="item.status"
  size="small"
/>
```

---

## Standard Page Structure

### Basic List Page Pattern

```vue
<script setup>
import {
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useEntityStore } from '@/stores/useEntityStore'
import { useListPage } from '@/composables/list'
import { useDialog } from '@/composables/dialog/useDialog'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const store = useEntityStore()

// List management
const listPage = useListPage({
  fetchFn: params => store.fetchList(params),
  initialSortKey: 'name',
  initialSortOrder: 'asc',
  immediate: true,
})

// CRUD dialogs
const editDialog = useDialog()
const deleteDialog = useDialog()

// Table headers
const headers = computed(() => [
  { title: t('Name'), key: 'name', sortable: true },
  { title: t('Code'), key: 'code', sortable: true },
  { title: t('Status'), key: 'status', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

// Actions
const rowActions = [
  { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]
</script>

<template>
  <div class="entity-list-page d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('entities.list.title')"
      :subtitle="$t('entities.list.subtitle')"
      icon="tabler-folder"
    />

    <VCard>
      <WpPageToolbar
        v-model:search="listPage.search.value"
        :create-label="$can('create', 'Module') ? $t('entities.add') : ''"
        :loading="store.loading"
        @create="openCreate"
        @refresh="listPage.refresh()"
      />

      <WpResponsiveList
        v-model:page="listPage.page.value"
        v-model:items-per-page="listPage.itemsPerPage.value"
        v-model:sort-by="listPage.sortBy.value"
        :headers="headers"
        :items="store.list"
        :total-items="store.totalItems"
        :loading="store.loading"
        :error="store.error"
        @retry="listPage.refresh()"
      >
        <template #item.status="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'secondary'"
            size="small"
            label
          >
            {{ item.is_active ? $t('Active') : $t('Inactive') }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <WpRowActions
            :actions="rowActions"
            @edit="openEdit(item)"
            @delete="deleteDialog.open(item.id)"
          />
        </template>

        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.name"
            :subtitle="item.code"
          >
            <template #actions>
              <VChip
                :color="item.is_active ? 'success' : 'secondary'"
                size="small"
                label
              >
                {{ item.is_active ? $t('Active') : $t('Inactive') }}
              </VChip>
              <WpRowActions
                :actions="rowActions"
                @edit="openEdit(item)"
                @delete="deleteDialog.open(item.id)"
              />
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- CRUD dialogs go here -->
  </div>
</template>
```

---

## List Page with Tabs Pattern

```vue
<template>
  <div class="entity-list-page d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('entities.list.title')"
      :subtitle="$t('entities.list.subtitle')"
      icon="tabler-folder"
    />

    <VCard>
      <VTabs v-model="activeTab">
        <VTab value="type-a">
          <VIcon start icon="tabler-icon-a" />
          {{ $t('Type A') }}
        </VTab>
        <VTab value="type-b">
          <VIcon start icon="tabler-icon-b" />
          {{ $t('Type B') }}
        </VTab>
      </VTabs>

      <VDivider />

      <VTabsWindow v-model="activeTab">
        <VTabsWindowItem value="type-a">
          <WpPageToolbar
            v-model:search="listPageA.search.value"
            :create-label="$t('Add Type A')"
            @create="openCreateA"
            @refresh="listPageA.refresh()"
          />

          <WpResponsiveList
            v-model:page="listPageA.page.value"
            v-model:items-per-page="listPageA.itemsPerPage.value"
            :headers="headersA"
            :items="storeA.list"
            :total-items="storeA.totalItems"
            :loading="storeA.loading"
          >
            <!-- Slots for Type A -->
          </WpResponsiveList>
        </VTabsWindowItem>

        <VTabsWindowItem value="type-b">
          <!-- Similar structure for Type B -->
        </VTabsWindowItem>
      </VTabsWindow>
    </VCard>
  </div>
</template>
```

---

## List Page with KPIs Pattern

```vue
<template>
  <div class="entity-list-page d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('entities.list.title')"
      :subtitle="$t('entities.list.subtitle')"
      icon="tabler-folder"
    >
      <template #actions>
        <VBtn color="primary" prepend-icon="tabler-plus" @click="onCreate">
          {{ $t('New Entity') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 2, sm: 2, md: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <VCard>
      <WpPageToolbar ... />
      <WpResponsiveList ... />
    </VCard>
  </div>
</template>
```

---

## List Page with Filters Pattern

```vue
<script setup>
const filterDialog = ref(false)
const filters = reactive({
  status: null,
  department_id: null,
})

const activeFilterCount = computed(() =>
  Object.values(filters).filter(v => v !== null && v !== '').length
)

const removeFilter = key => {
  filters[key] = null
  refresh()
}

const resetFilters = () => {
  Object.keys(filters).forEach(key => filters[key] = null)
  refresh()
}
</script>

<template>
  <VCard>
    <WpPageToolbar
      v-model:search="search"
      :show-filter="true"
      :active-filter-count="activeFilterCount"
      @open-filters="filterDialog = true"
      @refresh="refresh"
    >
      <template #filter-chips>
        <WpActiveFilters
          v-if="activeFilterCount > 0"
          :filters="filters"
          :label-map="{ status: $t('Status'), department_id: $t('Department') }"
          @remove="removeFilter"
          @clear-all="resetFilters"
        />
      </template>
    </WpPageToolbar>

    <WpResponsiveList ... />
  </VCard>

  <WpFilterDialog
    v-model="filterDialog"
    :active-count="activeFilterCount"
    @apply="() => { filterDialog = false; refresh() }"
    @reset="resetFilters"
  >
    <VRow>
      <VCol cols="12">
        <AppSelect
          v-model="filters.status"
          :label="$t('Status')"
          :items="statusOptions"
          clearable
        />
      </VCol>
      <VCol cols="12">
        <AppSelect
          v-model="filters.department_id"
          :label="$t('Department')"
          :items="departmentOptions"
          clearable
        />
      </VCol>
    </VRow>
  </WpFilterDialog>
</template>
```

---

## Theme & Styling Guidelines

### Use Vuexy Theme Tokens

Always use Vuexy's theme color names instead of hardcoded colors:

**✅ Correct:**
```vue
<VChip color="success">Active</VChip>
<VChip color="error">Inactive</VChip>
<VBtn color="primary">Save</VBtn>
```

**❌ Incorrect:**
```vue
<VChip color="#4ade80">Active</VChip>
<div style="color: #ef4444">Error</div>
```

### Available Theme Colors

- `primary` - Main brand color
- `secondary` - Secondary actions
- `success` - Positive states (active, completed, approved)
- `error` - Negative states (inactive, failed, rejected)
- `warning` - Caution states (pending, expiring)
- `info` - Informational states (scheduled, in progress)

### CSS Custom Properties

When you need custom colors in styles, use CSS variables:

```vue
<style scoped>
.custom-element {
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgb(var(--v-theme-on-surface));
}
</style>
```

---

## Examples from Existing Pages

### Departments & Positions Page
Location: `resources/js/pages/people-skills/departments/list/index.vue`
- Two-tab layout (Departments / Positions)
- WpResponsiveList for both tabs
- Simple CRUD dialogs with VDialog

### Teams Page
Location: `resources/js/pages/people-skills/teams/list/index.vue`
- Single entity list
- WpDetailDialog for viewing team members
- Department filter integration

### Skills & Certifications Page
Location: `resources/js/pages/people-skills/skills/list/index.vue`
- Two-tab layout with client-side filtering
- hide-pagination for in-memory lists
- Separate CRUD dialogs per tab

### Training Programs & Sessions Page
Location: `resources/js/pages/people-skills/training/list/index.vue`
- KPI cards at top
- Two-tab layout (Sessions / Programs)
- WpDetailDialog for participant management

### Approved Drivers Page
Location: `resources/js/pages/people-skills/fleet/drivers/index.vue`
- KPI cards
- WpFilterDialog with multiple filter fields
- WpActiveFilters for active filter chips
- Status-based action menus

---

## Composables

### useListPage
Manages pagination, sorting, and search for entity lists.

```js
import { useListPage } from '@/composables/list'

const listPage = useListPage({
  fetchFn: params => store.fetchList(params),
  initialFilters: { status: null },
  initialSortKey: 'name',
  initialSortOrder: 'asc',
  immediate: true, // Auto-fetch on mount
})

// Available refs:
listPage.page.value
listPage.itemsPerPage.value
listPage.sortBy.value
listPage.search.value
listPage.loading.value
listPage.error.value

// Methods:
listPage.refresh()
listPage.fetchData()
```

### useDialog
Manages dialog open/close state with optional item tracking.

```js
import { useDialog } from '@/composables/dialog/useDialog'

const editDialog = useDialog()
const deleteDialog = useDialog()

// Open with item ID/data
editDialog.open(item)
deleteDialog.open(item.id)

// Check state
editDialog.isOpen.value // boolean
editDialog.item.value    // stored item

// Close
editDialog.close()
```

---

## Best Practices

1. **Always provide mobile-card slot** - Never rely only on desktop table view
2. **Use theme colors** - No hardcoded hex colors or RGB values
3. **Respect permissions** - Conditionally show create/edit/delete based on `$can()`
4. **Provide i18n keys** - All user-facing text should use `$t()` or `t()`
5. **Handle loading & error states** - Always pass `loading` and `error` props
6. **Include proper ARIA labels** - Especially for icon-only buttons
7. **Keep dialogs consistent** - Use standard Vuetify VDialog with VCard structure
8. **Use WpRowActions for actions** - Consistent three-dot menu pattern
9. **Provide retry callbacks** - Allow users to retry failed loads
10. **Test responsive behavior** - Verify both desktop and mobile views work properly

---

## Common Gotchas

1. **Forgetting mobile-card slot** - The list will be empty on mobile if you don't provide it
2. **Not forwarding events** - Make sure to emit events from custom slots (e.g., @edit, @delete)
3. **Hardcoded widths** - Use responsive column configurations or let Vuetify handle it
4. **Missing imports** - Always import WpMobileCard even if you only see desktop view during development
5. **Status color inconsistency** - Use consistent color mapping (success=active, secondary=inactive, etc.)

---

## Migration Checklist

When converting an old entity page to use standardized components:

- [ ] Replace custom table with `WpResponsiveList`
- [ ] Add `WpPageToolbar` for search/create/refresh
- [ ] Implement `mobile-card` slot with `WpMobileCard`
- [ ] Use `WpRowActions` for action menus
- [ ] Replace hardcoded colors with theme tokens
- [ ] Add `WpPageHeader` at the top
- [ ] Use `useListPage` composable for pagination/sorting
- [ ] Use `useDialog` composable for modal management
- [ ] Add filter support with `WpFilterDialog` and `WpActiveFilters` if needed
- [ ] Test on mobile viewport
- [ ] Verify light and dark mode appearance

---

## Questions or Issues?

If you need to add a new entity list page or modify the standardized components, refer to the existing implementations:

- **Simple list**: Departments, Teams, Skills
- **Tabs**: Departments & Positions, Skills & Certifications
- **KPIs**: Training Programs, Approved Drivers
- **Filters**: Approved Drivers, Employees

All standardized components are in `resources/js/components/shared/`.
