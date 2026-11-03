<script setup>
/* eslint-disable camelcase */
import { WpDocumentList, WpMediaGallery } from '@/components/shared'
import { normalizeMediaUrl } from '@/composables/documents/documentFiles'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  catalogItemId: { type: String, required: true },
  title: { type: String, required: true },
  collection: { type: String, required: true },
  icon: { type: String, default: 'tabler-file' },
  mediaList: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const store = useCatalogItemStore()
const { showMessage } = useAppSnackbar()

const mediaGalleryRef = ref(null)
const docListRef = ref(null)
const uploading = ref(false)

const normalizedGalleryItems = computed(() =>
  (props.mediaList || []).map(media => ({
    ...media,
    url: normalizeMediaUrl(media.url || media.original_url || media.thumbnail_url || ''),
    thumb_url: normalizeMediaUrl(media.thumb_url || media.thumbnail_url || media.url || ''),
    preview_url: normalizeMediaUrl(media.preview_url || media.url || media.original_url || ''),
    title: media.custom_properties?.title || media.title || media.name || media.file_name || t('Image'),
  })),
)

const documentTypeOptions = computed(() => {
  if (props.collection === 'certificates') {
    return [
      { value: 'ce_certificate', title: t('CE / Conformity Certificate') },
      { value: 'iso_standard', title: t('ISO / EN Standard Certificate') },
      { value: 'test_report', title: t('Laboratory Test Report') },
      { value: 'safety_datasheet', title: t('Safety Data Sheet (SDS / MSDS)') },
      { value: 'warranty', title: t('Warranty Certificate') },
      { value: 'other', title: t('Other Certificate') },
    ]
  }

  return [
    { value: 'datasheet', title: t('Technical Datasheet') },
    { value: 'manual', title: t('User / Installation Manual') },
    { value: 'brochure', title: t('Product Brochure') },
    { value: 'specification', title: t('Engineering Specification') },
    { value: 'other', title: t('Other Document') },
  ]
})

// ── Handlers ────────────────────────────────────────────────────────────
const handleOpenAdd = () => {
  if (props.collection === 'images') {
    mediaGalleryRef.value?.openUploadDialog()
  } else {
    docListRef.value?.handleOpenAdd()
  }
}

const handleImageSave = async payload => {
  const filesToUpload = Array.isArray(payload?.files) && payload.files.length
    ? payload.files
    : (payload?.file ? [payload.file] : (payload instanceof File || payload instanceof Blob ? [payload] : []))

  if (!filesToUpload.length) {
    showMessage(t('No media file selected'), 'error')

    return
  }

  uploading.value = true
  try {
    for (let i = 0; i < filesToUpload.length; i++) {
      const rawItem = filesToUpload[i]
      const file = rawItem instanceof File || rawItem instanceof Blob ? rawItem : rawItem?.file
      if (!file) continue

      const formData = new FormData()

      formData.append('collection', 'images')

      const ext = file.type ? file.type.split('/')[1]?.split(';')[0]?.replace('jpeg', 'jpg') || 'jpg' : 'jpg'
      const itemTitle = rawItem?.title || (filesToUpload.length === 1 ? payload?.title : '')
      const safeTitle = itemTitle ? itemTitle.replace(/[^\w-]/g, '_') : null
      const fileName = file.name || (safeTitle ? `${safeTitle}.${ext}` : `media_${Date.now()}_${i}.${ext}`)

      formData.append('file', file, fileName)
      if (itemTitle) formData.append('title', itemTitle)

      await store.uploadMedia(props.catalogItemId, formData)
    }

    showMessage(t('Media uploaded successfully'), 'success')
    emit('refresh')
  } catch (error) {
    console.error('Error uploading catalog media:', error)
    showMessage(error?.data?.message || t('Failed to upload media'), 'error')
  } finally {
    uploading.value = false
  }
}

const handleDocumentSave = async payload => {
  const file = payload instanceof File || payload instanceof Blob ? payload : payload?.file
  if (!file) {
    showMessage(t('No document file selected'), 'error')

    return
  }

  uploading.value = true
  try {
    const formData = new FormData()

    formData.append('collection', props.collection)

    const fileName = file.name || `doc_${Date.now()}.pdf`

    formData.append('file', file, fileName)
    if (payload.title) formData.append('title', payload.title)
    if (payload.reference_number || payload.reference) {
      formData.append('reference', payload.reference_number || payload.reference)
    }
    if (payload.type || payload.document_type || payload.category) {
      formData.append('type', payload.type || payload.document_type || payload.category)
    }
    if (payload.notes) formData.append('notes', payload.notes)
    if (payload.expiration_date || payload.expiry_date) {
      formData.append('expires_at', payload.expiration_date || payload.expiry_date)
    }

    await store.uploadMedia(props.catalogItemId, formData)
    showMessage(t('Document uploaded successfully'), 'success')
    docListRef.value?.closeDialog()
    emit('refresh')
  } catch (error) {
    console.error('Error uploading document:', error)
    showMessage(error?.data?.message || t('Failed to upload document'), 'error')
  } finally {
    uploading.value = false
  }
}

const handleDelete = async itemOrId => {
  const mediaId = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
  if (!mediaId) return

  try {
    await store.deleteMedia(props.catalogItemId, mediaId)
    showMessage(t('File deleted successfully'), 'success')
    emit('refresh')
  } catch (error) {
    console.error('Error deleting media:', error)
    showMessage(t('Failed to delete file'), 'error')
  }
}

const handleDownload = async media => {
  if (!media?.id) return
  try {
    const downloadUrl = `/catalog/items/${props.catalogItemId}/media/${media.id}/download`
    const filename = media.file_name || media.title || 'download'

    await store.downloadMedia(downloadUrl, filename)
    showMessage(t('Download started'), 'success')
  } catch {
    showMessage(t('Failed to download file'), 'error')
  }
}

defineExpose({
  handleOpenAdd,
})
</script>

<template>
  <VCard class="mb-6 glass-panel">
    <VCardText>
      <!-- 👉 Images & Videos Gallery -->
      <WpMediaGallery
        v-if="collection === 'images'"
        ref="mediaGalleryRef"
        :items="normalizedGalleryItems"
        :title="$t(title)"
        :icon="icon"
        :loading="uploading"
        :read-only="readOnly"
        :can-set-main="false"
        show-title-input
        @upload="handleImageSave"
        @delete="handleDelete"
        @error="err => showMessage(err?.message || $t('Media upload failed'), 'error')"
      />

      <!-- 👉 Technical Documents & Certificates Attachment List -->
      <WpDocumentList
        v-else
        ref="docListRef"
        :documents="mediaList"
        :title="$t(title)"
        :icon="icon"
        compact-header
        :add-label="collection === 'certificates' ? $t('Add Certificate') : $t('Add Document')"
        :preset="collection === 'certificates' ? 'certificate' : 'technical'"
        :document-types="documentTypeOptions"
        :loading="uploading"
        :saving="uploading"
        :read-only="readOnly"
        :can-edit="false"
        :auto-close="false"
        :empty-title="$t(`No ${title.toLowerCase()} uploaded yet`)"
        @save="handleDocumentSave"
        @download="handleDownload"
        @delete="handleDelete"
      />
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}
</style>
