<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

/**
 * WpResponsiveList
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsive container that renders a WpDataTableCard on ≥ md screens
 * and a card-list layout on smaller screens, using the same data.
 *
 * Usage:
 *   <WpResponsiveList
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
 *
 *     <!-- Mobile card slot receives each item -->
 *     <template #mobile-card="{ item }">
 *       <WpMobileCard :title="item.name">...</WpMobileCard>
 *     </template>
 *   </WpResponsiveList>
 */
import WpEmptyState from '@/components/shared/feedback/WpEmptyState.vue'
import WpErrorBanner from '@/components/shared/feedback/WpErrorBanner.vue'
import WpLoadingState from '@/components/shared/feedback/WpLoadingState.vue'
import WpDataTableCard from './WpDataTableCard.vue'
import WpMobileCard from './WpMobileCard.vue'
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

  /** Breakpoint below which the mobile card view is used ('sm' or 'xs'). */
  mobileBreakpoint: {
    type: String,
    default: 'sm',
  },

  /** Density of the desktop table rows */
  density: {
    type: String,
    default: 'default',
  },

  /** Hide the pagination footer entirely */
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

const { t } = useI18n()
const display = useDisplay()

const isMobileView = computed(() => {
  if (props.mobileBreakpoint === 'xs') return display.xs.value
  if (props.mobileBreakpoint === 'sm') return display.smAndDown.value
  if (props.mobileBreakpoint === 'md') return display.width.value < 1024

  return display.smAndDown.value
})

const emptyTitle = computed(() => props.emptyTitle || t('wp.empty.title'))
const emptySubtitle = computed(() => props.emptySubtitle || t('wp.empty.subtitle'))

const sortableHeaders = computed(() => props.headers.filter(header => header.sortable !== false && header.key))

const mobileSortKey = computed({
  get: () => sortableHeaders.value.some(header => header.key === props.sortBy[0]?.key)
    ? props.sortBy[0].key
    : sortableHeaders.value[0]?.key ?? null,
  set: key => {
    if (key) emit('update:sort-by', [{ key, order: props.sortBy[0]?.order ?? 'asc' }])
  },
})

const mobileSortOrder = computed(() => props.sortBy[0]?.order ?? 'asc')

const toggleMobileSortOrder = () => {
  if (!mobileSortKey.value) return
  emit('update:sort-by', [{
    key: mobileSortKey.value,
    order: mobileSortOrder.value === 'asc' ? 'desc' : 'asc',
  }])
}

const updateMobileSelection = (item, selected) => {
  const value = item?.[props.itemValue]
  const selection = props.selected.filter(selectedValue => selectedValue !== value)
  if (selected) selection.push(value)
  emit('update:selected', selection)
}

const isMobileItemSelected = item => props.selected.includes(item?.[props.itemValue])
</script>

<template>
  <div class="wp-responsive-list">
    <!-- ── Desktop: VDataTableServer via WpDataTableCard ── -->
    <WpDataTableCard
      v-if="!isMobileView"
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
      :empty-title="props.emptyTitle"
      :empty-subtitle="props.emptySubtitle"
      :empty-icon="props.emptyIcon"
      :page-size-options="props.pageSizeOptions"
      :hide-pagination="props.hidePagination"
      @update:page="$emit('update:page', $event)"
      @update:items-per-page="$emit('update:items-per-page', $event)"
      @update:sort-by="$emit('update:sort-by', $event)"
      @update:selected="$emit('update:selected', $event)"
      @retry="$emit('retry')"
    >
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          v-if="name !== 'mobile-card'"
          :name="name"
          v-bind="slotProps ?? {}"
        />
      </template>
    </WpDataTableCard>

    <!-- ── Mobile: Card List ── -->
    <template v-else>
      <WpErrorBanner
        :error="props.error"
        @retry="$emit('retry')"
      />

      <div
        v-if="sortableHeaders.length"
        class="wp-responsive-list__mobile-controls d-flex align-center gap-2 px-3 px-sm-4 mb-3"
      >
        <VSelect
          v-model="mobileSortKey"
          :items="sortableHeaders.map(({ title, key }) => ({ title, value: key }))"
          :label="t('wp.list.sort_by', 'Sort by')"
          density="compact"
          variant="outlined"
          hide-details
          prepend-inner-icon="tabler-arrows-sort"
        />
        <VTooltip
          :text="t('wp.list.change_sort_order', 'Change sort order')"
          location="top"
        >
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              variant="outlined"
              :aria-label="t('wp.list.change_sort_order', 'Change sort order')"
              @click="toggleMobileSortOrder"
            >
              <VIcon :icon="mobileSortOrder === 'asc' ? 'tabler-sort-ascending' : 'tabler-sort-descending'" />
            </VBtn>
          </template>
        </VTooltip>
      </div>

      <WpLoadingState
        v-if="props.loading && !props.items.length"
        min-height="300px"
      />

      <TransitionGroup
        v-else-if="props.items.length"
        name="wp-mobile-list"
        tag="div"
        class="wp-responsive-list__mobile-cards px-2 px-sm-3 px-md-4"
      >
        <slot
          v-for="item in props.items"
          :key="item[props.itemValue]"
          name="mobile-card"
          :item="item"
          :selected="isMobileItemSelected(item)"
          :update-selected="value => updateMobileSelection(item, value)"
        >
          <WpMobileCard
            :title="item.name || item.title || item.legal_name || item.code || String(item[props.itemValue])"
            :subtitle="item.code || item.reference || ''"
            :status="item.status"
            :selected="isMobileItemSelected(item)"
            :show-select="props.showSelect"
            :item="item"
          >
            <template #details>
              <div class="d-flex flex-column gap-1 text-caption">
                <template
                  v-for="h in sortableHeaders.slice(0, 4)"
                  :key="h.key"
                >
                  <div
                    v-if="item[h.key] != null"
                    class="wp-mobile-row"
                  >
                    <span class="wp-mobile-row__label">{{ h.title }}:</span>
                    <span class="wp-mobile-row__value">{{ item[h.key] }}</span>
                  </div>
                </template>
              </div>
            </template>
          </WpMobileCard>
        </slot>
      </TransitionGroup>

      <WpEmptyState
        v-else-if="!props.loading"
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

      <!-- Mobile Pagination -->
      <WpPagination
        v-if="!props.hidePagination"
        class="px-2 px-sm-4"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :total-items="props.totalItems"
        :page-size-options="props.pageSizeOptions"
        @update:page="$emit('update:page', $event)"
        @update:items-per-page="$emit('update:items-per-page', $event)"
      />
    </template>
  </div>
</template>

<style scoped>
.wp-responsive-list__mobile-controls :deep(.v-input) {
  min-inline-size: 0;
}

.wp-responsive-list__mobile-controls :deep(.v-btn) {
  flex: 0 0 auto;
}

/* Consistent gap between mobile cards */
.wp-responsive-list__mobile-cards {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* Smooth card list transitions on mobile */
.wp-mobile-list-enter-active,
.wp-mobile-list-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.wp-mobile-list-enter-from,
.wp-mobile-list-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

.wp-mobile-list-move {
  transition: transform 0.3s ease;
}

@media (prefers-reduced-motion: reduce) {
  .wp-mobile-list-enter-active,
  .wp-mobile-list-leave-active,
  .wp-mobile-list-move {
    transition: none;
  }
}
</style>
