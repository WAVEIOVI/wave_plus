<script setup>
/**
 * WpErrorBanner
 * ─────────────────────────────────────────────────────────────────────────────
 * Dismissible inline error banner for list pages and dialogs.
 * Wraps Vuetify VAlert in a consistent ERP style.
 *
 * Usage:
 *   <WpErrorBanner
 *     :error="listError"
 *     @retry="fetchData"
 *   />
 *
 * @emits retry   - When the user clicks the "Retry" button.
 * @emits dismiss - When the user closes the banner.
 */
const props = defineProps({
  /** Error message string or Error object. */
  error: {
    type: [String, Error, Object],
    default: null,
  },
  /** Show a retry action button. */
  showRetry: {
    type: Boolean,
    default: true,
  },
  /** Label for the retry button. Falls back to i18n key. */
  retryLabel: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['retry', 'dismiss'])

const { t } = useI18n()
const isVisible = ref(!!props.error)

watch(() => props.error, error => {
  isVisible.value = !!error
})

function dismiss() {
  isVisible.value = false
  emit('dismiss')
}

const errorMessage = computed(() => {
  if (!props.error) return ''
  if (typeof props.error === 'string') return props.error
  if (props.error instanceof Error) return props.error.message

  return props.error?.message || props.error?.data?.message || t('wp.error.generic')
})

const retryText = computed(() => props.retryLabel || t('wp.error.retry'))
</script>

<template>
  <VAlert
    v-if="error && isVisible"
    type="error"
    variant="tonal"
    rounded="lg"
    class="wp-error-banner mb-4"
    closable
    @click:close="dismiss"
  >
    <template #title>
      {{ $t('wp.error.title') }}
    </template>

    <p class="text-body-2 mb-0">
      {{ errorMessage }}
    </p>

    <template
      v-if="showRetry"
      #append
    >
      <VBtn
        variant="tonal"
        color="error"
        size="small"
        :prepend-icon="'tabler-refresh'"
        @click="$emit('retry')"
      >
        {{ retryText }}
      </VBtn>
    </template>
  </VAlert>
</template>
