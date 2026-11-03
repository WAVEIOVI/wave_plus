<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpKpiGroup
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsive grid wrapper for a row of WpKpiCards.
 *
 * Usage:
 *   <WpKpiGroup :cols="{ xs: 2, sm: 3, md: 4, lg: 5 }">
 *     <WpKpiCard v-for="kpi in kpis" v-bind="kpi" :key="kpi.title" />
 *   </WpKpiGroup>
 */
const props = defineProps({
  /**
   * Number of columns at each breakpoint.
   * Vuetify VRow uses 12-col grid; these map to VCol's cols/sm/md/lg props.
   */
  cols: {
    type: Object,
    default: () => ({ xs: 2, sm: 2, md: 3, lg: 4 }),
  },
  itemCount: {
    type: Number,
    default: 0,
  },
})

const columnsFor = breakpoint => props.cols[breakpoint] ?? {
  xs: 2,
  sm: 2,
  md: 3,
  lg: 4,
}[breakpoint]

const spanFor = (breakpoint, index) => {
  const columns = columnsFor(breakpoint)
  const itemCount = props.itemCount || columns
  const finalRowCount = itemCount % columns || columns
  const finalRowStart = itemCount - finalRowCount
  const itemsInRow = index >= finalRowStart ? finalRowCount : columns

  return 12 / itemsInRow
}

const getColProps = index => ({
  cols: spanFor('xs', index),
  sm: spanFor('sm', index),
  md: spanFor('md', index),
  lg: spanFor('lg', index),
})
</script>

<template>
  <VRow class="wp-kpi-group mb-4">
    <!-- Expects WpKpiCard children — use default slot -->
    <slot v-bind="{ getColProps }" />
  </VRow>
</template>
