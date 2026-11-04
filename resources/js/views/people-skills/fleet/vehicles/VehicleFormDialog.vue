<script setup>
/* eslint-disable vue/no-mutating-props */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  form: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  typeOptions: {
    type: Array,
    default: () => [],
  },
  ownershipOptions: {
    type: Array,
    default: () => [],
  },
  fuelTypeOptions: {
    type: Array,
    default: () => [],
  },
  userOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const activeTab = ref('identity')

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      activeTab.value = 'identity'
    }
  },
)

const isEditing = computed(() => Boolean(props.form.id))

const dialogTitle = computed(() => {
  return isEditing.value
    ? `${t('Edit')} ${props.form.plate_number ? `(${props.form.plate_number})` : ''}`
    : t('fleet.add_vehicle')
})

const vehicleClassOptions = [
  { title: 'Light Duty', value: 'light' },
  { title: 'Medium Duty', value: 'medium' },
  { title: 'Heavy Duty', value: 'heavy' },
  { title: 'Passenger', value: 'passenger' },
  { title: 'Cargo / Logistics', value: 'cargo' },
  { title: 'Specialized', value: 'specialized' },
]

const currentYear = new Date().getFullYear()

const onSubmit = () => {
  emit('save')
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="840"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="dialogTitle">
      <VCardText class="pt-2">
        <!-- Navigation Tabs for Organised Sections -->
        <VTabs
          v-model="activeTab"
          density="comfortable"
          class="mb-4 border-b"
        >
          <VTab value="identity">
            <VIcon
              start
              icon="tabler-id"
              size="18"
            />
            {{ $t('fleet.identity') || 'Identity' }}
          </VTab>
          <VTab value="classification">
            <VIcon
              start
              icon="tabler-category"
              size="18"
            />
            {{ $t('fleet.classification') || 'Classification' }}
          </VTab>
          <VTab value="technical">
            <VIcon
              start
              icon="tabler-engine"
              size="18"
            />
            {{ $t('fleet.technical') || 'Technical' }}
          </VTab>
          <VTab value="compliance">
            <VIcon
              start
              icon="tabler-shield-check"
              size="18"
            />
            {{ $t('fleet.compliance') || 'Compliance' }}
          </VTab>
          <VTab value="operational">
            <VIcon
              start
              icon="tabler-dashboard"
              size="18"
            />
            {{ $t('fleet.operational_section') || 'Operational' }}
          </VTab>
          <VTab value="telemetry">
            <VIcon
              start
              icon="tabler-gps"
              size="18"
            />
            {{ $t('fleet.telemetry') || 'Tracker & GPS' }}
          </VTab>
          <VTab value="notes">
            <VIcon
              start
              icon="tabler-notes"
              size="18"
            />
            {{ $t('fleet.notes') || 'Notes' }}
          </VTab>
        </VTabs>

        <VWindow v-model="activeTab">
          <!-- 1. Identity -->
          <VWindowItem value="identity">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.fleet_code"
                  :label="$t('fleet.fleet_code') || 'Fleet Code'"
                  placeholder="Auto-generated if empty"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.plate_number"
                  :label="`${$t('fleet.plate_number') || 'Plate Number'} *`"
                  placeholder="e.g. 1234-ABC"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.label"
                  :label="`${$t('fleet.label') || 'Label / Name'} *`"
                  placeholder="e.g. Service Van 01"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.vin"
                  :label="$t('fleet.vin') || 'VIN / Chassis Number'"
                  placeholder="e.g. 1HGCR2F83HA123456"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.registration_number"
                  :label="$t('fleet.registration_number') || 'Registration Number'"
                  placeholder="e.g. REG-12345"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.registration_date"
                  :label="$t('fleet.registration_date') || 'Registration Date'"
                  type="date"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.color"
                  :label="$t('fleet.color') || 'Color'"
                  placeholder="e.g. White"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 2. Classification -->
          <VWindowItem value="classification">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.type"
                  :label="`${$t('fleet.type') || 'Vehicle Type'} *`"
                  :items="typeOptions"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.vehicle_class"
                  :label="$t('fleet.vehicle_class') || 'Vehicle Class'"
                  :items="vehicleClassOptions"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.ownership_type"
                  :label="`${$t('fleet.ownership') || 'Ownership Type'} *`"
                  :items="ownershipOptions"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.status"
                  :label="$t('fleet.status') || 'Status'"
                  :items="statusOptions"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 3. Technical -->
          <VWindowItem value="technical">
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="form.make"
                  label="Make / Brand"
                  placeholder="e.g. Toyota"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="form.model"
                  label="Model"
                  placeholder="e.g. Hilux"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.year"
                  label="Manufacturing Year"
                  type="number"
                  min="1950"
                  :max="currentYear + 1"
                  placeholder="e.g. 2023"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="form.fuel_type"
                  :label="$t('fleet.fuel_type') || 'Fuel Type'"
                  :items="fuelTypeOptions"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.seats"
                  :label="$t('fleet.seats') || 'Seats'"
                  type="number"
                  min="1"
                  placeholder="e.g. 5"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.capacity_kg"
                  :label="$t('fleet.capacity') || 'Payload Capacity (kg)'"
                  type="number"
                  min="0"
                  suffix="kg"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.capacity_m3"
                  label="Volume Capacity (m³)"
                  type="number"
                  min="0"
                  suffix="m³"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.fuel_tank_capacity"
                  :label="$t('fleet.fuel_tank_capacity') || 'Fuel Tank Capacity (L)'"
                  type="number"
                  min="0"
                  suffix="L"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.battery_capacity_kwh"
                  :label="$t('fleet.battery_capacity') || 'Battery Capacity (kWh)'"
                  type="number"
                  min="0"
                  step="0.1"
                  suffix="kWh"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 4. Compliance -->
          <VWindowItem value="compliance">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.next_service_date"
                  :label="$t('fleet.next_service') || 'Next Service Date'"
                  type="date"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.next_inspection_date"
                  :label="$t('fleet.next_inspection') || 'Next Inspection Date'"
                  type="date"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.insurance_expiry_date"
                  :label="$t('fleet.insurance_expiry') || 'Insurance Expiry Date'"
                  type="date"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.registration_expiry_date"
                  :label="$t('fleet.registration_expiry') || 'Registration Expiry Date'"
                  type="date"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 5. Operational -->
          <VWindowItem value="operational">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model.number="form.odometer_km"
                  :label="$t('fleet.odometer') || 'Odometer (km)'"
                  type="number"
                  min="0"
                  suffix="km"
                />
              </VCol>
              <VCol
                v-if="isEditing && form.odometer_last_updated"
                cols="12"
                md="6"
              >
                <AppTextField
                  :model-value="form.odometer_last_updated"
                  label="Odometer Last Updated"
                  readonly
                  disabled
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.default_driver_id"
                  :label="$t('fleet.default_driver') || 'Default Driver'"
                  :items="userOptions"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.last_inspection_date"
                  :label="$t('fleet.last_inspection') || 'Last Inspection Date'"
                  type="date"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.last_service_date"
                  :label="$t('fleet.last_service') || 'Last Service Date'"
                  type="date"
                />
              </VCol>
              <VCol
                v-if="isEditing"
                cols="12"
              >
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  Tip: To log routine mileage increases with audit activity, use the dedicated <strong>Update Odometer</strong> action from the vehicle 360 view or list menu.
                </VAlert>
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 6. GPS Tracker & Telemetry -->
          <VWindowItem value="telemetry">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.tracker_device_id"
                  :label="$t('fleet.tracker_device_id') || 'GPS Device ID / Identifier'"
                  placeholder="e.g. TEL-FMB920-10982"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.tracker_imei"
                  :label="$t('fleet.tracker_imei') || 'Tracker IMEI'"
                  placeholder="e.g. 864201048291029"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.tracker_provider"
                  :label="$t('fleet.tracker_provider') || 'Telematics Provider'"
                  :items="[
                    { title: 'Teltonika', value: 'Teltonika' },
                    { title: 'GeoTab', value: 'GeoTab' },
                    { title: 'Cartrack', value: 'Cartrack' },
                    { title: 'Tramigo', value: 'Tramigo' },
                    { title: 'Queclink', value: 'Queclink' },
                    { title: 'Other / Custom', value: 'Other' },
                  ]"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.tracker_installed_at"
                  :label="$t('fleet.tracker_installed_at') || 'Installation Date'"
                  type="date"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.last_location_address"
                  :label="$t('fleet.last_location') || 'Current / Last Known Address'"
                  placeholder="e.g. Zone Industrielle Charguia II, Tunis"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model.number="form.last_gps_latitude"
                  :label="$t('fleet.latitude') || 'GPS Latitude'"
                  type="number"
                  step="0.000001"
                  placeholder="e.g. 36.8065"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model.number="form.last_gps_longitude"
                  :label="$t('fleet.longitude') || 'GPS Longitude'"
                  type="number"
                  step="0.000001"
                  placeholder="e.g. 10.1815"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 6. Notes -->
          <VWindowItem value="notes">
            <VRow>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.notes"
                  :label="$t('fleet.notes') || 'General Notes / Remarks'"
                  rows="4"
                  placeholder="Any operational conditions, special equipment, or history..."
                />
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VCardActions class="justify-space-between pa-4 border-t">
        <div class="d-flex gap-2">
          <VBtn
            v-if="activeTab !== 'identity'"
            variant="text"
            prepend-icon="tabler-chevron-left"
            @click="activeTab = activeTab === 'notes' ? 'operational' : activeTab === 'operational' ? 'compliance' : activeTab === 'compliance' ? 'technical' : activeTab === 'technical' ? 'classification' : 'identity'"
          >
            Previous
          </VBtn>
          <VBtn
            v-if="activeTab !== 'notes'"
            variant="tonal"
            append-icon="tabler-chevron-right"
            @click="activeTab = activeTab === 'identity' ? 'classification' : activeTab === 'classification' ? 'technical' : activeTab === 'technical' ? 'compliance' : activeTab === 'compliance' ? 'operational' : 'notes'"
          >
            Next
          </VBtn>
        </div>

        <div class="d-flex gap-2">
          <VBtn
            variant="tonal"
            :disabled="loading"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            prepend-icon="tabler-device-floppy"
            @click="onSubmit"
          >
            {{ isEditing ? $t('Save') : $t('Create') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
