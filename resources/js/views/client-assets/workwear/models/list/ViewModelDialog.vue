<script setup>
import { WpJournalNav } from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import {
  WORKWEAR_FIT_VARIATIONS,
  WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS,
  resolveColorHex,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import CatalogZoomDialog from '@/views/catalog/shared/CatalogZoomDialog.vue'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import WorkwearModelMediaSection from '@/views/client-assets/workwear/models/components/WorkwearModelMediaSection.vue'
import WorkwearStudioZoomDialog from '@/views/client-assets/workwear/models/components/WorkwearStudioZoomDialog.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  modelId: { type: [String, Number], default: null },
  model: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const { locale, t } = useI18n()
const store = useClientAssetStore()
const { smAndDown } = useDisplay()

const loading = ref(false)
const modelDetails = ref(null)
const activeSection = ref('bio')
const activeView = ref('front')
const selectedPlacementIndex = ref(-1)
const isZoomStudioOpen = ref(false)
const isVariantsZoomOpen = ref(false)

const statusMap = { draft: 'secondary', active: 'success', discontinued: 'error' }

const targetId = computed(() => props.modelId || props.model?.id)
const resolvedModel = computed(() => modelDetails.value || props.model)

const silhouetteSummary = computed(() => resolveSilhouetteDisplay(resolvedModel.value))
const catalogItem = computed(() => resolvedModel.value?.catalog_item || null)
const fabricObj = computed(() => resolvedModel.value?.workwear_fabric || null)

const fitLabel = computed(() => {
  const fitVal = resolvedModel.value?.custom_made_requirements?.fit_variation
  if (!fitVal) return '—'

  return WORKWEAR_FIT_VARIATIONS.find(f => f.value === fitVal)?.title || fitVal
})

const sections = computed(() => [
  { id: 'bio', label: t('General & Catalog'), icon: 'tabler-info-circle' },
  { id: 'studio', label: t('2D Studio & Placements'), icon: 'tabler-vector-bezier-2' },
  { id: 'garments', label: t('Standards & Construction'), icon: 'tabler-certificate' },
  { id: 'options', label: t('Options & Variants'), icon: 'tabler-adjustments' },
  { id: 'media', label: t('Branding & Media'), icon: 'tabler-photo' },
  { id: 'versions', label: t('Revisions'), icon: 'tabler-history' },
])

const summaryItems = computed(() => [
  {
    label: t('Client'),
    value: resolvedModel.value?.client?.legal_name || '—',
  },
  {
    label: t('Catalog Item'),
    value: catalogItem.value?.code || '—',
  },
  {
    label: t('Placements'),
    value: `${resolvedModel.value?.placements?.length || 0} ${t('zones')}`,
  },
  {
    label: t('Items In Stock'),
    value: `${resolvedModel.value?.available_items_count ?? 0} / ${resolvedModel.value?.items_count ?? 0}`,
  },
])

function resolveLogoThumb(mediaId) {
  const logos = resolvedModel.value?.logos || []
  if (!mediaId || !logos.length) return null
  const found = logos.find(l => String(l.id) === String(mediaId))

  return found ? (found.thumb || found.url) : null
}

watch(
  () => [props.isDialogVisible, targetId.value],
  async ([visible, id]) => {
    if (visible && id) {
      activeSection.value = 'bio'
      activeView.value = 'front'
      selectedPlacementIndex.value = -1
      loading.value = true
      try {
        modelDetails.value = await store.fetchWorkwearModel(id)
      } catch (err) {
        console.error('Error loading model preview:', err)
      } finally {
        loading.value = false
      }
    } else if (!visible) {
      modelDetails.value = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <WpDetailDialog
    :model-value="isDialogVisible"
    :title="resolvedModel?.name || $t('Workwear Model Preview')"
    :subtitle="resolvedModel?.model_ref || ''"
    icon="tabler-shirt"
    :loading="loading && !resolvedModel"
    max-width="1180"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <template #badges>
      <div class="d-flex align-center gap-2 flex-wrap">
        <VChip
          v-if="resolvedModel?.status"
          size="x-small"
          label
          :color="statusMap[resolvedModel.status] || 'secondary'"
          class="text-capitalize font-weight-bold"
        >
          {{ resolvedModel.status }}
        </VChip>
        <VChip
          v-if="catalogItem?.code"
          size="x-small"
          color="info"
          variant="tonal"
          label
          class="font-mono"
        >
          {{ catalogItem.code }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="resolvedModel"
          variant="tonal"
          size="small"
          color="info"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-arrows-maximize'"
          @click="isZoomStudioOpen = true"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-arrows-maximize"
            size="18"
          />
          <span v-else>{{ $t('Zoom Studio') }}</span>
        </VBtn>

        <VBtn
          v-if="resolvedModel?.id"
          variant="tonal"
          size="small"
          color="warning"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :to="{ name: 'client-assets-workwear-edit-id', params: { id: resolvedModel.id }, query: { type: 'model' } }"
          @click="emit('update:isDialogVisible', false)"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-edit"
            size="18"
          />
          <span v-else>{{ $t('Edit Model') }}</span>
        </VBtn>
      </div>
    </template>

    <!-- Main Journal Mirror Layout -->
    <div
      v-if="resolvedModel"
      class="dialog-journal-layout"
    >
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-section-"
        container=".wp-detail-dialog__scroll"
      />

      <main class="dialog-journal-feed">
        <!-- ━━━ 1. GENERAL, CATALOG PRODUCT & FABRIC ━━━ -->
        <section
          id="dialog-section-bio"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-info-circle"
                  size="20"
                  color="primary"
                  class="me-2"
                />{{ $t('General Information, Catalog Product & Fabric') }}
              </h6>

              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <span class="text-caption text-disabled text-uppercase">{{ $t('Client Account') }}</span>
                    <div class="text-body-1 font-weight-bold text-high-emphasis">
                      {{ resolvedModel.client?.legal_name || '—' }}
                    </div>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <span class="text-caption text-disabled text-uppercase">{{ $t('Preferred Supplier / Manufacturer') }}</span>
                    <div class="text-body-1 font-weight-bold text-high-emphasis">
                      {{ resolvedModel.supplier?.legal_name || catalogItem?.preferred_supplier?.legal_name || '—' }}
                    </div>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <span class="text-caption text-disabled text-uppercase">{{ $t('Linked Physical Catalog Product') }}</span>
                    <div
                      v-if="catalogItem"
                      class="d-flex align-center justify-space-between gap-2 mt-1"
                    >
                      <div>
                        <div class="font-weight-bold text-body-2">
                          {{ catalogItem.code }} — {{ localizedValue(catalogItem.name, locale) }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ localizedValue(catalogItem.category?.name, locale) || $t('Physical Product') }}
                        </div>
                      </div>
                      <VBtn
                        v-if="catalogItem.variants?.length"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="tabler-arrows-maximize"
                        @click="isVariantsZoomOpen = true"
                      >
                        {{ catalogItem.variants.length }} {{ $t('Variants') }}
                      </VBtn>
                    </div>
                    <div
                      v-else
                      class="text-body-2 text-disabled mt-1"
                    >
                      —
                    </div>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <span class="text-caption text-disabled text-uppercase">{{ $t('Technical Workwear Fabric') }}</span>
                    <div class="text-body-2 font-weight-bold mt-1">
                      {{ fabricObj?.name || resolvedModel.fabric || '—' }}
                    </div>
                    <div
                      v-if="fabricObj?.composition || fabricObj?.weight_gsm"
                      class="text-caption text-medium-emphasis"
                    >
                      {{ fabricObj.composition }}
                      <template v-if="fabricObj.weight_gsm">
                        ({{ fabricObj.weight_gsm }} g/m²)
                      </template>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 2. 2D GARMENT STUDIO & CUSTOMIZATION PLACEMENTS TABLE ━━━ -->
        <section
          id="dialog-section-studio"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
                <div>
                  <h6 class="text-h6 mb-0">
                    <VIcon
                      icon="tabler-Vector-bezier-2"
                      size="20"
                      color="primary"
                      class="me-2"
                    />{{ $t('2D Garment Studio & Customization Placements') }}
                  </h6>
                  <span class="text-caption text-medium-emphasis">
                    {{ [silhouetteSummary.categoryTitle, silhouetteSummary.subcategoryTitle, silhouetteSummary.itemTitle].filter(Boolean).join(' › ') }}
                  </span>
                </div>

                <div class="d-flex align-center gap-2">
                  <div
                    v-if="resolvedModel.colors?.length"
                    class="d-flex align-center gap-1 me-2"
                  >
                    <span
                      v-for="c in resolvedModel.colors"
                      :key="c"
                      class="d-inline-block rounded-circle border"
                      :style="{ width: '16px', height: '16px', backgroundColor: resolveColorHex(c) }"
                      :title="c"
                    />
                  </div>
                  <VBtn
                    size="x-small"
                    color="info"
                    variant="tonal"
                    prepend-icon="tabler-arrows-maximize"
                    @click="isZoomStudioOpen = true"
                  >
                    {{ $t('Zoom Studio') }}
                  </VBtn>
                </div>
              </div>

              <VRow>
                <VCol
                  cols="12"
                  md="5"
                >
                  <WorkwearGarmentIllustration
                    v-model:active-view="activeView"
                    v-model:selected-placement-index="selectedPlacementIndex"
                    :silhouette="resolvedModel.silhouette || 'coverall'"
                    :colors="resolvedModel.colors?.length ? resolvedModel.colors : ['navy', 'hi-vis-yellow']"
                    :placements="resolvedModel.placements || []"
                    :logos="resolvedModel.logos || []"
                    allow-fullscreen-zoom
                    readonly
                    @request-fullscreen-zoom="isZoomStudioOpen = true"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="7"
                >
                  <VCard
                    variant="outlined"
                    class="bg-surface h-100"
                  >
                    <div class="px-4 py-3 border-b d-flex align-center justify-space-between">
                      <span class="text-subtitle-2 font-weight-bold">
                        {{ $t('Customization Placements Table') }}
                      </span>
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ resolvedModel.placements?.length || 0 }} {{ $t('items') }}
                      </VChip>
                    </div>

                    <VTable
                      v-if="resolvedModel.placements?.length"
                      density="compact"
                      class="text-no-wrap"
                    >
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>{{ $t('View') }}</th>
                          <th>{{ $t('Type') }}</th>
                          <th>{{ $t('Zone') }}</th>
                          <th>{{ $t('Print Type') }}</th>
                          <th>{{ $t('Logo / Spec') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(pl, idx) in resolvedModel.placements"
                          :key="pl.id || idx"
                          :class="{ 'bg-var-theme-background': selectedPlacementIndex === idx }"
                          class="cursor-pointer"
                          @click="
                            selectedPlacementIndex = idx;
                            activeView = pl.view || 'front';
                          "
                        >
                          <td class="font-mono text-caption font-weight-bold">
                            {{ idx + 1 }}
                          </td>
                          <td>
                            <VChip
                              size="x-small"
                              :color="(pl.view || 'front') === 'front' ? 'primary' : 'info'"
                              variant="tonal"
                              class="text-uppercase"
                            >
                              {{ pl.view || 'front' }}
                            </VChip>
                          </td>
                          <td class="text-caption text-capitalize font-weight-medium">
                            {{ pl.type }}
                          </td>
                          <td class="text-caption">
                            {{ pl.zone }}
                          </td>
                          <td class="text-caption">
                            {{ pl.method || '—' }}
                          </td>
                          <td>
                            <div class="d-flex align-center gap-2 py-1">
                              <VAvatar
                                v-if="resolveLogoThumb(pl.media_id)"
                                size="24"
                                rounded
                                class="border"
                              >
                                <VImg :src="resolveLogoThumb(pl.media_id)" />
                              </VAvatar>
                              <span class="text-caption">
                                {{ pl.text || pl.label || '—' }}
                              </span>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                    <div
                      v-else
                      class="text-center py-8 text-caption text-disabled"
                    >
                      {{ $t('No customization placements configured.') }}
                    </div>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 3. STANDARDS, CERTIFICATIONS & TECHNICAL PROFILE ━━━ -->
        <section
          id="dialog-section-garments"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="d-flex flex-column gap-4">
              <h6 class="text-h6 mb-0">
                <VIcon
                  icon="tabler-certificate"
                  size="20"
                  color="primary"
                  class="me-2"
                />{{ $t('Standards, Certifications & Technical Construction Profile') }}
              </h6>

              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                      {{ $t('EN / ISO Standards') }}
                    </div>
                    <div
                      v-if="resolvedModel.standards?.length"
                      class="d-flex flex-wrap gap-1"
                    >
                      <VChip
                        v-for="std in resolvedModel.standards"
                        :key="std"
                        size="small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ std }}
                      </VChip>
                    </div>
                    <span
                      v-else
                      class="text-caption text-disabled"
                    >—</span>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                      {{ $t('Certifications') }}
                    </div>
                    <div
                      v-if="resolvedModel.certifications?.length"
                      class="d-flex flex-wrap gap-1"
                    >
                      <VChip
                        v-for="cert in resolvedModel.certifications"
                        :key="cert"
                        size="small"
                        color="success"
                        variant="tonal"
                      >
                        {{ cert }}
                      </VChip>
                    </div>
                    <span
                      v-else
                      class="text-caption text-disabled"
                    >—</span>
                  </div>
                </VCol>
              </VRow>

              <!-- Technical Construction Grid -->
              <VRow v-if="resolvedModel.technical_specs">
                <template
                  v-for="(meta, key) in WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS"
                  :key="key"
                >
                  <VCol
                    v-if="resolvedModel.technical_specs[key]"
                    cols="12"
                    sm="6"
                  >
                    <div class="pa-3 rounded border bg-surface h-100">
                      <span class="text-caption text-disabled d-block">{{ $t(meta.label) }}</span>
                      <span class="text-body-2 font-weight-medium">{{ resolvedModel.technical_specs[key] }}</span>
                    </div>
                  </VCol>
                </template>
              </VRow>

              <!-- Custom-Made Profile -->
              <div
                v-if="resolvedModel.custom_made_requirements"
                class="pa-3 rounded border bg-surface"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-caption font-weight-bold text-uppercase text-disabled">
                    {{ $t('Custom-Made & Fit Profile') }}
                  </span>
                  <VChip
                    size="x-small"
                    :color="resolvedModel.custom_made_requirements.is_custom_made ? 'primary' : 'secondary'"
                    variant="tonal"
                  >
                    {{ resolvedModel.custom_made_requirements.is_custom_made ? $t('Custom-Made Required') : $t('Standard Fit') }}
                  </VChip>
                </div>
                <div class="text-body-2 font-weight-medium mb-1">
                  {{ fitLabel }}
                </div>
                <div
                  v-if="resolvedModel.custom_made_requirements.construction_requirements?.length"
                  class="d-flex flex-wrap gap-1 mt-2"
                >
                  <VChip
                    v-for="req in resolvedModel.custom_made_requirements.construction_requirements"
                    :key="req"
                    size="x-small"
                    color="info"
                    variant="tonal"
                  >
                    {{ req }}
                  </VChip>
                </div>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 4. CATALOG OPTIONS & VARIANTS ━━━ -->
        <section
          id="dialog-section-options"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
                <h6 class="text-h6 mb-0">
                  <VIcon
                    icon="tabler-adjustments"
                    size="20"
                    color="primary"
                    class="me-2"
                  />{{ $t('Catalog Options & Variants') }}
                </h6>
                <VBtn
                  v-if="catalogItem"
                  size="small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-arrows-maximize"
                  @click="isVariantsZoomOpen = true"
                >
                  {{ $t('View Catalog Variants') }} ({{ catalogItem.variants?.length || 0 }})
                </VBtn>
              </div>

              <div class="mb-4">
                <div class="text-caption text-disabled text-uppercase mb-1">
                  {{ $t('Approved Garment Size Range') }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="sz in (resolvedModel.size_range || [])"
                    :key="sz"
                    size="small"
                    color="primary"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    {{ sz }}
                  </VChip>
                </div>
              </div>

              <VRow v-if="catalogItem?.options?.length">
                <VCol
                  v-for="opt in catalogItem.options"
                  :key="opt.id"
                  cols="12"
                  sm="6"
                >
                  <div class="pa-3 rounded border bg-surface h-100">
                    <div class="font-weight-bold text-body-2 mb-2">
                      {{ localizedValue(opt.name, locale) }}
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-for="val in (opt.values || [])"
                        :key="val.id"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ localizedValue(val.value, locale) }}
                      </VChip>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ 5. BRANDING & MEDIA ━━━ -->
        <section
          id="dialog-section-media"
          class="dialog-journal-section"
        >
          <WorkwearModelMediaSection
            :logos="resolvedModel.logos || []"
            :design-files="resolvedModel.design_files || []"
            :placements="resolvedModel.placements || []"
            read-only
            @download-media="item => store.downloadModelMedia(resolvedModel.id, item.id, item.file_name || item.name || 'download')"
          />
        </section>

        <!-- ━━━ 6. REVISIONS & NOTES ━━━ -->
        <section
          id="dialog-section-versions"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-3">
                <VIcon
                  icon="tabler-history"
                  size="20"
                  color="primary"
                  class="me-2"
                />{{ $t('Revision History & Notes') }}
              </h6>

              <p
                v-if="resolvedModel.notes"
                class="text-body-2 mb-4 pa-3 rounded border bg-surface"
              >
                {{ resolvedModel.notes }}
              </p>

              <VTimeline
                v-if="resolvedModel.versions?.length"
                side="end"
                density="compact"
              >
                <VTimelineItem
                  v-for="v in resolvedModel.versions"
                  :key="v.id"
                  dot-color="primary"
                  size="x-small"
                >
                  <div class="d-flex justify-space-between align-center">
                    <span class="font-weight-bold">v{{ v.version_number }}</span>
                    <span class="text-caption text-disabled">
                      {{ v.created_at ? new Date(v.created_at).toLocaleString() : '' }}
                    </span>
                  </div>
                  <p
                    v-if="v.change_summary"
                    class="text-body-2 text-medium-emphasis mb-0 mt-1"
                  >
                    {{ v.change_summary }}
                  </p>
                </VTimelineItem>
              </VTimeline>
              <div
                v-else
                class="text-caption text-disabled"
              >
                {{ $t('No version snapshots recorded yet.') }}
              </div>
            </VCardText>
          </VCard>
        </section>
      </main>
    </div>

    <!-- Readonly Studio Fullscreen Zoom Dialog -->
    <WorkwearStudioZoomDialog
      v-if="resolvedModel"
      v-model="isZoomStudioOpen"
      :model-data="resolvedModel"
      :logos="resolvedModel.logos || []"
      readonly
    />

    <!-- Catalog Variants Zoom Dialog -->
    <CatalogZoomDialog
      v-model="isVariantsZoomOpen"
      :variants="catalogItem?.variants || []"
      :item-code="catalogItem?.code || ''"
    />
  </WpDetailDialog>
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 960px) {
    flex-direction: column;
  }
}

.dialog-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.25rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.dialog-journal-section {
  scroll-margin-block-start: 16px;
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  background: rgba(var(--v-theme-surface), 0.78) !important;
}
</style>
