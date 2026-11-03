<script setup>
/**
 * WpSearchSelectDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic "find and pick an entity" dialog (clients, suppliers, providers,
 * catalog items, addresses, employees …). It knows nothing about any entity:
 * data comes from a `fetcher`, appearance from `display` / `columns`.
 *
 * <WpSearchSelectDialog
 *   v-model="open"
 *   :title="$t('…')" entity-label="client"
 *   :fetcher="({ page, perPage, search, filters, sortBy, sortDir, signal }) => api.clients({ … }, { signal })"
 *   :columns="[{ key: 'name', title: 'Name', sortable: true }, …]"
 *   :display="{ title: 'name', subtitle: 'email', avatar: 'logo_url', chips: [i => ({ label: i.status })] }"
 *   :filters="[{ key: 'status', label: 'Status', type: 'choice', options: [...] }]"
 *   :selected="currentClient"
 *   @select="client => …"
 * />
 *
 * The fetcher may return an array, `{ data, total }`, `{ items, total }` or a Laravel
 * paginator. Pagination, sorting and filtering are ALWAYS server-side; outdated
 * responses can never overwrite newer ones (see `useSearchSelect`).
 *
 * Props   title · entityLabel · icon · fetcher · itemKey · columns · display · filters · sortOptions
 *         views (['table','list','grid']) · defaultView · multiple · maxSelection · confirmBehavior
 *         ('auto'|'immediate'|'confirm') · pageSize · pageSizeOptions · debounce · maxWidth
 *         isDisabled · clearable · selected · initialFilters · emptyTitle · emptyText
 * Emits   update:modelValue · select(item | items[] | null) · cancel · error(err)
 * Slots   item · card · cell-<key> · trailing · empty · toolbar-append · filters-extra · footer-start
 */
import { useSearchSelect } from '@/composables/shared/useSearchSelect'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  entityLabel: { type: String, default: '' },
  icon: { type: String, default: 'tabler-search' },
  fetcher: { type: Function, required: true },
  itemKey: { type: String, default: 'id' },
  columns: { type: Array, default: () => [] },
  display: { type: Object, default: () => ({}) },
  filters: { type: Array, default: () => [] },
  sortOptions: { type: Array, default: undefined },
  views: { type: Array, default: () => ['table', 'list', 'grid'] },
  defaultView: { type: String, default: 'list' },
  multiple: { type: Boolean, default: false },
  maxSelection: { type: Number, default: Infinity },
  confirmBehavior: { type: String, default: 'auto', validator: v => ['auto', 'immediate', 'confirm'].includes(v) },
  pageSize: { type: Number, default: 12 },
  pageSizeOptions: { type: Array, default: () => [6, 12, 24, 48] },
  debounce: { type: Number, default: 300 },
  maxWidth: { type: [Number, String], default: 960 },
  isDisabled: { type: Function, default: () => false },
  /** Allow clearing the current selection from the dialog. */
  clearable: { type: Boolean, default: false },
  /** Current selection (item or array of items). */
  selected: { type: [Object, Array], default: null },
  initialFilters: { type: Object, default: () => ({}) },
  emptyTitle: { type: String, default: '' },
  emptyText: { type: String, default: '' },
  initialSort: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'select', 'cancel', 'error'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const uid = useId()

const entity = computed(() => props.entityLabel || t('wp.picker.entity_default'))

const filterLabels = Object.fromEntries(props.filters.map(f => [f.key, f.label || f.key]))

const picker = useSearchSelect({
  fetcher: args => props.fetcher(args),
  itemKey: props.itemKey,
  multiple: props.multiple,
  maxSelection: props.maxSelection,
  perPage: props.pageSize,
  debounce: props.debounce,
  view: props.defaultView,
  sort: props.initialSort,
  filters: props.initialFilters,
  filterLabels,
})

const {
  search, filters, sortBy, sortDir, page, perPage, view, items, total, loading, error, loaded, isEmpty,
  activeFilters, activeFilterCount, hasCriteria, selectedItems, selectedKeys, limitReached,
} = picker

const filtersOpen = ref(false)

const immediate = computed(() => (props.confirmBehavior === 'auto' ? !props.multiple : props.confirmBehavior === 'immediate'))

// Table is too wide for phones → silently use the list instead.
const availableViews = computed(() => props.views.filter(v => !(smAndDown.value && v === 'table')))
const effectiveView = computed(() => (availableViews.value.includes(view.value) ? view.value : availableViews.value[0] || 'list'))

const VIEW_ICONS = { table: 'tabler-table', list: 'tabler-list', grid: 'tabler-layout-grid' }

const sortChoices = computed(() => props.sortOptions
  || props.columns.filter(c => c.sortable).map(c => ({ title: c.title, value: c.key })))

const labelFor = filter => {
  const def = props.filters.find(f => f.key === filter.key)
  const options = def?.options || []
  const pick = v => (options.find(o => (o?.value ?? o) === v)?.title ?? v)
  const value = Array.isArray(filter.value) ? filter.value.map(pick).join(', ') : pick(filter.value)

  return `${def?.label || filter.key}: ${filter.value === true ? t('wp.wizard.yes') : value}`
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
watch(() => props.modelValue, open => {
  if (!open) return
  picker.setSelection([].concat(props.selected ?? []))
  filtersOpen.value = false
  picker.resetAll()
})

onBeforeUnmount(() => picker.dispose())

watch(error, err => err && emit('error', err))

// ── Actions ──────────────────────────────────────────────────────────────────
const close = () => emit('update:modelValue', false)

const cancel = () => {
  emit('cancel')
  close()
}

const confirm = () => {
  emit('select', props.multiple ? selectedItems.value : selectedItems.value[0] ?? null)
  close()
}

const clear = () => {
  picker.clearSelection()
  emit('select', props.multiple ? [] : null)
  close()
}

const onToggle = item => {
  const ok = picker.toggle(item)

  if (ok && immediate.value && picker.isSelected(item)) confirm()
}

const onSort = key => picker.setSort(key)

const onSortSelect = key => picker.setSort(key, sortDir.value)

const toggleDir = () => picker.setSort(sortBy.value, sortDir.value === 'asc' ? 'desc' : 'asc')

const onFilterChange = (key, value) => (value === null || value === undefined || value === '' ? picker.removeFilter(key) : picker.setFilter(key, value))

const pageCount = computed(() => perPage.value)
const cellSlot = col => `cell-${col.key}`
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :fullscreen="smAndDown"
    scrollable
    class="wp-ss"
    @update:model-value="v => !v ? cancel() : emit('update:modelValue', true)"
  >
    <VCard
      class="wp-ss__card d-flex flex-column"
      role="dialog"
      :aria-labelledby="`${uid}-title`"
    >
      <!-- ── Header ──────────────────────────────────────────────────── -->
      <header class="wp-ss__header">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          :size="smAndDown ? 36 : 42"
          class="flex-shrink-0"
        >
          <VIcon
            :icon="icon"
            size="22"
          />
        </VAvatar>
        <div class="flex-grow-1 min-w-0">
          <h2
            :id="`${uid}-title`"
            class="text-h6 font-weight-bold text-truncate mb-0"
          >
            {{ title || t('wp.picker.title', { entity }) }}
          </h2>
          <div
            class="text-caption text-medium-emphasis"
            aria-live="polite"
          >
            <template v-if="loaded && !error">
              {{ t('wp.picker.results', { count: total }, total) }}
            </template>
          </div>
        </div>
        <IconBtn
          variant="text"
          size="small"
          :aria-label="t('wp.wizard.close')"
          @click="cancel"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </header>

      <!-- ── Toolbar ─────────────────────────────────────────────────── -->
      <div class="wp-ss__toolbar">
        <VTextField
          :model-value="search"
          :placeholder="t('wp.picker.search_placeholder', { entity })"
          :aria-label="t('wp.picker.search_placeholder', { entity })"
          prepend-inner-icon="tabler-search"
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          autofocus
          autocomplete="off"
          :loading="loading && !!items.length"
          class="wp-ss__search"
          @update:model-value="picker.setSearch"
          @keydown.enter.prevent="picker.flushSearch"
          @click:clear="picker.clearSearch"
        />

        <VBtn
          v-if="filters && props.filters.length"
          :variant="filtersOpen || activeFilterCount ? 'tonal' : 'outlined'"
          :color="activeFilterCount ? 'primary' : 'secondary'"
          prepend-icon="tabler-filter"
          :aria-expanded="filtersOpen"
          class="wp-ss__tool-btn"
          @click="filtersOpen = !filtersOpen"
        >
          <span class="d-none d-sm-inline">{{ t('wp.picker.filters') }}</span>
          <VBadge
            v-if="activeFilterCount"
            :content="activeFilterCount"
            color="primary"
            inline
          />
        </VBtn>

        <!-- Sort for list / grid (table sorts from its headers) -->
        <VMenu v-if="sortChoices.length && effectiveView !== 'table'">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              variant="outlined"
              color="secondary"
              :prepend-icon="sortDir === 'asc' ? 'tabler-sort-ascending' : 'tabler-sort-descending'"
              class="wp-ss__tool-btn"
              :aria-label="t('wp.picker.sort_by')"
            >
              <span class="d-none d-sm-inline">{{ t('wp.picker.sort_by') }}</span>
            </VBtn>
          </template>
          <VList
            density="compact"
            :selected="[sortBy]"
          >
            <VListItem
              v-for="opt in sortChoices"
              :key="opt.value"
              :value="opt.value"
              :title="opt.title"
              @click="onSortSelect(opt.value)"
            />
            <VDivider />
            <VListItem
              :prepend-icon="sortDir === 'asc' ? 'tabler-sort-descending' : 'tabler-sort-ascending'"
              :title="t(sortDir === 'asc' ? 'wp.picker.sort_desc' : 'wp.picker.sort_asc')"
              @click="toggleDir"
            />
          </VList>
        </VMenu>

        <VBtnToggle
          v-if="availableViews.length > 1"
          :model-value="effectiveView"
          mandatory
          density="comfortable"
          variant="outlined"
          color="primary"
          divided
          class="wp-ss__views"
          :aria-label="t('wp.picker.view')"
          @update:model-value="picker.setView"
        >
          <VBtn
            v-for="v in availableViews"
            :key="v"
            :value="v"
            :aria-label="t(`wp.picker.view_${v}`)"
            :title="t(`wp.picker.view_${v}`)"
          >
            <VIcon :icon="VIEW_ICONS[v]" />
          </VBtn>
        </VBtnToggle>

        <slot name="toolbar-append" />
      </div>

      <!-- ── Filters + active chips ──────────────────────────────────── -->
      <VExpandTransition>
        <div
          v-if="filtersOpen && props.filters.length"
          class="wp-ss__filters"
        >
          <WpSearchSelectFilters
            :filters="props.filters"
            :values="filters"
            @change="onFilterChange"
            @reset="picker.resetFilters"
          />
          <slot name="filters-extra" />
        </div>
      </VExpandTransition>

      <div
        v-if="activeFilters.length || search"
        class="wp-ss__chips"
      >
        <span class="text-caption text-medium-emphasis me-1">
          {{ t('wp.picker.filter_summary', { count: activeFilters.length + (search ? 1 : 0) }) }}
        </span>
        <VChip
          v-if="search"
          size="small"
          closable
          color="primary"
          variant="tonal"
          prepend-icon="tabler-search"
          @click:close="picker.clearSearch"
        >
          “{{ search }}”
        </VChip>
        <VChip
          v-for="f in activeFilters"
          :key="f.key"
          size="small"
          closable
          color="primary"
          variant="tonal"
          @click:close="picker.removeFilter(f.key)"
        >
          {{ labelFor(f) }}
        </VChip>
        <VBtn
          size="x-small"
          variant="text"
          color="error"
          @click="picker.resetAll"
        >
          {{ t('wp.picker.clear_all') }}
        </VBtn>
      </div>

      <VProgressLinear
        :active="loading && !!items.length"
        indeterminate
        height="2"
        color="primary"
      />
      <VDivider />

      <!-- ── Results (single native scroll region) ───────────────────── -->
      <div class="wp-ss__body">
        <WpStateBlock
          v-if="error"
          state="error"
          :error="error"
          @retry="picker.reload"
        />

        <template v-else-if="isEmpty">
          <slot name="empty">
            <WpEmptyState
              :icon="hasCriteria ? 'tabler-search-off' : 'tabler-inbox'"
              :title="emptyTitle || t('wp.picker.no_results_title')"
              :subtitle="emptyText || t(hasCriteria ? 'wp.picker.no_results_text' : 'wp.picker.empty_text', { entity })"
            >
              <template
                v-if="hasCriteria"
                #action
              >
                <VBtn
                  variant="tonal"
                  prepend-icon="tabler-filter-off"
                  @click="picker.resetAll"
                >
                  {{ t('wp.picker.clear_all') }}
                </VBtn>
              </template>
            </WpEmptyState>
          </slot>
        </template>

        <WpSearchSelectResults
          v-else
          :items="items"
          :view="effectiveView"
          :columns="columns"
          :display="display"
          :item-key="itemKey"
          :selected-keys="selectedKeys"
          :multiple="multiple"
          :loading="loading"
          :skeleton-count="Math.min(pageCount, 8)"
          :sort-by="sortBy"
          :sort-dir="sortDir"
          :is-disabled="isDisabled"
          @toggle="onToggle"
          @sort="onSort"
        >
          <template
            v-if="$slots.item"
            #item="slotProps"
          >
            <slot
              name="item"
              v-bind="slotProps"
            />
          </template>
          <template
            v-if="$slots.card"
            #card="slotProps"
          >
            <slot
              name="card"
              v-bind="slotProps"
            />
          </template>
          <template
            v-if="$slots.trailing"
            #trailing="slotProps"
          >
            <slot
              name="trailing"
              v-bind="slotProps"
            />
          </template>
          <template
            v-for="col in columns"
            :key="col.key"
            #[cellSlot(col)]="slotProps"
          >
            <slot
              :name="cellSlot(col)"
              v-bind="slotProps"
            >
              {{ col.format ? col.format(slotProps.value, slotProps.item) : (slotProps.value ?? '—') }}
            </slot>
          </template>
        </WpSearchSelectResults>
      </div>

      <!-- ── Pagination ──────────────────────────────────────────────── -->
      <WpPagination
        v-if="loaded && !error && total > 0"
        :page="page"
        :items-per-page="perPage"
        :total-items="total"
        :page-size-options="pageSizeOptions"
        @update:page="picker.setPage"
        @update:items-per-page="picker.setPerPage"
      />

      <!-- ── Footer ──────────────────────────────────────────────────── -->
      <WpActionFooter>
        <template #start>
          <slot name="footer-start">
            <div
              v-if="multiple || selectedItems.length"
              class="d-flex align-center flex-wrap gap-x-3"
              aria-live="polite"
            >
              <span class="font-weight-medium">
                {{ t('wp.picker.selected_count', { count: selectedItems.length }) }}
              </span>
              <span
                v-if="limitReached"
                class="text-warning"
                role="alert"
              >{{ t('wp.picker.limit_reached', { max: maxSelection }) }}</span>
              <VBtn
                v-if="selectedItems.length"
                size="x-small"
                variant="text"
                @click="picker.clearSelection"
              >
                {{ t('wp.picker.clear_selection') }}
              </VBtn>
            </div>
          </slot>
        </template>

        <VBtn
          variant="tonal"
          color="secondary"
          @click="cancel"
        >
          {{ t('wp.wizard.cancel') }}
        </VBtn>
        <VBtn
          v-if="clearable && selected && (!Array.isArray(selected) || selected.length)"
          variant="tonal"
          color="error"
          prepend-icon="tabler-x"
          @click="clear"
        >
          {{ t('wp.picker.clear_selection') }}
        </VBtn>
        <VBtn
          v-if="!immediate"
          color="primary"
          prepend-icon="tabler-check"
          :disabled="!selectedItems.length"
          @click="confirm"
        >
          {{ t('wp.picker.confirm') }}
        </VBtn>
      </WpActionFooter>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-ss__card {
  block-size: min(90dvh, 780px);
}

.wp-ss__header {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 12px;
  padding-block: 14px 8px;
  padding-inline: 20px;
}

.wp-ss__toolbar {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding-block: 4px 10px;
  padding-inline: 20px;
}

.wp-ss__search {
  flex: 1 1 220px;
  min-inline-size: 0;
}

.wp-ss__tool-btn {
  min-block-size: 48px;
}

.wp-ss__views {
  block-size: 48px;
}

.wp-ss__filters {
  flex: 0 0 auto;
  margin-inline: 20px;
  margin-block-end: 8px;
  max-block-size: 40dvh;
  overflow-y: auto;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.wp-ss__chips {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding-block: 0 8px;
  padding-inline: 20px;
}

.wp-ss__body {
  flex: 1 1 auto;
  overflow-y: auto;
  min-block-size: 0;
  overscroll-behavior: contain;
  padding: 14px 20px;
  -webkit-overflow-scrolling: touch;
}

@media (max-width: 599px) {
  .wp-ss__card {
    block-size: 100dvh;
  }

  .wp-ss__header,
  .wp-ss__toolbar,
  .wp-ss__chips,
  .wp-ss__body {
    padding-inline: 12px;
  }

  .wp-ss__filters {
    margin-inline: 12px;
  }

  .wp-ss__tool-btn {
    min-inline-size: 48px;
    padding-inline: 0;
  }
}
</style>
