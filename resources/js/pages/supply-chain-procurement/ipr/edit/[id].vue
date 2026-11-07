<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useIprStore } from '@/stores/useIprStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
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
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  urgencyLabel,
  triggerSourceLabel,
  urgencySelectOptions,
  triggerSourceOptions,
  userOptions,
  ensureUsers,
  fetchCatalogItemsForPicker,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const iprId = computed(() => route.params.id)
const rawDocument = ref(null)
const activeSection = ref('overview')
const convertDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const approvalDialogOpen = ref(false)
const submittingTransition = ref(false)

const normalizeIprToForm = doc => ({
  id: doc?.id || null,
  ipr_number: doc?.ipr_number || '',
  status: doc?.status || 'draft',
  trigger_source: doc?.trigger_source || 'manual_user',
  trigger_reference: doc?.trigger_reference || '',
  requested_by: doc?.requested_by || doc?.requester?.id || '',
  assigned_to: doc?.assigned_to || doc?.assignee?.id || null,
  urgency: doc?.urgency || 'normal',
  needed_by_date: doc?.needed_by_date ? String(doc.needed_by_date).slice(0, 10) : '',
  internal_justification: doc?.internal_justification || '',
  lines: (doc?.lines || []).map(l => {
    const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
    const price = Number(l.estimated_unit_cost ?? l.unit_price ?? 0) || 0

    return {
      ...l,
      item_name: resolveLineItemName(l),
      sku: resolveLineItemSku(l),
      quantity: qty,
      qty_requested: qty,
      unit_price: price,
      estimated_unit_cost: price,
      line_total: qty * price,
      description: l.ad_hoc_description || l.description || '',
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
  documentType: 'ipr',
  initialData: normalizeIprToForm({}),
  loadFn: async () => {
    await ensureUsers()

    const doc = await iprStore.fetchIpr(iprId.value)

    rawDocument.value = doc

    return normalizeIprToForm(doc)
  },
  saveFn: async current => {
    const payload = {
      trigger_source: current.trigger_source,
      trigger_reference: current.trigger_reference || null,
      requested_by: current.requested_by,
      assigned_to: current.assigned_to || null,
      urgency: current.urgency,
      needed_by_date: current.needed_by_date || null,
      internal_justification: current.internal_justification || null,
      lines: (current.lines || []).map(l => ({
        is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
        catalog_item_id: l.catalog_item_id || null,
        ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || 'Ad-hoc Item') : null,
        ad_hoc_sku: !l.catalog_item_id ? (l.sku || l.ad_hoc_sku || null) : null,
        ad_hoc_description: l.description || null,
        qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
        estimated_unit_cost: Number(l.unit_price ?? l.estimated_unit_cost ?? 0) || 0,
      })),
    }

    const updated = await iprStore.updateIpr(iprId.value, payload)

    rawDocument.value = updated

    return normalizeIprToForm(updated)
  },
})

onMounted(async () => {
  const doc = await iprStore.fetchIpr(iprId.value)
  if (doc) {
    rawDocument.value = doc
    reset(normalizeIprToForm(doc))
  }
  ensureUsers()
})

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { preset: 'ipr' })

const workflow = useDocumentWorkflow(rawDocument, {
  documentType: 'ipr',
  transitionFn: async (targetStatus, meta = {}) => {
    submittingTransition.value = true
    try {
      const updated = await iprStore.updateStatus(iprId.value, targetStatus, {
        approval_note: meta.comment || null,
        rejection_reason: meta.reason || null,
      })

      rawDocument.value = updated
      reset(normalizeIprToForm(updated))
      showSuccess(t('scp.ipr.snackbar.status_updated'))

      return updated
    }
    catch (err) {
      showError(err?.data?.message || t('scp.ipr.snackbar.error_status'))
      throw err
    }
    finally {
      submittingTransition.value = false
    }
  },
})

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { documentType: 'ipr' })

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
    value: rawDocument.value?.requester?.name || '—',
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
    disabled: isReadOnly.value || !isDirty.value,
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'convert',
    label: t('scp.ipr.actions.convert'),
    icon: 'tabler-transform',
    color: 'info',
    hidden: form.value.status !== 'approved',
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: ['cancelled', 'converted_to_po', 'merged'].includes(form.value.status),
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
    showSuccess(t('scp.ipr.snackbar.updated_success'))
  }
  catch (err) {
    showError(err?.data?.message || t('scp.ipr.snackbar.error_save'))
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'submit') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('pending_approval')
  }
  else if (key === 'decide') {
    approvalDialogOpen.value = true
  }
  else if (key === 'convert') {
    convertDialogOpen.value = true
  }
  else if (key === 'cancel') {
    await workflow.transitionTo('cancelled')
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
      approver: { name: doc.assignee?.name || t('scp.ipr.kpi.pending_approval') },
      decision: 'pending',
    })
  }
  if (doc.approved_by || doc.status === 'approved') {
    list.push({
      key: 'approved',
      label: t('scp.ipr.sections.approval'),
      approver: { name: doc.approver?.name || '—' },
      decision: 'approved',
      at: doc.approved_at,
      comment: doc.approval_note,
    })
  }
  if (doc.status === 'rejected' || doc.rejection_reason) {
    list.push({
      key: 'rejected',
      label: t('scp.ipr.sections.approval'),
      approver: { name: doc.approver?.name || '—' },
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

  await workflow.transitionTo(target, { reason, comment })
  approvalDialogOpen.value = false
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await iprStore.deleteIpr(iprId.value)
    showSuccess(t('scp.ipr.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/ipr/list')
  }
  catch (err) {
    showError(err?.data?.message || t('scp.ipr.snackbar.error_delete'))
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
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || '',
    unit: item.unit || '',
    quantity: qty,
    qty_requested: qty,
    unit_price: cost,
    estimated_unit_cost: cost,
    line_total: qty * cost,
    description: '',
  }
}

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.ipr.sections.overview'), icon: 'tabler-info-circle' },
  { id: 'lines', title: t('scp.ipr.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'approval', title: t('scp.ipr.sections.approval'), icon: 'tabler-checklist' },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])
</script>

<template>
  <div class="ipr-edit-page">
    <WpDocumentHeader
      :title="form.ipr_number || t('scp.ipr.title')"
      :reference="form.ipr_number"
      :subtitle="t('scp.ipr.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :priority="form.urgency"
      :priority-label="urgencyLabel(form.urgency)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.ipr'), to: '/supply-chain-procurement/ipr/list' },
        { title: form.ipr_number || t('scp.action.edit') },
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

    <WpJournalNav
      v-model="activeSection"
      :sections="journalSections"
    >
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
        />
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
          :chain="traceabilityChain"
        />
      </section>
    </WpJournalNav>

    <ConvertIprDialog
      v-model="convertDialogOpen"
      :ipr="rawDocument"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="form.ipr_number"
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
.ipr-edit-page {
  overflow-x: clip;
}
</style>
