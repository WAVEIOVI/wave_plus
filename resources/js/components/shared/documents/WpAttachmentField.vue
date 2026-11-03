<script setup>
/* eslint-disable camelcase */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WpAttachmentDialog from '@/components/shared/documents/WpAttachmentDialog.vue'
import WpDocumentScanner from '@/components/shared/documents/WpDocumentScanner.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import {
  downloadFromUrl,
  formatDocumentBytes as formatBytes,
  getExtension,
  localiseError,
  matchesAccept,
  resolveFileColor,
  resolveFileIcon,
  resolveFileKind,
  resolveFileMime,
} from '@/composables/documents/documentFiles'
import { useDocumentScanner } from '@/composables/documents/useDocumentScanner'
import { useOpenCv } from '@/composables/documents/useOpenCv'

const props = defineProps({
  modelValue: { type: [File, String, Object], default: null },
  label: { type: String, default: '' },
  category: { type: String, default: 'document' }, // 'document', 'image', 'certificate', 'design', 'video'
  accept: { type: String, default: '' },
  maxSizeMb: { type: Number, default: 20 },
  hint: { type: String, default: '' },
  errorMessages: { type: [String, Array], default: () => [] },
  downloadUrl: { type: String, default: '' },
  allowScan: { type: Boolean, default: false },
  allowDialog: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  density: { type: String, default: 'comfortable' },
})

const emit = defineEmits(['update:modelValue', 'change', 'error', 'remove', 'download'])

const { t } = useI18n()
const { warmUpOpenCv } = useOpenCv()

const fileInputRef = ref(null)
const scannerOpen = ref(false)
const isAttachmentDialogOpen = ref(false)
const isDragging = ref(false)
const isPackaging = ref(false)
const localError = ref('')
const isPreviewOpen = ref(false)
const objectUrlRef = ref('')

const uid = `wp-attachment-${Math.random().toString(36).slice(2, 9)}`

// Scanned pages are collected here, then packaged when the scanner closes.
const { addPage, clearPages, generatePdf, pagesRef } = useDocumentScanner({ maxBytes: props.maxSizeMb * 1024 * 1024 })

const computedAccept = computed(() => {
  if (props.accept) return props.accept

  switch (props.category) {
  case 'image':
    return 'image/jpeg,image/png,image/webp,image/svg+xml'
  case 'certificate':
    return '.pdf,image/jpeg,image/png,image/webp'
  case 'design':
    return '.ai,.eps,.pdf,.svg,.png,.psd'
  case 'video':
    return 'video/mp4,video/webm,video/quicktime,video/avi'
  case 'document':
  default:
    return '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv'
  }
})

const computedHint = computed(() => {
  if (props.hint) return props.hint

  switch (props.category) {
  case 'image':
    return t('wp.file.hint_image', { size: props.maxSizeMb }, `JPEG, PNG, WebP, SVG up to ${props.maxSizeMb}MB`)
  case 'certificate':
    return t('wp.file.hint_certificate', { size: props.maxSizeMb }, `PDF or images up to ${props.maxSizeMb}MB`)
  case 'design':
    return t('wp.file.hint_design', { size: props.maxSizeMb }, `CAD, vector, or PDF files up to ${props.maxSizeMb}MB`)
  case 'video':
    return t('wp.file.hint_video', { size: props.maxSizeMb }, `MP4, WebM, MOV up to ${props.maxSizeMb}MB`)
  case 'document':
  default:
    return t('wp.file.hint_document', { size: props.maxSizeMb }, `PDF, Word, Excel, CSV, TXT up to ${props.maxSizeMb}MB`)
  }
})

const hasFile = computed(() => Boolean(props.modelValue))
const isLocked = computed(() => props.disabled || props.readonly)

const fileName = computed(() => {
  const value = props.modelValue

  if (!value) return ''
  if (value instanceof File) return value.name
  if (typeof value === 'object') return value.file_name || value.name || value.title || t('wp.file.document', 'Document')
  if (typeof value === 'string') return value.split(/[?#]/)[0].split('/').pop() || t('wp.file.document', 'Document')

  return t('wp.file.document', 'Document')
})

const fileMime = computed(() => {
  const value = props.modelValue

  if (value instanceof File) return resolveFileMime(value)
  if (value && typeof value === 'object' && value.mime_type) return String(value.mime_type).toLowerCase()

  return resolveFileMime({ name: fileName.value })
})

const fileKind = computed(() => resolveFileKind(fileMime.value, fileName.value))
const fileIcon = computed(() => resolveFileIcon(fileMime.value, fileName.value))
const fileColor = computed(() => resolveFileColor(fileMime.value, fileName.value))
const isImage = computed(() => fileKind.value === 'image')

const fileSize = computed(() => {
  const value = props.modelValue

  if (value instanceof File) return formatBytes(value.size)
  if (value && typeof value === 'object' && value.size) return formatBytes(value.size)

  return ''
})

/**
 * One object URL per selected File, revoked when it changes or the field is
 * destroyed. (It used to be created inside a computed, so every re-evaluation
 * leaked a new blob URL.)
 */
watch(
  () => props.modelValue,
  value => {
    if (objectUrlRef.value) URL.revokeObjectURL(objectUrlRef.value)
    objectUrlRef.value = value instanceof File ? URL.createObjectURL(value) : ''
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (objectUrlRef.value) URL.revokeObjectURL(objectUrlRef.value)
  clearPages()
})

const previewUrl = computed(() => {
  const value = props.modelValue

  if (!value) return ''
  if (value instanceof File) return objectUrlRef.value
  if (typeof value === 'object') return value.url || value.thumb_url || value.preview_url || ''
  if (typeof value === 'string') return value

  return ''
})

const targetDownloadUrl = computed(() => {
  return props.downloadUrl || (typeof props.modelValue === 'string' ? props.modelValue : props.modelValue?.download_url || props.modelValue?.url || '')
})

const errorText = computed(() => {
  if (localError.value) return localError.value

  return Array.isArray(props.errorMessages) ? props.errorMessages[0] || '' : props.errorMessages || ''
})

const hasError = computed(() => Boolean(errorText.value))

const viewerItem = computed(() => ({
  title: fileName.value,
  url: previewUrl.value || targetDownloadUrl.value,
  download_url: targetDownloadUrl.value,
  mime_type: fileMime.value || (getExtension(fileName.value) === 'pdf' ? 'application/pdf' : 'application/octet-stream'),
  size: props.modelValue instanceof File ? props.modelValue.size : props.modelValue?.size,
}))

function fail(message, error = new Error(message)) {
  localError.value = message
  emit('error', error)
}

function triggerBrowse() {
  if (isLocked.value) return
  localError.value = ''
  fileInputRef.value?.click()
}

function handleFileSelect(event) {
  const file = event.target.files?.[0]

  event.target.value = ''
  if (file) processFile(file)
}

function handleDrop(event) {
  isDragging.value = false
  if (isLocked.value) return

  const file = event.dataTransfer?.files?.[0]

  if (file) processFile(file)
}

function processFile(file) {
  const maxBytes = props.maxSizeMb * 1024 * 1024

  // The native picker filters by `accept`; drag & drop and scans do not, so check here.
  if (!matchesAccept(file, computedAccept.value)) {
    fail(t('wp.file.error_unsupported', { types: computedAccept.value }, 'This file type is not accepted.'))

    return
  }

  if (file.size > maxBytes) {
    fail(t('wp.file.error_too_large', { max: `${props.maxSizeMb} MB` }, `File exceeds ${props.maxSizeMb} MB`))

    return
  }

  localError.value = ''
  emit('update:modelValue', file)
  emit('change', file)
}

function handleRemove(event) {
  event?.stopPropagation()
  localError.value = ''
  emit('update:modelValue', null)
  emit('remove')
}

function handleDownload(event) {
  event?.stopPropagation()
  if (!targetDownloadUrl.value) return
  downloadFromUrl(targetDownloadUrl.value, fileName.value)
  emit('download', targetDownloadUrl.value)
}

function handleOpenPreview(event) {
  event?.stopPropagation()
  if (previewUrl.value || targetDownloadUrl.value) {
    isPreviewOpen.value = true
  }
}

function openScanner() {
  if (isLocked.value) return
  localError.value = ''
  clearPages()
  scannerOpen.value = true
}

/**
 * Documents may be several pages: pages are collected while the scanner is
 * open and merged into ONE PDF when it closes, so MediaValidationService's
 * CATEGORY_DOCUMENT receives an authorised PDF. Other categories take the
 * single scanned image as is.
 */
function handleScanned(payload) {
  if (payload?.file) addPage({ ...payload, source: 'camera_scan' })
}

async function finalizeScan() {
  const pages = [...pagesRef.value]

  if (!pages.length) return

  isPackaging.value = true
  try {
    if (props.category === 'document') {
      const { file } = await generatePdf({ name: `scan-${Date.now()}.pdf` })

      processFile(file)
    } else {
      processFile(pages[0].file)
    }
  } catch (err) {
    console.error('Error packaging scan:', err)
    fail(localiseError(err, t, t('wp.file.error_scan_package', 'Could not package the scanned pages.')), err)
  } finally {
    clearPages()
    isPackaging.value = false
  }
}

watch(scannerOpen, open => {
  if (!open) finalizeScan()
})

function onScannerError(err) {
  emit('error', err)
}

function handleAttachmentDialogSave(payload) {
  if (payload?.file) {
    processFile(payload.file)
  }
  isAttachmentDialogOpen.value = false
}
</script>

<template>
  <div class="wp-attachment-field">
    <!-- Field Label -->
    <label
      v-if="label"
      :for="uid"
      class="v-label text-body-2 font-weight-medium mb-1 d-block"
      :class="{ 'text-error': hasError }"
    >
      {{ label }}
      <span
        v-if="required"
        class="text-error"
        aria-hidden="true"
      >*</span>
    </label>

    <input
      :id="uid"
      ref="fileInputRef"
      type="file"
      :accept="computedAccept"
      class="d-none"
      :disabled="isLocked"
      :aria-describedby="hasError ? `${uid}-error` : undefined"
      @change="handleFileSelect"
    >

    <!-- Empty / Dropzone State -->
    <div
      v-if="!hasFile"
      class="wp-attachment-dropzone border rounded-lg pa-4 text-center cursor-pointer"
      :class="[
        isDragging ? 'wp-attachment-dropzone--dragging' : 'border-dashed',
        hasError ? 'border-error' : '',
        isLocked ? 'wp-attachment-dropzone--disabled' : '',
      ]"
      :tabindex="isLocked ? -1 : 0"
      role="button"
      :aria-disabled="isLocked"
      :aria-label="`${label || $t('wp.file.attach', 'Attach a file')}. ${$t('wp.file.select_or_drop', 'Click to choose file or drag and drop')}`"
      @click="triggerBrowse"
      @keydown.enter.self.prevent="triggerBrowse"
      @keydown.space.self.prevent="triggerBrowse"
      @dragover.prevent="isDragging = !isLocked"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <div class="d-flex align-center justify-center gap-3 flex-wrap">
        <VAvatar
          size="36"
          color="primary"
          variant="tonal"
        >
          <VProgressCircular
            v-if="isPackaging"
            indeterminate
            size="20"
            width="2"
          />
          <VIcon
            v-else
            icon="tabler-paperclip"
            size="20"
          />
        </VAvatar>

        <div class="text-start">
          <span class="text-body-2 font-weight-medium d-block text-high-emphasis">
            {{ isPackaging
              ? $t('wp.file.packaging', 'Preparing your scan…')
              : $t('wp.file.select_or_drop', 'Click to choose file or drag and drop') }}
          </span>
          <span class="text-caption text-disabled d-block">
            {{ computedHint }}
          </span>
        </div>

        <div
          v-if="(allowScan || allowDialog) && !isLocked"
          class="ms-auto d-flex align-center gap-1"
        >
          <VBtn
            v-if="allowScan"
            size="small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-scan"
            :disabled="isPackaging"
            @pointerenter="warmUpOpenCv"
            @focus="warmUpOpenCv"
            @click.stop="openScanner"
          >
            {{ $t('wp.file.scan_button', 'Scan') }}
          </VBtn>

          <VBtn
            v-if="allowDialog"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-paperclip"
            :disabled="isPackaging"
            @click.stop="isAttachmentDialogOpen = true"
          >
            {{ $t('wp.file.attach_button', 'Attach') }}
          </VBtn>
        </div>
      </div>
    </div>

    <!-- Attached File State (drop a new file on it to replace) -->
    <div
      v-else
      class="wp-attachment-card border rounded-lg pa-3 bg-surface d-flex align-center justify-space-between"
      :class="[
        hasError ? 'border-error' : '',
        isDragging ? 'wp-attachment-card--dragging' : '',
        disabled ? 'opacity-50' : '',
      ]"
      @dragover.prevent="isDragging = !isLocked"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <button
        type="button"
        class="wp-attachment-main d-flex align-center gap-3 overflow-hidden"
        :disabled="!previewUrl && !targetDownloadUrl"
        :aria-label="`${$t('common.preview', 'Preview')}: ${fileName}`"
        @click="handleOpenPreview"
      >
        <VAvatar
          size="40"
          :color="fileColor"
          variant="tonal"
          rounded
          class="flex-shrink-0"
        >
          <img
            v-if="isImage && previewUrl"
            :src="previewUrl"
            alt=""
            class="wp-attachment-thumb"
          >
          <VIcon
            v-else
            :icon="fileIcon"
            size="22"
          />
        </VAvatar>

        <span class="overflow-hidden text-start">
          <span
            class="text-body-2 font-weight-bold d-block text-truncate"
            :title="fileName"
          >
            {{ fileName }}
          </span>
          <span class="text-caption text-disabled">
            {{ fileSize || $t('wp.file.attached', 'File Attached') }}
          </span>
        </span>
      </button>

      <div class="d-flex align-center gap-1 flex-shrink-0 ms-2">
        <IconBtn
          v-if="previewUrl || targetDownloadUrl"
          size="small"
          variant="text"
          color="primary"
          :title="$t('common.preview', 'Preview')"
          :aria-label="$t('common.preview', 'Preview')"
          @click="handleOpenPreview"
        >
          <VIcon
            icon="tabler-eye"
            size="18"
          />
        </IconBtn>

        <IconBtn
          v-if="targetDownloadUrl"
          size="small"
          variant="text"
          color="primary"
          :title="$t('common.download', 'Download')"
          :aria-label="$t('common.download', 'Download')"
          @click="handleDownload"
        >
          <VIcon
            icon="tabler-download"
            size="18"
          />
        </IconBtn>

        <IconBtn
          v-if="!isLocked"
          size="small"
          variant="text"
          color="secondary"
          :title="$t('wp.file.replace', 'Replace')"
          :aria-label="$t('wp.file.replace', 'Replace')"
          @click="triggerBrowse"
        >
          <VIcon
            icon="tabler-replace"
            size="18"
          />
        </IconBtn>

        <IconBtn
          v-if="!isLocked"
          size="small"
          variant="text"
          color="error"
          :title="$t('common.remove', 'Remove')"
          :aria-label="$t('common.remove', 'Remove')"
          @click="handleRemove"
        >
          <VIcon
            icon="tabler-x"
            size="18"
          />
        </IconBtn>
      </div>
    </div>

    <!-- Error message display -->
    <div
      v-if="hasError"
      :id="`${uid}-error`"
      class="text-caption text-error mt-1 ps-1"
      role="alert"
    >
      {{ errorText }}
    </div>

    <!-- Scanner modal: stays mounted (lazy content) so it animates closed; closing triggers packaging -->
    <WpDocumentScanner
      v-if="allowScan"
      v-model="scannerOpen"
      :multiple="category === 'document'"
      @scanned="handleScanned"
      @error="onScannerError"
    />

    <!-- Full Add Attachment Workflow modal -->
    <WpAttachmentDialog
      v-if="allowDialog"
      v-model="isAttachmentDialogOpen"
      :accept="computedAccept"
      :max-size-mb="maxSizeMb"
      preset="compact"
      @save="handleAttachmentDialogSave"
      @error="onScannerError"
    />

    <!-- Lightbox / Preview modal -->
    <WpMediaViewerDialog
      v-model="isPreviewOpen"
      :item="viewerItem"
    />
  </div>
</template>

<style scoped>
.wp-attachment-dropzone {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface), 0.5);
  transition: border-color 0.15s ease, background 0.15s ease;
}

.wp-attachment-dropzone:hover,
.wp-attachment-dropzone:focus-visible {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(var(--v-theme-primary), 0.04);
}

.wp-attachment-dropzone:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-attachment-dropzone--dragging,
.wp-attachment-card--dragging {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.08) !important;
}

.wp-attachment-dropzone--disabled {
  cursor: not-allowed;
  opacity: 0.6;
  pointer-events: none;
}

.wp-attachment-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.wp-attachment-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.35);
}

.wp-attachment-main {
  min-inline-size: 0;
  padding: 0;
  background: none;
  cursor: pointer;
  flex: 1 1 auto;
}

.wp-attachment-main:disabled {
  cursor: default;
}

.wp-attachment-main:focus-visible {
  border-radius: 6px;
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-attachment-thumb {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

@media (prefers-reduced-motion: reduce) {
  .wp-attachment-dropzone {
    transition: none;
  }
}
</style>
