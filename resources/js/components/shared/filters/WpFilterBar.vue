<script setup>
/**
 * WpFilterBar
 * ─────────────────────────────────────────────────────────────────────────────
 * The filter trigger button row (sits inside WpPageToolbar or below it).
 * Shows:
 *  - A "Filters" button (opens the WpFilterDialog)
 *  - Active filter chip count badge on the button
 *  - WpActiveFilters chips row below (when there are active filters)
 *
 * Usage:
 *   <WpFilterBar
 *     :active-filter-count="activeFilterCount"
 *     :active-filters="activeFilters"
 *     :label-map="{ status: $t('...'), industry: $t('...') }"
 *     @open-filters="filterDialogOpen = true"
 *     @remove-filter="removeFilter"
 *     @clear-all="resetFilters"
 *   />
 */
import WpActiveFilters from './WpActiveFilters.vue'

const props = defineProps({
  /** Total number of active filters (for badge). */
  activeFilterCount: {
    type: Number,
    default: 0,
  },
  /** The activeFilters array from useFilters(). */
  activeFilters: {
    type: Array,
    default: () => [],
  },
  /** Label map for chip display: { key: 'Human Label' } */
  labelMap: {
    type: Object,
    default: () => ({}),
  },
  /** Value formatters per key */
  valueFormatters: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['open-filters', 'remove-filter', 'clear-all'])
</script>

<template>
  <div class="wp-filter-bar">
    <!-- Filter Trigger -->
    <VBtn
      variant="outlined"
      color="default"
      prepend-icon="tabler-filter"
      size="small"
      class="wp-filter-bar__btn"
      @click="$emit('open-filters')"
    >
      {{ $t('wp.filters.label') }}

      <VBadge
        v-if="props.activeFilterCount > 0"
        :content="props.activeFilterCount"
        color="primary"
        inline
        class="ms-1"
      />
    </VBtn>

    <!-- Active Filter Chips -->
    <WpActiveFilters
      :filters="props.activeFilters"
      :label-map="props.labelMap"
      :value-formatters="props.valueFormatters"
      @remove="$emit('remove-filter', $event)"
      @clear-all="$emit('clear-all')"
    />
  </div>
</template>
