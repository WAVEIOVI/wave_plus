<script setup>
/* eslint-disable camelcase */
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpErrorBanner,
  WpSectionCard,
} from '@/components/shared'
import {
  complianceStatusOptions,
  criticalityOptions,
  lifecycleStatusOptions,
  useEquipmentConstants,
} from '@/composables/client-assets/equipment/useEquipmentConstants'
import { useEquipmentEdit } from '@/composables/client-assets/equipment/useEquipmentEdit'
import { useEquipmentEditModals } from '@/composables/client-assets/equipment/useEquipmentEditModals'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import EquipmentEditHeaderKpis from '@/views/client-assets/equipment/edit/EquipmentEditHeaderKpis.vue'
import EquipmentEditModals from '@/views/client-assets/equipment/edit/EquipmentEditModals.vue'
import EquipmentEditUnsavedBar from '@/views/client-assets/equipment/edit/EquipmentEditUnsavedBar.vue'
import EquipmentNavSidebar from '@/views/client-assets/equipment/edit/EquipmentNavSidebar.vue'
import EquipmentProfileSection from '@/views/client-assets/equipment/edit/EquipmentProfileSection.vue'
import EquipmentClassificationSection from '@/views/client-assets/equipment/sections/EquipmentClassificationSection.vue'
import EquipmentCodificationSection from '@/views/client-assets/equipment/sections/EquipmentCodificationSection.vue'
import EquipmentHistorySection from '@/views/client-assets/equipment/sections/EquipmentHistorySection.vue'
import EquipmentLifecycleDatesSection from '@/views/client-assets/equipment/sections/EquipmentLifecycleDatesSection.vue'
import EquipmentLocationSection from '@/views/client-assets/equipment/sections/EquipmentLocationSection.vue'
import EquipmentMediaGallery from '@/views/client-assets/equipment/sections/EquipmentMediaGallery.vue'
import EquipmentSpecsKvEditor from '@/views/client-assets/equipment/sections/EquipmentSpecsKvEditor.vue'
import EquipmentStatusTiles from '@/views/client-assets/equipment/sections/EquipmentStatusTiles.vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  embeddedEquipmentId: { type: [String, Number], default: null },
  isModal: { type: Boolean, default: false },
  isReadOnly: { type: Boolean, default: false },
})

definePage({
  path: '/client-assets/equipment/edit/:id',
  name: 'client-assets-equipment-edit-id',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})

const route = useRoute()
const router = useRouter()
const targetEquipmentId = computed(() => props.embeddedEquipmentId ?? route.params.id)
const { t } = useI18n()
const { smAndDown } = useDisplay()
const ability = useAbility()

const isReadOnly = computed(() => props.isReadOnly || !ability.can('update', 'ClientAssets'))
const canDelete = computed(() => !isNew.value && ability.can('delete', 'ClientAssets'))

// ── Composables ──────────────────────────────────────────────────────────────
const {
  sections,
  optionTitle,
  resolveLifecycleVariant,
  resolveComplianceVariant,
  resolveCriticalityVariant,
} = useEquipmentConstants()

const {
  loading,
  saving,
  pageError,
  fetchedEquipment,
  form,
  isDirty,
  isNew,
  optionSelections,
  isCustomModelName,
  equipmentClasses,
  blueprints,
  brandsList,
  sitesList,
  loadedBlueprintDetails,
  selectedClient,
  selectedSite,
  selectedArea,
  selectedAreaFullPath,
  selectedBlueprint,
  selectedClass,
  selectedOptionBadges,
  blueprintInterventionCycles,
  statusHistories,
  locationHistories,
  transferHistories,
  qrHistories,
  allEquipmentDocuments,
  updateField,
  generateRandomCode,
  updateModelNameFromOptions,
  handleClientSelected,
  clearArea,
  handleSiteAreaConfirm,
  handleClassBlueprintConfirm,
  fetchEquipment,
  loadMasterData,
  handleSave,
  handleDelete,
  handleDownloadQr,
} = useEquipmentEdit(targetEquipmentId)

// ── Modals & Dialog State Composable ─────────────────────────────────────────
const {
  isDeleteDialogVisible,
  isTransferDialogOpen,
  isReplaceQrDialogOpen,
  isQrScannerOpen,
  isClientSearchModalVisible,
  isClassBlueprintModalVisible,
  isSiteAreaModalVisible,
  isImageDialogOpen,
  isGuidelineDialogOpen,
  selectedGuidelineForView,
  handleViewGuideline,
  isClientViewDialogOpen,
  selectedClientForView,
  loadingClientView,
  onClientPicked,
  handleViewClient,
  handleQrScanned,
  isMediaViewerOpen,
  mediaViewerItems,
  mediaViewerInitialIndex,
  mediaViewerTitle,
  handleSavePhotoFromDialog,
  openPhotoPreview,
} = useEquipmentEditModals({
  form,
  fetchedEquipment,
  targetEquipmentId,
  selectedClient,
  isNew,
  fetchEquipment,
  handleClientSelected,
})

// ── Navigation & Breadcrumbs ─────────────────────────────────────────────────
const activeSection = ref('bio')
const quickActionsOpen = ref(false)
const mediaGalleryRef = ref(null)

const visibleSections = computed(() =>
  isNew.value ? sections.filter(s => s.id !== 'history') : sections,
)

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Client Assets'), to: '/client-assets/equipment/list', icon: 'tabler-building-estate' },
  { title: t('Equipment Registry'), to: '/client-assets/equipment/list', icon: 'tabler-fire-extinguisher' },
  {
    title: form.value.model_name || form.value.equipment_code || t('Edit Equipment'),
    icon: 'tabler-tool',
    disabled: true,
  },
])

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Unsaved Changes Guard ────────────────────────────────────────────────────
const guardDirty = computed(() => !props.isModal && !props.isReadOnly && isDirty.value)

const {
  isDialogVisible: isUnsavedChangesDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(guardDirty)

// ── Mobile Quick Actions ─────────────────────────────────────────────────────
const quickActions = computed(() => [
  {
    id: 'save-equipment',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'success',
    disabled: saving.value || !isDirty.value,
    onClick: handleSave,
  },
  {
    id: 'transfer-equipment',
    title: t('Transfer Equipment'),
    icon: 'tabler-arrows-exchange',
    color: 'info',
    disabled: loading.value,
    onClick: () => { isTransferDialogOpen.value = true },
  },
  {
    id: 'replace-qr',
    title: t('Replace QR Code'),
    icon: 'tabler-qrcode',
    color: 'warning',
    disabled: loading.value,
    onClick: () => { isReplaceQrDialogOpen.value = true },
  },
  {
    id: 'download-qr',
    title: t('Download QR Tag'),
    icon: 'tabler-download',
    color: 'primary',
    disabled: loading.value,
    onClick: handleDownloadQr,
  },
  {
    id: 'upload-photo',
    title: t('Take / Upload Photo'),
    icon: 'tabler-camera',
    color: 'success',
    disabled: loading.value,
    onClick: () => { isImageDialogOpen.value = true },
  },
  {
    id: 'upload-doc',
    title: t('Scan / Upload Document'),
    icon: 'tabler-file-upload',
    color: 'primary',
    disabled: loading.value,
    onClick: () => {
      scrollToSection('media')
      mediaGalleryRef.value?.openUploadDocDialog()
    },
  },
  {
    id: 'view-client',
    title: t('View Client Info'),
    icon: 'tabler-building-skyscraper',
    color: 'info',
    disabled: !selectedClient.value,
    onClick: handleViewClient,
  },
  ...(canDelete.value ? [{
    id: 'delete-equipment',
    title: t('Delete Equipment'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  }] : []),
])

// ── Keyboard Save Shortcut ───────────────────────────────────────────────────
const handleKeydown = e => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault()
    if (!isReadOnly.value && !saving.value && isDirty.value) {
      handleSave()
    }
  }
}

onMounted(async () => {
  // Creation is handled via EquipmentWizardDialog; redirect /client-assets/equipment/edit/new to list
  if (String(targetEquipmentId.value ?? '') === 'new') {
    await router.replace({ name: 'client-assets-equipment-list' })

    return
  }

  window.addEventListener('keydown', handleKeydown)

  await Promise.allSettled([
    fetchEquipment(),
    loadMasterData(),
  ])
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="client-equipment-edit-page">
    <!-- Breadcrumb -->
    <AppBreadcrumb
      v-if="!isModal"
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- Loading & Error States -->
    <VSkeletonLoader
      v-if="loading && !fetchedEquipment"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <WpErrorBanner
      v-else-if="pageError"
      :error="pageError"
      @retry="fetchEquipment"
    />

    <!-- Main Content Layout -->
    <div
      v-else-if="fetchedEquipment"
      class="equipment-edit-layout"
    >
      <!-- Sticky Sidebar Navigation (Desktop & Mobile via WpJournalNav) -->
      <EquipmentNavSidebar
        v-if="!isModal"
        v-model="activeSection"
        :equipment="fetchedEquipment"
        :form="form"
        :sections="visibleSections"
        :saving="saving"
        :is-dirty="isDirty"
        :is-new="false"
        :readonly="isReadOnly"
        :can-delete="canDelete"
        @save="handleSave"
        @transfer="isTransferDialogOpen = true"
        @delete="isDeleteDialogVisible = true"
        @replace-qr="isReplaceQrDialogOpen = true"
        @download-qr="handleDownloadQr"
      />

      <!-- Main Content Feed -->
      <main class="journal-feed">
        <!-- Top KPI Metrics Ribbon -->
        <EquipmentEditHeaderKpis
          :form="form"
          :fetched-equipment="fetchedEquipment"
          :all-equipment-documents-count="allEquipmentDocuments.length"
          :lifecycle-status-options="lifecycleStatusOptions"
          :compliance-status-options="complianceStatusOptions"
          :criticality-options="criticalityOptions"
          :option-title="optionTitle"
          :resolve-lifecycle-variant="resolveLifecycleVariant"
          :resolve-compliance-variant="resolveComplianceVariant"
          :resolve-criticality-variant="resolveCriticalityVariant"
          @scroll-to-section="scrollToSection"
        />

        <!-- Section 1: Asset Identity & Telemetry Overview -->
        <EquipmentProfileSection
          :equipment="fetchedEquipment"
          :form="form"
          :selected-client="selectedClient"
          :selected-site="selectedSite"
          :selected-area-full-path="selectedAreaFullPath"
          :selected-blueprint="selectedBlueprint"
          :selected-class="selectedClass"
          :selected-option-badges="selectedOptionBadges"
          :is-new="false"
          :readonly="isReadOnly"
          @update:field="updateField"
          @open-photo-dialog="isImageDialogOpen = true"
          @preview-photo="openPhotoPreview"
          @download-qr="handleDownloadQr"
          @generate-code="generateRandomCode"
          @open-transfer="isTransferDialogOpen = true"
          @view-client="handleViewClient"
        />

        <!-- Section 2: Commercial Assignment & Physical Location (Client Locked, Site/Zone via Transfer Dialog) -->
        <section
          id="section-general"
          class="journal-section mb-6"
        >
          <EquipmentLocationSection
            :form="form"
            :selected-client="selectedClient"
            :selected-site="selectedSite"
            :selected-area="selectedArea"
            :selected-area-full-path="selectedAreaFullPath"
            :is-mode-new="false"
            :is-client-locked="true"
            :is-transfer-only="true"
            show-view-client
            :loading="loading"
            @open-move-site-modal="isTransferDialogOpen = true"
            @open-transfer="isTransferDialogOpen = true"
            @view-client="handleViewClient"
          />
        </section>

        <!-- Section 3: Product Info, Blueprint & Codification -->
        <section
          id="section-product"
          class="journal-section mb-6 d-flex flex-column gap-6"
        >
          <EquipmentClassificationSection
            :form="form"
            :selected-blueprint="selectedBlueprint"
            :selected-class="selectedClass"
            :brands-list="brandsList"
            :selected-option-badges="selectedOptionBadges"
            :blueprint-intervention-cycles="blueprintInterventionCycles"
            :is-custom-model-name="isCustomModelName"
            :is-read-only="isReadOnly"
            :loading="loading"
            @update:field="updateField"
            @update:is-custom-model-name="val => { isCustomModelName = val }"
            @open-class-blueprint-modal="isClassBlueprintModalVisible = true"
            @sync-model-name="updateModelNameFromOptions"
            @view-guideline="handleViewGuideline"
          />

          <EquipmentCodificationSection
            :form="form"
            :loading="loading"
            :is-new="false"
            :is-read-only="isReadOnly"
            @update:field="updateField"
            @open-qr-scanner="isQrScannerOpen = true"
            @open-replace-qr="isReplaceQrDialogOpen = true"
            @download-qr="handleDownloadQr"
          />
        </section>

        <!-- Section 4: Operational Status, Compliance & Lifecycle Dates -->
        <section
          id="section-dates"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="$t('Status, Compliance & Lifecycle Dates')"
            :subtitle="$t('Operational status matrix, criticality rating, commissioning dates, and projected timeline')"
            icon="tabler-calendar-event"
            icon-color="warning"
          >
            <div class="d-flex flex-column gap-6">
              <EquipmentStatusTiles
                :lifecycle-status="form.lifecycle_status"
                :condition-status="form.condition_status"
                :compliance-status="form.compliance_status"
                :criticality="form.criticality"
                :disabled="isReadOnly"
                @update:lifecycle-status="v => { form.lifecycle_status = v }"
                @update:condition-status="v => { form.condition_status = v }"
                @update:compliance-status="v => { form.compliance_status = v }"
                @update:criticality="v => { form.criticality = v }"
              />

              <VDivider />

              <EquipmentLifecycleDatesSection
                :form="form"
                :blueprint-details="loadedBlueprintDetails || selectedBlueprint"
                :loading="loading"
                @update:field="updateField"
              />
            </div>
          </WpSectionCard>
        </section>

        <!-- Section 5: Technical Specifications, Certifications & Notes -->
        <section
          id="section-specs"
          class="journal-section mb-6"
        >
          <EquipmentSpecsKvEditor
            :specs="form.specs"
            :certifications="form.certifications"
            :description="form.description"
            :notes="form.notes"
            :disabled="isReadOnly"
            @update:specs="v => { form.specs = v }"
            @update:certifications="v => { form.certifications = v }"
            @update:description="v => { form.description = v }"
            @update:notes="v => { form.notes = v }"
          />
        </section>

        <!-- Section 6: Equipment Photography, Certificates & Documents -->
        <section
          id="section-media"
          class="journal-section mb-6"
        >
          <WpSectionCard
            :title="`${$t('Equipment Photography & Documents')} (${(fetchedEquipment.photos?.length || 0) + allEquipmentDocuments.length})`"
            :subtitle="$t('Field inspection photography, compliance certificates, technical manuals, and audit reports')"
            icon="tabler-photo"
            icon-color="success"
          >
            <template
              v-if="!smAndDown && !isReadOnly"
              #actions
            >
              <div class="d-flex align-center gap-2">
                <VBtn
                  size="small"
                  color="success"
                  prepend-icon="tabler-camera"
                  @click="isImageDialogOpen = true"
                >
                  {{ $t('Add Photo') }}
                </VBtn>
                <VBtn
                  size="small"
                  color="primary"
                  prepend-icon="tabler-scan"
                  @click="mediaGalleryRef?.openUploadDocDialog()"
                >
                  {{ $t('Scan / Upload Document') }}
                </VBtn>
              </div>
            </template>

            <EquipmentMediaGallery
              ref="mediaGalleryRef"
              :equipment-id="targetEquipmentId"
              :photos="fetchedEquipment.photos || []"
              :documents="fetchedEquipment.documents || []"
              :certificates="fetchedEquipment.certificates || []"
              :is-new="false"
              :read-only="isReadOnly"
              @refresh="fetchEquipment"
            />
          </WpSectionCard>
        </section>

        <!-- Section 7: Movement & Audit History -->
        <section
          id="section-history"
          class="journal-section mb-6"
        >
          <EquipmentHistorySection
            :status-histories="statusHistories"
            :location-histories="locationHistories"
            :transfer-histories="transferHistories"
            :qr-histories="qrHistories"
            :show-actions="!isReadOnly"
            @open-transfer="isTransferDialogOpen = true"
            @open-replace-qr="isReplaceQrDialogOpen = true"
          />
        </section>
      </main>
    </div>

    <!-- Sticky Unsaved Changes Bar -->
    <EquipmentEditUnsavedBar
      :is-read-only="isReadOnly"
      :is-dirty="isDirty"
      :is-new="false"
      :saving="saving"
      :sm-and-down="smAndDown"
      @discard="fetchEquipment"
      @save="handleSave"
    />

    <!-- Mobile Quick Actions Floating Launcher & Sheet -->
    <div
      v-if="smAndDown && fetchedEquipment && !isReadOnly"
      class="equipment-edit-quick-actions-launcher"
    >
      <VFab
        color="primary"
        icon="tabler-bolt"
        rounded="lg"
        size="default"
        class="elevation-3"
        :aria-label="$t('Quick Actions')"
        @click="quickActionsOpen = true"
      />
    </div>

    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Contextual Modals & Sub-dialogs -->
    <EquipmentEditModals
      v-model:client-search-modal="isClientSearchModalVisible"
      v-model:site-area-modal="isSiteAreaModalVisible"
      v-model:class-blueprint-modal="isClassBlueprintModalVisible"
      v-model:qr-scanner-modal="isQrScannerOpen"
      v-model:replace-qr-modal="isReplaceQrDialogOpen"
      v-model:transfer-modal="isTransferDialogOpen"
      v-model:client-view-modal="isClientViewDialogOpen"
      v-model:guideline-modal="isGuidelineDialogOpen"
      v-model:image-modal="isImageDialogOpen"
      v-model:media-viewer-modal="isMediaViewerOpen"
      v-model:delete-modal="isDeleteDialogVisible"
      v-model:unsaved-changes-modal="isUnsavedChangesDialogVisible"
      :form="form"
      :fetched-equipment="fetchedEquipment"
      :selected-client="selectedClient"
      :sites-list="sitesList"
      :equipment-classes="equipmentClasses"
      :blueprints="blueprints"
      :option-selections="optionSelections"
      :selected-guideline-for-view="selectedGuidelineForView"
      :selected-client-for-view="selectedClientForView"
      :loading-client-view="loadingClientView"
      :media-viewer-items="mediaViewerItems"
      :media-viewer-initial-index="mediaViewerInitialIndex"
      :media-viewer-title="mediaViewerTitle"
      @client-picked="onClientPicked"
      @site-area-confirm="handleSiteAreaConfirm"
      @class-blueprint-confirm="handleClassBlueprintConfirm"
      @qr-scanned="handleQrScanned"
      @equipment-reload="fetchEquipment"
      @save-photo="handleSavePhotoFromDialog"
      @delete-confirm="handleDelete"
      @confirm-leave="confirmLeave"
      @cancel-leave="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
.client-equipment-edit-page {
  min-block-size: 100vh;
  overflow-x: clip;
  padding-block-end: 2rem;
}

.equipment-edit-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 959px) {
    flex-direction: column;
    padding-block-end: calc(108px + env(safe-area-inset-bottom, 0px));
  }
}

.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.25rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 3.5rem;
}

.journal-section {
  min-inline-size: 0;
  scroll-margin-block-start: 160px;

  @media (max-width: 959px) {
    scroll-margin-block-start: 130px;
  }
}

.equipment-edit-quick-actions-launcher {
  position: fixed !important;
  z-index: 1001;
  inset-block-end: calc(84px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 16px;

  :deep(.v-fab) {
    margin: 0 !important;
  }
}

@media (max-width: 959px) {
  .equipment-edit-quick-actions-launcher {
    inset-inline-end: 16px;
  }

  :deep(.scroll-to-top) {
    inset-block-end: calc(144px + env(safe-area-inset-bottom, 0px)) !important;
    inset-inline-end: 16px !important;
  }
}
</style>
