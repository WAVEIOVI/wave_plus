<script setup>
import WpScanCropEditor from '@/components/shared/documents/WpScanCropEditor.vue'
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
import { localiseError } from '@/composables/documents/documentFiles'
import { useErrorI18n } from '@/composables/ui/useErrorI18n'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Full-screen document scanner.
 *  1. Camera step: live edge detection, A4 guide, optional auto-capture.
 *     Camera unavailable? Any photo can be imported and goes through the same review.
 *  2. Review step: crop (4 corners + edges), A4 orientation, rotate, colour
 *     filters with live thumbnails, brightness / contrast.
 *
 * Each confirmed page is emitted as `scanned`; the parent owns the pages.
 * With `multiple` (default) the scanner STAYS OPEN after each page so a whole
 * document can be captured in one go; the user ends the session with "Done".
 * With `:multiple="false"` it closes after the first confirmed page.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  multiple: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'scanned', 'error', 'done'])

const COOLDOWN_MS = 1500
const MAX_CAPTURE_SIDE = 1920
const ENGINE_WAIT_MS = 6000
const ADJUST_DEBOUNCE_MS = 120
const AUTO_CAPTURE_KEY = 'wp.scanner.autoCapture'

const { t } = useI18n()
const { errorMessage } = useErrorI18n()

const {
  videoRef,
  isActiveRef,
  isLoadingRef,
  errorRef,
  hasMultipleCameras,
  hasRealMultipleCameras,
  selectableCamerasRef,
  currentCameraIdRef,
  torchSupportedRef,
  torchEnabledRef,
  toggleTorch,
  zoomSupportedRef,
  zoomRef,
  zoomRangeRef,
  setZoom,
  selectCamera,
  startCamera,
  stopCamera,
  switchCamera,
  liveCornersRef,
  stableFramesRef: stableRef,
  STABLE_REQUIRED: STABLE_FRAMES,
  startDetectionLoop,
  stopDetectionLoop,
  resetDetectionState,
  CAMERA_FACING_MODES,
} = useDocumentScanner({ idealWidth: 1920, idealHeight: 1080 })

const {
  readyRef: engineReadyRef,
  loadingRef: engineLoadingRef,
  loadOpenCv,
  getCv,
} = useOpenCv()

function localisedError(key, fallback) {
  const error = new Error(t(key, fallback))

  error.i18nKey = key

  return error
}

/**
 * Tell the parent AND the user. Camera problems already have a recovery panel
 * on the stage; everything else (export failed, engine unavailable...) would be
 * invisible behind the full-screen scanner, so it gets a toast.
 */
function reportError(error) {
  emit('error', error)
  if (!errorRef.value) noticeRef.value = localiseError(error, t, t('wp.scanner.error_generic', 'Something went wrong. Please try again.'))
}

function readAutoCapturePreference() {
  try {
    const stored = localStorage.getItem(AUTO_CAPTURE_KEY)

    return stored === null ? true : stored === '1'
  } catch {
    return true // storage blocked (private mode): fall back to the default
  }
}

// ---- state ----------------------------------------------------------------
const stepRef = ref('camera') // 'camera' | 'review'
const stageRef = ref(null)
const importInputRef = ref(null)
const frameStyle = ref({})
const frameRatioRef = ref(1)
const cameraFacingModeRef = ref(CAMERA_FACING_MODES.ENVIRONMENT)
const engineFailedRef = ref(false)
const capturingRef = ref(false)
const flashRef = ref(false)
const noticeRef = ref('')

const orientationRef = ref(ORIENTATION.AUTO)
const filterRef = ref(SCAN_FILTER.ENHANCED)
const autoCaptureRef = ref(readAutoCapturePreference())
const adjustRef = ref({ ...DEFAULT_ADJUST })
const tuneOpenRef = ref(false)
const cameraMenuOpenRef = ref(false)

const sessionCountRef = ref(0)
const lastThumbRef = ref(null)

const viewRef = ref('result') // 'crop' | 'result'
const cornersRef = ref(DEFAULT_CORNERS.map(point => ({ ...point })))
const cornersDetectedRef = ref(false)
const rotationRef = ref(0)
const sourceUrlRef = ref(null)
const sourceSizeRef = ref({ width: 1, height: 1 })
const resultUrlRef = ref(null)
const filterThumbsRef = ref({})
const processingRef = ref(false)

// Heavy / non-reactive objects live outside Vue's reactivity on purpose.
let sourceCanvas = null
let previewBase = null
let previewKey = ''
let thumbsKey = ''
let renderToken = 0
let cooldownUntil = 0
let adjustTimer = 0
let resizeObserver = null

const filterItems = [
  { value: SCAN_FILTER.ENHANCED, label: 'wp.scanner.filter_enhanced', fallback: 'Enhanced', icon: 'tabler-wand' },
  { value: SCAN_FILTER.ORIGINAL, label: 'wp.scanner.filter_original', fallback: 'Original', icon: 'tabler-photo' },
  { value: SCAN_FILTER.GRAYSCALE, label: 'wp.scanner.filter_grayscale', fallback: 'Grayscale', icon: 'tabler-contrast-2' },
  { value: SCAN_FILTER.BLACK_WHITE, label: 'wp.scanner.filter_bw', fallback: 'Black & white', icon: 'tabler-contrast' },
]

// ---- derived --------------------------------------------------------------
/** Output shape: manual choice, or guessed from the detected sheet. */
const effectiveOrientationRef = computed(() => {
  if (orientationRef.value !== ORIENTATION.AUTO) return orientationRef.value

  const { width, height } = sourceSizeRef.value
  const base = suggestOrientation(cornersRef.value, width, height)

  if (rotationRef.value % 2 === 0) return base

  return base === ORIENTATION.LANDSCAPE ? ORIENTATION.PORTRAIT : ORIENTATION.LANDSCAPE
})

/** A4 guide drawn when no sheet is detected, in % of the video frame. */
const guideRectRef = computed(() => {
  const ratio = frameRatioRef.value || 1
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

const livePolygonRef = computed(() => (liveCornersRef.value || []).map(point => `${point.x * 100},${point.y * 100}`).join(' '))

/** Dim everything outside the guide / detected sheet (evenodd cutout). */
const guideMaskRef = computed(() => {
  const rect = guideRectRef.value

  return `M0,0H100V100H0Z M${rect.x},${rect.y} H${rect.x + rect.width} V${rect.y + rect.height} H${rect.x} Z`
})

const liveMaskRef = computed(() => {
  if (!liveCornersRef.value) return null

  const [first, ...rest] = liveCornersRef.value.map(point => `${point.x * 100},${point.y * 100}`)

  return `M0,0H100V100H0Z M${first} ${rest.map(point => `L${point}`).join(' ')} Z`
})

/** 0..1 progress of the "hold steady" phase, drives the shutter ring. */
const stableProgressRef = computed(() => Math.min(1, stableRef.value / STABLE_FRAMES))

const shutterRingStyleRef = computed(() => {
  if (!autoCaptureRef.value || !liveCornersRef.value || capturingRef.value) return {}

  const degrees = Math.round(stableProgressRef.value * 360)

  return {
    background: `conic-gradient(rgb(var(--v-theme-success)) ${degrees}deg, rgba(255, 255, 255, 25%) ${degrees}deg)`,
  }
})

const statusRef = computed(() => {
  if (engineFailedRef.value) return { key: 'wp.scanner.status_manual', fallback: 'Manual mode: tap the shutter', color: 'warning', icon: 'tabler-hand-move', spin: false }
  if (!engineReadyRef.value || engineLoadingRef.value) return { key: 'wp.scanner.status_loading', fallback: 'Preparing scanner…', color: 'secondary', icon: 'tabler-loader-2', spin: true }
  if (liveCornersRef.value) {
    return autoCaptureRef.value
      ? { key: 'wp.scanner.status_hold_steady', fallback: 'Hold steady…', color: 'success', icon: 'tabler-scan', spin: false }
      : { key: 'wp.scanner.status_detected', fallback: 'Page detected', color: 'success', icon: 'tabler-scan', spin: false }
  }

  return { key: 'wp.scanner.status_searching', fallback: 'Looking for a page…', color: 'secondary', icon: 'tabler-search', spin: false }
})

const showSearchHintRef = computed(() => {
  return isActiveRef.value && !errorRef.value && engineReadyRef.value && !liveCornersRef.value
})

const canCaptureRef = computed(() => isActiveRef.value && !isLoadingRef.value && !capturingRef.value)

const zoomLabelRef = computed(() => `${Number(zoomRef.value.toFixed(1))}×`)

const isAdjustedRef = computed(() => adjustRef.value.brightness !== 0 || adjustRef.value.contrast !== 0)

const confirmLabelRef = computed(() => (props.multiple
  ? t('wp.scanner.add_page', 'Add page')
  : t('wp.scanner.use_scan', 'Use scan')))

// ---- stage layout (video + overlay share one box with the video's ratio) ----
function layoutStage() {
  const box = stageRef.value
  const video = videoRef.value

  if (!box || !video?.videoWidth) return

  const ratio = video.videoWidth / video.videoHeight

  frameRatioRef.value = ratio

  let width = box.clientWidth
  let height = width / ratio

  if (height > box.clientHeight) {
    height = box.clientHeight
    width = height * ratio
  }

  frameStyle.value = { width: `${Math.floor(width)}px`, height: `${Math.floor(height)}px` }
}

// ---- live detection (delegated to canonical useDocumentScanner loop) --------
function resetLive() {
  resetDetectionState()
}

function startLoop() {
  startDetectionLoop({
    getCv,
    isBlocked: () => stepRef.value !== 'camera' || capturingRef.value,
    isAutoCapture: () => autoCaptureRef.value && Date.now() > cooldownUntil,
    onAutoCapture: () => {
      if (canCaptureRef.value) capture()
    },
  })
}

function stopLoop() {
  stopDetectionLoop()
}

// ---- capture ----------------------------------------------------------------
function grabFrame() {
  const video = videoRef.value
  const scale = Math.min(1, MAX_CAPTURE_SIDE / Math.max(video.videoWidth, video.videoHeight))
  const canvas = document.createElement('canvas')

  canvas.width = Math.round(video.videoWidth * scale)
  canvas.height = Math.round(video.videoHeight * scale)
  canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)

  return canvas
}

function flash() {
  flashRef.value = true
  navigator.vibrate?.(25)
  setTimeout(() => {
    flashRef.value = false
  }, 200)
}

function clearCanvasMemory(canvas) {
  if (!canvas) return
  canvas.width = 0
  canvas.height = 0
}

/** Small, self-owned thumbnail: the emitted preview URL belongs to the parent (it revokes it on delete). */
function makeThumb(canvas) {
  const scaled = scaleCanvas(canvas, 160)
  const url = scaled.toDataURL('image/jpeg', 0.7)

  if (scaled !== canvas) clearCanvasMemory(scaled)

  return url
}

function releaseReview() {
  if (sourceUrlRef.value) URL.revokeObjectURL(sourceUrlRef.value)
  sourceUrlRef.value = null
  resultUrlRef.value = null
  filterThumbsRef.value = {}
  clearCanvasMemory(sourceCanvas)
  clearCanvasMemory(previewBase)
  sourceCanvas = null
  previewBase = null
  previewKey = ''
  thumbsKey = ''
  renderToken++
  clearTimeout(adjustTimer)
}

/** Wait (bounded) for OpenCV so an early tap on the shutter still gets cropping. */
async function ensureEngine() {
  if (getCv()) return true
  if (engineFailedRef.value) return false

  try {
    await Promise.race([
      loadOpenCv(),
      new Promise((_resolve, reject) => setTimeout(() => reject(new Error('engine wait')), ENGINE_WAIT_MS)),
    ])
  } catch {
    return false
  }

  return Boolean(getCv())
}

function finishPage() {
  sessionCountRef.value++
  if (!props.multiple) closeScanner()
}

async function emitRawFrame(frame) {
  try {
    const blob = await canvasToBlob(frame, 0.9)
    const file = new File([blob], `scan-${Date.now()}.jpg`, { type: 'image/jpeg' })
    const preview = URL.createObjectURL(file)

    emit('scanned', { file, preview, width: frame.width, height: frame.height, pageCount: 1, format: null, orientation: null })
    lastThumbRef.value = makeThumb(frame)
    finishPage()
  } finally {
    clearCanvasMemory(frame)
  }
}

async function enterReview(frame, detected) {
  stopLoop()
  releaseReview()
  sourceCanvas = frame
  sourceSizeRef.value = { width: frame.width, height: frame.height }
  cornersRef.value = (detected || DEFAULT_CORNERS).map(point => ({ ...point }))
  cornersDetectedRef.value = !!detected
  rotationRef.value = 0
  adjustRef.value = { ...DEFAULT_ADJUST }
  viewRef.value = detected ? 'result' : 'crop'
  sourceUrlRef.value = URL.createObjectURL(await canvasToBlob(frame, 0.85))
  stepRef.value = 'review'

  if (viewRef.value === 'result') await renderPreview()
}

async function processFrame(frame, detectedHint = null) {
  stopLoop()

  const hasEngine = await ensureEngine()
  const cv = hasEngine ? getCv() : null

  if (!cv) {
    await emitRawFrame(frame)

    return
  }

  await enterReview(frame, detectDocumentCorners(cv, frame) || detectedHint)
}

async function capture() {
  const video = videoRef.value

  if (capturingRef.value || !video?.videoWidth) return
  capturingRef.value = true
  stopLoop()
  flash()

  try {
    await processFrame(grabFrame(), liveCornersRef.value)
  } catch (error) {
    reportError(error)
  } finally {
    capturingRef.value = false
  }
}

// ---- import an existing photo -----------------------------------------------
function openImport() {
  importInputRef.value?.click()
}

async function onImportChange(event) {
  const file = event.target.files?.[0]

  event.target.value = ''
  if (!file || capturingRef.value) return

  if (!file.type.startsWith('image/')) {
    reportError(localisedError('wp.scanner.error_import_type', 'Choose an image file (JPG, PNG or WebP).'))

    return
  }

  capturingRef.value = true

  try {
    await processFrame(await fileToCanvas(file, MAX_CAPTURE_SIDE))
  } catch {
    reportError(localisedError('wp.document.error_invalid_page', 'Invalid image file'))
  } finally {
    capturingRef.value = false
  }
}

function backToCamera() {
  releaseReview()
  resetLive()
  cooldownUntil = Date.now() + COOLDOWN_MS
  stepRef.value = 'camera'
  startLoop()
}

// ---- review: preview & export ---------------------------------------------
function orientedCorners() {
  return rotateCorners(cornersRef.value, rotationRef.value)
}

function nextPaint() {
  return new Promise(resolve => requestAnimationFrame(() => resolve()))
}

/**
 * Little previews of every filter on the current crop.
 * Generated across animation frames so mobile UI stays responsive.
 */
async function buildFilterThumbs(cv, token) {
  if (!previewBase || thumbsKey === previewKey) return

  const currentKey = previewKey
  const base = scaleCanvas(previewBase, THUMB_LONG_SIDE)
  const thumbs = {}

  try {
    for (const item of filterItems) {
      await nextPaint()
      if (token !== renderToken || !previewBase) return

      const filtered = applyScanFilter(cv, base, item.value)

      thumbs[item.value] = filtered.toDataURL('image/jpeg', 0.7)
      if (filtered !== base) clearCanvasMemory(filtered)
    }

    if (token === renderToken) {
      filterThumbsRef.value = thumbs
      thumbsKey = currentKey
    }
  } finally {
    if (base !== previewBase) clearCanvasMemory(base)
  }
}

async function renderPreview() {
  const cv = getCv()

  if (!cv || !sourceCanvas) return

  const token = ++renderToken

  processingRef.value = true
  await nextTick()
  await nextPaint() // let the spinner appear before the (synchronous) OpenCV work

  if (token !== renderToken || !sourceCanvas) return

  try {
    const key = JSON.stringify([cornersRef.value, rotationRef.value, effectiveOrientationRef.value])

    if (!previewBase || previewKey !== key) {
      if (previewBase) clearCanvasMemory(previewBase)
      previewBase = warpDocument(cv, sourceCanvas, orientedCorners(), {
        orientation: effectiveOrientationRef.value,
        longSide: PREVIEW_LONG_SIDE,
      })
      previewKey = key
    }

    const filtered = applyScanFilter(cv, previewBase, filterRef.value, adjustRef.value)

    resultUrlRef.value = filtered.toDataURL('image/jpeg', 0.85)
    if (filtered !== previewBase) clearCanvasMemory(filtered)
  } catch (error) {
    reportError(error)
  } finally {
    if (token === renderToken) processingRef.value = false
  }

  if (token === renderToken) {
    buildFilterThumbs(cv, token).catch(() => {})
  }
}

/**
 * @param {Object} [config]
 * @param {boolean} [config.finish=false]  also end the scanning session
 */
async function confirmPage({ finish = false } = {}) {
  const cv = getCv()

  if (!cv || !sourceCanvas || processingRef.value) return

  renderToken++
  processingRef.value = true
  await nextTick()
  await nextPaint()

  let warped = null
  let filtered = null

  try {
    const orientation = effectiveOrientationRef.value

    warped = warpDocument(cv, sourceCanvas, orientedCorners(), { orientation, longSide: EXPORT_LONG_SIDE })
    filtered = applyScanFilter(cv, warped, filterRef.value, adjustRef.value)

    const blob = await canvasToBlob(filtered, 0.9)
    const file = new File([blob], `scan-${Date.now()}.jpg`, { type: 'image/jpeg' })
    const preview = URL.createObjectURL(file)

    emit('scanned', {
      file,
      preview,
      width: filtered.width,
      height: filtered.height,
      pageCount: 1,
      format: 'a4',
      orientation,
    })
    lastThumbRef.value = makeThumb(filtered)

    if (!props.multiple || finish) {
      sessionCountRef.value++
      if (finish) emit('done', { count: sessionCountRef.value })
      closeScanner()
    } else {
      sessionCountRef.value++
      backToCamera()
    }
  } catch (error) {
    reportError(error)
  } finally {
    if (filtered && filtered !== warped) clearCanvasMemory(filtered)
    if (warped) clearCanvasMemory(warped)
    processingRef.value = false
  }
}

function rotateContent() {
  rotationRef.value = (rotationRef.value + 1) % 4
}

function redetect() {
  const cv = getCv()
  const detected = cv && sourceCanvas ? detectDocumentCorners(cv, sourceCanvas) : null

  cornersDetectedRef.value = !!detected
  if (detected) cornersRef.value = detected.map(point => ({ ...point }))
}

function selectWholePage() {
  cornersRef.value = [
    { x: 0.02, y: 0.02 },
    { x: 0.98, y: 0.02 },
    { x: 0.98, y: 0.98 },
    { x: 0.02, y: 0.98 },
  ]
  cornersDetectedRef.value = true
}

function resetAdjust() {
  adjustRef.value = { ...DEFAULT_ADJUST }
}

watch([filterRef, orientationRef, rotationRef], () => {
  if (stepRef.value === 'review' && viewRef.value === 'result') renderPreview()
})

watch(viewRef, view => {
  if (stepRef.value === 'review' && view === 'result') renderPreview()
})

watch(adjustRef, () => {
  clearTimeout(adjustTimer)
  adjustTimer = setTimeout(() => {
    if (stepRef.value === 'review' && viewRef.value === 'result') renderPreview()
  }, ADJUST_DEBOUNCE_MS)
}, { deep: true })

watch(autoCaptureRef, enabled => {
  try {
    localStorage.setItem(AUTO_CAPTURE_KEY, enabled ? '1' : '0')
  } catch {
    // storage blocked: the preference simply won't persist
  }
})

// ---- camera / dialog lifecycle ----------------------------------------------
async function toggleCamera() {
  try {
    await switchCamera()
  } catch (error) {
    reportError(error)
  }
}

async function pickCamera(deviceId) {
  try {
    await selectCamera(deviceId)
  } catch (error) {
    reportError(error)
  }
}

async function retryCamera() {
  try {
    await startCamera(cameraFacingModeRef.value)
  } catch (error) {
    reportError(error)
  }
}

function cycleZoom() {
  const { min, max } = zoomRangeRef.value
  const presets = [1, 2, 3].filter(value => value >= min && value <= max)
  const next = presets.find(value => value > zoomRef.value + 0.05) ?? presets[0] ?? min

  setZoom(next)
}

/**
 * Keyboard: Space / Enter = shutter, Enter = confirm in review, R = rotate,
 * Esc = back (review) / close (camera). The dialog is `persistent`, so Esc is ours.
 */
function onWindowKeydown(event) {
  if (!props.modelValue || capturingRef.value) return
  if (tuneOpenRef.value || cameraMenuOpenRef.value) return

  if (event.key === 'Escape') {
    event.preventDefault()
    if (stepRef.value === 'review') backToCamera()
    else closeScanner()

    return
  }

  const tag = event.target?.tagName

  if (tag === 'BUTTON' || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target?.isContentEditable) return

  if (stepRef.value === 'camera') {
    if (event.key !== ' ' && event.key !== 'Enter') return
    event.preventDefault()
    if (canCaptureRef.value) capture()

    return
  }

  if (event.key === 'Enter' && !processingRef.value) {
    event.preventDefault()
    confirmPage()
  } else if (event.key.toLowerCase() === 'r') {
    rotateContent()
  }
}

async function onOpened() {
  stepRef.value = 'camera'
  engineFailedRef.value = false
  resetLive()
  cooldownUntil = Date.now() + COOLDOWN_MS

  if (!stageRef.value) await nextTick()
  if (stageRef.value) {
    resizeObserver = new ResizeObserver(layoutStage)
    resizeObserver.observe(stageRef.value)
  }

  window.addEventListener('keydown', onWindowKeydown)

  loadOpenCv().catch(() => {
    engineFailedRef.value = true
    reportError(localisedError('wp.scanner.error_engine', 'Automatic edge detection is unavailable. You can still scan manually.'))
  })

  try {
    await startCamera(cameraFacingModeRef.value)
  } catch (error) {
    reportError(error)
  }

  startLoop()
}

function teardown() {
  stopLoop()
  stopCamera()
  window.removeEventListener('keydown', onWindowKeydown)
  resizeObserver?.disconnect()
  resizeObserver = null
  releaseReview()
}

function onClosed() {
  teardown()
  sessionCountRef.value = 0
  lastThumbRef.value = null
  noticeRef.value = ''
  stepRef.value = 'camera'
}

function closeScanner() {
  emit('update:modelValue', false)
}

function finishSession() {
  emit('done', { count: sessionCountRef.value })
  closeScanner()
}

watch(() => props.modelValue, open => {
  // switch the camera light off as soon as the dialog starts closing
  if (!open) {
    stopLoop()
    stopCamera()
  }
})

onBeforeUnmount(teardown)
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="$vuetify.display.smAndDown"
    max-width="960"
    persistent
    class="wp-scanner-dialog"
    @update:model-value="emit('update:modelValue', $event)"
    @after-enter="onOpened"
    @after-leave="onClosed"
  >
    <VCard
      theme="dark"
      class="wp-scanner"
    >
      <input
        ref="importInputRef"
        type="file"
        accept="image/*"
        class="d-none"
        @change="onImportChange"
      >

      <div class="wp-scanner__header d-flex align-center gap-2 px-3 py-2">
        <IconBtn
          v-if="stepRef === 'review'"
          variant="text"
          size="small"
          :title="$t('wp.scanner.retake')"
          :aria-label="$t('wp.scanner.retake')"
          @click="backToCamera"
        >
          <VIcon
            icon="tabler-arrow-left"
            size="20"
            class="wp-flip-rtl"
          />
        </IconBtn>
        <span class="text-subtitle-1 font-weight-medium text-truncate">
          {{ stepRef === 'camera' ? $t('wp.scanner.title') : $t('wp.scanner.review_title') }}
        </span>
        <VSpacer />
        <VChip
          v-if="sessionCountRef"
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-files"
        >
          {{ sessionCountRef }} {{ $t('wp.document.pages_count') }}
        </VChip>
        <IconBtn
          variant="text"
          size="small"
          :title="$t('wp.dialog.close')"
          :aria-label="$t('wp.dialog.close')"
          @click="closeScanner"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </div>

      <VDivider />

      <div class="wp-scanner__body">
        <!-- CAMERA STEP (v-show keeps the <video> alive while reviewing) -->
        <div
          v-show="stepRef === 'camera'"
          ref="stageRef"
          class="wp-scanner__stage"
          :class="{ 'wp-scanner__stage--flash': flashRef }"
        >
          <div
            class="wp-scanner__frame"
            :style="frameStyle"
          >
            <video
              ref="videoRef"
              class="wp-scanner__video"
              playsinline
              muted
              autoplay
              @loadedmetadata="layoutStage"
              @resize="layoutStage"
            />
            <svg
              class="wp-scanner__overlay"
              viewBox="0 0 100 100"
              preserveAspectRatio="none"
              aria-hidden="true"
            >
              <path
                :d="liveCornersRef ? liveMaskRef : guideMaskRef"
                class="wp-scanner__mask"
                fill-rule="evenodd"
              />
              <polygon
                v-if="liveCornersRef"
                :points="livePolygonRef"
                class="wp-scanner__doc"
              />
              <template v-if="liveCornersRef">
                <circle
                  v-for="(pt, cIdx) in liveCornersRef"
                  :key="cIdx"
                  :cx="pt.x * 100"
                  :cy="pt.y * 100"
                  r="1.3"
                  class="wp-scanner__doc-corner"
                />
              </template>
              <rect
                v-else
                :x="guideRectRef.x"
                :y="guideRectRef.y"
                :width="guideRectRef.width"
                :height="guideRectRef.height"
                class="wp-scanner__guide"
                rx="1.5"
              />
            </svg>
          </div>

          <VChip
            v-if="!errorRef"
            class="wp-scanner__status"
            :color="statusRef.color"
            variant="elevated"
            size="small"
            aria-live="polite"
          >
            <VIcon
              start
              :icon="statusRef.icon"
              size="16"
              :class="{ 'wp-scanner__status-icon--spin': statusRef.spin }"
            />
            {{ $t(statusRef.key, statusRef.fallback) }}
          </VChip>

          <p
            v-if="showSearchHintRef"
            class="wp-scanner__hint text-caption mb-0"
          >
            {{ $t('wp.scanner.hint_surface', 'Lay the page flat on a plain, contrasting surface with good light.') }}
          </p>

          <!-- Floating tool rail -->
          <div
            v-if="!errorRef"
            class="wp-scanner__tools"
          >
            <VMenu
              v-if="hasRealMultipleCameras"
              v-model="cameraMenuOpenRef"
              location="start"
            >
              <template #activator="{ props: menuProps }">
                <IconBtn
                  v-bind="menuProps"
                  class="wp-scanner__tool"
                  variant="tonal"
                  :disabled="isLoadingRef"
                  :title="$t('wp.camera.select_device')"
                  :aria-label="$t('wp.camera.select_device')"
                >
                  <VIcon
                    icon="tabler-video"
                    size="22"
                  />
                </IconBtn>
              </template>
              <VList density="compact" min-width="220">
                <VListSubheader>{{ $t('wp.camera.select_device') }}</VListSubheader>
                <VListItem
                  v-for="(cam, idx) in selectableCamerasRef"
                  :key="cam.deviceId || idx"
                  :active="cam.deviceId === currentCameraIdRef"
                  @click="pickCamera(cam.deviceId)"
                >
                  <template #prepend>
                    <VIcon
                      :icon="cam.deviceId === currentCameraIdRef ? 'tabler-check' : 'tabler-camera'"
                      size="18"
                    />
                  </template>
                  <VListItemTitle class="text-body-2">
                    {{ cam.label || `${$t('wp.camera.device')} #${idx + 1}` }}
                  </VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
            <IconBtn
              v-if="hasMultipleCameras"
              class="wp-scanner__tool"
              variant="tonal"
              :disabled="isLoadingRef"
              :title="$t('wp.camera.switch')"
              :aria-label="$t('wp.camera.switch')"
              @click="toggleCamera"
            >
              <VIcon
                icon="tabler-camera-rotate"
                size="22"
              />
            </IconBtn>

            <IconBtn
              v-if="torchSupportedRef"
              class="wp-scanner__tool"
              variant="tonal"
              :color="torchEnabledRef ? 'warning' : undefined"
              :aria-pressed="torchEnabledRef"
              :title="torchEnabledRef ? $t('wp.camera.torch_off') : $t('wp.camera.torch_on')"
              :aria-label="torchEnabledRef ? $t('wp.camera.torch_off') : $t('wp.camera.torch_on')"
              @click="() => toggleTorch()"
            >
              <VIcon
                :icon="torchEnabledRef ? 'tabler-bolt' : 'tabler-bolt-off'"
                size="22"
              />
            </IconBtn>

            <VBtn
              v-if="zoomSupportedRef"
              class="wp-scanner__tool wp-scanner__zoom"
              variant="tonal"
              icon
              size="40"
              :title="$t('wp.camera.zoom', 'Zoom')"
              :aria-label="$t('wp.camera.zoom', 'Zoom')"
              @click="cycleZoom"
            >
              <span class="text-caption font-weight-bold">{{ zoomLabelRef }}</span>
            </VBtn>

            <IconBtn
              class="wp-scanner__tool"
              variant="tonal"
              :title="$t('wp.scanner.import_photo', 'Import a photo')"
              :aria-label="$t('wp.scanner.import_photo', 'Import a photo')"
              :disabled="capturingRef"
              @click="openImport"
            >
              <VIcon
                icon="tabler-photo-up"
                size="22"
              />
            </IconBtn>
          </div>

          <div
            v-if="isLoadingRef"
            class="wp-scanner__center"
          >
            <VProgressCircular
              indeterminate
              size="48"
              color="primary"
            />
          </div>

          <!-- Camera unavailable: recover without leaving the scanner -->
          <div
            v-if="errorRef"
            class="wp-scanner__center wp-scanner__error text-center px-4"
          >
            <VIcon
              icon="tabler-camera-off"
              size="48"
              color="error"
              class="mb-2"
            />
            <p class="text-body-1 mb-4">
              {{ errorMessage(errorRef) }}
            </p>
            <div class="d-flex flex-wrap justify-center gap-2">
              <VBtn
                variant="tonal"
                prepend-icon="tabler-refresh"
                :loading="isLoadingRef"
                @click="retryCamera"
              >
                {{ $t('wp.camera.retry', 'Try again') }}
              </VBtn>
              <VBtn
                color="primary"
                prepend-icon="tabler-photo-up"
                @click="openImport"
              >
                {{ $t('wp.scanner.import_photo', 'Import a photo') }}
              </VBtn>
            </div>
          </div>
        </div>

        <!-- REVIEW STEP -->
        <template v-if="stepRef === 'review'">
          <div class="d-flex justify-center pt-3 px-3">
            <VBtnToggle
              v-model="viewRef"
              mandatory
              divided
              density="comfortable"
              variant="outlined"
              color="primary"
            >
              <VBtn value="result">
                <VIcon
                  start
                  icon="tabler-file-check"
                  size="18"
                />
                {{ $t('wp.scanner.result') }}
              </VBtn>
              <VBtn value="crop">
                <VIcon
                  start
                  icon="tabler-crop"
                  size="18"
                />
                {{ $t('wp.scanner.crop') }}
              </VBtn>
            </VBtnToggle>
          </div>

          <div class="wp-scanner__review-stage">
            <WpScanCropEditor
              v-if="viewRef === 'crop'"
              v-model="cornersRef"
              :src="sourceUrlRef"
              :ratio="sourceSizeRef.width / sourceSizeRef.height"
            />
            <div
              v-else
              class="wp-scanner__result"
            >
              <img
                v-if="resultUrlRef"
                :src="resultUrlRef"
                class="wp-scanner__result-img"
                :alt="$t('wp.scanner.result')"
              >
            </div>

            <div
              v-if="processingRef"
              class="wp-scanner__busy"
              role="status"
              :aria-label="$t('wp.scanner.processing', 'Processing…')"
            >
              <VProgressCircular
                indeterminate
                size="40"
                color="primary"
              />
            </div>
          </div>
        </template>
      </div>

      <VDivider />

      <div class="wp-scanner__dock">
        <!-- CAMERA DOCK -->
        <template v-if="stepRef === 'camera'">
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
            <VBtnToggle
              v-model="orientationRef"
              mandatory
              divided
              density="comfortable"
              variant="outlined"
              color="primary"
            >
              <VBtn
                :value="ORIENTATION.AUTO"
                size="small"
              >
                {{ $t('wp.scanner.orientation_auto') }}
              </VBtn>
              <VBtn
                :value="ORIENTATION.PORTRAIT"
                size="small"
                :title="$t('wp.scanner.orientation_portrait')"
                :aria-label="$t('wp.scanner.orientation_portrait')"
              >
                <VIcon
                  icon="tabler-rectangle-vertical"
                  size="18"
                />
              </VBtn>
              <VBtn
                :value="ORIENTATION.LANDSCAPE"
                size="small"
                :title="$t('wp.scanner.orientation_landscape')"
                :aria-label="$t('wp.scanner.orientation_landscape')"
              >
                <VIcon
                  icon="tabler-rectangle"
                  size="18"
                />
              </VBtn>
            </VBtnToggle>

            <VBtn
              size="small"
              :variant="autoCaptureRef ? 'tonal' : 'outlined'"
              :color="autoCaptureRef ? 'primary' : undefined"
              :aria-pressed="autoCaptureRef"
              @click="autoCaptureRef = !autoCaptureRef"
            >
              <VIcon
                start
                :icon="autoCaptureRef ? 'tabler-bolt' : 'tabler-bolt-off'"
                size="18"
              />
              {{ $t('wp.scanner.auto_capture') }}
            </VBtn>
          </div>

          <div class="wp-scanner__controls">
            <div class="d-flex align-center gap-2">
              <div
                v-if="lastThumbRef"
                class="wp-scanner__thumb-wrap"
              >
                <img
                  :src="lastThumbRef"
                  class="wp-scanner__thumb"
                  :alt="$t('wp.scanner.last_page', 'Last scanned page')"
                >
                <span class="wp-scanner__thumb-count">{{ sessionCountRef }}</span>
              </div>
            </div>

            <div
              class="wp-shutter-wrap"
              :style="shutterRingStyleRef"
            >
              <button
                type="button"
                class="wp-shutter"
                :class="{ 'wp-shutter--ready': liveCornersRef }"
                :disabled="!canCaptureRef"
                :aria-label="$t('wp.scanner.capture')"
                @click="capture"
              />
            </div>

            <div class="d-flex justify-end">
              <VBtn
                v-if="sessionCountRef"
                color="primary"
                prepend-icon="tabler-check"
                @click="finishSession"
              >
                {{ $t('wp.scanner.done') }}
              </VBtn>
            </div>
          </div>
          <p
            v-if="!$vuetify.display.xs"
            class="text-caption text-disabled text-center mt-2 mb-0"
          >
            {{ $t('wp.scanner.capture_hint') }}
          </p>
        </template>

        <!-- REVIEW DOCK -->
        <template v-else>
          <p
            v-if="viewRef === 'crop' && !cornersDetectedRef"
            class="text-caption text-warning text-center mb-2"
          >
            {{ $t('wp.scanner.crop_hint') }}
          </p>

          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
            <VBtnToggle
              v-model="orientationRef"
              mandatory
              divided
              density="comfortable"
              variant="outlined"
              color="primary"
            >
              <VBtn
                :value="ORIENTATION.AUTO"
                size="small"
              >
                {{ $t('wp.scanner.orientation_auto') }}
              </VBtn>
              <VBtn
                :value="ORIENTATION.PORTRAIT"
                size="small"
                :title="$t('wp.scanner.orientation_portrait')"
                :aria-label="$t('wp.scanner.orientation_portrait')"
              >
                <VIcon
                  icon="tabler-rectangle-vertical"
                  size="18"
                />
              </VBtn>
              <VBtn
                :value="ORIENTATION.LANDSCAPE"
                size="small"
                :title="$t('wp.scanner.orientation_landscape')"
                :aria-label="$t('wp.scanner.orientation_landscape')"
              >
                <VIcon
                  icon="tabler-rectangle"
                  size="18"
                />
              </VBtn>
            </VBtnToggle>

            <div class="d-flex align-center gap-1">
              <template v-if="viewRef === 'crop'">
                <VBtn
                  size="small"
                  variant="outlined"
                  @click="selectWholePage"
                >
                  <VIcon
                    start
                    icon="tabler-maximize"
                    size="18"
                  />
                  {{ $t('wp.scanner.whole_page', 'Whole image') }}
                </VBtn>
                <VBtn
                  size="small"
                  variant="outlined"
                  @click="redetect"
                >
                  <VIcon
                    start
                    icon="tabler-focus-2"
                    size="18"
                  />
                  {{ $t('wp.scanner.redetect') }}
                </VBtn>
              </template>

              <VMenu
                v-else
                v-model="tuneOpenRef"
                :close-on-content-click="false"
                location="top end"
              >
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    size="small"
                    :variant="isAdjustedRef ? 'tonal' : 'outlined'"
                    :color="isAdjustedRef ? 'primary' : undefined"
                  >
                    <VIcon
                      start
                      icon="tabler-adjustments-horizontal"
                      size="18"
                    />
                    {{ $t('wp.scanner.adjust', 'Adjust') }}
                  </VBtn>
                </template>
                <VCard
                  min-width="260"
                  class="pa-4"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-subtitle-2">{{ $t('wp.scanner.adjust', 'Adjust') }}</span>
                    <VBtn
                      size="x-small"
                      variant="text"
                      :disabled="!isAdjustedRef"
                      @click="resetAdjust"
                    >
                      {{ $t('wp.scanner.reset', 'Reset') }}
                    </VBtn>
                  </div>
                  <p
                    v-if="filterRef === SCAN_FILTER.BLACK_WHITE"
                    class="text-caption text-medium-emphasis mb-0"
                  >
                    {{ $t('wp.scanner.adjust_bw_note', 'Black & white is already at full contrast. Pick another filter to adjust it.') }}
                  </p>
                  <template v-else>
                    <VSlider
                      v-model="adjustRef.brightness"
                      :label="$t('wp.scanner.brightness', 'Brightness')"
                      :min="-50"
                      :max="50"
                      :step="1"
                      density="compact"
                      hide-details
                      color="primary"
                      class="mb-2"
                    />
                    <VSlider
                      v-model="adjustRef.contrast"
                      :label="$t('wp.scanner.contrast', 'Contrast')"
                      :min="-50"
                      :max="50"
                      :step="1"
                      density="compact"
                      hide-details
                      color="primary"
                    />
                  </template>
                </VCard>
              </VMenu>

              <VBtn
                size="small"
                variant="outlined"
                @click="rotateContent"
              >
                <VIcon
                  start
                  icon="tabler-rotate-clockwise-2"
                  size="18"
                />
                {{ $t('wp.scanner.rotate') }}
              </VBtn>
            </div>
          </div>

          <div
            v-if="viewRef === 'result'"
            class="wp-scanner__filters mb-3"
            role="radiogroup"
            :aria-label="$t('wp.scanner.filters', 'Filters')"
          >
            <button
              v-for="item in filterItems"
              :key="item.value"
              type="button"
              role="radio"
              class="wp-scanner__filter"
              :class="{ 'wp-scanner__filter--active': filterRef === item.value }"
              :aria-checked="filterRef === item.value"
              @click="filterRef = item.value"
            >
              <span class="wp-scanner__filter-thumb">
                <img
                  v-if="filterThumbsRef[item.value]"
                  :src="filterThumbsRef[item.value]"
                  alt=""
                >
                <VIcon
                  v-else
                  :icon="item.icon"
                  size="20"
                />
              </span>
              <span class="text-caption">{{ $t(item.label, item.fallback) }}</span>
            </button>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              variant="outlined"
              :disabled="processingRef"
              :aria-label="$t('wp.scanner.retake')"
              @click="backToCamera"
            >
              <VIcon
                :start="!$vuetify.display.xs"
                icon="tabler-camera"
                size="18"
              />
              <span class="d-none d-sm-inline">{{ $t('wp.scanner.retake') }}</span>
            </VBtn>
            <VBtn
              v-if="multiple"
              variant="tonal"
              color="primary"
              :disabled="processingRef"
              @click="confirmPage({ finish: true })"
            >
              {{ $t('wp.scanner.add_finish', 'Add & finish') }}
            </VBtn>
            <VBtn
              color="primary"
              class="flex-grow-1"
              :loading="processingRef"
              :disabled="processingRef"
              @click="confirmPage"
            >
              <VIcon
                start
                icon="tabler-check"
                size="18"
              />
              {{ confirmLabelRef }}
            </VBtn>
          </div>
        </template>
      </div>

      <VSnackbar
        :model-value="!!noticeRef"
        color="error"
        location="top"
        :timeout="4500"
        @update:model-value="noticeRef = ''"
      >
        {{ noticeRef }}
      </VSnackbar>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-scanner {
  display: flex;
  flex-direction: column;
  block-size: 100%;
}

.wp-scanner-dialog:not(.v-dialog--fullscreen) :deep(.v-overlay__content) {
  block-size: min(90vh, 820px);
}

.wp-scanner__body {
  display: flex;
  flex: 1 1 0;
  flex-direction: column;
  min-block-size: 0;
}

/* ---- camera ---- */
.wp-scanner__stage {
  position: relative;
  display: flex;
  overflow: hidden;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  background: #000;
  min-block-size: 0;
}

.wp-scanner__frame {
  position: relative;
}

.wp-scanner__video,
.wp-scanner__overlay {
  position: absolute;
  block-size: 100%;
  inline-size: 100%;
  inset: 0;
}

.wp-scanner__video {
  object-fit: fill;
}

.wp-scanner__overlay {
  pointer-events: none;
}

/* Dim outside the guide / detected sheet so the target area pops. */
.wp-scanner__mask {
  fill: rgba(0, 0, 0, 42%);
  transition: d 0.2s ease;
}

.wp-scanner__doc {
  fill: rgba(var(--v-theme-success), 0.18);
  stroke: rgb(var(--v-theme-success));
  stroke-linejoin: round;
  stroke-width: 3;
  vector-effect: non-scaling-stroke;
}

.wp-scanner__doc-corner {
  fill: #fff;
  stroke: rgb(var(--v-theme-success));
  stroke-width: 2.5;
  vector-effect: non-scaling-stroke;
}

.wp-scanner__guide {
  fill: none;
  stroke: rgba(255, 255, 255, 75%);
  stroke-dasharray: 8 6;
  stroke-width: 2;
  vector-effect: non-scaling-stroke;
}

.wp-scanner__status {
  position: absolute;
  z-index: 1;
  inset-block-start: 12px;
  inset-inline-start: 50%;
  max-inline-size: calc(100% - 24px);
  transform: translateX(-50%);
}

[dir="rtl"] .wp-scanner__status {
  transform: translateX(50%);
}

.wp-scanner__hint {
  position: absolute;
  z-index: 1;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 55%);
  color: #fff;
  inset-block-end: 12px;
  inset-inline-start: 50%;
  max-inline-size: calc(100% - 96px);
  text-align: center;
  transform: translateX(-50%);
}

[dir="rtl"] .wp-scanner__hint {
  transform: translateX(50%);
}

/* Floating tool rail: glass buttons over the live video. */
.wp-scanner__tools {
  position: absolute;
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: 10px;
  inset-block-start: 50%;
  inset-inline-end: 12px;
  transform: translateY(-50%);
}

.wp-scanner__tool {
  background-color: rgba(0, 0, 0, 45%);
  backdrop-filter: blur(8px);
  color: #fff;
}

.wp-scanner__status-icon--spin {
  animation: wp-scanner-spin 1.1s linear infinite;
}

@keyframes wp-scanner-spin {
  to { transform: rotate(360deg); }
}

.wp-scanner__center {
  position: absolute;
  z-index: 1;
  inset-block-start: 50%;
  inset-inline-start: 50%;
  transform: translate(-50%, -50%);
}

[dir="rtl"] .wp-scanner__center {
  transform: translate(50%, -50%);
}

.wp-scanner__error {
  z-index: 3;
  max-inline-size: min(420px, 100%);
}

.wp-scanner__stage--flash::after {
  position: absolute;
  animation: wp-scanner-flash 0.2s ease-out forwards;
  background: #fff;
  content: "";
  inset: 0;
  pointer-events: none;
}

@keyframes wp-scanner-flash {
  from { opacity: 0.8; }
  to { opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .wp-scanner__stage--flash::after {
    animation: none;
    opacity: 0;
  }

  .wp-scanner__status-icon--spin {
    animation: none;
  }

  .wp-scanner__review-stage {
    animation: none;
  }

  .wp-scanner__mask,
  .wp-shutter-wrap {
    transition: none;
  }
}

/* ---- review ---- */
.wp-scanner__review-stage {
  position: relative;
  flex: 1 1 0;
  animation: wp-scanner-reveal 0.2s ease-out;
  min-block-size: 0;
}

@keyframes wp-scanner-reveal {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: none;
  }
}

.wp-scanner__result {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  inset: 0;
}

.wp-scanner__result-img {
  border-radius: 4px;
  background: #fff;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 45%);
  max-block-size: 100%;
  max-inline-size: 100%;
  object-fit: contain;
}

.wp-scanner__busy {
  position: absolute;
  z-index: 4;
  display: grid;
  background: rgba(0, 0, 0, 35%);
  inset: 0;
  place-items: center;
}

/* ---- dock ---- */
.wp-scanner__dock {
  flex: 0 0 auto;
  padding-block: 12px;
  padding-block-end: max(12px, env(safe-area-inset-bottom));
  padding-inline: 16px;
}

/* Filter thumbnails scroll sideways on small screens instead of wrapping. */
.wp-scanner__filters {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding-block: 2px;
  scrollbar-width: thin;
}

.wp-scanner__filter {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  align-items: center;
  padding: 4px;
  border: 2px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: rgba(255, 255, 255, 75%);
  cursor: pointer;
  gap: 4px;
  inline-size: 84px;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.wp-scanner__filter:hover {
  color: #fff;
}

.wp-scanner__filter--active {
  border-color: rgb(var(--v-theme-primary));
  color: #fff;
}

.wp-scanner__filter:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-scanner__filter-thumb {
  display: grid;
  overflow: hidden;
  border-radius: 6px;
  background: rgba(255, 255, 255, 8%);
  block-size: 56px;
  inline-size: 100%;
  place-items: center;
}

.wp-scanner__filter-thumb img {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
  object-position: top;
}

.wp-scanner__controls {
  display: grid;
  align-items: center;
  grid-template-columns: 1fr auto 1fr;
}

.wp-scanner__thumb-wrap {
  position: relative;
}

.wp-scanner__thumb {
  border: 1px solid rgba(255, 255, 255, 35%);
  border-radius: 6px;
  background: #fff;
  block-size: 48px;
  inline-size: 36px;
  object-fit: cover;
}

.wp-scanner__thumb-count {
  position: absolute;
  display: grid;
  border-radius: 999px;
  background: rgb(var(--v-theme-primary));
  block-size: 18px;
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 600;
  inline-size: 18px;
  inset-block-start: -6px;
  inset-inline-end: -6px;
  place-items: center;
}

/* Shutter + auto-capture progress ring around it. */
.wp-shutter-wrap {
  display: grid;
  padding: 6px;
  border-radius: 50%;
  place-items: center;
  transition: background 0.15s linear;
}

.wp-shutter {
  display: grid;
  padding: 4px;
  border: 4px solid #fff;
  border-radius: 50%;
  background: transparent;
  block-size: 68px;
  cursor: pointer;
  inline-size: 68px;
  place-items: center;
  transition: border-color 0.15s ease;
}

.wp-shutter::before {
  border-radius: 50%;
  background: #fff;
  block-size: 100%;
  content: "";
  inline-size: 100%;
  transition: transform 0.12s ease;
}

.wp-shutter:active:not(:disabled)::before {
  transform: scale(0.88);
}

.wp-shutter--ready {
  border-color: rgb(var(--v-theme-success));
}

.wp-shutter:disabled {
  cursor: default;
  opacity: 0.4;
}

.wp-shutter:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

/* Directional icons mirror in RTL (defined here so it doesn't depend on a theme utility class). */
[dir="rtl"] .wp-flip-rtl {
  transform: scaleX(-1);
}
</style>
