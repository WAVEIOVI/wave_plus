<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  section: {
    type: String,
    required: true,
  },
  readonly: {
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

const { t } = useI18n()
const currentYear = new Date().getFullYear()

const vehicleClassOptions = [
  { title: 'Light Duty', value: 'light' },
  { title: 'Medium Duty', value: 'medium' },
  { title: 'Heavy Duty', value: 'heavy' },
  { title: 'Passenger', value: 'passenger' },
  { title: 'Cargo / Logistics', value: 'cargo' },
  { title: 'Specialized', value: 'specialized' },
]

const trackerProviderOptions = [
  { title: 'Teltonika', value: 'Teltonika' },
  { title: 'GeoTab', value: 'GeoTab' },
  { title: 'Cartrack', value: 'Cartrack' },
  { title: 'Tramigo', value: 'Tramigo' },
  { title: 'Queclink', value: 'Queclink' },
  { title: 'Other / Custom', value: 'Other' },
]

const sections = computed(() => ({
  identity: {
    title: t('fleet.identity', 'Identity'),
    icon: 'tabler-id',
    fields: [
      { key: 'fleet_code', label: t('fleet.fleet_code', 'Fleet Code') },
      { key: 'plate_number', label: t('fleet.plate_number', 'Plate Number'), required: true },
      { key: 'label', label: t('fleet.label', 'Label / Name'), required: true },
      { key: 'vin', label: t('fleet.vin', 'VIN / Chassis Number') },
      { key: 'registration_number', label: t('fleet.registration_number', 'Registration Number') },
      { key: 'registration_date', label: t('fleet.registration_date', 'Registration Date'), type: 'date' },
      { key: 'color', label: t('fleet.color', 'Color') },
    ],
  },
  classification: {
    title: t('fleet.classification', 'Classification'),
    icon: 'tabler-category',
    fields: [
      { key: 'type', label: t('fleet.type', 'Vehicle Type'), type: 'select', options: props.typeOptions, required: true, optionGroup: 'type' },
      { key: 'vehicle_class', label: t('fleet.vehicle_class', 'Vehicle Class'), type: 'select', options: vehicleClassOptions, optionGroup: 'vehicle_class' },
      { key: 'ownership_type', label: t('fleet.ownership', 'Ownership Type'), type: 'select', options: props.ownershipOptions, required: true, optionGroup: 'ownership' },
      { key: 'status', label: t('fleet.status', 'Status'), type: 'select', options: props.statusOptions, optionGroup: 'status' },
    ],
  },
  technical: {
    title: t('fleet.technical', 'Technical'),
    icon: 'tabler-engine',
    fields: [
      { key: 'make', label: t('fleet.make', 'Make / Brand') },
      { key: 'model', label: t('fleet.model', 'Model') },
      { key: 'year', label: t('fleet.manufacturing_year', 'Manufacturing Year'), type: 'number', min: 1950, max: currentYear + 1 },
      { key: 'fuel_type', label: t('fleet.fuel_type', 'Fuel Type'), type: 'select', options: props.fuelTypeOptions, optionGroup: 'fuel_type' },
      { key: 'seats', label: t('fleet.seats', 'Seats'), type: 'number', min: 1 },
      { key: 'capacity_kg', label: t('fleet.capacity', 'Payload Capacity (kg)'), type: 'number', min: 0, suffix: 'kg' },
      { key: 'capacity_m3', label: t('fleet.volume_capacity', 'Volume Capacity (m³)'), type: 'number', min: 0, suffix: 'm³' },
      { key: 'fuel_tank_capacity', label: t('fleet.fuel_tank_capacity', 'Fuel Tank Capacity (L)'), type: 'number', min: 0, suffix: 'L' },
      { key: 'battery_capacity_kwh', label: t('fleet.battery_capacity', 'Battery Capacity (kWh)'), type: 'number', min: 0, step: 0.1, suffix: 'kWh' },
    ],
  },
  compliance: {
    title: t('fleet.compliance', 'Compliance'),
    icon: 'tabler-shield-check',
    fields: [
      { key: 'next_service_date', label: t('fleet.next_service', 'Next Service Date'), type: 'date' },
      { key: 'next_inspection_date', label: t('fleet.next_inspection', 'Next Inspection Date'), type: 'date' },
      { key: 'insurance_expiry_date', label: t('fleet.insurance_expiry', 'Insurance Expiry Date'), type: 'date' },
      { key: 'registration_expiry_date', label: t('fleet.registration_expiry', 'Registration Expiry Date'), type: 'date' },
    ],
  },
  operational: {
    title: t('fleet.operational_section', 'Operational'),
    icon: 'tabler-dashboard',
    fields: [
      { key: 'odometer_km', label: t('fleet.odometer', 'Odometer (km)'), type: 'number', min: 0, suffix: 'km' },
      { key: 'odometer_last_updated', label: t('fleet.odometer_last_updated', 'Odometer Last Updated'), type: 'date', readonly: true },
      { key: 'default_driver_id', label: t('fleet.default_driver', 'Default Driver'), type: 'select', options: props.userOptions },
      { key: 'last_inspection_date', label: t('fleet.last_inspection', 'Last Inspection Date'), type: 'date' },
      { key: 'last_service_date', label: t('fleet.last_service', 'Last Service Date'), type: 'date' },
    ],
  },
  telemetry: {
    title: t('fleet.telemetry', 'Tracker & GPS'),
    icon: 'tabler-gps',
    fields: [
      { key: 'tracker_device_id', label: t('fleet.tracker_device_id', 'GPS Device ID / Identifier') },
      { key: 'tracker_imei', label: t('fleet.tracker_imei', 'Tracker IMEI') },
      { key: 'tracker_provider', label: t('fleet.tracker_provider', 'Telematics Provider'), type: 'select', options: trackerProviderOptions },
      { key: 'tracker_installed_at', label: t('fleet.tracker_installed_at', 'Installation Date'), type: 'date' },
      { key: 'last_location_address', label: t('fleet.last_location', 'Current / Last Known Address'), cols: 12 },
      { key: 'last_gps_latitude', label: t('fleet.latitude', 'GPS Latitude'), type: 'number', step: 0.000001 },
      { key: 'last_gps_longitude', label: t('fleet.longitude', 'GPS Longitude'), type: 'number', step: 0.000001 },
    ],
  },
  notes: {
    title: t('fleet.notes', 'Notes'),
    icon: 'tabler-notes',
    fields: [
      { key: 'notes', label: t('fleet.notes', 'General Notes / Remarks'), type: 'textarea', rows: 4, cols: 12 },
    ],
  },
}))

const sectionData = computed(() => sections.value[props.section] || sections.value.identity)

const columnSize = field => field.cols || (field.type === 'number' && ['year', 'seats'].includes(field.key) ? 4 : 6)

const fieldValue = field => {
  const value = props.form[field.key]

  return props.readonly && field.type === 'date' && typeof value === 'string'
    ? value.slice(0, 10)
    : value
}

const updateField = (field, value) => {
  if (props.readonly || field.readonly)
    return

  props.form[field.key] = field.type === 'number' && value !== null && value !== ''
    ? Number(value)
    : value
}

const fieldOptions = field => {
  const options = field.options || []

  return options.map(option => {
    if (!field.optionGroup)
      return option

    const key = `fleet.${field.optionGroup}_${option.value}`

    return {
      ...option,
      title: t(key, option.title || String(option.value).replaceAll('_', ' ')),
    }
  })
}
</script>

<template>
  <WpSectionCard
    :title="sectionData.title"
    :icon="sectionData.icon"
  >
    <VRow>
      <VCol
        v-for="field in sectionData.fields"
        :key="field.key"
        :cols="field.cols || 12"
        :md="field.cols ? undefined : columnSize(field)"
      >
        <AppSelect
          v-if="field.type === 'select'"
          :model-value="fieldValue(field)"
          :label="field.label"
          :items="fieldOptions(field)"
          :disabled="readonly || field.readonly"
          :clearable="!field.required && !readonly"
          @update:model-value="value => updateField(field, value)"
        />
        <AppTextarea
          v-else-if="field.type === 'textarea'"
          :model-value="fieldValue(field)"
          :label="field.label"
          :rows="field.rows || 3"
          :readonly="readonly || field.readonly"
          @update:model-value="value => updateField(field, value)"
        />
        <AppTextField
          v-else
          :model-value="fieldValue(field)"
          :label="field.label"
          :type="field.type || 'text'"
          :min="field.min"
          :max="field.max"
          :step="field.step"
          :suffix="field.suffix"
          :readonly="readonly || field.readonly"
          :required="field.required"
          @update:model-value="value => updateField(field, value)"
        />
      </VCol>
    </VRow>
  </WpSectionCard>
</template>
