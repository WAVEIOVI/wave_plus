<script setup>
import WpEmptyState from '@/components/shared/feedback/WpEmptyState.vue'
import WpErrorBanner from '@/components/shared/feedback/WpErrorBanner.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import WpPagination from './WpPagination.vue'

/**
 * WpEntityCardGrid
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsive grid layout for entity cards with pagination.
 *
 * Usage:
 *   <WpEntityCardGrid
 *     :entities="items"
 *     :total-items="totalItems"
 *     :loading="loading"
 *     :page="page"
 *     :items-per-page="itemsPerPage"
 *     :cols="{ xs: 12, sm: 6, md: 4, lg: 3 }"
 *     @update:page="page = $event"
 *     @update:items-per-page="itemsPerPage = $event"
 *   >
 *     <template #card="{ entity }">
 *       <WpEntityCard
 *         :entity="entity"
 *         :title="entity.name"
 *         :subtitle="entity.code"
 *         :status="entity.status"
 *         :status-type="entityType"
 *         :to="`/entity/${entity.id}`"
 *         clickable
 *       >
 *         <template #meta>
 *           <VChip size="x-small" variant="tonal">{{ entity.department }}</VChip>
 *         </template>
 *       </WpEntityCard>
 *     </template>
 *   </WpEntityCardGrid>
 */
const props = defineProps({
  /** Array of entity objects */
  entities: {
    type: Array,
    default: () => [],
  },

  /** Total items for pagination */
  totalItems: {
    type: Number,
    default: 0,
  },

  /** Current page */
  page: {
    type: Number,
    default: 1,
  },

  /** Items per page */
  itemsPerPage: {
    type: Number,
    default: 15,
  },

  /** Loading state */
  loading: {
    type: Boolean,
    default: false,
  },

  /** Error state */
  error: {
    type: [String, Object],
    default: null,
  },

  /**
   * Responsive grid column span per breakpoint.
   * Accepts VCol-style values (12-grid). Example:
   *   { xs: 12, sm: 6, md: 4, lg: 3 }
   */
  cols: {
    type: Object,
    default: () => ({ cols: 12, sm: 6, md: 4, lg: 3 }),
  },

  /** Gap between cards */
  gap: {
    type: String,
    default: '4',
  },

  /** Empty state title */
  emptyTitle: {
    type: String,
    default: '',
  },

  /** Empty state subtitle */
  emptySubtitle: {
    type: String,
    default: '',
  },

  /** Empty state icon */
  emptyIcon: {
    type: String,
    default: 'tabler-inbox',
  },

  /** Page size options for pagination */
  pageSizeOptions: {
    type: Array,
    default: () => [10, 15, 25, 50, 100],
  },

  /** Hide pagination */
  hidePagination: {
    type: Boolean,
    default: false,
  },

  /** Number of skeleton cards to show during the first load. */
  skeletonCount: {
    type: Number,
    default: 8,
  },

  /** item-value key used to key the grid cells. */
  itemValue: {
    type: String,
    default: 'id',
  },
})

const emit = defineEmits(['update:page', 'update:itemsPerPage', 'cardClick', 'retry'])

const { t } = useI18n()

const emptyTitle = computed(() => props.emptyTitle || t('wp.empty.title'))
const emptySubtitle = computed(() => props.emptySubtitle || t('wp.empty.subtitle'))

const totalPages = computed(() => Math.ceil(props.totalItems / props.itemsPerPage))

/** Error has highest priority, then loading, then grid, then empty. */
const hasError = computed(() => !!props.error)
const showInitialLoader = computed(() => props.loading && !props.entities.length && !hasError.value)
const showGrid = computed(() => !hasError.value && props.entities.length > 0)
const showEmpty = computed(() => !hasError.value && !props.loading && !props.entities.length)

const handleCardClick = (event, entity) => {
  emit('cardClick', entity, event)
}
</script>

<template>
  <div class="wp-entity-card-grid">
    <!-- Error State (highest priority) -->
    <WpErrorBanner
      v-if="hasError"
      :error="props.error"
      @retry="$emit('retry')"
    />

    <!-- Loading State (initial) -->
    <template v-else-if="showInitialLoader">
      <VRow dense>
        <VCol
          v-for="n in props.skeletonCount"
          :key="`skeleton-${n}`"
          v-bind="props.cols"
        >
          <VSkeletonLoader
            type="list-item-avatar-two-line, paragraph"
            class="wp-entity-card-grid__skeleton"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Grid -->
    <template v-else-if="showGrid">
      <VRow
        :class="`g-${props.gap}`"
        dense
      >
        <VCol
          v-for="entity in props.entities"
          :key="entity[props.itemValue] ?? entity.id"
          v-bind="props.cols"
          class="d-flex flex-column"
        >
          <slot
            name="card"
            :entity="entity"
          />
        </VCol>
      </VRow>

      <!-- Pagination -->
      <WpPagination
        v-if="!props.hidePagination && props.totalItems > 0"
        class="mt-6 px-2 px-sm-4"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :total-items="props.totalItems"
        :page-size-options="props.pageSizeOptions"
        @update:page="$emit('update:page', $event)"
        @update:items-per-page="$emit('update:itemsPerPage', $event)"
      />
    </template>

    <!-- Empty State -->
    <WpEmptyState
      v-else-if="showEmpty"
      :icon="props.emptyIcon"
      :title="emptyTitle"
      :subtitle="emptySubtitle"
    >
      <template
        v-if="$slots['empty-action']"
        #action
      >
        <slot name="empty-action" />
      </template>
    </WpEmptyState>
  </div>
</template>

<style scoped>
.wp-entity-card-grid {
  position: relative;
  min-block-size: 200px;
}

.wp-entity-card-grid__skeleton {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
}
</style>
