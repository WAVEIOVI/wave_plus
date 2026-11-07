<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useRfqStore } from '@/stores/useRfqStore'
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
const { format: formatMoney } = useCurrency()
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  paymentTermsLabel,
  supplierOptions,
  ensureSuppliers,
  fetchCatalogItemsForPicker,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
} = useProcurementHelpers()

const rfqId = computed(() => route.params.id)
const rawDocument = ref(null)
const activeSection = ref('overview')
const responseDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const submittingTransition = ref(false)

const normalizeRfqToForm = doc => {
  const ec = doc?.evaluation_criteria || {}

  return {
    id: doc?.id || null,
    rfq_number: doc?.rfq_number || '',
    status: doc?.status || 'draft',
    title: doc?.title || '',
    response_deadline: doc?.response_deadline ? String(doc.response_deadline).slice(0, 10) : '',
    required_delivery_date: doc?.required_delivery_date ? String(doc.required_delivery_date).slice(0, 10) : '',
    terms_and_conditions: doc?.terms_and_conditions || '',
    price_weight: ec.price_weight ?? 40,
    lead_time_weight: ec.lead_time_weight ?? 25,
    quality_weight: ec.quality_weight ?? 20,
    technical_weight: ec.technical_weight ?? 10,
    payment_weight: ec.payment_weight ?? 5,
    invited_supplier_ids: (doc?.invitations || []).map(inv => inv.supplier_id || inv.supplier?.id).filter(Boolean),
    lines: (doc?.lines || []).map(l => {
      const qty = Number(l.qty_requested ?? l.quantity ?? 1) || 1
      const price = Number(l.target_price_ht ?? l.unit_price ?? 0) || 0

      return {
        ...l,
        item_name: resolveLineItemName(l),
        sku: resolveLineItemSku(l),
        quantity: qty,
        qty_requested: qty,
        unit_price: price,
        target_price_ht: price,
        line_total: qty * price,
        description: l.description_for_supplier || l.description || '',
      }
    }),
  }
}

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
  documentType: 'rfq',
  initialData: normalizeRfqToForm({}),
  loadFn: async () => {
    await ensureSuppliers()

    const doc = await rfqStore.fetchRfq(rfqId.value)

    rawDocument.value = doc

    return normalizeRfqToForm(doc)
  },
  saveFn: async current => {
    const payload = {
      title: current.title,
      response_deadline: current.response_deadline,
      required_delivery_date: current.required_delivery_date || null,
      terms_and_conditions: current.terms_and_conditions || null,
      evaluation_criteria: {
        price_weight: Number(current.price_weight) || 0,
        lead_time_weight: Number(current.lead_time_weight) || 0,
        quality_weight: Number(current.quality_weight) || 0,
        technical_weight: Number(current.technical_weight) || 0,
        payment_weight: Number(current.payment_weight) || 0,
      },
      invitations: (current.invited_supplier_ids || []).map(id => ({ supplier_id: id })),
      lines: (current.lines || []).map(l => ({
        is_ad_hoc: Boolean(l.is_ad_hoc || !l.catalog_item_id),
        catalog_item_id: l.catalog_item_id || null,
        ad_hoc_name: !l.catalog_item_id ? (l.item_name || l.ad_hoc_name || 'Ad-hoc Item') : null,
        description_for_supplier: l.description || null,
        qty_requested: Number(l.quantity ?? l.qty_requested ?? 1) || 1,
        unit_id: l.unit_id || null,
        target_price_ht: Number(l.unit_price ?? l.target_price_ht ?? 0) || null,
      })),
    }

    const updated = await rfqStore.updateRfq(rfqId.value, payload)

    rawDocument.value = updated

    return normalizeRfqToForm(updated)
  },
})

const reloadRfq = async () => {
  const doc = await rfqStore.fetchRfq(rfqId.value)
  if (doc) {
    rawDocument.value = doc
    reset(normalizeRfqToForm(doc))
  }
}

onMounted(async () => {
  await reloadRfq()
  ensureSuppliers()
})

const linesRef = computed(() => form.value.lines || [])
const { totals } = useDocumentTotals(linesRef, { preset: 'rfq' })

const workflow = useDocumentWorkflow(rawDocument, {
  documentType: 'rfq',
  transitionFn: async (targetStatus, meta = {}) => {
    submittingTransition.value = true
    try {
      const updated = await rfqStore.updateStatus(rfqId.value, {
        status: targetStatus,
        ...(meta.awarded_to_supplier_id ? { awarded_to_supplier_id: meta.awarded_to_supplier_id } : {}),
      })

      rawDocument.value = updated
      reset(normalizeRfqToForm(updated))
      showSuccess(t('scp.rfq.snackbar.status_updated'))

      return updated
    }
    catch (err) {
      showError(err?.data?.message || t('scp.rfq.snackbar.error_status'))
      throw err
    }
    finally {
      submittingTransition.value = false
    }
  },
})

const { chain: traceabilityChain } = useDocumentTraceability(rawDocument, { documentType: 'rfq' })

const lifecycleSteps = computed(() =>
  workflow.lifecycleSteps.value.map(s => ({
    ...s,
    label: statusLabel(s.key),
  })),
)

const invitations = computed(() => rawDocument.value?.invitations || [])
const responses = computed(() => rawDocument.value?.responses || [])
const canRecordBid = computed(() => ['sent', 'awaiting_responses', 'under_evaluation'].includes(form.value.status))

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
    value: String(invitations.value.length),
  },
  {
    key: 'awarded',
    icon: 'tabler-award',
    label: t('scp.rfq.fields.awarded_supplier'),
    value: rawDocument.value?.awarded_supplier?.legal_name || rawDocument.value?.awarded_supplier?.trade_name || '—',
  },
])

const primaryHeaderAction = computed(() => {
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
    disabled: isReadOnly.value || !isDirty.value,
    loading: isSaving.value,
    hidden: isReadOnly.value,
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
    hidden: !['sent', 'awaiting_responses'].includes(form.value.status),
  },
  {
    key: 'convert_po',
    label: t('scp.action.convert_to_po'),
    icon: 'tabler-shopping-cart-plus',
    color: 'success',
    hidden: form.value.status !== 'awarded',
  },
  {
    key: 'cancel',
    label: t('scp.action.cancel'),
    icon: 'tabler-ban',
    color: 'warning',
    hidden: ['awarded', 'cancelled'].includes(form.value.status),
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
    showSuccess(t('scp.rfq.snackbar.updated_success'))
  }
  catch (err) {
    showError(err?.data?.message || t('scp.rfq.snackbar.error_save'))
  }
}

const onHeaderAction = async key => {
  if (key === 'save') {
    await handleSave()
  }
  else if (key === 'send') {
    if (isDirty.value) await handleSave()
    await workflow.transitionTo('sent')
  }
  else if (key === 'record_bid') {
    responseDialogOpen.value = true
  }
  else if (key === 'start_eval') {
    if (form.value.status === 'sent') {
      await workflow.transitionTo('awaiting_responses')
    }
    await workflow.transitionTo('under_evaluation')
  }
  else if (key === 'go_comparison') {
    activeSection.value = 'comparison'
    document.getElementById('section-comparison')?.scrollIntoView({ behavior: 'smooth' })
  }
  else if (key === 'convert_po') {
    router.push({ path: '/supply-chain-procurement/po/list', query: { from_rfq: rfqId.value } })
  }
  else if (key === 'cancel') {
    await workflow.transitionTo('cancelled')
  }
  else if (key === 'delete') {
    deleteDialogOpen.value = true
  }
}

const awardSupplier = async supplierId => {
  if (!supplierId) return
  try {
    if (form.value.status === 'sent') {
      await workflow.transitionTo('awaiting_responses')
    }
    if (rawDocument.value?.status === 'awaiting_responses') {
      await workflow.transitionTo('under_evaluation')
    }
    await workflow.transitionTo('awarded', { awarded_to_supplier_id: supplierId })
  }
  catch {
    // Error handled in transitionFn
  }
}

const handleDelete = async () => {
  deleting.value = true
  try {
    await rfqStore.deleteRfq(rfqId.value)
    showSuccess(t('scp.rfq.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    router.push('/supply-chain-procurement/rfq/list')
  }
  catch (err) {
    showError(err?.data?.message || t('scp.rfq.snackbar.error_delete'))
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
    item_name: item.item_name || item.name || item.sku,
    sku: item.sku || '',
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

const journalSections = computed(() => [
  { id: 'overview', title: t('scp.rfq.sections.overview'), icon: 'tabler-info-circle' },
  { id: 'suppliers', title: t('scp.rfq.sections.invited_suppliers'), icon: 'tabler-building-store', badge: invitations.value.length },
  { id: 'lines', title: t('scp.rfq.sections.lines'), icon: 'tabler-list-details', badge: form.value.lines.length },
  { id: 'comparison', title: t('scp.rfq.sections.comparison'), icon: 'tabler-git-compare', badge: responses.value.length },
  { id: 'traceability', title: t('scp.ipr.sections.traceability'), icon: 'tabler-git-branch' },
])
</script>

<template>
  <div class="rfq-edit-page">
    <WpDocumentHeader
      :title="form.rfq_number || t('scp.rfq.title')"
      :reference="form.rfq_number"
      :subtitle="form.title || t('scp.rfq.subtitle')"
      :status="form.status"
      :status-label="statusLabel(form.status)"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.rfq'), to: '/supply-chain-procurement/rfq/list' },
        { title: form.rfq_number || t('scp.action.edit') },
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
        :value="invitations.length"
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

    <WpJournalNav
      v-model="activeSection"
      :sections="journalSections"
    >
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
            v-if="invitations.length === 0"
            :title="t('scp.rfq.empty.no_suppliers')"
            icon="tabler-building-store"
            compact
          />

          <VRow v-else>
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
                      {{ inv.supplier?.legal_name || inv.supplier?.trade_name || '—' }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ inv.supplier?.supplier_code || '' }}
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
            >
              <template #header>
                <div>
                  <div class="font-weight-bold text-body-1">
                    {{ resp.supplier?.legal_name || resp.supplier?.trade_name || '—' }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ paymentTermsLabel(resp.payment_terms_offered) }} · {{ formatDate(resp.can_deliver_by) }}
                  </div>
                </div>
                <VChip
                  v-if="rawDocument?.awarded_to_supplier_id === resp.supplier_id"
                  size="small"
                  color="success"
                  prepend-icon="tabler-award"
                  label
                >
                  {{ statusLabel('awarded') }}
                </VChip>
              </template>

              <div class="d-flex justify-space-between align-center mt-2">
                <span class="text-body-2 font-weight-bold text-primary">
                  {{ formatMoney(resp.total_ht || 0) }}
                </span>
                <VChip
                  v-if="resp.weighted_score !== null && resp.weighted_score !== undefined"
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ t('scp.common.score') }}: {{ resp.weighted_score }}
                </VChip>
              </div>

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
                  <th
                    v-for="resp in responses"
                    :key="resp.id"
                    class="text-end"
                  >
                    <div class="font-weight-bold">
                      {{ resp.supplier?.legal_name || resp.supplier?.trade_name || '—' }}
                    </div>
                    <VChip
                      v-if="rawDocument?.awarded_to_supplier_id === resp.supplier_id"
                      size="x-small"
                      color="success"
                      prepend-icon="tabler-award"
                      label
                      class="mt-1"
                    >
                      {{ statusLabel('awarded') }}
                    </VChip>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="line in form.lines"
                  :key="line.id"
                >
                  <td class="font-weight-medium">
                    {{ line.item_name }}
                  </td>
                  <td class="text-end">
                    {{ line.qty_requested }}
                  </td>
                  <td
                    v-for="resp in responses"
                    :key="resp.id"
                    class="text-end"
                  >
                    <template v-if="findResponseLineFor(resp, line.id)">
                      <div class="font-weight-bold">
                        {{ formatMoney(findResponseLineFor(resp, line.id).offered_unit_price_ht) }}
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
                    colspan="2"
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
                    colspan="2"
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
                  <td colspan="2">
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
          :chain="traceabilityChain"
        />
      </section>
    </WpJournalNav>

    <RecordRfqResponseDialog
      v-model="responseDialogOpen"
      :rfq="rawDocument"
      @submitted="reloadRfq"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="form.rfq_number"
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
.rfq-edit-page {
  overflow-x: clip;
}
</style>
