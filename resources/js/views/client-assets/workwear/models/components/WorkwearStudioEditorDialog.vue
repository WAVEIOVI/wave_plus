<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { AppHybridTabs, WpZoomDialog } from '@/components/shared'
import {
  WORKWEAR_APPLICATION_METHODS,
  WORKWEAR_COLOR_PALETTE,
  WORKWEAR_PLACEMENT_TYPES,
  WORKWEAR_SILHOUETTES,
  WORKWEAR_SILHOUETTE_CATALOG,
  WORKWEAR_STANDARD_SIZES,
  clampPlacementToZoneBounds,
  extractCatalogColorsAndSizes,
  findSilhouetteItemMeta,
  getSilhouetteItems,
  getSilhouetteSubcategories,
  getTemplateColorRegions,
  getTemplateZones,
  normalizePlacementForTemplate,
  normalizeStudioPlacements,
  resolveColorHex,
  resolveModelColorRegions,
  resolvePlacementTypeMeta,
  resolveSilhouetteDisplay,
  setModelRegionColor,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  modelData: { type: Object, required: true },
  logos: { type: Array, default: () => [] },
  uploadMediaFile: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue', 'triggerUpload'])
const { t, locale } = useI18n()

const activeTab = ref('placements')
const activeView = ref('front')
const selectedPlacementIndex = ref(0)
const nudgeStep = ref(5)
const rowFileInputRef = ref(null)

const DIMENSION_PRESETS = [
  { key: 'chest_logo', label: 'Chest Logo (85×45)', width: 85, height: 45 },
  { key: 'name_badge', label: 'Name Badge (100×28)', width: 100, height: 28 },
  { key: 'sleeve_patch', label: 'Sleeve Patch (70×70)', width: 70, height: 70 },
  { key: 'back_text', label: 'Back Text (240×60)', width: 240, height: 60 },
  { key: 'full_band', label: 'Reflective Band (280×50)', width: 280, height: 50 },
]

watch(
  () => props.modelData,
  val => {
    if (!val) return
    if (!val.silhouette_category) val.silhouette_category = 'clothing'
    if (!val.silhouette_subcategory) val.silhouette_subcategory = 'one_piece'
    if (!val.silhouette) val.silhouette = val.garment_types?.[0] || 'coverall'
    if (!Array.isArray(val.colors) || val.colors.length === 0) {
      val.colors = ['navy', 'hi-vis-yellow']
    }
    if (!Array.isArray(val.size_range)) {
      val.size_range = ['S', 'M', 'L', 'XL', '2XL']
    }
    if (!Array.isArray(val.placements)) {
      val.placements = []
    }
    if (val.placements.length > 0 && selectedPlacementIndex.value === -1) {
      selectedPlacementIndex.value = 0
    }
  },
  { immediate: true },
)

/* ── Discard support ────────────────────────────────────────────────────── */
const STUDIO_KEYS = [
  'silhouette_category',
  'silhouette_subcategory',
  'silhouette_item',
  'silhouette',
  'colors',
  'size_range',
  'placements',
]

const pickStudioState = () => {
  const out = {}

  STUDIO_KEYS.forEach(key => { out[key] = props.modelData?.[key] })

  return JSON.parse(JSON.stringify(out))
}

const openSnapshot = ref(null)

watch(
  () => props.modelValue,
  open => {
    if (open) {
      openSnapshot.value = pickStudioState()
      selectedPlacementIndex.value = props.modelData.placements?.length ? 0 : -1
    }
  },
  { immediate: true },
)

const hasChanges = computed(() =>
  Boolean(openSnapshot.value)
  && JSON.stringify(pickStudioState()) !== JSON.stringify(openSnapshot.value),
)

function discardChanges() {
  if (!openSnapshot.value) return

  const saved = JSON.parse(JSON.stringify(openSnapshot.value))

  STUDIO_KEYS.forEach(key => { props.modelData[key] = saved[key] })
  selectedPlacementIndex.value = props.modelData.placements?.length ? 0 : -1
}

/* ── Derived state ────────────────────────────────────────────────────── */
const silhouetteSummary = computed(() =>
  resolveSilhouetteDisplay(props.modelData, { locale: locale.value, t }),
)

const placements = computed(() => props.modelData.placements || [])

const subcategoryOptions = computed(() =>
  getSilhouetteSubcategories(props.modelData.silhouette_category),
)

const silhouetteItemOptions = computed(() =>
  getSilhouetteItems(props.modelData.silhouette_category, props.modelData.silhouette_subcategory),
)

const catalogExtracted = computed(() =>
  extractCatalogColorsAndSizes(props.modelData?.catalog_item, locale.value),
)

const primaryColor = computed({
  get: () => props.modelData.colors?.[0] || 'navy',
  set: val => {
    if (!Array.isArray(props.modelData.colors)) props.modelData.colors = [val, 'hi-vis-yellow']
    else props.modelData.colors.splice(0, 1, val)
  },
})

const contrastColor = computed({
  get: () => props.modelData.colors?.[1] || 'hi-vis-yellow',
  set: val => {
    if (!Array.isArray(props.modelData.colors)) props.modelData.colors = ['navy', val]
    else if (props.modelData.colors.length < 2) props.modelData.colors.push(val)
    else props.modelData.colors.splice(1, 1, val)
  },
})

const activePlacement = computed(() => {
  const list = props.modelData.placements || []
  if (selectedPlacementIndex.value >= 0 && selectedPlacementIndex.value < list.length) {
    return list[selectedPlacementIndex.value]
  }

  return null
})

const currentCanvasZones = computed(() => {
  const sil = props.modelData.silhouette || 'coverall'

  return getTemplateZones(sil, activeView.value || 'front')
})

const availableZonesForView = computed(() => {
  const view = activePlacement.value?.view || activeView.value || 'front'
  const sil = props.modelData.silhouette || 'coverall'

  return getTemplateZones(sil, view)
})

const templateColorRegions = computed(() =>
  getTemplateColorRegions(props.modelData.silhouette || 'coverall').filter(r => r.role !== 'reflective'),
)

const resolvedColorRegions = computed(() =>
  resolveModelColorRegions(props.modelData.colors, props.modelData.silhouette || 'coverall'),
)

const logoSelectOptions = computed(() => [
  { title: t('No linked artwork'), value: null },
  ...(props.logos || []).map(l => ({
    title: l.name || `${t('Logo')} #${l.id}`,
    value: l.id,
  })),
])

const accentColorOptions = computed(() =>
  WORKWEAR_COLOR_PALETTE.map(c => ({ title: c.label, value: c.hex })),
)

const showsTextField = computed(() =>
  Boolean(activePlacement.value) && !['logo', 'reflective_band'].includes(activePlacement.value.type),
)

const studioTabs = computed(() => [
  { value: 'placements', icon: 'tabler-vector-bezier-2', title: t('Placements'), badge: placements.value.length || undefined },
  { value: 'silhouette', icon: 'tabler-shirt', title: t('Silhouette') },
  { value: 'colors', icon: 'tabler-palette', title: t('Colors'), badge: (props.modelData.colors || []).length || undefined },
  { value: 'sizes', icon: 'tabler-ruler-2', title: t('Sizes'), badge: (props.modelData.size_range || []).length || undefined },
])

/* ── Placements ───────────────────────────────────────────────────────── */
function isZoneOccupied(zoneValue) {
  return (props.modelData.placements || []).some(
    p => (p.view || 'front') === activeView.value && p.zone === zoneValue,
  )
}

function addPlacement(type = 'logo', presetZone = null) {
  if (!Array.isArray(props.modelData.placements)) {
    props.modelData.placements = []
  }
  const sil = props.modelData.silhouette || 'coverall'
  const viewZones = getTemplateZones(sil, activeView.value)
  const typeMeta = resolvePlacementTypeMeta(type)
  const fallbackZone = viewZones[0]?.value || (activeView.value === 'back' ? 'back_upper' : 'chest_left')
  const defaultZone = presetZone?.value || fallbackZone

  const normalized = normalizePlacementForTemplate({
    view: activeView.value,
    type,
    label: `${typeMeta.title} (${defaultZone.replace(/_/g, ' ')})`,
    zone: defaultZone,
    text: type === 'text' || type === 'badge' ? (props.modelData.client?.legal_name || 'COMPANY') : '',
    method: typeMeta.defaultMethod,
    color: typeMeta.color,
    width_mm: typeMeta.defaultWidth,
    height_mm: typeMeta.defaultHeight,
    offset_x_mm: 0,
    offset_y_mm: 0,
    media_id: type === 'logo' && props.logos?.[0]?.id ? props.logos[0].id : null,
    notes: '',
    sort_order: props.modelData.placements.length,
  }, sil)

  props.modelData.placements.push(normalized)
  selectedPlacementIndex.value = props.modelData.placements.length - 1
  activeTab.value = 'placements'
}

function selectPlacement(idx) {
  selectedPlacementIndex.value = idx
  activeView.value = props.modelData.placements[idx]?.view || 'front'
}

function handleZoneClick(zone) {
  const existingIdx = (props.modelData.placements || []).findIndex(
    p => (p.view || 'front') === activeView.value && p.zone === zone.value,
  )

  if (existingIdx >= 0) {
    selectedPlacementIndex.value = existingIdx
  } else if (activePlacement.value && (activePlacement.value.view || 'front') === activeView.value) {
    activePlacement.value.zone = zone.value
    const clamped = clampPlacementToZoneBounds(activePlacement.value, props.modelData.silhouette || 'coverall')

    Object.assign(activePlacement.value, clamped)
  } else {
    addPlacement('logo', zone)
  }
  activeTab.value = 'placements'
}

function applyDimensionPreset(preset) {
  if (!activePlacement.value) return
  activePlacement.value.width_mm = preset.width
  activePlacement.value.height_mm = preset.height
}

function removePlacement(idx) {
  if (!Array.isArray(props.modelData.placements)) return
  props.modelData.placements.splice(idx, 1)
  if (selectedPlacementIndex.value >= props.modelData.placements.length) {
    selectedPlacementIndex.value = props.modelData.placements.length - 1
  }
}

function nudgeOffset(dx, dy) {
  if (!activePlacement.value) return
  const nextX = Number(activePlacement.value.offset_x_mm || 0) + dx
  const nextY = Number(activePlacement.value.offset_y_mm || 0) + dy

  const clamped = clampPlacementToZoneBounds(
    { ...activePlacement.value, offset_x_mm: nextX, offset_y_mm: nextY },
    props.modelData.silhouette || 'coverall',
  )

  activePlacement.value.offset_x_mm = clamped.offset_x_mm
  activePlacement.value.offset_y_mm = clamped.offset_y_mm
}

function resetOffset() {
  if (!activePlacement.value) return
  activePlacement.value.offset_x_mm = 0
  activePlacement.value.offset_y_mm = 0
}

function handlePlacementViewChange(placement, newView) {
  placement.view = newView
  activeView.value = newView

  const sil = props.modelData.silhouette || 'coverall'
  const validZones = getTemplateZones(sil, newView)

  if (!validZones.some(z => z.value === placement.zone)) {
    placement.zone = validZones[0]?.value || (newView === 'back' ? 'back_upper' : 'chest_left')
  }
  const clamped = clampPlacementToZoneBounds(placement, sil)

  Object.assign(placement, clamped)
}

/* ── Silhouette ───────────────────────────────────────────────────────── */
function applyTemplateSilhouette(newSilhouette) {
  props.modelData.silhouette = newSilhouette || 'coverall'
  if (Array.isArray(props.modelData.placements) && props.modelData.placements.length) {
    props.modelData.placements = normalizeStudioPlacements(
      props.modelData.placements,
      props.modelData.silhouette,
    )
  }
}

function handleCategoryChange(catValue) {
  props.modelData.silhouette_category = catValue

  const subs = getSilhouetteSubcategories(catValue)
  const firstSub = subs[0]

  props.modelData.silhouette_subcategory = firstSub?.value || null

  const firstItem = firstSub?.items?.[0]

  props.modelData.silhouette_item = firstItem?.value || null
  applyTemplateSilhouette(firstItem?.renderer || firstSub?.defaultRenderer || 'coverall')
}

function handleSubcategoryChange(subValue) {
  props.modelData.silhouette_subcategory = subValue

  const items = getSilhouetteItems(props.modelData.silhouette_category, subValue)
  const firstItem = items[0]

  props.modelData.silhouette_item = firstItem?.value || null
  applyTemplateSilhouette(firstItem?.renderer || 'coverall')
}

function handleSilhouetteItemChange(itemValue) {
  props.modelData.silhouette_item = itemValue

  const meta = findSilhouetteItemMeta(
    props.modelData.silhouette_category,
    props.modelData.silhouette_subcategory,
    itemValue,
  )

  if (meta?.renderer) {
    applyTemplateSilhouette(meta.renderer)
  }
}

/* ── Colors & sizes ───────────────────────────────────────────────────── */
function updateNamedRegionColor(regionKey, colorVal) {
  props.modelData.colors = setModelRegionColor(
    props.modelData.colors,
    props.modelData.silhouette || 'coverall',
    regionKey,
    colorVal,
  )
}

function setSlotColor(slotKey, value) {
  if (slotKey === 'primary') primaryColor.value = value
  else contrastColor.value = value
}

function importColorsFromCatalog() {
  if (!catalogExtracted.value.colors.length) return
  const cols = [...catalogExtracted.value.colors]
  if (cols.length === 1) cols.push(contrastColor.value)
  props.modelData.colors = cols
}

function importSizesFromCatalog() {
  if (!catalogExtracted.value.sizes.length) return
  props.modelData.size_range = [...catalogExtracted.value.sizes]
}

function toggleSize(sz) {
  if (!Array.isArray(props.modelData.size_range)) props.modelData.size_range = []
  const idx = props.modelData.size_range.indexOf(sz)
  if (idx >= 0) {
    props.modelData.size_range.splice(idx, 1)
  } else {
    props.modelData.size_range.push(sz)
  }
}

function selectAllSizes() {
  props.modelData.size_range = [...WORKWEAR_STANDARD_SIZES]
}

function clearSizes() {
  props.modelData.size_range = []
}

/* ── Artwork upload ───────────────────────────────────────────────────── */
async function onUploadFileChange(event) {
  const file = event.target.files?.[0]
  if (!file || !props.uploadMediaFile) return
  const uploaded = await props.uploadMediaFile(file, 'logos')
  if (uploaded?.id && activePlacement.value) {
    activePlacement.value.media_id = uploaded.id
  }
  if (rowFileInputRef.value) rowFileInputRef.value.value = ''
}

function triggerArtworkUpload() {
  if (props.uploadMediaFile && rowFileInputRef.value) {
    rowFileInputRef.value.click()
  } else {
    emit('triggerUpload', 'logos')
  }
}
</script>

<template>
  <WpZoomDialog
    :model-value="modelValue"
    :max-width="1440"
    :max-height="920"
    hide-activator
    fullscreen-toggle
    no-padding
    icon="tabler-vector-bezier-2"
    :title="modelData.name || $t('Garment Studio Editor')"
    :subtitle="$t('Click garment zones on the illustration or use the quick zone chips to place and customize branding.')"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <template #header-actions="{ close }">
      <div class="d-flex align-center gap-2 me-1">
        <VChip
          v-if="hasChanges"
          size="small"
          color="warning"
          variant="tonal"
          class="d-none d-sm-inline-flex"
          role="status"
        >
          {{ $t('Unsaved changes') }}
        </VChip>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
          class="d-none d-lg-inline-flex"
        >
          {{ silhouetteSummary.itemTitle }}
        </VChip>
        <VBtn
          color="primary"
          variant="flat"
          size="small"
          prepend-icon="tabler-check"
          @click="close"
        >
          {{ $t('Done') }}
        </VBtn>
      </div>
    </template>

    <input
      ref="rowFileInputRef"
      type="file"
      accept="image/*"
      class="d-none"
      @change="onUploadFileChange"
    >

    <div class="studio-layout">
      <!-- ── Garment canvas ── -->
      <div class="studio-canvas-pane">
        <div class="illustration-viewport">
          <WorkwearGarmentIllustration
            v-model:active-view="activeView"
            v-model:selected-placement-index="selectedPlacementIndex"
            :silhouette="modelData.silhouette || 'coverall'"
            :colors="modelData.colors"
            :placements="modelData.placements"
            :logos="logos"
            :readonly="false"
            @zone-click="handleZoneClick"
          />
        </div>
      </div>

      <!-- ── Controls ── -->
      <div class="studio-controls-pane">
        <div class="px-3 pt-2 border-b flex-shrink-0">
          <AppHybridTabs
            v-model="activeTab"
            :tabs="studioTabs"
            density="compact"
          />
        </div>

        <div class="controls-scrollable">
          <!-- ━━ Placements ━━ -->
          <div
            v-if="activeTab === 'placements'"
            class="d-flex flex-column gap-4"
          >
            <!-- Quick Zone Selector Bar -->
            <div class="zone-bar">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                  {{ $t('1. Select Target Zone') }} ({{ activeView === 'back' ? $t('Back View') : $t('Front View') }})
                </span>
                <VBtnToggle
                  v-model="activeView"
                  mandatory
                  density="compact"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                >
                  <VBtn
                    value="front"
                    size="x-small"
                  >
                    {{ $t('Front') }}
                  </VBtn>
                  <VBtn
                    value="back"
                    size="x-small"
                  >
                    {{ $t('Back') }}
                  </VBtn>
                </VBtnToggle>
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="zone in currentCanvasZones"
                  :key="zone.value"
                  size="small"
                  :color="activePlacement?.zone === zone.value && (activePlacement?.view || 'front') === activeView ? 'primary' : isZoneOccupied(zone.value) ? 'info' : 'secondary'"
                  :variant="activePlacement?.zone === zone.value && (activePlacement?.view || 'front') === activeView ? 'flat' : 'tonal'"
                  class="cursor-pointer"
                  @click="handleZoneClick(zone)"
                >
                  <VIcon
                    v-if="isZoneOccupied(zone.value)"
                    start
                    icon="tabler-map-pin-filled"
                    size="12"
                  />
                  {{ $t(zone.title) }}
                </VChip>
              </div>
            </div>

            <!-- Add Placement by Type -->
            <div>
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
                {{ $t('2. Add Customization Element') }}
              </div>
              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  v-for="pt in WORKWEAR_PLACEMENT_TYPES"
                  :key="pt.value"
                  size="small"
                  variant="tonal"
                  color="primary"
                  :prepend-icon="pt.icon"
                  @click="addPlacement(pt.value)"
                >
                  {{ $t(pt.title) }}
                </VBtn>
              </div>
            </div>

            <!-- Configured Placements List -->
            <div>
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
                {{ $t('Configured Placements') }} ({{ placements.length }})
              </div>

              <VList
                v-if="placements.length"
                density="compact"
                nav
                class="pa-0"
              >
                <VListItem
                  v-for="(pl, idx) in placements"
                  :key="pl.id || idx"
                  :active="selectedPlacementIndex === idx"
                  color="primary"
                  rounded="lg"
                  @click="selectPlacement(idx)"
                >
                  <template #prepend>
                    <VAvatar
                      size="30"
                      rounded
                      variant="tonal"
                      :color="selectedPlacementIndex === idx ? 'primary' : 'secondary'"
                      class="me-3"
                    >
                      <VIcon
                        :icon="resolvePlacementTypeMeta(pl.type).icon"
                        size="16"
                      />
                    </VAvatar>
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ pl.label || pl.zone?.replace(/_/g, ' ') }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ (pl.view || 'front') === 'back' ? $t('Back View') : $t('Front View') }} · {{ pl.width_mm || 80 }}×{{ pl.height_mm || 45 }}mm
                  </VListItemSubtitle>
                  <template #append>
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="error"
                      :aria-label="$t('Delete')"
                      @click.stop="removePlacement(idx)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="18"
                      />
                    </VBtn>
                  </template>
                </VListItem>
              </VList>
              <p
                v-else
                class="text-body-2 text-medium-emphasis mb-0"
              >
                {{ $t('No placements added yet. Click any zone chip above or click directly on the garment illustration.') }}
              </p>
            </div>

            <!-- Inspector -->
            <div
              v-if="activePlacement"
              class="inspector"
            >
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-adjustments-horizontal"
                    size="18"
                    color="primary"
                  />
                  {{ $t('Placement Inspector') }} #{{ selectedPlacementIndex + 1 }}
                </span>
              </div>

              <VRow dense>
                <VCol cols="12">
                  <AppTextField
                    v-model="activePlacement.label"
                    :label="$t('Placement Label')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    :model-value="activePlacement.view || 'front'"
                    :items="[
                      { title: $t('Front View'), value: 'front' },
                      { title: $t('Back View'), value: 'back' },
                    ]"
                    :label="$t('Garment View')"
                    density="compact"
                    @update:model-value="handlePlacementViewChange(activePlacement, $event)"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    v-model="activePlacement.zone"
                    :items="availableZonesForView"
                    item-title="title"
                    item-value="value"
                    :label="$t('Placement Zone')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    v-model="activePlacement.type"
                    :items="WORKWEAR_PLACEMENT_TYPES"
                    item-title="title"
                    item-value="value"
                    :label="$t('Placement Type')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    v-model="activePlacement.method"
                    :items="WORKWEAR_APPLICATION_METHODS"
                    item-title="title"
                    item-value="value"
                    :label="$t('Application Method')"
                    density="compact"
                  />
                </VCol>

                <!-- Artwork for logos, free text for the other types -->
                <VCol
                  v-if="activePlacement.type === 'logo'"
                  cols="12"
                >
                  <div class="d-flex align-center gap-2">
                    <AppSelect
                      v-model="activePlacement.media_id"
                      :items="logoSelectOptions"
                      item-title="title"
                      item-value="value"
                      :label="$t('Linked Artwork / Logo')"
                      density="compact"
                      class="flex-grow-1"
                    />
                    <VBtn
                      variant="tonal"
                      color="primary"
                      icon="tabler-upload"
                      :title="$t('Upload new artwork')"
                      :aria-label="$t('Upload new artwork')"
                      @click="triggerArtworkUpload"
                    />
                  </div>
                </VCol>
                <VCol
                  v-else-if="showsTextField"
                  cols="12"
                >
                  <AppTextField
                    v-model="activePlacement.text"
                    :label="$t('Custom Text / Embroidery Name')"
                    :placeholder="$t('e.g. JOHN DOE / COMPANY')"
                    density="compact"
                  />
                </VCol>

                <!-- Quick Dimension Presets -->
                <VCol cols="12">
                  <div class="text-caption text-medium-emphasis mb-1">
                    {{ $t('Quick Dimension Presets (mm)') }}
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-1">
                    <VChip
                      v-for="preset in DIMENSION_PRESETS"
                      :key="preset.key"
                      size="x-small"
                      :color="activePlacement.width_mm === preset.width && activePlacement.height_mm === preset.height ? 'primary' : 'secondary'"
                      variant="tonal"
                      class="cursor-pointer"
                      @click="applyDimensionPreset(preset)"
                    >
                      {{ $t(preset.label) }}
                    </VChip>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="3"
                >
                  <AppTextField
                    v-model.number="activePlacement.width_mm"
                    type="number"
                    :label="$t('Width (mm)')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <AppTextField
                    v-model.number="activePlacement.height_mm"
                    type="number"
                    :label="$t('Height (mm)')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <AppTextField
                    v-model.number="activePlacement.offset_x_mm"
                    type="number"
                    :label="$t('X (mm)')"
                    density="compact"
                  />
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <AppTextField
                    v-model.number="activePlacement.offset_y_mm"
                    type="number"
                    :label="$t('Y (mm)')"
                    density="compact"
                  />
                </VCol>

                <VCol cols="12">
                  <AppSelect
                    v-model="activePlacement.color"
                    :items="accentColorOptions"
                    :label="$t('Accent Color')"
                    density="compact"
                  />
                </VCol>
              </VRow>

              <!-- Fine position -->
              <div class="nudge mt-4">
                <div class="min-w-0">
                  <span class="text-body-2 font-weight-medium d-block mb-2">{{ $t('Fine Position Tuning (mm)') }}</span>
                  <VBtnToggle
                    v-model="nudgeStep"
                    mandatory
                    density="compact"
                    variant="outlined"
                    color="primary"
                    divided
                    rounded="lg"
                  >
                    <VBtn
                      v-for="step in [1, 5, 10]"
                      :key="step"
                      :value="step"
                      size="small"
                    >
                      {{ step }} mm
                    </VBtn>
                  </VBtnToggle>
                </div>

                <div
                  class="nudge-pad"
                  role="group"
                  :aria-label="$t('Fine Position Tuning (mm)')"
                  @keydown.left.prevent="nudgeOffset(-nudgeStep, 0)"
                  @keydown.right.prevent="nudgeOffset(nudgeStep, 0)"
                  @keydown.up.prevent="nudgeOffset(0, -nudgeStep)"
                  @keydown.down.prevent="nudgeOffset(0, nudgeStep)"
                >
                  <VBtn
                    class="nudge-pad__up"
                    icon="tabler-arrow-up"
                    size="small"
                    variant="tonal"
                    :aria-label="$t('Up')"
                    @click="nudgeOffset(0, -nudgeStep)"
                  />
                  <VBtn
                    class="nudge-pad__left"
                    icon="tabler-arrow-left"
                    size="small"
                    variant="tonal"
                    :aria-label="$t('Left')"
                    @click="nudgeOffset(-nudgeStep, 0)"
                  />
                  <VBtn
                    class="nudge-pad__center"
                    icon="tabler-focus-centered"
                    size="small"
                    variant="text"
                    :title="$t('Center in zone')"
                    :aria-label="$t('Center in zone')"
                    @click="resetOffset"
                  />
                  <VBtn
                    class="nudge-pad__right"
                    icon="tabler-arrow-right"
                    size="small"
                    variant="tonal"
                    :aria-label="$t('Right')"
                    @click="nudgeOffset(nudgeStep, 0)"
                  />
                  <VBtn
                    class="nudge-pad__down"
                    icon="tabler-arrow-down"
                    size="small"
                    variant="tonal"
                    :aria-label="$t('Down')"
                    @click="nudgeOffset(0, nudgeStep)"
                  />
                </div>
              </div>

              <AppTextField
                v-model="activePlacement.notes"
                :label="$t('Stitching / Placement Instructions')"
                density="compact"
                class="mt-4"
              />
            </div>
          </div>

          <!-- ━━ Silhouette ━━ -->
          <div
            v-else-if="activeTab === 'silhouette'"
            class="d-flex flex-column gap-4"
          >
            <div class="text-body-2 font-weight-medium">
              {{ $t('2D Garment Template') }}
            </div>
            <div class="template-grid">
              <VBtn
                v-for="sil in WORKWEAR_SILHOUETTES"
                :key="sil.value"
                block
                size="small"
                :variant="modelData.silhouette === sil.value ? 'flat' : 'tonal'"
                :color="modelData.silhouette === sil.value ? 'primary' : 'secondary'"
                class="justify-start text-none px-3"
                :aria-pressed="modelData.silhouette === sil.value"
                @click="applyTemplateSilhouette(sil.value)"
              >
                <VIcon
                  start
                  :icon="sil.icon"
                  size="16"
                />
                <span class="text-truncate">{{ $t(sil.title) }}</span>
              </VBtn>
            </div>

            <VDivider />

            <div class="text-body-2 font-weight-medium">
              {{ $t('Garment Silhouette Taxonomy') }}
            </div>
            <AppSelect
              :model-value="modelData.silhouette_category"
              :items="WORKWEAR_SILHOUETTE_CATALOG"
              item-title="title"
              item-value="value"
              :label="$t('Primary Category')"
              density="compact"
              @update:model-value="handleCategoryChange"
            />
            <AppSelect
              :model-value="modelData.silhouette_subcategory"
              :items="subcategoryOptions"
              item-title="title"
              item-value="value"
              :label="$t('Sub-category')"
              density="compact"
              @update:model-value="handleSubcategoryChange"
            />
            <AppSelect
              :model-value="modelData.silhouette_item"
              :items="silhouetteItemOptions"
              item-title="title"
              item-value="value"
              :label="$t('Garment Item Type')"
              density="compact"
              @update:model-value="handleSilhouetteItemChange"
            />
          </div>

          <!-- ━━ Colors ━━ -->
          <div
            v-else-if="activeTab === 'colors'"
            class="d-flex flex-column gap-5"
          >
            <VAlert
              v-if="catalogExtracted.colors.length"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-0"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                <span class="text-caption">
                  {{ $t('Catalog product defines {count} color option(s).', { count: catalogExtracted.colors.length }) }}
                </span>
                <VBtn
                  size="x-small"
                  color="info"
                  variant="flat"
                  prepend-icon="tabler-download"
                  @click="importColorsFromCatalog"
                >
                  {{ $t('Apply Catalog Colors') }}
                </VBtn>
              </div>
            </VAlert>

            <div
              v-for="colorSlot in [
                { key: 'primary', title: $t('Primary Body Color'), model: primaryColor },
                { key: 'contrast', title: $t('Contrast Accent / Hi-Vis Color'), model: contrastColor },
              ]"
              :key="colorSlot.key"
            >
              <div class="d-flex justify-space-between align-center flex-wrap gap-1 mb-2">
                <span class="text-subtitle-2 font-weight-bold">{{ colorSlot.title }}</span>
                <span class="text-caption text-medium-emphasis font-mono">{{ colorSlot.model }} · {{ resolveColorHex(colorSlot.model) }}</span>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button
                  v-for="swatch in WORKWEAR_COLOR_PALETTE"
                  :key="`${colorSlot.key}-${swatch.value}`"
                  type="button"
                  class="color-swatch-btn"
                  :class="{ active: colorSlot.model === swatch.value }"
                  :style="{ backgroundColor: swatch.hex }"
                  :title="swatch.label"
                  :aria-label="swatch.label"
                  :aria-pressed="colorSlot.model === swatch.value"
                  @click="setSlotColor(colorSlot.key, swatch.value)"
                />
              </div>
            </div>

            <VDivider />

            <div>
              <div class="text-body-2 font-weight-medium mb-2">
                {{ $t('Named Garment Colour Regions') }}
              </div>
              <div class="d-flex flex-column gap-2">
                <div
                  v-for="region in templateColorRegions"
                  :key="region.key"
                  class="region-row"
                >
                  <div class="d-flex align-center gap-2 min-w-0">
                    <span
                      class="region-row__dot"
                      :style="{ backgroundColor: resolveColorHex(resolvedColorRegions[region.key]) }"
                    />
                    <span class="text-body-2 text-truncate">{{ $t(region.label) }}</span>
                  </div>
                  <AppSelect
                    :model-value="resolvedColorRegions[region.key]"
                    :items="WORKWEAR_COLOR_PALETTE"
                    item-title="label"
                    item-value="value"
                    density="compact"
                    hide-details
                    class="region-row__select"
                    :aria-label="$t(region.label)"
                    @update:model-value="val => updateNamedRegionColor(region.key, val)"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- ━━ Sizes ━━ -->
          <div
            v-else-if="activeTab === 'sizes'"
            class="d-flex flex-column gap-3"
          >
            <VAlert
              v-if="catalogExtracted.sizes.length"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-0"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                <span class="text-caption">
                  {{ $t('Catalog product defines sizes:') }} <strong>{{ catalogExtracted.sizes.join(', ') }}</strong>
                </span>
                <VBtn
                  size="x-small"
                  color="info"
                  variant="flat"
                  prepend-icon="tabler-download"
                  @click="importSizesFromCatalog"
                >
                  {{ $t('Import Catalog Sizes') }}
                </VBtn>
              </div>
            </VAlert>

            <div>
              <div class="text-subtitle-2 font-weight-bold">
                {{ $t('Approved Manufacturing Size Range') }}
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Select the standard sizes approved for this workwear model allocation.') }}
              </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="sz in WORKWEAR_STANDARD_SIZES"
                :key="sz"
                :color="(modelData.size_range || []).includes(sz) ? 'primary' : 'secondary'"
                :variant="(modelData.size_range || []).includes(sz) ? 'flat' : 'tonal'"
                :prepend-icon="(modelData.size_range || []).includes(sz) ? 'tabler-check' : undefined"
                :aria-pressed="(modelData.size_range || []).includes(sz)"
                class="font-weight-bold font-mono"
                @click="toggleSize(sz)"
              >
                {{ sz }}
              </VChip>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="text"
                @click="selectAllSizes"
              >
                {{ $t('Select all') }}
              </VBtn>
              <VBtn
                size="small"
                variant="text"
                color="secondary"
                @click="clearSizes"
              >
                {{ $t('Clear') }}
              </VBtn>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #actions="{ close }">
      <span class="text-caption text-medium-emphasis me-auto d-none d-md-inline">
        {{ $t('Changes apply instantly. Save the model to keep them.') }}
      </span>
      <VBtn
        variant="tonal"
        color="secondary"
        :disabled="!hasChanges"
        @click="discardChanges(); close()"
      >
        {{ $t('Discard changes') }}
      </VBtn>
      <VBtn
        color="primary"
        variant="flat"
        prepend-icon="tabler-check"
        @click="close"
      >
        {{ $t('Done') }}
      </VBtn>
    </template>
  </WpZoomDialog>
</template>

<style lang="scss" scoped>
$studio-chrome: 152px;

.studio-layout {
  display: flex;
  flex-direction: row;
  min-block-size: 680px;

  @media (max-width: 960px) {
    flex-direction: column;
    block-size: calc(100dvh - #{$studio-chrome});
    min-block-size: 0;
  }
}

/* ── Canvas ───────────────────────────────────────────────────────────── */
.studio-canvas-pane {
  flex: 1 1 56%;
  background: rgba(var(--v-theme-background), 0.5);
  min-inline-size: 0;
  padding: 1.25rem;

  @media (max-width: 960px) {
    flex: 0 0 auto;
    padding: 0.5rem;
  }
}

.illustration-viewport {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 14px;
  background: rgba(var(--v-theme-surface), 0.8);
  padding: 0.75rem;

  @media (max-width: 960px) {
    padding: 0.25rem;
  }
}

@media (max-width: 960px) {
  .illustration-viewport :deep(.studio-canvas-wrap) {
    min-block-size: 0;
    padding-block: 8px 12px;
  }

  .illustration-viewport :deep(.garment-svg) {
    max-block-size: 30dvh;
  }

  .illustration-viewport :deep(.studio-hud-bar),
  .illustration-viewport :deep(.studio-legend) {
    display: none;
  }
}

/* ── Controls ─────────────────────────────────────────────────────────── */
.studio-controls-pane {
  display: flex;
  flex: 1 1 44%;
  flex-direction: column;
  border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  inline-size: 100%;
  max-inline-size: 560px;
  min-block-size: 0;

  @media (max-width: 960px) {
    flex: 1 1 0;
    border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-inline-start: 0;
    max-inline-size: 100%;
  }
}

.controls-scrollable {
  flex: 1;
  overflow-y: auto;
  max-block-size: calc(85vh - 120px);
  min-block-size: 0;
  padding: 1rem;

  @media (max-width: 960px) {
    max-block-size: none;
  }
}

.zone-bar {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  padding: 0.75rem;
}

.inspector {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 0.875rem;
}

/* ── Nudge pad ────────────────────────────────────────────────────────── */
.nudge {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.nudge-pad {
  display: grid;
  gap: 4px;
  grid-template: "a up b" "left center right" "c down d" / 40px 40px 40px;

  &__up { grid-area: up; }
  &__left { grid-area: left; }
  &__center { grid-area: center; }
  &__right { grid-area: right; }
  &__down { grid-area: down; }
}

/* ── Silhouette templates ─────────────────────────────────────────────── */
.template-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

/* ── Colors ───────────────────────────────────────────────────────────── */
.color-swatch-btn {
  border: 2px solid transparent;
  border-radius: 8px;
  block-size: 36px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 15%);
  cursor: pointer;
  inline-size: 36px;
  transition: transform 0.15s ease, border-color 0.15s ease;

  &:hover {
    transform: scale(1.1);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &.active {
    border-color: rgb(var(--v-theme-surface));
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
  }

  @media (prefers-reduced-motion: reduce) {
    transition: none;
  }
}

.region-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;

  &__dot {
    flex-shrink: 0;
    border: 1px solid rgba(var(--v-border-color), 0.3);
    border-radius: 50%;
    block-size: 16px;
    inline-size: 16px;
  }

  &__select {
    flex: 1 1 150px;
    max-inline-size: 190px;
  }
}
</style>
