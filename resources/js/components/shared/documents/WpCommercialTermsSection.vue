<script setup>
/* eslint-disable camelcase, lines-around-comment */
/**
 * WpCommercialTermsSection
 * ─────────────────────────────────────────────────────────────────────────────
 * Section card with the commercial terms of a document. v-model is a plain
 * object; model keys can be remapped with `fieldKeys`.
 *
 *   fields   array of keys or { key: boolean } — defaults to all visible:
 *            payment_terms · incoterms · currency · expected_delivery_date ·
 *            supplier_reference · internal_notes · supplier_notes
 *   fieldKeys  { supplier_notes: 'notes_to_supplier', supplier_reference: 'external_reference' }
 *   paymentTermsOptions / incotermsOptions / currencyOptions  [{ title, value }]
 *   errors   error map keyed by the model key
 *   bare     render without the WpSectionCard shell (use inside another card)
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import WpSectionCard from '@/components/shared/layout/WpSectionCard.vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  title: { type: String, default: '' },
  fields: { type: [Object, Array], default: undefined },
  fieldKeys: { type: Object, default: () => ({}) },
  labels: { type: Object, default: () => ({}) },
  paymentTermsOptions: { type: Array, default: () => [] },
  incotermsOptions: { type: Array, default: () => [] },
  currencyOptions: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  bare: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const ALL = ['payment_terms', 'incoterms', 'currency', 'expected_delivery_date', 'supplier_reference', 'internal_notes', 'supplier_notes']

const visible = computed(() => {
  if (Array.isArray(props.fields)) return Object.fromEntries(ALL.map(k => [k, props.fields.includes(k)]))
  if (props.fields && typeof props.fields === 'object') return Object.fromEntries(ALL.map(k => [k, props.fields[k] !== false]))

  return Object.fromEntries(ALL.map(k => [k, true]))
})

const show = name => visible.value[name]
const keyOf = name => props.fieldKeys?.[name] || name
const labelOf = name => props.labels?.[name] || t(`wp.docs.terms.${name}`)
const valueOf = name => props.modelValue?.[keyOf(name)] ?? null

const errorOf = name => {
  const err = props.errors?.[keyOf(name)]

  return Array.isArray(err) ? err[0] : err
}

const update = (name, value) => emit('update:modelValue', { ...props.modelValue, [keyOf(name)]: value })
</script>

<template>
  <component
    :is="bare ? 'div' : WpSectionCard"
    v-bind="bare ? {} : { title: title || t('wp.docs.terms.title'), icon: 'tabler-file-text' }"
  >
    <VRow dense>
      <VCol
        v-if="show('payment_terms')"
        cols="12"
        sm="6"
      >
        <VSelect
          :model-value="valueOf('payment_terms')"
          :items="paymentTermsOptions"
          item-title="title"
          item-value="value"
          :label="labelOf('payment_terms')"
          :readonly="readonly"
          :error-messages="errorOf('payment_terms')"
          clearable
          @update:model-value="update('payment_terms', $event)"
        />
      </VCol>

      <VCol
        v-if="show('incoterms')"
        cols="12"
        sm="6"
      >
        <VSelect
          v-if="incotermsOptions.length"
          :model-value="valueOf('incoterms')"
          :items="incotermsOptions"
          item-title="title"
          item-value="value"
          :label="labelOf('incoterms')"
          :readonly="readonly"
          :error-messages="errorOf('incoterms')"
          clearable
          @update:model-value="update('incoterms', $event)"
        />
        <VTextField
          v-else
          :model-value="valueOf('incoterms')"
          :label="labelOf('incoterms')"
          :readonly="readonly"
          :error-messages="errorOf('incoterms')"
          @update:model-value="update('incoterms', $event)"
        />
      </VCol>

      <VCol
        v-if="show('currency')"
        cols="12"
        sm="6"
      >
        <VSelect
          v-if="currencyOptions.length"
          :model-value="valueOf('currency')"
          :items="currencyOptions"
          item-title="title"
          item-value="value"
          :label="labelOf('currency')"
          :readonly="readonly"
          :error-messages="errorOf('currency')"
          @update:model-value="update('currency', $event)"
        />
        <VTextField
          v-else
          :model-value="valueOf('currency')"
          :label="labelOf('currency')"
          :readonly="readonly"
          :error-messages="errorOf('currency')"
          @update:model-value="update('currency', $event)"
        />
      </VCol>

      <VCol
        v-if="show('expected_delivery_date')"
        cols="12"
        sm="6"
      >
        <VTextField
          :model-value="valueOf('expected_delivery_date')"
          type="date"
          :label="labelOf('expected_delivery_date')"
          :readonly="readonly"
          :error-messages="errorOf('expected_delivery_date')"
          @update:model-value="update('expected_delivery_date', $event)"
        />
      </VCol>

      <VCol
        v-if="show('supplier_reference')"
        cols="12"
      >
        <VTextField
          :model-value="valueOf('supplier_reference')"
          :label="labelOf('supplier_reference')"
          prepend-inner-icon="tabler-hash"
          :readonly="readonly"
          :error-messages="errorOf('supplier_reference')"
          @update:model-value="update('supplier_reference', $event)"
        />
      </VCol>

      <VCol
        v-if="show('supplier_notes')"
        cols="12"
        :md="show('internal_notes') ? 6 : 12"
      >
        <VTextarea
          :model-value="valueOf('supplier_notes')"
          :label="labelOf('supplier_notes')"
          :hint="readonly ? '' : t('wp.docs.terms.supplier_notes_hint')"
          persistent-hint
          rows="3"
          auto-grow
          :readonly="readonly"
          :error-messages="errorOf('supplier_notes')"
          @update:model-value="update('supplier_notes', $event)"
        />
      </VCol>

      <VCol
        v-if="show('internal_notes')"
        cols="12"
        :md="show('supplier_notes') ? 6 : 12"
      >
        <VTextarea
          :model-value="valueOf('internal_notes')"
          :label="labelOf('internal_notes')"
          :hint="readonly ? '' : t('wp.docs.terms.internal_notes_hint')"
          persistent-hint
          rows="3"
          auto-grow
          :readonly="readonly"
          :error-messages="errorOf('internal_notes')"
          @update:model-value="update('internal_notes', $event)"
        />
      </VCol>
    </VRow>
  </component>
</template>
