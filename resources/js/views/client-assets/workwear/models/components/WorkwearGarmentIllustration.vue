<script setup>
import {
  computePlacementGeometry,
  getStudioTemplate,
  getTemplateZones,
  resolveColorHex,
  resolveModelColorRegions,
  resolvePlacementTypeMeta,
  resolveZoneMeta,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  silhouette: { type: String, default: 'coverall' },
  colors: { type: Array, default: () => ['navy', 'hi-vis-yellow'] },
  placements: { type: Array, default: () => [] },
  logos: { type: Array, default: () => [] },
  activeView: { type: String, default: 'front' },
  selectedPlacementIndex: { type: Number, default: -1 },
  readonly: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  showCallouts: { type: Boolean, default: true },
  allowFullscreenZoom: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:activeView',
  'update:selectedPlacementIndex',
  'zoneClick',
  'requestFullscreenZoom',
])

const { t } = useI18n()

// Gradient/filter ids must be unique per drawing, otherwise url(#id) resolves
// to whichever instance is first in the DOM (breaks when that one is hidden).
const uid = `wwg-${Math.random().toString(36).slice(2, 8)}`

const zoomLevel = ref(1)
const showGrid = ref(true)
const showZonesOverlay = ref(true)
const hoveredZone = ref(null)

const currentView = computed({
  get: () => props.activeView || 'front',
  set: val => emit('update:activeView', val),
})

const primaryColorHex = computed(() => resolveColorHex(props.colors?.[0], '#1e293b'))
const contrastColorHex = computed(() => resolveColorHex(props.colors?.[1], '#eab308'))

const activeTemplate = computed(() => getStudioTemplate(props.silhouette || 'coverall'))

const colorRegionMap = computed(() =>
  resolveModelColorRegions(props.colors, props.silhouette || 'coverall'),
)

function regionHex(regionKey, fallbackHex = null) {
  const colorName = colorRegionMap.value?.[regionKey]
  if (colorName) {
    return resolveColorHex(colorName, fallbackHex || primaryColorHex.value)
  }

  return fallbackHex || primaryColorHex.value
}

const viewZones = computed(() =>
  getTemplateZones(props.silhouette || 'coverall', currentView.value),
)

const viewPlacements = computed(() =>
  (props.placements || [])
    .map((p, idx) => ({ ...p, _originalIndex: idx }))
    .filter(p => (p.view || 'front') === currentView.value),
)

const activePlacement = computed(() => {
  if (props.selectedPlacementIndex >= 0 && props.placements?.[props.selectedPlacementIndex]) {
    return props.placements[props.selectedPlacementIndex]
  }

  return null
})

const highlightedSafeBounds = computed(() => {
  if (props.readonly || props.compact) return null
  if (hoveredZone.value?.safeBounds) {
    return hoveredZone.value.safeBounds
  }
  if (activePlacement.value && (activePlacement.value.view || 'front') === currentView.value) {
    const zMeta = resolveZoneMeta(activePlacement.value.zone, props.silhouette || 'coverall')

    return zMeta?.safeBounds || null
  }

  return null
})

const hudText = computed(() => {
  const scaleTag = activeTemplate.value?.scale?.scaleVerified
    ? t('Verified scale')
    : t('Proportional scale (unverified)')

  if (activePlacement.value) {
    const z = (activePlacement.value.zone || 'chest_left').replace(/_/g, ' ')
    const ox = Number(activePlacement.value.offset_x_mm || 0)
    const oy = Number(activePlacement.value.offset_y_mm || 0)
    const sx = ox >= 0 ? `+${ox}` : `${ox}`
    const sy = oy >= 0 ? `+${oy}` : `${oy}`

    return `${t('Zone')}: ${z}  |  X: ${sx}mm  Y: ${sy}mm  |  ${scaleTag}`
  }
  if (hoveredZone.value) {
    return `${t('Zone')}: ${hoveredZone.value.value.replace(/_/g, ' ')}  |  ${t('Safe area')}: ${Math.round(hoveredZone.value.maxWidthMm)}×${Math.round(hoveredZone.value.maxHeightMm)}mm`
  }

  return `${t(currentView.value === 'back' ? 'Back View' : 'Front View')}  |  ${(props.silhouette || 'coverall').replace(/_/g, ' ')}  |  ${scaleTag}`
})

function getPlacementCoords(placement) {
  return computePlacementGeometry(placement, props.silhouette || 'coverall')
}

function resolveLogoUrl(mediaId) {
  if (!mediaId || !props.logos?.length) return null
  const found = props.logos.find(l => String(l.id) === String(mediaId))

  return found ? (found.thumb || found.url) : null
}

function handleSelectPlacement(originalIndex) {
  emit('update:selectedPlacementIndex', originalIndex)
}

function handleZoneClick(zone) {
  if (props.readonly) return
  emit('zoneClick', zone)
}

function cycleZoom(delta) {
  const next = Math.round((zoomLevel.value + delta) * 100) / 100

  zoomLevel.value = Math.max(0.75, Math.min(1.25, next))
}
</script>

<template>
  <div
    class="workwear-garment-studio d-flex flex-column"
    :class="{ 'is-compact': compact, 'is-readonly': readonly }"
  >
    <!-- Top Studio Toolbar -->
    <div
      v-if="!compact"
      class="studio-toolbar d-flex flex-wrap align-center justify-space-between gap-2 px-3 py-2"
    >
      <div class="d-flex align-center gap-1">
        <VBtn
          size="small"
          :variant="currentView === 'front' ? 'flat' : 'tonal'"
          :color="currentView === 'front' ? 'primary' : 'secondary'"
          prepend-icon="tabler-shirt"
          @click="currentView = 'front'"
        >
          {{ $t('Front View') }}
        </VBtn>
        <VBtn
          size="small"
          :variant="currentView === 'back' ? 'flat' : 'tonal'"
          :color="currentView === 'back' ? 'primary' : 'secondary'"
          prepend-icon="tabler-rotate-clockwise-2"
          @click="currentView = 'back'"
        >
          {{ $t('Back View') }}
        </VBtn>
      </div>

      <div class="d-flex align-center gap-1">
        <VBtn
          icon
          size="x-small"
          variant="tonal"
          color="secondary"
          :disabled="zoomLevel <= 0.75"
          :title="$t('Zoom Out')"
          @click="cycleZoom(-0.25)"
        >
          <VIcon
            icon="tabler-zoom-out"
            size="16"
          />
        </VBtn>
        <span class="text-caption font-mono px-1">{{ Math.round(zoomLevel * 100) }}%</span>
        <VBtn
          icon
          size="x-small"
          variant="tonal"
          color="secondary"
          :disabled="zoomLevel >= 1.25"
          :title="$t('Zoom In')"
          @click="cycleZoom(0.25)"
        >
          <VIcon
            icon="tabler-zoom-in"
            size="16"
          />
        </VBtn>
        <VBtn
          icon
          size="x-small"
          :variant="showGrid ? 'flat' : 'tonal'"
          :color="showGrid ? 'primary' : 'secondary'"
          :title="$t('Toggle Studio Grid')"
          @click="showGrid = !showGrid"
        >
          <VIcon
            icon="tabler-grid-dots"
            size="16"
          />
        </VBtn>
        <VBtn
          v-if="!readonly"
          icon
          size="x-small"
          :variant="showZonesOverlay ? 'flat' : 'tonal'"
          :color="showZonesOverlay ? 'info' : 'secondary'"
          :title="$t('Toggle Placement Anchors')"
          @click="showZonesOverlay = !showZonesOverlay"
        >
          <VIcon
            icon="tabler-focus-2"
            size="16"
          />
        </VBtn>
        <VBtn
          v-if="allowFullscreenZoom"
          size="x-small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-arrows-maximize"
          class="ms-1"
          @click="emit('requestFullscreenZoom')"
        >
          {{ $t('Zoom Studio') }}
        </VBtn>
      </div>
    </div>

    <!-- Canvas Viewport -->
    <div
      class="studio-canvas-wrap position-relative d-flex align-center justify-center overflow-hidden"
      :class="{ 'grid-bg': showGrid && !compact }"
    >
      <!-- Compact view switcher pill -->
      <div
        v-if="compact"
        class="compact-view-toggle position-absolute"
      >
        <VBtn
          size="x-small"
          :variant="currentView === 'front' ? 'flat' : 'tonal'"
          color="primary"
          @click.stop="currentView = currentView === 'front' ? 'back' : 'front'"
        >
          {{ currentView === 'front' ? $t('Front') : $t('Back') }}
        </VBtn>
      </div>

      <!-- Technical SVG Garment Illustration -->
      <svg
        viewBox="0 0 320 420"
        class="garment-svg"
        :style="{ transform: `scale(${zoomLevel})` }"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <!-- Drop shadow for garment -->
          <filter
            :id="`${uid}-shadow`"
            x="-10%"
            y="-10%"
            width="120%"
            height="120%"
          >
            <feDropShadow
              dx="0"
              dy="6"
              stdDeviation="6"
              flood-opacity="0.22"
            />
          </filter>

          <!-- 3M Silver Reflective Pattern -->
          <linearGradient
            :id="`${uid}-silver`"
            x1="0%"
            y1="0%"
            x2="100%"
            y2="0%"
          >
            <stop
              offset="0%"
              stop-color="#cbd5e1"
            />
            <stop
              offset="30%"
              stop-color="#f8fafc"
            />
            <stop
              offset="50%"
              stop-color="#94a3b8"
            />
            <stop
              offset="75%"
              stop-color="#f1f5f9"
            />
            <stop
              offset="100%"
              stop-color="#cbd5e1"
            />
          </linearGradient>

          <!-- Hi-Vis Reflective Trim -->
          <pattern
            :id="`${uid}-chevron`"
            width="12"
            height="12"
            patternUnits="userSpaceOnUse"
            patternTransform="rotate(45)"
          >
            <rect
              width="6"
              height="12"
              fill="#e2e8f0"
            />
            <rect
              x="6"
              width="6"
              height="12"
              fill="#94a3b8"
            />
          </pattern>
        </defs>

        <!-- Center Studio Axis Lines -->
        <g
          v-if="showGrid && !compact"
          stroke="rgba(148, 163, 184, 0.25)"
          stroke-dasharray="3 3"
          stroke-width="1"
        >
          <line
            x1="160"
            y1="16"
            x2="160"
            y2="404"
          />
          <line
            x1="24"
            y1="135"
            x2="296"
            y2="135"
          />
          <line
            x1="24"
            y1="210"
            x2="296"
            y2="210"
          />
        </g>

        <!-- ==================== GARMENT SILHOUETTES ==================== -->
        <g :filter="`url(#${uid}-shadow)`">
          <!-- 1. COVERALL / BOILERSUIT -->
          <g v-if="silhouette === 'coverall'">
            <!-- Main Torso & Legs -->
            <path
              d="M122 52 L198 52 L236 74 L274 176 L246 188 L220 116 L216 212 L222 384 L174 384 L160 254 L146 384 L98 384 L104 212 L100 116 L74 188 L46 176 L84 74 Z"
              :fill="regionHex('body', primaryColorHex)"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Contrast Hi-Vis Upper Yoke (Shoulder + Upper Chest/Back) -->
            <path
              d="M122 52 L198 52 L236 74 L252 118 L220 116 L218 124 L102 124 L100 116 L68 118 L84 74 Z"
              :fill="regionHex('yoke', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.5"
            />
            <!-- Collar -->
            <path
              v-if="currentView === 'front'"
              d="M122 52 L160 82 L198 52 L184 42 L136 42 Z"
              :fill="regionHex('collar', primaryColorHex)"
              stroke="#64748b"
              stroke-width="1.8"
            />
            <path
              v-else
              d="M122 52 Q160 62 198 52 L188 40 L132 40 Z"
              :fill="regionHex('collar', primaryColorHex)"
              stroke="#64748b"
              stroke-width="1.8"
            />
            <!-- Waistband -->
            <rect
              x="103"
              y="198"
              width="114"
              height="14"
              :fill="regionHex('collar', primaryColorHex)"
              stroke="#64748b"
              stroke-width="1.5"
            />
            <!-- Front Placket & Pockets -->
            <g
              v-if="currentView === 'front'"
              stroke="#94a3b8"
              stroke-width="1.3"
              fill="none"
            >
              <line
                x1="160"
                y1="82"
                x2="160"
                y2="254"
              />
              <!-- Chest pockets -->
              <rect
                x="114"
                y="116"
                width="32"
                height="36"
                rx="2"
                fill="rgba(15,23,42,0.22)"
              />
              <path
                d="M114 116 L146 116 L146 126 L130 130 L114 126 Z"
                :fill="regionHex('pockets', contrastColorHex)"
              />
              <rect
                x="174"
                y="116"
                width="32"
                height="36"
                rx="2"
                fill="rgba(15,23,42,0.22)"
              />
              <path
                d="M174 116 L206 116 L206 126 L190 130 L174 126 Z"
                :fill="regionHex('pockets', contrastColorHex)"
              />
              <!-- Cargo thigh pockets -->
              <rect
                x="100"
                y="262"
                width="22"
                height="38"
                rx="2"
                fill="rgba(15,23,42,0.2)"
              />
              <rect
                x="198"
                y="262"
                width="22"
                height="38"
                rx="2"
                fill="rgba(15,23,42,0.2)"
              />
              <!-- Knee pad pockets -->
              <rect
                x="108"
                y="318"
                width="28"
                height="34"
                rx="3"
                stroke-dasharray="2 2"
              />
              <rect
                x="184"
                y="318"
                width="28"
                height="34"
                rx="3"
                stroke-dasharray="2 2"
              />
            </g>
            <!-- Standard Reflective Tape Strips (Sleeve + Leg) -->
            <g opacity="0.88">
              <rect
                x="58"
                y="148"
                width="26"
                height="8"
                transform="rotate(-21 71 152)"
                :fill="`url(#${uid}-silver)`"
              />
              <rect
                x="236"
                y="148"
                width="26"
                height="8"
                transform="rotate(21 249 152)"
                :fill="`url(#${uid}-silver)`"
              />
              <rect
                x="100"
                y="354"
                width="46"
                height="10"
                :fill="`url(#${uid}-silver)`"
              />
              <rect
                x="174"
                y="354"
                width="46"
                height="10"
                :fill="`url(#${uid}-silver)`"
              />
            </g>
          </g>

          <!-- 2. WORK JACKET / 6. WEATHERPROOF PARKA -->
          <g v-else-if="silhouette === 'jacket' || silhouette === 'parka'">
            <path
              :d="silhouette === 'parka'
                ? 'M118 54 L202 54 L242 78 L278 196 L248 208 L222 126 L224 276 L96 276 L98 126 L72 208 L42 196 L78 78 Z'
                : 'M120 56 L200 56 L238 78 L274 190 L246 202 L220 124 L218 248 L102 248 L100 124 L74 202 L46 190 L82 78 Z'"
              :fill="regionHex('body', primaryColorHex)"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Contrast Yoke -->
            <path
              d="M120 56 L200 56 L238 78 L254 126 L220 124 L219 136 L101 136 L100 124 L66 126 L82 78 Z"
              :fill="regionHex('yoke', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.5"
            />
            <!-- Hood / Collar -->
            <path
              v-if="silhouette === 'parka'"
              d="M116 54 Q160 18 204 54 L192 68 L128 68 Z"
              :fill="regionHex('collar_hood', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.8"
            />
            <path
              v-else
              d="M120 56 L160 84 L200 56 L186 42 L134 42 Z"
              :fill="regionHex('collar_hood', primaryColorHex)"
              stroke="#64748b"
              stroke-width="1.8"
            />
            <!-- Front Zipper & Pockets -->
            <g
              v-if="currentView === 'front'"
              stroke="#94a3b8"
              stroke-width="1.4"
              fill="none"
            >
              <line
                x1="160"
                y1="68"
                x2="160"
                :y2="silhouette === 'parka' ? 276 : 248"
              />
              <rect
                x="112"
                y="118"
                width="34"
                height="36"
                rx="2"
                :fill="regionHex('pockets', 'rgba(15,23,42,0.22)')"
                fill-opacity="0.45"
              />
              <rect
                x="174"
                y="118"
                width="34"
                height="36"
                rx="2"
                :fill="regionHex('pockets', 'rgba(15,23,42,0.22)')"
                fill-opacity="0.45"
              />
              <line
                x1="112"
                y1="196"
                x2="140"
                y2="226"
              />
              <line
                x1="208"
                y1="196"
                x2="180"
                y2="226"
              />
            </g>
            <!-- Hem Ribbing -->
            <rect
              x="102"
              :y="silhouette === 'parka' ? 264 : 236"
              width="116"
              height="12"
              :fill="regionHex('hem_cuffs', primaryColorHex)"
              fill-opacity="0.85"
              stroke="#475569"
            />
          </g>

          <!-- 3. WORK TROUSERS -->
          <g v-else-if="silhouette === 'trousers'">
            <!-- Waistband -->
            <rect
              x="104"
              y="78"
              width="112"
              height="20"
              rx="2"
              :fill="regionHex('main_fabric', primaryColorHex)"
              stroke="#475569"
              stroke-width="2"
            />
            <!-- Legs -->
            <path
              d="M104 98 L216 98 L226 382 L174 382 L160 186 L146 382 L94 382 Z"
              :fill="regionHex('main_fabric', primaryColorHex)"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Contrast Knee / Cargo Accents -->
            <rect
              x="98"
              y="224"
              width="26"
              height="48"
              rx="3"
              :fill="regionHex('cargo_accents', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.3"
            />
            <rect
              x="196"
              y="224"
              width="26"
              height="48"
              rx="3"
              :fill="regionHex('cargo_accents', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.3"
            />
            <!-- Knee Reinforcement Patches -->
            <rect
              x="104"
              y="292"
              width="34"
              height="42"
              rx="4"
              :fill="regionHex('knee_reinforcements', 'rgba(15,23,42,0.35)')"
              fill-opacity="0.65"
              stroke="#64748b"
              stroke-width="1.3"
            />
            <rect
              x="182"
              y="292"
              width="34"
              height="42"
              rx="4"
              :fill="regionHex('knee_reinforcements', 'rgba(15,23,42,0.35)')"
              fill-opacity="0.65"
              stroke="#64748b"
              stroke-width="1.3"
            />
            <!-- Ankle Hi-Vis Bands -->
            <rect
              x="96"
              y="348"
              width="48"
              height="12"
              :fill="`url(#${uid}-silver)`"
            />
            <rect
              x="176"
              y="348"
              width="48"
              height="12"
              :fill="`url(#${uid}-silver)`"
            />
          </g>

          <!-- 4. POLO / WORK SHIRT -->
          <g v-else-if="silhouette === 'polo'">
            <path
              d="M120 58 L200 58 L244 82 L264 144 L232 156 L216 116 L216 252 L104 252 L104 116 L88 156 L56 144 L76 82 Z"
              :fill="regionHex('body', primaryColorHex)"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Contrast Shoulder Panels -->
            <path
              d="M120 58 L200 58 L244 82 L222 104 L98 104 L76 82 Z"
              :fill="regionHex('shoulder_panels', contrastColorHex)"
              stroke="#475569"
              stroke-width="1.4"
            />
            <!-- Polo Collar & 3-Button Placket -->
            <path
              d="M122 58 L160 80 L198 58 L186 44 L134 44 Z"
              :fill="regionHex('collar_placket', primaryColorHex)"
              stroke="#64748b"
              stroke-width="1.8"
            />
            <g v-if="currentView === 'front'">
              <rect
                x="153"
                y="76"
                width="14"
                height="46"
                rx="2"
                :fill="regionHex('collar_placket', 'rgba(15,23,42,0.28)')"
                fill-opacity="0.7"
                stroke="#94a3b8"
              />
              <circle
                cx="160"
                cy="88"
                r="2"
                fill="#e2e8f0"
              />
              <circle
                cx="160"
                cy="100"
                r="2"
                fill="#e2e8f0"
              />
              <circle
                cx="160"
                cy="112"
                r="2"
                fill="#e2e8f0"
              />
            </g>
          </g>

          <!-- 5. HI-VIS SAFETY VEST -->
          <g v-else-if="silhouette === 'vest'">
            <path
              d="M124 56 L142 56 L160 102 L178 56 L196 56 L220 76 L210 124 L218 248 L102 248 L110 124 L100 76 Z"
              :fill="regionHex('hi_vis_body', contrastColorHex)"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Lower Contrast Panel -->
            <rect
              x="103"
              y="198"
              width="114"
              height="50"
              :fill="regionHex('contrast_panel', primaryColorHex)"
              stroke="#475569"
            />
            <!-- EN ISO 20471 Reflective Tape Harness -->
            <rect
              x="126"
              y="62"
              width="14"
              height="176"
              :fill="`url(#${uid}-silver)`"
            />
            <rect
              x="180"
              y="62"
              width="14"
              height="176"
              :fill="`url(#${uid}-silver)`"
            />
            <rect
              x="105"
              y="156"
              width="110"
              height="14"
              :fill="`url(#${uid}-silver)`"
            />
            <rect
              x="103"
              y="192"
              width="114"
              height="14"
              :fill="`url(#${uid}-silver)`"
            />
            <line
              v-if="currentView === 'front'"
              x1="160"
              y1="102"
              x2="160"
              y2="248"
              stroke="#334155"
              stroke-width="2"
            />
          </g>

          <!-- 7. BIB & BRACE OVERALLS / DUNGAREES -->
          <g v-else-if="silhouette === 'overalls'">
            <!-- Straps -->
            <rect
              x="126"
              y="56"
              width="14"
              height="64"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
            <rect
              x="180"
              y="56"
              width="14"
              height="64"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
            <!-- Bib + Trousers -->
            <path
              d="M118 110 L202 110 L216 196 L226 382 L174 382 L160 236 L146 382 L94 382 L104 196 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Chest Bib Pocket -->
            <rect
              v-if="currentView === 'front'"
              x="132"
              y="124"
              width="56"
              height="42"
              rx="3"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.4"
            />
            <!-- Reflective Leg Bands -->
            <rect
              x="96"
              y="348"
              width="48"
              height="12"
              :fill="`url(#${uid}-silver)`"
            />
            <rect
              x="176"
              y="348"
              width="48"
              height="12"
              :fill="`url(#${uid}-silver)`"
            />
          </g>

          <!-- 8. APRON / TABARD / LAB SMOCK -->
          <g v-else-if="silhouette === 'apron'">
            <path
              d="M134 56 L186 56 L190 98 L218 134 L214 342 L106 342 L102 134 L130 98 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <rect
              x="126"
              y="188"
              width="68"
              height="48"
              rx="4"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
          </g>

          <!-- 9. SAFETY BOOTS / FOOTWEAR -->
          <g v-else-if="silhouette === 'boots'">
            <!-- Left & Right Boot Pair -->
            <path
              d="M78 116 L138 116 L142 256 L154 286 L154 316 L62 316 L62 284 L76 260 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <path
              d="M182 116 L242 116 L244 260 L258 284 L258 316 L166 316 L166 286 L178 256 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <!-- Toe Caps & Soles -->
            <rect
              x="60"
              y="304"
              width="96"
              height="16"
              rx="4"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
            <rect
              x="164"
              y="304"
              width="96"
              height="16"
              rx="4"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
          </g>

          <!-- 10. PROTECTIVE GLOVES / GAUNTLETS -->
          <g v-else-if="silhouette === 'gloves'">
            <path
              d="M74 160 L82 96 L96 96 L98 140 L104 88 L118 88 L118 140 L124 94 L138 94 L136 150 L148 128 L156 138 L142 192 L138 294 L76 294 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <path
              d="M246 160 L238 96 L224 96 L222 140 L216 88 L202 88 L202 140 L196 94 L182 94 L184 150 L172 128 L164 138 L178 192 L182 294 L244 294 Z"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
              stroke-linejoin="round"
            />
            <rect
              x="76"
              y="246"
              width="62"
              height="48"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
            <rect
              x="182"
              y="246"
              width="62"
              height="48"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="1.5"
            />
          </g>

          <!-- 11. SAFETY HELMET / HEADWEAR -->
          <g v-else-if="silhouette === 'helmet'">
            <path
              d="M72 224 C72 130 248 130 248 224 Z"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="2.2"
            />
            <rect
              x="56"
              y="220"
              width="208"
              height="18"
              rx="6"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
            />
            <rect
              x="146"
              y="136"
              width="28"
              height="84"
              rx="4"
              :fill="primaryColorHex"
              opacity="0.45"
            />
          </g>

          <!-- 12. EYE, FACE, HEARING & RESPIRATORY PPE -->
          <g v-else-if="silhouette === 'glasses'">
            <rect
              x="58"
              y="156"
              width="92"
              height="56"
              rx="14"
              :fill="primaryColorHex"
              stroke="#38bdf8"
              stroke-width="2.2"
            />
            <rect
              x="170"
              y="156"
              width="92"
              height="56"
              rx="14"
              :fill="primaryColorHex"
              stroke="#38bdf8"
              stroke-width="2.2"
            />
            <rect
              x="148"
              y="172"
              width="24"
              height="12"
              rx="3"
              :fill="contrastColorHex"
            />
          </g>

          <!-- 13. FALL ARREST HARNESS -->
          <g v-else>
            <path
              d="M116 64 L136 64 L160 156 L184 64 L204 64 L174 188 L208 296 L186 304 L160 218 L134 304 L112 296 L146 188 Z"
              :fill="contrastColorHex"
              stroke="#475569"
              stroke-width="2"
            />
            <rect
              x="108"
              y="182"
              width="104"
              height="20"
              rx="4"
              :fill="primaryColorHex"
              stroke="#475569"
              stroke-width="2"
            />
          </g>
        </g>

        <!-- ==================== SAFE BOUNDARY & ANCHOR ZONE TARGETS (EDIT MODE) ==================== -->
        <g v-if="!readonly && showZonesOverlay && !compact">
          <rect
            v-if="highlightedSafeBounds"
            :x="highlightedSafeBounds.x"
            :y="highlightedSafeBounds.y"
            :width="highlightedSafeBounds.width"
            :height="highlightedSafeBounds.height"
            rx="4"
            fill="rgba(56, 189, 248, 0.08)"
            stroke="#38bdf8"
            stroke-width="1"
            stroke-dasharray="4 3"
          />
          <g
            v-for="zone in viewZones"
            :key="zone.value"
            class="zone-anchor cursor-pointer"
            role="button"
            tabindex="0"
            :aria-label="zone.title || zone.value"
            @focus="hoveredZone = zone"
            @blur="hoveredZone = null"
            @keydown.enter.prevent="handleZoneClick(zone)"
            @keydown.space.prevent="handleZoneClick(zone)"
            @mouseenter="hoveredZone = zone"
            @mouseleave="hoveredZone = null"
            @click.stop="handleZoneClick(zone)"
          >
            <circle
              :cx="zone.x"
              :cy="zone.y"
              r="6"
              fill="rgba(37, 99, 235, 0.18)"
              stroke="#3b82f6"
              stroke-width="1.2"
              stroke-dasharray="2 2"
            />
            <circle
              :cx="zone.x"
              :cy="zone.y"
              r="2"
              fill="#60a5fa"
            />
          </g>
        </g>

        <!-- ==================== CUSTOMIZATION PLACEMENTS OVERLAY ==================== -->
        <g
          v-for="(placement, vIdx) in viewPlacements"
          :key="placement.id || `${placement.zone}-${placement._originalIndex}`"
          class="placement-node cursor-pointer"
          role="button"
          tabindex="0"
          :aria-label="placement.label || placement.zone"
          @keydown.enter.prevent="handleSelectPlacement(placement._originalIndex)"
          @keydown.space.prevent="handleSelectPlacement(placement._originalIndex)"
          @click.stop="handleSelectPlacement(placement._originalIndex)"
        >
          <template
            v-for="coords in [getPlacementCoords(placement)]"
            :key="coords.cx"
          >
            <!-- 1. Reflective Band / Fluo Panel Placement -->
            <g v-if="placement.type === 'reflective_band' || placement.type === 'fluo'">
              <rect
                :x="coords.x"
                :y="coords.y"
                :width="coords.w"
                :height="Math.max(12, coords.h * (placement.type === 'fluo' ? 0.75 : 0.45))"
                rx="2"
                :fill="placement.type === 'fluo' ? (placement.color || '#eab308') : `url(#${uid}-chevron)`"
                :fill-opacity="placement.type === 'fluo' ? 0.78 : 1"
                :stroke="selectedPlacementIndex === placement._originalIndex ? '#38bdf8' : '#eab308'"
                :stroke-width="selectedPlacementIndex === placement._originalIndex ? 2.4 : 1.4"
              />
              <text
                v-if="placement.type === 'fluo'"
                :x="coords.cx"
                :y="coords.y + Math.max(12, coords.h * 0.75) / 2 + 3"
                text-anchor="middle"
                fill="#0f172a"
                font-size="7.5"
                font-weight="800"
                font-family="monospace"
              >
                {{ (placement.text || placement.label || 'FLUO').slice(0, 14) }}
              </text>
            </g>

            <!-- 2. Logo / Text / Badge / Pocket Placement Box -->
            <g v-else>
              <rect
                :x="coords.x"
                :y="coords.y"
                :width="coords.w"
                :height="coords.h"
                rx="4"
                :fill="selectedPlacementIndex === placement._originalIndex ? 'rgba(37, 99, 235, 0.34)' : 'rgba(15, 23, 42, 0.72)'"
                :stroke="selectedPlacementIndex === placement._originalIndex ? '#38bdf8' : (placement.color || resolvePlacementTypeMeta(placement.type).color)"
                :stroke-width="selectedPlacementIndex === placement._originalIndex ? 2.2 : 1.5"
                :stroke-dasharray="selectedPlacementIndex === placement._originalIndex ? 'none' : '3 2'"
              />

              <!-- Uploaded Logo Image Preview inside Zone -->
              <image
                v-if="placement.type === 'logo' && resolveLogoUrl(placement.media_id)"
                :href="resolveLogoUrl(placement.media_id)"
                :x="coords.x + 3"
                :y="coords.y + 3"
                :width="Math.max(10, coords.w - 6)"
                :height="Math.max(10, coords.h - 6)"
                preserveAspectRatio="xMidYMid meet"
              />

              <!-- Text / Label Inside Zone -->
              <text
                v-else
                :x="coords.cx"
                :y="coords.cy + 3"
                text-anchor="middle"
                fill="#f8fafc"
                font-size="8.5"
                font-weight="700"
                font-family="monospace"
              >
                {{ (placement.text || placement.label || placement.zone).slice(0, 14) }}
              </text>
            </g>

            <!-- Callout Number Badge -->
            <g
              v-if="showCallouts && !compact"
              :transform="`translate(${coords.x + coords.w + 2}, ${coords.y - 4})`"
            >
              <circle
                r="8"
                :fill="selectedPlacementIndex === placement._originalIndex ? '#2563eb' : '#0f172a'"
                stroke="#38bdf8"
                stroke-width="1.4"
              />
              <text
                y="3"
                text-anchor="middle"
                fill="#ffffff"
                font-size="8"
                font-weight="700"
              >
                {{ vIdx + 1 }}
              </text>
            </g>
          </template>
        </g>
      </svg>

      <!-- Bottom HUD Bar -->
      <div
        v-if="!compact"
        class="studio-hud-bar position-absolute d-flex align-center justify-space-between px-3 py-1"
      >
        <div class="d-flex align-center gap-2">
          <span class="hud-dot" />
          <span class="text-caption font-mono text-white">{{ hudText }}</span>
        </div>
        <span class="text-caption font-mono text-info">
          {{ viewPlacements.length }} {{ $t('zones active') }}
        </span>
      </div>
    </div>

    <!-- Legend / Active Placements Bar -->
    <div
      v-if="!compact && viewPlacements.length"
      class="studio-legend d-flex flex-wrap align-center gap-2 px-3 py-2"
    >
      <VChip
        v-for="(p, vIdx) in viewPlacements"
        :key="p.id || p._originalIndex"
        size="x-small"
        :color="selectedPlacementIndex === p._originalIndex ? 'primary' : 'secondary'"
        :variant="selectedPlacementIndex === p._originalIndex ? 'flat' : 'tonal'"
        class="cursor-pointer font-weight-medium"
        @click="handleSelectPlacement(p._originalIndex)"
      >
        <span class="me-1 font-mono">#{{ vIdx + 1 }}</span>
        {{ p.label || p.zone }}
      </VChip>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.workwear-garment-studio {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), 0.14);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(15, 23, 42, 94%) 0%, rgba(15, 23, 42, 98%) 100%);

  &.is-compact {
    border-radius: 10px;

    .studio-canvas-wrap {
      padding: 8px;
      min-block-size: 160px;
    }

    .garment-svg {
      max-block-size: 150px;
    }
  }
}

.studio-toolbar {
  background: rgba(15, 23, 42, 85%);
  border-block-end: 1px solid rgba(148, 163, 184, 14%);
}

.studio-canvas-wrap {
  min-block-size: 430px;
  padding-block: 16px 40px;
  padding-inline: 16px;

  &.grid-bg {
    background-image:
      linear-gradient(to right, rgba(148, 163, 184, 7%) 1px, transparent 1px),
      linear-gradient(to bottom, rgba(148, 163, 184, 7%) 1px, transparent 1px);
    background-size: 20px 20px;
  }
}

.garment-svg {
  inline-size: 100%;
  max-block-size: 390px;
  max-inline-size: 310px;
  transition: transform 0.2s ease;
}

.compact-view-toggle {
  z-index: 2;
  inset-block-start: 6px;
  inset-inline-end: 6px;
}

.studio-hud-bar {
  border: 1px solid rgba(56, 189, 248, 22%);
  border-radius: 8px;
  backdrop-filter: blur(8px);
  background: rgba(2, 6, 23, 82%);
  inset-block-end: 8px;
  inset-inline: 12px;
}

.studio-hud-bar .text-caption {
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hud-dot {
  border-radius: 50%;
  background: #22c55e;
  block-size: 8px;
  box-shadow: 0 0 8px #22c55e;
  inline-size: 8px;
}

.studio-legend {
  background: rgba(15, 23, 42, 90%);
  border-block-start: 1px solid rgba(148, 163, 184, 14%);
}

.placement-node {
  transition: opacity 0.15s ease;

  &:hover {
    opacity: 0.92;
  }
}

.placement-node:focus-visible,
.zone-anchor:focus-visible {
  outline: none;

  :deep(rect),
  :deep(circle) {
    stroke: #38bdf8;
    stroke-width: 2.4;
  }
}

@media (prefers-reduced-motion: reduce) {
  .garment-svg,
  .placement-node {
    transition: none;
  }
}
</style>
