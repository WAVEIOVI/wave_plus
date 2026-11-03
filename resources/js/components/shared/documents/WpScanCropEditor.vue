<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { isConvexQuad } from '@/composables/documents/scanEngine'

/**
 * Four-corner crop editor.
 * v-model = [{ x, y } * 4] NORMALISED (0..1) and ordered TL, TR, BR, BL,
 * so it is independent from the displayed size.
 *
 * Handles: 4 corners + 4 edge midpoints (dragging an edge moves both of its
 * corners). A move that would cross the quad over itself is refused, because a
 * bow-tie makes the perspective warp produce garbage.
 */
const props = defineProps({
  src: { type: String, required: true },
  ratio: { type: Number, default: 1 },
  modelValue: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const LOUPE_SIZE = 96
const LOUPE_ZOOM = 2.2
const KEY_STEP = 0.005
const KEY_STEP_LARGE = 0.02

const boxRef = ref(null)
const frameRef = ref(null)
const frameStyle = ref({})
const frameSizeRef = ref({ width: 0, height: 0 })
const activeKeyRef = ref(null)

let resizeObserver = null
let drag = null

function layoutFrame() {
  const box = boxRef.value

  if (!box || !props.ratio) return

  const boxW = box.clientWidth
  const boxH = box.clientHeight

  let width = boxW
  let height = boxW / props.ratio

  if (height > boxH) {
    height = boxH
    width = boxH * props.ratio
  }

  width = Math.floor(width)
  height = Math.floor(height)

  frameStyle.value = { width: `${width}px`, height: `${height}px` }
  frameSizeRef.value = { width, height }
}

const toPoints = corners => corners.map(point => `${point.x * 100},${point.y * 100}`)

const polygonPoints = computed(() => toPoints(props.modelValue).join(' '))

const maskPath = computed(() => {
  const [first, ...rest] = toPoints(props.modelValue)

  return `M0,0H100V100H0Z M${first} ${rest.map(point => `L${point}`).join(' ')} Z`
})

const handles = computed(() => {
  const corners = props.modelValue

  return [
    ...corners.map((point, index) => ({ key: `c${index}`, kind: 'corner', index, x: point.x, y: point.y })),
    ...corners.map((point, index) => {
      const next = corners[(index + 1) % 4]

      return { key: `e${index}`, kind: 'edge', index, x: (point.x + next.x) / 2, y: (point.y + next.y) / 2 }
    }),
  ]
})

function handleLabel(handle) {
  return handle.kind === 'corner'
    ? t('wp.scanner.corner_label', { n: handle.index + 1 }, `Corner ${handle.index + 1}`)
    : t('wp.scanner.edge_label', { n: handle.index + 1 }, `Edge ${handle.index + 1}`)
}

/**
 * Magnifier loupe: zoomed view of the image under the active corner, so the
 * user can drop it exactly on the paper edge even on a small screen.
 * It floats above the handle (below it when the handle sits near the top edge).
 * Positions are PHYSICAL (left/top) on purpose: they map to image pixels, which
 * never mirror in RTL.
 */
const loupeStyleRef = computed(() => {
  const handle = handles.value.find(item => item.key === activeKeyRef.value)

  if (!handle || handle.kind !== 'corner') return { display: 'none' }

  const { width, height } = frameSizeRef.value

  if (!width || !height) return { display: 'none' }

  const bgW = width * LOUPE_ZOOM
  const bgH = height * LOUPE_ZOOM
  const x = Math.min(Math.max(handle.x, 0.14), 0.86) * 100
  const offsetUp = handle.y > 0.22

  return {
    left: `${x}%`,
    top: `${handle.y * 100}%`,
    transform: offsetUp ? 'translate(-50%, calc(-100% - 28px))' : 'translate(-50%, 28px)',
    backgroundImage: `url("${props.src}")`,
    backgroundPosition: `${-(handle.x * bgW - LOUPE_SIZE / 2)}px ${-(handle.y * bgH - LOUPE_SIZE / 2)}px`,
    backgroundSize: `${bgW}px ${bgH}px`,
  }
})

const clamp = value => Math.min(1, Math.max(0, value))

/** Corner indices a handle moves. */
function movedIndices(handle) {
  return handle.kind === 'corner' ? [handle.index] : [handle.index, (handle.index + 1) % 4]
}

function hasMinimumCornerSeparation(corners, minGap = 0.04) {
  for (let i = 0; i < 4; i++) {
    const a = corners[i]
    const b = corners[(i + 1) % 4]

    if (Math.hypot(a.x - b.x, a.y - b.y) < minGap) return false
  }

  return true
}

/**
 * Apply a normalised delta to the handle's corners (starting from `base`),
 * honouring bounds and convexity. Falls back to the X-only / Y-only part of the
 * move so the handle slides along an obstacle instead of freezing on it.
 */
function applyDelta(handle, base, dx, dy) {
  const indices = movedIndices(handle)
  const { width, height } = frameSizeRef.value

  const build = (moveX, moveY) => base.map((corner, index) => (indices.includes(index)
    ? { x: clamp(corner.x + moveX), y: clamp(corner.y + moveY) }
    : corner))

  const candidates = [build(dx, dy), build(dx, 0), build(0, dy)]

  return candidates.find(
    candidate => isConvexQuad(candidate, width || 1, height || 1) && hasMinimumCornerSeparation(candidate),
  ) || null
}

function commit(corners) {
  if (corners) emit('update:modelValue', corners)
}

function pointerToNormalised(event) {
  if (!frameRef.value) return { x: 0, y: 0 }
  const rect = frameRef.value.getBoundingClientRect()

  return {
    x: (event.clientX - rect.left) / (rect.width || 1),
    y: (event.clientY - rect.top) / (rect.height || 1),
  }
}

function onPointerDown(handle, event) {
  // The finger's offset from the handle centre is preserved (drag works on deltas), so the handle never jumps.
  drag = {
    key: handle.key,
    handle,
    start: pointerToNormalised(event),
    base: props.modelValue.map(corner => ({ ...corner })),
  }
  activeKeyRef.value = handle.key
  event.currentTarget.setPointerCapture(event.pointerId)
}

function onPointerMove(handle, event) {
  if (!drag || drag.key !== handle.key) return

  const point = pointerToNormalised(event)

  commit(applyDelta(drag.handle, drag.base, point.x - drag.start.x, point.y - drag.start.y))
}

function onPointerUp(handle, event) {
  if (!drag || drag.key !== handle.key) return
  drag = null
  activeKeyRef.value = null
  event.currentTarget.releasePointerCapture?.(event.pointerId)
}

function onKeydown(handle, event) {
  const step = event.shiftKey ? KEY_STEP_LARGE : KEY_STEP
  const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }
  const move = moves[event.key]

  if (!move) return
  event.preventDefault()
  commit(applyDelta(handle, props.modelValue, move[0], move[1]))
}

watch(() => props.ratio, () => nextTick(layoutFrame))

onMounted(() => {
  layoutFrame()
  resizeObserver = new ResizeObserver(layoutFrame)
  resizeObserver.observe(boxRef.value)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
})
</script>

<template>
  <div class="wp-crop">
    <div
      ref="boxRef"
      class="wp-crop__box"
    >
      <div
        ref="frameRef"
        class="wp-crop__frame"
        :style="frameStyle"
      >
        <img
          :src="src"
          class="wp-crop__img"
          draggable="false"
          alt=""
        >

        <svg
          class="wp-crop__svg"
          viewBox="0 0 100 100"
          preserveAspectRatio="none"
          aria-hidden="true"
        >
          <path
            :d="maskPath"
            class="wp-crop__mask"
            fill-rule="evenodd"
          />
          <polygon
            :points="polygonPoints"
            class="wp-crop__outline-halo"
          />
          <polygon
            :points="polygonPoints"
            class="wp-crop__outline"
          />
        </svg>

        <div
          class="wp-crop__loupe"
          :style="loupeStyleRef"
          aria-hidden="true"
        />

        <div
          v-for="handle in handles"
          :key="handle.key"
          class="wp-crop__handle"
          :class="[
            `wp-crop__handle--${handle.kind}`,
            { 'wp-crop__handle--active': activeKeyRef === handle.key },
          ]"
          :style="{ left: `${handle.x * 100}%`, top: `${handle.y * 100}%` }"
          role="slider"
          tabindex="0"
          :aria-label="handleLabel(handle)"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="Math.round(handle.x * 100)"
          :aria-valuetext="`${Math.round(handle.x * 100)}%, ${Math.round(handle.y * 100)}%`"
          @pointerdown.prevent="onPointerDown(handle, $event)"
          @pointermove="onPointerMove(handle, $event)"
          @pointerup="onPointerUp(handle, $event)"
          @pointercancel="onPointerUp(handle, $event)"
          @keydown="onKeydown(handle, $event)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wp-crop {
  position: absolute;
  inset: 0;
}

.wp-crop__box {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  inset: 20px;
}

.wp-crop__frame {
  position: relative;
  user-select: none;
}

.wp-crop__img,
.wp-crop__svg {
  position: absolute;
  block-size: 100%;
  inline-size: 100%;
  inset: 0;
}

.wp-crop__img {
  object-fit: fill;
  pointer-events: none;
}

.wp-crop__svg {
  pointer-events: none;
}

.wp-crop__mask {
  fill: rgba(0, 0, 0, 55%);
}

/* Dark under-stroke + coloured stroke: readable on white paper AND dark desks. */
.wp-crop__outline-halo,
.wp-crop__outline {
  fill: none;
  stroke-linejoin: round;
  vector-effect: non-scaling-stroke;
}

.wp-crop__outline-halo {
  stroke: rgba(0, 0, 0, 55%);
  stroke-width: 5;
}

.wp-crop__outline {
  stroke: rgb(var(--v-theme-primary));
  stroke-width: 2.5;
}

/* Zoomed preview shown while dragging a corner. */
.wp-crop__loupe {
  position: absolute;
  z-index: 3;
  border: 2px solid rgb(var(--v-theme-primary));
  border-radius: 50%;
  background-color: #000;
  background-repeat: no-repeat;
  block-size: 96px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 55%);
  inline-size: 96px;
  pointer-events: none;
}

.wp-crop__loupe::before,
.wp-crop__loupe::after {
  position: absolute;
  content: "";
  inset-block-start: 50%;
  inset-inline-start: 50%;
  transform: translate(-50%, -50%);
}

/* crosshair + ring */
.wp-crop__loupe::before {
  background:
    linear-gradient(#fff, #fff) center / 100% 1px no-repeat,
    linear-gradient(#fff, #fff) center / 1px 100% no-repeat;
  block-size: 22px;
  inline-size: 22px;
  mix-blend-mode: difference;
}

.wp-crop__loupe::after {
  border: 1px solid rgba(255, 255, 255, 80%);
  border-radius: 50%;
  block-size: 12px;
  inline-size: 12px;
}

/* 44px touch target, 18px visible dot */
.wp-crop__handle {
  position: absolute;
  z-index: 2;
  display: grid;
  block-size: 44px;
  cursor: grab;
  inline-size: 44px;
  place-items: center;
  touch-action: none;
  transform: translate(-50%, -50%);
}

.wp-crop__handle::before {
  border: 3px solid rgb(var(--v-theme-primary));
  border-radius: 50%;
  background: #fff;
  block-size: 18px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 45%);
  content: "";
  inline-size: 18px;
  transition: transform 0.12s ease;
}

/* Edge handles are quieter: a small pill, so corners stay the visual anchors. */
.wp-crop__handle--edge::before {
  border-width: 2px;
  block-size: 12px;
  inline-size: 12px;
  opacity: 0.9;
}

/* Soft halo while dragging, so the active handle is obvious. */
.wp-crop__handle::after {
  position: absolute;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.18);
  block-size: 40px;
  content: "";
  inline-size: 40px;
  opacity: 0;
  transition: opacity 0.12s ease;
}

.wp-crop__handle--active {
  cursor: grabbing;
}

.wp-crop__handle--active::before {
  transform: scale(1.35);
}

.wp-crop__handle--active::after {
  opacity: 1;
}

.wp-crop__handle:focus-visible {
  outline: none;
}

.wp-crop__handle:focus-visible::before {
  outline: 2px solid #fff;
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  .wp-crop__handle::before,
  .wp-crop__handle::after {
    transition: none;
  }
}
</style>
