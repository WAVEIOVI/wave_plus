<script setup>
/** A consistent, responsive heading for module and list pages. */
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  eyebrow: {
    type: String,
    default: '',
  },
  icon: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <header class="wp-page-header d-flex align-start justify-space-between flex-wrap gap-4">
    <div class="d-flex align-start gap-3">
      <VAvatar
        v-if="props.icon"
        color="primary"
        variant="tonal"
        rounded="lg"
        size="44"
        aria-hidden="true"
      >
        <VIcon :icon="props.icon" />
      </VAvatar>

      <div>
        <p
          v-if="props.eyebrow"
          class="text-overline text-primary font-weight-bold mb-1"
        >
          {{ props.eyebrow }}
        </p>
        <h1 class="text-h4 font-weight-bold text-high-emphasis mb-1">
          {{ props.title }}
        </h1>
        <p
          v-if="props.subtitle"
          class="text-body-2 text-medium-emphasis mb-0 wp-page-header__subtitle"
        >
          {{ props.subtitle }}
        </p>
      </div>
    </div>

    <div
      v-if="$slots.actions"
      class="wp-page-header__actions d-flex align-center flex-wrap gap-2"
    >
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.wp-page-header__subtitle {
  max-inline-size: 720px;
}

@media (max-width: 599px) {
  .wp-page-header :deep(.v-avatar) {
    display: none;
  }

  .wp-page-header h1 {
    font-size: 1.5rem !important;
    line-height: 1.25;
  }

  .wp-page-header__actions {
    inline-size: 100%;
  }
}
</style>
