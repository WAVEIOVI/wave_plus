<script setup>
import WpSearchField from '@/components/shared/filters/WpSearchField.vue'

/**
 * WpPageToolbar
 * ─────────────────────────────────────────────────────────────────────────────
 * Standard list-page toolbar providing:
 *  - Search field
 *  - Create button
 *  - Refresh button
 *  - Filter button (optional)
 *  - Extra actions slot (optional)
 *  - Responsive mobile layout (stacks vertically)
 *
 * Usage:
 *   <WpPageToolbar
 *     v-model:search="search"
 *     :create-label="$t('clients.list.actions.add_new')"
 *     :loading="loading"
 *     :show-filter="true"
 *     :active-filter-count="activeFilterCount"
 *     @create="onCreate"
 *     @refresh="refresh"
 *     @open-filters="filterDialogOpen = true"
 *   >
 *     <!-- optional extra actions -->
 *     <template #extra-actions>
 *       <VBtn ...>Export PDF</VBtn>
 *     </template>
 *   </WpPageToolbar>
 */

const props = defineProps({
  search: {
    type: String,
    default: '',
  },

  /** Label for the primary create button. Pass empty string to hide. */
  createLabel: {
    type: String,
    default: '',
  },

  /** Icon for the create button */
  createIcon: {
    type: String,
    default: 'tabler-plus',
  },

  /** Show the filter button */
  showFilter: {
    type: Boolean,
    default: false,
  },

  /** Hide the filter button below the medium breakpoint when a mobile alternative is available */
  hideFilterOnMobile: {
    type: Boolean,
    default: false,
  },

  /** Hide the create button below the medium breakpoint when a mobile alternative is available */
  hideCreateOnMobile: {
    type: Boolean,
    default: false,
  },

  /** Count of active filters (badge on filter button) */
  activeFilterCount: {
    type: Number,
    default: 0,
  },

  /** Show the refresh button */
  showRefresh: {
    type: Boolean,
    default: true,
  },

  /** Loading state (disables refresh, shows spinner) */
  loading: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: '',
  },

  /** Show search field */
  showSearch: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits([
  'update:search',
  'create',
  'refresh',
  'openFilters',
])
</script>

<template>
  <div class="wp-page-toolbar d-flex flex-column gap-3 pa-4">
    <!-- Top row -->
    <div class="wp-page-toolbar__row d-flex align-center flex-wrap gap-2">
      <!-- Search -->
      <WpSearchField
        v-if="props.showSearch"
        :model-value="props.search"
        :placeholder="props.searchPlaceholder"
        class="wp-page-toolbar__search flex-grow-1"
        style="max-inline-size: 360px;"
        @update:model-value="$emit('update:search', $event)"
      />

      <VSpacer class="d-none d-sm-flex" />

      <!-- Filter Button -->
      <VBtn
        v-if="props.showFilter"
        :class="{ 'd-none d-md-inline-flex': props.hideFilterOnMobile }"
        variant="outlined"
        color="default"
        prepend-icon="tabler-filter"
        size="small"
        :aria-label="$t('wp.filters.label')"
        @click="emit('openFilters')"
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

      <!-- Refresh button -->
      <VBtn
        v-if="props.showRefresh"
        icon="tabler-refresh"
        variant="text"
        color="default"
        size="small"
        :loading="props.loading"
        :aria-label="$t('wp.actions.refresh', 'Refresh')"
        :title="$t('wp.actions.refresh', 'Refresh')"
        @click="$emit('refresh')"
      />

      <!-- Extra actions slot -->
      <slot name="extra-actions">
        <slot name="actions" />
      </slot>

      <!-- Create button -->
      <VBtn
        v-if="props.createLabel"
        :class="{ 'd-none d-md-inline-flex': props.hideCreateOnMobile }"
        color="primary"
        :prepend-icon="props.createIcon"
        size="small"
        @click="$emit('create')"
      >
        {{ props.createLabel }}
      </VBtn>
    </div>

    <!-- Optional filter chip row (slot) -->
    <slot name="filter-chips" />
  </div>
</template>

<style scoped>
.wp-page-toolbar__row {
  min-block-size: 40px;
}

@media (max-width: 599px) {
  .wp-page-toolbar {
    gap: 8px !important;
    padding: 12px !important;
  }

  .wp-page-toolbar__search {
    flex: 1 1 auto !important;
    min-inline-size: 0 !important;
    max-inline-size: none !important;
  }

  .wp-page-toolbar__row {
    gap: 6px !important;
  }

  .wp-page-toolbar__row :deep(.v-btn) {
    flex-shrink: 0;
  }
}
</style>
