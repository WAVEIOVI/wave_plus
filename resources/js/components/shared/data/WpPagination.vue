<script setup>
import { defineEmits, defineProps } from 'vue'
import { useDisplay } from 'vuetify'


/**
 * WpPagination
 * ─────────────────────────────────────────────────────────────────────────────
 * Footer pagination row for list pages.
 * Shows "Showing X to Y of Z entries" + page controls.
 * Wraps the existing @core TablePagination logic with i18n support
 * and an items-per-page selector.
 *
 * Usage:
 *   <WpPagination
 *     v-model:page="page"
 *     v-model:items-per-page="itemsPerPage"
 *     :total-items="totalItems"
 *   />
 */
const props = defineProps({
  page: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  totalItems: {
    type: Number,
    required: true,
  },

  /** How many page buttons to show. */
  totalVisible: {
    type: Number,
    default: 5,
  },

  /** Available per-page options. */
  pageSizeOptions: {
    type: Array,
    default: () => [10, 15, 25, 50, 100],
  },
})

const emit = defineEmits(['update:page', 'update:items-per-page'])

const { t } = useI18n()
const { smAndDown, xs } = useDisplay()

const start = computed(() => {
  if (props.totalItems === 0) return 0

  return (props.page - 1) * props.itemsPerPage + 1
})

const end = computed(() => Math.min(props.page * props.itemsPerPage, props.totalItems))

const pageCount = computed(() =>
  props.itemsPerPage > 0 ? Math.ceil(props.totalItems / props.itemsPerPage) : 0,
)

const metaText = computed(() =>
  t('wp.pagination.meta', { start: start.value, end: end.value, total: props.totalItems }),
)
</script>

<template>
  <div>
    <VDivider />

    <div class="wp-pagination d-flex align-center flex-wrap gap-x-4 gap-y-2 px-4 py-3">
      <!-- Per-page selector -->
      <div class="d-flex align-center gap-2">
        <span
          v-if="!xs"
          class="text-body-2 text-medium-emphasis text-no-wrap"
        >
          {{ $t('wp.pagination.per_page') }}
        </span>
        <VSelect
          :model-value="props.itemsPerPage"
          :items="props.pageSizeOptions"
          density="compact"
          variant="outlined"
          hide-details
          class="wp-pagination__size-select"
          :aria-label="$t('wp.pagination.per_page')"
          @update:model-value="$emit('update:items-per-page', $event)"
        />
      </div>

      <!-- Entries label -->
      <span
        v-if="!smAndDown"
        class="text-body-2 text-medium-emphasis text-no-wrap"
      >
        {{ metaText }}
      </span>

      <VSpacer />

      <!-- Mobile entries label (compact, under controls) -->
      <span
        v-if="smAndDown"
        class="text-caption text-medium-emphasis text-no-wrap order-3 w-100 text-center"
      >
        {{ metaText }}
      </span>

      <!-- Page controls -->
      <VPagination
        :model-value="props.page"
        active-color="primary"
        :length="pageCount"
        :total-visible="xs ? 1 : (smAndDown ? 3 : props.totalVisible)"
        size="small"
        rounded="lg"
        :aria-label="$t('wp.pagination.navigation', 'Pagination')"
        @update:model-value="$emit('update:page', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.wp-pagination {
  min-block-size: 52px;
}

.wp-pagination__size-select {
  max-inline-size: 90px;
  min-inline-size: 76px;
}

.wp-pagination :deep(.v-pagination__item) {
  transition: background-color 0.15s ease, color 0.15s ease;
}

@media (max-width: 599px) {
  .wp-pagination {
    justify-content: center;
    gap: 8px 12px !important;
    padding-block: 10px !important;
    padding-inline: 12px !important;
  }
}
</style>
