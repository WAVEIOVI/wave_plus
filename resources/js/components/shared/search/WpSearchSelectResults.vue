<script setup>
/**
 * WpSearchSelectResults
 * ─────────────────────────────────────────────────────────────────────────────
 * Presentation of one page of search results in `table`, `list` or `grid`
 * form. Entity-agnostic: what an item looks like is configured via `display`
 * and `columns`, or fully replaced through slots.
 *
 * display = {
 *   title:    'name' | item => string,
 *   subtitle: 'email' | item => string,
 *   avatar:   'logo_url' | item => url,          // optional image
 *   icon:     'tabler-building',                 // fallback when no image
 *   chips:    [item => ({ label, color }) | 'status'],
 *   fields:   [{ label, value: 'path' | fn, icon }]   // extra lines (grid/list)
 * }
 * columns = [{ key, title, sortable, align, width, value?: item => any }]
 *
 * Slots:  item ({ item, selected })        replaces a list row
 *         card ({ item, selected })        replaces a grid card
 *         cell-<key> ({ item, value })     replaces a table cell
 *         trailing ({ item })              extra controls at the end of a row/card
 *
 * Selection is controlled by the parent (`selectedKeys`) – rows only emit
 * `toggle`. Rows are keyboard operable (Enter / Space) and expose aria-selected.
 */
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  items: { type: Array, default: () => [] },
  view: { type: String, default: 'list', validator: v => ['table', 'list', 'grid'].includes(v) },
  columns: { type: Array, default: () => [] },
  display: { type: Object, default: () => ({}) },
  itemKey: { type: String, default: 'id' },
  selectedKeys: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  skeletonCount: { type: Number, default: 6 },
  sortBy: { type: String, default: null },
  sortDir: { type: String, default: 'asc' },
  /** item => boolean | string(reason) */
  isDisabled: { type: Function, default: () => false },
})

const emit = defineEmits(['toggle', 'sort'])

const { t } = useI18n()

const keyOf = item => item?.[props.itemKey]
const isSelected = item => props.selectedKeys.includes(keyOf(item))
const disabledOf = item => Boolean(props.isDisabled(item))
const titleOf = item => resolveAccessor(props.display.title, item) ?? ''
const subtitleOf = item => resolveAccessor(props.display.subtitle, item) ?? ''
const imageOf = item => resolveAccessor(props.display.avatar, item) || ''

const chipsOf = item => (props.display.chips || [])
  .map(spec => {
    const value = resolveAccessor(spec, item)

    if (value && typeof value === 'object') return value
    if (value) return { label: String(value), color: 'primary' }

    return null
  })
  .filter(Boolean)

const fieldsOf = item => (props.display.fields || [])
  .map(f => ({ ...f, resolved: resolveAccessor(f.value, item) }))
  .filter(f => f.resolved !== undefined && f.resolved !== null && f.resolved !== '')

const cellValue = (item, col) => resolveAccessor(col.value || col.key, item)

const selectIcon = item => {
  if (props.multiple) return isSelected(item) ? 'tabler-square-check-filled' : 'tabler-square'

  return isSelected(item) ? 'tabler-circle-check-filled' : 'tabler-circle'
}

const toggle = item => {
  if (!disabledOf(item)) emit('toggle', item)
}

const ariaSort = col => (props.sortBy === col.key ? (props.sortDir === 'asc' ? 'ascending' : 'descending') : 'none')
</script>

<template>
  <div class="wp-ss-results">
    <!-- ── Loading skeletons ─────────────────────────────────────────── -->
    <template v-if="loading && !items.length">
      <div
        v-if="view === 'grid'"
        class="wp-ss-results__grid"
        aria-busy="true"
      >
        <VSkeletonLoader
          v-for="n in skeletonCount"
          :key="n"
          type="card-avatar"
          class="wp-ss-results__skeleton"
        />
      </div>
      <div
        v-else
        aria-busy="true"
      >
        <VSkeletonLoader
          v-for="n in skeletonCount"
          :key="n"
          :type="view === 'table' ? 'table-row' : 'list-item-avatar-two-line'"
          class="bg-transparent"
        />
      </div>
    </template>

    <!-- ── Table ─────────────────────────────────────────────────────── -->
    <VTable
      v-else-if="view === 'table'"
      class="wp-ss-results__table"
      :class="{ 'is-loading': loading }"
      hover
      fixed-header
    >
      <thead>
        <tr>
          <th class="wp-ss-results__select-col">
            <span class="sr-only">{{ t('wp.picker.select') }}</span>
          </th>
          <th
            v-for="col in columns"
            :key="col.key"
            :aria-sort="col.sortable ? ariaSort(col) : undefined"
            :style="{ textAlign: col.align || 'start', inlineSize: col.width }"
          >
            <button
              v-if="col.sortable"
              type="button"
              class="wp-ss-results__sort"
              @click="emit('sort', col.key)"
            >
              {{ col.title }}
              <VIcon
                :icon="sortBy === col.key ? (sortDir === 'asc' ? 'tabler-arrow-up' : 'tabler-arrow-down') : 'tabler-arrows-sort'"
                size="14"
                :class="{ 'opacity-40': sortBy !== col.key }"
              />
            </button>
            <template v-else>
              {{ col.title }}
            </template>
          </th>
          <th
            v-if="$slots.trailing"
            class="wp-ss-results__select-col"
          />
        </tr>
      </thead>
      <tbody role="listbox">
        <tr
          v-for="item in items"
          :key="keyOf(item)"
          role="option"
          tabindex="0"
          class="wp-ss-results__row"
          :class="{ 'is-selected': isSelected(item), 'is-disabled': disabledOf(item) }"
          :aria-selected="isSelected(item)"
          :aria-disabled="disabledOf(item)"
          @click="toggle(item)"
          @keydown.enter.prevent="toggle(item)"
          @keydown.space.prevent="toggle(item)"
        >
          <td class="wp-ss-results__select-col">
            <VIcon
              :icon="selectIcon(item)"
              :color="isSelected(item) ? 'primary' : undefined"
              size="20"
            />
          </td>
          <td
            v-for="col in columns"
            :key="col.key"
            :style="{ textAlign: col.align || 'start' }"
          >
            <slot
              :name="`cell-${col.key}`"
              :item="item"
              :value="cellValue(item, col)"
            >
              {{ col.format ? col.format(cellValue(item, col), item) : (cellValue(item, col) ?? '—') }}
            </slot>
          </td>
          <td
            v-if="$slots.trailing"
            class="wp-ss-results__select-col"
            @click.stop
          >
            <slot
              name="trailing"
              :item="item"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- ── Grid ──────────────────────────────────────────────────────── -->
    <div
      v-else-if="view === 'grid'"
      class="wp-ss-results__grid"
      :class="{ 'is-loading': loading }"
      role="listbox"
      :aria-multiselectable="multiple"
    >
      <div
        v-for="item in items"
        :key="keyOf(item)"
        role="option"
        tabindex="0"
        class="wp-ss-results__card"
        :class="{ 'is-selected': isSelected(item), 'is-disabled': disabledOf(item) }"
        :aria-selected="isSelected(item)"
        :aria-disabled="disabledOf(item)"
        @click="toggle(item)"
        @keydown.enter.prevent="toggle(item)"
        @keydown.space.prevent="toggle(item)"
      >
        <VIcon
          :icon="selectIcon(item)"
          :color="isSelected(item) ? 'primary' : undefined"
          size="20"
          class="wp-ss-results__card-check"
        />
        <slot
          name="card"
          :item="item"
          :selected="isSelected(item)"
        >
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="48"
            class="mb-3"
          >
            <VImg
              v-if="imageOf(item)"
              :src="imageOf(item)"
              :alt="String(titleOf(item))"
            />
            <VIcon
              v-else-if="display.icon"
              :icon="display.icon"
            />
            <span
              v-else
              class="text-subtitle-1 font-weight-bold"
            >{{ avatarText(String(titleOf(item))) }}</span>
          </VAvatar>
          <div class="text-subtitle-2 font-weight-bold wp-ss-results__clamp">
            {{ titleOf(item) }}
          </div>
          <div
            v-if="subtitleOf(item)"
            class="text-caption text-medium-emphasis wp-ss-results__clamp"
          >
            {{ subtitleOf(item) }}
          </div>
          <div
            v-for="f in fieldsOf(item)"
            :key="f.label"
            class="text-caption mt-1 d-flex align-center gap-1"
          >
            <VIcon
              v-if="f.icon"
              :icon="f.icon"
              size="14"
              class="text-medium-emphasis"
            />
            <span class="text-truncate">{{ f.resolved }}</span>
          </div>
          <div
            v-if="chipsOf(item).length"
            class="d-flex flex-wrap gap-1 mt-3"
          >
            <VChip
              v-for="chip in chipsOf(item)"
              :key="chip.label"
              size="x-small"
              label
              variant="tonal"
              :color="chip.color"
            >
              {{ chip.label }}
            </VChip>
          </div>
        </slot>
        <div
          v-if="$slots.trailing"
          class="mt-3"
          @click.stop
        >
          <slot
            name="trailing"
            :item="item"
          />
        </div>
      </div>
    </div>

    <!-- ── List ──────────────────────────────────────────────────────── -->
    <div
      v-else
      class="wp-ss-results__list"
      :class="{ 'is-loading': loading }"
      role="listbox"
      :aria-multiselectable="multiple"
    >
      <div
        v-for="item in items"
        :key="keyOf(item)"
        role="option"
        tabindex="0"
        class="wp-ss-results__item"
        :class="{ 'is-selected': isSelected(item), 'is-disabled': disabledOf(item) }"
        :aria-selected="isSelected(item)"
        :aria-disabled="disabledOf(item)"
        @click="toggle(item)"
        @keydown.enter.prevent="toggle(item)"
        @keydown.space.prevent="toggle(item)"
      >
        <VIcon
          :icon="selectIcon(item)"
          :color="isSelected(item) ? 'primary' : undefined"
          size="22"
          class="flex-shrink-0"
        />
        <slot
          name="item"
          :item="item"
          :selected="isSelected(item)"
        >
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="40"
            class="flex-shrink-0"
          >
            <VImg
              v-if="imageOf(item)"
              :src="imageOf(item)"
              :alt="String(titleOf(item))"
            />
            <VIcon
              v-else-if="display.icon"
              :icon="display.icon"
              size="20"
            />
            <span
              v-else
              class="text-body-2 font-weight-bold"
            >{{ avatarText(String(titleOf(item))) }}</span>
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <div class="text-body-2 font-weight-bold wp-ss-results__clamp">
              {{ titleOf(item) }}
            </div>
            <div
              v-if="subtitleOf(item)"
              class="text-caption text-medium-emphasis wp-ss-results__clamp"
            >
              {{ subtitleOf(item) }}
            </div>
            <div
              v-if="fieldsOf(item).length"
              class="d-flex flex-wrap gap-x-3 text-caption mt-1"
            >
              <span
                v-for="f in fieldsOf(item)"
                :key="f.label"
                class="d-inline-flex align-center gap-1"
              >
                <VIcon
                  v-if="f.icon"
                  :icon="f.icon"
                  size="13"
                  class="text-medium-emphasis"
                />{{ f.resolved }}
              </span>
            </div>
          </div>
          <div
            v-if="chipsOf(item).length"
            class="d-none d-sm-flex flex-wrap justify-end gap-1"
          >
            <VChip
              v-for="chip in chipsOf(item)"
              :key="chip.label"
              size="x-small"
              label
              variant="tonal"
              :color="chip.color"
            >
              {{ chip.label }}
            </VChip>
          </div>
        </slot>
        <div
          v-if="$slots.trailing"
          class="flex-shrink-0"
          @click.stop
        >
          <slot
            name="trailing"
            :item="item"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.is-loading {
  opacity: 0.55;
  pointer-events: none;
  transition: opacity 0.15s ease;
}

.sr-only {
  position: absolute;
  overflow: hidden;
  border: 0;
  block-size: 1px;
  clip: rect(0, 0, 0, 0);
  inline-size: 1px;
  margin-block: -1px;
  margin-inline: -1px;
  padding: 0;
  white-space: nowrap;
}

.wp-ss-results__clamp {
  display: -webkit-box;
  overflow: hidden;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

/* List */
.wp-ss-results__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.wp-ss-results__item,
.wp-ss-results__card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  cursor: pointer;
  outline: none;
  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.wp-ss-results__item {
  display: flex;
  align-items: center;
  gap: 12px;
  min-block-size: 56px;
  padding-block: 8px;
  padding-inline: 12px;
}

.wp-ss-results__item:hover,
.wp-ss-results__card:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.wp-ss-results__item:focus-visible,
.wp-ss-results__card:focus-visible,
.wp-ss-results__row:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.is-selected.wp-ss-results__item,
.is-selected.wp-ss-results__card {
  background: rgba(var(--v-theme-primary), 0.08);
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary)) inset;
}

.is-disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

/* Grid */
.wp-ss-results__grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
}

.wp-ss-results__card {
  position: relative;
  padding: 14px;
}

.wp-ss-results__card-check {
  position: absolute;
  inset-block-start: 10px;
  inset-inline-end: 10px;
}

.wp-ss-results__skeleton {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
}

/* Table */
.wp-ss-results__select-col {
  inline-size: 44px;
  text-align: center;
}

.wp-ss-results__row {
  cursor: pointer;
  outline: none;
}

.wp-ss-results__row.is-selected {
  background: rgba(var(--v-theme-primary), 0.08);
}

.wp-ss-results__sort {
  display: inline-flex;
  align-items: center;
  color: inherit;
  cursor: pointer;
  font: inherit;
  gap: 4px;
  text-transform: inherit;
}

@media (max-width: 599px) {
  .wp-ss-results__grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
  }
}
</style>
