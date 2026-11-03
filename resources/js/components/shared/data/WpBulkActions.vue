<script setup>
import { defineEmits, defineProps } from 'vue'
import { useDisplay } from 'vuetify'

/**
 * WpBulkActions
 * ─────────────────────────────────────────────────────────────────────────────
 * Contextual bulk-action bar that appears when rows are selected.
 * Slides in from the bottom of the toolbar area.
 *
 * Usage:
 *   <WpBulkActions
 *     :count="selected.length"
 *     :actions="[
 *       { label: $t('...'), icon: 'tabler-trash', emit: 'bulk-delete', color: 'error' },
 *     ]"
 *     @bulk-delete="onBulkDelete"
 *     @clear="clearSelection"
 *   />
 */
const props = defineProps({
  /** Number of currently selected items. */
  count: {
    type: Number,
    default: 0,
  },

  /**
   * Array of action objects:
   * { label, icon, emit, color?, disabled?, loading? }
   */
  actions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['clear'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const label = computed(() =>
  t('wp.bulk.selected', { count: props.count }),
)
</script>

<template>
  <Transition name="wp-slide-down">
    <VCard
      v-if="count > 0"
      color="primary"
      class="wp-bulk-actions d-flex align-center flex-wrap gap-2 px-4 py-2 mb-4"
      rounded="lg"
      elevation="0"
      variant="tonal"
      role="region"
      :aria-label="label"
      aria-live="polite"
    >
      <!-- Selection count label -->
      <div class="d-flex align-center gap-2 flex-grow-1 min-width-0">
        <VAvatar
          color="primary"
          size="26"
          variant="flat"
          class="flex-shrink-0"
        >
          <span class="text-caption font-weight-bold">{{ count }}</span>
        </VAvatar>
        <span class="text-body-2 font-weight-semibold text-truncate">{{ label }}</span>
      </div>

      <!-- Action buttons (icon-only with tooltip on small screens) -->
      <template
        v-for="(action, idx) in props.actions"
        :key="idx"
      >
        <VTooltip
          v-if="smAndDown"
          :text="action.label"
          location="top"
        >
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              :color="action.color || 'default'"
              :loading="action.loading"
              :disabled="action.disabled"
              :icon="action.icon"
              size="small"
              variant="elevated"
              :aria-label="action.label"
              @click="$emit(action.emit)"
            />
          </template>
        </VTooltip>

        <VBtn
          v-else
          :color="action.color || 'default'"
          :loading="action.loading"
          :disabled="action.disabled"
          :prepend-icon="action.icon"
          size="small"
          variant="elevated"
          @click="$emit(action.emit)"
        >
          {{ action.label }}
        </VBtn>
      </template>

      <!-- Clear selection -->
      <VTooltip
        :text="t('wp.bulk.clear', 'Clear selection')"
        location="top"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            v-bind="tooltipProps"
            icon="tabler-x"
            size="x-small"
            variant="text"
            color="default"
            :aria-label="t('wp.bulk.clear', 'Clear selection')"
            @click="$emit('clear')"
          />
        </template>
      </VTooltip>
    </VCard>
  </Transition>
</template>

<style scoped>
.min-width-0 {
  min-inline-size: 0;
}

.wp-slide-down-enter-active,
.wp-slide-down-leave-active {
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.wp-slide-down-enter-from,
.wp-slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
