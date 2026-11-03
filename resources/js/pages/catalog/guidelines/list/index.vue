<script setup>
import {
  AppFabSpeedDial,
  WpActiveFilters,
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
import GuidelineGridCard from '@/pages/catalog/guidelines/list/GuidelineGridCard.vue'
import { useGuidelineList } from '@/composables/catalog/guidelines/useGuidelineList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ViewGuidelineDialog from '@/views/catalog/guidelines/list/ViewGuidelineDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/catalog/guidelines/list',
  name: 'catalog-guidelines-list',
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
const guidelineStore = useGuidelineStore()
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

  // Headers & Options
  headers,
  statusOptions,

  // Selection
  selectedItems,
  clearSelection,

  // KPIs
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useGuidelineList()

// ── Dialog State ────────────────────────────────────────────────────────────
const viewDialogVisible = ref(false)
const selectedGuideline = ref(null)

const openView = async guideline => {
  try {
    const full = await guidelineStore.fetchGuideline(guideline.id)
    selectedGuideline.value = full
    viewDialogVisible.value = true
  } catch {
    snackbarStore.showMessage('Failed to load guideline details', 'error')
  }
}

// Delete confirmation dialog
const isDeleteDialogVisible = ref(false)
const guidelineToDelete = ref(null)
const isDeleting = ref(false)

const openDelete = guideline => {
  guidelineToDelete.value = guideline
  isDeleteDialogVisible.value = true
}

const confirmDelete = async () => {
  if (!guidelineToDelete.value) return
  isDeleting.value = true
  try {
    await guidelineStore.deleteGuideline(guidelineToDelete.value.id)
    snackbarStore.showMessage(t('catalog.guidelines.list.messages.delete_success', 'Guideline deleted successfully.'), 'success')
    isDeleteDialogVisible.value = false
    guidelineToDelete.value = null
    await refresh()
    await fetchStatistics()
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('catalog.guidelines.list.messages.delete_error', 'Failed to delete guideline.'), 'error')
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
    { label: t('catalog.guidelines.list.actions.view', 'View'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (ability.can('update', 'Catalog')) {
    actions.push({ label: t('catalog.guidelines.list.actions.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('delete', 'Catalog')) {
    actions.push({
      label: t('catalog.guidelines.list.actions.delete', 'Delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return actions
}

// ── Mobile FAB Actions ───────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'add-guideline',
    icon: 'tabler-plus',
    title: t('catalog.guidelines.list.actions.add_new', 'Add Guideline'),
    disabled: !ability.can('create', 'Catalog'),
    onClick: () => router.push({ name: 'catalog-guidelines-edit-id', params: { id: 'new' } }),
  },
  {
    id: 'toggle-view',
    icon: isGridView.value ? 'tabler-list' : 'tabler-layout-grid',
    title: isGridView.value
      ? t('catalog.guidelines.list.actions.list_view', 'List View')
      : t('catalog.guidelines.list.actions.grid_view', 'Grid View'),
    onClick: () => { isGridView.value = !isGridView.value },
  },
  {
    id: 'filter-guidelines',
    icon: 'tabler-filter',
    title: t('catalog.guidelines.list.filter.title', 'Filters'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="catalog-guidelines-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('catalog.guidelines.list.title', 'Maintenance Guidelines')"
      :subtitle="$t('catalog.guidelines.list.subtitle', 'Manage standard operating procedures, technical steps, and verification instructions')"
      icon="tabler-checklist"
    />

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
            :active="kpi.filterType ? (filters?.[kpi.filterType] === kpi.filterValue) : !filters?.is_active"
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
        :create-label="$can('create', 'Catalog') ? $t('catalog.guidelines.list.actions.add_new', 'Add Guideline') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="router.push({ name: 'catalog-guidelines-edit-id', params: { id: 'new' } })"
        @refresh="() => { refresh(); fetchStatistics() }"
        @open-filters="openFilterDialog"
      >
        <template #extra-actions>
          <div class="d-none d-md-flex align-center gap-2">
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
                :title="$t('catalog.guidelines.list.actions.table_view', 'Table View')"
              />
              <VBtn
                :value="Boolean(true)"
                icon="tabler-layout-grid"
                :title="$t('catalog.guidelines.list.actions.grid_view', 'Cards View')"
              />
            </VBtnToggle>
          </div>
        </template>

        <!-- Active filter chips -->
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterCount > 0"
            :filters="activeFilters"
            :label-map="{
              is_active: $t('catalog.guidelines.list.filter.status', 'Status'),
            }"
            :value-formatters="{
              is_active: val => (val === '1' ? 'Active' : 'Inactive'),
            }"
            @remove="removeFilter($event.key)"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- 👉 Grid View -->
      <div
        v-if="isGridView"
        class="pa-4"
      >
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
        <div
          v-else-if="!items || items.length === 0"
          class="d-flex flex-column align-center justify-center py-12 text-disabled"
        >
          <VIcon
            icon="tabler-clipboard-off"
            size="64"
            class="mb-3 opacity-50"
          />
          <h6 class="text-h6 font-weight-medium mb-1">
            {{ $t('wp.states.empty_title', 'No guidelines found') }}
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
            {{ $t('catalog.guidelines.list.filter.all_statuses', 'Clear Filters') }}
          </VBtn>
        </div>
        <div v-else>
          <VRow>
            <VCol
              v-for="guideline in (items || [])"
              :key="guideline.id"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <GuidelineGridCard
                :guideline="guideline"
                @view="openView(guideline)"
                @edit="router.push({ name: 'catalog-guidelines-edit-id', params: { id: guideline.id } })"
                @delete="openDelete(guideline)"
              />
            </VCol>
          </VRow>
          <VDivider class="my-4" />
          <div class="d-flex align-center justify-space-between flex-wrap gap-4 pt-2">
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('Showing {start} to {end} of {total} guidelines', {
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

      <!-- 👉 Standard Server-side Responsive List -->
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
              color="primary"
              rounded
            >
              <VIcon
                icon="tabler-clipboard-check"
                size="20"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span
                class="font-weight-medium text-primary cursor-pointer hover:underline"
                @click="router.push({ name: 'catalog-guidelines-edit-id', params: { id: item.id } })"
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
              style="max-inline-size: 300px;"
            >
              {{ localizedValue(item.description) }}
            </span>
          </div>
        </template>

        <!-- Steps Count column -->
        <template #item.steps_count="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ item.steps_count ?? item.steps?.length ?? 0 }} steps
          </VChip>
        </template>

        <!-- Status column -->
        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'warning'"
            size="small"
            label
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </VChip>
        </template>

        <!-- Row Actions column -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions(item)"
            density="compact"
            @view="openView(item)"
            @edit="router.push({ name: 'catalog-guidelines-edit-id', params: { id: item.id } })"
            @delete="openDelete(item)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="localizedValue(item.name, item.code)"
            :subtitle="item.code"
            avatar-icon="tabler-clipboard-check"
            avatar-color="primary"
            clickable
            @click="openView(item)"
          >
            <template #actions>
              <div class="d-flex align-center gap-0">
                <IconBtn
                  size="small"
                  :title="$t('catalog.guidelines.list.actions.view')"
                  @click.stop="openView(item)"
                >
                  <VIcon
                    icon="tabler-eye"
                    size="20"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('update', 'Catalog')"
                  size="small"
                  :title="$t('catalog.guidelines.list.actions.edit')"
                  @click.stop="router.push({ name: 'catalog-guidelines-edit-id', params: { id: item.id } })"
                >
                  <VIcon
                    icon="tabler-edit"
                    size="20"
                  />
                </IconBtn>
                <WpRowActions
                  :actions="getRowActions(item)"
                  @view="openView(item)"
                  @edit="router.push({ name: 'catalog-guidelines-edit-id', params: { id: item.id } })"
                  @delete="openDelete(item)"
                />
              </div>
            </template>
            <template #content>
              <div class="d-flex align-center justify-space-between pt-1">
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ item.steps_count ?? item.steps?.length ?? 0 }} {{ $t('steps') }}
                </VChip>
                <VChip
                  :color="item.is_active ? 'success' : 'warning'"
                  size="x-small"
                  label
                >
                  {{ item.is_active ? 'Active' : 'Inactive' }}
                </VChip>
              </div>
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
      class="d-md-none catalog-guidelines-quick-actions"
    />

    <!-- ═══ Filter Dialog ═══ -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :title="$t('catalog.guidelines.list.filter.title', 'Filter Guidelines')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.is_active"
            :label="$t('catalog.guidelines.list.filter.status', 'Status')"
            :items="statusOptions"
            clearable
            density="comfortable"
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- ═══ Guideline View Dialog ═══ -->
    <ViewGuidelineDialog
      v-model="viewDialogVisible"
      :guideline="selectedGuideline"
    />

    <!-- ═══ Delete Confirmation Dialog ═══ -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('catalog.guidelines.list.delete_dialog.title', 'Delete Guideline')"
      :description="$t('catalog.guidelines.list.delete_dialog.description', 'Are you sure you want to delete this guideline? This action cannot be undone.')"
      :loading="isDeleting"
      :confirm-action="confirmDelete"
    />
  </div>
</template>

<style lang="scss" scoped>
.catalog-guidelines-list {
  padding-block-end: calc(72px + env(safe-area-inset-bottom, 0px));
}

.catalog-guidelines-quick-actions {
  inset-inline-end: 25px;
}
</style>
