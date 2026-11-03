<script setup>
import { computed, useSlots } from 'vue'

/**
 * WpDataTableCard
 * ─────────────────────────────────────────────────────────────────────────────
 * A VCard shell that wraps WpDataTable + WpPagination into a single, ready-
 * to-use block for list pages. Accepts the same props as WpDataTable.
 *
 * Usage:
 *   <WpDataTableCard
 *     :headers="headers"
 *     :items="rows"
 *     :total-items="totalItems"
 *     v-model:page="page"
 *     v-model:items-per-page="itemsPerPage"
 *     v-model:sort-by="sortBy"
 *     v-model:selected="selected"
 *     :loading="loading"
 *     :error="error"
 *   >
 *     <template #item.status="{ item }">...</template>
 *   </WpDataTableCard>
 */
import WpDataTable from './WpDataTable.vue'
import WpPagination from './WpPagination.vue'

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
  showSelect: {
    type: Boolean,
    default: false,
  },
  itemValue: {
    type: String,
    default: 'id',
  },
  density: {
    type: String,
    default: 'default',
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
  pageSizeOptions: {
    type: Array,
    default: () => [10, 15, 25, 50, 100],
  },

  /** Highlight rows on hover. */
  hoverable: {
    type: Boolean,
    default: true,
  },

  /** Alternate row background. */
  striped: {
    type: Boolean,
    default: false,
  },

  /** Hide the WpPagination footer entirely */
  hidePagination: {
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

const forwardedSlots = computed(() => Object.keys(useSlots()).filter(name => name !== 'top' && name !== 'mobile-card'))
</script>

<template>
  <VCard
    class="wp-data-table-card"
    elevation="0"
    border
    rounded="lg"
  >
    <!-- Optional top slot: toolbar or KPI row -->
    <slot name="top" />

    <!-- Data Table -->
    <WpDataTable
      :headers="props.headers"
      :items="props.items"
      :total-items="props.totalItems"
      :page="props.page"
      :items-per-page="props.itemsPerPage"
      :sort-by="props.sortBy"
      :selected="props.selected"
      :loading="props.loading"
      :error="props.error"
      :show-select="props.showSelect"
      :item-value="props.itemValue"
      :density="props.density"
      :hoverable="props.hoverable"
      :striped="props.striped"
      :empty-title="props.emptyTitle"
      :empty-subtitle="props.emptySubtitle"
      :empty-icon="props.emptyIcon"
      @update:page="$emit('update:page', $event)"
      @update:items-per-page="$emit('update:items-per-page', $event)"
      @update:sort-by="$emit('update:sort-by', $event)"
      @update:selected="$emit('update:selected', $event)"
      @retry="$emit('retry')"
    >
      <!-- Pass ALL named slots through -->
      <template
        v-for="name in forwardedSlots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps ?? {}"
        />
      </template>
    </WpDataTable>

    <!-- Pagination Footer -->
    <WpPagination
      v-if="!props.hidePagination"
      :page="props.page"
      :items-per-page="props.itemsPerPage"
      :total-items="props.totalItems"
      :page-size-options="props.pageSizeOptions"
      @update:page="$emit('update:page', $event)"
      @update:items-per-page="$emit('update:items-per-page', $event)"
    />
  </VCard>
</template>

<style scoped>
.wp-data-table-card {
  overflow: hidden;
  transition: box-shadow 0.2s ease;
}

.wp-data-table-card:hover {
  box-shadow: 0 1px 6px rgba(0, 0, 0, 3%);
}
</style>
