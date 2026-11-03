<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { A11y, Keyboard, Navigation, Pagination } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import WpDeleteDialog from '@/components/shared/dialogs/WpDeleteDialog.vue'
import WpImageDialog from '@/components/shared/media/WpImageDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import { extensionForMime, formatDocumentBytes as formatBytes, matchesAccept, normalizeMediaUrl, resolveFileKind } from '@/composables/documents/documentFiles'

const props = defineProps({
  items: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  readOnly: { type: Boolean, default: false },
  canUpload: { type: Boolean, default: true },
  canRecordVideo: { type: Boolean, default: true },
  canCapturePhoto: { type: Boolean, default: true },
  canSetMain: { type: Boolean, default: true },
  canDelete: { type: Boolean, default: true },
  showTitleInput: { type: Boolean, default: false },
  fit: { type: String, default: 'cover' },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  emptyTitle: { type: String, default: '' },
  emptyText: { type: String, default: '' },
  accept: { type: String, default: 'image/jpeg,image/png,image/webp,image/svg+xml,video/mp4,video/webm,video/quicktime' },
  maxSizeMb: { type: Number, default: 50 },
})

const emit = defineEmits(['upload', 'setMain', 'delete', 'preview', 'error', 'retry'])

const { t } = useI18n()

const effectiveCanUpload = computed(() => !props.readOnly && props.canUpload)
const effectiveCanRecordVideo = computed(() => !props.readOnly && props.canRecordVideo)
const effectiveCanCapturePhoto = computed(() => !props.readOnly && props.canCapturePhoto)
const effectiveCanSetMain = computed(() => !props.readOnly && props.canSetMain)
const effectiveCanDelete = computed(() => !props.readOnly && props.canDelete)

const MAX_RECORDING_SECONDS = 120

const RECORDER_MIME_CANDIDATES = [
  'video/webm;codecs=vp9,opus',
  'video/webm;codecs=vp8,opus',
  'video/webm',
  'video/mp4', // Safari
]

// Display view modes: 'grid' or 'swiper'
const viewMode = ref('grid')
const isRtl = computed(() => typeof document !== 'undefined' && document.documentElement.dir === 'rtl')

// File input refs
const multiFileInputRef = ref(null)
const videoFileInputRef = ref(null)

// Dialog states
const isImageDialogOpen = ref(false)
const isViewerOpen = ref(false)
const viewerInitialIndex = ref(0)
const isDeleteOpen = ref(false)
const itemToDelete = ref(null)

// Upload feedback
const isDraggingFile = ref(false)
const uploadNotice = ref('')
let dragDepth = 0

// Video recording state
const isRecordVideoDialogOpen = ref(false)
const isRecording = ref(false)
const recordingSeconds = ref(0)
const recordedVideoBlob = ref(null)
const recordedVideoPreview = ref('')
const recordFacing = ref('environment')
const isStartingStream = ref(false)
const recordVideoRef = ref(null)
const videoRecordError = ref('')

let recordingTimer = null
let videoStream = null
let mediaRecorder = null
let streamToken = 0
let recordedBytes = 0

const isMediaRecorderSupported = computed(() => {
  return typeof window !== 'undefined' &&
    Boolean(window.MediaRecorder) &&
    Boolean(navigator.mediaDevices?.getUserMedia)
})

const hasItems = computed(() => props.items && props.items.length > 0)
const maxBytes = computed(() => props.maxSizeMb * 1024 * 1024)

const recordingClock = computed(() => {
  const minutes = String(Math.floor(recordingSeconds.value / 60)).padStart(2, '0')
  const seconds = String(recordingSeconds.value % 60).padStart(2, '0')

  return `${minutes}:${seconds}`
})

function isVideoItem(item) {
  if (!item) return false

  return resolveFileKind(item.mime_type, item.file_name || item.name || item.title || item.url) === 'video'
}

function getItemUrl(item) {
  if (!item) return ''

  const raw = item.thumb_url || item.thumbnail_url || item.thumb || item.preview_url || item.url || item.original_url || (typeof item === 'string' ? item : '')

  return normalizeMediaUrl(raw)
}

function getItemVideoUrl(item) {
  return normalizeMediaUrl(item?.url || item?.original_url || item?.preview_url || '')
}

function getItemTitle(item) {
  if (!item) return t('wp.media.media_file', 'Media file')

  return item.title || item.custom_properties?.title || item.name || item.file_name || t('wp.media.media_file', 'Media file')
}

// ---- Multi-file Upload (picker + drag & drop share one validated path) ----
function triggerMultiUpload() {
  multiFileInputRef.value?.click()
}

/**
 * Validate against `accept` and `maxSizeMb`, emit the good files, and tell the
 * user (once) about the rest. Previously `maxSizeMb` was declared but ignored.
 */
function ingestFiles(fileList) {
  const files = Array.from(fileList || [])

  if (!files.length) return

  const accepted = []
  const rejected = []

  files.forEach(file => {
    if (!matchesAccept(file, props.accept)) rejected.push({ file, reason: 'type' })
    else if (file.size > maxBytes.value) rejected.push({ file, reason: 'size' })
    else accepted.push(file)
  })

  if (rejected.length) {
    const error = new Error(`${rejected.length} file(s) skipped: unsupported type or larger than ${props.maxSizeMb} MB`)

    error.i18nKey = 'wp.media.error_files_rejected'
    error.i18nParams = { count: rejected.length, size: props.maxSizeMb }
    error.rejected = rejected
    uploadNotice.value = t('wp.media.error_files_rejected', { count: rejected.length, size: props.maxSizeMb }, error.message)
    emit('error', error)
  } else {
    uploadNotice.value = ''
  }

  if (accepted.length) {
    emit('upload', {
      files: accepted,
      isMultiple: accepted.length > 1,
      file: accepted[0],
    })
  }
}

function handleMultiFileSelect(event) {
  const files = Array.from(event.target.files || [])

  event.target.value = ''
  ingestFiles(files)
}

function hasFiles(event) {
  return Array.from(event.dataTransfer?.types || []).includes('Files')
}

function onDragEnter(event) {
  if (!effectiveCanUpload.value || !hasFiles(event)) return
  dragDepth++
  isDraggingFile.value = true
}

function onDragLeave() {
  if (!effectiveCanUpload.value) return
  dragDepth = Math.max(0, dragDepth - 1)
  if (!dragDepth) isDraggingFile.value = false
}

function onDrop(event) {
  dragDepth = 0
  isDraggingFile.value = false
  if (!effectiveCanUpload.value || !hasFiles(event)) return
  ingestFiles(event.dataTransfer.files)
}

function openUploadDialog() {
  if (!effectiveCanUpload.value) return
  isImageDialogOpen.value = true
}

// ---- Photo Capture Dialog ----
function handlePhotoSaved(payload) {
  isImageDialogOpen.value = false

  const rawItems = Array.isArray(payload?.files) && payload.files.length
    ? payload.files
    : (payload?.file ? [payload] : [])

  const extractedFiles = rawItems
    .map(item => (item instanceof File || item instanceof Blob ? item : item?.file))
    .filter(Boolean)

  emit('upload', {
    file: payload.file || extractedFiles[0] || null,
    files: extractedFiles,
    items: rawItems,
    isMultiple: extractedFiles.length > 1,
    preview: payload.preview || payload.previewUrl,
    title: payload.title,
    is_main: payload.is_main,
    isCapture: true,
  })
}

// ---- Video Recording Experience ----
function pickRecorderMime() {
  return RECORDER_MIME_CANDIDATES.find(type => MediaRecorder.isTypeSupported(type)) || ''
}

function stopStream() {
  if (videoStream) {
    videoStream.getTracks().forEach(track => track.stop())
    videoStream = null
  }
  if (recordVideoRef.value) recordVideoRef.value.srcObject = null
}

function revokeRecordedPreview() {
  if (recordedVideoPreview.value) URL.revokeObjectURL(recordedVideoPreview.value)
  recordedVideoPreview.value = ''
  recordedVideoBlob.value = null
}

/** Camera + mic; if only the microphone is missing, fall back to silent video instead of failing. */
async function requestStream() {
  const video = { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: { ideal: recordFacing.value } }

  try {
    return await navigator.mediaDevices.getUserMedia({ video, audio: true })
  } catch (error) {
    if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
      return navigator.mediaDevices.getUserMedia({ video, audio: false })
    }

    throw error
  }
}

async function acquireStream() {
  const token = ++streamToken

  isStartingStream.value = true
  videoRecordError.value = ''
  stopStream()

  try {
    const stream = await requestStream()

    // The dialog was closed (or the camera switched) while the permission prompt was open
    if (token !== streamToken) {
      stream.getTracks().forEach(track => track.stop())

      return
    }

    videoStream = stream
    await nextTick()
    if (recordVideoRef.value) {
      recordVideoRef.value.srcObject = stream
      recordVideoRef.value.play().catch(() => {})
    }
  } catch (err) {
    if (token !== streamToken) return
    console.error('Failed to get camera/mic stream:', err)
    videoRecordError.value = t('wp.video.permission_denied', 'Camera or microphone permission was denied or unavailable. You can upload a video file instead.')
  } finally {
    if (token === streamToken) isStartingStream.value = false
  }
}

async function startVideoRecordingWorkflow() {
  videoRecordError.value = ''
  revokeRecordedPreview()
  recordingSeconds.value = 0
  isRecordVideoDialogOpen.value = true

  if (!isMediaRecorderSupported.value) {
    videoRecordError.value = t('wp.video.not_supported', 'Video recording is not supported in this browser. Please upload a video file instead.')

    return
  }

  await acquireStream()
}

async function switchRecordCamera() {
  if (isRecording.value) return
  recordFacing.value = recordFacing.value === 'environment' ? 'user' : 'environment'
  await acquireStream()
}

function startActualRecording() {
  if (!videoStream) return

  try {
    const chunks = []
    const mimeType = pickRecorderMime()
    const recorder = new MediaRecorder(videoStream, mimeType ? { mimeType } : undefined)

    recordedBytes = 0

    recorder.ondataavailable = e => {
      if (e.data && e.data.size > 0) {
        chunks.push(e.data)
        recordedBytes += e.data.size

        // Stop a little before the upload limit rather than produce a file the server will reject.
        if (recordedBytes >= maxBytes.value * 0.95) stopActualRecording()
      }
    }

    recorder.onstop = () => {
      const blob = new Blob(chunks, { type: recorder.mimeType || mimeType || 'video/webm' })

      recordedVideoBlob.value = blob
      recordedVideoPreview.value = URL.createObjectURL(blob)
      stopStream()
    }

    recorder.start(500)
    mediaRecorder = recorder
    isRecording.value = true
    recordingSeconds.value = 0

    recordingTimer = setInterval(() => {
      recordingSeconds.value++
      if (recordingSeconds.value >= MAX_RECORDING_SECONDS) stopActualRecording()
    }, 1000)
  } catch (err) {
    console.error('MediaRecorder start failed:', err)
    videoRecordError.value = t('wp.video.record_failed', 'Failed to initialize video recording.')
  }
}

function stopActualRecording() {
  if (recordingTimer) {
    clearInterval(recordingTimer)
    recordingTimer = null
  }
  isRecording.value = false

  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop()
  }
}

async function retakeVideo() {
  revokeRecordedPreview()
  recordingSeconds.value = 0
  await acquireStream()
}

function closeVideoRecordDialog() {
  streamToken++ // cancels a pending permission request
  isStartingStream.value = false
  stopActualRecording()
  stopStream()
  revokeRecordedPreview()
  isRecordVideoDialogOpen.value = false
}

function applyRecordedVideo() {
  if (!recordedVideoBlob.value) return

  // `video/webm;codecs=vp9,opus` is not a valid File type for server-side validation: keep the base type.
  const baseType = (recordedVideoBlob.value.type || 'video/webm').split(';')[0]
  const file = new File([recordedVideoBlob.value], `video-recording-${Date.now()}.${extensionForMime(baseType) || 'webm'}`, { type: baseType })

  // A fresh URL: the dialog's own preview URL is revoked below, and the parent keeps this one.
  emit('upload', {
    file,
    preview: URL.createObjectURL(file),
    isVideo: true,
  })

  closeVideoRecordDialog()
}

function triggerVideoFileUploadFallback() {
  closeVideoRecordDialog()
  videoFileInputRef.value?.click()
}

function handleVideoFallbackSelect(event) {
  const file = event.target.files?.[0]

  event.target.value = ''
  if (!file) return

  if (file.size > maxBytes.value) {
    ingestFiles([file]) // reuses the size error path

    return
  }

  emit('upload', {
    file,
    isVideo: true,
  })
}

// ---- Preview / Viewer Integration ----
function openPreview(index) {
  viewerInitialIndex.value = index
  isViewerOpen.value = true
  emit('preview', props.items[index])
}

// ---- Main photo / Delete ----
function handleSetMain(item, event) {
  event?.stopPropagation()
  emit('setMain', item)
}

function confirmDelete(item, event) {
  event?.stopPropagation()
  itemToDelete.value = item
  isDeleteOpen.value = true
}

function executeDelete() {
  if (itemToDelete.value) {
    emit('delete', itemToDelete.value)
    itemToDelete.value = null
  }
  isDeleteOpen.value = false
}

onUnmounted(() => {
  streamToken++
  stopActualRecording()
  stopStream()
  revokeRecordedPreview()
})

defineExpose({
  openUploadDialog,
  handleOpenAdd: openUploadDialog,
  triggerMultiUpload,
  startVideoRecordingWorkflow,
})
</script>

<template>
  <div
    class="wp-media-gallery"
    @dragenter.prevent="onDragEnter"
    @dragover.prevent
    @dragleave.prevent="onDragLeave"
    @drop.prevent="onDrop"
  >
    <!-- Hidden multi-upload file inputs -->
    <input
      ref="multiFileInputRef"
      type="file"
      :accept="accept"
      multiple
      class="d-none"
      @change="handleMultiFileSelect"
    >
    <input
      ref="videoFileInputRef"
      type="file"
      accept="video/mp4,video/webm,video/quicktime,video/avi"
      class="d-none"
      @change="handleVideoFallbackSelect"
    >

    <!-- Header Toolbar -->
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-2">
      <div class="d-flex align-center gap-2">
        <VAvatar
          v-if="icon"
          size="34"
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon
            :icon="icon"
            size="18"
          />
        </VAvatar>
        <div>
          <h6
            v-if="title"
            class="text-subtitle-1 font-weight-bold mb-0"
          >
            {{ title }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ subtitle || $t('wp.media.count', { count: items.length }, `${items.length} media item(s)`) }}
          </span>
        </div>
      </div>

      <!-- Action Buttons & View Mode Switcher -->
      <div class="d-flex align-center gap-2 flex-wrap">
        <VBtnToggle
          v-if="hasItems"
          v-model="viewMode"
          density="compact"
          mandatory
          variant="outlined"
          color="primary"
          class="d-none d-sm-inline-flex"
        >
          <VBtn
            value="grid"
            icon="tabler-layout-grid"
            size="small"
            :title="$t('common.grid_view', 'Grid View')"
            :aria-label="$t('common.grid_view', 'Grid View')"
          />
          <VBtn
            value="swiper"
            icon="tabler-slideshow"
            size="small"
            :title="$t('common.carousel_view', 'Carousel View')"
            :aria-label="$t('common.carousel_view', 'Carousel View')"
          />
        </VBtnToggle>

        <VMenu
          v-if="effectiveCanUpload"
          location="bottom end"
        >
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              color="primary"
              size="small"
              prepend-icon="tabler-plus"
              append-icon="tabler-chevron-down"
            >
              {{ $t('wp.media.add_media', 'Add Media') }}
            </VBtn>
          </template>

          <VList density="compact">
            <VListItem
              prepend-icon="tabler-photo-edit"
              :title="effectiveCanCapturePhoto ? $t('wp.media.add_photo_studio', 'Add Photo (Upload / Camera / Crop)') : $t('wp.media.upload_crop_photo', 'Upload & Crop Image')"
              @click="isImageDialogOpen = true"
            />
            <VListItem
              prepend-icon="tabler-upload"
              :title="effectiveCanRecordVideo ? $t('wp.media.upload_files', 'Quick Upload (Photos / Videos)') : $t('wp.media.upload_images', 'Quick Upload Images')"
              @click="triggerMultiUpload"
            />
            <VListItem
              v-if="effectiveCanRecordVideo"
              prepend-icon="tabler-video"
              :title="$t('wp.media.record_video', 'Record Video Clip')"
              @click="startVideoRecordingWorkflow"
            />
          </VList>
        </VMenu>
      </div>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ error }}</span>
        <VBtn
          size="x-small"
          color="error"
          variant="tonal"
          prepend-icon="tabler-refresh"
          @click="emit('retry')"
        >
          {{ $t('common.retry', 'Retry') }}
        </VBtn>
      </div>
    </VAlert>

    <VProgressLinear
      v-if="loading && hasItems"
      indeterminate
      height="2"
      class="mb-2"
    />

    <VAlert
      v-if="uploadNotice"
      type="warning"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="uploadNotice = ''"
    >
      {{ uploadNotice }}
    </VAlert>

    <!-- Loading skeleton -->
    <div
      v-if="loading && !hasItems"
      class="wp-gallery-grid"
      aria-busy="true"
    >
      <VSkeletonLoader
        v-for="n in 6"
        :key="n"
        type="image"
        class="wp-gallery-skeleton rounded-lg"
      />
    </div>

    <!-- Empty Upload State -->
    <div
      v-else-if="!hasItems"
      class="wp-gallery-empty border-2 border-dashed rounded-lg pa-8 text-center bg-surface"
      :class="{ 'wp-gallery-empty--disabled': !effectiveCanUpload }"
      :tabindex="effectiveCanUpload ? 0 : -1"
      :role="effectiveCanUpload ? 'button' : undefined"
      :aria-label="effectiveCanUpload ? (emptyTitle || $t('wp.media.take_or_upload', 'Upload photos or videos')) : undefined"
      @click="effectiveCanUpload && (isImageDialogOpen = true)"
      @keydown.enter.self.prevent="effectiveCanUpload && (isImageDialogOpen = true)"
      @keydown.space.self.prevent="effectiveCanUpload && (isImageDialogOpen = true)"
    >
      <VAvatar
        size="64"
        color="primary"
        variant="tonal"
        class="mb-3"
      >
        <VIcon
          :icon="effectiveCanUpload ? 'tabler-photo-plus' : 'tabler-photo-off'"
          size="32"
        />
      </VAvatar>
      <h6 class="text-subtitle-1 font-weight-bold mb-1">
        {{ emptyTitle || (effectiveCanUpload ? $t('wp.media.take_or_upload', 'Upload photos or videos') : $t('wp.media.no_media', 'No media uploaded')) }}
      </h6>
      <p class="text-body-2 text-medium-emphasis mb-3 wp-gallery-empty__text">
        {{ emptyText || (effectiveCanUpload ? $t('wp.media.empty_hint', 'Drag and drop media files or click to browse. JPEG, PNG, WebP, MP4 up to {size}MB.', { size: maxSizeMb }) : $t('wp.media.no_media_readonly', 'No photos or videos are attached to this record.')) }}
      </p>

      <div
        v-if="effectiveCanUpload"
        class="d-flex justify-center gap-2 flex-wrap"
      >
        <VBtn
          color="primary"
          variant="tonal"
          size="small"
          prepend-icon="tabler-photo"
          @click.stop="isImageDialogOpen = true"
        >
          {{ $t('common.browse_files', 'Browse Files') }}
        </VBtn>
        <VBtn
          v-if="effectiveCanCapturePhoto"
          variant="outlined"
          color="secondary"
          size="small"
          prepend-icon="tabler-camera"
          @click.stop="isImageDialogOpen = true"
        >
          {{ $t('wp.media.camera', 'Camera') }}
        </VBtn>
        <VBtn
          v-if="effectiveCanRecordVideo"
          variant="outlined"
          color="secondary"
          size="small"
          prepend-icon="tabler-video"
          @click.stop="startVideoRecordingWorkflow"
        >
          {{ $t('wp.media.record_video', 'Record Video') }}
        </VBtn>
      </div>
    </div>

    <!-- 1. Grid Mode -->
    <div
      v-else-if="viewMode === 'grid'"
      class="wp-gallery-grid"
    >
      <div
        v-for="(item, idx) in items"
        :key="item.id || idx"
        class="wp-gallery-card rounded-lg border overflow-hidden position-relative bg-surface"
        :class="{ 'wp-gallery-card--contain': fit === 'contain' }"
        role="button"
        tabindex="0"
        :aria-label="`${$t('common.preview', 'Preview')}: ${getItemTitle(item)}`"
        @click="openPreview(idx)"
        @keydown.enter.self.prevent="openPreview(idx)"
        @keydown.space.self.prevent="openPreview(idx)"
      >
        <img
          v-if="!isVideoItem(item)"
          :src="getItemUrl(item)"
          :alt="getItemTitle(item)"
          class="wp-gallery-card__img"
          :class="{ 'wp-gallery-card__img--contain': fit === 'contain' }"
          loading="lazy"
          draggable="false"
        >

        <div
          v-else
          class="wp-gallery-card__video-wrap position-relative w-100 h-100 d-flex align-center justify-center bg-black"
        >
          <!-- #t=0.1 makes Safari paint a frame as the thumbnail -->
          <video
            :src="`${getItemVideoUrl(item)}#t=0.1`"
            preload="metadata"
            muted
            playsinline
            class="wp-gallery-card__video-thumb w-100 h-100"
          />
          <div class="wp-video-play-badge position-absolute d-flex align-center justify-center rounded-circle">
            <VIcon
              icon="tabler-player-play-filled"
              size="22"
              color="white"
            />
          </div>
        </div>

        <!-- Main badge -->
        <VChip
          v-if="item.is_main"
          class="wp-gallery-badge position-absolute"
          size="x-small"
          color="warning"
          variant="flat"
          prepend-icon="tabler-star-filled"
        >
          {{ $t('wp.media.main', 'Main') }}
        </VChip>

        <!-- Actions: shown on hover / keyboard focus, and always on touch screens -->
        <div
          v-if="effectiveCanSetMain || effectiveCanDelete"
          class="wp-gallery-overlay position-absolute d-flex align-center justify-center gap-1"
        >
          <IconBtn
            v-if="effectiveCanSetMain && !item.is_main && !isVideoItem(item)"
            size="small"
            variant="flat"
            color="surface"
            :title="$t('wp.media.set_main', 'Make Main')"
            :aria-label="$t('wp.media.set_main', 'Make Main')"
            @click.stop="handleSetMain(item, $event)"
          >
            <VIcon
              icon="tabler-star"
              size="16"
            />
          </IconBtn>

          <IconBtn
            v-if="effectiveCanDelete"
            size="small"
            variant="flat"
            color="error"
            :title="$t('common.delete', 'Delete')"
            :aria-label="$t('common.delete', 'Delete')"
            @click.stop="confirmDelete(item, $event)"
          >
            <VIcon
              icon="tabler-trash"
              size="16"
              color="white"
            />
          </IconBtn>
        </div>

        <div class="wp-gallery-info position-absolute px-2 py-1 text-truncate text-caption text-white">
          {{ getItemTitle(item) }}
          <span
            v-if="item.size"
            class="opacity-70"
          > • {{ formatBytes(item.size) }}</span>
        </div>
      </div>
    </div>

    <!-- 2. Swiper Carousel Mode -->
    <div
      v-else-if="viewMode === 'swiper'"
      class="wp-gallery-swiper-wrapper"
    >
      <Swiper
        :modules="[Navigation, Pagination, Keyboard, A11y]"
        navigation
        pagination
        keyboard
        :dir="isRtl ? 'rtl' : 'ltr'"
        :slides-per-view="1"
        :space-between="16"
        class="wp-gallery-swiper rounded-lg border overflow-hidden bg-black"
        style="block-size: 380px;"
      >
        <SwiperSlide
          v-for="(item, idx) in items"
          :key="item.id || idx"
          class="d-flex align-center justify-center position-relative h-100"
        >
          <img
            v-if="!isVideoItem(item)"
            :src="getItemUrl(item)"
            :alt="getItemTitle(item)"
            class="w-100 h-100 object-contain cursor-pointer"
            loading="lazy"
            @click="openPreview(idx)"
          >
          <video
            v-else
            controls
            playsinline
            preload="metadata"
            :src="getItemVideoUrl(item)"
            class="w-100 h-100 object-contain"
          />

          <div
            v-if="effectiveCanSetMain || effectiveCanDelete"
            class="position-absolute d-flex align-center gap-2 pa-3"
            style="inset-block-start: 0; inset-inline-end: 0; z-index: 5;"
          >
            <VBtn
              v-if="effectiveCanSetMain && !item.is_main && !isVideoItem(item)"
              size="x-small"
              variant="flat"
              color="surface"
              prepend-icon="tabler-star"
              @click.stop="handleSetMain(item, $event)"
            >
              {{ $t('wp.media.set_main', 'Make Main') }}
            </VBtn>

            <IconBtn
              v-if="effectiveCanDelete"
              size="small"
              color="error"
              variant="flat"
              :title="$t('common.delete', 'Delete')"
              :aria-label="$t('common.delete', 'Delete')"
              @click.stop="confirmDelete(item, $event)"
            >
              <VIcon
                icon="tabler-trash"
                size="16"
                color="white"
              />
            </IconBtn>
          </div>
        </SwiperSlide>
      </Swiper>
    </div>

    <!-- Drop overlay while dragging files over the gallery -->
    <Transition name="wp-fade">
      <div
        v-if="isDraggingFile"
        class="wp-gallery-drop"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-cloud-upload"
          size="44"
          color="primary"
        />
        <span class="text-subtitle-1 font-weight-medium">{{ $t('wp.media.drop_to_upload', 'Drop to upload') }}</span>
      </div>
    </Transition>

    <!-- Integrated Image Capture Dialog -->
    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('wp.media.capture_photo', 'Capture & Crop Photo')"
      :max-size-mb="maxSizeMb"
      :show-title-input="showTitleInput"
      :show-main-toggle="effectiveCanSetMain"
      multiple
      @save="handlePhotoSaved"
    />

    <!-- Integrated Media Viewer Dialog (Unified Image/Video Lightbox) -->
    <WpMediaViewerDialog
      v-model="isViewerOpen"
      :items="items"
      :initial-index="viewerInitialIndex"
    />

    <!-- Delete Confirmation Modal -->
    <WpDeleteDialog
      v-if="effectiveCanDelete"
      v-model="isDeleteOpen"
      :title="$t('wp.media.delete_title', 'Delete Media?')"
      :description="$t('wp.media.delete_desc', 'This action will permanently delete this media file.')"
      @confirm="executeDelete"
    />

    <!-- Video Recording Modal with Graceful Fallback -->
    <VDialog
      v-model="isRecordVideoDialogOpen"
      max-width="560"
      persistent
      class="wp-video-record-dialog"
    >
      <VCard class="wp-video-record-card bg-surface">
        <VCardTitle class="d-flex align-center justify-space-between px-5 pt-4 pb-2 border-b">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              color="error"
              variant="tonal"
              rounded
            >
              <VIcon
                icon="tabler-video"
                size="20"
              />
            </VAvatar>
            <span class="text-h6 font-weight-medium">
              {{ $t('wp.video.record_dialog_title', 'Record Video Clip') }}
            </span>
          </div>

          <IconBtn
            size="small"
            :title="$t('common.close', 'Close')"
            :aria-label="$t('common.close', 'Close')"
            @click="closeVideoRecordDialog"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </VCardTitle>

        <VCardText class="pa-5">
          <!-- Fallback if unsupported or denied -->
          <div
            v-if="videoRecordError"
            class="text-center py-4"
          >
            <VAlert
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-4 text-start"
            >
              {{ videoRecordError }}
            </VAlert>
            <div class="d-flex justify-center gap-2 flex-wrap">
              <VBtn
                v-if="isMediaRecorderSupported"
                variant="tonal"
                prepend-icon="tabler-refresh"
                @click="acquireStream"
              >
                {{ $t('wp.camera.retry', 'Try again') }}
              </VBtn>
              <VBtn
                color="primary"
                prepend-icon="tabler-upload"
                @click="triggerVideoFileUploadFallback"
              >
                {{ $t('wp.video.upload_instead', 'Upload a video file') }}
              </VBtn>
            </div>
          </div>

          <template v-else>
            <div class="wp-video-viewport position-relative rounded-lg overflow-hidden bg-black mb-3 w-100">
              <!-- Review the take -->
              <video
                v-if="recordedVideoPreview"
                :src="recordedVideoPreview"
                controls
                playsinline
                class="wp-video-el"
              />
              <!-- Live viewfinder -->
              <video
                v-else
                ref="recordVideoRef"
                muted
                playsinline
                class="wp-video-el"
                :class="{ 'wp-video-el--mirrored': recordFacing === 'user' }"
              />

              <div
                v-if="isStartingStream"
                class="wp-video-loading"
              >
                <VProgressCircular
                  indeterminate
                  color="primary"
                />
              </div>

              <div
                v-if="isRecording"
                class="wp-recording-badge position-absolute d-flex align-center gap-2 pa-2 rounded-pill bg-black-50 text-white"
              >
                <div class="wp-recording-dot" />
                <span class="text-caption font-weight-bold">{{ recordingClock }}</span>
              </div>
            </div>

            <VProgressLinear
              v-if="isRecording"
              :model-value="(recordingSeconds / MAX_RECORDING_SECONDS) * 100"
              color="error"
              height="3"
              class="mb-3"
              :aria-label="$t('wp.video.progress', 'Recording progress')"
            />
            <p
              v-else-if="!recordedVideoPreview"
              class="text-caption text-disabled text-center mb-3"
            >
              {{ $t('wp.video.limit_hint', { seconds: MAX_RECORDING_SECONDS, size: maxSizeMb }, `Up to ${MAX_RECORDING_SECONDS} seconds or ${maxSizeMb} MB.`) }}
            </p>

            <!-- Controls -->
            <div class="d-flex justify-center align-center gap-3 flex-wrap">
              <template v-if="recordedVideoPreview">
                <VBtn
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-rotate"
                  @click="retakeVideo"
                >
                  {{ $t('wp.video.retake', 'Retake') }}
                </VBtn>
                <VBtn
                  color="primary"
                  prepend-icon="tabler-check"
                  @click="applyRecordedVideo"
                >
                  {{ $t('wp.video.use_video', 'Use Video') }}
                </VBtn>
              </template>

              <template v-else-if="isRecording">
                <VBtn
                  color="error"
                  size="large"
                  prepend-icon="tabler-player-stop-filled"
                  @click="stopActualRecording"
                >
                  {{ $t('wp.video.stop', 'Stop') }}
                </VBtn>
              </template>

              <template v-else>
                <IconBtn
                  variant="tonal"
                  :disabled="isStartingStream"
                  :title="$t('wp.camera.switch', 'Switch camera')"
                  :aria-label="$t('wp.camera.switch', 'Switch camera')"
                  @click="switchRecordCamera"
                >
                  <VIcon
                    icon="tabler-camera-rotate"
                    size="20"
                  />
                </IconBtn>
                <VBtn
                  color="error"
                  size="large"
                  prepend-icon="tabler-circle-filled"
                  :disabled="isStartingStream || !isMediaRecorderSupported"
                  @click="startActualRecording"
                >
                  {{ $t('wp.video.start', 'Start Recording') }}
                </VBtn>
              </template>
            </div>
          </template>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.wp-media-gallery {
  position: relative;
}

.wp-gallery-empty {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.wp-gallery-empty:hover,
.wp-gallery-empty:focus-visible {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.04) !important;
}

.wp-gallery-empty:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-gallery-empty--disabled {
  cursor: default;
}

.wp-gallery-empty__text {
  margin-inline: auto;
  max-inline-size: 380px;
}

.wp-gallery-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
}

.wp-gallery-skeleton {
  aspect-ratio: 1;
}

.wp-gallery-card {
  aspect-ratio: 1;
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: box-shadow 0.18s ease, transform 0.18s ease;
}

.wp-gallery-card:hover,
.wp-gallery-card:focus-visible {
  box-shadow: 0 6px 18px rgba(0, 0, 0, 14%);
  transform: translateY(-2px);
}

.wp-gallery-card:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-gallery-card__img,
.wp-gallery-card__video-thumb {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.wp-gallery-card--contain {
  background: rgba(var(--v-border-color), 0.04) !important;
}

.wp-gallery-card__img--contain {
  object-fit: contain;
  padding: 10px;
}

.wp-video-play-badge {
  background: rgba(0, 0, 0, 55%);
  block-size: 44px;
  inline-size: 44px;
  pointer-events: none;
}

.wp-gallery-badge {
  z-index: 2;
  inset-block-start: 8px;
  inset-inline-start: 8px;
}

/* Hidden until hover / keyboard focus on pointer devices; permanently visible on touch. */
.wp-gallery-overlay {
  z-index: 3;
  inset-block-start: 6px;
  inset-inline-end: 6px;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.wp-gallery-card:hover .wp-gallery-overlay,
.wp-gallery-card:focus-within .wp-gallery-overlay {
  opacity: 1;
}

@media (hover: none) {
  .wp-gallery-overlay {
    opacity: 1;
  }
}

.wp-gallery-info {
  z-index: 2;
  background: linear-gradient(transparent, rgba(0, 0, 0, 70%));
  inset-block-end: 0;
  inset-inline: 0;
  padding-block-start: 18px !important;
}

.wp-gallery-drop {
  position: absolute;
  z-index: 20;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed rgb(var(--v-theme-primary));
  border-radius: 12px;
  background: rgba(var(--v-theme-surface), 0.88);
  gap: 8px;
  inset: 0;
  pointer-events: none;
}

.wp-fade-enter-active,
.wp-fade-leave-active {
  transition: opacity 0.15s ease;
}

.wp-fade-enter-from,
.wp-fade-leave-to {
  opacity: 0;
}

/* ---- recorder ---- */
.wp-video-viewport {
  aspect-ratio: 16 / 9;
}

.wp-video-el {
  block-size: 100%;
  inline-size: 100%;
  object-fit: contain;
}

.wp-video-el--mirrored {
  transform: scaleX(-1);
}

.wp-video-loading {
  position: absolute;
  display: grid;
  background: rgba(0, 0, 0, 45%);
  inset: 0;
  place-items: center;
}

.wp-recording-badge {
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.wp-recording-dot {
  animation: wp-recording-pulse 1.1s ease-in-out infinite;
  border-radius: 50%;
  background: rgb(var(--v-theme-error));
  block-size: 10px;
  inline-size: 10px;
}

@keyframes wp-recording-pulse {
  0%,
  100% { opacity: 1; }
  50% { opacity: 0.3; }
}

@media (prefers-reduced-motion: reduce) {
  .wp-gallery-card,
  .wp-gallery-empty,
  .wp-gallery-overlay {
    transition: none;
  }

  .wp-recording-dot {
    animation: none;
  }
}
</style>
