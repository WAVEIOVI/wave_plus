<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  fleet: {
    type: Object,
    default: () => ({
      total_vehicles: 0,
      available: 0,
      in_use: 0,
      maintenance: 0,
      rental_active: 0,
      client_use: 0,
      approved_drivers: 0,
      service_due_count: 0,
      driver_renewals_due: 0,
      total_alerts: 0,
    }),
  },
  loading: { type: Boolean, default: false },
})

const { t } = useI18n()
const router = useRouter()

const statusSegments = computed(() => [
  { key: 'available', label: t('fleet.status_available', 'Available'), value: props.fleet.available || 0, color: 'success' },
  { key: 'in_use', label: t('fleet.status_in_use', 'In Use'), value: props.fleet.in_use || 0, color: 'info' },
  { key: 'maintenance', label: t('fleet.status_maintenance', 'Maintenance'), value: props.fleet.maintenance || 0, color: 'warning' },
  { key: 'rental_active', label: t('fleet.status_rental_active', 'Rental'), value: props.fleet.rental_active || 0, color: 'primary' },
  { key: 'client_use', label: t('fleet.status_client_use', 'Client Use'), value: props.fleet.client_use || 0, color: 'secondary' },
])

const navigateVehicles = status => {
  const query = status ? { status } : {}

  router.push({ name: 'people-skills-vehicles-list', query })
}

const navigateDrivers = () => {
  router.push({ name: 'people-skills-approved-drivers-list' })
}
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem class="hr-dashboard-card-header">
      <template #prepend>
        <VAvatar
          color="secondary"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-car" />
        </VAvatar>
      </template>

      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('fleet.overview_title', 'Fleet & Driver Overview') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ fleet.total_vehicles }} {{ $t('fleet.total_vehicles', 'total vehicles') }} · {{ fleet.approved_drivers }} {{ $t('fleet.approved_drivers', 'approved drivers') }}
      </VCardSubtitle>

      <template #append>
        <div class="d-flex align-center gap-1">
          <VBtn
            size="small"
            variant="text"
            @click="() => navigateVehicles(null)"
          >
            {{ $t('fleet.vehicles_title', 'Vehicles') }}
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            @click="navigateDrivers"
          >
            {{ $t('fleet.drivers_title', 'Drivers') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@3"
        class="bg-transparent"
      />

      <template v-else>
        <!-- Alerts Banner if any -->
        <VAlert
          v-if="(fleet.total_alerts ?? 0) > 0"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4 cursor-pointer"
          @click="() => navigateVehicles(null)"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 text-caption font-weight-medium">
            <span>
              <strong>{{ fleet.service_due_count }}</strong> {{ $t('fleet.service_due', 'service/inspections due') }}
            </span>
            <span v-if="fleet.driver_renewals_due > 0">
              · <strong>{{ fleet.driver_renewals_due }}</strong> {{ $t('fleet.license_renewals', 'license renewals due') }}
            </span>
          </div>
        </VAlert>

        <!-- Status Breakdown Grid -->
        <div class="d-flex flex-wrap gap-2 mb-4">
          <VChip
            v-for="item in statusSegments"
            :key="item.key"
            :color="item.color"
            variant="tonal"
            class="cursor-pointer hover-chip"
            @click="() => navigateVehicles(item.key)"
          >
            <VIcon
              start
              icon="tabler-point-filled"
              size="12"
            />
            {{ item.label }}: <strong>{{ item.value }}</strong>
          </VChip>
        </div>

        <VDivider class="my-3" />

        <!-- Quick Summary Rows -->
        <div class="d-flex flex-column gap-y-2">
          <div
            class="d-flex align-center justify-space-between pa-2 rounded border cursor-pointer hover-row"
            @click="() => navigateVehicles('available')"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-circle-check"
                color="success"
                size="18"
              />
              <span class="text-body-2">{{ $t('fleet.available_for_dispatch', 'Available for Dispatch') }}</span>
            </div>
            <span class="font-weight-bold text-success">{{ fleet.available }}</span>
          </div>

          <div
            class="d-flex align-center justify-space-between pa-2 rounded border cursor-pointer hover-row"
            @click="navigateDrivers"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-license"
                color="primary"
                size="18"
              />
              <span class="text-body-2">{{ $t('fleet.active_authorized_drivers', 'Active Authorized Drivers') }}</span>
            </div>
            <span class="font-weight-bold text-primary">{{ fleet.approved_drivers }}</span>
          </div>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hover-chip:hover {
  filter: brightness(0.94);
}

.hover-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}
</style>
