/**
 * Edit-page layout presets
 * ─────────────────────────────────────────────────────────────────────────────
 * Same layout component (`WpEditPageLayout`), three presets inspired by the
 * Client Edit "journal" page:
 *
 *  standard   tab navigation, one section visible at a time, footer on change
 *  developed  sidebar + scroll-spy feed (desktop) / scrollable tabs (mobile)
 *  deeper     developed + grouped secondary sections, per-section badges,
 *             permanent footer and a wider sidebar
 */
export const EDIT_PRESETS = Object.freeze({
  standard: {
    nav: 'tabs',
    footer: 'dirty', // 'dirty' | 'always'
    sidebarWidth: 220,
    contentMaxWidth: 1040,
    sectionHeaders: false,
    groupSecondary: false,
  },
  developed: {
    nav: 'sidebar',
    footer: 'always',
    sidebarWidth: 240,
    contentMaxWidth: 1200,
    sectionHeaders: true,
    groupSecondary: false,
  },
  deeper: {
    nav: 'sidebar',
    footer: 'always',
    sidebarWidth: 280,
    contentMaxWidth: 1320,
    sectionHeaders: true,
    groupSecondary: true,
  },
})

export const EDIT_VARIANTS = Object.freeze(Object.keys(EDIT_PRESETS))

export function resolveEditPreset(name, overrides = {}) {
  const base = EDIT_PRESETS[name] || EDIT_PRESETS.standard
  const clean = Object.fromEntries(Object.entries(overrides).filter(([, v]) => v !== undefined))

  return { ...base, ...clean, name: EDIT_PRESETS[name] ? name : 'standard' }
}
