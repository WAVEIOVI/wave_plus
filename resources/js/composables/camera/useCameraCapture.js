import { ref, computed, onUnmounted, nextTick, getCurrentInstance, watch } from 'vue'

const CAMERA_FACING_MODES = {
  ENVIRONMENT: 'environment',
  USER: 'user',
}

const PERMISSION_STATES = {
  GRANTED: 'granted',
  DENIED: 'denied',
  PROMPT: 'prompt',
}

const ERROR_CODES = {
  NOT_ALLOWED: 'NotAllowedError',
  NOT_FOUND: 'NotFoundError',
  NOT_READABLE: 'NotReadableError',
  OVERCONSTRAINED: 'OverconstrainedError',
  SECURITY: 'SecurityError',
  ABORT: 'AbortError',
}

/**
 * @param {Object} [options]
 * @param {number} [options.idealWidth=1920]   preferred capture width (browsers fall back gracefully)
 * @param {number} [options.idealHeight=1080]  preferred capture height
 */
export function useCameraCapture(options = {}) {
  const { idealWidth = 1280, idealHeight = 720 } = options

  const videoRef = ref(null)
  const streamRef = ref(null)
  const facingModeRef = ref(CAMERA_FACING_MODES.ENVIRONMENT)
  const permissionStateRef = ref(PERMISSION_STATES.PROMPT)
  const errorRef = ref(null)
  const isLoadingRef = ref(false)
  const isActiveRef = ref(false)
  const availableCamerasRef = ref([])
  const currentCameraIdRef = ref(null)
  const torchSupportedRef = ref(false)
  const torchEnabledRef = ref(false)
  const zoomSupportedRef = ref(false)
  const zoomRef = ref(1)
  const zoomRangeRef = ref({ min: 1, max: 1, step: 0.1 })

  // Incremented by every start AND stop, so a getUserMedia() that resolves after
  // the dialog was closed (or after a newer start) can release its stream
  // instead of leaking a camera that keeps its light on.
  let requestToken = 0
  let captureCanvas = null
  let activeTrackCleanup = null
  let pausedByVisibility = false
  let lastActiveFacingMode = null
  let lastActiveDeviceId = null

  const selectableCamerasRef = computed(() => {
    return availableCamerasRef.value.filter(
      d => typeof d?.deviceId === 'string' && d.deviceId.trim().length > 0,
    )
  })

  const hasCamera = computed(() => availableCamerasRef.value.length > 0)

  const hasRealMultipleCameras = computed(() => selectableCamerasRef.value.length > 1)

  const canSwitchFacingModeRef = computed(() => {
    return Boolean(typeof navigator !== 'undefined' && /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent || ''))
  })

  const hasMultipleCameras = computed(() => {
    return hasRealMultipleCameras.value || canSwitchFacingModeRef.value
  })

  const isEnvironmentCamera = computed(() => facingModeRef.value === CAMERA_FACING_MODES.ENVIRONMENT)
  const isUserCamera = computed(() => facingModeRef.value === CAMERA_FACING_MODES.USER)

  async function enumerateCameras() {
    if (!navigator.mediaDevices?.enumerateDevices) {
      return []
    }
    try {
      const devices = await navigator.mediaDevices.enumerateDevices()
      const videoInputs = devices.filter(d => d.kind === 'videoinput')

      // Deduplicate virtual 'default'/'communications' aliases when real deviceIds exist
      const realDevices = videoInputs.filter(
        d => d.deviceId && d.deviceId !== 'default' && d.deviceId !== 'communications',
      )

      const realGroupIds = new Set(realDevices.map(d => d.groupId).filter(Boolean))

      const seenIds = new Set()
      const cameras = []

      for (const cam of videoInputs) {
        const id = typeof cam.deviceId === 'string' ? cam.deviceId.trim() : ''

        if (
          (id === 'default' || id === 'communications')
          && (realDevices.length > 0 || (cam.groupId && realGroupIds.has(cam.groupId)))
        ) {
          continue
        }

        if (id && seenIds.has(id)) {
          continue
        }

        if (id) seenIds.add(id)
        cameras.push(cam)
      }

      availableCamerasRef.value = cameras

      return cameras
    } catch (e) {
      console.warn('Failed to enumerate cameras:', e)

      return []
    }
  }

  function handleDeviceChange() {
    enumerateCameras().then(cameras => {
      if (
        isActiveRef.value
        && currentCameraIdRef.value
        && cameras.length > 0
        && !cameras.some(c => c.deviceId === currentCameraIdRef.value)
      ) {
        // Active camera was disconnected; switch to first available camera
        const fallback = cameras.find(c => c.deviceId)

        if (fallback) {
          selectCamera(fallback.deviceId).catch(() => {})
        }
      }
    })
  }

  function handleVisibilityChange() {
    if (typeof document === 'undefined') return

    if (document.hidden) {
      if (isActiveRef.value || streamRef.value) {
        pausedByVisibility = true
        lastActiveFacingMode = facingModeRef.value
        lastActiveDeviceId = currentCameraIdRef.value
        stopCamera()
      }
    } else if (pausedByVisibility) {
      pausedByVisibility = false
      startCamera(lastActiveFacingMode, lastActiveDeviceId || undefined).catch(() => {})
    }
  }

  const hasDeviceEvents = typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.addEventListener)
  const hasVisibilityEvents = typeof document !== 'undefined' && Boolean(document.addEventListener)

  if (hasDeviceEvents) {
    navigator.mediaDevices.addEventListener('devicechange', handleDeviceChange)
  }

  if (hasVisibilityEvents) {
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }

  async function checkPermission() {
    if (!navigator.permissions?.query) {
      return PERMISSION_STATES.PROMPT
    }
    try {
      // 'camera' is not a valid PermissionName in every browser (notably
      // Firefox/Safari); the query rejects and we fall back to 'prompt'.
      const status = await navigator.permissions.query({ name: 'camera' })

      permissionStateRef.value = status.state

      return status.state
    } catch {
      permissionStateRef.value = PERMISSION_STATES.PROMPT

      return PERMISSION_STATES.PROMPT
    }
  }

  function getConstraints(facingMode = facingModeRef.value, deviceId = currentCameraIdRef.value) {
    const videoConstraints = {
      width: { ideal: idealWidth, max: 1920 },
      height: { ideal: idealHeight, max: 1080 },
    }

    if (deviceId) {
      videoConstraints.deviceId = { exact: deviceId }
    } else if (facingMode) {
      videoConstraints.facingMode = { ideal: facingMode }
    }

    return {
      video: videoConstraints,
      audio: false,
    }
  }

  function getVideoTrack(stream = streamRef.value) {
    return stream?.getVideoTracks?.()[0] || null
  }

  function checkTorchSupport(stream = streamRef.value) {
    const track = getVideoTrack(stream)
    const supported = Boolean(track?.getCapabilities?.()?.torch)

    torchSupportedRef.value = supported
    if (!supported) torchEnabledRef.value = false

    return supported
  }

  function checkZoomSupport(stream = streamRef.value) {
    const zoom = getVideoTrack(stream)?.getCapabilities?.()?.zoom

    if (zoom && typeof zoom.max === 'number' && zoom.max > (zoom.min || 1)) {
      zoomSupportedRef.value = true
      zoomRangeRef.value = { min: zoom.min || 1, max: zoom.max, step: zoom.step || 0.1 }
      zoomRef.value = getVideoTrack(stream)?.getSettings?.()?.zoom || zoom.min || 1
    } else {
      zoomSupportedRef.value = false
      zoomRangeRef.value = { min: 1, max: 1, step: 0.1 }
      zoomRef.value = 1
    }

    return zoomSupportedRef.value
  }

  /** Continuous autofocus keeps text sharp when the user moves toward a page. */
  async function applyBestFocus(stream) {
    const track = getVideoTrack(stream)
    const modes = track?.getCapabilities?.()?.focusMode

    if (!Array.isArray(modes) || !modes.includes('continuous')) return

    try {
      await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] })
    } catch {
      // optional nicety: ignore
    }
  }

  async function toggleTorch(forceState) {
    const track = getVideoTrack()

    if (!track) return false

    if (!track.getCapabilities?.()?.torch) {
      torchSupportedRef.value = false
      torchEnabledRef.value = false

      return false
    }

    const nextState = typeof forceState === 'boolean' ? forceState : !torchEnabledRef.value

    try {
      await track.applyConstraints({ advanced: [{ torch: nextState }] })
      torchEnabledRef.value = nextState

      return nextState
    } catch (err) {
      console.warn('Failed to toggle torch:', err)

      return torchEnabledRef.value
    }
  }

  async function setZoom(value) {
    const track = getVideoTrack()

    if (!track || !zoomSupportedRef.value) return false

    const { min, max } = zoomRangeRef.value
    const next = Math.min(max, Math.max(min, Number(value) || min))

    try {
      await track.applyConstraints({ advanced: [{ zoom: next }] })
      zoomRef.value = next

      return true
    } catch (err) {
      console.warn('Failed to set zoom:', err)

      return false
    }
  }

  /** Release hardware synchronously (no token bump). */
  function releaseStream() {
    if (activeTrackCleanup) {
      activeTrackCleanup()
      activeTrackCleanup = null
    }
    if (streamRef.value) {
      streamRef.value.getTracks().forEach(track => {
        try {
          track.stop()
        } catch {
          // ignore
        }
      })
      streamRef.value = null
    }
    if (videoRef.value) {
      videoRef.value.srcObject = null
    }
    isActiveRef.value = false
  }

  function waitForVideoDimensions(video, timeoutMs = 350) {
    if (!video || video.videoWidth > 0 || typeof video.addEventListener !== 'function') {
      return Promise.resolve()
    }

    return new Promise(resolve => {
      let settled = false

      const finish = () => {
        if (settled) return
        settled = true
        clearTimeout(timer)
        video.removeEventListener?.('loadedmetadata', finish)
        video.removeEventListener?.('loadeddata', finish)
        video.removeEventListener?.('canplay', finish)
        resolve()
      }

      const timer = setTimeout(finish, timeoutMs)

      video.addEventListener('loadedmetadata', finish, { once: true })
      video.addEventListener('loadeddata', finish, { once: true })
      video.addEventListener('canplay', finish, { once: true })
    })
  }

  async function startCamera(facingMode, deviceId) {
    pausedByVisibility = false

    if (isActiveRef.value || streamRef.value) {
      await stopCamera()
    }

    const token = ++requestToken

    isLoadingRef.value = true
    errorRef.value = null

    const validFacing = typeof facingMode === 'string' && facingMode.trim() ? facingMode.trim() : null
    const validDeviceId = typeof deviceId === 'string' && deviceId.trim() ? deviceId.trim() : null

    if (validFacing) facingModeRef.value = validFacing
    currentCameraIdRef.value = validDeviceId

    try {
      if (!navigator.mediaDevices?.getUserMedia) {
        throw new DOMException('Camera access is unavailable in this browser or context.', 'NotFoundError')
      }

      await checkPermission()

      let stream

      try {
        stream = await navigator.mediaDevices.getUserMedia(getConstraints(facingModeRef.value, currentCameraIdRef.value))
      } catch (err) {
        // If exact deviceId failed, retry with facingMode only
        if (currentCameraIdRef.value && token === requestToken) {
          console.warn('Failed with exact camera deviceId, falling back to facingMode:', err)
          currentCameraIdRef.value = null
          try {
            stream = await navigator.mediaDevices.getUserMedia(getConstraints(facingModeRef.value, null))
          } catch {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
          }
        } else if ((err?.name === ERROR_CODES.OVERCONSTRAINED || err?.name === ERROR_CODES.NOT_FOUND) && token === requestToken) {
          try {
            stream = await navigator.mediaDevices.getUserMedia({
              video: { facingMode: { ideal: facingModeRef.value } },
              audio: false,
            })
          } catch {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
          }
        } else {
          throw err
        }
      }

      // Stopped / superseded while the permission prompt was open
      if (token !== requestToken) {
        stream.getTracks().forEach(track => track.stop())

        return null
      }

      streamRef.value = stream
      permissionStateRef.value = PERMISSION_STATES.GRANTED

      const track = getVideoTrack(stream)
      const settings = track?.getSettings?.()

      if (settings?.deviceId) currentCameraIdRef.value = settings.deviceId
      if (settings?.facingMode) facingModeRef.value = settings.facingMode

      if (track && typeof track.addEventListener === 'function') {
        const onEnded = () => {
          if (streamRef.value === stream && token === requestToken) {
            releaseStream()
            errorRef.value = normalizeError({
              name: ERROR_CODES.NOT_READABLE,
              message: 'Camera stream was interrupted.',
            })
          }
        }

        track.addEventListener('ended', onEnded)
        activeTrackCleanup = () => track.removeEventListener?.('ended', onEnded)
      }

      await nextTick()

      if (videoRef.value) {
        videoRef.value.srcObject = stream
        try {
          await videoRef.value.play()
        } catch (playError) {
          // play() rejects with AbortError when srcObject is swapped mid-load: harmless
          if (playError?.name !== 'AbortError') throw playError
        }
        await waitForVideoDimensions(videoRef.value)
      }

      if (token !== requestToken) return null

      isActiveRef.value = true
      await enumerateCameras()
      checkTorchSupport(stream)
      checkZoomSupport(stream)
      applyBestFocus(stream)

      // If currentCameraIdRef wasn't set by getSettings, try to match from enumerated cameras
      if (!currentCameraIdRef.value && selectableCamerasRef.value.length > 0) {
        currentCameraIdRef.value = selectableCamerasRef.value[0].deviceId
      } else if (!currentCameraIdRef.value && availableCamerasRef.value.length > 0) {
        currentCameraIdRef.value = availableCamerasRef.value[0].deviceId
      }

      return stream
    } catch (e) {
      if (token === requestToken) {
        releaseStream()
        errorRef.value = normalizeError(e)
      }
      throw e
    } finally {
      if (token === requestToken) isLoadingRef.value = false
    }
  }

  async function stopCamera() {
    requestToken++

    const track = getVideoTrack()

    if (torchEnabledRef.value && track) {
      try {
        await track.applyConstraints({ advanced: [{ torch: false }] })
      } catch {
        // ignore
      }
    }
    torchEnabledRef.value = false
    torchSupportedRef.value = false
    zoomSupportedRef.value = false
    zoomRef.value = 1
    releaseStream()
    isLoadingRef.value = false
  }

  function labelToFacingMode(label = '') {
    const text = label.toLowerCase()

    if (/front|user|selfie|facetime/.test(text)) return CAMERA_FACING_MODES.USER
    if (/back|rear|environment|world/.test(text)) return CAMERA_FACING_MODES.ENVIRONMENT

    return null
  }

  async function switchFacingMode() {
    const newFacingMode = isEnvironmentCamera.value
      ? CAMERA_FACING_MODES.USER
      : CAMERA_FACING_MODES.ENVIRONMENT

    try {
      await startCamera(newFacingMode, null)

      return true
    } catch {
      return false
    }
  }

  async function switchCamera(targetDeviceId = null) {
    const validTargetId = typeof targetDeviceId === 'string' && targetDeviceId.trim()
      ? targetDeviceId.trim()
      : null

    if (validTargetId) {
      return selectCamera(validTargetId)
    }

    const candidates = selectableCamerasRef.value.length > 1
      ? selectableCamerasRef.value
      : availableCamerasRef.value.filter(c => c.deviceId)

    if (candidates.length > 1) {
      const currentIndex = candidates.findIndex(
        c => c.deviceId === currentCameraIdRef.value,
      )

      const nextIndex = currentIndex >= 0
        ? (currentIndex + 1) % candidates.length
        : 0

      const nextCamera = candidates[nextIndex]

      const nextFacingMode = labelToFacingMode(nextCamera.label)
        || (isEnvironmentCamera.value ? CAMERA_FACING_MODES.USER : CAMERA_FACING_MODES.ENVIRONMENT)

      await startCamera(nextFacingMode, nextCamera.deviceId)

      return true
    }

    // Fallback: toggle facing mode directly
    return switchFacingMode()
  }

  async function selectCamera(deviceId) {
    const validId = typeof deviceId === 'string' && deviceId.trim() ? deviceId.trim() : null
    if (!validId) return false

    const camera = availableCamerasRef.value.find(c => c.deviceId === validId)
    const facingMode = labelToFacingMode(camera?.label) || facingModeRef.value

    await startCamera(facingMode, validId)

    return true
  }

  /**
   * Grab the current video frame.
   * @param {Object} [captureOptions]
   * @param {string}  [captureOptions.format='image/jpeg']
   * @param {number}  [captureOptions.quality=0.92]
   * @param {boolean} [captureOptions.mirror=false]   flip horizontally (selfie WYSIWYG)
   * @param {boolean} [captureOptions.dataUrl=true]   skip to avoid a second, wasted encode
   */
  function captureFrame(captureOptions = {}) {
    const { format = 'image/jpeg', quality = 0.92, mirror = false, dataUrl = true } = captureOptions
    const video = videoRef.value

    if (!video || !isActiveRef.value || !video.videoWidth) {
      return Promise.reject(new Error('Camera not active'))
    }

    const maxSide = captureOptions.maxSide || 1920
    const rawW = video.videoWidth
    const rawH = video.videoHeight
    const scale = Math.min(1, maxSide / Math.max(rawW, rawH))
    const w = Math.max(1, Math.round(rawW * scale))
    const h = Math.max(1, Math.round(rawH * scale))

    const canvas = captureCanvas || (captureCanvas = document.createElement('canvas'))

    canvas.width = w
    canvas.height = h

    const ctx = canvas.getContext('2d')

    ctx.setTransform?.(1, 0, 0, 1, 0, 0)
    if (mirror) {
      ctx.translate(canvas.width, 0)
      ctx.scale(-1, 1)
    }
    ctx.drawImage(video, 0, 0, w, h)

    return new Promise((resolve, reject) => {
      canvas.toBlob(blob => {
        if (blob) {
          resolve({
            blob,
            dataUrl: dataUrl ? canvas.toDataURL(format, quality) : null,
            width: w,
            height: h,
          })
        } else {
          reject(new Error('Failed to capture frame'))
        }
      }, format, quality)
    })
  }

  function captureFrameAsFile(name = `capture-${Date.now()}.jpg`, captureOptions = {}) {
    return captureFrame({ dataUrl: false, ...captureOptions }).then(({ blob, width, height }) => {
      const file = new File([blob], name, { type: blob.type })

      return { file, preview: URL.createObjectURL(blob), width, height }
    })
  }

  // Maps a DOMException name to a stable i18n key. Components localise; the
  // composable stays presentation-free.
  function errorI18nKey(code) {
    const keys = {
      [ERROR_CODES.NOT_ALLOWED]: 'wp.camera.error_permission_denied',
      [ERROR_CODES.NOT_FOUND]: 'wp.camera.error_not_found',
      [ERROR_CODES.NOT_READABLE]: 'wp.camera.error_not_readable',
      [ERROR_CODES.OVERCONSTRAINED]: 'wp.camera.error_overconstrained',
      [ERROR_CODES.SECURITY]: 'wp.camera.error_security',
      [ERROR_CODES.ABORT]: 'wp.camera.error_aborted',
    }

    return keys[code] || 'wp.camera.error_generic'
  }

  function normalizeError(error) {
    if (!error?.name) {
      return { code: 'UNKNOWN', i18nKey: 'wp.camera.error_generic', message: String(error) }
    }

    const messages = {
      [ERROR_CODES.NOT_ALLOWED]: 'Camera permission denied. Please allow camera access in your browser settings.',
      [ERROR_CODES.NOT_FOUND]: 'No camera found on this device.',
      [ERROR_CODES.NOT_READABLE]: 'Camera is already in use by another application.',
      [ERROR_CODES.OVERCONSTRAINED]: 'Camera constraints cannot be satisfied.',
      [ERROR_CODES.SECURITY]: 'Camera access blocked by security policy.',
      [ERROR_CODES.ABORT]: 'Camera access was aborted.',
    }

    return {
      code: error.name,
      i18nKey: errorI18nKey(error.name),
      message: messages[error.name] || `Camera error: ${error.message}`,
      original: error,
    }
  }

  async function init(facingMode = CAMERA_FACING_MODES.ENVIRONMENT) {
    await enumerateCameras()
    await checkPermission()

    return startCamera(facingMode)
  }

  function dispose() {
    pausedByVisibility = false
    if (hasDeviceEvents) {
      navigator.mediaDevices.removeEventListener('devicechange', handleDeviceChange)
    }
    if (hasVisibilityEvents) {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
    stopCamera()
    if (captureCanvas) {
      captureCanvas.width = 0
      captureCanvas.height = 0
      captureCanvas = null
    }
  }

  // Auto-connect video element when stream is already running
  watch(videoRef, el => {
    if (el && streamRef.value) {
      if (el.srcObject !== streamRef.value) {
        el.srcObject = streamRef.value
      }
      el.play().catch(err => {
        if (err?.name !== 'AbortError') console.warn('Camera video play error:', err)
      })
    }
  })

  // Outside a component (tests, plain scripts) the caller owns cleanup via dispose().
  if (getCurrentInstance()) onUnmounted(dispose)

  return {
    videoRef,
    streamRef,
    facingModeRef,
    permissionStateRef,
    errorRef,
    isLoadingRef,
    isActiveRef,
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
    switchCamera,
    switchFacingMode,
    selectCamera,
    captureFrame,
    captureFrameAsFile,
    enumerateCameras,
    checkPermission,
    init,
    dispose,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
  }
}
