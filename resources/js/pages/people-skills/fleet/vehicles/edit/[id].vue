<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppBreadcrumb,
  AppFabSpeedDial,
  AppHybridTabs,
  WpDeleteDialog,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useVehicleWorkflow } from '@/composables/fleet/useVehicleWorkflow'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useHrStore } from '@/stores/hrStore'
import { useClientStore } from '@/stores/useClientStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useVehicleStore } from '@/stores/useVehicleStore'
import VehicleAssignDialog from '@/views/people-skills/fleet/vehicles/VehicleAssignDialog.vue'
import VehicleClientUsageDialog from '@/views/people-skills/fleet/vehicles/VehicleClientUsageDialog.vue'
import VehicleFormFields from '@/views/people-skills/fleet/vehicles/VehicleFormFields.vue'
import VehicleOdometerDialog from '@/views/people-skills/fleet/vehicles/VehicleOdometerDialog.vue'
import VehicleRentalDialog from '@/views/people-skills/fleet/vehicles/VehicleRentalDialog.vue'
import VehicleReturnDialog from '@/views/people-skills/fleet/vehicles/VehicleReturnDialog.vue'
import VehicleStatusDialog from '@/views/people-skills/fleet/vehicles/VehicleStatusDialog.vue'
import VehicleTelemetryDialog from '@/views/people-skills/fleet/vehicles/VehicleTelemetryDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/fleet/vehicles/edit/:id',
  name: 'people-skills-fleet-vehicles-edit',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'update',
    subject: 'Fleet',
  },
})

const route = useRoute('people-skills-fleet-vehicles-edit')
const router = useRouter()
const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const ability = useAbility()
const { smAndDown } = useDisplay()

const store = useVehicleStore()
const hrStore = useHrStore()
const providerStore = useServiceProviderStore()
const clientStore = useClientStore()
const inventoryStore = useInventoryStore()

// ── State ───────────────────────────────────────────────────────────────────
const vehicleId = computed(() => route.params.id)
const vehicle = computed(() => store.currentItem)
const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const baseline = ref('')
const activeTab = ref('identity')

const serviceProviders = ref([])
const clients = ref([])
const sites = ref([])

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('fleet.vehicles_title', 'Fleet Vehicles'), to: { name: 'people-skills-vehicles-list' }, icon: 'tabler-car' },
  {
    title: vehicle.value?.plate_number || t('Edit Vehicle'),
    icon: 'tabler-edit',
  },
])

// ── Tabs Navigation with AppHybridTabs ──────────────────────────────────────
const tabs = computed(() => [
  { value: 'identity', title: t('fleet.identity', 'Identity'), icon: 'tabler-id' },
  { value: 'classification', title: t('fleet.classification', 'Classification'), icon: 'tabler-category' },
  { value: 'technical', title: t('fleet.technical', 'Technical'), icon: 'tabler-engine' },
  { value: 'compliance', title: t('fleet.compliance', 'Compliance'), icon: 'tabler-shield-check' },
  { value: 'operational', title: t('fleet.operational_section', 'Operational'), icon: 'tabler-dashboard' },
  { value: 'telemetry', title: t('fleet.telemetry', 'Tracker & GPS'), icon: 'tabler-gps' },
  { value: 'notes', title: t('fleet.notes', 'Notes'), icon: 'tabler-notes' },
])

const mobileNavItems = computed(() => tabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  key: tab.value,
  value: tab.value,
})))

const loadVehicle = async () => {
  loading.value = true
  error.value = null

  try {
    const data = await store.fetchById(vehicleId.value)

    if (!data) {
      error.value = t('fleet.vehicle_not_found', 'Vehicle details could not be loaded.')

      return
    }

    workflow.setForm(data)
    baseline.value = JSON.stringify(workflow.form)
  } catch (loadError) {
    error.value = loadError?.data?.message || loadError?.message || t('fleet.vehicle_load_error', 'Failed to load vehicle details.')
  } finally {
    loading.value = false
  }
}

const workflow = useVehicleWorkflow(loadVehicle)

const {
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
  assignment,
  returnData,
  statusData,
  odometerData,
  rentalData,
  clientUsageData,
  telemetryData,
} = workflow

const isDirty = computed(() => Boolean(baseline.value) && JSON.stringify(workflow.form) !== baseline.value)
const canUpdate = computed(() => ability.can('update', 'Fleet'))
const canDelete = computed(() => ability.can('delete', 'Fleet'))
const hasCurrentAssignment = computed(() => Boolean(vehicle.value?.current_assignment || vehicle.value?.current_responsible_employee_id))
const hasActiveRental = computed(() => vehicle.value?.ownership_type === 'rental' || vehicle.value?.status === 'rental_active')
const hasActiveClientUsage = computed(() => vehicle.value?.ownership_type === 'client' || vehicle.value?.status === 'client_use')

// ── Status Helper ───────────────────────────────────────────────────────────
const getStatusColor = status => {
  const statusColors = {
    available: 'success',
    in_use: 'primary',
    maintenance: 'warning',
    out_of_service: 'error',
    reserved: 'info',
    rental_active: 'secondary',
    client_use: 'info',
    retired: 'disabled',
  }

  return statusColors[status] || 'default'
}

const getStatusIcon = status => {
  const statusIcons = {
    available: 'tabler-circle-check',
    in_use: 'tabler-circle-dot',
    maintenance: 'tabler-tools',
    out_of_service: 'tabler-circle-x',
    reserved: 'tabler-clock-pause',
    rental_active: 'tabler-car',
    client_use: 'tabler-building',
    retired: 'tabler-archive',
  }

  return statusIcons[status] || 'tabler-circle'
}

const breadcrumbs = computed(() => [
  { title: t('Home'), to: '/' },
  { title: t('fleet.vehicles_title', 'Fleet Vehicles'), to: '/people-skills/fleet/vehicles' },
  { title: vehicle.value?.plate_number || t('Edit'), icon: 'tabler-car' },
])

const userOptions = computed(() => (hrStore.availableUsers || []).map(user => ({
  title: user.name || user.full_name || user.email,
  value: user.id,
})))

const providerOptions = computed(() => serviceProviders.value.map(provider => ({
  title: provider.name || provider.company_name,
  value: provider.id,
})))

const clientOptions = computed(() => clients.value.map(client => ({
  title: client.name || client.company_name,
  value: client.id,
})))

const siteOptions = computed(() => sites.value.map(site => {
  const name = site.name?.en || site.name?.fr || (typeof site.name === 'string' ? site.name : '') || ''

  return {
    title: name ? `${site.code} — ${name}` : site.code,
    value: site.id,
  }
}))

// ── Load Options ────────────────────────────────────────────────────────────

const loadOptions = async () => {
  const results = await Promise.allSettled([
    hrStore.fetchMeta(),
    providerStore.fetchServiceProviderOptions(),
    clientStore.fetchClientOptions(),
    inventoryStore.fetchSites({ per_page: 100 }),
  ])

  const [metaResult, providerResult, clientResult, siteResult] = results
  const firstFailure = results.find(result => result.status === 'rejected')

  if (providerResult.status === 'fulfilled')
    serviceProviders.value = providerResult.value?.data || []
  if (clientResult.status === 'fulfilled')
    clients.value = clientResult.value?.data || []
  if (siteResult.status === 'fulfilled' && siteResult.value)
    sites.value = siteResult.value.data || []

  if (
    firstFailure
    || metaResult.status === 'rejected'
    || !hrStore.metaLoaded
    || !siteResult.value
    || inventoryStore.error
  ) {
    showMessage(firstFailure?.reason?.data?.message || t('fleet.options_load_error'), 'error')
  }
}

// ── Save Handler ────────────────────────────────────────────────────────────

const handleSave = async () => {
  const requiredFields = ['plate_number', 'label', 'type', 'ownership_type']
  const firstMissingField = requiredFields.find(field => !workflow.form[field])

  if (firstMissingField) {
    activeTab.value = ['type', 'ownership_type'].includes(firstMissingField) ? 'classification' : 'identity'
    showMessage(t('fleet.required_fields', 'Please complete all required vehicle fields.'), 'error')

    return
  }

  saving.value = true

  try {
    const payload = Object.fromEntries(
      Object.entries(workflow.form)
        .filter(([key]) => !['id', 'odometer_last_updated'].includes(key)),
    )

    const updatedVehicle = await store.update(vehicleId.value, payload)

    if (!updatedVehicle)
      throw new Error(t('fleet.vehicle_save_error', 'Failed to save vehicle.'))

    showMessage(t('fleet.vehicle_saved', 'Vehicle saved successfully'), 'success')
    await loadVehicle()
  } catch (saveError) {
    const validationMessage = Object.values(store.validationErrors || {}).flat().join(' ')

    showMessage(validationMessage || saveError?.data?.message || saveError?.message || t('fleet.vehicle_save_error', 'Failed to save vehicle.'), 'error')
  } finally {
    saving.value = false
  }
}

// ── Discard Changes ─────────────────────────────────────────────────────────

const discardChanges = () => {
  if (!vehicle.value)
    return

  workflow.setForm(vehicle.value)
  baseline.value = JSON.stringify(workflow.form)
}

// ── Delete Handler ──────────────────────────────────────────────────────────
const handleDelete = async () => {
  const deleted = await workflow.submitDelete()

  if (deleted)
    router.push({ name: 'people-skills-vehicles-list' })
}

// ── Operation Helpers ───────────────────────────────────────────────────────
const openOperation = opener => {
  if (!isDirty.value && vehicle.value && typeof opener === 'function')
    opener(vehicle.value)
}

// ── Mobile FAB Speed Dial Actions ───────────────────────────────────────────
const isMobileFabOpen = ref(false)

const dynamicFabActions = computed(() => {
  const actions = [
    {
      id: 'save-vehicle',
      title: t('Save Changes'),
      icon: 'tabler-device-floppy',
      color: 'primary',
      onClick: handleSave,
      disabled: !isDirty.value,
    },
  ]

  if (canUpdate.value && vehicle.value) {
    actions.push({
      id: 'assign-return',
      title: hasCurrentAssignment.value ? t('fleet.return_vehicle', 'Return Vehicle') : t('fleet.assign_vehicle', 'Assign Vehicle'),
      icon: hasCurrentAssignment.value ? 'tabler-arrow-back-up' : 'tabler-user-plus',
      color: 'info',
      onClick: () => openOperation(v => hasCurrentAssignment.value ? workflow.openReturn(v) : workflow.openAssign(v)),
      disabled: isDirty.value || saving.value,
    })

    actions.push({
      id: 'update-odometer',
      title: t('fleet.update_odometer', 'Update Odometer'),
      icon: 'tabler-dashboard',
      color: 'warning',
      onClick: () => openOperation(workflow.openOdometer),
      disabled: isDirty.value || saving.value,
    })
  }

  return actions
})

// Unsaved changes guard
const { isDialogVisible: isUnsavedDialogVisible, confirmLeave, cancelLeave } = useUnsavedChangesGuard(isDirty)

// ── Lifecycle ───────────────────────────────────────────────────────────────

onMounted(() => {
  Promise.all([loadVehicle(), loadOptions()])
})
</script>

<template>
  <div class="vehicle-edit-page pb-16">
    <!-- 👉 Skeleton Loading state -->
    <div
      v-if="loading"
      class="d-flex flex-column gap-6"
    >
      <!-- Breadcrumb Skeleton -->
      <div class="d-flex align-center gap-2">
        <VSkeletonLoader
          type="text"
          width="260"
        />
      </div>

      <!-- Identity Header Card Skeleton -->
      <VCard class="vehicle-identity-header pa-3 pa-sm-4 border">
        <div class="d-flex align-center justify-space-between flex-wrap gap-3">
          <div class="d-flex align-center gap-3">
            <VSkeletonLoader
              type="avatar"
              size="40"
            />
            <div class="d-flex flex-column gap-2">
              <div class="d-flex align-center gap-2">
                <VSkeletonLoader
                  type="text"
                  width="180"
                />
                <VSkeletonLoader
                  type="chip"
                  width="70"
                />
              </div>
              <VSkeletonLoader
                type="text"
                width="240"
              />
            </div>
          </div>
          <div class="d-flex align-center gap-2">
            <VSkeletonLoader
              type="button"
              width="90"
            />
            <VSkeletonLoader
              type="button"
              width="130"
            />
          </div>
        </div>
      </VCard>

      <!-- Tabs Island Skeleton -->
      <VCard class="pa-2 border rounded-lg">
        <div class="d-flex align-center gap-2 overflow-x-auto">
          <VSkeletonLoader
            v-for="i in 5"
            :key="i"
            type="button"
            width="130"
            height="38"
          />
        </div>
      </VCard>

      <!-- Tab Content Card Skeleton -->
      <VCard class="border rounded-lg pa-4 pa-sm-6">
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <VSkeletonLoader type="text@2" />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VSkeletonLoader type="text@2" />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VSkeletonLoader type="text@2" />
          </VCol>
          <VCol cols="12">
            <VSkeletonLoader type="paragraph" />
          </VCol>
        </VRow>
      </VCard>
    </div>

    <div
      v-else
      class="d-flex flex-column gap-5"
    >
      <!-- ━━━ 1. Breadcrumbs ━━━ -->
      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- ━━━ 2. Premium Identity Header ━━━ -->
      <VCard class="vehicle-identity-header border overflow-hidden">
        <!-- Accent gradient stripe -->
        <div class="vehicle-header-accent" />

        <div class="pa-3 pa-sm-5">
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <!-- Identity -->
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="primary"
                variant="flat"
                :size="smAndDown ? 40 : 48"
                rounded="lg"
                class="vehicle-icon-avatar flex-shrink-0"
              >
                <VIcon
                  icon="tabler-car"
                  :size="smAndDown ? 22 : 26"
                />
              </VAvatar>

              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap mb-1">
                  <h4 class="text-subtitle-1 text-sm-h5 font-weight-black mb-0">
                    {{ vehicle?.plate_number || t('fleet.edit_vehicle', 'Edit Vehicle') }}
                  </h4>
                  <!-- Clickable status chip -->
                  <VChip
                    v-if="vehicle?.status"
                    :color="getStatusColor(vehicle.status)"
                    size="small"
                    label
                    class="cursor-pointer vehicle-status-chip"
                    @click="vehicle && !isDirty ? workflow.openStatus(vehicle) : null"
                  >
                    <VIcon
                      :icon="getStatusIcon(vehicle.status)"
                      size="13"
                      class="me-1"
                    />
                    {{ t(`fleet.status_${vehicle.status}`, vehicle.status) }}
                  </VChip>
                  <VChip
                    v-if="vehicle?.fleet_code"
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-id"
                  >
                    {{ vehicle.fleet_code }}
                  </VChip>
                </div>
                <span class="text-body-2 text-medium-emphasis">
                  {{ [vehicle?.label, vehicle?.make, vehicle?.model, vehicle?.year].filter(Boolean).join(' · ') || t('Vehicle Details') }}
                </span>
                <!-- Quick inline specs badges -->
                <div
                  v-if="vehicle?.vin || vehicle?.registration_number"
                  class="d-flex align-center gap-3 mt-1 flex-wrap"
                >
                  <span
                    v-if="vehicle.vin"
                    class="text-caption text-disabled d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-qrcode"
                      size="13"
                    />{{ t('fleet.vin', 'VIN') }}: {{ vehicle.vin }}
                  </span>
                  <span
                    v-if="vehicle.registration_number"
                    class="text-caption text-disabled d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-file-certificate"
                      size="13"
                    />{{ t('fleet.registration_number', 'Reg') }}: {{ vehicle.registration_number }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Header Actions (desktop only; mobile uses FAB speed dial) -->
            <div class="d-none d-md-flex align-center gap-2 flex-wrap flex-shrink-0">
              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-arrow-left"
                :to="{ name: 'people-skills-vehicles-list' }"
              >
                {{ t('Back') }}
              </VBtn>

              <VMenu v-if="canUpdate || canDelete">
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    variant="tonal"
                    icon="tabler-dots-vertical"
                    :disabled="!vehicle"
                    :aria-label="t('wp.actions.more', 'More actions')"
                  />
                </template>
                <VList density="compact">
                  <VListItem
                    v-if="canUpdate"
                    prepend-icon="tabler-refresh"
                    :title="t('fleet.change_status', 'Change Status')"
                    :disabled="isDirty || saving"
                    @click="openOperation(workflow.openStatus)"
                  />
                  <VListItem
                    v-if="canUpdate"
                    prepend-icon="tabler-map-pin"
                    :title="t('fleet.update_telemetry', 'Update GPS Telemetry')"
                    :disabled="isDirty || saving"
                    @click="openOperation(workflow.openTelemetry)"
                  />
                  <VListItem
                    v-if="canUpdate"
                    prepend-icon="tabler-arrow-back-up"
                    :title="t('fleet.return_vehicle', 'Return Vehicle')"
                    :disabled="isDirty || saving"
                    @click="openOperation(workflow.openReturn)"
                  />
                  <VListItem
                    v-if="canUpdate"
                    :prepend-icon="hasActiveRental ? 'tabler-car-off' : 'tabler-car'"
                    :title="hasActiveRental ? t('fleet.end_rental', 'End Rental') : t('fleet.start_rental', 'Start Rental')"
                    :disabled="isDirty || saving"
                    @click="openOperation(v => workflow.openRental(v, hasActiveRental))"
                  />
                  <VListItem
                    v-if="canUpdate"
                    :prepend-icon="hasActiveClientUsage ? 'tabler-building-bank' : 'tabler-building-estate'"
                    :title="hasActiveClientUsage ? t('fleet.end_client_usage', 'End Client Usage') : t('fleet.start_client_usage', 'Start Client Usage')"
                    :disabled="isDirty || saving"
                    @click="openOperation(v => workflow.openClientUsage(v, hasActiveClientUsage))"
                  />
                  <VDivider
                    v-if="canDelete"
                    class="my-1"
                  />
                  <VListItem
                    v-if="canDelete"
                    prepend-icon="tabler-trash"
                    :title="t('Delete')"
                    class="text-error"
                    :disabled="saving || isDirty"
                    @click="workflow.openDelete(vehicle)"
                  />
                </VList>
              </VMenu>

              <VBtn
                v-if="canUpdate"
                color="primary"
                variant="flat"
                prepend-icon="tabler-device-floppy"
                :loading="saving"
                :disabled="!isDirty"
                class="vehicle-save-btn"
                @click="handleSave"
              >
                {{ t('Save Changes') }}
              </VBtn>
            </div>
          </div>
        </div>
      </VCard>

      <!-- ━━━ 3. Tabs Content with AppHybridTabs ━━━ -->
      <AppHybridTabs
        v-model="activeTab"
        :tabs="tabs"
        header-class="d-none d-md-flex"
        class="vehicle-tabs"
      >
        <!-- ── Dynamic Tab Sections ── -->
        <template
          v-for="tab in tabs"
          #[`tab-${tab.value}`]
          :key="tab.value"
        >
          <VCardText class="pa-4 pa-sm-6">
            <VehicleFormFields
              :form="workflow.form"
              :section="tab.value"
              :readonly="!canUpdate"
              :status-options="workflow.statusOptions"
              :type-options="workflow.typeOptions"
              :ownership-options="workflow.ownershipOptions"
              :fuel-type-options="workflow.fuelTypeOptions"
              :user-options="userOptions"
            />
          </VCardText>
        </template>
      </AppHybridTabs>
    </div>

    <!-- ═══ Mobile Bottom Navigation ═══ -->
    <AppBottomNav
      v-if="smAndDown && !loading"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      @change="item => { activeTab = item.value }"
    />

    <!-- ═══ Mobile Floating Action Button ═══ -->
    <AppFabSpeedDial
      v-if="smAndDown && !loading"
      v-model="isMobileFabOpen"
      :actions="dynamicFabActions"
    />

    <!-- ═══ Unsaved Changes Dialog ═══ -->
    <WpUnsavedChangesDialog
      v-model="isUnsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />

    <!-- ═══ Operation Dialogs ═══ -->
    <VehicleAssignDialog
      v-model="isAssignOpen"
      :assignment="assignment"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :custody-options="workflow.custodyOptions"
      :user-options="userOptions"
      :site-options="siteOptions"
      @save="workflow.submitAssign"
    />

    <VehicleReturnDialog
      v-model="isReturnOpen"
      :return-data="returnData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :return-status-options="workflow.returnStatusOptions"
      @save="workflow.submitReturn"
    />

    <VehicleStatusDialog
      v-model="isStatusOpen"
      :status-data="statusData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :status-options="workflow.statusOptions"
      @save="workflow.submitStatus"
    />

    <VehicleOdometerDialog
      v-model="isOdometerOpen"
      :odometer-data="odometerData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      @save="workflow.submitOdometer"
    />

    <VehicleRentalDialog
      v-model="isRentalOpen"
      :rental-data="rentalData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :provider-options="providerOptions"
      @save="workflow.submitRental"
    />

    <VehicleClientUsageDialog
      v-model="isClientUsageOpen"
      :client-usage-data="clientUsageData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      :client-options="clientOptions"
      @save="workflow.submitClientUsage"
    />

    <VehicleTelemetryDialog
      v-model="isTelemetryOpen"
      :telemetry-data="telemetryData"
      :vehicle="targetVehicle"
      :loading="isSubmitting"
      @save="workflow.submitTelemetry"
    />

    <WpDeleteDialog
      v-model="isDeleteOpen"
      :title="t('fleet.delete_title')"
      :description="t('fleet.delete_confirm', { plate: targetVehicle?.plate_number })"
      :loading="isSubmitting"
      :confirm-action="handleDelete"
    />
  </div>
</template>

<style scoped lang="scss">
.vehicle-edit-page {
  :deep(.vehicle-identity-header) {
    position: relative;
  }

  .vehicle-header-accent {
    block-size: 6px;
    background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
  }

  .vehicle-icon-avatar {
    box-shadow: 0 2px 8px rgb(var(--v-theme-primary) / 15%);
  }

  .vehicle-status-chip {
    transition: all 0.2s ease-in-out;

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgb(0 0 0 / 10%);
    }
  }

  .vehicle-save-btn {
    box-shadow: 0 2px 10px rgb(var(--v-theme-primary) / 30%);
  }
}
</style>
