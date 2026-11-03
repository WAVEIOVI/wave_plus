/* eslint-disable camelcase */

/**
 * People & Skills (HR) — Shared Constants & Dropdown Options
 *
 * Centralised source of truth for the employee profile layout, status colours
 * and dropdown options used across the People & Skills module.  Every function
 * returns fresh arrays with i18n-translated titles so they stay reactive to
 * locale changes.
 */

import { getI18n } from '@/plugins/i18n'

const i18n = { get global() { return getI18n().global } }

// ────────────────────────────────────────────────────────────────────────────
// EMPLOYEE PROFILE — Section Navigation
// ────────────────────────────────────────────────────────────────────────────
// Shared by the edit page and the read-only 360° preview dialog so both stay
// perfectly in sync.

export function getEmployeeProfileSections() {
  return [
    { id: 'bio', label: i18n.global.t('hr.profile.bio'), icon: 'tabler-user-circle' },
    { id: 'personal', label: i18n.global.t('hr.profile.personal'), icon: 'tabler-id' },
    { id: 'job', label: i18n.global.t('hr.profile.job'), icon: 'tabler-briefcase' },
    { id: 'status', label: i18n.global.t('hr.profile.status'), icon: 'tabler-user-bolt' },
    { id: 'skills', label: i18n.global.t('hr.profile.skills'), icon: 'tabler-award' },
    { id: 'certifications', label: i18n.global.t('hr.profile.certifications'), icon: 'tabler-certificate' },
    { id: 'attendance', label: i18n.global.t('hr.profile.attendance'), icon: 'tabler-clock-record' },
    { id: 'leave', label: i18n.global.t('hr.profile.time_off'), icon: 'tabler-calendar-time' },
    { id: 'history', label: i18n.global.t('hr.profile.employment'), icon: 'tabler-history' },
    { id: 'training', label: i18n.global.t('hr.profile.training'), icon: 'tabler-school' },
    { id: 'appraisals', label: i18n.global.t('hr.profile.appraisals'), icon: 'tabler-chart-bar' },
    { id: 'documents', label: i18n.global.t('hr.profile.documents'), icon: 'tabler-files' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// EMPLOYEE — Status
// ────────────────────────────────────────────────────────────────────────────

const EMPLOYEE_STATUS_META = {
  active: { color: 'success', icon: 'tabler-user-check' },
  on_leave: { color: 'info', icon: 'tabler-calendar-time' },
  suspended: { color: 'warning', icon: 'tabler-user-pause' },
  terminated: { color: 'error', icon: 'tabler-user-x' },
}

export function getEmployeeStatusOptions() {
  return [
    { title: i18n.global.t('hr.employees.active'), value: 'active' },
    { title: i18n.global.t('hr.dashboard.on_leave'), value: 'on_leave' },
    { title: i18n.global.t('hr.employees.suspended'), value: 'suspended' },
    { title: i18n.global.t('hr.employees.terminated_status'), value: 'terminated' },
  ]
}

export function resolveEmployeeStatusColor(status) {
  return EMPLOYEE_STATUS_META[status]?.color || 'secondary'
}

export function resolveEmployeeStatusIcon(status) {
  return EMPLOYEE_STATUS_META[status]?.icon || 'tabler-user'
}

export function resolveEmployeeStatusLabel(status) {
  return getEmployeeStatusOptions().find(option => option.value === status)?.title
    || status
    || '—'
}

/**
 * Available lifecycle transitions for an employee file.  Each entry maps to the
 * dedicated HR endpoint handled by `EmployeeStatusChangeDialog`.
 */
export function getEmployeeStatusActions(status) {
  return [
    {
      action: 'activate',
      label: i18n.global.t('hr.employees.activate'),
      icon: 'tabler-user-check',
      color: 'success',
      disabled: status === 'active',
    },
    {
      action: 'suspend',
      label: i18n.global.t('hr.employees.suspend'),
      icon: 'tabler-user-pause',
      color: 'warning',
      disabled: status === 'suspended',
    },
    {
      action: 'terminate',
      label: i18n.global.t('hr.employees.terminate'),
      icon: 'tabler-user-x',
      color: 'error',
      disabled: status === 'terminated',
    },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// EMPLOYEE — Personal details
// ────────────────────────────────────────────────────────────────────────────

export function getEmployeeGenderOptions() {
  return [
    { title: i18n.global.t('Male'), value: 'male' },
    { title: i18n.global.t('Female'), value: 'female' },
    { title: i18n.global.t('Other'), value: 'other' },
  ]
}
