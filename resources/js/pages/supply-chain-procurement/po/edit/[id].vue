<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePoStore } from '@/stores/usePoStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  useDocumentForm,
  useDocumentTotals,
  useDocumentTraceability,
  useDocumentWorkflow,
} from '@/composables/documents'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

definePage({
  name: 'procurement-po-edit-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-po-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const poStore = usePoStore()
const supplierStore = useSupplierStore()
const { format: formatMoney } = useCurrency()
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  paymentTermsLabel,
  warehouseOptions,
  poPaymentTermsOptions,
  incotermsOptions,
  currencySelectOptions,
  ensureSuppliers,
  ensureWarehouses,
  fetchCatalogItemsForPicker,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const poId = computed(() => route.params.id)
const rawDocument = ref(null)
const activeSection = ref('overview')
const approvalDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

const normalizePoToForm = doc => ({
  id: doc?.id || null,
  po_number: doc?.po_number || '',
  status: doc?.status || 'draft',
  creation_path: doc?.creation_path || 'manual',
  rfq_id: doc?.rfq_id || null,
  supplier_id: doc?.supplier_id || doc?.supplier?.id || null,
  supplier: doc?.supplier || null,
  site_id: doc?.site_id || doc?.site?.id || null,
  delivery_address: doc?.delivery_address || '',
  currency: doc?.currency || 'TND',
  exchange_rate: doc?.exchange_rate ?? 1,
  payment_terms: doc?.payment_terms || 'net_30',
  incoterms: doc?.incoterms || '',
  committed_delivery_date: doc?.committed_delivery_date ? String(doc.committed_delivery_date).slice(0, 10) : '',
  approval_required: Boolean(doc?.approval_required),
  supplier_po_reference: doc?.supplier_po_reference || '',
  notes_to_supplier: doc?.notes_to_supplier || '',
  internal_notes: doc?.internal_notes || '',
  lines: (doc?.lines || []).map(l => {
    const qty = Number(l.qty_ordered ?? l.quantity ?? 1) || 1
    const price = Number(l.unit_price_ht ?? l.unit_price ?? 0) || 0
    const tax = Number(l.tax_rate_pct ?? l.tax_rate ?? 19) || 0
    const subtotal = qty * price

    return {
      ...l,
      item_name: resolveLineItemName(l),
      sku: resolveLineItemSku(l),
      quantity: qty,
      qty_ordered: qty,
      qty_received: Number(l.qty_received ?? 0) || 0,
      unit_price: price,
      unit_price_ht: price,
      tax_rate: tax,
      tax_rate_pct: tax,
      line_total: Number(l.line_total_ttc ?? subtotal + (subtotal * tax) / 100),
      description: l.description_snapshot || l.description || '',
      required_date: l.expected_delivery_date ? String(l.expected_delivery_date).slice(0, 10) : '',
    }
  }),
})

const {
  form,
  isDirty,
  isReadOnly,
  isLoading,
  isSaving,
  errors,
  fieldErrors,
  reset,
  save,
  guardDialog,
} = useDocumentForm({
  documentType: 'po',
  initialData: normalizePoToForm({}),
  loadFn: async () => {
    await Promise.all([ensureSuppliers(), ensureWarehouses()])

    const doc = await poStore.fetchPo(poId.value)

    rawDocument.value = doc

    return normalizePoToForm(doc)
  },
  saveFn: async current => {
    const payload = {
      supplier_id: current.supplier_id,
      site_id: current.site_id || null,
      delivery_address: current.delivery_address || null,
      currency: current.currency || 'TND',
      exchange_rate: Number(current.exchange_rate) || 1,
      payment_terms: current.payment_terms || 'net_30',
      incoterms: current.incoterms || null,
      committed_delivery_date: current.committed_delivery_date || null,
      approval_required: Boolean(current.approval_required),
      supplier_po_reference: current.supplier_po_reference || null,
      notes_to_supplier: current.notes_to_supplier || null,
      internal_notes: current.internal_notes || null,
      lines: (current.lines || []).map(l => ({
        is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
        catalog_item_id: l.catalog_item_id || null,
        ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || 'Ad-hoc Item') : null,
        ipr_line_id: l.ipr_line_id || null,
        description_snapshot: l.description || l.item_name || null,
        qty_ordered: Number(l.quantity ?? l.qty_ordered ?? 1) || 1,
        unit_price_ht: Number(l.unit_price ?? l.unit_price_ht ?? 0) || 0,
        tax_rate_pct: Number(l.tax_rate ?? l.tax_rate_pct ?? 19) || 0,
        expected_delivery_date: l.required_date || current.committed_delivery_date || null,
      })),
    }

    const updated = await poStore.updatePo(poId.value, payload)

    rawDocument.value = updated

    return normalizePoToForm(updated)
  },
})

onMounted(async () => {
  const doc = await poStore.fetchPo(poId.value)
  if (doc) {
    rawDocument.value = doc
    reset(normalizePoToForm(doc))
  }
  ensureSuppliers()
  ensureWarehouses()
})

const linesRef = computed(() => form.value.lines || [])

const { totals } = useDocumentTotals(linesRef, {
  preset: 'po',
  currency: computed(() => form.value.currency || 'TND'),
})

const workflow = useDocumentWorkflow(rawDocument, {
  documentType: 'po',
  transitionFn: async (targetStatus, meta = {}) => {
    submittingTransition.value = true
    try {
      const updated = await poStore.updateStatus(poId.value, targetStatus, {
        approval_note: meta.comment || null,
        rejection_reason: meta.reason || null,
      })

      rawDocument.value = updated
      reset(normalizePoToForm(updated))
      showSuccess(t('scp.po.snackbar.status_updated'))

      return updated
    }
    catch (err) {
      showError(err?.data?.message || t('scp.po.snackbar.error_status'))
      throw err
    }
    finally {
      submittingTransition.value = false
    }
  },
})

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { documentType: 'po' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const isReceivable = computed(() =>
  ['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(form.value.status),
)

const metadataItems = computed(() => [
  {
    key: 'supplier',
    icon: 'tabler-building-store',
    label: t('scp.common.supplier'),
    value: form.value.supplier?.legal_name || form.value.supplier?.trade_name || '—',
  },
  {
    key: 'payment_terms',
    icon: 'tabler-credit-card',
    label: t('wp.docs.terms.payment_terms'),
    value: paymentTermsLabel(form.value.payment_terms),
  },
  {
    key: 'delivery',
    icon: 'tabler-calendar',
    label: t('scp.po.fields.committed_delivery_date'),
    value: formatDate(form.value.committed_delivery_date),
  },
  {
    key: 'currency',
    icon: 'tabler-coin',
    label: t('scp.common.currency'),
    value: `${form.value.currency || 'TND'} (×${form.value.exchange_rate || 1})`,
  },
])

const primaryHeaderAction = computed(() => {
  const st = form.value.status
  if (st === 'draft') {
    return {
      key: form.value.approval_required ? 'submit_approval' : 'approve',
      label: form.value.approval_required ? t('scp.ipr.actions.submit_approval') : t('scp.ipr.actions.approve'),
      icon: form.value.approval_required ? 'tabler-send' : 'tabler-check',
      color: 'primary',
      loading: submittingTransition.value,
    }
  }
  if (st === 'pending_approval') {
    return {
      key: 'decide',
      label: t('wp.docs.approval.take_decision'),
      icon: 'tabler-gavel',
      color: 'primary',
    }
  }
  if (st === 'approved') {
    return {
      key: 'send',
      label: t('scp.po.actions.mark_sent'),
      icon: 'tabler-send',
      color: 'primary',
      loading: submittingTransition.value,
    }
  }
  if (isReceivable.value) {
    return {
      key: 'receive_grn',
      label: t('scp.po.actions.create_grn'),
      icon: 'tabler-package-import',
      color: 'success',
    }
  }

  return null
})

const secondaryHeaderActions = computed(() => [
  {
    key: 'save',
    label: t('scp.action.save'),
    icon: 'tabler-device-floppy',
    color: 'primary',
    disabled: isReadOnly.value || !isDirty.value,
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'acknowledge',
    label: t('scp.po.actions.mark_acknowledged'),
    icon: 'tabler-eye-check',
    color: 'info',
    hidden: form.value.status !== 'sent',
  },
  {
    key: 'in_delivery',
    label: t('scp.po.actions.mark_in_delivery'),
    icon: 'tabler-truck',
    color: 'warning',
    hidden: !['sent', 'acknowledged'].includes(form.value.status),
  },
  {
    key: 'receive_grn',
    label: t('scp.po.actions.create_grn'),
    icon: 'tabler-package-import',
    color: 'success',
    hidden: !isReceivable.value,
  },
  {
    key: 'match',
    label: t('scp.action.open_match_page'),
    icon: 'tabler-git-compare',
    color: 'info',
  },
  {
    key: 'close',
    label: t('scp.po.actions.close_po'),
    icon: 'tabler-lock',
    color: 'secondary',
    hidden: !['partially_received', 'fully_received'].includes(form.value.status),
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: ['partially_received', 'fully_received', 'closed', 'cancelled'].includes(form.value.status),
  },
  {
    key: 'delete',
    label: t('scp.action.delete'),
    icon: 'tabler-trash',
    color: 'error',
    hidden: !['draft', 'cancelled'].includes(form.value.status),
  },
])

const handleSave = async () => {
  try {
    await save()
    showSuccess(t('scp.po.snackbar.updated_success'))
  }
  catch (err) {
    showError(err?.data?.message || t('scp.po.snackbar.error_save'))
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'submit_approval') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('pending_approval')
  }
  else if (key === 'approve') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('approved')
  }
  else if (key === 'decide') {
    approvalDialogOpen.value = true
  }
  else if (key === 'send') {
    await workflow.transitionTo('sent')
  }
  else if (key === 'acknowledge') {
    await workflow.transitionTo('acknowledged')
  }
  else if (key === 'in_delivery') {
    await workflow.transitionTo('in_delivery')
  }
  else if (key === 'receive_grn') {
    router.push({ path: '/supply-chain-procurement/grn/list', query: { from_po: poId.value } })
  }
  else if (key === 'match') {
    router.push(`/supply-chain-procurement/po/match/${poId.value}`)
  }
  else if (key === 'close') {
    await workflow.transitionTo('closed')
  }
  else if (key === 'cancel') {
    await workflow.transitionTo('cancelled')
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
  }
}

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

const onSupplierChange = partner => {
  form.value.supplier = partner
  form.value.supplier_id = partner?.id || null
}

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
    qty_received: 0,
    unit_price: price,
    unit_price_ht: price,
    tax_rate: tax,
    tax_rate_pct: tax,
    line_total: subtotal + (subtotal * tax) / 100,
    description: '',
  }
}

const approvalSteps = computed(() => {
  const doc = rawDocument.value
  if (!doc) return []
  const list = []
  if (doc.status === 'pending_approval') {
    list.push({
      key: 'pending',
      label: t('scp.ipr.sections.approval'),
      approver: { name: t('scp.ipr.kpi.pending_approval') },
      decision: 'pending',
    })
  }
  if (doc.approved_by || ['approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received', 'fully_received', 'closed'].includes(doc.status)) {
    list.push({
      key: 'approved',
      label: t('scp.ipr.sections.approval'),
      approver: { name: doc.approver?.name || '—' },
      decision: 'approved',
      at: doc.approved_at,
      comment: doc.approval_note,
    })
  }

  return list
})

const onApprovalDecision = async ({ decision, reason, comment }) => {
  const target = decision === 'approved' ? 'approved' : 'cancelled'

  await workflow.transitionTo(target, { reason, comment })
  approvalDialogOpen.value = false
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await poStore.deletePo(poId.value)
    showSuccess(t('scp.po.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/po/list')
  }
  catch (err) {
    showError(err?.data?.message || t('scp.po.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const totalOrderedQty = computed(() =>
  form.value.lines.reduce((s, l) => s + (Number(l.qty_ordered ?? l.quantity ?? 0) || 0), 0),
)

const totalReceivedQty = computed(() =>
  form.value.lines.reduce((s, l) => s + (Number(l.qty_received ?? 0) || 0), 0),
)

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.po.sections.overview'), icon: 'tabler-building-store' },
  { id: 'lines', title: t('scp.po.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'terms', title: t('wp.docs.terms.title'), icon: 'tabler-file-text' },
  { id: 'approval', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])
</script>

<template>
  <div class="po-edit-page">
    <WpDocumentHeader
      :title="form.po_number || t('scp.po.title')"
      :reference="form.po_number"
      :subtitle="form.supplier?.legal_name || form.supplier?.trade_name || t('scp.po.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po'), to: '/supply-chain-procurement/po/list' },
        { title: form.po_number || t('scp.action.edit') },
      ]"
      :primary-action="primaryHeaderAction"
      :actions="secondaryHeaderActions"
      class="mb-4"
      @action="onHeaderAction"
    />

    <WpDocumentLifecycleStepper
      :steps="lifecycleSteps"
      class="mb-4"
    />

    <WpDocumentMetadataBar
      :items="metadataItems"
      class="mb-6"
    />

    <WpKpiGroup class="mb-6">
      <WpKpiCard
        :title="t('wp.docs.totals.subtotal')"
        :value="formatMoney(totals.subtotal, { currency: form.currency })"
        icon="tabler-calculator"
        color="info"
      />
      <WpKpiCard
        :title="t('wp.docs.totals.tax')"
        :value="formatMoney(totals.taxTotal, { currency: form.currency })"
        icon="tabler-receipt-tax"
        color="warning"
      />
      <WpKpiCard
        :title="t('wp.docs.totals.grand_total')"
        :value="formatMoney(totals.grandTotal, { currency: form.currency })"
        icon="tabler-coins"
        color="success"
      />
      <WpKpiCard
        :title="t('scp.po.fields.receiving_progress')"
        :value="`${totalReceivedQty} / ${totalOrderedQty}`"
        icon="tabler-package-import"
        color="primary"
      />
    </WpKpiGroup>

    <WpJournalNav
      v-model="activeSection"
      :sections="journalSections"
    >
      <!-- Section 1: Supplier & Delivery Site -->
      <section
        id="section-overview"
        class="mb-6"
      >
        <WpSectionCard
          :title="t('scp.po.sections.overview')"
          icon="tabler-building-store"
        >
          <template
            v-if="!isReadOnly"
            #actions
          >
            <VBtn
              color="primary"
              size="small"
              prepend-icon="tabler-device-floppy"
              :loading="isSaving"
              :disabled="!isDirty"
              min-height="40"
              @click="handleSave"
            >
              {{ t('scp.action.save') }}
            </VBtn>
          </template>

          <VRow>
            <VCol
              cols="12"
              md="6"
            >
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
                :locked="isReadOnly"
                :readonly="isReadOnly"
                @change="onSupplierChange"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VRow>
                <VCol cols="12">
                  <VAutocomplete
                    v-model="form.site_id"
                    :items="warehouseOptions"
                    item-title="title"
                    item-value="value"
                    :label="t('scp.common.warehouse')"
                    prepend-inner-icon="tabler-building-warehouse"
                    :readonly="isReadOnly"
                    clearable
                    :error-messages="fieldErrors('site_id')"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextField
                    v-model="form.delivery_address"
                    :label="t('scp.po.fields.delivery_address')"
                    prepend-inner-icon="tabler-map-pin"
                    :readonly="isReadOnly"
                    :error-messages="fieldErrors('delivery_address')"
                  />
                </VCol>
                <VCol cols="12">
                  <VSwitch
                    v-model="form.approval_required"
                    color="primary"
                    :label="t('scp.po.fields.approval_required')"
                    :readonly="isReadOnly"
                    hide-details
                  />
                </VCol>
              </VRow>
            </VCol>
          </VRow>
        </WpSectionCard>
      </section>

      <!-- Section 2: Line Items & Financial Summary -->
      <section
        id="section-lines"
        class="mb-6"
      >
        <WpDocumentLinesCard
          v-model:lines="form.lines"
          :currency="form.currency"
          :title="t('scp.po.sections.lines')"
          :readonly="isReadOnly"
          :loading="isLoading"
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
          class="mb-4"
        />

        <WpDocumentTotalsCard
          :totals="{
            subtotal: totals.subtotal,
            discount_total: totals.discountTotal,
            tax_breakdown: totals.taxBreakdown,
            tax_total: totals.taxTotal,
            grand_total: totals.grandTotal,
          }"
          :currency="form.currency"
        />
      </section>

      <!-- Section 3: Commercial Terms -->
      <section
        id="section-terms"
        class="mb-6"
      >
        <WpCommercialTermsSection
          v-model="form"
          :readonly="isReadOnly"
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
        />
      </section>

      <!-- Section 4: Approval, GRN Conversion & 3-Way Match -->
      <section
        id="section-approval"
        class="mb-6"
      >
        <div class="d-flex flex-wrap gap-2 mb-4">
          <VBtn
            v-if="isReceivable"
            color="success"
            prepend-icon="tabler-package-import"
            min-height="44"
            @click="router.push({ path: '/supply-chain-procurement/grn/list', query: { from_po: poId } })"
          >
            {{ t('scp.po.actions.create_grn') }}
          </VBtn>
          <VBtn
            color="info"
            variant="tonal"
            prepend-icon="tabler-git-compare"
            min-height="44"
            @click="router.push(`/supply-chain-procurement/po/match/${poId}`)"
          >
            {{ t('scp.action.open_match_page') }}
          </VBtn>
        </div>

        <WpDocumentApprovalSection
          v-model:dialog-open="approvalDialogOpen"
          :title="t('scp.ipr.sections.approval')"
          :steps="approvalSteps"
          :can-decide="form.status === 'pending_approval'"
          :allowed-decisions="['approved', 'rejected']"
          :submitting="submittingTransition"
          class="mb-6"
          @decision="onApprovalDecision"
        />

        <WpDocumentTraceabilitySection
          :title="t('scp.ipr.sections.traceability')"
          :chain="traceabilityChain"
        />
      </section>
    </WpJournalNav>

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="form.po_number"
      :loading="deleting"
      @confirm="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="guardDialog.open"
      @confirm="guardDialog.confirm()"
      @cancel="guardDialog.cancel()"
    />
  </div>
</template>

<style scoped lang="scss">
.po-edit-page {
  overflow-x: clip;
}
</style>
