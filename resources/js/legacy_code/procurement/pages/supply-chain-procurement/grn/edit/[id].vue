<script setup>
/* eslint-disable camelcase */
import { WpConfirmDeleteDialog, WpErrorBanner, WpPageHeader, WpUnsavedChangesDialog } from '@/components/shared'
import { useDocumentEdit } from '@/composables/use-document-edit'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useGrnStore } from '@/stores/useGrnStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { usePoStore } from '@/stores/usePoStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useUserStore } from '@/stores/useUserStore'
import GrnLinesRepeater from '@/views/supply-chain-procurement/grn/edit/GrnLinesRepeater.vue'
import GrnRejectedItemsTab from '@/views/supply-chain-procurement/grn/edit/GrnRejectedItemsTab.vue'
import ThreeWayMatchModal from '@/views/supply-chain-procurement/po/match/ThreeWayMatchModal.vue'
import { MobileLineSheet } from '@/components/shared'
import { StatusChangeModal } from '@/components/shared'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import CatalogPickerModal from '@/views/supply-chain-procurement/shared/CatalogPickerModal.vue'

definePage({
  name: 'procurement-grn-edit-id',
  meta: { layout: 'default', navActiveLink: 'procurement-grn-list', module: 'supply-chain-procurement', action: 'write', subject: 'SupplyChainProcurement' },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const grnStore = useGrnStore()
const inventoryStore = useInventoryStore()
const poStore = usePoStore()
const supplierStore = useSupplierStore()
const userStore = useUserStore()
const { success, error: showError } = useAppSnackbar()
const display = useDisplay()

const grnId = computed(() => route.params.id)
const isNew = computed(() => grnId.value === 'new')
const isSubmitting = ref(false)
const grnReference = ref(null)
const poSiteId = ref(null)
const poSummary = ref(null)

const form = ref({
  po_id: null, supplier_id: null, currency: 'TND', received_by: null,
  received_at: new Date().toISOString().substring(0, 10), status: 'draft',
  overall_qc_result: 'passed', is_partial_delivery: false, supplier_delivery_note_ref: null,
  delivery_on_time: false, days_late: 0, qc_performed_by: null, qc_performed_at: null,
  internal_notes: null, lines: [],
})

const activeTab = ref('lines')
const showMobileSummary = ref(false)
const isMobileToolsOpen = ref(false)

const posList = ref([])
const suppliersList = ref([])
const usersList = ref([])
const locationsList = ref([])
const zonesList = ref([])

const {
  isLoading, isDirty, bootstrap, refreshSnapshot, unsavedDialogVisible, confirmLeave, cancelLeave,
} = useDocumentEdit({
  form, id: route.params.id, isCreate: isNew.value,
  fetchDocument: id => grnStore.fetchDetails(id), isSubmitting,
  onRelationsFetch: async () => {
    const [suppRes, poRes, userRes] = await Promise.all([
      supplierStore.fetchSuppliers({ itemsPerPage: -1 }),
      poStore.fetchList({ itemsPerPage: -1, 'filter[status]': 'approved,sent,acknowledged,in_delivery,partially_received' }),
      userStore.fetchUsers({ itemsPerPage: -1 }),
    ])

    suppliersList.value = suppRes?.data || []
    posList.value = poRes?.data || []
    usersList.value = Array.isArray(userRes) ? userRes : (userRes?.data || userRes?.users || [])
  },
  onAfterFetch: async data => {
    grnReference.value = data.reference
    form.value.received_at = data.received_at?.substring(0, 10)
    form.value.purchase_order = data.purchase_order || null
    if (data.qc_performed_at) form.value.qc_performed_at = data.qc_performed_at.slice(0, 16)
    if (data.po_id) await fetchLocationsForPo(data.po_id)
    form.value.lines = (data.lines || []).map(line => ({
      ...line, rejection_reason: line.rejection_reason || '', rejection_photos: line.rejection_photos || [],
      _catalog_item: line.catalog_item || null,
      _variant: line.variant || line.catalog_variant || null,
      qty_ordered: line.po_line?.qty_ordered || 0,
    }))
  },
})

const poReference = computed(() => {
  if (form.value.purchase_order?.reference) return form.value.purchase_order.reference
  
  const found = posList.value.find(p => p.id === form.value.po_id)
  
  return found?.reference || ''
})

const apiError = computed(() => grnStore.error || null)
const isLocked = computed(() => form.value.status === 'stocked')

// GRN status transitions: only draft → under_qc → confirmed (not stocked — stocked is set by inventory)
const allowedGrnStatuses = computed(() => {
  const s = form.value.status
  if (s === 'draft') return ['under_qc']
  if (s === 'under_qc') return ['confirmed', 'partially_rejected', 'fully_rejected']
  if (s === 'partially_rejected') return ['confirmed']

  return []
})

const showQcNotes = computed(() => ['under_qc', 'partially_rejected'].includes(form.value.status))

onMounted(async () => {
  await bootstrap()
  if (isNew.value) {
    if (usersList.value.length) form.value.received_by = usersList.value[0]?.id
    if (route.query.po_id) { form.value.po_id = route.query.po_id; await populateFromPo(form.value.po_id) }
    refreshSnapshot()
  }
})

// ── Catalog Picker for Ad-hoc Items ─────────────────────────────────────
const isCatalogPickerVisible = ref(false)

const handleCatalogPickerConfirm = (lines) => {
  form.value.lines.push(...lines)
}

// ── PO & Inventory Logic ──────────────────────────────────────────────
const populateFromPo = async poId => {
  if (!poId) return
  isLoading.value = true
  try {
    const po = await poStore.fetchDetails(poId)

    poSummary.value = po
    poSiteId.value = po.site_id
    form.value.supplier_id = po.supplier_id
    form.value.currency = po.currency || 'TND'
    await fetchLocationsForSite(po.site_id)
    form.value.lines = (po.lines || [])
      .filter(l => l.fulfillment_status !== 'fully_received' && l.fulfillment_status !== 'over_received')
      .map(line => ({
        po_line_id: line.id, catalog_item_id: line.catalog_item_id,
        catalog_variant_id: line.variant_id || line.catalog_variant_id,
        qty_received: (line.qty_ordered || 0) - (line.qty_received || 0),
        qty_accepted: (line.qty_ordered || 0) - (line.qty_received || 0),
        qty_rejected: 0, unit_cost_ht: line.unit_price_ht || line.unit_price || 0,
        qc_result: 'passed', storage_location_id: null,
        rejection_reason: '', rejection_photos: [],
        _catalog_item: line.catalog_item || null,
        _variant: line.variant || line.catalog_variant || null,
        qty_ordered: line.qty_ordered || 0,
      }))
  } catch (e) { snackbarStore.showMessage('Error auto-populating from PO', 'error') } finally { isLoading.value = false }
}

const fetchLocationsForPo = async poId => {
  try {
    const po = await poStore.fetchDetails(poId)

    poSummary.value = po
    if (po.site_id) { poSiteId.value = po.site_id; await fetchLocationsForSite(po.site_id) }
    else { const res = await inventoryStore.fetchLocations({ itemsPerPage: -1 })

      locationsList.value = res.data || [] }
  } catch (e) { console.error('Error fetching locations:', e) }
}

const fetchLocationsForSite = async siteId => {
  if (!siteId) { locationsList.value = []; zonesList.value = [] 

    return }
  try {
    const [locRes, zoneRes] = await Promise.all([
      inventoryStore.fetchLocations({ itemsPerPage: -1, 'filter[site_id]': siteId }),
      inventoryStore.fetchZones({ itemsPerPage: -1, 'filter[site_id]': siteId }),
    ])

    locationsList.value = locRes.data || []
    zonesList.value = zoneRes.data || []
  } catch (e) { console.error('Error fetching site locations:', e) }
}

const openMobileLineEdit = index => { const line = form.value.lines[index]; if (!line) return; editingMobileLineIndex.value = index; mobileLineDraft.value = { ...line }; isMobileLineSheetVisible.value = true }
const saveMobileLine = draft => { if (editingMobileLineIndex.value !== null) { form.value.lines.splice(editingMobileLineIndex.value, 1, draft) }; isMobileLineSheetVisible.value = false }

const acceptedCount = computed(() => form.value.lines.reduce((s, l) => s + (parseFloat(l.qty_accepted) || 0), 0))
const rejectedCount = computed(() => form.value.lines.reduce((s, l) => s + (parseFloat(l.qty_rejected) || 0), 0))
const matchKey = computed(() => form.value.supplier_delivery_note_ref || '—')
const statusColorMap = { draft: 'secondary', under_qc: 'warning', confirmed: 'success', partially_rejected: 'warning', fully_rejected: 'error', stocked: 'primary' }

const saveGrn = async () => {
  if (!form.value.po_id || !form.value.supplier_id || form.value.lines.length === 0) {
    showError(t('scp.grn.alerts.required_fields'))
    
    return
  }

  // Filter out lines with 0 received quantity
  const validLines = form.value.lines.filter(l => parseFloat(l.qty_received) > 0)
  if (validLines.length === 0 && isNew.value) {
    showError('Cannot create GRN: All lines have 0 quantity received.')
    
    return
  }

  isSubmitting.value = true
  try {
    const formData = new FormData()
    
    // Append standard fields
    const excludedFields = ['lines', 'delivery_on_time', 'days_late']
    Object.keys(form.value).forEach(key => {
      if (excludedFields.includes(key)) return
      if (key === 'supplier_delivery_note_path') {
        const file = Array.isArray(form.value[key]) ? form.value[key][0] : form.value[key]
        if (file instanceof File) {
          formData.append('delivery_note_file', file)
        }
      } else {
        const val = form.value[key]
        if (val !== null && val !== undefined) {
          formData.append(key, typeof val === 'boolean' ? (val ? 1 : 0) : val)
        }
      }
    })

    // Map lines and append correctly
    validLines.forEach((l, index) => {
      formData.append(`lines[${index}][po_line_id]`, l.po_line_id)
      if (l.id) formData.append(`lines[${index}][id]`, l.id)
      
      formData.append(`lines[${index}][is_ad_hoc]`, l.is_ad_hoc ? 1 : 0)
      if (l.is_ad_hoc) {
        formData.append(`lines[${index}][ad_hoc_name]`, l.ad_hoc_name || '')
        if (l.ad_hoc_sku) formData.append(`lines[${index}][ad_hoc_sku]`, l.ad_hoc_sku)
      } else {
        if (l.catalog_item_id) formData.append(`lines[${index}][catalog_item_id]`, l.catalog_item_id)
        if (l.catalog_variant_id) formData.append(`lines[${index}][catalog_variant_id]`, l.catalog_variant_id)
      }
      
      formData.append(`lines[${index}][qty_received]`, l.qty_received || 0)
      formData.append(`lines[${index}][qty_accepted]`, l.qty_accepted || 0)
      formData.append(`lines[${index}][qty_rejected]`, l.qty_rejected || 0)
      formData.append(`lines[${index}][unit_cost_ht]`, l.unit_cost_ht || 0)
      if (l.storage_location_id) formData.append(`lines[${index}][storage_location_id]`, l.storage_location_id)
      if (l.qc_result) formData.append(`lines[${index}][qc_result]`, l.qc_result)
      if (l.qc_notes) formData.append(`lines[${index}][qc_notes]`, l.qc_notes)
    })

    if (isNew.value) {
      await grnStore.create(formData)
      success(t('scp.grn.snackbar.created_success'))
      refreshSnapshot()
      router.push({ name: 'procurement-grn-list' })
    } else {
      formData.append('_method', 'PUT')
      await grnStore.update(grnId.value, formData)
      success(t('scp.grn.snackbar.updated_success'))
      refreshSnapshot()
      router.push({ name: 'procurement-grn-list' })
    }
  } catch (e) {
    showError(t('scp.grn.snackbar.error_save'))
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  try {
    await grnStore.deleteItem(grnId.value)
    success(t('scp.grn.snackbar.deleted_success'))
    refreshSnapshot()
    router.push({ name: 'procurement-grn-list' })
  } catch (err) {
    showError(t('scp.grn.snackbar.error_delete'))
  } finally {
    isDeleteDialogOpen.value = false
  }
}

const handleStatusSubmit = async data => {
  if (data.status === 'confirmed') {
    const missingLocation = form.value.lines.some(l => {
      const isStockable = !l._catalog_item || l._catalog_item.is_stockable !== false
      
      return isStockable && !l.storage_location_id
    })

    if (missingLocation) {
      showError(t('scp.grn.alerts.missing_location'))
      
      return
    }
  }

  isSubmitting.value = true
  try {
    if (isDirty.value) {
      await grnStore.update(grnId.value, form.value)
      refreshSnapshot()
    }
    await grnStore.updateStatus(grnId.value, data)
    success(t('scp.grn.snackbar.status_updated'))
    await bootstrap()
    isStatusModalVisible.value = false
  } catch (e) {
    showError(t('scp.grn.snackbar.error_status'))
  } finally {
    isSubmitting.value = false
  }
}

const resolvePoReference = computed(() => poSummary.value?.reference || '')

const resolveSupplierName = computed(() => { const s = suppliersList.value.find(s => s.id === form.value.supplier_id) 

  return s?.legal_name || '' })
</script>

<template>
  <StatusChangeModal
    v-model:is-dialog-visible="isStatusModalVisible"
    document-type="GRN"
    :document="form"
    :is-submitting="isSubmitting"
    @submit="handleStatusSubmit"
  />
  <ThreeWayMatchModal
    v-if="form.po_id"
    v-model:is-dialog-visible="isMatchModalVisible"
    :po-id="form.po_id"
    :po-reference="poReference"
  />
  <MobileLineSheet
    v-model="isMobileLineSheetVisible"
    document-type="GRN"
    mode="edit"
    :line-value="mobileLineDraft || {}"
    :locations="locationsList"
    :zones="zonesList"
    :is-read-only="isLocked"
    @save="saveMobileLine"
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
        :to="{ name: 'procurement-grn-list' }"
        :title="$t('GRN')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="isNew ? $t('New GRN') : (grnReference || $t('Loading...'))"
        disabled
      />
    </VBreadcrumbs>

    <WpPageHeader
      :title="isNew ? 'Nouveau GRN' : (grnReference || 'Loading...')"
      subtitle="Réception de marchandises et contrôle qualité."
      icon="tabler-clipboard-check"
    >
      <template #actions>
        <div
          v-if="!isNew && !isLoading"
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
            v-if="resolvePoReference"
            size="x-small"
            label
            variant="tonal"
            color="info"
            prepend-icon="tabler-link"
          >
            PO: {{ resolvePoReference }}
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
      :validation-errors="grnStore.validationErrors"
    />
    <VAlert
      v-if="isLocked && !isLoading"
      type="info"
      variant="tonal"
      class="mb-4"
      icon="tabler-lock"
    >
      This GRN is stocked and locked.
    </VAlert>
    <VSkeletonLoader
      v-if="isLoading"
      type="card, article, table-row-divider"
      class="mb-6 bg-transparent"
    />

    <VRow
      v-else
      class="mt-2"
    >
      <!-- Left: GRN Overview & Header -->
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
              v-if="form.po_id"
              color="info"
              variant="tonal"
              size="x-small"
              prepend-icon="tabler-link"
              class="font-weight-bold"
              :to="{ name: 'procurement-po-edit-id', params: { id: form.po_id } }"
            >
              PO #{{ resolvePoReference || form.po_id }}
            </VChip>
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1 flex-grow-1">
            <div class="d-flex flex-column ga-2">
              <div class="field-row">
                <span class="field-row__label">Reference</span>
                <span class="field-row__value font-weight-bold">{{ isNew ? 'Auto-generated' : grnReference }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Received Date</span>
                <span class="field-row__value">{{ form.received_at ? new Date(form.received_at).toLocaleDateString() : 'Not Received' }}</span>
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
                :disabled="isLocked"
                :loading="isSubmitting"
                block
                @click="saveGrn"
              >
                Enregistrer
              </VBtn>
              <VBtn
                v-if="!isNew && !isLocked && allowedGrnStatuses.length > 0"
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-arrows-left-right"
                block
                @click="isStatusModalVisible = true"
              >
                Changer Statut
              </VBtn>
              <VBtn
                v-if="!isNew && form.status === 'draft'"
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

        <!-- Delivery Header -->
        <VCard
          class="mb-4 neo-raised d-flex flex-column"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-truck-delivery"
              size="18"
              color="primary"
            /> Delivery Header
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <VRow dense>
              <VCol cols="12">
                <AppSelect
                  v-if="isNew"
                  v-model="form.po_id"
                  label="Source Purchase Order"
                  :items="posList.map(p=>({title:p.reference,value:p.id}))"
                  :disabled="isLocked"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                  @update:model-value="populateFromPo"
                />
                <AppTextField
                  v-else
                  :model-value="resolvePoReference || form.po_id"
                  label="Source Purchase Order"
                  readonly
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.supplier_id"
                  label="Supplier"
                  :items="suppliersList.map(s=>({title:s.legal_name,value:s.id}))"
                  disabled
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.supplier_delivery_note_ref"
                  label="Delivery Note Ref"
                  :disabled="isLocked"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  v-model="form.supplier_delivery_note_path"
                  label="Delivery Note File"
                  accept=".pdf,image/*"
                  prepend-inner-icon="tabler-paperclip"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                  show-size
                  chips
                  :disabled="isLocked"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.received_at"
                  type="date"
                  label="Received Date"
                  :disabled="isLocked"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppAutocomplete
                  v-model="form.received_by"
                  label="Received By"
                  :items="usersList.map(u=>({title:`${u.first_name} ${u.last_name}`,value:u.id}))"
                  :disabled="isLocked"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.is_partial_delivery"
                  label="Partial Delivery"
                  inset
                  :disabled="isLocked"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.delivery_on_time"
                  label="Delivered On Time"
                  inset
                  readonly
                />
              </VCol>
              <VCol
                v-if="!form.delivery_on_time"
                cols="12"
              >
                <AppTextField
                  v-model="form.days_late"
                  label="Days Late"
                  type="number"
                  min="0"
                  readonly
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Right: Tabs -->
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
              <span>{{ t('scp.grn.tabs.lines') }}</span>
            </VTab>
            <VTab value="qc">
              <VIcon
                icon="tabler-microscope"
                class="mb-2"
              />
              <span>{{ t('scp.grn.tabs.qc') }}</span>
            </VTab>
            <VTab
              value="rejected"
              :disabled="rejectedCount === 0"
            >
              <VIcon
                icon="tabler-alert-triangle"
                class="mb-2"
                :color="rejectedCount > 0 ? 'error' : ''"
              />
              <span :class="{ 'text-error font-weight-bold': rejectedCount > 0 }">
                {{ t('scp.grn.tabs.rejected') }}
                <VChip
                  v-if="rejectedCount > 0"
                  size="x-small"
                  color="error"
                  class="ms-1"
                >
                  {{ rejectedCount }}
                </VChip>
              </span>
            </VTab>
            <VTab
              v-if="form.po_id"
              value="match"
            >
              <VIcon
                icon="tabler-arrows-join-2"
                class="mb-2"
              />
              <span>{{ t('scp.grn.tabs.match') }}</span>
            </VTab>
          </VTabs>
          <VDivider v-if="display.mdAndUp.value" />
          <VWindow v-model="activeTab">
            <VWindowItem value="lines">
              <GrnLinesRepeater
                v-model:lines="form.lines"
                :locations="locationsList"
                :zones="zonesList"
                :is-locked="isLocked"
                @add-adhoc-items="isCatalogPickerVisible = true"
              />
            </VWindowItem>
            <VWindowItem value="rejected">
              <GrnRejectedItemsTab :lines="form.lines" />
            </VWindowItem>
            <VWindowItem value="qc">
              <div class="pa-5">
                <VAlert
                  v-if="showQcNotes"
                  type="warning"
                  variant="tonal"
                  class="mb-4"
                  icon="tabler-alert-triangle"
                >
                  This GRN is currently <strong>{{ form.status.replace(/_/g, ' ') }}</strong>. Review QC findings before transitioning.
                </VAlert>
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="form.overall_qc_result"
                      label="Overall Result"
                      :items="['passed', 'failed', 'partial']"
                      :disabled="isLocked"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppAutocomplete
                      v-model="form.qc_performed_by"
                      label="QC Performed By"
                      :items="usersList.map(u=>({title:`${u.first_name} ${u.last_name}`,value:u.id}))"
                      :disabled="isLocked"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppDateTimePicker
                      v-model="form.qc_performed_at"
                      label="QC Performed At"
                      :disabled="isLocked"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="form.internal_notes"
                      label="QC Findings & Notes"
                      rows="4"
                      :readonly="isLocked"
                      placeholder="Document quality control findings..."
                    />
                  </VCol>
                </VRow>
              </div>
            </VWindowItem>
            <VWindowItem
              v-if="form.po_id"
              value="match"
            >
              <div class="pa-5">
                <div class="d-flex align-center ga-4 mb-4">
                  <VAvatar
                    rounded
                    size="48"
                    color="primary"
                    variant="tonal"
                  >
                    <VIcon
                      icon="tabler-arrows-join-2"
                      size="24"
                    />
                  </VAvatar>
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">
                      3-Way Match
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Connect PO → GRN → Invoice using the supplier delivery note reference.
                    </div>
                    <div class="d-flex ga-2 mt-2">
                      <VChip
                        label
                        size="small"
                        color="info"
                        variant="tonal"
                        prepend-icon="tabler-hash"
                      >
                        Key: {{ matchKey }}
                      </VChip>
                      <VChip
                        v-if="poSummary?.reference"
                        label
                        size="small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="tabler-link"
                      >
                        PO: {{ poSummary.reference }}
                      </VChip>
                    </div>
                  </div>
                </div>
                <div class="d-flex ga-2">
                  <VBtn
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-external-link"
                    :to="{ name: 'procurement-match-id', params: { id: form.po_id } }"
                  >
                    Open Match Page
                  </VBtn>
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-bolt"
                    @click="isMatchModalVisible = true"
                  >
                    Execute Match
                  </VBtn>
                </div>
              </div>
            </VWindowItem>
          </VWindow>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-if="display.mdAndUp.value && !isLoading"
      class="financial-bottom-bar"
    >
      <div
        class="d-flex align-center justify-end ga-6 px-6 py-3 mx-auto"
        style="max-inline-size: 1400px;"
      >
        <div class="d-flex align-center ga-2 text-success">
          <VIcon
            icon="tabler-check"
            size="20"
          /><span class="text-caption text-uppercase font-weight-bold text-success">Accepted</span><span class="text-h6 font-weight-bold">{{ acceptedCount }} units</span>
        </div>
        <VDivider
          vertical
          class="my-1"
        />
        <div class="d-flex align-center ga-2 text-error">
          <VIcon
            icon="tabler-x"
            size="20"
          /><span class="text-caption text-uppercase font-weight-bold text-error">Rejected</span><span class="text-body-1 font-weight-bold">{{ rejectedCount }} units</span>
        </div>
      </div>
    </div>

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
          value="qc"
          variant="text"
        >
          <VIcon>tabler-microscope</VIcon>
          QC
        </VBtn>
        <VBtn
          v-if="rejectedCount > 0"
          value="rejected"
          variant="text"
        >
          <VIcon>tabler-alert-triangle</VIcon>
          Rejected
        </VBtn>
        <VBtn
          v-if="form.po_id"
          value="match"
          variant="text"
        >
          <VIcon>tabler-arrows-join-2</VIcon>
          Match
        </VBtn>
        <VBtn
          value="save"
          variant="text"
          :disabled="isLocked || isSubmitting"
          @click="saveGrn"
        >
          <VIcon>tabler-device-floppy</VIcon>
          Save
        </VBtn>
        <VBtn
          v-if="!isNew && !isLocked && allowedGrnStatuses.length > 0"
          value="status"
          variant="text"
          @click="isStatusModalVisible = true"
        >
          <VIcon>tabler-arrows-left-right</VIcon>
          Status
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
    <CatalogPickerModal
      v-model:is-dialog-visible="isCatalogPickerVisible"
      document-type="grn"
      @confirm="handleCatalogPickerConfirm"
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

@media (min-width: 960px) { .edit-page-container { padding-block-end: 120px; } }
</style>
