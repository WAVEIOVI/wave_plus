/**
 * Document scanning helpers built on OpenCV.js.
 *
 * Pure functions: no Vue, no component state. Every function receives the
 * OpenCV namespace (`cv`) explicitly, so this file is easy to unit test and
 * never touches `window.cv` itself (see useOpenCv.js for loading).
 *
 * Corners are always NORMALISED ({ x, y } in 0..1, relative to the source
 * image) and ordered top-left, top-right, bottom-right, bottom-left.
 */

export const ORIENTATION = {
  AUTO: 'auto',
  PORTRAIT: 'portrait',
  LANDSCAPE: 'landscape',
}

export const SCAN_FILTER = {
  ORIGINAL: 'original',
  ENHANCED: 'enhanced',
  GRAYSCALE: 'grayscale',
  BLACK_WHITE: 'bw',
}

export const A4_RATIO = 210 / 297

/** A4 at ~200 dpi: good legibility for contracts without huge files. */
export const EXPORT_LONG_SIDE = 2339

/** Long side used while the user is tweaking crop / filters. */
export const PREVIEW_LONG_SIDE = 1100

/** Long side of the little filter thumbnails. */
export const THUMB_LONG_SIDE = 220

/** Manual brightness / contrast, both -50..50. */
export const DEFAULT_ADJUST = Object.freeze({ brightness: 0, contrast: 0 })

const DETECT_MAX_SIDE = 320
const MIN_AREA_RATIO = 0.10
const MAX_AREA_RATIO = 0.96
const MIN_SIDE_RATIO = 0.15
const APPROX_EPSILONS = [0.015, 0.025, 0.038, 0.05]
const MIN_CORNER_ANGLE = 50
const MAX_CORNER_ANGLE = 130
const MAX_SIDE_ASPECT = 2.4
const MIN_DYNAMIC_RANGE = 35

export const DEFAULT_CORNERS = [
  { x: 0.06, y: 0.06 },
  { x: 0.94, y: 0.06 },
  { x: 0.94, y: 0.94 },
  { x: 0.06, y: 0.94 },
]

let detectCanvas = null
let warpInputCanvas = null

function makeCanvas(width, height) {
  const canvas = document.createElement('canvas')

  canvas.width = width
  canvas.height = height

  return canvas
}

/** OpenCV mats live in WASM memory: they MUST be deleted manually. */
function createTracker() {
  const items = []

  return {
    track(item) {
      items.push(item)

      return item
    },
    release() {
      items.forEach(item => {
        try {
          item.delete()
        } catch {
          // already deleted
        }
      })
    },
  }
}

function getSourceSize(source) {
  return {
    width: source.videoWidth || source.naturalWidth || source.width || 0,
    height: source.videoHeight || source.naturalHeight || source.height || 0,
  }
}

function distance(a, b, width = 1, height = 1) {
  return Math.hypot((a.x - b.x) * width, (a.y - b.y) * height)
}

// ---- pure geometry (unit-tested, no OpenCV) -----------------------------------

/** Sort 4 points into TL, TR, BR, BL. Returns null for degenerate input. */
export function orderCorners(points) {
  if (!points || points.length !== 4) return null

  const sum = point => point.x + point.y
  const diff = point => point.y - point.x

  const tl = points.reduce((a, b) => (sum(b) < sum(a) ? b : a))
  const br = points.reduce((a, b) => (sum(b) > sum(a) ? b : a))
  const tr = points.reduce((a, b) => (diff(b) < diff(a) ? b : a))
  const bl = points.reduce((a, b) => (diff(b) > diff(a) ? b : a))
  const ordered = [tl, tr, br, bl]

  return new Set(ordered).size === 4 ? ordered : null
}

/**
 * Rotate the content by quarter turns (clockwise) by shifting which source
 * corner lands on which output corner.
 */
export function rotateCorners(corners, quarterTurns = 0) {
  const turns = ((quarterTurns % 4) + 4) % 4

  return corners.map((_, index) => corners[(index - turns + 4) % 4])
}

/** Landscape if the detected quad is clearly wider than tall. */
export function suggestOrientation(corners, width, height) {
  const [tl, tr, br, bl] = corners
  const w = (distance(tl, tr, width, height) + distance(bl, br, width, height)) / 2
  const h = (distance(tl, bl, width, height) + distance(tr, br, width, height)) / 2

  return w > h * 1.1 ? ORIENTATION.LANDSCAPE : ORIENTATION.PORTRAIT
}

/** Shoelace area of a polygon, in the units of its coordinates. */
export function polygonArea(points) {
  let area = 0

  for (let i = 0; i < points.length; i++) {
    const a = points[i]
    const b = points[(i + 1) % points.length]

    area += a.x * b.y - b.x * a.y
  }

  return Math.abs(area) / 2
}

/**
 * A quad is convex when every turn goes the same way. A crossed ("bow-tie")
 * quad would make the perspective warp produce garbage, so the crop editor
 * refuses moves that break convexity.
 */
export function isConvexQuad(points, width = 1, height = 1) {
  if (!points || points.length !== 4) return false

  let sign = 0

  for (let i = 0; i < 4; i++) {
    const a = points[i]
    const b = points[(i + 1) % 4]
    const c = points[(i + 2) % 4]
    const cross = (b.x - a.x) * width * (c.y - b.y) * height - (b.y - a.y) * height * (c.x - b.x) * width

    if (Math.abs(cross) < 1e-9) return false

    const current = Math.sign(cross)

    if (sign && current !== sign) return false
    sign = current
  }

  return true
}

function cornerAngle(prev, corner, next, width, height) {
  const ax = (prev.x - corner.x) * width
  const ay = (prev.y - corner.y) * height
  const bx = (next.x - corner.x) * width
  const by = (next.y - corner.y) * height
  const cos = (ax * bx + ay * by) / ((Math.hypot(ax, ay) * Math.hypot(bx, by)) || 1)

  return Math.acos(Math.max(-1, Math.min(1, cos))) * 180 / Math.PI
}

/**
 * Plausible sheet of paper seen with some perspective: convex, sides not tiny,
 * corners roughly square, opposite sides not wildly different.
 */
export function isPlausibleSheet(corners, width, height) {
  if (!isConvexQuad(corners, width, height)) return false

  const minSide = Math.min(width, height) * MIN_SIDE_RATIO
  const sides = corners.map((point, index) => distance(point, corners[(index + 1) % 4], width, height))

  if (!sides.every(side => side >= minSide)) return false
  if (Math.max(sides[0], sides[2]) / Math.min(sides[0], sides[2]) > MAX_SIDE_ASPECT) return false
  if (Math.max(sides[1], sides[3]) / Math.min(sides[1], sides[3]) > MAX_SIDE_ASPECT) return false

  return corners.every((corner, index) => {
    const angle = cornerAngle(corners[(index + 3) % 4], corner, corners[(index + 1) % 4], width, height)

    return angle >= MIN_CORNER_ANGLE && angle <= MAX_CORNER_ANGLE
  })
}

/**
 * Exponential moving average with deadband + jump snap for live corner overlays.
 * Prevents jitter while remaining responsive when the camera moves to a new page.
 */
export function smoothCorners(prevCorners, nextCorners, width = 1, height = 1) {
  if (!nextCorners || nextCorners.length !== 4) return null
  if (!prevCorners || prevCorners.length !== 4) return nextCorners.map(pt => ({ x: pt.x, y: pt.y }))

  const diag = Math.hypot(width, height) || 1
  const maxDrift = Math.max(...nextCorners.map((pt, i) => distance(pt, prevCorners[i], width, height)))

  // Large jump (new document or rapid camera move): snap immediately
  if (maxDrift > diag * 0.14) {
    return nextCorners.map(pt => ({ x: pt.x, y: pt.y }))
  }

  // Tiny sub-pixel jitter: hold steady
  if (maxDrift < diag * 0.005) {
    return prevCorners.map(pt => ({ x: pt.x, y: pt.y }))
  }

  const alpha = maxDrift > diag * 0.04 ? 0.55 : 0.35

  return nextCorners.map((pt, i) => ({
    x: prevCorners[i].x + (pt.x - prevCorners[i].x) * alpha,
    y: prevCorners[i].y + (pt.y - prevCorners[i].y) * alpha,
  }))
}

/**
 * Check whether two 4-corner sets are within a stability tolerance.
 */
export function areCornersStable(prevCorners, nextCorners, width = 1, height = 1, thresholdRatio = 0.018) {
  if (!prevCorners || !nextCorners || prevCorners.length !== 4 || nextCorners.length !== 4) {
    return false
  }

  const limit = (Math.hypot(width, height) || 1) * thresholdRatio

  return nextCorners.every((pt, i) => distance(pt, prevCorners[i], width, height) < limit)
}

// ---- detection ------------------------------------------------------------------

/** Convex hull first (survives curled / rounded corners), then loosen epsilon until 4 points remain. */
function approximateQuad(cv, contour) {
  const hull = new cv.Mat()
  const approx = new cv.Mat()

  try {
    cv.convexHull(contour, hull, false, true)

    const perimeter = cv.arcLength(hull, true)

    for (const epsilon of APPROX_EPSILONS) {
      cv.approxPolyDP(hull, approx, epsilon * perimeter, true)
      if (approx.rows === 4) return Array.from(approx.data32S)
      if (approx.rows < 4) return null
    }

    return null
  } finally {
    hull.delete()
    approx.delete()
  }
}

/**
 * Best plausible quad found in one binary image.
 * Inspects all contours by area first so small noise contours at earlier raster
 * indices never hide the actual document contour.
 */
function bestQuadIn(cv, binary, w, h, track) {
  const contours = track(new cv.MatVector())
  const hierarchy = track(new cv.Mat())

  // RETR_EXTERNAL retrieves only outer boundaries, eliminating hundreds of inner text contours
  cv.findContours(binary, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE)

  const frameArea = w * h
  const minArea = frameArea * MIN_AREA_RATIO
  const maxArea = frameArea * MAX_AREA_RATIO
  const total = contours.size()
  const candidates = []

  try {
    for (let i = 0; i < total; i++) {
      const contour = contours.get(i)
      const area = cv.contourArea(contour)

      if (area < minArea || area > maxArea) {
        contour.delete()
        continue
      }

      candidates.push({ contour, area })
    }

    candidates.sort((a, b) => b.area - a.area)

    let best = null
    let bestScore = 0
    const maxCandidates = Math.min(candidates.length, 12)

    for (let i = 0; i < maxCandidates; i++) {
      const { contour, area } = candidates[i]

      if (area * 1.08 <= bestScore) break

      const raw = approximateQuad(cv, contour)

      if (!raw) continue

      const ordered = orderCorners([
        { x: raw[0] / w, y: raw[1] / h },
        { x: raw[2] / w, y: raw[3] / h },
        { x: raw[4] / w, y: raw[5] / h },
        { x: raw[6] / w, y: raw[7] / h },
      ])

      if (!ordered || !isPlausibleSheet(ordered, w, h)) continue

      // Reject quads where >= 3 corners hug the very outer border of the camera viewport
      const borderHugs = ordered.filter(
        pt => pt.x <= 0.015 || pt.x >= 0.985 || pt.y <= 0.015 || pt.y >= 0.985,
      ).length

      if (borderHugs >= 3) continue

      const score = area * (borderHugs === 0 ? 1.08 : 0.95)

      if (score > bestScore) {
        best = ordered
        bestScore = score
      }
    }

    return best
  } finally {
    for (const item of candidates) {
      try {
        item.contour.delete()
      } catch {
        // ignore if already deleted
      }
    }
  }
}

function readCanvasToMat(cv, canvas, ctx, track) {
  if (typeof cv.matFromImageData === 'function' && ctx && typeof ctx.getImageData === 'function') {
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)

    return track(cv.matFromImageData(imageData))
  }

  return track(cv.imread(canvas))
}

/**
 * Find the biggest sheet-of-paper-like quad in the frame.
 * Works on a downscaled copy so it is cheap enough to run ~6-7 times/second.
 *
 * Strategy order matters:
 *  1. Standard Canny edges (45, 135) + morphological close.
 *  2. Low-contrast Canny edges (22, 75) + morphological close for light paper on light desk.
 *  3. Otsu threshold + open/close for high-contrast sheet segmentation.
 *
 * @returns {Array<{x:number,y:number}>|null} normalised, ordered corners
 */
export function detectDocumentCorners(cv, source) {
  const { width, height } = getSourceSize(source)

  if (!width || !height) return null

  const scale = Math.min(1, DETECT_MAX_SIDE / Math.max(width, height))
  const w = Math.max(1, Math.round(width * scale))
  const h = Math.max(1, Math.round(height * scale))

  if (!detectCanvas) detectCanvas = makeCanvas(w, h)
  if (detectCanvas.width !== w || detectCanvas.height !== h) {
    detectCanvas.width = w
    detectCanvas.height = h
  }

  const detectCtx = detectCanvas.getContext('2d', { willReadFrequently: true })

  detectCtx.drawImage(source, 0, 0, w, h)

  const { track, release } = createTracker()

  try {
    const src = readCanvasToMat(cv, detectCanvas, detectCtx, track)
    const gray = track(new cv.Mat())
    const blurred = track(new cv.Mat())
    const kernel = track(cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(5, 5)))

    cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY)
    cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0, 0, cv.BORDER_DEFAULT)

    // Guard against dark / covered camera frames where Otsu would hallucinate a quad
    const { minVal, maxVal } = cv.minMaxLoc(blurred)
    if (maxVal - minVal < MIN_DYNAMIC_RANGE) return null

    // 1. Primary Canny edges
    const edges = track(new cv.Mat())
    const closed = track(new cv.Mat())

    cv.Canny(blurred, edges, 45, 135)
    cv.morphologyEx(edges, closed, cv.MORPH_CLOSE, kernel)

    const fromEdges = bestQuadIn(cv, closed, w, h, track)

    if (fromEdges) return fromEdges

    // 2. Soft Canny edges for low-contrast paper on light surfaces
    cv.Canny(blurred, edges, 22, 75)
    cv.morphologyEx(edges, closed, cv.MORPH_CLOSE, kernel)

    const fromSoftEdges = bestQuadIn(cv, closed, w, h, track)

    if (fromSoftEdges) return fromSoftEdges

    // 3. Bright sheet on a darker surface
    const bright = track(new cv.Mat())
    const cleaned = track(new cv.Mat())

    cv.threshold(blurred, bright, 0, 255, cv.THRESH_BINARY | cv.THRESH_OTSU)
    cv.morphologyEx(bright, cleaned, cv.MORPH_OPEN, kernel)

    return bestQuadIn(cv, cleaned, w, h, track)
  } finally {
    release()
  }
}

// ---- warp & filters ---------------------------------------------------------------

/**
 * Perspective-correct the quad into a flat A4 page ("auto align").
 * Downscales oversized inputs when rendering interactive previews so WASM memory
 * stays small and mobile browsers stay responsive.
 *
 * @returns {HTMLCanvasElement}
 */
export function warpDocument(cv, source, corners, { orientation = ORIENTATION.PORTRAIT, longSide = EXPORT_LONG_SIDE } = {}) {
  const shortSide = Math.round(longSide * A4_RATIO)
  const [outW, outH] = orientation === ORIENTATION.LANDSCAPE ? [longSide, shortSide] : [shortSide, longSide]
  const rawSize = getSourceSize(source)
  const maxInputSide = Math.round(longSide * 1.35)
  let warpSource = source
  let width = rawSize.width
  let height = rawSize.height

  if (Math.max(width, height) > maxInputSide && typeof document !== 'undefined') {
    const scale = maxInputSide / Math.max(width, height)
    const scaledW = Math.max(1, Math.round(width * scale))
    const scaledH = Math.max(1, Math.round(height * scale))

    if (!warpInputCanvas) warpInputCanvas = makeCanvas(scaledW, scaledH)
    if (warpInputCanvas.width !== scaledW || warpInputCanvas.height !== scaledH) {
      warpInputCanvas.width = scaledW
      warpInputCanvas.height = scaledH
    }

    const inputCtx = warpInputCanvas.getContext('2d')

    inputCtx.drawImage(source, 0, 0, scaledW, scaledH)
    warpSource = warpInputCanvas
    width = scaledW
    height = scaledH
  }

  const { track, release } = createTracker()

  try {
    const src = track(cv.imread(warpSource))
    const dst = track(new cv.Mat())

    const from = track(cv.matFromArray(
      4,
      1,
      cv.CV_32FC2,
      corners.flatMap(point => [point.x * width, point.y * height]),
    ))

    const to = track(cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, outW, 0, outW, outH, 0, outH]))
    const transform = track(cv.getPerspectiveTransform(from, to))

    cv.warpPerspective(src, dst, transform, new cv.Size(outW, outH), cv.INTER_LINEAR, cv.BORDER_REPLICATE, new cv.Scalar())

    const out = makeCanvas(outW, outH)

    cv.imshow(out, dst)

    return out
  } finally {
    release()
  }
}

/**
 * Estimate the paper's lighting so shadows / uneven light can be divided out.
 * Dilate removes dark ink, blur smooths the result.
 *
 * Two safeguards keep stamps, logos and photos intact:
 *  - the dilate window scales with the page, so large solid areas survive;
 *  - the estimate never drops below 60% of the brightest paper level, so a
 *    solid dark region is not "flattened" to white.
 */
function estimateBackground(cv, src, track) {
  const small = track(new cv.Mat())
  const background = track(new cv.Mat())
  const smallW = Math.max(1, Math.round(src.cols / 4))
  const smallH = Math.max(1, Math.round(src.rows / 4))
  const windowSize = Math.max(9, Math.round(Math.min(smallW, smallH) * 0.12) | 1)
  const kernel = track(cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(windowSize, windowSize)))

  cv.resize(src, small, new cv.Size(smallW, smallH), 0, 0, cv.INTER_AREA)
  cv.dilate(small, small, kernel)
  cv.GaussianBlur(small, small, new cv.Size(9, 9), 0, 0, cv.BORDER_DEFAULT)

  const luma = track(new cv.Mat())

  if (small.channels() === 1) {
    small.copyTo(luma)
  } else {
    cv.cvtColor(small, luma, cv.COLOR_RGB2GRAY)
  }

  const floorValue = Math.round(cv.minMaxLoc(luma).maxVal * 0.6)
  const floor = track(new cv.Mat(smallH, smallW, small.type(), new cv.Scalar(floorValue, floorValue, floorValue, 0)))

  cv.max(small, floor, small)
  cv.resize(small, background, new cv.Size(src.cols, src.rows), 0, 0, cv.INTER_LINEAR)

  return background
}

function isNeutralAdjust(adjust) {
  return !adjust || (!adjust.brightness && !adjust.contrast)
}

/**
 * Apply a scan filter (+ optional manual brightness / contrast).
 * Returns a NEW canvas unless nothing needs doing (the input is left untouched so
 * the caller can cache the un-filtered page and switch filters instantly).
 *
 * @param {{ brightness?: number, contrast?: number }} [adjust]  each -50..50
 */
export function applyScanFilter(cv, canvas, filter = SCAN_FILTER.ORIGINAL, adjust = DEFAULT_ADJUST) {
  if (filter === SCAN_FILTER.ORIGINAL && isNeutralAdjust(adjust)) return canvas

  const { track, release } = createTracker()

  try {
    const rgba = track(cv.imread(canvas))
    const out = makeCanvas(canvas.width, canvas.height)
    let result = track(new cv.Mat())

    if (filter === SCAN_FILTER.ORIGINAL) {
      // 3 channels on purpose: convertTo(alpha, beta) below must not touch the alpha channel
      cv.cvtColor(rgba, result, cv.COLOR_RGBA2RGB)
    } else if (filter === SCAN_FILTER.ENHANCED) {
      const rgb = track(new cv.Mat())
      const flattened = track(new cv.Mat())

      cv.cvtColor(rgba, rgb, cv.COLOR_RGBA2RGB)
      cv.divide(rgb, estimateBackground(cv, rgb, track), flattened, 255, -1)
      flattened.convertTo(result, -1, 1.15, -18)
    } else {
      const gray = track(new cv.Mat())
      const flattened = track(new cv.Mat())

      cv.cvtColor(rgba, gray, cv.COLOR_RGBA2GRAY)
      cv.divide(gray, estimateBackground(cv, gray, track), flattened, 255, -1)

      if (filter === SCAN_FILTER.BLACK_WHITE) {
        // Black & white is already maximal contrast: manual adjust does not apply.
        cv.threshold(flattened, result, 0, 255, cv.THRESH_BINARY | cv.THRESH_OTSU)
      } else {
        flattened.convertTo(result, -1, 1.1, -10)
      }
    }

    if (!isNeutralAdjust(adjust) && filter !== SCAN_FILTER.BLACK_WHITE) {
      const adjusted = track(new cv.Mat())
      const alpha = 1 + (adjust.contrast || 0) / 100

      // contrast pivots around mid-grey; brightness is a plain offset (-75..75)
      const beta = 128 * (1 - alpha) + (adjust.brightness || 0) * 1.5

      result.convertTo(adjusted, -1, alpha, beta)
      result = adjusted
    }

    cv.imshow(out, result)

    return out
  } finally {
    release()
  }
}

// ---- canvas utilities ---------------------------------------------------------------

/** Downscale a canvas so its long side is at most `longSide` (returns the input if already small). */
export function scaleCanvas(canvas, longSide) {
  const scale = Math.min(1, longSide / Math.max(canvas.width, canvas.height))

  if (scale >= 1) return canvas

  const out = makeCanvas(Math.max(1, Math.round(canvas.width * scale)), Math.max(1, Math.round(canvas.height * scale)))
  const ctx = out.getContext('2d')

  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(canvas, 0, 0, out.width, out.height)

  return out
}

/**
 * Decode an image File into a canvas, honouring EXIF orientation (phone photos
 * are otherwise imported sideways) and capping the long side to protect memory.
 */
export async function fileToCanvas(file, maxSide = 3000) {
  let source
  let objectUrl = null

  try {
    if (typeof createImageBitmap === 'function') {
      source = await createImageBitmap(file, { imageOrientation: 'from-image' })
    }
  } catch {
    source = null
  }

  if (!source) {
    objectUrl = URL.createObjectURL(file)
    source = new Image()
    source.src = objectUrl
    await source.decode()
  }

  try {
    const width = source.width || source.naturalWidth
    const height = source.height || source.naturalHeight
    const scale = Math.min(1, maxSide / Math.max(width, height))
    const canvas = makeCanvas(Math.max(1, Math.round(width * scale)), Math.max(1, Math.round(height * scale)))

    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height)

    return canvas
  } finally {
    source.close?.()
    if (objectUrl) URL.revokeObjectURL(objectUrl)
  }
}

export function canvasToBlob(canvas, quality = 0.9, type = 'image/jpeg') {
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => (blob ? resolve(blob) : reject(new Error('Canvas export failed'))), type, quality)
  })
}

export function releaseScanEngineBuffers() {
  if (detectCanvas) {
    detectCanvas.width = 0
    detectCanvas.height = 0
    detectCanvas = null
  }
  if (warpInputCanvas) {
    warpInputCanvas.width = 0
    warpInputCanvas.height = 0
    warpInputCanvas = null
  }
}
