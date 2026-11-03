<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import WpWizardDialog from '@/components/shared/wizard/WpWizardDialog.vue'
import { formatDocumentBytes as formatBytes, matchesAccept, titleFromFileName, uid } from '@/composables/documents/documentFiles'
import {
  CROP_HANDLES,
  applyAspect,
  createCenteredRect,
  fitContain,
  moveRect,
  normalisedToRect,
  rectToNormalised,
  resizeRect,
  rotatedSize,
} from '@/composables/media/imageTransforms'
import { useImageCapture } from '@/composables/media/useImageCapture'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  accept: { type: String, default: 'image/*' },
  capture: { type: Boolean, default: true },
  showCropper: { type: Boolean, default: true },
  showTitleInput: { type: Boolean, default: true },
  initialTitle: { type: String, default: '' },
  aspect: { type: Number, default: null },
  maxSizeMb: { type: Number, default: 20 },
  multiple: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'save', 'error', 'close'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const KEY_CROP_STEP = 8
const KEY_CROP_STEP_LARGE = 24
const MIN_CROP_SIZE = 36

// ── Images List & Selected Active Image ──────────────────────────────────────
const images = ref([])
const activeIndex = ref(0)
const isSaving = ref(false)
const isDraggingFile = ref(false)
const isShutterFlashing = ref(false)
const showCameraGrid = ref(true)
const showCropGrid = ref(true)
const cameraMenuOpen = ref(false)
const localNotice = ref('')
const activeEditorTab = ref('transform') // 'transform' | 'filters' | 'details'
const fileInputRef = ref(null)
const appendInputRef = ref(null)
const wizardRef = ref(null)

// ── Camera Composable ────────────────────────────────────────────────────────
const {
  videoRef,
  isActiveRef: isCameraActive,
  isLoadingRef: isCameraLoading,
  errorRef: cameraError,
  hasMultipleCameras,
  hasRealMultipleCameras,
  selectableCamerasRef,
  currentCameraIdRef,
  isUserCamera,
  torchSupportedRef: torchSupported,
  torchEnabledRef: torchEnabled,
  toggleTorch,
  zoomSupportedRef: zoomSupported,
  zoomRef,
  zoomRangeRef,
  setZoom,
  selectCamera,
  switchCamera,
  startCamera,
  stopCamera,
  captureFrameAsFile,
} = useImageCapture({ idealWidth: 1920, idealHeight: 1080 })

// ── Auto Camera Activation on Viewfinder Mount ───────────────────────────────
watch(videoRef, async el => {
  if (el) {
    try {
      await startCamera()
    }
    catch (err) {
      console.warn('Auto start photo camera error:', err)
    }
  }
  else {
    stopCamera()
  }
})

async function handleRetryCamera() {
  localNotice.value = ''
  try {
    await startCamera()
  }
  catch (err) {
    console.error('Manual retry camera error:', err)
  }
}

async function handleSelectCamera(deviceId) {
  try {
    await selectCamera(deviceId)
  }
  catch (err) {
    console.warn('Camera switch error:', err)
  }
}

function cycleCameraZoom() {
  const { min, max } = zoomRangeRef.value
  const presets = [1, 1.5, 2, 3].filter(v => v >= min && v <= max)
  const next = presets.find(v => v > zoomRef.value + 0.05) ?? presets[0] ?? min

  setZoom(next)
}

const cameraZoomLabel = computed(() => `${Number(zoomRef.value.toFixed(1))}×`)

// ── Active Image Computed Helpers ────────────────────────────────────────────
const activeImage = computed(() => images.value[activeIndex.value] || null)
const maxBytes = computed(() => props.maxSizeMb * 1024 * 1024)
const totalBytes = computed(() => images.value.reduce((sum, item) => sum + (item.file?.size || 0), 0))

// ── Crop & Stage State ───────────────────────────────────────────────────────
const stageRef = ref(null)
const stageSize = ref({ width: 480, height: 340 })
const cropDraft = ref(null)
const activeHandle = ref(null)
let resizeObserver = null
let drag = null
let reviewRenderToken = 0

const aspectOptions = computed(() => [
  { key: 'free', label: t('wp.image.aspect_free', 'Free'), icon: 'tabler-Dimensions', value: null },
  { key: '1:1', label: '1:1', icon: 'tabler-square', value: 1 },
  { key: '4:3', label: '4:3', icon: 'tabler-rectangle', value: 4 / 3 },
  { key: '16:9', label: '16:9', icon: 'tabler-app-window', value: 16 / 9 },
  { key: '3:4', label: '3:4', icon: 'tabler-rectangle-vertical', value: 3 / 4 },
])

const filterOptions = computed(() => [
  { key: 'normal', label: t('Original'), icon: 'tabler-photo' },
  { key: 'enhance', label: t('Enhance'), icon: 'tabler-wand' },
  { key: 'vibrant', label: t('Vibrant'), icon: 'tabler-sun' },
  { key: 'bw', label: t('B&W'), icon: 'tabler-contrast' },
  { key: 'warm', label: t('Warm'), icon: 'tabler-flame' },
  { key: 'cool', label: t('Cool'), icon: 'tabler-snowflake' },
])

// ── Wizard Steps Definition ──────────────────────────────────────────────────
const wizardSteps = computed(() => {
  const steps = []

  if (props.capture) {
    steps.push({
      id: 'source',
      title: t('Source Choice'),
      subtitle: t('Camera or Upload'),
      icon: 'tabler-camera-plus',
      validate: form => {
        if (!form.source) return t('Please choose a capture or upload mode.')

        return true
      },
    })
  }

  steps.push({
    id: 'capture',
    title: t('Add Photos'),
    subtitle: t('Snap or upload images'),
    icon: 'tabler-photo-plus',
    validate: () => {
      if (!images.value.length) return t('Please capture or upload at least one image.')

      return true
    },
  })

  if (props.showCropper || props.showTitleInput) {
    steps.push({
      id: 'edit',
      title: t('Edit & Studio'),
      subtitle: t('Crop, filter & details'),
      icon: 'tabler-adjustments-horizontal',
      validate: () => {
        if (!images.value.length) return t('No images to edit.')

        return true
      },
    })
  }

  return steps
})

// ── Stage & Crop Geometry ────────────────────────────────────────────────────
function measureStage() {
  const el = stageRef.value
  if (!el) return

  const pad = smAndDown.value ? 16 : 24
  const nextWidth = Math.max(160, el.clientWidth - pad)
  const nextHeight = Math.max(160, el.clientHeight - pad)

  stageSize.value = { width: nextWidth, height: nextHeight }

  if (activeImage.value && !cropDraft.value) {
    initCropForActive()
  }
}

watch(stageRef, el => {
  resizeObserver?.disconnect()
  if (el) {
    measureStage()
    resizeObserver = new ResizeObserver(measureStage)
    resizeObserver.observe(el)
    nextTick(() => {
      measureStage()
      initCropForActive()
    })
  }
})

const activeDisplayedSize = computed(() => {
  if (!activeImage.value) return { width: 1, height: 1 }

  return rotatedSize(
    activeImage.value.naturalWidth || 800,
    activeImage.value.naturalHeight || 600,
    activeImage.value.rotation || 0,
  )
})

const fullBox = computed(() => {
  const fitted = fitContain(
    stageSize.value.width,
    stageSize.value.height,
    activeDisplayedSize.value.width / activeDisplayedSize.value.height,
  )

  return {
    x: Math.max(0, Math.floor((stageSize.value.width - fitted.width) / 2)),
    y: Math.max(0, Math.floor((stageSize.value.height - fitted.height) / 2)),
    width: fitted.width,
    height: fitted.height,
  }
})

const quarterTurned = computed(() => ((activeImage.value?.rotation || 0) % 180 !== 0))

const activeImageStyle = computed(() => {
  if (!activeImage.value) return {}
  const box = fullBox.value
  const rot = activeImage.value.rotation || 0
  const flipH = activeImage.value.flipH ? -1 : 1
  const flipV = activeImage.value.flipV ? -1 : 1

  return {
    inlineSize: `${quarterTurned.value ? box.height : box.width}px`,
    blockSize: `${quarterTurned.value ? box.width : box.height}px`,
    transform: `translate(-50%, -50%) rotate(${rot}deg) scale(${flipH}, ${flipV})`,
    filter: getFilterCss(activeImage.value.filter, activeImage.value.brightness, activeImage.value.contrast),
  }
})

const cropPx = computed(() => {
  if (!cropDraft.value || !fullBox.value.width || !fullBox.value.height) return null

  return normalisedToRect(cropDraft.value, fullBox.value)
})

const cropStyle = computed(() => {
  const rect = cropPx.value
  if (!rect) return {}

  return {
    left: `${Math.round(rect.x)}px`,
    top: `${Math.round(rect.y)}px`,
    width: `${Math.round(rect.width)}px`,
    height: `${Math.round(rect.height)}px`,
  }
})

const cropOutputDimensions = computed(() => {
  if (!activeImage.value) return { width: 0, height: 0 }
  const rotSize = activeDisplayedSize.value
  const crop = cropDraft.value || activeImage.value.cropRect || { x: 0, y: 0, width: 1, height: 1 }

  return {
    width: Math.max(1, Math.round(crop.width * rotSize.width)),
    height: Math.max(1, Math.round(crop.height * rotSize.height)),
  }
})

function getFilterCss(filterName, brightness = 0, contrast = 0) {
  let base = `brightness(${1 + brightness / 100}) contrast(${1 + contrast / 100})`
  if (filterName === 'enhance') base += ' saturate(1.25) contrast(1.12)'
  else if (filterName === 'vibrant') base += ' saturate(1.48) brightness(1.04)'
  else if (filterName === 'bw') base += ' grayscale(1) contrast(1.18)'
  else if (filterName === 'warm') base += ' sepia(0.24) saturate(1.18)'
  else if (filterName === 'cool') base += ' hue-rotate(180deg) sepia(0.12) saturate(1.08)'

  return base
}

function handleLabel(handle) {
  const names = {
    nw: t('Top-left corner'),
    n: t('Top edge'),
    ne: t('Top-right corner'),
    e: t('Right edge'),
    se: t('Bottom-right corner'),
    s: t('Bottom edge'),
    sw: t('Bottom-left corner'),
    w: t('Left edge'),
  }

  return names[handle] || handle
}

// ── Crop Handlers ────────────────────────────────────────────────────────────
function initCropForActive() {
  if (!activeImage.value) {
    cropDraft.value = null

    return
  }

  if (activeImage.value.cropRect) {
    cropDraft.value = { ...activeImage.value.cropRect }

    return
  }

  if (!fullBox.value.width || !fullBox.value.height) {
    cropDraft.value = { x: 0.04, y: 0.04, width: 0.92, height: 0.92 }
    activeImage.value.cropRect = { ...cropDraft.value }

    return
  }

  const defaultRect = createCenteredRect(fullBox.value, {
    aspect: activeImage.value.aspect || props.aspect || null,
    fill: 0.92,
  })

  const normalised = rectToNormalised(defaultRect, fullBox.value)

  cropDraft.value = { ...normalised }
  activeImage.value.cropRect = { ...normalised }
}

function selectFullFrameCrop() {
  if (!activeImage.value || !fullBox.value.width) return

  if (activeImage.value.aspect) {
    const rect = createCenteredRect(fullBox.value, { aspect: activeImage.value.aspect, fill: 1 })
    const norm = rectToNormalised(rect, fullBox.value)

    cropDraft.value = { ...norm }
    activeImage.value.cropRect = { ...norm }
  }
  else {
    const full = { x: 0, y: 0, width: 1, height: 1 }

    cropDraft.value = { ...full }
    activeImage.value.cropRect = { ...full }
  }
  invalidateEditedPreview(activeImage.value)
}

function resetCropForActive() {
  if (!activeImage.value) return
  activeImage.value.aspect = props.aspect || null
  activeImage.value.rotation = 0
  activeImage.value.flipH = false
  activeImage.value.flipV = false
  activeImage.value.cropRect = null
  nextTick(() => {
    measureStage()
    initCropForActive()
    invalidateEditedPreview(activeImage.value)
  })
}

function resetAllAdjustmentsForActive() {
  if (!activeImage.value) return
  activeImage.value.filter = 'normal'
  activeImage.value.brightness = 0
  activeImage.value.contrast = 0
  resetCropForActive()
}

function onCropPointerDown(event, handle = null) {
  if (!cropPx.value || !fullBox.value.width) return
  event.preventDefault()
  event.stopPropagation()

  activeHandle.value = handle || 'move'
  event.currentTarget?.setPointerCapture?.(event.pointerId)

  drag = {
    pointerId: event.pointerId,
    handle,
    startX: event.clientX,
    startY: event.clientY,
    startRect: { ...cropPx.value },
    aspect: activeImage.value?.aspect || null,
  }

  window.addEventListener('pointermove', onCropPointerMove)
  window.addEventListener('pointerup', onCropPointerUp)
  window.addEventListener('pointercancel', onCropPointerUp)
}

function onCropPointerMove(event) {
  if (!drag || !fullBox.value.width) return
  const dx = event.clientX - drag.startX
  const dy = event.clientY - drag.startY

  const nextPx = drag.handle
    ? resizeRect(drag.startRect, drag.handle, dx, dy, fullBox.value, { aspect: drag.aspect, minSize: MIN_CROP_SIZE })
    : moveRect(drag.startRect, dx, dy, fullBox.value)

  cropDraft.value = rectToNormalised(nextPx, fullBox.value)
  if (activeImage.value) {
    activeImage.value.cropRect = { ...cropDraft.value }
    invalidateEditedPreview(activeImage.value)
  }
}

function onCropPointerUp() {
  activeHandle.value = null
  drag = null
  window.removeEventListener('pointermove', onCropPointerMove)
  window.removeEventListener('pointerup', onCropPointerUp)
  window.removeEventListener('pointercancel', onCropPointerUp)
}

function onCropKeydown(event, handle = null) {
  if (!cropPx.value || !fullBox.value.width) return
  const step = event.shiftKey ? KEY_CROP_STEP_LARGE : KEY_CROP_STEP

  const moves = {
    ArrowLeft: [-step, 0],
    ArrowRight: [step, 0],
    ArrowUp: [0, -step],
    ArrowDown: [0, step],
  }

  const delta = moves[event.key]
  if (!delta) return

  event.preventDefault()
  event.stopPropagation()

  const nextPx = handle
    ? resizeRect(cropPx.value, handle, delta[0], delta[1], fullBox.value, {
      aspect: activeImage.value?.aspect || null,
      minSize: MIN_CROP_SIZE,
    })
    : moveRect(cropPx.value, delta[0], delta[1], fullBox.value)

  cropDraft.value = rectToNormalised(nextPx, fullBox.value)
  if (activeImage.value) {
    activeImage.value.cropRect = { ...cropDraft.value }
    invalidateEditedPreview(activeImage.value)
  }
}

function setAspect(val) {
  if (!activeImage.value) return
  activeImage.value.aspect = val
  if (val && fullBox.value.width) {
    const current = cropPx.value || createCenteredRect(fullBox.value, { aspect: val, fill: 0.92 })
    const adjusted = applyAspect(current, val, fullBox.value)

    cropDraft.value = rectToNormalised(adjusted, fullBox.value)
    activeImage.value.cropRect = { ...cropDraft.value }
  }
  invalidateEditedPreview(activeImage.value)
}

function rotateActive(degrees = 90) {
  if (!activeImage.value) return
  activeImage.value.rotation = (((activeImage.value.rotation || 0) + degrees) % 360 + 360) % 360
  activeImage.value.cropRect = null
  cropDraft.value = null
  nextTick(() => {
    measureStage()
    initCropForActive()
    invalidateEditedPreview(activeImage.value)
  })
}

function flipActiveH() {
  if (!activeImage.value) return
  activeImage.value.flipH = !activeImage.value.flipH
  invalidateEditedPreview(activeImage.value)
}

function flipActiveV() {
  if (!activeImage.value) return
  activeImage.value.flipV = !activeImage.value.flipV
  invalidateEditedPreview(activeImage.value)
}

function setFilter(filterKey) {
  if (!activeImage.value) return
  activeImage.value.filter = filterKey
  invalidateEditedPreview(activeImage.value)
}

function selectActiveImage(idx) {
  if (idx < 0 || idx >= images.value.length) return
  activeIndex.value = idx
  cropDraft.value = null
  nextTick(() => {
    measureStage()
    initCropForActive()
  })
}

function setMainImage(targetImg) {
  if (!targetImg) return
  images.value.forEach(img => {
    img.is_main = img.id === targetImg.id
  })
}

// ── Image Ingestion Helpers ──────────────────────────────────────────────────
function invalidateEditedPreview(item) {
  if (!item) return
  if (item.editedPreviewUrl) {
    URL.revokeObjectURL(item.editedPreviewUrl)
    item.editedPreviewUrl = null
  }
}

function revokeImageUrls(item) {
  if (!item) return
  if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
  if (item.editedPreviewUrl) URL.revokeObjectURL(item.editedPreviewUrl)
}

function processIncomingFile(file) {
  return new Promise((resolve, reject) => {
    if (!matchesAccept(file, props.accept)) {
      reject(new Error(t('wp.file.error_unsupported', 'File type not supported.')))

      return
    }

    if (file.size > maxBytes.value) {
      reject(new Error(t('wp.avatar.error_too_large', { size: props.maxSizeMb }, `Image file exceeds ${props.maxSizeMb} MB.`)))

      return
    }

    const previewUrl = URL.createObjectURL(file)
    const img = new Image()

    img.onload = () => {
      const isFirst = images.value.length === 0

      const defaultTitle = (isFirst && props.initialTitle)
        ? props.initialTitle
        : titleFromFileName(file.name)

      resolve({
        id: uid('img'),
        file,
        previewUrl,
        editedPreviewUrl: null,
        naturalWidth: img.naturalWidth || img.width || 800,
        naturalHeight: img.naturalHeight || img.height || 600,
        title: defaultTitle,
        rotation: 0,
        flipH: false,
        flipV: false,
        aspect: props.aspect || null,
        cropRect: null,
        filter: 'normal',
        brightness: 0,
        contrast: 0,
        is_main: isFirst,
      })
    }
    img.onerror = () => {
      URL.revokeObjectURL(previewUrl)
      reject(new Error(t('Failed to load image.')))
    }
    img.src = previewUrl
  })
}

async function addFiles(fileList) {
  if (!fileList || !fileList.length) return
  localNotice.value = ''

  const added = []
  const errors = []

  const candidates = props.multiple ? Array.from(fileList) : [fileList[0]]

  for (const f of candidates) {
    try {
      const item = await processIncomingFile(f)

      added.push(item)
    }
    catch (err) {
      errors.push(err)
      emit('error', err)
    }
  }

  if (errors.length) {
    localNotice.value = errors[0].message
  }

  if (added.length) {
    if (props.multiple) {
      images.value.push(...added)
      activeIndex.value = images.value.length - 1
    }
    else {
      images.value.forEach(revokeImageUrls)
      added[0].is_main = true
      images.value = [added[0]]
      activeIndex.value = 0
    }

    cropDraft.value = null
    nextTick(() => {
      measureStage()
      initCropForActive()
    })
  }
}

function onFileInputChange(event) {
  addFiles(event.target.files)
  event.target.value = ''
}

function onDrop(event) {
  isDraggingFile.value = false
  if (event.dataTransfer?.files?.length) {
    addFiles(event.dataTransfer.files)
  }
}

function removeImage(idx) {
  const item = images.value[idx]
  if (!item) return
  const wasMain = item.is_main

  revokeImageUrls(item)
  images.value.splice(idx, 1)

  if (wasMain && images.value.length > 0 && !images.value.some(i => i.is_main)) {
    images.value[0].is_main = true
  }

  if (activeIndex.value >= images.value.length) {
    activeIndex.value = Math.max(0, images.value.length - 1)
  }

  cropDraft.value = null
  nextTick(() => {
    measureStage()
    initCropForActive()
  })
}

function moveImageOrder(fromIdx, toIdx) {
  if (fromIdx < 0 || fromIdx >= images.value.length) return
  if (toIdx < 0 || toIdx >= images.value.length) return
  if (fromIdx === toIdx) return

  const [moved] = images.value.splice(fromIdx, 1)

  images.value.splice(toIdx, 0, moved)
  activeIndex.value = toIdx
}

// ── Camera Snapping ──────────────────────────────────────────────────────────
async function snapFromCamera() {
  const video = videoRef.value
  if (!video || !video.videoWidth || !isCameraActive.value) return

  isShutterFlashing.value = true
  navigator.vibrate?.(25)
  setTimeout(() => {
    isShutterFlashing.value = false
  }, 200)

  const indexNum = images.value.length + 1
  const baseName = props.initialTitle ? `${props.initialTitle}-${indexNum}` : `photo-${indexNum}-${Date.now()}`

  try {
    const captured = await captureFrameAsFile(`${baseName}.jpg`, {
      format: 'image/jpeg',
      quality: 0.92,
      maxSide: 2048,
      mirror: isUserCamera.value,
    })

    if (captured?.preview) {
      URL.revokeObjectURL(captured.preview)
    }
    if (captured?.file) {
      await addFiles([captured.file])
    }
  }
  catch (err) {
    emit('error', err)
  }
}

// ── Final Image Rendering ────────────────────────────────────────────────────
function renderFinalCanvas(item) {
  return new Promise((resolve, reject) => {
    const img = new Image()

    img.onload = () => {
      const rot = ((item.rotation || 0) % 360 + 360) % 360
      const quarter = rot % 180 !== 0
      const naturalW = img.naturalWidth || img.width
      const naturalH = img.naturalHeight || img.height
      const rotW = quarter ? naturalH : naturalW
      const rotH = quarter ? naturalW : naturalH

      // Calculate cropped sub-rectangle in rotated coordinates
      const crop = item.cropRect || { x: 0, y: 0, width: 1, height: 1 }
      const cropX = Math.round(crop.x * rotW)
      const cropY = Math.round(crop.y * rotH)
      const cropW = Math.max(1, Math.round(crop.width * rotW))
      const cropH = Math.max(1, Math.round(crop.height * rotH))

      const canvas = document.createElement('canvas')

      canvas.width = cropW
      canvas.height = cropH

      const ctx = canvas.getContext('2d')

      ctx.imageSmoothingEnabled = true
      ctx.imageSmoothingQuality = 'high'

      // Apply CSS-like filter string directly to 2D context
      ctx.filter = getFilterCss(item.filter, item.brightness, item.contrast)

      // Transform context so (0,0) in canvas maps to (cropX, cropY) in rotated image
      ctx.save()
      ctx.translate(-cropX, -cropY)
      ctx.translate(rotW / 2, rotH / 2)
      ctx.rotate((rot * Math.PI) / 180)
      ctx.scale(item.flipH ? -1 : 1, item.flipV ? -1 : 1)
      ctx.drawImage(img, -naturalW / 2, -naturalH / 2, naturalW, naturalH)
      ctx.restore()

      canvas.toBlob(blob => {
        canvas.width = 0
        canvas.height = 0
        if (!blob) {
          reject(new Error('Canvas conversion failed'))

          return
        }
        const ext = 'jpg'
        const cleanName = (item.title || 'photo').trim().replace(/[^\w\- ]/g, '').replace(/\s+/g, '-')
        const file = new File([blob], `${cleanName || 'photo'}.${ext}`, { type: 'image/jpeg' })
        const preview = URL.createObjectURL(file)

        resolve({
          file,
          title: item.title || cleanName || 'Photo',
          is_main: Boolean(item.is_main),
          preview,
          previewUrl: preview,
          width: cropW,
          height: cropH,
          rotation: rot,
        })
      }, 'image/jpeg', 0.92)
    }
    img.onerror = reject
    img.src = item.previewUrl
  })
}

async function generateReviewPreviews() {
  const token = ++reviewRenderToken

  for (const item of images.value) {
    if (token !== reviewRenderToken) return
    if (item.editedPreviewUrl) continue
    try {
      const rendered = await renderFinalCanvas(item)
      if (token !== reviewRenderToken) {
        URL.revokeObjectURL(rendered.previewUrl)

        return
      }
      item.editedPreviewUrl = rendered.previewUrl
      item.editedWidth = rendered.width
      item.editedHeight = rendered.height
      item.editedSize = rendered.file.size
    }
    catch {
      // Fallback to raw previewUrl if canvas fails
    }
  }
}

function handleStepChange(step) {
  if (step?.id === 'capture') {
    if (props.capture && !isCameraActive.value && !isCameraLoading.value) {
      startCamera().catch(() => {})
    }
  }
  else {
    stopCamera()
    if (step?.id === 'edit') {
      nextTick(() => {
        measureStage()
        initCropForActive()
      })
    }
    else if (step?.isReview) {
      generateReviewPreviews()
    }
  }
}

// ── Wizard Submit Handler ────────────────────────────────────────────────────
async function handleWizardSubmit() {
  if (!images.value.length) return
  isSaving.value = true

  try {
    const processed = []

    for (const item of images.value) {
      const res = await renderFinalCanvas(item)

      processed.push(res)
    }

    const primaryItem = processed.find(p => p.is_main) || processed[0]

    emit('save', {
      file: primaryItem.file,
      files: processed,
      preview: primaryItem.preview,
      previewUrl: primaryItem.previewUrl,
      title: primaryItem.title,
      is_main: primaryItem.is_main,
      width: primaryItem.width,
      height: primaryItem.height,
    })

    emit('update:modelValue', false)
  }
  catch (err) {
    console.error('Failed to process photos:', err)
    emit('error', err)
  }
  finally {
    isSaving.value = false
  }
}

// ── Reset & Cleanup ──────────────────────────────────────────────────────────
function resetAll() {
  reviewRenderToken++
  images.value.forEach(revokeImageUrls)
  images.value = []
  activeIndex.value = 0
  cropDraft.value = null
  localNotice.value = ''
  activeEditorTab.value = 'transform'
  stopCamera()
}

function handleCancel() {
  resetAll()
  emit('close')
}

watch(() => props.modelValue, open => {
  if (open) {
    resetAll()
    nextTick(measureStage)
  }
  else {
    resetAll()
  }
})

onBeforeUnmount(() => {
  onCropPointerUp()
  resetAll()
  resizeObserver?.disconnect()
})
</script>

<template>
  <WpWizardDialog
    ref="wizardRef"
    :model-value="modelValue"
    variant="standard"
    :title="title || $t('Photography & Image Studio')"
    :description="$t('Capture live photos or upload images, crop with precision, enhance lighting, and organize.')"
    icon="tabler-camera"
    :steps="wizardSteps"
    :initial-values="{ source: capture ? 'upload' : 'upload' }"
    :submit="handleWizardSubmit"
    review
    :review-title="$t('Review & Save')"
    :max-width="920"
    @update:model-value="$emit('update:modelValue', $event)"
    @step-change="handleStepChange"
    @cancel="handleCancel"
  >
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 1: CHOICE OF SOURCE                                                -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <template #step-source="{ form }">
      <div class="py-1">
        <div class="mb-5">
          <h5 class="text-h6 font-weight-bold mb-1">
            {{ $t('How would you like to add photos?') }}
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('Choose live camera capture for on-site photography or upload existing high-resolution images from your device.') }}
          </p>
        </div>

        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="source-choice-card pa-5 cursor-pointer h-100 d-flex flex-column justify-space-between"
              :class="{ 'source-choice-card--active': form.source === 'camera' }"
              role="radio"
              :aria-checked="form.source === 'camera'"
              tabindex="0"
              @click="form.source = 'camera'"
              @dblclick="form.source = 'camera'; wizardRef?.wizard?.next()"
              @keydown.enter.prevent="form.source = 'camera'"
              @keydown.space.prevent="form.source = 'camera'"
            >
              <div>
                <div class="d-flex align-center justify-space-between gap-3 mb-4">
                  <VAvatar
                    size="54"
                    color="success"
                    variant="tonal"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-camera"
                      size="28"
                    />
                  </VAvatar>
                  <VChip
                    size="x-small"
                    :color="form.source === 'camera' ? 'success' : 'secondary'"
                    :variant="form.source === 'camera' ? 'flat' : 'tonal'"
                    prepend-icon="tabler-device-mobile"
                  >
                    {{ $t('Mobile & Webcam') }}
                  </VChip>
                </div>

                <h6 class="text-subtitle-1 font-weight-bold mb-1">
                  {{ $t('Take Live Photos') }}
                </h6>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  {{ $t('Use your phone, tablet, or computer camera with framing grid, flash/torch, and multi-shot filmstrip.') }}
                </p>
              </div>

              <div class="d-flex flex-wrap gap-1 pt-2 border-t">
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="success"
                >
                  {{ $t('HD Capture') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ $t('Front / Rear Lens') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ $t('Rule of Thirds') }}
                </VChip>
              </div>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="source-choice-card pa-5 cursor-pointer h-100 d-flex flex-column justify-space-between"
              :class="{ 'source-choice-card--active': form.source === 'upload' }"
              role="radio"
              :aria-checked="form.source === 'upload'"
              tabindex="0"
              @click="form.source = 'upload'"
              @dblclick="form.source = 'upload'; wizardRef?.wizard?.next()"
              @keydown.enter.prevent="form.source = 'upload'"
              @keydown.space.prevent="form.source = 'upload'"
            >
              <div>
                <div class="d-flex align-center justify-space-between gap-3 mb-4">
                  <VAvatar
                    size="54"
                    color="primary"
                    variant="tonal"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-photo-up"
                      size="28"
                    />
                  </VAvatar>
                  <VChip
                    size="x-small"
                    :color="form.source === 'upload' ? 'primary' : 'secondary'"
                    :variant="form.source === 'upload' ? 'flat' : 'tonal'"
                    prepend-icon="tabler-cloud-upload"
                  >
                    {{ multiple ? $t('Batch Ready') : $t('Single Image') }}
                  </VChip>
                </div>

                <h6 class="text-subtitle-1 font-weight-bold mb-1">
                  {{ $t('Upload Image Files') }}
                </h6>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  {{ $t('Browse or drag and drop JPG, PNG, or WebP images from your device gallery or cloud storage.') }}
                </p>
              </div>

              <div class="d-flex flex-wrap gap-1 pt-2 border-t">
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="primary"
                >
                  {{ $t('Drag & Drop') }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ $t('Up to {size}MB', { size: maxSizeMb }) }}
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ $t('Crop & Filter') }}
                </VChip>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 2: UPLOAD OR CAMERA CAPTURE                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <template #step-capture="{ form }">
      <div>
        <!-- Quick Mode Switcher Pill Bar (allows switching between Camera and Upload without going back) -->
        <div
          v-if="capture"
          class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3"
        >
          <VBtnToggle
            :model-value="form.source || 'upload'"
            mandatory
            density="compact"
            variant="outlined"
            color="primary"
            divided
            @update:model-value="form.source = $event"
          >
            <VBtn
              value="camera"
              size="small"
              prepend-icon="tabler-camera"
            >
              {{ $t('Live Camera') }}
            </VBtn>
            <VBtn
              value="upload"
              size="small"
              prepend-icon="tabler-upload"
            >
              {{ $t('Upload Files') }}
            </VBtn>
          </VBtnToggle>

          <span
            v-if="images.length"
            class="text-caption font-weight-semibold text-primary"
          >
            {{ $t('{count} photo(s) staged', { count: images.length }) }} • {{ formatBytes(totalBytes) }}
          </span>
        </div>

        <VAlert
          v-if="localNotice"
          type="warning"
          variant="tonal"
          density="compact"
          closable
          class="mb-3"
          @click:close="localNotice = ''"
        >
          {{ localNotice }}
        </VAlert>

        <!-- Hidden file inputs shared across modes -->
        <input
          ref="fileInputRef"
          type="file"
          :accept="accept"
          :multiple="multiple"
          class="d-none"
          @change="onFileInputChange"
        >
        <input
          ref="appendInputRef"
          type="file"
          :accept="accept"
          :multiple="multiple"
          class="d-none"
          @change="onFileInputChange"
        >

        <!-- Mode A: Camera Live Viewfinder -->
        <div
          v-if="form.source === 'camera'"
          class="camera-capture-step"
        >
          <div class="camera-stage-container position-relative rounded-lg overflow-hidden mb-3 bg-black">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="camera-video"
              :class="{ 'camera-video--mirrored': isUserCamera }"
            />

            <!-- Rule-of-Thirds Framing Grid -->
            <div
              v-if="showCameraGrid && isCameraActive"
              class="camera-grid-overlay position-absolute pointer-events-none"
              aria-hidden="true"
            >
              <div class="camera-grid-line camera-grid-line--h1" />
              <div class="camera-grid-line camera-grid-line--h2" />
              <div class="camera-grid-line camera-grid-line--v1" />
              <div class="camera-grid-line camera-grid-line--v2" />
            </div>

            <!-- Shutter Flash Effect -->
            <div
              v-if="isShutterFlashing"
              class="camera-shutter-flash position-absolute"
            />

            <!-- Loading View -->
            <div
              v-if="isCameraLoading && !isCameraActive"
              class="camera-overlay-state position-absolute d-flex flex-column align-center justify-center text-white"
            >
              <VProgressCircular
                indeterminate
                color="primary"
                size="44"
                class="mb-3"
              />
              <span class="text-body-2">{{ $t('Initializing device camera…') }}</span>
            </div>

            <!-- Error / Permission Denied State -->
            <div
              v-else-if="cameraError && !isCameraActive"
              class="camera-overlay-state position-absolute d-flex flex-column align-center justify-center pa-4 text-center text-white"
            >
              <VAvatar
                size="56"
                color="error"
                variant="tonal"
                class="mb-3"
              >
                <VIcon
                  icon="tabler-camera-off"
                  size="28"
                />
              </VAvatar>
              <h6 class="text-subtitle-1 font-weight-bold text-white mb-1">
                {{ $t('Camera Access Unavailable') }}
              </h6>
              <p
                class="text-caption text-medium-emphasis mb-4"
                style="max-inline-size: 340px;"
              >
                {{ cameraError.message || $t('Please grant camera permissions in your browser settings or upload files instead.') }}
              </p>
              <div class="d-flex flex-wrap justify-center gap-2">
                <VBtn
                  size="small"
                  color="primary"
                  prepend-icon="tabler-refresh"
                  @click="handleRetryCamera"
                >
                  {{ $t('Retry Camera') }}
                </VBtn>
                <VBtn
                  size="small"
                  variant="tonal"
                  color="surface"
                  prepend-icon="tabler-upload"
                  @click="form.source = 'upload'"
                >
                  {{ $t('Switch to Upload') }}
                </VBtn>
              </div>
            </div>

            <!-- Live viewfinder controls -->
            <template v-else>
              <div class="camera-controls-bar d-flex align-center justify-space-between px-3 py-2">
                <div class="d-flex align-center gap-2">
                  <VChip
                    size="small"
                    color="success"
                    variant="elevated"
                  >
                    <VIcon
                      start
                      icon="tabler-circle-dot"
                      size="12"
                      class="pulse-dot"
                    />
                    {{ $t('Live') }}
                  </VChip>
                </div>

                <div class="d-flex align-center gap-1.5">
                  <IconBtn
                    size="small"
                    variant="tonal"
                    class="camera-tool-btn"
                    :color="showCameraGrid ? 'primary' : 'default'"
                    :title="$t('Framing Grid')"
                    :aria-label="$t('Framing Grid')"
                    @click="showCameraGrid = !showCameraGrid"
                  >
                    <VIcon
                      icon="tabler-grid-dots"
                      size="18"
                    />
                  </IconBtn>

                  <VBtn
                    v-if="zoomSupported"
                    size="x-small"
                    variant="tonal"
                    class="camera-tool-btn font-weight-bold"
                    :title="$t('wp.camera.zoom', 'Zoom')"
                    @click="cycleCameraZoom"
                  >
                    {{ cameraZoomLabel }}
                  </VBtn>

                  <IconBtn
                    v-if="torchSupported"
                    size="small"
                    variant="tonal"
                    class="camera-tool-btn"
                    :color="torchEnabled ? 'warning' : 'default'"
                    :title="torchEnabled ? $t('Flash On') : $t('Flash Off')"
                    :aria-label="torchEnabled ? $t('Flash On') : $t('Flash Off')"
                    @click="() => toggleTorch()"
                  >
                    <VIcon
                      :icon="torchEnabled ? 'tabler-bolt' : 'tabler-bolt-off'"
                      size="18"
                    />
                  </IconBtn>

                  <!-- Multi-camera selector menu when >= 2 real cameras + quick switch -->
                  <VMenu
                    v-if="hasRealMultipleCameras"
                    v-model="cameraMenuOpen"
                    location="bottom end"
                  >
                    <template #activator="{ props: menuProps }">
                      <IconBtn
                        v-bind="menuProps"
                        size="small"
                        variant="tonal"
                        class="camera-tool-btn"
                        :title="$t('Select Camera')"
                        :aria-label="$t('Select Camera')"
                      >
                        <VIcon
                          icon="tabler-video"
                          size="18"
                        />
                      </IconBtn>
                    </template>
                    <VList density="compact" min-width="210">
                      <VListSubheader>{{ $t('Select Camera') }}</VListSubheader>
                      <VListItem
                        v-for="(cam, idx) in selectableCamerasRef"
                        :key="cam.deviceId || idx"
                        :active="cam.deviceId === currentCameraIdRef"
                        @click="handleSelectCamera(cam.deviceId)"
                      >
                        <template #prepend>
                          <VIcon
                            :icon="cam.deviceId === currentCameraIdRef ? 'tabler-check' : 'tabler-camera'"
                            size="16"
                          />
                        </template>
                        <VListItemTitle class="text-caption">
                          {{ cam.label || `${$t('Camera')} #${idx + 1}` }}
                        </VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>

                  <IconBtn
                    v-if="hasMultipleCameras"
                    size="small"
                    variant="tonal"
                    class="camera-tool-btn"
                    :title="$t('Switch Camera')"
                    :aria-label="$t('Switch Camera')"
                    @click="() => switchCamera()"
                  >
                    <VIcon
                      icon="tabler-camera-rotate"
                      size="18"
                    />
                  </IconBtn>
                </div>
              </div>

              <!-- Bottom Camera Trigger Bar -->
              <div class="camera-shutter-bar d-flex justify-space-between align-center px-4 py-3">
                <VBtn
                  size="small"
                  variant="tonal"
                  color="surface"
                  prepend-icon="tabler-photo-up"
                  class="camera-tool-btn"
                  @click="appendInputRef?.click()"
                >
                  <span class="d-none d-sm-inline">{{ $t('Import') }}</span>
                </VBtn>

                <button
                  type="button"
                  class="camera-shutter-btn"
                  :disabled="!isCameraActive || isCameraLoading"
                  :aria-label="$t('Take Photo')"
                  @click="snapFromCamera"
                >
                  <span class="shutter-inner" />
                </button>

                <div
                  class="d-flex justify-end"
                  style="min-inline-size: 72px;"
                >
                  <VChip
                    v-if="images.length"
                    size="small"
                    color="success"
                    variant="elevated"
                    prepend-icon="tabler-check"
                  >
                    {{ images.length }}
                  </VChip>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Mode B: File Upload Dropzone -->
        <div
          v-else
          class="file-upload-step"
        >
          <div
            class="upload-dropzone pa-6 pa-sm-8 text-center rounded-lg border-dashed cursor-pointer mb-3"
            :class="{ 'upload-dropzone--active': isDraggingFile }"
            role="button"
            tabindex="0"
            @click="fileInputRef?.click()"
            @keydown.enter.prevent="fileInputRef?.click()"
            @keydown.space.prevent="fileInputRef?.click()"
            @dragover.prevent="isDraggingFile = true"
            @dragleave.prevent="isDraggingFile = false"
            @drop.prevent="onDrop"
          >
            <VAvatar
              size="60"
              color="primary"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                icon="tabler-cloud-upload"
                size="30"
              />
            </VAvatar>

            <h6 class="text-subtitle-1 font-weight-bold mb-1">
              {{ $t('Click to browse or drag & drop photos here') }}
            </h6>
            <p class="text-caption text-medium-emphasis mb-4">
              {{ multiple
                ? $t('Supports JPG, PNG, WebP up to {size}MB each. Select one or multiple photos.', { size: maxSizeMb })
                : $t('Supports JPG, PNG, WebP up to {size}MB.', { size: maxSizeMb }) }}
            </p>

            <div class="d-flex justify-center gap-2 flex-wrap">
              <VBtn
                size="small"
                color="primary"
                prepend-icon="tabler-folder-open"
                @click.stop="fileInputRef?.click()"
              >
                {{ $t('Browse Files') }}
              </VBtn>
              <VBtn
                v-if="capture"
                size="small"
                variant="tonal"
                color="success"
                prepend-icon="tabler-camera"
                @click.stop="form.source = 'camera'"
              >
                {{ $t('Use Camera') }}
              </VBtn>
            </div>
          </div>
        </div>

        <!-- Unified Staged Photos Tray (visible in both Camera and Upload modes) -->
        <div
          v-if="images.length"
          class="staged-tray pa-3 rounded-lg border bg-surface"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-photo-check"
                size="18"
                color="success"
              />
              <span class="text-subtitle-2 font-weight-bold">
                {{ $t('{count} Photo(s) Ready', { count: images.length }) }}
              </span>
              <span class="text-caption text-medium-emphasis">({{ formatBytes(totalBytes) }})</span>
            </div>

            <div class="d-flex align-center gap-1">
              <VBtn
                v-if="multiple"
                size="x-small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-plus"
                @click="appendInputRef?.click()"
              >
                {{ $t('Add More') }}
              </VBtn>
            </div>
          </div>

          <div class="staged-grid">
            <div
              v-for="(img, idx) in images"
              :key="img.id"
              class="staged-card rounded-lg border pa-2 d-flex align-center gap-2.5 bg-surface"
              :class="{ 'staged-card--main': img.is_main && multiple }"
            >
              <div class="position-relative flex-shrink-0">
                <VImg
                  :src="img.previewUrl"
                  cover
                  width="52"
                  height="52"
                  class="rounded border bg-surface-variant"
                />
                <span
                  v-if="multiple"
                  class="staged-index-badge"
                >#{{ idx + 1 }}</span>
              </div>

              <div class="staged-card__meta grow">
                <span class="text-body-2 font-weight-semibold d-block text-truncate">
                  {{ img.title || img.file?.name }}
                </span>
                <div class="d-flex align-center gap-1.5 text-caption text-medium-emphasis">
                  <span>{{ formatBytes(img.file?.size) }}</span>
                  <span>•</span>
                  <span>{{ img.naturalWidth }}×{{ img.naturalHeight }}</span>
                </div>
              </div>

              <div class="d-flex align-center gap-0.5 flex-shrink-0">
                <IconBtn
                  v-if="multiple"
                  size="x-small"
                  :color="img.is_main ? 'warning' : 'default'"
                  :variant="img.is_main ? 'tonal' : 'text'"
                  :title="img.is_main ? $t('Primary Photo') : $t('Set as Primary')"
                  :aria-label="img.is_main ? $t('Primary Photo') : $t('Set as Primary')"
                  @click="setMainImage(img)"
                >
                  <VIcon
                    :icon="img.is_main ? 'tabler-star-filled' : 'tabler-star'"
                    size="16"
                  />
                </IconBtn>

                <IconBtn
                  size="x-small"
                  color="error"
                  variant="text"
                  :title="$t('Remove')"
                  :aria-label="$t('Remove')"
                  @click="removeImage(idx)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="16"
                  />
                </IconBtn>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 3: EDIT, CROP, FILTER, AND NAME                                    -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <template #step-edit>
      <div
        v-if="images.length"
        class="photo-edit-studio"
      >
        <!-- Top Multi-Photo Carousel Switcher (if > 1 image) -->
        <div
          v-if="images.length > 1"
          class="editor-switcher-strip pa-2 rounded-lg border mb-3 d-flex align-center gap-2 overflow-x-auto"
        >
          <button
            v-for="(img, idx) in images"
            :key="img.id"
            type="button"
            class="editor-thumb-pill cursor-pointer pa-1.5 rounded-lg border d-flex align-center gap-2 flex-shrink-0 text-start"
            :class="{ 'editor-thumb-pill--active': activeIndex === idx }"
            @click="selectActiveImage(idx)"
          >
            <img
              :src="img.previewUrl"
              alt=""
              class="editor-thumb-img rounded"
              :style="{ filter: getFilterCss(img.filter, img.brightness, img.contrast) }"
            >
            <div class="editor-thumb-meta pe-1">
              <span class="text-caption font-weight-bold d-block text-truncate">
                {{ img.title || `${$t('Photo')} ${idx + 1}` }}
              </span>
              <span
                v-if="img.is_main"
                class="text-xs text-warning font-weight-semibold d-flex align-center gap-0.5"
              >
                <VIcon
                  icon="tabler-star-filled"
                  size="11"
                />
                {{ $t('Main') }}
              </span>
              <span
                v-else
                class="text-xs text-disabled"
              >
                #{{ idx + 1 }}
              </span>
            </div>
          </button>
        </div>

        <VRow>
          <!-- Left: Live Interactive Canvas Stage with Crop Handles -->
          <VCol
            v-if="showCropper"
            cols="12"
            md="7"
          >
            <VCard
              variant="outlined"
              class="edit-canvas-card pa-3 h-100 d-flex flex-column"
            >
              <!-- Card Header / Aspect Ratio & Quick Transform Toolbar -->
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-2">
                <div class="d-flex align-center gap-1 flex-wrap">
                  <VBtn
                    v-for="opt in aspectOptions"
                    :key="opt.key"
                    size="x-small"
                    :variant="activeImage?.aspect === opt.value ? 'flat' : 'tonal'"
                    :color="activeImage?.aspect === opt.value ? 'primary' : 'secondary'"
                    @click="setAspect(opt.value)"
                  >
                    {{ opt.label }}
                  </VBtn>
                </div>

                <div class="d-flex align-center gap-1">
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :title="$t('Rotate Counter-Clockwise')"
                    :aria-label="$t('Rotate Counter-Clockwise')"
                    @click="rotateActive(-90)"
                  >
                    <VIcon
                      icon="tabler-rotate-2"
                      size="18"
                    />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :title="$t('Rotate Clockwise')"
                    :aria-label="$t('Rotate Clockwise')"
                    @click="rotateActive(90)"
                  >
                    <VIcon
                      icon="tabler-rotate-clockwise-2"
                      size="18"
                    />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :color="activeImage?.flipH ? 'primary' : undefined"
                    :title="$t('Flip Horizontal')"
                    :aria-label="$t('Flip Horizontal')"
                    @click="flipActiveH"
                  >
                    <VIcon
                      icon="tabler-flip-horizontal"
                      size="18"
                    />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :color="activeImage?.flipV ? 'primary' : undefined"
                    :title="$t('Flip Vertical')"
                    :aria-label="$t('Flip Vertical')"
                    @click="flipActiveV"
                  >
                    <VIcon
                      icon="tabler-flip-vertical"
                      size="18"
                    />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :title="$t('Full Image')"
                    :aria-label="$t('Full Image')"
                    @click="selectFullFrameCrop"
                  >
                    <VIcon
                      icon="tabler-maximize"
                      size="18"
                    />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    variant="tonal"
                    :title="$t('Reset Crop & Transform')"
                    :aria-label="$t('Reset Crop & Transform')"
                    @click="resetCropForActive"
                  >
                    <VIcon
                      icon="tabler-restore"
                      size="18"
                    />
                  </IconBtn>
                </div>
              </div>

              <!-- Stage container -->
              <div
                ref="stageRef"
                class="edit-stage-viewport position-relative overflow-hidden rounded-lg grow"
              >
                <div
                  v-if="activeImage && fullBox.width"
                  class="position-absolute"
                  :style="{
                    left: `${fullBox.x}px`,
                    top: `${fullBox.y}px`,
                    width: `${fullBox.width}px`,
                    height: `${fullBox.height}px`,
                  }"
                >
                  <!-- The transformed image -->
                  <div
                    class="position-absolute"
                    style="left: 50%; top: 50%;"
                  >
                    <img
                      :src="activeImage.previewUrl"
                      alt=""
                      :style="activeImageStyle"
                      class="user-select-none pointer-events-none d-block"
                      draggable="false"
                    >
                  </div>

                  <!-- Crop Box Overlay -->
                  <div
                    v-if="cropPx"
                    class="crop-box position-absolute"
                    :class="{ 'crop-box--active': Boolean(activeHandle) }"
                    :style="cropStyle"
                    role="application"
                    tabindex="0"
                    :aria-label="$t('Crop frame. Use arrow keys to move.')"
                    @pointerdown="onCropPointerDown($event, null)"
                    @keydown="onCropKeydown($event, null)"
                  >
                    <template v-if="showCropGrid">
                      <div class="crop-grid-rule-h" />
                      <div class="crop-grid-rule-v" />
                    </template>

                    <!-- Live Crop Dimensions Pill -->
                    <div
                      v-if="activeHandle"
                      class="crop-dimensions-pill"
                    >
                      {{ cropOutputDimensions.width }} × {{ cropOutputDimensions.height }} px
                    </div>

                    <!-- 8 Interactive Handles (4 corners + 4 edges with 44px touch targets) -->
                    <span
                      v-for="h in CROP_HANDLES"
                      :key="h"
                      class="crop-handle"
                      :class="[
                        `crop-handle--${h}`,
                        h.length === 2 ? 'crop-handle--corner' : 'crop-handle--edge',
                        { 'crop-handle--active': activeHandle === h },
                      ]"
                      role="slider"
                      tabindex="0"
                      :aria-label="handleLabel(h)"
                      @pointerdown.stop="onCropPointerDown($event, h)"
                      @keydown.stop="onCropKeydown($event, h)"
                    />
                  </div>
                </div>
              </div>

              <!-- Footer info bar -->
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mt-2 pt-2 border-t text-caption text-medium-emphasis">
                <span class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-hand-move"
                    size="14"
                  />
                  {{ $t('Drag corners, edges, or frame to crop') }}
                </span>
                <span
                  v-if="activeImage"
                  class="font-monospace"
                >
                  {{ cropOutputDimensions.width }} × {{ cropOutputDimensions.height }} px
                </span>
              </div>
            </VCard>
          </VCol>

          <!-- Right: Details, Filters & Lighting Controls -->
          <VCol
            cols="12"
            :md="showCropper ? 5 : 12"
          >
            <div
              v-if="activeImage"
              class="d-flex flex-column gap-3"
            >
              <!-- Card 1: Identification & Primary Role -->
              <VCard
                v-if="showTitleInput || multiple"
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <h6 class="text-subtitle-2 font-weight-bold d-flex align-center gap-1.5 mb-0">
                    <VIcon
                      icon="tabler-id"
                      size="18"
                      color="primary"
                    />
                    {{ $t('Photo Details') }}
                  </h6>
                  <div
                    v-if="images.length > 1"
                    class="d-flex align-center gap-1"
                  >
                    <IconBtn
                      size="x-small"
                      variant="tonal"
                      :disabled="activeIndex === 0"
                      :title="$t('Move Earlier')"
                      @click="moveImageOrder(activeIndex, activeIndex - 1)"
                    >
                      <VIcon
                        icon="tabler-arrow-left"
                        size="14"
                      />
                    </IconBtn>
                    <IconBtn
                      size="x-small"
                      variant="tonal"
                      :disabled="activeIndex === images.length - 1"
                      :title="$t('Move Later')"
                      @click="moveImageOrder(activeIndex, activeIndex + 1)"
                    >
                      <VIcon
                        icon="tabler-arrow-right"
                        size="14"
                      />
                    </IconBtn>
                  </div>
                </div>

                <div
                  v-if="showTitleInput"
                  class="mb-3"
                >
                  <VTextField
                    v-model="activeImage.title"
                    :label="$t('wp.image.title_label', 'Title / Caption')"
                    density="compact"
                    variant="outlined"
                    :placeholder="$t('wp.image.title_placeholder', 'e.g. Front View, Main Entrance')"
                    prepend-inner-icon="tabler-letter-case"
                    hide-details
                  />
                </div>

                <div
                  v-if="multiple"
                  class="pa-2.5 rounded-lg border bg-surface-variant d-flex align-center justify-space-between"
                >
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      :icon="activeImage.is_main ? 'tabler-star-filled' : 'tabler-star'"
                      size="20"
                      :color="activeImage.is_main ? 'warning' : 'medium-emphasis'"
                    />
                    <div>
                      <span class="text-caption font-weight-bold d-block">{{ $t('Primary Cover Photo') }}</span>
                      <span class="text-xs text-medium-emphasis">{{ $t('Featured as main thumbnail') }}</span>
                    </div>
                  </div>
                  <VSwitch
                    :model-value="activeImage.is_main"
                    color="warning"
                    density="compact"
                    hide-details
                    @update:model-value="setMainImage(activeImage)"
                  />
                </div>
              </VCard>

              <!-- Card 2: Visual Filter Presets -->
              <VCard
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <h6 class="text-subtitle-2 font-weight-bold d-flex align-center gap-1.5 mb-0">
                    <VIcon
                      icon="tabler-wand"
                      size="18"
                      color="primary"
                    />
                    {{ $t('Visual Filter') }}
                  </h6>
                  <span class="text-caption text-medium-emphasis text-capitalize">{{ activeImage.filter }}</span>
                </div>

                <div class="filter-presets-grid">
                  <button
                    v-for="filter in filterOptions"
                    :key="filter.key"
                    type="button"
                    class="filter-preset-btn rounded-lg border pa-1.5 d-flex flex-column align-center gap-1 cursor-pointer"
                    :class="{ 'filter-preset-btn--active': activeImage.filter === filter.key }"
                    @click="setFilter(filter.key)"
                  >
                    <div class="filter-preset-thumb rounded overflow-hidden">
                      <img
                        :src="activeImage.previewUrl"
                        alt=""
                        :style="{ filter: getFilterCss(filter.key, activeImage.brightness, activeImage.contrast) }"
                      >
                    </div>
                    <span class="text-xs font-weight-semibold">{{ filter.label }}</span>
                  </button>
                </div>
              </VCard>

              <!-- Card 3: Light & Contrast Adjustments -->
              <VCard
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <h6 class="text-subtitle-2 font-weight-bold d-flex align-center gap-1.5 mb-0">
                    <VIcon
                      icon="tabler-adjustments"
                      size="18"
                      color="primary"
                    />
                    {{ $t('Lighting & Exposure') }}
                  </h6>
                  <VBtn
                    v-if="activeImage.brightness !== 0 || activeImage.contrast !== 0 || activeImage.filter !== 'normal'"
                    variant="text"
                    size="x-small"
                    color="primary"
                    @click="resetAllAdjustmentsForActive"
                  >
                    {{ $t('wp.scanner.reset', 'Reset All') }}
                  </VBtn>
                </div>

                <div class="mb-2">
                  <div class="d-flex justify-space-between text-caption mb-0.5">
                    <span class="font-weight-medium d-flex align-center gap-1">
                      <VIcon
                        icon="tabler-sun"
                        size="14"
                      />
                      {{ $t('wp.scanner.brightness', 'Brightness') }}
                    </span>
                    <span class="font-monospace">{{ activeImage.brightness > 0 ? `+${activeImage.brightness}` : activeImage.brightness }}%</span>
                  </div>
                  <VSlider
                    v-model="activeImage.brightness"
                    :min="-50"
                    :max="50"
                    :step="1"
                    density="compact"
                    color="primary"
                    hide-details
                    thumb-size="14"
                    @update:model-value="invalidateEditedPreview(activeImage)"
                  />
                </div>

                <div>
                  <div class="d-flex justify-space-between text-caption mb-0.5">
                    <span class="font-weight-medium d-flex align-center gap-1">
                      <VIcon
                        icon="tabler-contrast"
                        size="14"
                      />
                      {{ $t('wp.scanner.contrast', 'Contrast') }}
                    </span>
                    <span class="font-monospace">{{ activeImage.contrast > 0 ? `+${activeImage.contrast}` : activeImage.contrast }}%</span>
                  </div>
                  <VSlider
                    v-model="activeImage.contrast"
                    :min="-50"
                    :max="50"
                    :step="1"
                    density="compact"
                    color="primary"
                    hide-details
                    thumb-size="14"
                    @update:model-value="invalidateEditedPreview(activeImage)"
                  />
                </div>
              </VCard>
            </div>
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 4: REVIEW & SAVE SUMMARY                                           -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <template #review="{ wizard }">
      <div class="py-1">
        <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
          <div>
            <h5 class="text-h6 font-weight-bold mb-0.5">
              {{ $t('Review Edited Photos ({count})', { count: images.length }) }}
            </h5>
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ $t('Verify your cropped and enhanced photos before saving.') }}
            </p>
          </div>
          <VChip
            color="success"
            variant="tonal"
            size="small"
            prepend-icon="tabler-circle-check"
          >
            {{ $t('Ready to Save') }}
          </VChip>
        </div>

        <VRow>
          <VCol
            v-for="(img, idx) in images"
            :key="img.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              variant="outlined"
              class="photo-review-card position-relative overflow-hidden h-100 d-flex flex-column"
            >
              <div
                class="position-relative overflow-hidden bg-black d-flex align-center justify-center"
                style="block-size: 160px;"
              >
                <img
                  :src="img.editedPreviewUrl || img.previewUrl"
                  alt=""
                  class="w-100 h-100"
                  style="object-fit: contain;"
                >
                <div
                  v-if="img.is_main && multiple"
                  class="position-absolute"
                  style="inset-block-start: 8px; inset-inline-start: 8px;"
                >
                  <VChip
                    size="x-small"
                    color="warning"
                    variant="elevated"
                    prepend-icon="tabler-star-filled"
                    class="font-weight-bold"
                  >
                    {{ $t('Main Photo') }}
                  </VChip>
                </div>

                <IconBtn
                  v-if="showCropper || showTitleInput"
                  size="x-small"
                  variant="flat"
                  color="surface"
                  class="position-absolute"
                  style="inset-block-start: 8px; inset-inline-end: 8px;"
                  :title="$t('Edit Photo')"
                  @click="selectActiveImage(idx); wizard.goTo(wizardSteps.findIndex(s => s.id === 'edit'))"
                >
                  <VIcon
                    icon="tabler-pencil"
                    size="14"
                  />
                </IconBtn>
              </div>

              <div class="pa-3 grow d-flex flex-column justify-space-between">
                <span class="text-subtitle-2 font-weight-bold d-block text-truncate mb-1">
                  {{ img.title || `${$t('Photo')} ${idx + 1}` }}
                </span>
                <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
                  <span>{{ formatBytes(img.editedSize || img.file?.size) }}</span>
                  <span class="font-monospace">
                    {{ img.editedWidth || img.naturalWidth }}×{{ img.editedHeight || img.naturalHeight }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>
  </WpWizardDialog>
</template>

<style lang="scss" scoped>
.source-choice-card {
  transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.15s ease;
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));

  &:hover,
  &:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.55);
    background: rgba(var(--v-theme-primary), 0.02);
    transform: translateY(-1px);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.05) !important;
  }
}

.camera-stage-container {
  inline-size: 100%;
  block-size: clamp(250px, 44vh, 380px);
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  overflow: hidden;
  background: #0b0f19;
}

.camera-video {
  inline-size: 100%;
  block-size: 100%;
  object-fit: cover;

  &--mirrored {
    transform: scaleX(-1);
  }
}

.camera-grid-overlay {
  inset: 0;
  z-index: 2;
}

.camera-grid-line {
  position: absolute;
  background: rgba(255, 255, 255, 0.28);
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.35);

  &--h1 { inset-inline: 0; top: 33.333%; block-size: 1px; }
  &--h2 { inset-inline: 0; top: 66.666%; block-size: 1px; }
  &--v1 { inset-block: 0; left: 33.333%; inline-size: 1px; }
  &--v2 { inset-block: 0; left: 66.666%; inline-size: 1px; }
}

.camera-shutter-flash {
  inset: 0;
  background: rgba(255, 255, 255, 0.88);
  z-index: 10;
  pointer-events: none;
  animation: shutterFlashFade 0.2s ease-out forwards;
}

@keyframes shutterFlashFade {
  from { opacity: 1; }
  to { opacity: 0; }
}

.camera-overlay-state {
  inset: 0;
  background: rgba(11, 15, 25, 0.94);
  z-index: 6;
}

.camera-controls-bar {
  position: absolute;
  z-index: 4;
  inset-block-start: 0;
  inset-inline: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.72), transparent);
}

.camera-tool-btn {
  background: rgba(20, 24, 36, 0.65) !important;
  color: #fff !important;
  backdrop-filter: blur(8px);
}

.camera-shutter-bar {
  position: absolute;
  z-index: 5;
  inset-block-end: 0;
  inset-inline: 0;
  background: linear-gradient(0deg, rgba(0, 0, 0, 0.78) 0%, rgba(0, 0, 0, 0.3) 70%, transparent 100%);
}

.camera-shutter-btn {
  inline-size: 66px;
  block-size: 66px;
  border-radius: 50%;
  border: 4px solid rgba(255, 255, 255, 0.92);
  background: rgba(255, 255, 255, 0.22);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.14s ease, background-color 0.14s ease;

  &:hover:not(:disabled) {
    transform: scale(1.05);
    background: rgba(255, 255, 255, 0.32);
  }

  &:active:not(:disabled) {
    transform: scale(0.92);
  }

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }

  .shutter-inner {
    inline-size: 50px;
    block-size: 50px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
  }
}

.upload-dropzone {
  border: 2px dashed rgba(var(--v-theme-primary), 0.38);
  background: rgba(var(--v-theme-primary), 0.02);
  transition: border-color 0.2s ease, background-color 0.2s ease;

  &:hover,
  &:focus-visible,
  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
  }
}

.staged-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
}

.staged-card {
  min-inline-size: 0;
  transition: border-color 0.15s ease;

  &--main {
    border-color: rgba(var(--v-theme-warning), 0.65) !important;
    background: rgba(var(--v-theme-warning), 0.04) !important;
  }

  &__meta {
    min-inline-size: 0;
  }
}

.staged-index-badge {
  position: absolute;
  inset-block-end: 2px;
  inset-inline-start: 2px;
  background: rgba(0, 0, 0, 0.72);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  padding: 0 4px;
  border-radius: 4px;
  line-height: 1.4;
}

.editor-Thumb-strip {
  scrollbar-width: thin;
}

.editor-thumb-pill {
  background: transparent;
  transition: border-color 0.18s ease, background-color 0.18s ease;
  min-inline-size: 0;

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08);
  }
}

.editor-thumb-img {
  inline-size: 38px;
  block-size: 38px;
  object-fit: cover;
  flex-shrink: 0;
}

.editor-thumb-meta {
  max-inline-size: 96px;
  min-inline-size: 0;
}

.edit-stage-viewport {
  min-block-size: 260px;
  block-size: clamp(260px, 42vh, 360px);
  background-color: #0e1117;
  background-image:
    linear-gradient(45deg, rgba(255, 255, 255, 0.03) 25%, transparent 25%),
    linear-gradient(-45deg, rgba(255, 255, 255, 0.03) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.03) 75%),
    linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.03) 75%);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0;
  user-select: none;
}

.crop-box {
  border: 2px solid #fff;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.58), 0 0 0 1px rgba(0, 0, 0, 0.4);
  cursor: move;
  touch-action: none;

  &--active {
    border-color: rgb(var(--v-theme-primary));
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}

.crop-dimensions-pill {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.78);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  white-space: nowrap;
  pointer-events: none;
  z-index: 4;
}

.crop-grid-rule-h,
.crop-grid-rule-v {
  position: absolute;
  pointer-events: none;
  opacity: 0.45;
}

.crop-grid-rule-h {
  inset-inline: 0;
  top: 33.333%;
  height: 33.333%;
  border-top: 1px dashed rgba(255, 255, 255, 0.85);
  border-bottom: 1px dashed rgba(255, 255, 255, 0.85);
}

.crop-grid-rule-v {
  inset-block: 0;
  left: 33.333%;
  width: 33.333%;
  border-left: 1px dashed rgba(255, 255, 255, 0.85);
  border-right: 1px dashed rgba(255, 255, 255, 0.85);
}

/* 44px touch target around every handle for mobile-first ergonomics */
.crop-handle {
  position: absolute;
  z-index: 3;
  inline-size: 40px;
  block-size: 40px;
  display: grid;
  place-items: center;
  touch-action: none;
  transform: translate(-50%, -50%);

  &::before {
    content: "";
    background: #fff;
    border: 2.5px solid rgb(var(--v-theme-primary));
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.45);
    transition: transform 0.12s ease, background-color 0.12s ease;
  }

  &--corner::before {
    inline-size: 16px;
    block-size: 16px;
    border-radius: 50%;
  }

  &--edge::before {
    inline-size: 12px;
    block-size: 12px;
    border-radius: 4px;
  }

  &--active::before,
  &:hover::before {
    transform: scale(1.25);
    background: rgb(var(--v-theme-primary));
    border-color: #fff;
  }

  &:focus-visible {
    outline: none;
  }

  &:focus-visible::before {
    outline: 2px solid #fff;
    outline-offset: 2px;
  }

  /* Match CROP_HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'] */
  &--nw { top: 0; left: 0; cursor: nwse-resize; }
  &--n  { top: 0; left: 50%; cursor: ns-resize; }
  &--ne { top: 0; left: 100%; cursor: nesw-resize; }
  &--e  { top: 50%; left: 100%; cursor: ew-resize; }
  &--se { top: 100%; left: 100%; cursor: nwse-resize; }
  &--s  { top: 100%; left: 50%; cursor: ns-resize; }
  &--sw { top: 100%; left: 0; cursor: nesw-resize; }
  &--w  { top: 50%; left: 0; cursor: ew-resize; }
}

.filter-presets-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.filter-preset-btn {
  background: transparent;
  transition: border-color 0.15s ease, background-color 0.15s ease;
  min-inline-size: 0;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.45);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgb(var(--v-theme-primary));
  }
}

.filter-preset-thumb {
  inline-size: 100%;
  aspect-ratio: 16 / 10;
  background: #111;

  img {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
    display: block;
  }
}

.pulse-dot {
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}
</style>
