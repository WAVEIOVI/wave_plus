/* eslint-disable camelcase */
/**
 * WAVE PLUS — Canonical status visual language
 * ─────────────────────────────────────────────────────────────────────────────
 * A single source of truth mapping every domain status to a Vuetify colour and
 * an icon, so statuses look identical wherever they appear (lists, cards,
 * detail headers, timelines). Colours intentionally stay restrained: success /
 * info / warning / error / primary / secondary, matching the existing Vuexy
 * theme tokens.
 *
 * Usage:
 *   resolveWpStatusMeta('vehicle', 'maintenance') // { color: 'warning', icon: 'tabler-tool' }
 */

export const WP_STATUS_REGISTRY = Object.freeze({
  department: {
    active: { color: 'success', icon: 'tabler-building-community' },
    inactive: { color: 'secondary', icon: 'tabler-building' },
  },

  position: {
    active: { color: 'success', icon: 'tabler-briefcase' },
    inactive: { color: 'secondary', icon: 'tabler-briefcase-off' },
  },

  team: {
    active: { color: 'success', icon: 'tabler-users-group' },
    inactive: { color: 'secondary', icon: 'tabler-users-off' },
  },

  employee: {
    active: { color: 'success', icon: 'tabler-user-check' },
    on_leave: { color: 'info', icon: 'tabler-calendar-time' },
    suspended: { color: 'warning', icon: 'tabler-user-pause' },
    terminated: { color: 'error', icon: 'tabler-user-x' },
  },

  vehicle: {
    available: { color: 'success', icon: 'tabler-circle-check' },
    in_use: { color: 'info', icon: 'tabler-steering-wheel' },
    maintenance: { color: 'warning', icon: 'tabler-tool' },
    out_of_service: { color: 'error', icon: 'tabler-alert-triangle' },
    rental_active: { color: 'primary', icon: 'tabler-key' },
    client_use: { color: 'secondary', icon: 'tabler-building-bank' },
    retired: { color: 'secondary', icon: 'tabler-archive' },
    sold: { color: 'secondary', icon: 'tabler-receipt' },
  },

  driver: {
    active: { color: 'success', icon: 'tabler-id-badge-2' },
    pending: { color: 'info', icon: 'tabler-clock-hour-4' },
    suspended: { color: 'warning', icon: 'tabler-user-pause' },
    expired: { color: 'error', icon: 'tabler-calendar-x' },
    revoked: { color: 'error', icon: 'tabler-ban' },
  },

  attendance: {
    present: { color: 'success', icon: 'tabler-circle-check' },
    late: { color: 'warning', icon: 'tabler-clock-exclamation' },
    absent: { color: 'error', icon: 'tabler-circle-x' },
    partial: { color: 'info', icon: 'tabler-clock-half' },
    excused: { color: 'secondary', icon: 'tabler-file-check' },
  },

  leave: {
    pending: { color: 'warning', icon: 'tabler-clock-hour-4' },
    submitted: { color: 'warning', icon: 'tabler-clock-hour-4' },
    approved: { color: 'success', icon: 'tabler-circle-check' },
    rejected: { color: 'error', icon: 'tabler-circle-x' },
    cancelled: { color: 'secondary', icon: 'tabler-ban' },
    draft: { color: 'secondary', icon: 'tabler-file-pencil' },
    taken: { color: 'info', icon: 'tabler-calendar-time' },
  },

  certification: {
    valid: { color: 'success', icon: 'tabler-certificate' },
    expiring: { color: 'warning', icon: 'tabler-alert-triangle' },
    expired: { color: 'error', icon: 'tabler-certificate-off' },
    pending: { color: 'info', icon: 'tabler-clock-hour-4' },
  },

  candidate: {
    applied: { color: 'info', icon: 'tabler-file-plus' },
    screening: { color: 'info', icon: 'tabler-filter' },
    interview: { color: 'warning', icon: 'tabler-users' },
    evaluated: { color: 'primary', icon: 'tabler-clipboard-check' },
    selected: { color: 'success', icon: 'tabler-thumb-up' },
    offer: { color: 'primary', icon: 'tabler-mail-forward' },
    hired: { color: 'success', icon: 'tabler-user-check' },
    rejected: { color: 'error', icon: 'tabler-user-x' },
  },

  training: {
    enrolled: { color: 'info', icon: 'tabler-user-plus' },
    attended: { color: 'primary', icon: 'tabler-user-check' },
    completed: { color: 'success', icon: 'tabler-certificate' },
    failed: { color: 'error', icon: 'tabler-circle-x' },
  },

  review: {
    open: { color: 'warning', icon: 'tabler-alert-circle' },
    acknowledged: { color: 'info', icon: 'tabler-eye' },
    resolved: { color: 'success', icon: 'tabler-circle-check' },
    dismissed: { color: 'secondary', icon: 'tabler-ban' },
  },

  maintenance: {
    scheduled: { color: 'info', icon: 'tabler-calendar-event' },
    in_progress: { color: 'warning', icon: 'tabler-tool' },
    completed: { color: 'success', icon: 'tabler-circle-check' },
    overdue: { color: 'error', icon: 'tabler-alert-triangle' },
  },
})

const FALLBACK_META = Object.freeze({ color: 'secondary', icon: 'tabler-point' })

/**
 * Resolve the colour + icon for a domain status.
 *
 * @param {string} type   Domain key (e.g. 'employee', 'vehicle', 'leave')
 * @param {string} status Raw status value from the API (snake_case tolerated)
 * @returns {{ color: string, icon: string }}
 */
export function resolveWpStatusMeta(type, status) {
  if (!status) return FALLBACK_META

  const normalized = String(status).toLowerCase().trim().replaceAll('-', '_')
  const domain = WP_STATUS_REGISTRY[type]

  return domain?.[normalized] || FALLBACK_META
}

/** Colour only — convenient for progress bars / avatars. */
export function resolveWpStatusColor(type, status) {
  return resolveWpStatusMeta(type, status).color
}

/** Icon only — convenient for leading icons. */
export function resolveWpStatusIcon(type, status) {
  return resolveWpStatusMeta(type, status).icon
}

/**
 * Turn a raw API status into a readable fallback label.
 * Pages should normally pass an explicit i18n label; this is only a safety net
 * so raw snake_case is never shown to the user.
 */
export function humanizeWpStatus(status) {
  if (!status) return '—'

  return String(status)
    .replaceAll('_', ' ')
    .replaceAll('-', ' ')
    .replace(/\b\w/g, char => char.toUpperCase())
}
