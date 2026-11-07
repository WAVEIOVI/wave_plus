<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useRfqStore } from '@/stores/useRfqStore'
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
import RecordRfqResponseDialog from '@/views/supply-chain-procurement/rfq/RecordRfqResponseDialog.vue'

definePage({
  name: 'procurement-rfq-edit-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-rfq-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { smAndDown } = useDisplay()
const rfqStore = useRfqStore()
const iprStore = useIprStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  paymentTermsLabel,
  supplierOptions,
  ensureSuppliers,
  fetchCatalogItemsForPicker,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const rfqId = computed(() => route.params.id)
const isCreateMode = computed(() => rfqId.value === 'new')
const rawDocument = ref(null)
const responseDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

// Smart Import from Approved IPR state
const importIprDialogOpen = ref(false)
const approvedIprs = ref([])
const selectedImportIprIds = ref([])
const loadingApprovedIprs = ref(false)
const importingIprs = ref(false)

const normalizeRfqToForm = doc => {
  const ec = doc?.evaluation_criteria || {}

  return {
    id: doc?.id || null,
    reference: doc?.reference || '',
    status: doc?.status || 'draft',
    title: doc?.title || '',
    response_deadline: doc?.response_deadline ? String(doc.response_deadline).slice(0, 10) : '',
    required_delivery_date: doc?.required_delivery_date ? String(doc.required_delivery_date).slice(0, 10) : '',
    terms_and_conditions: doc?.terms_and_conditions || '',
    price_weight: Number(ec.price_weight ?? 40),
    lead_time_weight: Number(ec.lead_time_weight ?? 25),
    quality_weight: Number(ec.quality_weight ?? 20),
    technical_weight: Number(ec.technical_weight ?? 10),
    payment_weight: Number(ec.payment_weight ?? 5),
    ipr_ids: Array.isArray(doc?.ipr_ids)
      ? [...doc.ipr_ids]
      : (doc?.iprs || []).map(i => i.id).filter(Boolean),
    invited_supplier_ids: (doc?.invitations || []).map(inv => inv.supplier_id || inv.supplier?.id).filter(Boolean),
    lines: (doc?.lines || []).map(l => {
      const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
      const price = Number(l.target_price_ht ?? l.unit_price ?? 0) || 0

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
        unit_id: l.unit_id || l.unit?.id || null,
        unit_price: price,
        target_price_ht: price,
        line_total: qty * price,
        description: l.description_for_supplier || l.description || '',
      }
    }),
  }
}

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
  initial: () => normalizeRfqToForm({}),
  lockedStatuses: ['awarded', 'cancelled'],
  unsavedGuard: true,
})

const reloadRfq = async () => {
  isLoading.value = true
  try {
    await ensureSuppliers()

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizeRfqToForm({}))

      return
    }

    const doc = await rfqStore.fetchDetails(rfqId.value)
    if (doc) {
      rawDocument.value = doc
      setDocument(normalizeRfqToForm(doc))
    }
  }
  catch (err) {
    showApiError(err, t('scp.rfq.snackbar.error_load', 'Failed to load RFQ'))
  }
  finally {
    isLoading.value = false
  }
}

onMounted(reloadRfq)

watch(rfqId, (next, prev) => {
  if (next && next !== prev) reloadRfq()
})

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { fields: 'rfq' })

const workflow = useDocumentWorkflow(rawDocument, { config: 'rfq' })

const executeTransition = async (targetStatus, extraPayload = {}) => {
  if (isCreateMode.value || !rfqId.value) return null
  submittingTransition.value = true
  try {
    const updated = await rfqStore.updateStatus(rfqId.value, {
      status: targetStatus,
      ...extraPayload,
    })

    rawDocument.value = updated
    setDocument(normalizeRfqToForm(updated))
    success(t('scp.rfq.snackbar.status_updated'))

    return updated
  }
  catch (err) {
    showApiError(err, t('scp.rfq.snackbar.error_status'))
    throw err
  }
  finally {
    submittingTransition.value = false
  }
}

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { type: 'rfq' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const invitations = computed(() => rawDocument.value?.invitations || [])
const responses = computed(() => rawDocument.value?.responses || [])
const canRecordBid = computed(() => !isCreateMode.value && ['sent', 'awaiting_responses', 'under_evaluation'].includes(form.value.status))

const criteriaWeights = computed(() => [
  { key: 'price_weight', label: t('scp.rfq.weights.price', 'Price Weight'), icon: 'tabler-currency-dollar', color: 'primary' },
  { key: 'lead_time_weight', label: t('scp.rfq.weights.lead_time', 'Lead Time Weight'), icon: 'tabler-clock', color: 'info' },
  { key: 'quality_weight', label: t('scp.rfq.weights.quality', 'Quality Weight'), icon: 'tabler-shield-check', color: 'success' },
  { key: 'technical_weight', label: t('scp.rfq.weights.technical', 'Technical Weight'), icon: 'tabler-settings', color: 'warning' },
  { key: 'payment_weight', label: t('scp.rfq.weights.payment', 'Payment Terms Weight'), icon: 'tabler-credit-card', color: 'secondary' },
])

const totalCriteriaWeight = computed(() =>
  (Number(form.value.price_weight) || 0)
  + (Number(form.value.lead_time_weight) || 0)
  + (Number(form.value.quality_weight) || 0)
  + (Number(form.value.technical_weight) || 0)
  + (Number(form.value.payment_weight) || 0),
)

const metadataItems = computed(() => [
  {
    key: 'deadline',
    icon: 'tabler-calendar-due',
    label: t('scp.rfq.fields.response_deadline'),
    value: formatDate(form.value.response_deadline),
  },
  {
    key: 'delivery',
    icon: 'tabler-truck-delivery',
    label: t('scp.rfq.fields.required_delivery_date'),
    value: formatDate(form.value.required_delivery_date),
  },
  {
    key: 'suppliers',
    icon: 'tabler-building-store',
    label: t('scp.rfq.fields.invited_suppliers'),
    value: String(invitations.value.length || form.value.invited_supplier_ids?.length || 0),
  },
  {
    key: 'awarded',
    icon: 'tabler-award',
    label: t('scp.rfq.fields.awarded_supplier'),
    value: rawDocument.value?.awarded_to ? resolveSupplierName(rawDocument.value.awarded_to) : '—',
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
  if (st === 'draft') {
    return {
      key: 'send',
      label: t('scp.rfq.actions.send_to_suppliers'),
      icon: 'tabler-send',
      color: 'primary',
      loading: submittingTransition.value,
    }
  }
  if (st === 'sent' || st === 'awaiting_responses') {
    return {
      key: 'record_bid',
      label: t('scp.rfq.actions.record_bid'),
      icon: 'tabler-file-dollar',
      color: 'primary',
    }
  }
  if (st === 'under_evaluation') {
    return {
      key: 'go_comparison',
      label: t('scp.rfq.sections.comparison'),
      icon: 'tabler-git-compare',
      color: 'info',
    }
  }
  if (st === 'awarded') {
    return {
      key: 'convert_po',
      label: t('scp.action.convert_to_po'),
      icon: 'tabler-shopping-cart-plus',
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
    key: 'import_ipr',
    label: t('scp.rfq.actions.import_ipr', 'Import from Approved IPR'),
    icon: 'tabler-file-import',
    color: 'secondary',
    hidden: isReadOnly.value || form.value.status !== 'draft',
  },
  {
    key: 'record_bid',
    label: t('scp.rfq.actions.record_bid'),
    icon: 'tabler-file-dollar',
    color: 'info',
    hidden: !canRecordBid.value,
  },
  {
    key: 'start_eval',
    label: t('scp.rfq.actions.start_evaluation'),
    icon: 'tabler-git-compare',
    color: 'warning',
    hidden: isCreateMode.value || !['sent', 'awaiting_responses'].includes(form.value.status),
  },
  {
    key: 'convert_po',
    label: t('scp.action.convert_to_po'),
    icon: 'tabler-shopping-cart-plus',
    color: 'success',
    hidden: isCreateMode.value || form.value.status !== 'awarded',
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: isCreateMode.value || ['awarded', 'cancelled'].includes(form.value.status),
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
  title: current.title,
  response_deadline: current.response_deadline,
  required_delivery_date: current.required_delivery_date || null,
  terms_and_conditions: current.terms_and_conditions || null,
  ipr_ids: Array.isArray(current.ipr_ids) ? current.ipr_ids : [],
  evaluation_criteria: {
    price_weight: Number(current.price_weight) || 0,
    lead_time_weight: Number(current.lead_time_weight) || 0,
    quality_weight: Number(current.quality_weight) || 0,
    technical_weight: Number(current.technical_weight) || 0,
    payment_weight: Number(current.payment_weight) || 0,
  },
  invitations: (current.invited_supplier_ids || []).map(id => {
    const existing = invitations.value.find(inv => inv.supplier_id === id)

    return existing?.id ? { id: existing.id, supplier_id: id } : { supplier_id: id }
  }),
  lines: (current.lines || []).map(l => {
    const isAdHoc = Boolean(l.is_ad_hoc || !l.catalog_item_id)

    return {
      ...(l.id ? { id: l.id } : {}),
      is_ad_hoc: isAdHoc,
      catalog_item_id: isAdHoc ? null : (l.catalog_item_id || null),
      variant_id: isAdHoc ? null : (l.variant_id || null),
      ad_hoc_name: isAdHoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || l.sku || null) : null,
      description_for_supplier: l.description || null,
      qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
      unit_id: l.unit_id || null,
      target_price_ht: Number(l.unit_price ?? l.target_price_ht ?? 0) || null,
    }
  }),
})

const handleSave = async () => {
  isSaving.value = true
  try {
    const payload = buildSavePayload(form.value)
    if (isCreateMode.value) {
      const created = await rfqStore.create(payload)

      rawDocument.value = created
      setDocument(normalizeRfqToForm(created))
      success(t('scp.rfq.snackbar.created_success'))
      await router.replace(`/supply-chain-procurement/rfq/edit/${created.id}`)

      return created
    }

    const updated = await rfqStore.update(rfqId.value, payload)

    rawDocument.value = updated
    setDocument(normalizeRfqToForm(updated))
    success(t('scp.rfq.snackbar.updated_success'))

    return updated
  }
  catch (err) {
    applyServerErrors(err)
    showApiError(err, t('scp.rfq.snackbar.error_save'))
    throw err
  }
  finally {
    isSaving.value = false
  }
}

const openImportIprDialog = async () => {
  selectedImportIprIds.value = [...(form.value.ipr_ids || [])]
  importIprDialogOpen.value = true
  loadingApprovedIprs.value = true
  try {
    const res = await iprStore.fetchList({ 'filter[status]': 'approved', per_page: 50 })

    approvedIprs.value = res?.data || iprStore.list || []
  }
  catch (err) {
    showApiError(err)
  }
  finally {
    loadingApprovedIprs.value = false
  }
}

const approvedIprOptions = computed(() =>
  approvedIprs.value.map(ipr => ({
    title: `${ipr.reference} — ${ipr.requested_by_user?.name || ipr.requester?.name || ''}`,
    value: ipr.id,
  })),
)

const confirmImportFromIprs = async () => {
  importingIprs.value = true
  try {
    const ids = selectedImportIprIds.value || []
    const existingIprIds = new Set(form.value.ipr_ids || [])
    const newLines = []

    for (const id of ids) {
      if (existingIprIds.has(id) && form.value.lines.some(l => l.source_ipr_id === id)) continue

      let ipr = approvedIprs.value.find(item => item.id === id)
      if (!ipr?.lines?.length) {
        ipr = await iprStore.fetchDetails(id)
      }

      for (const l of ipr?.lines || []) {
        const qty = Number(l.qty_requested ?? 1) || 1
        const price = Number(l.estimated_unit_cost ?? 0) || 0

        newLines.push({
          source_ipr_id: id,
          is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
          catalog_item_id: l.catalog_item_id || null,
          variant_id: l.variant_id || l.variant?.id || null,
          ad_hoc_name: l.ad_hoc_name || '',
          ad_hoc_sku: l.ad_hoc_sku || '',
          item_name: resolveLineItemName(l),
          sku: resolveLineItemSku(l),
          quantity: qty,
          qty_requested: qty,
          unit_id: l.unit_id || l.catalog_item?.uom_id || null,
          unit_price: price,
          target_price_ht: price,
          line_total: qty * price,
          description: l.ad_hoc_description || '',
        })
      }
    }

    form.value.ipr_ids = [...new Set([...(form.value.ipr_ids || []), ...ids])]
    if (newLines.length) {
      form.value.lines = [...(form.value.lines || []), ...newLines]
    }
    importIprDialogOpen.value = false
    success(t('scp.rfq.snackbar.imported_ipr', 'IPR lines imported into RFQ'))
  }
  catch (err) {
    showApiError(err)
  }
  finally {
    importingIprs.value = false
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'import_ipr') {
    await openImportIprDialog()
  }
  else if (key === 'send') {
    if (isDirty.value) await handleSave()
    await executeTransition('sent')
  }
  else if (key === 'record_bid') {
    responseDialogOpen.value = true
  }
  else if (key === 'start_eval') {
    if (form.value.status === 'sent') {
      await executeTransition('awaiting_responses')
    }
    await executeTransition('under_evaluation')
  }
  else if (key === 'go_comparison') {
    scrollToSection('comparison')
  }
  else if (key === 'convert_po') {
    router.push({ path: '/supply-chain-procurement/po/list', query: { from_rfq: rfqId.value } })
  }
  else if (key === 'cancel') {
    await executeTransition('cancelled')
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
  }
}

const awardSupplier = async supplierId => {
  if (!supplierId) return
  try {
    if (form.value.status === 'sent') {
      await executeTransition('awaiting_responses')
    }
    if (rawDocument.value?.status === 'awaiting_responses') {
      await executeTransition('under_evaluation')
    }
    await executeTransition('awarded', { awarded_to_supplier_id: supplierId })
  }
  catch {
    // Error handled in executeTransition
  }
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await rfqStore.deleteItem(rfqId.value)
    success(t('scp.rfq.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/rfq/list')
  }
  catch (err) {
    showApiError(err, t('scp.rfq.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const mapCatalogToRfqLine = (item, quantity = 1) => {
  const price = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const qty = Number(quantity) || 1

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    variant_id: item.variant_id || null,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || item.code || '',
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

const findResponseLineFor = (response, rfqLineId) =>
  (response?.lines || []).find(rl => rl.rfq_line_id === rfqLineId)

const computeVsTargetPct = (offeredPrice, targetPrice) => {
  const offered = Number(offeredPrice)
  const target = Number(targetPrice)
  if (!Number.isFinite(offered) || !Number.isFinite(target) || target <= 0) return null

  return Math.round(((offered - target) / target) * 1000) / 10
}

const formatEvaluationScore = resp => {
  const score = resp?.evaluation_score ?? resp?.weighted_score
  if (score === null || score === undefined) return null

  return Number(score).toFixed(1)
}

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.rfq.sections.overview'), icon: 'tabler-info-circle' },
  { id: 'suppliers', title: t('scp.rfq.sections.invited_suppliers'), icon: 'tabler-building-store', badge: invitations.value.length || form.value.invited_supplier_ids.length },
  { id: 'lines', title: t('scp.rfq.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'comparison', title: t('scp.rfq.sections.comparison'), icon: 'tabler-git-compare', badge: responses.value.length },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])

const { activeSection, scrollToSection } = useJournalNav(journalSections, { idPrefix: 'section-' })
</script>

<template>
  <div class="rfq-edit-page">
    <WpDocumentHeader
      :title="form.reference || t('scp.rfq.new_title', 'New Request for Quotation')"
      :reference="form.reference"
      :subtitle="form.title || t('scp.rfq.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.rfq'), to: '/supply-chain-procurement/rfq/list' },
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
        :title="t('scp.rfq.fields.invited_suppliers')"
        :value="invitations.length || form.invited_supplier_ids.length"
        icon="tabler-users"
        color="info"
      />
      <WpKpiCard
        :title="t('scp.rfq.sections.responses')"
        :value="responses.length"
        icon="tabler-messages"
        color="primary"
      />
      <WpKpiCard
        :title="t('scp.common.lines')"
        :value="form.lines.length"
        icon="tabler-list-numbers"
        color="warning"
      />
      <WpKpiCard
        :title="t('scp.rfq.fields.target_total')"
        :value="formatMoney(totals.grandTotal)"
        icon="tabler-coins"
        color="success"
      />
    </WpKpiGroup>

    <div class="scp-edit-layout">
      <WpJournalNav
        v-model="activeSection"
        :sections="journalSections"
        :title="form.reference || t('scp.rfq.title')"
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
        <!-- Section 1: Overview & Evaluation Criteria -->
        <section
          id="section-overview"
          class="mb-6"
        >
          <WpSectionCard
            :title="t('scp.rfq.sections.overview')"
            icon="tabler-info-circle"
          >
            <template
              v-if="!isReadOnly"
              #actions
            >
              <VBtn
                v-if="form.status === 'draft'"
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-file-import"
                min-height="40"
                @click="openImportIprDialog"
              >
                {{ t('scp.rfq.actions.import_ipr', 'Import from Approved IPR') }}
              </VBtn>
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
                md="6"
              >
                <VTextField
                  v-model="form.title"
                  :label="t('scp.rfq.fields.title')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('title')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VTextField
                  v-model="form.response_deadline"
                  type="date"
                  :label="t('scp.rfq.fields.response_deadline')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('response_deadline')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VTextField
                  v-model="form.required_delivery_date"
                  type="date"
                  :label="t('scp.rfq.fields.required_delivery_date')"
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('required_delivery_date')"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.terms_and_conditions"
                  :label="t('scp.rfq.fields.terms_and_conditions')"
                  rows="2"
                  auto-grow
                  :readonly="isReadOnly"
                  :error-messages="fieldErrors('terms_and_conditions')"
                />
              </VCol>
            </VRow>

            <VDivider class="my-4" />

            <!-- Evaluation Criteria Weights -->
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-scale"
                  size="20"
                  color="primary"
                />
                <span class="text-subtitle-1 font-weight-bold">
                  {{ t('scp.rfq.sections.evaluation_criteria', 'Evaluation Criteria Weights') }}
                </span>
              </div>
              <VChip
                size="small"
                label
                variant="tonal"
                :color="totalCriteriaWeight === 100 ? 'success' : 'error'"
              >
                {{ t('scp.common.total', 'Total') }}: {{ totalCriteriaWeight }}% / 100%
              </VChip>
            </div>

            <VAlert
              v-if="fieldErrors('evaluation_criteria') || totalCriteriaWeight !== 100"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              {{ fieldErrors('evaluation_criteria') || t('scp.rfq.alerts.weights_sum_100', 'Evaluation criteria weights must sum to 100%.') }}
            </VAlert>

            <VRow>
              <VCol
                v-for="cw in criteriaWeights"
                :key="cw.key"
                cols="12"
                sm="6"
                md="4"
              >
                <div class="pa-3 rounded border">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-body-2 font-weight-medium d-flex align-center gap-1">
                      <VIcon
                        :icon="cw.icon"
                        size="16"
                        :color="cw.color"
                      />
                      {{ cw.label }}
                    </span>
                    <VChip
                      size="x-small"
                      :color="cw.color"
                      variant="tonal"
                      label
                    >
                      {{ form[cw.key] }}%
                    </VChip>
                  </div>
                  <div class="d-flex align-center gap-3">
                    <VSlider
                      v-model="form[cw.key]"
                      min="0"
                      max="100"
                      step="5"
                      :color="cw.color"
                      :readonly="isReadOnly"
                      hide-details
                      class="flex-grow-1"
                    />
                    <VTextField
                      v-model.number="form[cw.key]"
                      type="number"
                      min="0"
                      max="100"
                      suffix="%"
                      density="compact"
                      variant="outlined"
                      :readonly="isReadOnly"
                      hide-details
                      style="max-inline-size: 88px;"
                    />
                  </div>
                </div>
              </VCol>
            </VRow>
          </WpSectionCard>
        </section>

        <!-- Section 2: Invited Suppliers -->
        <section
          id="section-suppliers"
          class="mb-6"
        >
          <WpSectionCard
            :title="t('scp.rfq.sections.invited_suppliers')"
            icon="tabler-building-store"
          >
            <VAutocomplete
              v-if="!isReadOnly"
              v-model="form.invited_supplier_ids"
              :items="supplierOptions"
              item-title="title"
              item-value="value"
              :label="t('scp.rfq.fields.invited_suppliers')"
              multiple
              chips
              closable-chips
              class="mb-4"
            />

            <WpEmptyState
              v-if="invitations.length === 0 && form.invited_supplier_ids.length === 0"
              :title="t('scp.rfq.empty.no_suppliers')"
              icon="tabler-building-store"
              compact
            />

            <VRow v-else-if="invitations.length">
              <VCol
                v-for="inv in invitations"
                :key="inv.id"
                cols="12"
                sm="6"
                md="4"
              >
                <VCard
                  variant="outlined"
                  class="pa-3"
                >
                  <div class="d-flex align-center justify-space-between gap-2">
                    <div class="min-w-0">
                      <div class="font-weight-bold text-body-2 text-truncate">
                        {{ resolveSupplierName(inv.supplier) }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ inv.supplier?.supplier_id || inv.supplier?.primary_email || '' }}
                      </div>
                    </div>
                    <VChip
                      size="x-small"
                      label
                      variant="tonal"
                      :color="responses.some(r => r.supplier_id === inv.supplier_id) ? 'success' : 'warning'"
                    >
                      {{ responses.some(r => r.supplier_id === inv.supplier_id) ? t('scp.rfq.bid_received') : t('scp.rfq.awaiting_bid') }}
                    </VChip>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </WpSectionCard>
        </section>

        <!-- Section 3: Requested Items -->
        <section
          id="section-lines"
          class="mb-6"
        >
          <WpDocumentLinesCard
            v-model:lines="form.lines"
            :title="t('scp.rfq.sections.lines')"
            :readonly="isReadOnly"
            :loading="isLoading"
            :fields="['item', 'description', 'quantity', 'unit', 'unit_price']"
            :field-labels="{
              quantity: t('scp.ipr.fields.qty_requested'),
              unit_price: t('scp.rfq.fields.target_price_ht'),
            }"
            :totals="{
              grand_total: totals.grandTotal,
            }"
            show-catalog-picker
            show-scan
            :fetch-catalog-items="fetchCatalogItemsForPicker"
            :map-catalog-item="mapCatalogToRfqLine"
            :errors="errors"
          />
        </section>

        <!-- Section 4: Supplier Responses & Bid Comparison Matrix -->
        <section
          id="section-comparison"
          class="mb-6"
        >
          <WpSectionCard
            :title="t('scp.rfq.sections.comparison')"
            icon="tabler-git-compare"
          >
            <template #actions>
              <VBtn
                v-if="canRecordBid"
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-plus"
                min-height="40"
                @click="responseDialogOpen = true"
              >
                {{ t('scp.rfq.actions.record_bid') }}
              </VBtn>
              <VBtn
                v-if="form.status === 'awarded'"
                size="small"
                color="success"
                prepend-icon="tabler-shopping-cart-plus"
                min-height="40"
                @click="router.push({ path: '/supply-chain-procurement/po/list', query: { from_rfq: rfqId } })"
              >
                {{ t('scp.action.convert_to_po') }}
              </VBtn>
            </template>

            <WpEmptyState
              v-if="responses.length === 0"
              :title="t('scp.rfq.empty.no_responses')"
              :subtitle="t('scp.rfq.empty.no_responses_desc')"
              icon="tabler-messages-off"
              compact
            />

            <!-- Mobile Bid Cards -->
            <div
              v-else-if="smAndDown"
              class="d-flex flex-column gap-3"
            >
              <WpMobileCard
                v-for="resp in responses"
                :key="resp.id"
                :title="resolveSupplierName(resp.supplier)"
                :subtitle="`${paymentTermsLabel(resp.payment_terms_offered)} · ${formatDate(resp.can_deliver_by)}`"
              >
                <template #badges>
                  <VChip
                    v-if="rawDocument?.awarded_to_supplier_id === resp.supplier_id"
                    size="x-small"
                    color="success"
                    prepend-icon="tabler-award"
                    label
                  >
                    {{ statusLabel('awarded') }}
                  </VChip>
                  <VChip
                    v-if="formatEvaluationScore(resp) !== null"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    label
                  >
                    {{ t('scp.common.score') }}: {{ formatEvaluationScore(resp) }}
                  </VChip>
                </template>

                <template #content>
                  <div class="d-flex justify-space-between align-center mt-1">
                    <span class="text-body-2 font-weight-bold text-primary">
                      {{ formatMoney(resp.total_ht || 0) }}
                    </span>
                  </div>
                  <div
                    v-if="resp.evaluation_details"
                    class="d-flex flex-wrap gap-1 mt-2"
                  >
                    <VChip
                      v-for="(val, key) in resp.evaluation_details"
                      :key="key"
                      size="x-small"
                      variant="outlined"
                      color="secondary"
                    >
                      {{ key }}: {{ val }}
                    </VChip>
                  </div>
                </template>

                <template
                  v-if="form.status !== 'awarded' && form.status !== 'cancelled'"
                  #actions
                >
                  <VBtn
                    size="small"
                    color="success"
                    variant="tonal"
                    prepend-icon="tabler-award"
                    min-height="40"
                    @click="awardSupplier(resp.supplier_id)"
                  >
                    {{ t('scp.rfq.actions.award_supplier') }}
                  </VBtn>
                </template>
              </WpMobileCard>
            </div>

            <!-- Desktop Side-by-Side Comparison Matrix -->
            <div
              v-else
              class="overflow-x-auto"
            >
              <VTable density="comfortable">
                <thead>
                  <tr>
                    <th>{{ t('scp.common.item') }}</th>
                    <th class="text-end">
                      {{ t('scp.ipr.fields.qty_requested') }}
                    </th>
                    <th class="text-end">
                      {{ t('scp.rfq.fields.target_price_ht') }}
                    </th>
                    <th
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end"
                    >
                      <div class="font-weight-bold">
                        {{ resolveSupplierName(resp.supplier) }}
                      </div>
                      <div class="d-flex justify-end align-center gap-1 mt-1">
                        <VChip
                          v-if="formatEvaluationScore(resp) !== null"
                          size="x-small"
                          color="info"
                          variant="tonal"
                          label
                        >
                          {{ t('scp.common.score') }}: {{ formatEvaluationScore(resp) }}
                        </VChip>
                        <VChip
                          v-if="rawDocument?.awarded_to_supplier_id === resp.supplier_id"
                          size="x-small"
                          color="success"
                          prepend-icon="tabler-award"
                          label
                        >
                          {{ statusLabel('awarded') }}
                        </VChip>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="line in form.lines"
                    :key="line.id || line.item_name"
                  >
                    <td class="font-weight-medium">
                      {{ line.item_name }}
                    </td>
                    <td class="text-end">
                      {{ line.qty_requested }}
                    </td>
                    <td class="text-end text-medium-emphasis">
                      {{ formatMoney(line.target_price_ht || 0) }}
                    </td>
                    <td
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end"
                    >
                      <template v-if="findResponseLineFor(resp, line.id)">
                        <div class="d-flex align-center justify-end gap-1">
                          <span class="font-weight-bold">
                            {{ formatMoney(findResponseLineFor(resp, line.id).offered_unit_price_ht) }}
                          </span>
                          <VChip
                            v-if="computeVsTargetPct(findResponseLineFor(resp, line.id).offered_unit_price_ht, line.target_price_ht) !== null"
                            size="x-small"
                            label
                            variant="tonal"
                            :color="computeVsTargetPct(findResponseLineFor(resp, line.id).offered_unit_price_ht, line.target_price_ht) <= 0 ? 'success' : 'warning'"
                          >
                            {{ computeVsTargetPct(findResponseLineFor(resp, line.id).offered_unit_price_ht, line.target_price_ht) > 0 ? '+' : '' }}{{ computeVsTargetPct(findResponseLineFor(resp, line.id).offered_unit_price_ht, line.target_price_ht) }}%
                          </VChip>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ findResponseLineFor(resp, line.id).offered_lead_time_days ?? '—' }} {{ t('scp.rfq.days') }}
                        </div>
                      </template>
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </td>
                  </tr>

                  <!-- Summary Rows -->
                  <tr class="bg-surface">
                    <td
                      colspan="3"
                      class="font-weight-bold"
                    >
                      {{ t('scp.rfq.response_dialog.total_quoted_ht') }}
                    </td>
                    <td
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end font-weight-bold text-primary text-body-1"
                    >
                      {{ formatMoney(resp.total_ht || 0) }}
                    </td>
                  </tr>
                  <tr>
                    <td
                      colspan="3"
                      class="text-medium-emphasis"
                    >
                      {{ t('scp.common.score', 'Evaluation Breakdown') }}
                    </td>
                    <td
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end"
                    >
                      <div
                        v-if="resp.evaluation_details"
                        class="d-inline-flex flex-wrap justify-end gap-1"
                      >
                        <VChip
                          v-for="(val, key) in resp.evaluation_details"
                          :key="key"
                          size="x-small"
                          variant="tonal"
                          color="secondary"
                        >
                          {{ key }}: {{ val }}
                        </VChip>
                      </div>
                      <span
                        v-else
                        class="text-caption text-medium-emphasis"
                      >
                        {{ formatEvaluationScore(resp) ?? '—' }}
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td
                      colspan="3"
                      class="text-medium-emphasis"
                    >
                      {{ t('wp.docs.terms.payment_terms') }} / {{ t('scp.rfq.response_dialog.can_deliver_by') }}
                    </td>
                    <td
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end text-caption"
                    >
                      {{ paymentTermsLabel(resp.payment_terms_offered) }} · {{ formatDate(resp.can_deliver_by) }}
                    </td>
                  </tr>
                  <tr v-if="form.status !== 'awarded' && form.status !== 'cancelled'">
                    <td colspan="3">
                      {{ t('scp.rfq.actions.award_decision') }}
                    </td>
                    <td
                      v-for="resp in responses"
                      :key="resp.id"
                      class="text-end"
                    >
                      <VBtn
                        size="small"
                        color="success"
                        variant="tonal"
                        prepend-icon="tabler-award"
                        :loading="submittingTransition"
                        @click="awardSupplier(resp.supplier_id)"
                      >
                        {{ t('scp.rfq.actions.award_supplier') }}
                      </VBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </WpSectionCard>
        </section>

        <!-- Section 5: Traceability -->
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

    <!-- Smart Import from Approved IPR Dialog -->
    <WpFormDialog
      v-model="importIprDialogOpen"
      :title="t('scp.rfq.actions.import_ipr', 'Import from Approved IPR')"
      icon="tabler-file-import"
      size="medium"
      :loading="importingIprs"
      :submit-label="t('scp.action.import', 'Import Lines')"
      @submit="confirmImportFromIprs"
    >
      <div class="pa-4">
        <VAutocomplete
          v-model="selectedImportIprIds"
          :items="approvedIprOptions"
          item-title="title"
          item-value="value"
          :loading="loadingApprovedIprs"
          :label="t('scp.rfq.fields.source_iprs', 'Approved Purchase Requests')"
          prepend-inner-icon="tabler-file-import"
          multiple
          chips
          closable-chips
        />
      </div>
    </WpFormDialog>

    <RecordRfqResponseDialog
      v-model="responseDialogOpen"
      :rfq="rawDocument"
      @submitted="reloadRfq"
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
.rfq-edit-page {
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
