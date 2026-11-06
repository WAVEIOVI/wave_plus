<script setup>
import {
  WpDocumentList,
  WpMediaGallery,
  WpSectionCard,
} from '@/components/shared'
import {
  equipmentDocumentCategories as documentCategories,
} from '@/composables/client-assets/equipment/useEquipmentConstants'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  equipmentId: { type: [String, Number], default: null },
  photos: { type: Array, default: () => [] },
  documents: { type: Array, default: () => [] },
  certificates: { type: Array, default: () => [] },
  isNew: { type: Boolean, default: false },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const store = useClientAssetStore()
const { showSuccess, showError, showWarning } = useAppSnackbar()

const uploadingPhoto = ref(false)
const uploadingDocument = ref(false)
const galleryRef = ref(null)
const docListRef = ref(null)

const allDocuments = computed(() => [
  ...(props.documents || []).map(d => ({
    ...d,
    _kind: 'document',
    category: d.category || 'general',
  })),
  ...(props.certificates || []).map(d => ({
    ...d,
    _kind: 'certificate',
    category: d.category || 'certificate',
  })),
])

const handleGalleryUpload = async payload => {
  const fileList = Array.isArray(payload?.files) && payload.files.length
    ? payload.files.map(item => (item instanceof File || item instanceof Blob ? item : item?.file)).filter(Boolean)
    : [payload instanceof File || payload instanceof Blob ? payload : payload?.file].filter(Boolean)

  if (!fileList.length || !props.equipmentId) return

  uploadingPhoto.value = true
  try {
    for (let i = 0; i < fileList.length; i++) {
      const file = fileList[i]
      const formData = new FormData()
      const fileName = file.name || `photo_${Date.now()}_${i + 1}.jpg`

      formData.append('collection', 'photos')
      formData.append('file', file, fileName)
      if (payload?.title) formData.append('title', payload.title)
      if (!props.photos?.length && i === 0) formData.append('is_main', '1')

      await store.uploadEquipmentMedia(props.equipmentId, formData)
    }
    snackbarStore.showMessage(t('Media uploaded successfully'), 'success')
    emit('refresh')
  }
  catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('Failed to upload media'), 'error')
  }
  finally {
    uploadingPhoto.value = false
  }
}

const handleSetMainPhoto = async itemOrId => {
  const mediaId = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
  if (!mediaId || !props.equipmentId) return

  try {
    await store.setEquipmentMainPhoto(props.equipmentId, mediaId)
    showSuccess(t('Main photo updated'))
    emit('refresh')
  }
  catch (err) {
    showError(t('Failed to update main photo'))
  }
}

const handleDeleteMedia = async (kind, itemOrId) => {
  const mediaId = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
  if (!mediaId || !props.equipmentId) return

  try {
    await store.deleteEquipmentMedia(props.equipmentId, mediaId)
    showSuccess(kind === 'photo' ? t('Photo deleted') : t('Document deleted'))
    emit('refresh')
  }
  catch (err) {
    showError(
      kind === 'photo' ? t('Failed to delete photo') : t('Failed to delete document'),
    )
  }
}

const handleUploadDocumentFromDialog = async payload => {
  const items = payload?.files?.length ? payload.files : (payload?.file ? [payload] : [])
  if (!items.length || !props.equipmentId) {
    showWarning(t('Please select a file to upload'))

    return
  }

  uploadingDocument.value = true
  try {
    for (const item of items) {
      const file = item.file || item
      const category = item.category || item.type || payload.category || payload.type || 'general'
      const collection = category === 'certificate' ? 'certificates' : (payload.collection || 'documents')
      const fileName = file.name || `${item.title || payload.title || 'document'}.pdf`
      const formData = new FormData()

      formData.append('collection', collection)
      formData.append('file', file, fileName)
      if (item.title || payload.title) formData.append('title', item.title || payload.title)
      if (category) formData.append('category', category)
      if (item.reference || payload.reference) formData.append('reference', item.reference || payload.reference)
      if (item.notes || payload.notes) formData.append('notes', item.notes || payload.notes)

      await store.uploadEquipmentMedia(props.equipmentId, formData)
    }

    showSuccess(
      items.length > 1 ? t('Documents uploaded successfully') : t('Document uploaded successfully'),
    )
    docListRef.value?.closeDialog()
    emit('refresh')
  }
  catch (err) {
    console.error(err)
    showError(t('Failed to upload document'))
  }
  finally {
    uploadingDocument.value = false
  }
}

const handleDownloadDocument = async doc => {
  if (!doc?.id || !props.equipmentId) return
  try {
    await store.downloadEquipmentMedia(props.equipmentId, doc.id, doc.file_name || doc.title || 'document')
  }
  catch (err) {
    showError(t('Failed to download document'))
  }
}

const openUploadDocDialog = () => {
  docListRef.value?.handleOpenAdd()
}

defineExpose({
  openUploadDocDialog,
})
</script>

<template>
  <WpSectionCard
    :title="t('Equipment Media & Documents')"
    :subtitle="t('Photos, inspection captures, compliance certificates, and technical attachments')"
    icon="tabler-photo"
    icon-color="primary"
  >
    <VAlert
      v-if="isNew"
      type="info"
      variant="tonal"
      class="mb-0"
    >
      {{ t('Save the equipment first to upload photos and documents.') }}
    </VAlert>

    <template v-else>
      <!-- Shared Media Gallery for Equipment Photos & Video Recordings -->
      <WpMediaGallery
        ref="galleryRef"
        :items="photos"
        :title="t('Equipment Photos')"
        :subtitle="t('Equipment photos, inspection captures, and video recordings')"
        :read-only="readOnly"
        :can-upload="!readOnly"
        :can-capture-photo="!readOnly"
        :can-record-video="!readOnly"
        :can-set-main="!readOnly"
        :can-delete="!readOnly"
        :loading="uploadingPhoto"
        class="mb-6"
        @upload="handleGalleryUpload"
        @set-main="handleSetMainPhoto"
        @delete="item => handleDeleteMedia('photo', item)"
      />

      <VDivider class="my-5" />

      <!-- Standardized Attachment List for Compliance Certificates & Technical Documents -->
      <WpDocumentList
        ref="docListRef"
        :documents="allDocuments"
        :title="t('Compliance Certificates & Technical Documents')"
        :subtitle="t('Inspection certificates, user manuals, calibration sheets, and compliance records')"
        icon="tabler-file-certificate"
        :add-label="t('Scan / Upload Document')"
        :empty-text="t('No compliance certificates or technical documents uploaded yet.')"
        :document-types="documentCategories"
        :loading="uploadingDocument"
        :saving="uploadingDocument"
        :read-only="readOnly"
        :can-edit="false"
        :auto-close="false"
        compact-header
        @save="handleUploadDocumentFromDialog"
        @download="handleDownloadDocument"
        @delete="docId => handleDeleteMedia('document', docId)"
      />
    </template>
  </WpSectionCard>
</template>
