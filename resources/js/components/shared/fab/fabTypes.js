// ─────────────────────────────────────────────────────────────────────────────
// Fab — Shared types for the Floating Action Button system
// ─────────────────────────────────────────────────────────────────────────────
// Fully typed via JSDoc — IDEs surface these as real types everywhere the
// pieces are imported. Conventions follow the Vuexy 9 JS edition
// (resources/js/@core/types.js pattern).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * A single FAB action item.
 *
 * Used by `AppFab`, `AppFabSpeedDial` and `AppQuickActionsSheet`.
 * Actions whose `to` property is set are rendered as router links
 * (via VBtn/VListItem `:to`).
 *
 * @typedef   {object} FabAction
 * @property  {string}                        id          Unique identifier (returned in `action-click`)
 * @property  {string}                        [icon]      Tabler / MDI icon name, e.g. `tabler-qrcode`
 * @property  {string}                        [title]     Visible label — sheet list, speed-dial tooltip, grid card
 * @property  {string}                        [label]     Alias of `title` (back-compat with `AppFab` v1)
 * @property  {string}                        [color]     Vuetify color name or CSS color (overrides components' default)
 * @property  {string}                        [description] Optional short description, rendered as subtitle
 * @property  {string}                        [to]        Router location — renders as a link instead of a button
 * @property  {boolean}                       [disabled]  Disable the action
 * @property  {(action: FabAction) => void}   [onClick]   Per-action click handler (still emits `action-click`)
 * @property  {number|string}                 [badge]     Optional badge (recents, items count...)
 * @property  {boolean}                       [favorite]  Marks the action as a favorite (Favorites tab)
 */

/**
 * Nested category node used by `AppNestedActionsSheet`.
 *
 * A category may contain both `actions` and further `categories`
 * (sub-categories), forming a hierarchy displayed as accordion groups.
 *
 * @typedef   {object} FabCategory
 * @property  {string}        id           Unique identifier
 * @property  {string}        title        Category display name
 * @property  {string}        [icon]       Tabler / MDI icon name
 * @property  {string}        [description] Short description shown under the title
 * @property  {string}        [color]      Vuetify color for the avatar/icon
 * @property  {FabAction[]}   [actions]    Directly-owned actions (depth 1)
 * @property  {FabCategory[]} [categories] Nested sub-categories (deeper levels)
 * @property  {string|number} [badge]      Badge text/count (e.g. "High Priority", "3 items") — auto-counted when omitted
 * @property  {'success'|'info'|'warning'|'error'|'primary'|'secondary'} [badgeColor] Vuetify color for the badge chip
 * @property  {boolean}       [expanded]   Initially expanded state
 * @property  {boolean}       [disabled]   Disable the whole group
 */

/**
 * One tab inside `AppQuickActionsSheet` (Favorites | All Tools | Recents...).
 *
 * @typedef   {object}                FabSheetTab
 * @property  {string}                id      Tab identifier
 * @property  {string}                title   Tab label
 * @property  {string}                [icon]  Tab icon (shown before the label)
 * @property  {FabAction[]}           [actions]  Actions shown under this tab
 * @property  {((a: FabAction) => boolean)} [filter]  Optional predicate over the full action set — takes precedence over `actions`
 */

/**
 * Full-width secondary action pinned at the bottom of `AppQuickActionsSheet`
 * (e.g. "Share Workspace Link").
 *
 * @typedef   {object}                FabSecondaryAction
 * @property  {string}                id        Unique identifier
 * @property  {string}                title     Button label
 * @property  {string}                [icon]    Button icon
 * @property  {string}                [color]   Vuetify color (default `secondary`)
 * @property  {string}                [variant] Vuetify button variant (default `tonal`)
 */

/**
 * Requested FAB display mode.
 * - `auto`        resolve from actions count (1 → single, 2–4 → speed-dial, 5+ → sheet)
 * - `speed-dial`  force speed dial
 * - `sheet`       force quick-actions sheet
 * - `nested`      force component rendered nothing itself (custom nesting)
 *
 * @typedef {'auto' | 'speed-dial' | 'sheet' | 'nested'} FabMode
 */

/**
 * Mode after `auto` resolution has been applied.
 *
 * @typedef {'single' | 'speed-dial' | 'sheet' | 'nested'} FabResolvedMode
 */

/** Keyboard/viewport threshold: viewport shrink > this (px) ≈ keyboard open. */
export const FAB_KEYBOARD_THRESHOLD = 150

/** Scroll distance (px) downward before `hideOnScroll` kicks in. */
export const FAB_SCROLL_HIDE_THRESHOLD = 24

/** Vuetify sizes considered valid for the main FAB. */
export const FAB_SIZES = ['x-small', 'small', 'medium', 'large']
