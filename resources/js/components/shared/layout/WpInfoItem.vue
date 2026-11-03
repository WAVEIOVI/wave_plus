<script setup>
/**
 * WpInfoItem
 * ─────────────────────────────────────────────────────────────────────────────
 * Read-only "label / value" tile with a tonal icon, used in detail grids of the
 * edit pages (same look as the client edit "detail-row").
 *
 *   <WpInfoItem icon="tabler-mail" :label="$t('…')" :value="email" :href="`mailto:${email}`" />
 *   <WpInfoItem icon="tabler-user" :label="…"><VChip>custom</VChip></WpInfoItem>
 */
import { computed, useSlots } from 'vue'

const props = defineProps({
  icon: { type: String, default: '' },
  label: { type: String, required: true },
  value: { type: [String, Number], default: null },
  color: { type: String, default: 'primary' },
  href: { type: String, default: '' },
  mono: { type: Boolean, default: false },
  /** Hide the tile completely when there is no value and no slot content. */
  hideWhenEmpty: { type: Boolean, default: false },
  emptyText: { type: String, default: '—' },
})

const slots = useSlots()
const hasValue = computed(() => props.value !== null && props.value !== undefined && props.value !== '')
const visible = computed(() => !props.hideWhenEmpty || hasValue.value || !!slots.default)
</script>

<template>
  <div
    v-if="visible"
    class="wp-info-item"
  >
    <VAvatar
      v-if="icon"
      variant="tonal"
      :color="color"
      rounded
      size="36"
      class="wp-info-item__icon"
    >
      <VIcon
        :icon="icon"
        size="20"
      />
    </VAvatar>

    <div class="wp-info-item__body">
      <span class="wp-info-item__label">{{ label }}</span>
      <div class="wp-info-item__value">
        <slot>
          <a
            v-if="hasValue && href"
            :href="href"
            class="text-link"
            :class="{ 'font-monospace': mono }"
          >{{ value }}</a>
          <span
            v-else-if="hasValue"
            :class="{ 'font-monospace': mono }"
          >{{ value }}</span>
          <span
            v-else
            class="text-disabled"
          >{{ emptyText }}</span>
        </slot>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wp-info-item {
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: 10px;
  gap: 12px;
  min-inline-size: 0;
  transition: background 0.2s ease;
}

.wp-info-item:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.wp-info-item__icon {
  flex-shrink: 0;
}

.wp-info-item__body {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.wp-info-item__label {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  line-height: 1.3;
  text-transform: uppercase;
}

.wp-info-item__value {
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

@media (max-width: 599px) {
  .wp-info-item {
    padding: 6px;
    gap: 10px;
  }
}
</style>
