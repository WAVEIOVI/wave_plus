<script setup>
import {
  MobileLineSheet,
  StatusChangeModal,
  WpConfirmDeleteDialog,
  WpErrorBanner,
  WpPageHeader,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useDocumentEdit } from '@/composables/use-document-edit'
import { localizedValue } from '@/composables/useLocaleField'

import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useIprStore } from '@/stores/useIprStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useUserStore } from '@/stores/useUserStore'

import CatalogSearchModal from '@/views/supply-chain-procurement/ipr/edit/CatalogSearchModal.vue'
import VariantSelectModal from '@/views/supply-chain-procurement/ipr/edit/VariantSelectModal.vue'
import SupplierSearchModal from '@/views/supply-chain-procurement/ipr/SupplierSearchModal.vue'
import CatalogPickerModal from '@/views/supply-chain-procurement/shared/CatalogPickerModal.vue'
import DocumentLinesRepeater from '@/views/supply-chain-procurement/shared/DocumentLinesRepeater.vue'

import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

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

const route = useRoute()
const router = useRouter()
const iprStore = useIprStore()
const catalogItemStore = useCatalogItemStore()
const supplierStore = useSupplierStore()
const userStore = useUserStore()
const snackbarStore = useSnackbarStore()
const display = useDisplay()

const iprId = computed(() => route.params.id)
const isCreate = computed(() => iprId.value === 'new')

// ── State ───────────────────────────────────────────────────────────────
const isSubmitting = ref(false)
const iprReference = ref(null)
const activeTab = ref('lines')
const showMobileSummary = ref(false)
const isMobileToolsOpen = ref(false)

const form = ref({
  trigger_source: 'manual_user',
  trigger_reference: '',
  requested_by: null,
  assigned_to: null,
  urgency: 'normal',
  needed_by_date: null,
  status: 'draft',
  internal_justification: '',
  approval_note: '',
  rejection_reason: '',
  converted_to_po_id: null,
  converted_po: null,
  lines: [],
})

// ── Options ─────────────────────────────────────────────────────────────
const triggerSources = [
  { title: 'Auto Stock Alert', value: 'auto_stock_alert' },
  { title: 'Auto Workshop', value: 'auto_workshop' },
  { title: 'Manual User', value: 'manual_user' },
  { title: 'Planned Project', value: 'planned_project' },
]

const urgencyOptions = [
  { title: 'Low', value: 'low', icon: 'tabler-arrows-down', color: 'info' },
  { title: 'Normal', value: 'normal', icon: 'tabler-minus', color: 'primary' },
  { title: 'Urgent', value: 'urgent', icon: 'tabler-alert-triangle', color: 'warning' },
  { title: 'Critical', value: 'critical', icon: 'tabler-flame', color: 'error' },
]

// ── Relationship Data ───────────────────────────────────────────────────
const usersList = ref([])
const catalogItemsList = ref([])
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
  fetchDocument: id => iprStore.fetchDetails(id),
  lines: computed(() => form.value.lines),
  isSubmitting,
  qtyKey: 'qty_requested',
  priceKey: 'estimated_unit_cost',
  onRelationsFetch: async () => {
    await Promise.all([
      userStore.fetchUsers({ itemsPerPage: -1 }).then(res => {
        usersList.value = Array.isArray(res) ? res : (res?.data || res?.users || [])
      }),
      catalogItemStore.fetchItems({ itemsPerPage: -1, 'filter[status]': 'active', include: 'uom,preferred_supplier' }).then(res => {
        catalogItemsList.value = Array.isArray(res) ? res : (res?.data || [])
      }),
      supplierStore.fetchSuppliers({ itemsPerPage: -1 }).then(res => {
        suppliersList.value = Array.isArray(res) ? res : (res?.data || [])
      }),
    ])
  },
  onAfterFetch: data => {
    iprReference.value = data.reference
    form.value.converted_to_po_id = data.converted_to_po_id
    form.value.converted_po = data.po || null
    form.value.lines = data.lines.map(line => ({
      ...line,
      _catalog_item: line.catalog_item || null,
      _variant: line.variant || null,
      _suggested_supplier: line.suggested_supplier || null,
    }))
  },
})

const apiError = computed(() => iprStore.error || null)

// ── Lifecycle ───────────────────────────────────────────────────────────
onMounted(() => bootstrap())

const isReadOnly = computed(() => {
  if (isCreate.value) return false

  return !['draft', 'pending_approval'].includes(form.value.status)
})

// ── Modals ──────────────────────────────────────────────────────────────
const isCatalogModalVisible = ref(false)
const isCatalogPickerVisible = ref(false)
const isVariantModalVisible = ref(false)
const isStatusModalVisible = ref(false)
const isSupplierModalVisible = ref(false)
const isDeleteDialogOpen = ref(false)
const isMobileLineSheetVisible = ref(false)
const mobileLineSheetMode = ref('create')
const editingMobileLineIndex = ref(null)
const mobileLineDraft = ref(null)
const activeLineIndexForSupplier = ref(null)
const selectedCatalogItemForVariant = ref(null)

// ── Line Helpers ────────────────────────────────────────────────────────
const addCatalogLine = () => { isCatalogModalVisible.value = true }

const handleCatalogPickerConfirm = lines => {
  form.value.lines.push(...lines)
}

const handleCatalogItemSelected = async item => {
  try {
    const fullItem = await catalogItemStore.fetchItem(item.id)
    const variants = fullItem.variants || []

    if (variants.length === 1) {
      pushCatalogLine(fullItem, variants[0])

      return
    }

    if (variants.length > 1) {
      selectedCatalogItemForVariant.value = fullItem
      isVariantModalVisible.value = true

      return
    }

    pushCatalogLine(fullItem, null)
  }
  catch (error) {
    snackbarStore.showMessage('Failed to load item variants.', 'error')
  }
}

const handleVariantSelected = ({ item, variant }) => {
  pushCatalogLine(item, variant)
}

const pushCatalogLine = (catalogItem, variant = null) => {
  const lineData = {
    catalog_item_id: catalogItem.id,
    variant_id: variant?.id || null,
    is_ad_hoc: false,
    ad_hoc_name: '',
    ad_hoc_sku: 'NEW',
    qty_requested: 1,
    qty_current_stock: variant?.stock ?? 0,
    suggested_supplier_id: catalogItem.preferred_supplier_id || null,
    estimated_unit_cost: variant?.price ?? 0,
    _catalog_item: catalogItem,
    _variant: variant,
    _suggested_supplier: catalogItem.preferred_supplier || null,
  }

  if (isMobileLineSheetVisible.value && mobileLineDraft.value) {
    mobileLineDraft.value = { ...mobileLineDraft.value, ...lineData }
  } else {
    form.value.lines.push(lineData)
  }
}

const addAdHocLine = () => {
  form.value.lines.push({
    catalog_item_id: null,
    variant_id: null,
    is_ad_hoc: true,
    ad_hoc_name: '',
    ad_hoc_sku: 'NEW',
    qty_requested: 1,
    qty_current_stock: 0,
    suggested_supplier_id: null,
    estimated_unit_cost: null,
    _catalog_item: null,
    _variant: null,
    _suggested_supplier: null,
  })
}

const openSupplierSearch = index => {
  activeLineIndexForSupplier.value = index
  isSupplierModalVisible.value = true
}

const handleSupplierSelected = supplier => {
  if (isMobileLineSheetVisible.value && mobileLineDraft.value) {
    mobileLineDraft.value.suggested_supplier_id = supplier.id
    mobileLineDraft.value._suggested_supplier = supplier

    return
  }

  if (activeLineIndexForSupplier.value !== null) {
    const line = form.value.lines[activeLineIndexForSupplier.value]
    if (line) {
      line.suggested_supplier_id = supplier.id
      line._suggested_supplier = supplier
    }
    activeLineIndexForSupplier.value = null
  }
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
const saveIpr = async () => {
  if (!form.value.requested_by) {
    snackbarStore.showMessage('Requested By is required', 'error')

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
      lines: form.value.lines.map(line => ({
        ...(line.id ? { id: line.id } : {}),
        catalog_item_id: line.is_ad_hoc ? null : line.catalog_item_id,
        variant_id: line.is_ad_hoc ? null : (line.variant_id || line._variant?.id || null),
        is_ad_hoc: line.is_ad_hoc,
        ad_hoc_name: line.is_ad_hoc ? line.ad_hoc_name : null,
        ad_hoc_sku: line.is_ad_hoc ? (line.ad_hoc_sku || 'NEW') : null,
        qty_requested: line.qty_requested,
        qty_current_stock: line.qty_current_stock,
        suggested_supplier_id: line.suggested_supplier_id,
        estimated_unit_cost: line.estimated_unit_cost,
      })),
    }

    if (isCreate.value) {
      const res = await iprStore.create(payload)

      snackbarStore.showMessage('IPR created successfully', 'success')
      refreshSnapshot()
      router.push({ name: 'procurement-ipr-edit-id', params: { id: res?.data?.id || res?.id } })
    } else {
      await iprStore.update(iprId.value, payload)
      snackbarStore.showMessage('IPR updated successfully', 'success')
      refreshSnapshot()
      router.push({ name: 'procurement-ipr-list' })
    }
  } catch (error) {
    snackbarStore.showMessage('Failed to save IPR. Please check inputs.', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  try {
    await iprStore.deleteItem(iprId.value)
    snackbarStore.showMessage('IPR deleted successfully', 'success')
    refreshSnapshot()
    router.push({ name: 'procurement-ipr-list' })
  } catch (err) {
    snackbarStore.showMessage('Failed to delete IPR', 'error')
  } finally {
    isDeleteDialogOpen.value = false
  }
}

const handleStatusSubmit = async data => {
  isSubmitting.value = true
  try {
    await iprStore.updateStatus(iprId.value, data)
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
  draft: 'secondary',
  pending_approval: 'warning',
  approved: 'success',
  rejected: 'error',
  merged: 'info',
  converted_to_po: 'primary',
  cancelled: 'default',
}

const computedUsers = computed(() => usersList.value.map(u => ({
  title: `${u.first_name || ''} ${u.last_name || ''} — ${u.email}`,
  value: u.id,
})))

const resolveUrgencyColor = value => {
  const opt = urgencyOptions.find(u => u.value === value)

  return opt?.color || 'primary'
}

const resolveSupplierName = id => {
  const s = suppliersList.value.find(s => s.id === id)

  return s ? (localizedValue(s.commercial_name) || s.legal_name || 'Supplier') : '—'
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
    document-type="ipr"
    @confirm="handleCatalogPickerConfirm"
  />
  <VariantSelectModal
    v-model:is-dialog-visible="isVariantModalVisible"
    :catalog-item="selectedCatalogItemForVariant"
    @select="handleVariantSelected"
  />
  <SupplierSearchModal
    v-model:is-dialog-visible="isSupplierModalVisible"
    @select="handleSupplierSelected"
  />
  <StatusChangeModal
    v-model:is-dialog-visible="isStatusModalVisible"
    document-type="IPR"
    :document="form"
    :is-submitting="isSubmitting"
    @submit="handleStatusSubmit"
  />
  <MobileLineSheet
    v-model="isMobileLineSheetVisible"
    document-type="IPR"
    :mode="mobileLineSheetMode"
    :line-value="mobileLineDraft || {}"
    :catalog-items="catalogItemsList"
    :suppliers="suppliersList"
    :is-read-only="isReadOnly"
    @save="saveMobileLine"
    @open-catalog="addCatalogLine"
    @open-supplier="isSupplierModalVisible = true"
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
        :to="{ name: 'procurement-ipr-list' }"
        :title="$t('IPR')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="isCreate ? $t('New IPR') : (iprReference || $t('Loading...'))"
        disabled
      />
    </VBreadcrumbs>

    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="isCreate ? 'Nouvelle IPR' : (iprReference || 'Loading...')"
      subtitle="Gérer les détails de la demande d'achat interne."
      icon="tabler-file-invoice"
    >
      <template #actions>
        <!-- Status + Urgency badges -->
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
            :color="resolveUrgencyColor(form.urgency)"
            label
            size="x-small"
            variant="tonal"
            class="text-capitalize"
          >
            <VIcon
              start
              size="12"
              :icon="urgencyOptions.find(o => o.value === form.urgency)?.icon"
            />
            {{ form.urgency }}
          </VChip>
        </div>
      </template>
    </WpPageHeader>

    <WpErrorBanner
      :error="apiError"
      :validation-errors="iprStore.validationErrors"
    />

    <!-- 👉 Skeleton Loader -->
    <VSkeletonLoader
      v-if="isLoading"
      type="card, article, table-row-divider"
      class="mb-6 bg-transparent"
    />

    <!-- 👉 Form Layout -->
    <VRow
      v-else
      class="mt-2"
    >
      <!-- Left Column: Request Details -->
      <VCol
        cols="12"
        md="4"
      >
        <!-- Overview Card -->
        <VCard
          class="mb-4 neo-raised d-flex flex-column"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex justify-space-between align-center">
            <div class="d-flex align-center ga-2">
              <VIcon
                icon="tabler-layout-dashboard"
                size="18"
                color="primary"
              />
              Overview
            </div>
            <VChip
              v-if="form.converted_to_po_id"
              color="info"
              variant="tonal"
              size="x-small"
              prepend-icon="tabler-link"
              class="font-weight-bold"
              :to="{ name: 'procurement-po-edit-id', params: { id: form.converted_to_po_id } }"
            >
              {{ form.converted_po?.reference || 'PO #' + form.converted_to_po_id }}
            </VChip>
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1 flex-grow-1">
            <div class="d-flex flex-column ga-2">
              <div class="field-row">
                <span class="field-row__label">Reference</span>
                <span class="field-row__value font-weight-bold">{{ isCreate ? 'Auto-generated' : iprReference }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Needed By</span>
                <span class="field-row__value">{{ form.needed_by_date ? new Date(form.needed_by_date).toLocaleDateString() : 'Not Set' }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Lines</span>
                <VChip
                  color="primary"
                  size="x-small"
                  label
                  variant="tonal"
                >
                  {{ form.lines.length }}
                </VChip>
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
                @click="saveIpr"
              >
                Enregistrer
              </VBtn>
              <VBtn
                v-if="!isCreate && form.status === 'approved'"
                color="success"
                variant="tonal"
                prepend-icon="tabler-transform"
                block
                :to="{ name: 'procurement-po-edit-id', params: { id: 'new' }, query: { path: 'from_ipr', ipr_ids: iprId } }"
              >
                Convertir en PO
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

        <!-- Request Details -->
        <VCard
          class="mb-4 neo-raised"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-info-circle"
              size="18"
              color="primary"
            />
            Request Details
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <VRow dense>
              <VCol cols="12">
                <AppSelect
                  v-model="form.trigger_source"
                  label="Trigger Source"
                  :items="triggerSources"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.trigger_reference"
                  label="Trigger Reference"
                  placeholder="e.g. WO-1234, PROJ-56"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppAutocomplete
                  v-model="form.requested_by"
                  label="Requested By *"
                  :items="computedUsers"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppAutocomplete
                  v-model="form.assigned_to"
                  label="Assigned To"
                  :items="computedUsers"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.urgency"
                  label="Urgency"
                  :items="urgencyOptions"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.needed_by_date"
                  label="Needed By Date"
                  type="date"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.internal_justification"
                  label="Internal Justification"
                  rows="2"
                  :readonly="isReadOnly"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Right Column: Tabbed Content (Lines + Review) -->
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
            <VTab value="review">
              <VIcon
                icon="tabler-message-dots"
                class="mb-2"
              />
              <span>Review & Feedback</span>
            </VTab>
          </VTabs>
          <VDivider v-if="display.mdAndUp.value" />
          <VWindow v-model="activeTab">
            <!-- Lines Tab -->
            <VWindowItem value="lines">
              <DocumentLinesRepeater
                v-model:lines="form.lines"
                document-type="IPR"
                :catalog-items="catalogItemsList"
                :suppliers="suppliersList"
                :is-read-only="isReadOnly"
                @add-catalog-line="addCatalogLine"
                @add-adhoc-line="addAdHocLine"
                @open-supplier-search="openSupplierSearch"
                @edit-mobile-line="openMobileLineEdit"
              />

              <!-- Multi-Select Picker Button -->
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
                  Select Multiple Items from Catalog
                </VBtn>
              </div>
            </VWindowItem>

            <!-- Review & Feedback Tab -->
            <VWindowItem value="review">
              <div class="pa-5">
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextarea
                      v-model="form.approval_note"
                      label="Approval Note"
                      rows="3"
                      readonly
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextarea
                      v-model="form.rejection_reason"
                      label="Rejection Reason"
                      rows="3"
                      readonly
                    />
                  </VCol>
                </VRow>
              </div>
            </VWindowItem>
          </VWindow>
        </VCard>
      </VCol>
      <div
        v-if="display.mdAndUp.value && !isLoading"
        class="financial-bottom-bar"
      >
        <div
          class="d-flex align-center justify-end ga-6 px-6 py-3 mx-auto"
          style="max-inline-size: 1400px;"
        >
          <div class="d-flex align-center ga-2">
            <span class="text-caption text-uppercase font-weight-bold text-disabled">Total HT</span>
            <span class="text-body-1 font-weight-bold">{{ subtotal.toFixed(3) }}</span>
          </div>
          <VDivider
            vertical
            class="my-1"
          />
          <div class="d-flex align-center ga-2">
            <span class="text-caption text-uppercase font-weight-bold text-disabled">TVA</span>
            <span class="text-body-2 font-weight-medium">{{ taxAmount.toFixed(3) }}</span>
          </div>
          <VDivider
            vertical
            class="my-1"
          />
          <div class="d-flex align-center ga-2">
            <span class="text-caption text-uppercase font-weight-bold text-disabled">Total TTC</span>
            <span class="text-h6 font-weight-bold text-primary">{{ grandTotal.toFixed(3) }} TND</span>
          </div>
        </div>
      </div>

      <!-- 👉 Mobile: Tools FAB + Bottom Nav -->
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
            Lines
          </VBtn>
          <VBtn
            value="review"
            variant="text"
          >
            <VIcon>tabler-message-dots</VIcon>
            Review
          </VBtn>
          <VBtn
            value="save"
            variant="text"
            :disabled="!isDirty || isReadOnly || isSubmitting"
            @click="saveIpr"
          >
            <VIcon>tabler-device-floppy</VIcon>
            Save
          </VBtn>
          <VBtn
            v-if="!isCreate"
            value="status"
            variant="text"
            @click="isStatusModalVisible = true"
          >
            <VIcon>tabler-arrows-left-right</VIcon>
            Status
          </VBtn>
        </VBottomNavigation>
      </template>
    </VRow>

    <!-- 👉 Dialogs -->
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
.edit-main-card {
  min-block-size: 500px;
}

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

.procurement-mobile-summary-panel {
  position: fixed;
  z-index: 98;
  background: rgb(var(--v-theme-surface));
  border-block-start: 1px solid rgba(var(--v-border-color), 0.12);
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 8%);
  inset-block-end: 56px;
  inset-inline: 0;
}

.mobile-tools-fab {
  position: fixed !important;
  z-index: 100;
  inset-block-end: 72px;
  inset-inline-end: 16px;
}

.sticky-summary {
  position: sticky;
  z-index: 10;
  inset-block-start: 84px;
}

/* Give desktop bottom bar clearance */
@media (min-width: 960px) { .edit-page-container { padding-block-end: 120px; } }
</style>
