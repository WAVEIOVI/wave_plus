<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGrnStore } from '@/stores/useGrnStore'
import { usePoStore } from '@/stores/usePoStore'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  prefillPo: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'created'])

const { t } = useI18n()
const grnStore = useGrnStore()
const poStore = usePoStore()

const {
  currentUserId,
  userOptions,
  qcResultOptions,
  overallQcResultOptions,
  ensureUsers,
  ensureWarehouses,
  ensureSiteLocations,
  locationOptions,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const receivablePos = ref([])
const siteLocationOptions = computed(() => locationOptions())

const todayStr = () => new Date().toISOString().slice(0, 10)

const buildGrnLinesFromPo = (po, defaultLocationId = null) =>
  (po?.lines || []).map(l => {
    const ordered = Number(l.qty_ordered) || 0
    const alreadyReceived = Number(l.qty_received) || 0
    const remaining = Math.max(0.01, ordered - alreadyReceived)
    const isAdHoc = Boolean(l.is_ad_hoc || !l.catalog_item_id)

    return {
      po_line_id: l.id,
      is_ad_hoc: isAdHoc,
      catalog_item_id: l.catalog_item_id || null,
      catalog_variant_id: l.variant_id || l.catalog_variant_id || null,
      ad_hoc_name: isAdHoc ? (l.ad_hoc_name || resolveLineItemName(l)) : null,
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || resolveLineItemSku(l) || null) : null,
      item_name: resolveLineItemName(l),
      qty_ordered: ordered,
      qty_already_received: alreadyReceived,
      qty_received: remaining,
      qty_accepted: remaining,
      qty_rejected: 0,
      unit_cost_ht: Number(l.unit_price_ht) || 0,
      storage_location_id: defaultLocationId || null,
      lot_number: '',
      expiry_date: '',
      qc_result: 'passed',
      qc_notes: '',
    }
  })

watch(
  () => props.modelValue,
  async open => {
    if (!open) return
    await Promise.all([ensureUsers(), ensureWarehouses()])
    if (props.prefillPo?.site_id) {
      await ensureSiteLocations(props.prefillPo.site_id)
    }
    try {
      const res = await poStore.fetchList({
        'filter[status]': 'sent,acknowledged,in_delivery,partially_received',
        per_page: 50,
      })

      const all = res?.data || poStore.list || []

      receivablePos.value = all.filter(p =>
        ['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(p.status),
      )
    }
    catch {
      receivablePos.value = []
    }
  },
  { immediate: true },
)

const receivablePoOptions = computed(() => {
  const list = receivablePos.value.map(p => ({
    value: p.id,
    title: `${p.reference} — ${resolveSupplierName(p.supplier)}`.trim(),
  }))

  if (props.prefillPo?.id && !list.some(o => o.value === props.prefillPo.id)) {
    list.unshift({
      value: props.prefillPo.id,
      title: `${props.prefillPo.reference} — ${resolveSupplierName(props.prefillPo.supplier)}`.trim(),
    })
  }

  return list
})

const initialValues = computed(() => {
  const po = props.prefillPo
  const defaultLoc = siteLocationOptions.value[0]?.value || null

  return {
    po_id: po?.id || null,
    supplier_id: po?.supplier_id || po?.supplier?.id || null,
    supplier_name: po?.supplier ? resolveSupplierName(po.supplier) : '',
    received_by: currentUserId.value || userOptions.value[0]?.value || '',
    received_at: todayStr(),
    default_location_id: defaultLoc,
    supplier_delivery_note_ref: '',
    delivery_note_file: null,
    allow_over_receive: false,
    is_partial_delivery: false,
    overall_qc_result: 'passed',
    lines: po ? buildGrnLinesFromPo(po, defaultLoc) : [],
  }
})

const onPoChanged = async (poId, form, setValue) => {
  setValue('po_id', poId)
  if (!poId) {
    setValue('lines', [])

    return
  }
  const fullPo = await poStore.fetchDetails(poId)
  if (!fullPo) return

  if (fullPo.site_id) {
    await ensureSiteLocations(fullPo.site_id)
  }
  const loc = siteLocationOptions.value[0]?.value || null

  setValue('supplier_id', fullPo.supplier_id || fullPo.supplier?.id || null)
  setValue('supplier_name', fullPo.supplier ? resolveSupplierName(fullPo.supplier) : '')
  setValue('default_location_id', loc)
  setValue('lines', buildGrnLinesFromPo(fullPo, loc))
}

const applyLocationToAll = (locId, form, setValue) => {
  setValue('default_location_id', locId)
  setValue(
    'lines',
    (form.lines || []).map(l => ({ ...l, storage_location_id: locId })),
  )
}

const onReceivedChange = (line, val) => {
  const rec = Math.max(0, Number(val) || 0)

  line.qty_received = rec
  line.qty_accepted = Math.max(0, rec - (Number(line.qty_rejected) || 0))
}

const onRejectedChange = (line, val) => {
  const rej = Math.max(0, Number(val) || 0)

  line.qty_rejected = rej
  line.qty_accepted = Math.max(0, (Number(line.qty_received) || 0) - rej)
  if (rej > 0 && line.qc_result === 'passed') {
    line.qc_result = 'passed_with_remarks'
  }
}

const steps = computed(() => [
  {
    id: 'po_context',
    title: t('scp.grn.wizard.step_po'),
    subtitle: t('scp.grn.wizard.step_po_desc'),
    icon: 'tabler-shopping-cart',
    validate: form => {
      const errs = {}
      if (!form.po_id) errs.po_id = t('wp.wizard.required')
      if (!form.received_by) errs.received_by = t('wp.wizard.required')
      if (!form.received_at) errs.received_at = t('wp.wizard.required')

      return Object.keys(errs).length ? errs : true
    },
  },
  {
    id: 'lines',
    title: t('scp.grn.wizard.step_lines'),
    subtitle: t('scp.grn.wizard.step_lines_desc'),
    icon: 'tabler-package-import',
    validate: form => {
      if (!Array.isArray(form.lines) || form.lines.length === 0) {
        return t('scp.grn.alerts.required_fields')
      }

      return true
    },
  },
  {
    id: 'delivery_note',
    title: t('scp.grn.wizard.step_delivery_note'),
    subtitle: t('scp.grn.wizard.step_delivery_note_desc'),
    icon: 'tabler-file-check',
  },
])

const handleSubmit = async form => {
  const mappedLines = (form.lines || []).map(l => ({
    po_line_id: l.po_line_id,
    is_ad_hoc: Boolean(l.is_ad_hoc),
    catalog_item_id: l.catalog_item_id || null,
    catalog_variant_id: l.variant_id || l.catalog_variant_id || null,
    ad_hoc_name: l.is_ad_hoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
    ad_hoc_sku: l.is_ad_hoc ? (l.ad_hoc_sku || l.sku || null) : null,
    qty_received: Number(l.qty_received) || 0.01,
    qty_accepted: Number(l.qty_accepted ?? l.qty_received) || 0,
    qty_rejected: Number(l.qty_rejected) || 0,
    unit_cost_ht: Number(l.unit_cost_ht) || 0,
    storage_location_id: l.storage_location_id || form.default_location_id || null,
    lot_number: l.lot_number || null,
    expiry_date: l.expiry_date || null,
    qc_result: l.qc_result || 'passed',
    qc_notes: l.qc_notes || null,
  }))

  let body
  if (form.delivery_note_file instanceof File) {
    body = new FormData()
    body.append('po_id', form.po_id)
    if (form.supplier_id) body.append('supplier_id', form.supplier_id)
    body.append('received_by', form.received_by || currentUserId.value)
    body.append('received_at', form.received_at)
    if (form.supplier_delivery_note_ref) body.append('supplier_delivery_note_ref', form.supplier_delivery_note_ref)
    body.append('allow_over_receive', form.allow_over_receive ? '1' : '0')
    body.append('is_partial_delivery', form.is_partial_delivery ? '1' : '0')
    body.append('overall_qc_result', form.overall_qc_result || 'passed')
    body.append('delivery_note_file', form.delivery_note_file)

    mappedLines.forEach((line, i) => {
      Object.entries(line).forEach(([key, val]) => {
        if (val !== null && val !== undefined) {
          body.append(`lines[${i}][${key}]`, typeof val === 'boolean' ? (val ? '1' : '0') : String(val))
        }
      })
    })
  }
  else {
    body = {
      po_id: form.po_id,
      supplier_id: form.supplier_id,
      received_by: form.received_by || currentUserId.value,
      received_at: form.received_at,
      supplier_delivery_note_ref: form.supplier_delivery_note_ref || null,
      allow_over_receive: Boolean(form.allow_over_receive),
      is_partial_delivery: Boolean(form.is_partial_delivery),
      overall_qc_result: form.overall_qc_result || 'passed',
      lines: mappedLines,
    }
  }

  const created = await grnStore.create(body)

  emit('created', created)

  return created
}
</script>

<template>
  <WpWizardDialog
    :model-value="modelValue"
    variant="standard"
    :title="t('scp.grn.new_title')"
    :description="t('scp.grn.subtitle')"
    icon="tabler-package-import"
    :steps="steps"
    :initial-values="initialValues"
    :submit="handleSubmit"
    :submit-label="t('scp.action.save')"
    :success-message="t('scp.grn.snackbar.created_success')"
    max-width="980"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Step 1: Select PO & Receiving Context -->
    <template #step-po_context="{ form, errors, setValue }">
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VAutocomplete
            :model-value="form.po_id"
            :items="receivablePoOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.linked_po')"
            prepend-inner-icon="tabler-shopping-cart"
            :error-messages="errors.po_id"
            @update:model-value="onPoChanged($event, form, setValue)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.supplier_name"
            :label="t('scp.common.supplier')"
            prepend-inner-icon="tabler-building-store"
            readonly
          />
        </VCol>

        <VCol
          cols="12"
          sm="4"
        >
          <VAutocomplete
            :model-value="form.received_by"
            :items="userOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.received_by')"
            prepend-inner-icon="tabler-user-check"
            :error-messages="errors.received_by"
            @update:model-value="setValue('received_by', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            :model-value="form.received_at"
            type="date"
            :label="t('scp.grn.fields.received_at')"
            prepend-inner-icon="tabler-calendar"
            :error-messages="errors.received_at"
            @update:model-value="setValue('received_at', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="4"
        >
          <VAutocomplete
            :model-value="form.default_location_id"
            :items="siteLocationOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.storage_location')"
            prepend-inner-icon="tabler-building-warehouse"
            clearable
            @update:model-value="applyLocationToAll($event, form, setValue)"
          />
        </VCol>
      </VRow>
    </template>

    <!-- Step 2: Receive Quantities -->
    <template #step-lines="{ form }">
      <WpEmptyState
        v-if="!form.lines || form.lines.length === 0"
        :title="t('scp.grn.empty.no_lines')"
        :subtitle="t('scp.grn.empty.select_po')"
        icon="tabler-package-off"
        compact
      />

      <VTable
        v-else
        density="comfortable"
      >
        <thead>
          <tr>
            <th>{{ t('scp.common.item') }}</th>
            <th class="text-end">
              {{ t('scp.grn.qty.ordered') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.qty.received') }}
            </th>
            <th class="text-end">
              {{ t('scp.grn.qty.rejected') }}
            </th>
            <th>{{ t('scp.grn.fields.batch_number') }}</th>
            <th>{{ t('scp.grn.fields.storage_location') }}</th>
            <th>{{ t('scp.grn.fields.qc_result') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in form.lines"
            :key="line.po_line_id"
          >
            <td class="font-weight-medium">
              {{ line.item_name }}
            </td>
            <td class="text-end">
              {{ line.qty_ordered }}
            </td>
            <td style="inline-size: 110px;">
              <VTextField
                :model-value="line.qty_received"
                type="number"
                min="0.01"
                step="any"
                density="compact"
                hide-details
                @update:model-value="onReceivedChange(line, $event)"
              />
            </td>
            <td style="inline-size: 100px;">
              <VTextField
                :model-value="line.qty_rejected"
                type="number"
                min="0"
                step="any"
                density="compact"
                hide-details
                @update:model-value="onRejectedChange(line, $event)"
              />
            </td>
            <td style="inline-size: 130px;">
              <VTextField
                v-model="line.lot_number"
                density="compact"
                hide-details
                :placeholder="t('scp.grn.fields.batch_number')"
              />
            </td>
            <td style="inline-size: 170px;">
              <VSelect
                v-model="line.storage_location_id"
                :items="siteLocationOptions"
                item-title="title"
                item-value="value"
                density="compact"
                hide-details
                clearable
              />
            </td>
            <td style="inline-size: 150px;">
              <VSelect
                v-model="line.qc_result"
                :items="qcResultOptions"
                item-title="title"
                item-value="value"
                density="compact"
                hide-details
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </template>

    <!-- Step 3: Delivery Note & Confirm -->
    <template #step-delivery_note="{ form, setValue }">
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="form.supplier_delivery_note_ref"
            :label="t('scp.grn.fields.delivery_note_ref')"
            prepend-inner-icon="tabler-file-invoice"
            @update:model-value="setValue('supplier_delivery_note_ref', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            :model-value="form.overall_qc_result"
            :items="overallQcResultOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.grn.fields.overall_qc_result')"
            @update:model-value="setValue('overall_qc_result', $event)"
          />
        </VCol>

        <VCol cols="12">
          <WpAttachmentField
            :model-value="form.delivery_note_file"
            :label="t('scp.grn.fields.delivery_note_file', 'Delivery Note File (PDF / Image)')"
            category="certificate"
            accept=".pdf,image/jpeg,image/png"
            :max-size-mb="10"
            @update:model-value="setValue('delivery_note_file', $event)"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSwitch
            :model-value="form.is_partial_delivery"
            color="primary"
            :label="t('scp.grn.fields.partial_delivery')"
            hide-details
            @update:model-value="setValue('is_partial_delivery', Boolean($event))"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSwitch
            :model-value="form.allow_over_receive"
            color="warning"
            :label="t('scp.grn.fields.allow_over_receive')"
            hide-details
            @update:model-value="setValue('allow_over_receive', Boolean($event))"
          />
        </VCol>
      </VRow>
    </template>
  </WpWizardDialog>
</template>
