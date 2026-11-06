<script setup>
/* eslint-disable camelcase */
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpDocumentDialog,
  WpErrorBanner,
  WpImageDialog,
  WpKpiCard,
  WpKpiGroup,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import { useSiteAreas } from '@/composables/client-assets/sites/useSiteAreas'
import {
  documentCategories,
  operatingStatusOptions,
  resolveSiteStatusVariant,
  riskClassOptions,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import { useSiteEdit } from '@/composables/client-assets/sites/useSiteEdit'
import { useSiteEquipment } from '@/composables/client-assets/sites/useSiteEquipment'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import ViewAddressDialog from '@/views/business-partners/shared-components/dialogs/ViewAddressDialog.vue'
import EquipmentWizardDialog from '@/views/client-assets/equipment/dialogs/EquipmentWizardDialog.vue'
import EquipmentViewModal from '@/views/client-assets/equipment/EquipmentViewModal.vue'
import AreaManageDialog from '@/views/client-assets/sites/edit/AreaManageDialog.vue'
import SiteAreasSection from '@/views/client-assets/sites/edit/SiteAreasSection.vue'
import SiteClientAddressSection from '@/views/client-assets/sites/edit/SiteClientAddressSection.vue'
import SiteEquipmentSection from '@/views/client-assets/sites/edit/SiteEquipmentSection.vue'
import SiteEquipmentZoomDialog from '@/views/client-assets/sites/edit/SiteEquipmentZoomDialog.vue'
import SiteHistorySection from '@/views/client-assets/sites/edit/SiteHistorySection.vue'
import SiteInstructionsSection from '@/views/client-assets/sites/edit/SiteInstructionsSection.vue'
import SiteMediaSection from '@/views/client-assets/sites/edit/SiteMediaSection.vue'
import SiteNavSidebar from '@/views/client-assets/sites/edit/SiteNavSidebar.vue'
import SiteParametersSection from '@/views/client-assets/sites/edit/SiteParametersSection.vue'
import SiteProfileSection from '@/views/client-assets/sites/edit/SiteProfileSection.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/client-assets/sites/edit/:id',
  name: 'client-assets-sites-edit-id',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})

const route = useRoute()
const targetSiteId = computed(() => route.params.id)
const store = useClientAssetStore()
const clientStore = useClientStore()
const ability = useAbility()
const { t } = useI18n()
const { success: showSuccess, error: showError, warning: showWarning, showApiError, showMessage } = useAppSnackbar()
const { mdAndDown, smAndDown } = useDisplay()

// ── Permissions & Read-only State ───────────────────────────────────────────
const isReadOnly = computed(() => !ability.can('update', 'ClientAssets'))
const canDelete = computed(() => ability.can('delete', 'ClientAssets'))

// ── Composables ──────────────────────────────────────────────────────────────
const {
  sections,
  mobileNavItems,
  optionTitle,
  resolveStatusVariant,
  resolveRiskVariant,
} = useSiteConstants()

const {
  loading,
  saving,
  pageError,
  fetchedSite,
  form,
  isDirty,
  allSiteDocuments,
  fetchSite,
  handleSave,
  handleDelete,
} = useSiteEdit(targetSiteId)

const {
  loadingAreas,
  siteAreasFlat,
  areasViewMode,
  currentDrillAreaId,
  expandedAreaIds,
  flattenedHierarchy,
  currentCardsAreas,
  cardsBreadcrumbs,
  fetchAreas,
  toggleAreaExpand,
  expandAllAreas,
  collapseAllAreas,
} = useSiteAreas(targetSiteId)

const {
  siteEquipment,
  equipmentViewMode,
  equipmentKPIs,
  fetchEquipment,
} = useSiteEquipment(targetSiteId)

// ── Navigation & Breadcrumbs ─────────────────────────────────────────────────
const activeSection = ref('bio')
const quickActionsOpen = ref(false)

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Client Assets'), to: '/client-assets/sites/list', icon: 'tabler-building-estate' },
  { title: t('Sites'), to: '/client-assets/sites/list', icon: 'tabler-map-pin' },
  {
    title: form.value.name || fetchedSite.value?.site_code || t('Edit Site'),
    icon: 'tabler-building',
    disabled: true,
  },
])

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Unsaved Changes Guard ────────────────────────────────────────────────────
const {
  isDialogVisible: isUnsavedChangesDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Dialog States ────────────────────────────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const isAreaDialogOpen = ref(false)
const selectedAreaForEdit = ref(null)
const selectedParentAreaId = ref(null)

const isEquipmentViewModalOpen = ref(false)
const selectedViewEquipmentId = ref(null)
const selectedEditEquipmentId = ref(null)
const isAddEquipmentDialogOpen = ref(false)
const isEquipmentFullscreenOpen = ref(false)
const selectedLocationAreaId = ref(null)

const isClientViewDialogOpen = ref(false)
const selectedClientForView = ref(null)
const loadingClientView = ref(false)
const isAddressViewDialogOpen = ref(false)

const isImageDialogOpen = ref(false)
const isUploadDocDialogOpen = ref(false)
const uploadingDocument = ref(false)

const isMediaViewerOpen = ref(false)
const mediaViewerItems = ref([])
const mediaViewerInitialIndex = ref(0)
const mediaViewerTitle = ref('')

// ── Contextual Dialog Openers ────────────────────────────────────────────────
const openAreaDialog = (area = null, parentId = null) => {
  selectedAreaForEdit.value = area
  selectedParentAreaId.value = parentId
  isAreaDialogOpen.value = true
}

const openAddEquipmentContextual = (areaId = null) => {
  if (areaId) selectedLocationAreaId.value = areaId
  selectedEditEquipmentId.value = null
  isAddEquipmentDialogOpen.value = true
}

const handleViewEquipment = eqId => {
  selectedViewEquipmentId.value = eqId
  isEquipmentViewModalOpen.value = true
}

const handleEditEquipment = eqId => {
  selectedEditEquipmentId.value = eqId
  isAddEquipmentDialogOpen.value = true
}

const handleViewClient = async () => {
  const clientId = fetchedSite.value?.client_id || fetchedSite.value?.client?.id
  if (!clientId) return

  loadingClientView.value = true
  isClientViewDialogOpen.value = true
  try {
    const data = await clientStore.fetchClient(clientId)

    selectedClientForView.value = data
  }
  catch (err) {
    console.warn('Failed to load full client for dialog:', err?.message)
    selectedClientForView.value = fetchedSite.value?.client
  }
  finally {
    loadingClientView.value = false
  }
}

// ── Delete Confirmation Logic ────────────────────────────────────────────────
const isDeleteItemDialogOpen = ref(false)
const pendingDelete = ref(null)

const deleteDialogCopy = computed(() => ({
  area: { title: t('Delete Area'), description: t('Are you sure you want to delete this area?') },
  photo: { title: t('Delete Photo'), description: t('Are you sure you want to delete this photo?') },
  document: { title: t('Delete Document'), description: t('Are you sure you want to delete this document?') },
}[pendingDelete.value?.kind] || { title: '', description: '' }))

const requestDelete = async (kind, payload, alreadyConfirmed = false) => {
  const mediaId = typeof payload === 'object' && kind !== 'area' ? payload?.id : payload
  if (alreadyConfirmed && (kind === 'photo' || kind === 'document')) {
    pendingDelete.value = { kind, payload: mediaId }
    await confirmDeleteItem()

    return
  }

  pendingDelete.value = { kind, payload: kind === 'area' ? payload : mediaId }
  isDeleteItemDialogOpen.value = true
}

const confirmDeleteItem = async () => {
  const pending = pendingDelete.value
  if (!pending) return false

  try {
    if (pending.kind === 'area') {
      await store.deleteSiteArea(pending.payload.id)
      showSuccess(t('Area deleted successfully'))
      await fetchAreas()
      await fetchEquipment()
    }
    else if (pending.kind === 'photo') {
      await store.deleteSiteMedia(targetSiteId.value, pending.payload)
      showSuccess(t('Photo deleted'))
      await fetchSite()
    }
    else {
      await store.deleteSiteMedia(targetSiteId.value, pending.payload)
      showSuccess(t('Document deleted'))
      await fetchSite()
    }

    return true
  }
  catch (err) {
    const fallback = {
      area: t('Failed to delete area'),
      photo: t('Failed to delete photo'),
      document: t('Failed to delete document'),
    }[pending.kind]

    showError(pending.kind === 'area' ? (err?.data?.message || fallback) : fallback)

    return false
  }
}

const handleDeleteArea = area => {
  const childCount = siteAreasFlat.value.filter(a => a.parent_area_id === area.id).length
  const equipCount = area.equipment_count || 0

  if (childCount > 0) {
    showError(t('Cannot delete area: it contains child sub-areas. Delete or move sub-areas first.'))

    return
  }

  if (equipCount > 0) {
    showError(t('Cannot delete area: it contains installed equipment assets. Move equipment first.'))

    return
  }

  requestDelete('area', area)
}

// ── Media Operations ─────────────────────────────────────────────────────────
const uploadingPhoto = ref(false)

const handleSavePhotoFromDialog = async payload => {
  const items = payload?.files?.length ? payload.files : (payload?.file ? [payload] : [])
  if (!items.length) return

  uploadingPhoto.value = true
  try {
    for (const item of items) {
      const file = item instanceof File || item instanceof Blob ? item : item?.file
      if (!file) continue

      await store.uploadSitePhoto(targetSiteId.value, file, {
        is_main: item?.is_main ?? payload?.is_main ?? (!fetchedSite.value?.photos?.length),
        title: item?.title || payload?.title || null,
      })
    }
    showSuccess(items.length > 1 ? t('Photos uploaded successfully') : t('Photo uploaded successfully'))
    isImageDialogOpen.value = false
    await fetchSite()
  }
  catch (err) {
    console.error('Photo dialog upload error:', err)
    showError(err?.data?.message || t('Failed to upload photo'))
  }
  finally {
    uploadingPhoto.value = false
  }
}

const handleSetMainPhoto = async itemOrId => {
  const mediaId = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
  if (!mediaId) return

  try {
    await store.setSiteMainPhoto(targetSiteId.value, mediaId)
    showSuccess(t('Main photo updated'))
    await fetchSite()
  }
  catch {
    showError(t('Failed to update main photo'))
  }
}

const openPhotoPreview = photoOrUrl => {
  const photos = fetchedSite.value?.photos || []

  mediaViewerItems.value = photos
  if (typeof photoOrUrl === 'object' && photoOrUrl !== null) {
    const idx = photos.findIndex(p => p.id === photoOrUrl.id)

    mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  }
  else if (typeof photoOrUrl === 'string') {
    const idx = photos.findIndex(p => p.url === photoOrUrl)

    mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  }
  else {
    mediaViewerInitialIndex.value = 0
  }
  mediaViewerTitle.value = t('Facility Photography')
  isMediaViewerOpen.value = true
}

const openDocPreview = doc => {
  const docs = allSiteDocuments.value || []

  mediaViewerItems.value = docs

  const idx = docs.findIndex(d => d.id === doc.id)

  mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  mediaViewerTitle.value = doc.title || doc.file_name || t('Document')
  isMediaViewerOpen.value = true
}

const handleUploadDocument = async (payload, onDone) => {
  const items = payload?.files?.length ? payload.files : (payload?.file ? [payload] : [])
  if (!items.length) {
    showWarning(t('Please select a file to upload'))

    return
  }

  uploadingDocument.value = true
  try {
    for (const item of items) {
      const file = item instanceof File || item instanceof Blob ? item : (item.file || item)
      const category = item.category || item.type || item.document_type || payload.category || payload.type || payload.document_type || 'general'

      await store.uploadSiteDocument(targetSiteId.value, file, {
        title: item.title || payload.title || file.name,
        category,
        notes: item.notes || payload.notes || null,
        collection: category === 'site_plans' ? 'site_plans' : 'documents',
      })
    }

    showSuccess(items.length > 1 ? t('Documents uploaded successfully') : t('Document uploaded successfully'))
    isUploadDocDialogOpen.value = false
    onDone?.()
    await fetchSite()
  }
  catch (err) {
    console.error('Document upload error:', err)
    showError(err?.data?.message || t('Failed to upload document'))
  }
  finally {
    uploadingDocument.value = false
  }
}

const handleDownloadDocument = async (mediaId, filename) => {
  try {
    await store.downloadSiteMedia(targetSiteId.value, mediaId, filename)
  }
  catch (err) {
    showError(t('Failed to download document'))
  }
}

// ── Section References ───────────────────────────────────────────────────────
const parametersSectionRef = ref(null)
const instructionsSectionRef = ref(null)

// ── Mobile Quick Actions ─────────────────────────────────────────────────────
const quickActions = computed(() => [
  {
    id: 'save-site',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'success',
    disabled: saving.value || !isDirty.value,
    onClick: handleSave,
  },
  {
    id: 'edit-status-risk',
    title: t('Edit Status & Risk'),
    icon: 'tabler-adjustments',
    color: 'warning',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => parametersSectionRef.value?.openStatusDialog(),
  },
  {
    id: 'edit-schedule-lifecycle',
    title: t('Edit Schedule & Lifecycle'),
    icon: 'tabler-calendar-time',
    color: 'info',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => parametersSectionRef.value?.openScheduleDialog(),
  },
  {
    id: 'edit-protocols',
    title: t('Edit Safety & Protocols'),
    icon: 'tabler-shield-cog',
    color: 'warning',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => instructionsSectionRef.value?.openProtocolsDialog(),
  },
  {
    id: 'add-area',
    title: t('Add Zone / Area'),
    icon: 'tabler-hierarchy',
    color: 'info',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => openAreaDialog(null, currentDrillAreaId.value),
  },
  {
    id: 'add-equipment',
    title: t('Add Equipment'),
    icon: 'tabler-fire-extinguisher',
    color: 'warning',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => openAddEquipmentContextual(selectedLocationAreaId.value),
  },
  {
    id: 'upload-photo',
    title: t('Take / Upload Photo'),
    icon: 'tabler-camera',
    color: 'success',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => { isImageDialogOpen.value = true },
  },
  {
    id: 'upload-doc',
    title: t('Upload Document'),
    icon: 'tabler-file-upload',
    color: 'primary',
    disabled: loading.value || !fetchedSite.value,
    onClick: () => { isUploadDocDialogOpen.value = true },
  },
  {
    id: 'view-client',
    title: t('View Client Info'),
    icon: 'tabler-building-skyscraper',
    color: 'primary',
    disabled: !fetchedSite.value?.client,
    onClick: handleViewClient,
  },
  {
    id: 'view-address',
    title: t('View Site Address'),
    icon: 'tabler-map-pin',
    color: 'info',
    disabled: !fetchedSite.value?.address,
    onClick: () => { isAddressViewDialogOpen.value = true },
  },
  ...(canDelete.value ? [{
    id: 'delete-site',
    title: t('Delete Site'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  }] : []),
])

onMounted(async () => {
  await fetchSite()
  await Promise.allSettled([
    fetchAreas(),
    fetchEquipment(),
  ])
})
</script>

<template>
  <div class="client-site-edit-page">
    <!-- Breadcrumb -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- Loading & Error States -->
    <VSkeletonLoader
      v-if="loading && !fetchedSite"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <WpErrorBanner
      v-else-if="pageError"
      :error="pageError"
      @retry="fetchSite"
    />

    <!-- Main Content Layout -->
    <div
      v-else-if="fetchedSite"
      class="site-edit-layout"
    >
      <!-- Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation -->
      <SiteNavSidebar
        v-model="activeSection"
        :site="fetchedSite"
        :form="form"
        :sections="sections"
        :saving="saving"
        :is-dirty="isDirty"
        :is-read-only="isReadOnly"
        :can-delete="canDelete"
        id-prefix="section-"
        @save="handleSave"
        @delete="isDeleteDialogVisible = true"
      />

      <!-- Main Content Feed -->
      <main class="journal-feed">
        <!-- Top KPI Metrics -->
        <WpKpiGroup
          :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
          class="site-top-kpis"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="$t('Zones & Areas')"
                :value="siteAreasFlat.length"
                icon="tabler-hierarchy"
                icon-color="info"
                clickable
                @click="scrollToSection('areas')"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="$t('Equipment')"
                :value="siteEquipment.length"
                icon="tabler-fire-extinguisher"
                icon-color="warning"
                clickable
                @click="scrollToSection('equipment')"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="$t('Operating Status')"
                :value="optionTitle(operatingStatusOptions, form.status)"
                icon="tabler-circle-check"
                :icon-color="resolveSiteStatusVariant(form.status)"
                clickable
                @click="scrollToSection('general')"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="$t('Safety Risk')"
                :value="optionTitle(riskClassOptions, form.risk_class)"
                icon="tabler-shield-check"
                :icon-color="resolveRiskVariant(form.risk_class)"
                clickable
                @click="scrollToSection('general')"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- Section 1: Facility Profile -->
        <SiteProfileSection
          :site="fetchedSite"
          :form="form"
          @open-photo-dialog="isImageDialogOpen = true"
        />

        <!-- Section 2: Client & Address Details -->
        <SiteClientAddressSection
          :site="fetchedSite"
          :form="form"
          @view-client="handleViewClient"
          @view-address="isAddressViewDialogOpen = true"
        />

        <!-- Section 3: Status & Operational Parameters -->
        <SiteParametersSection
          ref="parametersSectionRef"
          :form="form"
        />

        <!-- Section 4: Access & Safety Guidelines -->
        <SiteInstructionsSection
          ref="instructionsSectionRef"
          :form="form"
        />

        <!-- Section 5: Zones & Building Areas -->
        <SiteAreasSection
          v-model:areas-view-mode="areasViewMode"
          v-model:current-drill-area-id="currentDrillAreaId"
          :site="fetchedSite"
          :site-name="form.name"
          :site-areas-flat="siteAreasFlat"
          :flattened-hierarchy="flattenedHierarchy"
          :current-cards-areas="currentCardsAreas"
          :cards-breadcrumbs="cardsBreadcrumbs"
          :expanded-area-ids="expandedAreaIds"
          @toggle-expand="toggleAreaExpand"
          @expand-all="expandAllAreas"
          @collapse-all="collapseAllAreas"
          @open-area-dialog="({ area, parentId }) => openAreaDialog(area, parentId)"
          @delete-area="handleDeleteArea"
        />

        <!-- Section 6: Installed Equipment Assets -->
        <SiteEquipmentSection
          :site="fetchedSite"
          :site-id="targetSiteId"
          :site-equipment="siteEquipment"
          :site-areas-flat="siteAreasFlat"
          :selected-location-area-id="selectedLocationAreaId"
          @add-equipment="openAddEquipmentContextual"
          @view-equipment="handleViewEquipment"
          @edit-equipment="handleEditEquipment"
          @open-zoom="isEquipmentFullscreenOpen = true"
        />

        <!-- Section 7: Photos & Technical Documents -->
        <SiteMediaSection
          :photos="fetchedSite.photos"
          :documents="allSiteDocuments"
          :uploading-photo="uploadingPhoto"
          :uploading-doc="uploadingDocument"
          @open-photo-dialog="isImageDialogOpen = true"
          @open-upload-doc-dialog="isUploadDocDialogOpen = true"
          @upload-photo="handleSavePhotoFromDialog"
          @upload-doc="handleUploadDocument"
          @preview-photo="openPhotoPreview"
          @preview-doc="openDocPreview"
          @set-main-photo="handleSetMainPhoto"
          @delete-photo="(id, confirmed) => requestDelete('photo', id, confirmed)"
          @download-doc="({ id, filename }) => handleDownloadDocument(id, filename)"
          @delete-doc="(id, confirmed) => requestDelete('document', id, confirmed)"
        />

        <!-- Section 8: Activity & Audit Timeline -->
        <SiteHistorySection
          :site="fetchedSite"
          :form="form"
          :site-areas-flat="siteAreasFlat"
          :site-equipment="siteEquipment"
          :operational-count="equipmentKPIs.operational"
        />
      </main>
    </div>

    <!-- Mobile Quick Actions Floating Launcher & Sheet -->
    <div
      v-if="smAndDown && fetchedSite"
      class="site-edit-quick-actions-launcher"
    >
      <VFab
        color="primary"
        icon="tabler-bolt"
        rounded="lg"
        size="large"
        :aria-label="$t('Quick Actions')"
        @click="quickActionsOpen = true"
      />
    </div>

    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Dialogs -->
    <WpDocumentDialog
      v-model="isUploadDocDialogOpen"
      :title="$t('Scan / Upload Site Document')"
      :document-types="documentCategories"
      preset="compact"
      @save="handleUploadDocument"
      @error="err => showError(err?.message || $t('Document upload error'))"
    />

    <WpMediaViewerDialog
      v-model="isMediaViewerOpen"
      :items="mediaViewerItems"
      :initial-index="mediaViewerInitialIndex"
      :title="mediaViewerTitle"
      @download="item => handleDownloadDocument(item.id, item.file_name || item.name)"
    />

    <AreaManageDialog
      v-model="isAreaDialogOpen"
      :site-id="targetSiteId"
      :area="selectedAreaForEdit"
      :parent-area-id="selectedParentAreaId"
      :areas-list="siteAreasFlat"
      @saved="() => { fetchAreas(); fetchEquipment(); }"
    />

    <EquipmentWizardDialog
      v-model="isAddEquipmentDialogOpen"
      :prefilled-client="fetchedSite?.client"
      :prefilled-site="fetchedSite"
      :site-id="targetSiteId"
      :initial-area-id="selectedLocationAreaId"
      :equipment-id="selectedEditEquipmentId"
      @created="() => { fetchEquipment(); fetchAreas(); }"
      @saved="() => { fetchEquipment(); fetchAreas(); }"
      @close="() => { isAddEquipmentDialogOpen = false; selectedEditEquipmentId = null; }"
    />

    <EquipmentViewModal
      v-model="isEquipmentViewModalOpen"
      :equipment-id="selectedViewEquipmentId"
    />

    <SiteEquipmentZoomDialog
      v-model="isEquipmentFullscreenOpen"
      v-model:view-mode="equipmentViewMode"
      v-model:selected-location-area-id="selectedLocationAreaId"
      :site="fetchedSite"
      :site-id="targetSiteId"
      :site-title="form.name || fetchedSite?.site_code"
      :equipment="siteEquipment"
      :site-areas-flat="siteAreasFlat"
      :flattened-hierarchy="flattenedHierarchy"
      :initial-view-mode="equipmentViewMode"
      @view-equipment="handleViewEquipment"
      @edit-equipment="handleEditEquipment"
      @add-equipment="openAddEquipmentContextual"
      @refresh="fetchEquipment"
      @toggle-expand="toggleAreaExpand"
    />

    <ViewClientDialog
      v-model:is-dialog-visible="isClientViewDialogOpen"
      :client-id="fetchedSite?.client_id || fetchedSite?.client?.id"
      :client="selectedClientForView"
      :loading="loadingClientView"
    />

    <ViewAddressDialog
      v-model:is-dialog-visible="isAddressViewDialogOpen"
      :address="fetchedSite?.address"
    />

    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('Site Facility Photo')"
      capture
      show-cropper
      show-title-input
      @save="handleSavePhotoFromDialog"
      @error="err => showError(err?.message || $t('Photo upload error'))"
    />

    <WpDeleteDialog
      v-model:is-open="isDeleteDialogVisible"
      :title="$t('Delete Site')"
      :message="$t('Are you sure you want to delete site {name}?', { name: form.name || fetchedSite?.site_code || '' })"
      @confirm="handleDelete"
    />

    <WpDeleteDialog
      v-model:is-open="isDeleteItemDialogOpen"
      :title="deleteDialogCopy.title"
      :message="deleteDialogCopy.description"
      @confirm="confirmDeleteItem"
    />

    <WpUnsavedChangesDialog
      v-model:is-open="isUnsavedChangesDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
.client-site-edit-page {
  min-block-size: 100vh;
  overflow-x: clip;
  padding-block-end: 1rem;
}

.site-edit-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 959px) {
    flex-direction: column;
    padding-block-end: calc(92px + env(safe-area-inset-bottom, 0px));
  }
}

.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.journal-feed > :deep(.journal-section) {
  margin-block-end: 0 !important;
}

.journal-section {
  min-inline-size: 0;
  scroll-margin-block-start: 160px;

  @media (max-width: 959px) {
    scroll-margin-block-start: 130px;
  }
}

.site-edit-quick-actions-launcher {
  position: fixed !important;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 16px;

  :deep(.v-fab) {
    margin: 0 !important;
  }
}

@media (max-width: 959px) {
  .journal-feed {
    gap: 1rem;
  }

  .site-edit-quick-actions-launcher {
    inset-inline-end: 16px;
  }

  :deep(.scroll-to-top) {
    inset-block-end: calc(144px + env(safe-area-inset-bottom, 0px)) !important;
    inset-inline-end: 16px !important;
  }
}

/* Small mobile devices: one KPI card per row */
@media (max-width: 430px) {
  .site-top-kpis {
    :deep(.v-row) {
      margin: 0 !important;
    }

    :deep(.v-col) {
      flex: 0 0 100% !important;
      max-width: 100% !important;
      padding: 0.25rem !important;
    }

    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.5rem 0.75rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.65rem !important;
        margin-bottom: 2px !important;
        line-height: 1.2;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 1.05rem !important;
        line-height: 1.3;
      }

      .wp-kpi-card__icon {
        block-size: 32px !important;
        inline-size: 32px !important;

        .v-icon {
          font-size: 18px !important;
        }
      }
    }
  }
}

@media (max-width: 390px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.4375rem 0.6875rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.64rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 1.025rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 30px !important;
        inline-size: 30px !important;

        .v-icon {
          font-size: 17px !important;
        }
      }
    }
  }
}

@media (max-width: 375px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.375rem 0.625rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.625rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 0.975rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 28px !important;
        inline-size: 28px !important;

        .v-icon {
          font-size: 16px !important;
        }
      }
    }
  }
}

@media (max-width: 360px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.3125rem 0.5625rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.615rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 0.95rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 27px !important;
        inline-size: 27px !important;

        .v-icon {
          font-size: 15.5px !important;
        }
      }
    }
  }
}

@media (max-width: 340px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.3125rem 0.5rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.61rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 0.925rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 26px !important;
        inline-size: 26px !important;

        .v-icon {
          font-size: 15px !important;
        }
      }
    }
  }
}

@media (max-width: 320px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.25rem 0.5rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.6rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 0.9rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 25px !important;
        inline-size: 25px !important;

        .v-icon {
          font-size: 14.5px !important;
        }
      }
    }
  }
}

@media (max-width: 310px) {
  .site-top-kpis {
    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.25rem 0.4375rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.59rem !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 0.875rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 24px !important;
        inline-size: 24px !important;

        .v-icon {
          font-size: 14px !important;
        }
      }
    }
  }
}
</style>
