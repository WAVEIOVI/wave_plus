// ─────────────────────────────────────────────────────────────────────────────
// Shared Export Format & Selection Normalization Helpers — WAVE PLUS
// ─────────────────────────────────────────────────────────────────────────────

export const SUPPORTED_EXPORT_FORMATS = Object.freeze({
  pdf: Object.freeze({
    value: 'pdf',
    label: 'PDF',
    titleKey: 'PDF Document (.pdf)',
    descriptionKey: 'Formatted printable table layout for sharing and archiving',
    icon: 'tabler-file-type-pdf',
    color: 'error',
    extension: 'pdf',
    mimeType: 'application/pdf',
  }),
  csv: Object.freeze({
    value: 'csv',
    label: 'CSV',
    titleKey: 'CSV Spreadsheet (.csv)',
    descriptionKey: 'Comma-separated values compatible with Excel and data tools',
    icon: 'tabler-file-spreadsheet',
    color: 'success',
    extension: 'csv',
    mimeType: 'text/csv',
  }),
})

/**
 * Resolves a list of requested format keys/configs into genuinely supported format definitions.
 * Unsupported formats (e.g. 'xlsx' when no generator is installed) are filtered out.
 *
 * @param {Array<string|object>} [requestedFormats]
 * @returns {Array<object>}
 */
export function resolveExportFormats(requestedFormats = ['pdf', 'csv']) {
  const input = Array.isArray(requestedFormats) && requestedFormats.length > 0
    ? requestedFormats
    : ['pdf', 'csv']

  const seen = new Set()
  const resolved = []

  for (const entry of input) {
    const rawKey = typeof entry === 'string'
      ? entry
      : (entry && typeof entry === 'object' ? entry.value || entry.format : '')

    const key = String(rawKey || '').trim().toLowerCase()
    if (!key || seen.has(key) || !Object.prototype.hasOwnProperty.call(SUPPORTED_EXPORT_FORMATS, key))
      continue

    seen.add(key)

    const base = SUPPORTED_EXPORT_FORMATS[key]
    if (entry && typeof entry === 'object') {
      resolved.push({
        ...base,
        ...entry,
        value: base.value,
      })
    }
    else {
      resolved.push({ ...base })
    }
  }

  return resolved
}

/**
 * Extracts and deduplicates valid record IDs (ULID/UUID strings or positive integers)
 * from an array/Set of primitive IDs or row objects.
 *
 * @param {Array|Set} [selection]
 * @returns {Array<string|number>}
 */
export function normalizeExportIds(selection = []) {
  const items = selection instanceof Set
    ? Array.from(selection)
    : (Array.isArray(selection) ? selection : [])

  const seen = new Set()
  const result = []

  for (const item of items) {
    const raw = item !== null && typeof item === 'object' ? item.id : item
    if (raw === null || raw === undefined || typeof raw === 'boolean')
      continue

    if (typeof raw === 'number') {
      if (!Number.isInteger(raw) || raw <= 0)
        continue

      const key = String(raw)
      if (seen.has(key))
        continue

      seen.add(key)
      result.push(raw)
      continue
    }

    if (typeof raw === 'string') {
      const trimmed = raw.trim()
      if (!trimmed || !/^[\w-]+$/.test(trimmed))
        continue

      if (/^-?\d+$/.test(trimmed)) {
        const parsed = Number(trimmed)
        if (!Number.isInteger(parsed) || parsed <= 0)
          continue
      }

      const key = trimmed.toLowerCase()
      if (seen.has(key))
        continue

      seen.add(key)
      result.push(trimmed)
    }
  }

  return result
}

/**
 * Validates and constructs a normalized batch export payload.
 *
 * @param {Array|Set} selection
 * @param {string} [format]
 * @param {Array<string|object>} [supportedFormats]
 * @returns {{ valid: boolean, ids: Array<number>, format: string, count: number, error: string|null }}
 */
export function buildBatchExportPayload(selection = [], format = 'pdf', supportedFormats = ['pdf', 'csv']) {
  const ids = normalizeExportIds(selection)
  const resolvedFormats = resolveExportFormats(supportedFormats)
  const normalizedFormat = String(format || '').trim().toLowerCase()
  const matchedFormat = resolvedFormats.find(item => item.value === normalizedFormat)

  if (ids.length === 0) {
    return {
      valid: false,
      ids: [],
      format: matchedFormat ? matchedFormat.value : (resolvedFormats[0]?.value || 'pdf'),
      count: 0,
      error: 'Please select at least one record to export.',
    }
  }

  if (!matchedFormat) {
    return {
      valid: false,
      ids,
      format: normalizedFormat,
      count: ids.length,
      error: 'Selected export format is not supported.',
    }
  }

  return {
    valid: true,
    ids,
    format: matchedFormat.value,
    count: ids.length,
    error: null,
  }
}
