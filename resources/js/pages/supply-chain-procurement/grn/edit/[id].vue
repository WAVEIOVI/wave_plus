<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useGrnStore } from '@/stores/useGrnStore'
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
import GrnLotSerialDialog from '@/views/supply-chain-procurement/grn/GrnLotSerialDialog.vue'

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
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

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
const lotDialogOpen = ref(false)
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

const loadGrn = async () => {
  isLoading.value = true
  try {
    await Promise.all([ensureUsers(), ensureWarehouses()])
    deliveryNoteFile.value = null

    if (isCreateMode.value) {
      rawDocument.value = null
      setDocument(normalizeGrnToForm({}))

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

const receiveAllRemaining = () => {
  if (isReadOnly.value) return
  const defaultLoc = filteredLocationOptions.value[0]?.value || allLocationOptions.value[0]?.value || null

  form.value.lines = form.value.lines.map(l => {
    const qty = Number(l.qty_ordered) || Number(l.qty_received) || 1

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

const { activeSection, scrollToSection } = useJournalNav(journalSections, { idPrefix: 'section-' })
</script>

<template>
  <div class="grn-edit-page">
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
      :steps="lifecycleSteps"
      class="mb-4"
    />

    <WpDocumentMetadataBar
      :items="metadataItems"
      class="mb-6"
    />

    <WpKpiGroup class="mb-6">
      <WpKpiCard
        :title="t('scp.grn.qty.received')"
        :value="totalReceivedQty"
        icon="tabler-package-import"
        color="primary"
      />
      <WpKpiCard
        :title="t('scp.grn.qty.accepted')"
        :value="totalAcceptedQty"
        icon="tabler-circle-check"
        color="success"
      />
      <WpKpiCard
        :title="t('scp.grn.qty.rejected')"
        :value="totalRejectedQty"
        icon="tabler-alert-triangle"
        color="error"
      />
      <WpKpiCard
        :title="t('scp.common.total')"
        :value="formatMoney(totals.grandTotal)"
        icon="tabler-coins"
        color="info"
      />
    </WpKpiGroup>

    <div class="scp-edit-layout">
      <WpJournalNav
        v-model="activeSection"
        :sections="journalSections"
        :title="form.reference || t('scp.grn.title')"
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
        <!-- Section 1: Receiving Overview & Delivery Note Attachment -->
        <section
          id="section-overview"
          class="mb-6"
        >
          <WpSectionCard
            :title="t('scp.grn.sections.receiving_details')"
            icon="tabler-truck-delivery"
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
                :disabled="!isDirty && !hasUnsavedFile && !isCreateMode"
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
          class="mb-6"
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
                    <div
                      v-if="line.sku"
                      class="text-caption text-medium-emphasis"
                    >
                      {{ line.sku }}
                    </div>
                  </td>
                  <td class="text-end font-weight-bold">
                    {{ line.qty_received }}
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
          class="mb-6"
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

            <!-- Rejected Items Summary Alert & Table -->
            <template v-if="rejectedLines.length">
              <VDivider class="my-4" />
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

              <VTable density="compact">
                <thead>
                  <tr>
                    <th>{{ t('scp.common.item') }}</th>
                    <th class="text-end">
                      {{ t('scp.grn.qty.rejected') }}
                    </th>
                    <th>{{ t('scp.grn.fields.qc_result') }}</th>
                    <th>{{ t('scp.grn.fields.line_notes') }}</th>
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
                    <td class="text-end text-error font-weight-bold">
                      {{ rLine.qty_rejected }}
                    </td>
                    <td>
                      <VChip
                        size="x-small"
                        color="error"
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
            </template>
          </WpSectionCard>
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

    <GrnLotSerialDialog
      v-model="lotDialogOpen"
      :line="editingLine"
      :readonly="isReadOnly"
      @save="onLineModalSave"
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
