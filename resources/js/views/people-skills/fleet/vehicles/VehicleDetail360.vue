<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { WpStatusChip } from '@/components/shared'
import VehicleTabOverview from './tabs/VehicleTabOverview.vue'
import VehicleTabAssignments from './tabs/VehicleTabAssignments.vue'
import VehicleTabDocuments from './tabs/VehicleTabDocuments.vue'
import VehicleTabMaintenance from './tabs/VehicleTabMaintenance.vue'
import VehicleTabFuel from './tabs/VehicleTabFuel.vue'
import VehicleTabChecklists from './tabs/VehicleTabChecklists.vue'
import VehicleTabActivity from './tabs/VehicleTabActivity.vue'
import VehicleTabUtilization from './tabs/VehicleTabUtilization.vue'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  userOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'edit',
  'assign',
  'return',
  'status',
  'odometer',
  'telemetry',
  'rental',
  'clientUsage',
  'delete',
  'refresh',
])

const { t } = useI18n()
const ability = useAbility()
const currentTab = ref('overview')
const canManageFleet = computed(() => ability.can('update', 'Fleet'))
const canDeleteFleet = computed(() => ability.can('delete', 'Fleet'))

// Localised labels with a humanised fallback for statuses without a key.
const statusLabel = status => status ? t(`fleet.status_${status}`, status.replaceAll('_', ' ')) : '—'
const ownershipLabel = value => value ? t(`fleet.ownership_${value}`, value) : '—'

const currentAssignment = computed(() => {
  return props.vehicle.current_assignment || props.vehicle.currentAssignment || null
})

const hasActiveRental = computed(() => {
  return props.vehicle.ownership_type === 'rental' || props.vehicle.status === 'rental_active'
})

const hasActiveClientUsage = computed(() => {
  return props.vehicle.ownership_type === 'client' || props.vehicle.status === 'client_use'
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Resource 360 Header Banner -->
    <VCard class="pa-6">
      <div class="d-flex flex-wrap align-start justify-space-between gap-4">
        <!-- Identity info -->
        <div class="d-flex align-start gap-3">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            color="default"
            class="mt-1"
            to="/people-skills/fleet/vehicles"
            :title="$t('Back')"
            :aria-label="$t('Back')"
          />
          <VAvatar
            color="primary"
            variant="tonal"
            size="64"
            rounded
          >
            <VIcon
              icon="tabler-car"
              size="36"
            />
          </VAvatar>

          <div>
            <div class="d-flex align-center gap-3">
              <h2 class="text-h4 font-weight-bold">
                {{ vehicle.plate_number }}
              </h2>
              <WpStatusChip
                type="vehicle"
                :status="vehicle.status"
                :label="statusLabel(vehicle.status)"
              />
              <WpStatusChip
                :status="vehicle.ownership_type"
                :label="ownershipLabel(vehicle.ownership_type)"
                color="primary"
                variant="outlined"
              />
            </div>

            <div class="text-subtitle-1 text-medium-emphasis mt-1">
              {{ vehicle.label }}
              <span v-if="vehicle.make || vehicle.model">
                · {{ [vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ') }}
              </span>
              <span
                v-if="vehicle.fleet_code"
                class="text-caption ms-2"
              >
                [{{ vehicle.fleet_code }}]
              </span>
            </div>

            <div class="text-caption text-disabled mt-1 font-mono">
              {{ $t('fleet.vin') }}: {{ vehicle.vin || '—' }}
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div
          v-if="canManageFleet || canDeleteFleet"
          class="d-flex flex-wrap align-center gap-2"
        >
          <!-- Assign / Return Primary Flow -->
          <VBtn
            v-if="canManageFleet && currentAssignment"
            color="warning"
            variant="tonal"
            prepend-icon="tabler-arrow-back-up"
            @click="emit('return', vehicle)"
          >
            {{ $t('fleet.return_vehicle') || 'Return Vehicle' }}
          </VBtn>
          <VBtn
            v-else-if="canManageFleet"
            color="primary"
            prepend-icon="tabler-user-plus"
            @click="emit('assign', vehicle)"
          >
            {{ $t('fleet.assign_vehicle') || 'Assign Vehicle' }}
          </VBtn>

          <!-- Odometer -->
          <VBtn
            v-if="canManageFleet"
            variant="tonal"
            prepend-icon="tabler-dashboard"
            @click="emit('odometer', vehicle)"
          >
            {{ $t('fleet.update_odometer') || 'Odometer' }}
          </VBtn>

          <!-- Status -->
          <VBtn
            v-if="canManageFleet"
            variant="tonal"
            prepend-icon="tabler-refresh"
            @click="emit('status', vehicle)"
          >
            {{ $t('fleet.change_status') || 'Status' }}
          </VBtn>

          <!-- Edit -->
          <VBtn
            v-if="canManageFleet"
            variant="tonal"
            prepend-icon="tabler-edit"
            @click="emit('edit', vehicle)"
          >
            {{ $t('Edit') }}
          </VBtn>

          <!-- More Operations Menu -->
          <VMenu v-if="canManageFleet || canDeleteFleet">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                icon="tabler-dots-vertical"
              />
            </template>
            <VList density="compact">
              <!-- Rental Workflows -->
              <VListItem
                v-if="canManageFleet && hasActiveRental"
                prepend-icon="tabler-car-off"
                :title="$t('fleet.end_rental')"
                @click="emit('rental', vehicle, true)"
              />
              <VListItem
                v-else-if="canManageFleet"
                prepend-icon="tabler-car"
                :title="$t('fleet.start_rental')"
                @click="emit('rental', vehicle, false)"
              />

              <!-- Client Usage Workflows -->
              <VListItem
                v-if="canManageFleet && hasActiveClientUsage"
                prepend-icon="tabler-building-bank"
                :title="$t('fleet.end_client_usage')"
                @click="emit('clientUsage', vehicle, true)"
              />
              <VListItem
                v-else-if="canManageFleet"
                prepend-icon="tabler-building-estate"
                :title="$t('fleet.start_client_usage')"
                @click="emit('clientUsage', vehicle, false)"
              />

              <VListItem
                v-if="canManageFleet"
                prepend-icon="tabler-map-pin"
                :title="$t('fleet.update_telemetry') || 'Update GPS Telemetry'"
                @click="emit('telemetry', vehicle)"
              />

              <VDivider
                v-if="canManageFleet && canDeleteFleet"
                class="my-1"
              />

              <VListItem
                v-if="canDeleteFleet"
                prepend-icon="tabler-trash"
                :title="$t('fleet.delete_title')"
                class="text-error"
                @click="emit('delete', vehicle)"
              />
            </VList>
          </VMenu>
        </div>
      </div>

      <!-- Header KPI / Indicator Strip -->
      <VDivider class="my-4" />
      <VRow dense>
        <VCol
          cols="6"
          sm="3"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('fleet.current_odometer') }}
          </div>
          <div class="text-h6 font-weight-bold">
            {{ vehicle.odometer_km != null ? `${Number(vehicle.odometer_km).toLocaleString()} km` : '—' }}
          </div>
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('fleet.custody') }}
          </div>
          <div class="text-h6 font-weight-bold text-truncate">
            {{ currentAssignment?.responsible_employee?.name || vehicle.current_site?.name || $t('fleet.storage') }}
          </div>
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('fleet.next_service') }}
          </div>
          <div class="text-h6 font-weight-bold">
            {{ formatDate(vehicle.next_service_date) }}
          </div>
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('fleet.insurance_expiry') }}
          </div>
          <div class="text-h6 font-weight-bold">
            {{ formatDate(vehicle.insurance_expiry_date) }}
          </div>
        </VCol>
      </VRow>
    </VCard>

    <!-- Resource 360 Tabs Navigation -->
    <VCard>
      <VTabs
        v-model="currentTab"
        show-arrows
        grow
        class="border-b"
      >
        <VTab value="overview">
          <VIcon
            start
            icon="tabler-layout-dashboard"
            size="18"
          />
          {{ $t('fleet.overview') || 'Overview' }}
        </VTab>
        <VTab value="assignments">
          <VIcon
            start
            icon="tabler-user-check"
            size="18"
          />
          {{ $t('fleet.assignments') || 'Assignments' }}
        </VTab>
        <VTab value="documents">
          <VIcon
            start
            icon="tabler-file-certificate"
            size="18"
          />
          {{ $t('fleet.documents_compliance') || 'Documents' }}
        </VTab>
        <VTab value="maintenance">
          <VIcon
            start
            icon="tabler-tool"
            size="18"
          />
          {{ $t('fleet.maintenance') || 'Maintenance' }}
        </VTab>
        <VTab value="fuel">
          <VIcon
            start
            icon="tabler-gas-station"
            size="18"
          />
          {{ $t('fleet.fuel') || 'Fuel' }}
        </VTab>
        <VTab value="checklists">
          <VIcon
            start
            icon="tabler-clipboard-check"
            size="18"
          />
          {{ $t('fleet.checklists') || 'Checklists' }}
        </VTab>
        <VTab value="activity">
          <VIcon
            start
            icon="tabler-history"
            size="18"
          />
          {{ $t('fleet.activity') || 'Activity' }}
        </VTab>
        <VTab value="utilization">
          <VIcon
            start
            icon="tabler-chart-pie"
            size="18"
          />
          {{ $t('fleet.utilization') || 'Utilization' }}
        </VTab>
      </VTabs>

      <!-- Tabs Content -->
      <VCardText class="pa-6">
        <VWindow v-model="currentTab">
          <VWindowItem value="overview">
            <VehicleTabOverview
              :vehicle="vehicle"
              @assign="emit('assign', $event)"
              @return="emit('return', $event)"
              @status="emit('status', $event)"
              @odometer="emit('odometer', $event)"
              @telemetry="emit('telemetry', $event)"
              @rental="emit('rental', $event)"
              @client-usage="emit('clientUsage', $event)"
            />
          </VWindowItem>

          <VWindowItem value="assignments">
            <VehicleTabAssignments
              :vehicle="vehicle"
              @assign="emit('assign', $event)"
              @return="emit('return', $event)"
            />
          </VWindowItem>

          <VWindowItem value="documents">
            <VehicleTabDocuments
              :vehicle="vehicle"
              @refresh="emit('refresh')"
            />
          </VWindowItem>

          <VWindowItem value="maintenance">
            <VehicleTabMaintenance
              :vehicle="vehicle"
              @refresh="emit('refresh')"
            />
          </VWindowItem>

          <VWindowItem value="fuel">
            <VehicleTabFuel
              :vehicle="vehicle"
              :user-options="userOptions"
              @refresh="emit('refresh')"
            />
          </VWindowItem>

          <VWindowItem value="checklists">
            <VehicleTabChecklists
              :vehicle="vehicle"
              :user-options="userOptions"
              @refresh="emit('refresh')"
            />
          </VWindowItem>

          <VWindowItem value="activity">
            <VehicleTabActivity :vehicle="vehicle" />
          </VWindowItem>

          <VWindowItem value="utilization">
            <VehicleTabUtilization :vehicle="vehicle" />
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>
