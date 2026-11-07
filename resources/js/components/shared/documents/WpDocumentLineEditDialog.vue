<script setup>
/* eslint-disable camelcase, lines-around-comment, vue/custom-event-name-casing */
/**
 * WpDocumentLineEditDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Add / edit one document line, built on WpFormDialog.
 *
 *   line      initial line (copied; never mutated)
 *   fields    which inputs are shown — array of keys or an object whose values
 *             are booleans or { visible, label, required, readonly }
 *             keys: item description quantity unit unit_price discount
 *                   tax_rate required_date notes
 *   labels    per-field label overrides  { quantity: 'Qty received' }
 *   errors    error map, keyed `field` or `lines.{index}.{field}` (needs `index`)
 *   units     optional [{ title, value }] — renders the unit as a select
 *
 * Emits  save(line)  (with a recomputed `line_total`), update:modelValue,
 *        select-item  (the parent opens its catalog picker).
 * Presentational only: no API calls.
 */
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { computeLineTotal, patchLine, resolveLineFields } from './documentLineUtils'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  line: { type: Object, default: () => ({}) },
  index: { type: Number, default: null },
  isNew: { type: Boolean, default: false },
  fields: { type: [Object, Array], default: undefined },
  labels: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  units: { type: Array, default: () => [] },
  currency: { type: String, default: '' },
  title: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'select-item'])

const { t } = useI18n()
const { format } = useCurrency()

const form = reactive({})

const cfg = computed(() => resolveLineFields(props.fields, props.labels))

const DEFAULT_LABELS = {
  item: 'item',
  description: 'description',
  quantity: 'quantity',
  unit: 'unit',
  unit_price: 'unit_price',
  discount: 'discount',
  tax_rate: 'tax_rate',
  required_date: 'required_date',
  notes: 'notes',
}

const labelOf = key => cfg.value[key].label || t(`wp.docs.line.${DEFAULT_LABELS[key]}`)
const show = key => cfg.value[key].visible

const rulesFor = key => {
  const rules = []

  if (cfg.value[key].required) rules.push(v => (v !== null && v !== undefined && v !== '') || t('wp.docs.line.required'))
  if (['quantity', 'unit_price', 'discount', 'tax_rate'].includes(key)) {
    rules.push(v => v === '' || v === null || v === undefined || Number(v) >= 0 || t('wp.docs.line.non_negative'))
  }
  if (key === 'quantity') rules.push(v => Number(v) > 0 || t('wp.docs.line.quantity_positive'))
  if (['discount', 'tax_rate'].includes(key)) {
    rules.push(v => v === '' || v === null || v === undefined || Number(v) <= 100 || t('wp.docs.line.max_percent'))
  }

  return rules
}

const errorOf = key => {
  const direct = props.errors?.[key] ?? (props.index !== null ? props.errors?.[`lines.${props.index}.${key}`] : undefined)

  return Array.isArray(direct) ? direct[0] : direct
}

const reset = () => {
  const src = props.line || {}

  Object.keys(form).forEach(k => delete form[k])
  Object.assign(form, {
    ...src,
    quantity: src.quantity ?? src.qty ?? 1,
    unit_price: src.unit_price ?? src.price ?? 0,
    discount: src.discount ?? 0,
    tax_rate: src.tax_rate ?? 0,
    description: src.description ?? '',
    notes: src.notes ?? '',
    required_date: src.required_date ?? '',
    unit: src.unit ?? '',
    is_ad_hoc: src.is_ad_hoc ?? false,
    ad_hoc_name: src.ad_hoc_name ?? '',
    ad_hoc_sku: src.ad_hoc_sku ?? '',
  })
}

watch(() => props.modelValue, open => {
  if (open) reset()
}, { immediate: true })

const itemName = computed(() => form.item_name || form.name || form.sku || '')
const isAdHoc = computed(() => Boolean(form.is_ad_hoc) || (!form.catalog_item_id && !form.item_name && !form.name))
const total = computed(() => computeLineTotal(form))
const formattedTotal = computed(() => format(total.value, props.currency ? { currency: props.currency } : {}))

const submit = () => {
  // Totals only make sense when prices are part of this document line.
  const next = show('unit_price') ? patchLine({ ...form }, {}) : { ...form }

  ;['quantity', 'unit_price', 'discount', 'tax_rate'].forEach(k => {
    if (k in next) next[k] = Number(next[k]) || 0
  })
  if ('qty' in props.line && !('quantity' in props.line)) next.qty = next.quantity
  // Carry through ad-hoc fields explicitly
  next.is_ad_hoc = Boolean(form.is_ad_hoc)
  if (form.is_ad_hoc) {
    next.ad_hoc_name = form.ad_hoc_name ?? ''
    next.ad_hoc_sku = form.ad_hoc_sku ?? ''
  }
  emit('save', next, props.index)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="title || (isNew ? t('wp.docs.line.title_add') : t('wp.docs.line.title_edit'))"
    icon="tabler-list-details"
    :loading="loading"
    :submit-label="t('wp.docs.line.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <VRow class="pa-4">
      <VCol
        v-if="show('item')"
        cols="12"
      >
        <!-- Ad-hoc / Custom item toggle (shown when item field is visible and not force-readonly) -->
        <div
          v-if="!cfg.item.readonly"
          class="d-flex align-center gap-2 mb-3"
        >
          <VSwitch
            v-model="form.is_ad_hoc"
            :label="t('wp.docs.line.ad_hoc_toggle')"
            density="compact"
            hide-details
            color="primary"
            class="flex-shrink-0"
          />
        </div>

        <!-- Catalog item display / picker trigger (when not ad-hoc) -->
        <VTextField
          v-if="!form.is_ad_hoc"
          :model-value="itemName"
          :label="labelOf('item')"
          readonly
          :error-messages="errorOf('item') || errorOf('catalog_item_id')"
          prepend-inner-icon="tabler-package"
          :rules="cfg.item.required ? [() => !!itemName || t('wp.docs.line.required')] : []"
          @click="!cfg.item.readonly && emit('select-item')"
        >
          <template
            v-if="!cfg.item.readonly"
            #append-inner
          >
            <VBtn
              size="small"
              variant="text"
              color="primary"
              @click.stop="emit('select-item')"
            >
              {{ itemName ? t('wp.docs.line.change_item') : t('wp.docs.line.select_item') }}
            </VBtn>
          </template>
        </VTextField>

        <!-- Ad-hoc item inputs (when is_ad_hoc) -->
        <template v-else>
          <VTextField
            v-model="form.ad_hoc_name"
            :label="t('wp.docs.line.ad_hoc_name')"
            prepend-inner-icon="tabler-pencil"
            :error-messages="errorOf('ad_hoc_name') || errorOf('catalog_item_id')"
            class="mb-3"
          />
          <VTextField
            v-model="form.ad_hoc_sku"
            :label="t('wp.docs.line.ad_hoc_sku')"
            prepend-inner-icon="tabler-barcode"
            :error-messages="errorOf('ad_hoc_sku')"
          />
        </template>
      </VCol>

      <VCol
        v-if="show('description')"
        cols="12"
      >
        <VTextarea
          v-model="form.description"
          :label="labelOf('description')"
          rows="2"
          auto-grow
          :readonly="cfg.description.readonly"
          :rules="rulesFor('description')"
          :error-messages="errorOf('description')"
        />
      </VCol>

      <VCol
        v-if="show('quantity')"
        cols="12"
        :sm="show('unit') ? 6 : 12"
      >
        <VTextField
          v-model="form.quantity"
          :label="labelOf('quantity')"
          type="number"
          inputmode="decimal"
          min="0"
          step="any"
          :readonly="cfg.quantity.readonly"
          :rules="rulesFor('quantity')"
          :error-messages="errorOf('quantity') || errorOf('qty_requested')"
        />
      </VCol>

      <VCol
        v-if="show('unit')"
        cols="12"
        :sm="show('quantity') ? 6 : 12"
      >
        <VSelect
          v-if="units.length"
          v-model="form.unit"
          :items="units"
          item-title="title"
          item-value="value"
          :label="labelOf('unit')"
          :readonly="cfg.unit.readonly"
          :rules="rulesFor('unit')"
          :error-messages="errorOf('unit')"
        />
        <VTextField
          v-else
          v-model="form.unit"
          :label="labelOf('unit')"
          :readonly="cfg.unit.readonly"
          :rules="rulesFor('unit')"
          :error-messages="errorOf('unit')"
        />
      </VCol>

      <VCol
        v-if="show('unit_price')"
        cols="12"
        :sm="show('discount') || show('tax_rate') ? 4 : 12"
      >
        <VTextField
          v-model="form.unit_price"
          :label="labelOf('unit_price')"
          type="number"
          inputmode="decimal"
          min="0"
          step="any"
          :suffix="currency"
          :readonly="cfg.unit_price.readonly"
          :rules="rulesFor('unit_price')"
          :error-messages="errorOf('unit_price')"
        />
      </VCol>

      <VCol
        v-if="show('discount')"
        cols="6"
        sm="4"
      >
        <VTextField
          v-model="form.discount"
          :label="labelOf('discount')"
          type="number"
          inputmode="decimal"
          min="0"
          max="100"
          step="any"
          suffix="%"
          :readonly="cfg.discount.readonly"
          :rules="rulesFor('discount')"
          :error-messages="errorOf('discount')"
        />
      </VCol>

      <VCol
        v-if="show('tax_rate')"
        cols="6"
        sm="4"
      >
        <VTextField
          v-model="form.tax_rate"
          :label="labelOf('tax_rate')"
          type="number"
          inputmode="decimal"
          min="0"
          max="100"
          step="any"
          suffix="%"
          :readonly="cfg.tax_rate.readonly"
          :rules="rulesFor('tax_rate')"
          :error-messages="errorOf('tax_rate') || errorOf('tax_rate_pct')"
        />
      </VCol>

      <VCol
        v-if="show('required_date')"
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.required_date"
          :label="labelOf('required_date')"
          type="date"
          :readonly="cfg.required_date.readonly"
          :rules="rulesFor('required_date')"
          :error-messages="errorOf('required_date')"
        />
      </VCol>

      <VCol
        v-if="show('notes')"
        cols="12"
      >
        <VTextarea
          v-model="form.notes"
          :label="labelOf('notes')"
          rows="2"
          auto-grow
          :readonly="cfg.notes.readonly"
          :rules="rulesFor('notes')"
          :error-messages="errorOf('notes')"
        />
      </VCol>

      <VCol
        v-if="show('unit_price') || show('quantity')"
        cols="12"
      >
        <div class="wp-line-dialog__total">
          <span class="text-body-2 text-medium-emphasis">{{ t('wp.docs.line.line_total') }}</span>
          <span class="text-h6 font-weight-bold text-primary">{{ formattedTotal }}</span>
        </div>
      </VCol>
    </VRow>
  </WpFormDialog>
</template>

<style scoped>
.wp-line-dialog__total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.06);
}
</style>
