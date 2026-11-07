<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */

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
 *
 * Interactive editing (all opt-in, existing consumers are unaffected):
 * - `v-model:lines`  : emitted for inline edits, steppers, dialog saves, bulk add,
 *                      scan and (when listened to) removals.
 * - `inlineEdit`     : desktop inline qty / price / discount / tax inputs. Defaults
 *                      to on only when a `update:lines` listener is present.
 * - `editDialog`     : add / edit through WpDocumentLineEditDialog (tap a card on
 *                      mobile). Defaults to on only when a `update:lines` listener
 *                      is present; otherwise `add-line` / `edit-line` are emitted.
 * - `fields`         : which fields exist (see documentLineUtils.resolveLineFields);
 *                      also derives default `columns`. `fieldLabels` overrides labels.
 * - `showCatalogPicker` + `fetchCatalogItems(params) → { items, total }`
 *                    : "Bulk add from catalog" through WpCatalogItemPickerDialog.
 * - `showScan`       : "Scan QR / barcode" through WpQrScannerDialog. The scanned
 *                      code is resolved with `fetchCatalogItems` when provided.
 * - `mapCatalogItem(item, quantity) → line` customises new lines.
 *
 * Extra events: `update:lines`, `update-line(line, index)`, `save-line(line, index)`,
 * `bulk-add([{ item, quantity }])`, `scan(code)`, `scan-not-found(code)`,
 * `select-item(index)`. Handle either `update:lines` or the granular events, not both.
 */
import { computed, getCurrentInstance, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { useCurrency } from '@/composables/useCurrency'
import { localizedValue } from '@/composables/useLocaleField'
import { patchLine, resolveLineFields } from '@/components/shared/documents/documentLineUtils'

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
  fields: { type: [Object, Array], default: undefined },
  fieldLabels: { type: Object, default: () => ({}) },
  units: { type: Array, default: () => [] },
  inlineEdit: { type: Boolean, default: undefined },
  editDialog: { type: Boolean, default: undefined },
  showCatalogPicker: { type: Boolean, default: false },
  fetchCatalogItems: { type: Function, default: null },
  catalogCategories: { type: Array, default: () => [] },
  showScan: { type: Boolean, default: false },
  mapCatalogItem: { type: Function, default: null },
})

const emit = defineEmits([
  'add-line',
  'edit-line',
  'remove-line',
  'view-line',
  'update-line',
  'update:lines',
  'save-line',
  'bulk-add',
  'scan',
  'scan-not-found',
  'select-item',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { format, formatCurrency } = useCurrency()
const instance = getCurrentInstance()

const hasLinesListener = computed(() => Boolean(instance?.vnode?.props?.['onUpdate:lines']))
const inlineEnabled = computed(() => !props.readonly && props.canEdit && (props.inlineEdit ?? hasLinesListener.value))
const dialogEnabled = computed(() => !props.readonly && props.canEdit && (props.editDialog ?? hasLinesListener.value))
const fieldCfg = computed(() => resolveLineFields(props.fields, props.fieldLabels))
const showField = key => fieldCfg.value[key].visible
const labelFor = (key, fallback) => fieldCfg.value[key].label || fallback

const pickerOpen = ref(false)
const scanOpen = ref(false)
const scanMessage = ref('')
const dialogOpen = ref(false)
const dialogIndex = ref(null)
const dialogLine = ref({})

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
  if (props.columns && props.columns.length) return props.columns
  if (!props.fields) return defaultColumns

  // Derive the default columns from the visible fields.
  return defaultColumns
    .filter(col => {
      if (col.key === 'item') return showField('item') || showField('description')
      if (col.key === 'total') return showField('unit_price')

      return showField(col.key)
    })
    .map(col => (col.key === 'quantity' ? { ...col, title: labelFor('quantity', col.title) } : col))
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

  if (direct) return Array.isArray(direct) ? direct[0] : direct
  if (fieldKey) return null

  const own = props.errors[`lines.${index}`] || props.errors[`lines.${k}`]

  if (own) return Array.isArray(own) ? own[0] : own

  // Any field-level error on the line flags the row.
  const prefix = `lines.${index}.`
  const hit = Object.keys(props.errors).find(key => key.startsWith(prefix))

  return hit ? (Array.isArray(props.errors[hit]) ? props.errors[hit][0] : props.errors[hit]) : null
}

const itemLabel = line => localizedValue(line.item_name || line.name, line.sku || '')

const qtyKeyOf = line => (!('quantity' in line) && 'qty' in line ? 'qty' : 'quantity')
const qtyOf = line => Number(line.quantity ?? line.qty ?? 0) || 0

// ── Mutations (emit-only; the parent owns the data) ─────────────────────────
const emitLines = next => emit('update:lines', next)

const applyPatch = (line, index, patch) => {
  const next = patchLine(line, patch)

  emit('update-line', next, index)
  emitLines(props.lines.map((l, i) => (i === index ? next : l)))
}

const onInlineInput = (line, index, key, value) => {
  if (value === '' || value === null) return

  const n = Number(value)

  if (!Number.isFinite(n) || n < 0) return
  applyPatch(line, index, { [key === 'quantity' ? qtyKeyOf(line) : key]: n })
}

const stepQty = (line, index, delta) => {
  const next = Math.max(0, Math.round((qtyOf(line) + delta) * 1000) / 1000)

  applyPatch(line, index, { [qtyKeyOf(line)]: next })
}

const removeLine = (line, index) => {
  emit('remove-line', line, index)
  if (hasLinesListener.value) emitLines(props.lines.filter((_, i) => i !== index))
}

const onAdd = () => {
  if (dialogEnabled.value) {
    dialogIndex.value = null
    dialogLine.value = { quantity: 1 }
    dialogOpen.value = true

    return
  }
  emit('add-line')
}

const onEdit = (line, index) => {
  if (dialogEnabled.value) {
    dialogIndex.value = index
    dialogLine.value = line
    dialogOpen.value = true

    return
  }
  emit('edit-line', line, index)
}

const onDialogSave = next => {
  const index = dialogIndex.value

  emit('save-line', next, index)
  if (index === null) emitLines([...props.lines, next])
  else {
    emit('update-line', next, index)
    emitLines(props.lines.map((l, i) => (i === index ? next : l)))
  }
  dialogOpen.value = false
}

// ── Catalog (bulk add / scan) ───────────────────────────────────────────────
const catalogIdOf = item => item?.id ?? item?.catalog_item_id

const buildLine = (item, quantity) => {
  if (props.mapCatalogItem) return props.mapCatalogItem(item, quantity)

  return patchLine({
    catalog_item_id: catalogIdOf(item),
    item_name: localizedValue(item.name ?? item.item_name, item.sku || ''),
    sku: item.sku,
    image_url: item.image_url,
    unit: item.unit ?? '',
    description: '',
    quantity,
    unit_price: Number(item.unit_price ?? item.price ?? 0) || 0,
    discount: 0,
    tax_rate: Number(item.tax_rate ?? 0) || 0,
  }, {})
}

const mergeEntries = entries => {
  const next = [...props.lines]

  entries.forEach(({ item, quantity }) => {
    const id = catalogIdOf(item)
    const at = id === undefined ? -1 : next.findIndex(l => l.catalog_item_id === id)

    if (at >= 0) next[at] = patchLine(next[at], { [qtyKeyOf(next[at])]: qtyOf(next[at]) + quantity })
    else next.push(buildLine(item, quantity))
  })

  return next
}

const onBulkConfirm = entries => {
  if (!entries?.length) return
  emit('bulk-add', entries)
  emitLines(mergeEntries(entries))
}

const onScanned = async result => {
  const code = String(result?.value ?? result ?? '').trim()

  if (!code) return
  scanOpen.value = false
  scanMessage.value = ''
  emit('scan', code)
  if (!props.fetchCatalogItems) return

  try {
    const found = await props.fetchCatalogItems({ q: code, barcode: code, page: 1, per_page: 5 })
    const items = Array.isArray(found?.items) ? found.items : []
    const lower = code.toLowerCase()
    const exact = items.find(i => [i.barcode, i.sku, i.code].some(v => v && String(v).toLowerCase() === lower))
    const match = exact || (items.length === 1 ? items[0] : null)

    if (!match) {
      emit('scan-not-found', code)
      scanMessage.value = t('wp.docs.lines.scan_not_found', { code })

      return
    }
    onBulkConfirm([{ item: match, quantity: 1 }])
  }
  catch {
    scanMessage.value = t('wp.docs.picker.scan_failed')
  }
}

const canAct = computed(() => !props.readonly)
const hasRowActions = computed(() => (props.canEdit || props.canRemove || props.canView) && !props.readonly)
const pickerAvailable = computed(() => props.showCatalogPicker && props.fetchCatalogItems && canAct.value && props.canAdd)
const scanAvailable = computed(() => props.showScan && canAct.value && props.canAdd)
const touchHeight = computed(() => (smAndDown.value ? 44 : undefined))
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
            v-if="pickerAvailable"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-packages"
            :min-height="touchHeight"
            @click="pickerOpen = true"
          >
            {{ t('wp.docs.lines.bulk_add') }}
          </VBtn>
          <VBtn
            v-if="scanAvailable"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-barcode"
            :min-height="touchHeight"
            @click="scanOpen = true"
          >
            {{ t('wp.docs.lines.scan') }}
          </VBtn>
          <VBtn
            v-if="canAdd && !readonly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            :min-height="touchHeight"
            @click="onAdd"
          >
            {{ addLabel || t('wp.cards.doc_lines.add_line') }}
          </VBtn>
        </template>
      </WpSectionHeader>

      <VAlert
        v-if="scanMessage"
        type="info"
        variant="tonal"
        density="compact"
        closable
        class="mt-3"
        @click:close="scanMessage = ''"
      >
        {{ scanMessage }}
      </VAlert>
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
                  v-if="hasRowActions"
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
                            {{ itemLabel(line) || '—' }}
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
                      <VTextField
                        v-if="inlineEnabled && !fieldCfg.quantity.readonly"
                        :model-value="line.quantity ?? line.qty ?? 0"
                        type="number"
                        min="0"
                        step="any"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="wp-doc-lines-card__inline"
                        :aria-label="labelFor('quantity', t('wp.docs.lines.quantity'))"
                        :error="Boolean(getLineError(line, idx, 'quantity'))"
                        @update:model-value="onInlineInput(line, idx, 'quantity', $event)"
                      />
                      <template v-else>
                        <span class="font-weight-medium">{{ line.quantity ?? line.qty ?? 1 }}</span>
                        <span
                          v-if="line.unit"
                          class="text-caption text-disabled ms-1"
                        >{{ line.unit }}</span>
                      </template>
                    </template>

                    <template v-else-if="col.key === 'unit_price' || col.key === 'price'">
                      <VTextField
                        v-if="inlineEnabled && !fieldCfg.unit_price.readonly"
                        :model-value="line.unit_price ?? line.price ?? 0"
                        type="number"
                        min="0"
                        step="any"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="wp-doc-lines-card__inline wp-doc-lines-card__inline--wide"
                        :aria-label="labelFor('unit_price', t('wp.docs.lines.unit_price'))"
                        :error="Boolean(getLineError(line, idx, 'unit_price'))"
                        @update:model-value="onInlineInput(line, idx, 'unit_price', $event)"
                      />
                      <span v-else>{{ formatMoney(line.unit_price ?? line.price) }}</span>
                    </template>

                    <template v-else-if="col.key === 'discount'">
                      <VTextField
                        v-if="inlineEnabled && !fieldCfg.discount.readonly"
                        :model-value="line.discount ?? 0"
                        type="number"
                        min="0"
                        max="100"
                        step="any"
                        suffix="%"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="wp-doc-lines-card__inline"
                        :aria-label="labelFor('discount', t('wp.docs.lines.discount'))"
                        @update:model-value="onInlineInput(line, idx, 'discount', $event)"
                      />
                      <template v-else>
                        <span v-if="line.discount">{{ line.discount }}%</span>
                        <span
                          v-else
                          class="text-disabled"
                        >—</span>
                      </template>
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
                  v-if="hasRowActions"
                  class="text-end text-no-wrap"
                >
                  <VBtn
                    v-if="canView"
                    icon
                    size="x-small"
                    variant="text"
                    :aria-label="t('wp.cards.view')"
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
                    :aria-label="t('wp.cards.edit')"
                    @click="onEdit(line, idx)"
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
                    :aria-label="t('wp.docs.lines.remove')"
                    @click="removeLine(line, idx)"
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
            <div
              class="d-flex align-start justify-space-between gap-2"
              :class="{ 'wp-doc-lines-card__tap': dialogEnabled }"
              :role="dialogEnabled ? 'button' : undefined"
              :tabindex="dialogEnabled ? 0 : undefined"
              @click="dialogEnabled && onEdit(line, idx)"
              @keydown.enter="dialogEnabled && onEdit(line, idx)"
            >
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
                    {{ itemLabel(line) || t('wp.cards.doc_lines.unnamed_item') }}
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
                <div
                  v-if="showField('unit_price')"
                  class="text-body-2 font-weight-bold text-primary"
                >
                  {{ formatMoney(line.line_total ?? line.total) }}
                </div>
                <div
                  v-if="showField('unit_price')"
                  class="text-caption text-medium-emphasis"
                >
                  {{ line.quantity ?? line.qty ?? 1 }} {{ line.unit || t('wp.docs.lines.unit_fallback') }} × {{ formatMoney(line.unit_price ?? line.price) }}
                </div>
                <div
                  v-else
                  class="text-caption text-medium-emphasis"
                >
                  {{ line.quantity ?? line.qty ?? 1 }} {{ line.unit || t('wp.docs.lines.unit_fallback') }}
                </div>
              </div>
            </div>

            <!-- Quantity stepper -->
            <div
              v-if="inlineEnabled && showField('quantity') && !fieldCfg.quantity.readonly"
              class="wp-doc-lines-card__stepper mt-2"
            >
              <VBtn
                icon
                size="44"
                variant="tonal"
                :disabled="qtyOf(line) <= 0"
                :aria-label="t('wp.docs.lines.decrease')"
                @click="stepQty(line, idx, -1)"
              >
                <VIcon icon="tabler-minus" />
              </VBtn>
              <span
                class="wp-doc-lines-card__stepper-value text-body-1 font-weight-bold"
                aria-live="polite"
              >{{ qtyOf(line) }}</span>
              <VBtn
                icon
                size="44"
                variant="tonal"
                :aria-label="t('wp.docs.lines.increase')"
                @click="stepQty(line, idx, 1)"
              >
                <VIcon icon="tabler-plus" />
              </VBtn>
            </div>

            <!-- Additional columns details on mobile -->
            <div class="d-flex flex-wrap align-center gap-2 mt-2 pt-2 border-t">
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
                  size="small"
                  variant="tonal"
                  color="primary"
                  min-height="36"
                  @click="onEdit(line, idx)"
                >
                  {{ t('wp.cards.edit') }}
                </VBtn>
                <VBtn
                  v-if="canRemove"
                  size="small"
                  variant="tonal"
                  color="error"
                  icon="tabler-trash"
                  :aria-label="t('wp.docs.lines.remove')"
                  @click="removeLine(line, idx)"
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
            :min-height="touchHeight"
            @click="onAdd"
          >
            {{ addLabel || t('wp.cards.doc_lines.add_line') }}
          </VBtn>
        </template>
      </WpStateBlock>
    </VCardText>

    <WpDocumentLineEditDialog
      v-if="dialogEnabled"
      v-model="dialogOpen"
      :line="dialogLine"
      :index="dialogIndex"
      :is-new="dialogIndex === null"
      :fields="fields"
      :labels="fieldLabels"
      :errors="errors"
      :units="units"
      :currency="currency"
      @save="onDialogSave"
      @select-item="emit('select-item', dialogIndex)"
    />

    <WpCatalogItemPickerDialog
      v-if="pickerAvailable"
      v-model="pickerOpen"
      :fetch-items="fetchCatalogItems"
      :categories="catalogCategories"
      :title="t('wp.docs.lines.picker_title')"
      @confirm="onBulkConfirm"
    />

    <WpQrScannerDialog
      v-if="scanAvailable"
      v-model="scanOpen"
      :title="t('wp.docs.picker.scan_title')"
      :subtitle="t('wp.docs.picker.scan_subtitle')"
      close-on-scan
      @scanned="onScanned"
    />
  </VCard>
</template>

<style scoped>
.wp-doc-lines-card__table-wrapper {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

.wp-doc-lines-card__inline {
  min-inline-size: 80px;
  max-inline-size: 110px;
  margin-inline-start: auto;
}

.wp-doc-lines-card__inline--wide {
  max-inline-size: 140px;
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

.wp-doc-lines-card__tap {
  cursor: pointer;
}

.wp-doc-lines-card__stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
}

.wp-doc-lines-card__stepper-value {
  min-inline-size: 48px;
  text-align: center;
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
