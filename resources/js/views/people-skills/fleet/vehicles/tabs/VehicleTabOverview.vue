<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['assign', 'return', 'status', 'odometer', 'rental', 'clientUsage', 'telemetry'])

const { t } = useI18n()

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}

const isOverdue = dateStr => {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

const isUpcoming = (dateStr, days = 30) => {
  if (!dateStr) return false
  const target = new Date(dateStr)
  const now = new Date()
  const diffTime = target - now
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays >= 0 && diffDays <= days
}

const currentAssignment = computed(() => {
  return props.vehicle.current_assignment || props.vehicle.currentAssignment || null
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Top Row: Current Custody & Compliance Cards -->
    <VRow>
      <!-- Current Custody Card -->
      <VCol
        cols="12"
        md="6"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="info"
                variant="tonal"
                class="me-2"
              >
                <VIcon icon="tabler-user-pin" />
              </VAvatar>
            </template>
            <VCardTitle class="text-base">
              {{ $t('fleet.custody') || 'Current Custody & Responsibility' }}
            </VCardTitle>
            <template #append>
              <VBtn
                v-if="currentAssignment"
                variant="tonal"
                color="warning"
                size="small"
                prepend-icon="tabler-arrow-back-up"
                @click="emit('return', vehicle)"
              >
                {{ $t('fleet.return_vehicle') || 'Return' }}
              </VBtn>
              <VBtn
                v-else
                variant="tonal"
                color="primary"
                size="small"
                prepend-icon="tabler-user-plus"
                @click="emit('assign', vehicle)"
              >
                {{ $t('fleet.assign_vehicle') || 'Assign' }}
              </VBtn>
            </template>
          </VCardItem>

          <VCardText>
            <div
              v-if="currentAssignment"
              class="d-flex flex-column gap-3"
            >
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Responsible Employee:</span>
                <span class="font-weight-medium">{{ currentAssignment.responsible_employee?.name || currentAssignment.responsible_employee?.email || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Custody Type:</span>
                <VChip
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ currentAssignment.custody_type?.replaceAll('_', ' ') }}
                </VChip>
              </div>
              <div
                v-if="vehicle.current_site?.name"
                class="d-flex justify-space-between align-center"
              >
                <span class="text-caption text-medium-emphasis">Base Site / Warehouse:</span>
                <span>{{ vehicle.current_site.name }}</span>
              </div>
              <div
                v-if="currentAssignment.location_notes"
                class="d-flex justify-space-between align-center"
              >
                <span class="text-caption text-medium-emphasis">Location:</span>
                <span>{{ currentAssignment.location_notes }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Assigned Since:</span>
                <span>{{ formatDate(currentAssignment.start_date) }}</span>
              </div>
              <div
                v-if="currentAssignment.reason"
                class="d-flex justify-space-between align-center"
              >
                <span class="text-caption text-medium-emphasis">Reason:</span>
                <span class="text-body-2">{{ currentAssignment.reason }}</span>
              </div>
            </div>

            <div
              v-else
              class="d-flex flex-column align-center justify-center py-6 text-center text-disabled"
            >
              <VIcon
                icon="tabler-circle-check"
                size="36"
                class="mb-2"
              />
              <div>Vehicle is currently in pool storage with no active custody assignment.</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Compliance Timeline & Expiries -->
      <VCol
        cols="12"
        md="6"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="warning"
                variant="tonal"
                class="me-2"
              >
                <VIcon icon="tabler-shield-check" />
              </VAvatar>
            </template>
            <VCardTitle class="text-base">
              {{ $t('fleet.compliance') || 'Compliance & Due Dates' }}
            </VCardTitle>
          </VCardItem>

          <VCardText>
            <div class="d-flex flex-column gap-3">
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">{{ $t('fleet.next_service') || 'Next Service Due' }}:</span>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="isOverdue(vehicle.next_service_date)"
                    color="error"
                    size="x-small"
                    label
                  >
                    Overdue
                  </VChip>
                  <VChip
                    v-else-if="isUpcoming(vehicle.next_service_date)"
                    color="warning"
                    size="x-small"
                    label
                  >
                    Upcoming
                  </VChip>
                  <span class="font-weight-medium">{{ formatDate(vehicle.next_service_date) }}</span>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">{{ $t('fleet.next_inspection') || 'Next Periodic Inspection' }}:</span>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="isOverdue(vehicle.next_inspection_date)"
                    color="error"
                    size="x-small"
                    label
                  >
                    Overdue
                  </VChip>
                  <VChip
                    v-else-if="isUpcoming(vehicle.next_inspection_date)"
                    color="warning"
                    size="x-small"
                    label
                  >
                    Upcoming
                  </VChip>
                  <span class="font-weight-medium">{{ formatDate(vehicle.next_inspection_date) }}</span>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">{{ $t('fleet.insurance_expiry') || 'Insurance Policy Expiry' }}:</span>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="isOverdue(vehicle.insurance_expiry_date)"
                    color="error"
                    size="x-small"
                    label
                  >
                    Expired
                  </VChip>
                  <VChip
                    v-else-if="isUpcoming(vehicle.insurance_expiry_date)"
                    color="warning"
                    size="x-small"
                    label
                  >
                    Expiring Soon
                  </VChip>
                  <span class="font-weight-medium">{{ formatDate(vehicle.insurance_expiry_date) }}</span>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">{{ $t('fleet.registration_expiry') || 'Registration Expiry' }}:</span>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="isOverdue(vehicle.registration_expiry_date)"
                    color="error"
                    size="x-small"
                    label
                  >
                    Expired
                  </VChip>
                  <VChip
                    v-else-if="isUpcoming(vehicle.registration_expiry_date)"
                    color="warning"
                    size="x-small"
                    label
                  >
                    Expiring Soon
                  </VChip>
                  <span class="font-weight-medium">{{ formatDate(vehicle.registration_expiry_date) }}</span>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Specifications & Ownership Matrix -->
    <VRow>
      <!-- Specifications Table/Grid -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard
          variant="outlined"
          title="Vehicle Specifications"
        >
          <VCardText>
            <VRow dense>
              <VCol
                cols="6"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis">Make / Brand</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.make || '—' }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis">Model</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.model || '—' }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis">Year</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.year || '—' }}</div>
              </VCol>

              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Vehicle Type</div>
                <div class="text-body-1 font-weight-medium text-capitalize">{{ vehicle.type }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Vehicle Class</div>
                <div class="text-body-1 font-weight-medium text-capitalize">{{ vehicle.vehicle_class || '—' }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Fuel Type</div>
                <div class="text-body-1 font-weight-medium text-capitalize">{{ vehicle.fuel_type || '—' }}</div>
              </VCol>

              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Seats</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.seats ?? '—' }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Payload Capacity</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.capacity_kg ? `${vehicle.capacity_kg} kg` : '—' }}</div>
              </VCol>
              <VCol
                cols="6"
                sm="4"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Cargo Volume</div>
                <div class="text-body-1 font-weight-medium">{{ vehicle.capacity_m3 ? `${vehicle.capacity_m3} m³` : '—' }}</div>
              </VCol>

              <VCol
                cols="12"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Chassis / VIN Number</div>
                <div class="text-body-2 font-mono">{{ vehicle.vin || '—' }}</div>
              </VCol>

              <VCol
                v-if="vehicle.notes"
                cols="12"
                class="mt-3"
              >
                <div class="text-caption text-medium-emphasis">Notes & Remarks</div>
                <div class="text-body-2">{{ vehicle.notes }}</div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- GPS Tracker & Telemetry Card -->
        <VCard
          variant="outlined"
          class="mt-6"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                :color="vehicle.tracker_device_id ? 'success' : 'secondary'"
                variant="tonal"
                class="me-2"
              >
                <VIcon icon="tabler-gps" />
              </VAvatar>
            </template>
            <VCardTitle class="text-base">
              GPS Tracker & Location Telemetry
            </VCardTitle>
            <template #append>
              <div class="d-flex align-center gap-2">
                <VChip
                  :color="vehicle.tracker_device_id ? 'success' : 'secondary'"
                  size="small"
                  label
                >
                  {{ vehicle.tracker_device_id ? 'Tracker Active' : 'No Tracker Configured' }}
                </VChip>
                <VBtn
                  size="small"
                  variant="tonal"
                  density="compact"
                  prepend-icon="tabler-map-pin"
                  @click="emit('telemetry', vehicle)"
                >
                  Update GPS
                </VBtn>
              </div>
            </template>
          </VCardItem>
          <VCardText>
            <VRow>
              <VCol cols="12" sm="4">
                <div class="text-caption text-medium-emphasis">Device ID / Provider</div>
                <div class="text-body-2 font-weight-medium">
                  {{ vehicle.tracker_device_id || '—' }}
                  <span v-if="vehicle.tracker_provider" class="text-caption text-medium-emphasis">({{ vehicle.tracker_provider }})</span>
                </div>
              </VCol>
              <VCol cols="12" sm="4">
                <div class="text-caption text-medium-emphasis">IMEI Number</div>
                <div class="text-body-2 font-mono">{{ vehicle.tracker_imei || '—' }}</div>
              </VCol>
              <VCol cols="12" sm="4">
                <div class="text-caption text-medium-emphasis">Installed Date</div>
                <div class="text-body-2">{{ formatDate(vehicle.tracker_installed_at) }}</div>
              </VCol>

              <VCol cols="12" sm="8" class="mt-2">
                <div class="text-caption text-medium-emphasis">Last Known Location Address</div>
                <div class="text-body-2 font-weight-medium">
                  <VIcon icon="tabler-map-pin" size="16" class="me-1 text-primary" />
                  {{ vehicle.last_location_address || vehicle.current_location_notes || '—' }}
                </div>
              </VCol>
              <VCol cols="12" sm="4" class="mt-2">
                <div class="text-caption text-medium-emphasis">Last GPS Ping</div>
                <div class="text-body-2">{{ formatDate(vehicle.last_gps_timestamp) }}</div>
              </VCol>

              <VCol v-if="vehicle.last_gps_latitude && vehicle.last_gps_longitude" cols="12" class="mt-2">
                <div class="d-flex align-center gap-3 flex-wrap">
                  <VChip variant="tonal" size="small" color="primary">
                    Lat: {{ Number(vehicle.last_gps_latitude).toFixed(5) }}, Long: {{ Number(vehicle.last_gps_longitude).toFixed(5) }}
                  </VChip>
                  <VChip v-if="vehicle.last_speed_kmh != null" variant="tonal" size="small" color="info">
                    {{ vehicle.last_speed_kmh }} km/h
                  </VChip>
                  <VBtn
                    variant="text"
                    size="small"
                    density="compact"
                    color="primary"
                    prepend-icon="tabler-external-link"
                    :href="`https://maps.google.com/?q=${vehicle.last_gps_latitude},${vehicle.last_gps_longitude}`"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    View on Map
                  </VBtn>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ownership & Operational Status -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          variant="outlined"
          title="Ownership & Contract"
        >
          <VCardText class="d-flex flex-column gap-3">
            <div class="d-flex justify-space-between align-center">
              <span class="text-caption text-medium-emphasis">Ownership Mode:</span>
              <VChip
                color="primary"
                size="small"
                label
                class="text-capitalize"
              >
                {{ vehicle.ownership_type }}
              </VChip>
            </div>

            <!-- Rental Details if Rental -->
            <template v-if="vehicle.ownership_type === 'rental'">
              <VDivider />
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Rental Provider:</span>
                <span class="font-weight-medium">{{ vehicle.rental_provider?.name || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Contract Ref:</span>
                <span>{{ vehicle.rental_contract_reference || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Daily Rate:</span>
                <span>{{ vehicle.rental_daily_rate ? `${vehicle.rental_daily_rate} ${vehicle.rental_currency || 'TND'}` : '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Contract Expiry:</span>
                <span>{{ formatDate(vehicle.rental_end_date) }}</span>
              </div>
            </template>

            <!-- Client Usage Details if Client -->
            <template v-if="vehicle.ownership_type === 'client'">
              <VDivider />
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Client:</span>
                <span class="font-weight-medium">{{ vehicle.client?.name || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Driver Provided:</span>
                <span>{{ vehicle.client_supplies_driver ? 'By Client' : 'Internal Driver' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Usage Window:</span>
                <span>{{ formatDate(vehicle.client_usage_start) }} — {{ formatDate(vehicle.client_usage_end) }}</span>
              </div>
            </template>

            <VDivider />

            <!-- Odometer Card Mini -->
            <div class="pa-3 rounded bg-var-theme-background">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-medium-emphasis">Current Mileage</span>
                <VBtn
                  variant="text"
                  density="compact"
                  size="x-small"
                  color="primary"
                  @click="emit('odometer', vehicle)"
                >
                  Update
                </VBtn>
              </div>
              <div class="text-h6 font-weight-bold">
                {{ vehicle.odometer_km != null ? Number(vehicle.odometer_km).toLocaleString() : '0' }} <span class="text-caption">km</span>
              </div>
              <div class="text-caption text-disabled">
                Last updated: {{ formatDate(vehicle.odometer_last_updated) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
