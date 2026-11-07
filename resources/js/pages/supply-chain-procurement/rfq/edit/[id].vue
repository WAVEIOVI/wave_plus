<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useRfqStore } from '@/stores/useRfqStore'
import { useIprStore } from '@/stores/useIprStore'
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
import RecordRfqResponseDialog from '@/views/supply-chain-procurement/rfq/RecordRfqResponseDialog.vue'
import RfqSuppliersSection from '@/views/supply-chain-procurement/rfq/RfqSuppliersSection.vue'
import RfqBroadcastDialog from '@/views/supply-chain-procurement/rfq/RfqBroadcastDialog.vue'
import RfqComparisonSection from '@/views/supply-chain-procurement/rfq/RfqComparisonSection.vue'
import VariantSelectDialog from '@/views/supply-chain-procurement/shared/VariantSelectDialog.vue'
import SourceDocumentImporterDialog from '@/views/supply-chain-procurement/shared/SourceDocumentImporterDialog.vue'
import ProcurementUnsavedBar from '@/views/supply-chain-procurement/shared/ProcurementUnsavedBar.vue'

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
const rfqStore = useRfqStore()
const iprStore = useIprStore()
const supplierStore = useSupplierStore()
const { format: formatMoney } = useCurrency()
const { success, error: showError, showApiError } = useAppSnackbar()

const {
  statusLabel,
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
const pageError = ref(null)
const responseDialogOpen = ref(false)
const editingResponse = ref(null)
const broadcastDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

// Smart Import from Approved IPR state
const importIprDialogOpen = ref(false)

// Variant selection dialog state
const variantDialogOpen = ref(false)
const variantDialogCatalogItem = ref(null)
const variantDialogLineIndex = ref(null)

const commonCertOptions = [
  'ISO 9001',
  'ISO 14001',
  'CE',
  'EN3',
  'UL',
  'NFPA',
  'ATEX',
]

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
    iprs: Array.isArray(doc?.iprs) ? [...doc.iprs] : [],
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
        ad_hoc_sku: l.ad_hoc_sku || 'NEW',
        item_name: resolveLineItemName(l),
        sku: resolveLineItemSku(l),
        item_type: l.catalog_item?.item_type || l.item_type || '',
        subtype: l.catalog_item?.subtype || l.subtype || '',
        quantity: qty,
        qty_requested: qty,
        unit_id: l.unit_id || l.unit?.id || null,
        unit_price: price,
        target_price_ht: price,
        line_total: qty * price,
        description: l.description_for_supplier || l.description || '',
        required_certifications: Array.isArray(l.required_certifications) ? [...l.required_certifications] : [],
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

const mapIprLineToRfqLine = (l, sourceIprId) => {
  const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
  const price = Number(l.resolved_unit_price ?? l.estimated_unit_cost ?? l.unit_price ?? 0) || 0

  return {
    source_ipr_id: sourceIprId || null,
    is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
    catalog_item_id: l.catalog_item_id || null,
    variant_id: l.variant_id || l.variant?.id || null,
    ad_hoc_name: l.ad_hoc_name || '',
    ad_hoc_sku: l.ad_hoc_sku || 'NEW',
    item_name: resolveLineItemName(l),
    sku: resolveLineItemSku(l),
    item_type: l.catalog_item?.item_type || l.item_type || '',
    subtype: l.catalog_item?.subtype || l.subtype || '',
    quantity: qty,
    qty_requested: qty,
    unit_id: l.unit_id || l.catalog_item?.uom_id || null,
    unit_price: price,
    target_price_ht: price,
    line_total: qty * price,
    description: l.ad_hoc_description || l.description || '',
    required_certifications: [],
  }
}

const reloadRfq = async () => {
  isLoading.value = true
  pageError.value = null
  try {
    await ensureSuppliers()

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizeRfqToForm({}))

      const fromIprId = route.query?.from_ipr ? String(route.query.from_ipr) : null
      if (fromIprId) {
        const ipr = await iprStore.fetchDetails(fromIprId).catch(() => null)
        if (ipr) {
          form.value.ipr_ids = [ipr.id]
          form.value.iprs = [{ id: ipr.id, reference: ipr.reference }]
          if (!form.value.title && ipr.reference) {
            form.value.title = `RFQ — ${ipr.reference}`
          }
          if (!form.value.required_delivery_date && ipr.needed_by_date) {
            form.value.required_delivery_date = String(ipr.needed_by_date).slice(0, 10)
          }
          form.value.lines = (ipr.lines || []).map(l => mapIprLineToRfqLine(l, ipr.id))
        }
      }

      return
    }

    const doc = await rfqStore.fetchDetails(rfqId.value)
    if (doc) {
      rawDocument.value = doc
      setDocument(normalizeRfqToForm(doc))
    }
  }
  catch (err) {
    pageError.value = err?.data?.message || err?.message || t('scp.rfq.snackbar.error_load', 'Failed to load RFQ')
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

    const fresh = await rfqStore.fetchDetails(rfqId.value).catch(() => updated)

    rawDocument.value = fresh || updated
    setDocument(normalizeRfqToForm(fresh || updated))
    success(t('scp.rfq.snackbar.status_updated'))

    return fresh || updated
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
  invited_supplier_ids: Array.isArray(current.invited_supplier_ids) ? current.invited_supplier_ids : [],
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
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || l.sku || 'NEW') : null,
      description_for_supplier: l.description || null,
      qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
      unit_id: l.unit_id || null,
      target_price_ht: Number(l.unit_price ?? l.target_price_ht ?? 0) || null,
      required_certifications: Array.isArray(l.required_certifications) ? l.required_certifications : [],
    }
  }),
})

const handleSave = async () => {
  if (!form.value.title?.trim()) {
    errors.value = { ...errors.value, title: [t('scp.rfq.validation.title_required', 'RFQ title is required.')] }
    showError(t('scp.rfq.validation.title_required', 'RFQ title is required.'))

    return null
  }
  if (!form.value.response_deadline) {
    errors.value = { ...errors.value, response_deadline: [t('scp.rfq.validation.deadline_required', 'Response deadline is required.')] }
    showError(t('scp.rfq.validation.deadline_required', 'Response deadline is required.'))

    return null
  }
  if (!Array.isArray(form.value.lines) || form.value.lines.length === 0) {
    showError(t('scp.rfq.validation.lines_required', 'At least one requested item is required.'))

    return null
  }
  if (totalCriteriaWeight.value !== 100) {
    showError(t('scp.rfq.alerts.weights_sum_100', 'Evaluation criteria weights must sum to 100%.'))

    return null
  }

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

const openImportIprDialog = () => {
  importIprDialogOpen.value = true
}

const handleImportSource = ({ sourceId, sourceDoc, lines }) => {
  if (!sourceId || !Array.isArray(lines)) return
  const importedLines = lines.map(l => mapIprLineToRfqLine(l, sourceId))

  form.value.ipr_ids = [...new Set([...(form.value.ipr_ids || []), sourceId])]

  const existingIprs = Array.isArray(form.value.iprs) ? form.value.iprs : []
  if (!existingIprs.some(i => i.id === sourceId)) {
    form.value.iprs = [
      ...existingIprs,
      { id: sourceId, reference: sourceDoc?.reference || `#${String(sourceId).slice(-6)}` },
    ]
  }
  if (importedLines.length) {
    form.value.lines = [...(form.value.lines || []), ...importedLines]
  }
  success(t('scp.rfq.snackbar.imported_ipr', 'IPR lines imported into RFQ'))
}

const detachIpr = iprIdToRemove => {
  if (isReadOnly.value) return
  form.value.ipr_ids = (form.value.ipr_ids || []).filter(id => id !== iprIdToRemove)
  form.value.iprs = (form.value.iprs || []).filter(i => i.id !== iprIdToRemove)
}

const openRecordResponse = (resp = null) => {
  editingResponse.value = resp || null
  responseDialogOpen.value = true
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'import_ipr') {
    openImportIprDialog()
  }
  else if (key === 'send') {
    if (isDirty.value) {
      const saved = await handleSave()
      if (!saved) return
    }
    broadcastDialogOpen.value = true
  }
  else if (key === 'record_bid') {
    openRecordResponse(null)
  }
  else if (key === 'start_eval') {
    if (form.value.status === 'sent') {
      await executeTransition('awaiting_responses')
    }
    await executeTransition('under_evaluation')
  }
  else if (key === 'go_comparison') {
    journalNavRef.value?.scrollToSection('comparison')
  }
  else if (key === 'convert_po') {
    router.push({ path: '/supply-chain-procurement/po/edit/new', query: { from_rfq: rfqId.value } })
  }
  else if (key === 'cancel') {
    await executeTransition('cancelled')
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
  }
}

const handleToggleAward = async supplierId => {
  try {
    if (form.value.status === 'awarded') {
      await executeTransition('under_evaluation')

      return
    }
    if (!supplierId) return
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
  const raw = item.raw || item

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    variant_id: item.variant_id || null,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || item.code || '',
    item_type: raw?.item_type || item.item_type || '',
    subtype: raw?.subtype || item.subtype || '',
    unit: item.unit || '',
    unit_id: item.unit_id || null,
    quantity: qty,
    qty_requested: qty,
    unit_price: price,
    target_price_ht: price,
    line_total: qty * price,
    description: '',
    required_certifications: [],
    raw_catalog_item: raw,
  }
}

const addAdHocLine = () => {
  if (isReadOnly.value) return
  form.value.lines = [
    ...form.value.lines,
    {
      is_ad_hoc: true,
      catalog_item_id: null,
      variant_id: null,
      ad_hoc_name: '',
      ad_hoc_sku: 'NEW',
      item_name: '',
      sku: 'NEW',
      item_type: 'ad_hoc',
      subtype: '',
      unit: '',
      unit_id: null,
      quantity: 1,
      qty_requested: 1,
      unit_price: 0,
      target_price_ht: 0,
      line_total: 0,
      description: '',
      required_certifications: [],
    },
  ]
}

const resolveItemTypeBadgeColor = type => {
  const map = {
    product: 'primary',
    consumable: 'info',
    eq_servicing: 'warning',
    service: 'success',
  }

  return map[type] || 'secondary'
}

const lineColumns = computed(() => [
  { key: 'item', title: t('scp.common.item') },
  { key: 'sku', title: t('scp.common.sku', 'Variant / SKU'), width: '140px' },
  { key: 'required_certifications', title: t('scp.rfq.fields.required_certifications', 'Required Certifications'), width: '240px' },
  { key: 'quantity', title: t('scp.ipr.fields.qty_requested'), align: 'end', width: '120px' },
  { key: 'unit_price', title: t('scp.rfq.fields.target_price_ht'), align: 'end', width: '150px' },
  { key: 'line_total', title: t('scp.common.total'), align: 'end', width: '140px' },
])

const openVariantDialogForLine = (line, index) => {
  const catItem = line?.raw_catalog_item || line?.catalog_item || line?.item
  if (!catItem || !Array.isArray(catItem.variants) || catItem.variants.length === 0) return
  variantDialogCatalogItem.value = catItem
  variantDialogLineIndex.value = index
  variantDialogOpen.value = true
}

const onBulkAddLines = entries => {
  if (!Array.isArray(entries) || !entries.length) return
  const multiVariantEntry = entries.find(e => Array.isArray(e?.item?.variants) && e.item.variants.length > 1)
  if (multiVariantEntry) {
    const idx = form.value.lines.findIndex(l => l.catalog_item_id === multiVariantEntry.item.id)
    if (idx >= 0) {
      openVariantDialogForLine({ raw_catalog_item: multiVariantEntry.item }, idx)
    }
  }
}

const handleVariantSelected = ({ item, variant }) => {
  const idx = variantDialogLineIndex.value
  if (idx === null || idx === undefined || !form.value.lines[idx] || !variant) return
  const line = form.value.lines[idx]
  const unitPrice = Number(variant.cost_price ?? variant.price ?? line.unit_price ?? 0) || 0
  const qty = Number(line.quantity ?? line.qty_requested ?? 1) || 1

  form.value.lines[idx] = {
    ...line,
    variant_id: variant.id,
    sku: variant.sku || item?.sku || line.sku,
    unit_price: unitPrice,
    target_price_ht: unitPrice,
    line_total: qty * unitPrice,
  }
}

const handleDiscardChanges = () => {
  if (isCreateMode.value) {
    setDocument(normalizeRfqToForm({}))
  }
  else if (rawDocument.value) {
    setDocument(normalizeRfqToForm(rawDocument.value))
  }
}

const unsavedSummaryItems = computed(() => [
  { label: t('scp.common.lines'), value: form.value.lines?.length || 0 },
  { label: t('scp.rfq.fields.invited_suppliers'), value: invitations.value.length || form.value.invited_supplier_ids.length },
  { label: t('scp.rfq.fields.target_total'), value: formatMoney(totals.value.grandTotal), color: 'primary' },
])

const mobileQuickActions = computed(() => {
  const list = []
  if (form.value.status === 'draft') {
    list.push({
      id: 'import_ipr',
      title: t('scp.rfq.actions.import_ipr', 'Import from Approved IPR'),
      icon: 'tabler-file-import',
      color: 'info',
      handler: () => openImportIprDialog(),
    })
  }
  if (!isCreateMode.value && form.value.status === 'draft') {
    list.push({
      id: 'send',
      title: t('scp.rfq.actions.send_suppliers'),
      icon: 'tabler-send',
      color: 'primary',
      handler: () => onHeaderAction('send'),
    })
  }
  if (canRecordBid.value) {
    list.push({
      id: 'record_bid',
      title: t('scp.rfq.actions.record_bid'),
      icon: 'tabler-plus',
      color: 'primary',
      handler: () => onHeaderAction('record_bid'),
    })
  }
  if (!isCreateMode.value && form.value.status === 'awarded') {
    list.push({
      id: 'convert_po',
      title: t('scp.action.convert_to_po'),
      icon: 'tabler-shopping-cart-plus',
      color: 'success',
      handler: () => onHeaderAction('convert_po'),
    })
  }

  return list
})

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.rfq.sections.overview'), icon: 'tabler-info-circle' },
  { id: 'suppliers', title: t('scp.rfq.sections.invited_suppliers'), icon: 'tabler-building-store', badge: invitations.value.length || form.value.invited_supplier_ids.length },
  { id: 'lines', title: t('scp.rfq.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'comparison', title: t('scp.rfq.sections.comparison'), icon: 'tabler-git-compare', badge: responses.value.length },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])

const activeSection = ref('overview')
const journalNavRef = ref(null)
</script>

<template>
  <div class="rfq-edit-page">
    <WpErrorBanner
      :error="pageError"
      class="mb-4"
      @retry="reloadRfq"
    />

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
      v-if="!isCreateMode"
      :steps="lifecycleSteps"
      class="mb-4"
    />

    <WpDocumentMetadataBar
      v-if="!isCreateMode"
      :items="metadataItems"
      class="mb-6"
    />

    <VAlert
      v-else
      type="info"
      variant="tonal"
      density="comfortable"
      icon="tabler-file-plus"
      class="mb-6"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <div class="font-weight-semibold">
            {{ t('scp.rfq.new_title', 'New Request for Quotation (Draft)') }}
          </div>
          <div class="text-caption">
            {{ t('scp.rfq.subtitle') }}
          </div>
        </div>
        <VChip
          size="small"
          color="secondary"
          variant="tonal"
          label
        >
          {{ statusLabel('draft') }}
        </VChip>
      </div>
    </VAlert>

    <VSkeletonLoader
      v-if="isLoading && !rawDocument && !isCreateMode"
      type="card-avatar, article, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <div
      v-else
      class="scp-edit-layout"
    >
      <WpJournalNav
        ref="journalNavRef"
        v-model="activeSection"
        :sections="journalSections"
        :title="form.reference || t('scp.rfq.title')"
        :subtitle="statusLabel(form.status)"
        id-prefix="section-"
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
        <WpKpiGroup
          :item-count="4"
          :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
          class="mb-6"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="t('scp.rfq.fields.invited_suppliers')"
                :value="invitations.length || form.invited_supplier_ids.length"
                icon="tabler-users"
                icon-color="info"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="t('scp.rfq.sections.responses')"
                :value="responses.length"
                icon="tabler-messages"
                icon-color="primary"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="t('scp.common.lines')"
                :value="form.lines.length"
                icon="tabler-list-numbers"
                icon-color="warning"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="t('scp.rfq.fields.target_total')"
                :value="formatMoney(totals.grandTotal)"
                icon="tabler-coins"
                icon-color="success"
                :loading="isLoading"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- Section 1: Overview & Evaluation Criteria -->
        <section
          id="section-overview"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="t('scp.rfq.sections.overview')"
            icon="tabler-info-circle"
          >
            <template
              v-if="!isReadOnly && form.status === 'draft'"
              #actions
            >
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-file-import"
                min-height="40"
                @click="openImportIprDialog"
              >
                {{ t('scp.rfq.actions.import_ipr', 'Import from Approved IPR') }}
              </VBtn>
            </template>

            <!-- Linked Source IPR Chips -->
            <div
              v-if="form.iprs?.length || form.ipr_ids?.length"
              class="d-flex flex-wrap align-center gap-2 mb-4 pa-3 rounded border bg-surface"
            >
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis me-1">
                <VIcon
                  icon="tabler-link"
                  size="14"
                  class="me-1"
                />
                {{ t('scp.rfq.fields.source_iprs', 'Linked Source IPR(s)') }}:
              </span>

              <VChip
                v-for="ipr in (form.iprs?.length ? form.iprs : form.ipr_ids.map(id => ({ id, reference: `#${String(id).slice(-6)}` })))"
                :key="ipr.id"
                size="small"
                color="primary"
                variant="tonal"
                label
                :closable="!isReadOnly"
                class="font-weight-semibold cursor-pointer"
                @click="router.push(`/supply-chain-procurement/ipr/edit/${ipr.id}`)"
                @click:close="detachIpr(ipr.id)"
              >
                {{ ipr.reference || ipr.id }}
              </VChip>
            </div>

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
          class="journal-section mb-6"
        >
          <RfqSuppliersSection
            v-model:invited-supplier-ids="form.invited_supplier_ids"
            :invitations="invitations"
            :suppliers="supplierStore.suppliers"
            :readonly="isReadOnly"
          />
        </section>

        <!-- Section 3: Requested Items -->
        <section
          id="section-lines"
          class="journal-section mb-6"
        >
          <WpDocumentLinesCard
            v-model:lines="form.lines"
            :title="t('scp.rfq.sections.lines')"
            :readonly="isReadOnly"
            :loading="isLoading"
            :columns="lineColumns"
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
            @bulk-add="onBulkAddLines"
          >
            <template
              v-if="!isReadOnly && form.status === 'draft'"
              #actions
            >
              <VBtn
                size="small"
                color="info"
                variant="tonal"
                prepend-icon="tabler-file-import"
                @click="openImportIprDialog"
              >
                {{ t('scp.rfq.actions.import_ipr', 'Import from Approved IPR') }}
              </VBtn>
              <VBtn
                size="small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-Sparkles"
                @click="addAdHocLine"
              >
                {{ t('scp.ipr.actions.add_ad_hoc', 'Add Ad-Hoc Item') }}
              </VBtn>
            </template>

            <template #cell-item="{ line }">
              <div class="d-flex flex-column gap-1 py-1">
                <div class="d-flex flex-wrap align-center gap-2">
                  <VChip
                    v-if="line.is_ad_hoc"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                    label
                    class="font-weight-bold"
                  >
                    AD-HOC
                  </VChip>
                  <VChip
                    v-else-if="line.item_type || line.catalog_item?.item_type"
                    size="x-small"
                    :color="resolveItemTypeBadgeColor(line.item_type || line.catalog_item?.item_type)"
                    variant="tonal"
                    label
                    class="text-uppercase font-weight-semibold"
                  >
                    {{ line.item_type || line.catalog_item?.item_type }}
                  </VChip>
                  <span
                    v-if="!line.is_ad_hoc && (line.subtype || line.catalog_item?.subtype)"
                    class="text-caption text-medium-emphasis"
                  >
                    {{ line.subtype || line.catalog_item?.subtype }}
                  </span>
                </div>

                <VTextField
                  v-if="!isReadOnly && line.is_ad_hoc"
                  v-model="line.ad_hoc_name"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :placeholder="t('scp.common.item')"
                  @update:model-value="line.item_name = $event"
                />
                <div
                  v-else
                  class="text-body-2 font-weight-bold text-high-emphasis"
                >
                  {{ line.is_ad_hoc ? (line.ad_hoc_name || line.item_name || '—') : (line.item_name || '—') }}
                </div>

                <div
                  v-if="line.description"
                  class="text-caption text-medium-emphasis text-truncate"
                >
                  {{ line.description }}
                </div>
              </div>
            </template>

            <template #cell-sku="{ line, index }">
              <VChip
                v-if="line.is_ad_hoc"
                size="x-small"
                variant="tonal"
                color="warning"
                label
              >
                {{ line.ad_hoc_sku || line.sku || 'NEW' }}
              </VChip>
              <VChip
                v-else-if="line.sku || line.ad_hoc_sku"
                size="x-small"
                variant="tonal"
                :color="!isReadOnly && (line.raw_catalog_item?.variants?.length > 1 || line.catalog_item?.variants?.length > 1) ? 'primary' : 'secondary'"
                label
                :class="{ 'cursor-pointer': !isReadOnly && (line.raw_catalog_item?.variants?.length > 1 || line.catalog_item?.variants?.length > 1) }"
                @click="!isReadOnly && openVariantDialogForLine(line, index)"
              >
                <VIcon
                  v-if="!isReadOnly && (line.raw_catalog_item?.variants?.length > 1 || line.catalog_item?.variants?.length > 1)"
                  icon="tabler-layers-linked"
                  size="12"
                  class="me-1"
                />
                {{ line.sku || line.ad_hoc_sku }}
              </VChip>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <template #cell-required_certifications="{ line }">
              <VCombobox
                v-if="!isReadOnly"
                v-model="line.required_certifications"
                :items="commonCertOptions"
                density="compact"
                variant="outlined"
                multiple
                chips
                closable-chips
                hide-details
                :placeholder="t('scp.rfq.fields.required_certifications', 'Certs...')"
              />
              <div
                v-else-if="line.required_certifications?.length"
                class="d-flex flex-wrap gap-1"
              >
                <VChip
                  v-for="cert in line.required_certifications"
                  :key="cert"
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ cert }}
                </VChip>
              </div>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>
          </WpDocumentLinesCard>
        </section>

        <!-- Section 4: Supplier Responses & Bid Comparison Matrix -->
        <section
          id="section-comparison"
          class="journal-section mb-6"
        >
          <RfqComparisonSection
            :rfq="rawDocument || form"
            :lines="form.lines"
            :responses="responses"
            :readonly="isCreateMode"
            :submitting-award="submittingTransition"
            @record-response="openRecordResponse(null)"
            @edit-response="openRecordResponse($event)"
            @toggle-award="handleToggleAward($event)"
            @convert-to-po="router.push({ path: '/supply-chain-procurement/po/edit/new', query: { from_rfq: rfqId } })"
          />
        </section>

        <!-- Section 5: Traceability -->
        <section
          id="section-traceability"
          class="journal-section mb-6"
        >
          <WpDocumentTraceabilitySection
            :title="t('scp.ipr.sections.traceability')"
            :items="traceabilityChain"
          />
        </section>
      </main>
    </div>

    <SourceDocumentImporterDialog
      v-model="importIprDialogOpen"
      :allowed-sources="['ipr']"
      @import="handleImportSource"
    />

    <VariantSelectDialog
      v-model="variantDialogOpen"
      :catalog-item="variantDialogCatalogItem"
      @select="handleVariantSelected"
    />

    <ProcurementUnsavedBar
      :is-dirty="isDirty"
      :is-saving="isSaving"
      :is-create-mode="isCreateMode"
      :is-read-only="isReadOnly"
      :summary-items="unsavedSummaryItems"
      :mobile-actions="mobileQuickActions"
      :save-label="t('scp.action.save')"
      @save="handleSave"
      @discard="handleDiscardChanges"
    />

    <RfqBroadcastDialog
      v-model="broadcastDialogOpen"
      :rfq-id="rfqId"
      :invitations="invitations"
      :invited-supplier-ids="form.invited_supplier_ids"
      :suppliers="supplierStore.suppliers"
      @completed="reloadRfq"
    />

    <RecordRfqResponseDialog
      v-model="responseDialogOpen"
      :rfq="rawDocument"
      :response-to-edit="editingResponse"
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
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 959px) {
    flex-direction: column;
    padding-block-end: calc(104px + env(safe-area-inset-bottom, 0px));
  }
}

.scp-journal-feed {
  flex: 1 1 auto;
  min-inline-size: 0;

  @media (max-width: 959px) {
    inline-size: 100%;
  }
}

.journal-section {
  min-inline-size: 0;
  scroll-margin-block-start: 96px;

  @media (max-width: 959px) {
    scroll-margin-block-start: 24px;
  }
}
</style>
