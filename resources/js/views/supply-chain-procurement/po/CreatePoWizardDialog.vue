<script setup>
/* eslint-disable camelcase */
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePoStore } from '@/stores/usePoStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  prefillIpr: { type: Object, default: null },
  prefillRfq: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'created'])

const { t } = useI18n()
const poStore = usePoStore()
const supplierStore = useSupplierStore()

const {
  supplierOptions,
  warehouseOptions,
  poPaymentTermsOptions,
  incotermsOptions,
  currencySelectOptions,
  ensureSuppliers,
  ensureWarehouses,
  fetchCatalogItemsForPicker,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

watch(
  () => props.modelValue,
  async open => {
    if (!open) return
    await Promise.all([ensureSuppliers(), ensureWarehouses()])
  },
  { immediate: true },
)

const supplierSearchConfig = computed(() => ({
  confirmBehavior: 'immediate',
  items: supplierStore.suppliers,
  itemKey: 'id',
  fields: {
    title: 'legal_name',
    subtitle: 'supplier_code',
    badge: 'status',
  },
}))

const mapCatalogToPoLine = (item, quantity = 1) => {
  const qty = Number(quantity) || 1
  const price = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const tax = Number(item.tax_rate ?? 19) || 0
  const subtotal = qty * price

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || '',
    unit: item.unit || '',
    quantity: qty,
    qty_ordered: qty,
    unit_price: price,
    unit_price_ht: price,
    tax_rate: tax,
    tax_rate_pct: tax,
    line_total: subtotal + (subtotal * tax) / 100,
    description: '',
  }
}

const initialValues = computed(() => {
  const ipr = props.prefillIpr
  const rfq = props.prefillRfq

  let creation_path = 'manual'
  let supplier_id = null
  let supplierObj = null
  let lines = []

  if (rfq) {
    creation_path = 'from_rfq'
    supplier_id = rfq.awarded_to_supplier_id || rfq.awarded_supplier?.id || null
    supplierObj = rfq.awarded_supplier || supplierStore.suppliers.find(s => s.id === supplier_id) || null

    const awardedResp = (rfq.responses || []).find(r => r.supplier_id === supplier_id)

    lines = (rfq.lines || []).map(l => {
      const respLine = (awardedResp?.lines || []).find(rl => rl.rfq_line_id === l.id)
      const qty = Number(respLine?.offered_qty ?? l.qty_requested ?? 1) || 1
      const price = Number(respLine?.offered_unit_price_ht ?? l.target_price_ht ?? 0) || 0
      const tax = 19

      return {
        is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
        catalog_item_id: l.catalog_item_id || null,
        item_name: resolveLineItemName(l),
        sku: resolveLineItemSku(l),
        quantity: qty,
        qty_ordered: qty,
        unit_price: price,
        unit_price_ht: price,
        tax_rate: tax,
        tax_rate_pct: tax,
        line_total: qty * price * (1 + tax / 100),
        description: l.description_for_supplier || '',
      }
    })
  }
  else if (ipr) {
    creation_path = 'from_ipr'
    lines = (ipr.lines || []).map(l => {
      const qty = Number(l.qty_requested ?? 1) || 1
      const price = Number(l.estimated_unit_cost ?? 0) || 0
      const tax = 19

      return {
        is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
        catalog_item_id: l.catalog_item_id || null,
        ipr_line_id: l.id || null,
        item_name: resolveLineItemName(l),
        sku: resolveLineItemSku(l),
        quantity: qty,
        qty_ordered: qty,
        unit_price: price,
        unit_price_ht: price,
        tax_rate: tax,
        tax_rate_pct: tax,
        line_total: qty * price * (1 + tax / 100),
        description: l.ad_hoc_description || '',
      }
    })
  }

  return {
    creation_path,
    rfq_id: rfq?.id || null,
    ipr_ids: ipr?.id ? [ipr.id] : [],
    supplier_id,
    supplier: supplierObj,
    site_id: warehouseOptions.value[0]?.value || null,
    delivery_address: '',
    currency: 'TND',
    exchange_rate: 1,
    payment_terms: 'net_30',
    incoterms: '',
    committed_delivery_date: rfq?.required_delivery_date
      ? String(rfq.required_delivery_date).slice(0, 10)
      : ipr?.needed_by_date
        ? String(ipr.needed_by_date).slice(0, 10)
        : '',
    approval_required: false,
    supplier_po_reference: '',
    notes_to_supplier: '',
    internal_notes: '',
    lines,
  }
})

const steps = computed(() => [
  {
    id: 'supplier_site',
    title: t('scp.po.wizard.step_supplier'),
    subtitle: t('scp.po.wizard.step_supplier_desc'),
    icon: 'tabler-building-store',
    validate: form => {
      if (!form.supplier_id) return { supplier_id: t('wp.wizard.required') }

      return true
    },
  },
  {
    id: 'lines',
    title: t('scp.po.wizard.step_lines'),
    subtitle: t('scp.po.wizard.step_lines_desc'),
    icon: 'tabler-list-details',
    validate: form => {
      if (!Array.isArray(form.lines) || form.lines.length === 0) {
        return t('scp.ipr.snackbar.error_no_lines')
      }

      return true
    },
  },
  {
    id: 'terms',
    title: t('scp.po.wizard.step_terms'),
    subtitle: t('scp.po.wizard.step_terms_desc'),
    icon: 'tabler-file-text',
    validate: form => {
      if (!form.payment_terms) return { payment_terms: t('wp.wizard.required') }

      return true
    },
  },
])

const onSupplierSelected = (partner, setValue) => {
  setValue('supplier', partner)
  setValue('supplier_id', partner?.id || null)
}

const onSupplierIdSelected = (id, setValue) => {
  setValue('supplier_id', id)

  const found = supplierStore.suppliers.find(s => s.id === id) || null

  setValue('supplier', found)
}

const handleSubmit = async form => {
  const payload = {
    creation_path: form.creation_path || 'manual',
    rfq_id: form.rfq_id || null,
    ipr_ids: Array.isArray(form.ipr_ids) && form.ipr_ids.length ? form.ipr_ids : null,
    supplier_id: form.supplier_id,
    site_id: form.site_id || null,
    delivery_address: form.delivery_address || null,
    currency: form.currency || 'TND',
    exchange_rate: Number(form.exchange_rate) || 1,
    payment_terms: form.payment_terms || 'net_30',
    incoterms: form.incoterms || null,
    committed_delivery_date: form.committed_delivery_date || null,
    approval_required: Boolean(form.approval_required),
    supplier_po_reference: form.supplier_po_reference || null,
    notes_to_supplier: form.notes_to_supplier || null,
    internal_notes: form.internal_notes || null,
    lines: (form.lines || []).map(l => ({
      is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
      catalog_item_id: l.catalog_item_id || null,
      ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || 'Ad-hoc Item') : null,
      ipr_line_id: l.ipr_line_id || null,
      description_snapshot: l.description || l.item_name || null,
      qty_ordered: Number(l.quantity ?? l.qty_ordered ?? 1) || 1,
      unit_price_ht: Number(l.unit_price ?? l.unit_price_ht ?? 0) || 0,
      tax_rate_pct: Number(l.tax_rate ?? l.tax_rate_pct ?? 19) || 0,
      expected_delivery_date: l.required_date || form.committed_delivery_date || null,
    })),
  }

  const created = await poStore.createPo(payload)

  emit('created', created)

  return created
}
</script>

<template>
  <WpWizardDialog
    :model-value="modelValue"
    variant="standard"
    :title="t('scp.po.new_title')"
    :description="t('scp.po.subtitle')"
    icon="tabler-shopping-cart-plus"
    :steps="steps"
    :initial-values="initialValues"
    :submit="handleSubmit"
    :submit-label="t('scp.action.save')"
    :success-message="t('scp.po.snackbar.created_success')"
    max-width="960"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Step 1: Supplier & Warehouse Site -->
    <template #step-supplier_site="{ form, errors, setValue }">
      <VRow>
        <VCol cols="12">
          <WpPartnerCard
            :partner="form.supplier"
            :partner-label="t('scp.common.supplier')"
            :fields="{
              name: 'legal_name',
              tradeName: 'trade_name',
              code: 'supplier_code',
              status: 'status',
              email: 'email',
              phone: 'phone',
            }"
            :search-config="supplierSearchConfig"
            :select-dialog-props="{ confirmBehavior: 'immediate' }"
            clearable
            @change="onSupplierSelected($event, setValue)"
            @clear="onSupplierSelected(null, setValue)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            :model-value="form.supplier_id"
            :items="supplierOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.supplier')"
            prepend-inner-icon="tabler-building-store"
            :error-messages="errors.supplier_id"
            @update:model-value="onSupplierIdSelected($event, setValue)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            :model-value="form.site_id"
            :items="warehouseOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.warehouse')"
            prepend-inner-icon="tabler-building-warehouse"
            clearable
            :error-messages="errors.site_id"
            @update:model-value="setValue('site_id', $event)"
          />
        </VCol>

        <VCol cols="12">
          <VTextField
            :model-value="form.delivery_address"
            :label="t('scp.po.fields.delivery_address')"
            prepend-inner-icon="tabler-map-pin"
            :error-messages="errors.delivery_address"
            @update:model-value="setValue('delivery_address', $event)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 2: Line Items -->
    <template #step-lines="{ form, errors, setValue }">
      <WpDocumentLinesCard
        :lines="form.lines"
        :currency="form.currency"
        :title="t('scp.po.sections.lines')"
        :fields="['item', 'description', 'quantity', 'unit', 'unit_price', 'tax_rate', 'required_date']"
        :field-labels="{
          quantity: t('scp.po.fields.qty_ordered'),
          unit_price: t('scp.po.fields.unit_price_ht'),
        }"
        show-catalog-picker
        show-scan
        :fetch-catalog-items="fetchCatalogItemsForPicker"
        :map-catalog-item="mapCatalogToPoLine"
        :errors="errors"
        @update:lines="setValue('lines', $event)"
      />
    </template>

    <!-- Step 3: Commercial Terms -->
    <template #step-terms="{ form, errors, setValue }">
      <WpCommercialTermsSection
        :model-value="form"
        bare
        :field-keys="{
          expected_delivery_date: 'committed_delivery_date',
          supplier_reference: 'supplier_po_reference',
          supplier_notes: 'notes_to_supplier',
          internal_notes: 'internal_notes',
        }"
        :payment-terms-options="poPaymentTermsOptions"
        :incoterms-options="incotermsOptions"
        :currency-options="currencySelectOptions"
        :errors="errors"
        @update:model-value="next => Object.entries(next).forEach(([k, v]) => setValue(k, v))"
      />

      <VDivider class="my-4" />

      <VSwitch
        :model-value="form.approval_required"
        color="primary"
        :label="t('scp.po.fields.approval_required')"
        hide-details
        @update:model-value="setValue('approval_required', Boolean($event))"
      />
    </template>
  </WpWizardDialog>
</template>
