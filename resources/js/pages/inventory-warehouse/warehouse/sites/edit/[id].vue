<script setup>
/* eslint-disable camelcase */
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpErrorBanner,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpStatusChip,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useWarehouseSiteEdit } from '@/composables/inventory/sites/useWarehouseSiteEdit'
import { useJournalNav } from '@/composables/useJournalNav'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import WarehouseSiteLocationsSection from '@/views/inventory/sites/edit/WarehouseSiteLocationsSection.vue'
import WarehouseSiteMobileVehicleSection from '@/views/inventory/sites/edit/WarehouseSiteMobileVehicleSection.vue'
import WarehouseSiteProfileSection from '@/views/inventory/sites/edit/WarehouseSiteProfileSection.vue'
import WarehouseSiteZonesSection from '@/views/inventory/sites/edit/WarehouseSiteZonesSection.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/inventory-warehouse/warehouse/sites/edit/:id',
  name: 'inventory-warehouse-sites-edit-id',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ability = useAbility()
const { smAndDown } = useDisplay()

const siteId = computed(() => route.params.id)

// ── Permissions ─────────────────────────────────────────────────────────────
const isReadOnly = computed(() => !ability.can('update', 'InventoryWarehouseSites'))
const canDelete = computed(() => ability.can('delete', 'InventoryWarehouseSites'))

// ── Edit Page Composable ────────────────────────────────────────────────────
const {
  loading,
  saving,
  pageError,
  fetchedSite,
  form,
  isDirty,
  fetchSite,
  handleSave,
  handleDelete,
} = useWarehouseSiteEdit(siteId)

// ── Unsaved Changes Guard ───────────────────────────────────────────────────
const {
  isDialogVisible: isUnsavedChangesDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Dialog States ───────────────────────────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const quickActionsOpen = ref(false)

// ── Sections for Journal Navigation ─────────────────────────────────────────
const sections = computed(() => {
  const list = [
    {
      id: 'profile',
      title: t('inventory.sites.sections.basic_info', 'Facility Profile'),
      icon: 'tabler-building-warehouse',
    },
    {
      id: 'zones',
      title: t('inventory.zones.title', 'Warehouse Zones'),
      icon: 'tabler-layers',
      badge: fetchedSite.value?.zones?.length ?? fetchedSite.value?.zones_count ?? 0,
      badgeColor: 'info',
    },
    {
      id: 'locations',
      title: t('inventory.locations.title', 'Bin Locations'),
      icon: 'tabler-map-pin',
      badge: fetchedSite.value?.locations_count ?? 0,
      badgeColor: 'success',
    },
  ]

  if (fetchedSite.value?.site_type === 'mobile') {
    list.push({
      id: 'vehicle',
      title: t('inventory.sites.mobile.section_title', 'Mobile Carrier'),
      icon: 'tabler-truck',
    })
  }

  return list
})

const {
  activeSection,
  scrollToSection,
} = useJournalNav(sections, { idPrefix: 'section-' })

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('inventory.nav.title', 'Inventory & Warehouse'), to: '/inventory-warehouse', icon: 'tabler-packages' },
  { title: t('inventory.sites.title', 'Warehouse Sites'), to: '/inventory-warehouse/warehouse/sites/list', icon: 'tabler-building-warehouse' },
  {
    title: localizedValue(form.value.name) || form.value.code || fetchedSite.value?.code || t('inventory.sites.edit', 'Edit Site'),
    disabled: true,
  },
])

// ── Quick Actions for Mobile ────────────────────────────────────────────────
const quickActions = computed(() => {
  const acts = [
    {
      id: 'save-site',
      title: t('Save Changes'),
      icon: 'tabler-device-floppy',
      color: 'primary',
      disabled: saving.value || !isDirty.value || isReadOnly.value,
      onClick: handleSave,
    },
    {
      id: 'goto-zones',
      title: t('inventory.zones.title', 'Warehouse Zones'),
      icon: 'tabler-layers',
      color: 'info',
      onClick: () => scrollToSection('zones'),
    },
    {
      id: 'goto-locations',
      title: t('inventory.locations.title', 'Bin Locations'),
      icon: 'tabler-map-pin',
      color: 'success',
      onClick: () => scrollToSection('locations'),
    },
  ]

  if (fetchedSite.value?.site_type === 'mobile') {
    acts.push({
      id: 'goto-vehicle',
      title: t('inventory.sites.mobile.section_title', 'Mobile Carrier'),
      icon: 'tabler-truck',
      color: 'warning',
      onClick: () => scrollToSection('vehicle'),
    })
  }

  if (canDelete.value) {
    acts.push({
      id: 'delete-site',
      title: t('Delete Site'),
      icon: 'tabler-trash',
      color: 'error',
      onClick: () => {
        isDeleteDialogVisible.value = true
      },
    })
  }

  return acts
})

// ── Delete Confirmation ─────────────────────────────────────────────────────
const confirmDeleteSite = async () => {
  const ok = await handleDelete()

  if (ok) {
    isDeleteDialogVisible.value = false
    router.push({ name: 'inventory-warehouse-sites-list' })
  }
}

const resolveStatusVariant = status => ({
  active: 'success',
  inactive: 'secondary',
  maintenance: 'warning',
}[status?.toLowerCase()] || 'primary')

const resolveSiteTypeVariant = type => ({
  fixed: 'primary',
  mobile: 'info',
}[type?.toLowerCase()] || 'secondary')

onMounted(fetchSite)
</script>

<template>
  <div class="warehouse-site-edit-page">
    <!-- Breadcrumb -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- Loading Skeleton -->
    <VSkeletonLoader
      v-if="loading && !fetchedSite"
      type="card-avatar, article, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- Error Banner -->
    <WpErrorBanner
      v-else-if="pageError"
      :error="pageError"
      class="mb-4"
      @retry="fetchSite"
    />

    <!-- Main Workspace Layout -->
    <div
      v-else-if="fetchedSite"
      class="site-edit-layout"
    >
      <!-- 240px Sticky Sidebar Navigation (Desktop) + Bottom Nav (Mobile) -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="localizedValue(form.name) || form.code || fetchedSite.code"
        :subtitle="form.code || fetchedSite.code"
        id-prefix="section-"
        @update:model-value="scrollToSection($event)"
      >
        <template #avatar>
          <VAvatar
            rounded="lg"
            :size="40"
            :color="resolveSiteTypeVariant(form.site_type)"
            variant="tonal"
          >
            <VIcon
              :icon="form.site_type === 'mobile' ? 'tabler-truck' : 'tabler-building-warehouse'"
              size="22"
            />
          </VAvatar>
        </template>

        <template #footer>
          <div v-if="!isReadOnly">
            <VBtn
              block
              color="primary"
              :disabled="saving || !isDirty"
              :loading="saving"
              prepend-icon="tabler-device-floppy"
              class="mb-2 font-weight-semibold"
              @click="handleSave"
            >
              {{ t('Save Changes') }}
            </VBtn>

            <VBtn
              v-if="canDelete"
              block
              variant="tonal"
              color="error"
              prepend-icon="tabler-trash"
              class="font-weight-medium"
              @click="isDeleteDialogVisible = true"
            >
              {{ t('Delete') }}
            </VBtn>
          </div>
        </template>
      </WpJournalNav>

      <!-- Main Journal Feed Content -->
      <main class="journal-feed">
        <!-- Top KPI Metrics -->
        <WpKpiGroup
          :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
          :item-count="4"
          class="mb-6 site-top-kpis"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="t('inventory.sites.columns.zones', 'Zones')"
                :value="fetchedSite.zones?.length ?? fetchedSite.zones_count ?? 0"
                icon="tabler-layers"
                icon-color="info"
                clickable
                @click="scrollToSection('zones')"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="t('inventory.sites.columns.locations', 'Locations')"
                :value="fetchedSite.locations_count ?? 0"
                icon="tabler-map-pin"
                icon-color="success"
                clickable
                @click="scrollToSection('locations')"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="t('inventory.sites.columns.type', 'Site Type')"
                :value="t(`inventory.sites.types.${form.site_type ?? 'fixed'}`)"
                :icon="form.site_type === 'mobile' ? 'tabler-truck' : 'tabler-building-warehouse'"
                :icon-color="resolveSiteTypeVariant(form.site_type)"
                clickable
                @click="scrollToSection('profile')"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="t('inventory.sites.columns.status', 'Status')"
                :value="t(`inventory.sites.statuses.${form.status ?? 'active'}`)"
                icon="tabler-circle-check"
                :icon-color="resolveStatusVariant(form.status)"
                clickable
                @click="scrollToSection('profile')"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- Section 1: Facility Profile -->
        <WarehouseSiteProfileSection
          :form="form"
          :site="fetchedSite"
          :is-read-only="isReadOnly"
        />

        <!-- Section 2: Warehouse Zones -->
        <WarehouseSiteZonesSection
          :site="fetchedSite"
          :is-read-only="isReadOnly"
          @refresh="fetchSite"
        />

        <!-- Section 3: Bin Locations -->
        <WarehouseSiteLocationsSection
          :site="fetchedSite"
          :is-read-only="isReadOnly"
          @refresh="fetchSite"
        />

        <!-- Section 4: Mobile Vehicle Carrier (Mobile Sites Only) -->
        <WarehouseSiteMobileVehicleSection
          v-if="form.site_type === 'mobile'"
          :site="fetchedSite"
          :is-read-only="isReadOnly"
          @refresh="fetchSite"
        />
      </main>
    </div>

    <!-- Mobile Quick Actions Floating Launcher & Sheet -->
    <div
      v-if="smAndDown && fetchedSite"
      class="site-edit-quick-actions-launcher"
    >
      <VFab
        color="primary"
        icon="tabler-bolt"
        rounded="lg"
        size="large"
        :aria-label="t('Quick Actions')"
        @click="quickActionsOpen = true"
      />
    </div>

    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Unsaved Changes Dialog -->
    <WpUnsavedChangesDialog
      v-model="isUnsavedChangesDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />

    <!-- Delete Confirmation Dialog -->
    <WpDeleteDialog
      :is-open="isDeleteDialogVisible"
      :title="t('inventory.sites.delete_title')"
      :description="t('inventory.sites.delete_confirm', { name: localizedValue(fetchedSite?.name) || fetchedSite?.code })"
      :loading="saving"
      :confirm-action="confirmDeleteSite"
      @update:is-open="v => { if (!v) isDeleteDialogVisible = false }"
    />
  </div>
</template>

<style lang="scss" scoped>
.warehouse-site-edit-page {
  min-block-size: 100vh;
  overflow-x: clip;
  padding-block-end: 1rem;
}

.site-edit-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 959px) {
    flex-direction: column;
    padding-block-end: calc(92px + env(safe-area-inset-bottom, 0px));
  }
}

.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.25rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.journal-section {
  min-inline-size: 0;
  scroll-margin-block-start: 160px;

  @media (max-width: 959px) {
    scroll-margin-block-start: 130px;
  }
}

.site-edit-quick-actions-launcher {
  position: fixed !important;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 16px;

  :deep(.v-fab) {
    margin: 0 !important;
  }
}
</style>
