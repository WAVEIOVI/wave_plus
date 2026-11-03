<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import {
  WORKWEAR_APPLICATION_METHODS,
  WORKWEAR_COLOR_PALETTE,
  WORKWEAR_PLACEMENT_TYPES,
  clampPlacementToZoneBounds,
  getTemplateZones,
  normalizePlacementForTemplate,
  resolvePlacementTypeMeta,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  modelData: { type: Object, required: true },
  logos: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'triggerUpload'])

const { t } = useI18n()

const activeView = ref('front')
const selectedPlacementIndex = ref(0)

const activePlacement = computed(() => {
  const list = props.modelData?.placements || []
  if (selectedPlacementIndex.value >= 0 && selectedPlacementIndex.value < list.length) {
    return list[selectedPlacementIndex.value]
  }

  return null
})

const silhouetteSummary = computed(() => resolveSilhouetteDisplay(props.modelData))

const availableZonesForView = computed(() => {
  const view = activePlacement.value?.view || activeView.value || 'front'
  const sil = props.modelData?.silhouette || 'coverall'

  return getTemplateZones(sil, view)
})

const logoSelectOptions = computed(() => [
  { title: t('No linked artwork'), value: null },
  ...(props.logos || []).map(l => ({
    title: l.name || `${t('Logo')} #${l.id}`,
    value: l.id,
  })),
])

function addPlacement(type = 'logo', presetZone = null) {
  if (props.readonly) return
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
}

function handleZoneClick(zone) {
  if (props.readonly) return

  const existingIdx = (props.modelData.placements || []).findIndex(
    p => (p.view || 'front') === activeView.value && p.zone === zone.value,
  )

  if (existingIdx >= 0) {
    selectedPlacementIndex.value = existingIdx
  } else {
    addPlacement('logo', zone)
  }
}

function removePlacement(idx) {
  if (props.readonly || !Array.isArray(props.modelData.placements)) return
  props.modelData.placements.splice(idx, 1)
  if (selectedPlacementIndex.value >= props.modelData.placements.length) {
    selectedPlacementIndex.value = props.modelData.placements.length - 1
  }
}

function nudgeOffset(dx, dy) {
  if (!activePlacement.value || props.readonly) return
  const nextX = Number(activePlacement.value.offset_x_mm || 0) + dx
  const nextY = Number(activePlacement.value.offset_y_mm || 0) + dy

  const clamped = clampPlacementToZoneBounds(
    { ...activePlacement.value, offset_x_mm: nextX, offset_y_mm: nextY },
    props.modelData?.silhouette || 'coverall',
  )

  activePlacement.value.offset_x_mm = clamped.offset_x_mm
  activePlacement.value.offset_y_mm = clamped.offset_y_mm
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1360"
    :fullscreen="$vuetify.display.smAndDown"
    scrollable
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard class="studio-zoom-dialog d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between px-5 py-3 border-b gap-3">
        <div class="d-flex align-center gap-3 min-w-0">
          <VAvatar
            color="primary"
            variant="tonal"
            size="38"
            rounded
          >
            <VIcon
              icon="tabler-vector-bezier-2"
              size="20"
            />
          </VAvatar>
          <div class="min-w-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <h6 class="text-subtitle-1 font-weight-bold mb-0">
                {{ modelData.name || $t('Garment Studio Zoom') }}
              </h6>
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ silhouetteSummary.itemTitle }}
              </VChip>
              <VChip
                size="x-small"
                color="info"
                variant="tonal"
                class="font-mono"
              >
                {{ (modelData.placements || []).length }} {{ $t('Placements') }}
              </VChip>
            </div>
            <span class="text-caption text-medium-emphasis">
              {{ $t('Click garment zones to place artwork') }}
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <template v-if="!readonly">
            <VBtn
              v-for="pt in WORKWEAR_PLACEMENT_TYPES.slice(0, 5)"
              :key="pt.value"
              size="x-small"
              variant="tonal"
              color="primary"
              :prepend-icon="pt.icon"
              class="d-none d-md-inline-flex"
              @click="addPlacement(pt.value)"
            >
              + {{ $t(pt.title) }}
            </VBtn>
          </template>
          <IconBtn
            size="small"
            :aria-label="$t('Close')"
            @click="emit('update:modelValue', false)"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </VCardTitle>

      <!-- Body -->
      <VCardText class="pa-4 pa-md-6">
        <VRow>
          <!-- Large Dual / Interactive Canvas -->
          <VCol
            cols="12"
            :md="readonly ? 12 : 7"
          >
            <div class="zoomed-canvas-container">
              <WorkwearGarmentIllustration
                v-model:active-view="activeView"
                v-model:selected-placement-index="selectedPlacementIndex"
                :silhouette="modelData.silhouette || 'coverall'"
                :colors="modelData.colors"
                :placements="modelData.placements"
                :logos="logos"
                :readonly="readonly"
                @zone-click="handleZoneClick"
              />
            </div>
          </VCol>

          <!-- Precision Inspector Panel -->
          <VCol
            v-if="!readonly"
            cols="12"
            md="5"
          >
            <VCard
              variant="outlined"
              class="pa-4 h-100 d-flex flex-column gap-3"
            >
              <div class="d-flex align-center justify-space-between">
                <span class="text-subtitle-2 font-weight-bold">
                  {{ $t('Placement Precision Inspector') }}
                </span>
                <VBtn
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  @click="addPlacement('logo')"
                >
                  {{ $t('New Placement') }}
                </VBtn>
              </div>

              <!-- Placement selector pills -->
              <div
                v-if="modelData.placements?.length"
                class="d-flex flex-wrap gap-1"
              >
                <VChip
                  v-for="(pl, idx) in modelData.placements"
                  :key="pl.id || idx"
                  size="small"
                  :color="selectedPlacementIndex === idx ? 'primary' : 'secondary'"
                  :variant="selectedPlacementIndex === idx ? 'flat' : 'tonal'"
                  closable
                  class="cursor-pointer"
                  @click="
                    selectedPlacementIndex = idx;
                    activeView = pl.view || 'front';
                  "
                  @click:close="removePlacement(idx)"
                >
                  <span class="font-mono me-1">{{ (pl.view || 'front') === 'front' ? 'F' : 'B' }}{{ idx + 1 }}:</span>
                  {{ pl.label || pl.zone }}
                </VChip>
              </div>

              <VDivider />

              <template v-if="activePlacement">
                <VRow dense>
                  <VCol cols="7">
                    <AppTextField
                      v-model="activePlacement.label"
                      :label="$t('Placement Label')"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="5">
                    <AppSelect
                      v-model="activePlacement.view"
                      :label="$t('Garment View')"
                      :items="[
                        { title: $t('Front View'), value: 'front' },
                        { title: $t('Back View'), value: 'back' },
                      ]"
                      density="compact"
                      @update:model-value="v => { activeView = v }"
                    />
                  </VCol>

                  <VCol cols="6">
                    <AppSelect
                      v-model="activePlacement.type"
                      :label="$t('Item Type')"
                      :items="WORKWEAR_PLACEMENT_TYPES"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="6">
                    <AppSelect
                      v-model="activePlacement.zone"
                      :label="$t('Garment Anchor Zone')"
                      :items="availableZonesForView"
                      density="compact"
                    />
                  </VCol>

                  <VCol cols="12">
                    <AppCombobox
                      v-model="activePlacement.method"
                      :label="$t('Print / Application Type')"
                      :items="WORKWEAR_APPLICATION_METHODS"
                      density="compact"
                    />
                  </VCol>

                  <!-- Artwork attachment for Logo / Flag / Badge -->
                  <VCol
                    v-if="['logo', 'flag', 'badge'].includes(activePlacement.type)"
                    cols="12"
                  >
                    <div class="d-flex align-end gap-2">
                      <AppSelect
                        v-model="activePlacement.media_id"
                        :label="$t('Attached Artwork / Logo')"
                        :items="logoSelectOptions"
                        density="compact"
                        class="flex-grow-1"
                      />
                      <VBtn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="tabler-upload"
                        @click="emit('triggerUpload', 'logos')"
                      >
                        {{ $t('Upload') }}
                      </VBtn>
                    </div>
                  </VCol>

                  <VCol cols="8">
                    <AppTextField
                      v-model="activePlacement.text"
                      :label="$t('Custom Text / Flag Code / Spec')"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="4">
                    <AppSelect
                      v-model="activePlacement.color"
                      :label="$t('Accent Color')"
                      :items="WORKWEAR_COLOR_PALETTE.map(c => ({ title: c.label, value: c.hex }))"
                      density="compact"
                    />
                  </VCol>

                  <VCol cols="3">
                    <AppTextField
                      v-model.number="activePlacement.width_mm"
                      type="number"
                      :label="$t('W (mm)')"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="3">
                    <AppTextField
                      v-model.number="activePlacement.height_mm"
                      type="number"
                      :label="$t('H (mm)')"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="3">
                    <AppTextField
                      v-model.number="activePlacement.offset_x_mm"
                      type="number"
                      :label="$t('X (mm)')"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="3">
                    <AppTextField
                      v-model.number="activePlacement.offset_y_mm"
                      type="number"
                      :label="$t('Y (mm)')"
                      density="compact"
                    />
                  </VCol>

                  <!-- Quick D-Pad Nudge Controls -->
                  <VCol cols="12">
                    <div class="d-flex align-center justify-space-between pa-2 rounded bg-var-theme-background">
                      <span class="text-caption font-weight-medium">{{ $t('Fine Position Nudge (5 mm)') }}</span>
                      <div class="d-flex align-center gap-1">
                        <VBtn
                          icon="tabler-arrow-left"
                          size="x-small"
                          variant="tonal"
                          :aria-label="$t('Left')"
                          @click="nudgeOffset(-5, 0)"
                        />
                        <VBtn
                          icon="tabler-arrow-up"
                          size="x-small"
                          variant="tonal"
                          :aria-label="$t('Up')"
                          @click="nudgeOffset(0, -5)"
                        />
                        <VBtn
                          icon="tabler-arrow-down"
                          size="x-small"
                          variant="tonal"
                          :aria-label="$t('Down')"
                          @click="nudgeOffset(0, 5)"
                        />
                        <VBtn
                          icon="tabler-arrow-right"
                          size="x-small"
                          variant="tonal"
                          :aria-label="$t('Right')"
                          @click="nudgeOffset(5, 0)"
                        />
                      </div>
                    </div>
                  </VCol>

                  <VCol cols="12">
                    <AppTextField
                      v-model="activePlacement.notes"
                      :label="$t('Stitching / Placement Instructions')"
                      density="compact"
                    />
                  </VCol>
                </VRow>
              </template>
              <div
                v-else
                class="text-center py-8 text-medium-emphasis my-auto"
              >
                {{ $t('Select a placement on the garment canvas or click + New Placement.') }}
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 py-3 border-t">
        <VBtn
          color="primary"
          variant="flat"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Done') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.zoomed-canvas-container :deep(.studio-canvas-wrap) {
  min-block-size: 560px;
}

.zoomed-canvas-container :deep(.garment-svg) {
  max-inline-size: 420px;
  max-block-size: 520px;
}
</style>
