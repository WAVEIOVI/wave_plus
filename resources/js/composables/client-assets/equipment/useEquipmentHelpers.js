/* eslint-disable camelcase */
/**
 * useEquipmentHelpers
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared display-only helpers for equipment assets. Every component that
 * renders equipment cards, tables, or list rows can import these instead
 * of duplicating the same status/color/label logic.
 *
 * Does NOT own any state or API calls — those live in useSiteEquipment or
 * the relevant Pinia store.
 */
import { buildAreaBreadcrumb } from '@/composables/client-assets/sites/areaHierarchyHelper'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

// ── Static Maps ──────────────────────────────────────────────────────────────

export const LIFECYCLE_STATUS_MAP = {
  operational: { label: 'Operational', color: 'success' },
  active: { label: 'Active', color: 'success' },
  under_construction: { label: 'Under Construction', color: 'warning' },
  under_maintenance: { label: 'Under Maintenance', color: 'warning' },
  stored: { label: 'In Storage', color: 'secondary' },
  inactive: { label: 'Inactive', color: 'secondary' },
  draft: { label: 'Draft', color: 'secondary' },
  decommissioned: { label: 'Decommissioned', color: 'error' },
  scrapped: { label: 'Scrapped', color: 'error' },
  disposed: { label: 'Disposed', color: 'error' },
}

export const COMPLIANCE_STATUS_MAP = {
  compliant: { label: 'Compliant', color: 'success' },
  due_soon: { label: 'Due Soon', color: 'warning' },
  overdue: { label: 'Overdue', color: 'error' },
  non_compliant: { label: 'Non-Compliant', color: 'error' },
  not_applicable: { label: 'Not Applicable', color: 'secondary' },
}

export const CRITICALITY_MAP = {
  low: { label: 'Low', color: 'secondary' },
  medium: { label: 'Medium', color: 'info' },
  high: { label: 'High', color: 'warning' },
  critical: { label: 'Critical', color: 'error' },
}

// ── Pure resolvers (no i18n required) ────────────────────────────────────────

export const resolveLifecycleVariant = stat =>
  LIFECYCLE_STATUS_MAP[stat?.toLowerCase()]?.color || 'primary'

export const resolveComplianceVariant = comp =>
  COMPLIANCE_STATUS_MAP[comp?.toLowerCase()]?.color || 'secondary'

export const resolveCriticalityVariant = crit =>
  CRITICALITY_MAP[crit?.toLowerCase()]?.color || 'secondary'

export const humanizeEquipmentValue = value => (value || '').toString().replaceAll('_', ' ')

/** Extract the primary photo URL from an equipment record. */
export const equipmentPhoto = eq => {
  if (typeof eq.main_photo_url === 'string' && eq.main_photo_url)
    return eq.main_photo_url
  if (eq.main_photo_url?.url)
    return eq.main_photo_url.url
  if (eq.photos?.[0]?.url)
    return eq.photos[0].url

  return null
}

/** Resolve brand display name. */
export const brandLabel = eq =>
  (typeof eq.brand === 'object' ? eq.brand?.name : eq.brand) || ''

/** Resolve equipment class display name. */
export const classLabel = eq =>
  localizedValue(eq.equipment_class?.name) || eq.equipment_class?.name_text || ''

// ── Composable (i18n-aware helpers) ──────────────────────────────────────────

export function useEquipmentHelpers() {
  const { t } = useI18n()

  /** Localized equipment title with fallback chain. */
  const equipmentTitle = eq =>
    localizedValue(eq.model_name) || localizedValue(eq.blueprint?.name) || localizedValue(eq.name) || t('Asset')

  /** Localized lifecycle status label. */
  const lifecycleLabel = stat => {
    const entry = LIFECYCLE_STATUS_MAP[(stat || '').toLowerCase()]

    return entry ? t(entry.label) : humanizeEquipmentValue(stat)
  }

  /** Localized compliance status label. */
  const complianceLabel = comp => {
    const entry = COMPLIANCE_STATUS_MAP[(comp || '').toLowerCase()]

    return entry ? t(entry.label) : humanizeEquipmentValue(comp)
  }

  /** Localized criticality label. */
  const criticalityLabel = crit => {
    const entry = CRITICALITY_MAP[(crit || '').toLowerCase()]

    return entry ? t(entry.label) : humanizeEquipmentValue(crit)
  }

  /** Build the full area breadcrumb path for display. */
  const areaLabel = (eq, areasFlat = []) => {
    const areaId = eq.area_id || eq.area?.id
    if (!areaId)
      return t('Unassigned Area')

    const path = buildAreaBreadcrumb(areaId, areasFlat)
      .map(a => localizedValue(a.name))
      .join(' › ')

    return path || localizedValue(eq.area?.name) || t('Unassigned Area')
  }

  /** Next inspection countdown display. */
  const inspectionCountdown = dateStr => {
    if (!dateStr)
      return null

    const today = new Date()

    today.setHours(0, 0, 0, 0)

    const target = new Date(dateStr)

    target.setHours(0, 0, 0, 0)

    const diffDays = Math.ceil((target - today) / (1000 * 60 * 60 * 24))

    if (diffDays < 0) {
      return {
        text: t('Overdue by {days}d', { days: Math.abs(diffDays) }),
        color: 'error',
        icon: 'tabler-alert-triangle',
      }
    }
    if (diffDays === 0) {
      return {
        text: t('Due today'),
        color: 'error',
        icon: 'tabler-alarm',
      }
    }

    return {
      text: t('Due in {days}d', { days: diffDays }),
      color: diffDays <= 30 ? 'warning' : 'secondary',
      icon: diffDays <= 30 ? 'tabler-clock' : 'tabler-calendar',
    }
  }

  /** Lifecycle status items for filter dropdowns. */
  const lifecycleFilterItems = [
    { title: t('Operational'), value: 'operational' },
    { title: t('Under Maintenance'), value: 'under_maintenance' },
    { title: t('In Storage'), value: 'stored' },
    { title: t('Draft'), value: 'draft' },
    { title: t('Decommissioned'), value: 'decommissioned' },
  ]

  /** Compliance status items for filter dropdowns. */
  const complianceFilterItems = [
    { title: t('Compliant'), value: 'compliant' },
    { title: t('Due Soon'), value: 'due_soon' },
    { title: t('Overdue'), value: 'overdue' },
    { title: t('Non-Compliant'), value: 'non_compliant' },
  ]

  /** Criticality items for filter dropdowns. */
  const criticalityFilterItems = [
    { title: t('Low'), value: 'low' },
    { title: t('Medium'), value: 'medium' },
    { title: t('High'), value: 'high' },
    { title: t('Critical'), value: 'critical' },
  ]

  return {
    // Labels
    equipmentTitle,
    lifecycleLabel,
    complianceLabel,
    criticalityLabel,
    areaLabel,
    inspectionCountdown,

    // Filter items
    lifecycleFilterItems,
    complianceFilterItems,
    criticalityFilterItems,
  }
}
