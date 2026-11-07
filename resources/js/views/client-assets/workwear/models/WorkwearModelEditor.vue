<script setup>
/* eslint-disable camelcase */
/**
 * WorkwearModelEditor
 * ─────────────────────────────────────────────────────────────────────────────
 * Journal-style editor for Workwear Models aligned with Client Site & Equipment
 * edit pages: WpJournalNav (desktop sticky sidebar + mobile bottom nav),
 * top WpKpiGroup, mobile-first layout, and clean scroll behavior.
 */
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpDocumentDialog,
  WpErrorBanner,
  WpImageDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMediaViewerDialog,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useWorkwearModelEdit } from '@/composables/client-assets/workwear/useWorkwearModelEdit'
import { resolveSilhouetteDisplay } from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import WorkwearModelBioSection from '@/views/client-assets/workwear/models/components/WorkwearModelBioSection.vue'
import WorkwearModelGarmentsSection from '@/views/client-assets/workwear/models/components/WorkwearModelGarmentsSection.vue'
import WorkwearModelItemsSection from '@/views/client-assets/workwear/models/components/WorkwearModelItemsSection.vue'
import WorkwearModelMediaSection from '@/views/client-assets/workwear/models/components/WorkwearModelMediaSection.vue'
import WorkwearModelNavSidebar from '@/views/client-assets/workwear/models/components/WorkwearModelNavSidebar.vue'
import WorkwearModelNotesSection from '@/views/client-assets/workwear/models/components/WorkwearModelNotesSection.vue'
import WorkwearModelOptionsSection from '@/views/client-assets/workwear/models/components/WorkwearModelOptionsSection.vue'
import WorkwearModelStudioSection from '@/views/client-assets/workwear/models/components/WorkwearModelStudioSection.vue'
import WorkwearModelVersionsSection from '@/views/client-assets/workwear/models/components/WorkwearModelVersionsSection.vue'
import WorkwearSnapshotDialog from '@/views/client-assets/workwear/models/components/WorkwearSnapshotDialog.vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const modelId = computed(() => props.id || route.params.id)
const { smAndDown } = useDisplay()

const listRoute = { name: 'client-assets-workwear-list', query: { tab: 'models' } }

const activeSection = ref('overview')
const isDeleteDialogVisible = ref(false)
const quickActionsSheetOpen = ref(false)

const {
  loading,
  saving,
  error,
  modelData,
  items,
  options,
  versions,
  statusOptions,
  isDirty,
  fetchModel,
  handleUpdate,
  handleDelete,

  // Media
  mediaCollectionToUpload,
  isUploadingMedia,
  uploadMediaFile,
  handleDeleteMedia,
  handleDownloadMedia,

  // Versions
  isVersionDialogOpen,
  isCreatingVersion,
  handleCreateVersion,
} = useWorkwearModelEdit(modelId)

const { unsavedDialogVisible, confirmLeave } = useUnsavedChangesGuard(isDirty)

/* ── Navigation model ─────────────────────────────────────────────────── */
const countOf = list => (Array.isArray(list) ? list.length : 0)

const sections = computed(() => {
  const m = modelData.value || {}
  const optionCount = countOf(m.catalog_item?.options) || countOf(options.value)

  return [
    { id: 'overview', label: t('Overview & Assets'), icon: 'tabler-id' },
    { id: 'studio', label: t('Garment Studio (2D)'), icon: 'tabler-vector-bezier-2', badge: countOf(m.placements) },
    { id: 'garments', label: t('Standards & Specs'), icon: 'tabler-certificate', badge: countOf(m.standards) },
    { id: 'options', label: t('Catalog Options'), icon: 'tabler-adjustments', badge: optionCount },
    { id: 'media', label: t('Branding & Media'), icon: 'tabler-photo', badge: countOf(m.logos) + countOf(m.design_files) },
    { id: 'items', label: t('Physical Items'), icon: 'tabler-hanger', badge: countOf(items.value) },
    { id: 'versions', label: t('Version History'), icon: 'tabler-history', badge: countOf(versions.value) },
    { id: 'notes', label: t('Internal Notes'), icon: 'tabler-notes' },
  ]
})

const silhouetteSummary = computed(() =>
  resolveSilhouetteDisplay(modelData.value, { locale: locale.value, t }),
)

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Client Assets'), to: listRoute, icon: 'tabler-shirt' },
  { title: t('Workwear Models'), to: listRoute, icon: 'tabler-list' },
  { title: modelData.value?.name || modelData.value?.model_ref || t('Edit Model'), icon: 'tabler-edit', disabled: true },
])

const goBack = () => router.push(listRoute)

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

/* ── Media (logos / design files) ─────────────────────────────────────── */
const isImageDialogOpen = ref(false)
const isDocDialogOpen = ref(false)
const isViewerOpen = ref(false)
const viewerFile = ref(null)

const handleTriggerUpload = collection => {
  mediaCollectionToUpload.value = collection
  if (collection === 'logos') isImageDialogOpen.value = true
  else isDocDialogOpen.value = true
}

const handleMediaSaved = async payload => {
  const collection = mediaCollectionToUpload.value || 'logos'

  await uploadMediaFile(payload, collection, payload)
  isImageDialogOpen.value = false
  isDocDialogOpen.value = false
}

const handleSectionUploadMedia = async ({ payload, collection }) => {
  mediaCollectionToUpload.value = collection || 'logos'
  await uploadMediaFile(payload, mediaCollectionToUpload.value, payload)
}

const handlePreviewMedia = media => {
  viewerFile.value = {
    title: media.title || t('Workwear Media'),
    url: media.url,
    downloadUrl: media.url,
    mimeType: media.mimeType,
  }
  isViewerOpen.value = true
}

/* ── Quick actions (mobile FAB) ───────────────────────────────────────── */
const quickActions = computed(() => [
  {
    id: 'save-model',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'success',
    disabled: saving.value || !isDirty.value,
    onClick: handleUpdate,
  },
  {
    id: 'open-studio',
    title: t('Garment Studio'),
    icon: 'tabler-vector-bezier-2',
    color: 'primary',
    onClick: () => scrollToSection('studio'),
  },
  {
    id: 'open-standards',
    title: t('Standards & Specs'),
    icon: 'tabler-certificate',
    color: 'info',
    onClick: () => scrollToSection('garments'),
  },
  {
    id: 'create-snapshot',
    title: t('Create Snapshot'),
    icon: 'tabler-camera',
    color: 'info',
    onClick: () => { isVersionDialogOpen.value = true },
  },
  {
    id: 'back-to-list',
    title: t('Back to List'),
    icon: 'tabler-arrow-left',
    color: 'secondary',
    onClick: goBack,
  },
  {
    id: 'delete-model',
    title: t('Delete Model'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  },
])

/* ── Ctrl/Cmd + S ─────────────────────────────────────────────────────── */
const onSaveShortcut = event => {
  if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 's') return
  event.preventDefault()
  if (isDirty.value && !saving.value) handleUpdate()
}

onMounted(async () => {
  window.addEventListener('keydown', onSaveShortcut)
  await fetchModel()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onSaveShortcut)
})
</script>

<template>
  <div class="workwear-edit-page">
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- Loading -->
    <VSkeletonLoader
      v-if="loading && !modelData"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- Error -->
    <WpErrorBanner
      v-else-if="error"
      :error="error"
      @retry="fetchModel"
    />

    <!-- Journal -->
    <div
      v-else-if="modelData"
      class="journal-layout"
    >
      <!-- Desktop Sticky Sidebar + Mobile Bottom Nav via WpJournalNav -->
      <WorkwearModelNavSidebar
        v-model="activeSection"
        :model-data="modelData"
        :sections="sections"
        :active-section="activeSection"
        :saving="saving"
        :is-dirty="isDirty"
        @save="handleUpdate"
        @create-snapshot="isVersionDialogOpen = true"
        @back="goBack"
        @delete="isDeleteDialogVisible = true"
      />

      <main class="journal-feed">
        <!-- Top KPI Cards (Standard across Client Assets Edit Pages) -->
        <WpKpiGroup
          :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
          class="workwear-top-kpis"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="$t('2D Silhouette')"
                :value="silhouetteSummary.shortTitle || silhouetteSummary.itemTitle"
                icon="tabler-shirt"
                icon-color="primary"
                clickable
                @click="scrollToSection('studio')"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="$t('Custom Placements')"
                :value="countOf(modelData.placements)"
                icon="tabler-vector-bezier-2"
                icon-color="info"
                clickable
                @click="scrollToSection('studio')"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="$t('PPE Standards')"
                :value="countOf(modelData.standards)"
                icon="tabler-shield-check"
                icon-color="success"
                clickable
                @click="scrollToSection('garments')"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="$t('Physical Items')"
                :value="items.length"
                icon="tabler-hanger"
                icon-color="warning"
                clickable
                @click="scrollToSection('items')"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <WorkwearModelBioSection
          :model-data="modelData"
          :status-options="statusOptions"
          :items-count="items.length"
        />

        <WorkwearModelStudioSection
          :model-data="modelData"
          :logos="modelData.logos"
          :upload-media-file="uploadMediaFile"
          @trigger-upload="handleTriggerUpload"
        />

        <WorkwearModelGarmentsSection :model-data="modelData" />

        <WorkwearModelOptionsSection
          :model-data="modelData"
          :options="options"
        />

        <WorkwearModelMediaSection
          :logos="modelData.logos"
          :design-files="modelData.design_files"
          :placements="modelData.placements"
          :is-uploading-media="isUploadingMedia"
          :media-collection-to-upload="mediaCollectionToUpload"
          @trigger-upload="handleTriggerUpload"
          @upload-media="handleSectionUploadMedia"
          @delete-media="handleDeleteMedia"
          @download-media="handleDownloadMedia"
          @preview-media="handlePreviewMedia"
        />

        <WorkwearModelItemsSection :items="items" />

        <WorkwearModelVersionsSection
          :versions="versions"
          @create-snapshot="isVersionDialogOpen = true"
        />

        <WorkwearModelNotesSection :model-data="modelData" />
      </main>
    </div>

    <!-- Mobile Quick Actions Floating Launcher & Sheet -->
    <div
      v-if="smAndDown && !loading && modelData"
      class="workwear-edit-quick-actions-launcher"
    >
      <VFab
        color="primary"
        icon="tabler-bolt"
        rounded="lg"
        size="default"
        class="elevation-3"
        :aria-label="$t('Quick Actions')"
        @click="quickActionsSheetOpen = true"
      />
    </div>

    <AppQuickActionsSheet
      v-model="quickActionsSheetOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Dialogs -->
    <WorkwearSnapshotDialog
      v-model="isVersionDialogOpen"
      :loading="isCreatingVersion"
      :has-unsaved-changes="isDirty"
      @save="handleCreateVersion"
    />

    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('Delete Workwear Model')"
      :message="$t('Are you sure you want to delete this workwear model? Models with linked physical items cannot be deleted.')"
      :item-name="modelData?.name"
      @confirm="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="unsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="unsavedDialogVisible = false"
    />

    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('Upload Placement Logo')"
      show-title-input
      @save="handleMediaSaved"
    />

    <WpDocumentDialog
      v-model="isDocDialogOpen"
      :title="$t('Upload Design Spec or Certificate')"
      preset="compact"
      @save="handleMediaSaved"
    />

    <WpMediaViewerDialog
      v-model:model-value="isViewerOpen"
      :file="viewerFile"
    />
  </div>
</template>

<style lang="scss" scoped>
.workwear-edit-page {
  min-block-size: 100vh;
  overflow-x: clip;
  padding-block-end: 2rem;
}

.journal-layout {
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

.workwear-edit-quick-actions-launcher {
  position: fixed !important;
  z-index: 1001;
  inset-block-end: calc(84px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 16px;

  :deep(.v-fab) {
    margin: 0 !important;
  }
}
</style>
