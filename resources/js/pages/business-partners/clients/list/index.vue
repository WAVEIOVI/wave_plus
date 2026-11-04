<script setup>
import {
  AppFabSpeedDial,
  WpActiveFilters,
  WpBulkActions,
  WpDeleteDialog,
  WpExportDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useClientActions } from '@/composables/business-partners/clients/useClientActions'
import { useClientDialogs } from '@/composables/business-partners/clients/useClientDialogs'
import { useClientList } from '@/composables/business-partners/clients/useClientList'
import { localizedValue } from '@/composables/useLocaleField'
import AddNewClientDialog from '@/views/business-partners/clients/list/AddNewClientDialog.vue'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import BpBulkEmailDialog from '@/views/business-partners/shared-components/dialogs/BpBulkEmailDialog.vue'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { avatarText } from '@core/utils/formatters'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/business-partners/clients/list',
  name: 'business-partners-clients-list',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'clients',
  },
})

const { t, te } = useI18n()
const router = useRouter()
const ability = useAbility()
const isMobileFabOpen = ref(false)

// ── Client List Orchestration ───────────────────────────────────────────────
// useClientList owns: fetching, sorting, filtering, pagination, KPIs, selection.
// The page mounts → list is fetched immediately (no user interaction required).
// KPIs are fetched independently in parallel.
const {
  clients,
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

  // Options
  headers,
  statusOptions,
  categoryOptions,
  industryOptions,
  getStatusTitle,
  getCategoryTitle,
  getIndustryTitle,
  resolveStatusVariant,
  resolveCategoryVariant,

  // Selection
  selectedClients,
  clearSelection,

  // KPIs
  statistics,
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useClientList()

// ── Selection Mode State & Handlers ─────────────────────────────────────────
const isSelectionMode = ref(false)

const toggleSelectionMode = () => {
  isSelectionMode.value = !isSelectionMode.value
  if (!isSelectionMode.value) {
    clearSelection()
  }
}

const isAllOnPageSelected = computed(() => {
  return clients.value.length > 0 && clients.value.every(c => selectedClients.value.includes(c.id))
})

const selectAllOnPage = () => {
  const pageIds = clients.value.map(c => c.id)

  selectedClients.value = [...new Set([...selectedClients.value, ...pageIds])]
}

const deselectPage = () => {
  const pageIdSet = new Set(clients.value.map(c => c.id))

  selectedClients.value = selectedClients.value.filter(id => !pageIdSet.has(id))
}

const toggleSelectPage = () => {
  if (isAllOnPageSelected.value)
    deselectPage()
  else
    selectAllOnPage()
}

const deselectAll = () => {
  clearSelection()
}

// ── Dialog State ────────────────────────────────────────────────────────────
// useClientDialogs owns: view/add/delete/email dialog open state + async loading.
const dialogs = useClientDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
// useClientActions owns: create/delete/email/export with snackbar notifications.
const actions = useClientActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteClient = () => actions.deleteClient(dialogs.deleteDialog.item.value)

// ── Export Dialog State & Handlers ──────────────────────────────────────────
const isExportDialogOpen = ref(false)

const openExportDialog = () => {
  if (selectedClients.value.length === 0)
    return

  actions.exportError.value = ''
  isExportDialogOpen.value = true
}

const handleExportClients = async ({ format }) => {
  const success = await actions.exportSelected({
    ids: selectedClients.value,
    format,
  })

  if (success) {
    isExportDialogOpen.value = false
    clearSelection()
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

// ── Bulk Actions Configuration ───────────────────────────────────────────────
const bulkActionButtons = computed(() => {
  const actionsList = []

  if (ability.can('export', 'clients')) {
    actionsList.push({
      label: te('clients.list.actions.export') ? t('clients.list.actions.export') : t('Export'),
      icon: 'tabler-file-export',
      emit: 'export',
      color: 'secondary',
      loading: actions.isExportingList.value,
      disabled: selectedClients.value.length === 0 || actions.isExportingList.value,
    })
  }

  if (ability.can('create', 'email-messages')) {
    actionsList.push({
      label: t('clients.list.actions.send_mail'),
      icon: 'tabler-mail',
      emit: 'send-email',
      color: 'primary',
      disabled: selectedClients.value.length === 0,
    })
  }

  return actionsList
})

// ── Row Actions ──────────────────────────────────────────────────────────────
const getRowActions = () => {
  const rowActions = [
    { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (ability.can('update', 'clients')) {
    rowActions.push({ label: t('Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('delete', 'clients')) {
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

const openSelectedClientsEmail = () => {
  if (selectedClients.value.length === 0)
    return

  isBulkEmailDialogOpen.value = true
}

const mobileFabActions = computed(() => [
  {
    id: 'add-client',
    icon: 'tabler-user-plus',
    title: t('clients.list.actions.add_new'),
    disabled: !ability.can('create', 'clients'),
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
    id: 'export',
    icon: 'tabler-file-export',
    title: te('clients.list.actions.export') ? t('clients.list.actions.export') : t('Export'),
    disabled: selectedClients.value.length === 0 || actions.isExportingList.value || !ability.can('export', 'clients'),
    onClick: openExportDialog,
  },
  {
    id: 'send-email',
    icon: 'tabler-mail',
    title: t('clients.list.actions.send_mail'),
    disabled: selectedClients.value.length === 0 || !ability.can('create', 'email-messages'),
    onClick: openSelectedClientsEmail,
  },
  {
    id: 'filter-clients',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="business-partners-clients-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('clients.list.title')"
      :subtitle="$t('clients.list.subtitle')"
      icon="tabler-users"
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
            :active="kpi.filterValue !== null ? filters.status === kpi.filterValue : !filters.status"
            @click="handleKpiClick(kpi.filterValue)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card Container -->
    <VCard>
      <!-- 👉 Page Toolbar -->
      <WpPageToolbar
        v-model:search="search"
        :create-label="$can('create', 'clients') ? $t('clients.list.actions.add_new') : ''"
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
              status: $t('clients.list.filter.status'),
              client_category: $t('clients.list.filter.category'),
              industry: $t('clients.list.filter.industry'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              client_category: val => getCategoryTitle(val),
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
              :color="isAllOnPageSelected ? 'secondary' : 'primary'"
              :prepend-icon="isAllOnPageSelected ? 'tabler-square-minus' : 'tabler-list-check'"
              :disabled="clients.length === 0"
              @click="toggleSelectPage"
            >
              {{ isAllOnPageSelected ? $t('Deselect Page') : $t('Select Page') }} ({{ clients.length }})
            </VBtn>
            <VBtn
              v-if="selectedClients.length > 0"
              size="x-small"
              variant="text"
              color="error"
              prepend-icon="tabler-x"
              @click="deselectAll"
            >
              {{ $t('Deselect') }} ({{ selectedClients.length }})
            </VBtn>
          </div>

          <div class="d-flex align-center gap-2">
            <span
              v-if="selectedClients.length > 0"
              class="text-caption font-weight-bold text-primary"
            >
              {{ selectedClients.length }} {{ $t('Selected') }}
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
      <div class="px-4">
        <WpBulkActions
          :count="selectedClients.length"
          :actions="bulkActionButtons"
          @export="openExportDialog"
          @send-email="openSelectedClientsEmail"
          @clear="clearSelection"
        />
      </div>

      <!-- 👉 Server-side Responsive List -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedClients"
        :headers="headers"
        :items="clients"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        :show-select="isSelectionMode"
        item-value="id"
        @retry="refresh"
      >
        <!-- ── Desktop Table Custom Slots ── -->
        <!-- Client column: Avatar + Legal / Commercial names -->
        <template #item.client="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveCategoryVariant(item.client_category)"
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
                :to="{ name: 'business-partners-clients-edit-id', params: { id: item.id } }"
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
                  {{ item.client_id }}
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
              >{{ $t('clients.list.empty.email') }}</span>
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
              >{{ $t('clients.list.empty.phone') }}</span>
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
              >{{ $t('clients.list.empty.location') }}</span>
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
        <template #item.client_category="{ item }">
          <VChip
            v-if="item.client_category"
            :color="resolveCategoryVariant(item.client_category)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getCategoryTitle(item.client_category) }}
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
            @edit="router.push({ name: 'business-partners-clients-edit-id', params: { id: item.id } })"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="client-mobile-card"
            :title="localizedValue(item.commercial_name, localizedValue(item.legal_name))"
            :subtitle="localizedValue(item.legal_name)"
            :status="item.status"
            :status-label="getStatusTitle(item.status)"
            :status-color="resolveStatusVariant(item.status)"
            clickable
            :show-select="isSelectionMode"
            :selected="selected"
            @update:selected="updateSelected"
            @click="isSelectionMode ? updateSelected(!selected) : dialogs.openView(item.id)"
          >
            <template #avatar>
              <VAvatar
                size="40"
                variant="tonal"
                :color="resolveCategoryVariant(item.client_category)"
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
                @edit="router.push({ name: 'business-partners-clients-edit-id', params: { id: item.id } })"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #badges>
              <div
                class="d-flex flex-wrap gap-1 align-center min-w-0"
                style="display: contents;"
              >
                <VChip
                  v-if="item.client_id"
                  size="x-small"
                  label
                  color="secondary"
                  variant="tonal"
                  class="font-weight-medium flex-shrink-0"
                >
                  {{ item.client_id }}
                </VChip>
                <VChip
                  v-if="item.client_category"
                  size="x-small"
                  label
                  :color="resolveCategoryVariant(item.client_category)"
                  variant="tonal"
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getCategoryTitle(item.client_category) }}
                </VChip>
                <VChip
                  v-if="item.industry"
                  size="x-small"
                  label
                  variant="tonal"
                  class="text-capitalize text-truncate"
                  style="max-inline-size: 140px;"
                >
                  {{ getIndustryTitle(item.industry) }}
                </VChip>
              </div>
            </template>

            <template #details>
              <div class="d-flex flex-column gap-1.5 min-w-0">
                <div
                  v-if="item.primary_email"
                  class="wp-mobile-row min-w-0"
                >
                  <VIcon
                    size="14"
                    icon="tabler-mail"
                    color="primary"
                    class="wp-mobile-row__icon flex-shrink-0"
                  />
                  <span class="wp-mobile-row__value text-truncate">{{ item.primary_email }}</span>
                </div>
                <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1 min-w-0">
                  <div
                    v-if="item.primary_phone"
                    class="wp-mobile-row min-w-0"
                  >
                    <VIcon
                      size="14"
                      icon="tabler-phone"
                      color="success"
                      class="wp-mobile-row__icon flex-shrink-0"
                    />
                    <span class="wp-mobile-row__value text-truncate">{{ item.primary_phone }}</span>
                  </div>
                  <div
                    v-if="localizedValue(item.hq_city) || localizedValue(item.hq_address)"
                    class="wp-mobile-row min-w-0"
                  >
                    <VIcon
                      size="14"
                      icon="tabler-map-pin"
                      color="error"
                      class="wp-mobile-row__icon flex-shrink-0"
                    />
                    <span class="wp-mobile-row__value text-truncate">
                      {{ [localizedValue(item.hq_address), localizedValue(item.hq_city)].filter(Boolean).join(', ') }}{{ item.hq_postcode ? ` ${item.hq_postcode}` : '' }}
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
      :title="$t('clients.list.filter.title', 'Filter Clients')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.client_category"
            :label="$t('clients.list.filter.category')"
            :items="categoryOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.industry"
            :label="$t('clients.list.filter.industry')"
            :items="industryOptions"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('clients.list.filter.status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Client Dialog (mirror of edit page in read-only mode) -->
    <ViewClientDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :client="dialogs.viewClient.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Contextual Bulk Email Dialog -->
    <BpBulkEmailDialog
      v-model="isBulkEmailDialogOpen"
      partner-type="client"
      :partner-ids="selectedClients"
      @completed="clearSelection(); refresh()"
    />

    <!-- 👉 Add New Client Dialog -->
    <AddNewClientDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      :loading="actions.isCreating.value"
      @add-client="actions.createClient"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('clients.list.delete_dialog.title', 'Are you sure you want to delete this client?')"
      :description="$t('clients.list.delete_dialog.description', 'This action cannot be undone.')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteClient"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <!-- 👉 Export Selected Clients Dialog -->
    <WpExportDialog
      v-model="isExportDialogOpen"
      :entity-name="$t('clients.list.title')"
      :selected-count="selectedClients.length"
      :formats="['pdf', 'csv']"
      :loading="actions.isExportingList.value"
      :error="actions.exportError.value"
      @export="handleExportClients"
    />
    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none clients-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.clients-list-quick-actions {
  inset-inline-end: 25px;
}

.client-mobile-card {
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .business-partners-clients-list {
    padding-block-end: 7rem;
  }
}
</style>
