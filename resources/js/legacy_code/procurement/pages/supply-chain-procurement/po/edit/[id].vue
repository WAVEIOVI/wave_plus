<script setup>
/* eslint-disable camelcase */
import { MobileLineSheet, StatusChangeModal, WpConfirmDeleteDialog, WpErrorBanner, WpPageHeader, WpUnsavedChangesDialog } from '@/components/shared'
import { useDocumentEdit } from '@/composables/use-document-edit'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useIprStore } from '@/stores/useIprStore'
import { usePoStore } from '@/stores/usePoStore'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useUserStore } from '@/stores/useUserStore'
import CatalogSearchModal from '@/views/supply-chain-procurement/ipr/edit/CatalogSearchModal.vue'
import VariantSelectModal from '@/views/supply-chain-procurement/ipr/edit/VariantSelectModal.vue'
import SupplierSearchModal from '@/views/supply-chain-procurement/ipr/SupplierSearchModal.vue'
import ThreeWayMatchModal from '@/views/supply-chain-procurement/po/match/ThreeWayMatchModal.vue'
import CatalogPickerModal from '@/views/supply-chain-procurement/shared/CatalogPickerModal.vue'
import DocumentLinesRepeater from '@/views/supply-chain-procurement/shared/DocumentLinesRepeater.vue'
import SourceDocumentImporter from '@/views/supply-chain-procurement/shared/SourceDocumentImporter.vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  name: 'procurement-po-edit-id',
  meta: { layout: 'default', navActiveLink: 'procurement-po-list', module: 'supply-chain-procurement', action: 'read', subject: 'SupplyChainProcurement' },
})

const route = useRoute()
const router = useRouter()
const poStore = usePoStore()
const catalogStore = useCatalogItemStore()
const supplierStore = useSupplierStore()
const userStore = useUserStore()
const inventoryStore = useInventoryStore()
const rfqStore = useRfqStore()
const iprStore = useIprStore()
const snackbarStore = useSnackbarStore()
const display = useDisplay()

const poId = computed(() => route.params.id)
const isCreate = computed(() => poId.value === 'new')
const isSubmitting = ref(false)
const poReference = ref(null)
const activeTab = ref('lines')
const showMobileSummary = ref(false)
const isMobileToolsOpen = ref(false)

const form = ref({
  creation_path: 'manual', supplier_id: null, supplier_contact_id: null, supplier_address_id: null,
  site_id: null, currency: 'TND', payment_terms: 'immediate', delivery_address: '',
  committed_delivery_date: null, notes_to_supplier: '', internal_notes: '', status: 'draft',
  sent_at: null, acknowledged_at: null, supplier_po_reference: null, ipr_ids: [], rfq_id: null, lines: [],
})

const paymentTermsOptions = [
  { title: 'Immediate', value: 'immediate' },
  { title: 'Net 15', value: 'net_15' },
  { title: 'Net 30', value: 'net_30' },
  { title: 'Net 60', value: 'net_60' },
  { title: 'Net 90', value: 'net_90' },
  { title: '100% Advance', value: 'advance_100' },
]

const currencyOptions = ['TND', 'EUR', 'USD', 'GBP']

const usersList = ref([])
const suppliersList = ref([])
const sitesList = ref([])
const supplierContacts = ref([])
const supplierAddresses = ref([])

const fetchSupplierRelations = async supplierId => {
  if (!supplierId) return
  try {
    const [contacts, addresses] = await Promise.all([
      supplierStore.fetchSupplierContacts(supplierId),
      supplierStore.fetchSupplierAddresses(supplierId),
    ])

    supplierContacts.value = contacts?.data || []
    supplierAddresses.value = addresses?.data || []
  } catch (e) { console.error('Failed to load supplier relations', e) }
}

const {
  isLoading, isDirty, subtotal, taxAmount, grandTotal,
  bootstrap, refreshSnapshot, unsavedDialogVisible, confirmLeave, cancelLeave,
} = useDocumentEdit({
  form, id: route.params.id, isCreate: isCreate.value,
  fetchDocument: id => poStore.fetchDetails(id),
  lines: computed(() => form.value.lines), isSubmitting,
  qtyKey: 'qty_ordered', priceKey: 'unit_price', taxKey: 'tax_rate',
  onRelationsFetch: async () => {
    await Promise.all([
      userStore.fetchUsers({ itemsPerPage: -1 }).then(res => { usersList.value = Array.isArray(res) ? res : (res?.data || []) }),
      supplierStore.fetchSuppliers({ itemsPerPage: -1 }).then(res => { suppliersList.value = res?.data || [] }),
      inventoryStore.fetchSites({ itemsPerPage: -1 }).then(res => { sitesList.value = res?.data || [] }),
    ])
  },
  onAfterFetch: async data => {
    poReference.value = data.reference
    form.value.supplier_contact_id = data.supplier_contact_id
    form.value.supplier_address_id = data.supplier_address_id
    form.value.sent_at = data.sent_at
    form.value.acknowledged_at = data.acknowledged_at
    form.value.supplier_po_reference = data.supplier_po_reference
    form.value.ipr_ids = data.ipr_ids || []
    form.value.rfq_id = data.rfq_id || null
    if (data.supplier_id) await fetchSupplierRelations(data.supplier_id)
    form.value.lines = (data.lines || []).map(line => ({
      ...line, qty_ordered: line.qty_ordered || 0,
      unit_price: line.unit_price_ht || line.unit_price || 0,
      tax_rate: line.tax_rate_pct || line.tax_rate || 19,
      tax_rate_pct: line.tax_rate_pct || line.tax_rate || 19,
      _catalog_item: line.catalog_item || null, _variant: line.variant || null,
    }))
  },
})

const apiError = computed(() => poStore.error || null)

onMounted(async () => {
  await bootstrap()
  if (isCreate.value) {
    const creationPath = route.query.path || 'manual'

    form.value.creation_path = creationPath

    const sourceIprIds = (route.query.ipr_ids || '').split(',').filter(Boolean)
    if (creationPath === 'from_ipr' && sourceIprIds.length > 0) {
      form.value.ipr_ids = sourceIprIds
      for (const iprId of sourceIprIds) {
        try {
          const ipr = await iprStore.fetchDetails(iprId)
          if (ipr?.lines) {
            ipr.lines.forEach(l => {
              form.value.lines.push({
                catalog_item_id: l.catalog_item_id, variant_id: l.variant_id,
                is_ad_hoc: l.is_ad_hoc, ad_hoc_name: l.ad_hoc_name, ad_hoc_sku: l.ad_hoc_sku,
                qty_ordered: l.qty_requested, unit_price: l.estimated_unit_cost || 0, tax_rate: 19,
                _catalog_item: l.catalog_item, _variant: l.variant,
              })
            })
          }
        } catch (e) { console.error('IPR Import failed', e) }
      }
    }
    const sourceRfqId = route.query.rfq_id
    if (creationPath === 'from_rfq' && sourceRfqId) {
      form.value.rfq_id = sourceRfqId
      try {
        const rfq = await rfqStore.fetchDetails(sourceRfqId)
        if (rfq?.awarded_to_supplier_id) {
          form.value.supplier_id = rfq.awarded_to_supplier_id

          const awardedResponse = rfq.responses?.find(r => r.supplier_id == rfq.awarded_to_supplier_id && r.is_awarded)
          if (awardedResponse?.lines) {
            form.value.lines = awardedResponse.lines.map(rl => ({
              catalog_item_id: rl.rfq_line?.catalog_item_id, variant_id: rl.rfq_line?.variant_id,
              is_ad_hoc: rl.rfq_line?.is_ad_hoc, ad_hoc_name: rl.rfq_line?.ad_hoc_name,
              qty_ordered: rl.offered_qty || rl.rfq_line?.qty_requested,
              unit_price: rl.offered_unit_price_ht || 0, tax_rate: 19,
              _catalog_item: rl.rfq_line?.catalog_item, _variant: rl.rfq_line?.variant,
            }))
          }
        }
      } catch (e) { console.error('RFQ Import failed', e) }
    }
    refreshSnapshot()
  }
})

const isReadOnly = computed(() => { if (isCreate.value) return false 

  return !['draft', 'pending_approval'].includes(form.value.status) })

// ── Modals ──────────────────────────────────────────────────────────────
const isCatalogModalVisible = ref(false)
const isCatalogPickerVisible = ref(false)
const isVariantModalVisible = ref(false)
const isStatusModalVisible = ref(false)
const isSupplierModalVisible = ref(false)
const isImporterVisible = ref(false)
const isDeleteDialogOpen = ref(false)
const isMatchModalVisible = ref(false)
const selectedCatalogItemForVariant = ref(null)
const isMobileLineSheetVisible = ref(false)
const mobileLineSheetMode = ref('create')
const editingMobileLineIndex = ref(null)
const mobileLineDraft = ref(null)

// ── Line Helpers ────────────────────────────────────────────────────────
const addCatalogLine = () => { isCatalogModalVisible.value = true }

const handleCatalogPickerConfirm = lines => {
  form.value.lines.push(...lines.map(l => ({
    ...l,
    tax_rate: l.tax_rate_pct ?? l.tax_rate ?? 19,
  })))
}

const handleCatalogItemSelected = async item => {
  try {
    const fullItem = await catalogStore.fetchItem(item.id)
    const variants = fullItem.variants || []
    if (variants.length === 1) { pushCatalogLine(fullItem, variants[0]) 

      return }
    if (variants.length > 1) { selectedCatalogItemForVariant.value = fullItem; isVariantModalVisible.value = true 

      return }
    pushCatalogLine(fullItem, null)
  } catch (error) { snackbarStore.showMessage('Failed to load item variants.', 'error') }
}

const handleVariantSelected = ({ item, variant }) => { pushCatalogLine(item, variant) }

const pushCatalogLine = (catalogItem, variant = null) => {
  const lineData = { catalog_item_id: catalogItem.id, variant_id: variant?.id || null, is_ad_hoc: false, ad_hoc_name: '', ad_hoc_sku: 'NEW', qty_ordered: 1, unit_price: variant?.price ?? catalogItem.cost_price ?? 0, tax_rate: 19, _catalog_item: catalogItem, _variant: variant }
  if (isMobileLineSheetVisible.value && mobileLineDraft.value) { mobileLineDraft.value = { ...mobileLineDraft.value, ...lineData } } else { form.value.lines.push(lineData) }
}

const addAdHocLine = () => { form.value.lines.push({ catalog_item_id: null, variant_id: null, is_ad_hoc: true, ad_hoc_name: '', ad_hoc_sku: 'NEW', qty_ordered: 1, unit_price: 0, tax_rate: 19, _catalog_item: null, _variant: null }) }
const handleSupplierSelected = async supplier => { form.value.supplier_id = supplier.id; form.value._supplier = supplier; form.value.supplier_contact_id = null; form.value.supplier_address_id = null; await fetchSupplierRelations(supplier.id) }

const importFromDocument = data => {
  if (data.sourceId) {
    if (data.sourceType === 'rfq' && !form.value.rfq_id) {
      form.value.rfq_id = data.sourceId
    } else if (data.sourceType === 'ipr' && !form.value.ipr_ids.includes(data.sourceId)) {
      form.value.ipr_ids.push(data.sourceId)
    }
  }
  if (data.sourceType === 'rfq' && data.awardedSupplierId) {
    form.value.supplier_id = data.awardedSupplierId
    fetchSupplierRelations(data.awardedSupplierId)
  }
  ;(data.lines || []).forEach(l => {
    form.value.lines.push({
      catalog_item_id: l.catalog_item_id,
      variant_id: l.variant_id,
      is_ad_hoc: l.is_ad_hoc,
      ad_hoc_name: l.ad_hoc_name,
      ad_hoc_sku: l.ad_hoc_sku,
      qty_ordered: l.qty,
      unit_price: l.unit_cost,
      tax_rate: 19,
      _catalog_item: l.catalog_item || null,
      _variant: l.variant || null,
    })
  })
  snackbarStore.showMessage(`Imported ${(data.lines || []).length} lines from ${data.sourceReference}`, 'success')
}

const onSiteChange = siteId => {
  if (!siteId) return
  const site = sitesList.value.find(s => s.id === siteId)
  if (site) { const fullAddress = [localizedValue(site.address), site.city, site.country].filter(Boolean).join(', '); if (fullAddress) form.value.delivery_address = fullAddress }
}

const openMobileLineEdit = index => { const line = form.value.lines[index]; if (!line) return; mobileLineSheetMode.value = 'edit'; editingMobileLineIndex.value = index; mobileLineDraft.value = { ...line }; isMobileLineSheetVisible.value = true }
const saveMobileLine = draft => { if (mobileLineSheetMode.value === 'edit' && editingMobileLineIndex.value !== null) { form.value.lines.splice(editingMobileLineIndex.value, 1, draft) } else { form.value.lines.push(draft) }; isMobileLineSheetVisible.value = false }

// ── Actions ─────────────────────────────────────────────────────────────
const savePo = async () => {
  if (!form.value.supplier_id) { snackbarStore.showMessage('Supplier is required', 'error') 

    return }
  if (form.value.lines.length === 0) { snackbarStore.showMessage('At least one line is required.', 'error') 

    return }
  isSubmitting.value = true
  try {
    const payload = { ...form.value, lines: form.value.lines.map(l => ({ id: l.id, catalog_item_id: l.catalog_item_id, variant_id: l.variant_id, is_ad_hoc: l.is_ad_hoc, ad_hoc_name: l.ad_hoc_name, ad_hoc_sku: l.ad_hoc_sku, description_snapshot: l.description_snapshot || '', qty_ordered: l.qty_ordered, unit_price_ht: l.unit_price, tax_rate_pct: l.tax_rate_pct ?? l.tax_rate ?? 19 })) }
    if (isCreate.value) { await poStore.create(payload); snackbarStore.showMessage('Purchase Order created successfully', 'success'); refreshSnapshot(); router.push({ name: 'procurement-po-list' }) }
    else { await poStore.update(poId.value, payload); snackbarStore.showMessage('Purchase Order updated successfully', 'success'); refreshSnapshot(); router.push({ name: 'procurement-po-list' }) }
  } catch (error) { snackbarStore.showMessage('Failed to save PO. Please check inputs.', 'error') } finally { isSubmitting.value = false }
}

const handleDelete = async () => { try { await poStore.deleteItem(poId.value); snackbarStore.showMessage('PO deleted successfully', 'success'); refreshSnapshot(); router.push({ name: 'procurement-po-list' }) } catch (err) { snackbarStore.showMessage('Failed to delete PO', 'error') } finally { isDeleteDialogOpen.value = false } }
const handleStatusSubmit = async data => { isSubmitting.value = true; try { await poStore.updateStatus(poId.value, data); snackbarStore.showMessage('Status updated successfully', 'success'); await bootstrap(); isStatusModalVisible.value = false } catch (error) { snackbarStore.showMessage('Failed to update status', 'error') } finally { isSubmitting.value = false } }

const statusColorMap = { draft: 'secondary', pending_approval: 'warning', approved: 'success', rejected: 'error', sent: 'info', acknowledged: 'primary', in_delivery: 'warning', partially_received: 'primary', fully_received: 'success', closed: 'secondary', cancelled: 'default' }
const computedSuppliers = computed(() => suppliersList.value.map(s => ({ title: `${s.supplier_id} — ${s.legal_name || localizedValue(s.commercial_name)}`, value: s.id })))
const computedSites = computed(() => sitesList.value.map(s => ({ title: `${s.code} — ${localizedValue(s.name)}`, value: s.id })))
const computedContacts = computed(() => supplierContacts.value.map(c => ({ title: `${localizedValue(c.first_name)} ${localizedValue(c.last_name)}` + (c.title ? ` (${c.title})` : ''), value: c.id })))
const computedAddresses = computed(() => supplierAddresses.value.map(a => ({ title: [localizedValue(a.address_street), localizedValue(a.city)].filter(Boolean).join(', ') || 'Address ID: ' + a.id, value: a.id })))

const resolveSupplierName = computed(() => { const s = suppliersList.value.find(s => s.id === form.value.supplier_id) 

  return s ? (localizedValue(s.commercial_name) || s.legal_name) : '' })
</script>

<template>
  <CatalogSearchModal
    v-model:is-dialog-visible="isCatalogModalVisible"
    @select="handleCatalogItemSelected"
  />
  <CatalogPickerModal
    v-model:is-dialog-visible="isCatalogPickerVisible"
    document-type="po"
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
    document-type="PO"
    :document="form"
    :is-submitting="isSubmitting"
    @submit="handleStatusSubmit"
  />
  <ThreeWayMatchModal
    v-if="!isCreate"
    v-model:is-dialog-visible="isMatchModalVisible"
    :po-id="poId"
    :po-reference="poReference"
    @matched="bootstrap"
  />
  <SourceDocumentImporter
    v-model:is-dialog-visible="isImporterVisible"
    :allowed-sources="['ipr', 'rfq']"
    target-label="PO Lines"
    @import="importFromDocument"
  />
  <MobileLineSheet
    v-model="isMobileLineSheetVisible"
    document-type="PO"
    :mode="mobileLineSheetMode"
    :line-value="mobileLineDraft || {}"
    :catalog-items="catalogStore.items"
    :suppliers="suppliersList"
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
        :to="{ name: 'procurement-po-list' }"
        :title="$t('Purchase Orders')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="isCreate ? $t('New PO') : (poReference || $t('Loading...'))"
        disabled
      />
    </VBreadcrumbs>

    <WpPageHeader
      :title="isCreate ? 'Nouveau PO' : (poReference || 'Loading...')"
      subtitle="Gérer le bon de commande fournisseur."
      icon="tabler-shopping-cart"
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
            v-if="resolveSupplierName"
            size="x-small"
            label
            variant="tonal"
            color="primary"
          >
            {{ resolveSupplierName }}
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
      :validation-errors="poStore.validationErrors"
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
              v-if="form.rfq_id"
              color="info"
              variant="tonal"
              size="x-small"
              prepend-icon="tabler-link"
              class="font-weight-bold"
              :to="{ name: 'procurement-rfq-edit-id', params: { id: form.rfq_id } }"
            >
              RFQ #{{ form.rfq_id }}
            </VChip>
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1 flex-grow-1">
            <div class="d-flex flex-column ga-2">
              <div class="field-row">
                <span class="field-row__label">Reference</span>
                <span class="field-row__value font-weight-bold">{{ isCreate ? 'Auto-generated' : poReference }}</span>
              </div>
              <div class="field-row">
                <span class="field-row__label">Delivery Date</span>
                <span class="field-row__value">{{ form.committed_delivery_date ? new Date(form.committed_delivery_date).toLocaleDateString() : 'Not Set' }}</span>
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
                @click="savePo"
              >
                Enregistrer
              </VBtn>
              <VBtn
                v-if="!isCreate && ['sent', 'confirmed', 'acknowledged'].includes(form.status)"
                color="success"
                variant="tonal"
                prepend-icon="tabler-package-import"
                block
                :to="{ name: 'procurement-grn-edit-id', params: { id: 'new' }, query: { po_id: poId } }"
              >
                Créer GRN
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

        <!-- Supplier & Shipping -->
        <VCard
          class="mb-4 neo-raised d-flex flex-column"
          flat
        >
          <VCardTitle class="px-5 pt-4 pb-2 text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <VIcon
              icon="tabler-building-store"
              size="18"
              color="primary"
            /> Supplier & Shipping
          </VCardTitle>
          <VCardText class="px-5 pb-4 pt-1">
            <VRow dense>
              <VCol cols="12">
                <AppTextField
                  :model-value="computedSuppliers.find(s => s.value === form.supplier_id)?.title || ''"
                  label="Supplier"
                  placeholder="Click to select..."
                  readonly
                  class="cursor-pointer"
                  append-inner-icon="tabler-search"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                  @click="isReadOnly ? null : isSupplierModalVisible = true"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.supplier_contact_id"
                  :items="computedContacts"
                  label="Contact"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.supplier_address_id"
                  :items="computedAddresses"
                  label="Address"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppAutocomplete
                  v-model="form.site_id"
                  :items="computedSites"
                  label="Delivery Site"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                  @update:model-value="onSiteChange"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.supplier_po_reference"
                  label="Supplier PO Ref"
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
                  v-model="form.currency"
                  :items="currencyOptions"
                  label="Currency"
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
                  v-model="form.payment_terms"
                  :items="paymentTermsOptions"
                  label="Payment Terms"
                  :readonly="isReadOnly"
                  variant="solo-filled"
                  flat
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <AppDateTimePicker
                  v-model="form.committed_delivery_date"
                  label="Committed Delivery"
                  :readonly="isReadOnly"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

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
            <VTab value="delivery">
              <VIcon
                icon="tabler-truck-delivery"
                class="mb-2"
              />
              <span>Delivery & Notes</span>
            </VTab>
            <VTab
              v-if="!isCreate"
              value="match"
            >
              <VIcon
                icon="tabler-arrows-join-2"
                class="mb-2"
              />
              <span>3-Way Match</span>
            </VTab>
          </VTabs>
          <VDivider v-if="display.mdAndUp.value" />
          <VWindow v-model="activeTab">
            <VWindowItem value="lines">
              <DocumentLinesRepeater
                v-model:lines="form.lines"
                document-type="PO"
                :catalog-items="catalogStore.items"
                :suppliers="suppliersList"
                :is-read-only="isReadOnly"
                @add-catalog-line="addCatalogLine"
                @add-adhoc-line="addAdHocLine"
                @import-lines="isImporterVisible = true"
                @edit-mobile-line="openMobileLineEdit"
              />
              <div
                v-if="!isReadOnly"
                class="pa-4 d-flex ga-2"
              >
                <VBtn
                  color="primary"
                  variant="outlined"
                  class="flex-grow-1"
                  prepend-icon="tabler-grid-3x3"
                  @click="isCatalogPickerVisible = true"
                >
                  Multi-Select
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="outlined"
                  class="flex-grow-1"
                  prepend-icon="tabler-database-import"
                  @click="isImporterVisible = true"
                >
                  Import Source
                </VBtn>
              </div>
            </VWindowItem>
            <VWindowItem value="delivery">
              <div class="pa-5">
                <VRow>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="form.delivery_address"
                      label="Full Delivery Address"
                      rows="2"
                      :readonly="isReadOnly"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="form.notes_to_supplier"
                      label="Notes to Supplier"
                      rows="2"
                      :readonly="isReadOnly"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="form.internal_notes"
                      label="Internal Notes"
                      rows="2"
                      :readonly="isReadOnly"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppDateTimePicker
                      v-model="form.sent_at"
                      label="Sent At"
                      :readonly="isReadOnly"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppDateTimePicker
                      v-model="form.acknowledged_at"
                      label="Acknowledged At"
                      :readonly="isReadOnly"
                    />
                  </VCol>
                </VRow>
              </div>
            </VWindowItem>
            <VWindowItem
              v-if="!isCreate"
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
                    </div><div class="text-caption text-medium-emphasis">
                      Validate PO → GRN → Invoice alignment
                    </div>
                  </div>
                </div>
                <div class="d-flex ga-2">
                  <VBtn
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-external-link"
                    :to="{ name: 'procurement-match-id', params: { id: poId } }"
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
        <div class="d-flex align-center ga-2">
          <span class="text-caption text-uppercase font-weight-bold text-disabled">Total HT</span><span class="text-body-1 font-weight-bold">{{ subtotal.toFixed(3) }}</span>
        </div>
        <VDivider
          vertical
          class="my-1"
        />
        <div class="d-flex align-center ga-2">
          <span class="text-caption text-uppercase font-weight-bold text-disabled">TVA</span><span class="text-body-2">{{ taxAmount.toFixed(3) }}</span>
        </div>
        <VDivider
          vertical
          class="my-1"
        />
        <div class="d-flex align-center ga-2">
          <span class="text-caption text-uppercase font-weight-bold text-disabled">TTC</span><span class="text-h6 font-weight-bold text-primary">{{ grandTotal.toFixed(3) }} {{ form.currency }}</span>
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
          value="delivery"
          variant="text"
        >
          <VIcon>tabler-truck-delivery</VIcon>
          Delivery
        </VBtn>
        <VBtn
          value="save"
          variant="text"
          :disabled="!isDirty || isReadOnly || isSubmitting"
          @click="savePo"
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

@media (min-width: 960px) { .edit-page-container { padding-block-end: 120px; } }
</style>


