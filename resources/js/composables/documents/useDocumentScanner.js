import { ref, computed, onUnmounted, getCurrentInstance } from 'vue'
import { useCameraCapture } from '@/composables/camera/useCameraCapture'
import { resolveFileKind, resolveFileMime, uid } from '@/composables/documents/documentFiles'
import {
  areCornersStable,
  canvasToBlob,
  detectDocumentCorners,
  fileToCanvas,
  releaseScanEngineBuffers,
  smoothCorners,
} from '@/composables/documents/scanEngine'

const DETECT_INTERVAL_MS = 150
const STABLE_REQUIRED = 6
const MISS_GRACE_FRAMES = 3

// Module-level active detection loop guard: ensures two scanner instances
// can never run concurrent OpenCV detection loops simultaneously.
let activeDetectionStopFn = null

const DOCUMENT_SOURCE = {
  UPLOAD: 'upload',
  CAMERA_SCAN: 'camera_scan',
  CAMERA_IMAGE: 'camera_image',
  CAMERA_PHOTO: 'camera_photo',
  MEDIA: 'media',
}

const MAX_UPLOAD_BYTES = 20 * 1024 * 1024

/** A4 in PDF points (1/72 in). Scanned pages are exported at this real size. */
const A4_POINTS = { width: 595.28, height: 841.89 }

const ALLOWED_MIME_TYPES = new Set([
  'application/pdf',
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'image/svg+xml',
  'video/mp4',
  'video/webm',
  'video/quicktime',
  'video/x-msvideo',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'text/csv',
  'text/plain',
])

/** Kinds that can be merged into one multi-page PDF. Everything else must travel alone. */
const COMBINABLE_KINDS = new Set(['image', 'pdf'])

/**
 * Create an Error carrying a stable i18n key so consuming modules can localise
 * the message instead of hardcoding English copy in shared infrastructure.
 */
function i18nError(key, fallback, params) {
  const error = new Error(fallback)

  error.i18nKey = key
  if (params) error.i18nParams = params

  return error
}

function loadImageSize(file) {
  const preview = URL.createObjectURL(file)

  return new Promise((resolve, reject) => {
    const img = new Image()

    img.onload = () => resolve({ preview, width: img.naturalWidth || img.width, height: img.naturalHeight || img.height })
    img.onerror = () => {
      URL.revokeObjectURL(preview)
      reject(i18nError('wp.document.error_invalid_page', 'Invalid image file'))
    }
    img.src = preview
  })
}

/** Scale a page to fit inside A4 (either orientation) without changing its ratio. */
function fitInsideA4(width, height) {
  const landscape = width > height
  const box = landscape ? { width: A4_POINTS.height, height: A4_POINTS.width } : A4_POINTS
  const scale = Math.min(1, box.width / width, box.height / height)

  return { width: width * scale, height: height * scale }
}

/** pdf-lib embeds JPEG + PNG natively; anything else (WebP...) is re-encoded as JPEG first. */
async function toEmbeddableImage(file) {
  const mime = resolveFileMime(file)

  if (mime === 'image/jpeg' || mime === 'image/png') {
    return { bytes: await file.arrayBuffer(), type: mime === 'image/png' ? 'png' : 'jpeg' }
  }

  const canvas = await fileToCanvas(file, 4000)
  try {
    const blob = await canvasToBlob(canvas, 0.92)

    return { bytes: await blob.arrayBuffer(), type: 'jpeg' }
  } finally {
    canvas.width = 0
    canvas.height = 0
  }
}

/**
 * @param {Object}  [options]
 * @param {number}  [options.maxBytes]  per-file size limit (default 20 MB)
 */
export function useDocumentScanner(options = {}) {
  const maxBytes = options.maxBytes || MAX_UPLOAD_BYTES

  const {
    videoRef,
    streamRef,
    facingModeRef,
    permissionStateRef,
    isActiveRef,
    isLoadingRef,
    errorRef,
    availableCamerasRef,
    selectableCamerasRef,
    currentCameraIdRef,
    torchSupportedRef,
    torchEnabledRef,
    toggleTorch,
    zoomSupportedRef,
    zoomRef,
    zoomRangeRef,
    setZoom,
    hasCamera,
    hasRealMultipleCameras,
    canSwitchFacingModeRef,
    hasMultipleCameras,
    isEnvironmentCamera,
    isUserCamera,
    startCamera,
    stopCamera: rawStopCamera,
    switchCamera,
    switchFacingMode,
    selectCamera,
    captureFrame,
    captureFrameAsFile,
    enumerateCameras,
    checkPermission,
    init,
    dispose: disposeCamera,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
  } = useCameraCapture({
    idealWidth: options.idealWidth || 1920,
    idealHeight: options.idealHeight || 1080,
  })

  const pagesRef = ref([])
  const currentPageIndexRef = ref(-1)
  const processingRef = ref(false)
  const liveCornersRef = ref(null)
  const stableFramesRef = ref(0)

  let detectRafId = null
  let lastDetectTime = 0
  let detectingInProgress = false
  let missedFrames = 0

  function resetDetectionState() {
    liveCornersRef.value = null
    stableFramesRef.value = 0
    missedFrames = 0
  }

  function stopDetectionLoop() {
    if (detectRafId !== null && typeof cancelAnimationFrame === 'function') {
      cancelAnimationFrame(detectRafId)
    }
    detectRafId = null
    detectingInProgress = false
    if (activeDetectionStopFn === stopDetectionLoop) {
      activeDetectionStopFn = null
    }
  }

  /**
   * Canonical single-flight OpenCV live document edge detection loop.
   * Automatically stops any other active detection loop before starting.
   *
   * @param {Object} loopOptions
   * @param {Function} loopOptions.getCv
   * @param {Function} [loopOptions.isBlocked]    returns true when studio/modal is open or busy
   * @param {Function} [loopOptions.isAutoCapture] returns true when auto-capture is enabled
   * @param {Function} [loopOptions.onAutoCapture] called when STABLE_REQUIRED frames are reached
   */
  function startDetectionLoop(loopOptions = {}) {
    if (activeDetectionStopFn && activeDetectionStopFn !== stopDetectionLoop) {
      activeDetectionStopFn()
    }
    stopDetectionLoop()
    activeDetectionStopFn = stopDetectionLoop
    lastDetectTime = 0

    const tick = now => {
      if (detectRafId === null) return

      const blocked = typeof loopOptions.isBlocked === 'function' ? loopOptions.isBlocked() : false

      if (blocked || !isActiveRef.value || (typeof document !== 'undefined' && document.hidden)) {
        detectRafId = requestAnimationFrame(tick)

        return
      }

      if (!detectingInProgress && now - lastDetectTime >= DETECT_INTERVAL_MS) {
        lastDetectTime = now

        const cv = typeof loopOptions.getCv === 'function' ? loopOptions.getCv() : null
        const video = videoRef.value

        if (cv && video?.videoWidth) {
          detectingInProgress = true
          try {
            const found = detectDocumentCorners(cv, video)

            if (found) {
              missedFrames = 0

              const prev = liveCornersRef.value
              const stable = prev ? areCornersStable(prev, found, video.videoWidth, video.videoHeight) : false

              liveCornersRef.value = smoothCorners(prev, found, video.videoWidth, video.videoHeight)
              stableFramesRef.value = stable ? Math.min(STABLE_REQUIRED, stableFramesRef.value + 1) : 1

              const autoEnabled = typeof loopOptions.isAutoCapture === 'function'
                ? loopOptions.isAutoCapture()
                : false

              if (autoEnabled && stableFramesRef.value >= STABLE_REQUIRED && typeof loopOptions.onAutoCapture === 'function') {
                stableFramesRef.value = 0
                loopOptions.onAutoCapture(liveCornersRef.value)
              }
            } else {
              missedFrames++
              if (missedFrames >= MISS_GRACE_FRAMES) {
                liveCornersRef.value = null
                stableFramesRef.value = 0
              }
            }
          } catch {
            // Never let a transient frame error crash the RAF loop
          } finally {
            detectingInProgress = false
          }
        }
      }

      if (detectRafId !== null && typeof requestAnimationFrame === 'function') {
        detectRafId = requestAnimationFrame(tick)
      }
    }

    if (typeof requestAnimationFrame === 'function') {
      detectRafId = requestAnimationFrame(tick)
    }
  }

  async function stopCamera() {
    stopDetectionLoop()
    resetDetectionState()

    return rawStopCamera()
  }

  const currentPageRef = computed(() => pagesRef.value[currentPageIndexRef.value] || null)
  const pageCountRef = computed(() => pagesRef.value.reduce((total, page) => total + (page.pageCount || 1), 0))
  const totalBytesRef = computed(() => pagesRef.value.reduce((total, page) => total + (page.file?.size || 0), 0))
  const hasPagesRef = computed(() => pagesRef.value.length > 0)
  const canReorderRef = computed(() => pagesRef.value.length > 1)

  function revokePreview(page) {
    if (page?.preview) URL.revokeObjectURL(page.preview)
  }

  function addPage(pageData) {
    pagesRef.value.push({
      id: uid('page'),
      file: pageData.file,
      preview: pageData.preview,
      width: pageData.width,
      height: pageData.height,
      pageCount: pageData.pageCount || 1,
      rotation: 0,
      format: pageData.format || null,
      orientation: pageData.orientation || null,
      source: pageData.source || DOCUMENT_SOURCE.CAMERA_SCAN,
      timestamp: new Date().toISOString(),
    })
    currentPageIndexRef.value = pagesRef.value.length - 1
  }

  function removePage(index) {
    const page = pagesRef.value[index]

    if (!page) return
    revokePreview(page)
    pagesRef.value.splice(index, 1)
    if (currentPageIndexRef.value >= pagesRef.value.length) {
      currentPageIndexRef.value = pagesRef.value.length - 1
    }
  }

  function movePage(fromIndex, toIndex) {
    if (fromIndex < 0 || fromIndex >= pagesRef.value.length) return
    if (toIndex < 0 || toIndex >= pagesRef.value.length) return
    if (fromIndex === toIndex) return

    const [page] = pagesRef.value.splice(fromIndex, 1)

    pagesRef.value.splice(toIndex, 0, page)

    if (currentPageIndexRef.value === fromIndex) {
      currentPageIndexRef.value = toIndex
    } else if (fromIndex < currentPageIndexRef.value && toIndex >= currentPageIndexRef.value) {
      currentPageIndexRef.value--
    } else if (fromIndex > currentPageIndexRef.value && toIndex <= currentPageIndexRef.value) {
      currentPageIndexRef.value++
    }
  }

  /** @param {number} degrees  multiples of 90, negative = counter-clockwise */
  function rotatePage(index, degrees = 90) {
    const page = pagesRef.value[index]

    if (!page) return
    page.rotation = (((page.rotation + degrees) % 360) + 360) % 360
  }

  function setPageSource(index, source) {
    const page = pagesRef.value[index]

    if (page && Object.values(DOCUMENT_SOURCE).includes(source)) {
      page.source = source
    }
  }

  function setCurrentPage(index) {
    if (index >= 0 && index < pagesRef.value.length) {
      currentPageIndexRef.value = index
    }
  }

  function clearPages() {
    pagesRef.value.forEach(revokePreview)
    pagesRef.value = []
    currentPageIndexRef.value = -1
  }

  async function capturePage(facingMode = CAMERA_FACING_MODES.ENVIRONMENT) {
    if (!isActiveRef.value) {
      await startCamera(facingMode)
    }

    const result = await captureFrameAsFile(`page-${Date.now()}.jpg`, {
      format: 'image/jpeg',
      quality: 0.9,
    })

    addPage({ ...result, source: DOCUMENT_SOURCE.CAMERA_SCAN })

    return result
  }

  // ---- uploads ---------------------------------------------------------------

  /** Throws a localisable error when a single file is not acceptable. */
  function validateFile(file) {
    if (file.size > maxBytes) {
      throw i18nError('wp.document.error_too_large', `File exceeds the ${Math.round(maxBytes / 1048576)} MB limit`, { size: Math.round(maxBytes / 1048576) })
    }

    if (!ALLOWED_MIME_TYPES.has(resolveFileMime(file))) {
      throw i18nError('wp.document.error_unsupported_type', 'Unsupported file type')
    }
  }

  /**
   * A Word/Excel/text file cannot be merged with other files into one PDF.
   * Caught here (while picking) instead of blowing up later during save.
   */
  function validateCombination(files, existing = []) {
    const kinds = [...existing.map(page => resolveFileKind(resolveFileMime(page.file), page.file?.name)),
      ...files.map(file => resolveFileKind(resolveFileMime(file), file.name))]

    if (kinds.length > 1 && kinds.some(kind => !COMBINABLE_KINDS.has(kind))) {
      throw i18nError('wp.document.error_mixed_types', 'Word, Excel and text files cannot be combined with other files')
    }
  }

  async function buildUploadedPage(file, source) {
    const mime = resolveFileMime(file)
    const kind = resolveFileKind(mime, file.name)

    const base = {
      id: uid('page'),
      file,
      preview: null,
      width: null,
      height: null,
      pageCount: null,
      rotation: 0,
      format: null,
      orientation: null,
      source,
      timestamp: new Date().toISOString(),
    }

    if (kind === 'pdf') {
      let pageCount = null

      try {
        const { PDFDocument } = await import('pdf-lib')
        const pdf = await PDFDocument.load(await file.arrayBuffer())

        pageCount = pdf.getPageCount()
      } catch {
        pageCount = null // encrypted / damaged: still uploadable on its own
      }

      return { ...base, pageCount }
    }

    if (kind === 'image') {
      const { preview, width, height } = await loadImageSize(file)

      return { ...base, preview, width, height, pageCount: 1 }
    }

    if (kind === 'video') {
      const preview = URL.createObjectURL(file)

      return { ...base, preview, width: 1280, height: 720, pageCount: 1 }
    }

    return base
  }

  /**
   * Validate EVERYTHING first, then build, then swap in: a rejected pick never
   * leaves the user with a half-added or wiped selection.
   *
   * @param {FileList|File[]} files
   * @param {Object} [config]
   * @param {boolean} [config.replace=false]  discard the current pages when the new ones are ready
   */
  async function addUploadedFiles(files, { replace = false, source = DOCUMENT_SOURCE.UPLOAD } = {}) {
    const list = Array.from(files || [])

    if (!list.length) return []

    list.forEach(validateFile)
    validateCombination(list, replace ? [] : pagesRef.value)

    const built = []

    try {
      for (const file of list) {
        built.push(await buildUploadedPage(file, source))
      }
    } catch (error) {
      built.forEach(revokePreview)
      throw error
    }

    if (replace) clearPages()

    pagesRef.value.push(...built)
    currentPageIndexRef.value = pagesRef.value.length - 1

    return built
  }

  async function addUploadedFile(file, source = DOCUMENT_SOURCE.UPLOAD) {
    const [page] = await addUploadedFiles([file], { source })

    return page
  }

  /** Replace one page with a new image (keeps its position in the document). */
  async function replacePage(index, file) {
    const page = pagesRef.value[index]

    if (!page) throw i18nError('wp.document.error_invalid_page', 'Page not found')

    validateFile(file)

    if (resolveFileKind(resolveFileMime(file), file.name) !== 'image') {
      throw i18nError('wp.document.error_invalid_page', 'Invalid image file')
    }

    const { preview, width, height } = await loadImageSize(file)

    revokePreview(page)
    page.file = file
    page.preview = preview
    page.width = width
    page.height = height
    page.pageCount = 1
    page.rotation = 0

    // A replaced page is a plain photo: it must NOT inherit the scan's A4 stretch.
    page.format = null
    page.orientation = null
    page.source = DOCUMENT_SOURCE.UPLOAD
    page.timestamp = new Date().toISOString()

    return page
  }

  // ---- PDF -------------------------------------------------------------------

  /**
   * @param {Object} [pdfOptions]
   * @param {string} [pdfOptions.name]     file name
   * @param {string} [pdfOptions.title]    PDF metadata title
   * @param {string} [pdfOptions.creator]  PDF metadata creator
   */
  async function generatePdf(pdfOptions = {}) {
    if (!hasPagesRef.value) {
      throw i18nError('wp.document.error_file_required', 'No pages to generate PDF')
    }

    processingRef.value = true

    try {
      const { PDFDocument, degrees } = await import('pdf-lib')
      const pdfDoc = await PDFDocument.create()

      if (pdfOptions.title) pdfDoc.setTitle(pdfOptions.title)
      pdfDoc.setCreator(pdfOptions.creator || 'WAVE PLUS')
      pdfDoc.setCreationDate(new Date())

      for (const page of pagesRef.value) {
        const mime = resolveFileMime(page.file)
        const kind = resolveFileKind(mime, page.file.name)

        if (kind === 'pdf') {
          let existingPdf

          try {
            existingPdf = await PDFDocument.load(await page.file.arrayBuffer())
          } catch {
            throw i18nError('wp.document.error_pdf_unreadable', `Cannot read ${page.file.name}. It may be password-protected or damaged.`, { name: page.file.name })
          }

          const copiedPages = await pdfDoc.copyPages(existingPdf, existingPdf.getPageIndices())

          copiedPages.forEach(copied => pdfDoc.addPage(copied))
          continue
        }

        if (kind !== 'image') {
          throw i18nError('wp.document.error_mixed_types', `Cannot combine ${page.file.name} with scanned pages`, { name: page.file.name })
        }

        const { bytes, type } = await toEmbeddableImage(page.file)
        const pdfImage = type === 'png' ? await pdfDoc.embedPng(bytes) : await pdfDoc.embedJpg(bytes)

        // Scanned pages already have the A4 ratio: give them the real A4 size.
        // Other photos keep their own ratio, scaled to fit A4 instead of a
        // multi-metre page measured in pixels-as-points.
        let size

        if (page.format === 'a4') {
          const landscape = page.orientation === 'landscape'

          size = landscape
            ? { width: A4_POINTS.height, height: A4_POINTS.width }
            : { width: A4_POINTS.width, height: A4_POINTS.height }
        } else {
          size = fitInsideA4(pdfImage.width, pdfImage.height)
        }

        const pdfPage = pdfDoc.addPage([size.width, size.height])

        pdfPage.drawImage(pdfImage, { x: 0, y: 0, width: size.width, height: size.height })

        if (page.rotation) {
          pdfPage.setRotation(degrees(page.rotation))
        }
      }

      const pdfBytes = await pdfDoc.save()
      const blob = new Blob([pdfBytes], { type: 'application/pdf' })
      const file = new File([blob], pdfOptions.name || `document-${Date.now()}.pdf`, { type: 'application/pdf' })

      return {
        file,
        pageCount: pdfDoc.getPageCount(),
      }
    } finally {
      processingRef.value = false
    }
  }

  function getPagesForSubmit() {
    return pagesRef.value.map((page, index) => ({
      index,
      file: page.file,
      preview: page.preview,
      width: page.width,
      height: page.height,
      rotation: page.rotation,
      source: page.source,
    }))
  }

  function dispose() {
    stopDetectionLoop()
    resetDetectionState()
    clearPages()
    releaseScanEngineBuffers()
    disposeCamera()
  }

  if (getCurrentInstance()) {
    onUnmounted(dispose)
  }

  return {
    videoRef,
    streamRef,
    facingModeRef,
    permissionStateRef,
    isActiveRef,
    isLoadingRef,
    errorRef,
    availableCamerasRef,
    selectableCamerasRef,
    currentCameraIdRef,
    torchSupportedRef,
    torchEnabledRef,
    toggleTorch,
    zoomSupportedRef,
    zoomRef,
    zoomRangeRef,
    setZoom,
    hasCamera,
    hasRealMultipleCameras,
    canSwitchFacingModeRef,
    hasMultipleCameras,
    isEnvironmentCamera,
    isUserCamera,
    pagesRef,
    currentPageIndexRef,
    currentPageRef,
    pageCountRef,
    totalBytesRef,
    hasPagesRef,
    canReorderRef,
    processingRef,
    liveCornersRef,
    stableFramesRef,
    STABLE_REQUIRED,
    startDetectionLoop,
    stopDetectionLoop,
    resetDetectionState,
    startCamera,
    stopCamera,
    switchCamera,
    switchFacingMode,
    selectCamera,
    captureFrame,
    captureFrameAsFile,
    enumerateCameras,
    checkPermission,
    init,
    dispose,
    addPage,
    removePage,
    movePage,
    rotatePage,
    setPageSource,
    setCurrentPage,
    clearPages,
    capturePage,
    addUploadedFile,
    addUploadedFiles,
    replacePage,
    generatePdf,
    getPagesForSubmit,
    DOCUMENT_SOURCE,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
    MAX_UPLOAD_BYTES,
  }
}
