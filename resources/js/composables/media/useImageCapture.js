import { ref, computed, onUnmounted, getCurrentInstance } from 'vue'
import { useCameraCapture } from '@/composables/camera/useCameraCapture'
import { extensionForMime } from '@/composables/documents/documentFiles'
import { normaliseRotation, rotatedSize } from '@/composables/media/imageTransforms'

function i18nError(key, fallback, params) {
  const error = new Error(fallback)

  error.i18nKey = key
  if (params) error.i18nParams = params

  return error
}

const OUTPUT_TYPES = ['image/jpeg', 'image/png', 'image/webp']

/**
 * @param {Object}  [options]
 * @param {number}  [options.maxBytes]     reject picked files above this size (no limit by default)
 * @param {number}  [options.idealWidth]   preferred camera capture width
 * @param {number}  [options.idealHeight]  preferred camera capture height
 */
export function useImageCapture(options = {}) {
  const {
    videoRef,
    streamRef,
    facingModeRef: cameraFacingModeRef,
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
    selectCamera,
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

  const imageRef = ref(null)
  const previewRef = ref('')
  const widthRef = ref(0)
  const heightRef = ref(0)
  const isCapturingRef = ref(false)
  const rotationRef = ref(0)

  /** Normalised { x, y, width, height } (0..1) of the ROTATED image, or null for "no crop". */
  const cropRectRef = ref(null)
  const facingModeRef = cameraFacingModeRef

  const hasImageRef = computed(() => !!imageRef.value)
  const isCameraActiveRef = computed(() => isActiveRef.value)

  function setImage(file, preview, width, height) {
    if (previewRef.value && previewRef.value !== preview) {
      URL.revokeObjectURL(previewRef.value)
    }
    imageRef.value = file
    previewRef.value = preview
    widthRef.value = width
    heightRef.value = height
    rotationRef.value = 0
    cropRectRef.value = null
  }

  /**
   * Capture a photo from the active camera stream (starting the camera if needed).
   * @param {string} [facingMode]
   * @param {Object} [captureOptions]
   * @param {boolean} [captureOptions.mirror]          defaults to true for the front camera
   * @param {boolean} [captureOptions.keepStreamAlive] keep stream running after capture (default false)
   * @param {number}  [captureOptions.maxSide=1920]
   * @param {number}  [captureOptions.quality=0.92]
   * @param {string}  [captureOptions.name]
   */
  async function capture(facingMode, captureOptions = {}) {
    isCapturingRef.value = true
    try {
      const validFacing = typeof facingMode === 'string' && facingMode.trim()
        ? facingMode.trim()
        : facingModeRef.value

      if (!isActiveRef.value) {
        await startCamera(validFacing)
      }

      const fileName = captureOptions.name || `photo-${Date.now()}.jpg`

      const result = await captureFrameAsFile(fileName, {
        format: captureOptions.format || 'image/jpeg',
        quality: captureOptions.quality ?? 0.92,
        maxSide: captureOptions.maxSide || 1920,
        mirror: captureOptions.mirror ?? isUserCamera.value,
      })

      if (captureOptions.storePreview !== false) {
        setImage(result.file, result.preview, result.width, result.height)
      }

      if (!captureOptions.keepStreamAlive) {
        await stopCamera()
      }

      return result
    } finally {
      isCapturingRef.value = false
    }
  }

  async function selectFromDevice(file) {
    if (!file) return null

    if (!file.type.startsWith('image/')) {
      throw i18nError('wp.image.error_invalid', 'Invalid image file')
    }

    if (options.maxBytes && file.size > options.maxBytes) {
      const size = Math.round(options.maxBytes / 1048576)

      throw i18nError('wp.image.error_too_large', `Image exceeds ${size} MB`, { size })
    }

    const preview = URL.createObjectURL(file)

    return new Promise((resolve, reject) => {
      const img = new Image()

      img.onload = () => {
        const width = img.naturalWidth || img.width
        const height = img.naturalHeight || img.height

        setImage(file, preview, width, height)
        resolve({ file, preview, width, height })
      }
      img.onerror = () => {
        URL.revokeObjectURL(preview)
        reject(i18nError('wp.image.error_invalid', 'Invalid image file'))
      }
      img.src = preview
    })
  }

  /**
   * Rotation changes which pixels the crop rectangle refers to, so it resets:
   * a stale rectangle after a turn would crop the wrong part of the photo.
   */
  function rotate(degrees = 90) {
    rotationRef.value = normaliseRotation(rotationRef.value + degrees)
    cropRectRef.value = null
  }

  function resetRotation() {
    rotationRef.value = 0
    cropRectRef.value = null
  }

  function remove() {
    if (previewRef.value) {
      URL.revokeObjectURL(previewRef.value)
    }
    imageRef.value = null
    previewRef.value = ''
    widthRef.value = 0
    heightRef.value = 0
    rotationRef.value = 0
    cropRectRef.value = null
  }

  function reset() {
    remove()
    stopCamera()
  }

  function setCropRect(rect) {
    cropRectRef.value = rect
  }

  function resetCrop() {
    cropRectRef.value = null
  }

  /**
   * Bake rotation, then crop, into a new file. Order matters: the crop
   * rectangle is expressed against the image AS DISPLAYED (already rotated),
   * which is what the user actually dragged on.
   */
  async function getProcessedFile() {
    if (!imageRef.value || !previewRef.value) {
      throw i18nError('wp.image.error_no_image', 'No image selected')
    }

    const rotation = normaliseRotation(rotationRef.value)

    if (rotation === 0 && !cropRectRef.value) {
      return { file: imageRef.value, width: widthRef.value, height: heightRef.value }
    }

    const image = new Image()

    image.src = previewRef.value
    await image.decode()

    const sourceWidth = image.naturalWidth || image.width
    const sourceHeight = image.naturalHeight || image.height
    const rotated = rotatedSize(sourceWidth, sourceHeight, rotation)

    // 1. rotate (quarter turns only, so the result is exact and never has empty corners)
    const rotatedCanvas = document.createElement('canvas')

    rotatedCanvas.width = rotated.width
    rotatedCanvas.height = rotated.height

    const rotatedCtx = rotatedCanvas.getContext('2d')

    if (!rotatedCtx) throw i18nError('wp.image.error_process_failed', 'Unable to process image')

    rotatedCtx.translate(rotated.width / 2, rotated.height / 2)
    rotatedCtx.rotate((rotation * Math.PI) / 180)
    rotatedCtx.drawImage(image, -sourceWidth / 2, -sourceHeight / 2)

    // 2. crop
    let finalCanvas = rotatedCanvas

    if (cropRectRef.value) {
      const { x, y, width, height } = cropRectRef.value
      const normalised = x <= 1 && y <= 1 && width <= 1 && height <= 1
      const cropX = Math.max(0, Math.round(normalised ? x * rotated.width : x))
      const cropY = Math.max(0, Math.round(normalised ? y * rotated.height : y))
      const cropW = Math.max(1, Math.min(rotated.width - cropX, Math.round(normalised ? width * rotated.width : width)))
      const cropH = Math.max(1, Math.min(rotated.height - cropY, Math.round(normalised ? height * rotated.height : height)))

      finalCanvas = document.createElement('canvas')
      finalCanvas.width = cropW
      finalCanvas.height = cropH

      const cropCtx = finalCanvas.getContext('2d')

      if (!cropCtx) throw i18nError('wp.image.error_process_failed', 'Unable to process image')

      cropCtx.drawImage(rotatedCanvas, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH)
    }

    const mimeType = OUTPUT_TYPES.includes(imageRef.value.type) ? imageRef.value.type : 'image/jpeg'

    const blob = await new Promise((resolve, reject) => {
      finalCanvas.toBlob(
        value => value
          ? resolve(value)
          : reject(i18nError('wp.image.error_process_failed', 'Unable to process image')),
        mimeType,
        0.92,
      )
    })

    // Safari cannot encode WebP and silently returns PNG: keep the name honest.
    const baseName = (imageRef.value.name || 'photo').replace(/\.[^/.]+$/, '')

    const extension = extensionForMime(blob.type)
    const outWidth = finalCanvas.width
    const outHeight = finalCanvas.height

    if (finalCanvas !== rotatedCanvas) {
      finalCanvas.width = 0
      finalCanvas.height = 0
    }
    rotatedCanvas.width = 0
    rotatedCanvas.height = 0

    return {
      file: new File([blob], extension ? `${baseName}.${extension}` : baseName, { type: blob.type }),
      width: outWidth,
      height: outHeight,
    }
  }

  function dispose() {
    if (previewRef.value) {
      URL.revokeObjectURL(previewRef.value)
      previewRef.value = ''
    }
    disposeCamera()
  }

  if (getCurrentInstance()) {
    onUnmounted(dispose)
  }

  return {
    imageRef,
    previewRef,
    widthRef,
    heightRef,
    videoRef,
    streamRef,
    permissionStateRef,
    isCapturingRef,
    isCameraActiveRef,
    isActiveRef,
    isLoadingRef,
    errorRef,
    rotationRef,
    cropRectRef,
    facingModeRef,
    hasImageRef,
    hasCamera,
    hasRealMultipleCameras,
    canSwitchFacingModeRef,
    hasMultipleCameras,
    isEnvironmentCamera,
    isUserCamera,
    isUserCameraRef: isUserCamera,
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
    selectCamera,
    startCamera,
    stopCamera,
    switchCamera,
    switchFacingMode,
    captureFrame,
    captureFrameAsFile,
    enumerateCameras,
    checkPermission,
    init,
    dispose,
    capture,
    setImage,
    selectFromDevice,
    rotate,
    resetRotation,
    setCropRect,
    resetCrop,
    remove,
    reset,
    getProcessedFile,
    CAMERA_FACING_MODES,
    PERMISSION_STATES,
  }
}
