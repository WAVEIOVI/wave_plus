import { computed } from 'vue'
import { useDisplay } from 'vuetify'

/**
 * useResponsive
 * ─────────────────────────────────────────────────────────────────────────────
 * Thin wrapper around Vuetify's `useDisplay` exposing the breakpoints
 * relevant to WAVE PLUS ERP list pages.
 *
 * Usage:
 *   const { isMobile, isTablet, isDesktop, smAndDown, mdAndDown } = useResponsive()
 *
 * @returns {{
 *   isMobile: import('vue').ComputedRef<boolean>,
 *   isTablet: import('vue').ComputedRef<boolean>,
 *   isDesktop: import('vue').ComputedRef<boolean>,
 *   smAndDown: import('vue').Ref<boolean>,
 *   mdAndDown: import('vue').Ref<boolean>,
 *   lgAndUp: import('vue').Ref<boolean>,
 *   name: import('vue').Ref<string>,
 * }}
 */
export function useResponsive() {
  const { smAndDown, mdAndDown, lgAndUp, name, xs, sm, md } = useDisplay()

  const isMobile = computed(() => smAndDown.value)
  const isTablet = computed(() => md.value)
  const isDesktop = computed(() => lgAndUp.value)

  return {
    isMobile,
    isTablet,
    isDesktop,
    smAndDown,
    mdAndDown,
    lgAndUp,
    xs,
    sm,
    md,
    name,
  }
}
