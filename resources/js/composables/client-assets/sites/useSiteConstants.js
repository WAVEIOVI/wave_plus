/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const facilityTypeCards = [
  { value: 'office', title: 'Office / HQ', desc: 'Corporate & administrative offices', icon: 'tabler-building', color: 'info' },
  { value: 'factory', title: 'Factory / Plant', desc: 'Manufacturing & processing facilities', icon: 'tabler-building-factory-2', color: 'primary' },
  { value: 'warehouse', title: 'Warehouse / Depot', desc: 'Storage, logistics & fulfillment', icon: 'tabler-building-warehouse', color: 'success' },
  { value: 'retail', title: 'Retail / Showroom', desc: 'Stores, points of sale & outlets', icon: 'tabler-building-store', color: 'secondary' },
  { value: 'hospital', title: 'Healthcare / Hospital', desc: 'Clinics, hospitals & medical centers', icon: 'tabler-building-hospital', color: 'error' },
  { value: 'school', title: 'Campus / School', desc: 'Universities, colleges & training centers', icon: 'tabler-school', color: 'warning' },
  { value: 'hotel', title: 'Hotel / Hospitality', desc: 'Hotels, resorts & accommodations', icon: 'tabler-hotel', color: 'primary' },
  { value: 'datacenter', title: 'Data Center', desc: 'Server facilities & tech infrastructure', icon: 'tabler-server', color: 'info' },
  { value: 'construction_site', title: 'Construction Site', desc: 'Civil engineering & building jobsites', icon: 'tabler-crane', color: 'warning' },
  { value: 'government', title: 'Government / Public', desc: 'Public institutions & civic buildings', icon: 'tabler-building-monument', color: 'secondary' },
  { value: 'industrial', title: 'Heavy Industry', desc: 'Refineries, utilities & heavy infrastructure', icon: 'tabler-tools', color: 'error' },
  { value: 'other', title: 'Other Facility', desc: 'Specialized or multipurpose premises', icon: 'tabler-map-pin', color: 'secondary' },
]

export const operatingStatusOptions = [
  { value: 'active', title: 'Active / Operational', color: 'success', icon: 'tabler-circle-check', description: 'Fully commissioned and in regular service' },
  { value: 'under_construction', title: 'Under Construction / Setup', color: 'warning', icon: 'tabler-tool', description: 'Undergoing setup, renovation or commissioning' },
  { value: 'inactive', title: 'Inactive / Idle', color: 'secondary', icon: 'tabler-player-pause', description: 'Temporarily inactive or operations on hold' },
  { value: 'decommissioned', title: 'Decommissioned / Closed', color: 'error', icon: 'tabler-circle-x', description: 'Permanently shutdown or decommissioned' },
]

export const riskClassOptions = [
  { value: 'low', title: 'Low Risk', color: 'success', icon: 'tabler-shield-check', description: 'Standard safety compliance environment' },
  { value: 'medium', title: 'Medium Risk', color: 'info', icon: 'tabler-shield-half', description: 'Moderate hazards & periodic safety audits' },
  { value: 'high', title: 'High Risk', color: 'warning', icon: 'tabler-shield-x', description: 'Strict PPE & daily safety protocols required' },
  { value: 'critical', title: 'Critical Risk', color: 'error', icon: 'tabler-alert-triangle', description: 'Hazardous ATEX/chemical environment' },
]

export const documentCategories = [
  { title: 'General Document', value: 'general' },
  { title: 'Floor Plan / Site Map', value: 'site_plans' },
  { title: 'Safety & Emergency Plan', value: 'emergency_plan' },
  { title: 'Compliance & Audit Certificate', value: 'compliance' },
  { title: 'Technical Specification', value: 'technical_spec' },
]

export const editSections = [
  { id: 'bio', label: 'Facility Profile', shortLabel: 'Profile', icon: 'tabler-building-estate' },
  { id: 'client-address', label: 'Client & Address Details', shortLabel: 'Client', icon: 'tabler-map-pin' },
  { id: 'general', label: 'Status & Operational Parameters', shortLabel: 'Status', icon: 'tabler-adjustments-horizontal' },
  { id: 'instructions', label: 'Access & Safety Guidelines', shortLabel: 'Safety', icon: 'tabler-shield-lock' },
  { id: 'areas', label: 'Zones & Areas', shortLabel: 'Areas', icon: 'tabler-hierarchy' },
  { id: 'equipment', label: 'Installed Equipment', shortLabel: 'Equipment', icon: 'tabler-fire-extinguisher' },
  { id: 'media', label: 'Photos & Documents', shortLabel: 'Media', icon: 'tabler-photo' },
  { id: 'history', label: 'Site Activity History', shortLabel: 'History', icon: 'tabler-history' },
]

export const humanize = value => (value || '').toString().replaceAll('_', ' ')

export const resolveSiteStatusVariant = stat => ({
  operational: 'success',
  active: 'success',
  under_construction: 'warning',
  under_maintenance: 'warning',
  stored: 'secondary',
  inactive: 'secondary',
  decommissioned: 'error',
  scrapped: 'error',
  disposed: 'error',
}[stat?.toLowerCase()] || 'primary')

export const resolveRiskVariant = risk => ({
  low: 'success',
  medium: 'info',
  high: 'warning',
  critical: 'error',
}[risk?.toLowerCase()] || 'secondary')

export const resolveSiteTypeVariant = type => ({
  office: 'info',
  factory: 'primary',
  warehouse: 'success',
  hospital: 'error',
  construction_site: 'warning',
  datacenter: 'primary',
}[type?.toLowerCase()] || 'secondary')

export const resolveAreaTypeIcon = type => ({
  building: 'tabler-building',
  floor: 'tabler-stairs',
  zone: 'tabler-layout-dashboard',
  room: 'tabler-door',
  exterior: 'tabler-trees',
  roof: 'tabler-sun',
  basement: 'tabler-arrow-down-bar',
  other: 'tabler-map-pin',
}[type?.toLowerCase()] || 'tabler-hierarchy-2')

export const formatFileSize = bytes => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export function useSiteConstants() {
  const { t } = useI18n()

  const optionTitle = (options, value) => {
    const match = options.find(o => o.value === value)

    return match ? t(match.title) : humanize(value)
  }

  const mobileNavItems = computed(() =>
    editSections.map(s => ({
      title: t(s.shortLabel || s.label),
      icon: s.icon,
      value: s.id,
      key: s.id,
    })),
  )

  return {
    facilityTypeCards,
    operatingStatusOptions,
    riskClassOptions,
    documentCategories,
    sections: editSections,
    mobileNavItems,
    humanize,
    optionTitle,
    resolveStatusVariant,
    resolveRiskVariant,
    resolveSiteTypeVariant,
    resolveAreaTypeIcon,
    formatFileSize,
  }
}
