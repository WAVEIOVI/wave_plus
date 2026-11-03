<script setup>
/**
 * WpLoadingState
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic centered loading indicator for list pages and dialogs.
 * Matches existing Vuexy skeleton/loading patterns.
 *
 * Usage:
 *   <WpLoadingState />
 *   <WpLoadingState :text="$t('wp.loading')" />
 */
const props = defineProps({
  /** Supplementary label shown below the spinner. */
  text: {
    type: String,
    default: '',
  },
  /** Size of the circular loader. */
  size: {
    type: [Number, String],
    default: 48,
  },
  /** Minimum height of the container (CSS value). */
  minHeight: {
    type: String,
    default: '200px',
  },
})
</script>

<template>
  <div
    class="wp-loading-state d-flex flex-column align-center justify-center ga-3"
    :style="{ minHeight: props.minHeight }"
  >
    <VProgressCircular
      indeterminate
      color="primary"
      :size="props.size"
    />
    <span
      v-if="props.text"
      class="text-body-2 text-medium-emphasis"
    >{{ props.text }}</span>
  </div>
</template>
