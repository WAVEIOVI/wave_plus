<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { WpLoadingState, WpErrorBanner, WpPageHeader } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useHrStore } from '@/stores/hrStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useClientStore } from '@/stores/useClientStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useVehicleWorkflow } from '@/composables/fleet/useVehicleWorkflow'
import VehicleDetail360 from '@/views/people-skills/fleet/vehicles/VehicleDetail360.vue'
import VehicleFormDialog from '@/views/people-skills/fleet/vehicles/VehicleFormDialog.vue'
import VehicleAssignDialog from '@/views/people-skills/fleet/vehicles/VehicleAssignDialog.vue'
import VehicleReturnDialog from '@/views/people-skills/fleet/vehicles/VehicleReturnDialog.vue'
import VehicleStatusDialog from '@/views/people-skills/fleet/vehicles/VehicleStatusDialog.vue'
import VehicleOdometerDialog from '@/views/people-skills/fleet/vehicles/VehicleOdometerDialog.vue'
import VehicleRentalDialog from '@/views/people-skills/fleet/vehicles/VehicleRentalDialog.vue'
import VehicleClientUsageDialog from '@/views/people-skills/fleet/vehicles/VehicleClientUsageDialog.vue'

definePage({
  path: '/people-skills/fleet/vehicles/:id',
  name: 'people-skills-fleet-vehicles-detail',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'Fleet',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { showMessage } = useAppSnackbar()

const store = useVehicleStore()
const hrStore = useHrStore()
const providerStore = useServiceProviderStore()
const clientStore = useClientStore()
const inventoryStore = useInventoryStore()

const vehicleId = computed(() => route.params.id)
const vehicle = computed(() => store.currentItem)
const loading = ref(true)
const error = ref(null)

const serviceProviders = ref([])
const clients = ref([])
const sites = ref([])

const loadVehicle = async () => {
  loading.value = true
  error.value = null
  try {
    await store.fetchById(vehicleId.value)
  } catch (err) {
    error.value = err?.data?.message || err?.message || 'Failed to load vehicle details'
  } finally {
    loading.value = false
  }
}

const workflow = useVehicleWorkflow(async () => {
  await loadVehicle()
})

const userOptions = computed(() => {
  return (hrStore.availableUsers || []).map(u => ({
    title: u.name || u.full_name || u.email,
    value: u.id,
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

const siteOptions = computed(() => sites.value.map(site => {
  const name = site.name?.en || site.name?.fr || (typeof site.name === 'string' ? site.name : '') || ''

  return {
    title: name ? `${site.code} — ${name}` : site.code,
    value: site.id,
  }
}))

const loadOptions = async () => {
  const [metaResult, providerResult, clientResult, siteResult] = await Promise.allSettled([
    hrStore.fetchMeta(),
    providerStore.fetchServiceProviderOptions(),
    clientStore.fetchClientOptions(),
    inventoryStore.fetchSites({ per_page: 100 }),
  ])

  const firstFailure = [metaResult, providerResult, clientResult, siteResult]
    .find(result => result.status === 'rejected')

  if (providerResult.status === 'fulfilled')
    serviceProviders.value = providerResult.value?.data || []
  if (clientResult.status === 'fulfilled')
    clients.value = clientResult.value?.data || []
  if (siteResult.status === 'fulfilled')
    sites.value = siteResult.value?.data || []

  if (
    firstFailure
    || !hrStore.metaLoaded
    || !siteResult.value
    || inventoryStore.error
  ) {
    const reason = firstFailure?.reason

    showMessage(reason?.data?.message || t('fleet.options_load_error'), 'error')
  }
}

onMounted(async () => {
  await loadVehicle()
  await loadOptions()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="$t('Home')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem :title="$t('fleet.vehicles_title') || 'Fleet Vehicles'" />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'people-skills-fleet-vehicles-list' }"
        :title="$t('fleet.vehicles_title') || 'Fleet Vehicles'"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="vehicle?.plate_number || vehicle?.label || $t('fleet.view_title')"
        disabled
      />
    </VBreadcrumbs>

    <!-- Page Header -->
    <WpPageHeader
      v-if="vehicle"
      :title="vehicle.plate_number"
      :subtitle="vehicle.label"
      icon="tabler-car"
    />

    <!-- Loading State -->
    <WpLoadingState
      v-if="loading && !vehicle"
      :text="$t('wp.loading')"
      min-height="300px"
    />

    <!-- Error State -->
    <WpErrorBanner
      v-else-if="error"
      :error="error"
      @retry="loadVehicle"
    />

    <!-- Content: Vehicle Resource 360 -->
    <template v-else-if="vehicle">
      <VehicleDetail360
        :vehicle="vehicle"
        :loading="loading"
        :user-options="userOptions"
        @edit="workflow.openEdit"
        @assign="workflow.openAssign"
        @return="workflow.openReturn"
        @status="workflow.openStatus"
        @odometer="workflow.openOdometer"
        @rental="workflow.openRental"
        @client-usage="workflow.openClientUsage"
        @delete="workflow.openDelete"
        @refresh="loadVehicle"
      />

      <!-- All Modals / Dialogs -->
      <VehicleFormDialog
        v-model="workflow.isFormOpen.value"
        :form="workflow.form"
        :loading="workflow.isSubmitting.value"
        :status-options="workflow.statusOptions"
        :type-options="workflow.typeOptions"
        :ownership-options="workflow.ownershipOptions"
        :fuel-type-options="workflow.fuelTypeOptions"
        @save="workflow.submitForm"
      />

      <VehicleAssignDialog
        v-model="workflow.isAssignOpen.value"
        :assignment="workflow.assignment"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        :custody-options="workflow.custodyOptions"
        :user-options="userOptions"
        :site-options="siteOptions"
        @save="workflow.submitAssign"
      />

      <VehicleReturnDialog
        v-model="workflow.isReturnOpen.value"
        :return-data="workflow.returnData"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        :return-status-options="workflow.returnStatusOptions"
        @save="workflow.submitReturn"
      />

      <VehicleStatusDialog
        v-model="workflow.isStatusOpen.value"
        :status-data="workflow.statusData"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        :status-options="workflow.statusOptions"
        @save="workflow.submitStatus"
      />

      <VehicleOdometerDialog
        v-model="workflow.isOdometerOpen.value"
        :odometer-data="workflow.odometerData"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        @save="workflow.submitOdometer"
      />

      <VehicleRentalDialog
        v-model="workflow.isRentalOpen.value"
        :rental-data="workflow.rentalData"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        :provider-options="providerOptions"
        @save="workflow.submitRental"
      />

      <VehicleClientUsageDialog
        v-model="workflow.isClientUsageOpen.value"
        :client-usage-data="workflow.clientUsageData"
        :vehicle="workflow.targetVehicle.value"
        :loading="workflow.isSubmitting.value"
        :client-options="clientOptions"
        @save="workflow.submitClientUsage"
      />

      <VDialog
        v-model="workflow.isDeleteOpen.value"
        max-width="440"
      >
        <VCard :title="$t('fleet.delete_title')">
          <VCardText>
            {{ $t('fleet.delete_confirm', { plate: workflow.targetVehicle.value?.plate_number }) }}
          </VCardText>
          <VCardActions class="justify-end pa-4 gap-2">
            <VBtn
              variant="tonal"
              :disabled="workflow.isSubmitting.value"
              @click="workflow.isDeleteOpen.value = false"
            >
              {{ $t('Cancel') }}
            </VBtn>
            <VBtn
              color="error"
              :loading="workflow.isSubmitting.value"
              @click="workflow.submitDelete"
            >
              {{ $t('Delete') }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </div>
</template>
