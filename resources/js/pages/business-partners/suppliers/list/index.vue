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
import { useSupplierActions } from '@/composables/business-partners/suppliers/useSupplierActions'
import { useSupplierDialogs } from '@/composables/business-partners/suppliers/useSupplierDialogs'
import { useSupplierList } from '@/composables/business-partners/suppliers/useSupplierList'
import { localizedValue } from '@/composables/useLocaleField'
import AddNewSupplierDialog from '@/views/business-partners/suppliers/list/AddNewSupplierDialog.vue'
import ViewSupplierDialog from '@/views/business-partners/suppliers/list/ViewSupplierDialog.vue'
import BpBulkEmailDialog from '@/views/business-partners/shared-components/dialogs/BpBulkEmailDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/business-partners/suppliers/list',
  name: 'business-partners-suppliers-list',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'suppliers',
  },
})

const { t } = useI18n()
const router = useRouter()
const ability = useAbility()
const isMobileFabOpen = ref(false)

// ── Supplier List Orchestration ─────────────────────────────────────────────
const {
  suppliers,
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
  headers,
  statusOptions,
  categoryOptions,
  industryOptions,
  getStatusTitle,
  getCategoryTitle,
  getIndustryTitle,
  resolveStatusVariant,
  resolveCategoryVariant,
  selectedSuppliers,
  clearSelection,
  statistics,
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useSupplierList()

// ── Selection Mode State & Handlers ─────────────────────────────────────────
const isSelectionMode = ref(false)

const toggleSelectionMode = () => {
  isSelectionMode.value = !isSelectionMode.value
  if (!isSelectionMode.value) {
    clearSelection()
  }
}

const selectAllOnPage = () => {
  const pageIds = suppliers.value.map(s => s.id)

  selectedSuppliers.value = [...new Set([...selectedSuppliers.value, ...pageIds])]
}

const selectAllSuppliers = () => {
  selectedSuppliers.value = suppliers.value.map(s => s.id)
}

const deselectAll = () => {
  clearSelection()
}

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useSupplierDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useSupplierActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteSupplier = () => actions.deleteSupplier(dialogs.deleteDialog.item.value)

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

// ── Bulk Actions Configuration ───────────────────────────────────────────────
const bulkActionButtons = computed(() => {
  const actionsList = []

  if (ability.can('export', 'suppliers')) {
    actionsList.push({
      label: t('suppliers.list.actions.export_pdf', 'Export PDF'),
      icon: 'tabler-file-download',
      emit: 'export-pdf',
      color: 'secondary',
      loading: actions.isExportingListPdf.value,
    })
  }

  if (ability.can('create', 'email-messages')) {
    actionsList.push({
      label: t('suppliers.list.actions.send_mail', 'Send Email'),
      icon: 'tabler-mail',
      emit: 'send-email',
      color: 'primary',
    })
  }

  return actionsList
})

// ── Row Actions ──────────────────────────────────────────────────────────────
const getRowActions = () => {
  const rowActions = [
    { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (ability.can('update', 'suppliers')) {
    rowActions.push({ label: t('Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('delete', 'suppliers')) {
    rowActions.push({
      label: t('Delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return rowActions
}

const isBulkEmailDialogOpen = ref(false)

const openBulkEmail = () => {
  if (selectedSuppliers.value.length === 0)
    return

  isBulkEmailDialogOpen.value = true
}

const mobileFabActions = computed(() => [
  {
    id: 'add-supplier',
    icon: 'tabler-truck-delivery',
    title: t('suppliers.list.actions.add_new', 'Add New Supplier'),
    disabled: !ability.can('create', 'suppliers'),
    onClick: () => dialogs.addDialog.open(),
  },
  {
    id: 'toggle-selection',
    icon: isSelectionMode.value ? 'tabler-checkbox' : 'tabler-square-check',
    title: isSelectionMode.value ? t('Exit Selection Mode') : t('Selection Mode'),
    color: isSelectionMode.value ? 'primary' : undefined,
    onClick: toggleSelectionMode,
  },
  {
    id: 'export-pdf',
    icon: 'tabler-file-type-pdf',
    title: t('suppliers.list.actions.export_pdf', 'Export PDF'),
    disabled: selectedSuppliers.value.length === 0 || actions.isExportingListPdf.value || !ability.can('export', 'suppliers'),
    onClick: () => actions.exportListPdf(),
  },
  {
    id: 'send-email',
    icon: 'tabler-mail',
    title: t('suppliers.list.actions.send_mail', 'Send Email'),
    disabled: selectedSuppliers.value.length === 0 || !ability.can('create', 'email-messages'),
    onClick: openBulkEmail,
  },
  {
    id: 'filter-suppliers',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="business-partners-suppliers-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('suppliers.list.title', 'Suppliers')"
      :subtitle="$t('suppliers.list.subtitle', 'Manage supplier accounts, business details, and vendor relationships')"
      icon="tabler-truck-delivery"
    />

    <!-- 👉 KPI Cards Group -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 2, sm: 2, md: 3, lg: 5 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.titleKey"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="$t(kpi.titleKey, kpi.titleKey)"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.iconColor"
            :loading="metaLoading"
            clickable
            :active="kpi.filterValue !== null ? filters.status === kpi.filterValue : !filters.status"
            @click="handleKpiClick(kpi.filterValue)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card Container -->
    <VCard>
      <!-- 👉 Page Toolbar inside VCard -->
      <WpPageToolbar
        v-model:search="search"
        :create-label="$can('create', 'suppliers') ? $t('suppliers.list.actions.add_new', 'Add New Supplier') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="dialogs.addDialog.open()"
        @refresh="() => { refresh(); fetchStatistics() }"
        @open-filters="openFilterDialog"
      >
        <!-- Extra Action beside filter: Toggle Selection Mode (Desktop) -->
        <template #extra-actions>
          <VBtn
            icon
            size="small"
            :variant="isSelectionMode ? 'elevated' : 'text'"
            :color="isSelectionMode ? 'primary' : 'default'"
            class="d-none d-md-inline-flex"
            :aria-label="isSelectionMode ? $t('Exit Selection Mode') : $t('Selection Mode')"
            @click="toggleSelectionMode"
          >
            <VIcon
              :icon="isSelectionMode ? 'tabler-checkbox' : 'tabler-square-check'"
              size="20"
            />
            <VTooltip
              activator="parent"
              location="top"
            >
              {{ isSelectionMode ? $t('Exit Selection Mode') : $t('Selection Mode') }}
            </VTooltip>
          </VBtn>
        </template>

        <!-- Active filter chips below search bar -->
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterCount > 0"
            :filters="activeFilters"
            :label-map="{
              status: $t('suppliers.list.filter.status', 'Status'),
              supplier_category: $t('suppliers.list.filter.category', 'Category'),
              industry: $t('suppliers.list.filter.industry', 'Industry'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              supplier_category: val => getCategoryTitle(val),
              industry: val => getIndustryTitle(val),
            }"
            @remove="removeFilter($event.key)"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- 👉 Selection Mode Quick Controls Bar (visible when selection mode is active) -->
      <VExpandTransition>
        <div
          v-if="isSelectionMode"
          class="selection-mode-bar px-4 py-2 border-b d-flex align-center justify-space-between flex-wrap gap-2"
        >
          <div class="d-flex align-center gap-2 flex-wrap">
            <VChip
              size="small"
              color="primary"
              variant="elevated"
              prepend-icon="tabler-checkbox"
              class="font-weight-medium"
            >
              {{ $t('Selection Mode') }}
            </VChip>
            <VBtn
              size="x-small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-list-check"
              @click="selectAllOnPage"
            >
              {{ $t('Select Page') }} ({{ suppliers.length }})
            </VBtn>
            <VBtn
              size="x-small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-checks"
              @click="selectAllSuppliers"
            >
              {{ $t('Select All') }} ({{ totalItems }})
            </VBtn>
            <VBtn
              v-if="selectedSuppliers.length > 0"
              size="x-small"
              variant="text"
              color="error"
              prepend-icon="tabler-x"
              @click="deselectAll"
            >
              {{ $t('Deselect') }}
            </VBtn>
          </div>

          <div class="d-flex align-center gap-2">
            <span
              v-if="selectedSuppliers.length > 0"
              class="text-caption font-weight-bold text-primary"
            >
              {{ selectedSuppliers.length }} {{ $t('Selected') }}
            </span>
            <VBtn
              size="x-small"
              variant="text"
              color="secondary"
              @click="toggleSelectionMode"
            >
              {{ $t('Exit Selection Mode') }}
            </VBtn>
          </div>
        </div>
      </VExpandTransition>

      <!-- 👉 Bulk Actions Bar (only visible when rows are selected) -->
      <div class="px-4 d-none d-md-block">
        <WpBulkActions
          :count="selectedSuppliers.length"
          :actions="bulkActionButtons"
          @export-pdf="actions.exportListPdf()"
          @send-email="openBulkEmail"
          @clear="clearSelection"
        />
      </div>

      <!-- 👉 Server-side Responsive List -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedSuppliers"
        :headers="headers"
        :items="suppliers"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        :show-select="isSelectionMode"
        item-value="id"
        @retry="refresh"
      >
        <!-- ── Desktop Table Custom Slots ── -->

        <!-- Supplier column: Avatar + Legal / Commercial names -->
        <template #item.supplier="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveCategoryVariant(item.supplier_category)"
              rounded
            >
              <VImg
                v-if="item.logo_url"
                :src="item.logo_url"
              />
              <span
                v-else
                class="font-weight-medium"
              >
                {{ avatarText(localizedValue(item.legal_name)) }}
              </span>
            </VAvatar>

            <div class="d-flex flex-column">
              <RouterLink
                :to="{ name: 'business-partners-suppliers-edit-id', params: { id: item.id } }"
                class="text-base font-weight-medium text-link text-truncate bp-partner-name"
              >
                {{ localizedValue(item.commercial_name, localizedValue(item.legal_name)) }}
              </RouterLink>
              <span class="text-caption text-medium-emphasis d-flex align-center gap-1">
                {{ localizedValue(item.legal_name) }} •
                <VChip
                  size="x-small"
                  label
                  color="secondary"
                  variant="flat"
                >
                  {{ item.supplier_id }}
                </VChip>
              </span>
            </div>
          </div>
        </template>

        <!-- Contact & Location column -->
        <template #item.contact_location="{ item }">
          <div class="d-flex flex-column gap-1 py-2">
            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-mail"
                color="primary"
                class="shrink-0"
              />
              <span
                v-if="item.primary_email"
                class="text-body-2 text-truncate bp-partner-email"
              >{{ item.primary_email }}</span>
              <span
                v-else
                class="text-caption text-disabled"
              >—</span>
            </div>

            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-phone"
                color="success"
                class="shrink-0"
              />
              <span
                v-if="item.primary_phone"
                class="text-body-2 text-truncate bp-partner-phone"
              >{{ item.primary_phone }}</span>
              <span
                v-else
                class="text-caption text-disabled"
              >—</span>
            </div>

            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-map-pin"
                color="error"
                class="shrink-0"
              />
              <span
                v-if="localizedValue(item.hq_city) || localizedValue(item.hq_address)"
                class="text-body-2 text-truncate bp-partner-location"
              >
                {{ localizedValue(item.hq_city) }}{{ item.hq_postcode ? `, ${item.hq_postcode}` : '' }}
              </span>
              <span
                v-else
                class="text-caption text-disabled"
              >—</span>
            </div>
          </div>
        </template>

        <!-- Industry column -->
        <template #item.industry="{ item }">
          <span
            v-if="item.industry"
            class="text-capitalize font-weight-medium"
          >
            {{ getIndustryTitle(item.industry) }}
          </span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Category column -->
        <template #item.supplier_category="{ item }">
          <VChip
            v-if="item.supplier_category"
            :color="resolveCategoryVariant(item.supplier_category)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getCategoryTitle(item.supplier_category) }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >—</span>
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

        <!-- Desktop Actions column -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions(item)"
            @view="dialogs.openView(item.id)"
            @edit="router.push({ name: 'business-partners-suppliers-edit-id', params: { id: item.id } })"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="supplier-mobile-card"
            :title="localizedValue(item.commercial_name, localizedValue(item.legal_name))"
            :subtitle="localizedValue(item.legal_name)"
            :status="item.status"
            :status-label="getStatusTitle(item.status)"
            :status-color="resolveStatusVariant(item.status)"
            clickable
            :show-select="false"
            :selected="selected"
            @update:selected="updateSelected"
            @click="isSelectionMode ? updateSelected(!selected) : dialogs.openView(item.id)"
          >
            <template #avatar>
              <VAvatar
                size="40"
                variant="tonal"
                :color="resolveCategoryVariant(item.supplier_category)"
                rounded
              >
                <VImg
                  v-if="item.logo_url"
                  :src="item.logo_url"
                  cover
                />
                <span
                  v-else
                  class="font-weight-medium"
                >
                  {{ avatarText(localizedValue(item.legal_name) || localizedValue(item.commercial_name)) }}
                </span>
              </VAvatar>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="dialogs.openView(item.id)"
                @edit="router.push({ name: 'business-partners-suppliers-edit-id', params: { id: item.id } })"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #badges>
              <div
                class="d-flex flex-wrap gap-1 align-center min-w-0"
                style="display: contents;"
              >
                <VChip
                  v-if="item.supplier_id"
                  size="x-small"
                  label
                  color="secondary"
                  variant="tonal"
                  class="font-weight-medium flex-shrink-0"
                >
                  {{ item.supplier_id }}
                </VChip>
                <VChip
                  v-if="item.supplier_category"
                  size="x-small"
                  label
                  :color="resolveCategoryVariant(item.supplier_category)"
                  variant="tonal"
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getCategoryTitle(item.supplier_category) }}
                </VChip>
                <VChip
                  v-if="item.industry"
                  size="x-small"
                  label
                  variant="tonal"
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getIndustryTitle(item.industry) }}
                </VChip>
              </div>
            </template>

            <template #details>
              <div class="d-flex flex-column gap-1.5">
                <div
                  v-if="item.primary_email"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="14"
                    icon="tabler-mail"
                    color="primary"
                    class="wp-mobile-row__icon"
                  />
                  <span class="wp-mobile-row__value">{{ item.primary_email }}</span>
                </div>
                <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                  <div
                    v-if="item.primary_phone"
                    class="wp-mobile-row"
                  >
                    <VIcon
                      size="14"
                      icon="tabler-phone"
                      color="success"
                      class="wp-mobile-row__icon"
                    />
                    <span class="wp-mobile-row__value">{{ item.primary_phone }}</span>
                  </div>
                  <div
                    v-if="localizedValue(item.hq_city) || localizedValue(item.hq_address)"
                    class="wp-mobile-row"
                  >
                    <VIcon
                      size="14"
                      icon="tabler-map-pin"
                      color="error"
                      class="wp-mobile-row__icon"
                    />
                    <span class="wp-mobile-row__value">
                      {{ localizedValue(item.hq_city) }}{{ item.hq_postcode ? `, ${item.hq_postcode}` : '' }}
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Filter Dialog -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="activeFilterCount"
      :title="$t('suppliers.list.filter.title', 'Filter Suppliers')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.supplier_category"
            :label="$t('suppliers.list.filter.category', 'Category')"
            :items="categoryOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.industry"
            :label="$t('suppliers.list.filter.industry', 'Industry')"
            :items="industryOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('suppliers.list.filter.status', 'Status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Supplier Dialog (mirror of edit page in read-only mode) -->
    <ViewSupplierDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :supplier-id="dialogs.viewDialog.item.value"
      :supplier="dialogs.viewSupplier.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Contextual Bulk Email Dialog -->
    <BpBulkEmailDialog
      v-model="isBulkEmailDialogOpen"
      partner-type="supplier"
      :partner-ids="selectedSuppliers"
      @completed="clearSelection(); refresh()"
    />

    <!-- 👉 Add New Supplier Dialog -->
    <AddNewSupplierDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      :loading="actions.isCreating.value"
      @add-supplier="actions.createSupplier"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('suppliers.list.delete_dialog.title', 'Are you sure you want to delete this supplier?')"
      :description="$t('suppliers.list.delete_dialog.description', 'This action cannot be undone.')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteSupplier"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none suppliers-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.suppliers-list-quick-actions {
  inset-inline-end: 25px;
}

.supplier-mobile-card {
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .business-partners-suppliers-list {
    padding-block-end: 7rem;
  }
}
</style>
