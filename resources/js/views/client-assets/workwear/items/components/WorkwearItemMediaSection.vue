<script setup>
/* eslint-disable camelcase */
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  itemData: { type: Object, required: true },
  modelStockMatrix: { type: Array, default: () => [] },
  isUploadingPhoto: { type: Boolean, default: false },
})

const emit = defineEmits(['openPhotoDialog', 'previewPhoto', 'deletePhoto'])

const { t } = useI18n()
</script>

<template>
  <section
    id="section-media"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-photo"
      :title="$t('Product Media & Linked Model')"
      :subtitle="$t('Garment illustration from model blueprint, sibling stock availability, and physical inspection photos.')"
    >
      <template #actions>
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-upload"
          :loading="isUploadingPhoto"
          @click="emit('openPhotoDialog')"
        >
          {{ $t('Upload Photo') }}
        </VBtn>
      </template>

      <VRow>
        <!-- Left: Linked Model Illustration Preview & Variant Stock -->
        <VCol
          cols="12"
          md="5"
        >
          <div class="d-flex justify-space-between align-center mb-3">
            <h6 class="text-subtitle-1 font-weight-bold mb-0">
              <VIcon
                icon="tabler-shirt"
                size="18"
                class="me-1 text-primary"
              />{{ $t('Linked Model Specification') }}
            </h6>
            <VBtn
              v-if="itemData.model_id"
              size="x-small"
              variant="tonal"
              color="primary"
              :to="{ name: 'client-assets-workwear-edit-id', params: { id: itemData.model_id }, query: { type: 'model' } }"
            >
              {{ $t('View Model') }}
            </VBtn>
          </div>

          <WorkwearGarmentIllustration
            v-if="itemData.model"
            :silhouette="itemData.model.silhouette || itemData.garment_type || 'coverall'"
            :colors="itemData.model.colors?.length ? itemData.model.colors : [itemData.color || 'navy', 'hi-vis-yellow']"
            :placements="itemData.model.placements || []"
            :logos="itemData.model.logos || []"
            readonly
            compact
          />

          <div
            v-if="modelStockMatrix.length"
            class="mt-3"
          >
            <div class="text-caption font-weight-bold text-uppercase text-disabled mb-1">
              {{ $t('Sibling Variant Stock Availability') }}
            </div>
            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-for="(st, sIdx) in modelStockMatrix"
                :key="sIdx"
                size="x-small"
                :color="st.available > 0 ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ st.size || 'STD' }}{{ st.color ? ` (${st.color})` : '' }}: {{ st.available }}/{{ st.total }}
              </VChip>
            </div>
          </div>
        </VCol>

        <!-- Right: Product Photos Gallery -->
        <VCol
          cols="12"
          md="7"
        >
          <div class="d-flex justify-space-between align-center mb-3">
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-0">
                <VIcon
                  icon="tabler-camera"
                  size="18"
                  class="me-1 text-primary"
                />{{ $t('Product Media & Inspection Photos') }}
              </h6>
              <span class="text-caption text-medium-emphasis">
                {{ $t('Upload actual garment photos, barcode tags, or condition snapshots.') }}
              </span>
            </div>
          </div>

          <div
            v-if="itemData.photos?.length"
            class="d-flex flex-wrap gap-3"
          >
            <VCard
              v-for="photo in itemData.photos"
              :key="photo.id"
              variant="outlined"
              class="pa-2 d-flex flex-column align-center cursor-pointer"
              style="inline-size: 135px;"
              @click="emit('previewPhoto', photo)"
            >
              <VImg
                :src="photo.thumb || photo.url"
                height="85"
                width="115"
                cover
                class="rounded mb-2"
              />
              <span class="text-caption text-truncate w-100 text-center">{{ photo.name }}</span>
              <VBtn
                size="x-small"
                variant="text"
                color="error"
                class="mt-1"
                @click.stop="emit('deletePhoto', photo.id)"
              >
                {{ $t('Remove') }}
              </VBtn>
            </VCard>
          </div>

          <WpEmptyState
            v-else
            icon="tabler-camera-plus"
            :title="$t('No product photos uploaded for this item yet.')"
            :subtitle="$t('Upload inspection photos or condition snapshots using the button above.')"
          />
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>
