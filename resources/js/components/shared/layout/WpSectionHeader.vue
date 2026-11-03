<script setup>
/**
 * WpSectionHeader
 * ─────────────────────────────────────────────────────────────────────────────
 * Compact section heading with icon, count badge and a contextual action group.
 * Use inside cards/pages when `WpSectionCard`'s boxed header is too heavy.
 *
 * Props: title · subtitle · icon · count · level (heading level 2-6)
 * Slots: actions (contextual action group) · title-append
 */
const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconColor: { type: String, default: 'primary' },
  count: { type: [Number, String], default: undefined },
  /** Semantic heading level (visual size is constant). */
  level: { type: Number, default: 3 },
})

const tag = computed(() => `h${Math.min(6, Math.max(2, props.level))}`)
</script>

<template>
  <div class="wp-section-header">
    <VAvatar
      v-if="icon"
      :color="iconColor"
      variant="tonal"
      rounded="lg"
      size="34"
      class="flex-shrink-0"
    >
      <VIcon
        :icon="icon"
        size="18"
      />
    </VAvatar>

    <div class="wp-section-header__text">
      <component
        :is="tag"
        class="text-subtitle-1 font-weight-bold text-high-emphasis mb-0 d-flex align-center gap-2"
      >
        <span class="text-truncate">{{ title }}</span>
        <VChip
          v-if="count !== undefined && count !== null"
          size="x-small"
          variant="tonal"
          color="secondary"
          label
        >
          {{ count }}
        </VChip>
        <slot name="title-append" />
      </component>
      <div
        v-if="subtitle"
        class="text-caption text-medium-emphasis"
      >
        {{ subtitle }}
      </div>
    </div>

    <div
      v-if="$slots.actions"
      class="wp-section-header__actions"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.wp-section-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}

.wp-section-header__text {
  flex: 1 1 160px;
  min-inline-size: 0;
}

.wp-section-header__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  margin-inline-start: auto;
  gap: 6px;
}
</style>
