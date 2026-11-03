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
import { useServiceProviderActions } from '@/composables/business-partners/service-providers/useServiceProviderActions'
import { useServiceProviderDialogs } from '@/composables/business-partners/service-providers/useServiceProviderDialogs'
import { useServiceProviderList } from '@/composables/business-partners/service-providers/useServiceProviderList'
import { localizedValue } from '@/composables/useLocaleField'
import AddNewServiceProviderDialog from '@/views/business-partners/service-providers/list/AddNewServiceProviderDialog.vue'
import ViewServiceProviderDialog from '@/views/business-partners/service-providers/list/ViewServiceProviderDialog.vue'
import BpBulkEmailDialog from '@/views/business-partners/shared-components/dialogs/BpBulkEmailDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/business-partners/service-providers/list',
  name: 'business-partners-service-providers-list',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'BusinessPartners',
  },
})

const { t } = useI18n()
const router = useRouter()
const ability = useAbility()
const isMobileFabOpen = ref(false)

// ── Service Provider List Orchestration ──────────────────────────────────────
const {
  serviceProviders,
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
  tierOptions,
  availabilityOptions,
  industryOptions,
  getStatusTitle,
  getTierTitle,
  getAvailabilityTitle,
  getIndustryTitle,
  resolveStatusVariant,
  resolveTierVariant,
  resolveAvailabilityVariant,
  selectedServiceProviders,
  clearSelection,
  statistics,
  metaLoading,
  kpiCards,
  handleKpiClick,
  fetchStatistics,
} = useServiceProviderList()

// ── Selection Mode State & Handlers ─────────────────────────────────────────
const isSelectionMode = ref(false)

const toggleSelectionMode = () => {
  isSelectionMode.value = !isSelectionMode.value
  if (!isSelectionMode.value) {
    clearSelection()
  }
}

const selectAllOnPage = () => {
  const pageIds = serviceProviders.value.map(s => s.id)

  selectedServiceProviders.value = [...new Set([...selectedServiceProviders.value, ...pageIds])]
}

const selectAllServiceProviders = () => {
  selectedServiceProviders.value = serviceProviders.value.map(s => s.id)
}

const deselectAll = () => {
  clearSelection()
}

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useServiceProviderDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useServiceProviderActions({
  onRefresh: refresh,
  onMetaRefresh: fetchStatistics,
})

const confirmDeleteServiceProvider = () => actions.deleteServiceProvider(dialogs.deleteDialog.item.value)

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
    label: t('service_providers.list.actions.send_mail', 'Send Email'),
    icon: 'tabler-mail',
    emit: 'send-email',
    color: 'primary',
  },
])

// ── Row Actions ──────────────────────────────────────────────────────────────
const getRowActions = () => [
  { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
  {
    label: t('Delete'),
    icon: 'tabler-trash',
    emit: 'delete',
    color: 'error',
    dividerBefore: true,
  },
]

// ── Email send handler ───────────────────────────────────────────────────────
const handleSendEmail = async emailData => {
  const success = await actions.sendEmail({
    serviceProviderId: dialogs.emailDialog.value.serviceProviderId,
    emailData,
  })

  if (success) {
    dialogs.closeEmail()
  }
}

const isBulkEmailDialogOpen = ref(false)

const openBulkEmail = () => {
  if (selectedServiceProviders.value.length === 0)
    return

  isBulkEmailDialogOpen.value = true
}

const mobileFabActions = computed(() => [
  {
    id: 'add-service-provider',
    icon: 'tabler-briefcase-2',
    title: t('service_providers.list.actions.add_new', 'Add Service Provider'),
    disabled: !ability.can('create', 'BusinessPartners'),
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
    id: 'send-email',
    icon: 'tabler-mail',
    title: t('service_providers.list.actions.send_mail', 'Send Email'),
    disabled: selectedServiceProviders.value.length === 0,
    onClick: openBulkEmail,
  },
  {
    id: 'filter-service-providers',
    icon: 'tabler-filter',
    title: t('wp.filters.label'),
    onClick: openFilterDialog,
  },
])
</script>

<template>
  <div class="business-partners-service-providers-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('service_providers.list.title', 'Service Providers')"
      :subtitle="$t('service_providers.list.subtitle', 'Manage service provider accounts, specialties, and contractor relationships')"
      icon="tabler-briefcase-2"
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
        :create-label="$can('create', 'BusinessPartners') ? $t('service_providers.list.actions.add_new', 'Add Service Provider') : ''"
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
              status: $t('service_providers.filters.status', 'Status'),
              provider_tier: $t('service_providers.filters.tier', 'Provider Tier'),
              industry: $t('service_providers.filters.industry', 'Industry'),
            }"
            :value-formatters="{
              status: val => getStatusTitle(val),
              provider_tier: val => getTierTitle(val),
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
              {{ $t('Select Page') }} ({{ serviceProviders.length }})
            </VBtn>
            <VBtn
              size="x-small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-checks"
              @click="selectAllServiceProviders"
            >
              {{ $t('Select All') }} ({{ totalItems }})
            </VBtn>
            <VBtn
              v-if="selectedServiceProviders.length > 0"
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
              v-if="selectedServiceProviders.length > 0"
              class="text-caption font-weight-bold text-primary"
            >
              {{ selectedServiceProviders.length }} {{ $t('Selected') }}
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
          :count="selectedServiceProviders.length"
          :actions="bulkActionButtons"
          @send-email="openBulkEmail"
          @clear="clearSelection"
        />
      </div>

      <!-- 👉 Server-side Responsive List -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedServiceProviders"
        :headers="headers"
        :items="serviceProviders"
        :total-items="totalItems"
        :loading="loading"
        :error="error"
        :show-select="isSelectionMode"
        item-value="id"
        @retry="refresh"
      >
        <!-- ── Desktop Table Custom Slots ── -->

        <!-- Service Provider column: Avatar + Legal / Commercial names -->
        <template #item.service_provider="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="40"
              variant="tonal"
              :color="resolveTierVariant(item.provider_tier)"
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
                :to="{ name: 'business-partners-service-providers-edit-id', params: { id: item.id } }"
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
                  {{ item.provider_id || item.service_provider_id }}
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

        <!-- Provider Tier column -->
        <template #item.provider_tier="{ item }">
          <VChip
            v-if="item.provider_tier"
            :color="resolveTierVariant(item.provider_tier)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getTierTitle(item.provider_tier) }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Availability Status column -->
        <template #item.availability_status="{ item }">
          <VChip
            v-if="item.availability_status"
            :color="resolveAvailabilityVariant(item.availability_status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getAvailabilityTitle(item.availability_status) }}
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
            @edit="router.push({ name: 'business-partners-service-providers-edit-id', params: { id: item.id } })"
            @delete="dialogs.openDelete(item.id)"
          />
        </template>

        <!-- ── Mobile Card Template ── -->
        <template #mobile-card="{ item, selected, updateSelected }">
          <WpMobileCard
            class="service-provider-mobile-card"
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
                :color="resolveTierVariant(item.provider_tier)"
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
                @edit="router.push({ name: 'business-partners-service-providers-edit-id', params: { id: item.id } })"
                @delete="dialogs.openDelete(item.id)"
              />
            </template>

            <template #badges>
              <div
                class="d-flex flex-wrap gap-1 align-center min-w-0"
                style="display: contents;"
              >
                <VChip
                  v-if="item.provider_id || item.service_provider_id"
                  size="x-small"
                  label
                  color="secondary"
                  variant="tonal"
                  class="font-weight-medium flex-shrink-0"
                >
                  {{ item.provider_id || item.service_provider_id }}
                </VChip>
                <VChip
                  v-if="item.provider_tier"
                  size="x-small"
                  label
                  :color="resolveTierVariant(item.provider_tier)"
                  variant="tonal"
                  class="text-capitalize flex-shrink-0"
                >
                  {{ getTierTitle(item.provider_tier) }}
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
      :title="$t('service_providers.list.filter.title', 'Filter Service Providers')"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.provider_tier"
            :label="$t('service_providers.filters.tier', 'Provider Tier')"
            :items="tierOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.industry"
            :label="$t('service_providers.filters.industry', 'Industry')"
            :items="industryOptions"
            variant="outlined"
            clearable
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="stagedFilters.status"
            :label="$t('service_providers.filters.status', 'Status')"
            :items="statusOptions"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Service Provider Dialog (mirror of edit page in read-only mode) -->
    <ViewServiceProviderDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :service-provider-id="dialogs.viewDialog.item.value"
      :service-provider="dialogs.viewServiceProvider.value"
      :loading="dialogs.viewLoading.value"
      :error="dialogs.viewError.value"
      @open-email="dialogs.openSingleEmail"
      @retry="dialogs.openView(dialogs.viewDialog.item.value)"
    />

    <!-- 👉 Email Dialog -->
    <WpEmailDialog
      v-model="dialogs.emailDialog.value.isOpen"
      :title="dialogs.emailDialog.value.isSingle
        ? `${$t('service_providers.list.actions.send_mail', 'Send Email')} — ${localizedValue(dialogs.viewServiceProvider.value?.legal_name) || ''}`
        : $t('service_providers.list.actions.send_mail', 'Send Email')"
      :recipients="dialogs.emailDialog.value.recipients"
      :loading="actions.isSendingEmail.value"
      @send="handleSendEmail"
      @close="dialogs.closeEmail()"
    />

    <!-- 👉 Contextual Bulk Email Dialog -->
    <BpBulkEmailDialog
      v-model="isBulkEmailDialogOpen"
      partner-type="service_provider"
      :partner-ids="selectedServiceProviders"
      @completed="clearSelection(); refresh()"
    />

    <!-- 👉 Add New Service Provider Dialog -->
    <AddNewServiceProviderDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      :loading="actions.isCreating.value"
      @add-service-provider="actions.createServiceProvider"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model="dialogs.deleteDialog.isOpen.value"
      :title="$t('service_providers.list.delete_dialog.title', 'Are you sure you want to delete this service provider?')"
      :description="$t('service_providers.list.delete_dialog.description', 'This action cannot be undone.')"
      :loading="actions.isDeleting.value"
      :confirm-action="confirmDeleteServiceProvider"
      @update:model-value="value => !value && dialogs.deleteDialog.close()"
    />

    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none service-providers-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.service-providers-list-quick-actions {
  inset-inline-end: 25px;
}

.service-provider-mobile-card {
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .business-partners-service-providers-list {
    padding-block-end: 7rem;
  }
}
</style>
