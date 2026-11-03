<script setup>
import { WpAddressSiteCard, WpPartnerCard, WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  site: { type: Object, default: null },
  form: { type: Object, required: true },
})

defineEmits(['viewClient', 'viewAddress'])

const { t } = useI18n()
const mapContainer = ref(null)
const leafletMap = ref(null)
const mapMarker = ref(null)

const initMap = () => {
  if (!mapContainer.value) return
  if (leafletMap.value) {
    leafletMap.value.remove()
    leafletMap.value = null
  }

  const lat = props.site?.address?.latitude
  const lng = props.site?.address?.longitude

  const hasCoords = Number.isFinite(lat) && Number.isFinite(lng)
  const center = hasCoords ? [lat, lng] : [36.8065, 10.1815]
  const zoom = hasCoords ? 15 : 6

  try {
    leafletMap.value = L.map(mapContainer.value, {
      center,
      zoom,
      zoomControl: true,
      scrollWheelZoom: false,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(leafletMap.value)

    if (hasCoords) {
      const addressText = localizedValue(props.site?.address?.address_street) || localizedValue(props.site?.name) || t('Site Location')
      const cityText = localizedValue(props.site?.address?.city) || ''

      const popupEl = document.createElement('div')
      const popupTitle = document.createElement('strong')

      popupTitle.textContent = props.form.name || t('Site Location')
      popupEl.append(
        popupTitle,
        document.createElement('br'),
        document.createTextNode(`${addressText}${cityText ? `, ${cityText}` : ''}`),
      )

      mapMarker.value = L.marker([lat, lng])
        .addTo(leafletMap.value)
        .bindPopup(popupEl)
        .openPopup()
    }
  }
  catch (e) {
    console.error('Error initializing Leaflet map:', e)
  }
}

onMounted(() => {
  nextTick(() => {
    initMap()
  })
})

watch(() => props.site?.address, () => {
  nextTick(() => {
    initMap()
  })
}, { deep: true })

onBeforeUnmount(() => {
  if (leafletMap.value) {
    leafletMap.value.remove()
    leafletMap.value = null
  }
})
</script>

<template>
  <section
    id="section-client-address"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Client Relationship & Physical Location')"
      :subtitle="$t('Contracting business partner, operational premises address, and GIS coordinates')"
      icon="tabler-map-pin"
      icon-color="info"
    >
      <VRow>
        <!-- Standard WpPartnerCard -->
        <VCol
          cols="12"
          md="6"
        >
          <WpPartnerCard
            :partner="site?.client"
            :partner-label="$t('Client')"
            type="client"
            :fields="{
              name: 'legal_name',
              tradeName: 'commercial_name',
              code: p => p?.client_code || p?.client_id || p?.code,
              status: 'status',
              category: 'industry',
              logo: p => p?.logo_url || p?.logo || p?.logo_external_url,
              logoSource: 'logo_source',
              logoDomain: 'logo_domain',
              email: 'primary_email',
              phone: 'primary_phone',
            }"
            :can-edit="false"
            :can-change="false"
            can-view
            class="h-100"
            @view="$emit('viewClient')"
          />
        </VCol>

        <!-- Standard WpAddressSiteCard -->
        <VCol
          cols="12"
          md="6"
        >
          <WpAddressSiteCard
            :address="site?.address"
            :title="$t('Site Physical Address')"
            :can-edit="false"
            :can-change="false"
            can-view
            :show-sites="false"
            :expandable="false"
            class="h-100"
            @view="$emit('viewAddress')"
          />
        </VCol>

        <!-- Map Preview -->
        <VCol cols="12">
          <div class="position-relative rounded-lg overflow-hidden border map-wrapper">
            <div
              ref="mapContainer"
              class="leaflet-map-preview"
            />
            <div class="map-badge-overlay d-flex gap-2">
              <VChip
                v-if="site?.address?.latitude && site?.address?.longitude"
                color="primary"
                size="small"
                variant="elevated"
                prepend-icon="tabler-current-location"
              >
                {{ site.address.latitude.toFixed(4) }}, {{ site.address.longitude.toFixed(4) }}
              </VChip>
              <VBtn
                v-if="site?.address?.latitude && site?.address?.longitude"
                size="small"
                color="surface"
                variant="elevated"
                prepend-icon="tabler-external-link"
                :href="`https://www.openstreetmap.org/?mlat=${site.address.latitude}&mlon=${site.address.longitude}#map=16/${site.address.latitude}/${site.address.longitude}`"
                target="_blank"
                rel="noopener noreferrer"
              >
                {{ $t('Open in Maps') }}
              </VBtn>
            </div>
          </div>
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.leaflet-map-preview {
  block-size: 280px;
  inline-size: 100%;
}

.map-wrapper {
  background: rgba(var(--v-theme-surface-variant), 0.5);
}

.map-badge-overlay {
  position: absolute;
  z-index: 1000;
  inset-block-start: 12px;
  inset-inline-end: 12px;
}
</style>
