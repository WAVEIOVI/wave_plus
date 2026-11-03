<script setup>
import { humanizeWpStatus, resolveWpStatusMeta } from '@/utils/wp-status'
import { defineProps } from 'vue'

/**
 * WpStatusChip
 * ─────────────────────────────────────────────────────────────────────────────
 * The single status chip used across WAVE PLUS lists, cards and detail headers.
 * Colours and icons are resolved from the shared status registry so a given
 * status looks identical everywhere.
 *
 * Usage:
 *   <WpStatusChip type="employee" status="active" :label="$t('employees.list.active')" />
 *   <WpStatusChip type="vehicle" status="maintenance" dot pulse />
 *
 * Pass `color` to override the registry colour for one-off statuses.
 */
const props = defineProps({
  /** Raw status value coming from the API. */
  status: {
    type: [String, Number],
    default: '',
  },

  /** Domain key in the status registry (employee, vehicle, leave…). */
  type: {
    type: String,
    default: '',
  },

  /** Explicit label (usually an i18n string). Falls back to a humanised status. */
  label: {
    type: String,
    default: '',
  },

  /** Manual colour override. */
  color: {
    type: String,
    default: '',
  },

  /** Manual icon override. */
  icon: {
    type: String,
    default: '',
  },

  size: {
    type: String,
    default: 'small',
  },

  variant: {
    type: String,
    default: 'tonal',
  },

  /** Show a leading status dot instead of an icon. */
  dot: {
    type: Boolean,
    default: false,
  },

  /** Animate the dot with a soft pulse (live states: active, online, running…). */
  pulse: {
    type: Boolean,
    default: false,
  },

  /** Show the registry icon before the label. */
  showIcon: {
    type: Boolean,
    default: false,
  },

  /** Optional tooltip shown on hover (falls back to the chip label when true). */
  tooltip: {
    type: [String, Boolean],
    default: false,
  },
})

const meta = computed(() => resolveWpStatusMeta(props.type, props.status))

const chipColor = computed(() => props.color || meta.value.color)
const chipIcon = computed(() => props.icon || meta.value.icon)
const chipLabel = computed(() => props.label || humanizeWpStatus(props.status))

const tooltipText = computed(() =>
  typeof props.tooltip === 'string' ? props.tooltip : (props.tooltip ? chipLabel.value : ''),
)
</script>

<template>
  <VTooltip
    :disabled="!tooltipText"
    :text="tooltipText"
    location="top"
  >
    <template #activator="{ props: activatorProps }">
      <VChip
        v-bind="activatorProps"
        :color="chipColor"
        :size="props.size"
        :variant="props.variant"
        label
        class="wp-status-chip text-capitalize"
      >
        <template
          v-if="props.dot"
          #prepend
        >
          <span
            class="wp-status-chip__dot"
            :class="{ 'wp-status-chip__dot--pulse': props.pulse }"
            :style="{ backgroundColor: `rgb(var(--v-theme-${chipColor}))` }"
            aria-hidden="true"
          />
        </template>
        <template
          v-else-if="props.showIcon"
          #prepend
        >
          <VIcon
            :icon="chipIcon"
            size="14"
          />
        </template>

        {{ chipLabel }}
      </VChip>
    </template>
  </VTooltip>
</template>

<style scoped>
.wp-status-chip {
  font-weight: 500;
  letter-spacing: 0.01em;
  transition: filter 0.15s ease, transform 0.15s ease;
}

.wp-status-chip:hover {
  filter: brightness(0.96);
}

.wp-status-chip :deep(.v-chip__prepend) {
  margin-inline-end: 6px;
}

.wp-status-chip__dot {
  display: inline-block;
  flex: 0 0 auto;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}

.wp-status-chip__dot--pulse {
  animation: wp-status-pulse 1.8s ease-in-out infinite;
}

@keyframes wp-status-pulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 transparent;
    opacity: 1;
  }

  50% {
    box-shadow: 0 0 0 3px rgba(var(--v-theme-on-surface), 0.08);
    opacity: 0.75;
  }
}
</style>
