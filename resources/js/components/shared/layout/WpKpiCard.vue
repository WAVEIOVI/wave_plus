<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpKpiCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic KPI / statistic card for list page summaries.
 *
 * Usage:
 *   <WpKpiCard
 *     :title="$t('clients.list.stats.total')"
 *     :value="stats.total"
 *     icon="tabler-users"
 *     icon-color="primary"
 *     :loading="statsLoading"
 *   />
 *
 * Trend example:
 *   <WpKpiCard
 *     title="Active Clients"
 *     :value="stats.active"
 *     :trend="{ value: '+12%', direction: 'up', label: 'vs last month' }"
 *   />
 */
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  subtitle: {
    type: String,
    default: '',
  },
  icon: {
    type: String,
    default: 'tabler-chart-bar',
  },

  /** Vuetify color token for the icon background */
  iconColor: {
    type: String,
    default: 'primary',
  },

  /** Loading skeleton state */
  loading: {
    type: Boolean,
    default: false,
  },

  /**
   * Optional trend object:
   * { value: '+12%', direction: 'up' | 'down' | 'neutral', label: string }
   */
  trend: {
    type: Object,
    default: null,
  },

  /** Whether this card is "active" / selected (used for filter shortcut cards) */
  active: {
    type: Boolean,
    default: false,
  },

  /** Cursor if the card is clickable */
  clickable: {
    type: Boolean,
    default: false,
  },
  to: {
    type: [String, Object],
    default: undefined,
  },
})

const emit = defineEmits(['click'])

const trendColor = computed(() => {
  if (!props.trend) return 'default'
  if (props.trend.direction === 'up') return 'success'
  if (props.trend.direction === 'down') return 'error'

  return 'default'
})

const trendIcon = computed(() => {
  if (!props.trend) return ''
  if (props.trend.direction === 'up') return 'tabler-trending-up'
  if (props.trend.direction === 'down') return 'tabler-trending-down'

  return 'tabler-minus'
})

const isInteractive = computed(() => props.clickable || Boolean(props.to))
</script>

<template>
  <VCard
    class="wp-kpi-card"
    elevation="0"
    border
    rounded="lg"
    :class="[
      {
        'wp-kpi-card--active': props.active,
        'cursor-pointer': isInteractive,
      },
    ]"
    :to="props.to"
    @click="props.clickable && $emit('click')"
  >
    <VCardText class="pa-4">
      <VSkeletonLoader
        v-if="props.loading"
        type="text, text"
      />

      <template v-else>
        <div class="d-flex align-start justify-space-between">
          <!-- Text -->
          <div class="wp-kpi-card__content">
            <p class="wp-kpi-card__title text-caption text-medium-emphasis text-uppercase font-weight-medium mb-1">
              {{ props.title }}
            </p>
            <p
              class="wp-kpi-card__value font-weight-bold text-high-emphasis mb-0"
              :class="[
                typeof props.value === 'string' && props.value.length > 7
                  ? 'wp-kpi-card__value--compact'
                  : 'text-h5'
              ]"
            >
              {{ props.value }}
            </p>
            <p
              v-if="props.subtitle"
              class="text-caption text-medium-emphasis mt-1 mb-0"
            >
              {{ props.subtitle }}
            </p>
          </div>

          <!-- Icon -->
          <VAvatar
            class="wp-kpi-card__icon"
            :color="props.iconColor"
            variant="tonal"
            size="44"
            rounded="lg"
          >
            <VIcon
              :icon="props.icon"
              size="24"
            />
          </VAvatar>
        </div>

        <!-- Trend -->
        <div
          v-if="props.trend"
          class="d-flex align-center flex-wrap gap-1 mt-2"
        >
          <VIcon
            :icon="trendIcon"
            :color="trendColor"
            size="14"
          />
          <span
            class="text-caption font-weight-semibold"
            :class="`text-${trendColor}`"
          >{{ props.trend.value }}</span>
          <span
            v-if="props.trend.label"
            class="text-caption text-medium-emphasis"
          >{{ props.trend.label }}</span>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.wp-kpi-card--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
}

.wp-kpi-card {
  block-size: 100%;
}

.wp-kpi-card :deep(.v-card-text) {
  block-size: 100%;
}

.wp-kpi-card__content {
  min-inline-size: 0;
  flex: 1 1 0;
}

.wp-kpi-card__value {
  word-break: normal;
  overflow-wrap: break-word;
  line-height: 1.2;
}

.wp-kpi-card__value--compact {
  font-size: 1rem;
}

.wp-kpi-card__icon {
  flex: 0 0 auto;
}

@media (max-width: 480px) {
  .wp-kpi-card :deep(.v-card-text) {
    padding: 0.625rem 0.75rem !important;
  }

  .wp-kpi-card__icon {
    block-size: 32px !important;
    inline-size: 32px !important;
    border-radius: 8px !important;
  }

  .wp-kpi-card__icon :deep(.v-icon) {
    font-size: 18px !important;
  }

  .wp-kpi-card :deep(.text-h5) {
    font-size: 1.15rem !important;
  }

  .wp-kpi-card__value--compact {
    font-size: 0.85rem !important;
    line-height: 1.15 !important;
  }

  .wp-kpi-card__title {
    font-size: 0.6875rem !important;
    line-height: 1.15 !important;
  }
}
</style>
