<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpDocumentMetadataBar
 * ─────────────────────────────────────────────────────────────────────────────
 * Compact strip of document metadata (created by / date, updated, site,
 * currency / rate…). Items wrap onto several rows on small screens.
 *
 *   items: [{ key?, icon, label, value }]   items with an empty value are hidden
 *
 * Slot `item` (scoped: { item }) lets a caller customise a value.
 */
import { computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },

  /** Keep items whose value is empty (shown as an em dash). */
  showEmpty: { type: Boolean, default: false },
})

const visibleItems = computed(() => (props.items || []).filter(item => {
  if (!item) return false
  if (props.showEmpty) return true

  return item.value !== null && item.value !== undefined && item.value !== ''
}))
</script>

<template>
  <div
    v-if="visibleItems.length"
    class="wp-doc-meta-bar"
  >
    <div
      v-for="(item, index) in visibleItems"
      :key="item.key ?? index"
      class="wp-doc-meta-bar__item"
    >
      <VIcon
        v-if="item.icon"
        :icon="item.icon"
        size="18"
        class="text-medium-emphasis flex-shrink-0"
      />
      <div class="min-w-0 wp-doc-meta-bar__content">
        <div class="text-caption text-medium-emphasis wp-doc-meta-bar__label">
          {{ item.label }}
        </div>
        <div class="text-body-2 font-weight-medium wp-doc-meta-bar__value">
          <slot
            name="item"
            :item="item"
          >
            {{ item.value === null || item.value === undefined || item.value === '' ? '—' : item.value }}
          </slot>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wp-doc-meta-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 24px;
  padding: 10px 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.6);
}

.wp-doc-meta-bar__item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  min-inline-size: 0;
  overflow: hidden;
}

.wp-doc-meta-bar__content {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.wp-doc-meta-bar__label {
  line-height: 1.25;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.wp-doc-meta-bar__value {
  overflow-wrap: anywhere;
  word-break: break-word;
}

@media (max-width: 599px) {
  .wp-doc-meta-bar {
    gap: 10px 16px;
  }

  .wp-doc-meta-bar__item {
    flex: 1 1 calc(50% - 16px);
  }
}
</style>
