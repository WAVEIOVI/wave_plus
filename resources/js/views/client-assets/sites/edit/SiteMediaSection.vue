<script setup>
/* eslint-disable camelcase */
import { WpDocumentList, WpMediaGallery, WpSectionCard } from '@/components/shared'
import { documentCategories } from '@/composables/client-assets/sites/useSiteConstants'
import { ref } from 'vue'
import { useDisplay } from 'vuetify'

defineProps({
  photos: { type: Array, default: () => [] },
  documents: { type: Array, default: () => [] },
  uploadingPhoto: { type: Boolean, default: false },
  uploadingDoc: { type: Boolean, default: false },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'openPhotoDialog',
  'openUploadDocDialog',
  'uploadPhoto',
  'uploadDoc',
  'previewPhoto',
  'previewDoc',
  'setMainPhoto',
  'deletePhoto',
  'downloadDoc',
  'deleteDoc',
])

const { smAndDown } = useDisplay()
const mediaGalleryRef = ref(null)
const docListRef = ref(null)

function openPhotoDialog() {
  if (mediaGalleryRef.value?.openUploadDialog) {
    mediaGalleryRef.value.openUploadDialog()
  } else {
    emit('openPhotoDialog')
  }
}

function openUploadDocDialog() {
  if (docListRef.value?.handleOpenAdd) {
    docListRef.value.handleOpenAdd()
  } else {
    emit('openUploadDocDialog')
  }
}

function handleSaveDoc(payload) {
  emit('uploadDoc', payload, () => docListRef.value?.closeDialog())
}

defineExpose({
  openPhotoDialog,
  openUploadDocDialog,
  closeDocDialog: () => docListRef.value?.closeDialog(),
})
</script>

<template>
  <section
    id="section-media"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="`${$t('Facility Photography & Documents')} (${(photos.length || 0) + documents.length})`"
      :subtitle="$t('Building photography, floor plans, evacuation schemes, compliance certificates, and specs')"
      icon="tabler-photo"
      icon-color="success"
    >
      <template #actions>
        <div
          v-if="!smAndDown && !readOnly"
          class="d-flex align-center gap-2"
        >
          <VBtn
            size="small"
            color="success"
            variant="tonal"
            prepend-icon="tabler-camera"
            @click="openPhotoDialog"
          >
            {{ $t('Add Photo') }}
          </VBtn>
          <VBtn
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-scan"
            @click="openUploadDocDialog"
          >
            {{ $t('Scan / Upload Document') }}
          </VBtn>
        </div>
      </template>

      <!-- Photos & Videos Gallery -->
      <div class="mb-6">
        <WpMediaGallery
          ref="mediaGalleryRef"
          :items="photos"
          :title="$t('Facility Photography')"
          icon="tabler-camera"
          :loading="uploadingPhoto"
          :read-only="readOnly"
          show-title-input
          :empty-title="$t('No facility photos uploaded yet')"
          @upload="payload => emit('uploadPhoto', payload)"
          @set-main="item => emit('setMainPhoto', item?.id ?? item)"
          @delete="item => emit('deletePhoto', item?.id ?? item, true)"
          @preview="item => emit('previewPhoto', item)"
        />
      </div>

      <VDivider class="my-4" />

      <!-- Floor Plans & Technical Documents Attachment List -->
      <div>
        <WpDocumentList
          ref="docListRef"
          :documents="documents"
          :title="$t('Floor Plans & Technical Documents')"
          icon="tabler-file-text"
          compact-header
          :add-label="$t('Scan / Upload Document')"
          preset="compact"
          :document-types="documentCategories"
          :loading="uploadingDoc"
          :saving="uploadingDoc"
          :read-only="readOnly"
          :can-edit="false"
          :empty-title="$t('No floor plans or technical documents attached yet')"
          @save="handleSaveDoc"
          @download="doc => emit('downloadDoc', { id: doc.id, filename: doc.file_name || doc.title })"
          @delete="id => emit('deleteDoc', id?.id ?? id, true)"
        />
      </div>
    </WpSectionCard>
  </section>
</template>
