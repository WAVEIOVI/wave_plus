// ─────────────────────────────────────────────────────────────────────────────
// useBottomNav — Shared logic for the Soft Floating Pill Bottom Navigation
// ─────────────────────────────────────────────────────────────────────────────
// Provides:
//   • useBottomNavItems:    normalizes items + derives each item's value/key
//   • useBottomNavActive:   resolves the active value (v-model ←→ router)
//   • useBottomNavScroll:   hide-on-scroll behavior for the floating bar
//
// `useBottomNavScroll` now delegates to the FAB system's `useFabScrollHide`
// (previously a verbatim copy) so both floating bars share one implementation
// while keeping their own default threshold.
// ─────────────────────────────────────────────────────────────────────────────

import { BOTTOM_NAV_SCROLL_THRESHOLD } from '@/components/shared/nav/bottomNavTypes'
import { useFabScrollHide } from '@/composables/useFabSystem'

/**
 * Normalize the `items` prop:
 *   • guarantees a stable `key` per item (value → id-like fallback → index)
 *   • drops falsy / malformed entries
 *
 * @param   {import('vue').MaybeRefOrGetter<import('@/components/shared/nav/bottomNavTypes').BottomNavItem[]>} items
 * @returns {import('vue').ComputedRef<import('@/components/shared/nav/bottomNavTypes').NormalizedBottomNavItem[]>}
 */
export function useBottomNavItems(items) {
  return computed(() => {
    const list = toValue(items) ?? []

    return list
      .filter(item => Boolean(item) && typeof item === 'object')
      .map((item, index) => ({
        ...item,
        key: item.value ?? item.to ?? item.href ?? `bottom-nav-item-${index}`,
      }))
  })
}

/**
 * Resolve the currently active item's key.
 *
 * Priority:
 *   1. explicit `modelValue` (v-model)
 *   2. current route matching an item's `to` (full path match)
 *   3. current route name matching an item's `to` (route { name } form)
 *
 * @param   {object}                                              [options]
 * @param   {import('vue').MaybeRefOrGetter<*>}                   [options.modelValue]  v-model value
 * @param   {import('vue').ComputedRef<NormalizedBottomNavItem[]>} options.items        normalized item list
 * @returns {import('vue').ComputedRef<string|number|null>}
 */
export function useBottomNavActive({ modelValue, items }) {
  const route = useRoute()

  return computed(() => {
    // 1. Explicit v-model wins
    const explicit = toValue(modelValue)
    if (explicit !== undefined && explicit !== null)
      return explicit

    // 2/3. Router-driven highlight
    if (route) {
      const byPath = items.value.find(item => typeof item.to === 'string' && item.to === route.path)
      if (byPath)
        return byPath.key

      const byName = items.value.find(item =>
        typeof item.to === 'object' && item.to !== null && item.to.name === route.name)

      if (byName)
        return byName.key
    }

    return null
  })
}

/**
 * Hide the floating bar while scrolling down, reveal on scroll up / at top.
 * Delegates to `useFabScrollHide` (single shared implementation for both
 * floating bars) with the nav's own default threshold.
 *
 * @param   {import('vue').MaybeRefOrGetter<boolean>} enabled `hideOnScroll` prop
 * @param   {number} [threshold]                              Scroll distance in px before hiding
 * @returns {{ hidden: import('vue').Ref<boolean>, scrollY: import('vue').Ref<number> }}
 */
export function useBottomNavScroll(enabled, threshold = BOTTOM_NAV_SCROLL_THRESHOLD) {
  return useFabScrollHide(enabled, threshold)
}
