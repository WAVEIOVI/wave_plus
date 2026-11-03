<script setup>
/* eslint-disable camelcase */
defineProps({
  timelineFormProxy: { type: Object, required: true },
  calculatedExpiryDate: { type: String, default: null },
  timelineGroupedByYear: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="d-flex flex-column gap-4 pt-2">
    <VCard
      variant="outlined"
      class="rounded-lg pa-4"
    >
      <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2 mb-3">
        <VIcon
          icon="tabler-calendar-event"
          size="18"
          color="warning"
        />
        {{ $t('Service & Lifecycle Schedule') }}
      </span>

      <div class="hardware-grid">
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Manufacture Date') }}</span>
          <span class="hardware-cell__value">{{ timelineFormProxy.manufacture_date || '—' }}</span>
        </div>
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Commissioning Date') }}</span>
          <span class="hardware-cell__value">{{ timelineFormProxy.install_date || '—' }}</span>
        </div>
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Last Inspection') }}</span>
          <span class="hardware-cell__value">{{ timelineFormProxy.last_inspection_date || '—' }}</span>
        </div>
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Next Inspection Due') }}</span>
          <span class="hardware-cell__value text-warning">{{ timelineFormProxy.next_inspection_date || '—' }}</span>
        </div>
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Shelf Life (Years)') }}</span>
          <span class="hardware-cell__value">{{ timelineFormProxy.shelf_life_years || '—' }}</span>
        </div>
        <div class="hardware-cell">
          <span class="hardware-cell__label">{{ $t('Projected Expiry') }}</span>
          <span class="hardware-cell__value">{{ calculatedExpiryDate || '—' }}</span>
        </div>
      </div>
    </VCard>

    <!-- Projected Timeline Milestones -->
    <VCard
      variant="outlined"
      class="rounded-lg pa-4"
    >
      <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2 mb-3">
        <VIcon
          icon="tabler-timeline"
          size="18"
          color="primary"
        />
        {{ $t('Projected Lifecycle Milestones') }}
      </span>

      <div
        v-if="timelineGroupedByYear.length"
        class="d-flex flex-column gap-3"
      >
        <div
          v-for="grp in timelineGroupedByYear"
          :key="grp.year"
        >
          <VChip
            size="x-small"
            color="primary"
            variant="tonal"
            class="font-weight-bold mb-2"
          >
            {{ grp.year }}
          </VChip>
          <div class="d-flex flex-column gap-2 ps-2">
            <div
              v-for="evt in grp.events"
              :key="evt.id"
              class="d-flex align-center justify-space-between py-1.5 px-3 rounded-lg border"
            >
              <div class="d-flex align-center gap-2.5">
                <VAvatar
                  size="26"
                  :color="evt.color"
                  variant="tonal"
                  rounded
                >
                  <VIcon
                    :icon="evt.icon"
                    size="14"
                  />
                </VAvatar>
                <span class="text-body-2 font-weight-medium">{{ evt.title }}</span>
              </div>
              <span class="text-caption text-medium-emphasis font-mono">{{ evt.date }}</span>
            </div>
          </div>
        </div>
      </div>
      <div
        v-else
        class="text-caption text-disabled py-6 text-center"
      >
        {{ $t('No projected lifecycle events available.') }}
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

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
