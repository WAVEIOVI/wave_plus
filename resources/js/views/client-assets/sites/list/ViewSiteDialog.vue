<script setup>
/* eslint-disable camelcase */
/**
 * ViewSiteDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * WAVE PLUS Standard Client Site View Dialog.
 * Clones the architecture of ViewClientDialog:
 * - Uses WpDetailDialog with size="xlarge"
 * - Header with avatar, site code/chips subtitle, analytics & edit actions
 * - Desktop sticky sidebar navigation with PerfectScrollbar (WpJournalNav)
 * - Read-only glassmorphic sections with detail-rows, KPIs, areas tree,
 *   equipment asset list, photography gallery, and technical documents
 * - Integrated Leaflet GPS map preview and subdialog openers.
 */
import {
  WpAddressSiteCard,
  WpDocumentList,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpMediaGallery,
  WpPartnerCard,
} from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import {
  documentCategories,
  facilityTypeCards,
  operatingStatusOptions,
  resolveAreaTypeIcon,
  resolveRiskVariant,
  resolveSiteStatusVariant,
  resolveSiteTypeVariant,
  riskClassOptions,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import PartnerViewDialogSkeleton from '@/views/business-partners/shared-components/components/PartnerViewDialogSkeleton.vue'
import ViewAddressDialog from '@/views/business-partners/shared-components/dialogs/ViewAddressDialog.vue'
import EquipmentViewModal from '@/views/client-assets/equipment/EquipmentViewModal.vue'
import SiteAnalyticsDialog from '@/views/client-assets/sites/list/SiteAnalyticsDialog.vue'
import { avatarText } from '@core/utils/formatters'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {
  computed,
  nextTick,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  isDialogVisible: {
    type: Boolean,
    default: undefined,
  },
  siteId: {
    type: [Number, String],
    default: null,
  },
  site: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object],
    default: null,
  },
})

const emit = defineEmits([
  'update:modelValue',
  'update:isDialogVisible',
  'updated',
  'edit',
  'retry',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { optionTitle } = useSiteConstants()

// ── Dialog Visibility Model ──────────────────────────────────────────────────
const visible = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
  },
})

// ── Internal State ───────────────────────────────────────────────────────────
const internalSite = ref(null)
const siteAreas = ref([])
const siteEquipment = ref([])
const loadingInternal = ref(false)
const internalError = ref(null)
const activeSection = ref('bio')

// Subdialogs
const isAnalyticsDialogOpen = ref(false)
const isClientDialogOpen = ref(false)
const isAddressDialogOpen = ref(false)
const isEquipmentModalOpen = ref(false)
const selectedEquipmentId = ref(null)
const isMediaViewerOpen = ref(false)
const mediaViewerItems = ref([])
const mediaViewerInitialIndex = ref(0)
const mediaViewerTitle = ref('')

// Leaflet Map
const mapContainer = ref(null)
const leafletMap = ref(null)
const mapMarker = ref(null)

// ── Resolved Site Data ───────────────────────────────────────────────────────
const activeSite = computed(() => internalSite.value || props.site)
const targetSiteId = computed(() => props.siteId || props.site?.id || internalSite.value?.id)
const isEffectiveLoading = computed(() => (props.loading || loadingInternal.value) && !activeSite.value)
const effectiveError = computed(() => props.error || internalError.value)

const currentFacilityTypeObj = computed(() => {
  const val = activeSite.value?.site_type || activeSite.value?.facility_type
  
  return facilityTypeCards.find(c => c.value === val) || {
    value: 'other',
    title: 'Other Facility',
    desc: 'Specialized or multipurpose premises',
    icon: 'tabler-building-estate',
    color: 'primary',
  }
})

const clientLegalName = computed(() => {
  const c = activeSite.value?.client
  if (!c) return ''
  
  return localizedValue(c.legal_name || c.commercial_name || c.trade_name) || c.legal_name || ''
})

const allDocuments = computed(() => {
  const docs = activeSite.value?.documents || []
  const plans = activeSite.value?.site_plans || []
  
  return [...docs, ...plans.map(p => ({ ...p, category: 'site_plans' }))]
})

// ── Navigation Sections ──────────────────────────────────────────────────────
const sections = [
  { id: 'bio', label: 'Facility Profile', icon: 'tabler-building-estate' },
  { id: 'client-address', label: 'Client & Address', icon: 'tabler-map-pin' },
  { id: 'parameters', label: 'Status & Parameters', icon: 'tabler-adjustments-horizontal' },
  { id: 'instructions', label: 'Access & Safety', icon: 'tabler-shield-lock' },
  { id: 'areas', label: 'Zones & Areas', icon: 'tabler-hierarchy' },
  { id: 'equipment', label: 'Installed Equipment', icon: 'tabler-fire-extinguisher' },
  { id: 'media', label: 'Photos & Documents', icon: 'tabler-photo' },
  { id: 'history', label: 'Activity & Audit', icon: 'tabler-history' },
]

const summaryItems = computed(() => [
  { icon: 'tabler-hierarchy', label: t('Areas'), count: siteAreas.value.length },
  { icon: 'tabler-fire-extinguisher', label: t('Equipment'), count: siteEquipment.value.length },
  { icon: 'tabler-photo', label: t('Photos'), count: (activeSite.value?.photos || []).length },
  { icon: 'tabler-file-text', label: t('Docs'), count: allDocuments.value.length },
])

// ── Bio Detail Grid ──────────────────────────────────────────────────────────
const bioDetails = computed(() => {
  const s = activeSite.value
  if (!s) return []

  const formattedAddress = [
    localizedValue(s.address?.address_street),
    localizedValue(s.address?.city),
    s.address?.country,
  ].filter(Boolean).join(', ')

  return [
    { icon: 'tabler-hash', label: t('Site Code'), value: s.site_code },
    { icon: currentFacilityTypeObj.value.icon || 'tabler-building', label: t('Facility Type'), value: t(currentFacilityTypeObj.value.title) },
    { icon: 'tabler-circle-check', label: t('Operating Status'), value: optionTitle(operatingStatusOptions, s.status) },
    { icon: 'tabler-shield-check', label: t('Safety Risk Class'), value: optionTitle(riskClassOptions, s.risk_class) },
    { icon: 'tabler-building-skyscraper', label: t('Client'), value: clientLegalName.value },
    { icon: 'tabler-calendar', label: t('Commissioned / Opened'), value: s.opened_at },
    { icon: 'tabler-calendar-off', label: t('Decommissioned / Closed'), value: s.closed_at },
    { icon: 'tabler-clock', label: t('Operating Hours'), value: s.operating_hours },
    { icon: 'tabler-phone', label: t('Primary Phone'), value: s.client?.primary_phone || s.address?.phone },
    { icon: 'tabler-mail', label: t('Primary Email'), value: s.client?.primary_email || s.address?.email },
    { icon: 'tabler-map-pin', label: t('Location'), value: formattedAddress },
  ].filter(i => i.value)
})

// ── Equipment Stats ──────────────────────────────────────────────────────────
const equipmentKpis = computed(() => {
  const list = siteEquipment.value
  const operational = list.filter(e => ['operational', 'active'].includes(e.status?.toLowerCase())).length
  const maintenance = list.filter(e => ['under_maintenance', 'maintenance'].includes(e.status?.toLowerCase())).length
  const other = list.length - operational - maintenance

  return {
    total: list.length,
    operational,
    maintenance,
    other: Math.max(0, other),
  }
})

// ── Map Integration ──────────────────────────────────────────────────────────
const initMap = () => {
  if (!mapContainer.value) return
  if (leafletMap.value) {
    leafletMap.value.remove()
    leafletMap.value = null
  }

  const lat = activeSite.value?.address?.latitude
  const lng = activeSite.value?.address?.longitude
  const hasCoords = Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
  const center = hasCoords ? [Number(lat), Number(lng)] : [36.8065, 10.1815]
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
      attribution: '&copy; OpenStreetMap',
    }).addTo(leafletMap.value)

    if (hasCoords) {
      const addressText = localizedValue(activeSite.value?.address?.address_street) || localizedValue(activeSite.value?.name) || t('Site Location')
      const cityText = localizedValue(activeSite.value?.address?.city) || ''

      const popupEl = document.createElement('div')
      const popupTitle = document.createElement('strong')

      popupTitle.textContent = activeSite.value?.name || t('Site Location')
      popupEl.append(popupTitle, document.createElement('br'), document.createTextNode(`${addressText}${cityText ? `, ${cityText}` : ''}`))

      mapMarker.value = L.marker([Number(lat), Number(lng)])
        .addTo(leafletMap.value)
        .bindPopup(popupEl)
    }
  }
  catch (e) {
    console.error('Error initializing Leaflet map:', e)
  }
}

// ── Data Fetching ────────────────────────────────────────────────────────────
const loadSiteData = async id => {
  if (!id) return
  loadingInternal.value = true
  internalError.value = null
  try {
    const [siteRes, areasRes, eqRes] = await Promise.all([
      store.fetchSite(id),
      store.fetchSiteAreas(id).catch(() => ({ flat: [] })),
      store.fetchEquipment({ site_id: id, itemsPerPage: -1 }).catch(() => ({ data: [] })),
    ])

    internalSite.value = siteRes
    siteAreas.value = areasRes?.flat || []
    siteEquipment.value = eqRes?.data || []

    nextTick(() => {
      setTimeout(() => {
        initMap()
        leafletMap.value?.invalidateSize()
      }, 250)
    })
  }
  catch (err) {
    console.error('Failed to load site details:', err)
    internalError.value = err?.data?.message || err?.message || t('Failed to load site details')
  }
  finally {
    loadingInternal.value = false
  }
}

watch(
  () => [props.modelValue, props.isDialogVisible, targetSiteId.value],
  ([mv, idv, sid]) => {
    const isShowing = idv !== undefined ? idv : mv
    if (isShowing) {
      activeSection.value = 'bio'
      if (props.site && !internalSite.value) {
        internalSite.value = props.site
      }
      if (sid) {
        loadSiteData(sid)
      }
    }
    else {
      internalSite.value = null
      siteAreas.value = []
      siteEquipment.value = []
      internalError.value = null
      if (leafletMap.value) {
        leafletMap.value.remove()
        leafletMap.value = null
      }
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (leafletMap.value) {
    leafletMap.value.remove()
    leafletMap.value = null
  }
})

// ── Handlers ─────────────────────────────────────────────────────────────────
const handleViewEquipment = eq => {
  selectedEquipmentId.value = eq?.id || eq
  isEquipmentModalOpen.value = true
}

const openPhotoPreview = photo => {
  const photos = activeSite.value?.photos || []

  mediaViewerItems.value = photos

  const idx = photos.findIndex(p => p.id === photo?.id || p.url === photo?.url)

  mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  mediaViewerTitle.value = t('Facility Photography')
  isMediaViewerOpen.value = true
}

const openDocumentPreview = doc => {
  const docs = allDocuments.value

  mediaViewerItems.value = docs

  const idx = docs.findIndex(d => d.id === doc?.id)

  mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  mediaViewerTitle.value = doc.title || doc.file_name || t('Document')
  isMediaViewerOpen.value = true
}

const handleDownloadDocument = async doc => {
  if (!targetSiteId.value || !doc?.id) return
  try {
    await store.downloadSiteMedia(targetSiteId.value, doc.id, doc.file_name || doc.title)
    snackbarStore.showMessage(t('Document downloaded successfully'), 'success')
  }
  catch {
    snackbarStore.showMessage(t('Failed to download document'), 'error')
  }
}
</script>

<template>
  <WpDetailDialog
    :model-value="visible"
    :title="activeSite?.name || activeSite?.site_code || $t('Site Preview')"
    size="xlarge"
    :loading="isEffectiveLoading"
    :error="effectiveError"
    @update:model-value="visible = $event"
    @retry="targetSiteId ? loadSiteData(targetSiteId) : emit('retry')"
  >
    <!-- 👉 Loading Skeleton -->
    <template #loading>
      <PartnerViewDialogSkeleton />
    </template>

    <!-- 👉 Header Avatar -->
    <template #avatar>
      <VAvatar
        rounded
        :size="smAndDown ? 36 : 44"
        variant="tonal"
        :color="resolveSiteTypeVariant(activeSite?.site_type || activeSite?.facility_type)"
      >
        <VImg
          v-if="activeSite?.main_photo_url || activeSite?.photos?.[0]?.url"
          :src="activeSite?.main_photo_url || activeSite?.photos?.[0]?.url"
          alt=""
        />
        <VIcon
          v-else
          :icon="currentFacilityTypeObj.icon || 'tabler-building-estate'"
          size="22"
        />
      </VAvatar>
    </template>

    <!-- 👉 Header Subtitle with Standard Chips -->
    <template #subtitle>
      <div class="d-flex align-center flex-wrap gap-1">
        <VChip
          v-if="activeSite?.site_code"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ activeSite.site_code }}
        </VChip>
        <span
          v-if="clientLegalName"
          class="text-truncate text-caption text-medium-emphasis"
        >
          {{ clientLegalName }}
        </span>
        <VChip
          v-if="activeSite?.site_type || activeSite?.facility_type"
          size="x-small"
          label
          color="info"
          variant="tonal"
          class="text-capitalize"
        >
          {{ $t(currentFacilityTypeObj.title) }}
        </VChip>
        <VChip
          v-if="activeSite?.status"
          size="x-small"
          label
          :color="resolveSiteStatusVariant(activeSite.status)"
          class="text-capitalize"
        >
          {{ optionTitle(operatingStatusOptions, activeSite.status) }}
        </VChip>
        <VChip
          v-if="activeSite?.risk_class"
          size="x-small"
          label
          :color="resolveRiskVariant(activeSite.risk_class)"
          class="text-capitalize"
        >
          {{ optionTitle(riskClassOptions, activeSite.risk_class) }}
        </VChip>
      </div>
    </template>

    <!-- 👉 Header Context Actions -->
    <template #actions>
      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="activeSite?.id"
          variant="tonal"
          size="small"
          color="info"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-chart-pie-2'"
          :aria-label="$t('Site Analytics')"
          @click="isAnalyticsDialogOpen = true"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-chart-pie-2"
            size="18"
          />
          <span v-else>{{ $t('Analytics') }}</span>
        </VBtn>

        <VBtn
          v-if="activeSite?.id"
          variant="tonal"
          size="small"
          color="primary"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit Site')"
          :to="{ name: 'client-assets-sites-edit-id', params: { id: activeSite.id } }"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-edit"
            size="18"
          />
          <span v-else>{{ $t('Edit') }}</span>
        </VBtn>
      </div>
    </template>

    <!-- ═══ Main Mirror Content Layout ═══ -->
    <div
      v-if="activeSite"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-site-section-"
        container=".wp-detail-dialog__scroll"
      />

      <!-- ═══ Journal Feed ═══ -->
      <main class="dialog-journal-feed">
        <!-- ━━━ 1. Bio / Facility Profile ━━━ -->
        <section
          id="dialog-site-section-bio"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="text-center pt-8 pb-4">
              <VAvatar
                rounded="lg"
                :size="92"
                :color="!activeSite.main_photo_url && !activeSite.photos?.[0]?.url ? 'primary' : undefined"
                :variant="!activeSite.main_photo_url && !activeSite.photos?.[0]?.url ? 'tonal' : undefined"
                class="elevation-3 border"
              >
                <VImg
                  v-if="activeSite.main_photo_url || activeSite.photos?.[0]?.url"
                  :src="activeSite.main_photo_url || activeSite.photos[0].url"
                  cover
                />
                <VIcon
                  v-else
                  :icon="currentFacilityTypeObj.icon || 'tabler-building-estate'"
                  size="44"
                />
              </VAvatar>

              <h5 class="text-h5 mt-3 font-weight-bold">
                {{ activeSite.name || activeSite.site_code }}
              </h5>
              <div
                v-if="clientLegalName"
                class="text-body-2 text-medium-emphasis mt-1"
              >
                {{ clientLegalName }}
              </div>

              <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
                <VChip
                  label
                  size="small"
                  :color="resolveSiteStatusVariant(activeSite.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ optionTitle(operatingStatusOptions, activeSite.status) }}
                </VChip>
                <VChip
                  label
                  size="small"
                  :color="currentFacilityTypeObj.color"
                  class="text-capitalize font-weight-medium"
                >
                  {{ $t(currentFacilityTypeObj.title) }}
                </VChip>
                <VChip
                  v-if="activeSite.risk_class"
                  label
                  size="small"
                  :color="resolveRiskVariant(activeSite.risk_class)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ optionTitle(riskClassOptions, activeSite.risk_class) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in bioDetails"
                  :key="item.label"
                  cols="12"
                  sm="6"
                >
                  <div class="detail-row">
                    <VAvatar
                      variant="tonal"
                      color="primary"
                      size="34"
                      rounded
                    >
                      <VIcon
                        :icon="item.icon"
                        size="18"
                      />
                    </VAvatar>
                    <div class="min-w-0">
                      <span class="text-caption text-disabled text-uppercase font-weight-medium d-block">
                        {{ item.label }}
                      </span>
                      <span class="text-body-2 font-weight-medium text-truncate d-block">
                        {{ item.value }}
                      </span>
                    </div>
                  </div>
                </VCol>
              </VRow>

              <div
                v-if="activeSite.description"
                class="mt-4 pt-4 border-t"
              >
                <span class="text-caption text-disabled text-uppercase font-weight-medium d-block mb-1">
                  {{ $t('Facility Description') }}
                </span>
                <p class="text-body-2 text-medium-emphasis mb-0 whitespace-pre-line">
                  {{ activeSite.description }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 2. Client & Address Details ━━━ -->
        <section
          id="dialog-site-section-client-address"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-map-pin"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Client & Physical Location') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Contracting business partner and GIS operational coordinates') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <VRow class="mb-3">
                <!-- Client Card -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <WpPartnerCard
                    :partner="activeSite.client"
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
                    @view="isClientDialogOpen = true"
                  />
                </VCol>

                <!-- Address Card -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <WpAddressSiteCard
                    :address="activeSite.address"
                    :title="$t('Site Physical Address')"
                    :can-edit="false"
                    :can-change="false"
                    can-view
                    :show-sites="false"
                    :expandable="false"
                    class="h-100"
                    @view="isAddressDialogOpen = true"
                  />
                </VCol>

                <!-- Leaflet GIS Map -->
                <VCol cols="12">
                  <div class="position-relative rounded-lg overflow-hidden border map-wrapper">
                    <div
                      ref="mapContainer"
                      class="leaflet-map-preview"
                    />
                    <div class="map-badge-overlay d-flex gap-2">
                      <VChip
                        v-if="activeSite.address?.latitude && activeSite.address?.longitude"
                        color="primary"
                        size="small"
                        variant="elevated"
                        prepend-icon="tabler-current-location"
                      >
                        {{ Number(activeSite.address.latitude).toFixed(4) }}, {{ Number(activeSite.address.longitude).toFixed(4) }}
                      </VChip>
                      <VBtn
                        v-if="activeSite.address?.latitude && activeSite.address?.longitude"
                        size="small"
                        color="surface"
                        variant="elevated"
                        prepend-icon="tabler-external-link"
                        :href="`https://www.openstreetmap.org/?mlat=${activeSite.address.latitude}&mlon=${activeSite.address.longitude}#map=16/${activeSite.address.latitude}/${activeSite.address.longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        {{ $t('Open in Maps') }}
                      </VBtn>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 3. Status & Operational Parameters ━━━ -->
        <section
          id="dialog-site-section-parameters"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-adjustments-horizontal"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Status & Operational Parameters') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Operating lifecycle status, compliance rating, and facility shift timings') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard
                    variant="outlined"
                    class="h-100 pa-4 rounded-lg"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        :color="resolveSiteStatusVariant(activeSite.status)"
                        variant="tonal"
                        size="36"
                        rounded
                      >
                        <VIcon
                          icon="tabler-circle-check"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Operating Status') }}</span>
                        <span class="text-body-1 font-weight-bold">{{ optionTitle(operatingStatusOptions, activeSite.status) }}</span>
                      </div>
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0">
                      {{ operatingStatusOptions.find(o => o.value === activeSite.status)?.description || $t('Fully commissioned and in regular service') }}
                    </p>
                  </VCard>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard
                    variant="outlined"
                    class="h-100 pa-4 rounded-lg"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        :color="resolveRiskVariant(activeSite.risk_class)"
                        variant="tonal"
                        size="36"
                        rounded
                      >
                        <VIcon
                          icon="tabler-shield-check"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Safety Risk Classification') }}</span>
                        <span class="text-body-1 font-weight-bold">{{ optionTitle(riskClassOptions, activeSite.risk_class) }}</span>
                      </div>
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0">
                      {{ riskClassOptions.find(o => o.value === activeSite.risk_class)?.description || $t('Standard safety compliance environment') }}
                    </p>
                  </VCard>
                </VCol>

                <VCol cols="12">
                  <VCard
                    variant="outlined"
                    class="pa-4 rounded-lg"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        color="info"
                        variant="tonal"
                        size="36"
                        rounded
                      >
                        <VIcon
                          icon="tabler-clock"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Operating Hours & Shifts') }}</span>
                        <span class="text-body-1 font-weight-bold">{{ activeSite.operating_hours || $t('Standard Business Hours (08:00 - 18:00)') }}</span>
                      </div>
                    </div>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 4. Access & Safety Guidelines ━━━ -->
        <section
          id="dialog-site-section-instructions"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-shield-lock"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Access, Safety & Emergency Protocols') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Contractor guidelines, required PPE gear, and emergency evacuation procedures') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <VRow>
                <!-- Access Protocols -->
                <VCol cols="12">
                  <VCard
                    variant="outlined"
                    class="rounded-lg pa-4"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="34"
                        rounded
                      >
                        <VIcon
                          icon="tabler-key"
                          size="18"
                        />
                      </VAvatar>
                      <h6 class="text-subtitle-2 font-weight-bold mb-0">
                        {{ $t('Access & Check-In Directives') }}
                      </h6>
                    </div>
                    <p
                      v-if="activeSite.access_instructions"
                      class="text-body-2 text-medium-emphasis mb-0 whitespace-pre-line"
                    >
                      {{ activeSite.access_instructions }}
                    </p>
                    <span
                      v-else
                      class="text-caption text-disabled font-italic"
                    >
                      {{ $t('No specific visitor badge or entry instructions configured.') }}
                    </span>
                  </VCard>
                </VCol>

                <!-- Safety & PPE Rules -->
                <VCol cols="12">
                  <VCard
                    variant="outlined"
                    class="rounded-lg pa-4"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        color="warning"
                        variant="tonal"
                        size="34"
                        rounded
                      >
                        <VIcon
                          icon="tabler-jacket"
                          size="18"
                        />
                      </VAvatar>
                      <h6 class="text-subtitle-2 font-weight-bold mb-0">
                        {{ $t('Safety Instructions & Mandatory PPE') }}
                      </h6>
                    </div>
                    <p
                      v-if="activeSite.safety_instructions"
                      class="text-body-2 text-medium-emphasis mb-0 whitespace-pre-line"
                    >
                      {{ activeSite.safety_instructions }}
                    </p>
                    <span
                      v-else
                      class="text-caption text-disabled font-italic"
                    >
                      {{ $t('Standard PPE rules apply (safety boots, high-vis vest where required).') }}
                    </span>
                  </VCard>
                </VCol>

                <!-- Special / Emergency Instructions -->
                <VCol cols="12">
                  <VCard
                    variant="outlined"
                    class="rounded-lg pa-4"
                  >
                    <div class="d-flex align-center gap-3 mb-2">
                      <VAvatar
                        color="error"
                        variant="tonal"
                        size="34"
                        rounded
                      >
                        <VIcon
                          icon="tabler-alert-triangle"
                          size="18"
                        />
                      </VAvatar>
                      <h6 class="text-subtitle-2 font-weight-bold mb-0">
                        {{ $t('Emergency Directives & Hazard Warnings') }}
                      </h6>
                    </div>
                    <p
                      v-if="activeSite.special_instructions"
                      class="text-body-2 text-medium-emphasis mb-0 whitespace-pre-line"
                    >
                      {{ activeSite.special_instructions }}
                    </p>
                    <span
                      v-else
                      class="text-caption text-disabled font-italic"
                    >
                      {{ $t('Follow on-site safety evacuation signals and report to designated assembly areas.') }}
                    </span>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 5. Zones & Building Areas ━━━ -->
        <section
          id="dialog-site-section-areas"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-hierarchy"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Zones & Building Areas') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Structured facility zones, buildings, floors, and compartments') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <!-- Areas KPI Group -->
              <WpKpiGroup
                :cols="{ xs: 2, sm: 2, md: 3, lg: 3 }"
                class="mb-4"
              >
                <template #default="{ getColProps }">
                  <VCol v-bind="getColProps(0)">
                    <WpKpiCard
                      :title="$t('Total Zones')"
                      :value="siteAreas.length"
                      icon="tabler-hierarchy"
                      icon-color="info"
                    />
                  </VCol>
                  <VCol v-bind="getColProps(1)">
                    <WpKpiCard
                      :title="$t('Root Buildings / Zones')"
                      :value="siteAreas.filter(a => !a.parent_area_id).length"
                      icon="tabler-building"
                      icon-color="primary"
                    />
                  </VCol>
                  <VCol v-bind="getColProps(2)">
                    <WpKpiCard
                      :title="$t('Sub-Areas / Rooms')"
                      :value="siteAreas.filter(a => a.parent_area_id).length"
                      icon="tabler-door"
                      icon-color="success"
                    />
                  </VCol>
                </template>
              </WpKpiGroup>

              <!-- Areas List Table -->
              <div
                v-if="siteAreas.length"
                class="border rounded-lg overflow-hidden"
              >
                <VTable
                  density="compact"
                  hover
                >
                  <thead>
                    <tr>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Area Name') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Type') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Area Code') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold text-center">
                        {{ $t('Equipment') }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="area in siteAreas"
                      :key="area.id"
                    >
                      <td>
                        <div class="d-flex align-center gap-2 py-1">
                          <VIcon
                            :icon="resolveAreaTypeIcon(area.area_type)"
                            size="18"
                            color="primary"
                          />
                          <span class="font-weight-medium text-body-2">{{ area.name }}</span>
                        </div>
                      </td>
                      <td>
                        <VChip
                          size="x-small"
                          label
                          color="secondary"
                          class="text-capitalize"
                        >
                          {{ area.area_type || 'area' }}
                        </VChip>
                      </td>
                      <td>
                        <span class="text-caption text-medium-emphasis">{{ area.area_code || '—' }}</span>
                      </td>
                      <td class="text-center">
                        <VChip
                          size="x-small"
                          label
                          :color="area.equipment_count ? 'primary' : 'default'"
                          variant="tonal"
                        >
                          {{ area.equipment_count || 0 }}
                        </VChip>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>

              <div
                v-else
                class="text-center py-8 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-hierarchy-off"
                  size="36"
                  class="mb-2 text-disabled"
                />
                <p class="text-body-2 mb-0">
                  {{ $t('No specific building zones or sub-areas created for this facility.') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 6. Installed Equipment Assets ━━━ -->
        <section
          id="dialog-site-section-equipment"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-fire-extinguisher"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Installed Equipment Assets') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Physical asset inventory installed and operating on this site') }}
              </VCardSubtitle>
              <template #append>
                <VBtn
                  variant="tonal"
                  size="small"
                  color="warning"
                  prepend-icon="tabler-chart-pie-2"
                  @click="isAnalyticsDialogOpen = true"
                >
                  {{ $t('View Analytics') }}
                </VBtn>
              </template>
            </VCardItem>

            <VCardText>
              <!-- Equipment KPIs -->
              <WpKpiGroup
                :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
                class="mb-4"
              >
                <template #default="{ getColProps }">
                  <VCol v-bind="getColProps(0)">
                    <WpKpiCard
                      :title="$t('Total Installed')"
                      :value="equipmentKpis.total"
                      icon="tabler-fire-extinguisher"
                      icon-color="warning"
                    />
                  </VCol>
                  <VCol v-bind="getColProps(1)">
                    <WpKpiCard
                      :title="$t('Operational')"
                      :value="equipmentKpis.operational"
                      icon="tabler-circle-check"
                      icon-color="success"
                    />
                  </VCol>
                  <VCol v-bind="getColProps(2)">
                    <WpKpiCard
                      :title="$t('Maintenance')"
                      :value="equipmentKpis.maintenance"
                      icon="tabler-tool"
                      icon-color="warning"
                    />
                  </VCol>
                  <VCol v-bind="getColProps(3)">
                    <WpKpiCard
                      :title="$t('Other / Offline')"
                      :value="equipmentKpis.other"
                      icon="tabler-alert-circle"
                      icon-color="secondary"
                    />
                  </VCol>
                </template>
              </WpKpiGroup>

              <!-- Equipment Table -->
              <div
                v-if="siteEquipment.length"
                class="border rounded-lg overflow-hidden"
              >
                <VTable
                  density="compact"
                  hover
                >
                  <thead>
                    <tr>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Asset / Tag') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Model / Class') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Zone Location') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold">
                        {{ $t('Status') }}
                      </th>
                      <th class="text-uppercase text-caption font-weight-bold text-end">
                        {{ $t('Action') }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="eq in siteEquipment"
                      :key="eq.id"
                    >
                      <td>
                        <div class="py-1">
                          <span class="font-weight-bold text-body-2 d-block">{{ eq.serial_number || eq.asset_tag || eq.id }}</span>
                          <span class="text-caption text-disabled">{{ eq.barcode || eq.current_qr_code?.code || '—' }}</span>
                        </div>
                      </td>
                      <td>
                        <div class="py-1">
                          <span class="font-weight-medium text-body-2 d-block">{{ localizedValue(eq.blueprint?.name) || eq.blueprint?.name || eq.name || $t('Equipment') }}</span>
                          <span class="text-caption text-disabled">{{ eq.blueprint?.model || eq.blueprint?.brand?.name || '' }}</span>
                        </div>
                      </td>
                      <td>
                        <span class="text-body-2">{{ eq.area?.name || '—' }}</span>
                      </td>
                      <td>
                        <VChip
                          size="x-small"
                          label
                          :color="resolveSiteStatusVariant(eq.status)"
                          class="text-capitalize"
                        >
                          {{ eq.status || 'active' }}
                        </VChip>
                      </td>
                      <td class="text-end">
                        <IconBtn
                          size="small"
                          variant="tonal"
                          color="primary"
                          :title="$t('View Asset Details')"
                          :aria-label="$t('View Asset Details')"
                          @click="handleViewEquipment(eq)"
                        >
                          <VIcon
                            icon="tabler-eye"
                            size="16"
                          />
                        </IconBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>

              <div
                v-else
                class="text-center py-8 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-fire-extinguisher"
                  size="36"
                  class="mb-2 text-disabled"
                />
                <p class="text-body-2 mb-0">
                  {{ $t('No equipment assets currently mapped to this facility.') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 7. Photos & Technical Documents ━━━ -->
        <section
          id="dialog-site-section-media"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-photo"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Photography & Technical Documents') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Premises photography, evacuation maps, blueprints, and compliance attachments') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <div class="mb-6">
                <WpMediaGallery
                  :items="activeSite.photos || []"
                  :title="$t('Facility Photography')"
                  icon="tabler-camera"
                  read-only
                  :empty-title="$t('No photography uploaded for this facility')"
                />
              </div>

              <VDivider class="my-4" />

              <WpDocumentList
                :documents="allDocuments"
                :title="$t('Technical Documents & Floor Plans')"
                icon="tabler-file-text"
                compact-header
                :document-types="documentCategories"
                read-only
                :empty-title="$t('No technical documents attached to this site')"
                @download="handleDownloadDocument"
              />
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 8. Activity & Audit History ━━━ -->
        <section
          id="dialog-site-section-history"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="secondary"
                  variant="tonal"
                  size="38"
                  rounded
                >
                  <VIcon
                    icon="tabler-history"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ $t('Site Activity & Audit Record') }}
              </VCardTitle>
              <VCardSubtitle>
                {{ $t('Record creation, modification history, and operational timeline') }}
              </VCardSubtitle>
            </VCardItem>

            <VCardText>
              <VRow dense>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="detail-row">
                    <VAvatar
                      variant="tonal"
                      color="primary"
                      size="34"
                      rounded
                    >
                      <VIcon
                        icon="tabler-calendar-plus"
                        size="18"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption text-disabled text-uppercase font-weight-medium d-block">
                        {{ $t('Created At') }}
                      </span>
                      <span class="text-body-2 font-weight-medium">
                        {{ activeSite.created_at || '—' }}
                      </span>
                    </div>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="detail-row">
                    <VAvatar
                      variant="tonal"
                      color="info"
                      size="34"
                      rounded
                    >
                      <VIcon
                        icon="tabler-calendar-time"
                        size="18"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption text-disabled text-uppercase font-weight-medium d-block">
                        {{ $t('Last Updated') }}
                      </span>
                      <span class="text-body-2 font-weight-medium">
                        {{ activeSite.updated_at || '—' }}
                      </span>
                    </div>
                  </div>
                </VCol>

                <VCol
                  v-if="activeSite.notes"
                  cols="12"
                  class="mt-3 pt-3 border-t"
                >
                  <span class="text-caption text-disabled text-uppercase font-weight-medium d-block mb-1">
                    {{ $t('Operational Notes') }}
                  </span>
                  <p class="text-body-2 text-medium-emphasis mb-0 whitespace-pre-line">
                    {{ activeSite.notes }}
                  </p>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>
      </main>
    </div>

    <!-- ═══ Child Subdialogs ═══ -->
    <SiteAnalyticsDialog
      v-model="isAnalyticsDialogOpen"
      :site-id="targetSiteId"
      :site="activeSite"
    />

    <EquipmentViewModal
      v-model="isEquipmentModalOpen"
      :equipment-id="selectedEquipmentId"
    />

    <ViewClientDialog
      v-if="activeSite?.client"
      v-model:is-dialog-visible="isClientDialogOpen"
      :client="activeSite.client"
    />

    <ViewAddressDialog
      v-if="activeSite?.address"
      v-model:is-dialog-visible="isAddressDialogOpen"
      :address="activeSite.address"
    />

    <WpMediaViewerDialog
      v-model="isMediaViewerOpen"
      :items="mediaViewerItems"
      :initial-index="mediaViewerInitialIndex"
      :title="mediaViewerTitle"
      @download="item => store.downloadSiteMedia(targetSiteId, item.id, item.file_name || item.name)"
    />
  </WpDetailDialog>
</template>

<style lang="scss" scoped>
/* ── Journal Layout Grid ─────────────────────────────────────────────── */
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  padding: 1.5rem;
  gap: 1.5rem;
  min-block-size: 500px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 0.75rem;
  }
}

/* ── Journal Feed ────────────────────────────────────────────────────── */
.dialog-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.dialog-journal-section {
  scroll-margin-block-start: 16px;
}

/* ── Glassmorphism Panels ────────────────────────────────────────────── */
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 4%),
    0 1px 4px rgba(0, 0, 0, 3%);
}

/* ── Detail Row ──────────────────────────────────────────────────────── */
.detail-row {
  display: flex;
  align-items: center;
  border-radius: 8px;
  gap: 12px;
  padding-block: 6px;
  padding-inline: 8px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

/* ── Photo Gallery Grid ──────────────────────────────────────────────── */
.photo-gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 12px;
}

.photo-gallery-item {
  position: relative;
  cursor: pointer;
  transition: transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.main-photo-badge {
  position: absolute;
  inset-block-start: 6px;
  inset-inline-start: 6px;
}

.photo-img {
  aspect-ratio: 1;
  inline-size: 100%;
  object-fit: cover;
}

/* ── Leaflet Map Preview ─────────────────────────────────────────────── */
.leaflet-map-preview {
  block-size: 260px;
  inline-size: 100%;
}

.map-wrapper {
  background: rgba(var(--v-theme-surface-variant), 0.5);
}

.map-badge-overlay {
  position: absolute;
  z-index: 400;
  inset-block-end: 12px;
  inset-inline-start: 12px;
}
</style>
