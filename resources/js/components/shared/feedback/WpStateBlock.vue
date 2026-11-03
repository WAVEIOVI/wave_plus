<script setup>
/**
 * WpStateBlock
 * ─────────────────────────────────────────────────────────────────────────────
 * One switch for the four non-happy-path states of a page, card or dialog body,
 * built on the existing `WpEmptyState`:
 *
 *   state="loading"   skeleton (`skeleton` = VSkeletonLoader type)
 *   state="error"     message + retry (emits `retry`)
 *   state="empty"     empty state (+ `#empty-action`)
 *   state="forbidden" permission denied (+ `#forbidden-action`)
 *   state="ready"     renders the default slot
 *
 * Authorization still happens on the server – `forbidden` is only a friendly
 * presentation of a 403 / failed `$can` check.
 */
const props = defineProps({
  state: { type: String, default: 'ready', validator: v => ['ready', 'loading', 'error', 'empty', 'forbidden'].includes(v) },
  skeleton: { type: String, default: 'list-item-avatar-two-line, list-item-avatar-two-line, list-item-avatar-two-line' },
  title: { type: String, default: '' },
  text: { type: String, default: '' },
  icon: { type: String, default: '' },
  /** String or Error/ofetch error – shown as the error text. */
  error: { type: [String, Object], default: null },
  retryLabel: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

defineEmits(['retry'])

const { t } = useI18n()

const errorText = computed(() => {
  if (props.text) return props.text
  if (typeof props.error === 'string') return props.error

  return props.error?.data?.message || props.error?.message || t('wp.states.error_text')
})
</script>

<template>
  <div
    class="wp-state-block"
    :class="{ 'wp-state-block--compact': compact }"
  >
    <VSkeletonLoader
      v-if="state === 'loading'"
      :type="skeleton"
      class="bg-transparent"
      role="status"
      aria-busy="true"
      :aria-label="t('wp.states.loading')"
    />

    <WpEmptyState
      v-else-if="state === 'error'"
      icon="tabler-alert-circle"
      :title="title || t('wp.states.error_title')"
      :subtitle="errorText"
    >
      <template #action>
        <slot name="error-action">
          <VBtn
            variant="tonal"
            color="error"
            prepend-icon="tabler-refresh"
            @click="$emit('retry')"
          >
            {{ retryLabel || t('wp.states.retry') }}
          </VBtn>
        </slot>
      </template>
    </WpEmptyState>

    <WpEmptyState
      v-else-if="state === 'empty'"
      :icon="icon || 'tabler-inbox'"
      :title="title || t('wp.states.empty_title')"
      :subtitle="text || t('wp.states.empty_text')"
    >
      <template
        v-if="$slots['empty-action']"
        #action
      >
        <slot name="empty-action" />
      </template>
    </WpEmptyState>

    <WpEmptyState
      v-else-if="state === 'forbidden'"
      :icon="icon || 'tabler-lock'"
      :title="title || t('wp.states.forbidden_title')"
      :subtitle="text || t('wp.states.forbidden_text')"
    >
      <template
        v-if="$slots['forbidden-action']"
        #action
      >
        <slot name="forbidden-action" />
      </template>
    </WpEmptyState>

    <slot v-else />
  </div>
</template>

<style scoped>
.wp-state-block--compact :deep(.wp-empty-state) {
  padding: 16px !important;
}
</style>
