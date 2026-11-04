<script setup>
/* eslint-disable vue/no-mutating-props */
import { MultiLangField, WpWizardDialog } from '@/components/shared'
import { computed, ref } from 'vue'
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

const emit = defineEmits(['update:modelValue', 'save', 'submitted'])

const { t } = useI18n()
const wizardRef = ref(null)

const vehicleClassOptions = [
  { title: 'Light Duty', value: 'light' },
  { title: 'Medium Duty', value: 'medium' },
  { title: 'Heavy Duty', value: 'heavy' },
  { title: 'Passenger', value: 'passenger' },
  { title: 'Cargo / Logistics', value: 'cargo' },
  { title: 'Specialized', value: 'specialized' },
]

const currentYear = new Date().getFullYear()

const wizardSteps = computed(() => [
  {
    id: 'identity',
    title: t('fleet.identity') || 'Identity',
    subtitle: t('fleet.identity_subtitle') || 'Core vehicle identification',
    icon: 'tabler-id',
    fields: [
      { key: 'plate_number', label: t('fleet.plate_number') || 'Plate Number', type: 'text', required: true },
      { key: 'label', label: t('fleet.label') || 'Label / Name', type: 'text', required: true },
      { key: 'fleet_code', label: t('fleet.fleet_code') || 'Fleet Code', type: 'text' },
      { key: 'vin', label: t('fleet.vin') || 'VIN / Chassis Number', type: 'text' },
      { key: 'registration_number', label: t('fleet.registration_number') || 'Registration Number', type: 'text' },
      { key: 'registration_date', label: t('fleet.registration_date') || 'Registration Date', type: 'date' },
      { key: 'color', label: t('fleet.color') || 'Color', type: 'text' },
    ],
  },
  {
    id: 'classification',
    title: t('fleet.classification') || 'Classification',
    subtitle: t('fleet.classification_subtitle') || 'Type and ownership',
    icon: 'tabler-category',
    fields: [
      { key: 'type', label: t('fleet.type') || 'Vehicle Type', type: 'select', required: true },
      { key: 'vehicle_class', label: t('fleet.vehicle_class') || 'Vehicle Class', type: 'select' },
      { key: 'ownership_type', label: t('fleet.ownership') || 'Ownership Type', type: 'select', required: true },
      { key: 'status', label: t('fleet.status') || 'Status', type: 'select' },
    ],
  },
  {
    id: 'technical',
    title: t('fleet.technical') || 'Technical',
    subtitle: t('fleet.technical_subtitle') || 'Specifications and capacity',
    icon: 'tabler-engine',
    fields: [
      { key: 'make', label: 'Make / Brand', type: 'text' },
      { key: 'model', label: 'Model', type: 'text' },
      { key: 'year', label: 'Manufacturing Year', type: 'number' },
      { key: 'fuel_type', label: t('fleet.fuel_type') || 'Fuel Type', type: 'select' },
      { key: 'seats', label: t('fleet.seats') || 'Seats', type: 'number' },
      { key: 'capacity_kg', label: t('fleet.capacity') || 'Payload Capacity (kg)', type: 'number' },
      { key: 'capacity_m3', label: 'Volume Capacity (m³)', type: 'number' },
      { key: 'fuel_tank_capacity', label: t('fleet.fuel_tank_capacity') || 'Fuel Tank Capacity (L)', type: 'number' },
      { key: 'battery_capacity_kwh', label: t('fleet.battery_capacity') || 'Battery Capacity (kWh)', type: 'number' },
    ],
  },
  {
    id: 'compliance',
    title: t('fleet.compliance') || 'Compliance',
    subtitle: t('fleet.compliance_subtitle') || 'Service and inspection dates',
    icon: 'tabler-shield-check',
    fields: [
      { key: 'next_service_date', label: t('fleet.next_service') || 'Next Service Date', type: 'date' },
      { key: 'next_inspection_date', label: t('fleet.next_inspection') || 'Next Inspection Date', type: 'date' },
      { key: 'insurance_expiry_date', label: t('fleet.insurance_expiry') || 'Insurance Expiry Date', type: 'date' },
      { key: 'registration_expiry_date', label: t('fleet.registration_expiry') || 'Registration Expiry Date', type: 'date' },
    ],
  },
  {
    id: 'operational',
    title: t('fleet.operational_section') || 'Operational',
    subtitle: t('fleet.operational_subtitle') || 'Current state and assignment',
    icon: 'tabler-dashboard',
    fields: [
      { key: 'odometer_km', label: t('fleet.odometer') || 'Odometer (km)', type: 'number' },
      { key: 'default_driver_id', label: t('fleet.default_driver') || 'Default Driver', type: 'select' },
      { key: 'last_inspection_date', label: t('fleet.last_inspection') || 'Last Inspection Date', type: 'date' },
      { key: 'last_service_date', label: t('fleet.last_service') || 'Last Service Date', type: 'date' },
    ],
  },
  {
    id: 'telemetry',
    title: t('fleet.telemetry') || 'Telemetry',
    subtitle: t('fleet.telemetry_subtitle') || 'GPS and tracking information',
    icon: 'tabler-gps',
    fields: [
      { key: 'tracker_device_id', label: t('fleet.tracker_device_id') || 'GPS Device ID', type: 'text' },
      { key: 'tracker_imei', label: t('fleet.tracker_imei') || 'Tracker IMEI', type: 'text' },
      { key: 'tracker_provider', label: t('fleet.tracker_provider') || 'Telematics Provider', type: 'select' },
      { key: 'tracker_installed_at', label: t('fleet.tracker_installed_at') || 'Installation Date', type: 'date' },
      { key: 'last_location_address', label: t('fleet.last_location') || 'Current / Last Known Address', type: 'text' },
      { key: 'last_gps_latitude', label: t('fleet.latitude') || 'GPS Latitude', type: 'number' },
      { key: 'last_gps_longitude', label: t('fleet.longitude') || 'GPS Longitude', type: 'number' },
    ],
  },
  {
    id: 'notes',
    title: t('fleet.notes') || 'Notes',
    subtitle: t('fleet.notes_subtitle') || 'Additional remarks',
    icon: 'tabler-notes',
    fields: [
      { key: 'notes', label: t('fleet.notes') || 'General Notes / Remarks', type: 'textarea' },
    ],
  },
])

const handleSubmit = async payload => {
  // The wizard already validates, so we can directly save
  emit('save', payload)
}

const handleSubmitted = result => {
  emit('submitted', result)
}

const trackerProviderOptions = [
  { title: 'Teltonika', value: 'Teltonika' },
  { title: 'GeoTab', value: 'GeoTab' },
  { title: 'Cartrack', value: 'Cartrack' },
  { title: 'Tramigo', value: 'Tramigo' },
  { title: 'Queclink', value: 'Queclink' },
  { title: 'Other / Custom', value: 'Other' },
]
</script>

<template>
  <WpWizardDialog
    ref="wizardRef"
    :model-value="modelValue"
    variant="developed"
    :title="$t('fleet.add_vehicle') || 'Add New Vehicle'"
    :description="$t('fleet.add_vehicle_description') || 'Register a new vehicle in the fleet management system'"
    icon="tabler-car-plus"
    :steps="wizardSteps"
    :initial-values="form"
    :submit="handleSubmit"
    :success-message="$t('fleet.vehicle_created') || 'Vehicle created successfully!'"
    draft-key="fleet:add-vehicle"
    close-after-submit
    @update:model-value="val => emit('update:modelValue', val)"
    @submitted="handleSubmitted"
  >
    <!-- Step 1: Identity -->
    <template #step-identity="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.fleet_code"
            :label="$t('fleet.fleet_code') || 'Fleet Code'"
            placeholder="Auto-generated if empty"
            variant="outlined"
            :error-messages="errors.fleet_code"
            @update:model-value="v => setValue('fleet_code', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.plate_number"
            :label="`${$t('fleet.plate_number') || 'Plate Number'} *`"
            placeholder="e.g. 1234-ABC"
            variant="outlined"
            :error-messages="errors.plate_number"
            @update:model-value="v => setValue('plate_number', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.label"
            :label="`${$t('fleet.label') || 'Label / Name'} *`"
            placeholder="e.g. Service Van 01"
            variant="outlined"
            :error-messages="errors.label"
            @update:model-value="v => setValue('label', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.vin"
            :label="$t('fleet.vin') || 'VIN / Chassis Number'"
            placeholder="e.g. 1HGCR2F83HA123456"
            variant="outlined"
            :error-messages="errors.vin"
            @update:model-value="v => setValue('vin', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.registration_number"
            :label="$t('fleet.registration_number') || 'Registration Number'"
            placeholder="e.g. REG-12345"
            variant="outlined"
            :error-messages="errors.registration_number"
            @update:model-value="v => setValue('registration_number', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.registration_date"
            :label="$t('fleet.registration_date') || 'Registration Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.registration_date"
            @update:model-value="v => setValue('registration_date', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.color"
            :label="$t('fleet.color') || 'Color'"
            placeholder="e.g. White"
            variant="outlined"
            :error-messages="errors.color"
            @update:model-value="v => setValue('color', v)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 2: Classification -->
    <template #step-classification="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.type"
            :label="`${$t('fleet.type') || 'Vehicle Type'} *`"
            :items="typeOptions"
            variant="outlined"
            :error-messages="errors.type"
            @update:model-value="v => setValue('type', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.vehicle_class"
            :label="$t('fleet.vehicle_class') || 'Vehicle Class'"
            :items="vehicleClassOptions"
            variant="outlined"
            clearable
            :error-messages="errors.vehicle_class"
            @update:model-value="v => setValue('vehicle_class', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.ownership_type"
            :label="`${$t('fleet.ownership') || 'Ownership Type'} *`"
            :items="ownershipOptions"
            variant="outlined"
            :error-messages="errors.ownership_type"
            @update:model-value="v => setValue('ownership_type', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.status"
            :label="$t('fleet.status') || 'Status'"
            :items="statusOptions"
            variant="outlined"
            :error-messages="errors.status"
            @update:model-value="v => setValue('status', v)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 3: Technical -->
    <template #step-technical="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.make"
            label="Make / Brand"
            placeholder="e.g. Toyota"
            variant="outlined"
            :error-messages="errors.make"
            @update:model-value="v => setValue('make', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.model"
            label="Model"
            placeholder="e.g. Hilux"
            variant="outlined"
            :error-messages="errors.model"
            @update:model-value="v => setValue('model', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.year"
            label="Manufacturing Year"
            type="number"
            :min="1950"
            :max="currentYear + 1"
            placeholder="e.g. 2023"
            variant="outlined"
            :error-messages="errors.year"
            @update:model-value="v => setValue('year', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppSelect
            :model-value="stepForm.fuel_type"
            :label="$t('fleet.fuel_type') || 'Fuel Type'"
            :items="fuelTypeOptions"
            variant="outlined"
            clearable
            :error-messages="errors.fuel_type"
            @update:model-value="v => setValue('fuel_type', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.seats"
            :label="$t('fleet.seats') || 'Seats'"
            type="number"
            min="1"
            placeholder="e.g. 5"
            variant="outlined"
            :error-messages="errors.seats"
            @update:model-value="v => setValue('seats', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.capacity_kg"
            :label="$t('fleet.capacity') || 'Payload Capacity (kg)'"
            type="number"
            min="0"
            suffix="kg"
            variant="outlined"
            :error-messages="errors.capacity_kg"
            @update:model-value="v => setValue('capacity_kg', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.capacity_m3"
            label="Volume Capacity (m³)"
            type="number"
            min="0"
            suffix="m³"
            variant="outlined"
            :error-messages="errors.capacity_m3"
            @update:model-value="v => setValue('capacity_m3', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.fuel_tank_capacity"
            :label="$t('fleet.fuel_tank_capacity') || 'Fuel Tank Capacity (L)'"
            type="number"
            min="0"
            suffix="L"
            variant="outlined"
            :error-messages="errors.fuel_tank_capacity"
            @update:model-value="v => setValue('fuel_tank_capacity', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            :model-value="stepForm.battery_capacity_kwh"
            :label="$t('fleet.battery_capacity') || 'Battery Capacity (kWh)'"
            type="number"
            min="0"
            step="0.1"
            suffix="kWh"
            variant="outlined"
            :error-messages="errors.battery_capacity_kwh"
            @update:model-value="v => setValue('battery_capacity_kwh', v ? Number(v) : null)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 4: Compliance -->
    <template #step-compliance="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.next_service_date"
            :label="$t('fleet.next_service') || 'Next Service Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.next_service_date"
            @update:model-value="v => setValue('next_service_date', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.next_inspection_date"
            :label="$t('fleet.next_inspection') || 'Next Inspection Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.next_inspection_date"
            @update:model-value="v => setValue('next_inspection_date', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.insurance_expiry_date"
            :label="$t('fleet.insurance_expiry') || 'Insurance Expiry Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.insurance_expiry_date"
            @update:model-value="v => setValue('insurance_expiry_date', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.registration_expiry_date"
            :label="$t('fleet.registration_expiry') || 'Registration Expiry Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.registration_expiry_date"
            @update:model-value="v => setValue('registration_expiry_date', v)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 5: Operational -->
    <template #step-operational="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.odometer_km"
            :label="$t('fleet.odometer') || 'Odometer (km)'"
            type="number"
            min="0"
            suffix="km"
            variant="outlined"
            :error-messages="errors.odometer_km"
            @update:model-value="v => setValue('odometer_km', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.default_driver_id"
            :label="$t('fleet.default_driver') || 'Default Driver'"
            :items="userOptions"
            variant="outlined"
            clearable
            :error-messages="errors.default_driver_id"
            @update:model-value="v => setValue('default_driver_id', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.last_inspection_date"
            :label="$t('fleet.last_inspection') || 'Last Inspection Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.last_inspection_date"
            @update:model-value="v => setValue('last_inspection_date', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.last_service_date"
            :label="$t('fleet.last_service') || 'Last Service Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.last_service_date"
            @update:model-value="v => setValue('last_service_date', v)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 6: Telemetry -->
    <template #step-telemetry="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.tracker_device_id"
            :label="$t('fleet.tracker_device_id') || 'GPS Device ID / Identifier'"
            placeholder="e.g. TEL-FMB920-10982"
            variant="outlined"
            :error-messages="errors.tracker_device_id"
            @update:model-value="v => setValue('tracker_device_id', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.tracker_imei"
            :label="$t('fleet.tracker_imei') || 'Tracker IMEI'"
            placeholder="e.g. 864201048291029"
            variant="outlined"
            :error-messages="errors.tracker_imei"
            @update:model-value="v => setValue('tracker_imei', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            :model-value="stepForm.tracker_provider"
            :label="$t('fleet.tracker_provider') || 'Telematics Provider'"
            :items="trackerProviderOptions"
            variant="outlined"
            clearable
            :error-messages="errors.tracker_provider"
            @update:model-value="v => setValue('tracker_provider', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.tracker_installed_at"
            :label="$t('fleet.tracker_installed_at') || 'Installation Date'"
            type="date"
            variant="outlined"
            :error-messages="errors.tracker_installed_at"
            @update:model-value="v => setValue('tracker_installed_at', v)"
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            :model-value="stepForm.last_location_address"
            :label="$t('fleet.last_location') || 'Current / Last Known Address'"
            placeholder="e.g. Zone Industrielle Charguia II, Tunis"
            variant="outlined"
            :error-messages="errors.last_location_address"
            @update:model-value="v => setValue('last_location_address', v)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.last_gps_latitude"
            :label="$t('fleet.latitude') || 'GPS Latitude'"
            type="number"
            step="0.000001"
            placeholder="e.g. 36.8065"
            variant="outlined"
            :error-messages="errors.last_gps_latitude"
            @update:model-value="v => setValue('last_gps_latitude', v ? Number(v) : null)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="stepForm.last_gps_longitude"
            :label="$t('fleet.longitude') || 'GPS Longitude'"
            type="number"
            step="0.000001"
            placeholder="e.g. 10.1815"
            variant="outlined"
            :error-messages="errors.last_gps_longitude"
            @update:model-value="v => setValue('last_gps_longitude', v ? Number(v) : null)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 7: Notes -->
    <template #step-notes="{ form: stepForm, errors, setValue }">
      <VRow>
        <VCol cols="12">
          <AppTextarea
            :model-value="stepForm.notes"
            :label="$t('fleet.notes') || 'General Notes / Remarks'"
            rows="4"
            placeholder="Any operational conditions, special equipment, or history..."
            variant="outlined"
            :error-messages="errors.notes"
            @update:model-value="v => setValue('notes', v)"
          />
        </VCol>
      </VRow>
    </template>
  </WpWizardDialog>
</template>
