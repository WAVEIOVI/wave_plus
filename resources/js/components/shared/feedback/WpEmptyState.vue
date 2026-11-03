<script setup>
/**
 * WpEmptyState
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic empty-state placeholder for all list pages.
 *
 * Usage:
 *   <WpEmptyState
 *     icon="tabler-inbox"
 *     :title="$t('wp.empty.title')"
 *     :subtitle="$t('wp.empty.subtitle')"
 *   >
 *     <template #action>
 *       <VBtn @click="onCreate">{{ $t('wp.empty.cta') }}</VBtn>
 *     </template>
 *   </WpEmptyState>
 */
const props = defineProps({
  /** Tabler icon name */
  icon: {
    type: String,
    default: 'tabler-inbox',
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="wp-empty-state d-flex flex-column align-center justify-center text-center pa-12 ga-4">
    <div class="wp-empty-state__icon-wrapper">
      <VIcon
        :icon="props.icon"
        size="56"
        color="secondary"
        class="wp-empty-state__icon"
      />
    </div>

    <div class="wp-empty-state__text">
      <p
        v-if="props.title"
        class="text-h6 font-weight-semibold text-high-emphasis mb-1"
      >
        {{ props.title }}
      </p>
      <p
        v-if="props.subtitle"
        class="text-body-2 text-medium-emphasis mb-0"
      >
        {{ props.subtitle }}
      </p>
    </div>

    <div
      v-if="$slots.action"
      class="wp-empty-state__action"
    >
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.wp-empty-state__icon-wrapper {
  padding: 20px;
  border-radius: 50%;
  background: rgba(var(--v-theme-secondary), 0.08);
}

.wp-empty-state__icon {
  opacity: 0.6;
}
</style>
