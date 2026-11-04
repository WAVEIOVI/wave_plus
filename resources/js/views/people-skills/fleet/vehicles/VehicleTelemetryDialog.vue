<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  telemetryData: {
    type: Object,
    required: true,
  },
  vehicle: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const geoLoading = ref(false)
const geoError = ref('')

watch(() => props.modelValue, val => {
  if (val) {
    // Initialize form from telemetryData when dialog opens
    if (!props.telemetryData.last_gps_timestamp) {
      props.telemetryData.last_gps_timestamp = new Date().toISOString().slice(0, 16)
    }
  } else {
    // Clear geo error when closing
    geoError.value = ''
  }
}, { immediate: true })

const dialogTitle = computed(() => {
  return props.vehicle
    ? `${t('fleet.telemetry_title') || 'Update GPS Telemetry'} — ${props.vehicle.plate_number}`
    : (t('fleet.telemetry_title') || 'Update GPS Telemetry')
})

const fetchCurrentLocation = () => {
  if (!navigator.geolocation) {
    geoError.value = 'Geolocation is not supported by your browser.'

    return
  }

  geoLoading.value = true
  geoError.value = ''
  navigator.geolocation.getCurrentPosition(
    position => {
      props.telemetryData.last_gps_latitude = Number(position.coords.latitude.toFixed(7))
      props.telemetryData.last_gps_longitude = Number(position.coords.longitude.toFixed(7))
      props.telemetryData.last_gps_timestamp = new Date().toISOString().slice(0, 16)
      if (position.coords.speed != null) {
        props.telemetryData.last_speed_kmh = Math.round(position.coords.speed * 3.6)
      }
      geoLoading.value = false
    },
    error => {
      geoError.value = error.message || 'Unable to retrieve location.'
      geoLoading.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 },
  )
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="dialogTitle"
    icon="tabler-map-pin"
    icon-color="primary"
    max-width="560"
    :loading="loading"
    :submit-label="$t('fleet.update_telemetry') || 'Save Coordinates'"
    submit-icon="tabler-map-pin"
    @update:model-value="val => emit('update:modelValue', val)"
    @submit="emit('save')"
  >
    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ $t('fleet.telemetry_help') || 'Record the latest GPS coordinates, telemetry, and location snapshot for this fleet vehicle.' }}
    </VAlert>

    <VAlert
      v-if="geoError"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
      closable
      @click:close="geoError = ''"
    >
      {{ geoError }}
    </VAlert>

    <div class="d-flex justify-end mb-3">
      <VBtn
        size="small"
        variant="tonal"
        color="primary"
        prepend-icon="tabler-crosshair"
        :loading="geoLoading"
        @click="fetchCurrentLocation"
      >
        {{ $t('fleet.use_device_location') || 'Use Device GPS' }}
      </VBtn>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model.number="telemetryData.last_gps_latitude"
          :label="`${$t('fleet.latitude') || 'Latitude'} *`"
          type="number"
          step="0.000001"
          placeholder="e.g. 36.806500"
          required
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model.number="telemetryData.last_gps_longitude"
          :label="`${$t('fleet.longitude') || 'Longitude'} *`"
          type="number"
          step="0.000001"
          placeholder="e.g. 10.181500"
          required
          variant="outlined"
        />
      </VCol>

      <VCol cols="12">
        <AppTextField
          v-model="telemetryData.last_location_address"
          :label="$t('fleet.location_address') || 'Location Address / Landmark'"
          placeholder="e.g. Zone Industrielle Charguia II, Tunis"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model.number="telemetryData.last_speed_kmh"
          :label="$t('fleet.speed_kmh') || 'Speed (km/h)'"
          type="number"
          min="0"
          max="250"
          suffix="km/h"
          placeholder="0"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="telemetryData.last_gps_timestamp"
          :label="$t('fleet.timestamp') || 'Ping Timestamp'"
          type="datetime-local"
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
