// ─────────────────────────────────────────────────────────────────────────────
// useFabSystem — Shared logic for the FAB system (Vue 3 + Vuetify 3)
// ─────────────────────────────────────────────────────────────────────────────
// Provides:
//   • useFabMode:       resolves the requested mode to an actual render mode
//   • useFabScrollHide: hide the FAB while scrolling down, reveal on scroll up
//   • useFabSafeArea:   reads safe-area inset values for notched / home-bar devices
//   • useFabKeyboard:   detects the virtual keyboard via viewport heuristics
//   • useFabVisibility: combined hide/show signal (scroll + keyboard)
//   • normalizeFabActions: tolerates `label`/`title` aliases + applies fallbacks
//
// Types live in `@/components/shared/fab/fabTypes` (JSDoc).
// Auto-imported by unplugin-auto-import (vite.config.js `composables/**` glob) —
// no explicit import needed inside SFCs.
// ─────────────────────────────────────────────────────────────────────────────

import { FAB_KEYBOARD_THRESHOLD, FAB_SCROLL_HIDE_THRESHOLD } from '@/components/shared/fab/fabTypes'

/**
 * Resolve the requested `mode` to an actual render mode.
 *
 * `auto` behaviour:
 *   0 actions → `nested`      (render nothing — consumer nests custom actions)
 *   1 action  → `single`      (one plain FAB)
 *   2–4       → `speed-dial`  (AppFabSpeedDial)
 *   5+        → `sheet`       (AppQuickActionsSheet)
 *
 * @param   {import('vue').MaybeRefOrGetter<import('@/components/shared/fab/fabTypes').FabMode>} mode
 * @param   {import('vue').MaybeRefOrGetter<number>} count
 * @returns {import('vue').ComputedRef<import('@/components/shared/fab/fabTypes').FabResolvedMode>}
 */
export function useFabMode(mode, count) {
  return computed(() => {
    const m = toValue(mode)
    const n = toValue(count)

    if (m === 'auto') {
      if (n <= 0)
        return 'nested'

      if (n === 1)
        return 'single'

      if (n <= 4)
        return 'speed-dial'

      return 'sheet'
    }

    if (m === 'speed-dial')
      return 'speed-dial'

    if (m === 'sheet')
      return 'sheet'

    if (m === 'nested')
      return 'nested'

    return 'single'
  })
}

/**
 * Hide the FAB while scrolling down, reveal again on scroll up / at top.
 *
 * Uses `useWindowScroll` from `@vueuse/core` (already a project dependency)
 * so it works for any scroll-container setup.
 *
 * @param   {import('vue').MaybeRefOrGetter<boolean>} enabled  `hideOnScroll` prop
 * @param   {number} [threshold]                              Scroll distance in px before hiding
 * @returns {{ hidden: import('vue').Ref<boolean>, scrollY: import('vue').Ref<number> }}
 */
export function useFabScrollHide(enabled, threshold = FAB_SCROLL_HIDE_THRESHOLD) {
  const enabledRef = toRef(enabled)
  const { y: scrollY } = useWindowScroll()
  const hidden = ref(false)

  watch(scrollY, (current, previous) => {
    if (!enabledRef.value)
      return

    if (current <= threshold || current < previous)
      hidden.value = false
    else if (current > previous)
      hidden.value = true
  })

  // Disable → always visible
  watch(enabledRef, value => {
    if (!value)
      hidden.value = false
  })

  return { hidden, scrollY }
}

/**
 * Read CSS safe-area insets (`env(safe-area-inset-*)`) so the FAB can sit
 * above notches / home indicator bars on mobile devices.
 *
 * Values are plain numbers in px (0 when the feature is unsupported).
 *
 * @returns {{ safeAreaTop: import('vue').Ref<number>, safeAreaBottom: import('vue').Ref<number> }}
 */
export function useFabSafeArea() {
  const safeAreaTop = ref(0)
  const safeAreaBottom = ref(0)

  /** @type {HTMLDivElement | null} */
  let probe = null

  const measure = () => {
    if (typeof window === 'undefined')
      return

    if (!probe) {
      probe = document.createElement('div')
      probe.setAttribute('aria-hidden', 'true')
      probe.style.cssText = 'position:fixed;visibility:hidden;pointer-events:none;height:0'
      document.body.appendChild(probe)
    }

    // Probe pinned to the top: offset = env(safe-area-inset-top)
    probe.style.top = 'env(safe-area-inset-top, 0px)'
    probe.style.bottom = 'auto'

    const topPx = probe.getBoundingClientRect().top

    // Probe pinned to the bottom: gap = viewport height − bottom edge position
    probe.style.top = 'auto'
    probe.style.bottom = 'env(safe-area-inset-bottom, 0px)'

    const bottomPx = window.innerHeight - probe.getBoundingClientRect().bottom

    safeAreaTop.value = Math.max(0, Math.round(topPx))
    safeAreaBottom.value = Math.max(0, Math.round(bottomPx))
  }

  onMounted(() => {
    measure()

    // Orientation changes trigger new insets
    window.addEventListener('orientationchange', measure)
    window.addEventListener('resize', measure)
  })

  onUnmounted(() => {
    window.removeEventListener('orientationchange', measure)
    window.removeEventListener('resize', measure)
    probe?.remove()
    probe = null
  })

  return { safeAreaTop, safeAreaBottom }
}

/**
 * Detect the virtual keyboard on mobile devices via viewport heuristics.
 *
 * Rely on the `visualViewport` API where available (iOS ≥ 13, modern Android),
 * falling back to window-resize deltas on older browsers.
 *
 * @param   {number} [threshold] Viewport shrink (px) treated as "keyboard open"
 * @returns {{ keyboardOpen: import('vue').Ref<boolean>, keyboardHeight: import('vue').Ref<number> }}
 */
export function useFabKeyboard(threshold = FAB_KEYBOARD_THRESHOLD) {
  const keyboardOpen = ref(false)
  const keyboardHeight = ref(0)

  let baseline = typeof window !== 'undefined'
    ? window.visualViewport?.height ?? window.innerHeight
    : 0

  /** Guard so we only measure once per animation frame. */
  let rafPending = false

  const update = () => {
    if (rafPending)
      return

    rafPending = true

    requestAnimationFrame(() => {
      rafPending = false

      const vv = window.visualViewport
      const viewportHeight = vv ? vv.height : window.innerHeight
      const delta = baseline - viewportHeight

      // Viewport shrink beyond the threshold ≈ keyboard opened
      if (delta > threshold) {
        keyboardOpen.value = true
        keyboardHeight.value = Math.round(delta)
      }
      else if (delta < 50) {
        keyboardOpen.value = false
        keyboardHeight.value = 0
      }

      // Refresh the reference height while the keyboard is closed so rotation,
      // URL-bar changes, etc. don't get misread as a keyboard.
      if (!keyboardOpen.value)
        baseline = viewportHeight
    })
  }

  onMounted(() => {
    window.visualViewport?.addEventListener('resize', update)
    window.addEventListener('resize', update)
  })

  onUnmounted(() => {
    window.visualViewport?.removeEventListener('resize', update)
    window.removeEventListener('resize', update)
  })

  return { keyboardOpen, keyboardHeight }
}

/**
 * Combined visibility signal: hide the FAB when scrolling down (optional) or
 * when the virtual keyboard is open (always).
 *
 * @param   {object}                                        [options]
 * @param   {import('vue').MaybeRefOrGetter<boolean>}       [options.hideOnScroll]  Enable scroll-hide
 * @param   {import('vue').MaybeRefOrGetter<boolean>}       [options.forceVisible]  When true, never hide
 * @param   {number}                                        [options.scrollThreshold]
 * @returns {{ hidden: import('vue').ComputedRef<boolean>, scrollY: import('vue').Ref<number> }}
 */
export function useFabVisibility(options = {}) {
  const hideOnScroll = toRef(() => options.hideOnScroll ?? true)
  const forceVisible = toRef(() => options.forceVisible ?? false)

  const { hidden: scrollHidden, scrollY } = useFabScrollHide(hideOnScroll, options.scrollThreshold)
  const { keyboardOpen } = useFabKeyboard()

  const hidden = computed(() => !forceVisible.value && (keyboardOpen.value || scrollHidden.value))

  return { hidden, keyboardOpen, scrollHidden, scrollY }
}

/**
 * Normalize an action list:
 *   • maps the legacy `label` alias onto `title`
 *   • drops falsy / malformed entries
 *   • guarantees an `id` (index-based fallback)
 *   • wires `onClick` through a uniform handler shape
 *
 * Accepts an array (or a getter) and returns a normalized reactive copy.
 *
 * @param   {import('vue').MaybeRefOrGetter<import('@/components/shared/fab/fabTypes').FabAction[]>} actions
 * @param   {{ fallbackColor?: string }} [options]  Applied when the action has no color of its own
 * @returns {import('vue').ComputedRef<Required<import('@/components/shared/fab/fabTypes').FabAction>[]>}
 */
export function normalizeFabActions(actions, options = {}) {
  return computed(() => {
    const list = toValue(actions) ?? []

    return list
      .filter(a => Boolean(a) && typeof a === 'object')
      .map((action, index) => ({
        id: action.id ?? `fab-action-${index}`,
        icon: action.icon ?? 'tabler-point',
        title: action.title ?? action.label ?? action.id ?? `Action ${index + 1}`,
        label: action.label,
        color: action.color ?? options.fallbackColor,
        description: action.description,
        to: action.to,
        disabled: action.disabled ?? false,
        onClick: action.onClick,
        badge: action.badge,
        favorite: action.favorite ?? false,
      }))
  })
}
