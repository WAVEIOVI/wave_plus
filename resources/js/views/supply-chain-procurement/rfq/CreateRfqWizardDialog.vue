<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRfqStore } from '@/stores/useRfqStore'
import { useIprStore } from '@/stores/useIprStore'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  prefillIpr: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'created'])

const { t } = useI18n()
const rfqStore = useRfqStore()
const iprStore = useIprStore()

const {
  supplierOptions,
  ensureSuppliers,
  fetchCatalogItemsForPicker,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const approvedIprs = ref([])

const defaultDeadline = () => {
  const d = new Date()

  d.setDate(d.getDate() + 7)

  return d.toISOString().slice(0, 10)
}

watch(
  () => props.modelValue,
  async open => {
    if (!open) return
    await ensureSuppliers()
    try {
      const res = await iprStore.fetchList({ 'filter[status]': 'approved', per_page: 50 })

      approvedIprs.value = res?.data || iprStore.list || []
    }
    catch {
      approvedIprs.value = []
    }
  },
  { immediate: true },
)

const approvedIprOptions = computed(() => {
  const list = approvedIprs.value.map(ipr => ({
    value: ipr.id,
    title: `${ipr.reference} — ${ipr.requested_by_user?.name || ''}`.trim().replace(/—\s*$/, '').trim(),
  }))

  if (props.prefillIpr?.id && !list.some(o => o.value === props.prefillIpr.id)) {
    list.unshift({
      value: props.prefillIpr.id,
      title: `${props.prefillIpr.reference} — ${props.prefillIpr.requested_by_user?.name || ''}`.trim().replace(/—\s*$/, '').trim(),
    })
  }

  return list
})

const mapCatalogToRfqLine = (item, quantity = 1) => {
  const price = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const qty = Number(quantity) || 1

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    variant_id: item.variant_id || null,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || '',
    unit: item.unit || '',
    unit_id: item.unit_id || null,
    quantity: qty,
    qty_requested: qty,
    unit_price: price,
    target_price_ht: price,
    line_total: qty * price,
    description: '',
  }
}

const mapIprLineToRfqLine = (l, sourceIprId = null) => {
  const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
  const price = Number(l.estimated_unit_cost ?? l.unit_price ?? 0) || 0

  return {
    source_ipr_id: sourceIprId || l.ipr_id || null,
    source_ipr_line_id: l.id || null,
    is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
    catalog_item_id: l.catalog_item_id || null,
    variant_id: l.variant_id || null,
    ad_hoc_name: l.ad_hoc_name || (!l.catalog_item_id ? resolveLineItemName(l) : null),
    ad_hoc_sku: l.ad_hoc_sku || (!l.catalog_item_id ? resolveLineItemSku(l) : null),
    item_name: resolveLineItemName(l),
    sku: resolveLineItemSku(l),
    unit_id: l.unit_id || l.catalog_item?.uom_id || null,
    quantity: qty,
    qty_requested: qty,
    unit_price: price,
    target_price_ht: price,
    line_total: qty * price,
    description: l.ad_hoc_description || '',
  }
}

const onIprIdsChanged = async (nextIds, form, setValue) => {
  const ids = Array.isArray(nextIds) ? nextIds : []

  setValue('ipr_ids', ids)

  const currentLines = Array.isArray(form.lines) ? form.lines : []
  const keptLines = currentLines.filter(l => !l.source_ipr_id || ids.includes(l.source_ipr_id))
  const representedIprIds = new Set(keptLines.map(l => l.source_ipr_id).filter(Boolean))
  const addedLines = []

  for (const id of ids) {
    if (representedIprIds.has(id)) continue

    let ipr = (props.prefillIpr?.id === id ? props.prefillIpr : null)
      || approvedIprs.value.find(item => item.id === id)
      || null

    if (!ipr?.lines?.length) {
      try {
        const fetched = await iprStore.fetchDetails(id)
        if (fetched) {
          ipr = fetched

          const idx = approvedIprs.value.findIndex(item => item.id === id)
          if (idx >= 0) approvedIprs.value[idx] = fetched
        }
      }
      catch {
        // Ignore detail fetch failure
      }
    }

    for (const l of ipr?.lines || []) {
      addedLines.push(mapIprLineToRfqLine(l, id))
    }
  }

  setValue('lines', [...keptLines, ...addedLines])
}

const initialValues = computed(() => {
  const ipr = props.prefillIpr
  const prefilledLines = (ipr?.lines || []).map(l => mapIprLineToRfqLine(l, ipr?.id))

  return {
    title: ipr ? `${t('scp.rfq.title')} — ${ipr.reference}` : '',
    response_deadline: defaultDeadline(),
    required_delivery_date: ipr?.needed_by_date ? String(ipr.needed_by_date).slice(0, 10) : '',
    ipr_ids: ipr?.id ? [ipr.id] : [],
    lines: prefilledLines,
    invited_supplier_ids: [],
    price_weight: 40,
    lead_time_weight: 25,
    quality_weight: 20,
    technical_weight: 10,
    payment_weight: 5,
    terms_and_conditions: '',
  }
})

const weightsSum = form =>
  (Number(form.price_weight) || 0)
  + (Number(form.lead_time_weight) || 0)
  + (Number(form.quality_weight) || 0)
  + (Number(form.technical_weight) || 0)
  + (Number(form.payment_weight) || 0)

const steps = computed(() => [
  {
    id: 'general',
    title: t('scp.rfq.wizard.step_general'),
    subtitle: t('scp.rfq.wizard.step_general_desc'),
    icon: 'tabler-file-info',
    validate: form => {
      const errs = {}
      if (!String(form.title || '').trim()) errs.title = t('wp.wizard.required')
      if (!form.response_deadline) errs.response_deadline = t('wp.wizard.required')

      return Object.keys(errs).length ? errs : true
    },
  },
  {
    id: 'lines',
    title: t('scp.rfq.wizard.step_lines'),
    subtitle: t('scp.rfq.wizard.step_lines_desc'),
    icon: 'tabler-list-details',
    validate: form => {
      if (!Array.isArray(form.lines) || form.lines.length === 0) {
        return t('scp.ipr.snackbar.error_no_lines')
      }

      return true
    },
  },
  {
    id: 'suppliers',
    title: t('scp.rfq.wizard.step_suppliers'),
    subtitle: t('scp.rfq.wizard.step_suppliers_desc'),
    icon: 'tabler-building-store',
    validate: form => {
      if (Math.round(weightsSum(form)) !== 100) {
        return t('scp.rfq.validation.weights_sum_100')
      }

      return true
    },
  },
])

const handleSubmit = async form => {
  const payload = {
    title: String(form.title).trim(),
    response_deadline: form.response_deadline,
    required_delivery_date: form.required_delivery_date || null,
    ipr_ids: Array.isArray(form.ipr_ids) ? form.ipr_ids : [],
    terms_and_conditions: form.terms_and_conditions || null,
    evaluation_criteria: {
      price_weight: Number(form.price_weight) || 0,
      lead_time_weight: Number(form.lead_time_weight) || 0,
      quality_weight: Number(form.quality_weight) || 0,
      technical_weight: Number(form.technical_weight) || 0,
      payment_weight: Number(form.payment_weight) || 0,
    },
    invitations: (form.invited_supplier_ids || []).map(id => ({ supplier_id: id })),
    lines: (form.lines || []).map(l => ({
      is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
      catalog_item_id: l.catalog_item_id || null,
      variant_id: l.variant_id || null,
      ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || 'Ad-hoc Item') : null,
      ad_hoc_sku: !l.catalog_item_id ? (l.sku || l.ad_hoc_sku || null) : null,
      description_for_supplier: l.description || l.description_for_supplier || null,
      qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
      unit_id: l.unit_id || null,
      target_price_ht: Number(l.unit_price ?? l.target_price_ht ?? 0) || null,
    })),
  }

  const created = await rfqStore.create(payload)

  emit('created', created)

  return created
}
</script>

<template>
  <WpWizardDialog
    :model-value="modelValue"
    variant="standard"
    :title="t('scp.rfq.new_title')"
    :description="t('scp.rfq.subtitle')"
    icon="tabler-scale"
    :steps="steps"
    :initial-values="initialValues"
    :submit="handleSubmit"
    :submit-label="t('scp.action.save')"
    :success-message="t('scp.rfq.snackbar.created_success')"
    max-width="940"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Step 1: General Info -->
    <template #step-general="{ form, errors, setValue }">
      <VRow>
        <VCol cols="12">
          <VTextField
            :model-value="form.title"
            :label="t('scp.rfq.fields.title')"
            prepend-inner-icon="tabler-file-text"
            :error-messages="errors.title"
            @update:model-value="setValue('title', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.response_deadline"
            type="date"
            :label="t('scp.rfq.fields.response_deadline')"
            prepend-inner-icon="tabler-calendar-due"
            :error-messages="errors.response_deadline"
            @update:model-value="setValue('response_deadline', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.required_delivery_date"
            type="date"
            :label="t('scp.rfq.fields.required_delivery_date')"
            prepend-inner-icon="tabler-truck-delivery"
            :error-messages="errors.required_delivery_date"
            @update:model-value="setValue('required_delivery_date', $event)"
          />
        </VCol>

        <VCol cols="12">
          <VAutocomplete
            :model-value="form.ipr_ids"
            :items="approvedIprOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.rfq.fields.linked_iprs')"
            prepend-inner-icon="tabler-link"
            multiple
            chips
            closable-chips
            clearable
            @update:model-value="onIprIdsChanged($event, form, setValue)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 2: Requested Items -->
    <template #step-lines="{ form, errors, setValue }">
      <WpDocumentLinesCard
        :lines="form.lines"
        :title="t('scp.rfq.sections.lines')"
        :fields="['item', 'description', 'quantity', 'unit', 'unit_price']"
        :field-labels="{
          quantity: t('scp.ipr.fields.qty_requested'),
          unit_price: t('scp.rfq.fields.target_price_ht'),
        }"
        show-catalog-picker
        show-scan
        :fetch-catalog-items="fetchCatalogItemsForPicker"
        :map-catalog-item="mapCatalogToRfqLine"
        :errors="errors"
        @update:lines="setValue('lines', $event)"
      />
    </template>

    <!-- Step 3: Invited Suppliers & Evaluation Criteria -->
    <template #step-suppliers="{ form, errors, setValue }">
      <VRow>
        <VCol cols="12">
          <VAutocomplete
            :model-value="form.invited_supplier_ids"
            :items="supplierOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.rfq.fields.invited_suppliers')"
            prepend-inner-icon="tabler-building-store"
            multiple
            chips
            closable-chips
            clearable
            @update:model-value="setValue('invited_supplier_ids', $event)"
          />
        </VCol>

        <VCol cols="12">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-bold">{{ t('scp.rfq.sections.evaluation_criteria') }}</span>
            <VChip
              size="small"
              label
              :color="Math.round(weightsSum(form)) === 100 ? 'success' : 'error'"
            >
              {{ weightsSum(form) }}% / 100%
            </VChip>
          </div>
        </VCol>

        <VCol
          cols="6"
          sm="4"
        >
          <VTextField
            :model-value="form.price_weight"
            type="number"
            min="0"
            max="100"
            suffix="%"
            :label="t('scp.rfq.weights.price')"
            @update:model-value="setValue('price_weight', Number($event) || 0)"
          />
        </VCol>
        <VCol
          cols="6"
          sm="4"
        >
          <VTextField
            :model-value="form.lead_time_weight"
            type="number"
            min="0"
            max="100"
            suffix="%"
            :label="t('scp.rfq.weights.lead_time')"
            @update:model-value="setValue('lead_time_weight', Number($event) || 0)"
          />
        </VCol>
        <VCol
          cols="6"
          sm="4"
        >
          <VTextField
            :model-value="form.quality_weight"
            type="number"
            min="0"
            max="100"
            suffix="%"
            :label="t('scp.rfq.weights.quality')"
            @update:model-value="setValue('quality_weight', Number($event) || 0)"
          />
        </VCol>
        <VCol
          cols="6"
          sm="6"
        >
          <VTextField
            :model-value="form.technical_weight"
            type="number"
            min="0"
            max="100"
            suffix="%"
            :label="t('scp.rfq.weights.technical')"
            @update:model-value="setValue('technical_weight', Number($event) || 0)"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.payment_weight"
            type="number"
            min="0"
            max="100"
            suffix="%"
            :label="t('scp.rfq.weights.payment')"
            @update:model-value="setValue('payment_weight', Number($event) || 0)"
          />
        </VCol>

        <VCol cols="12">
          <VTextarea
            :model-value="form.terms_and_conditions"
            :label="t('scp.rfq.fields.terms_and_conditions')"
            rows="3"
            auto-grow
            :error-messages="errors.terms_and_conditions"
            @update:model-value="setValue('terms_and_conditions', $event)"
          />
        </VCol>
      </VRow>
    </template>
  </WpWizardDialog>
</template>
