<script setup>
/**
 * WpActiveFilters
 * ─────────────────────────────────────────────────────────────────────────────
 * Renders active filters as dismissible chips below the toolbar.
 *
 * Usage:
 *   <WpActiveFilters
 *     :filters="activeFilters"
 *     :label-map="{ status: $t('wp.filters.status'), industry: $t('wp.filters.industry') }"
 *     @remove="removeFilter"
 *     @clear-all="resetFilters"
 *   />
 *
 *  `filters` is the `activeFilters` array from useFilters():
 *  [{ key: 'status', value: 'active' }, { key: 'industry', value: 'IT' }]
 *
 *  `labelMap` maps filter keys to human-readable labels:
 *  { status: 'Status', industry: 'Industry' }
 *
 * @emits remove    - ({ key, value }) when a chip's close button is clicked.
 * @emits clear-all - When "Clear all" link is clicked.
 */
const props = defineProps({
  /** Array from useFilters().activeFilters */
  filters: {
    type: Array,
    default: () => [],
  },
  /**
   * Map of filter key → human-readable label.
   * Falls back to capitalised key name if not provided.
   */
  labelMap: {
    type: Object,
    default: () => ({}),
  },
  /**
   * Optional value formatter per key.
   * { status: (v) => v.charAt(0).toUpperCase() + v.slice(1) }
   */
  valueFormatters: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['remove', 'clear-all'])

const { t } = useI18n()

const chipLabel = filter => {
  const key = props.labelMap[filter.key] || filter.key
  const formatter = props.valueFormatters[filter.key]
  const value = formatter ? formatter(filter.value) : filter.value

  return `${key}: ${value}`
}
</script>

<template>
  <div
    v-if="filters.length"
    class="wp-active-filters d-flex align-center flex-wrap gap-2 py-2"
  >
    <VChip
      v-for="filter in filters"
      :key="filter.key"
      closable
      size="small"
      color="primary"
      variant="tonal"
      class="wp-active-filters__chip"
      @click:close="$emit('remove', filter)"
    >
      {{ chipLabel(filter) }}
    </VChip>

    <VBtn
      variant="text"
      size="x-small"
      color="error"
      class="text-caption"
      @click="$emit('clear-all')"
    >
      {{ $t('wp.filters.clear_all') }}
    </VBtn>
  </div>
</template>
