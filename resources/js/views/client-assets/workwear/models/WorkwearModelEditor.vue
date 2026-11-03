<script setup>
/* eslint-disable camelcase */
/**
 * WorkwearModelEditor
 * ─────────────────────────────────────────────────────────────────────────────
 * Journal-style editor for Workwear Models.
 * Structure follows the Catalog Item Edit page: 240px sticky sidebar,
 * AppBreadcrumb, AppBottomNav, VFab + AppQuickActionsSheet,
 * WpDeleteDialog, WpUnsavedChangesDialog and useUnsavedChangesGuard.
 */
import {
  AppBottomNav,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpDocumentDialog,
  WpImageDialog,
  WpMediaViewerDialog,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useWorkwearModelEdit } from '@/composables/client-assets/workwear/useWorkwearModelEdit'
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const { t } = useI18n()
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

const mobileNavItems = computed(() =>
  sections.value.map(s => ({
    title: s.label,
    icon: s.icon,
    key: s.id,
    value: s.id,
  })),
)

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/dashboard', icon: 'tabler-home' },
  { title: t('Client Assets'), to: listRoute, icon: 'tabler-shirt' },
  { title: t('Workwear Models'), to: listRoute, icon: 'tabler-list' },
  { title: modelData.value?.name || modelData.value?.model_ref || t('Edit Model'), icon: 'tabler-edit' },
])

const goBack = () => router.push(listRoute)

/* ── Scroll spy ───────────────────────────────────────────────────────── */
let observer = null
let scrollLockUntil = 0
const visibleSections = new Set()

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  // Smooth scrolling crosses other sections; keep the clicked one active.
  scrollLockUntil = Date.now() + 800

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const observeSections = () => {
  observer?.disconnect()
  visibleSections.clear()

  observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      const key = entry.target.id.replace('section-', '')

      if (entry.isIntersecting) visibleSections.add(key)
      else visibleSections.delete(key)
    })

    if (Date.now() < scrollLockUntil) return

    // Topmost visible section wins, in page order.
    const first = sections.value.find(s => visibleSections.has(s.id))
    if (first) activeSection.value = first.id
  }, { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' })

  sections.value.forEach(s => {
    const el = document.getElementById(`section-${s.id}`)
    if (el) observer.observe(el)
  })
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
    color: 'primary',
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
  await nextTick()
  observeSections()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onSaveShortcut)
  observer?.disconnect()
  observer = null
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
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-3">
        <span>{{ error }}</span>
        <VBtn
          size="small"
          variant="tonal"
          color="error"
          prepend-icon="tabler-refresh"
          @click="fetchModel"
        >
          {{ $t('Retry') }}
        </VBtn>
      </div>
    </VAlert>

    <!-- Journal -->
    <div
      v-else-if="modelData"
      class="journal-layout"
    >
      <WorkwearModelNavSidebar
        v-if="!smAndDown"
        :model-data="modelData"
        :sections="sections"
        :active-section="activeSection"
        :saving="saving"
        :is-dirty="isDirty"
        @navigate="scrollToSection"
        @save="handleUpdate"
        @create-snapshot="isVersionDialogOpen = true"
        @back="goBack"
        @delete="isDeleteDialogVisible = true"
      />

      <main class="journal-feed">
        <!-- Mobile identity bar -->
        <div
          v-if="smAndDown"
          class="d-flex align-center gap-3 min-w-0 w-100"
        >
          <VAvatar
            rounded
            :size="40"
            color="primary"
            variant="tonal"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-shirt"
              size="22"
            />
          </VAvatar>
          <div class="min-w-0 flex-grow-1">
            <h1 class="text-subtitle-1 font-weight-bold text-truncate mb-0">
              {{ modelData.name || modelData.model_ref }}
            </h1>
            <span class="text-caption font-mono text-disabled d-block text-truncate">{{ modelData.model_ref }}</span>
          </div>
          <VChip
            v-if="isDirty"
            size="small"
            color="warning"
            variant="tonal"
            class="flex-shrink-0"
            role="status"
          >
            {{ $t('Unsaved changes') }}
          </VChip>
        </div>

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

    <!-- Mobile navigation + quick actions -->
    <AppBottomNav
      v-if="smAndDown && !loading && modelData"
      v-model="activeSection"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      @change="item => scrollToSection(item.value)"
    />

    <VFab
      v-if="smAndDown && !loading && modelData"
      class="workwear-edit-quick-actions-launcher"
      color="primary"
      icon="tabler-bolt"
      rounded="lg"
      size="large"
      :aria-label="$t('Quick Actions')"
      @click="quickActionsSheetOpen = true"
    />
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
.journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;
  min-block-size: 100vh;

  @media (max-width: 960px) {
    flex-direction: column;
    // room for the bottom nav and the FAB
    padding-block-end: calc(96px + env(safe-area-inset-bottom, 0px));
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

  @media (max-width: 600px) {
    gap: 1rem;
  }
}

.workwear-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 20px;
}
</style>
