<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { facilityTypeCards } from '@/composables/client-assets/sites/useSiteConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'

const props = defineProps({
  site: { type: Object, default: null },
  form: { type: Object, required: true },
})

defineEmits(['openPhotoDialog'])

const facilityTypePanel = ref([])

const currentFacilityTypeObj = computed(() => {
  return facilityTypeCards.find(c => c.value === props.form.site_type) || facilityTypeCards[0]
})
</script>

<template>
  <section
    id="section-bio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Facility Profile & Overview')"
      :subtitle="$t('Primary site identification, photography, and facility operational classification')"
      icon="tabler-building-estate"
      icon-color="primary"
    >
      <!-- Hero with avatar & identity -->
      <div class="d-flex flex-column flex-sm-row align-center gap-5 mb-5 pb-4 border-b">
        <div class="position-relative">
          <VAvatar
            rounded="lg"
            :size="96"
            color="primary"
            variant="tonal"
            class="elevation-2 border"
          >
            <img
              v-if="site?.main_photo_url || site?.photos?.[0]?.url"
              :src="site.main_photo_url || site.photos[0].url"
              alt=""
              style="inline-size: 100%; block-size: 100%; object-fit: cover;"
            >
            <VIcon
              v-else
              icon="tabler-building-estate"
              size="44"
            />
          </VAvatar>

          <VBtn
            icon
            size="x-small"
            color="primary"
            class="avatar-camera-btn elevation-2"
            :aria-label="$t('Change Photo')"
            @click="$emit('openPhotoDialog')"
          >
            <VIcon
              icon="tabler-camera"
              size="14"
            />
          </VBtn>
        </div>

        <div class="grow text-center text-sm-start">
          <div class="d-flex align-center justify-center justify-sm-start gap-2 flex-wrap mb-1">
            <VChip
              v-if="site?.site_code"
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-semibold"
            >
              {{ site.site_code }}
            </VChip>
            <VChip
              size="small"
              :color="currentFacilityTypeObj.color"
              variant="tonal"
              class="text-capitalize"
            >
              {{ $t(currentFacilityTypeObj.title) }}
            </VChip>
          </div>
          <h4 class="text-h5 font-weight-bold mb-1">
            {{ form.name || site?.site_code }}
          </h4>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ localizedValue(site?.client?.legal_name) || $t('Client') }} • {{ localizedValue(site?.address?.city) || $t('Location') }}
          </p>
        </div>
      </div>

      <!-- Fields -->
      <VRow>
        <!-- Collapsible Facility Type Selector -->
        <VCol cols="12">
          <VExpansionPanels
            v-model="facilityTypePanel"
            variant="accordion"
            class="expansion-custom mb-1"
          >
            <VExpansionPanel elevation="0">
              <VExpansionPanelTitle class="facility-classification-title py-3 px-4">
                <div class="facility-classification-summary d-flex align-center justify-space-between w-100 me-3 flex-wrap gap-2">
                  <div class="facility-classification-current d-flex align-center gap-3">
                    <VAvatar
                      size="36"
                      :color="currentFacilityTypeObj.color"
                      variant="tonal"
                      rounded
                    >
                      <VIcon
                        :icon="currentFacilityTypeObj.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Facility Classification') }} *</span>
                      <span class="text-body-1 font-weight-bold">{{ $t(currentFacilityTypeObj.title) }}</span>
                    </div>
                  </div>
                  <VChip
                    class="facility-classification-chip text-capitalize font-weight-semibold"
                    size="small"
                    :color="currentFacilityTypeObj.color"
                    variant="tonal"
                  >
                    {{ $t(currentFacilityTypeObj.title) }}
                  </VChip>
                </div>
              </VExpansionPanelTitle>
              <VExpansionPanelText class="pt-2">
                <span class="text-caption text-medium-emphasis mb-3 d-block">
                  {{ $t('Select the primary operational classification for this site location:') }}
                </span>
                <div class="selection-grid">
                  <div
                    v-for="item in facilityTypeCards"
                    :key="item.value"
                    class="selection-card"
                    :class="{ 'is-selected': form.site_type === item.value }"
                    role="button"
                    tabindex="0"
                    :aria-pressed="form.site_type === item.value"
                    @click="form.site_type = item.value"
                    @keydown.enter.prevent="form.site_type = item.value"
                    @keydown.space.prevent="form.site_type = item.value"
                  >
                    <div class="d-flex align-center gap-3">
                      <VAvatar
                        size="36"
                        :color="form.site_type === item.value ? item.color : 'secondary'"
                        :variant="form.site_type === item.value ? 'elevated' : 'tonal'"
                        rounded
                      >
                        <VIcon
                          :icon="item.icon"
                          size="18"
                        />
                      </VAvatar>
                      <div class="overflow-hidden grow">
                        <div class="d-flex align-center justify-space-between">
                          <span class="text-body-2 font-weight-bold text-truncate">{{ $t(item.title) }}</span>
                          <VIcon
                            v-if="form.site_type === item.value"
                            icon="tabler-circle-check-filled"
                            size="16"
                            color="primary"
                          />
                        </div>
                        <span class="text-caption text-medium-emphasis text-truncate d-block">{{ $t(item.desc) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCol> 
        <VCol cols="12">
          <AppTextField
            v-model="form.name"
            class="facility-name-field"
            :label="$t('Facility / Site Name') + ' *'"
            :placeholder="$t('e.g. Sousse Manufacturing Plant - Block B')"
            variant="outlined"
          />
        </VCol>

        <!-- Facility Description -->
        <VCol cols="12">
          <AppTextarea
            v-model="form.description"
            :label="$t('Facility Description & Mission')"
            :placeholder="$t('Add details regarding operations, site capacity, and architectural features...')"
            rows="3"
            auto-grow
            variant="outlined"
          />
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.avatar-camera-btn {
  position: absolute;
  inset-block-end: -6px;
  inset-inline-end: -6px;
}

.selection-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.selection-card {
  min-width: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  padding-block: 0.75rem;
  padding-inline: 1rem;
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &.is-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;
  }
}

@media (max-width: 375px) {
  .facility-classification-title {
    :deep(.v-expansion-panel-title__content) {
      min-width: 0;
    }
  }

  .facility-classification-summary {
    justify-content: center !important;
    margin-inline-end: 0 !important;
  }

  .facility-classification-current {
    flex-direction: column;
    gap: 0.5rem !important;
    text-align: center;
  }

  .facility-classification-chip {
    display: none;
  }

  .selection-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .selection-card > div,
  .selection-card > div > div {
    min-width: 0;
  }

  .facility-name-field {
    :deep(> .v-label) {
      display: flex;
      justify-content: center;
      width: 100%;
      text-align: center;
    }

    :deep(.v-field__input input) {
      text-align: center;
    }
  }
}

.subtle-box {
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.whitespace-pre-line {
  white-space: pre-line;
}
</style>
