<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpDeleteDialog,
  WpEmptyState,
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
import { useVehicleWorkflow } from '@/composables/fleet/useVehicleWorkflow'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useHrStore } from '@/stores/hrStore'
import { useClientStore } from '@/stores/useClientStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useVehicleStore } from '@/stores/useVehicleStore'
import VehicleAssignDialog from '@/views/people-skills/fleet/vehicles/VehicleAssignDialog.vue'
import VehicleClientUsageDialog from '@/views/people-skills/fleet/vehicles/VehicleClientUsageDialog.vue'
import VehicleDetailDialog from '@/views/people-skills/fleet/vehicles/VehicleDetailDialog.vue'
import VehicleOdometerDialog from '@/views/people-skills/fleet/vehicles/VehicleOdometerDialog.vue'
import VehicleRentalDialog from '@/views/people-skills/fleet/vehicles/VehicleRentalDialog.vue'
import VehicleReturnDialog from '@/views/people-skills/fleet/vehicles/VehicleReturnDialog.vue'
import VehicleStatusDialog from '@/views/people-skills/fleet/vehicles/VehicleStatusDialog.vue'
import VehicleTelemetryDialog from '@/views/people-skills/fleet/vehicles/VehicleTelemetryDialog.vue'
import VehicleWizardDialog from '@/views/people-skills/fleet/vehicles/VehicleWizardDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/people-skills/fleet/vehicles',
  name: 'people-skills-vehicles-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'Fleet',
  },
})

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const ability = useAbility()
const router = useRouter()
const store = useVehicleStore()
const hrStore = useHrStore()
const providerStore = useServiceProviderStore()
const clientStore = useClientStore()
const inventoryStore = useInventoryStore()

// View mode: 'table' or 'grid'
const viewMode = ref('table')
const filterOpen = ref(false)
const isViewOpen = ref(false)
const isViewLoading = ref(false)
const viewError = ref(null)
const selectedVehicle = ref(null)
let viewRequestId = 0

// Alert arrays normalized from single endpoint
const alerts = ref({ maintenance: [], documents: [] })

const serviceProviders = ref([])
const clients = ref([])
const sites = ref([])

// Page composable
const page = useListPage({
  fetchFn: params => store.fetchList(params),
  initialFilters: { status: null, type: null, ownership_type: null },
  initialSortKey: 'created_at',
  initialSortOrder: 'desc',
})

// Unified Workflow Composable
const workflow = useVehicleWorkflow(async () => {
  await page.refresh()
  await loadAlerts()
})

const {
  isFormOpen,
  isAssignOpen,
  isReturnOpen,
  isStatusOpen,
  isOdometerOpen,
  isRentalOpen,
  isClientUsageOpen,
  isTelemetryOpen,
  isDeleteOpen,
  isSubmitting,
  targetVehicle,
} = workflow

const userOptions = computed(() => {
  return (hrStore.availableUsers || []).map(user => ({
    title: user.name || user.full_name || user.email,
    value: user.id,
  }))
})

const providerOptions = computed(() => {
  return serviceProviders.value.map(p => ({
    title: p.name || p.company_name,
    value: p.id,
  }))
})

const clientOptions = computed(() => {
  return clients.value.map(c => ({
    title: c.name || c.company_name,
    value: c.id,
  }))
})

// Real warehouse / base sites fetched from the Inventory module API.
const siteOptions = computed(() => {
  return sites.value.map(site => {
    const name = site.name?.en || site.name?.fr || (typeof site.name === 'string' ? site.name : '') || ''

    return {
      title: name ? `${site.code} — ${name}` : site.code,
      value: site.id,
    }
  })
})

// Single API call for alerts normalization
const loadAlerts = async () => {
  try {
    const res = await store.fetchAlerts()

    alerts.value.maintenance = Array.isArray(res?.maintenance) ? res.maintenance : []
    alerts.value.documents = Array.isArray(res?.documents) ? res.documents : []
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('fleet.alerts_load_error'), 'error')
  }
}

// Guaranteed numeric KPI calculations
const kpis = computed(() => [
  {
    title: t('fleet.total_vehicles') || 'Total Vehicles',
    value: Number(page.totalItems.value || 0),
    icon: 'tabler-car',
    color: 'primary',
  },
  {
    title: t('fleet.status_operational') || 'Operational',
    value: Number((page.rows.value || []).filter(v => ['available', 'in_use'].includes(v.status)).length),
    icon: 'tabler-circle-check',
    color: 'success',
  },
  {
    title: t('fleet.maintenance_needed') || 'Maintenance Needed',
    value: Number(alerts.value.maintenance.length || 0),
    icon: 'tabler-tool',
    color: 'warning',
  },
  {
    title: t('fleet.document_alerts') || 'Document Alerts',
    value: Number(alerts.value.documents.length || 0),
    icon: 'tabler-file-alert',
    color: 'error',
  },
])

// Table Headers
const headers = computed(() => [
  { title: t('fleet.plate_number') || 'Vehicle', key: 'vehicle', sortable: true, minWidth: '220px' },
  { title: t('fleet.type') || 'Type', key: 'type', sortable: true },
  { title: t('fleet.ownership') || 'Ownership', key: 'ownership_type', sortable: true },
  { title: t('fleet.custody') || 'Assigned Custody', key: 'assignment', sortable: false },
  { title: t('fleet.odometer') || 'Odometer', key: 'odometer_km', sortable: true },
  { title: t('fleet.status') || 'Status', key: 'status', sortable: true },
  { title: t('Actions') || 'Actions', key: 'actions', sortable: false, align: 'end', width: '100px' },
])

const openVehicleView = async vehicle => {
  const requestId = ++viewRequestId

  selectedVehicle.value = vehicle
  isViewOpen.value = true
  isViewLoading.value = true
  viewError.value = null

  try {
    const fullVehicle = await store.fetchById(vehicle.id)

    if (requestId !== viewRequestId || !isViewOpen.value)
      return

    if (fullVehicle)
      selectedVehicle.value = fullVehicle
    else
      viewError.value = t('fleet.vehicle_not_found', 'Vehicle details could not be loaded.')
  } catch (error) {
    if (requestId === viewRequestId && isViewOpen.value)
      viewError.value = error?.data?.message || error?.message || t('fleet.vehicle_load_error', 'Failed to load vehicle details.')
  } finally {
    if (requestId === viewRequestId)
      isViewLoading.value = false
  }
}

// Localised status label with a humanised fallback for statuses without a key.
const statusLabel = status => {
  if (!status) return t('fleet.status') || 'Status'

  return t(`fleet.status_${status}`, status.replaceAll('_', ' '))
}

// Action items generator
const rowActions = vehicle => {
  const isAssigned = Boolean(vehicle.current_assignment || vehicle.current_responsible_employee_id)
  const isRental = vehicle.ownership_type === 'rental' || vehicle.status === 'rental_active'
  const isClient = vehicle.ownership_type === 'client' || vehicle.status === 'client_use'

  const actions = [
    { label: t('fleet.view_details') || 'View Vehicle', icon: 'tabler-eye', emit: 'view' },
  ]

  if (ability.can('update', 'Fleet')) {
    actions.push(
      { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
      {
        label: isAssigned ? (t('fleet.return_vehicle') || 'Return Vehicle') : (t('fleet.assign_vehicle') || 'Assign Vehicle'),
        icon: isAssigned ? 'tabler-arrow-back-up' : 'tabler-user-plus',
        emit: isAssigned ? 'return' : 'assign',
      },
      { label: t('fleet.update_odometer') || 'Update Odometer', icon: 'tabler-dashboard', emit: 'odometer' },
      { label: t('fleet.update_telemetry') || 'Update GPS Telemetry', icon: 'tabler-map-pin', emit: 'telemetry' },
      { label: t('fleet.change_status') || 'Change Status', icon: 'tabler-refresh', emit: 'status' },
      ...(isRental
        ? [{ label: t('fleet.end_rental') || 'End Rental', icon: 'tabler-car-off', emit: 'rental_end' }]
        : [{ label: t('fleet.start_rental') || 'Start Rental', icon: 'tabler-car', emit: 'rental_start' }]),
      ...(isClient
        ? [{ label: t('fleet.end_client_usage') || 'End Client Use', icon: 'tabler-building-bank', emit: 'client_end' }]
        : [{ label: t('fleet.start_client_usage') || 'Start Client Use', icon: 'tabler-building-estate', emit: 'client_start' }]),
    )
  }

  if (ability.can('delete', 'Fleet')) {
    actions.push({ label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true })
  }

  return actions
}

const navigateToEdit = id => {
  router.push({ name: 'people-skills-fleet-vehicles-edit', params: { id } })
}

const onRowAction = (actionName, vehicle) => {
  switch (actionName) {
  case 'view':
    openVehicleView(vehicle)
    break
  case 'edit':
    navigateToEdit(vehicle.id)
    break
  case 'assign':
    workflow.openAssign(vehicle)
    break
  case 'return':
    workflow.openReturn(vehicle)
    break
  case 'odometer':
    workflow.openOdometer(vehicle)
    break
  case 'telemetry':
    workflow.openTelemetry(vehicle)
    break
  case 'status':
    workflow.openStatus(vehicle)
    break
  case 'rental_start':
    workflow.openRental(vehicle, false)
    break
  case 'rental_end':
    workflow.openRental(vehicle, true)
    break
  case 'client_start':
    workflow.openClientUsage(vehicle, false)
    break
  case 'client_end':
    workflow.openClientUsage(vehicle, true)
    break
  case 'delete':
    workflow.openDelete(vehicle)
    break
  }
}

onMounted(async () => {
  const [metaResult, providersResult, clientsResult, sitesResult] = await Promise.allSettled([
    hrStore.fetchMeta(),
    providerStore.fetchServiceProviderOptions(),
    clientStore.fetchClientOptions(),
    inventoryStore.fetchSites({ per_page: 100 }),
    loadAlerts(),
  ])

  let optionsFailed = false

  if (metaResult.status === 'rejected') optionsFailed = true
  if (providersResult.status === 'fulfilled') serviceProviders.value = providersResult.value?.data || []
  else optionsFailed = true
  if (clientsResult.status === 'fulfilled') clients.value = clientsResult.value?.data || []
  else optionsFailed = true

  // A missing result can indicate the Inventory store handled an authorization failure.
  if (sitesResult.status === 'fulfilled' && sitesResult.value) sites.value = sitesResult.value.data || []
  else optionsFailed = true

  if (optionsFailed) {
    showMessage(t('fleet.options_load_error'), 'error')
  }
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Header -->
    <WpPageHeader
      :title="$t('fleet.vehicles_title') || 'Fleet Vehicles'"
      :subtitle="$t('fleet.vehicles_subtitle') || 'Resource management and operational fleet master registry'"
      icon="tabler-car"
    />

    <!-- KPI Strip -->
    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 2, sm: 2, md: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- Main Card -->
    <VCard>
      <!-- Toolbar with Table/Grid View Switcher -->
      <WpPageToolbar
        v-model:search="page.search.value"
        :create-label="$can('create', 'Fleet') ? ($t('fleet.add_vehicle') || 'Add Vehicle') : ''"
        show-filter
        :active-filter-count="page.activeFilterCount.value"
        :loading="page.loading.value"
        @create="workflow.openCreate"
        @refresh="() => { page.refresh(); loadAlerts() }"
        @open-filters="() => { page.stageFilters(); filterOpen = true }"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="page.activeFilterCount.value"
            :filters="page.activeFilters.value"
            :label-map="{
              status: $t('fleet.status') || 'Status',
              type: $t('fleet.type') || 'Type',
              ownership_type: $t('fleet.ownership') || 'Ownership',
            }"
            @remove="page.removeFilter($event.key)"
            @clear-all="page.resetFilters"
          />
        </template>

        <template #actions-append>
          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            variant="outlined"
            divided
            class="ms-2"
          >
            <VBtn
              value="table"
              icon="tabler-layout-list"
              size="small"
              :title="$t('fleet.view_mode_table')"
              :aria-label="$t('fleet.view_mode_table')"
            />
            <VBtn
              value="grid"
              icon="tabler-layout-grid"
              size="small"
              :title="$t('fleet.view_mode_grid')"
              :aria-label="$t('fleet.view_mode_grid')"
            />
          </VBtnToggle>
        </template>
      </WpPageToolbar>

      <!-- VIEW 1: TABLE / LIST VIEW -->
      <template v-if="viewMode === 'table'">
        <WpResponsiveList
          v-model:page="page.page.value"
          v-model:items-per-page="page.itemsPerPage.value"
          v-model:sort-by="page.sortBy.value"
          :headers="headers"
          :items="page.rows.value"
          :total-items="page.totalItems.value"
          :loading="page.loading.value"
          :error="page.error.value"
          @retry="page.refresh"
        >
          <!-- Vehicle Column -->
          <template #item.vehicle="{ item }">
            <div
              class="d-flex align-center gap-3 py-2 cursor-pointer"
              @click="openVehicleView(item)"
            >
              <VAvatar
                color="primary"
                variant="tonal"
              >
                <VIcon icon="tabler-car" />
              </VAvatar>
              <div>
                <div class="font-weight-medium text-primary text-decoration-underline-hover">
                  {{ item.plate_number }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.label }}
                  <span v-if="item.make || item.model">
                    · {{ [item.make, item.model].filter(Boolean).join(' ') }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <!-- Type Column -->
          <template #item.type="{ item }">
            <VChip
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.type }}
            </VChip>
          </template>

          <!-- Ownership Column -->
          <template #item.ownership_type="{ item }">
            <span class="text-capitalize">{{ item.ownership_type }}</span>
          </template>

          <!-- Assignment Column -->
          <template #item.assignment="{ item }">
            <div
              v-if="item.current_assignment || item.current_responsible_employee_id"
              class="text-body-2"
            >
              <div class="font-weight-medium">
                {{ item.current_assignment?.responsible_employee?.name || item.current_site?.name || $t('fleet.assigned') }}
              </div>
              <div class="text-caption text-medium-emphasis text-capitalize">
                {{ item.custody_type?.replaceAll('_', ' ') }}
              </div>
            </div>
            <span
              v-else
              class="text-disabled"
            >
              —
            </span>
          </template>

          <!-- Odometer Column -->
          <template #item.odometer_km="{ item }">
            {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
          </template>

          <!-- Status Column -->
          <template #item.status="{ item }">
            <WpStatusChip
              type="vehicle"
              :status="item.status"
              :label="statusLabel(item.status)"
            />
          </template>

          <!-- Actions Column -->
          <template #item.actions="{ item }">
            <WpRowActions
              :actions="rowActions(item)"
              @action="act => onRowAction(act, item)"
            />
          </template>

          <!-- Mobile Card Fallback -->
          <template #mobile-card="{ item }">
            <WpMobileCard
              :title="item.plate_number"
              :subtitle="`${item.label} · ${item.type}`"
              @click="openVehicleView(item)"
            >
              <template #avatar>
                <VAvatar
                  color="primary"
                  variant="tonal"
                >
                  <VIcon icon="tabler-car" />
                </VAvatar>
              </template>

              <template #actions>
                <div
                  class="d-flex align-center gap-1"
                  @click.stop
                >
                  <WpStatusChip
                    type="vehicle"
                    :status="item.status"
                    :label="statusLabel(item.status)"
                    size="x-small"
                  />
                  <WpRowActions
                    :actions="rowActions(item)"
                    @action="act => onRowAction(act, item)"
                  />
                </div>
              </template>

              <template #content>
                <div class="text-caption mt-2">
                  {{ item.current_assignment ? `${$t('fleet.assigned')}: ${item.current_assignment.responsible_employee?.name || item.custody_type}` : $t('fleet.unassigned') }}
                  · {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
                </div>
              </template>
            </WpMobileCard>
          </template>
        </WpResponsiveList>
      </template>

      <!-- VIEW 2: CARD / GRID VIEW -->
      <template v-else>
        <div class="pa-6">
          <VProgressLinear
            v-if="page.loading.value"
            indeterminate
            color="primary"
            class="mb-4"
          />

          <VRow v-if="page.rows.value.length">
            <VCol
              v-for="item in page.rows.value"
              :key="item.id"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <VCard
                variant="outlined"
                class="h-100 d-flex flex-column justify-space-between transition-swing hover-elevation"
              >
                <!-- Card Header -->
                <VCardItem class="pb-2">
                  <template #prepend>
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      class="me-2"
                    >
                      <VIcon icon="tabler-car" />
                    </VAvatar>
                  </template>
                  <VCardTitle
                    class="text-base font-weight-bold cursor-pointer"
                    @click="openVehicleView(item)"
                  >
                    {{ item.plate_number }}
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    {{ item.label }}
                  </VCardSubtitle>
                  <template #append>
                    <WpRowActions
                      :actions="rowActions(item)"
                      @action="act => onRowAction(act, item)"
                    />
                  </template>
                </VCardItem>

                <!-- Card Body -->
                <VCardText class="py-2">
                  <div class="d-flex align-center gap-2 mb-3">
                    <WpStatusChip
                      type="vehicle"
                      :status="item.status"
                      :label="statusLabel(item.status)"
                      size="x-small"
                    />
                    <VChip
                      size="x-small"
                      variant="tonal"
                      class="text-capitalize"
                    >
                      {{ item.type }}
                    </VChip>
                    <VChip
                      size="x-small"
                      variant="outlined"
                      class="text-capitalize"
                    >
                      {{ item.ownership_type }}
                    </VChip>
                  </div>

                  <div class="d-flex flex-column gap-1 text-caption">
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ $t('fleet.mileage') }}:</span>
                      <span class="font-weight-medium">
                        {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
                      </span>
                    </div>

                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ $t('fleet.custody') }}:</span>
                      <span
                        class="text-truncate"
                        style="max-inline-size: 150px;"
                      >
                        {{ item.current_assignment?.responsible_employee?.name || item.current_site?.name || $t('fleet.storage') }}
                      </span>
                    </div>

                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ $t('fleet.next_service') }}:</span>
                      <span>{{ workflow.formatDate(item.next_service_date) }}</span>
                    </div>
                  </div>
                </VCardText>

                <!-- Card Footer Action -->
                <VCardActions class="border-t pt-2 px-4 pb-3">
                  <VBtn
                    variant="tonal"
                    size="small"
                    block
                    prepend-icon="tabler-eye"
                    @click="openVehicleView(item)"
                  >
                    {{ $t('fleet.view_details') || 'View 360' }}
                  </VBtn>
                </VCardActions>
              </VCard>
            </VCol>
          </VRow>

          <!-- Empty Grid State -->
          <WpEmptyState
            v-else-if="!page.loading.value"
            icon="tabler-car-off"
            :title="$t('fleet.no_vehicles')"
            :subtitle="$t('fleet.no_vehicles_hint')"
          />

          <!-- Grid Pagination Controls -->
          <div
            v-if="page.totalItems.value > page.itemsPerPage.value"
            class="d-flex justify-center mt-6"
          >
            <VPagination
              v-model="page.page.value"
              :length="Math.ceil(page.totalItems.value / page.itemsPerPage.value)"
              total-visible="5"
            />
          </div>
        </div>
      </template>
    </VCard>

    <!-- Filters Dialog -->
    <WpFilterDialog
      v-model="filterOpen"
      :active-count="page.activeFilterCount.value"
      :title="$t('Filters')"
      @apply="() => { page.applyStaged(); filterOpen = false }"
      @reset="() => { page.resetStaged(); page.applyStaged(); filterOpen = false }"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="page.stagedFilters.status"
            :label="$t('fleet.status') || 'Status'"
            :items="workflow.statusOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="page.stagedFilters.type"
            :label="$t('fleet.type') || 'Vehicle Type'"
            :items="workflow.typeOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="page.stagedFilters.ownership_type"
            :label="$t('fleet.ownership') || 'Ownership Type'"
            :items="workflow.ownershipOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- All Vehicle Operation Dialogs -->
    <VehicleWizardDialog
      v-model="isFormOpen"
      :form="workflow.form"
      :loading="isSubmitting"
      :status-options="workflow.statusOptions"
      :type-options="workflow.typeOptions"
      :ownership-options="workflow.ownershipOptions"
      :fuel-type-options="workflow.fuelTypeOptions"
      :user-options="userOptions"
      @save="workflow.submitForm"
    />

    <VehicleAssignDialog
      v-model="isAssignOpen"
      :assignment="workflow.assignment"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :custody-options="workflow.custodyOptions"
      :user-options="userOptions"
      :site-options="siteOptions"
      @save="workflow.submitAssign"
    />

    <VehicleReturnDialog
      v-model="isReturnOpen"
      :return-data="workflow.returnData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :return-status-options="workflow.returnStatusOptions"
      @save="workflow.submitReturn"
    />

    <VehicleStatusDialog
      v-model="isStatusOpen"
      :status-data="workflow.statusData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :status-options="workflow.statusOptions"
      @save="workflow.submitStatus"
    />

    <VehicleOdometerDialog
      v-model="isOdometerOpen"
      :odometer-data="workflow.odometerData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      @save="workflow.submitOdometer"
    />

    <VehicleRentalDialog
      v-model="isRentalOpen"
      :rental-data="workflow.rentalData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :provider-options="providerOptions"
      @save="workflow.submitRental"
    />

    <VehicleClientUsageDialog
      v-model="isClientUsageOpen"
      :client-usage-data="workflow.clientUsageData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :client-options="clientOptions"
      @save="workflow.submitClientUsage"
    />

    <VehicleTelemetryDialog
      v-model="isTelemetryOpen"
      :telemetry-data="workflow.telemetryData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      @save="workflow.submitTelemetry"
    />

    <WpDeleteDialog
      v-model="isDeleteOpen"
      :title="$t('fleet.delete_title')"
      :description="$t('fleet.delete_confirm', { plate: targetVehicle?.plate_number })"
      :loading="isSubmitting"
      :confirm-action="workflow.submitDelete"
    />

    <VehicleDetailDialog
      v-model="isViewOpen"
      :vehicle="selectedVehicle"
      :loading="isViewLoading"
      :error="viewError"
      :can-edit="ability.can('update', 'Fleet')"
      :status-options="workflow.statusOptions"
      :type-options="workflow.typeOptions"
      :ownership-options="workflow.ownershipOptions"
      :fuel-type-options="workflow.fuelTypeOptions"
      :user-options="userOptions"
      @edit="vehicle => { isViewOpen = false; navigateToEdit(vehicle.id) }"
      @retry="selectedVehicle && openVehicleView(selectedVehicle)"
    />
  </div>
</template>

<style scoped>
.hover-elevation:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  box-shadow: 0 4px 18px rgba(0, 0, 0, 8%);
}

.text-decoration-underline-hover:hover {
  text-decoration: underline;
}
</style>
