/**
 * Centralized HR status → color/label lookups.
 * Single source of truth for all employee tab badges.
 */

const STATUS_COLORS = {
  // attendance
  present: 'success',
  late: 'warning',
  absent: 'error',
  partial: 'info',

  // leave requests
  approved: 'success',
  submitted: 'warning',
  rejected: 'error',
  cancelled: 'secondary',

  // certifications
  valid: 'success',
  expiring: 'warning',
  expired: 'error',

  // trainings
  completed: 'success',
  attended: 'info',
  enrolled: 'warning',

  // appraisals
  draft: 'secondary',
  reviewed: 'info',
  acknowledged: 'success',
}

export const SKILL_LEVEL_COLORS = {
  basic: 'secondary',
  intermediate: 'info',
  advanced: 'primary',
  expert: 'error',
}

export const resolveStatusColor = (status, fallback = 'secondary') =>
  STATUS_COLORS[status] ?? fallback

export const resolveSkillLevelColor = level =>
  SKILL_LEVEL_COLORS[level] ?? 'secondary'

export const formatBytes = (bytes, decimals = 2) => {
  if (!bytes) return '0 Bytes'
  const k = 1024
  const dm = Math.max(decimals, 0)
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
}

