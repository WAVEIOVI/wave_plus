<script setup>
/* eslint-disable camelcase */
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIprStore } from '@/stores/useIprStore'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'created'])

const { t } = useI18n()
const iprStore = useIprStore()
const { format: formatMoney } = useCurrency()

const {
  currentUserId,
  urgencySelectOptions,
  triggerSourceOptions,
  userOptions,
  ensureUsers,
  fetchCatalogItemsForPicker,
} = useProcurementHelpers()

watch(
  () => props.modelValue,
  open => {
    if (open) ensureUsers()
  },
  { immediate: true },
)

const initialValues = computed(() => ({
  trigger_source: 'manual_user',
  trigger_reference: '',
  requested_by: currentUserId.value || userOptions.value[0]?.value || '',
  assigned_to: null,
  urgency: 'normal',
  needed_by_date: '',
  internal_justification: '',
  lines: [],
}))

const steps = computed(() => [
  {
    id: 'context',
    title: t('scp.ipr.wizard.step_context'),
    subtitle: t('scp.ipr.wizard.step_context_desc'),
    icon: 'tabler-user-check',
    validate: form => {
      const errs = {}
      if (!form.requested_by) errs.requested_by = t('scp.ipr.snackbar.error_requested_by')
      if (!form.urgency) errs.urgency = t('wp.wizard.required')

      return Object.keys(errs).length ? errs : true
    },
  },
  {
    id: 'lines',
    title: t('scp.ipr.wizard.step_lines'),
    subtitle: t('scp.ipr.wizard.step_lines_desc'),
    icon: 'tabler-list-details',
    validate: form => {
      if (!Array.isArray(form.lines) || form.lines.length === 0) {
        return t('scp.ipr.snackbar.error_no_lines')
      }
      const bad = form.lines.some(l => Number(l.quantity ?? l.qty_requested ?? 0) <= 0)
      if (bad) return t('wp.documents.lines.errors.qty_positive')

      return true
    },
  },
  {
    id: 'justification',
    title: t('scp.ipr.wizard.step_justification'),
    subtitle: t('scp.ipr.wizard.step_justification_desc'),
    icon: 'tabler-file-description',
  },
])

const mapCatalogToIprLine = (item, quantity = 1) => {
  const unitCost = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const qty = Number(quantity) || 1

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || '',
    unit: item.unit || '',
    quantity: qty,
    qty_requested: qty,
    unit_price: unitCost,
    estimated_unit_cost: unitCost,
    line_total: qty * unitCost,
    description: '',
  }
}

const computeEstimatedTotal = lines =>
  (lines || []).reduce((sum, l) => {
    const qty = Number(l.quantity ?? l.qty_requested ?? 0) || 0
    const cost = Number(l.unit_price ?? l.estimated_unit_cost ?? 0) || 0

    return sum + qty * cost
  }, 0)

const handleSubmit = async form => {
  const payload = {
    trigger_source: form.trigger_source || 'manual_user',
    trigger_reference: form.trigger_reference || null,
    requested_by: form.requested_by || currentUserId.value,
    assigned_to: form.assigned_to || null,
    urgency: form.urgency || 'normal',
    needed_by_date: form.needed_by_date || null,
    internal_justification: form.internal_justification || null,
    lines: (form.lines || []).map(l => ({
      is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
      catalog_item_id: l.catalog_item_id || null,
      ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || l.description || 'Ad-hoc Item') : null,
      ad_hoc_sku: !l.catalog_item_id ? (l.sku || l.ad_hoc_sku || null) : null,
      ad_hoc_description: l.description || null,
      qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
      estimated_unit_cost: Number(l.unit_price ?? l.estimated_unit_cost ?? 0) || 0,
    })),
  }

  const created = await iprStore.createIpr(payload)

  emit('created', created)

  return created
}
</script>

<template>
  <WpWizardDialog
    :model-value="modelValue"
    variant="standard"
    :title="t('scp.ipr.new_title')"
    :description="t('scp.ipr.subtitle')"
    icon="tabler-clipboard-plus"
    :steps="steps"
    :initial-values="initialValues"
    :submit="handleSubmit"
    :submit-label="t('scp.action.save')"
    :success-message="t('scp.ipr.snackbar.created_success')"
    max-width="920"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Step 1: Requester & Context -->
    <template #step-context="{ form, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            :model-value="form.requested_by"
            :items="userOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.requested_by')"
            prepend-inner-icon="tabler-user"
            :error-messages="errors.requested_by"
            @update:model-value="setValue('requested_by', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            :model-value="form.assigned_to"
            :items="userOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.assigned_to')"
            prepend-inner-icon="tabler-user-check"
            clearable
            :error-messages="errors.assigned_to"
            @update:model-value="setValue('assigned_to', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            :model-value="form.urgency"
            :items="urgencySelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.urgency')"
            prepend-inner-icon="tabler-flag"
            :error-messages="errors.urgency"
            @update:model-value="setValue('urgency', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.needed_by_date"
            type="date"
            :label="t('scp.ipr.fields.needed_by_date')"
            prepend-inner-icon="tabler-calendar"
            :error-messages="errors.needed_by_date"
            @update:model-value="setValue('needed_by_date', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            :model-value="form.trigger_source"
            :items="triggerSourceOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.trigger_source')"
            prepend-inner-icon="tabler-bolt"
            :error-messages="errors.trigger_source"
            @update:model-value="setValue('trigger_source', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.trigger_reference"
            :label="t('scp.ipr.fields.trigger_reference')"
            prepend-inner-icon="tabler-hash"
            :error-messages="errors.trigger_reference"
            @update:model-value="setValue('trigger_reference', $event)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 2: Requested Items -->
    <template #step-lines="{ form, errors, setValue }">
      <WpDocumentLinesCard
        :lines="form.lines"
        :title="t('scp.ipr.sections.lines')"
        :fields="['item', 'description', 'quantity', 'unit', 'unit_price']"
        :field-labels="{
          quantity: t('scp.ipr.fields.qty_requested'),
          unit_price: t('scp.ipr.fields.estimated_unit_cost'),
        }"
        :totals="{
          grand_total: computeEstimatedTotal(form.lines),
        }"
        show-catalog-picker
        show-scan
        :fetch-catalog-items="fetchCatalogItemsForPicker"
        :map-catalog-item="mapCatalogToIprLine"
        :errors="errors"
        @update:lines="setValue('lines', $event)"
      />
    </template>

    <!-- Step 3: Justification & Review -->
    <template #step-justification="{ form, errors, setValue }">
      <VRow>
        <VCol cols="12">
          <VTextarea
            :model-value="form.internal_justification"
            :label="t('scp.ipr.fields.internal_justification')"
            :placeholder="t('scp.ipr.fields.justification_placeholder')"
            rows="4"
            auto-grow
            :error-messages="errors.internal_justification"
            @update:model-value="setValue('internal_justification', $event)"
          />
        </VCol>

        <VCol cols="12">
          <VAlert
            type="info"
            variant="tonal"
            density="comfortable"
          >
            <div class="d-flex flex-wrap align-center justify-space-between gap-2">
              <span>
                <strong>{{ (form.lines || []).length }}</strong> {{ t('scp.common.lines') }}
              </span>
              <span class="text-body-1 font-weight-bold">
                {{ t('scp.ipr.fields.estimated_total') }}: {{ formatMoney(computeEstimatedTotal(form.lines)) }}
              </span>
            </div>
          </VAlert>
        </VCol>
      </VRow>
    </template>
  </WpWizardDialog>
</template>

