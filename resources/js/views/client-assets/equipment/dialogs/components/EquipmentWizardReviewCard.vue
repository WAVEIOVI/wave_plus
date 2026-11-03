<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  reviewForm: { type: Object, required: true },
  resolveLifecycleVariant: { type: Function, required: true },
  selectedBlueprint: { type: Object, default: null },
  selectedOptionBadges: { type: Array, default: () => [] },
  selectedSite: { type: Object, default: null },
  selectedArea: { type: Object, default: null },
  calculatedExpiryDate: { type: String, default: null },
})
</script>

<template>
  <VCard
    variant="tonal"
    color="primary"
    class="review-summary pa-4 rounded-lg mt-4"
  >
    <div class="d-flex align-center justify-space-between flex-wrap gap-3">
      <div class="d-flex align-center gap-3 min-w-0">
        <VAvatar
          size="46"
          color="primary"
          variant="elevated"
          rounded="lg"
        >
          <VIcon
            icon="tabler-fire-extinguisher"
            size="26"
          />
        </VAvatar>
        <div class="min-w-0">
          <div class="d-flex align-center gap-2 flex-wrap">
            <span class="text-subtitle-1 font-weight-bold text-break">
              {{ reviewForm.equipment_code || $t('New Equipment') }}
            </span>
            <VChip
              size="x-small"
              :color="resolveLifecycleVariant(reviewForm.lifecycle_status)"
              variant="flat"
              class="font-weight-medium"
            >
              {{ reviewForm.lifecycle_status?.replace(/_/g, ' ') }}
            </VChip>
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ reviewForm.model_name || reviewForm.brand || selectedBlueprint?.model_name || $t('Standard Equipment') }}
          </div>
        </div>
      </div>

      <div
        v-if="selectedOptionBadges.length"
        class="d-flex flex-wrap gap-1"
      >
        <VChip
          v-for="b in selectedOptionBadges"
          :key="b.name"
          size="x-small"
          color="warning"
          variant="tonal"
        >
          {{ b.name }}: {{ b.value }}
        </VChip>
      </div>
    </div>

    <VDivider class="my-3 opacity-20" />

    <div class="d-flex align-center justify-space-between flex-wrap gap-2 text-caption">
      <div>
        <VIcon
          icon="tabler-map-pin"
          size="14"
          class="me-1 text-info"
        />
        <span class="font-weight-medium">{{ localizedValue(selectedSite?.name) || $t('No Site') }}</span>
        <span
          v-if="selectedArea"
          class="text-medium-emphasis ms-1"
        >({{ localizedValue(selectedArea.name) }})</span>
      </div>

      <div v-if="calculatedExpiryDate">
        <VIcon
          icon="tabler-clock"
          size="14"
          class="me-1 text-warning"
        />
        <span>{{ $t('Expires') }}: <strong>{{ calculatedExpiryDate }}</strong></span>
      </div>
    </div>
  </VCard>
</template>
