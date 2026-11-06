<script setup>
import {
  WpConfirmDeleteDialog,
  WpErrorBanner,
  WpPageHeader,
  WpUnsavedChangesDialog,
  MobileLineSheet, StatusChangeModal } from '@/components/shared'
import { useDocumentEdit } from '@/composables/use-document-edit'

import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useIprStore } from '@/stores/useIprStore'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useUserStore } from '@/stores/useUserStore'

import CatalogSearchModal from '@/views/supply-chain-procurement/ipr/edit/CatalogSearchModal.vue'
import VariantSelectModal from '@/views/supply-chain-procurement/ipr/edit/VariantSelectModal.vue'
import CompareRfq from '@/views/supply-chain-procurement/rfq/compare/CompareRfq.vue'
import BroadcastModal from '@/views/supply-chain-procurement/rfq/edit/BroadcastModal.vue'
import RfqSuppliersTable from '@/views/supply-chain-procurement/rfq/edit/RfqSuppliersTable.vue'
import CatalogPickerModal from '@/views/supply-chain-procurement/shared/CatalogPickerModal.vue'
import DocumentLinesRepeater from '@/views/supply-chain-procurement/shared/DocumentLinesRepeater.vue'

import SourceDocumentImporter from '@/views/supply-chain-procurement/shared/SourceDocumentImporter.vue'


import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

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

const route = useRoute()
const router = useRouter()
const rfqStore = useRfqStore()
const catalogStore = useCatalogItemStore()
const supplierStore = useSupplierStore()
const userStore = useUserStore()
const iprStore = useIprStore()
const snackbarStore = useSnackbarStore()
const display = useDisplay()

const rfqId = computed(() => route.params.id)
const isCreate = computed(() => rfqId.value === 'new')

// ── State ───────────────────────────────────────────────────────────────
const isSubmitting = ref(false)
const rfqReference = ref(null)
const activeTab = ref('lines')
const showMobileSummary = ref(false)
const isMobileToolsOpen = ref(false)

const form = ref({
  title: '',
  responseDeadline: '',
  required_delivery_date: null,
  evaluationCriteria: { priceWeight: 20, leadTimeWeight: 20, qualityWeight: 20, technicalWeight: 20, paymentWeight: 20 },
  terms_and_conditions: '',
  status: 'draft',
  ipr_ids: [],
  converted_to_po_id: null,
  lines: [],
  invitations: [],
  awardedToSupplierId: null,
  awarded_at: null,
  awarded_by: null,
  awarded_by_name: null,
  iprs: [],
})

// ── Relationship Data ───────────────────────────────────────────────────
const suppliersList = ref([])

// ── Composable ──────────────────────────────────────────────────────────
const {
  isLoading,
  isDirty,
  subtotal,
  taxAmount,
  grandTotal,
  bootstrap,
  refreshSnapshot,
  unsavedDialogVisible,
  confirmLeave,
  cancelLeave,
} = useDocumentEdit({
  form,
  id: route.params.id,
  isCreate: isCreate.value,
  fetchDocument: id => rfqStore.fetchDetails(id),
  lines: computed(() => form.value.lines),
  isSubmitting,
  qtyKey: 'qty_requested',
  priceKey: 'target_price_ht',
  onRelationsFetch: async () => {
    await Promise.all([
      supplierStore.fetchSuppliers({ itemsPerPage: -1 }).then(res => {
        suppliersList.value = res?.data || []
      }),
    ])
  },
  onAfterFetch: data => {
    rfqReference.value = data.reference
    form.value.converted_to_po_id = data.converted_to_po_id
    form.value.responseDeadline = data.response_deadline ? data.response_deadline.slice(0, 16) : ''
    form.value.evaluationCriteria = data.evaluation_criteria
      ? {
        priceWeight: data.evaluation_criteria.price_weight ?? 20,
        leadTimeWeight: data.evaluation_criteria.lead_time_weight ?? 20,
        qualityWeight: data.evaluation_criteria.quality_weight ?? 20,
        technicalWeight: data.evaluation_criteria.technical_weight ?? 20,
        paymentWeight: data.evaluation_criteria.payment_weight ?? 20,
      }
      : { priceWeight: 20, leadTimeWeight: 20, qualityWeight: 20, technicalWeight: 20, paymentWeight: 20 }

    form.value.required_delivery_date = data.required_delivery_date
    form.value.terms_and_conditions = data.terms_and_conditions
    form.value.ipr_ids = data.ipr_ids || []
    form.value.iprs = data.iprs || []

    form.value.lines = (data.lines || []).map(line => ({
      ...line,
      qty_requested: line.qty_requested,
      target_price_ht: line.target_price_ht,
      required_certifications: line.required_certifications || [],
      _catalog_item: line.catalog_item || null,
      _variant: line.variant || null,
    }))

    form.value.invitations = (data.invitations || []).map(inv => ({
      ...inv,
      supplierId: inv.supplier_id,
      _supplier: inv.supplier || null,
    }))

    form.value.awardedToSupplierId = data.awarded_to_supplier_id || null
    form.value.awarded_at = data.awarded_at
    form.value.awarded_by = data.awarded_by
    form.value.awarded_by_name = data.awarded_by_user_name || null
  },
})

const apiError = computed(() => rfqStore.error || null)

onMounted(async () => { await bootstrap() })

const isReadOnly = computed(() => {
  if (isCreate.value) return false

  return !['draft'].includes(form.value.status)
})

// ── Modals ──────────────────────────────────────────────────────────────
const isCatalogModalVisible = ref(false)
const isCatalogPickerVisible = ref(false)
const isVariantModalVisible = ref(false)
const isStatusModalVisible = ref(false)
const isImporterVisible = ref(false)
const isBroadcastVisible = ref(false)
const isDeleteDialogOpen = ref(false)
const selectedCatalogItemForVariant = ref(null)

const isMobileLineSheetVisible = ref(false)
const mobileLineSheetMode = ref('create')
const editingMobileLineIndex = ref(null)
const mobileLineDraft = ref(null)

// ── Line Helpers ────────────────────────────────────────────────────────
const addCatalogLine = () => { isCatalogModalVisible.value = true }
const handleCatalogPickerConfirm = lines => { form.value.lines.push(...lines) }

const handleCatalogItemSelected = async item => {
  try {
    const fullItem = await catalogStore.fetchItem(item.id)
    const variants = fullItem.variants || []
    if (variants.length === 1) { pushCatalogLine(fullItem, variants[0]) 

      return }
    if (variants.length > 1) { selectedCatalogItemForVariant.value = fullItem; isVariantModalVisible.value = true 

      return }
    pushCatalogLine(fullItem, null)
  } catch (error) {
    snackbarStore.showMessage('Failed to load item variants.', 'error')
  }
}

const handleVariantSelected = ({ item, variant }) => { pushCatalogLine(item, variant) }

const pushCatalogLine = (catalogItem, variant = null) => {
  const lineData = {
    catalog_item_id: catalogItem.id,
    variant_id: variant?.id || null,
    is_ad_hoc: false,
    ad_hoc_name: '',
    ad_hoc_sku: 'NEW',
    qty_requested: 1,
    target_price_ht: variant?.price ?? catalogItem.cost_price ?? 0,
    _catalog_item: catalogItem,
    _variant: variant,
  }

  if (isMobileLineSheetVisible.value && mobileLineDraft.value) {
    mobileLineDraft.value = { ...mobileLineDraft.value, ...lineData }
  } else {
    form.value.lines.push(lineData)
  }
}

const addAdHocLine = () => {
  form.value.lines.push({
    catalog_item_id: null, variant_id: null, is_ad_hoc: true, ad_hoc_name: '', ad_hoc_sku: 'NEW',
    qty_requested: 1, target_price_ht: 0, _catalog_item: null, _variant: null,
  })
}

const openMobileLineEdit = index => {
  const line = form.value.lines[index]
  if (!line) return
  mobileLineSheetMode.value = 'edit'
  editingMobileLineIndex.value = index
  mobileLineDraft.value = { ...line }
  isMobileLineSheetVisible.value = true
}

const saveMobileLine = draft => {
  if (mobileLineSheetMode.value === 'edit' && editingMobileLineIndex.value !== null) {
    form.value.lines.splice(editingMobileLineIndex.value, 1, draft)
  } else {
    form.value.lines.push(draft)
  }
  isMobileLineSheetVisible.value = false
}

// ── Actions ─────────────────────────────────────────────────────────────
const saveRfq = async (shouldRedirect = true) => {
  if (!form.value.title || !form.value.responseDeadline) {
    snackbarStore.showMessage('Title and deadline are required', 'error')

    return
  }
  if (form.value.lines.length === 0) {
    snackbarStore.showMessage('At least one line is required.', 'error')

    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      ...form.value,
      response_deadline: form.value.responseDeadline,
      evaluation_criteria: {
        price_weight: form.value.evaluationCriteria.priceWeight,
        lead_time_weight: form.value.evaluationCriteria.leadTimeWeight,
        quality_weight: form.value.evaluationCriteria.qualityWeight,
        technical_weight: form.value.evaluationCriteria.technicalWeight,
        payment_weight: form.value.evaluationCriteria.paymentWeight,
      },
      lines: form.value.lines.map(l => ({
        id: l.id, catalog_item_id: l.catalog_item_id, variant_id: l.variant_id,
        is_ad_hoc: l.is_ad_hoc, ad_hoc_name: l.ad_hoc_name, ad_hoc_sku: l.ad_hoc_sku,
        qty_requested: l.qty_requested, target_price_ht: l.target_price_ht,
        required_certifications: l.required_certifications || [],
      })),
      invitations: form.value.invitations.map(inv => ({ id: inv.id, supplier_id: inv.supplierId })),
      awarded_to_supplier_id: form.value.awardedToSupplierId,
      ipr_ids: form.value.ipr_ids,
    }

    if (isCreate.value) {
      await rfqStore.create(payload)
      snackbarStore.showMessage('RFQ created successfully', 'success')
      refreshSnapshot()
      if (shouldRedirect) {
        router.push({ name: 'procurement-rfq-list' })
      }
    } else {
      await rfqStore.update(rfqId.value, payload)
      snackbarStore.showMessage('RFQ updated successfully', 'success')
      refreshSnapshot()
      if (shouldRedirect) {
        router.push({ name: 'procurement-rfq-list' })
      } else {
        await bootstrap()
      }
    }
  } catch (error) {
    snackbarStore.showMessage('Failed to save RFQ. Please check inputs.', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  try {
    await rfqStore.deleteItem(rfqId.value)
    snackbarStore.showMessage('RFQ deleted successfully', 'success')
    refreshSnapshot()
    router.push({ name: 'procurement-rfq-list' })
  } catch (err) {
    snackbarStore.showMessage('Failed to delete RFQ', 'error')
  } finally {
    isDeleteDialogOpen.value = false
  }
}

const handleStatusSubmit = async data => {
  isSubmitting.value = true
  try {
    await rfqStore.updateStatus(rfqId.value, data)
    snackbarStore.showMessage('Status updated successfully', 'success')
    await bootstrap()
    isStatusModalVisible.value = false
  } catch (error) {
    snackbarStore.showMessage('Failed to update status', 'error')
  } finally {
    isSubmitting.value = false
  }
}

// ── Visual Mapping ──────────────────────────────────────────────────────
const statusColorMap = {
  draft: 'secondary', sent: 'info', awaiting_responses: 'warning',
  under_evaluation: 'primary', awarded: 'success', cancelled: 'default',
}

const importFromIpr = data => {
  if (data.sourceId && !form.value.ipr_ids.includes(data.sourceId)) {
    form.value.ipr_ids.push(data.sourceId)
    form.value.iprs.push({ id: data.sourceId, reference: data.sourceReference })
  }

  ;(data.lines || []).forEach(l => {
    form.value.lines.push({
      ...l,
      qty_requested: parseFloat(l.qty) || 0,
      target_price_ht: parseFloat(l.unit_cost) || 0,
      required_certifications: [],
      _catalog_item: l.catalog_item || null,
      _variant: l.variant || null,
    })
  })

  snackbarStore.showMessage(`Imported ${(data.lines || []).length} lines from ${data.sourceReference}`, 'success')
}

const handleToggleAward = async supplierId => {
  isSubmitting.value = true
  try {
    if (form.value.awardedToSupplierId === supplierId) {
      // Revoke award - transition back to under_evaluation
      await rfqStore.updateStatus(rfqId.value, {
        status: 'under_evaluation',
        awarded_to_supplier_id: null,
      })
      form.value.awardedToSupplierId = null
      form.value.status = 'under_evaluation'
      form.value.awarded_at = null
      form.value.awarded_by = null
      form.value.awarded_by_name = null
    } else {
      // Award to supplier - use the status transition endpoint
      await rfqStore.updateStatus(rfqId.value, {
        status: 'awarded',
        awarded_to_supplier_id: supplierId,
      })
      form.value.awardedToSupplierId = supplierId
      form.value.status = 'awarded'
      form.value.awarded_at = new Date().toISOString()
      form.value.awarded_by = userStore.user?.id || null
      form.value.awarded_by_name = userStore.user?.full_name || userStore.user?.username || 'Current User'
    }
    snackbarStore.showMessage('RFQ award updated successfully', 'success')
    await bootstrap()
  } catch (error) {
    snackbarStore.showMessage('Failed to update award', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const detachIpr = iprId => {
  form.value.ipr_ids = form.value.ipr_ids.filter(id => id !== iprId)
  form.value.iprs = form.value.iprs.filter(ipr => ipr.id !== iprId)
}
</script>

<template>
  <!-- 👉 Modals -->
  <CatalogSearchModal
    v-model:is-dialog-visible="isCatalogModalVisible"
    @select="handleCatalogItemSelected"
  />
  <CatalogPickerModal
    v-model:is-dialog-visible="isCatalogPickerVisible"
    document-type="rfq"
    @confirm="handleCatalogPickerConfirm"
  />
  <VariantSelectModal
    v-model:is-dialog-visible="isVariantModalVisible"
    :catalog-item="selectedCatalogItemForVariant"
    @select="handleVariantSelected"
  />
  <StatusChangeModal
    v-model:is-dialog-visible="isStatusModalVisible"
    document-type="RFQ"
    :document="form"
    :is-submitting="isSubmitting"
    @submit="handleStatusSubmit"
  />
  <SourceDocumentImporter
    v-model:is-dialog-visible="isImporterVisible"
    :allowed-sources="['ipr']"
    target-label="RFQ Lines"
    @import="importFromIpr"
  />
  <BroadcastModal
    v-model:is-dialog-visible="isBroadcastVisible"
    :rfq-id="rfqId"
    :rfq-reference="rfqReference || 'New RFQ'"
    :invitations="form.invitations"
  />
  <MobileLineSheet
    v-model="isMobileLineSheetVisible"
    document-type="RFQ"
    :mode="mobileLineSheetMode"
    :line-value="mobileLineDraft || {}"
    :catalog-items="catalogStore.items"
    :is-read-only="isReadOnly"
    @save="saveMobileLine"
    @open-catalog="addCatalogLine"
  />

  <div class="edit-page-container">
    <!-- 👉 Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="$t('Home')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem :title="$t('Supply Chain Procurement')" />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'procurement-rfq-list' }"
        :title="$t('RFQ')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="isCreate ? $t('New RFQ') : (rfqReference || $t('Loading...'))"
        disabled
      />
    </VBreadcrumbs>

    <WpPageHeader
      :title="isCreate ? 'Nouveau RFQ' : (rfqReference || 'Loading...')"
      subtitle="Gérer l'appel d'offres et les réponses fournisseurs."
      icon="tabler-file-description"
    >
      <template #actions>
        <div
          v-if="!isCreate && !isLoading"
          class="d-flex align-center ga-2 me-3"
        >
          <VChip
            :color="statusColorMap[form.status] || 'primary'"
            label
            size="small"
            class="text-capitalize"
          >
            {{ form.status.replace(/_/g, ' ') }}
          </VChip>
          <VChip
            v-if="form.converted_to_po_id"
            color="info"
            variant="tonal"
            size="x-small"
            prepend-icon="tabler-link"
          >
            PO #{{ form.converted_to_po_id }}
          </VChip>
        </div>
        <template v-if="display.mdAndUp.value">
          <div class="d-none">
            <!-- Buttons moved to side card -->
          </div>
        </template>
      </template>
    </WpPageHeader>

    <WpErrorBanner
      :error="apiError"
      :validation-errors="rfqStore.validationErrors"
    />

    <VSkeletonLoader
      v-if="isLoading"
      type="card, article, table-row-divider"
      class="mb-6 bg-transparent"
    />

    <VRow
      v-else
      class="mt-2"
    >
      <!-- Left Column: RFQ Details -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          class="mb-4 neo-raised d-flex flex-column"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-layout-dashboard"
              size="18"
              color="primary"
            /> Overview
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <div class="d-flex flex-column ga-2">
              <div class="field-row">
                <span class="field-row__label">Deadline</span><span class="field-row__value">{{ form.responseDeadline ? new Date(form.responseDeadline).toLocaleDateString() : 'Not Set' }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Required By</span><span class="field-row__value">{{ form.required_delivery_date ? new Date(form.required_delivery_date).toLocaleDateString() : 'Not Set' }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Lines</span><VChip
                  color="primary"
                  size="x-small"
                  label
                  variant="tonal"
                >
                  {{ form.lines.length }}
                </VChip>
              </div>
            </div>
            <div
              v-if="form.iprs?.length"
              class="d-flex align-center ga-1 mt-3 flex-wrap"
            >
              <span class="text-caption text-disabled me-1">Source IPRs:</span>
              <VChip
                v-for="ipr in form.iprs"
                :key="ipr.id"
                size="x-small"
                label
                color="secondary"
                variant="tonal"
                closable
                :readonly="isReadOnly"
                @click:close="detachIpr(ipr.id)"
              >
                #{{ ipr.reference || ipr.id.slice(0, 8) }}
              </VChip>
            </div>
            <div
              v-if="form.status === 'awarded' || form.awardedToSupplierId"
              class="mt-3 pa-3 rounded-lg d-flex align-center ga-3"
              style="background: rgba(var(--v-theme-success), 0.08);"
            >
              <VAvatar
                color="success"
                variant="tonal"
                size="32"
              >
                <VIcon
                  icon="tabler-trophy"
                  size="18"
                />
              </VAvatar>
              <div>
                <div class="text-caption font-weight-bold text-success">
                  Awarded {{ form.awarded_at ? new Date(form.awarded_at).toLocaleDateString() : '' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  By {{ form.awarded_by_name || form.awarded_by }}
                </div>
              </div>
            </div>

            <!-- Action Buttons for Desktop -->
            <div
              v-if="display.mdAndUp.value"
              class="mt-6 d-flex flex-column ga-3"
            >
              <VBtn
                color="primary"
                prepend-icon="tabler-device-floppy"
                :disabled="!isDirty || isReadOnly"
                :loading="isSubmitting"
                block
                @click="saveRfq"
              >
                Enregistrer
              </VBtn>
              <VBtn
                v-if="!isCreate && form.status === 'draft'"
                color="info"
                variant="tonal"
                prepend-icon="tabler-send"
                block
                @click="isBroadcastVisible = true"
              >
                Broadcast
              </VBtn>
              <VBtn
                v-if="!isCreate"
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-arrows-left-right"
                block
                @click="isStatusModalVisible = true"
              >
                Changer Statut
              </VBtn>
              <VBtn
                v-if="!isCreate && form.status === 'draft'"
                color="error"
                variant="tonal"
                prepend-icon="tabler-trash"
                block
                @click="isDeleteDialogOpen = true"
              >
                Supprimer
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard
          class="mb-4 neo-raised"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-info-circle"
              size="18"
              color="primary"
            /> Header & Terms
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <VRow dense>
              <VCol cols="12">
                <AppTextField
                  v-model="form.title"
                  label="RFQ Title"
                  placeholder="Descriptive title..."
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppDateTimePicker
                  v-model="form.responseDeadline"
                  label="Response Deadline"
                  :readonly="isReadOnly"
                  :config="{ enableTime: true, dateFormat: 'Y-m-d H:i' }"
                />
              </VCol>
              <VCol cols="12">
                <AppDateTimePicker
                  v-model="form.required_delivery_date"
                  label="Required Delivery Date"
                  :readonly="isReadOnly"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.terms_and_conditions"
                  label="Terms & Conditions"
                  rows="2"
                  :readonly="isReadOnly"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Evaluation Weights -->
        <VCard
          class="mb-4 neo-raised"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-award"
              size="18"
              color="primary"
            /> Weights
            <VSpacer />
            <VChip
              size="x-small"
              :color="Object.values(form.evaluationCriteria).reduce((a, b) => a + b, 0) === 100 ? 'success' : 'error'"
            >
              {{ Object.values(form.evaluationCriteria).reduce((a, b) => a + b, 0) }}%
            </VChip>
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <div
              v-for="(val, key) in form.evaluationCriteria"
              :key="key"
              class="mb-2"
            >
              <div class="text-caption text-capitalize mb-1">
                {{ key.replace('Weight', '') }}
              </div>
              <VSlider
                v-model="form.evaluationCriteria[key]"
                min="0"
                max="100"
                step="5"
                thumb-label
                density="compact"
                hide-details
                :readonly="isReadOnly"
                color="primary"
              >
                <template #append>
                  <span
                    class="text-caption font-weight-bold"
                    style="inline-size: 35px;"
                  >{{ form.evaluationCriteria[key] }}%</span>
                </template>
              </VSlider>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Right Column: Tabs -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard
          class="neo-raised edit-main-card"
          flat
        >
          <VTabs
            v-if="display.mdAndUp.value"
            v-model="activeTab"
            grow
            stacked
            color="primary"
          >
            <VTab value="lines">
              <VIcon
                icon="tabler-list-details"
                class="mb-2"
              />
              <span>Lines ({{ form.lines.length }})</span>
            </VTab>
            <VTab value="suppliers">
              <VIcon
                icon="tabler-building-store"
                class="mb-2"
              />
              <span>Suppliers ({{ form.invitations.length }})</span>
            </VTab>
            <VTab
              v-if="!isCreate"
              value="compare"
            >
              <VIcon
                icon="tabler-chart-bar"
                class="mb-2"
              />
              <span>Compare</span>
            </VTab>
          </VTabs>
          <VDivider />
          <VWindow v-model="activeTab">
            <VWindowItem value="lines">
              <DocumentLinesRepeater
                v-model:lines="form.lines"
                document-type="RFQ"
                :catalog-items="catalogStore.items"
                :is-read-only="isReadOnly"
                @add-catalog-line="addCatalogLine"
                @add-adhoc-line="addAdHocLine"
                @import-lines="isImporterVisible = true"
                @edit-mobile-line="openMobileLineEdit"
              />
              <div
                v-if="!isReadOnly"
                class="pa-4"
              >
                <VBtn
                  color="primary"
                  variant="outlined"
                  block
                  prepend-icon="tabler-grid-3x3"
                  @click="isCatalogPickerVisible = true"
                >
                  Multi-Select from Catalog
                </VBtn>
              </div>
            </VWindowItem>
            <VWindowItem value="suppliers">
              <RfqSuppliersTable
                v-model:invitations="form.invitations"
                :suppliers-list="suppliersList"
                :is-read-only="isReadOnly"
              />
            </VWindowItem>
            <VWindowItem
              v-if="!isCreate"
              value="compare"
            >
              <CompareRfq
                :rfq-id="rfqId"
                :rfq-status="form.status"
                :form-awarded-supplier-id="form.awardedToSupplierId"
                @toggle-award="handleToggleAward"
              />
            </VWindowItem>
          </VWindow>
        </VCard>
      </VCol>
    </VRow>

    <!-- Desktop Financial Bottom Bar -->
    <div
      v-if="display.mdAndUp.value && !isLoading"
      class="financial-bottom-bar"
    >
      <div
        class="d-flex align-center justify-end ga-6 px-6 py-3 mx-auto"
        style="max-inline-size: 1400px;"
      >
        <div class="d-flex align-center ga-2">
          <span class="text-caption text-uppercase font-weight-bold text-disabled">Total HT</span><span class="text-h6 font-weight-bold text-primary">{{ subtotal.toFixed(3) }} TND</span>
        </div>
      </div>
    </div>

    <!-- Mobile Bottom Bar + Tools Nav -->
    <template v-if="display.smAndDown.value && !isLoading">
      <VBottomNavigation
        v-model="activeTab"
        color="primary"
        fixed
        grow
        class="elevation-8 border-t"
      >
        <VBtn
          value="lines"
          variant="text"
        >
          <VIcon>tabler-list-details</VIcon>
          <span class="text-caption">Lines</span>
        </VBtn>
        <VBtn
          value="suppliers"
          variant="text"
        >
          <VIcon>tabler-users</VIcon>
          <span class="text-caption">Suppliers</span>
        </VBtn>
        <VBtn
          v-if="!isCreate"
          value="compare"
          variant="text"
        >
          <VIcon>tabler-chart-bar</VIcon>
          <span class="text-caption">Compare</span>
        </VBtn>
        <VBtn
          value="save"
          variant="text"
          :disabled="!isDirty || isReadOnly || isSubmitting"
          @click="saveRfq"
        >
          <VIcon>tabler-device-floppy</VIcon>
          <span class="text-caption">Save</span>
        </VBtn>
        <VBtn
          v-if="!isCreate"
          value="status"
          variant="text"
          @click="isStatusModalVisible = true"
        >
          <VIcon>tabler-arrows-left-right</VIcon>
          <span class="text-caption">Status</span>
        </VBtn>
      </VBottomNavigation>
    </template>

    <WpConfirmDeleteDialog
      v-model="isDeleteDialogOpen"
      @confirm="handleDelete"
    />
    <WpUnsavedChangesDialog
      v-model="unsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style scoped lang="scss">
.edit-main-card { min-block-size: 500px; }

.financial-bottom-bar {
  position: fixed;
  z-index: 50;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.92);
  border-block-start: 1px solid rgba(var(--v-border-color), 0.12);
  box-shadow: 0 -4px 24px rgba(0, 0, 0, 6%);
  inset-block-end: 0;
  inset-inline: 0;
}

.mobile-tools-fab {
  position: fixed !important;
  z-index: 100;
  inset-block-end: 72px;
  inset-inline-end: 16px;
}

.border-dashed { border-style: dashed !important; }

@media (min-width: 960px) { .edit-page-container { padding-block-end: 120px; } }
</style>
