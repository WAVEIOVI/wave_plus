/**
 * Document preset configurations for WAVE PLUS shared document dialogs and lists.
 */

export const DOCUMENT_PRESETS = Object.freeze({
  BP: 'bp',
  FLEET: 'fleet',
  EMPLOYEE: 'employee',
  SITE: 'site',
  CATALOG: 'catalog',
  COMPACT: 'compact',
  FULL: 'full',
})

export const DOCUMENT_PRESET_META = Object.freeze({
  bp: {
    key: 'bp',
    icon: 'tabler-building-skyscraper',
    labelKey: 'documents.preset.bp',
    defaultLabel: 'Business Partner Compliance',
    requireReference: false,
    showComplianceSummary: true,
  },
  fleet: {
    key: 'fleet',
    icon: 'tabler-truck',
    labelKey: 'documents.preset.fleet',
    defaultLabel: 'Fleet & Vehicle Dossier',
    requireReference: false,
    showComplianceSummary: true,
  },
  employee: {
    key: 'employee',
    icon: 'tabler-id-badge-2',
    labelKey: 'documents.preset.employee',
    defaultLabel: 'Employee HR Records',
    requireReference: false,
    showComplianceSummary: true,
  },
  site: {
    key: 'site',
    icon: 'tabler-map-pin-check',
    labelKey: 'documents.preset.site',
    defaultLabel: 'Site & Facility Permits',
    requireReference: false,
    showComplianceSummary: true,
  },
  catalog: {
    key: 'catalog',
    icon: 'tabler-package',
    labelKey: 'documents.preset.catalog',
    defaultLabel: 'Product & Technical Sheets',
    requireReference: false,
    showComplianceSummary: false,
  },
  compact: {
    key: 'compact',
    icon: 'tabler-files',
    labelKey: 'documents.preset.compact',
    defaultLabel: 'Quick Attachment',
    requireReference: false,
    showComplianceSummary: false,
  },
  full: {
    key: 'full',
    icon: 'tabler-file-certificate',
    labelKey: 'documents.preset.full',
    defaultLabel: 'Documents & Compliance',
    requireReference: false,
    showComplianceSummary: true,
  },
})

/**
 * Resolves the active visible fields for a given preset.
 *
 * @param {string} preset
 * @param {Array<string>|null} customFields
 * @returns {Array<string>}
 */
export function resolveDocumentPresetFields(preset = 'bp', customFields = null) {
  if (Array.isArray(customFields) && customFields.length > 0) {
    return customFields
  }

  switch (preset) {
  case 'fleet':
    return ['type', 'title', 'reference', 'issuer', 'issue_date', 'expiration_date', 'status', 'notes']
  case 'compact':
  case 'employee':
  case 'site':
    return ['type', 'title', 'notes']
  case 'catalog':
    return ['title', 'type', 'reference', 'expiration_date', 'notes']
  case 'bp':
  case 'full':
  default:
    return ['type', 'title', 'reference', 'issuer', 'status', 'issue_date', 'effective_date', 'expiration_date', 'auto_renewable', 'notes']
  }
}

/**
 * Returns metadata for a given document preset.
 *
 * @param {string} preset
 * @returns {object}
 */
export function getDocumentPresetConfig(preset = 'full') {
  return DOCUMENT_PRESET_META[preset] || DOCUMENT_PRESET_META.full
}
