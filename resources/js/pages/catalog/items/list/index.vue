<script setup>
import {
  AppFabSpeedDial,
  WpActiveFilters,
  WpBulkActions,
  WpDeleteDialog,
  WpEmailDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useCatalogItemActions } from '@/composables/catalog/items/useCatalogItemActions'
import { useCatalogItemDialogs } from '@/composables/catalog/items/useCatalogItemDialogs'
import { useCatalogItemList } from '@/composables/catalog/items/useCatalogItemList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import CatalogItemGridCard from '@/views/catalog/items/list/CatalogItemGridCard.vue'
import ViewCatalogItemDialog from '@/views/catalog/items/list/ViewCatalogItemDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/catalog/items/list',
  name: 'catalog-items-list',
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
const isMobileFabOpen = ref(false)

// ── Catalog Item List Orchestration ─────────────────────────────────────────
// useCatalogItemList owns: fetching, sorting, filtering, pagination, KPIs, selection, view mode.
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
  typeOptions,
  categoryItems,
  getStatusTitle,
  getTypeTitle,
  getSubtypeTitle,
  resolveStatusVariant,
  resolveTypeVariant,

  // Selection
  selectedItems,
  clearSelection,

  // KPIs
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useCatalogItemList()

// ── Dialog State ────────────────────────────────────────────────────────────
// useCatalogItemDialogs owns: view/delete/email dialog open states + async item loading.
const dialogs = useCatalogItemDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
// useCatalogItemActions owns: delete/email/export with notifications.
const actions = useCatalogItemActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteItem = () => actions.deleteItem(dialogs.deleteDialog.item.value)

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
const bulkActionButtons = computed(() => [
  {
    label: t('catalog.items.list.actions.export_pdf'),
    icon: 'tabler-file-download',
    emit: 'export-pdf',
    color: 'secondary',
    loading: actions.isExportingListPdf.value,
  },
  {
    label: t('catalog.items.list.actions.send_mail'),
    icon: 'tabler-mail',
    emit: 'send-email',
    color: 'primary',
  },
])

// ── Row Actions ──────────────────────────────────────────────────────────────
const getRowActions = item => {
  const rowActionsList = [
    { label: t('catalog.items.list.actions.view'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (ability.can('update', 'Catalog')) {
    rowActionsList.push({ label: t('catalog.items.list.actions.edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('read', 'Catalog')) {
    rowActionsList.push(
      { label: t('catalog.items.list.actions.export_pdf'), icon: 'tabler-file-download', emit: 'export-pdf' },
      { label: t('catalog.items.list.actions.send_single_mail'), icon: 'tabler-mail', emit: 'send-email' },
    )
  }

  if (ability.can('delete', 'Catalog')) {
    rowActionsList.push({
      label: t('catalog.items.list.actions.delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return rowActionsList
}

// ── Email send handler ───────────────────────────────────────────────────────
const handleSendEmail = async emailData => {
  const success = await actions.sendEmail({
    itemId: dialogs.emailDialog.value.itemId,
    emailData,
  })

  if (success) {
    dialogs.closeEmail()
  }
}

// ── Stock indicator color ────────────────────────────────────────────────────
const getStockColor = stock => {
  if (stock === null || stock === undefined) return 'grey'
  if (stock === 0) return 'error'
  if (stock > 0 && stock <= 10) return 'warning'

  return 'success'
}

// ── Mobile FAB Actions ───────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'add-item',
    icon: 'tabler-plus',
    title: t('catalog.items.list.actions.add_new'),
    disabled: !ability.can('create', 'Catalog'),
    onClick: () => router.push({ name: 'catalog-items-edit-id', params: { id: 'new' } }),
  },
  {
    id: 'export-pdf',
    icon: 'tabler-file-type-pdf',
    title: t('catalog.items.list.actions.export_pdf'),
    disabled: actions.isExportingListPdf.value,
    onClick: () => actions.exportListPdf(),
  },
  {
    id: 'send-email',
    icon: 'tabler-mail',
    title: t('catalog.items.list.actions.send_mail'),
    onClick: () => dialogs.openEmail(),
  },
  {
    id: 'toggle-view',
    icon: isGridView.value ? 'tabler-list' : 'tabler-layout-grid',
    title: isGridView.value ? t('catalog.items.list.actions.list_view') : t('catalog.items.list.actions.grid_view'),
    onClick: () => { isGridView.value = !isGridView.value },
  },
  {
    id: 'filter-items',
    icon: 'tabler-filter',
    title: t('catalog.items.list.filter.title'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="catalog-items-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('catalog.items.list.title')"
      :subtitle="$t('catalog.items.list.subtitle')"
      icon="tabler-packages"
    />

    <!-- 👉 KPI Cards Group -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
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
            :active="kpi.filterType ? (filters[kpi.filterType] === kpi.filterValue) : (!filters.status && !filters.item_type)"
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
        :create-label="$can('create', 'Catalog') ? $t('catalog.items.list.actions.add_new') : ''"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="router.push({ name: 'catalog-items-edit-id', params: { id: 'new' } })"
        @refresh="() => { refresh(); fetchStatistics() }"
        @open-filters="openFilterDialog"
      >
        <!-- Additional desktop toolbar actions -->
        <template #extra-actions>
          <div class="d-flex align-center gap-2">
            <!-- Grid / List toggle -->
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
                :title="$t('catalog.items.list.actions.table_view', 'Table View')"
              />
              <VBtn
                :value="Boolean(true)"
                icon="tabler-layout-grid"
                :title="$t('catalog.items.list.actions.grid_view', 'Cards View')"
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
              status: $t('catalog.items.list.filter.status'),
              item_type: $t('catalog.items.list.filter.item_type'),
              category_id: $t('catalog.items.list.filter.category'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              item_type: val => getTypeTitle(val),
              category_id: val => {
                const item = categoryItems.find(c => c.value === val)
                return item ? item.title : val
              },
            }"
            @remove="removeFilter($event.key)"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- 👉 Bulk Actions Bar (visible when rows are selected) -->
      <div
        v-if="selectedItems.length > 0"
        class="px-4 d-none d-md-block"
      >
        <WpBulkActions
          :count="selectedItems.length"
          :actions="bulkActionButtons"
          @export-pdf="actions.exportListPdf()"
          @send-email="dialogs.openEmail()"
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
          v-else-if="items.length === 0"
          class="d-flex flex-column align-center justify-center py-12 text-disabled"
        >
          <VIcon
            icon="tabler-package-off"
            size="64"
            class="mb-3 opacity-50"
          />
          <h6 class="text-h6 font-weight-medium mb-1">
            {{ $t('wp.states.empty_title', 'No catalog items found') }}
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
            {{ $t('catalog.items.list.filter.all_statuses', 'Clear Filters') }}
          </VBtn>
        </div>

        <!-- Items Grid -->
        <div v-else>
          <VRow>
            <VCol
              v-for="item in items"
              :key="item.id"
              cols="12"
              sm="6"
              md="4"
              lg="3"
              class="d-flex flex-column"
            >
              <CatalogItemGridCard
                :item="item"
                @view="dialogs.openView(item.id)"
                @edit="router.push({ name: 'catalog-items-edit-id', params: { id: item.id } })"
                @export-pdf="actions.exportSinglePdf(item)"
                @send-email="dialogs.openSingleEmail(item)"
                @delete="dialogs.openDelete(item.id)"
              />
            </VCol>
          </VRow>

          <!-- Grid Pagination Bar -->
          <VDivider class="my-4" />
          <div class="d-flex align-center justify-space-between flex-wrap gap-4 pt-2">
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('Showing {start} to {end} of {total} items', {
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
        <!-- ── Desktop Table Custom Slots ── -->
        <!-- Code + Avatar + Type Icon column -->
        <template #item.code="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveTypeVariant(item.item_type)"
              rounded
            >
              <VIcon
                size="22"
                :icon="item.item_type === 'product' ? 'tabler-box' : item.item_type === 'consumable' ? 'tabler-flame' : 'tabler-tools'"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <RouterLink
                :to="{ name: 'catalog-items-edit-id', params: { id: item.id } }"
                class="text-base font-weight-medium text-link text-truncate"
                style="max-inline-size: 200px;"
              >
                {{ item.code }}
              </RouterLink>
              <span class="text-caption text-medium-emphasis d-flex align-center gap-1">
                <VChip
                  v-if="item.is_stockable === false"
                  size="x-small"
                  label
                  color="warning"
                  variant="flat"
                >
                  {{ $t('catalog.items.list.table.non_stockable') }}
                </VChip>
              </span>
            </div>
          </div>
        </template>

        <!-- Name column -->
        <template #item.name="{ item }">
          <span
            class="text-body-1 font-weight-medium text-truncate d-inline-block"
            style="max-inline-size: 240px;"
            :title="localizedValue(item.name, '—')"
          >
            {{ localizedValue(item.name, '—') }}
          </span>
        </template>

        <!-- Type column -->
        <template #item.item_type="{ item }">
          <VChip
            :color="resolveTypeVariant(item.item_type)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getTypeTitle(item.item_type) }}
          </VChip>
        </template>

        <!-- Subtype column -->
        <template #item.sub_type="{ item }">
          <span
            v-if="item.sub_type"
            class="text-body-2 text-capitalize"
          >
            {{ getSubtypeTitle(item.sub_type) }}
          </span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Category column -->
        <template #item.category="{ item }">
          <span
            v-if="item.category"
            class="text-body-2"
          >
            {{ localizedValue(item.category.name, '—') }}
          </span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Price column -->
        <template #item.price_range="{ item }">
          <span
            v-if="item.price_range != null"
            class="font-weight-medium"
          >
            {{ item.price_range }}
          </span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Stock column -->
        <template #item.current_stock="{ item }">
          <div
            v-if="item.current_stock !== null && item.current_stock !== undefined"
            class="d-flex align-center gap-2"
          >
            <div
              class="stock-indicator-dot"
              :class="`bg-${getStockColor(item.current_stock)}`"
            />
            <span class="font-weight-medium">{{ item.current_stock }}</span>
          </div>
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
            @edit="router.push({ name: 'catalog-items-edit-id', params: { id: item.id } })"
            @export-pdf="actions.exportSinglePdf(item)"
            @send-email="dialogs.openSingleEmail(item)"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="catalog-item-mobile-card"
            :title="localizedValue(item.name, item.code)"
            :subtitle="item.code"
            :status="getStatusTitle(item.status)"
            :status-color="resolveStatusVariant(item.status)"
            show-select
            :selected="selected"
            clickable
            @update:selected="updateSelected"
            @click="dialogs.openView(item.id)"
          >
            <template #avatar>
              <VAvatar
                size="42"
                variant="tonal"
                :color="resolveTypeVariant(item.item_type)"
                rounded
              >
                <VIcon
                  size="22"
                  :icon="item.item_type === 'product' ? 'tabler-box' : item.item_type === 'consumable' ? 'tabler-flame' : 'tabler-tools'"
                />
              </VAvatar>
            </template>

            <template #badges>
              <VChip
                :color="resolveTypeVariant(item.item_type)"
                size="x-small"
                label
                variant="tonal"
                class="text-capitalize"
              >
                {{ getTypeTitle(item.item_type) }}
              </VChip>
              <VChip
                v-if="item.is_stockable === false"
                size="x-small"
                color="warning"
                variant="tonal"
                label
              >
                {{ $t('catalog.items.non_stockable', 'Non-Stockable') }}
              </VChip>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="dialogs.openView(item.id)"
                @edit="router.push({ name: 'catalog-items-edit-id', params: { id: item.id } })"
                @export-pdf="actions.exportSinglePdf(item)"
                @send-email="dialogs.openSingleEmail(item)"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #details>
              <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                <div
                  v-if="item.price_range"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-currency-euro"
                    color="success"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.price', 'Price') }}</span>
                  <span class="wp-mobile-row__value font-weight-medium">{{ item.price_range }}</span>
                </div>
                <div
                  v-if="item.category"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-folder"
                    color="primary"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.category', 'Category') }}</span>
                  <span class="wp-mobile-row__value text-truncate">{{ localizedValue(item.category.name, '—') }}</span>
                </div>
              </div>
            </template>

            <template
              v-if="item.current_stock !== null && item.current_stock !== undefined"
              #footer
            >
              <span class="text-caption text-medium-emphasis">
                {{ $t('catalog.items.stock', 'Current Stock') }}
              </span>
              <span class="text-caption d-flex align-center gap-1 font-weight-medium">
                <span
                  class="stock-indicator-dot"
                  :class="`bg-${getStockColor(item.current_stock)}`"
                />
                {{ item.current_stock }}
              </span>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Filter Dialog -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="activeFilterCount"
      :title="$t('catalog.items.list.filter.title')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.item_type"
            :label="$t('catalog.items.list.filter.item_type')"
            :items="typeOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.category_id"
            :label="$t('catalog.items.list.filter.category')"
            :items="categoryItems"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('catalog.items.list.filter.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Catalog Item Dialog (detail modal) -->
    <ViewCatalogItemDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :item="dialogs.viewItem.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @open-email="dialogs.openSingleEmail"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Email Dialog — page level (always on top of dialog stack) -->
    <WpEmailDialog
      v-model="dialogs.emailDialog.value.isOpen"
      :title="dialogs.emailDialog.value.isSingle
        ? `${$t('catalog.items.list.actions.send_single_mail')} — ${dialogs.emailDialog.value.itemCode}`
        : $t('catalog.items.list.actions.send_mail')"
      :recipients="dialogs.emailDialog.value.recipients"
      :loading="actions.isSendingEmail.value"
      @send="handleSendEmail"
      @close="dialogs.closeEmail()"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('catalog.items.list.delete_dialog.title')"
      :description="$t('catalog.items.list.delete_dialog.description')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteItem"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <!-- 👉 Mobile Quick Actions Speed Dial -->
    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none catalog-items-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.catalog-items-quick-actions {
  inset-inline-end: 25px;
}

.stock-indicator-dot {
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}

.catalog-item-mobile-card {
  min-inline-size: 0;

  &__details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-inline-size: 0;
  }
}
</style>
