/**
 * Wizard presets
 * ─────────────────────────────────────────────────────────────────────────────
 * One wizard engine (`useWizard` + `WpWizardDialog`), four presets. A preset only
 * tunes presentation and workflow defaults; the caller still owns the steps.
 *
 *  fast       Quick tasks · ≤3 short steps · compact dialog · progress bar
 *  standard   Normal create/edit · clear titled steps · stepper header
 *  developed  Multi-section · conditional fields & steps · dependent selects
 *  deeper     Complex workflows · nested sections · side rail · review step
 *
 * Keys
 *  width               dialog max-width (px)
 *  stepperStyle        'bar' | 'steps' | 'rail'
 *  review              append the auto-generated review step
 *  nested              nested sections are expected (informational)
 *  showStepDescription show step descriptions in the stepper / body
 *  allowJump           'none' | 'visited' | 'completed' — stepper navigation policy
 *  persistent          protected close (informational, dialog is always protected when dirty)
 *  density             field density passed to WpFormField
 *  transition          'slide' | 'fade' | 'none' — step change animation
 *  stableHeight        keep the dialog from shrinking/growing between steps
 *  celebrate           show a short in-dialog success state before closing
 *  shortcutHints       show the "Ctrl/⌘ + Enter" hint in the footer (pointer devices)
 *  requiredProgress    show a required-fields meter per step in the rail
 */
export const WIZARD_TRANSITIONS = Object.freeze(['slide', 'fade', 'none'])

export const WIZARD_PRESETS = Object.freeze({
  fast: {
    width: 520,
    stepperStyle: 'bar',
    review: false,
    nested: false,
    showStepDescription: false,
    allowJump: 'none',
    persistent: false,
    density: 'comfortable',
    transition: 'fade',
    stableHeight: false,
    celebrate: false,
    shortcutHints: false,
    requiredProgress: false,
  },
  standard: {
    width: 720,
    stepperStyle: 'steps',
    review: false,
    nested: false,
    showStepDescription: true,
    allowJump: 'visited',
    persistent: false,
    density: 'comfortable',
    transition: 'slide',
    stableHeight: true,
    celebrate: false,
    shortcutHints: true,
    requiredProgress: false,
  },
  developed: {
    width: 920,
    stepperStyle: 'steps',
    review: false,
    nested: false,
    showStepDescription: true,
    allowJump: 'visited',
    persistent: true,
    density: 'comfortable',
    transition: 'slide',
    stableHeight: true,
    celebrate: true,
    shortcutHints: true,
    requiredProgress: false,
  },
  deeper: {
    width: 1120,
    stepperStyle: 'rail',
    review: true,
    nested: true,
    showStepDescription: true,
    allowJump: 'completed',
    persistent: true,
    density: 'default',
    transition: 'slide',
    stableHeight: false, // the rail layout already has a fixed height
    celebrate: true,
    shortcutHints: true,
    requiredProgress: true,
  },
})

export const WIZARD_VARIANTS = Object.freeze(Object.keys(WIZARD_PRESETS))

/**
 * Merge a preset with caller overrides (undefined overrides are ignored).
 *
 * @param {string} variant
 * @param {Record<string, any>} [overrides]
 */
export function resolveWizardPreset(variant, overrides = {}) {
  const base = WIZARD_PRESETS[variant] || WIZARD_PRESETS.standard
  const clean = Object.fromEntries(Object.entries(overrides || {}).filter(([, v]) => v !== undefined))

  return { ...base, ...clean, variant: WIZARD_PRESETS[variant] ? variant : 'standard' }
}

/** Size presets for the zoom / expanded dialog. */
export const ZOOM_SIZES = Object.freeze({
  small: { width: 480, height: '60vh' },
  medium: { width: 720, height: '70vh' },
  large: { width: 1000, height: '80vh' },
  xl: { width: 1280, height: '88vh' },
  full: { width: '96vw', height: '94vh' },
})
