<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useIprStore } from '@/stores/useIprStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useJournalNav } from '@/composables/useJournalNav'
import {
  useDocumentForm,
  useDocumentTotals,
  useDocumentTraceability,
  useDocumentWorkflow,
} from '@/composables/documents'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import ConvertIprDialog from '@/views/supply-chain-procurement/ipr/ConvertIprDialog.vue'

definePage({
  name: 'procurement-ipr-edit-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-ipr-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const iprStore = useIprStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  urgencyLabel,
  triggerSourceLabel,
  urgencySelectOptions,
  triggerSourceOptions,
  userOptions,
  supplierOptions,
  currentUserId,
  ensureUsers,
  ensureSuppliers,
  fetchCatalogItemsForPicker,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const iprId = computed(() => route.params.id)
const isCreateMode = computed(() => iprId.value === 'new')
const rawDocument = ref(null)
const convertDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const approvalDialogOpen = ref(false)
const submittingTransition = ref(false)

const normalizeIprToForm = doc => ({
  id: doc?.id || null,
  reference: doc?.reference || '',
  status: doc?.status || 'draft',
  trigger_source: doc?.trigger_source || 'manual_user',
  trigger_reference: doc?.trigger_reference || '',
  requested_by: doc?.requested_by || doc?.requested_by_user?.id || doc?.requester?.id || currentUserId.value || '',
  assigned_to: doc?.assigned_to || doc?.assigned_to_user?.id || doc?.assignee?.id || null,
  urgency: doc?.urgency || 'normal',
  needed_by_date: doc?.needed_by_date ? String(doc.needed_by_date).slice(0, 10) : '',
  internal_justification: doc?.internal_justification || '',
  lines: (doc?.lines || []).map(l => {
    const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
    const price = Number(l.estimated_unit_cost ?? l.unit_price ?? 0) || 0

    return {
      ...l,
      id: l.id || null,
      variant_id: l.variant_id || l.variant?.id || null,
      is_ad_hoc: Boolean(l.is_ad_hoc || (!l.catalog_item_id && l.ad_hoc_name)),
      ad_hoc_name: l.ad_hoc_name || '',
      ad_hoc_sku: l.ad_hoc_sku || '',
      item_name: resolveLineItemName(l),
      sku: resolveLineItemSku(l),
      quantity: qty,
      qty_requested: qty,
      qty_current_stock: l.qty_current_stock ?? l.current_stock ?? 0,
      suggested_supplier_id: l.suggested_supplier_id || l.suggested_supplier?.id || null,
      unit_price: price,
      estimated_unit_cost: price,
      line_total: Number(l.estimated_total_ht) || qty * price,
      description: l.ad_hoc_description || l.description || '',
    }
  }),
})

const {
  document: form,
  loading: isLoading,
  saving: isSaving,
  isDirty,
  isReadonly: isReadOnly,
  errors,
  fieldErrors,
  setDocument,
  applyServerErrors,
  unsavedGuard,
} = useDocumentForm({
  initial: () => normalizeIprToForm({}),
  lockedStatuses: ['pending_approval', 'approved', 'merged', 'converted_to_po', 'cancelled'],
  unsavedGuard: true,
})

const loadIpr = async () => {
  isLoading.value = true
  try {
    await Promise.all([ensureUsers(), ensureSuppliers()])

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizeIprToForm({}))

      return
    }

    const doc = await iprStore.fetchDetails(iprId.value)
    if (doc) {
      rawDocument.value = doc
      setDocument(normalizeIprToForm(doc))
    }
  }
  catch (err) {
    showApiError(err, t('scp.ipr.snackbar.error_load', 'Failed to load purchase request'))
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadIpr)

watch(iprId, (next, prev) => {
  if (next && next !== prev) loadIpr()
})

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { fields: 'ipr' })

const workflow = useDocumentWorkflow(rawDocument, { config: 'ipr' })

const executeTransition = async (targetStatus, extraPayload = {}) => {
  if (isCreateMode.value || !iprId.value) return null
  submittingTransition.value = true
  try {
    const updated = await iprStore.updateStatus(iprId.value, {
      status: targetStatus,
      ...extraPayload,
    })

    rawDocument.value = updated
    setDocument(normalizeIprToForm(updated))
    success(t('scp.ipr.snackbar.status_updated'))

    return updated
  }
  catch (err) {
    showApiError(err, t('scp.ipr.snackbar.error_status'))
    throw err
  }
  finally {
    submittingTransition.value = false
  }
}

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { type: 'ipr' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const metadataItems = computed(() => [
  {
    key: 'requester',
    icon: 'tabler-user',
    label: t('scp.ipr.fields.requested_by'),
    value: rawDocument.value?.requested_by_user?.name || rawDocument.value?.requester?.name || '—',
  },
  {
    key: 'assignee',
    icon: 'tabler-user-check',
    label: t('scp.ipr.fields.assigned_to'),
    value: rawDocument.value?.assigned_to_user?.name || rawDocument.value?.assignee?.name || '—',
  },
  {
    key: 'trigger',
    icon: 'tabler-bolt',
    label: t('scp.ipr.fields.trigger_source'),
    value: triggerSourceLabel(form.value.trigger_source),
  },
  {
    key: 'needed_by',
    icon: 'tabler-calendar',
    label: t('scp.ipr.fields.needed_by_date'),
    value: formatDate(form.value.needed_by_date),
  },
  {
    key: 'created_at',
    icon: 'tabler-clock',
    label: t('scp.common.created_at'),
    value: formatDate(rawDocument.value?.created_at),
  },
])

const primaryHeaderAction = computed(() => {
  if (isCreateMode.value) {
    return {
      key: 'save',
      label: t('scp.action.save'),
      icon: 'tabler-device-floppy',
      color: 'primary',
      loading: isSaving.value,
    }
  }
  const st = form.value.status
  if (st === 'draft' || st === 'rejected') {
    return {
      key: 'submit',
      label: t('scp.ipr.actions.submit_approval'),
      icon: 'tabler-send',
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
      loading: submittingTransition.value,
    }
  }
  if (st === 'approved') {
    return {
      key: 'convert',
      label: t('scp.ipr.actions.convert'),
      icon: 'tabler-transform',
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
    disabled: isReadOnly.value || (!isDirty.value && !isCreateMode.value),
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'convert',
    label: t('scp.ipr.actions.convert'),
    icon: 'tabler-transform',
    color: 'info',
    hidden: isCreateMode.value || form.value.status !== 'approved',
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: isCreateMode.value || ['cancelled', 'converted_to_po', 'merged'].includes(form.value.status),
  },
  {
    key: 'delete',
    label: t('scp.action.delete'),
    icon: 'tabler-trash',
    color: 'error',
    hidden: isCreateMode.value || !['draft', 'cancelled'].includes(form.value.status),
  },
])

const buildSavePayload = current => ({
  trigger_source: current.trigger_source || 'manual_user',
  trigger_reference: current.trigger_reference || null,
  requested_by: current.requested_by || currentUserId.value,
  assigned_to: current.assigned_to || null,
  urgency: current.urgency || 'normal',
  needed_by_date: current.needed_by_date || null,
  internal_justification: current.internal_justification || null,
  lines: (current.lines || []).map(l => {
    const isAdHoc = Boolean(l.is_ad_hoc || !l.catalog_item_id)

    return {
      ...(l.id ? { id: l.id } : {}),
      is_ad_hoc: isAdHoc,
      catalog_item_id: isAdHoc ? null : (l.catalog_item_id || null),
      variant_id: isAdHoc ? null : (l.variant_id || null),
      ad_hoc_name: isAdHoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || l.sku || null) : null,
      qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
      qty_current_stock: l.qty_current_stock !== null && l.qty_current_stock !== undefined && l.qty_current_stock !== ''
        ? Number(l.qty_current_stock)
        : 0,
      suggested_supplier_id: l.suggested_supplier_id || null,
      estimated_unit_cost: Number(l.unit_price ?? l.estimated_unit_cost ?? 0) || 0,
    }
  }),
})

const handleSave = async () => {
  isSaving.value = true
  try {
    const payload = buildSavePayload(form.value)
    if (isCreateMode.value) {
      const created = await iprStore.create(payload)

      rawDocument.value = created
      setDocument(normalizeIprToForm(created))
      success(t('scp.ipr.snackbar.created_success'))
      await router.replace(`/supply-chain-procurement/ipr/edit/${created.id}`)

      return created
    }

    const updated = await iprStore.update(iprId.value, payload)

    rawDocument.value = updated
    setDocument(normalizeIprToForm(updated))
    success(t('scp.ipr.snackbar.updated_success'))

    return updated
  }
  catch (err) {
    applyServerErrors(err)
    showApiError(err, t('scp.ipr.snackbar.error_save'))
    throw err
  }
  finally {
    isSaving.value = false
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'submit') {
    if (isDirty.value) await handleSave()
    await executeTransition('pending_approval')
  }
  else if (key === 'decide') {
    approvalDialogOpen.value = true
  }
  else if (key === 'convert') {
    convertDialogOpen.value = true
  }
  else if (key === 'cancel') {
    await executeTransition('cancelled')
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
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
      approver: { name: doc.assigned_to_user?.name || doc.assignee?.name || t('scp.ipr.kpi.pending_approval') },
      decision: 'pending',
    })
  }
  if (doc.approved_by || doc.status === 'approved') {
    list.push({
      key: 'approved',
      label: t('scp.ipr.sections.approval'),
      approver: { name: doc.approved_by_user?.name || doc.approver?.name || '—' },
      decision: 'approved',
      at: doc.approved_at,
      comment: doc.approval_note,
    })
  }
  if (doc.status === 'rejected' || doc.rejection_reason) {
    list.push({
      key: 'rejected',
      label: t('scp.ipr.sections.approval'),
      approver: { name: doc.approved_by_user?.name || doc.approver?.name || '—' },
      decision: 'rejected',
      at: doc.approved_at || doc.updated_at,
      reason: doc.rejection_reason,
      comment: doc.approval_note,
    })
  }

  return list
})

const onApprovalDecision = async ({ decision, reason, comment }) => {
  const target = decision === 'approved' ? 'approved' : 'rejected'

  await executeTransition(target, {
    rejection_reason: reason || null,
    approval_note: comment || null,
  })
  approvalDialogOpen.value = false
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await iprStore.deleteItem(iprId.value)
    success(t('scp.ipr.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/ipr/list')
  }
  catch (err) {
    showApiError(err, t('scp.ipr.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const mapCatalogToIprLine = (item, quantity = 1) => {
  const cost = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const qty = Number(quantity) || 1

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    variant_id: item.variant_id || null,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || item.code || '',
    unit: item.unit || '',
    quantity: qty,
    qty_requested: qty,
    qty_current_stock: Number(item.current_stock ?? 0) || 0,
    suggested_supplier_id: item.raw?.preferred_supplier_id || item.raw?.preferred_supplier?.id || null,
    unit_price: cost,
    estimated_unit_cost: cost,
    line_total: qty * cost,
    description: '',
  }
}

const lineColumns = computed(() => [
  { key: 'item', title: t('scp.common.item') },
  { key: 'sku', title: t('scp.common.sku', 'Variant / SKU'), width: '140px' },
  { key: 'qty_current_stock', title: t('scp.ipr.fields.current_stock', 'Current Stock'), align: 'end', width: '120px' },
  { key: 'suggested_supplier_id', title: t('scp.ipr.fields.suggested_supplier', 'Suggested Supplier'), width: '210px' },
  { key: 'quantity', title: t('scp.ipr.fields.qty_requested'), align: 'end', width: '130px' },
  { key: 'unit_price', title: t('scp.ipr.fields.estimated_unit_cost'), align: 'end', width: '150px' },
  { key: 'line_total', title: t('scp.common.total'), align: 'end', width: '140px' },
])

const supplierNameById = id => {
  if (!id) return '—'
  const opt = supplierOptions.value.find(s => s.value === id)

  return opt ? opt.title : resolveSupplierName({ id })
}

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.ipr.sections.overview'), icon: 'tabler-info-circle' },
  { id: 'lines', title: t('scp.ipr.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'approval', title: t('scp.ipr.sections.approval'), icon: 'tabler-checklist' },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])

const { activeSection, scrollToSection } = useJournalNav(journalSections, { idPrefix: 'section-' })
</script>

<template>
  <div class="ipr-edit-page">
    <WpDocumentHeader
      :title="form.reference || t('scp.ipr.new_title', 'New Purchase Request')"
      :reference="form.reference"
      :subtitle="t('scp.ipr.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :priority="form.urgency"
      :priority-label="urgencyLabel(form.urgency)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.ipr'), to: '/supply-chain-procurement/ipr/list' },
        { title: form.reference || t('scp.action.edit') },
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
        :title="t('scp.common.lines')"
        :value="form.lines.length"
        icon="tabler-list-numbers"
        color="primary"
      />
      <WpKpiCard
        :title="t('scp.ipr.fields.estimated_total')"
        :value="formatMoney(totals.grandTotal)"
        icon="tabler-coins"
        color="success"
      />
      <WpKpiCard
        :title="t('scp.ipr.fields.urgency')"
        :value="urgencyLabel(form.urgency)"
        icon="tabler-flag"
        color="warning"
      />
      <WpKpiCard
        :title="t('scp.common.status')"
        :value="statusLabel(form.status)"
        icon="tabler-activity"
        color="info"
      />
    </WpKpiGroup>

    <div class="scp-edit-layout">
      <WpJournalNav
        v-model="activeSection"
        :sections="journalSections"
        :title="form.reference || t('scp.ipr.title')"
        :subtitle="statusLabel(form.status)"
        id-prefix="section-"
        @update:model-value="scrollToSection($event)"
      >
        <template #footer>
          <div v-if="!isReadOnly">
            <VBtn
              block
              color="primary"
              prepend-icon="tabler-device-floppy"
              :loading="isSaving"
              :disabled="!isDirty && !isCreateMode"
              class="mb-2 font-weight-semibold"
              @click="handleSave"
            >
              {{ t('scp.action.save') }}
            </VBtn>
            <VBtn
              v-if="!isCreateMode && ['draft', 'cancelled'].includes(form.status)"
              block
              variant="tonal"
              color="error"
              prepend-icon="tabler-trash"
              @click="deleteDialogOpen = true"
            >
              {{ t('scp.action.delete') }}
            </VBtn>
          </div>
        </template>
      </WpJournalNav>

      <main class="scp-journal-feed">
        <!-- Section 1: Overview -->
        <section
          id="section-overview"
          class="mb-6"
        >
          <WpSectionCard
            :title="t('scp.ipr.sections.overview')"
            icon="tabler-info-circle"
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
                :disabled="!isDirty && !isCreateMode"
                min-height="40"
                @click="handleSave"
              >
                {{ t('scp.action.save') }}
              </VBtn>
            </template>

            <VRow>
              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VAutocomplete
                  v-model="form.requested_by"
                  :items="userOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.ipr.fields.requested_by')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('requested_by')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VAutocomplete
                  v-model="form.assigned_to"
                  :items="userOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.ipr.fields.assigned_to')"
                  :readonly="isReadOnly"
                  clearable
                  :error-messages="fieldErrors('assigned_to')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VSelect
                  v-model="form.urgency"
                  :items="urgencySelectOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.ipr.fields.urgency')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('urgency')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VTextField
                  v-model="form.needed_by_date"
                  type="date"
                  :label="t('scp.ipr.fields.needed_by_date')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('needed_by_date')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VSelect
                  v-model="form.trigger_source"
                  :items="triggerSourceOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.ipr.fields.trigger_source')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('trigger_source')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <VTextField
                  v-model="form.trigger_reference"
                  :label="t('scp.ipr.fields.trigger_reference')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('trigger_reference')"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.internal_justification"
                  :label="t('scp.ipr.fields.internal_justification')"
                  rows="3"
                  auto-grow
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('internal_justification')"
                />
              </VCol>
            </VRow>
          </WpSectionCard>
        </section>

        <!-- Section 2: Requested Items -->
        <section
          id="section-lines"
          class="mb-6"
        >
          <WpDocumentLinesCard
            v-model:lines="form.lines"
            :title="t('scp.ipr.sections.lines')"
            :readonly="isReadOnly"
            :loading="isLoading"
            :columns="lineColumns"
            :fields="['item', 'description', 'quantity', 'unit', 'unit_price']"
            :field-labels="{
              quantity: t('scp.ipr.fields.qty_requested'),
              unit_price: t('scp.ipr.fields.estimated_unit_cost'),
            }"
            :totals="{
              subtotal: totals.subtotal,
              grand_total: totals.grandTotal,
            }"
            show-catalog-picker
            show-scan
            :fetch-catalog-items="fetchCatalogItemsForPicker"
            :map-catalog-item="mapCatalogToIprLine"
            :errors="errors"
          >
            <template #cell-sku="{ line }">
              <VChip
                v-if="line.sku || line.ad_hoc_sku"
                size="x-small"
                variant="tonal"
                color="secondary"
                label
              >
                {{ line.sku || line.ad_hoc_sku }}
              </VChip>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <template #cell-qty_current_stock="{ line }">
              <VTextField
                v-if="!isReadOnly"
                v-model.number="line.qty_current_stock"
                type="number"
                min="0"
                step="any"
                density="compact"
                variant="outlined"
                hide-details
                style="max-inline-size: 100px;"
              />
              <span v-else>{{ line.qty_current_stock ?? 0 }}</span>
            </template>

            <template #cell-suggested_supplier_id="{ line }">
              <VAutocomplete
                v-if="!isReadOnly"
                v-model="line.suggested_supplier_id"
                :items="supplierOptions"
                item-title="title"
                item-value="value"
                density="compact"
                variant="outlined"
                hide-details
                clearable
                :placeholder="t('scp.common.supplier')"
              />
              <span v-else>{{ supplierNameById(line.suggested_supplier_id) }}</span>
            </template>
          </WpDocumentLinesCard>
        </section>

        <!-- Section 3: Approval & Workflow -->
        <section
          id="section-approval"
          class="mb-6"
        >
          <WpDocumentApprovalSection
            v-model:dialog-open="approvalDialogOpen"
            :title="t('scp.ipr.sections.approval')"
            :steps="approvalSteps"
            :can-decide="form.status === 'pending_approval'"
            :allowed-decisions="['approved', 'rejected']"
            :submitting="submittingTransition"
            @decision="onApprovalDecision"
          />
        </section>

        <!-- Section 4: Traceability -->
        <section
          id="section-traceability"
          class="mb-6"
        >
          <WpDocumentTraceabilitySection
            :title="t('scp.ipr.sections.traceability')"
            :items="traceabilityChain"
          />
        </section>
      </main>
    </div>

    <ConvertIprDialog
      v-model="convertDialogOpen"
      :ipr="rawDocument"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="form.reference"
      :loading="deleting"
      @confirm="handleDelete"
    />

    <WpUnsavedChangesDialog
      :model-value="unsavedGuard?.isDialogVisible?.value"
      @confirm="unsavedGuard?.confirmLeave()"
      @update:model-value="v => !v && unsavedGuard?.cancelLeave()"
    />
  </div>
</template>

<style scoped lang="scss">
.ipr-edit-page {
  overflow-x: clip;
}

.scp-edit-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
}

.scp-journal-feed {
  flex: 1 1 auto;
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .scp-edit-layout {
    flex-direction: column;
  }

  .scp-journal-feed {
    inline-size: 100%;
  }
}
</style>
