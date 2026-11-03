<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { clickAnchor, downloadFromUrl, formatDocumentBytes as formatBytes, normalizeMediaUrl, resolveFileIcon, resolveFileKind, resolveFileMime } from '@/composables/documents/documentFiles'
import { clamp, isQuarterTurn } from '@/composables/media/imageTransforms'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: () => null },
  file: { type: Object, default: () => null },
  items: { type: Array, default: () => [] },
  initialIndex: { type: Number, default: 0 },
  title: { type: String, default: '' },
  canDownload: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'close', 'download', 'change'])

const { t } = useI18n()

const MIN_ZOOM = 0.5
const MAX_ZOOM = 5
const DOUBLE_TAP_ZOOM = 2.5
const STAGE_PADDING = 32
const SWIPE_DISTANCE = 60

const stageRef = ref(null)
const imageRef = ref(null)
const currentIndex = ref(props.initialIndex || 0)
const zoomLevel = ref(1)
const panX = ref(0)
const panY = ref(0)
const rotationDegrees = ref(0)
const stageSize = ref({ width: 0, height: 0 })
const isPanning = ref(false)
const imageLoaded = ref(false)
const imageFailed = ref(false)
const pdfLoadError = ref(false)
const pdfBlobUrl = ref(null)
const pdfLoading = ref(false)

function cleanupPdfBlob() {
  if (pdfBlobUrl.value) {
    if (pdfBlobUrl.value.startsWith('blob:')) {
      URL.revokeObjectURL(pdfBlobUrl.value)
    }
    pdfBlobUrl.value = null
  }
}

function getAuthToken() {
  if (typeof useCookie === 'function') {
    const val = useCookie('accessToken')?.value
    if (val) return val
  }
  if (typeof document !== 'undefined') {
    const match = document.cookie.match(/(?:^|;\s*)accessToken=([^;]+)/)
    if (match) {
      try {
        return decodeURIComponent(match[1]).replace(/^"|"$/g, '')
      } catch {
        return match[1]
      }
    }
  }

  return null
}

async function loadPdfBlob(url) {
  cleanupPdfBlob()

  const needsAuthBlob = isPdf.value || Boolean(activeItem.value?.is_private) || Boolean(url && url.includes('/api/'))
  if (!url || !needsAuthBlob) return

  if (url.startsWith('blob:') || url.startsWith('data:')) {
    pdfBlobUrl.value = url

    return
  }

  pdfLoading.value = true
  pdfLoadError.value = false
  try {
    const token = getAuthToken()
    const headers = { 'X-Requested-With': 'XMLHttpRequest', Accept: isPdf.value ? 'application/pdf,*/*' : '*/*' }
    if (token) headers.Authorization = `Bearer ${token}`

    const res = await fetch(url, { credentials: 'include', headers })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const blob = await res.blob()

    pdfBlobUrl.value = URL.createObjectURL(blob)
  } catch (err) {
    console.warn('Media preview load error:', err)
    pdfLoadError.value = true
    if (isImage.value) imageFailed.value = true
  } finally {
    pdfLoading.value = false
  }
}

let resizeObserver = null
const pointers = new Map()
let gesture = null

watch(
  () => props.initialIndex,
  val => {
    if (val !== undefined && val >= 0) {
      currentIndex.value = val
    }
  },
)

watch(
  () => [props.item, props.file],
  ([valItem, valFile]) => {
    const val = valItem || valFile
    if (val && props.items.length) {
      const idx = props.items.findIndex(i => (i.id && i.id === val.id) || (i.url && i.url === val.url))

      if (idx !== -1) {
        currentIndex.value = idx
      }
    }
  },
)

const activeItem = computed(() => {
  if (props.items.length > 0) {
    return props.items[currentIndex.value] || props.items[0] || null
  }

  return props.item || props.file || null
})

const hasMultipleItems = computed(() => props.items.length > 1)

const activeUrl = computed(() => {
  const it = activeItem.value

  if (!it) return ''

  const raw = it.preview_url || it.url || it.original_url || it.file_url || it.download_url || it.downloadUrl || (typeof it === 'string' ? it : '')

  return normalizeMediaUrl(raw)
})

const activeDownloadUrl = computed(() => {
  const it = activeItem.value

  if (!it) return ''

  const raw = it.download_url || it.downloadUrl || it.url || it.original_url || (typeof it === 'string' ? it : '')

  return normalizeMediaUrl(raw)
})

const activeTitle = computed(() => {
  const it = activeItem.value

  if (!it) return props.title || t('common.preview', 'Preview')

  return it.title || it.custom_properties?.title || it.name || it.file_name || props.title || t('common.preview', 'Preview')
})

const activeMime = computed(() => {
  const it = activeItem.value

  if (!it) return ''

  return resolveFileMime({ type: it.mime_type || it.mimeType, name: it.file_name || it.name || it.title || activeUrl.value })
})

const activeKind = computed(() => resolveFileKind(activeMime.value, activeItem.value?.file_name || activeItem.value?.name || activeItem.value?.title || activeUrl.value))
const isImage = computed(() => activeKind.value === 'image')
const isVideo = computed(() => activeKind.value === 'video')
const isPdf = computed(() => activeKind.value === 'pdf')

const displayMediaUrl = computed(() => {
  if (activeItem.value?.is_private || activeUrl.value.includes('/api/')) {
    return pdfBlobUrl.value || ''
  }

  return activeUrl.value
})

const quarterTurned = computed(() => isQuarterTurn(rotationDegrees.value))
const isZoomed = computed(() => zoomLevel.value !== 1 || panX.value !== 0 || panY.value !== 0 || rotationDegrees.value !== 0)

/** Rotating 90deg swaps which dimension must fit the stage. */
const imageStyle = computed(() => {
  const width = Math.max(0, stageSize.value.width - STAGE_PADDING)
  const height = Math.max(0, stageSize.value.height - STAGE_PADDING)
  const [maxWidth, maxHeight] = quarterTurned.value ? [height, width] : [width, height]

  return {
    maxWidth: `${maxWidth}px`,
    maxHeight: `${maxHeight}px`,
    transform: `translate(${panX.value}px, ${panY.value}px) scale(${zoomLevel.value}) rotate(${rotationDegrees.value}deg)`,
  }
})

function iconForItem(item) {
  return resolveFileIcon(item?.mime_type, item?.url || item?.title)
}

function thumbIsImage(item) {
  return resolveFileKind(item?.mime_type, item?.url || item?.title) === 'image'
}

// ---- view state -------------------------------------------------------------
function resetView() {
  zoomLevel.value = 1
  panX.value = 0
  panY.value = 0
  rotationDegrees.value = 0
}

watch(activeUrl, url => {
  resetView()
  imageLoaded.value = false
  imageFailed.value = false
  pdfLoadError.value = false
  loadPdfBlob(url)
  preloadNeighbours()
})

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      resetView()
      imageLoaded.value = false
      imageFailed.value = false
      pdfLoadError.value = false
      loadPdfBlob(activeUrl.value)
      window.addEventListener('keydown', handleKeydown)
    } else {
      cleanupPdfBlob()
      window.removeEventListener('keydown', handleKeydown)
    }
  },
  { immediate: true },
)

function preloadNeighbours() {
  if (!hasMultipleItems.value) return

  ;[1, -1].forEach(offset => {
    const neighbour = props.items[(currentIndex.value + offset + props.items.length) % props.items.length]

    if (neighbour && thumbIsImage(neighbour)) new Image().src = neighbour.url || neighbour.original_url || ''
  })
}

/** Keep the picture reachable: you can only pan as far as the zoomed image overflows the stage. */
function clampPan() {
  const img = imageRef.value

  if (!img || zoomLevel.value <= 1) {
    panX.value = 0
    panY.value = 0

    return
  }

  const baseWidth = quarterTurned.value ? img.offsetHeight : img.offsetWidth
  const baseHeight = quarterTurned.value ? img.offsetWidth : img.offsetHeight
  const maxX = Math.max(0, (baseWidth * zoomLevel.value - stageSize.value.width) / 2)
  const maxY = Math.max(0, (baseHeight * zoomLevel.value - stageSize.value.height) / 2)

  panX.value = clamp(panX.value, -maxX, maxX)
  panY.value = clamp(panY.value, -maxY, maxY)
}

function setZoom(value) {
  zoomLevel.value = clamp(+value.toFixed(3), MIN_ZOOM, MAX_ZOOM)
  clampPan()
}

const zoomIn = () => setZoom(zoomLevel.value * 1.25)
const zoomOut = () => setZoom(zoomLevel.value / 1.25)

function rotateImage() {
  rotationDegrees.value = (rotationDegrees.value + 90) % 360
  nextTick(clampPan)
}

function toggleZoom() {
  if (zoomLevel.value > 1) resetView()
  else setZoom(DOUBLE_TAP_ZOOM)
}

// ---- pointer gestures: pan (1 finger), pinch (2), swipe (1 finger at zoom 1) ---------
function distanceBetweenPointers() {
  const [a, b] = [...pointers.values()]

  return Math.hypot(a.x - b.x, a.y - b.y)
}

function onPointerDown(event) {
  if (!isImage.value) return
  event.currentTarget.setPointerCapture?.(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (pointers.size === 2) {
    gesture = { type: 'pinch', startDistance: distanceBetweenPointers(), startZoom: zoomLevel.value }
    isPanning.value = false
  } else {
    gesture = {
      type: 'single',
      startX: event.clientX,
      startY: event.clientY,
      startPanX: panX.value,
      startPanY: panY.value,
    }
    isPanning.value = zoomLevel.value > 1
  }
}

function onPointerMove(event) {
  if (!pointers.has(event.pointerId) || !gesture) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (gesture.type === 'pinch' && pointers.size >= 2) {
    zoomLevel.value = clamp(gesture.startZoom * (distanceBetweenPointers() / gesture.startDistance), MIN_ZOOM, MAX_ZOOM)
    clampPan()
  } else if (gesture.type === 'single' && zoomLevel.value > 1) {
    panX.value = gesture.startPanX + (event.clientX - gesture.startX)
    panY.value = gesture.startPanY + (event.clientY - gesture.startY)
    clampPan()
  }
}

function isRtl() {
  return stageRef.value ? getComputedStyle(stageRef.value).direction === 'rtl' : false
}

function onPointerUp(event) {
  const wasSingle = gesture?.type === 'single' && pointers.size === 1

  pointers.delete(event.pointerId)
  isPanning.value = false

  if (wasSingle && zoomLevel.value <= 1 && hasMultipleItems.value) {
    const dx = event.clientX - gesture.startX
    const dy = event.clientY - gesture.startY

    if (Math.abs(dx) > SWIPE_DISTANCE && Math.abs(dy) < SWIPE_DISTANCE) {
      // swiping toward the start edge reveals the NEXT item in LTR, the PREVIOUS in RTL
      const towardStart = isRtl() ? dx > 0 : dx < 0

      if (towardStart) handleNext()
      else handlePrev()
    }
  }

  if (!pointers.size) gesture = null
  else if (pointers.size === 1 && gesture?.type === 'pinch') {
    const [remaining] = [...pointers.values()]

    gesture = { type: 'single', startX: remaining.x, startY: remaining.y, startPanX: panX.value, startPanY: panY.value }
    isPanning.value = zoomLevel.value > 1
  }
}

function onWheel(event) {
  if (!isImage.value) return
  setZoom(zoomLevel.value * Math.exp(-event.deltaY * (event.ctrlKey ? 0.01 : 0.0015)))
}

// ---- navigation / actions ---------------------------------------------------
function handleClose() {
  emit('update:modelValue', false)
  emit('close')
}

function goTo(index) {
  if (props.items.length <= 1) return
  currentIndex.value = (index + props.items.length) % props.items.length
  emit('change', activeItem.value)
}

const handleNext = () => goTo(currentIndex.value + 1)
const handlePrev = () => goTo(currentIndex.value - 1)

async function handleDownloadClick() {
  emit('download', activeItem.value)
  if (pdfBlobUrl.value) {
    clickAnchor(pdfBlobUrl.value, activeTitle.value || 'document.pdf')
  } else if (activeDownloadUrl.value) {
    try {
      await downloadFromUrl(activeDownloadUrl.value, activeTitle.value || 'download')
    } catch (err) {
      console.warn('Media download failed:', err)
    }
  }
}

async function openInNewTab() {
  if (pdfBlobUrl.value) {
    window.open(pdfBlobUrl.value, '_blank')
  } else if (activeUrl.value) {
    try {
      await downloadFromUrl(activeUrl.value, activeTitle.value || 'download')
    } catch (err) {
      console.warn('Open in new tab failed:', err)
    }
  }
}

/**
 * Only bound while the viewer is open. Esc is left to VDialog (handling it here
 * too used to close the viewer twice). Arrow keys respect RTL.
 */
function handleKeydown(event) {
  if (!props.modelValue || event.defaultPrevented) return
  if (event.target?.tagName === 'INPUT' || event.target?.tagName === 'TEXTAREA') return

  const key = event.key

  if ((key === 'ArrowRight' || key === 'ArrowLeft') && hasMultipleItems.value && zoomLevel.value <= 1) {
    const forward = (key === 'ArrowRight') !== isRtl()

    if (forward) handleNext()
    else handlePrev()
  } else if (isImage.value && (key === '+' || key === '=')) {
    zoomIn()
  } else if (isImage.value && (key === '-' || key === '_')) {
    zoomOut()
  } else if (isImage.value && key === '0') {
    resetView()
  } else if (isImage.value && key.toLowerCase() === 'r') {
    rotateImage()
  }
}

function measureStage() {
  const el = stageRef.value

  if (!el) return
  stageSize.value = { width: el.clientWidth, height: el.clientHeight }
  clampPan()
}

function onAfterEnter() {
  measureStage()
  resizeObserver?.disconnect()
  if (stageRef.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(measureStage)
    resizeObserver.observe(stageRef.value)
  }
  preloadNeighbours()
}

function onAfterLeave() {
  resizeObserver?.disconnect()
  resizeObserver = null
  pointers.clear()
  gesture = null
}

onBeforeUnmount(() => {
  cleanupPdfBlob()
  window.removeEventListener('keydown', handleKeydown)
  resizeObserver?.disconnect()
  resizeObserver = null
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    fullscreen
    transition="dialog-bottom-transition"
    class="wp-media-viewer-dialog"
    @update:model-value="$emit('update:modelValue', $event)"
    @after-enter="onAfterEnter"
    @after-leave="onAfterLeave"
  >
    <VCard
      theme="dark"
      class="wp-viewer-card d-flex flex-column h-100 overflow-hidden"
    >
      <!-- Header Toolbar -->
      <header class="wp-viewer-header d-flex align-center justify-space-between px-4 py-2">
        <div class="d-flex align-center gap-3 overflow-hidden me-2">
          <VAvatar
            size="32"
            color="primary"
            variant="tonal"
            rounded
            class="flex-shrink-0"
          >
            <VIcon
              :icon="resolveFileIcon(activeMime, activeUrl)"
              size="18"
            />
          </VAvatar>
          <div class="overflow-hidden">
            <h6 class="text-subtitle-1 font-weight-medium text-truncate mb-0">
              {{ activeTitle }}
            </h6>
            <div class="d-flex align-center gap-2 text-caption text-medium-emphasis">
              <span v-if="activeItem?.size">{{ formatBytes(activeItem.size) }}</span>
              <span v-if="hasMultipleItems">{{ currentIndex + 1 }} / {{ items.length }}</span>
              <span v-if="isImage && zoomLevel !== 1">{{ Math.round(zoomLevel * 100) }}%</span>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="d-flex align-center gap-1 flex-shrink-0">
          <template v-if="isImage">
            <IconBtn
              variant="text"
              size="small"
              :title="$t('wp.viewer.zoom_in', 'Zoom in')"
              :aria-label="$t('wp.viewer.zoom_in', 'Zoom in')"
              @click="zoomIn"
            >
              <VIcon
                icon="tabler-zoom-in"
                size="18"
              />
            </IconBtn>
            <IconBtn
              variant="text"
              size="small"
              :title="$t('wp.viewer.zoom_out', 'Zoom out')"
              :aria-label="$t('wp.viewer.zoom_out', 'Zoom out')"
              @click="zoomOut"
            >
              <VIcon
                icon="tabler-zoom-out"
                size="18"
              />
            </IconBtn>
            <IconBtn
              variant="text"
              size="small"
              :title="$t('wp.viewer.rotate', 'Rotate')"
              :aria-label="$t('wp.viewer.rotate', 'Rotate')"
              @click="rotateImage"
            >
              <VIcon
                icon="tabler-rotate-clockwise-2"
                size="18"
              />
            </IconBtn>
            <IconBtn
              v-if="isZoomed"
              variant="text"
              size="small"
              :title="$t('wp.viewer.reset', 'Reset zoom')"
              :aria-label="$t('wp.viewer.reset', 'Reset zoom')"
              @click="resetView"
            >
              <VIcon
                icon="tabler-zoom-reset"
                size="18"
              />
            </IconBtn>
          </template>

          <IconBtn
            v-if="canDownload && activeDownloadUrl"
            variant="text"
            size="small"
            :title="$t('common.download', 'Download')"
            :aria-label="$t('common.download', 'Download')"
            @click="handleDownloadClick"
          >
            <VIcon
              icon="tabler-download"
              size="18"
            />
          </IconBtn>

          <IconBtn
            v-if="activeUrl"
            variant="text"
            size="small"
            :title="$t('wp.actions.open_original', 'Open original')"
            :aria-label="$t('wp.actions.open_original', 'Open original')"
            :href="activeUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <VIcon
              icon="tabler-external-link"
              size="18"
            />
          </IconBtn>

          <VDivider
            vertical
            class="mx-1 my-2 border-opacity-25"
          />

          <IconBtn
            variant="text"
            size="small"
            :title="$t('common.close', 'Close')"
            :aria-label="$t('common.close', 'Close')"
            @click="handleClose"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </div>
      </header>

      <!-- Main Display Area -->
      <main
        ref="stageRef"
        class="wp-viewer-stage"
        :class="{ 'wp-viewer-stage--zoomed': isImage && zoomLevel > 1, 'wp-viewer-stage--panning': isPanning }"
        @wheel.prevent="onWheel"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @dblclick="isImage && toggleZoom()"
      >
        <!-- Image Viewer -->
        <template v-if="isImage && (displayMediaUrl || pdfLoading) && !imageFailed">
          <img
            v-if="displayMediaUrl"
            ref="imageRef"
            :src="displayMediaUrl"
            :alt="activeTitle"
            class="wp-viewer-image"
            :class="{ 'wp-viewer-image--still': isPanning }"
            :style="imageStyle"
            draggable="false"
            @load="imageLoaded = true; measureStage()"
            @error="imageFailed = true"
          >
          <VProgressCircular
            v-if="!imageLoaded || pdfLoading"
            indeterminate
            color="primary"
            class="wp-viewer-loading"
          />
        </template>

        <!-- Video Player -->
        <video
          v-else-if="isVideo && displayMediaUrl"
          :key="displayMediaUrl"
          controls
          autoplay
          playsinline
          class="wp-viewer-video"
          :src="displayMediaUrl"
        >
          {{ $t('wp.viewer.video_unsupported', 'Your browser does not support the video tag.') }}
        </video>

        <!-- PDF loading spinner -->
        <div
          v-else-if="isPdf && pdfLoading"
          class="d-flex flex-column align-center justify-center fill-height py-12"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="48"
            class="mb-3"
          />
          <span class="text-caption text-medium-emphasis">{{ $t('wp.viewer.loading_pdf', 'Loading document preview…') }}</span>
        </div>

        <!-- PDF Viewer with fallback -->
        <iframe
          v-else-if="isPdf && pdfBlobUrl && !pdfLoadError"
          :key="pdfBlobUrl"
          :src="`${pdfBlobUrl}#toolbar=1&navpanes=0`"
          class="wp-viewer-pdf-frame"
          :title="activeTitle"
          @error="pdfLoadError = true"
        />

        <!-- Unsupported / Failed State -->
        <div
          v-else
          class="wp-viewer-fallback text-center pa-6"
        >
          <VAvatar
            size="72"
            color="surface"
            variant="tonal"
            class="mb-4"
          >
            <VIcon
              :icon="imageFailed ? 'tabler-photo-off' : isPdf ? 'tabler-file-type-pdf' : 'tabler-file'"
              size="36"
              color="primary"
            />
          </VAvatar>
          <h5 class="text-h6 mb-2">
            {{ activeTitle }}
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-4 wp-viewer-fallback__text">
            {{ imageFailed
              ? $t('wp.viewer.image_failed', 'This image could not be loaded. It may have been moved or you may be offline.')
              : pdfLoadError
                ? $t('wp.viewer.pdf_load_failed', 'The document file could not be loaded from the server. It may have been moved or deleted.')
                : $t('wp.viewer.browser_preview_unavailable', 'This file type cannot be previewed directly inside the browser. You can open or download the file.') }}
          </p>
          <div class="d-flex justify-center gap-3">
            <VBtn
              v-if="pdfLoadError && activeUrl"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-refresh"
              @click="loadPdfBlob(activeUrl)"
            >
              {{ $t('common.retry', 'Retry') }}
            </VBtn>
            <VBtn
              v-else-if="pdfBlobUrl || activeUrl"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-external-link"
              @click="openInNewTab"
            >
              {{ $t('wp.actions.open_in_new_tab', 'Open in New Tab') }}
            </VBtn>
            <VBtn
              v-if="canDownload && activeDownloadUrl"
              variant="flat"
              color="primary"
              prepend-icon="tabler-download"
              @click="handleDownloadClick"
            >
              {{ $t('common.download', 'Download File') }}
            </VBtn>
          </div>
        </div>

        <!-- Next / Previous Navigation Controls (mirror in RTL) -->
        <IconBtn
          v-if="hasMultipleItems"
          variant="flat"
          class="wp-nav-arrow wp-nav-arrow--prev"
          :aria-label="$t('common.previous', 'Previous')"
          @click.stop="handlePrev"
          @pointerdown.stop
          @dblclick.stop
        >
          <VIcon
            icon="tabler-chevron-left"
            size="28"
            class="wp-flip-rtl"
          />
        </IconBtn>

        <IconBtn
          v-if="hasMultipleItems"
          variant="flat"
          class="wp-nav-arrow wp-nav-arrow--next"
          :aria-label="$t('common.next', 'Next')"
          @click.stop="handleNext"
          @pointerdown.stop
          @dblclick.stop
        >
          <VIcon
            icon="tabler-chevron-right"
            size="28"
            class="wp-flip-rtl"
          />
        </IconBtn>
      </main>

      <!-- Filmstrip -->
      <footer
        v-if="hasMultipleItems"
        class="wp-viewer-strip"
        role="tablist"
        :aria-label="$t('wp.viewer.items', 'Items')"
      >
        <button
          v-for="(entry, idx) in items"
          :key="entry.id || idx"
          type="button"
          role="tab"
          class="wp-viewer-thumb"
          :class="{ 'wp-viewer-thumb--active': idx === currentIndex }"
          :aria-selected="idx === currentIndex"
          :aria-label="entry.title || entry.name || entry.file_name || `${idx + 1}`"
          @click="goTo(idx)"
        >
          <img
            v-if="thumbIsImage(entry)"
            :src="entry.thumb_url || entry.preview_url || entry.url"
            alt=""
            loading="lazy"
          >
          <VIcon
            v-else
            :icon="iconForItem(entry)"
            size="22"
          />
        </button>
      </footer>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-viewer-card {
  background: #0c0c0e;
  user-select: none;
}

.wp-viewer-header {
  border-block-end: 1px solid rgba(255, 255, 255, 12%);
  background: #08080a;
}

.wp-viewer-stage {
  position: relative;
  display: flex;
  overflow: hidden;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  min-block-size: 0;
  touch-action: none;
}

.wp-viewer-stage--zoomed {
  cursor: grab;
}

.wp-viewer-stage--panning {
  cursor: grabbing;
}

.wp-viewer-image {
  box-shadow: 0 10px 40px rgba(0, 0, 0, 50%);
  object-fit: contain;
  transition: transform 0.18s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: transform;
}

/* No easing while a finger/mouse is dragging: it must track 1:1. */
.wp-viewer-image--still {
  transition: none;
}

.wp-viewer-loading {
  position: absolute;
}

.wp-viewer-video {
  border-radius: 8px;
  max-block-size: 85%;
  max-inline-size: 90%;
  outline: none;
}

.wp-viewer-pdf-frame {
  border: 0;
  background-color: #525659;
  block-size: 100%;
  inline-size: 100%;
}

.wp-viewer-fallback__text {
  margin-inline: auto;
  max-inline-size: 420px;
}

.wp-nav-arrow {
  position: absolute;
  z-index: 10;
  background: rgba(0, 0, 0, 55%) !important;
  backdrop-filter: blur(8px);
  color: #fff;
  inset-block-start: 50%;
  transform: translateY(-50%);
}

.wp-nav-arrow--prev {
  inset-inline-start: 16px;
}

.wp-nav-arrow--next {
  inset-inline-end: 16px;
}

.wp-viewer-strip {
  display: flex;
  overflow-x: auto;
  justify-content: center;
  padding: 8px 12px;
  border-block-start: 1px solid rgba(255, 255, 255, 12%);
  background: #08080a;
  gap: 8px;
  scrollbar-width: thin;
}

.wp-viewer-thumb {
  display: grid;
  overflow: hidden;
  flex: 0 0 auto;
  border: 2px solid transparent;
  border-radius: 6px;
  background: rgba(255, 255, 255, 8%);
  block-size: 52px;
  color: rgba(255, 255, 255, 70%);
  cursor: pointer;
  inline-size: 52px;
  opacity: 0.65;
  place-items: center;
  transition: opacity 0.15s ease, border-color 0.15s ease;
}

.wp-viewer-thumb img {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.wp-viewer-thumb:hover {
  opacity: 1;
}

.wp-viewer-thumb--active {
  border-color: rgb(var(--v-theme-primary));
  opacity: 1;
}

.wp-viewer-thumb:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .wp-viewer-image,
  .wp-viewer-thumb {
    transition: none;
  }
}

/* Directional icons mirror in RTL (defined here so it doesn't depend on a theme utility class). */
[dir="rtl"] .wp-flip-rtl {
  transform: scaleX(-1);
}
</style>
