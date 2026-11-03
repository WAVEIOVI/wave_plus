<script setup>
import WpEmptyState from '@/components/shared/feedback/WpEmptyState.vue'
import WpErrorBanner from '@/components/shared/feedback/WpErrorBanner.vue'

/**
 * WpDataTable wraps VDataTableServer with reusable loading and empty states.
 * State is controlled by the parent so all table changes reach the list composable.
 */
const props = defineProps({
  headers: {
    type: Array,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  page: {
    type: Number,
    default: 1,
  },
  itemsPerPage: {
    type: Number,
    default: 15,
  },
  sortBy: {
    type: Array,
    default: () => [],
  },
  selected: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object],
    default: null,
  },

  /** Enable row checkbox selection */
  showSelect: {
    type: Boolean,
    default: false,
  },

  /** item-value key for selection */
  itemValue: {
    type: String,
    default: 'id',
  },

  /** Density of the table rows */
  density: {
    type: String,
    default: 'default',
  },

  /** Hide the default footer (use WpPagination instead) */
  hideDefaultFooter: {
    type: Boolean,
    default: true,
  },
  emptyTitle: {
    type: String,
    default: '',
  },
  emptySubtitle: {
    type: String,
    default: '',
  },
  emptyIcon: {
    type: String,
    default: 'tabler-inbox',
  },
  fixedHeader: {
    type: Boolean,
    default: false,
  },
  height: {
    type: [String, Number],
    default: undefined,
  },

  /** Highlight the row on hover (desktop only). */
  hoverable: {
    type: Boolean,
    default: true,
  },

  /** Alternate row background (zebra striping). */
  striped: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:page',
  'update:items-per-page',
  'update:sort-by',
  'update:selected',
  'retry',
])

const { t } = useI18n()

const emptyTitle = computed(() => props.emptyTitle || t('wp.empty.title'))
const emptySubtitle = computed(() => props.emptySubtitle || t('wp.empty.subtitle'))

/** Skeleton row count matches the current page size (bounded 3–10). */
const skeletonRowCount = computed(() => Math.min(10, Math.max(3, props.itemsPerPage || 6)))
</script>

<template>
  <div class="wp-data-table">
    <!-- Error Banner -->
    <WpErrorBanner
      :error="error"
      @retry="$emit('retry')"
    />

    <!-- Data Table -->
    <VDataTableServer
      :headers="props.headers"
      :items="props.items"
      :items-length="props.totalItems"
      :page="props.page"
      :items-per-page="props.itemsPerPage"
      :sort-by="props.sortBy"
      :model-value="props.selected"
      :loading="props.loading"
      :density="props.density"
      :show-select="props.showSelect"
      :item-value="props.itemValue"
      :fixed-header="props.fixedHeader"
      :height="props.height"
      :hide-default-footer="props.hideDefaultFooter"
      class="wp-data-table__table text-no-wrap"
      :class="{
        'wp-data-table__table--hoverable': props.hoverable,
        'wp-data-table__table--striped': props.striped,
      }"
      @update:page="$emit('update:page', $event)"
      @update:items-per-page="$emit('update:items-per-page', $event)"
      @update:sort-by="$emit('update:sort-by', $event)"
      @update:model-value="$emit('update:selected', $event)"
    >
      <!-- Pass all named slots through to the underlying VDataTableServer -->
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps ?? {}"
        />
      </template>

      <!-- Empty state override -->
      <template #no-data>
        <WpEmptyState
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
      </template>

      <!-- Loading state override -->
      <template #loading>
        <VSkeletonLoader
          v-for="n in skeletonRowCount"
          :key="n"
          type="table-row"
          class="wp-data-table__skeleton"
        />
      </template>
    </VDataTableServer>
  </div>
</template>

<style scoped>
.wp-data-table__table :deep(.v-data-table__td) {
  vertical-align: middle;
}

/* Keep the first and last columns clear of the card edge on wide tables. */
.wp-data-table__table :deep(thead tr th:first-child),
.wp-data-table__table :deep(tbody tr td:first-child) {
  padding-inline-start: 24px !important;
}

.wp-data-table__table :deep(thead tr th:last-child),
.wp-data-table__table :deep(tbody tr td:last-child) {
  padding-inline-end: 24px !important;
}

/* Header styling: clear visual separation from rows */
.wp-data-table__table :deep(thead tr th) {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: none;
}

/* Hoverable row feedback */
.wp-data-table__table--hoverable :deep(tbody tr) {
  transition: background-color 0.15s ease;
}

.wp-data-table__table--hoverable :deep(tbody tr:hover) {
  background: rgba(var(--v-theme-primary), 0.04);
}

/* Zebra striping */
.wp-data-table__table--striped :deep(tbody tr:nth-child(even)) {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.wp-data-table__skeleton :deep(.v-skeleton-loader__bone) {
  margin-block: 6px;
}

/* Improve row divider subtlety */
.wp-data-table__table :deep(tbody tr td) {
  border-block-end-color: rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.6));
}
</style>
