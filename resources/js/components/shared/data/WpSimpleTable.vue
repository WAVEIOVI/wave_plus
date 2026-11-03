<script setup>
/**
 * WpSimpleTable
 * ─────────────────────────────────────────────────────────────────────────────
 * Mobile-first table used inside cards. Renders a regular VTable (with
 * horizontal scroll safety) on wide screens and a stacked list of "record
 * cards" (label : value rows) below `breakpoint` px — from 320px up to 4k.
 *
 *   <WpSimpleTable :columns="cols" :items="rows">
 *     <template #cell.status="{ item }"><VChip>{{ item.status }}</VChip></template>
 *   </WpSimpleTable>
 *
 * columns: [{ key, title, align?: 'start'|'center'|'end', mobile?: 'title'|'hide' }]
 *   • mobile: 'title' → rendered as the card heading (default: first column)
 *   • mobile: 'hide'  → omitted from the stacked card
 * Slots   : `cell.<key>` ({ item, value }) · `empty`
 */
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  columns: { type: Array, required: true },
  items: { type: Array, default: () => [] },
  itemKey: { type: String, default: 'id' },
  /** Viewport width (px) below which rows are stacked as cards. */
  breakpoint: { type: Number, default: 640 },
})

const { width } = useDisplay()
const stacked = computed(() => width.value < props.breakpoint)

const titleColumn = computed(() =>
  props.columns.find(c => c.mobile === 'title') || props.columns[0])

const bodyColumns = computed(() =>
  props.columns.filter(c => c.key !== titleColumn.value?.key && c.mobile !== 'hide'))

const rowKey = (item, index) => item?.[props.itemKey] ?? index
const cellValue = (item, key) => item?.[key]
const alignClass = col => (col.align ? `text-${col.align}` : '')
</script>

<template>
  <div class="wp-simple-table">
    <!-- Desktop / tablet -->
    <VTable
      v-if="!stacked && items.length"
      class="text-no-wrap"
      density="comfortable"
    >
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :class="alignClass(col)"
          >
            {{ col.title }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(item, index) in items"
          :key="rowKey(item, index)"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            :class="alignClass(col)"
          >
            <slot
              :name="`cell.${col.key}`"
              :item="item"
              :value="cellValue(item, col.key)"
            >
              {{ cellValue(item, col.key) ?? '—' }}
            </slot>
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Mobile cards -->
    <div
      v-else-if="stacked && items.length"
      class="wp-simple-table__cards"
    >
      <div
        v-for="(item, index) in items"
        :key="rowKey(item, index)"
        class="wp-simple-table__card"
      >
        <div class="wp-simple-table__card-title">
          <slot
            :name="`cell.${titleColumn.key}`"
            :item="item"
            :value="cellValue(item, titleColumn.key)"
          >
            {{ cellValue(item, titleColumn.key) ?? '—' }}
          </slot>
        </div>

        <div
          v-for="col in bodyColumns"
          :key="col.key"
          class="wp-simple-table__row"
        >
          <span class="wp-simple-table__label">{{ col.title }}</span>
          <span class="wp-simple-table__value">
            <slot
              :name="`cell.${col.key}`"
              :item="item"
              :value="cellValue(item, col.key)"
            >
              {{ cellValue(item, col.key) ?? '—' }}
            </slot>
          </span>
        </div>
      </div>
    </div>

    <slot
      v-else
      name="empty"
    />
  </div>
</template>

<style scoped>
.wp-simple-table {
  min-inline-size: 0;
}

.wp-simple-table :deep(.v-table__wrapper) {
  overflow-x: auto;
}

.wp-simple-table__cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.wp-simple-table__card {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.wp-simple-table__card-title {
  margin-block-end: 6px;
  font-size: 0.9375rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.wp-simple-table__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding-block: 4px;
  border-block-start: 1px dashed rgba(var(--v-border-color), 0.18);
  gap: 12px;
}

.wp-simple-table__label {
  flex: 0 0 auto;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}

.wp-simple-table__value {
  min-inline-size: 0;
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
  text-align: end;
}
</style>
