<script setup>
/* eslint-disable lines-around-comment */
/**
 * Section card used by the equipment wizard steps.
 * Replaces the repeated VCard > VCardItem (avatar + title + subtitle) > VCardText pattern.
 * Pass `style="--i: n"` from the parent to stagger the entrance animation.
 */
defineProps({
  icon: { type: String, default: null },
  color: { type: String, default: 'primary' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  padded: { type: Boolean, default: true },
})
</script>

<template>
  <VCard
    class="step-card"
    :class="{ 'step-card--error': error }"
  >
    <VCardItem class="step-card__head pb-2">
      <template
        v-if="icon"
        #prepend
      >
        <VAvatar
          variant="tonal"
          :color="color"
          rounded="lg"
        >
          <VIcon :icon="icon" />
        </VAvatar>
      </template>

      <VCardTitle class="step-card__title">
        {{ title }}
      </VCardTitle>
      <VCardSubtitle
        v-if="subtitle"
        class="step-card__subtitle"
      >
        {{ subtitle }}
      </VCardSubtitle>

      <template
        v-if="$slots.append"
        #append
      >
        <div class="step-card__append">
          <slot name="append" />
        </div>
      </template>
    </VCardItem>

    <VCardText :class="{ 'pa-3 pa-sm-5': padded }">
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line, list-item-two-line, actions"
        class="step-card__skeleton"
      />
      <slot v-else />
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.step-card {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px;
  animation: step-card-in 0.35s cubic-bezier(0.4, 0, 0.2, 1) both;
  animation-delay: calc(var(--i, 0) * 60ms);
  background: rgba(var(--v-theme-surface), 0.75);
  backdrop-filter: blur(12px);
  overflow-x: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  min-inline-size: 0;
  max-inline-size: 100%;

  &--error {
    border-color: rgb(var(--v-theme-error));
    box-shadow: 0 0 0 1px rgba(var(--v-theme-error), 0.35);
  }

  &__head :deep(.v-card-item__prepend) {
    align-self: flex-start;
    margin-block-start: 2px;
  }

  &__head :deep(.v-card-item__content) {
    min-inline-size: 0;
  }

  &__title,
  &__subtitle {
    white-space: normal;
    word-break: break-word;
  }

  &__skeleton {
    background: transparent;
  }
}

@keyframes step-card-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

/* Keep long header actions from squeezing the title on 360px screens. */
@media (max-width: 599px) {
  .step-card__head {
    padding-inline: 0.85rem;
  }

  .step-card__head :deep(.v-card-item__append) {
    grid-column: 1 / -1;
    margin-block-start: 0.5rem;
    padding-inline-start: 0;
  }

  .step-card__append {
    inline-size: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .step-card {
    animation: none;
  }
}
</style>
