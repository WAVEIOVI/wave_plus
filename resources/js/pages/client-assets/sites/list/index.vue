<script setup>
/* eslint-disable camelcase */
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
import { useSiteActions } from '@/composables/client-assets/sites/useSiteActions'
import { useSiteDialogs } from '@/composables/client-assets/sites/useSiteDialogs'
import { useSiteList } from '@/composables/client-assets/sites/useSiteList'
import AddNewSiteDialog from '@/views/client-assets/sites/list/AddNewSiteDialog.vue'
import ViewSiteDialog from '@/views/client-assets/sites/list/ViewSiteDialog.vue'
import AreaManageDialog from '@/views/client-assets/sites/edit/AreaManageDialog.vue'
import SiteAnalyticsDialog from '@/views/client-assets/sites/list/SiteAnalyticsDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/client-assets/sites/list',
  name: 'client-assets-sites-list',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})

const { t } = useI18n()
const router = useRouter()
const { smAndDown } = useDisplay()
const isMobileFabOpen = ref(false)

// ── Site List Orchestration ────────────────────────────────────────────────
const {
  sites,
  totalSites,
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
  siteTypeOptions,
  statusOptions,
  riskOptions,
  clientsList,
  resolveStatusVariant,
  resolveRiskVariant,
  resolveTypeVariant,
  selectedSites,
  clearSelection,
  kpiCards,
} = useSiteList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useSiteDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useSiteActions({
  onRefresh: refresh,
})

const handleRowDelete = item => {
  dialogs.openDelete(item)
}

const handleConfirmDelete = async () => {
  if (dialogs.activeSite.value?.id) {
    await actions.deleteSite(dialogs.activeSite.value.id)
    dialogs.closeDelete()
  }
}

const handleBulkDelete = async () => {
  for (const id of selectedSites.value) {
    await actions.deleteSite(id)
  }
  clearSelection()
}

const handleKpiClick = kpi => {
  if (kpi.id === 'active') {
    setFilter('status', filters.status === 'active' ? null : 'active')
  } else if (kpi.id === 'high_risk') {
    setFilter('risk_class', filters.risk_class === 'high' ? null : 'high')
  } else if (kpi.id === 'construction') {
    setFilter('status', filters.status === 'under_construction' ? null : 'under_construction')
  } else {
    resetFilters()
  }
}

const isKpiActive = kpi => {
  if (kpi.id === 'active') return filters.status === 'active'
  if (kpi.id === 'high_risk') return filters.risk_class === 'high'
  if (kpi.id === 'construction') return filters.status === 'under_construction'
  if (kpi.id === 'total') return !filters.status && !filters.risk_class

  return false
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Site Workspace'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Manage Areas'), icon: 'tabler-sitemap', emit: 'manage-areas' },
  { label: t('Site Analytics'), icon: 'tabler-chart-pie', emit: 'analytics' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Mobile FAB Actions ──────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'new-site',
    icon: 'tabler-plus',
    title: t('New Site'),
    onClick: () => dialogs.openAdd(),
  },
  {
    id: 'filter-sites',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: () => { dialogs.isFilterDialogOpen.value = true },
  },
])
</script>

<template>
  <div class="sites-list-page d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('Client Sites')"
      :subtitle="$t('Manage client premises, industrial facilities, hierarchical zones, and asset locations.')"
      icon="tabler-building-estate"
    />

    <!-- 👉 KPI Cards -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.id"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="loading"
            clickable
            :active="isKpiActive(kpi)"
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
        :search-placeholder="$t('Search by name, code, address, city...')"
        :create-label="$t('New Site')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="dialogs.openAdd"
        @open-filters="dialogs.isFilterDialogOpen.value = true"
        @refresh="refresh"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilters.length"
            :filters="activeFilters"
            @remove="removeFilter"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <!-- 👉 Bulk Actions Bar -->
      <div
        v-if="selectedSites.length"
        class="px-4 pb-2"
      >
        <WpBulkActions
          :selected-count="selectedSites.length"
          @clear="clearSelection"
          @delete="handleBulkDelete"
        />
      </div>

      <!-- 👉 Responsive List (Desktop Table + Mobile Cards) -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedSites"
        :headers="headers"
        :items="sites"
        :total-items="totalSites"
        :loading="loading"
        :error="error"
        show-select
        item-value="id"
        @retry="refresh"
      >
        <!-- Site Name / Ref column -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="36"
            >
              <VIcon
                icon="tabler-building-estate"
                size="20"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <RouterLink
                :to="{ name: 'client-assets-sites-edit-id', params: { id: item.id } }"
                class="text-body-1 font-weight-medium text-high-emphasis text-link"
              >
                {{ item.name }}
              </RouterLink>
              <span class="text-caption text-disabled">
                {{ item.site_code || (item.city ? `${item.city}, ${item.country || ''}` : $t('No Location Ref')) }}
              </span>
            </div>
          </div>
        </template>

        <!-- Client column -->
        <template #item.client="{ item }">
          <span class="font-weight-medium text-high-emphasis py-1 d-block">
            {{ item.client?.legal_name || item.client?.name || '—' }}
          </span>
        </template>

        <!-- Site Type column -->
        <template #item.site_type="{ item }">
          <VChip
            :color="resolveTypeVariant(item.site_type)"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ (item.site_type || 'other').replace(/_/g, ' ') }}
          </VChip>
        </template>

        <!-- Status column -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ (item.status || 'draft').replace(/_/g, ' ') }}
          </VChip>
        </template>

        <!-- Risk Class column -->
        <template #item.risk_class="{ item }">
          <VChip
            :color="resolveRiskVariant(item.risk_class)"
            size="small"
            variant="tonal"
            label
            class="text-capitalize"
          >
            {{ item.risk_class || 'low' }}
          </VChip>
        </template>

        <!-- Equipment Count column -->
        <template #item.equipment_count="{ item }">
          <div class="text-center">
            <VChip
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ item.equipment_count ?? item.equipment?.length ?? 0 }}
            </VChip>
          </div>
        </template>

        <!-- Row Actions column -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions()"
            @view="dialogs.openView(item)"
            @edit="router.push({ name: 'client-assets-sites-edit-id', params: { id: item.id } })"
            @manage-areas="dialogs.openAreaManage(item)"
            @analytics="dialogs.openAnalytics(item)"
            @delete="handleRowDelete(item)"
          />
        </template>

        <!-- Mobile Card Template -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.name"
            :subtitle="item.client?.legal_name || item.client?.name || ''"
            icon="tabler-building-estate"
            :status="item.status"
            :status-color="resolveStatusVariant(item.status)"
            clickable
            @click="dialogs.openView(item)"
          >
            <template #badges>
              <VChip
                :color="resolveRiskVariant(item.risk_class)"
                size="x-small"
                variant="tonal"
                label
                class="text-capitalize"
              >
                {{ item.risk_class || 'low' }}
              </VChip>
            </template>

            <template #details>
              <div
                v-if="item.site_type"
                class="wp-mobile-row"
              >
                <VIcon
                  icon="tabler-category"
                  size="16"
                  class="wp-mobile-row__icon shrink-0"
                />
                <span class="wp-mobile-row__label">{{ $t('wp.labels.type', 'Type') }}</span>
                <span class="wp-mobile-row__value text-capitalize">{{ (item.site_type || '').replace(/_/g, ' ') }}</span>
              </div>
              <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1">
                <div
                  v-if="item.city || item.address_line1"
                  class="wp-mobile-row"
                >
                  <VIcon
                    icon="tabler-map-pin"
                    size="16"
                    color="error"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('wp.labels.location', 'Location') }}</span>
                  <span class="wp-mobile-row__value text-truncate">
                    {{ [item.address_line1, item.city].filter(Boolean).join(', ') }}
                  </span>
                </div>
                <div class="wp-mobile-row">
                  <VIcon
                    icon="tabler-fire-extinguisher"
                    size="16"
                    color="primary"
                    class="wp-mobile-row__icon shrink-0"
                  />
                  <span class="wp-mobile-row__label">{{ $t('Equipment') }}</span>
                  <span class="wp-mobile-row__value font-weight-medium">
                    {{ item.equipment_count ?? item.equipment?.length ?? 0 }}
                  </span>
                </div>
              </div>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions()"
                @view="dialogs.openView(item)"
                @edit="router.push({ name: 'client-assets-sites-edit-id', params: { id: item.id } })"
                @manage-areas="dialogs.openAreaManage(item)"
                @analytics="dialogs.openAnalytics(item)"
                @delete="handleRowDelete(item)"
              />
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Filter Dialog -->
    <WpFilterDialog
      v-model:is-open="dialogs.isFilterDialogOpen.value"
      :title="$t('Filter Sites')"
      @apply="applyStaged"
      @reset="resetStaged"
    >
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.client_id"
            :label="$t('Client')"
            :items="clientsList"
            item-title="legal_name"
            item-value="id"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.site_type"
            :label="$t('Site Type')"
            :items="siteTypeOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.status"
            :label="$t('Status')"
            :items="statusOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.risk_class"
            :label="$t('Risk Class')"
            :items="riskOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Site Dialog -->
    <ViewSiteDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :site-id="dialogs.activeSite.value?.id"
      :site="dialogs.activeSite.value"
      @updated="refresh"
    />

    <!-- 👉 Add Site Dialog -->
    <AddNewSiteDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      @add-site="refresh"
    />

    <!-- 👉 Manage Areas Dialog -->
    <AreaManageDialog
      v-if="dialogs.areaManageDialog.isOpen.value"
      v-model="dialogs.areaManageDialog.isOpen.value"
      :site-id="dialogs.activeSite.value?.id"
      @updated="refresh"
    />

    <!-- 👉 Site Analytics Dialog -->
    <SiteAnalyticsDialog
      v-if="dialogs.analyticsDialog.isOpen.value"
      v-model="dialogs.analyticsDialog.isOpen.value"
      :site-id="dialogs.activeSite.value?.id"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-open="dialogs.deleteDialog.isOpen.value"
      :title="$t('Delete Site')"
      :message="$t('Are you sure you want to delete site {name}? All associated areas and equipment will be affected.', { name: dialogs.activeSite.value?.name || '' })"
      :loading="actions.isDeleting.value"
      @confirm="handleConfirmDelete"
    />

    <!-- 👉 Mobile FAB Speed Dial -->
    <AppFabSpeedDial
      v-if="smAndDown"
      v-model="isMobileFabOpen"
      class="d-md-none"
      icon="tabler-plus"
      active-icon="tabler-x"
      :actions="mobileFabActions"
      activator-rounded="lg"
    />
  </div>
</template>
