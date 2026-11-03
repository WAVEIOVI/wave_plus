<script setup>
import { computed, nextTick, onBeforeUnmount, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCameraCapture } from '@/composables/camera/useCameraCapture'
import { matchesAccept } from '@/composables/documents/documentFiles'
import { clamp, clampPan, coverLayout, normaliseRotation } from '@/composables/media/imageTransforms'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  initialAvatarUrl: { type: String, default: '' },
  maxSizeMb: { type: Number, default: 10 },
  outputSize: { type: Number, default: 512 },

  /** Offer a "Remove photo" action when there is a current avatar. Emits `remove`. */
  allowRemove: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'error', 'close', 'remove'])

const { t } = useI18n()

const MAX_ZOOM = 4
const KEY_PAN_STEP = 12
const ACCEPT = 'image/jpeg,image/png,image/webp'

const {
  videoRef,
  isActiveRef: isCameraActive,
  isLoadingRef: isCameraLoading,
  errorRef: cameraError,
  selectableCamerasRef,
  currentCameraIdRef,
  hasMultipleCameras,
  hasRealMultipleCameras,
  isUserCamera,
  startCamera,
  stopCamera,
  switchCamera,
  selectCamera,
  captureFrameAsFile,
  CAMERA_FACING_MODES,
} = useCameraCapture()

// Local state
const mode = ref('upload') // 'upload' | 'camera' | 'crop'
const fileInputRef = ref(null)
const cropRef = ref(null)
const previewUrl = ref(props.initialAvatarUrl || '')
const zoom = ref(1)
const rotation = ref(0)
const pan = ref({ x: 0, y: 0 })
const viewportSize = ref(280)
const isDragging = ref(false)
const isFileDragging = ref(false)
const isProcessing = ref(false)
const errorMessage = ref('')
const natural = ref({ width: 0, height: 0 })

let resizeObserver = null
const pointers = new Map()
let gesture = null

// ---- crop geometry (see imageTransforms.coverLayout: same maths as the export) -----
const layout = computed(() => (natural.value.width
  ? coverLayout({
    imageWidth: natural.value.width,
    imageHeight: natural.value.height,
    rotation: rotation.value,
    zoom: zoom.value,
    size: viewportSize.value,
  })
  : null))

const imageStyle = computed(() => {
  if (!layout.value) return {}

  return {
    inlineSize: `${layout.value.drawWidth}px`,
    blockSize: `${layout.value.drawHeight}px`,
    transform: `translate(${pan.value.x}px, ${pan.value.y}px) translate(-50%, -50%) rotate(${rotation.value}deg)`,
  }
})

/** Re-clamp whenever size, zoom or rotation change so the circle never shows empty corners. */
watch(layout, value => {
  if (value) pan.value = clampPan(pan.value, value)
})

function measureViewport() {
  if (cropRef.value) viewportSize.value = cropRef.value.clientWidth || 280
}

watch(cropRef, el => {
  resizeObserver?.disconnect()
  if (el) {
    measureViewport()
    resizeObserver = new ResizeObserver(measureViewport)
    resizeObserver.observe(el)
  }
}, { flush: 'post' })

watch(cameraError, err => {
  if (err) {
    errorMessage.value = t('wp.avatar.camera_error', 'Unable to access camera. Please upload an image instead.')
  }
})

function revokeCurrentPreview() {
  if (previewUrl.value?.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
}

function resetState() {
  stopCamera()
  mode.value = 'upload'
  revokeCurrentPreview()
  previewUrl.value = props.initialAvatarUrl || ''
  natural.value = { width: 0, height: 0 }
  zoom.value = 1
  rotation.value = 0
  pan.value = { x: 0, y: 0 }
  errorMessage.value = ''
  isProcessing.value = false
}

watch(() => props.modelValue, isOpen => {
  if (isOpen) resetState()
  else stopCamera()
})

function handleClose() {
  emit('update:modelValue', false)
  emit('close')
}

function handleRemoveAvatar() {
  emit('remove')
  handleClose()
}

// ---- choose image -----------------------------------------------------------------
function triggerFileInput() {
  fileInputRef.value?.click()
}

function resetFraming() {
  zoom.value = 1
  rotation.value = 0
  pan.value = { x: 0, y: 0 }
}

function loadImage(url, file) {
  return new Promise((resolve, reject) => {
    const img = new Image()

    img.onload = () => {
      revokeCurrentPreview()
      previewUrl.value = url
      natural.value = { width: img.naturalWidth || img.width, height: img.naturalHeight || img.height }
      resetFraming()
      mode.value = 'crop'
      resolve(file)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Invalid image'))
    }
    img.src = url
  })
}

async function acceptFile(file) {
  if (!file) return
  errorMessage.value = ''

  if (!file.type.startsWith('image/') || !matchesAccept(file, ACCEPT)) {
    errorMessage.value = t('wp.avatar.error_not_image', 'Please select a valid image file (JPG, PNG, WebP).')

    return
  }

  if (file.size > props.maxSizeMb * 1024 * 1024) {
    errorMessage.value = t('wp.avatar.error_too_large', { size: props.maxSizeMb }, `Image file exceeds ${props.maxSizeMb} MB.`)

    return
  }

  try {
    await loadImage(URL.createObjectURL(file), file)
  } catch {
    errorMessage.value = t('wp.avatar.error_not_image', 'Please select a valid image file (JPG, PNG, WebP).')
  }
}

async function handleFileSelect(event) {
  const file = event.target.files?.[0]

  event.target.value = ''
  await acceptFile(file)
}

function handleFileDrop(event) {
  isFileDragging.value = false
  acceptFile(event.dataTransfer?.files?.[0])
}

async function startCameraMode() {
  errorMessage.value = ''
  mode.value = 'camera'
  await nextTick()
  try {
    await startCamera(CAMERA_FACING_MODES.USER)
  } catch {
    errorMessage.value = t('wp.avatar.camera_permission_denied', 'Camera access was denied or is unavailable.')
    mode.value = 'upload'
  }
}

async function leaveCamera() {
  mode.value = 'upload'
  await stopCamera()
}

async function takeSnapshot() {
  try {
    // The front camera preview is mirrored: mirror the photo too so it matches what the user framed.
    const result = await captureFrameAsFile(`avatar-capture-${Date.now()}.jpg`, {
      format: 'image/jpeg',
      quality: 0.95,
      mirror: isUserCamera.value,
    })

    await stopCamera()
    await loadImage(result.preview, result.file)
  } catch {
    errorMessage.value = t('wp.avatar.capture_failed', 'Failed to capture photo from camera.')
  }
}

// ---- framing: drag, pinch, wheel, keyboard, slider ---------------------------------------
function rotate(degrees = 90) {
  rotation.value = normaliseRotation(rotation.value + degrees)
}

/** Zoom about the centre: scale the pan with the image so the framed point stays put. */
function setZoom(next) {
  const target = clamp(next, 1, MAX_ZOOM)
  const ratio = target / zoom.value

  pan.value = { x: pan.value.x * ratio, y: pan.value.y * ratio }
  zoom.value = target
}

function applyPan(x, y) {
  pan.value = layout.value ? clampPan({ x, y }, layout.value) : { x: 0, y: 0 }
}

function pointerDistance() {
  const [a, b] = [...pointers.values()]

  return Math.hypot(a.x - b.x, a.y - b.y)
}

function onPointerDown(event) {
  if (mode.value !== 'crop') return
  event.currentTarget.setPointerCapture(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (pointers.size === 2) {
    gesture = { type: 'pinch', startDistance: pointerDistance(), startZoom: zoom.value }
    isDragging.value = false
  } else {
    gesture = { type: 'drag', startX: event.clientX, startY: event.clientY, startPan: { ...pan.value } }
    isDragging.value = true
  }
}

function onPointerMove(event) {
  if (!pointers.has(event.pointerId) || !gesture) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (gesture.type === 'pinch' && pointers.size >= 2) {
    setZoom(gesture.startZoom * (pointerDistance() / gesture.startDistance))
  } else if (gesture.type === 'drag') {
    applyPan(gesture.startPan.x + (event.clientX - gesture.startX), gesture.startPan.y + (event.clientY - gesture.startY))
  }
}

function onPointerUp(event) {
  pointers.delete(event.pointerId)
  if (!pointers.size) {
    gesture = null
    isDragging.value = false
  } else if (pointers.size === 1) {
    const [remaining] = [...pointers.values()]

    gesture = { type: 'drag', startX: remaining.x, startY: remaining.y, startPan: { ...pan.value } }
    isDragging.value = true
  }
}

function onWheel(event) {
  if (mode.value !== 'crop') return
  setZoom(zoom.value * Math.exp(-event.deltaY * (event.ctrlKey ? 0.01 : 0.002)))
}

function onCropKeydown(event) {
  const moves = {
    ArrowLeft: [KEY_PAN_STEP, 0],
    ArrowRight: [-KEY_PAN_STEP, 0],
    ArrowUp: [0, KEY_PAN_STEP],
    ArrowDown: [0, -KEY_PAN_STEP],
  }

  const move = moves[event.key]

  if (move) {
    event.preventDefault()
    applyPan(pan.value.x + move[0], pan.value.y + move[1])
  } else if (event.key === '+' || event.key === '=') {
    event.preventDefault()
    setZoom(zoom.value * 1.1)
  } else if (event.key === '-' || event.key === '_') {
    event.preventDefault()
    setZoom(zoom.value / 1.1)
  }
}

// ---- export ---------------------------------------------------------------------------------
async function applyCropAndSave() {
  if (!previewUrl.value || !layout.value) return

  isProcessing.value = true
  errorMessage.value = ''

  try {
    const image = new Image()

    image.src = previewUrl.value
    await image.decode()

    const size = props.outputSize
    const canvas = document.createElement('canvas')

    canvas.width = size
    canvas.height = size

    const ctx = canvas.getContext('2d')

    if (!ctx) throw new Error('Failed to create canvas context')

    ctx.imageSmoothingEnabled = true
    ctx.imageSmoothingQuality = 'high'

    // white behind transparent PNGs
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, size, size)

    // Exactly what the DOM shows (see imageStyle): viewport px -> output px.
    const k = size / viewportSize.value
    const { drawWidth, drawHeight } = layout.value

    ctx.translate(size / 2 + pan.value.x * k, size / 2 + pan.value.y * k)
    ctx.rotate((rotation.value * Math.PI) / 180)
    ctx.drawImage(image, (-drawWidth * k) / 2, (-drawHeight * k) / 2, drawWidth * k, drawHeight * k)

    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.92))

    canvas.width = 0
    canvas.height = 0

    if (!blob) throw new Error('Could not generate image blob')

    const file = new File([blob], `avatar-${Date.now()}.jpg`, { type: 'image/jpeg' })

    emit('save', {
      file,
      preview: URL.createObjectURL(blob),
      width: size,
      height: size,
    })

    handleClose()
  } catch (err) {
    console.error('Avatar crop failed:', err)
    errorMessage.value = t('wp.avatar.save_failed', 'Failed to process and crop avatar.')
    emit('error', err)
  } finally {
    isProcessing.value = false
  }
}

onBeforeUnmount(() => resizeObserver?.disconnect())

onUnmounted(() => {
  stopCamera()
  revokeCurrentPreview()
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="540"
    persistent
    class="wp-avatar-dialog"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="wp-avatar-card">
      <input
        ref="fileInputRef"
        type="file"
        :accept="ACCEPT"
        class="d-none"
        @change="handleFileSelect"
      >

      <VCardTitle class="d-flex align-center justify-space-between px-5 pt-4 pb-2">
        <div class="d-flex align-center gap-2">
          <VAvatar
            size="32"
            color="primary"
            variant="tonal"
            rounded
          >
            <VIcon
              icon="tabler-user-circle"
              size="20"
            />
          </VAvatar>
          <span class="text-h6 font-weight-medium">
            {{ title || $t('wp.avatar.dialog_title', 'Update Avatar Photo') }}
          </span>
        </div>
        <IconBtn
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
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Error Alert -->
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          density="compact"
          closable
          class="mb-4"
          role="alert"
          @click:close="errorMessage = ''"
        >
          {{ errorMessage }}
        </VAlert>

        <!-- Mode 1: Initial Chooser (Upload vs Camera) -->
        <div
          v-if="mode === 'upload'"
          class="wp-avatar-chooser d-flex flex-column align-center text-center py-4"
          @dragover.prevent="isFileDragging = true"
          @dragleave.prevent="isFileDragging = false"
          @drop.prevent="handleFileDrop"
        >
          <div
            class="wp-avatar-preview-circle mb-5"
            :class="{ 'wp-avatar-preview-circle--dragging': isFileDragging }"
          >
            <VAvatar
              size="140"
              color="primary"
              variant="tonal"
            >
              <VImg
                v-if="previewUrl"
                :src="previewUrl"
                cover
              />
              <VIcon
                v-else
                icon="tabler-user"
                size="64"
                color="primary"
              />
            </VAvatar>
          </div>

          <p class="text-body-2 text-medium-emphasis mb-5 wp-avatar-hint">
            {{ $t('wp.avatar.chooser_hint', 'Upload a square portrait photo or take a picture using your webcam/device camera.') }}
          </p>

          <div class="d-flex flex-wrap gap-3 justify-center w-100">
            <VBtn
              color="primary"
              prepend-icon="tabler-upload"
              @click="triggerFileInput"
            >
              {{ $t('wp.avatar.upload_photo', 'Upload Photo') }}
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-camera"
              @click="startCameraMode"
            >
              {{ $t('wp.avatar.take_photo', 'Take Photo') }}
            </VBtn>
          </div>

          <span class="text-caption text-disabled mt-3">
            {{ $t('wp.avatar.allowed_formats', 'Supports JPG, PNG, WebP up to {size}MB', { size: maxSizeMb }) }}
          </span>
        </div>

        <!-- Mode 2: Live Camera Viewfinder -->
        <div
          v-else-if="mode === 'camera'"
          class="wp-avatar-camera d-flex flex-column align-center"
        >
          <div class="wp-camera-viewport mb-4">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="wp-camera-video"
              :class="{ 'wp-camera-video--mirrored': isUserCamera }"
            />
            <div
              v-if="isCameraLoading"
              class="wp-camera-loading"
            >
              <VProgressCircular
                indeterminate
                color="primary"
              />
            </div>
          </div>

          <div class="d-flex align-center gap-3">
            <VBtn
              variant="tonal"
              size="small"
              prepend-icon="tabler-arrow-left"
              @click="leaveCamera"
            >
              {{ $t('common.back', 'Back') }}
            </VBtn>

            <VBtn
              color="primary"
              size="large"
              prepend-icon="tabler-camera"
              :disabled="isCameraLoading || !isCameraActive"
              @click="takeSnapshot"
            >
              {{ $t('wp.avatar.capture', 'Capture Photo') }}
            </VBtn>

            <VMenu
              v-if="hasRealMultipleCameras"
              location="top end"
            >
              <template #activator="{ props: menuProps }">
                <IconBtn
                  v-bind="menuProps"
                  variant="tonal"
                  size="small"
                  :title="$t('wp.camera.select_camera', 'Select camera')"
                  :aria-label="$t('wp.camera.select_camera', 'Select camera')"
                >
                  <VIcon
                    icon="tabler-video"
                    size="20"
                  />
                </IconBtn>
              </template>
              <VList
                density="compact"
                min-width="220"
              >
                <VListItem
                  v-for="(cam, idx) in selectableCamerasRef"
                  :key="cam.deviceId || idx"
                  :active="cam.deviceId === currentCameraIdRef"
                  @click="selectCamera(cam.deviceId)"
                >
                  <template #prepend>
                    <VIcon
                      :icon="cam.deviceId === currentCameraIdRef ? 'tabler-check' : 'tabler-camera'"
                      size="16"
                    />
                  </template>
                  <VListItemTitle class="text-caption">
                    {{ cam.label || `${$t('Camera')} ${idx + 1}` }}
                  </VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>

            <IconBtn
              v-if="hasMultipleCameras"
              variant="tonal"
              size="small"
              :title="$t('wp.camera.switch_camera', 'Switch camera')"
              :aria-label="$t('wp.camera.switch_camera', 'Switch camera')"
              @click="() => switchCamera()"
            >
              <VIcon
                icon="tabler-camera-rotate"
                size="20"
              />
            </IconBtn>
          </div>
        </div>

        <!-- Mode 3: Interactive Circular Crop & Zoom -->
        <div
          v-else-if="mode === 'crop'"
          class="wp-avatar-crop-view d-flex flex-column align-center"
        >
          <div
            ref="cropRef"
            class="wp-crop-container mb-4"
            :class="{ 'wp-crop-container--dragging': isDragging }"
            tabindex="0"
            role="application"
            :aria-label="$t('wp.avatar.crop_area', 'Photo framing area. Use arrow keys to move and plus or minus to zoom.')"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @wheel.prevent="onWheel"
            @keydown="onCropKeydown"
          >
            <img
              :src="previewUrl"
              class="wp-crop-image"
              :style="imageStyle"
              alt=""
              draggable="false"
            >

            <!-- Circular mask overlay: shows what the round avatar will look like -->
            <div class="wp-crop-mask" />
          </div>

          <!-- Controls: Zoom & Rotate -->
          <div class="w-100 px-2 mb-2">
            <div class="d-flex align-center gap-3 mb-2">
              <VIcon
                icon="tabler-zoom-out"
                size="18"
                color="medium-emphasis"
              />
              <VSlider
                :model-value="zoom"
                :min="1"
                :max="MAX_ZOOM"
                :step="0.02"
                density="compact"
                hide-details
                color="primary"
                :aria-label="$t('wp.avatar.zoom', 'Zoom')"
                @update:model-value="setZoom"
              />
              <VIcon
                icon="tabler-zoom-in"
                size="18"
                color="medium-emphasis"
              />

              <IconBtn
                variant="tonal"
                size="small"
                :title="$t('wp.avatar.rotate', 'Rotate 90 degrees')"
                :aria-label="$t('wp.avatar.rotate', 'Rotate 90 degrees')"
                @click="rotate(90)"
              >
                <VIcon
                  icon="tabler-rotate-clockwise-2"
                  size="18"
                />
              </IconBtn>
            </div>
            <p class="text-caption text-disabled text-center mb-0">
              {{ $t('wp.avatar.drag_hint', 'Drag the image to adjust position inside the circle.') }}
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-space-between">
        <VBtn
          v-if="mode === 'crop'"
          variant="text"
          size="small"
          prepend-icon="tabler-refresh"
          @click="resetState"
        >
          {{ $t('wp.avatar.choose_another', 'Choose Another') }}
        </VBtn>
        <VBtn
          v-else-if="allowRemove && initialAvatarUrl && mode === 'upload'"
          variant="text"
          size="small"
          color="error"
          prepend-icon="tabler-trash"
          @click="handleRemoveAvatar"
        >
          {{ $t('wp.avatar.remove', 'Remove photo') }}
        </VBtn>
        <div v-else />

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="handleClose"
          >
            {{ $t('common.cancel', 'Cancel') }}
          </VBtn>

          <VBtn
            v-if="mode === 'crop'"
            color="primary"
            :loading="isProcessing"
            prepend-icon="tabler-check"
            @click="applyCropAndSave"
          >
            {{ $t('wp.avatar.apply_photo', 'Save Avatar') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-avatar-hint {
  max-inline-size: 340px;
}

.wp-avatar-preview-circle {
  display: inline-block;
  border: 2px solid rgb(var(--v-theme-primary));
  border-radius: 50%;
  padding: 3px;
  transition: box-shadow 0.15s ease;
}

.wp-avatar-preview-circle--dragging {
  box-shadow: 0 0 0 6px rgba(var(--v-theme-primary), 0.2);
}

.wp-camera-viewport {
  position: relative;
  overflow: hidden;
  border: 2px solid rgb(var(--v-theme-primary));
  border-radius: 50%;
  background-color: #000;
  block-size: 260px;
  inline-size: 260px;
}

.wp-camera-video {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.wp-camera-video--mirrored {
  transform: scaleX(-1);
}

.wp-camera-loading {
  position: absolute;
  display: grid;
  background: rgba(0, 0, 0, 50%);
  inset: 0;
  place-items: center;
}

/* Square viewport that scales down on small phones; the image is cover-fit inside it. */
.wp-crop-container {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
  background-color: #1a1a1a;
  cursor: grab;
  aspect-ratio: 1;
  inline-size: min(300px, 100%);
  touch-action: none;
  user-select: none;
}

.wp-crop-container--dragging {
  cursor: grabbing;
}

.wp-crop-container:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

/* Sized and positioned by JS (cover layout). left/top are physical: image pixels never mirror in RTL. */
.wp-crop-image {
  position: absolute;
  left: 50%;
  max-block-size: none;
  max-inline-size: none;
  pointer-events: none;
  top: 50%;
  transform-origin: center;
}

.wp-crop-mask {
  position: absolute;
  border: 2px dashed rgba(255, 255, 255, 85%);
  border-radius: 50%;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 58%);
  inset: 0;
  pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
  .wp-avatar-preview-circle {
    transition: none;
  }
}
</style>
