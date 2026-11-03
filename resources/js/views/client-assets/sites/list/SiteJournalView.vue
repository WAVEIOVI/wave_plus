<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpDocumentDialog,
  WpErrorBanner,
  WpImageDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useSiteAreas } from '@/composables/client-assets/sites/useSiteAreas'
import {
  documentCategories,
  operatingStatusOptions,
  riskClassOptions,
  resolveSiteStatusVariant,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import { useSiteEdit } from '@/composables/client-assets/sites/useSiteEdit'
import { useSiteEquipment } from '@/composables/client-assets/sites/useSiteEquipment'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import ViewAddressDialog from '@/views/business-partners/shared-components/dialogs/ViewAddressDialog.vue'
import EquipmentWizardDialog from '@/views/client-assets/equipment/dialogs/EquipmentWizardDialog.vue'
import EquipmentViewModal from '@/views/client-assets/equipment/EquipmentViewModal.vue'
import AreaManageDialog from '@/views/client-assets/sites/edit/AreaManageDialog.vue'
import SiteAreasSection from '@/views/client-assets/sites/edit/SiteAreasSection.vue'
import SiteClientAddressSection from '@/views/client-assets/sites/edit/SiteClientAddressSection.vue'
import SiteEditHeader from '@/views/client-assets/sites/edit/SiteEditHeader.vue'
import SiteEquipmentSection from '@/views/client-assets/sites/edit/SiteEquipmentSection.vue'
import SiteEquipmentZoomDialog from '@/views/client-assets/sites/edit/SiteEquipmentZoomDialog.vue'
import SiteHistorySection from '@/views/client-assets/sites/edit/SiteHistorySection.vue'
import SiteInstructionsSection from '@/views/client-assets/sites/edit/SiteInstructionsSection.vue'
import SiteMediaSection from '@/views/client-assets/sites/edit/SiteMediaSection.vue'
import SiteParametersSection from '@/views/client-assets/sites/edit/SiteParametersSection.vue'
import SitePhotoPreviewDialog from '@/views/client-assets/sites/edit/SitePhotoPreviewDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import SiteProfileSection from '@/views/client-assets/sites/edit/SiteProfileSection.vue'
import DeleteSiteDialog from '@/views/client-assets/sites/list/DeleteSiteDialog.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  siteId: { type: [Number, String], default: null },
  readonly: { type: Boolean, default: false },
  embedded: { type: Boolean, default: false },
})

const emit = defineEmits(['updated', 'deleted', 'close'])

const route = useRoute()
const targetSiteId = computed(() => props.siteId || route.params.id)
const store = useClientAssetStore()
const clientStore = useClientStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { mdAndDown, smAndDown } = useDisplay()

// ── Composables ──────────────────────────────────────────────────────────────
const {
  sections,
  mobileNavItems,
  optionTitle,
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
  handleSave: baseSave,
  handleDelete: baseDelete,
} = useSiteEdit(targetSiteId)

const handleSave = async () => {
  const result = await baseSave()

  emit('updated', fetchedSite.value)

  return result
}

const handleDelete = async () => {
  const result = await baseDelete()

  emit('deleted', targetSiteId.value)

  return result
}

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

// ── Navigation ───────────────────────────────────────────────────────────────
const activeSection = ref('bio')
const quickActionsOpen = ref(false)

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

const isPhotoPreviewOpen = ref(false)
const previewPhotoUrl = ref('')
const isImageDialogOpen = ref(false)
const isUploadDocDialogOpen = ref(false)
const uploadingDocument = ref(false)

// ── Contextual Openers ───────────────────────────────────────────────────────
const openAreaDialog = (area = null, parentId = null) => {
  selectedAreaForEdit.value = area
  selectedParentAreaId.value = parentId ?? area?.parent_area_id ?? null
  isAreaDialogOpen.value = true
}

const openAddEquipmentContextual = (areaId = null) => {
  selectedEditEquipmentId.value = 'new'
  selectedLocationAreaId.value = areaId ?? null
  isAddEquipmentDialogOpen.value = true
}

const handleViewEquipment = eq => {
  selectedViewEquipmentId.value = eq?.id || eq
  isEquipmentViewModalOpen.value = true
}

const handleEditEquipment = eq => {
  selectedEditEquipmentId.value = eq?.id || eq
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
  } catch (err) {
    selectedClientForView.value = fetchedSite.value?.client
  } finally {
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

const requestDelete = (kind, payload) => {
  pendingDelete.value = { kind, payload }
  isDeleteItemDialogOpen.value = true
}

const confirmDeleteItem = async () => {
  const pending = pendingDelete.value
  if (!pending) return false

  try {
    if (pending.kind === 'area') {
      await store.deleteSiteArea(pending.payload.id)
      snackbarStore.showMessage(t('Area deleted successfully'), 'success')
      await fetchAreas()
      await fetchEquipment()
    } else if (pending.kind === 'photo') {
      await store.deleteSiteMedia(targetSiteId.value, pending.payload)
      snackbarStore.showMessage(t('Photo deleted'), 'success')
      await fetchSite()
    } else {
      await store.deleteSiteMedia(targetSiteId.value, pending.payload)
      snackbarStore.showMessage(t('Document deleted'), 'success')
      await fetchSite()
    }

    return true
  } catch (err) {
    const fallback = {
      area: t('Failed to delete area'),
      photo: t('Failed to delete photo'),
      document: t('Failed to delete document'),
    }[pending.kind]

    snackbarStore.showMessage(pending.kind === 'area' ? (err?.data?.message || fallback) : fallback, 'error')

    return false
  }
}

const handleDeleteArea = area => {
  const childCount = siteAreasFlat.value.filter(a => a.parent_area_id === area.id).length
  const equipCount = area.equipment_count || 0

  if (childCount > 0) {
    snackbarStore.showMessage(t('Cannot delete area: it contains child sub-areas. Delete or move sub-areas first.'), 'error')

    return
  }

  if (equipCount > 0) {
    snackbarStore.showMessage(t('Cannot delete area: it contains installed equipment assets. Move equipment first.'), 'error')

    return
  }

  requestDelete('area', area)
}

// ── Media Operations ─────────────────────────────────────────────────────────
const handleSavePhotoFromDialog = async payload => {
  const items = payload?.files?.length ? payload.files : (payload?.file ? [payload] : [])
  if (!items.length) return

  try {
    for (const item of items) {
      const file = item instanceof File || item instanceof Blob ? item : item?.file
      if (!file) continue

      await store.uploadSitePhoto(targetSiteId.value, file, {
        is_main: item.is_main ?? (!fetchedSite.value?.photos?.length),
        title: item.title || payload.title || null,
      })
    }
    snackbarStore.showMessage(items.length > 1 ? t('Photos uploaded successfully') : t('Photo uploaded successfully'), 'success')
    isImageDialogOpen.value = false
    await fetchSite()
  } catch (err) {
    console.error('Photo dialog upload error:', err)
    snackbarStore.showMessage(t('Failed to upload photo'), 'error')
  }
}

const handleSetMainPhoto = async mediaId => {
  try {
    await store.setSiteMainPhoto(targetSiteId.value, mediaId)
    snackbarStore.showMessage(t('Main photo updated'), 'success')
    await fetchSite()
  } catch (err) {
    snackbarStore.showMessage(t('Failed to update main photo'), 'error')
  }
}

const mediaViewerItems = ref([])
const mediaViewerInitialIndex = ref(0)
const mediaViewerTitle = ref('')
const isMediaViewerOpen = ref(false)

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

const openDocumentPreview = doc => {
  const docs = allSiteDocuments.value || []

  mediaViewerItems.value = docs

  const idx = docs.findIndex(d => d.id === doc.id)

  mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
  mediaViewerTitle.value = doc.title || doc.file_name || t('Document')
  isMediaViewerOpen.value = true
}

const handleUploadDocument = async payload => {
  if (!payload?.file) {
    snackbarStore.showMessage(t('Please select a file to upload'), 'warning')

    return
  }

  uploadingDocument.value = true
  try {
    const file = Array.isArray(payload.file) ? payload.file[0] : payload.file

    await store.uploadSiteDocument(targetSiteId.value, file, {
      title: payload.title || file.name,
      category: payload.category,
      notes: payload.notes,
      collection: payload.category === 'site_plans' ? 'site_plans' : 'documents',
    })

    snackbarStore.showMessage(t('Document uploaded successfully'), 'success')
    isUploadDocDialogOpen.value = false
    await fetchSite()
  } catch (err) {
    console.error('Document upload error:', err)
    snackbarStore.showMessage(t('Failed to upload document'), 'error')
  } finally {
    uploadingDocument.value = false
  }
}

const handleDownloadDocument = async (mediaId, filename) => {
  try {
    await store.downloadSiteMedia(targetSiteId.value, mediaId, filename)
  } catch (err) {
    snackbarStore.showMessage(t('Failed to download document'), 'error')
  }
}

// ── Section References ───────────────────────────────────────────────────────
const parametersSectionRef = ref(null)
const instructionsSectionRef = ref(null)

// ── Mobile Quick Actions ─────────────────────────────────────────────────────
const quickActions = computed(() => {
  const actions = []
  if (!props.readonly) {
    actions.push({
      id: 'save-site',
      title: t('Save Changes'),
      icon: 'tabler-device-floppy',
      color: 'success',
      disabled: saving.value || !isDirty.value,
      onClick: handleSave,
    })
  }

  actions.push(
    {
      id: 'add-area',
      title: t('Add Zone / Area'),
      icon: 'tabler-hierarchy',
      color: 'info',
      disabled: loading.value || !fetchedSite.value || props.readonly,
      onClick: () => openAreaDialog(null, currentDrillAreaId.value),
    },
    {
      id: 'add-equipment',
      title: t('Add Equipment'),
      icon: 'tabler-fire-extinguisher',
      color: 'warning',
      disabled: loading.value || !fetchedSite.value || props.readonly,
      onClick: () => openAddEquipmentContextual(selectedLocationAreaId.value),
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
  )

  if (!props.readonly) {
    actions.push({
      id: 'delete-site',
      title: t('Delete Site'),
      icon: 'tabler-trash',
      color: 'error',
      onClick: () => { isDeleteDialogVisible.value = true },
    })
  }

  return actions
})

// ── Intersection Observer ────────────────────────────────────────────────────
let sectionObserver = null

onMounted(async () => {
  await fetchSite()
  await Promise.allSettled([
    fetchAreas(),
    fetchEquipment(),
  ])

  sectionObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id.replace('section-', '')

        activeSection.value = id
      }
    })
  }, { threshold: 0.2, rootMargin: '-150px 0px -50% 0px' })

  nextTick(() => {
    sections.forEach(s => {
      const el = document.getElementById(`section-${s.id}`)
      if (el) sectionObserver?.observe(el)
    })
  })
})

onBeforeUnmount(() => {
  sectionObserver?.disconnect()
  sectionObserver = null
})
</script>

<template>
  <div
    class="site-journal-view"
    :class="{ 'is-embedded': embedded, 'is-readonly': readonly }"
  >
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
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="form.name || fetchedSite?.site_code"
        :subtitle="fetchedSite?.site_code"
        :avatar="fetchedSite?.main_photo_url || fetchedSite?.photos?.[0]?.url"
        avatar-icon="tabler-building-estate"
        :is-dialog="embedded"
        :readonly="readonly"
        id-prefix="section-"
        :container="embedded ? '.view-dialog-card .v-card-text' : null"
      >
        <template
          v-if="!readonly"
          #footer
        >
          <VBtn
            block
            color="primary"
            :disabled="saving || !isDirty"
            :loading="saving"
            prepend-icon="tabler-device-floppy"
            :aria-label="$t('Save Changes')"
            class="mb-2 font-weight-semibold"
            @click="handleSave"
          >
            {{ $t('Save Changes') }}
          </VBtn>
          <VBtn
            block
            variant="tonal"
            color="error"
            prepend-icon="tabler-trash"
            :aria-label="$t('Delete')"
            class="font-weight-semibold"
            @click="isDeleteDialogVisible = true"
          >
            {{ $t('Delete') }}
          </VBtn>
        </template>
      </WpJournalNav>

      <!-- Main Content Feed -->
      <main class="journal-feed">
        <!-- Site Header -->
        <SiteEditHeader
          :site="fetchedSite"
          :form="form"
          :is-dirty="isDirty"
        />

        <!-- Top KPI Metrics -->
        <WpKpiGroup
          :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
          class="mb-6"
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
                :title="$t('Installed Equipment')"
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
          @open-photo-dialog="isImageDialogOpen = true"
          @open-upload-doc-dialog="isUploadDocDialogOpen = true"
          @preview-photo="openPhotoPreview"
          @preview-doc="openDocumentPreview"
          @set-main-photo="handleSetMainPhoto"
          @delete-photo="id => requestDelete('photo', id)"
          @download-doc="({ id, filename }) => handleDownloadDocument(id, filename)"
          @delete-doc="id => requestDelete('document', id)"
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

    <!-- Mobile Bottom Navigation -->
    <AppBottomNav
      v-if="mdAndDown && !embedded"
      v-model="activeSection"
      :items="mobileNavItems"
      @update:model-value="scrollToSection"
    />

    <!-- Mobile Quick Actions Floating Launcher & Sheet -->
    <VFab
      v-if="smAndDown && fetchedSite && !embedded"
      class="site-edit-quick-actions-launcher"
      color="primary"
      icon="tabler-bolt"
      rounded="lg"
      size="large"
      :aria-label="$t('Quick Actions')"
      @click="quickActionsOpen = true"
    />

    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Dialogs -->
    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('Site Photography')"
      multiple
      @save="handleSavePhotoFromDialog"
      @error="err => snackbarStore.showMessage(err?.message || $t('Image capture error'), 'error')"
    />

    <WpDocumentDialog
      v-model="isUploadDocDialogOpen"
      :title="$t('Scan / Upload Site Document')"
      :document-types="documentCategories"
      preset="compact"
      @save="handleUploadDocument"
      @error="err => snackbarStore.showMessage(err?.message || $t('Document upload error'), 'error')"
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
      :areas-list="siteAreasFlat"
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
    />

    <ViewAddressDialog
      v-model:is-dialog-visible="isAddressViewDialogOpen"
      :address="fetchedSite?.address"
    />

    <DeleteSiteDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      :site-id="targetSiteId"
      :site-name="form.name || fetchedSite?.site_code"
      @deleted="handleDelete"
    />

    <WpDeleteDialog
      v-model="isDeleteItemDialogOpen"
      :title="deleteDialogCopy.title"
      :description="deleteDialogCopy.description"
      :confirm-action="confirmDeleteItem"
    />

    <WpUnsavedChangesDialog
      v-model="isUnsavedChangesDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
.site-journal-view {
  min-block-size: 100%;
}

.site-edit-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
}

.journal-feed {
  flex: 1;
  min-inline-size: 0;
}

.journal-section {
  scroll-margin-top: 90px;
}

.site-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1000;
  inset-block-end: 80px;
  inset-inline-end: 20px;
}

.is-embedded {
  .journal-feed {
    padding: 1rem;
  }
}

.is-readonly {
  :deep(.v-field--disabled),
  :deep(.v-btn--disabled) {
    opacity: 0.8;
  }
}
</style>
