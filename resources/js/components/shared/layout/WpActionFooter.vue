<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpActionFooter
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsive action footer shared by wizard dialogs, search dialogs and edit
 * pages: optional "start" content (hint, dirty indicator) on the leading side
 * and the actions on the trailing side. On phones the actions stretch to fill
 * the row and the footer honours the iOS/Android safe-area inset.
 *
 * Slots: default (actions) · start
 *
 *   <WpActionFooter sticky>
 *     <template #start>{{ $t('wp.edit.unsaved') }}</template>
 *     <VBtn>…</VBtn>
 *   </WpActionFooter>
 */
defineProps({
  /** Stick to the bottom of the nearest scroll container. */
  sticky: { type: Boolean, default: false },
  /** Draw a top border + surface background. */
  bordered: { type: Boolean, default: true },
  /** Stack actions vertically on phones (default: share the row). */
  stackOnMobile: { type: Boolean, default: false },
})
</script>

<template>
  <footer
    class="wp-action-footer"
    :class="{
      'wp-action-footer--sticky': sticky,
      'wp-action-footer--bordered': bordered,
      'wp-action-footer--stack': stackOnMobile,
    }"
  >
    <div
      v-if="$slots.start"
      class="wp-action-footer__start text-body-2 text-medium-emphasis"
    >
      <slot name="start" />
    </div>
    <VSpacer />
    <div class="wp-action-footer__actions">
      <slot />
    </div>
  </footer>
</template>

<style scoped>
.wp-action-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  background: rgb(var(--v-theme-surface));
  gap: 8px 12px;
  padding-block: 12px;
  padding-inline: 20px;
}

.wp-action-footer--bordered {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.wp-action-footer--sticky {
  position: sticky;
  z-index: 3;
  inset-block-end: 0;
}

.wp-action-footer__start {
  min-inline-size: 0;
}

.wp-action-footer__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

@media (max-width: 599px) {
  .wp-action-footer {
    padding-block: 10px calc(10px + env(safe-area-inset-bottom, 0px));
    padding-inline: 12px;
  }

  .wp-action-footer__actions {
    flex: 1 1 100%;
    flex-wrap: nowrap;
  }

  .wp-action-footer__actions :deep(.v-btn:not(.v-btn--icon)) {
    flex: 1 1 0;
    min-block-size: 38px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
  }

  .wp-action-footer__actions :deep(.v-btn--icon) {
    flex: 0 0 auto;
    inline-size: 38px;
    block-size: 38px;
    min-inline-size: 38px;
  }

  .wp-action-footer--stack .wp-action-footer__actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }
}
</style>
