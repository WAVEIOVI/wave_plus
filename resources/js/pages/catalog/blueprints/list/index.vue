<script setup>
import {
  AppFabSpeedDial,
  WpActiveFilters,
  WpBulkActions,
  WpDeleteDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useEquipmentBlueprintList } from '@/composables/catalog/blueprints/useEquipmentBlueprintList'
import { localizedValue } from '@/composables/useLocaleField'
import EquipmentBlueprintGridCard from '@/pages/catalog/blueprints/list/EquipmentBlueprintGridCard.vue'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ViewBlueprintDialog from '@/views/catalog/blueprints/list/ViewBlueprintDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/catalog/blueprints/list',
  name: 'catalog-blueprints-list',
  meta: {
    layout: 'default',
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})

const { t } = useI18n()
const router = useRouter()
const ability = useAbility()
const blueprintStore = useEquipmentBlueprintStore()
const snackbarStore = useSnackbarStore()

const isMobileFabOpen = ref(false)

// ── Orchestration Composable ────────────────────────────────────────────────
const {
  items,
  totalItems,
  loading,
  error,
  search,
  filters,
  stagedFilters,
  activeFilters,
  activeFilterCount,
  setFilter,
  removeFilter,
  resetFilters,
  stageFilters,
  applyStaged,
  resetStaged,
  page,
  itemsPerPage,
  sortBy,
  refresh,

  // View mode
  isGridView,

  // Options & resolvers
  headers,
  statusOptions,
  classOptions,
  getStatusTitle,
  getClassTitle,
  resolveStatusVariant,
  resolveClassVariant,

  // Selection
  selectedItems,
  clearSelection,

  // KPIs
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useEquipmentBlueprintList()

// ── Dialog State ────────────────────────────────────────────────────────────
const viewDialogVisible = ref(false)
const selectedBlueprint = ref(null)

const openView = blueprint => {
  selectedBlueprint.value = blueprint
  viewDialogVisible.value = true
}

// Delete Confirmation Dialog
const isDeleteDialogVisible = ref(false)
const blueprintToDelete = ref(null)
const isDeleting = ref(false)

const openDelete = blueprint => {
  blueprintToDelete.value = blueprint
  isDeleteDialogVisible.value = true
}

const confirmDelete = async () => {
  if (!blueprintToDelete.value) return
  isDeleting.value = true
  try {
    await blueprintStore.deleteBlueprint(blueprintToDelete.value.id)
    snackbarStore.showMessage(t('catalog.blueprints.list.messages.delete_success', 'Equipment blueprint deleted successfully.'), 'success')
    isDeleteDialogVisible.value = false
    blueprintToDelete.value = null
    await refresh()
    await fetchStatistics()
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('catalog.blueprints.list.messages.delete_error', 'Failed to delete blueprint.'), 'error')
  } finally {
    isDeleting.value = false
  }
}

// ── Filter Dialog handlers ───────────────────────────────────────────────────
const isFilterDialogOpen = ref(false)

const openFilterDialog = () => {
  stageFilters()
  isFilterDialogOpen.value = true
}

const handleApplyFilters = () => {
  applyStaged()
  isFilterDialogOpen.value = false
}

const handleResetFilters = () => {
  resetStaged()
  applyStaged()
  isFilterDialogOpen.value = false
}

// ── Row Actions ──────────────────────────────────────────────────────────────
const getRowActions = item => {
  const actions = [
    { label: t('catalog.blueprints.list.actions.view', 'View'), icon: 'tabler-eye', emit: 'view' },
  ]

  actions.push({
    label: t('catalog.blueprints.list.actions.test_bench', 'Test Bench'),
    icon: 'tabler-device-desktop-analytics',
    emit: 'test-bench',
    color: 'warning',
  })

  if (ability.can('update', 'Catalog')) {
    actions.push({ label: t('catalog.blueprints.list.actions.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('delete', 'Catalog')) {
    actions.push({
      label: t('catalog.blueprints.list.actions.delete', 'Delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return actions
}

// ── Bulk Actions Configuration ───────────────────────────────────────────────
const bulkActionButtons = computed(() => [
  {
    label: t('catalog.blueprints.list.actions.test_bench', 'Test Bench'),
    icon: 'tabler-device-desktop-analytics',
    emit: 'test-bench',
    color: 'warning',
  },
])

// ── Mobile FAB Actions ───────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'add-blueprint',
    icon: 'tabler-plus',
    title: t('catalog.blueprints.list.actions.add_new', 'Add Blueprint'),
    disabled: !ability.can('create', 'Catalog'),
    onClick: () => router.push({ name: 'catalog-blueprints-edit-id', params: { id: 'new' } }),
  },
  {
    id: 'test-bench',
    icon: 'tabler-device-desktop-analytics',
    title: t('catalog.blueprints.list.actions.test_bench', 'Technician Test Bench'),
    onClick: () => router.push({ name: 'catalog-blueprints-test-bench' }),
  },
  {
    id: 'toggle-view',
    icon: isGridView.value ? 'tabler-list' : 'tabler-layout-grid',
    title: isGridView.value ? t('catalog.blueprints.list.actions.list_view', 'List View') : t('catalog.blueprints.list.actions.grid_view', 'Grid View'),
    onClick: () => { isGridView.value = !isGridView.value },
  },
  {
    id: 'filter-blueprints',
    icon: 'tabler-filter',
    title: t('catalog.blueprints.list.filter.title', 'Filters'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="catalog-blueprints-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('catalog.blueprints.list.title', 'Equipment Blueprints')"
      :subtitle="$t('catalog.blueprints.list.subtitle', 'Define technical configurations, maintenance cycles, and service tasks for equipment classes')"
      icon="tabler-file-settings"
    >
      <template #actions>
        <VBtn
          color="warning"
          variant="tonal"
          prepend-icon="tabler-device-desktop-analytics"
          :to="{ name: 'catalog-blueprints-test-bench' }"
          class="me-2 d-none d-md-inline-flex"
        >
          {{ $t('catalog.blueprints.list.actions.test_bench', 'Technician Test Bench') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- 👉 KPI Cards Group -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.titleKey"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="$t(kpi.titleKey)"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.iconColor"
            :loading="metaLoading"
            clickable
            :active="kpi.filterType ? (filters?.[kpi.filterType] === kpi.filterValue) : (!filters?.status && !filters?.equipment_class_id)"
            @click="handleKpiClick(kpi)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card Container -->
    <VCard>
      <!-- 👉 Page Toolbar -->
      <WpPageToolbar
        v-model:search="search"
        :create-label="$can('create', 'Catalog') ? $t('catalog.blueprints.list.actions.add_new', 'Add Blueprint') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="router.push({ name: 'catalog-blueprints-edit-id', params: { id: 'new' } })"
        @refresh="() => { refresh(); fetchStatistics() }"
        @open-filters="openFilterDialog"
      >
        <!-- Additional desktop toolbar actions -->
        <template #extra-actions>
          <div class="d-none d-md-flex align-center gap-2">
            <!-- Grid / Table toggle -->
            <VBtnToggle
              v-model="isGridView"
              color="primary"
              variant="outlined"
              density="compact"
              mandatory
            >
              <VBtn
                :value="false"
                icon="tabler-table"
                :title="$t('catalog.blueprints.list.actions.table_view', 'Table View')"
              />
              <VBtn
                :value="Boolean(true)"
                icon="tabler-layout-grid"
                :title="$t('catalog.blueprints.list.actions.grid_view', 'Cards View')"
              />
            </VBtnToggle>
          </div>
        </template>

        <!-- Active filter chips below search bar -->
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterCount > 0"
            :filters="activeFilters"
            :label-map="{
              status: $t('catalog.blueprints.list.filter.status', 'Status'),
              equipment_class_id: $t('catalog.blueprints.list.filter.equipment_class', 'Equipment Class'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              equipment_class_id: val => {
                const item = classOptions.find(c => c.value === val)
                return item ? item.title : val
              },
            }"
            @remove="removeFilter($event.key)"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- 👉 Bulk Actions Bar -->
      <div
        v-if="selectedItems.length > 0"
        class="px-4 d-none d-md-block"
      >
        <WpBulkActions
          :count="selectedItems.length"
          :actions="bulkActionButtons"
          @test-bench="selectedItems[0] && router.push({ name: 'catalog-blueprints-test-bench', query: { blueprint_id: selectedItems[0] } })"
          @clear="clearSelection"
        />
      </div>

      <!-- 👉 Grid View Container (when grid mode active) -->
      <div
        v-if="isGridView"
        class="pa-4"
      >
        <!-- Loading State -->
        <div
          v-if="loading"
          class="d-flex justify-center align-center py-12"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="48"
          />
        </div>

        <!-- Empty State -->
        <div
          v-else-if="!items || items.length === 0"
          class="d-flex flex-column align-center justify-center py-12 text-disabled"
        >
          <VIcon
            icon="tabler-file-off"
            size="64"
            class="mb-3 opacity-50"
          />
          <h6 class="text-h6 font-weight-medium mb-1">
            {{ $t('wp.states.empty_title', 'No equipment blueprints found') }}
          </h6>
          <p class="text-body-2 mb-4">
            {{ $t('wp.states.empty_text', 'Try adjusting your search or filters.') }}
          </p>
          <VBtn
            variant="tonal"
            color="primary"
            size="small"
            @click="resetFilters"
          >
            {{ $t('Clear Filters') }}
          </VBtn>
        </div>

        <!-- Items Grid -->
        <div v-else>
          <VRow>
            <VCol
              v-for="blueprint in (items || [])"
              :key="blueprint.id"
              cols="12"
              sm="6"
              md="4"
              lg="3"
              class="d-flex flex-column"
            >
              <EquipmentBlueprintGridCard
                :blueprint="blueprint"
                @view="openView(blueprint)"
                @edit="router.push({ name: 'catalog-blueprints-edit-id', params: { id: blueprint.id } })"
                @test-bench="router.push({ name: 'catalog-blueprints-test-bench', query: { blueprint_id: blueprint.id } })"
                @delete="openDelete(blueprint)"
              />
            </VCol>
          </VRow>

          <!-- Grid Pagination Bar -->
          <VDivider class="my-4" />
          <div class="d-flex align-center justify-space-between flex-wrap gap-4 pt-2">
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('Showing {start} to {end} of {total} blueprints', {
                start: (page - 1) * itemsPerPage + 1,
                end: Math.min(page * itemsPerPage, totalItems),
                total: totalItems,
              }) }}
            </span>
            <VPagination
              v-model="page"
              :length="Math.ceil(totalItems / itemsPerPage) || 1"
              :total-visible="$vuetify.display.xs ? 1 : 5"
              density="comfortable"
            />
          </div>
        </div>
      </div>

      <!-- 👉 Standard Server-side Responsive List (when list mode active) -->
      <WpResponsiveList
        v-else
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedItems"
        :headers="headers"
        :items="items"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        show-select
        item-value="id"
        @retry="refresh"
      >
        <!-- Desktop Table: Code + Avatar -->
        <template #item.code="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveClassVariant(item.equipment_class)"
              rounded
            >
              <VIcon
                icon="tabler-file-settings"
                size="20"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span
                class="font-weight-medium text-primary cursor-pointer hover:underline"
                @click="router.push({ name: 'catalog-blueprints-edit-id', params: { id: item.id } })"
              >
                {{ item.code }}
              </span>
              <span class="text-xs text-disabled">
                ID #{{ item.id }}
              </span>
            </div>
          </div>
        </template>

        <!-- Name column -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-semibold text-body-2">
              {{ localizedValue(item.name, '—') }}
            </span>
            <span
              v-if="item.description"
              class="text-xs text-medium-emphasis text-truncate"
              style="max-inline-size: 260px;"
            >
              {{ localizedValue(item.description) }}
            </span>
          </div>
        </template>

        <!-- Equipment Class column -->
        <template #item.equipment_class="{ item }">
          <VChip
            :color="resolveClassVariant(item.equipment_class)"
            size="small"
            label
          >
            {{ getClassTitle(item) }}
          </VChip>
        </template>

        <!-- Shelf life column -->
        <template #item.shelf_life_years="{ item }">
          <span class="text-body-2">
            {{ item.shelf_life_years ? `${item.shelf_life_years} yrs` : '—' }}
          </span>
        </template>

        <!-- Status column -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getStatusTitle(item.status) }}
          </VChip>
        </template>

        <!-- Row Actions column -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions(item)"
            density="compact"
            @view="openView(item)"
            @edit="router.push({ name: 'catalog-blueprints-edit-id', params: { id: item.id } })"
            @test-bench="router.push({ name: 'catalog-blueprints-test-bench', query: { blueprint_id: item.id } })"
            @delete="openDelete(item)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="localizedValue(item.name, item.code)"
            :subtitle="item.code"
            avatar-icon="tabler-file-settings"
            :avatar-color="resolveClassVariant(item.equipment_class)"
            :status="getStatusTitle(item.status)"
            :status-color="resolveStatusVariant(item.status)"
            clickable
            @click="openView(item)"
          >
            <template #badges>
              <VChip
                size="x-small"
                :color="resolveClassVariant(item.equipment_class)"
                label
              >
                {{ getClassTitle(item) }}
              </VChip>
              <VChip
                v-if="item.working_pressure_bar"
                size="x-small"
                variant="outlined"
              >
                {{ item.working_pressure_bar }} bar
              </VChip>
              <VChip
                v-if="item.shelf_life_years"
                size="x-small"
                variant="outlined"
              >
                {{ item.shelf_life_years }}y shelf
              </VChip>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="openView(item)"
                @edit="router.push({ name: 'catalog-blueprints-edit-id', params: { id: item.id } })"
                @test-bench="router.push({ name: 'catalog-blueprints-test-bench', query: { blueprint_id: item.id } })"
                @delete="openDelete(item)"
              />
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- ═══ Mobile FAB Speed Dial ═══ -->
    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
      class="d-md-none catalog-blueprints-quick-actions"
    />

    <!-- ═══ Filter Dialog ═══ -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :title="$t('catalog.blueprints.list.filter.title', 'Filter Blueprints')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <!-- Status Filter -->
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('catalog.blueprints.list.filter.status', 'Status')"
            :items="statusOptions"
            clearable
            density="comfortable"
          />
        </VCol>

        <!-- Equipment Class Filter -->
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.equipment_class_id"
            :label="$t('catalog.blueprints.list.filter.equipment_class', 'Equipment Class')"
            :items="classOptions"
            clearable
            density="comfortable"
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- ═══ Blueprint View Dialog ═══ -->
    <ViewBlueprintDialog
      v-model="viewDialogVisible"
      :blueprint="selectedBlueprint"
    />

    <!-- ═══ Delete Confirmation Dialog ═══ -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('catalog.blueprints.list.delete_dialog.title', 'Delete Equipment Blueprint')"
      :description="$t('catalog.blueprints.list.delete_dialog.description', 'Are you sure you want to delete this blueprint? This action cannot be undone.')"
      :loading="isDeleting"
      :confirm-action="confirmDelete"
    />
  </div>
</template>

<style lang="scss" scoped>
.catalog-blueprints-list {
  padding-block-end: calc(72px + env(safe-area-inset-bottom, 0px));
}

.catalog-blueprints-quick-actions {
  inset-inline-end: 25px;
}
</style>
