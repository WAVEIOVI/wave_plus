<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WpScanCropEditor from '@/components/shared/documents/WpScanCropEditor.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import WpWizardDialog from '@/components/shared/wizard/WpWizardDialog.vue'
import {
  ATTACHMENT_ALL_ACCEPT,
  ATTACHMENT_DOCUMENT_ACCEPT,
  ATTACHMENT_MEDIA_ACCEPT,
  DOCUMENT_ACCEPT,
  addYears,
  formatDocumentBytes as formatBytes,
  getExpiryState,
  localiseError,
  matchesAccept,
  resolveFileColor,
  resolveFileIcon,
  resolveFileKind,
  resolveFileMime,
  resolveDocumentStatusColor as resolveStatusColor,
  titleFromFileName,
  toDateInputValue,
} from '@/composables/documents/documentFiles'
import {
  getDocumentPresetConfig,
  resolveDocumentPresetFields,
} from '@/composables/documents/documentPresets'
import {
  A4_RATIO,
  DEFAULT_ADJUST,
  DEFAULT_CORNERS,
  EXPORT_LONG_SIDE,
  ORIENTATION,
  PREVIEW_LONG_SIDE,
  SCAN_FILTER,
  THUMB_LONG_SIDE,
  applyScanFilter,
  canvasToBlob,
  detectDocumentCorners,
  fileToCanvas,
  rotateCorners,
  scaleCanvas,
  suggestOrientation,
  warpDocument,
} from '@/composables/documents/scanEngine'
import { useDocumentScanner } from '@/composables/documents/useDocumentScanner'
import { useOpenCv } from '@/composables/documents/useOpenCv'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  documentTypes: { type: Array, default: () => [] },
  documentStatuses: { type: Array, default: () => [] },
  initialData: { type: Object, default: () => null },
  preset: { type: String, default: 'compact' },
  fields: { type: Array, default: () => null },
  maxSizeMb: { type: Number, default: 50 },
  accept: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  defaultSource: { type: String, default: 'file' },
  allowedSources: { type: Array, default: () => ['file', 'photo', 'scan', 'media'] },
  multiple: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'error', 'close'])

const { t, locale } = useI18n()

// ── Constants for OpenCV Live Scanner & Studio ──────────────────────────────
const COOLDOWN_MS = 1600
const MAX_CAPTURE_SIDE = 1920
const ADJUST_DEBOUNCE_MS = 120
const AUTO_CAPTURE_KEY = 'wp.attachment.autoSnap'

// ── Attachment Metadata Form State ──────────────────────────────────────────
const formMeta = reactive({
  title: '',
  type: 'general',
  reference_number: '',
  issuer: '',
  status: 'active',
  issue_date: '',
  effective_date: '',
  expiration_date: '',
  auto_renewable: false,
  notes: '',
})

const isCompiling = ref(false)
const isDragging = ref(false)
const validationNotice = ref('')
const fileInputRef = ref(null)
const mediaInputRef = ref(null)
const photoFallbackInputRef = ref(null)
const appendInputRef = ref(null)
const importScanInputRef = ref(null)
const replaceInputRef = ref(null)
const replacingPageIndex = ref(-1)
const dragPageIndex = ref(-1)
const previewViewerItem = ref(null)
const isPreviewViewerOpen = ref(false)
const cameraMenuOpenRef = ref(false)
const activeStepIdRef = ref('source')
const currentSourceRef = ref(props.defaultSource || 'file')

// ── Preset & Field Visibility ───────────────────────────────────────────────
const activeFields = computed(() => resolveDocumentPresetFields(props.preset, props.fields))
const presetConfig = computed(() => getDocumentPresetConfig(props.preset))

function hasField(fieldName) {
  return activeFields.value.includes(fieldName)
}

const isEditMode = computed(() => Boolean(props.initialData?.id || props.initialData?.file_url || props.initialData?.file_name || props.initialData?.url))

const existingFileName = computed(() => {
  if (!props.initialData) return ''

  return props.initialData.file_name
    || props.initialData.filename
    || props.initialData.original_name
    || props.initialData.name
    || props.initialData.title
    || (props.initialData.file_url ? String(props.initialData.file_url).split('/').pop() : '')
    || (props.initialData.url ? String(props.initialData.url).split('/').pop() : '')
    || t('Attached File')
})

function extractLocalizedTitle(rawTitle) {
  if (!rawTitle) return ''
  if (typeof rawTitle === 'string') return rawTitle
  if (typeof rawTitle === 'object') {
    return rawTitle[locale.value] || rawTitle.fr || rawTitle.en || rawTitle.ar || Object.values(rawTitle).find(Boolean) || ''
  }

  return String(rawTitle)
}

// ── Unified Document, Camera & Scanner Composable ───────────────────────────
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
  pagesRef,
  pageCountRef,
  totalBytesRef,
  liveCornersRef,
  stableFramesRef: stableRef,
  STABLE_REQUIRED: STABLE_FRAMES,
  startDetectionLoop: startScannerDetectionLoop,
  stopDetectionLoop,
  resetDetectionState,
  addPage,
  addUploadedFiles,
  removePage,
  movePage,
  rotatePage,
  replacePage,
  generatePdf,
  clearPages,
  DOCUMENT_SOURCE,
} = useDocumentScanner({
  maxBytes: props.maxSizeMb * 1024 * 1024,
  idealWidth: 1920,
  idealHeight: 1080,
})

// ── OpenCV Engine & Scan Studio State ───────────────────────────────────────
const {
  readyRef: engineReadyRef,
  loadingRef: engineLoadingRef,
  loadOpenCv,
  getCv,
} = useOpenCv()

function readAutoCapturePreference() {
  try {
    const stored = localStorage.getItem(AUTO_CAPTURE_KEY)

    return stored === '1'
  }
  catch {
    return false
  }
}

const isShutterFlashing = ref(false)
const scanSubStep = ref('camera') // 'camera' | 'studio'
const editingExistingPageIndex = ref(-1) // -1 when new scan, >= 0 when re-editing a page
const stageRef = ref(null)
const frameStyle = ref({})
const frameRatioRef = ref(1)
const engineFailedRef = ref(false)
const capturingRef = ref(false)
const autoCaptureRef = ref(readAutoCapturePreference())

// Studio (Perspective Crop & Filter) state
const studioViewRef = ref('result') // 'crop' | 'result'
const cornersRef = ref(DEFAULT_CORNERS.map(p => ({ ...p })))
const cornersDetectedRef = ref(false)
const rotationRef = ref(0)
const orientationRef = ref(ORIENTATION.AUTO)
const filterRef = ref(SCAN_FILTER.ENHANCED)
const adjustRef = ref({ ...DEFAULT_ADJUST })
const tuneOpenRef = ref(false)
const sourceUrlRef = ref(null)
const sourceSizeRef = ref({ width: 1, height: 1 })
const resultUrlRef = ref(null)
const filterThumbsRef = ref({})
const studioProcessingRef = ref(false)

let sourceCanvas = null
let previewBase = null
let previewKey = ''
let thumbsKey = ''
let renderToken = 0
let cooldownUntil = 0
let adjustTimer = 0
let stageResizeObserver = null

function clearCanvasMemory(canvas) {
  if (!canvas) return
  canvas.width = 0
  canvas.height = 0
}

function revokeFilterThumbs() {
  const thumbs = filterThumbsRef.value || {}

  Object.values(thumbs).forEach(url => {
    if (typeof url === 'string' && url.startsWith('blob:')) {
      URL.revokeObjectURL(url)
    }
  })
  filterThumbsRef.value = {}
}

const filterItems = computed(() => [
  { value: SCAN_FILTER.ENHANCED, label: t('wp.scanner.filter_enhanced', 'Enhanced'), icon: 'tabler-wand' },
  { value: SCAN_FILTER.ORIGINAL, label: t('wp.scanner.filter_original', 'Original'), icon: 'tabler-photo' },
  { value: SCAN_FILTER.GRAYSCALE, label: t('wp.scanner.filter_grayscale', 'Grayscale'), icon: 'tabler-contrast-2' },
  { value: SCAN_FILTER.BLACK_WHITE, label: t('wp.scanner.filter_bw', 'B&W Clean'), icon: 'tabler-contrast' },
])

const orientationItems = computed(() => [
  { value: ORIENTATION.AUTO, label: t('Auto') },
  { value: ORIENTATION.PORTRAIT, label: t('Portrait') },
  { value: ORIENTATION.LANDSCAPE, label: t('Landscape') },
])

const effectiveOrientationRef = computed(() => {
  if (orientationRef.value !== ORIENTATION.AUTO) return orientationRef.value

  const { width, height } = sourceSizeRef.value
  const base = suggestOrientation(cornersRef.value, width, height)

  if (rotationRef.value % 2 === 0) return base

  return base === ORIENTATION.LANDSCAPE ? ORIENTATION.PORTRAIT : ORIENTATION.LANDSCAPE
})

const guideRectRef = computed(() => {
  const ratio = frameRatioRef.value || 1.33
  const aspect = orientationRef.value === ORIENTATION.LANDSCAPE ? 1 / A4_RATIO : A4_RATIO
  const heightFraction = Math.min(0.84, (0.84 * ratio) / aspect)
  const widthFraction = (aspect * heightFraction) / ratio

  return {
    x: ((1 - widthFraction) / 2) * 100,
    y: ((1 - heightFraction) / 2) * 100,
    width: widthFraction * 100,
    height: heightFraction * 100,
  }
})

const livePolygonRef = computed(() => (liveCornersRef.value || []).map(p => `${p.x * 100},${p.y * 100}`).join(' '))

const guideMaskRef = computed(() => {
  const rect = guideRectRef.value

  return `M0,0H100V100H0Z M${rect.x},${rect.y} H${rect.x + rect.width} V${rect.y + rect.height} H${rect.x} Z`
})

const liveMaskRef = computed(() => {
  if (!liveCornersRef.value) return null
  const [first, ...rest] = liveCornersRef.value.map(p => `${p.x * 100},${p.y * 100}`)

  return `M0,0H100V100H0Z M${first} ${rest.map(p => `L${p}`).join(' ')} Z`
})

const stableProgressRef = computed(() => Math.min(1, stableRef.value / STABLE_FRAMES))

const shutterRingStyleRef = computed(() => {
  if (!autoCaptureRef.value || !liveCornersRef.value || capturingRef.value) return {}
  const degrees = Math.round(stableProgressRef.value * 360)

  return {
    background: `conic-gradient(rgb(var(--v-theme-success)) ${degrees}deg, rgba(255, 255, 255, 28%) ${degrees}deg)`,
  }
})

const scannerStatusRef = computed(() => {
  if (engineFailedRef.value) {
    return { text: t('Manual capture ready'), color: 'warning', icon: 'tabler-hand-move', spin: false }
  }
  if (!engineReadyRef.value || engineLoadingRef.value) {
    return { text: t('Initializing AI edge detector...'), color: 'info', icon: 'tabler-loader-2', spin: true }
  }
  if (liveCornersRef.value) {
    return autoCaptureRef.value
      ? { text: t('Hold steady - auto snapping'), color: 'success', icon: 'tabler-sparkles', spin: false }
      : { text: t('Document edges aligned'), color: 'success', icon: 'tabler-check', spin: false }
  }

  return { text: t('Fit paper within guide frame'), color: 'surface', icon: 'tabler-focus-2', spin: false }
})

const zoomLabelRef = computed(() => {
  const current = zoomRef.value
  if (!current || current <= 1.05) return '1x'

  return `${current.toFixed(1).replace(/\.0$/, '')}x`
})

function cycleCameraZoom() {
  const { min, max } = zoomRangeRef.value
  const current = zoomRef.value
  let target = current + 0.5
  if (target > max || target > 3.0) target = min

  setZoom(Math.max(min, Math.min(max, target)))
}

async function handleSelectCamera(deviceId) {
  try {
    await selectCamera(deviceId)
  }
  catch (err) {
    reportError(err)
  }
}

async function handleRetryCamera() {
  validationNotice.value = ''
  try {
    await startCamera()
    if (currentSourceRef.value === 'scan' && scanSubStep.value === 'camera') {
      startDetectionLoop()
    }
  }
  catch (err) {
    reportError(err)
  }
}

function reportError(error) {
  const message = localiseError(error, t)

  validationNotice.value = message
  emit('error', error)
}

function layoutStage() {
  const stage = stageRef.value
  const video = videoRef.value
  if (!stage) return

  const containerW = stage.clientWidth
  const containerH = stage.clientHeight
  if (!containerW || !containerH) return

  const videoW = video?.videoWidth || 1920
  const videoH = video?.videoHeight || 1080
  const ratio = videoW / videoH

  frameRatioRef.value = ratio

  let width = containerW
  let height = containerW / ratio
  if (height > containerH) {
    height = containerH
    width = containerH * ratio
  }

  frameStyle.value = {
    width: `${Math.round(width)}px`,
    height: `${Math.round(height)}px`,
  }
}

function startDetectionLoop() {
  startScannerDetectionLoop({
    getCv,
    isBlocked: () => activeStepIdRef.value !== 'capture'
      || currentSourceRef.value !== 'scan'
      || scanSubStep.value !== 'camera'
      || capturingRef.value,
    isAutoCapture: () => autoCaptureRef.value && Date.now() > cooldownUntil,
    onAutoCapture: () => {
      if (!capturingRef.value) {
        captureAndOpenStudio(-1)
      }
    },
  })
}

async function syncCameraAndScannerMode(source = currentSourceRef.value) {
  const normalized = source === 'upload' ? 'file' : (source || 'file')

  currentSourceRef.value = normalized

  if (activeStepIdRef.value !== 'capture') {
    stopDetectionLoop()
    if (isCameraActive.value || isCameraLoading.value) {
      await stopCamera()
    }

    return
  }

  if (normalized === 'photo' || normalized === 'scan') {
    if (normalized === 'scan') {
      engineFailedRef.value = false
      loadOpenCv().catch(() => {
        engineFailedRef.value = true
      })
    }
    else {
      stopDetectionLoop()
    }

    if (scanSubStep.value === 'camera') {
      if (!isCameraActive.value && !isCameraLoading.value) {
        try {
          await startCamera()
        }
        catch {
          // cameraError ref handles the UI state
        }
      }

      if (normalized === 'scan' && isCameraActive.value) {
        startDetectionLoop()
      }
    }
  }
  else {
    stopDetectionLoop()
    if (isCameraActive.value || isCameraLoading.value) {
      await stopCamera()
    }
  }
}

function setSourceChoice(form, nextSource) {
  const normalized = nextSource === 'upload' ? 'file' : nextSource

  if (form) {
    form.source = normalized
  }
  currentSourceRef.value = normalized

  if (activeStepIdRef.value === 'capture') {
    if (scanSubStep.value === 'studio') {
      exitStudioToCamera(false)
    }
    syncCameraAndScannerMode(normalized)
  }
}

function handleWizardStepChange(step) {
  const stepId = step?.id || (step?.isReview ? 'review' : 'source')

  activeStepIdRef.value = stepId
  syncCameraAndScannerMode(currentSourceRef.value)
}

watch(videoRef, el => {
  if (el && activeStepIdRef.value === 'capture' && (currentSourceRef.value === 'photo' || currentSourceRef.value === 'scan')) {
    syncCameraAndScannerMode(currentSourceRef.value)
  }
})

watch(stageRef, el => {
  stageResizeObserver?.disconnect()
  stageResizeObserver = null
  if (el && typeof ResizeObserver !== 'undefined') {
    stageResizeObserver = new ResizeObserver(layoutStage)
    stageResizeObserver.observe(el)
    layoutStage()
  }
})

watch(autoCaptureRef, enabled => {
  try {
    localStorage.setItem(AUTO_CAPTURE_KEY, enabled ? '1' : '0')
  }
  catch {
    // ignore storage restrictions
  }
})

// ── Studio Image Capture & Warping ──────────────────────────────────────────
async function captureAndOpenStudio(editIndex = -1) {
  if (capturingRef.value) return
  capturingRef.value = true

  const validEditIndex = typeof editIndex === 'number' && Number.isInteger(editIndex) ? editIndex : -1

  editingExistingPageIndex.value = validEditIndex
  stopDetectionLoop()

  try {
    let frameCanvas = null
    let detected = false
    const cv = getCv()

    if (validEditIndex >= 0 && pagesRef.value[validEditIndex]) {
      const page = pagesRef.value[validEditIndex]

      frameCanvas = await fileToCanvas(page.originalFile || page.file, MAX_CAPTURE_SIDE)
      cornersRef.value = page.corners ? page.corners.map(p => ({ ...p })) : DEFAULT_CORNERS.map(p => ({ ...p }))
      cornersDetectedRef.value = Boolean(page.corners)
      rotationRef.value = page.rotation || 0
      orientationRef.value = page.orientation || ORIENTATION.AUTO
      detected = true
    }
    else {
      const video = videoRef.value
      if (!video || !video.videoWidth) {
        await startCamera()
      }

      const activeVideo = videoRef.value
      if (!activeVideo || !activeVideo.videoWidth) {
        throw new Error(t('Camera feed is unavailable.'))
      }

      isShutterFlashing.value = true
      setTimeout(() => { isShutterFlashing.value = false }, 180)

      const scale = Math.min(1, MAX_CAPTURE_SIDE / Math.max(activeVideo.videoWidth, activeVideo.videoHeight))

      frameCanvas = document.createElement('canvas')
      frameCanvas.width = Math.max(1, Math.round(activeVideo.videoWidth * scale))
      frameCanvas.height = Math.max(1, Math.round(activeVideo.videoHeight * scale))

      const ctx = frameCanvas.getContext('2d')

      ctx.drawImage(activeVideo, 0, 0, frameCanvas.width, frameCanvas.height)

      const liveHint = liveCornersRef.value ? liveCornersRef.value.map(p => ({ ...p })) : null

      if (cv) {
        const found = detectDocumentCorners(cv, frameCanvas) || liveHint
        if (found) {
          cornersRef.value = found
          cornersDetectedRef.value = true
          detected = true
        }
        else {
          cornersRef.value = DEFAULT_CORNERS.map(p => ({ ...p }))
          cornersDetectedRef.value = false
        }
      }
      else {
        cornersRef.value = (liveHint || DEFAULT_CORNERS).map(p => ({ ...p }))
        cornersDetectedRef.value = Boolean(liveHint)
      }

      rotationRef.value = 0
      orientationRef.value = ORIENTATION.AUTO
    }

    if (sourceCanvas && sourceCanvas !== frameCanvas) {
      clearCanvasMemory(sourceCanvas)
    }
    if (previewBase) {
      clearCanvasMemory(previewBase)
    }

    sourceCanvas = frameCanvas
    sourceSizeRef.value = { width: frameCanvas.width, height: frameCanvas.height }

    if (sourceUrlRef.value) URL.revokeObjectURL(sourceUrlRef.value)
    if (resultUrlRef.value) URL.revokeObjectURL(resultUrlRef.value)
    revokeFilterThumbs()

    previewBase = null
    previewKey = ''
    thumbsKey = ''

    studioViewRef.value = validEditIndex >= 0 || !detected || !cv ? 'crop' : 'result'
    sourceUrlRef.value = URL.createObjectURL(await canvasToBlob(frameCanvas, 0.88))
    scanSubStep.value = 'studio'

    if (studioViewRef.value === 'result' && cv) {
      await renderStudioPreview()
    }
  }
  catch (err) {
    reportError(err)
  }
  finally {
    capturingRef.value = false
  }
}

// ── Direct Photo Capture (Clean Photo, No A4 Warp) ──────────────────────────
async function capturePhotoDirectly() {
  if (capturingRef.value) return
  capturingRef.value = true
  validationNotice.value = ''

  try {
    if (!isCameraActive.value) {
      await startCamera()
    }

    isShutterFlashing.value = true
    navigator.vibrate?.(25)
    setTimeout(() => { isShutterFlashing.value = false }, 180)

    const dateStr = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)

    const result = await captureFrameAsFile(`photo-${dateStr}.jpg`, {
      quality: 0.92,
      maxSide: 1920,
      mirror: isUserCamera.value,
    })

    addPage({
      file: result.file,
      preview: result.preview,
      width: result.width,
      height: result.height,
      pageCount: 1,
      format: null,
      orientation: result.width > result.height ? ORIENTATION.LANDSCAPE : ORIENTATION.PORTRAIT,
      source: DOCUMENT_SOURCE.CAMERA_PHOTO || 'camera_photo',
    })

    if (!formMeta.title) {
      formMeta.title = titleFromFileName(result.file.name)
    }
  }
  catch (err) {
    reportError(err)
  }
  finally {
    capturingRef.value = false
  }
}

function handlePhotoFallbackSelected(event) {
  const files = event.target.files
  if (!files || !files.length) return
  const file = files[0]

  event.target.value = ''

  const preview = URL.createObjectURL(file)

  addPage({
    file,
    preview,
    width: null,
    height: null,
    pageCount: 1,
    source: DOCUMENT_SOURCE.CAMERA_PHOTO || 'camera_photo',
  })

  if (!formMeta.title) {
    formMeta.title = titleFromFileName(file.name)
  }
}

function handleMediaUploadSelected(event) {
  const files = event.target.files
  if (!files || !files.length) return
  handleFilesSelected(files, DOCUMENT_SOURCE.MEDIA || 'media')
  event.target.value = ''
}

// ── Studio Perspective Warping & Contrast Filtering ────────────────────────
async function getWarpedPreviewCanvas(longSide = PREVIEW_LONG_SIDE) {
  const cv = getCv()
  if (!cv || !sourceCanvas) return null

  const key = `${cornersRef.value.map(p => `${p.x.toFixed(3)},${p.y.toFixed(3)}`).join(';')}|${effectiveOrientationRef.value}|${longSide}`
  if (previewBase && previewKey === key) return previewBase

  if (previewBase) {
    clearCanvasMemory(previewBase)
  }

  const warped = warpDocument(cv, sourceCanvas, cornersRef.value, {
    orientation: effectiveOrientationRef.value,
    longSide,
  })

  previewBase = warped
  previewKey = key

  return warped
}

async function renderStudioPreview() {
  const cv = getCv()
  if (!cv || !sourceCanvas) return

  const token = ++renderToken

  studioProcessingRef.value = true

  let rotated = null
  let filtered = null

  try {
    const warped = await getWarpedPreviewCanvas(PREVIEW_LONG_SIDE)
    if (!warped || token !== renderToken) return

    rotated = warped
    if (rotationRef.value !== 0) {
      const rotCanvas = document.createElement('canvas')
      const swap = rotationRef.value % 2 !== 0

      rotCanvas.width = swap ? warped.height : warped.width
      rotCanvas.height = swap ? warped.width : warped.height

      const ctx = rotCanvas.getContext('2d')

      ctx.translate(rotCanvas.width / 2, rotCanvas.height / 2)
      ctx.rotate((rotationRef.value * 90 * Math.PI) / 180)
      ctx.drawImage(warped, -warped.width / 2, -warped.height / 2)
      rotated = rotCanvas
    }

    filtered = applyScanFilter(cv, rotated, filterRef.value, adjustRef.value)
    if (token !== renderToken) return

    const blob = await canvasToBlob(filtered, 0.88)
    if (token !== renderToken) return

    if (resultUrlRef.value) URL.revokeObjectURL(resultUrlRef.value)
    resultUrlRef.value = URL.createObjectURL(blob)
  }
  catch (err) {
    console.error('Failed to render scan preview:', err)
  }
  finally {
    if (filtered && filtered !== rotated && filtered !== previewBase) {
      clearCanvasMemory(filtered)
    }
    if (rotated && rotated !== previewBase) {
      clearCanvasMemory(rotated)
    }
    if (token === renderToken) studioProcessingRef.value = false
  }
}

async function renderFilterThumbnails() {
  const cv = getCv()
  if (!cv || !sourceCanvas) return

  const key = `${cornersRef.value.map(p => `${p.x.toFixed(2)},${p.y.toFixed(2)}`).join(';')}|${rotationRef.value}|${effectiveOrientationRef.value}`
  if (thumbsKey === key && Object.keys(filterThumbsRef.value).length) return
  thumbsKey = key

  const token = renderToken

  try {
    const warped = await getWarpedPreviewCanvas(PREVIEW_LONG_SIDE)
    if (!warped) return

    const thumbSource = scaleCanvas(warped, THUMB_LONG_SIDE)
    const thumbMap = {}

    try {
      for (const item of filterItems.value) {
        await new Promise(resolve => requestAnimationFrame(resolve))
        if (token !== renderToken) return

        const filtered = applyScanFilter(cv, thumbSource, item.value, adjustRef.value)
        const blob = await canvasToBlob(filtered, 0.72)

        if (filtered !== thumbSource) clearCanvasMemory(filtered)
        thumbMap[item.value] = URL.createObjectURL(blob)
      }

      if (token === renderToken) {
        revokeFilterThumbs()
        filterThumbsRef.value = thumbMap
      }
      else {
        Object.values(thumbMap).forEach(u => URL.revokeObjectURL(u))
      }
    }
    finally {
      if (thumbSource !== warped) clearCanvasMemory(thumbSource)
    }
  }
  catch {
    // Thumbnail render failure fallback
  }
}

function rotateStudioClockwise() {
  rotationRef.value = (rotationRef.value + 1) % 4
  if (previewBase) clearCanvasMemory(previewBase)
  previewBase = null
  previewKey = ''
  renderStudioPreview()
  renderFilterThumbnails()
}

function selectFilter(filterName) {
  filterRef.value = filterName
  renderStudioPreview()
}

function scheduleAdjustUpdate() {
  clearTimeout(adjustTimer)
  adjustTimer = setTimeout(() => {
    renderStudioPreview()
  }, ADJUST_DEBOUNCE_MS)
}

async function confirmStudioCrop() {
  const cv = getCv()

  studioViewRef.value = 'result'
  if (previewBase) clearCanvasMemory(previewBase)
  previewBase = null
  previewKey = ''
  if (cv) {
    await renderStudioPreview()
    renderFilterThumbnails()
  }
}

async function commitStudioPage() {
  if (!sourceCanvas) return
  studioProcessingRef.value = true

  let warped = null
  let filtered = null

  try {
    const cv = getCv()
    const orientation = effectiveOrientationRef.value
    let finalCanvas = sourceCanvas

    if (cv) {
      warped = warpDocument(cv, sourceCanvas, rotateCorners(cornersRef.value, rotationRef.value), {
        orientation,
        longSide: EXPORT_LONG_SIDE,
      })

      filtered = applyScanFilter(cv, warped, filterRef.value, adjustRef.value)
      finalCanvas = filtered
    }

    const blob = await canvasToBlob(finalCanvas, 0.92)
    const rawBlob = await canvasToBlob(sourceCanvas, 0.9)

    const pageNum = editingExistingPageIndex.value >= 0
      ? editingExistingPageIndex.value + 1
      : pagesRef.value.length + 1

    const file = new File([blob], `scan-page-${pageNum}.jpg`, { type: 'image/jpeg' })
    const originalFile = new File([rawBlob], `scan-raw-${pageNum}.jpg`, { type: 'image/jpeg' })
    const preview = URL.createObjectURL(file)

    if (editingExistingPageIndex.value >= 0 && pagesRef.value[editingExistingPageIndex.value]) {
      const target = pagesRef.value[editingExistingPageIndex.value]
      if (target.preview) URL.revokeObjectURL(target.preview)

      target.file = file
      target.originalFile = originalFile
      target.corners = cornersRef.value.map(p => ({ ...p }))
      target.preview = preview
      target.width = finalCanvas.width
      target.height = finalCanvas.height
      target.rotation = 0
      target.format = 'a4'
      target.orientation = orientation
      target.source = DOCUMENT_SOURCE.CAMERA_SCAN
    }
    else {
      addPage({
        file,
        preview,
        width: finalCanvas.width,
        height: finalCanvas.height,
        pageCount: 1,
        format: 'a4',
        orientation,
        source: DOCUMENT_SOURCE.CAMERA_SCAN,
      })

      const added = pagesRef.value[pagesRef.value.length - 1]
      if (added) {
        added.originalFile = originalFile
        added.corners = cornersRef.value.map(p => ({ ...p }))
      }
    }

    if (!formMeta.title && pagesRef.value.length === 1) {
      formMeta.title = `${t('Document Scan')} ${new Date().toLocaleDateString(locale.value)}`
    }

    exitStudioToCamera()
  }
  catch (err) {
    reportError(err)
  }
  finally {
    if (filtered && filtered !== warped && filtered !== sourceCanvas) clearCanvasMemory(filtered)
    if (warped && warped !== sourceCanvas) clearCanvasMemory(warped)
    studioProcessingRef.value = false
  }
}

function exitStudioToCamera(resumeLoop = true) {
  scanSubStep.value = 'camera'
  editingExistingPageIndex.value = -1
  cooldownUntil = Date.now() + COOLDOWN_MS
  if (sourceUrlRef.value) URL.revokeObjectURL(sourceUrlRef.value)
  if (resultUrlRef.value) URL.revokeObjectURL(resultUrlRef.value)
  revokeFilterThumbs()
  sourceUrlRef.value = null
  resultUrlRef.value = null
  clearCanvasMemory(previewBase)
  clearCanvasMemory(sourceCanvas)
  previewBase = null
  sourceCanvas = null
  resetDetectionState()
  if (resumeLoop && activeStepIdRef.value === 'capture' && currentSourceRef.value === 'scan') {
    syncCameraAndScannerMode('scan')
  }
}

// ── File Selection & Organizing ─────────────────────────────────────────────
async function handleFilesSelected(fileList, source = DOCUMENT_SOURCE.UPLOAD) {
  if (!fileList || !fileList.length) return
  validationNotice.value = ''

  const incoming = Array.from(fileList)
  const activeAccept = props.accept || (source === 'media' ? ATTACHMENT_MEDIA_ACCEPT : ATTACHMENT_ALL_ACCEPT)
  const invalidAccept = incoming.find(f => !matchesAccept(f, activeAccept))
  if (invalidAccept) {
    reportError(new Error(t('Unsupported file type: {name}', { name: invalidAccept.name })))

    return
  }

  try {
    await addUploadedFiles(incoming, { replace: false, source })
    if (!formMeta.title && incoming[0]) {
      formMeta.title = titleFromFileName(incoming[0].name)
    }
  }
  catch (err) {
    reportError(err)
  }
}

function onFileInputChange(e) {
  handleFilesSelected(e.target.files, DOCUMENT_SOURCE.UPLOAD)
  e.target.value = ''
}

function onDrop(e) {
  isDragging.value = false
  handleFilesSelected(e.dataTransfer?.files, DOCUMENT_SOURCE.UPLOAD)
}

function onReplaceFileChange(e) {
  const file = e.target.files?.[0]

  e.target.value = ''
  if (!file || replacingPageIndex.value < 0) return

  replacePage(replacingPageIndex.value, file).catch(reportError)
  replacingPageIndex.value = -1
}

function onImportScanFileChange(e) {
  const file = e.target.files?.[0]

  e.target.value = ''
  if (!file) return

  stopDetectionLoop()
  fileToCanvas(file, MAX_CAPTURE_SIDE).then(async canvas => {
    if (sourceCanvas) clearCanvasMemory(sourceCanvas)
    if (previewBase) clearCanvasMemory(previewBase)
    sourceCanvas = canvas
    sourceSizeRef.value = { width: canvas.width, height: canvas.height }

    const cv = getCv()
    const detected = cv ? detectDocumentCorners(cv, canvas) : null

    cornersRef.value = (detected || DEFAULT_CORNERS).map(p => ({ ...p }))
    cornersDetectedRef.value = Boolean(detected)
    rotationRef.value = 0
    orientationRef.value = ORIENTATION.AUTO
    if (sourceUrlRef.value) URL.revokeObjectURL(sourceUrlRef.value)
    sourceUrlRef.value = URL.createObjectURL(file)
    studioViewRef.value = detected && cv ? 'result' : 'crop'
    scanSubStep.value = 'studio'
    if (studioViewRef.value === 'result' && cv) {
      await renderStudioPreview()
    }
  }).catch(reportError)
}

function previewPageItem(page) {
  if (!page) return
  const file = page.file
  const mime = resolveFileMime(file)

  previewViewerItem.value = {
    title: file?.name || t('Page Preview'),
    url: page.preview || URL.createObjectURL(file),
    download_url: page.preview || null,
    mime_type: mime,
    size: file?.size || 0,
  }
  isPreviewViewerOpen.value = true
}

// ── Categories List ─────────────────────────────────────────────────────────
const categoriesList = computed(() => {
  if (props.documentTypes && props.documentTypes.length) {
    return props.documentTypes.map(item => typeof item === 'string' ? { value: item, title: item } : item)
  }

  return [
    { value: 'general', title: t('General Document'), icon: 'tabler-file' },
    { value: 'contract', title: t('Contract / Agreement'), icon: 'tabler-file-certificate' },
    { value: 'id_scan', title: t('ID / Passport Scan'), icon: 'tabler-id-badge' },
    { value: 'photo', title: t('Inspection Photo'), icon: 'tabler-camera' },
    { value: 'video', title: t('Video Clip'), icon: 'tabler-movie' },
    { value: 'report', title: t('Technical Report'), icon: 'tabler-clipboard-text' },
    { value: 'certificate', title: t('Compliance Certificate'), icon: 'tabler-award' },
    { value: 'invoice', title: t('Invoice / Receipt'), icon: 'tabler-receipt' },
    { value: 'other', title: t('Other Attachment'), icon: 'tabler-paperclip' },
  ]
})

// ── Wizard Steps Definition ─────────────────────────────────────────────────
const wizardSteps = computed(() => [
  {
    id: 'source',
    title: t('Action Choice'),
    subtitle: t('Upload, Photo, Scan, Media'),
    icon: 'tabler-paperclip',
    validate: form => {
      if (!form.source) return t('Please choose an attachment action.')
      currentSourceRef.value = form.source === 'upload' ? 'file' : form.source

      return true
    },
  },
  {
    id: 'capture',
    title: t('Attachment Staging'),
    subtitle: t('Capture, crop & organize'),
    icon: 'tabler-files',
    validate: () => {
      if (scanSubStep.value === 'studio') {
        return t('Please confirm or cancel the current page crop before proceeding.')
      }
      if (!pagesRef.value.length && !isEditMode.value) {
        return t('Please add or capture at least one attachment.')
      }

      return true
    },
  },
  {
    id: 'details',
    title: t('Details & Metadata'),
    subtitle: t('Indexing & compliance'),
    icon: 'tabler-file-description',
    validate: () => {
      if (!formMeta.title?.trim()) return t('Attachment title/name is required.')
      if (formMeta.issue_date && formMeta.expiration_date && formMeta.expiration_date < formMeta.issue_date) {
        return t('Expiration date cannot be earlier than the date.')
      }

      return true
    },
  },
])

// ── Wizard Submit Handler ───────────────────────────────────────────────────
async function handleWizardSubmit() {
  if (!pagesRef.value.length && !isEditMode.value) return
  isCompiling.value = true
  validationNotice.value = ''

  try {
    let finalFile = null
    let finalFiles = pagesRef.value.map(p => p.file).filter(Boolean)
    let primarySource = 'upload'

    if (pagesRef.value.length > 0) {
      const hasScannedPage = pagesRef.value.some(p => p.source === DOCUMENT_SOURCE.CAMERA_SCAN)
      const hasPhotoPage = pagesRef.value.some(p => p.source === (DOCUMENT_SOURCE.CAMERA_PHOTO || 'camera_photo'))
      const hasRotatedPage = pagesRef.value.some(p => Boolean(p.rotation))
      const hasVideoPage = pagesRef.value.some(p => resolveFileKind(resolveFileMime(p.file), p.file?.name) === 'video')
      const firstPage = pagesRef.value[0]
      const firstKind = resolveFileKind(resolveFileMime(firstPage.file), firstPage.file?.name)

      if (hasScannedPage) primarySource = 'camera_scan'
      else if (hasPhotoPage) primarySource = 'camera_photo'
      else if (firstKind === 'video') primarySource = 'media'
      else primarySource = 'upload'

      const acceptRequiresPdf = Boolean(
        props.accept
        && firstKind === 'image'
        && !matchesAccept(firstPage.file, props.accept)
        && matchesAccept({ name: 'document.pdf', type: 'application/pdf' }, props.accept),
      )

      // Compile into PDF when pages were scanned as A4 document, rotated,
      // multiple combinable pages were staged, or the target accept filter requires PDF.
      const shouldCompilePdf = !hasVideoPage && (
        hasScannedPage
        || hasRotatedPage
        || acceptRequiresPdf
        || (pagesRef.value.length > 1 && (firstKind === 'image' || firstKind === 'pdf'))
      )

      if (!shouldCompilePdf) {
        finalFile = firstPage.file
      }
      else {
        try {
          const safeSlug = (formMeta.title || 'document')
            .trim()
            .replace(/[^\w\- ]+/g, '')
            .replace(/\s+/g, '-')
            .toLowerCase() || 'attachment'

          const { file: compiledPdf } = await generatePdf({
            title: formMeta.title.trim() || 'Attachment',
            name: `${safeSlug}.pdf`,
          })

          finalFile = compiledPdf
          finalFiles = [compiledPdf]
        }
        catch (pdfErr) {
          console.warn('PDF generation fallback to first file:', pdfErr)
          finalFile = firstPage.file
        }
      }
    }

    const resolvedTitle = formMeta.title.trim() || finalFile?.name || existingFileName.value || 'Attachment'
    const resolvedType = formMeta.type || categoriesList.value[0]?.value || 'general'

    emit('save', {
      ...(props.initialData?.id ? { id: props.initialData.id } : {}),
      file: finalFile,
      files: finalFiles.length ? finalFiles : (finalFile ? [finalFile] : []),
      title: resolvedTitle,
      name: resolvedTitle,
      type: resolvedType,
      document_type: resolvedType,
      category: resolvedType,
      reference_number: formMeta.reference_number?.trim() || null,
      reference: formMeta.reference_number?.trim() || null,
      document_number: formMeta.reference_number?.trim() || null,
      issuer: formMeta.issuer?.trim() || null,
      status: formMeta.status || 'active',
      date: formMeta.issue_date || null,
      issue_date: formMeta.issue_date || null,
      effective_date: formMeta.effective_date || null,
      expiration_date: formMeta.expiration_date || null,
      expiry_date: formMeta.expiration_date || null,
      auto_renewable: Boolean(formMeta.auto_renewable),
      notes: formMeta.notes?.trim() || null,
      description: formMeta.notes?.trim() || null,
      source: primarySource,
    })
  }
  catch (err) {
    reportError(err)
  }
  finally {
    isCompiling.value = false
  }
}

function resetAll() {
  exitStudioToCamera(false)
  clearPages()
  stopDetectionLoop()
  stopCamera()
  validationNotice.value = ''
  scanSubStep.value = 'camera'
  editingExistingPageIndex.value = -1
  activeStepIdRef.value = 'source'
  currentSourceRef.value = props.defaultSource || 'file'

  formMeta.title = ''
  formMeta.type = categoriesList.value[0]?.value || 'general'
  formMeta.reference_number = ''
  formMeta.issuer = ''
  formMeta.status = 'active'
  formMeta.issue_date = ''
  formMeta.effective_date = ''
  formMeta.expiration_date = ''
  formMeta.auto_renewable = false
  formMeta.notes = ''
}

// ── Watch Initial Data for Edit Mode ────────────────────────────────────────
watch(
  () => props.initialData,
  init => {
    if (init) {
      formMeta.title = extractLocalizedTitle(init.title || init.name)
      formMeta.type = init.type || init.document_type || init.category || categoriesList.value[0]?.value || 'general'
      formMeta.reference_number = init.reference_number || init.document_number || init.reference || ''
      formMeta.issuer = init.issuer || ''
      formMeta.status = init.status || 'active'
      formMeta.issue_date = toDateInputValue(init.issue_date || init.date)
      formMeta.effective_date = toDateInputValue(init.effective_date)
      formMeta.expiration_date = toDateInputValue(init.expiration_date || init.expiry_date || init.expires_at)
      formMeta.auto_renewable = Boolean(init.auto_renewable)
      formMeta.notes = init.notes || init.description || ''
    }
  },
  { immediate: true },
)

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      activeStepIdRef.value = 'source'
      currentSourceRef.value = props.defaultSource || 'file'
    }
    else {
      resetAll()
      emit('close')
    }
  },
)

onBeforeUnmount(() => {
  resetAll()
  if (sourceUrlRef.value) URL.revokeObjectURL(sourceUrlRef.value)
  if (resultUrlRef.value) URL.revokeObjectURL(resultUrlRef.value)
  revokeFilterThumbs()
  stageResizeObserver?.disconnect()
})
</script>

<template>
  <WpWizardDialog
    :model-value="modelValue"
    variant="standard"
    :title="title || (isEditMode ? $t('Edit Attachment') : $t('Add Attachment'))"
    :description="$t('Upload files, take camera photos, scan paper documents, or attach media.')"
    :icon="presetConfig.icon || 'tabler-paperclip'"
    :steps="wizardSteps"
    :initial-values="{ source: defaultSource }"
    :submit="handleWizardSubmit"
    :loading="saving || isCompiling"
    review
    :review-title="$t('Review & Save')"
    :max-width="920"
    @update:model-value="$emit('update:modelValue', $event)"
    @step-change="handleWizardStepChange"
    @cancel="resetAll"
  >
    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 1: CHOICE OF SOURCE ACTION                                    -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-source="{ form }">
      <div class="py-2">
        <!-- Existing File Banner in Edit Mode -->
        <VAlert
          v-if="isEditMode"
          type="info"
          variant="tonal"
          density="comfortable"
          icon="tabler-file-check"
          class="mb-5"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div>
              <div class="font-weight-bold text-body-2">
                {{ $t('Editing Existing Attachment') }}: {{ existingFileName }}
              </div>
              <div class="text-caption">
                {{ $t('You can update metadata directly, or capture/upload a new file below to replace it.') }}
              </div>
            </div>
          </div>
        </VAlert>

        <h5 class="text-h6 font-weight-bold mb-1">
          {{ $t('How would you like to add this attachment?') }}
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-6">
          {{ $t('Select an action: upload files from your device, snap a camera photo, scan paper documents, or attach media.') }}
        </p>

        <VRow>
          <!-- 1. Upload File -->
          <VCol
            v-if="allowedSources.includes('file')"
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="choice-card pa-5 cursor-pointer h-100"
              :class="{ 'choice-card--active': form.source === 'file' || form.source === 'upload' }"
              tabindex="0"
              @click="setSourceChoice(form, 'file')"
              @keydown.enter.prevent="setSourceChoice(form, 'file')"
            >
              <div class="d-flex align-center gap-4 mb-3">
                <VAvatar
                  size="52"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-file-upload"
                    size="28"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="d-flex align-center gap-2 flex-wrap">
                    <h6 class="text-subtitle-1 font-weight-bold mb-0">
                      {{ $t('Upload a File') }}
                    </h6>
                    <VChip
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      label
                    >
                      PDF & Office
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis">{{ $t('PDF, Word, Excel, CSV, Blueprints') }}</span>
                </div>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Upload digital PDF documents, architectural blueprints, Word agreements, spreadsheets, or technical files.') }}
              </p>
            </VCard>
          </VCol>

          <!-- 2. Take a Photo -->
          <VCol
            v-if="allowedSources.includes('photo')"
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="choice-card pa-5 cursor-pointer h-100"
              :class="{ 'choice-card--active': form.source === 'photo' }"
              tabindex="0"
              @click="setSourceChoice(form, 'photo')"
              @keydown.enter.prevent="setSourceChoice(form, 'photo')"
            >
              <div class="d-flex align-center gap-4 mb-3">
                <VAvatar
                  size="52"
                  color="warning"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-camera"
                    size="28"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="d-flex align-center gap-2 flex-wrap">
                    <h6 class="text-subtitle-1 font-weight-bold mb-0">
                      {{ $t('Take a Photo') }}
                    </h6>
                    <VChip
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      label
                    >
                      {{ $t('Live Camera') }}
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis">{{ $t('Direct Camera Capture') }}</span>
                </div>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Snap a photo directly with your camera. Ideal for equipment inspections, site areas, serial badges, or workwear items.') }}
              </p>
            </VCard>
          </VCol>

          <!-- 3. Smart Document Scanner -->
          <VCol
            v-if="allowedSources.includes('scan')"
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="choice-card pa-5 cursor-pointer h-100"
              :class="{ 'choice-card--active': form.source === 'scan' }"
              tabindex="0"
              @click="setSourceChoice(form, 'scan')"
              @keydown.enter.prevent="setSourceChoice(form, 'scan')"
            >
              <div class="d-flex align-center gap-4 mb-3">
                <VAvatar
                  size="52"
                  color="success"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-scan"
                    size="28"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="d-flex align-center gap-2 flex-wrap">
                    <h6 class="text-subtitle-1 font-weight-bold mb-0">
                      {{ $t('Scan Paper Document') }}
                    </h6>
                    <VChip
                      size="x-small"
                      color="success"
                      variant="tonal"
                      label
                    >
                      Auto-Edge AI
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis">{{ $t('Perspective Warp & Shadow Removal') }}</span>
                </div>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Automatically tracks paper corners, flattens skewed pages into crisp A4 sheets, and compiles multi-page PDFs.') }}
              </p>
            </VCard>
          </VCol>

          <!-- 4. Upload Image or Video -->
          <VCol
            v-if="allowedSources.includes('media')"
            cols="12"
            sm="6"
          >
            <VCard
              variant="outlined"
              class="choice-card pa-5 cursor-pointer h-100"
              :class="{ 'choice-card--active': form.source === 'media' }"
              tabindex="0"
              @click="setSourceChoice(form, 'media')"
              @keydown.enter.prevent="setSourceChoice(form, 'media')"
            >
              <div class="d-flex align-center gap-4 mb-3">
                <VAvatar
                  size="52"
                  color="info"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-movie"
                    size="28"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="d-flex align-center gap-2 flex-wrap">
                    <h6 class="text-subtitle-1 font-weight-bold mb-0">
                      {{ $t('Upload Image or Video') }}
                    </h6>
                    <VChip
                      size="x-small"
                      color="info"
                      variant="tonal"
                      label
                    >
                      Media
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis">{{ $t('High-Res Photos & Video Clips') }}</span>
                </div>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Upload high-resolution graphics, photos, or video recordings (MP4, WebM, MOV) with instant in-browser playback.') }}
              </p>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 2: CAPTURE / UPLOAD / STAGING                                 -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-capture="{ form }">
      <div>
        <!-- Quick Mode Switcher Pill Bar -->
        <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
          <div class="d-flex align-center gap-2">
            <VBtnToggle
              :model-value="form.source === 'upload' ? 'file' : form.source"
              mandatory
              density="compact"
              color="primary"
              variant="outlined"
              divided
              @update:model-value="val => { if (val) setSourceChoice(form, val) }"
            >
              <VBtn
                v-if="allowedSources.includes('file')"
                value="file"
                size="small"
                prepend-icon="tabler-file-upload"
              >
                {{ $t('File') }}
              </VBtn>
              <VBtn
                v-if="allowedSources.includes('photo')"
                value="photo"
                size="small"
                prepend-icon="tabler-camera"
              >
                {{ $t('Photo') }}
              </VBtn>
              <VBtn
                v-if="allowedSources.includes('scan')"
                value="scan"
                size="small"
                prepend-icon="tabler-scan"
              >
                {{ $t('Scan') }}
              </VBtn>
              <VBtn
                v-if="allowedSources.includes('media')"
                value="media"
                size="small"
                prepend-icon="tabler-movie"
              >
                {{ $t('Media') }}
              </VBtn>
            </VBtnToggle>
          </div>

          <div
            v-if="pagesRef.length"
            class="d-flex align-center gap-2"
          >
            <VChip
              size="small"
              color="success"
              variant="tonal"
              prepend-icon="tabler-files"
            >
              {{ pagesRef.length }} {{ $t('items') }} ({{ formatBytes(totalBytesRef) }})
            </VChip>
          </div>
        </div>

        <!-- Validation / Error Alert -->
        <VAlert
          v-if="validationNotice"
          type="warning"
          variant="tonal"
          density="compact"
          closable
          class="mb-3"
          @click:close="validationNotice = ''"
        >
          {{ validationNotice }}
        </VAlert>

        <!-- Hidden Fallback Inputs -->
        <input
          ref="photoFallbackInputRef"
          type="file"
          accept="image/*"
          capture="environment"
          class="d-none"
          @change="handlePhotoFallbackSelected"
        >
        <input
          ref="importScanInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="d-none"
          @change="onImportScanFileChange"
        >
        <input
          ref="replaceInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="d-none"
          @change="onReplaceFileChange"
        >

        <!-- ────────────────────────────────────────────────────────────── -->
        <!-- SUB-VIEW 1: PERSPECTIVE CROP & ENHANCEMENT STUDIO (for Scans)   -->
        <!-- ────────────────────────────────────────────────────────────── -->
        <div
          v-if="scanSubStep === 'studio'"
          class="scan-studio-card rounded-lg border overflow-hidden mb-4"
        >
          <!-- Studio Header Toolbar -->
          <div class="studio-header d-flex align-center justify-space-between flex-wrap gap-2 px-4 py-2 bg-surface">
            <div class="d-flex align-center gap-2">
              <VBtn
                size="small"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-arrow-left"
                @click="() => exitStudioToCamera(true)"
              >
                {{ editingExistingPageIndex >= 0 ? $t('Cancel Edit') : $t('Retake') }}
              </VBtn>
              <span class="text-subtitle-2 font-weight-bold">
                {{ editingExistingPageIndex >= 0
                  ? $t('Enhance & Crop Page #{n}', { n: editingExistingPageIndex + 1 })
                  : $t('Verify Edges & Enhance Scan') }}
              </span>
            </div>

            <div class="d-flex align-center gap-2">
              <VBtnToggle
                v-model="studioViewRef"
                mandatory
                density="compact"
                size="small"
                variant="outlined"
              >
                <VBtn
                  value="crop"
                  prepend-icon="tabler-crop"
                >
                  {{ $t('Crop Edges') }}
                </VBtn>
                <VBtn
                  value="result"
                  prepend-icon="tabler-wand"
                  @click="confirmStudioCrop"
                >
                  {{ $t('Enhanced') }}
                </VBtn>
              </VBtnToggle>

              <IconBtn
                size="small"
                variant="tonal"
                :title="$t('Rotate 90°')"
                @click="rotateStudioClockwise"
              >
                <VIcon icon="tabler-rotate-clockwise" />
              </IconBtn>
            </div>
          </div>

          <!-- Studio Body: Crop Editor or Warped Result -->
          <div class="studio-body bg-black position-relative d-flex align-center justify-center py-4">
            <!-- Loading indicator -->
            <div
              v-if="studioProcessingRef"
              class="position-absolute d-flex flex-column align-center justify-center w-100 h-100 studio-loader"
              style="z-index: 10; background: rgba(0,0,0,0.6);"
            >
              <VProgressCircular
                indeterminate
                color="primary"
                size="42"
                class="mb-2"
              />
              <span class="text-caption text-white">{{ $t('Warping A4 Sheet & Enhancing...') }}</span>
            </div>

            <div
              v-if="studioViewRef === 'crop' && sourceUrlRef"
              class="w-100 position-relative"
              style="height: 420px;"
            >
              <WpScanCropEditor
                v-model="cornersRef"
                :src="sourceUrlRef"
                :ratio="sourceSizeRef.width / (sourceSizeRef.height || 1)"
              />
            </div>

            <div
              v-else-if="resultUrlRef"
              class="w-100 d-flex justify-center pa-2"
              style="max-height: 480px;"
            >
              <img
                :src="resultUrlRef"
                class="rounded elevation-3 result-preview-image"
                alt="Warped Scan Preview"
              >
            </div>
          </div>

          <!-- Studio Footer Controls -->
          <div class="studio-footer px-4 py-3 bg-surface border-t">
            <!-- Filter Pills -->
            <div
              v-if="studioViewRef === 'result'"
              class="mb-3"
            >
              <div class="text-caption font-weight-medium mb-1 text-medium-emphasis">
                {{ $t('Document Filter') }}
              </div>
              <div class="d-flex align-center gap-2 flex-wrap">
                <VBtn
                  v-for="item in filterItems"
                  :key="item.value"
                  size="small"
                  :variant="filterRef === item.value ? 'flat' : 'outlined'"
                  :color="filterRef === item.value ? 'primary' : 'default'"
                  :prepend-icon="item.icon"
                  @click="selectFilter(item.value)"
                >
                  {{ item.label }}
                </VBtn>
              </div>
            </div>

            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <div class="d-flex align-center gap-2">
                <VSelect
                  v-model="orientationRef"
                  :items="orientationItems"
                  item-title="label"
                  item-value="value"
                  density="compact"
                  hide-details
                  style="max-width: 170px;"
                  :label="$t('A4 Orientation')"
                  @update:model-value="() => { if (previewBase) clearCanvasMemory(previewBase); previewBase = null; previewKey = ''; renderStudioPreview(); }"
                />
                <VBtn
                  v-if="studioViewRef === 'crop'"
                  size="small"
                  variant="text"
                  prepend-icon="tabler-maximize"
                  @click="cornersRef = [{ x: 0.02, y: 0.02 }, { x: 0.98, y: 0.02 }, { x: 0.98, y: 0.98 }, { x: 0.02, y: 0.98 }]"
                >
                  {{ $t('wp.scanner.full_page', 'Full page') }}
                </VBtn>
              </div>

              <div class="d-flex align-center gap-2">
                <VBtn
                  v-if="studioViewRef === 'crop'"
                  color="primary"
                  variant="flat"
                  prepend-icon="tabler-check"
                  @click="confirmStudioCrop"
                >
                  {{ $t('Apply Crop') }}
                </VBtn>
                <VBtn
                  v-else
                  color="success"
                  variant="flat"
                  prepend-icon="tabler-check"
                  :loading="studioProcessingRef"
                  @click="commitStudioPage"
                >
                  {{ editingExistingPageIndex >= 0 ? $t('Save Changes') : $t('Add Page to Document') }}
                </VBtn>
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────── -->
        <!-- SUB-VIEW 2 & 3: UNIFIED LIVE CAMERA (Direct Photo & A4 Scanner) -->
        <!-- ────────────────────────────────────────────────────────────── -->
        <div
          v-else-if="form.source === 'photo' || form.source === 'scan'"
          class="photo-camera-card rounded-lg border overflow-hidden mb-4 bg-black"
        >
          <div
            ref="stageRef"
            class="camera-stage position-relative d-flex align-center justify-center overflow-hidden"
            style="min-height: 380px; max-height: 480px;"
          >
            <!-- Always-Mounted Video Element so getUserMedia stream binds reliably -->
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="camera-video-feed"
              :class="{ 'camera-video-feed--mirrored': form.source === 'photo' && isUserCamera }"
              :style="frameStyle"
              @loadedmetadata="layoutStage"
              @resize="layoutStage"
            />

            <!-- Shutter Flash Overlay -->
            <div
              v-if="isShutterFlashing"
              class="shutter-flash-overlay"
            />

            <!-- Framing Grid Overlay (Photo Mode) -->
            <div
              v-if="form.source === 'photo' && isCameraActive && !cameraError"
              class="camera-grid-overlay pointer-events-none"
            >
              <div class="grid-line grid-line--h1" />
              <div class="grid-line grid-line--h2" />
              <div class="grid-line grid-line--v1" />
              <div class="grid-line grid-line--v2" />
            </div>

            <!-- SVG Edge Guide & Detected Polygon (Scan Mode) -->
            <svg
              v-if="form.source === 'scan' && isCameraActive && !cameraError"
              class="position-absolute w-100 h-100 pointer-events-none"
              viewBox="0 0 100 100"
              preserveAspectRatio="none"
            >
              <path
                :d="liveCornersRef ? liveMaskRef : guideMaskRef"
                fill="rgba(0, 0, 0, 0.42)"
                fill-rule="evenodd"
              />
              <polygon
                v-if="livePolygonRef"
                :points="livePolygonRef"
                fill="rgba(40, 199, 111, 0.25)"
                stroke="rgb(40, 199, 111)"
                stroke-width="0.7"
              />
              <template v-if="liveCornersRef">
                <circle
                  v-for="(pt, cIdx) in liveCornersRef"
                  :key="cIdx"
                  :cx="pt.x * 100"
                  :cy="pt.y * 100"
                  r="1.3"
                  fill="#fff"
                  stroke="rgb(40, 199, 111)"
                  stroke-width="0.5"
                />
              </template>
              <rect
                v-else-if="guideRectRef"
                :x="guideRectRef.x"
                :y="guideRectRef.y"
                :width="guideRectRef.width"
                :height="guideRectRef.height"
                fill="none"
                stroke="rgba(255, 255, 255, 0.55)"
                stroke-width="0.4"
                stroke-dasharray="2,2"
              />
            </svg>

            <!-- Top Controls Overlay -->
            <div
              v-if="!cameraError"
              class="camera-controls-top d-flex align-center justify-space-between w-100 px-4 py-2"
            >
              <VChip
                v-if="form.source === 'photo'"
                size="small"
                color="warning"
                variant="elevated"
              >
                <VIcon
                  start
                  icon="tabler-camera"
                  size="14"
                />
                {{ $t('High-Resolution Photo') }}
              </VChip>
              <VChip
                v-else
                size="small"
                :color="scannerStatusRef.color"
                variant="elevated"
              >
                <VIcon
                  start
                  :icon="scannerStatusRef.icon"
                  :class="{ 'status-spin': scannerStatusRef.spin }"
                  size="14"
                />
                {{ scannerStatusRef.text }}
              </VChip>

              <div class="d-flex align-center gap-2">
                <VBtn
                  v-if="form.source === 'scan' && !engineFailedRef"
                  size="x-small"
                  :color="autoCaptureRef ? 'success' : 'secondary'"
                  variant="elevated"
                  prepend-icon="tabler-sparkles"
                  @click="autoCaptureRef = !autoCaptureRef"
                >
                  {{ autoCaptureRef ? $t('Auto-Snap: ON') : $t('Auto-Snap: OFF') }}
                </VBtn>

                <VBtn
                  v-if="zoomSupported"
                  size="x-small"
                  variant="tonal"
                  class="bg-surface"
                  @click="cycleCameraZoom"
                >
                  {{ zoomLabelRef }}
                </VBtn>

                <IconBtn
                  v-if="torchSupported"
                  size="small"
                  variant="tonal"
                  class="bg-surface"
                  :color="torchEnabled ? 'warning' : 'default'"
                  :title="torchEnabled ? $t('wp.camera.torch_off') : $t('wp.camera.torch_on')"
                  @click="() => toggleTorch()"
                >
                  <VIcon :icon="torchEnabled ? 'tabler-bolt' : 'tabler-bolt-off'" />
                </IconBtn>

                <VMenu
                  v-if="hasRealMultipleCameras"
                  v-model="cameraMenuOpenRef"
                  location="bottom end"
                >
                  <template #activator="{ props: menuProps }">
                    <IconBtn
                      v-bind="menuProps"
                      size="small"
                      variant="tonal"
                      class="bg-surface"
                      :disabled="isCameraLoading"
                      :title="$t('wp.camera.select_device')"
                    >
                      <VIcon icon="tabler-video" />
                    </IconBtn>
                  </template>
                  <VList density="compact" min-width="210">
                    <VListSubheader>{{ $t('wp.camera.select_device') }}</VListSubheader>
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
                        {{ cam.label || `${$t('wp.camera.device')} #${idx + 1}` }}
                      </VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>

                <IconBtn
                  v-if="hasMultipleCameras"
                  size="small"
                  variant="tonal"
                  class="bg-surface"
                  :disabled="isCameraLoading"
                  :title="$t('wp.camera.switch')"
                  @click="() => switchCamera()"
                >
                  <VIcon icon="tabler-camera-rotate" />
                </IconBtn>

                <IconBtn
                  size="small"
                  variant="tonal"
                  class="bg-surface"
                  :title="form.source === 'scan' ? $t('Import Document Image') : $t('Select Photo from Device')"
                  @click="form.source === 'scan' ? importScanInputRef?.click() : photoFallbackInputRef?.click()"
                >
                  <VIcon icon="tabler-photo-up" />
                </IconBtn>
              </div>
            </div>

            <!-- Bottom Shutter Bar -->
            <div
              v-if="isCameraActive && !cameraError"
              class="camera-controls-bottom d-flex align-center justify-center w-100 py-3"
            >
              <button
                v-if="form.source === 'photo'"
                type="button"
                class="photo-shutter-btn d-flex align-center justify-center"
                :disabled="capturingRef || isCameraLoading"
                :aria-label="$t('Take Photo')"
                @click="capturePhotoDirectly"
              >
                <div class="photo-shutter-inner">
                  <VIcon
                    icon="tabler-camera"
                    size="28"
                    color="white"
                  />
                </div>
              </button>

              <button
                v-else
                type="button"
                class="scan-shutter-btn d-flex align-center justify-center"
                :style="shutterRingStyleRef"
                :disabled="capturingRef || isCameraLoading"
                :aria-label="$t('Scan Page')"
                @click="() => captureAndOpenStudio(-1)"
              >
                <div class="scan-shutter-inner">
                  <VIcon
                    icon="tabler-scan"
                    size="28"
                    color="white"
                  />
                </div>
              </button>
            </div>

            <!-- Camera Loading Overlay -->
            <div
              v-if="isCameraLoading"
              class="camera-state-overlay d-flex flex-column align-center justify-center py-10"
            >
              <VProgressCircular
                indeterminate
                :color="form.source === 'scan' ? 'success' : 'warning'"
                size="44"
                class="mb-3"
              />
              <span class="text-caption text-white">
                {{ form.source === 'scan' ? $t('Initializing AI edge detector...') : $t('Activating camera feed...') }}
              </span>
            </div>

            <!-- Camera Error / Blocked Overlay -->
            <div
              v-else-if="cameraError"
              class="camera-state-overlay d-flex flex-column align-center justify-center text-center pa-6 text-white"
            >
              <VIcon
                icon="tabler-camera-off"
                size="44"
                :color="form.source === 'scan' ? 'error' : 'warning'"
                class="mb-3"
              />
              <div class="text-subtitle-1 font-weight-bold mb-1">
                {{ $t('Camera Access Unavailable') }}
              </div>
              <p
                class="text-caption text-medium-emphasis mb-4"
                style="max-width: 380px;"
              >
                {{ $t('Camera permission was denied or no camera device is connected. You can retry camera access or select an image directly from your device.') }}
              </p>
              <div class="d-flex align-center justify-center gap-2 flex-wrap">
                <VBtn
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-refresh"
                  @click="handleRetryCamera"
                >
                  {{ $t('wp.camera.retry', 'Try again') }}
                </VBtn>
                <VBtn
                  :color="form.source === 'scan' ? 'success' : 'warning'"
                  variant="flat"
                  :prepend-icon="form.source === 'scan' ? 'tabler-photo-scan' : 'tabler-photo'"
                  @click="form.source === 'scan' ? importScanInputRef?.click() : photoFallbackInputRef?.click()"
                >
                  {{ form.source === 'scan' ? $t('Import Document Image') : $t('Select Photo from Device') }}
                </VBtn>
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────── -->
        <!-- SUB-VIEW 4: FILE / MEDIA UPLOAD DROPZONE                       -->
        <!-- ────────────────────────────────────────────────────────────── -->
        <div
          v-else
          class="upload-capture-step"
        >
          <div
            class="upload-dropzone pa-7 text-center rounded-lg border-dashed cursor-pointer mb-4"
            :class="{ 'upload-dropzone--active': isDragging }"
            @click="form.source === 'media' ? mediaInputRef?.click() : fileInputRef?.click()"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
          >
            <!-- File Input for standard documents -->
            <input
              ref="fileInputRef"
              type="file"
              :accept="accept || ATTACHMENT_ALL_ACCEPT"
              :multiple="multiple"
              class="d-none"
              @change="onFileInputChange"
            >

            <!-- File Input for media (image / video) -->
            <input
              ref="mediaInputRef"
              type="file"
              :accept="accept || ATTACHMENT_MEDIA_ACCEPT"
              :multiple="multiple"
              class="d-none"
              @change="handleMediaUploadSelected"
            >

            <VAvatar
              size="60"
              :color="form.source === 'media' ? 'info' : 'primary'"
              variant="tonal"
              class="mb-3"
            >
              <VIcon
                :icon="form.source === 'media' ? 'tabler-movie' : 'tabler-cloud-upload'"
                size="30"
              />
            </VAvatar>

            <h6 class="text-subtitle-1 font-weight-bold mb-1">
              {{ form.source === 'media'
                ? $t('Click to upload photos or videos, or drag & drop here')
                : $t('Click to upload documents or drag & drop here') }}
            </h6>
            <p class="text-caption text-medium-emphasis mb-3">
              {{ form.source === 'media'
                ? $t('Supports JPG, PNG, WebP, SVG, MP4, WebM, MOV up to {size}MB.', { size: maxSizeMb })
                : $t('Supports PDF, Word, Excel, CSV, text, and technical blueprints up to {size}MB.', { size: maxSizeMb }) }}
            </p>

            <div class="d-flex justify-center gap-2 flex-wrap">
              <VBtn
                size="small"
                :color="form.source === 'media' ? 'info' : 'primary'"
                :prepend-icon="form.source === 'media' ? 'tabler-movie' : 'tabler-file-plus'"
                @click.stop="form.source === 'media' ? mediaInputRef?.click() : fileInputRef?.click()"
              >
                {{ form.source === 'media' ? $t('Select Images / Videos') : $t('Select Files') }}
              </VBtn>

              <VBtn
                v-if="allowedSources.includes('photo')"
                size="small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-camera"
                @click.stop="setSourceChoice(form, 'photo')"
              >
                {{ $t('Switch to Camera') }}
              </VBtn>

              <VBtn
                v-if="allowedSources.includes('scan')"
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-scan"
                @click.stop="setSourceChoice(form, 'scan')"
              >
                {{ $t('Switch to Scanner') }}
              </VBtn>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────── -->
        <!-- STAGED ITEMS ORGANIZER (Preview, Reorder, Delete)              -->
        <!-- ────────────────────────────────────────────────────────────── -->
        <div
          v-if="pagesRef.length"
          class="staged-items-container mt-4 pt-3 border-t"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <span class="text-subtitle-2 font-weight-bold d-flex align-center gap-2">
              <VIcon
                icon="tabler-layers-intersect"
                size="18"
                color="primary"
              />
              {{ $t('Staged Attachments ({n})', { n: pagesRef.length }) }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ $t('Drag cards to reorder. Click eye to inspect.') }}
            </span>
          </div>

          <VRow dense>
            <VCol
              v-for="(page, idx) in pagesRef"
              :key="page.id || idx"
              cols="12"
              sm="6"
              md="4"
            >
              <VCard
                variant="outlined"
                class="staged-item-card pa-3 position-relative h-100"
              >
                <div class="d-flex align-center gap-3">
                  <!-- Thumbnail / Icon -->
                  <div
                    class="item-thumb-wrapper rounded overflow-hidden cursor-pointer d-flex align-center justify-center bg-surface-variant flex-shrink-0"
                    style="width: 58px; height: 58px;"
                    @click="previewPageItem(page)"
                  >
                    <!-- Video tag if video -->
                    <video
                      v-if="resolveFileKind(resolveFileMime(page.file), page.file?.name) === 'video'"
                      :src="page.preview"
                      class="w-100 h-100 object-cover"
                      muted
                      preload="metadata"
                    />
                    <!-- Image if preview exists -->
                    <img
                      v-else-if="page.preview"
                      :src="page.preview"
                      class="w-100 h-100 object-cover"
                      alt="Thumbnail"
                    >
                    <!-- Fallback semantic icon -->
                    <VIcon
                      v-else
                      :icon="resolveFileIcon(resolveFileMime(page.file), page.file?.name)"
                      :color="resolveFileColor(resolveFileMime(page.file), page.file?.name)"
                      size="28"
                    />
                  </div>

                  <!-- Details -->
                  <div class="min-w-0 flex-grow-1">
                    <div
                      class="text-body-2 font-weight-medium text-truncate mb-1"
                      :title="page.file?.name"
                    >
                      {{ page.file?.name || `${$t('Item')} #${idx + 1}` }}
                    </div>
                    <div class="d-flex align-center gap-1 flex-wrap">
                      <VChip
                        size="x-small"
                        :color="page.source === 'camera_scan' ? 'success' : (page.source === 'camera_photo' ? 'warning' : 'primary')"
                        variant="tonal"
                        label
                      >
                        {{ page.source === 'camera_scan' ? $t('Scan') : (page.source === 'camera_photo' ? $t('Photo') : (resolveFileKind(resolveFileMime(page.file), page.file?.name) === 'video' ? $t('Video') : $t('File'))) }}
                      </VChip>
                      <span class="text-caption text-disabled">
                        {{ page.file?.size ? formatBytes(page.file.size) : '' }}
                      </span>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="d-flex flex-column align-center gap-1">
                    <IconBtn
                      size="x-small"
                      variant="text"
                      :title="$t('Preview')"
                      @click="previewPageItem(page)"
                    >
                      <VIcon
                        icon="tabler-eye"
                        size="16"
                      />
                    </IconBtn>

                    <IconBtn
                      v-if="resolveFileKind(resolveFileMime(page.file)) === 'image' || page.source === 'camera_scan'"
                      size="x-small"
                      variant="text"
                      :title="$t('Rotate 90°')"
                      @click="rotatePage(idx)"
                    >
                      <VIcon
                        icon="tabler-rotate-clockwise"
                        size="16"
                      />
                    </IconBtn>

                    <IconBtn
                      size="x-small"
                      variant="text"
                      color="error"
                      :title="$t('Remove')"
                      @click="removePage(idx)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="16"
                      />
                    </IconBtn>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </div>
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 3: DETAILS & METADATA                                         -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-details>
      <div class="py-2">
        <h5 class="text-h6 font-weight-bold mb-1">
          {{ $t('Attachment Classification & Indexing') }}
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-5">
          {{ $t('Add standard metadata to ensure fast searching, indexing, and compliance tracking.') }}
        </p>

        <VRow dense>
          <!-- Title / Name -->
          <VCol
            v-if="hasField('title') || hasField('name')"
            cols="12"
            md="8"
          >
            <VTextField
              v-model="formMeta.title"
              :label="$t('Attachment Name / Title') + ' *'"
              :placeholder="$t('e.g. Workshop Inspection Photo, Technical Permit 2026')"
              density="comfortable"
              prepend-inner-icon="tabler-heading"
            />
          </VCol>

          <!-- Type / Category -->
          <VCol
            v-if="hasField('type') || hasField('category')"
            cols="12"
            md="4"
          >
            <VSelect
              v-model="formMeta.type"
              :items="categoriesList"
              item-title="title"
              item-value="value"
              :label="$t('Category / Type') + ' *'"
              density="comfortable"
              prepend-inner-icon="tabler-category"
            />
          </VCol>

          <!-- Reference Number -->
          <VCol
            v-if="hasField('reference') || hasField('reference_number')"
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="formMeta.reference_number"
              :label="$t('Reference / Document No.')"
              :placeholder="$t('e.g. DOC-2026-0042')"
              density="comfortable"
              prepend-inner-icon="tabler-hash"
            />
          </VCol>

          <!-- Issuer -->
          <VCol
            v-if="hasField('issuer')"
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="formMeta.issuer"
              :label="$t('Issuing Authority / Vendor')"
              :placeholder="$t('e.g. Bureau Veritas, Ministry of Industry')"
              density="comfortable"
              prepend-inner-icon="tabler-building"
            />
          </VCol>

          <!-- Status -->
          <VCol
            v-if="hasField('status')"
            cols="12"
            sm="6"
            md="4"
          >
            <VSelect
              v-model="formMeta.status"
              :items="[
                { value: 'active', title: $t('Active / Valid') },
                { value: 'draft', title: $t('Draft') },
                { value: 'under_review', title: $t('Under Review') },
                { value: 'pending_renewal', title: $t('Pending Renewal') },
                { value: 'expired', title: $t('Expired') },
              ]"
              item-title="title"
              item-value="value"
              :label="$t('Status')"
              density="comfortable"
              prepend-inner-icon="tabler-circle-check"
            />
          </VCol>

          <!-- Date / Issue Date -->
          <VCol
            v-if="hasField('issue_date') || hasField('date')"
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="formMeta.issue_date"
              type="date"
              :label="$t('Issue Date / Date')"
              density="comfortable"
              prepend-inner-icon="tabler-calendar"
            />
          </VCol>

          <!-- Expiry Date -->
          <VCol
            v-if="hasField('expiration_date') || hasField('expiry_date')"
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="formMeta.expiration_date"
              type="date"
              :label="$t('Expiration / Expiry Date')"
              density="comfortable"
              prepend-inner-icon="tabler-calendar-due"
              :min="formMeta.issue_date || undefined"
            />
          </VCol>

          <!-- Auto-renewable -->
          <VCol
            v-if="hasField('auto_renewable')"
            cols="12"
            sm="6"
            md="4"
            class="d-flex align-center"
          >
            <VCheckbox
              v-model="formMeta.auto_renewable"
              :label="$t('Auto-renewable contract/policy')"
              density="compact"
              hide-details
            />
          </VCol>

          <!-- Notes / Description -->
          <VCol
            v-if="hasField('notes') || hasField('description')"
            cols="12"
          >
            <VTextarea
              v-model="formMeta.notes"
              :label="$t('Description & Internal Notes')"
              :placeholder="$t('Add any technical notes, inspection observations, or special instructions...')"
              rows="3"
              density="comfortable"
              prepend-inner-icon="tabler-note"
            />
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- REVIEW STEP                                                        -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #review>
      <div class="py-2">
        <h5 class="text-h6 font-weight-bold mb-1">
          {{ $t('Review Attachment Before Saving') }}
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-5">
          {{ $t('Verify the classification, metadata, and attached file.') }}
        </p>

        <VCard
          variant="outlined"
          class="pa-5 mb-4"
        >
          <div class="d-flex align-center gap-4 mb-4">
            <VAvatar
              size="56"
              color="primary"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-paperclip"
                size="30"
              />
            </VAvatar>

            <div class="min-w-0 flex-grow-1">
              <h6 class="text-h6 font-weight-bold mb-1 text-truncate">
                {{ formMeta.title || existingFileName }}
              </h6>
              <div class="d-flex align-center gap-2 flex-wrap">
                <VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ formMeta.type }}
                </VChip>
                <span class="text-caption text-medium-emphasis">
                  <template v-if="pagesRef.length">
                    {{ pagesRef.length }} {{ $t('files/pages') }} &bull; {{ formatBytes(totalBytesRef) }}
                  </template>
                  <template v-else-if="isEditMode">
                    {{ existingFileName }}
                  </template>
                </span>
              </div>
            </div>
          </div>

          <VDivider class="mb-4" />

          <!-- Metadata Summary Grid -->
          <VRow
            v-if="formMeta.reference_number || formMeta.issuer || formMeta.issue_date || formMeta.expiration_date"
            dense
            class="mb-3"
          >
            <VCol
              v-if="formMeta.reference_number"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                {{ $t('Reference') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ formMeta.reference_number }}
              </div>
            </VCol>
            <VCol
              v-if="formMeta.issuer"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                {{ $t('Issuer') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ formMeta.issuer }}
              </div>
            </VCol>
            <VCol
              v-if="formMeta.issue_date"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                {{ $t('Date') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ formMeta.issue_date }}
              </div>
            </VCol>
            <VCol
              v-if="formMeta.expiration_date"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                {{ $t('Expiry') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ formMeta.expiration_date }}
              </div>
            </VCol>
          </VRow>

          <div
            v-if="formMeta.notes"
            class="text-body-2 bg-surface pa-3 rounded text-medium-emphasis"
          >
            {{ formMeta.notes }}
          </div>
        </VCard>
      </div>
    </template>
  </WpWizardDialog>

  <!-- In-app Media Viewer Dialog for inspection -->
  <WpMediaViewerDialog
    v-model="isPreviewViewerOpen"
    :item="previewViewerItem"
  />
</template>

<style scoped>
.choice-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.18s ease;
}

.choice-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.choice-card--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  border-width: 2px !important;
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

.upload-dropzone {
  border-color: rgba(var(--v-border-color), 0.8);
  background-color: rgba(var(--v-theme-surface), 0.5);
  transition: all 0.18s ease;
}

.upload-dropzone:hover,
.upload-dropzone--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.camera-stage {
  background: #000;
}

.camera-state-overlay {
  position: absolute;
  inset: 0;
  z-index: 6;
  background: rgba(0, 0, 0, 0.82);
}

.camera-video-feed {
  object-fit: cover;
  max-width: 100%;
  max-height: 100%;
}

.camera-video-feed--mirrored {
  transform: scaleX(-1);
}

.camera-controls-top {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 5;
  background: linear-gradient(180deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0) 100%);
}

.camera-controls-bottom {
  position: absolute;
  bottom: 0;
  left: 0;
  z-index: 5;
  background: linear-gradient(0deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0) 100%);
}

.shutter-flash-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: #fff;
  z-index: 8;
  pointer-events: none;
  animation: flashAnim 0.18s ease-out forwards;
}

@keyframes flashAnim {
  0% { opacity: 0.9; }
  100% { opacity: 0; }
}

.camera-grid-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
}

.grid-line {
  position: absolute;
  background: rgba(255, 255, 255, 0.15);
}

.grid-line--h1 { top: 33.33%; left: 0; width: 100%; height: 1px; }
.grid-line--h2 { top: 66.66%; left: 0; width: 100%; height: 1px; }
.grid-line--v1 { left: 33.33%; top: 0; width: 1px; height: 100%; }
.grid-line--v2 { left: 66.66%; top: 0; width: 1px; height: 100%; }

.photo-shutter-btn,
.scan-shutter-btn {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  border: 4px solid rgba(255, 255, 255, 0.85);
  background: rgba(255, 255, 255, 0.2);
  cursor: pointer;
  transition: transform 0.12s ease;
}

.photo-shutter-btn:active,
.scan-shutter-btn:active {
  transform: scale(0.92);
}

.photo-shutter-inner {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgb(var(--v-theme-warning));
  display: flex;
  align-items: center;
  justify-content: center;
}

.scan-shutter-inner {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgb(var(--v-theme-success));
  display: flex;
  align-items: center;
  justify-content: center;
}

.result-preview-image {
  max-width: 100%;
  max-height: 440px;
  object-fit: contain;
}

.staged-item-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  transition: all 0.15s ease;
}

.staged-item-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.object-cover {
  object-fit: cover;
}

.pointer-events-none {
  pointer-events: none;
}

.status-spin {
  animation: spin 1.2s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}
</style>
