<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpDeleteDialog,
  WpErrorBanner,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
  WpStatusChip,
} from '@/components/shared'
import { useWarehouseSiteActions } from '@/composables/inventory/sites/useWarehouseSiteActions'
import { useWarehouseSiteDialogs } from '@/composables/inventory/sites/useWarehouseSiteDialogs'
import { useWarehouseSiteList } from '@/composables/inventory/sites/useWarehouseSiteList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import AddNewWarehouseSiteDialog from '@/views/inventory/sites/list/AddNewWarehouseSiteDialog.vue'
import ViewWarehouseSiteDialog from '@/views/inventory/sites/list/ViewWarehouseSiteDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/inventory-warehouse/warehouse/sites/list',
  name: 'inventory-warehouse-sites-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouseSites',
  },
})

const { t } = useI18n()
const router = useRouter()
const ability = useAbility()

const {
  sites,
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
  siteTypeOptions,
  resolveStatusVariant,
  resolveSiteTypeVariant,
  resolveSiteTypeIcon,
} = useWarehouseSiteList()

const {
  createSiteDialog,
  deleteSiteDialog,
  detailSiteDialog,
  activeSite,
  openCreateSite,
  closeCreateSite,
  openDeleteSite,
  closeDeleteSite,
  openDetailSite,
  closeDetailSite,
} = useWarehouseSiteDialogs()

const actions = useWarehouseSiteActions({ onRefresh: refresh })

// Filter dialog
const filterDialogOpen = ref(false)

const openFilters = () => {
  stageFilters()
  filterDialogOpen.value = true
}

const applyFilters = () => {
  applyStaged()
  filterDialogOpen.value = false
}

const resetFilterDialog = () => {
  resetStaged()
  applyStaged()
  filterDialogOpen.value = false
}

// ── KPIs Calculation ────────────────────────────────────────────────────────
const kpiStats = computed(() => {
  const list = sites.value || []
  const fixedCount = list.filter(s => s.site_type === 'fixed').length
  const mobileCount = list.filter(s => s.site_type === 'mobile').length
  const totalZones = list.reduce((sum, s) => sum + (Number(s.zones_count) || 0), 0)
  const totalLocations = list.reduce((sum, s) => sum + (Number(s.locations_count) || 0), 0)

  return {
    total: totalItems.value || list.length,
    fixed: fixedCount,
    mobile: mobileCount,
    zonesAndLocations: `${totalZones} / ${totalLocations}`,
  }
})

const handleKpiFilter = type => {
  if (type === 'all') {
    resetFilters()
  } else {
    setFilter('site_type', type)
  }
}

// ── Submit Handlers ─────────────────────────────────────────────────────────
const handleCreateSubmit = async payload => {
  const result = await actions.createSite(payload)
  if (result) {
    closeCreateSite()

    if (result?.id) {
      router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: result.id } })
    }
  }
}

const handleDelete = async () => {
  if (!activeSite.value?.id) return

  const ok = await actions.deleteSite(activeSite.value.id)

  if (ok) closeDeleteSite()
}

const navigateToEdit = site => {
  router.push({ name: 'inventory-warehouse-sites-edit-id', params: { id: site.id } })
}

const handleView = site => {
  openDetailSite(site)
}

// ── Row Actions ─────────────────────────────────────────────────────────────
const rowActions = site => {
  const acts = [
    {
      label: t('inventory.stock_items.actions.view'),
      icon: 'tabler-eye',
      action: () => handleView(site),
    },
    {
      label: t('inventory.sites.actions.edit'),
      icon: 'tabler-edit',
      action: () => navigateToEdit(site),
    },
  ]

  if (ability.can('delete', 'InventoryWarehouseSites')) {
    acts.push({
      label: t('inventory.sites.actions.delete'),
      icon: 'tabler-trash',
      color: 'error',
      action: () => openDeleteSite(site),
    })
  }

  return acts
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <WpPageHeader
      :title="t('inventory.sites.title')"
      :subtitle="t('inventory.sites.subtitle')"
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <VBtn
          v-if="ability.can('create', 'InventoryWarehouseSites')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateSite"
        >
          {{ t('inventory.sites.add') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- Error Banner -->
    <WpErrorBanner
      v-if="error"
      :error="error"
      class="mb-4"
      @retry="refresh"
    />

    <!-- KPI Summary Cards -->
    <WpKpiGroup
      :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
      :item-count="4"
      class="mb-6"
    >
      <template #default="{ getColProps }">
        <VCol v-bind="getColProps(0)">
          <WpKpiCard
            :title="t('inventory.sites.kpi.total_sites')"
            :value="kpiStats.total"
            icon="tabler-building-warehouse"
            icon-color="primary"
            :loading="loading"
            clickable
            @click="handleKpiFilter('all')"
          />
        </VCol>
        <VCol v-bind="getColProps(1)">
          <WpKpiCard
            :title="t('inventory.sites.kpi.fixed_sites')"
            :value="kpiStats.fixed"
            icon="tabler-building"
            icon-color="info"
            :loading="loading"
            clickable
            @click="handleKpiFilter('fixed')"
          />
        </VCol>
        <VCol v-bind="getColProps(2)">
          <WpKpiCard
            :title="t('inventory.sites.kpi.mobile_sites')"
            :value="kpiStats.mobile"
            icon="tabler-truck"
            icon-color="warning"
            :loading="loading"
            clickable
            @click="handleKpiFilter('mobile')"
          />
        </VCol>
        <VCol v-bind="getColProps(3)">
          <WpKpiCard
            :title="t('inventory.sites.kpi.zones_and_locations')"
            :value="kpiStats.zonesAndLocations"
            icon="tabler-hierarchy"
            icon-color="success"
            :loading="loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- Toolbar -->
    <WpPageToolbar
      v-model:search="search"
      :active-filter-count="activeFilterCount"
      :loading="loading"
      @refresh="refresh"
      @open-filters="openFilters"
    >
      <template #filter-chips>
        <WpActiveFilters
          :filters="activeFilters"
          @remove="key => removeFilter(key)"
          @clear-all="resetFilters"
        />
      </template>
    </WpPageToolbar>

    <!-- Responsive List Table -->
    <WpResponsiveList
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="sites"
      :total-items="totalItems"
      :loading="loading"
      :error="error"
      @retry="refresh"
    >
      <!-- Code -->
      <template #item.code="{ item }">
        <span class="font-monospace font-weight-medium">{{ item.code }}</span>
      </template>

      <!-- Name -->
      <template #item.name="{ item }">
        <div class="font-weight-medium text-body-1">
          {{ localizedValue(item.name) || item.code }}
        </div>
        <div
          v-if="item.city || item.country"
          class="text-caption text-medium-emphasis"
        >
          {{ [item.city, item.country].filter(Boolean).join(', ') }}
        </div>
      </template>

      <!-- Site Type with Vehicle Plate if mobile -->
      <template #item.site_type="{ item }">
        <div class="d-flex flex-column gap-1">
          <div class="d-flex align-center gap-1">
            <VIcon
              :icon="resolveSiteTypeIcon(item.site_type)"
              size="16"
              :color="resolveSiteTypeVariant(item.site_type)"
            />
            <WpStatusChip
              :status="item.site_type"
              :color="resolveSiteTypeVariant(item.site_type)"
              :label="t(`inventory.sites.types.${item.site_type ?? 'fixed'}`)"
              size="x-small"
            />
          </div>
          <div
            v-if="item.site_type === 'mobile' && (item.vehicle?.plate_number || item.vehicle?.registration_number)"
            class="text-caption font-monospace text-primary"
          >
            <VIcon
              icon="tabler-car"
              size="12"
              class="me-1"
            />
            {{ item.vehicle.plate_number || item.vehicle.registration_number }}
          </div>
        </div>
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :color="resolveStatusVariant(item.status)"
          :label="t(`inventory.sites.statuses.${item.status}`)"
          size="x-small"
        />
      </template>

      <!-- Zones Count -->
      <template #item.zones_count="{ item }">
        <VChip
          size="x-small"
          color="secondary"
          variant="tonal"
        >
          {{ item.zones_count ?? item.zones?.length ?? 0 }}
        </VChip>
      </template>

      <!-- Locations Count -->
      <template #item.locations_count="{ item }">
        <VChip
          size="x-small"
          color="info"
          variant="tonal"
        >
          {{ item.locations_count ?? 0 }}
        </VChip>
      </template>

      <!-- Manager -->
      <template #item.manager="{ item }">
        <span class="text-body-2">{{ item.manager?.name || '—' }}</span>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <WpRowActions :actions="rowActions(item)" />
      </template>

      <!-- Mobile Card -->
      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="localizedValue(item.name) || item.code"
          :subtitle="item.code"
          :status="item.status"
          :status-label="t(`inventory.sites.statuses.${item.status}`)"
          :status-color="resolveStatusVariant(item.status)"
          clickable
          @click="handleView(item)"
        >
          <template #badges>
            <WpStatusChip
              :status="item.site_type"
              :color="resolveSiteTypeVariant(item.site_type)"
              :label="t(`inventory.sites.types.${item.site_type ?? 'fixed'}`)"
              size="x-small"
            />
            <VChip
              v-if="item.site_type === 'mobile' && (item.vehicle?.plate_number || item.vehicle?.registration_number)"
              size="x-small"
              color="info"
              variant="tonal"
              class="font-monospace"
            >
              {{ item.vehicle.plate_number || item.vehicle.registration_number }}
            </VChip>
          </template>

          <template #details>
            <div class="d-flex gap-4">
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.sites.columns.zones') }}
                </div>
                <div class="font-weight-medium">
                  {{ item.zones_count ?? item.zones?.length ?? 0 }}
                </div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.sites.columns.locations') }}
                </div>
                <div class="font-weight-medium">
                  {{ item.locations_count ?? 0 }}
                </div>
              </div>
              <div v-if="item.city">
                <div class="text-caption text-medium-emphasis">
                  {{ t('inventory.sites.columns.city') }}
                </div>
                <div class="font-weight-medium">
                  {{ item.city }}
                </div>
              </div>
            </div>
          </template>

          <template #actions>
            <WpRowActions :actions="rowActions(item)" />
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>

    <!-- Filter Dialog -->
    <WpFilterDialog
      v-model="filterDialogOpen"
      @apply="applyFilters"
      @reset="resetFilterDialog"
    >
      <VRow>
        <VCol cols="12">
          <VSelect
            v-model="stagedFilters.status"
            :items="statusOptions"
            :label="t('inventory.sites.filters.status')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VSelect
            v-model="stagedFilters.site_type"
            :items="siteTypeOptions"
            :label="t('inventory.sites.filters.type')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- Create Dialog -->
    <AddNewWarehouseSiteDialog
      v-model="createSiteDialog.isOpen.value"
      :loading="actions.saving.value"
      @submit="handleCreateSubmit"
    />

    <!-- View Detail Dialog -->
    <ViewWarehouseSiteDialog
      v-model="detailSiteDialog.isOpen.value"
      :site="activeSite"
      @edit="navigateToEdit"
    />

    <!-- Delete Confirmation Dialog -->
    <WpDeleteDialog
      :is-open="deleteSiteDialog.isOpen.value"
      :title="t('inventory.sites.delete_title')"
      :description="t('inventory.sites.delete_confirm', { name: localizedValue(activeSite?.name) || activeSite?.code })"
      :loading="actions.isDeleting.value"
      :confirm-action="handleDelete"
      @update:is-open="v => { if (!v) closeDeleteSite() }"
    />
  </div>
</template>
