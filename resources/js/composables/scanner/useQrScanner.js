import { ref, computed, getCurrentInstance, onUnmounted } from 'vue'
import { useCameraCapture } from '../camera/useCameraCapture.js'
import jsQR from 'jsqr'

const SCAN_INTERVAL = 140
const SCAN_TIMEOUT = 60_000
const QR_SCAN_MAX_SIDE = 640
const FILE_SCAN_PASSES = [1200, 640]

function safeDecodeURIComponent(value) {
  try {
    return decodeURIComponent(value)
  } catch {
    return value
  }
}

/**
 * Normalize a scanned QR payload (raw string, `/ca/scan/eq/<code>` URL,
 * `WP:EQ:<code>` token, or `useQrScanner` result object) into the clean
 * equipment / QR code string expected by backend validation and lookup endpoints.
 */
export function extractEquipmentQrCode(scanPayload) {
  const raw = typeof scanPayload === 'string'
    ? scanPayload
    : (scanPayload?.value ?? scanPayload?.rawValue ?? scanPayload?.text ?? '')

  if (typeof raw !== 'string') return ''

  let candidate = raw.trim()
  if (!candidate) return ''

  // Strip WAVE PLUS equipment prefix if present (e.g. WP:EQ:EQ-2026-4821)
  if (/^wp:eq:/i.test(candidate)) {
    candidate = candidate.replace(/^wp:eq:/i, '').trim()
  }

  // Match canonical ClientEquipmentController::qrDownload URL or path (/ca/scan/eq/{code})
  const scanPathMatch = candidate.match(/\/ca\/scan\/eq\/([^/?#]+)/i)
  if (scanPathMatch?.[1]) {
    return safeDecodeURIComponent(scanPathMatch[1]).trim()
  }

  // Handle generic URLs by checking query params first, then trailing path segment
  if (/^https?:\/\//i.test(candidate)) {
    try {
      const parsed = new URL(candidate)

      const queryCode = parsed.searchParams.get('equipment_code')
        || parsed.searchParams.get('qr_code')
        || parsed.searchParams.get('code')

      if (queryCode?.trim()) {
        return queryCode.trim()
      }

      const lastSegment = parsed.pathname.split('/').filter(Boolean).pop()
      if (lastSegment) {
        return safeDecodeURIComponent(lastSegment).trim()
      }
    } catch {
      // fall through to path segment extraction
    }
  }

  if (candidate.includes('/')) {
    const cleanPath = candidate.split(/[?#]/)[0]
    const lastSegment = cleanPath.split('/').filter(Boolean).pop()
    if (lastSegment) {
      return safeDecodeURIComponent(lastSegment).trim()
    }
  }

  return candidate
}

/**
 * Normalize a scanned QR payload or manual badge string into the clean badge
 * identifier expected by `/hr/time-clock/scan` (`WP:EMP:<code_or_id>`,
 * employee number, or numeric employee ID).
 */
export function extractEmployeeBadgeCode(scan) {
  let raw = scan
  if (raw && typeof raw === 'object') {
    raw = raw.badge ?? raw.value ?? raw.rawValue ?? raw.text ?? ''
    if (raw && typeof raw === 'object') {
      raw = raw.value ?? raw.rawValue ?? raw.text ?? ''
    }
  }
  if (raw == null) return ''

  return String(raw).trim()
}

// Module-level single-flight guard so two QR scanner instances never run concurrent loops.
let activeQrScannerStopFn = null

export function useQrScanner() {
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
    stopCamera,
    switchCamera: baseSwitchCamera,
    selectCamera: baseSelectCamera,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
  } = useCameraCapture()

  let scanTimeoutId = null
  let scanFrameTimeoutId = null
  let frameInFlight = false
  let scanCanvas = null
  let scanCtx = null

  const scanningRef = ref(false)
  const lastScannedValueRef = ref(null)
  const scanCountRef = ref(0)
  const manualInputRef = ref('')
  const showManualInputRef = ref(false)

  const scanResultRef = ref(null)
  const hasScannedRef = computed(() => !!scanResultRef.value)

  function releaseScanCanvas() {
    if (scanCanvas) {
      scanCanvas.width = 0
      scanCanvas.height = 0
      scanCanvas = null
      scanCtx = null
    }
  }

  function clearTimers() {
    if (scanTimeoutId) {
      clearTimeout(scanTimeoutId)
      scanTimeoutId = null
    }
    if (scanFrameTimeoutId) {
      clearTimeout(scanFrameTimeoutId)
      scanFrameTimeoutId = null
    }
  }

  function resetScanState() {
    clearTimers()
    frameInFlight = false
    scanResultRef.value = null
    lastScannedValueRef.value = null
    scanCountRef.value = 0
    manualInputRef.value = ''
    showManualInputRef.value = false
    errorRef.value = null
  }

  function validateQrValue(value) {
    if (!value || typeof value !== 'string') return false

    return value.trim().length > 0
  }

  function processScanResult(value) {
    if (!validateQrValue(value)) return false

    const trimmed = value.trim()
    if (trimmed === lastScannedValueRef.value) return false

    lastScannedValueRef.value = trimmed
    scanCountRef.value++
    scanResultRef.value = {
      value: trimmed,
      format: 'qr',
      rawValue: value,
      timestamp: new Date().toISOString(),
    }

    return true
  }

  function ensureScanCanvas(width, height) {
    if (typeof document === 'undefined') return null
    if (!scanCanvas) {
      scanCanvas = document.createElement('canvas')
      scanCtx = scanCanvas.getContext('2d', { willReadFrequently: true })
    }
    if (scanCanvas.width !== width || scanCanvas.height !== height) {
      scanCanvas.width = width
      scanCanvas.height = height
    }

    return scanCtx ? { canvas: scanCanvas, ctx: scanCtx } : null
  }

  function scheduleNextFrame() {
    if (!scanningRef.value) return
    if (scanFrameTimeoutId) clearTimeout(scanFrameTimeoutId)
    scanFrameTimeoutId = setTimeout(scanFrame, SCAN_INTERVAL)
  }

  function armScanTimeout() {
    if (scanTimeoutId) clearTimeout(scanTimeoutId)
    scanTimeoutId = setTimeout(() => {
      if (!scanningRef.value) return
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      errorRef.value = {
        code: 'SCAN_TIMEOUT',
        i18nKey: 'wp.qr.error_timeout',
        message: 'No QR code found. Try again.',
      }
      stopCamera()
    }, SCAN_TIMEOUT)
  }

  async function scanFrame() {
    if (!scanningRef.value) return
    if (frameInFlight) {
      scheduleNextFrame()

      return
    }

    // While camera is starting or switching, keep the loop alive until frames flow
    if (isLoadingRef.value || !isActiveRef.value) {
      scheduleNextFrame()

      return
    }

    const video = videoRef.value
    const vw = video?.videoWidth || 0
    const vh = video?.videoHeight || 0

    if (!video || !vw || !vh) {
      scheduleNextFrame()

      return
    }

    frameInFlight = true
    try {
      const scale = Math.min(1, QR_SCAN_MAX_SIDE / Math.max(vw, vh))
      const targetW = Math.max(1, Math.round(vw * scale))
      const targetH = Math.max(1, Math.round(vh * scale))
      const surface = ensureScanCanvas(targetW, targetH)

      if (!surface) throw new Error('Unable to read camera frame')

      surface.ctx.drawImage(video, 0, 0, targetW, targetH)

      const imageData = surface.ctx.getImageData(0, 0, targetW, targetH)

      const code = jsQR(imageData.data, targetW, targetH, {
        inversionAttempts: 'attemptBoth',
      })

      if (code?.data && processScanResult(code.data)) {
        scanningRef.value = false
        clearTimers()
        releaseScanCanvas()
        if (activeQrScannerStopFn === stopScanning) {
          activeQrScannerStopFn = null
        }
        await stopCamera()

        return scanResultRef.value
      }
    } catch (e) {
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      errorRef.value = {
        code: 'SCAN_FRAME_ERROR',
        i18nKey: 'wp.qr.error_frame',
        message: e.message || 'Unable to scan camera frame',
      }
      await stopCamera()

      return
    } finally {
      frameInFlight = false
    }

    scheduleNextFrame()
  }

  async function startScanning(facingMode = CAMERA_FACING_MODES.ENVIRONMENT, deviceId = null) {
    if (activeQrScannerStopFn && activeQrScannerStopFn !== stopScanning) {
      try {
        await activeQrScannerStopFn()
      } catch {
        // ignore previous scanner cleanup error
      }
    }
    activeQrScannerStopFn = stopScanning

    resetScanState()
    try {
      await startCamera(facingMode, deviceId)
      scanningRef.value = true
      armScanTimeout()
      await nextFrame()
      scanFrame()
    } catch (e) {
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      if (activeQrScannerStopFn === stopScanning) {
        activeQrScannerStopFn = null
      }
      throw e
    }
  }

  /**
   * Clear the previous scan result and resume scanning on the active stream
   * (or start the camera if it is not currently active).
   */
  async function rearmScanning(facingMode = facingModeRef.value || CAMERA_FACING_MODES.ENVIRONMENT) {
    clearTimers()
    frameInFlight = false
    scanResultRef.value = null
    lastScannedValueRef.value = null
    errorRef.value = null

    if (isActiveRef.value && streamRef.value) {
      scanningRef.value = true
      armScanTimeout()
      scheduleNextFrame()

      return
    }

    await startScanning(facingMode, currentCameraIdRef.value)
  }

  async function switchCamera() {
    const wasScanning = scanningRef.value && !hasScannedRef.value
    if (wasScanning) {
      scanningRef.value = true
      armScanTimeout()
    }
    await baseSwitchCamera()
    if (wasScanning && scanningRef.value) {
      scheduleNextFrame()
    }
  }

  async function selectCamera(deviceId) {
    const wasScanning = scanningRef.value && !hasScannedRef.value
    if (wasScanning) {
      scanningRef.value = true
      armScanTimeout()
    }
    await baseSelectCamera(deviceId)
    if (wasScanning && scanningRef.value) {
      scheduleNextFrame()
    }
  }

  function nextFrame() {
    if (typeof requestAnimationFrame === 'function') {
      return new Promise(resolve => requestAnimationFrame(resolve))
    }

    return Promise.resolve()
  }

  async function stopScanning() {
    scanningRef.value = false
    frameInFlight = false
    clearTimers()
    releaseScanCanvas()
    if (activeQrScannerStopFn === stopScanning) {
      activeQrScannerStopFn = null
    }
    await stopCamera()
  }

  function useManualInput(value) {
    if (validateQrValue(value)) {
      processScanResult(value)
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      if (activeQrScannerStopFn === stopScanning) {
        activeQrScannerStopFn = null
      }
      stopCamera()

      return scanResultRef.value
    }

    return null
  }

  function toggleManualInput() {
    showManualInputRef.value = !showManualInputRef.value
    if (showManualInputRef.value) {
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      stopCamera()
    }
  }

  async function scanFromFile(file) {
    return new Promise((resolve, reject) => {
      if (!file) {
        const error = new Error('No image selected')

        error.i18nKey = 'wp.qr.error_file_load'
        reject(error)

        return
      }

      const img = new Image()
      const imageUrl = URL.createObjectURL(file)

      img.onload = async () => {
        const canvas = document.createElement('canvas')
        const ctx = canvas.getContext('2d', { willReadFrequently: true })
        try {
          if (!ctx) throw new Error('Unable to read image')

          const srcW = img.naturalWidth || img.width
          const srcH = img.naturalHeight || img.height
          let detectedCode = null

          for (const maxSide of FILE_SCAN_PASSES) {
            const scale = Math.min(1, maxSide / Math.max(srcW, srcH, 1))
            const w = Math.max(1, Math.round(srcW * scale))
            const h = Math.max(1, Math.round(srcH * scale))

            canvas.width = w
            canvas.height = h
            ctx.drawImage(img, 0, 0, w, h)

            const imageData = ctx.getImageData(0, 0, w, h)

            const code = jsQR(imageData.data, w, h, {
              inversionAttempts: 'attemptBoth',
            })

            if (code?.data && validateQrValue(code.data)) {
              detectedCode = code.data
              break
            }
          }

          if (detectedCode && processScanResult(detectedCode)) {
            scanningRef.value = false
            clearTimers()
            releaseScanCanvas()
            await stopCamera()
            resolve(scanResultRef.value)
          } else {
            const error = new Error('No QR code found in image')

            error.code = 'QR_FILE_NO_CODE'
            error.i18nKey = 'wp.qr.error_file_no_code'
            errorRef.value = {
              code: 'QR_FILE_NO_CODE',
              i18nKey: 'wp.qr.error_file_no_code',
              message: error.message,
            }
            reject(error)
          }
        } catch (error) {
          errorRef.value = {
            code: 'QR_FILE_ERROR',
            i18nKey: error.i18nKey || 'wp.qr.error_file_load',
            message: error.message || 'Failed to read QR image',
          }
          reject(error)
        } finally {
          canvas.width = 0
          canvas.height = 0
          URL.revokeObjectURL(imageUrl)
        }
      }
      img.onerror = () => {
        URL.revokeObjectURL(imageUrl)

        const error = new Error('Failed to load image')

        error.code = 'QR_FILE_LOAD_ERROR'
        error.i18nKey = 'wp.qr.error_file_load'
        errorRef.value = {
          code: 'QR_FILE_LOAD_ERROR',
          i18nKey: 'wp.qr.error_file_load',
          message: error.message,
        }
        reject(error)
      }
      img.src = imageUrl
    })
  }

  function retry() {
    resetScanState()
    scanningRef.value = false
  }

  if (getCurrentInstance()) {
    onUnmounted(() => {
      scanningRef.value = false
      clearTimers()
      releaseScanCanvas()
      if (activeQrScannerStopFn === stopScanning) {
        activeQrScannerStopFn = null
      }
      stopCamera()
    })
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
    scanningRef,
    scanResultRef,
    hasScannedRef,
    manualInputRef,
    showManualInputRef,
    startScanning,
    stopScanning,
    rearmScanning,
    switchCamera,
    selectCamera,
    useManualInput,
    toggleManualInput,
    scanFromFile,
    retry,
    resetScanState,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
  }
}
