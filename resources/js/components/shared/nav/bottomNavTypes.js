// ─────────────────────────────────────────────────────────────────────────────
// Bottom Nav — Shared types for the Soft Floating Pill Bottom Navigation
// ─────────────────────────────────────────────────────────────────────────────
// Fully typed via JSDoc — IDEs surface these as real types everywhere the
// pieces are imported. Conventions follow the Vuexy 9 JS edition.

/**
 * A single navigation item for `AppBottomNav`.
 *
 * At least one of `to`, `href` or `value` should be provided so the item can
 * be identified when selected.
 *
 * @typedef   {object} BottomNavItem
 * @property  {string}  title      Visible label (short, 1–2 words work best)
 * @property  {string}  icon       Tabler / MDI icon name, e.g. `tabler-home`
 * @property  {string|import('vue-router').RouteLocationRaw} [to]  Router location
 * @property  {string}  [href]     External URL (renders an `<a>`)
 * @property  {string|number} [value] Explicit identity (used as the v-model value)
 * @property  {string|number} [badge] Badge content (a count or short text); boolean `true` renders a dot
 * @property  {boolean} [disabled] Disable the item
 */

/**
 * Mode after item normalization — internal only (adds a stable `key`).
 *
 * @typedef   {BottomNavItem & { key: string|number }} NormalizedBottomNavItem
 */

/** Gap (px) between the pill bar and the bottom viewport edge. */
export const BOTTOM_NAV_FLOAT_GAP = 12

/** Recommended max number of items (design guideline, not enforced hard). */
export const BOTTOM_NAV_MAX_ITEMS = 5

/** Scroll distance (px) downward before `hideOnScroll` kicks in. */
export const BOTTOM_NAV_SCROLL_THRESHOLD = 24
