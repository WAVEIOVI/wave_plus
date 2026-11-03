/* eslint-disable camelcase */
import {
  humanizeEquipmentValue as humanize,
  resolveComplianceVariant,
  resolveCriticalityVariant,
  resolveLifecycleVariant,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const lifecycleStatusOptions = [
  { value: 'operational', title: 'Operational', color: 'success', icon: 'tabler-circle-check', description: 'Active in field service' },
  { value: 'under_maintenance', title: 'Under Maintenance', color: 'warning', icon: 'tabler-tool', description: 'Service or repair in progress' },
  { value: 'stored', title: 'In Storage', color: 'info', icon: 'tabler-archive', description: 'Idle or reserved in depot' },
  { value: 'draft', title: 'Draft', color: 'secondary', icon: 'tabler-file-text', description: 'Not yet commissioned' },
  { value: 'decommissioned', title: 'Decommissioned', color: 'error', icon: 'tabler-ban', description: 'Retired from service' },
]

export const complianceStatusOptions = [
  { value: 'compliant', title: 'Compliant', color: 'success', icon: 'tabler-shield-check', description: 'All regulatory checks passed' },
  { value: 'due_soon', title: 'Due Soon', color: 'warning', icon: 'tabler-clock', description: 'Scheduled inspection approaching' },
  { value: 'overdue', title: 'Overdue', color: 'error', icon: 'tabler-alarm', description: 'Inspection deadline expired' },
  { value: 'non_compliant', title: 'Non-Compliant', color: 'error', icon: 'tabler-shield-x', description: 'Safety or audit non-conformance' },
  { value: 'not_applicable', title: 'Not Applicable', color: 'secondary', icon: 'tabler-minus', description: 'No regulatory rules apply' },
]

export const criticalityOptions = [
  { value: 'low', title: 'Low', color: 'info', icon: 'tabler-chevrons-down', description: 'Standard non-critical asset' },
  { value: 'medium', title: 'Medium', color: 'secondary', icon: 'tabler-equal', description: 'Important operational process' },
  { value: 'high', title: 'High', color: 'warning', icon: 'tabler-chevrons-up', description: 'Vital facility safety impact' },
  { value: 'critical', title: 'Critical', color: 'error', icon: 'tabler-flame', description: 'Zero tolerance for failure' },
]

export const conditionStatusOptions = [
  { value: 'good', title: 'Good', color: 'success', icon: 'tabler-thumb-up', description: 'Optimal operating performance' },
  { value: 'fair', title: 'Fair', color: 'warning', icon: 'tabler-alert-circle', description: 'Minor wear & tear observed' },
  { value: 'poor', title: 'Poor', color: 'error', icon: 'tabler-alert-triangle', description: 'Needs corrective attention' },
  { value: 'damaged', title: 'Damaged', color: 'error', icon: 'tabler-crash', description: 'Out of order / requires repair' },
]

export const equipmentDocumentCategories = [
  { title: 'General Document', value: 'general' },
  { title: 'Compliance Certificate', value: 'certificate' },
  { title: 'Technical Manual', value: 'manual' },
  { title: 'Inspection Report', value: 'inspection' },
  { title: 'Warranty Document', value: 'warranty' },
]

export const equipmentEditSections = [
  { id: 'bio', label: 'Identity & Overview', shortLabel: 'Profile', icon: 'tabler-id' },
  { id: 'general', label: 'Location & Assignment', shortLabel: 'Location', icon: 'tabler-map-pin' },
  { id: 'product', label: 'Blueprint & Codification', shortLabel: 'Blueprint', icon: 'tabler-box' },
  { id: 'dates', label: 'Status & Lifecycle Dates', shortLabel: 'Lifecycle', icon: 'tabler-calendar-event' },
  { id: 'specs', label: 'Specs, Certs & Notes', shortLabel: 'Specs', icon: 'tabler-tools' },
  { id: 'media', label: 'Photos & Documents', shortLabel: 'Media', icon: 'tabler-photo' },
  { id: 'history', label: 'Audit & Movements', shortLabel: 'History', icon: 'tabler-history' },
]

export const resolveConditionVariant = cond => ({
  excellent: 'success',
  good: 'success',
  fair: 'warning',
  poor: 'error',
  damaged: 'error',
  critical: 'error',
}[cond?.toLowerCase()] || 'secondary')

export const formatEquipmentFileSize = bytes => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export function useEquipmentConstants() {
  const { t } = useI18n()

  const optionTitle = (options, value) => {
    const match = options.find(o => o.value === value)

    return match ? t(match.title) : humanize(value)
  }

  const mobileNavItems = computed(() =>
    equipmentEditSections.map(s => ({
      title: t(s.shortLabel || s.label),
      icon: s.icon,
      value: s.id,
      key: s.id,
    })),
  )

  return {
    lifecycleStatusOptions,
    complianceStatusOptions,
    criticalityOptions,
    conditionStatusOptions,
    documentCategories: equipmentDocumentCategories,
    sections: equipmentEditSections,
    mobileNavItems,
    humanize,
    optionTitle,
    resolveLifecycleVariant,
    resolveComplianceVariant,
    resolveCriticalityVariant,
    resolveConditionVariant,
    formatFileSize: formatEquipmentFileSize,
  }
}
