<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePoStore } from '@/stores/usePoStore'
import { useIprStore } from '@/stores/useIprStore'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
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
const iprStore = useIprStore()
const rfqStore = useRfqStore()
const supplierStore = useSupplierStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

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
  formatPartnerForCard,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const poId = computed(() => route.params.id)
const isCreateMode = computed(() => poId.value === 'new')
const rawDocument = ref(null)
const approvalDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

// Supplier Contacts & Addresses
const supplierContacts = ref([])
const supplierAddresses = ref([])

const loadSupplierDetails = async supplierId => {
  if (!supplierId) {
    supplierContacts.value = []
    supplierAddresses.value = []

    return
  }

  const [contactsRes, addressesRes] = await Promise.all([
    supplierStore.fetchSupplierContacts(supplierId).catch(() => []),
    supplierStore.fetchSupplierAddresses(supplierId).catch(() => []),
  ])

  supplierContacts.value = contactsRes?.data || (Array.isArray(contactsRes) ? contactsRes : [])
  supplierAddresses.value = addressesRes?.data || (Array.isArray(addressesRes) ? addressesRes : [])
}

const contactOptions = computed(() =>
  supplierContacts.value.map(c => ({
    title: [c.first_name, c.last_name].filter(Boolean).join(' ') || c.full_name || c.name || c.email || String(c.id),
    value: c.id,
  })),
)

const addressOptions = computed(() =>
  supplierAddresses.value.map(a => ({
    title: [a.address_line_1 || a.street, a.city, a.country].filter(Boolean).join(', ') || a.label || String(a.id),
    value: a.id,
    raw: a,
  })),
)

// Smart Import from IPR / RFQ state
const importDialogOpen = ref(false)
const importMode = ref('ipr')
const approvedIprs = ref([])
const awardedRfqs = ref([])
const selectedImportIprIds = ref([])
const selectedImportRfqId = ref(null)
const loadingImportSources = ref(false)
const importingSource = ref(false)

const normalizePoToForm = doc => ({
  id: doc?.id || null,
  reference: doc?.reference || '',
  status: doc?.status || 'draft',
  creation_path: doc?.creation_path || 'manual',
  rfq_id: doc?.rfq_id || null,
  ipr_ids: Array.isArray(doc?.ipr_ids) ? [...doc.ipr_ids] : [],
  supplier_id: doc?.supplier_id || doc?.supplier?.id || null,
  supplier: doc?.supplier || null,
  supplier_contact_id: doc?.supplier_contact_id || null,
  supplier_address_id: doc?.supplier_address_id || null,
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
      id: l.id || null,
      variant_id: l.variant_id || l.variant?.id || null,
      ipr_line_id: l.ipr_line_id || null,
      supplier_reference: l.supplier_reference || '',
      is_ad_hoc: Boolean(l.is_ad_hoc || (!l.catalog_item_id && l.ad_hoc_name)),
      ad_hoc_name: l.ad_hoc_name || '',
      ad_hoc_sku: l.ad_hoc_sku || '',
      item_name: resolveLineItemName(l),
      sku: resolveLineItemSku(l),
      quantity: qty,
      qty_ordered: qty,
      qty_received: Number(l.qty_received ?? 0) || 0,
      unit_price: price,
      unit_price_ht: price,
      tax_rate: tax,
      tax_rate_pct: tax,
      line_total: Number(l.total_ttc ?? l.line_total_ttc ?? subtotal + (subtotal * tax) / 100),
      description: l.description_snapshot || l.description || '',
      required_date: l.expected_delivery_date ? String(l.expected_delivery_date).slice(0, 10) : '',
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
  initial: () => normalizePoToForm({}),
  lockedStatuses: [
    'pending_approval',
    'approved',
    'sent',
    'acknowledged',
    'in_delivery',
    'partially_received',
    'fully_received',
    'closed',
    'cancelled',
  ],
  unsavedGuard: true,
})

const loadPo = async () => {
  isLoading.value = true
  try {
    await Promise.all([ensureSuppliers(), ensureWarehouses()])

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizePoToForm({}))

      return
    }

    const doc = await poStore.fetchDetails(poId.value)
    if (doc) {
      rawDocument.value = doc
      setDocument(normalizePoToForm(doc))
      await loadSupplierDetails(doc.supplier_id || doc.supplier?.id)
    }
  }
  catch (err) {
    showApiError(err, t('scp.po.snackbar.error_load', 'Failed to load Purchase Order'))
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadPo)

watch(poId, (next, prev) => {
  if (next && next !== prev) loadPo()
})

const linesRef = computed(() => form.value.lines || [])

const { totals } = useDocumentTotals(linesRef, {
  fields: 'po',
})

const workflow = useDocumentWorkflow(rawDocument, { config: 'po' })

const executeTransition = async (targetStatus, extraPayload = {}) => {
  if (isCreateMode.value || !poId.value) return null
  submittingTransition.value = true
  try {
    const updated = await poStore.updateStatus(poId.value, {
      status: targetStatus,
      ...extraPayload,
    })

    rawDocument.value = updated
    setDocument(normalizePoToForm(updated))
    success(t('scp.po.snackbar.status_updated'))

    return updated
  }
  catch (err) {
    showApiError(err, t('scp.po.snackbar.error_status'))
    throw err
  }
  finally {
    submittingTransition.value = false
  }
}

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { type: 'po' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const isReceivable = computed(() =>
  !isCreateMode.value && ['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(form.value.status),
)

const metadataItems = computed(() => [
  {
    key: 'supplier',
    icon: 'tabler-building-store',
    label: t('scp.common.supplier'),
    value: resolveSupplierName(form.value.supplier),
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
    disabled: isReadOnly.value || (!isDirty.value && !isCreateMode.value),
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'import_source',
    label: t('scp.po.actions.import_source', 'Import from IPR / RFQ'),
    icon: 'tabler-file-import',
    color: 'secondary',
    hidden: isReadOnly.value || form.value.status !== 'draft',
  },
  {
    key: 'acknowledge',
    label: t('scp.po.actions.mark_acknowledged'),
    icon: 'tabler-eye-check',
    color: 'info',
    hidden: isCreateMode.value || form.value.status !== 'sent',
  },
  {
    key: 'in_delivery',
    label: t('scp.po.actions.mark_in_delivery'),
    icon: 'tabler-truck',
    color: 'warning',
    hidden: isCreateMode.value || !['sent', 'acknowledged'].includes(form.value.status),
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
    hidden: isCreateMode.value,
  },
  {
    key: 'close',
    label: t('scp.po.actions.close_po'),
    icon: 'tabler-lock',
    color: 'secondary',
    hidden: isCreateMode.value || !['partially_received', 'fully_received'].includes(form.value.status),
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: isCreateMode.value || ['partially_received', 'fully_received', 'closed', 'cancelled'].includes(form.value.status),
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
  creation_path: current.creation_path || 'manual',
  rfq_id: current.rfq_id || null,
  ipr_ids: Array.isArray(current.ipr_ids) ? current.ipr_ids : [],
  supplier_id: current.supplier_id,
  supplier_contact_id: current.supplier_contact_id || null,
  supplier_address_id: current.supplier_address_id || null,
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
  lines: (current.lines || []).map(l => {
    const isAdHoc = Boolean(l.is_ad_hoc || !l.catalog_item_id)

    return {
      ...(l.id ? { id: l.id } : {}),
      is_ad_hoc: isAdHoc,
      catalog_item_id: isAdHoc ? null : (l.catalog_item_id || null),
      variant_id: isAdHoc ? null : (l.variant_id || null),
      ad_hoc_name: isAdHoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
      ad_hoc_sku: isAdHoc ? (l.ad_hoc_sku || l.sku || null) : null,
      ipr_line_id: l.ipr_line_id || null,
      supplier_reference: l.supplier_reference || null,
      description_snapshot: l.description || l.description_snapshot || l.item_name || null,
      qty_ordered: Number(l.quantity ?? l.qty_ordered ?? 1) || 1,
      unit_price_ht: Number(l.unit_price ?? l.unit_price_ht ?? 0) || 0,
      tax_rate_pct: Number(l.tax_rate ?? l.tax_rate_pct ?? 19) || 0,
      expected_delivery_date: l.required_date || current.committed_delivery_date || null,
    }
  }),
})

const handleSave = async () => {
  isSaving.value = true
  try {
    const payload = buildSavePayload(form.value)
    if (isCreateMode.value) {
      const created = await poStore.create(payload)

      rawDocument.value = created
      setDocument(normalizePoToForm(created))
      success(t('scp.po.snackbar.created_success'))
      await router.replace(`/supply-chain-procurement/po/edit/${created.id}`)

      return created
    }

    const updated = await poStore.update(poId.value, payload)

    rawDocument.value = updated
    setDocument(normalizePoToForm(updated))
    success(t('scp.po.snackbar.updated_success'))

    return updated
  }
  catch (err) {
    applyServerErrors(err)
    showApiError(err, t('scp.po.snackbar.error_save'))
    throw err
  }
  finally {
    isSaving.value = false
  }
}

const openImportDialog = async () => {
  selectedImportIprIds.value = [...(form.value.ipr_ids || [])]
  selectedImportRfqId.value = form.value.rfq_id || null
  importDialogOpen.value = true
  loadingImportSources.value = true
  try {
    const [iprRes, rfqRes] = await Promise.all([
      iprStore.fetchList({ 'filter[status]': 'approved', per_page: 50 }),
      rfqStore.fetchList({ 'filter[status]': 'awarded', per_page: 50 }),
    ])

    approvedIprs.value = iprRes?.data || iprStore.list || []
    awardedRfqs.value = rfqRes?.data || rfqStore.list || []
  }
  catch (err) {
    showApiError(err)
  }
  finally {
    loadingImportSources.value = false
  }
}

const approvedIprOptions = computed(() =>
  approvedIprs.value.map(ipr => ({
    title: `${ipr.reference} — ${ipr.requested_by_user?.name || ipr.requester?.name || ''}`,
    value: ipr.id,
  })),
)

const awardedRfqOptions = computed(() =>
  awardedRfqs.value.map(rfq => ({
    title: `${rfq.reference} — ${rfq.title || ''} (${resolveSupplierName(rfq.awarded_to)})`,
    value: rfq.id,
  })),
)

const confirmImportSource = async () => {
  importingSource.value = true
  try {
    if (importMode.value === 'rfq' && selectedImportRfqId.value) {
      const rfq = await rfqStore.fetchDetails(selectedImportRfqId.value)
      if (rfq) {
        form.value.rfq_id = rfq.id
        form.value.creation_path = 'from_rfq'
        if (Array.isArray(rfq.ipr_ids) && rfq.ipr_ids.length) {
          form.value.ipr_ids = [...new Set([...(form.value.ipr_ids || []), ...rfq.ipr_ids])]
        }

        const awardedResp = (rfq.responses || []).find(r => r.supplier_id === rfq.awarded_to_supplier_id) || rfq.responses?.[0]
        if (rfq.awarded_to_supplier_id && !form.value.supplier_id) {
          form.value.supplier_id = rfq.awarded_to_supplier_id
          form.value.supplier = rfq.awarded_to || awardedResp?.supplier || null
          await loadSupplierDetails(rfq.awarded_to_supplier_id)
        }

        const importedLines = (rfq.lines || []).map(rl => {
          const respLine = (awardedResp?.lines || []).find(l => l.rfq_line_id === rl.id)
          const qty = Number(rl.qty_requested ?? 1) || 1
          const price = Number(respLine?.offered_unit_price_ht ?? rl.target_price_ht ?? 0) || 0
          const tax = 19

          return {
            is_ad_hoc: Boolean(rl.is_ad_hoc || !rl.catalog_item_id),
            catalog_item_id: rl.catalog_item_id || null,
            variant_id: rl.variant_id || rl.variant?.id || null,
            ad_hoc_name: rl.ad_hoc_name || '',
            ad_hoc_sku: rl.ad_hoc_sku || '',
            item_name: resolveLineItemName(rl),
            sku: resolveLineItemSku(rl),
            quantity: qty,
            qty_ordered: qty,
            qty_received: 0,
            unit_price: price,
            unit_price_ht: price,
            tax_rate: tax,
            tax_rate_pct: tax,
            line_total: qty * price * (1 + tax / 100),
            description: rl.description_for_supplier || '',
            required_date: form.value.committed_delivery_date || '',
          }
        })

        form.value.lines = [...(form.value.lines || []), ...importedLines]
      }
    }
    else if (importMode.value === 'ipr' && selectedImportIprIds.value.length) {
      const ids = selectedImportIprIds.value
      const importedLines = []

      for (const id of ids) {
        const ipr = await iprStore.fetchDetails(id)
        for (const l of ipr?.lines || []) {
          const qty = Number(l.qty_requested ?? 1) || 1
          const price = Number(l.estimated_unit_cost ?? 0) || 0
          const tax = 19

          importedLines.push({
            ipr_line_id: l.id || null,
            is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
            catalog_item_id: l.catalog_item_id || null,
            variant_id: l.variant_id || l.variant?.id || null,
            ad_hoc_name: l.ad_hoc_name || '',
            ad_hoc_sku: l.ad_hoc_sku || '',
            item_name: resolveLineItemName(l),
            sku: resolveLineItemSku(l),
            quantity: qty,
            qty_ordered: qty,
            qty_received: 0,
            unit_price: price,
            unit_price_ht: price,
            tax_rate: tax,
            tax_rate_pct: tax,
            line_total: qty * price * (1 + tax / 100),
            description: l.ad_hoc_description || '',
            required_date: form.value.committed_delivery_date || '',
          })
        }
      }

      form.value.ipr_ids = [...new Set([...(form.value.ipr_ids || []), ...ids])]
      if (!form.value.rfq_id) form.value.creation_path = 'from_ipr'
      form.value.lines = [...(form.value.lines || []), ...importedLines]
    }

    importDialogOpen.value = false
    success(t('scp.po.snackbar.imported_source', 'Lines imported into Purchase Order'))
  }
  catch (err) {
    showApiError(err)
  }
  finally {
    importingSource.value = false
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'import_source') {
    await openImportDialog()
  }
  else if (key === 'submit_approval') {
    if (isDirty.value) await handleSave()
    await executeTransition('pending_approval')
  }
  else if (key === 'approve') {
    if (isDirty.value) await handleSave()
    await executeTransition('approved')
  }
  else if (key === 'decide') {
    approvalDialogOpen.value = true
  }
  else if (key === 'send') {
    await executeTransition('sent')
  }
  else if (key === 'acknowledge') {
    await executeTransition('acknowledged')
  }
  else if (key === 'in_delivery') {
    await executeTransition('in_delivery')
  }
  else if (key === 'receive_grn') {
    router.push({ path: '/supply-chain-procurement/grn/list', query: { from_po: poId.value } })
  }
  else if (key === 'match') {
    router.push(`/supply-chain-procurement/po/match/${poId.value}`)
  }
  else if (key === 'close') {
    await executeTransition('closed')
  }
  else if (key === 'cancel') {
    await executeTransition('cancelled')
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
    title: s => resolveSupplierName(s),
    subtitle: 'supplier_id',
    badge: 'status',
  },
}))

const onSupplierChange = async partner => {
  form.value.supplier = partner
  form.value.supplier_id = partner?.id || null
  form.value.supplier_contact_id = null
  form.value.supplier_address_id = null
  await loadSupplierDetails(partner?.id || null)
}

const onSupplierAddressChange = addressId => {
  form.value.supplier_address_id = addressId
  if (!form.value.delivery_address && addressId) {
    const found = addressOptions.value.find(a => a.value === addressId)
    if (found?.title) {
      form.value.delivery_address = found.title
    }
  }
}

const mapCatalogToPoLine = (item, quantity = 1) => {
  const qty = Number(quantity) || 1
  const price = Number(item.unit_price ?? item.purchase_price_ht ?? 0) || 0
  const tax = Number(item.tax_rate ?? 19) || 0
  const subtotal = qty * price

  return {
    is_ad_hoc: false,
    catalog_item_id: item.id,
    variant_id: item.variant_id || null,
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || item.code || '',
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
      approver: { name: doc.approved_by_user?.name || doc.approver?.name || '—' },
      decision: 'approved',
      at: doc.approved_at,
      comment: doc.approval_note,
    })
  }

  return list
})

const onApprovalDecision = async ({ decision, reason, comment }) => {
  const target = decision === 'approved' ? 'approved' : 'cancelled'

  await executeTransition(target, {
    rejection_reason: reason || null,
    approval_note: comment || null,
  })
  approvalDialogOpen.value = false
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await poStore.deleteItem(poId.value)
    success(t('scp.po.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/po/list')
  }
  catch (err) {
    showApiError(err, t('scp.po.snackbar.error_delete'))
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

const { activeSection, scrollToSection } = useJournalNav(journalSections, { idPrefix: 'section-' })
</script>

<template>
  <div class="po-edit-page">
    <WpDocumentHeader
      :title="form.reference || t('scp.po.new_title', 'New Purchase Order')"
      :reference="form.reference"
      :subtitle="form.supplier ? resolveSupplierName(form.supplier) : t('scp.po.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po'), to: '/supply-chain-procurement/po/list' },
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

    <div class="scp-edit-layout">
      <WpJournalNav
        v-model="activeSection"
        :sections="journalSections"
        :title="form.reference || t('scp.po.title')"
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
                v-if="form.status === 'draft'"
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-file-import"
                min-height="40"
                @click="openImportDialog"
              >
                {{ t('scp.po.actions.import_source', 'Import from IPR / RFQ') }}
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
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSelect
                      v-model="form.supplier_contact_id"
                      :items="contactOptions"
                      item-title="title"
                      item-value="value"
                      :label="t('scp.po.fields.supplier_contact', 'Supplier Contact')"
                      prepend-inner-icon="tabler-user"
                      :readonly="isReadOnly"
                      :disabled="!form.supplier_id"
                      clearable
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSelect
                      :model-value="form.supplier_address_id"
                      :items="addressOptions"
                      item-title="title"
                      item-value="value"
                      :label="t('scp.po.fields.supplier_address', 'Supplier Address')"
                      prepend-inner-icon="tabler-address-book"
                      :readonly="isReadOnly"
                      :disabled="!form.supplier_id"
                      clearable
                      @update:model-value="onSupplierAddressChange"
                    />
                  </VCol>

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
          <div
            v-if="!isCreateMode"
            class="d-flex flex-wrap gap-2 mb-4"
          >
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
            :items="traceabilityChain"
          />
        </section>
      </main>
    </div>

    <!-- Import from Approved IPR / Awarded RFQ Dialog -->
    <WpFormDialog
      v-model="importDialogOpen"
      :title="t('scp.po.actions.import_source', 'Import from IPR / RFQ')"
      icon="tabler-file-import"
      size="medium"
      :loading="importingSource"
      :submit-label="t('scp.action.import', 'Import Lines')"
      @submit="confirmImportSource"
    >
      <div class="pa-4">
        <VBtnToggle
          v-model="importMode"
          mandatory
          color="primary"
          variant="outlined"
          class="mb-4 w-100"
        >
          <VBtn
            value="ipr"
            class="flex-grow-1"
            prepend-icon="tabler-clipboard-list"
          >
            {{ t('scp.nav.ipr_short') }}
          </VBtn>
          <VBtn
            value="rfq"
            class="flex-grow-1"
            prepend-icon="tabler-scale"
          >
            {{ t('scp.nav.rfq_short') }}
          </VBtn>
        </VBtnToggle>

        <VAutocomplete
          v-if="importMode === 'ipr'"
          v-model="selectedImportIprIds"
          :items="approvedIprOptions"
          item-title="title"
          item-value="value"
          :loading="loadingImportSources"
          :label="t('scp.rfq.fields.source_iprs', 'Approved Purchase Requests')"
          prepend-inner-icon="tabler-file-import"
          multiple
          chips
          closable-chips
        />

        <VAutocomplete
          v-else
          v-model="selectedImportRfqId"
          :items="awardedRfqOptions"
          item-title="title"
          item-value="value"
          :loading="loadingImportSources"
          :label="t('scp.po.fields.linked_rfq', 'Awarded RFQ')"
          prepend-inner-icon="tabler-scale"
          clearable
        />
      </div>
    </WpFormDialog>

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
.po-edit-page {
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
