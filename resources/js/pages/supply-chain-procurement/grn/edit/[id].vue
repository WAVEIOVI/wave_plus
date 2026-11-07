<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useGrnStore } from '@/stores/useGrnStore'
import { usePoStore } from '@/stores/usePoStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  useDocumentForm,
  useDocumentTotals,
  useDocumentTraceability,
  useDocumentWorkflow,
} from '@/composables/documents'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import GrnReceiveQcDialog from '@/views/supply-chain-procurement/grn/GrnReceiveQcDialog.vue'
import GrnBatchReceiveDialog from '@/views/supply-chain-procurement/grn/GrnBatchReceiveDialog.vue'
import ProcurementUnsavedBar from '@/views/supply-chain-procurement/shared/ProcurementUnsavedBar.vue'

definePage({
  name: 'procurement-grn-edit-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-grn-list',
    module: 'supply-chain-procurement',
    action: 'write',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { smAndDown } = useDisplay()
const grnStore = useGrnStore()
const poStore = usePoStore()
const { format: formatMoney } = useCurrency()
const { success, error: showError, showApiError } = useAppSnackbar()

const {
  statusLabel,
  userOptions,
  zoneOptions,
  locationOptions,
  overallQcResultOptions,
  currentUserId,
  ensureUsers,
  ensureWarehouses,
  ensureSiteLocations,
  formatDate,
  formatPartnerForCard,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const grnId = computed(() => route.params.id)
const isCreateMode = computed(() => grnId.value === 'new')
const rawDocument = ref(null)
const pageError = ref(null)
const receivablePos = ref([])
const lotDialogOpen = ref(false)
const batchDialogOpen = ref(false)
const editingLineIndex = ref(null)
const editingLine = ref(null)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)
const selectedZoneId = ref(null)
const deliveryNoteFile = ref(null)

const filteredLocationOptions = computed(() => locationOptions(selectedZoneId.value))
const allLocationOptions = computed(() => locationOptions(null))

const normalizeGrnToForm = doc => ({
  id: doc?.id || null,
  reference: doc?.reference || '',
  status: doc?.status || 'draft',
  po_id: doc?.po_id || doc?.purchase_order?.id || null,
  po_reference: doc?.purchase_order?.reference || '',
  purchase_order: doc?.purchase_order || null,
  supplier_id: doc?.supplier_id || doc?.supplier?.id || null,
  supplier: doc?.supplier || null,
  received_by: doc?.received_by || doc?.received_by_user?.id || doc?.receiver?.id || currentUserId.value || '',
  received_at: doc?.received_at ? String(doc.received_at).slice(0, 10) : '',
  supplier_delivery_note_ref: doc?.supplier_delivery_note_ref || '',
  allow_over_receive: Boolean(doc?.allow_over_receive),
  is_partial_delivery: Boolean(doc?.is_partial_delivery),
  overall_qc_result: doc?.overall_qc_result || 'passed',
  qc_performed_by: doc?.qc_performed_by || null,
  qc_performed_at: doc?.qc_performed_at ? String(doc.qc_performed_at).slice(0, 10) : '',
  lines: (doc?.lines || []).map(l => ({
    ...l,
    id: l.id || null,
    po_line_id: l.po_line_id || null,
    catalog_item_id: l.catalog_item_id || null,
    catalog_variant_id: l.catalog_variant_id || l.variant_id || l.catalog_variant?.id || null,
    is_ad_hoc: Boolean(l.is_ad_hoc || (!l.catalog_item_id && l.ad_hoc_name)),
    ad_hoc_name: l.ad_hoc_name || '',
    ad_hoc_sku: l.ad_hoc_sku || '',
    item_name: resolveLineItemName(l),
    sku: resolveLineItemSku(l),
    qty_ordered: Number(l.po_line?.qty_ordered ?? l.qty_ordered ?? l.qty_received ?? 0) || 0,
    qty_received: Number(l.qty_received ?? 0) || 0,
    qty_accepted: Number(l.qty_accepted ?? l.qty_received ?? 0) || 0,
    qty_rejected: Number(l.qty_rejected ?? 0) || 0,
    unit_cost_ht: Number(l.unit_cost_ht ?? 0) || 0,
    storage_location_id: l.storage_location_id || null,
    lot_number: l.lot_number || '',
    expiry_date: l.expiry_date ? String(l.expiry_date).slice(0, 10) : '',
    qc_result: l.qc_result || 'passed',
    qc_notes: l.qc_notes || '',
    certificates_received: Array.isArray(l.certificates_received) ? [...l.certificates_received] : [],
  })),
})

const {
  document: form,
  loading: isLoading,
  saving: isSaving,
  isDirty,
  isReadonly: isReadOnly,
  fieldErrors,
  setDocument,
  applyServerErrors,
  unsavedGuard,
} = useDocumentForm({
  initial: () => normalizeGrnToForm({}),
  lockedStatuses: ['confirmed', 'fully_rejected', 'stocked'],
  unsavedGuard: true,
})

const RECEIVABLE_PO_STATUSES = ['approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received']

const receivablePoOptions = computed(() =>
  receivablePos.value.map(po => ({
    title: `${po.reference} — ${resolveSupplierName(po.supplier)}`,
    subtitle: po.committed_delivery_date ? `${t('scp.po.fields.committed_delivery_date')}: ${formatDate(po.committed_delivery_date)}` : '',
    value: po.id,
  })),
)

const populateFromPo = async poId => {
  if (!poId) return
  const po = await poStore.fetchDetails(String(poId)).catch(() => null)

  if (!po) return

  form.value.po_id = po.id
  form.value.po_reference = po.reference || ''
  form.value.purchase_order = po
  form.value.supplier_id = po.supplier_id || po.supplier?.id || null
  form.value.supplier = po.supplier || null
  if (!form.value.received_at) {
    form.value.received_at = new Date().toISOString().slice(0, 10)
  }
  await ensureSiteLocations(po.site_id || po.site?.id || null)

  form.value.lines = (po.lines || [])
    .map(pl => {
      const ordered = Number(pl.qty_ordered ?? pl.quantity ?? 0) || 0
      const alreadyReceived = Number(pl.qty_received ?? 0) || 0
      const remaining = Math.max(0, ordered - alreadyReceived)

      return { pl, ordered, alreadyReceived, remaining }
    })
    .filter(({ pl, remaining }) => pl.status !== 'fully_received' && remaining > 0)
    .map(({ pl, ordered, alreadyReceived, remaining }) => ({
      id: null,
      po_line_id: pl.id || null,
      catalog_item_id: pl.catalog_item_id || null,
      catalog_item: pl.catalog_item || null,
      catalog_variant_id: pl.variant_id || pl.variant?.id || null,
      catalog_variant: pl.variant || null,
      is_ad_hoc: Boolean(pl.is_ad_hoc || (!pl.catalog_item_id && pl.ad_hoc_name)),
      ad_hoc_name: pl.ad_hoc_name || '',
      ad_hoc_sku: pl.ad_hoc_sku || '',
      item_name: resolveLineItemName(pl),
      sku: resolveLineItemSku(pl),
      qty_ordered: ordered,
      qty_already_received: alreadyReceived,
      qty_received: remaining,
      qty_accepted: remaining,
      qty_rejected: 0,
      unit_cost_ht: Number(pl.unit_price_ht ?? pl.unit_price ?? 0) || 0,
      storage_location_id: null,
      lot_number: '',
      expiry_date: '',
      qc_result: 'passed',
      qc_notes: '',
      certificates_received: [],
    }))

  if (!form.value.lines.length) {
    showError(t('scp.grn.snackbar.po_fully_received', 'All lines of this purchase order are already fully received.'))
  }
}

const applyPoToGrnForm = populateFromPo

const loadGrn = async () => {
  isLoading.value = true
  pageError.value = null
  try {
    await Promise.all([ensureUsers(), ensureWarehouses()])
    deliveryNoteFile.value = null

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizeGrnToForm({
        received_at: new Date().toISOString().slice(0, 10),
      }))

      const poRes = await poStore.fetchList({ per_page: 100 }).catch(() => null)

      receivablePos.value = (poRes?.data || poStore.list || []).filter(p => RECEIVABLE_PO_STATUSES.includes(p.status))

      // ULIDs: never coerce to Number.
      const fromPoId = route.query?.from_po || route.query?.po_id || null

      if (fromPoId) {
        const id = String(fromPoId)

        await populateFromPo(id)
        if (form.value.purchase_order && !receivablePos.value.some(p => p.id === id)) {
          receivablePos.value = [form.value.purchase_order, ...receivablePos.value]
        }
      }

      return
    }

    const doc = await grnStore.fetchDetails(grnId.value)
    if (doc) {
      rawDocument.value = doc
      setDocument(normalizeGrnToForm(doc))
      await ensureSiteLocations(doc.purchase_order?.site_id || null)
    }
  }
  catch (err) {
    pageError.value = err?.data?.message || err?.message || t('scp.grn.snackbar.error_load', 'Failed to load Goods Receipt Note')
    showApiError(err, t('scp.grn.snackbar.error_load', 'Failed to load Goods Receipt Note'))
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadGrn)

watch(grnId, (next, prev) => {
  if (next && next !== prev) loadGrn()
})

const hasUnsavedFile = computed(() => deliveryNoteFile.value instanceof File)

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { fields: 'grn' })

const workflow = useDocumentWorkflow(rawDocument, { config: 'grn' })

const executeTransition = async (targetStatus, extraPayload = {}) => {
  if (isCreateMode.value || !grnId.value) return null
  submittingTransition.value = true
  try {
    const updated = await grnStore.updateStatus(grnId.value, {
      status: targetStatus,
      allow_over_receive: Boolean(form.value.allow_over_receive),
      ...extraPayload,
    })

    rawDocument.value = updated
    setDocument(normalizeGrnToForm(updated))
    success(t('scp.grn.snackbar.status_updated'))

    return updated
  }
  catch (err) {
    showApiError(err, t('scp.grn.snackbar.error_status'))
    throw err
  }
  finally {
    submittingTransition.value = false
  }
}

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { type: 'grn' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const metadataItems = computed(() => [
  {
    key: 'po',
    icon: 'tabler-shopping-cart',
    label: t('scp.grn.fields.linked_po'),
    value: form.value.purchase_order?.reference || form.value.po_reference || '—',
  },
  {
    key: 'supplier',
    icon: 'tabler-building-store',
    label: t('scp.common.supplier'),
    value: resolveSupplierName(form.value.supplier),
  },
  {
    key: 'received_at',
    icon: 'tabler-calendar',
    label: t('scp.grn.fields.received_at'),
    value: formatDate(form.value.received_at),
  },
  {
    key: 'delivery_note',
    icon: 'tabler-file-invoice',
    label: t('scp.grn.fields.delivery_note_ref'),
    value: form.value.supplier_delivery_note_ref || '—',
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
      key: 'start_qc',
      label: t('scp.grn.action.start_qc'),
      icon: 'tabler-test-pipe',
      color: 'primary',
      loading: submittingTransition.value,
    }
  }
  if (st === 'under_qc' || st === 'partially_rejected') {
    return {
      key: 'confirm_receipt',
      label: t('scp.grn.action.confirm_receipt'),
      icon: 'tabler-circle-check',
      color: 'success',
      loading: submittingTransition.value,
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
    disabled: isReadOnly.value || (!isDirty.value && !hasUnsavedFile.value && !isCreateMode.value),
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'partial_reject',
    label: statusLabel('partially_rejected'),
    icon: 'tabler-alert-triangle',
    color: 'warning',
    hidden: isCreateMode.value || form.value.status !== 'under_qc',
  },
  {
    key: 'full_reject',
    label: statusLabel('fully_rejected'),
    icon: 'tabler-circle-x',
    color: 'error',
    hidden: isCreateMode.value || !['under_qc', 'partially_rejected'].includes(form.value.status),
  },
  {
    key: 'match',
    label: t('scp.action.open_match_page'),
    icon: 'tabler-git-compare',
    color: 'info',
    hidden: !form.value.po_id,
  },
  {
    key: 'delete',
    label: t('scp.action.delete'),
    icon: 'tabler-trash',
    color: 'error',
    hidden: isCreateMode.value || isReadOnly.value,
  },
])

const buildSaveBody = current => {
  const mappedLines = (current.lines || []).map(l => {
    const isAdHoc = Boolean(l.is_ad_hoc || !l.catalog_item_id)

    return {
      ...(l.id ? { id: l.id } : {}),
      po_line_id: l.po_line_id,
      is_ad_hoc: isAdHoc,
      catalog_item_id: isAdHoc ? null : (l.catalog_item_id || null),
      catalog_variant_id: isAdHoc ? null : (l.catalog_variant_id || l.variant_id || null),
      ad_hoc_name: isAdHoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || l.sku || null) : null,
      qty_received: Number(l.qty_received) || 0.01,
      qty_accepted: Number(l.qty_accepted) || 0,
      qty_rejected: Number(l.qty_rejected) || 0,
      unit_cost_ht: Number(l.unit_cost_ht) || 0,
      storage_location_id: l.storage_location_id || null,
      lot_number: l.lot_number || null,
      expiry_date: l.expiry_date || null,
      qc_result: l.qc_result || 'passed',
      qc_notes: l.qc_notes || null,
      certificates_received: Array.isArray(l.certificates_received) ? l.certificates_received : [],
    }
  })

  if (deliveryNoteFile.value instanceof File) {
    const fd = new FormData()
    if (!isCreateMode.value) fd.append('_method', 'PUT')
    if (current.po_id) fd.append('po_id', current.po_id)
    if (current.supplier_id) fd.append('supplier_id', current.supplier_id)
    if (current.received_by) fd.append('received_by', current.received_by)
    if (current.received_at) fd.append('received_at', current.received_at)
    if (current.supplier_delivery_note_ref) fd.append('supplier_delivery_note_ref', current.supplier_delivery_note_ref)
    fd.append('allow_over_receive', current.allow_over_receive ? '1' : '0')
    fd.append('is_partial_delivery', current.is_partial_delivery ? '1' : '0')
    if (current.overall_qc_result) fd.append('overall_qc_result', current.overall_qc_result)
    if (current.qc_performed_by) fd.append('qc_performed_by', current.qc_performed_by)
    if (current.qc_performed_at) fd.append('qc_performed_at', current.qc_performed_at)
    fd.append('delivery_note_file', deliveryNoteFile.value)

    mappedLines.forEach((line, i) => {
      Object.entries(line).forEach(([key, val]) => {
        if (val === null || val === undefined) return
        if (Array.isArray(val)) {
          val.forEach((entry, j) => {
            fd.append(`lines[${i}][${key}][${j}]`, String(entry))
          })
        }
        else {
          fd.append(`lines[${i}][${key}]`, typeof val === 'boolean' ? (val ? '1' : '0') : String(val))
        }
      })
    })

    return fd
  }

  return {
    po_id: current.po_id,
    supplier_id: current.supplier_id,
    received_by: current.received_by,
    received_at: current.received_at,
    supplier_delivery_note_ref: current.supplier_delivery_note_ref || null,
    allow_over_receive: Boolean(current.allow_over_receive),
    is_partial_delivery: Boolean(current.is_partial_delivery),
    overall_qc_result: current.overall_qc_result || null,
    qc_performed_by: current.qc_performed_by || null,
    qc_performed_at: current.qc_performed_at || null,
    lines: mappedLines,
  }
}

const handleSave = async () => {
  isSaving.value = true
  try {
    const body = buildSaveBody(form.value)
    if (isCreateMode.value) {
      const created = await grnStore.create(body)

      rawDocument.value = created
      deliveryNoteFile.value = null
      setDocument(normalizeGrnToForm(created))
      success(t('scp.grn.snackbar.created_success'))
      await router.replace(`/supply-chain-procurement/grn/edit/${created.id}`)

      return created
    }

    const updated = await grnStore.update(grnId.value, body)

    rawDocument.value = updated
    deliveryNoteFile.value = null
    setDocument(normalizeGrnToForm(updated))
    success(t('scp.grn.snackbar.updated_success'))

    return updated
  }
  catch (err) {
    applyServerErrors(err)
    showApiError(err, t('scp.grn.snackbar.error_save'))
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
  else if (key === 'start_qc') {
    if (isDirty.value || hasUnsavedFile.value) await handleSave()
    await executeTransition('under_qc')
  }
  else if (key === 'confirm_receipt') {
    if (!validateStorageBeforeConfirm()) return
    if (isDirty.value || hasUnsavedFile.value) await handleSave()
    await executeTransition('confirmed')
  }
  else if (key === 'partial_reject') {
    if (isDirty.value || hasUnsavedFile.value) await handleSave()
    await executeTransition('partially_rejected')
  }
  else if (key === 'full_reject') {
    if (isDirty.value || hasUnsavedFile.value) await handleSave()
    await executeTransition('fully_rejected')
  }
  else if (key === 'match') {
    if (form.value.po_id) {
      router.push(`/supply-chain-procurement/po/match/${form.value.po_id}`)
    }
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
  }
}

const isLineStockable = line => !line?.is_ad_hoc && line?.catalog_item?.is_stockable !== false

// Legacy handleStatusSubmit rule: every stockable line with accepted quantity needs a bin location.
const validateStorageBeforeConfirm = () => {
  const missing = form.value.lines.filter(l =>
    isLineStockable(l) && (Number(l.qty_accepted) || 0) > 0 && !l.storage_location_id,
  )

  if (missing.length) {
    showError(t(
      'scp.grn.snackbar.storage_required',
      { count: missing.length },
      `Please assign a storage location to ${missing.length} stockable line(s) before confirming receipt.`,
    ))

    return false
  }

  return true
}

const varianceOf = line => {
  const ordered = Number(line.qty_ordered) || 0
  const received = Number(line.qty_received) || 0

  if (!ordered || received === ordered) return null

  return received > ordered ? 'over' : 'under'
}

const expectedDeliveryDate = computed(() =>
  form.value.purchase_order?.committed_delivery_date
  || rawDocument.value?.purchase_order?.committed_delivery_date
  || null,
)

const deliveryPerformance = computed(() => {
  const expected = expectedDeliveryDate.value
  const received = form.value.received_at

  if (!expected || !received) {
    return { expected, onTime: rawDocument.value?.delivery_on_time ?? null, daysLate: Number(rawDocument.value?.days_late) || 0 }
  }

  const diff = Math.round((new Date(received) - new Date(String(expected).slice(0, 10))) / 86400000)

  return { expected, onTime: diff <= 0, daysLate: Math.max(0, diff) }
})

const openBatchDialog = () => {
  batchDialogOpen.value = true
}

const onBatchApply = updatedLines => {
  if (!Array.isArray(updatedLines)) return
  form.value.lines = updatedLines
}

const receiveAllRemaining = () => {
  if (isReadOnly.value) return
  const defaultLoc = filteredLocationOptions.value[0]?.value || allLocationOptions.value[0]?.value || null

  form.value.lines = form.value.lines.map(l => {
    const qty = Math.max(0, (Number(l.qty_ordered) || 0) - (Number(l.qty_already_received) || 0)) || Number(l.qty_received) || 1

    return {
      ...l,
      qty_received: qty,
      qty_accepted: qty,
      qty_rejected: 0,
      storage_location_id: l.storage_location_id || defaultLoc,
      qc_result: 'passed',
    }
  })
}

const batchLocationId = ref(null)

const applyBatchLocation = locId => {
  batchLocationId.value = locId
  if (!locId || isReadOnly.value) return
  form.value.lines = form.value.lines.map(l => ({
    ...l,
    storage_location_id: locId,
  }))
}

const openLineModal = (line, idx) => {
  editingLineIndex.value = idx
  editingLine.value = line
  lotDialogOpen.value = true
}

const onLineModalSave = updatedLine => {
  if (editingLineIndex.value === null) return
  form.value.lines = form.value.lines.map((l, i) => (i === editingLineIndex.value ? updatedLine : l))
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await grnStore.deleteItem(grnId.value)
    success(t('scp.grn.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/grn/list')
  }
  catch (err) {
    showApiError(err, t('scp.grn.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const totalReceivedQty = computed(() =>
  form.value.lines.reduce((s, l) => s + (Number(l.qty_received) || 0), 0),
)

const totalAcceptedQty = computed(() =>
  form.value.lines.reduce((s, l) => s + (Number(l.qty_accepted) || 0), 0),
)

const totalRejectedQty = computed(() =>
  form.value.lines.reduce((s, l) => s + (Number(l.qty_rejected) || 0), 0),
)

const rejectedLines = computed(() =>
  form.value.lines.filter(l => (Number(l.qty_rejected) || 0) > 0 || l.qc_result === 'rejected'),
)

const locationNameOf = id => {
  if (!id) return '—'
  const found = allLocationOptions.value.find(loc => loc.value === id)

  return found?.title || '—'
}

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.grn.sections.receiving_details'), icon: 'tabler-truck-delivery' },
  { id: 'lines', title: t('scp.grn.tabs.lines'), icon: 'tabler-package-import', badge: form.value.lines.length },
  { id: 'qc', title: t('scp.grn.tabs.qc'), icon: 'tabler-test-pipe', badge: rejectedLines.value.length || undefined },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])

const activeSection = ref('overview')
const journalNavRef = ref(null)

const handleDiscardChanges = () => {
  deliveryNoteFile.value = null
  if (isCreateMode.value) {
    setDocument(normalizeGrnToForm({
      received_at: new Date().toISOString().slice(0, 10),
    }))
  }
  else if (rawDocument.value) {
    setDocument(normalizeGrnToForm(rawDocument.value))
  }
}

const unsavedSummaryItems = computed(() => [
  { label: t('scp.grn.qty.received'), value: totalReceivedQty.value },
  { label: t('scp.grn.qty.accepted'), value: totalAcceptedQty.value, color: 'success' },
  { label: t('scp.grn.qty.rejected'), value: totalRejectedQty.value, color: totalRejectedQty.value > 0 ? 'error' : undefined },
  { label: t('scp.common.total'), value: formatMoney(totals.value.grandTotal), color: 'primary' },
])

const mobileQuickActions = computed(() => {
  const list = []
  if (!isReadOnly.value && form.value.lines.length > 0) {
    list.push({
      id: 'receive_all',
      title: t('scp.grn.action.receive_all_remaining'),
      icon: 'tabler-checks',
      color: 'success',
      handler: () => receiveAllRemaining(),
    })
  }
  if (!isCreateMode.value && form.value.status === 'draft') {
    list.push({
      id: 'start_qc',
      title: t('scp.grn.action.start_qc'),
      icon: 'tabler-test-pipe',
      color: 'primary',
      handler: () => onHeaderAction('start_qc'),
    })
  }
  if (!isCreateMode.value && (form.value.status === 'under_qc' || form.value.status === 'partially_rejected')) {
    list.push({
      id: 'confirm_receipt',
      title: t('scp.grn.action.confirm_receipt'),
      icon: 'tabler-circle-check',
      color: 'success',
      handler: () => onHeaderAction('confirm_receipt'),
    })
  }
  if (form.value.po_id) {
    list.push({
      id: 'match',
      title: t('scp.action.open_match_page'),
      icon: 'tabler-git-compare',
      color: 'info',
      handler: () => onHeaderAction('match'),
    })
  }

  return list
})
</script>

<template>
  <div class="grn-edit-page">
    <WpErrorBanner
      :error="pageError"
      class="mb-4"
      @retry="loadGrn"
    />

    <WpDocumentHeader
      :title="form.reference || t('scp.grn.new_title', 'New Goods Receipt Note')"
      :reference="form.reference"
      :subtitle="form.supplier ? resolveSupplierName(form.supplier) : t('scp.grn.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.grn'), to: '/supply-chain-procurement/grn/list' },
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
        :title="form.reference || t('scp.grn.title')"
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
              :disabled="!isDirty && !hasUnsavedFile && !isCreateMode"
              class="mb-2 font-weight-semibold"
              @click="handleSave"
            >
              {{ t('scp.action.save') }}
            </VBtn>
            <VBtn
              v-if="!isCreateMode"
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
                :title="t('scp.grn.qty.received')"
                :value="totalReceivedQty"
                icon="tabler-package-import"
                icon-color="primary"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="t('scp.grn.qty.accepted')"
                :value="totalAcceptedQty"
                icon="tabler-circle-check"
                icon-color="success"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="t('scp.grn.qty.rejected')"
                :value="totalRejectedQty"
                icon="tabler-alert-triangle"
                icon-color="error"
                :loading="isLoading"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="t('scp.common.total')"
                :value="formatMoney(totals.grandTotal)"
                icon="tabler-coins"
                icon-color="info"
                :loading="isLoading"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- Section 1: Receiving Overview & Delivery Note Attachment -->
        <section
          id="section-overview"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="t('scp.grn.sections.receiving_details')"
            icon="tabler-truck-delivery"
          >
            <VRow>
              <VCol
                v-if="isCreateMode"
                cols="12"
              >
                <VCard
                  variant="tonal"
                  color="primary"
                  class="pa-3"
                >
                  <div class="text-subtitle-2 font-weight-bold mb-2 d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-shopping-cart"
                      size="18"
                    />
                    {{ t('scp.grn.fields.source_po', 'Source Purchase Order') }}
                  </div>
                  <VAutocomplete
                    :model-value="form.po_id"
                    :items="receivablePoOptions"
                    item-title="title"
                    item-value="value"
                    :label="t('scp.grn.fields.linked_po')"
                    :placeholder="t('scp.grn.fields.select_po', 'Select a receivable purchase order')"
                    prepend-inner-icon="tabler-search"
                    clearable
                    variant="outlined"
                    bg-color="surface"
                    :no-data-text="t('scp.grn.empty.no_receivable_po', 'No receivable purchase orders')"
                    :error-messages="fieldErrors('po_id')"
                    @update:model-value="populateFromPo"
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem
                        v-bind="itemProps"
                        :title="item.raw.title"
                        :subtitle="item.raw.subtitle"
                      />
                    </template>
                  </VAutocomplete>
                </VCard>
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <WpPartnerCard
                  :partner="formatPartnerForCard(form.supplier)"
                  :partner-label="t('scp.common.supplier')"
                  :fields="{
                    name: 'legal_name',
                    tradeName: 'commercial_name',
                    code: 'supplier_id',
                    status: 'status',
                    email: 'primary_email',
                    phone: 'primary_phone',
                  }"
                  locked
                  readonly
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VAutocomplete
                      v-model="form.received_by"
                      :items="userOptions"
                      item-title="title"
                      item-value="value"
                      :label="t('scp.grn.fields.received_by')"
                      :readonly="isReadOnly"
                      :error-messages="fieldErrors('received_by')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VTextField
                      v-model="form.received_at"
                      type="date"
                      :label="t('scp.grn.fields.received_at')"
                      :readonly="isReadOnly"
                      :error-messages="fieldErrors('received_at')"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextField
                      v-model="form.supplier_delivery_note_ref"
                      :label="t('scp.grn.fields.delivery_note_ref')"
                      prepend-inner-icon="tabler-file-invoice"
                      :readonly="isReadOnly"
                      :error-messages="fieldErrors('supplier_delivery_note_ref')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSwitch
                      v-model="form.is_partial_delivery"
                      color="primary"
                      :label="t('scp.grn.fields.partial_delivery')"
                      :readonly="isReadOnly"
                      hide-details
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSwitch
                      v-model="form.allow_over_receive"
                      color="warning"
                      :label="t('scp.grn.fields.allow_over_receive')"
                      :readonly="isReadOnly"
                      hide-details
                    />
                  </VCol>

                  <!-- Delivery Performance (from linked PO) -->
                  <VCol
                    v-if="expectedDeliveryDate"
                    cols="12"
                  >
                    <div class="d-flex flex-wrap align-center gap-2 pa-3 rounded border">
                      <VIcon
                        icon="tabler-clock-check"
                        size="18"
                        color="primary"
                      />
                      <span class="text-caption text-medium-emphasis">
                        {{ t('scp.grn.fields.expected_delivery_date', 'Expected delivery') }}:
                      </span>
                      <strong>{{ formatDate(deliveryPerformance.expected) }}</strong>
                      <VChip
                        v-if="deliveryPerformance.onTime !== null"
                        size="small"
                        label
                        variant="tonal"
                        :color="deliveryPerformance.onTime ? 'success' : 'error'"
                        :prepend-icon="deliveryPerformance.onTime ? 'tabler-circle-check' : 'tabler-alert-triangle'"
                      >
                        {{ deliveryPerformance.onTime ? t('scp.grn.fields.on_time', 'On time') : t('scp.grn.fields.late', 'Late') }}
                      </VChip>
                      <VChip
                        v-if="!deliveryPerformance.onTime && deliveryPerformance.daysLate > 0"
                        size="small"
                        label
                        color="error"
                      >
                        {{ deliveryPerformance.daysLate }} {{ t('scp.common.days', 'days') }} {{ t('scp.grn.fields.days_late', 'late') }}
                      </VChip>
                    </div>
                  </VCol>
                </VRow>
              </VCol>

              <!-- Delivery Note File Download & Upload -->
              <VCol cols="12">
                <VDivider class="my-2" />
                <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-paperclip"
                      size="20"
                      color="primary"
                    />
                    <span class="text-subtitle-2 font-weight-bold">
                      {{ t('scp.grn.fields.delivery_note_file', 'Supplier Delivery Note Attachment') }}
                    </span>
                  </div>

                  <VBtn
                    v-if="rawDocument?.delivery_note_download_url"
                    :href="rawDocument.delivery_note_download_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    size="small"
                    color="info"
                    variant="tonal"
                    prepend-icon="tabler-download"
                  >
                    {{ t('scp.grn.action.download_delivery_note', 'Download Delivery Note') }}
                  </VBtn>
                </div>

                <WpAttachmentField
                  v-if="!isReadOnly"
                  v-model="deliveryNoteFile"
                  :label="t('scp.grn.fields.delivery_note_file', 'Upload / Replace Delivery Note (PDF, JPG, PNG)')"
                  category="certificate"
                  accept=".pdf,image/jpeg,image/png"
                  :max-size-mb="10"
                />
              </VCol>
            </VRow>
          </WpSectionCard>
        </section>

        <!-- Section 2: Receiving Lines & Batch Receive / QC -->
        <section
          id="section-lines"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="t('scp.grn.sections.receiving_qc')"
            icon="tabler-package-import"
          >
            <template
              v-if="!isReadOnly"
              #actions
            >
              <VBtn
                size="small"
                color="info"
                variant="tonal"
                prepend-icon="tabler-list-check"
                min-height="40"
                :disabled="!form.lines.length"
                @click="openBatchDialog"
              >
                {{ t('scp.grn.action.batch_receive', 'Batch Receive') }}
              </VBtn>
              <VBtn
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-checks"
                min-height="40"
                @click="receiveAllRemaining"
              >
                {{ t('scp.grn.action.receive_all_remaining') }}
              </VBtn>
            </template>

            <!-- Zone & Batch Location Filter Bar -->
            <VRow
              v-if="!isReadOnly"
              class="mb-4"
            >
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="selectedZoneId"
                  :items="zoneOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.grn.fields.warehouse_zone', 'Filter by Warehouse Zone')"
                  prepend-inner-icon="tabler-layers"
                  density="compact"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  :model-value="batchLocationId"
                  :items="filteredLocationOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.grn.fields.apply_location_all', 'Batch Apply Bin Location to All Lines')"
                  prepend-inner-icon="tabler-map-pin"
                  density="compact"
                  clearable
                  hide-details
                  @update:model-value="applyBatchLocation"
                />
              </VCol>
            </VRow>

            <!-- Mobile Line Cards -->
            <div
              v-if="smAndDown"
              class="d-flex flex-column gap-3"
            >
              <WpMobileCard
                v-for="(line, idx) in form.lines"
                :key="line.id || idx"
                :title="line.item_name"
                :subtitle="`${locationNameOf(line.storage_location_id)} · ${line.lot_number || t('scp.common.not_set')}`"
                clickable
                @click="openLineModal(line, idx)"
              >
                <template #badges>
                  <VChip
                    v-if="!isLineStockable(line)"
                    size="x-small"
                    label
                    color="warning"
                    variant="tonal"
                  >
                    {{ t('scp.grn.badges.non_stockable', 'Non-Stockable') }}
                  </VChip>
                  <VChip
                    v-if="varianceOf(line)"
                    size="x-small"
                    label
                    variant="tonal"
                    :color="varianceOf(line) === 'over' ? 'warning' : 'error'"
                  >
                    {{ varianceOf(line) === 'over' ? t('scp.grn.badges.over_received', 'Over Received') : t('scp.grn.badges.under_received', 'Under Received') }}
                  </VChip>
                  <VChip
                    size="x-small"
                    label
                    variant="tonal"
                    :color="line.qc_result === 'rejected' ? 'error' : line.qc_result === 'passed_with_remarks' ? 'warning' : 'success'"
                  >
                    {{ t(`scp.grn.qc_results.${line.qc_result || 'passed'}`) }}
                  </VChip>
                  <VChip
                    v-for="cert in line.certificates_received || []"
                    :key="cert"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    label
                  >
                    {{ cert }}
                  </VChip>
                </template>

                <template #content>
                  <div class="d-flex justify-space-between text-caption mt-1">
                    <span>{{ t('scp.grn.qty.received') }}: <strong>{{ line.qty_received }}</strong></span>
                    <span>{{ t('scp.grn.qty.accepted') }}: <strong class="text-success">{{ line.qty_accepted }}</strong></span>
                    <span>{{ t('scp.grn.qty.rejected') }}: <strong class="text-error">{{ line.qty_rejected }}</strong></span>
                  </div>
                </template>
              </WpMobileCard>
            </div>

            <!-- Desktop Receiving Lines Table -->
            <VTable
              v-else
              density="comfortable"
            >
              <thead>
                <tr>
                  <th>{{ t('scp.common.item') }}</th>
                  <th class="text-end">
                    {{ t('scp.grn.qty.received') }}
                  </th>
                  <th class="text-end">
                    {{ t('scp.grn.qty.accepted') }}
                  </th>
                  <th class="text-end">
                    {{ t('scp.grn.qty.rejected') }}
                  </th>
                  <th>{{ t('scp.grn.fields.storage_location') }}</th>
                  <th>{{ t('scp.grn.fields.batch_number') }}</th>
                  <th>{{ t('scp.grn.fields.qc_result') }}</th>
                  <th class="text-end">
                    {{ t('common.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(line, idx) in form.lines"
                  :key="line.id || idx"
                >
                  <td>
                    <div class="font-weight-medium">
                      {{ line.item_name }}
                    </div>
                    <div class="d-flex flex-wrap align-center gap-1 mt-1">
                      <VChip
                        v-if="line.catalog_item?.code"
                        size="x-small"
                        label
                        color="primary"
                        variant="tonal"
                      >
                        {{ line.catalog_item.code }}
                      </VChip>
                      <VChip
                        v-if="line.sku"
                        size="x-small"
                        label
                        variant="outlined"
                      >
                        {{ line.sku }}
                      </VChip>
                      <VChip
                        v-if="!isLineStockable(line)"
                        size="x-small"
                        label
                        color="warning"
                        variant="tonal"
                      >
                        {{ t('scp.grn.badges.non_stockable', 'Non-Stockable') }}
                      </VChip>
                    </div>
                  </td>
                  <td class="text-end font-weight-bold">
                    <div>{{ line.qty_received }} <span class="text-caption text-medium-emphasis">/ {{ line.qty_ordered }}</span></div>
                    <VChip
                      v-if="varianceOf(line)"
                      size="x-small"
                      label
                      variant="tonal"
                      :color="varianceOf(line) === 'over' ? 'warning' : 'error'"
                    >
                      {{ varianceOf(line) === 'over' ? t('scp.grn.badges.over_received', 'Over Received') : t('scp.grn.badges.under_received', 'Under Received') }}
                    </VChip>
                  </td>
                  <td class="text-end text-success font-weight-bold">
                    {{ line.qty_accepted }}
                  </td>
                  <td class="text-end">
                    <span :class="Number(line.qty_rejected) > 0 ? 'text-error font-weight-bold' : 'text-disabled'">
                      {{ line.qty_rejected }}
                    </span>
                  </td>
                  <td>
                    <VSelect
                      v-if="!isReadOnly"
                      v-model="line.storage_location_id"
                      :items="filteredLocationOptions"
                      item-title="title"
                      item-value="value"
                      density="compact"
                      hide-details
                      clearable
                      style="min-inline-size: 170px;"
                    />
                    <span v-else>{{ locationNameOf(line.storage_location_id) }}</span>
                  </td>
                  <td>
                    <div>{{ line.lot_number || '—' }}</div>
                    <div
                      v-if="(line.certificates_received || []).length"
                      class="d-flex flex-wrap gap-1 mt-1"
                    >
                      <VChip
                        v-for="cert in line.certificates_received"
                        :key="cert"
                        size="x-small"
                        color="info"
                        variant="tonal"
                        label
                      >
                        {{ cert }}
                      </VChip>
                    </div>
                  </td>
                  <td>
                    <VChip
                      size="x-small"
                      label
                      variant="tonal"
                      :color="line.qc_result === 'rejected' ? 'error' : line.qc_result === 'passed_with_remarks' ? 'warning' : 'success'"
                    >
                      {{ t(`scp.grn.qc_results.${line.qc_result || 'passed'}`) }}
                    </VChip>
                  </td>
                  <td class="text-end">
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-edit"
                      @click="openLineModal(line, idx)"
                    >
                      {{ t('scp.grn.action.receive_qc') }}
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </WpSectionCard>
        </section>

        <!-- Section 3: Quality Inspection & Rejected Items Summary -->
        <section
          id="section-qc"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="t('scp.grn.sections.qc_summary')"
            icon="tabler-test-pipe"
          >
            <VRow>
              <VCol
                cols="12"
                sm="4"
              >
                <VSelect
                  v-model="form.overall_qc_result"
                  :items="overallQcResultOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.grn.fields.overall_qc_result')"
                  :readonly="isReadOnly"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <VAutocomplete
                  v-model="form.qc_performed_by"
                  :items="userOptions"
                  item-title="title"
                  item-value="value"
                  :label="t('scp.grn.fields.qc_performed_by')"
                  :readonly="isReadOnly"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <VTextField
                  v-model="form.qc_performed_at"
                  type="date"
                  :label="t('scp.grn.fields.qc_performed_at')"
                  :readonly="isReadOnly"
                />
              </VCol>
            </VRow>

            <!-- Rejected Items -->
            <VDivider class="my-4" />

            <div
              v-if="!rejectedLines.length"
              class="d-flex flex-column align-center text-center py-6"
            >
              <VAvatar
                color="success"
                variant="tonal"
                size="56"
                class="mb-3"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="30"
                />
              </VAvatar>
              <div class="text-subtitle-1 font-weight-bold text-success">
                {{ t('scp.grn.empty.zero_rejected', 'Zero Rejected Items') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('scp.grn.empty.zero_rejected_desc', 'All received items passed quality inspection.') }}
              </div>
            </div>

            <template v-else>
              <VAlert
                type="error"
                variant="tonal"
                density="comfortable"
                icon="tabler-alert-octagon"
                class="mb-4"
              >
                <div class="font-weight-bold">
                  {{ t('scp.grn.sections.rejected_summary', 'Rejected Items Summary') }} ({{ totalRejectedQty }})
                </div>
                <div class="text-caption">
                  {{ t('scp.grn.alerts.rejected_items_notice', 'The following items failed quality control or had quantities rejected.') }}
                </div>
              </VAlert>

              <div class="overflow-x-auto">
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th>{{ t('scp.common.item') }}</th>
                      <th class="text-end">
                        {{ t('scp.grn.qty.ordered', 'Ordered') }}
                      </th>
                      <th class="text-end">
                        {{ t('scp.grn.qty.received') }}
                      </th>
                      <th class="text-end">
                        {{ t('scp.grn.qty.rejected') }}
                      </th>
                      <th>{{ t('scp.grn.fields.qc_result') }}</th>
                      <th>{{ t('scp.grn.fields.line_notes', 'QC Notes') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(rLine, rIdx) in rejectedLines"
                      :key="rLine.id || rIdx"
                    >
                      <td class="font-weight-medium">
                        {{ rLine.item_name }}
                      </td>
                      <td class="text-end">
                        {{ rLine.qty_ordered }}
                      </td>
                      <td class="text-end">
                        {{ rLine.qty_received }}
                      </td>
                      <td class="text-end">
                        <VChip
                          size="x-small"
                          color="error"
                          label
                        >
                          {{ rLine.qty_rejected }}
                        </VChip>
                      </td>
                      <td>
                        <VChip
                          size="x-small"
                          :color="rLine.qc_result === 'passed_with_remarks' ? 'warning' : 'error'"
                          variant="tonal"
                          label
                        >
                          {{ t(`scp.grn.qc_results.${rLine.qc_result || 'rejected'}`) }}
                        </VChip>
                      </td>
                      <td class="text-body-2">
                        {{ rLine.qc_notes || '—' }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </template>
          </WpSectionCard>
        </section>

        <!-- Section 4: Traceability -->
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

    <ProcurementUnsavedBar
      :is-dirty="isDirty || hasUnsavedFile"
      :is-saving="isSaving"
      :is-create-mode="isCreateMode"
      :is-read-only="isReadOnly"
      :summary-items="unsavedSummaryItems"
      :mobile-actions="mobileQuickActions"
      :save-label="t('scp.action.save')"
      @save="handleSave"
      @discard="handleDiscardChanges"
    />

    <GrnReceiveQcDialog
      v-model="lotDialogOpen"
      :line="editingLine"
      :readonly="isReadOnly"
      @save="onLineModalSave"
    />

    <GrnBatchReceiveDialog
      v-model="batchDialogOpen"
      :lines="form.lines"
      @apply="onBatchApply"
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
.grn-edit-page {
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
