<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import {
  resolvePlacementTypeMeta,
  resolveSilhouetteDisplay,
  resolveColorHex,
  useWorkwearStudioConstants,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import WorkwearStudioEditorDialog from '@/views/client-assets/workwear/models/components/WorkwearStudioEditorDialog.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelData: { type: Object, required: true },
  logos: { type: Array, default: () => [] },
  uploadMediaFile: { type: Function, default: null },
})

const emit = defineEmits(['triggerUpload'])
const { t, locale } = useI18n()
const { resolveColorName } = useWorkwearStudioConstants()

const activeView = ref('front')
const isStudioDialogOpen = ref(false)

// Defaults the whole studio relies on (kept identical to the editor dialog).
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
  },
  { immediate: true },
)

const silhouetteSummary = computed(() =>
  resolveSilhouetteDisplay(props.modelData, { locale: locale.value, t }),
)

const primaryColor = computed(() => props.modelData.colors?.[0] || 'navy')
const contrastColor = computed(() => props.modelData.colors?.[1] || 'hi-vis-yellow')

const colorSwatches = computed(() => [
  { key: 'primary', label: t('Primary color'), value: primaryColor.value },
  { key: 'contrast', label: t('Contrast color'), value: contrastColor.value },
])

const placements = computed(() => props.modelData.placements || [])
const placementsCount = computed(() => placements.value.length)

const frontPlacements = computed(() =>
  placements.value.filter(p => (p.view || 'front') === 'front'),
)

const backPlacements = computed(() =>
  placements.value.filter(p => (p.view || 'front') === 'back'),
)

const viewPlacementsCount = computed(() =>
  (activeView.value === 'front' ? frontPlacements.value : backPlacements.value).length,
)

const openStudio = () => {
  isStudioDialogOpen.value = true
}
</script>

<template>
  <section
    id="section-studio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-vector-bezier-2"
      :title="$t('Garment Studio (2D)')"
      :subtitle="$t('Configured 2D vector silhouette, brand color scheme, approved sizes, and custom placements.')"
    >
      <template #actions>
        <VChip
          size="small"
          color="info"
          variant="tonal"
        >
          {{ silhouetteSummary.itemTitle }}
        </VChip>
        <VBtn
          color="primary"
          prepend-icon="tabler-vector-bezier-2"
          @click="openStudio"
        >
          {{ $t('Open Studio Editor') }}
        </VBtn>
      </template>

      <VRow>
        <!-- Drawing -->
        <VCol
          cols="12"
          md="6"
        >
          <div class="studio-preview">
            <div class="studio-preview__bar">
              <VBtnToggle
                v-model="activeView"
                mandatory
                density="compact"
                color="primary"
                variant="outlined"
                rounded="lg"
                :aria-label="$t('Garment View')"
              >
                <VBtn
                  value="front"
                  size="small"
                  prepend-icon="tabler-eye"
                >
                  {{ $t('Front') }}
                </VBtn>
                <VBtn
                  value="back"
                  size="small"
                  prepend-icon="tabler-rotate-clockwise-2"
                >
                  {{ $t('Back') }}
                </VBtn>
              </VBtnToggle>

              <span class="text-caption text-medium-emphasis">
                {{ viewPlacementsCount }} {{ $t('placements') }}
              </span>
            </div>

            <div class="canvas-viewport">
              <WorkwearGarmentIllustration
                v-model:active-view="activeView"
                :silhouette="modelData.silhouette || 'coverall'"
                :colors="modelData.colors"
                :placements="modelData.placements"
                :logos="logos"
                compact
                readonly
                @zone-click="openStudio"
              />
            </div>
          </div>
        </VCol>

        <!-- Summary -->
        <VCol
          cols="12"
          md="6"
        >
          <div class="d-flex flex-column gap-5">
            <!-- Garment -->
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="40"
                color="primary"
                variant="tonal"
                rounded
              >
                <VIcon
                  icon="tabler-shirt"
                  size="22"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="text-subtitle-1 font-weight-bold text-break">
                  {{ silhouetteSummary.itemTitle }}
                </div>
                <span class="text-body-2 text-medium-emphasis">
                  {{ silhouetteSummary.categoryTitle }} · {{ silhouetteSummary.subcategoryTitle }}
                </span>
              </div>
            </div>

            <!-- Colors -->
            <div class="d-flex flex-wrap gap-3">
              <div
                v-for="swatch in colorSwatches"
                :key="swatch.key"
                class="swatch-tile"
              >
                <span
                  class="swatch-tile__dot"
                  :style="{ backgroundColor: resolveColorHex(swatch.value) }"
                />
                <div class="min-w-0">
                  <span class="text-caption text-medium-emphasis d-block">{{ swatch.label }}</span>
                  <span class="text-body-2 font-weight-medium text-capitalize">{{ resolveColorName(swatch.value) }}</span>
                </div>
              </div>
            </div>

            <!-- Sizes -->
            <div>
              <span class="text-body-2 text-medium-emphasis d-block mb-2">{{ $t('Approved Sizes') }}</span>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="sz in (modelData.size_range || [])"
                  :key="sz"
                  size="small"
                  color="primary"
                  variant="tonal"
                  class="font-mono font-weight-bold"
                >
                  {{ sz }}
                </VChip>
              </div>
            </div>

            <!-- Placements -->
            <div>
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-2">
                <span class="text-body-2 text-medium-emphasis">
                  {{ $t('Customization Placements') }} ({{ placementsCount }})
                </span>
                <div
                  v-if="placementsCount"
                  class="d-flex gap-1"
                >
                  <VChip
                    size="x-small"
                    variant="outlined"
                  >
                    {{ $t('Front') }} {{ frontPlacements.length }}
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="outlined"
                  >
                    {{ $t('Back') }} {{ backPlacements.length }}
                  </VChip>
                </div>
              </div>

              <div
                v-if="placementsCount"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="(pl, idx) in placements"
                  :key="pl.id || idx"
                  size="small"
                  variant="tonal"
                  color="secondary"
                  @click="openStudio"
                >
                  <VIcon
                    start
                    :icon="resolvePlacementTypeMeta(pl.type).icon"
                    size="14"
                  />
                  {{ pl.label || pl.zone?.replace(/_/g, ' ') }}
                  <span class="ms-1 text-medium-emphasis">· {{ $t((pl.view || 'front') === 'back' ? 'Back' : 'Front') }}</span>
                </VChip>
              </div>

              <div
                v-else
                class="empty-placements"
              >
                <VIcon
                  icon="tabler-vector-bezier"
                  size="24"
                  class="text-disabled"
                />
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ $t('No placements yet. Open the studio to add logos, text or fluo strips.') }}
                </p>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>

      <WorkwearStudioEditorDialog
        v-model="isStudioDialogOpen"
        :model-data="modelData"
        :logos="logos"
        :upload-media-file="uploadMediaFile"
        @trigger-upload="emit('triggerUpload', $event)"
      />
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.studio-preview {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 0.75rem;

  &__bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    margin-block-end: 0.5rem;
    gap: 0.5rem;
  }

  // The illustration ships a compact mode sized for dialogs; give it more room here.
  .canvas-viewport :deep(.is-compact .studio-canvas-wrap) {
    min-block-size: 240px;
  }

  .canvas-viewport :deep(.is-compact .garment-svg) {
    max-block-size: 230px;
  }
}

.swatch-tile {
  display: flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  flex: 1 1 140px;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;

  &__dot {
    flex-shrink: 0;
    border: 1px solid rgba(var(--v-border-color), 0.3);
    border-radius: 50%;
    block-size: 24px;
    inline-size: 24px;
  }
}

.empty-placements {
  display: flex;
  align-items: center;
  border: 1px dashed rgba(var(--v-border-color), 0.4);
  border-radius: 10px;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
}
</style>
