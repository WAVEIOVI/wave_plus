<script setup>
/* eslint-disable camelcase */
import {
  extractSilhouetteSpecBadge,
  findSilhouetteItemMeta,
  formatSilhouetteShortTitle,
  formatSubcategoryShortTitle,
  getSilhouetteItems,
  getSilhouetteSubcategories,
  resolveColorHex,
  resolveSilhouetteDisplay,
  useWorkwearStudioConstants,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  category: { type: String, default: 'clothing' },
  subcategory: { type: String, default: 'one_piece' },
  silhouetteItem: { type: String, default: 'single_zip_coverall' },
  silhouette: { type: String, default: 'coverall' },
  fabricId: { type: [String, Number], default: null },
  fabric: { type: String, default: '' },
  colors: { type: Array, default: () => ['navy', 'hi-vis-yellow'] },
  fabrics: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:category',
  'update:subcategory',
  'update:silhouetteItem',
  'update:silhouette',
  'update:fabricId',
  'update:fabric',
  'update:colors',
])

const { t, locale } = useI18n()

// Preview view is local: the compact drawing's Front/Back pill needs a listener.
const previewView = ref('front')

// Active color slot being edited: 0 = Primary Color, 1 = Contrast Color
const activeColorSlot = ref(0)

const {
  localizedSilhouetteCatalog,
  localizedColorPalette,
  WORKWEAR_SILHOUETTES,
} = useWorkwearStudioConstants()

// Current category object
const activeCategoryObj = computed(() =>
  localizedSilhouetteCatalog.value.find(c => c.value === props.category) || localizedSilhouetteCatalog.value[0],
)

// Subcategories of active category
const subcategories = computed(() =>
  activeCategoryObj.value?.subcategories || [],
)

// Specific silhouette items in active subcategory
const silhouetteItems = computed(() => {
  const activeSub = subcategories.value.find(s => s.value === props.subcategory) || subcategories.value[0]

  return activeSub?.items || []
})

// Selected fabric details
const selectedFabricObj = computed(() =>
  props.fabrics.find(f => f.id === props.fabricId) || null,
)

// Live silhouette display summary for CAD preview
const silhouetteSummary = computed(() =>
  resolveSilhouetteDisplay({
    silhouette_category: props.category,
    silhouette_subcategory: props.subcategory,
    silhouette_item: props.silhouetteItem,
    silhouette: props.silhouette,
  }, { locale: locale.value, t }),
)

// Helper to get silhouette icon from renderer
function getSilhouetteIcon(renderer) {
  const match = WORKWEAR_SILHOUETTES.find(s => s.value === renderer)

  return match?.icon || 'tabler-shirt'
}

// ── Selection Handlers ──────────────────────────────────────────────────────────
function onSelectCategory(catValue) {
  emit('update:category', catValue)

  const subs = getSilhouetteSubcategories(catValue)
  const firstSub = subs[0]
  const firstSubValue = firstSub?.value || null

  emit('update:subcategory', firstSubValue)

  const items = getSilhouetteItems(catValue, firstSubValue)
  const firstItem = items[0]

  emit('update:silhouetteItem', firstItem?.value || null)
  emit('update:silhouette', firstItem?.renderer || firstSub?.defaultRenderer || 'coverall')
}

function onSelectSubcategory(subValue) {
  emit('update:subcategory', subValue)

  const items = getSilhouetteItems(props.category, subValue)
  const firstItem = items[0]

  emit('update:silhouetteItem', firstItem?.value || null)
  emit('update:silhouette', firstItem?.renderer || 'coverall')
}

function onSelectSilhouetteItem(item) {
  emit('update:silhouetteItem', item.value)

  const meta = findSilhouetteItemMeta(props.category, props.subcategory, item.value)
  const renderer = meta?.renderer || item.renderer || 'coverall'

  emit('update:silhouette', renderer)
}

function onFabricChange(fabricId) {
  emit('update:fabricId', fabricId)

  const found = props.fabrics.find(f => f.id === fabricId)
  if (found) {
    emit('update:fabric', found.name)
  }
}

// ── Color Selection Handlers (Editing Primary and Contrast Slots) ──────────────
function selectColor(colorValue) {
  const currentColors = props.colors && props.colors.length ? [...props.colors] : ['navy']

  if (activeColorSlot.value === 0) {
    // Directly update the Primary color (Slot 0)
    if (currentColors[1] === colorValue) {
      // If user sets primary to existing contrast, swap them
      currentColors[1] = currentColors[0]
    }
    currentColors[0] = colorValue
    emit('update:colors', currentColors)
  } else {
    // Directly update or add the Contrast color (Slot 1)
    if (currentColors[0] === colorValue) {
      // If same as primary, keep single-color garment
      emit('update:colors', [currentColors[0]])
    } else {
      currentColors[1] = colorValue
      emit('update:colors', currentColors)
    }
  }
}

function removeContrastColor() {
  if (props.colors.length > 1) {
    emit('update:colors', [props.colors[0]])
    activeColorSlot.value = 0
  }
}

function swapColors() {
  if (props.colors.length >= 2) {
    emit('update:colors', [props.colors[1], props.colors[0]])
  }
}
</script>

<template>
  <div class="silhouette-step">
    <!-- ━━━ Live preview (pinned strip on phones, side column from md) ━━━ -->
    <aside
      class="silhouette-preview"
      :aria-label="$t('Live Garment Drawing')"
    >
      <VCard
        variant="outlined"
        class="preview-card overflow-hidden"
      >
        <div class="preview-head d-none d-md-flex">
          <span class="text-body-2 font-weight-medium d-flex align-center gap-2">
            <VIcon
              icon="tabler-vector-bezier-2"
              color="primary"
              size="18"
            />
            {{ $t('Live Garment Drawing') }}
          </span>
          <VChip
            size="x-small"
            color="primary"
            variant="tonal"
            class="font-mono"
          >
            {{ silhouetteSummary.renderer }}
          </VChip>
        </div>

        <div class="preview-body">
          <div class="preview-drawing">
            <WorkwearGarmentIllustration
              v-model:active-view="previewView"
              :silhouette="props.silhouette"
              :colors="props.colors"
              readonly
              compact
            />
          </div>

          <div class="preview-summary">
            <div class="text-body-2 font-weight-bold text-high-emphasis text-truncate">
              {{ formatSilhouetteShortTitle(silhouetteItems.find(i => i.value === props.silhouetteItem) || { title: silhouetteSummary.itemTitle }, locale) }}
            </div>
            <div class="text-caption text-medium-emphasis text-truncate">
              {{ silhouetteSummary.categoryTitle }} › {{ formatSubcategoryShortTitle(subcategories.find(s => s.value === props.subcategory) || { title: silhouetteSummary.subcategoryTitle }, locale) }}
            </div>
            <div class="d-flex flex-wrap align-center gap-2 mt-2">
              <span class="d-inline-flex align-center gap-1">
                <span
                  class="mini-dot"
                  :style="{ backgroundColor: resolveColorHex(props.colors[0], '#1e293b') }"
                />
                <span class="text-caption">{{ localizedColorPalette.find(c => c.value === props.colors[0])?.label || props.colors[0] }}</span>
              </span>
              <span
                v-if="props.colors[1]"
                class="d-inline-flex align-center gap-1"
              >
                <span
                  class="mini-dot"
                  :style="{ backgroundColor: resolveColorHex(props.colors[1], '#eab308') }"
                />
                <span class="text-caption">{{ localizedColorPalette.find(c => c.value === props.colors[1])?.label || props.colors[1] }}</span>
              </span>
            </div>
          </div>
        </div>
      </VCard>
    </aside>

    <!-- ━━━ Controls ━━━ -->
    <div class="silhouette-controls">
      <!-- 1 · Silhouette classification -->
      <VCard
        variant="outlined"
        class="pa-4"
      >
        <div class="step-head">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              icon="tabler-hierarchy-2"
              size="20"
            />
          </VAvatar>
          <div>
            <h4 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('Silhouette Classification') }}
            </h4>
            <div class="text-caption text-medium-emphasis">
              {{ $t('Category › Sub-Category › Garment Silhouette') }}
            </div>
          </div>
        </div>

        <!-- Tier 1 -->
        <div class="mb-5">
          <div class="tier-label">
            <span class="tier-number">1</span>
            <span class="text-body-2 font-weight-medium">{{ $t('Product Category') }}</span>
          </div>

          <div
            class="category-grid"
            role="radiogroup"
            :aria-label="$t('Product Category')"
          >
            <div
              v-for="cat in localizedSilhouetteCatalog"
              :key="cat.value"
              class="pick-card category-card"
              :class="{ 'pick-card--active': props.category === cat.value }"
              role="radio"
              tabindex="0"
              :aria-checked="props.category === cat.value"
              @click="onSelectCategory(cat.value)"
              @keydown.enter.prevent="onSelectCategory(cat.value)"
              @keydown.space.prevent="onSelectCategory(cat.value)"
            >
              <VAvatar
                :color="props.category === cat.value ? 'primary' : 'secondary'"
                :variant="props.category === cat.value ? 'flat' : 'tonal'"
                size="36"
              >
                <VIcon
                  :icon="cat.icon"
                  size="20"
                />
              </VAvatar>
              <span class="text-body-2 font-weight-medium text-truncate w-100 text-center">
                {{ cat.title }}
              </span>
            </div>
          </div>
        </div>

        <!-- Tier 2 -->
        <div
          v-if="subcategories.length"
          class="mb-5"
        >
          <div class="tier-label justify-space-between">
            <div class="d-flex align-center gap-2">
              <span class="tier-number">2</span>
              <span class="text-body-2 font-weight-medium">{{ $t('Sub-Category') }}</span>
            </div>
            <span class="text-caption text-medium-emphasis">
              {{ subcategories.length }} {{ $t('subcategories') }}
            </span>
          </div>

          <div
            class="pick-grid"
            role="radiogroup"
            :aria-label="$t('Sub-Category')"
          >
            <div
              v-for="sub in subcategories"
              :key="sub.value"
              class="pick-card pick-card--row"
              :class="{ 'pick-card--active': props.subcategory === sub.value }"
              role="radio"
              tabindex="0"
              :aria-checked="props.subcategory === sub.value"
              :title="locale === 'fr' ? (sub.titleFr || sub.title) : sub.title"
              @click="onSelectSubcategory(sub.value)"
              @keydown.enter.prevent="onSelectSubcategory(sub.value)"
              @keydown.space.prevent="onSelectSubcategory(sub.value)"
            >
              <VAvatar
                size="28"
                :color="props.subcategory === sub.value ? 'primary' : 'secondary'"
                :variant="props.subcategory === sub.value ? 'flat' : 'tonal'"
                rounded
                class="flex-shrink-0"
              >
                <VIcon
                  :icon="getSilhouetteIcon(sub.defaultRenderer)"
                  size="16"
                />
              </VAvatar>
              <span class="pick-title">{{ formatSubcategoryShortTitle(sub, locale) }}</span>
              <VIcon
                v-if="props.subcategory === sub.value"
                icon="tabler-check"
                color="primary"
                size="16"
                class="flex-shrink-0 ms-auto"
              />
            </div>
          </div>
        </div>

        <!-- Tier 3 -->
        <div>
          <div class="tier-label justify-space-between">
            <div class="d-flex align-center gap-2">
              <span class="tier-number">3</span>
              <span class="text-body-2 font-weight-medium">{{ $t('Specific Garment Silhouette') }}</span>
            </div>
            <span class="text-caption text-medium-emphasis">
              {{ silhouetteItems.length }} {{ $t('available') }}
            </span>
          </div>

          <PerfectScrollbar
            :options="{ wheelPropagation: false, suppressScrollX: true }"
            class="silhouette-items-scrollbar"
          >
            <div
              class="pick-grid"
              role="radiogroup"
              :aria-label="$t('Specific Garment Silhouette')"
            >
              <div
                v-for="item in silhouetteItems"
                :key="item.value"
                class="pick-card pick-card--row pick-card--tall"
                :class="{ 'pick-card--active': props.silhouetteItem === item.value }"
                role="radio"
                tabindex="0"
                :aria-checked="props.silhouetteItem === item.value"
                :title="locale === 'fr' ? (item.titleFr || item.title) : item.title"
                @click="onSelectSilhouetteItem(item)"
                @keydown.enter.prevent="onSelectSilhouetteItem(item)"
                @keydown.space.prevent="onSelectSilhouetteItem(item)"
              >
                <VAvatar
                  size="32"
                  :color="props.silhouetteItem === item.value ? 'primary' : 'secondary'"
                  :variant="props.silhouetteItem === item.value ? 'flat' : 'tonal'"
                  rounded
                  class="flex-shrink-0"
                >
                  <VIcon
                    :icon="getSilhouetteIcon(item.renderer)"
                    size="18"
                  />
                </VAvatar>

                <span class="pick-title">{{ formatSilhouetteShortTitle(item, locale) }}</span>
                <span
                  v-if="item.specBadge || extractSilhouetteSpecBadge(item)"
                  class="spec-badge"
                >
                  {{ item.specBadge || extractSilhouetteSpecBadge(item) }}
                </span>

                <VIcon
                  v-if="props.silhouetteItem === item.value"
                  icon="tabler-circle-check-filled"
                  color="primary"
                  size="18"
                  class="flex-shrink-0 ms-auto"
                />
              </div>
            </div>
          </PerfectScrollbar>
        </div>
      </VCard>

      <!-- 2 · Fabric -->
      <VCard
        variant="outlined"
        class="pa-4"
      >
        <div class="step-head">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              icon="tabler-layers-intersect"
              size="20"
            />
          </VAvatar>
          <div class="flex-grow-1">
            <h4 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('Technical Workwear Fabric') }}
            </h4>
            <div class="text-caption text-medium-emphasis">
              {{ $t('Textile composition, weave & safety properties') }}
            </div>
          </div>
          <VChip
            v-if="selectedFabricObj?.weight_gsm"
            size="small"
            color="info"
            variant="tonal"
            class="font-mono font-weight-bold"
          >
            {{ selectedFabricObj.weight_gsm }} g/m²
          </VChip>
        </div>

        <AppAutocomplete
          :model-value="props.fabricId"
          :items="props.fabrics"
          item-title="name"
          item-value="id"
          :label="$t('Select Technical Fabric')"
          :placeholder="$t('Search and select certified fabric...')"
          prepend-inner-icon="tabler-layers-intersect"
          clearable
          @update:model-value="onFabricChange"
        >
          <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps">
              <template #subtitle>
                <span class="text-caption">
                  {{ item.raw.code }} • {{ item.raw.composition || '—' }}
                  <template v-if="item.raw.weight_gsm">
                    ({{ item.raw.weight_gsm }} g/m²)
                  </template>
                </span>
              </template>
            </VListItem>
          </template>
        </AppAutocomplete>

        <div
          v-if="selectedFabricObj"
          class="fabric-info mt-3"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-1">
            <span class="text-body-2 font-weight-medium text-high-emphasis">
              {{ selectedFabricObj.composition }}
              <span v-if="selectedFabricObj.weave_type">• {{ selectedFabricObj.weave_type }}</span>
            </span>
            <span
              v-if="selectedFabricObj.code"
              class="text-caption font-mono text-medium-emphasis"
            >
              {{ selectedFabricObj.code }}
            </span>
          </div>
          <div
            v-if="selectedFabricObj.properties?.length"
            class="d-flex flex-wrap gap-1 mt-2"
          >
            <VChip
              v-for="property in selectedFabricObj.properties"
              :key="property"
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ property }}
            </VChip>
          </div>
        </div>
      </VCard>

      <!-- 3 · Colors -->
      <VCard
        variant="outlined"
        class="pa-4"
      >
        <div class="step-head">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              icon="tabler-palette"
              size="20"
            />
          </VAvatar>
          <div class="flex-grow-1">
            <h4 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('Color Scheme (Max 2 Colors)') }}
            </h4>
            <div class="text-caption text-medium-emphasis">
              {{ $t('Select a slot below to edit Primary or Contrast color') }}
            </div>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn
              v-if="props.colors.length >= 2"
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrows-left-right"
              :title="$t('Swap Primary & Contrast Colors')"
              @click="swapColors"
            >
              {{ $t('Swap') }}
            </VBtn>
            <VChip
              size="small"
              :color="props.colors.length === 2 ? 'primary' : 'secondary'"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ props.colors.length }} / 2 {{ $t('Selected') }}
            </VChip>
          </div>
        </div>

        <!-- Slots -->
        <div
          class="color-slots"
          role="radiogroup"
          :aria-label="$t('Color Scheme (Max 2 Colors)')"
        >
          <div
            class="color-slot"
            :class="{ 'color-slot--active': activeColorSlot === 0 }"
            role="radio"
            tabindex="0"
            :aria-checked="activeColorSlot === 0"
            @click="activeColorSlot = 0"
            @keydown.enter.prevent="activeColorSlot = 0"
            @keydown.space.prevent="activeColorSlot = 0"
          >
            <span
              class="color-dot"
              :style="{ backgroundColor: resolveColorHex(props.colors[0], '#1e293b') }"
            />
            <div class="min-w-0 flex-grow-1">
              <div class="text-caption text-medium-emphasis">
                {{ $t('1. Primary Color') }}
              </div>
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ localizedColorPalette.find(c => c.value === props.colors[0])?.label || props.colors[0] || '—' }}
              </div>
            </div>
            <VChip
              v-if="activeColorSlot === 0"
              size="x-small"
              color="primary"
              variant="flat"
              class="flex-shrink-0"
            >
              {{ $t('Editing Primary') }}
            </VChip>
          </div>

          <div
            class="color-slot"
            :class="{ 'color-slot--active': activeColorSlot === 1 }"
            role="radio"
            tabindex="0"
            :aria-checked="activeColorSlot === 1"
            @click="activeColorSlot = 1"
            @keydown.enter.prevent="activeColorSlot = 1"
            @keydown.space.prevent="activeColorSlot = 1"
          >
            <span
              class="color-dot"
              :class="{ 'color-dot--empty': !props.colors[1] }"
              :style="props.colors[1] ? { backgroundColor: resolveColorHex(props.colors[1], '#eab308') } : undefined"
            />
            <div class="min-w-0 flex-grow-1">
              <div class="text-caption text-medium-emphasis">
                {{ $t('2. Contrast Color') }}
              </div>
              <div class="text-body-2 font-weight-medium text-truncate">
                <template v-if="props.colors[1]">
                  {{ localizedColorPalette.find(c => c.value === props.colors[1])?.label || props.colors[1] }}
                </template>
                <span
                  v-else
                  class="text-medium-emphasis font-weight-regular"
                >{{ $t('None (Click to add...)') }}</span>
              </div>
            </div>
            <VChip
              v-if="activeColorSlot === 1"
              size="x-small"
              color="warning"
              variant="flat"
              class="flex-shrink-0"
            >
              {{ $t('Editing Contrast') }}
            </VChip>
            <VBtn
              v-if="props.colors[1]"
              icon="tabler-x"
              size="x-small"
              variant="text"
              color="secondary"
              :title="$t('Remove Contrast Color')"
              :aria-label="$t('Remove Contrast Color')"
              @click.stop="removeContrastColor"
            />
          </div>
        </div>

        <p class="text-body-2 text-medium-emphasis mt-3 mb-3">
          {{ activeColorSlot === 0 ? $t('Click any palette swatch below to change the Primary Garment Color:') : $t('Click any palette swatch below to set or update the Contrast Color:') }}
        </p>

        <!-- Palette -->
        <div class="swatch-grid">
          <button
            v-for="sw in localizedColorPalette"
            :key="sw.value"
            type="button"
            class="swatch"
            :class="{
              'swatch--primary': props.colors[0] === sw.value,
              'swatch--contrast': props.colors[1] === sw.value,
            }"
            :style="{ backgroundColor: sw.hex }"
            :title="sw.label"
            :aria-label="sw.label"
            :aria-pressed="props.colors.includes(sw.value)"
            @click="selectColor(sw.value)"
          >
            <span
              v-if="props.colors[0] === sw.value"
              class="swatch__badge"
            >1</span>
            <span
              v-else-if="props.colors[1] === sw.value"
              class="swatch__badge"
            >2</span>
          </button>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style lang="scss" scoped>
/* ── Layout: preview first and pinned on phones, side column from md ───── */
.silhouette-step {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1fr);

  @media (min-width: 960px) {
    gap: 1.5rem;
    grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
  }
}

.silhouette-preview {
  position: sticky;
  z-index: 3;
  background: rgb(var(--v-theme-surface));
  inset-block-start: 0;
  padding-block: 0.25rem;

  @media (min-width: 960px) {
    align-self: start;
    background: transparent;
    grid-column: 2;
    grid-row: 1;
    padding-block: 0;
  }
}

.silhouette-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-inline-size: 0;

  @media (min-width: 960px) {
    grid-column: 1;
    grid-row: 1;
  }
}

/* ── Preview ──────────────────────────────────────────────────────────── */
.preview-head {
  align-items: center;
  justify-content: space-between;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 0.5rem 0.75rem;
}

.preview-body {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;

  @media (min-width: 960px) {
    flex-direction: column;
    padding: 0.75rem;
    text-align: center;
  }
}

.preview-drawing {
  flex: 0 0 112px;
  inline-size: 112px;
  overflow: hidden;

  :deep(.is-compact .studio-canvas-wrap) {
    min-block-size: 0;
    padding: 4px;
  }

  :deep(.is-compact .garment-svg) {
    max-block-size: 96px;
  }

  @media (min-width: 960px) {
    flex-basis: auto;
    inline-size: 100%;

    :deep(.is-compact .studio-canvas-wrap) {
      min-block-size: 175px;
      padding: 4px 8px 12px;
    }

    :deep(.is-compact .garment-svg) {
      max-block-size: 165px;
    }
  }
}

.preview-summary {
  min-inline-size: 0;
  flex: 1;

  @media (min-width: 960px) {
    inline-size: 100%;

    .d-flex {
      justify-content: center;
    }
  }
}

.mini-dot {
  border: 1px solid rgba(var(--v-border-color), 0.4);
  border-radius: 50%;
  block-size: 12px;
  inline-size: 12px;
}

/* ── Card headers & tiers ─────────────────────────────────────────────── */
.step-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-block-end: 1rem;
}

.tier-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.5rem;
}

.tier-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  block-size: 20px;
  color: rgb(var(--v-theme-on-primary));
  font-size: 0.75rem;
  font-weight: 700;
  inline-size: 20px;
}

/* ── Selectable cards (radio semantics, keyboard reachable) ───────────── */
.pick-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  min-inline-size: 0;
  transition: border-color 0.15s ease, background-color 0.15s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.03);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
  }

  &--row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-block-size: 44px;
    padding-inline: 0.625rem;
  }

  &--tall {
    min-block-size: 48px;
  }

  @media (prefers-reduced-motion: reduce) {
    transition: none;
  }
}

.category-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));

  @media (min-width: 600px) {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.category-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  padding: 0.75rem 0.5rem;
}

.pick-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: minmax(0, 1fr);

  @media (min-width: 520px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.pick-title {
  min-inline-size: 0;
  overflow: hidden;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.silhouette-items-scrollbar {
  max-block-size: 280px;
  padding: 2px;
}

.spec-badge {
  flex-shrink: 0;
  border-radius: 4px;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-family: monospace;
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 1.1;
  padding-block: 1px;
  padding-inline: 4px;
  white-space: nowrap;
}

.fabric-info {
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  padding: 0.75rem;
}

/* ── Colors ───────────────────────────────────────────────────────────── */
.color-slots {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(0, 1fr);

  @media (min-width: 600px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.color-slot {
  display: flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  cursor: pointer;
  gap: 0.75rem;
  min-inline-size: 0;
  padding: 0.5rem 0.75rem;

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.05);
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }
}

.color-dot {
  flex-shrink: 0;
  border: 1px solid rgba(var(--v-border-color), 0.4);
  border-radius: 50%;
  block-size: 24px;
  inline-size: 24px;

  &--empty {
    border-style: dashed;
  }
}

.swatch-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fill, minmax(36px, 1fr));
}

.swatch {
  position: relative;
  border: 2px solid transparent;
  border-radius: 8px;
  aspect-ratio: 1;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 15%);
  cursor: pointer;
  min-block-size: 36px;

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &--primary {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
  }

  &--contrast {
    outline: 2px solid rgb(var(--v-theme-warning));
    outline-offset: 1px;
  }

  &__badge {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgb(var(--v-theme-surface));
    block-size: 16px;
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.65rem;
    font-weight: 700;
    inline-size: 16px;
    inset-block-start: -6px;
    inset-inline-end: -6px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 30%);
  }
}
</style>
