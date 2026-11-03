<script setup>
import WpActiveFilters from '@/components/shared/filters/WpActiveFilters.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import WpEntityCardGrid from './WpEntityCardGrid.vue'
import WpResponsiveList from './WpResponsiveList.vue'

/**
 * WpEntityCollection
 * ─────────────────────────────────────────────────────────────────────────────
 * Unified entity collection component supporting both Table/List and Card/Grid views.
 * Includes view mode toggle, search, filters, selection, sorting, pagination.
 *
 * Usage:
 *   <WpEntityCollection
 *     v-model:view-mode="viewMode"
 *     v-model:search="search"
 *     v-model:filters="filters"
 *     v-model:selected="selected"
 *     v-model:page="page"
 *     v-model:items-per-page="itemsPerPage"
 *     v-model:sort-by="sortBy"
 *     :headers="headers"
 *     :items="items"
 *     :total-items="totalItems"
 *     :loading="loading"
 *     :grid-cols="{ cols: 12, sm: 6, md: 4, lg: 3 }"
 *     show-search
 *   >
 *     <!-- Table slots -->
 *     <template #item.status="{ item }">...</template>
 *
 *     <!-- Grid card slot -->
 *     <template #card="{ entity }">
 *       <WpEntityCard :entity="entity" :title="entity.name" ... />
 *     </template>
 *
 *     <!-- Optional toolbar extras -->
 *     <template #toolbar-prepend>...</template>
 *     <template #toolbar-append>
 *       <VBtn color="primary" prepend-icon="tabler-plus">New</VBtn>
 *     </template>
 *   </WpEntityCollection>
 */
const props = defineProps({
  /** Table headers (required for table view) */
  headers: {
    type: Array,
    default: () => [],
  },

  /** Array of entity objects */
  items: {
    type: Array,
    default: () => [],
  },

  /** Total items for pagination */
  totalItems: {
    type: Number,
    default: 0,
  },

  /** Current view mode */
  viewMode: {
    type: String,
    default: 'table',
    validator: v => ['table', 'grid'].includes(v),
  },

  /** Search query */
  search: {
    type: String,
    default: '',
  },

  /** Active filters object */
  filters: {
    type: Object,
    default: () => ({}),
  },

  /** Selected item IDs */
  selected: {
    type: Array,
    default: () => [],
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

  /** Sort configuration */
  sortBy: {
    type: Array,
    default: () => [],
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

  /** Show selection checkboxes */
  showSelect: {
    type: Boolean,
    default: false,
  },

  /** Item value key for selection */
  itemValue: {
    type: String,
    default: 'id',
  },

  /** Responsive grid columns */
  gridCols: {
    type: Object,
    default: () => ({ cols: 12, sm: 6, md: 4, lg: 3 }),
  },

  /** Grid gap */
  gridGap: {
    type: String,
    default: '4',
  },

  /** Table density */
  tableDensity: {
    type: String,
    default: 'default',
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

  /** Page size options */
  pageSizeOptions: {
    type: Array,
    default: () => [10, 15, 25, 50, 100],
  },

  /** Mobile breakpoint for responsive list */
  mobileBreakpoint: {
    type: String,
    default: 'sm',
  },

  /** Hide view mode toggle */
  hideViewModeToggle: {
    type: Boolean,
    default: false,
  },

  /** Hide pagination */
  hidePagination: {
    type: Boolean,
    default: false,
  },

  /** Show the integrated search input */
  showSearch: {
    type: Boolean,
    default: false,
  },

  /** Search placeholder override */
  searchPlaceholder: {
    type: String,
    default: '',
  },
})

const emit = defineEmits([
  'update:viewMode',
  'update:search',
  'update:filters',
  'update:selected',
  'update:page',
  'update:itemsPerPage',
  'update:sortBy',
  'cardClick',
  'retry',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const emptyTitle = computed(() => props.emptyTitle || t('wp.empty.title'))
const emptySubtitle = computed(() => props.emptySubtitle || t('wp.empty.subtitle'))
const searchPlaceholder = computed(() => props.searchPlaceholder || t('wp.collection.search', 'Search…'))

const activeFilterCount = computed(() =>
  Object.values(props.filters).filter(v => v !== null && v !== '' && v !== undefined).length,
)

// Local v-model proxy for the toggle (prevents direct mutation of a prop).
const internalViewMode = computed({
  get: () => props.viewMode,
  set: val => emit('update:viewMode', val),
})

const internalSearch = computed({
  get: () => props.search,
  set: val => {
    emit('update:search', val)
    emit('update:page', 1)
  },
})

const handleFilterRemove = key => {
  const newFilters = { ...props.filters }

  delete newFilters[key]
  emit('update:filters', newFilters)
  emit('update:page', 1)
}

const handleFilterClearAll = () => {
  emit('update:filters', {})
  emit('update:page', 1)
}

const handleSelectionChange = value => emit('update:selected', value)
const handlePageChange = value => emit('update:page', value)

const handleItemsPerPageChange = value => {
  emit('update:itemsPerPage', value)
  emit('update:page', 1)
}

const handleSortChange = value => emit('update:sortBy', value)
</script>

<template>
  <div class="wp-entity-collection d-flex flex-column gap-4">
    <!-- ── Toolbar: search + view toggle + custom slots ── -->
    <div
      v-if="props.showSearch || !props.hideViewModeToggle || $slots['toolbar-prepend'] || $slots['toolbar-append']"
      class="wp-entity-collection__toolbar d-flex align-center flex-wrap gap-3"
    >
      <slot name="toolbar-prepend" />

      <VTextField
        v-if="props.showSearch"
        v-model="internalSearch"
        :placeholder="searchPlaceholder"
        prepend-inner-icon="tabler-search"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="wp-entity-collection__search"
        :aria-label="searchPlaceholder"
      />

      <VSpacer v-if="!smAndDown" />

      <!-- Active filters count badge -->
      <VChip
        v-if="activeFilterCount > 0"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-filter"
      >
        {{ t('wp.collection.filters_count', { count: activeFilterCount }, activeFilterCount) }}
      </VChip>

      <slot name="toolbar-append" />

      <!-- View mode toggle -->
      <VBtnToggle
        v-if="!props.hideViewModeToggle"
        v-model="internalViewMode"
        mandatory
        density="compact"
        variant="outlined"
        divided
        rounded="lg"
        class="wp-entity-collection__view-toggle"
      >
        <VTooltip
          :text="t('wp.collection.view_table', 'List view')"
          location="top"
        >
          <template #activator="{ props: tp }">
            <VBtn
              v-bind="tp"
              value="table"
              icon="tabler-layout-list"
              size="small"
              :aria-label="t('wp.collection.view_table', 'List view')"
            />
          </template>
        </VTooltip>
        <VTooltip
          :text="t('wp.collection.view_grid', 'Grid view')"
          location="top"
        >
          <template #activator="{ props: tp }">
            <VBtn
              v-bind="tp"
              value="grid"
              icon="tabler-layout-grid"
              size="small"
              :aria-label="t('wp.collection.view_grid', 'Grid view')"
            />
          </template>
        </VTooltip>
      </VBtnToggle>
    </div>

    <!-- Active Filter chips (shown above the collection for immediate feedback) -->
    <WpActiveFilters
      v-if="activeFilterCount > 0"
      :filters="props.filters"
      @remove="handleFilterRemove"
      @clear-all="handleFilterClearAll"
    />

    <!-- Collection body -->
    <Transition
      name="wp-view-fade"
      mode="out-in"
    >
      <!-- Table View -->
      <WpResponsiveList
        v-if="internalViewMode === 'table'"
        key="table"
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
        :density="props.tableDensity"
        :empty-title="props.emptyTitle"
        :empty-subtitle="props.emptySubtitle"
        :empty-icon="props.emptyIcon"
        :page-size-options="props.pageSizeOptions"
        :mobile-breakpoint="props.mobileBreakpoint"
        :hide-pagination="props.hidePagination"
        @update:page="handlePageChange"
        @update:items-per-page="handleItemsPerPageChange"
        @update:sort-by="handleSortChange"
        @update:selected="handleSelectionChange"
        @retry="$emit('retry')"
      >
        <!-- Pass through all slots except card and toolbar-* -->
        <template
          v-for="(_, name) in $slots"
          #[name]="slotProps"
        >
          <slot
            v-if="!['card', 'toolbar-prepend', 'toolbar-append'].includes(name)"
            :name="name"
            v-bind="slotProps ?? {}"
          />
        </template>
      </WpResponsiveList>

      <!-- Grid View -->
      <WpEntityCardGrid
        v-else
        key="grid"
        :entities="props.items"
        :total-items="props.totalItems"
        :loading="props.loading"
        :error="props.error"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :cols="props.gridCols"
        :gap="props.gridGap"
        :empty-title="props.emptyTitle"
        :empty-subtitle="props.emptySubtitle"
        :empty-icon="props.emptyIcon"
        :page-size-options="props.pageSizeOptions"
        :hide-pagination="props.hidePagination"
        :item-value="props.itemValue"
        @update:page="handlePageChange"
        @update:items-per-page="handleItemsPerPageChange"
        @card-click="(entity, event) => $emit('cardClick', entity, event)"
        @retry="$emit('retry')"
      >
        <template #card="{ entity }">
          <slot
            name="card"
            :entity="entity"
          />
        </template>

        <template
          v-if="$slots['empty-action']"
          #empty-action
        >
          <slot name="empty-action" />
        </template>
      </WpEntityCardGrid>
    </Transition>
  </div>
</template>

<style scoped>
.wp-entity-collection__toolbar {
  min-block-size: 40px;
}

.wp-entity-collection__search {
  flex: 1 1 240px;
  max-inline-size: 400px;
}

.wp-entity-collection__view-toggle {
  flex: 0 0 auto;
}

/* Smooth cross-fade when switching between table and grid */
.wp-view-fade-enter-active,
.wp-view-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.wp-view-fade-enter-from,
.wp-view-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (max-width: 599px) {
  .wp-entity-collection__toolbar {
    gap: 8px !important;
  }

  .wp-entity-collection__search {
    flex: 1 1 100%;
    max-inline-size: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wp-view-fade-enter-active,
  .wp-view-fade-leave-active {
    transition: none;
  }
}
</style>
