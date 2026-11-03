/**
 * Pure geometry for image editing. No Vue, no DOM.
 *
 * Two users:
 *  - WpImageDialog  -> free / aspect-locked crop rectangle (`moveRect`, `resizeRect`)
 *  - WpAvatarDialog -> square "cover" crop with zoom, rotation and clamped pan
 *                      (`coverLayout`, `clampPan`)
 *
 * Rect = { x, y, width, height } in PIXELS of the bounds box ({ width, height }).
 * Doing the maths in pixels (not 0..1) is what makes a "1:1" crop truly square
 * on a non-square image.
 */

export const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

export function normaliseRotation(degrees = 0) {
  return ((Math.round(degrees / 90) * 90) % 360 + 360) % 360
}

export function isQuarterTurn(degrees) {
  const rotation = normaliseRotation(degrees)

  return rotation === 90 || rotation === 270
}

/** Image size after rotating by a multiple of 90 degrees. */
export function rotatedSize(width, height, degrees) {
  return isQuarterTurn(degrees) ? { width: height, height: width } : { width, height }
}

/** Largest box with `ratio` (w / h) that fits inside bounds. */
export function fitContain(boundsWidth, boundsHeight, ratio) {
  if (!boundsWidth || !boundsHeight || !ratio) return { width: 0, height: 0 }

  let width = boundsWidth
  let height = width / ratio

  if (height > boundsHeight) {
    height = boundsHeight
    width = height * ratio
  }

  return { width: Math.floor(width), height: Math.floor(height) }
}

// ---- crop rectangle ---------------------------------------------------------

export const CROP_HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']

/**
 * Centered rect covering `fill` of the bounds, optionally locked to an aspect
 * ratio (w / h).
 */
export function createCenteredRect(bounds, { aspect = null, fill = 0.9 } = {}) {
  let width = bounds.width * fill
  let height = bounds.height * fill

  if (aspect) {
    if (width / height > aspect) width = height * aspect
    else height = width / aspect
  }

  return { x: (bounds.width - width) / 2, y: (bounds.height - height) / 2, width, height }
}

export function moveRect(rect, dx, dy, bounds) {
  return {
    ...rect,
    x: clamp(rect.x + dx, 0, Math.max(0, bounds.width - rect.width)),
    y: clamp(rect.y + dy, 0, Math.max(0, bounds.height - rect.height)),
  }
}

/** Re-shape an existing rect to `aspect`, keeping its centre, staying inside bounds. */
export function applyAspect(rect, aspect, bounds) {
  if (!aspect) return rect

  const cx = rect.x + rect.width / 2
  const cy = rect.y + rect.height / 2
  let width = rect.width
  let height = width / aspect

  if (height > rect.height) {
    height = rect.height
    width = height * aspect
  }

  const maxWidth = 2 * Math.min(cx, bounds.width - cx)
  const maxHeight = 2 * Math.min(cy, bounds.height - cy)
  const shrink = Math.min(1, maxWidth / width, maxHeight / height)

  width *= shrink
  height *= shrink

  return { x: cx - width / 2, y: cy - height / 2, width, height }
}

/**
 * Drag one of the 8 handles by (dx, dy). The opposite side stays put.
 * With `aspect` (w / h) the shape is locked.
 */
export function resizeRect(rect, handle, dx, dy, bounds, { aspect = null, minSize = 32 } = {}) {
  const right = rect.x + rect.width
  const bottom = rect.y + rect.height
  const movesLeft = handle.includes('w')
  const movesRight = handle.includes('e')
  const movesTop = handle.includes('n')
  const movesBottom = handle.includes('s')
  const isCorner = handle.length === 2

  if (!aspect) {
    let left = movesLeft ? clamp(rect.x + dx, 0, right - minSize) : rect.x
    let top = movesTop ? clamp(rect.y + dy, 0, bottom - minSize) : rect.y
    let rightEdge = movesRight ? clamp(right + dx, left + minSize, bounds.width) : right
    let bottomEdge = movesBottom ? clamp(bottom + dy, top + minSize, bounds.height) : bottom

    left = Math.min(left, rightEdge - minSize)
    top = Math.min(top, bottomEdge - minSize)
    rightEdge = Math.max(rightEdge, left + minSize)
    bottomEdge = Math.max(bottomEdge, top + minSize)

    return { x: left, y: top, width: rightEdge - left, height: bottomEdge - top }
  }

  const minWidth = Math.max(minSize, minSize * aspect)

  if (isCorner) {
    const anchorX = movesLeft ? right : rect.x
    const anchorY = movesTop ? bottom : rect.y
    const pointerX = (movesLeft ? rect.x : right) + dx
    const pointerY = (movesTop ? rect.y : bottom) + dy
    const signX = movesLeft ? -1 : 1
    const signY = movesTop ? -1 : 1

    let width = Math.max(0, (pointerX - anchorX) * signX)
    let height = Math.max(0, (pointerY - anchorY) * signY)

    width = Math.max(width, height * aspect, minWidth)
    height = width / aspect

    const availableWidth = signX > 0 ? bounds.width - anchorX : anchorX
    const availableHeight = signY > 0 ? bounds.height - anchorY : anchorY
    const shrink = Math.min(1, availableWidth / width, availableHeight / height)

    width *= shrink
    height *= shrink

    return {
      x: signX > 0 ? anchorX : anchorX - width,
      y: signY > 0 ? anchorY : anchorY - height,
      width,
      height,
    }
  }

  // edge handle: resize on one axis, the other axis follows around the centre
  const cx = rect.x + rect.width / 2
  const cy = rect.y + rect.height / 2

  if (movesLeft || movesRight) {
    // the opposite vertical edge stays fixed, so it limits how wide we can grow
    const room = movesRight ? bounds.width - rect.x : right
    let width = clamp(movesRight ? rect.width + dx : rect.width - dx, minWidth, Math.max(minWidth, room))
    let height = width / aspect
    const maxHeight = 2 * Math.min(cy, bounds.height - cy)

    if (height > maxHeight) {
      height = maxHeight
      width = height * aspect
    }

    return { x: movesRight ? rect.x : right - width, y: cy - height / 2, width, height }
  }

  const room = movesBottom ? bounds.height - rect.y : bottom
  let height = clamp(movesBottom ? rect.height + dy : rect.height - dy, minWidth / aspect, Math.max(minWidth / aspect, room))
  let width = height * aspect
  const maxWidth = 2 * Math.min(cx, bounds.width - cx)

  if (width > maxWidth) {
    width = maxWidth
    height = width / aspect
  }

  return { x: cx - width / 2, y: movesBottom ? rect.y : bottom - height, width, height }
}

export function rectToNormalised(rect, bounds) {
  return {
    x: rect.x / bounds.width,
    y: rect.y / bounds.height,
    width: rect.width / bounds.width,
    height: rect.height / bounds.height,
  }
}

export function normalisedToRect(rect, bounds) {
  return {
    x: rect.x * bounds.width,
    y: rect.y * bounds.height,
    width: rect.width * bounds.width,
    height: rect.height * bounds.height,
  }
}

// ---- avatar: square cover-fit crop ----------------------------------------------

/**
 * Layout of an image inside a square viewport of `size` px so that it always
 * COVERS the square (no empty corners). zoom 1 = just covers.
 *
 * Element geometry (matches the canvas export 1:1):
 *   drawWidth/drawHeight are the UNROTATED image dimensions in viewport px.
 *   Centre sits at (size/2 + pan.x, size/2 + pan.y); rotation is about the centre.
 */
export function coverLayout({ imageWidth, imageHeight, rotation = 0, zoom = 1, size }) {
  const rotated = rotatedSize(imageWidth, imageHeight, rotation)
  const base = Math.max(size / rotated.width, size / rotated.height)
  const scale = base * Math.max(1, zoom)

  return {
    scale,
    drawWidth: imageWidth * scale,
    drawHeight: imageHeight * scale,
    maxPanX: Math.max(0, (rotated.width * scale - size) / 2),
    maxPanY: Math.max(0, (rotated.height * scale - size) / 2),
  }
}

export function clampPan(pan, layout) {
  return {
    x: clamp(pan.x, -layout.maxPanX, layout.maxPanX) || 0,
    y: clamp(pan.y, -layout.maxPanY, layout.maxPanY) || 0,
  }
}
