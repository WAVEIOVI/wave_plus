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
import { useOrganisationActions } from '@/composables/business-partners/organisations/useOrganisationActions'
import { useOrganisationDialogs } from '@/composables/business-partners/organisations/useOrganisationDialogs'
import { useOrganisationList } from '@/composables/business-partners/organisations/useOrganisationList'
import { localizedValue } from '@/composables/useLocaleField'
import AddNewOrganisationDialog from '@/views/business-partners/organisations/list/AddNewOrganisationDialog.vue'
import ViewOrganisationDialog from '@/views/business-partners/organisations/list/ViewOrganisationDialog.vue'
import BpBulkEmailDialog from '@/views/business-partners/shared-components/dialogs/BpBulkEmailDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/business-partners/organisations/list',
  name: 'business-partners-organisations-list',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'organisations',
  },
})

const { t } = useI18n()
const router = useRouter()
const ability = useAbility()
const isMobileFabOpen = ref(false)

// ── Organisation List Orchestration ──────────────────────────────────────────
const {
  organisations,
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
  typeOptions,
  scopeOptions,
  getStatusTitle,
  getTypeTitle,
  getScopeTitle,
  resolveStatusVariant,
  resolveTypeVariant,
  selectedOrganisations,
  clearSelection,
  statistics,
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useOrganisationList()

// ── Selection Mode State & Handlers ─────────────────────────────────────────
const isSelectionMode = ref(false)

const toggleSelectionMode = () => {
  isSelectionMode.value = !isSelectionMode.value
  if (!isSelectionMode.value) {
    clearSelection()
  }
}

const selectAllOnPage = () => {
  const pageIds = organisations.value.map(s => s.id)

  selectedOrganisations.value = [...new Set([...selectedOrganisations.value, ...pageIds])]
}

const selectAllOrganisations = () => {
  selectedOrganisations.value = organisations.value.map(s => s.id)
}

const deselectAll = () => {
  clearSelection()
}

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useOrganisationDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useOrganisationActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteOrganisation = () => actions.deleteOrganisation(dialogs.deleteDialog.item.value)

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

  if (ability.can('export', 'organisations')) {
    actionsList.push({
      label: t('organisations.list.actions.export_pdf', 'Export PDF'),
      icon: 'tabler-file-download',
      emit: 'export-pdf',
      color: 'secondary',
      loading: actions.isExportingListPdf.value,
    })
  }

  if (ability.can('create', 'email-messages')) {
    actionsList.push({
      label: t('organisations.list.actions.send_mail', 'Send Email'),
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

  if (ability.can('update', 'organisations')) {
    rowActions.push({ label: t('Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  if (ability.can('delete', 'organisations')) {
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
  if (selectedOrganisations.value.length === 0)
    return

  isBulkEmailDialogOpen.value = true
}

const mobileFabActions = computed(() => [
  {
    id: 'add-organisation',
    icon: 'tabler-building-community',
    title: t('organisations.list.actions.add_new', 'Add Organisation'),
    disabled: !ability.can('create', 'organisations'),
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
    title: t('organisations.list.actions.export_pdf', 'Export PDF'),
    disabled: selectedOrganisations.value.length === 0 || actions.isExportingListPdf.value || !ability.can('export', 'organisations'),
    onClick: () => actions.exportListPdf(),
  },
  {
    id: 'send-email',
    icon: 'tabler-mail',
    title: t('organisations.list.actions.send_mail', 'Send Email'),
    disabled: selectedOrganisations.value.length === 0 || !ability.can('create', 'email-messages'),
    onClick: openBulkEmail,
  },
  {
    id: 'filter-organisations',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="business-partners-organisations-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('organisations.list.title', 'Organisations')"
      :subtitle="$t('organisations.list.subtitle', 'Manage government, institutional, and partner organisations')"
      icon="tabler-building-community"
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
        :create-label="$can('create', 'organisations') ? $t('organisations.list.actions.add_new', 'Add Organisation') : ''"
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
              status: $t('organisations.filters.status', 'Status'),
              organisation_type: $t('organisations.filters.type', 'Type'),
              scope: $t('organisations.filters.scope', 'Scope'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              organisation_type: val => getTypeTitle(val),
              scope: val => getScopeTitle(val),
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
              {{ $t('Select Page') }} ({{ organisations.length }})
            </VBtn>
            <VBtn
              size="x-small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-checks"
              @click="selectAllOrganisations"
            >
              {{ $t('Select All') }} ({{ totalItems }})
            </VBtn>
            <VBtn
              v-if="selectedOrganisations.length > 0"
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
              v-if="selectedOrganisations.length > 0"
              class="text-caption font-weight-bold text-primary"
            >
              {{ selectedOrganisations.length }} {{ $t('Selected') }}
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
          :count="selectedOrganisations.length"
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
        v-model:selected="selectedOrganisations"
        :headers="headers"
        :items="organisations"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        :show-select="isSelectionMode"
        item-value="id"
        @retry="refresh"
      >
        <!-- ── Desktop Table Custom Slots ── -->

        <!-- Organisation column: Avatar + Legal / Commercial names -->
        <template #item.organisation="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveTypeVariant(item.organisation_type)"
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
                :to="{ name: 'business-partners-organisations-edit-id', params: { id: item.id } }"
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
                  {{ item.organisation_id }}
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

        <!-- Organisation Type column -->
        <template #item.organisation_type="{ item }">
          <VChip
            v-if="item.organisation_type"
            :color="resolveTypeVariant(item.organisation_type)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getTypeTitle(item.organisation_type) }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Member Count column -->
        <template #item.member_count="{ item }">
          <span
            v-if="item.member_count !== null && item.member_count !== undefined"
            class="text-body-2 font-weight-medium"
          >
            {{ Number(item.member_count).toLocaleString() }}
          </span>
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
            @edit="router.push({ name: 'business-partners-organisations-edit-id', params: { id: item.id } })"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="organisation-mobile-card"
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
                :color="resolveTypeVariant(item.organisation_type)"
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
                @edit="router.push({ name: 'business-partners-organisations-edit-id', params: { id: item.id } })"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #badges>
              <div
                class="d-flex flex-wrap gap-1 align-center min-w-0"
                style="display: contents;"
              >
                <VChip
                  v-if="item.organisation_id"
                  size="x-small"
                  label
                  color="secondary"
                  variant="tonal"
                  class="font-weight-medium flex-shrink-0"
                >
                  {{ item.organisation_id }}
                </VChip>
                <VChip
                  v-if="item.organisation_type"
                  :color="resolveTypeVariant(item.organisation_type)"
                  size="x-small"
                  label
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getTypeTitle(item.organisation_type) }}
                </VChip>
                <VChip
                  v-if="item.scope"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  label
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getScopeTitle(item.scope) }}
                </VChip>
              </div>
            </template>

            <template #details>
              <div
                v-if="item.primary_email"
                class="wp-mobile-row"
              >
                <VIcon
                  size="16"
                  icon="tabler-mail"
                  color="primary"
                  class="wp-mobile-row__icon shrink-0"
                />
                <span class="wp-mobile-row__label">{{ $t('wp.labels.email', 'Email') }}</span>
                <span class="wp-mobile-row__value text-truncate">{{ item.primary_email }}</span>
              </div>
              <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                <div
                  v-if="item.primary_phone"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-phone"
                    color="success"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.phone', 'Phone') }}</span>
                  <span class="wp-mobile-row__value">{{ item.primary_phone }}</span>
                </div>
                <div
                  v-if="localizedValue(item.hq_city) || localizedValue(item.hq_address)"
                  class="wp-mobile-row"
                >
                  <VIcon
                    size="16"
                    icon="tabler-map-pin"
                    color="error"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.location', 'Location') }}</span>
                  <span class="wp-mobile-row__value text-truncate">
                    {{ localizedValue(item.hq_city) }}{{ item.hq_postcode ? `, ${item.hq_postcode}` : '' }}
                  </span>
                </div>
              </div>
            </template>

            <template
              v-if="item.member_count !== null && item.member_count !== undefined"
              #footer
            >
              <span class="text-caption text-medium-emphasis">
                {{ $t('organisations.list.headers.member_count', 'Members') }}
              </span>
              <span class="text-caption font-weight-medium">
                {{ Number(item.member_count).toLocaleString() }}
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
      :title="$t('organisations.list.filter.title', 'Filter Organisations')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.organisation_type"
            :label="$t('organisations.filters.type', 'Organisation Type')"
            :items="typeOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.scope"
            :label="$t('organisations.filters.scope', 'Scope')"
            :items="scopeOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('organisations.filters.status', 'Status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Organisation Dialog (mirror of edit page in read-only mode) -->
    <ViewOrganisationDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :organisation-id="dialogs.viewDialog.item.value"
      :organisation="dialogs.viewOrganisation.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Contextual Bulk Email Dialog -->
    <BpBulkEmailDialog
      v-model="isBulkEmailDialogOpen"
      partner-type="organisation"
      :partner-ids="selectedOrganisations"
      @completed="clearSelection(); refresh()"
    />

    <!-- 👉 Add New Organisation Dialog -->
    <AddNewOrganisationDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      :loading="actions.isCreating.value"
      @add-organisation="actions.createOrganisation"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('organisations.list.delete_dialog.title', 'Are you sure you want to delete this organisation?')"
      :description="$t('organisations.list.delete_dialog.description', 'This action cannot be undone.')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteOrganisation"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none organisations-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.organisations-list-quick-actions {
  inset-inline-end: 25px;
}

.organisation-mobile-card {
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .business-partners-organisations-list {
    padding-block-end: 7rem;
  }
}
</style>
