<script setup>
/**
 * WpDocumentLinesCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Presentation and editing pattern for document line items in Purchase Orders,
 * Sales Orders, supplier/customer invoices, quotations, etc.
 *
 * Keeps calculation and financial domain logic in the parent/store.
 *
 * Requirements:
 * - Configurable columns: item identity, description, quantity, unit, price, discount, tax, line total
 * - Document totals summary block (subtotal, discounts, tax, grand total)
 * - Add, edit, remove, view line actions
 * - Configurable slots for custom business fields
 * - Validation errors per line / per field
 * - Mobile-first: table on desktop, cards on mobile
 * - Loading and empty states
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { useCurrency } from '@/composables/useCurrency'

const props = defineProps({
  lines: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  title: { type: String, default: '' },
  currency: { type: String, default: 'TND' },
  canAdd: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
  canRemove: { type: Boolean, default: true },
  canView: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  lineKey: { type: String, default: 'id' },
  addLabel: { type: String, default: '' },
})

const emit = defineEmits([
  'add-line',
  'edit-line',
  'remove-line',
  'view-line',
  'update-line',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { format, formatCurrency } = useCurrency()

// Default columns if caller doesn't override
const defaultColumns = [
  { key: 'item', title: 'Item / Product', align: 'start' },
  { key: 'quantity', title: 'Qty', align: 'end', width: '90px' },
  { key: 'unit_price', title: 'Unit Price', align: 'end', width: '120px' },
  { key: 'discount', title: 'Discount', align: 'end', width: '100px' },
  { key: 'tax_rate', title: 'Tax', align: 'end', width: '90px' },
  { key: 'total', title: 'Line Total', align: 'end', width: '130px' },
]

const resolvedColumns = computed(() => {
  return props.columns && props.columns.length ? props.columns : defaultColumns
})

const formatMoney = val => {
  if (val === null || val === undefined || val === '') return '—'
  const num = Number(val)
  if (isNaN(num)) return String(val)
  const fn = formatCurrency || format
  if (typeof fn === 'function') {
    return fn(num, props.currency)
  }
  return `${num.toFixed(2)} ${props.currency || ''}`.trim()
}

const keyOf = (line, index) => {
  return line?.[props.lineKey] ?? index
}

const getLineError = (line, index, fieldKey) => {
  if (!props.errors) return null
  const k = keyOf(line, index)
  // Check index-based or id-based dotted error keys: lines.0.quantity or lines[0].quantity
  const direct = props.errors[`lines.${index}.${fieldKey}`] || props.errors[`lines.${k}.${fieldKey}`]
  if (direct) return direct
  if (fieldKey) return null
  return props.errors[`lines.${index}`] || props.errors[`lines.${k}`]
}
</script>

<template>
  <VCard
    class="wp-doc-lines-card"
    variant="flat"
    border
    rounded="lg"
  >
    <VCardText class="pb-2">
      <WpSectionHeader
        :title="title || t('wp.cards.doc_lines.title')"
        icon="tabler-list-details"
        :count="lines.length"
        :level="3"
      >
        <template #actions>
          <slot name="actions" />
          <VBtn
            v-if="canAdd && !readonly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="emit('add-line')"
          >
            {{ addLabel || t('wp.cards.doc_lines.add_line') }}
          </VBtn>
        </template>
      </WpSectionHeader>
    </VCardText>

    <VCardText class="pt-0">
      <WpStateBlock
        :state="loading ? 'loading' : lines.length === 0 ? 'empty' : 'ready'"
        skeleton="table-row-divider, table-row-divider, table-row-divider"
        :title="t('wp.cards.doc_lines.empty_title')"
        :text="t('wp.cards.doc_lines.empty_text')"
        compact
      >
        <!-- Desktop Table Presentation -->
        <div
          v-if="!smAndDown"
          class="wp-doc-lines-card__table-wrapper"
        >
          <VTable
            hover
            class="wp-doc-lines-card__table"
          >
            <thead>
              <tr>
                <th
                  style="inline-size: 40px;"
                  class="text-center text-medium-emphasis"
                >
                  #
                </th>
                <th
                  v-for="col in resolvedColumns"
                  :key="col.key"
                  :style="{ textAlign: col.align || 'start', inlineSize: col.width }"
                >
                  {{ col.title }}
                </th>
                <th
                  v-if="(canEdit || canRemove || canView) && !readonly"
                  style="inline-size: 90px;"
                  class="text-end"
                >
                  {{ t('wp.cards.doc_lines.actions') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in lines"
                :key="keyOf(line, idx)"
                :class="{ 'has-error': getLineError(line, idx) }"
              >
                <td class="text-center text-medium-emphasis text-caption">
                  {{ idx + 1 }}
                </td>

                <td
                  v-for="col in resolvedColumns"
                  :key="col.key"
                  :style="{ textAlign: col.align || 'start' }"
                >
                  <slot
                    :name="`cell-${col.key}`"
                    :line="line"
                    :index="idx"
                    :value="resolveAccessor(col.value || col.key, line)"
                  >
                    <!-- Default Cell Renderers -->
                    <template v-if="col.key === 'item'">
                      <div class="d-flex align-center gap-2">
                        <VAvatar
                          v-if="line.image_url"
                          size="32"
                          rounded
                          color="secondary"
                          variant="tonal"
                        >
                          <VImg :src="line.image_url" />
                        </VAvatar>
                        <div class="min-w-0">
                          <div class="text-body-2 font-weight-bold text-truncate">
                            {{ line.item_name || line.name || line.sku || '—' }}
                          </div>
                          <div
                            v-if="line.description"
                            class="text-caption text-medium-emphasis text-truncate"
                          >
                            {{ line.description }}
                          </div>
                        </div>
                      </div>
                    </template>

                    <template v-else-if="col.key === 'quantity'">
                      <span class="font-weight-medium">{{ line.quantity ?? line.qty ?? 1 }}</span>
                      <span
                        v-if="line.unit"
                        class="text-caption text-disabled ms-1"
                      >{{ line.unit }}</span>
                    </template>

                    <template v-else-if="col.key === 'unit_price' || col.key === 'price'">
                      <span>{{ formatMoney(line.unit_price ?? line.price) }}</span>
                    </template>

                    <template v-else-if="col.key === 'discount'">
                      <span v-if="line.discount">{{ line.discount }}%</span>
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </template>

                    <template v-else-if="col.key === 'tax_rate' || col.key === 'tax'">
                      <span v-if="line.tax_rate">{{ line.tax_rate }}%</span>
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </template>

                    <template v-else-if="col.key === 'total' || col.key === 'line_total'">
                      <span class="font-weight-bold text-high-emphasis">
                        {{ formatMoney(line.line_total ?? line.total) }}
                      </span>
                    </template>

                    <template v-else>
                      {{ resolveAccessor(col.value || col.key, line) ?? '—' }}
                    </template>
                  </slot>

                  <div
                    v-if="getLineError(line, idx, col.key)"
                    class="text-caption text-error"
                  >
                    {{ getLineError(line, idx, col.key) }}
                  </div>
                </td>

                <!-- Row Actions -->
                <td
                  v-if="(canEdit || canRemove || canView) && !readonly"
                  class="text-end text-no-wrap"
                >
                  <VBtn
                    v-if="canView"
                    icon
                    size="x-small"
                    variant="text"
                    @click="emit('view-line', line, idx)"
                  >
                    <VIcon
                      icon="tabler-eye"
                      size="16"
                    />
                  </VBtn>

                  <VBtn
                    v-if="canEdit && !readonly"
                    icon
                    size="x-small"
                    variant="text"
                    color="primary"
                    @click="emit('edit-line', line, idx)"
                  >
                    <VIcon
                      icon="tabler-edit"
                      size="16"
                    />
                  </VBtn>

                  <VBtn
                    v-if="canRemove && !readonly"
                    icon
                    size="x-small"
                    variant="text"
                    color="error"
                    @click="emit('remove-line', line, idx)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      size="16"
                    />
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <!-- Mobile Card List Presentation -->
        <div
          v-else
          class="wp-doc-lines-card__mobile-list"
        >
          <div
            v-for="(line, idx) in lines"
            :key="keyOf(line, idx)"
            class="wp-doc-lines-card__mobile-item"
            :class="{ 'has-error': getLineError(line, idx) }"
          >
            <div class="d-flex align-start justify-space-between gap-2">
              <div class="d-flex align-center gap-2 min-w-0">
                <VAvatar
                  size="36"
                  rounded
                  color="secondary"
                  variant="tonal"
                >
                  <VImg
                    v-if="line.image_url"
                    :src="line.image_url"
                  />
                  <VIcon
                    v-else
                    icon="tabler-package"
                    size="18"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-body-2 font-weight-bold text-truncate">
                    {{ line.item_name || line.name || line.sku || t('wp.cards.doc_lines.unnamed_item') }}
                  </div>
                  <div
                    v-if="line.description"
                    class="text-caption text-medium-emphasis text-truncate"
                  >
                    {{ line.description }}
                  </div>
                </div>
              </div>

              <div class="text-end flex-shrink-0">
                <div class="text-body-2 font-weight-bold text-primary">
                  {{ formatMoney(line.line_total ?? line.total) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ line.quantity ?? line.qty ?? 1 }} {{ line.unit || 'unit' }} × {{ formatMoney(line.unit_price ?? line.price) }}
                </div>
              </div>
            </div>

            <!-- Additional columns details on mobile -->
            <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-t">
              <VChip
                v-if="line.discount"
                size="x-small"
                variant="tonal"
                color="info"
                label
              >
                {{ t('wp.cards.doc_lines.discount') }}: {{ line.discount }}%
              </VChip>
              <VChip
                v-if="line.tax_rate"
                size="x-small"
                variant="tonal"
                color="secondary"
                label
              >
                {{ t('wp.cards.doc_lines.tax') }}: {{ line.tax_rate }}%
              </VChip>

              <VSpacer />

              <div
                v-if="!readonly"
                class="d-flex gap-1"
              >
                <VBtn
                  v-if="canEdit"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  @click="emit('edit-line', line, idx)"
                >
                  {{ t('wp.cards.edit') }}
                </VBtn>
                <VBtn
                  v-if="canRemove"
                  size="x-small"
                  variant="tonal"
                  color="error"
                  icon="tabler-trash"
                  @click="emit('remove-line', line, idx)"
                />
              </div>
            </div>

            <div
              v-if="getLineError(line, idx)"
              class="text-caption text-error mt-1"
            >
              {{ getLineError(line, idx) }}
            </div>
          </div>
        </div>

        <!-- Document Totals Summary Block -->
        <div
          v-if="totals && Object.keys(totals).length && lines.length"
          class="wp-doc-lines-card__totals-wrapper mt-4 d-flex justify-end"
        >
          <div class="wp-doc-lines-card__totals">
            <div
              v-if="totals.subtotal !== undefined"
              class="d-flex justify-space-between py-1 text-body-2"
            >
              <span class="text-medium-emphasis">{{ t('wp.cards.doc_lines.subtotal') }}:</span>
              <span class="font-weight-medium">{{ formatMoney(totals.subtotal) }}</span>
            </div>

            <div
              v-if="totals.discount_total !== undefined && Number(totals.discount_total) > 0"
              class="d-flex justify-space-between py-1 text-body-2 text-info"
            >
              <span>{{ t('wp.cards.doc_lines.discount_total') }}:</span>
              <span class="font-weight-medium">- {{ formatMoney(totals.discount_total) }}</span>
            </div>

            <div
              v-if="totals.tax_total !== undefined"
              class="d-flex justify-space-between py-1 text-body-2"
            >
              <span class="text-medium-emphasis">{{ t('wp.cards.doc_lines.tax_total') }}:</span>
              <span class="font-weight-medium">{{ formatMoney(totals.tax_total) }}</span>
            </div>

            <VDivider class="my-2" />

            <div class="d-flex justify-space-between py-1 text-subtitle-1 font-weight-bold">
              <span>{{ t('wp.cards.doc_lines.grand_total') }}:</span>
              <span class="text-primary">{{ formatMoney(totals.grand_total ?? totals.total) }}</span>
            </div>
          </div>
        </div>

        <template #empty-action>
          <VBtn
            v-if="canAdd && !readonly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="emit('add-line')"
          >
            {{ addLabel || t('wp.cards.doc_lines.add_line') }}
          </VBtn>
        </template>
      </WpStateBlock>
    </VCardText>
  </VCard>
</template>

<style scoped>
.wp-doc-lines-card__table-wrapper {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

.wp-doc-lines-card__mobile-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.wp-doc-lines-card__mobile-item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 10px;
  background: rgba(var(--v-theme-surface), 0.6);
}

.wp-doc-lines-card__mobile-item.has-error,
.wp-doc-lines-card__table tr.has-error td {
  background: rgba(var(--v-theme-error), 0.05);
  border-color: rgb(var(--v-theme-error));
}

.wp-doc-lines-card__totals {
  inline-size: 100%;
  max-inline-size: 320px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.4);
}
</style>
