<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  equipment: { type: Object, required: true },
  hardwareProfileItems: { type: Array, required: true },
  optionBadges: { type: Array, default: () => [] },
  specEntries: { type: Array, default: () => [] },
  normalizedCerts: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="d-flex flex-column gap-4 pt-2">
    <!-- Hardware Profile Grid -->
    <VCard
      variant="outlined"
      class="rounded-lg pa-4"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2">
          <VIcon
            icon="tabler-cpu"
            size="18"
            color="primary"
          />
          {{ $t('Technical Hardware Profile') }}
        </span>

        <div
          v-if="equipment.blueprint?.fire_class_rating || equipment.blueprint?.working_pressure_bar"
          class="d-flex gap-1.5 flex-wrap"
        >
          <VChip
            v-if="equipment.blueprint?.fire_class_rating"
            size="x-small"
            color="error"
            variant="tonal"
            label
          >
            {{ equipment.blueprint.fire_class_rating }}
          </VChip>
          <VChip
            v-if="equipment.blueprint?.working_pressure_bar"
            size="x-small"
            color="info"
            variant="tonal"
            label
          >
            {{ equipment.blueprint.working_pressure_bar }} bar
          </VChip>
        </div>
      </div>

      <div class="hardware-grid">
        <div
          v-for="item in hardwareProfileItems"
          :key="item.label"
          class="hardware-cell"
        >
          <span class="hardware-cell__label">
            <VIcon
              :icon="item.icon"
              size="13"
              class="me-1"
            />
            {{ item.label }}
          </span>
          <span
            class="hardware-cell__value text-truncate"
            :class="{ 'font-mono': item.mono }"
          >
            {{ item.value }}
          </span>
        </div>
      </div>

      <!-- Selected Blueprint Options -->
      <div
        v-if="optionBadges.length"
        class="mt-4 pt-3 border-t"
      >
        <span class="text-caption font-weight-bold text-uppercase text-disabled d-block mb-2">
          {{ $t('Configured Blueprint Options') }}
        </span>
        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="opt in optionBadges"
            :key="opt.id"
            size="small"
            color="info"
            variant="tonal"
            label
          >
            <strong>{{ opt.name }}:</strong>&nbsp;{{ opt.value }}
          </VChip>
        </div>
      </div>
    </VCard>

    <!-- Custom Technical Specifications & Certifications -->
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard
          variant="outlined"
          class="rounded-lg pa-4 h-100"
        >
          <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2 mb-3">
            <VIcon
              icon="tabler-list-details"
              size="18"
              color="info"
            />
            {{ $t('Custom Specifications') }} ({{ specEntries.length }})
          </span>

          <div
            v-if="specEntries.length"
            class="d-flex flex-column gap-2"
          >
            <div
              v-for="spec in specEntries"
              :key="spec.key"
              class="d-flex align-center justify-space-between py-1.5 px-2.5 rounded-lg bg-surface-variant-subtle"
            >
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ spec.key }}</span>
              <span class="text-body-2 font-weight-bold">{{ spec.value }}</span>
            </div>
          </div>
          <div
            v-else
            class="text-caption text-disabled py-4 text-center"
          >
            {{ $t('No custom key-value parameters recorded.') }}
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VCard
          variant="outlined"
          class="rounded-lg pa-4 h-100"
        >
          <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2 mb-3">
            <VIcon
              icon="tabler-certificate"
              size="18"
              color="success"
            />
            {{ $t('Registered Certifications') }} ({{ normalizedCerts.length }})
          </span>

          <div
            v-if="normalizedCerts.length"
            class="d-flex flex-wrap gap-2"
          >
            <VChip
              v-for="cert in normalizedCerts"
              :key="cert.id"
              size="small"
              color="success"
              variant="tonal"
            >
              <VIcon
                start
                icon="tabler-shield-check"
                size="14"
              />
              {{ cert.name }}
              <span
                v-if="cert.body"
                class="ms-1 opacity-75"
              >({{ cert.body }})</span>
            </VChip>
          </div>
          <div
            v-else
            class="text-caption text-disabled py-4 text-center"
          >
            {{ $t('No compliance standards registered.') }}
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Description & Technician Notes -->
    <VCard
      v-if="localizedValue(equipment.description) || localizedValue(equipment.notes)"
      variant="outlined"
      class="rounded-lg pa-4"
    >
      <div
        v-if="localizedValue(equipment.description)"
        class="mb-3"
      >
        <span class="text-caption font-weight-bold text-uppercase text-disabled d-block mb-1">
          {{ $t('Functional Role & Description') }}
        </span>
        <p class="text-body-2 mb-0">
          {{ localizedValue(equipment.description) }}
        </p>
      </div>
      <div v-if="localizedValue(equipment.notes)">
        <span class="text-caption font-weight-bold text-uppercase text-disabled d-block mb-1">
          {{ $t('Field Observations & Technician Notes') }}
        </span>
        <p class="text-body-2 mb-0">
          {{ localizedValue(equipment.notes) }}
        </p>
      </div>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.hardware-grid {
  display: grid;
  gap: 0.65rem;
  grid-template-columns: repeat(2, 1fr);

  @media (max-width: 599px) {
    grid-template-columns: 1fr;
  }
}

.hardware-cell {
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  background: rgba(var(--v-theme-on-surface), 0.015);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;

  &__label {
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.55);
    display: flex;
    align-items: center;
  }

  &__value {
    font-size: 0.875rem;
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.9);
  }
}

.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
