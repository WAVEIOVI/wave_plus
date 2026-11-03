<script setup>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
  addressQuery: { type: String, default: '' },
  isVisible: { type: Boolean, default: false },
  height: { type: [Number, String], default: 280 },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:latitude', 'update:longitude', 'error', 'located'])

const mapContainer = ref(null)
const map = ref(null)
const marker = ref(null)
const isGeocoding = ref(false)
const isLocating = ref(false)
const locationError = ref('')
const locationMessage = ref('')
const fallbackCenter = [36.8065, 10.1815]
const nominatimUrl = 'https://nominatim.openstreetmap.org'

const coordinates = computed(() => {
  if (props.latitude === null || props.latitude === undefined || props.latitude === ''
    || props.longitude === null || props.longitude === undefined || props.longitude === '')
    return null

  const latitude = Number(props.latitude)
  const longitude = Number(props.longitude)

  return Number.isFinite(latitude) && Number.isFinite(longitude) && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180
    ? [latitude, longitude]
    : null
})

const setCoordinates = (latitude, longitude, shouldCenter = true) => {
  emit('update:latitude', Number(latitude.toFixed(7)))
  emit('update:longitude', Number(longitude.toFixed(7)))

  if (!map.value)
    return

  const location = [latitude, longitude]

  marker.value?.setLatLng(location)
  if (!marker.value) {
    marker.value = L.marker(location, { draggable: !props.readonly }).addTo(map.value)
    if (!props.readonly) {
      marker.value.on('dragend', () => {
        const position = marker.value.getLatLng()

        setCoordinates(position.lat, position.lng, false)
        reverseGeocode(position.lat, position.lng)
      })
    }
  }
  if (shouldCenter)
    map.value.setView(location, 16)
}

const reverseGeocode = async (latitude, longitude) => {
  try {
    const response = await fetch(`${nominatimUrl}/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`, {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok)
      return

    const result = await response.json()

    if (result.display_name)
      locationMessage.value = result.display_name
  }
  catch {
    // Reverse lookup is supplemental; the selected coordinates remain valid.
  }
}

const locateAddress = async () => {
  if (!props.addressQuery.trim()) {
    locationError.value = 'Enter an address before locating it.'

    return
  }

  isGeocoding.value = true
  locationError.value = ''
  locationMessage.value = ''

  try {
    const response = await fetch(`${nominatimUrl}/search?format=jsonv2&limit=1&addressdetails=1&q=${encodeURIComponent(props.addressQuery)}`, {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok)
      throw new Error('Geocoding request failed')

    const [result] = await response.json()
    const latitude = Number(result?.lat)
    const longitude = Number(result?.lon)
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude))
      throw new Error('Address was not found')

    setCoordinates(latitude, longitude)
    locationMessage.value = result.display_name || 'Address located. Adjust the marker if needed.'
    emit('located')
  }
  catch {
    locationError.value = 'Address could not be located. Select the position manually on the map.'
  }
  finally {
    isGeocoding.value = false
  }
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    locationError.value = 'Current location is not available in this browser.'

    return
  }

  isLocating.value = true
  locationError.value = ''
  navigator.geolocation.getCurrentPosition(
    position => {
      setCoordinates(position.coords.latitude, position.coords.longitude)
      locationMessage.value = 'Current location selected.'
      isLocating.value = false
    },
    () => {
      locationError.value = 'Current location could not be accessed. Check browser permissions or select a position manually.'
      isLocating.value = false
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
  )
}

const initializeMap = () => {
  const center = coordinates.value || fallbackCenter

  map.value = L.map(mapContainer.value).setView(center, coordinates.value ? 16 : 5)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map.value)
  if (!props.readonly) {
    map.value.on('click', event => {
      setCoordinates(event.latlng.lat, event.latlng.lng)
      reverseGeocode(event.latlng.lat, event.latlng.lng)
    })
  }

  if (coordinates.value)
    setCoordinates(...coordinates.value, false)
}

watch(coordinates, value => {
  if (value && map.value)
    setCoordinates(...value)
})

watch(() => props.isVisible, visible => {
  if (visible && map.value)
    nextTick(() => map.value.invalidateSize())
})

defineExpose({ locateAddress, useCurrentLocation, isGeocoding, isLocating })

onMounted(() => nextTick(initializeMap))

onBeforeUnmount(() => map.value?.remove())
</script>

<template>
  <div class="location-picker">
    <div
      ref="mapContainer"
      class="location-picker__map"
      :style="{ height: `${height}px` }"
    />
    <VAlert
      v-if="locationError"
      type="warning"
      variant="tonal"
      density="compact"
      class="mt-2"
    >
      {{ locationError }}
    </VAlert>
    <div
      v-if="locationMessage"
      class="text-body-2 text-medium-emphasis mt-2"
    >
      {{ locationMessage }}
    </div>
    <div class="text-caption text-medium-emphasis mt-2">
      Map data &copy; OpenStreetMap contributors
    </div>
  </div>
</template>

<style scoped>
.location-picker__map {
  overflow: hidden;
  border-radius: 6px;
  background: rgb(var(--v-theme-surface-variant));
  min-block-size: 220px;
}
</style>
