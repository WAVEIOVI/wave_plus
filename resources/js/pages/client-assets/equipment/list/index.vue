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
import { useEquipmentActions } from '@/composables/client-assets/equipment/useEquipmentActions'
import { useEquipmentDialogs } from '@/composables/client-assets/equipment/useEquipmentDialogs'
import { useEquipmentList } from '@/composables/client-assets/equipment/useEquipmentList'
import { localizedValue } from '@/composables/useLocaleField'
import AddNewEquipmentDialog from '@/views/client-assets/equipment/list/AddNewEquipmentDialog.vue'
import DeleteEquipmentDialog from '@/views/client-assets/equipment/list/DeleteEquipmentDialog.vue'
import ViewEquipmentDialog from '@/views/client-assets/equipment/list/ViewEquipmentDialog.vue'
import EquipmentReplaceQrDialog from '@/views/client-assets/equipment/EquipmentReplaceQrDialog.vue'
import EquipmentTransferDialog from '@/views/client-assets/equipment/EquipmentTransferDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/client-assets/equipment/list',
  name: 'client-assets-equipment-list',
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

// ── Equipment List Orchestration ───────────────────────────────────────────
const {
  equipment,
  totalEquipment,
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
  lifecycleOptions,
  complianceOptions,
  criticalityOptions,
  clientsList,
  sitesList,
  classesList,
  blueprintsList,
  resolveLifecycleVariant,
  resolveComplianceVariant,
  resolveCriticalityVariant,
  selectedEquipment,
  clearSelection,
  kpiCards,
} = useEquipmentList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useEquipmentDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useEquipmentActions({
  onRefresh: refresh,
})

const handleRowDelete = item => {
  dialogs.openDelete(item)
}

const handleConfirmDelete = async () => {
  if (dialogs.activeEquipment.value?.id) {
    await actions.deleteEquipment(dialogs.activeEquipment.value.id)
    dialogs.closeDelete()
  }
}

const handleBulkDelete = async () => {
  for (const id of selectedEquipment.value) {
    await actions.deleteEquipment(id)
  }
  clearSelection()
}

const handleKpiClick = kpi => {
  if (kpi.id === 'operational') {
    setFilter('lifecycle_status', filters.lifecycle_status === 'operational' ? null : 'operational')
  } else if (kpi.id === 'maintenance') {
    setFilter('lifecycle_status', filters.lifecycle_status === 'under_maintenance' ? null : 'under_maintenance')
  } else if (kpi.id === 'overdue') {
    setFilter('compliance_status', filters.compliance_status === 'overdue' ? null : 'overdue')
  } else {
    resetFilters()
  }
}

const isKpiActive = kpi => {
  if (kpi.id === 'operational') return filters.lifecycle_status === 'operational'
  if (kpi.id === 'maintenance') return filters.lifecycle_status === 'under_maintenance'
  if (kpi.id === 'overdue') return filters.compliance_status === 'overdue'
  if (kpi.id === 'total') return !filters.lifecycle_status && !filters.compliance_status

  return false
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Equipment'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Transfer Location'), icon: 'tabler-arrows-transfer-down', emit: 'transfer' },
  { label: t('Replace QR Code'), icon: 'tabler-qrcode', emit: 'replace-qr' },
  { label: t('Download QR'), icon: 'tabler-download', emit: 'download-qr' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Mobile FAB Actions ──────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'new-equipment',
    icon: 'tabler-plus',
    title: t('New Equipment'),
    onClick: () => router.push({ name: 'client-assets-equipment-edit-id', params: { id: 'new' } }),
  },
  {
    id: 'quick-add',
    icon: 'tabler-bolt',
    title: t('Quick Add'),
    onClick: () => dialogs.openAdd(),
  },
  {
    id: 'filter-equipment',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: () => { dialogs.isFilterDialogOpen.value = true },
  },
])
</script>

<template>
  <div class="equipment-list-page d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('Equipment Units')"
      :subtitle="$t('Track operational equipment, fire extinguishers, testing schedules, and site allocations.')"
      icon="tabler-fire-extinguisher"
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
        :search-placeholder="$t('Search by code, brand, model, S/N...')"
        :create-label="$t('New Equipment')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @create="router.push({ name: 'client-assets-equipment-edit-id', params: { id: 'new' } })"
        @open-filters="dialogs.isFilterDialogOpen.value = true"
        @refresh="refresh"
      >
        <template #prepend>
          <VBtn
            class="d-none d-md-inline-flex"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-bolt"
            @click="dialogs.openAdd"
          >
            {{ $t('Quick Add') }}
          </VBtn>
        </template>

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
        v-if="selectedEquipment.length"
        class="px-4 pb-2"
      >
        <WpBulkActions
          :selected-count="selectedEquipment.length"
          @clear="clearSelection"
          @delete="handleBulkDelete"
        />
      </div>

      <!-- 👉 Responsive List (Desktop Table + Mobile Cards) -->
      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        v-model:selected="selectedEquipment"
        :headers="headers"
        :items="equipment"
        :total-items="totalEquipment"
        :loading="loading"
        :error="error"
        show-select
        item-value="id"
        @retry="refresh"
      >
        <!-- Equipment & Model (Merged Column) -->
        <template #item.equipment_code="{ item }">
          <div class="d-flex align-center gap-3 py-2 min-w-0">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="40"
              class="shrink-0"
            >
              <VIcon
                icon="tabler-fire-extinguisher"
                size="22"
              />
            </VAvatar>
            <div class="d-flex flex-column min-w-0">
              <RouterLink
                :to="{ name: 'client-assets-equipment-edit-id', params: { id: item.id } }"
                class="text-body-1 font-weight-bold text-high-emphasis text-link text-truncate"
                :title="item.brand ? `${item.brand} ${localizedValue(item.model_name) || item.model_name}` : (localizedValue(item.model_name) || item.model_name || item.equipment_code)"
              >
                {{ item.brand ? `${item.brand} ` : '' }}{{ localizedValue(item.model_name) || item.model_name || $t('Unnamed Equipment') }}
              </RouterLink>
              <div class="d-flex align-center gap-1 flex-wrap mt-0.5 text-caption">
                <span class="font-mono text-primary font-weight-medium">
                  {{ item.equipment_code }}
                </span>
                <span
                  v-if="item.serial_number"
                  class="text-medium-emphasis"
                >
                  · S/N: {{ item.serial_number }}
                </span>
                <span
                  v-if="item.equipment_class?.name"
                  class="text-disabled text-truncate"
                  style="max-inline-size: 160px;"
                >
                  · {{ localizedValue(item.equipment_class.name) || item.equipment_class.name }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Client & Location column (Contact & Location style with 3 icons) -->
        <template #item.client="{ item }">
          <div class="d-flex flex-column gap-1 py-2">
            <!-- Client row with client icon -->
            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-building-store"
                color="primary"
                class="shrink-0"
              />
              <span
                v-if="item.client?.legal_name || item.client?.name"
                class="text-body-2 font-weight-medium text-truncate"
                style="max-inline-size: 220px;"
                :title="localizedValue(item.client?.legal_name) || item.client?.legal_name || item.client?.name"
              >
                {{ localizedValue(item.client?.legal_name) || item.client?.legal_name || item.client?.name }}
              </span>
              <span
                v-else
                class="text-caption text-disabled"
              >
                {{ $t('No Client Assigned') }}
              </span>
            </div>

            <!-- Site row with site icon -->
            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-map-pin"
                color="error"
                class="shrink-0"
              />
              <span
                v-if="item.site?.name"
                class="text-body-2 text-truncate"
                style="max-inline-size: 220px;"
                :title="localizedValue(item.site.name) || item.site.name"
              >
                {{ localizedValue(item.site.name) || item.site.name }}
              </span>
              <span
                v-else
                class="text-caption text-disabled"
              >
                {{ $t('No Site Assigned') }}
              </span>
            </div>

            <!-- Zone row with zone icon -->
            <div class="d-flex align-center gap-2">
              <VIcon
                size="14"
                icon="tabler-hierarchy"
                color="info"
                class="shrink-0"
              />
              <span
                v-if="item.area?.name || item.area?.hierarchy_path"
                class="text-caption text-medium-emphasis text-truncate"
                style="max-inline-size: 220px;"
                :title="item.area.hierarchy_path || localizedValue(item.area.name) || item.area.name"
              >
                {{ item.area.hierarchy_path || localizedValue(item.area.name) || item.area.name }}
              </span>
              <span
                v-else
                class="text-caption text-disabled font-italic"
              >
                {{ $t('Site-wide (no area)') }}
              </span>
            </div>
          </div>
        </template>

        <!-- Lifecycle Status column -->
        <template #item.lifecycle_status="{ item }">
          <VChip
            :color="resolveLifecycleVariant(item.lifecycle_status)"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ (item.lifecycle_status || 'draft').replace(/_/g, ' ') }}
          </VChip>
        </template>

        <!-- Compliance Status column -->
        <template #item.compliance_status="{ item }">
          <VChip
            :color="resolveComplianceVariant(item.compliance_status)"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ (item.compliance_status || 'not_applicable').replace(/_/g, ' ') }}
          </VChip>
        </template>

        <!-- Criticality column -->
        <template #item.criticality="{ item }">
          <VChip
            :color="resolveCriticalityVariant(item.criticality)"
            size="small"
            variant="tonal"
            label
            class="text-capitalize"
          >
            {{ item.criticality || 'low' }}
          </VChip>
        </template>

        <!-- Row Actions column -->
        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions()"
            @view="dialogs.openView(item)"
            @edit="router.push({ name: 'client-assets-equipment-edit-id', params: { id: item.id } })"
            @transfer="dialogs.openTransfer(item)"
            @replace-qr="dialogs.openReplaceQr(item)"
            @download-qr="actions.downloadQrCode(item.id)"
            @delete="handleRowDelete(item)"
          />
        </template>

        <!-- Mobile Card Template -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.brand ? `${item.brand} ${localizedValue(item.model_name) || item.model_name || ''}` : localizedValue(item.model_name) || item.model_name || item.equipment_code"
            :subtitle="item.equipment_code"
            icon="tabler-fire-extinguisher"
            :status="item.lifecycle_status"
            :status-color="resolveLifecycleVariant(item.lifecycle_status)"
            clickable
            @click="dialogs.openView(item)"
          >
            <template #badges>
              <VChip
                :color="resolveComplianceVariant(item.compliance_status)"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ (item.compliance_status || 'not_applicable').replace(/_/g, ' ') }}
              </VChip>
            </template>

            <template #details>
              <div
                v-if="item.client"
                class="wp-mobile-row"
              >
                <VIcon
                  icon="tabler-building-store"
                  size="16"
                  color="primary"
                  class="wp-mobile-row__icon shrink-0"
                />
                <span class="wp-mobile-row__label">{{ $t('wp.labels.client', 'Client') }}</span>
                <span class="wp-mobile-row__value text-truncate">{{ localizedValue(item.client.legal_name) || item.client.legal_name || item.client.name }}</span>
              </div>
              <div
                v-if="item.site"
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
                  {{ localizedValue(item.site.name) || item.site.name }}
                </span>
              </div>
              <div
                v-if="item.area"
                class="wp-mobile-row"
              >
                <VIcon
                  icon="tabler-hierarchy"
                  size="16"
                  color="info"
                  class="wp-mobile-row__icon shrink-0"
                />
                <span class="wp-mobile-row__label">{{ t('Zone') }}</span>
                <span class="wp-mobile-row__value text-truncate">
                  {{ item.area.hierarchy_path || localizedValue(item.area.name) || item.area.name }}
                </span>
              </div>
              <div
                v-if="item.serial_number"
                class="wp-mobile-row"
              >
                <VIcon
                  icon="tabler-barcode"
                  size="16"
                  class="wp-mobile-row__icon shrink-0"
                />
                <span class="wp-mobile-row__label">{{ $t('wp.labels.serial_number', 'S/N') }}</span>
                <span class="wp-mobile-row__value font-mono">{{ item.serial_number }}</span>
              </div>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions()"
                @view="dialogs.openView(item)"
                @edit="router.push({ name: 'client-assets-equipment-edit-id', params: { id: item.id } })"
                @transfer="dialogs.openTransfer(item)"
                @replace-qr="dialogs.openReplaceQr(item)"
                @download-qr="actions.downloadQrCode(item.id)"
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
      :title="$t('Filter Equipment')"
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
            v-model="stagedFilters.site_id"
            :label="$t('Site')"
            :items="sitesList"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.equipment_class_id"
            :label="$t('Equipment Class')"
            :items="classesList"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.blueprint_id"
            :label="$t('Catalog Blueprint')"
            :items="blueprintsList"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.lifecycle_status"
            :label="$t('Lifecycle Status')"
            :items="lifecycleOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.compliance_status"
            :label="$t('Compliance Status')"
            :items="complianceOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.criticality"
            :label="$t('Criticality')"
            :items="criticalityOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 View Equipment Dialog -->
    <ViewEquipmentDialog
      v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
      :equipment-id="dialogs.activeEquipment.value?.id"
      :equipment="dialogs.activeEquipment.value"
      @updated="refresh"
    />

    <!-- 👉 Quick Add Equipment Dialog -->
    <AddNewEquipmentDialog
      v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
      @add-equipment="refresh"
    />

    <!-- 👉 Transfer Equipment Dialog -->
    <EquipmentTransferDialog
      v-model="dialogs.transferDialog.isOpen.value"
      :equipment-id="dialogs.activeEquipment.value?.id"
      @transferred="refresh"
    />

    <!-- 👉 Replace QR Code Dialog -->
    <EquipmentReplaceQrDialog
      v-model="dialogs.replaceQrDialog.isOpen.value"
      :equipment-id="dialogs.activeEquipment.value?.id"
      @saved="refresh"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-open="dialogs.deleteDialog.isOpen.value"
      :title="$t('Delete Equipment')"
      :message="$t('Are you sure you want to delete equipment {code}? This action cannot be undone.', { code: dialogs.activeEquipment.value?.equipment_code || '' })"
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
