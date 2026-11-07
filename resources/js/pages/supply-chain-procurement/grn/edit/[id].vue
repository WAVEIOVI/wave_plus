<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useGrnStore } from '@/stores/useGrnStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
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
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  userOptions,
  warehouseOptions,
  qcResultOptions,
  overallQcResultOptions,
  ensureUsers,
  ensureWarehouses,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const grnId = computed(() => route.params.id)
const rawDocument = ref(null)
const activeSection = ref('overview')
const lotDialogOpen = ref(false)
const editingLineIndex = ref(null)
const editingLine = ref(null)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

const normalizeGrnToForm = doc => ({
  id: doc?.id || null,
  grn_number: doc?.grn_number || '',
  status: doc?.status || 'draft',
  po_id: doc?.po_id || doc?.purchase_order?.id || null,
  po_number: doc?.purchase_order?.po_number || '',
  supplier_id: doc?.supplier_id || doc?.supplier?.id || null,
  supplier: doc?.supplier || null,
  received_by: doc?.received_by || doc?.receiver?.id || '',
  received_at: doc?.received_at ? String(doc.received_at).slice(0, 10) : '',
  supplier_delivery_note_ref: doc?.supplier_delivery_note_ref || '',
  allow_over_receive: Boolean(doc?.allow_over_receive),
  is_partial_delivery: Boolean(doc?.is_partial_delivery),
  overall_qc_result: doc?.overall_qc_result || 'passed',
  qc_performed_by: doc?.qc_performed_by || null,
  qc_performed_at: doc?.qc_performed_at ? String(doc.qc_performed_at).slice(0, 10) : '',
  lines: (doc?.lines || []).map(l => ({
    ...l,
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
  })),
})

const {
  form,
  isDirty,
  isReadOnly,
  isSaving,
  fieldErrors,
  reset,
  save,
  guardDialog,
} = useDocumentForm({
  documentType: 'grn',
  initialData: normalizeGrnToForm({}),
  loadFn: async () => {
    await Promise.all([ensureUsers(), ensureWarehouses()])

    const doc = await grnStore.fetchGrn(grnId.value)

    rawDocument.value = doc

    return normalizeGrnToForm(doc)
  },
  saveFn: async current => {
    const payload = {
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
      lines: (current.lines || []).map(l => ({
        po_line_id: l.po_line_id,
        is_ad_hoc: Boolean(l.is_ad_hoc),
        catalog_item_id: l.catalog_item_id || null,
        ad_hoc_name: l.is_ad_hoc ? (l.ad_hoc_name || l.item_name || 'Ad-hoc Item') : null,
        qty_received: Number(l.qty_received) || 0.01,
        qty_accepted: Number(l.qty_accepted) || 0,
        qty_rejected: Number(l.qty_rejected) || 0,
        unit_cost_ht: Number(l.unit_cost_ht) || 0,
        storage_location_id: l.storage_location_id || null,
        lot_number: l.lot_number || null,
        expiry_date: l.expiry_date || null,
        qc_result: l.qc_result || 'passed',
        qc_notes: l.qc_notes || null,
      })),
    }

    const updated = await grnStore.updateGrn(grnId.value, payload)

    rawDocument.value = updated

    return normalizeGrnToForm(updated)
  },
})

onMounted(async () => {
  const doc = await grnStore.fetchGrn(grnId.value)
  if (doc) {
    rawDocument.value = doc
    reset(normalizeGrnToForm(doc))
  }
  ensureUsers()
  ensureWarehouses()
})

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { preset: 'grn' })

const workflow = useDocumentWorkflow(rawDocument, {
  documentType: 'grn',
  transitionFn: async targetStatus => {
    submittingTransition.value = true
    try {
      const updated = await grnStore.updateStatus(grnId.value, targetStatus, {
        allow_over_receive: Boolean(form.value.allow_over_receive),
      })

      rawDocument.value = updated
      reset(normalizeGrnToForm(updated))
      showSuccess(t('scp.grn.snackbar.status_updated'))

      return updated
    }
    catch (err) {
      showError(err?.data?.message || t('scp.grn.snackbar.error_status'))
      throw err
    }
    finally {
      submittingTransition.value = false
    }
  },
})

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { documentType: 'grn' })

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
    value: form.value.po_number || '—',
  },
  {
    key: 'supplier',
    icon: 'tabler-building-store',
    label: t('scp.common.supplier'),
    value: form.value.supplier?.legal_name || form.value.supplier?.trade_name || '—',
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
    disabled: isReadOnly.value || !isDirty.value,
    loading: isSaving.value,
    hidden: isReadOnly.value,
  },
  {
    key: 'partial_reject',
    label: statusLabel('partially_rejected'),
    icon: 'tabler-alert-triangle',
    color: 'warning',
    hidden: form.value.status !== 'under_qc',
  },
  {
    key: 'full_reject',
    label: statusLabel('fully_rejected'),
    icon: 'tabler-circle-x',
    color: 'error',
    hidden: !['under_qc', 'partially_rejected'].includes(form.value.status),
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
    hidden: isReadOnly.value,
  },
])

const handleSave = async () => {
  try {
    await save()
    showSuccess(t('scp.grn.snackbar.updated_success'))
  }
  catch (err) {
    showError(err?.data?.message || t('scp.grn.snackbar.error_save'))
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'start_qc') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('under_qc')
  }
  else if (key === 'confirm_receipt') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('confirmed')
  }
  else if (key === 'partial_reject') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('partially_rejected')
  }
  else if (key === 'full_reject') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('fully_rejected')
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
  const defaultLoc = warehouseOptions.value[0]?.value || null

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
    await grnStore.deleteGrn(grnId.value)
    showSuccess(t('scp.grn.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/grn/list')
  }
  catch (err) {
    showError(err?.data?.message || t('scp.grn.snackbar.error_delete'))
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

const warehouseNameOf = id => {
  const found = warehouseOptions.value.find(w => w.value === id)

  return found?.title || '—'
}

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.grn.sections.receiving_details'), icon: 'tabler-truck-delivery' },
  { id: 'lines', title: t('scp.grn.tabs.lines'), icon: 'tabler-package-import', badge: form.value.lines.length },
  { id: 'qc', title: t('scp.grn.tabs.qc'), icon: 'tabler-test-pipe' },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])
</script>

<template>
  <div class="grn-edit-page">
    <WpDocumentHeader
      :title="form.grn_number || t('scp.grn.title')"
      :reference="form.grn_number"
      :subtitle="form.supplier?.legal_name || form.supplier?.trade_name || t('scp.grn.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.grn'), to: '/supply-chain-procurement/grn/list' },
        { title: form.grn_number || t('scp.action.edit') },
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

    <WpJournalNav
      v-model="activeSection"
      :sections="journalSections"
    >
      <!-- Section 1: Receiving Overview -->
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
          </VRow>
        </WpSectionCard>
      </section>

      <!-- Section 2: Receiving Lines & Quantities -->
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

          <!-- Mobile Line Cards -->
          <div
            v-if="smAndDown"
            class="d-flex flex-column gap-3"
          >
            <WpMobileCard
              v-for="(line, idx) in form.lines"
              :key="line.id || idx"
              @click="openLineModal(line, idx)"
            >
              <template #header>
                <div>
                  <div class="font-weight-bold text-body-2">
                    {{ line.item_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ warehouseNameOf(line.storage_location_id) }} · {{ line.lot_number || t('scp.common.not_set') }}
                  </div>
                </div>
                <VChip
                  size="x-small"
                  label
                  variant="tonal"
                  :color="line.qc_result === 'rejected' ? 'error' : line.qc_result === 'passed_with_remarks' ? 'warning' : 'success'"
                >
                  {{ t(`scp.grn.qc_results.${line.qc_result || 'passed'}`) }}
                </VChip>
              </template>

              <div class="d-flex justify-space-between text-caption mt-2">
                <span>{{ t('scp.grn.qty.received') }}: <strong>{{ line.qty_received }}</strong></span>
                <span>{{ t('scp.grn.qty.accepted') }}: <strong class="text-success">{{ line.qty_accepted }}</strong></span>
                <span>{{ t('scp.grn.qty.rejected') }}: <strong class="text-error">{{ line.qty_rejected }}</strong></span>
              </div>
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
                    :items="warehouseOptions"
                    item-title="title"
                    item-value="value"
                    density="compact"
                    hide-details
                    style="min-inline-size: 160px;"
                  />
                  <span v-else>{{ warehouseNameOf(line.storage_location_id) }}</span>
                </td>
                <td>{{ line.lot_number || '—' }}</td>
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

      <!-- Section 3: Quality Inspection Summary -->
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
        </WpSectionCard>
      </section>

      <!-- Section 4: Attachments & Traceability -->
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

    <GrnLotSerialDialog
      v-model="lotDialogOpen"
      :line="editingLine"
      :readonly="isReadOnly"
      @save="onLineModalSave"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="form.grn_number"
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
.grn-edit-page {
  overflow-x: clip;
}
</style>
