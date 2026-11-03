/**
 * Shared, framework-free helpers for document & media components.
 *
 * Replaces the `formatDocumentBytes`, mime/icon and expiry helpers that were copy-pasted
 * across WpDocumentDialog, WpDocumentList, WpAttachmentField and the viewers.
 * Nothing in here touches Vue, so it is trivial to unit test.
 */

/** Default picker filter for documents: office files + photos of paper documents. */
export const DOCUMENT_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/jpeg,image/png,image/webp'

/** Dedicated filters for the unified Add Attachment workflow */
export const ATTACHMENT_DOCUMENT_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/jpeg,image/png,image/webp,image/svg+xml'
export const ATTACHMENT_MEDIA_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime'
export const ATTACHMENT_ALL_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime,.ai,.eps,.psd'

const MIME_BY_EXTENSION = {
  pdf: 'application/pdf',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  png: 'image/png',
  webp: 'image/webp',
  gif: 'image/gif',
  bmp: 'image/bmp',
  avif: 'image/avif',
  heic: 'image/heic',
  heif: 'image/heif',
  svg: 'image/svg+xml',
  doc: 'application/msword',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xls: 'application/vnd.ms-excel',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  csv: 'text/csv',
  txt: 'text/plain',
  mp4: 'video/mp4',
  webm: 'video/webm',
  mov: 'video/quicktime',
  avi: 'video/x-msvideo',
  ai: 'application/postscript',
  eps: 'application/postscript',
  psd: 'image/vnd.adobe.photoshop',
}

export function normalizeMediaUrl(url = '') {
  if (!url || typeof url !== 'string') return ''
  if (typeof window !== 'undefined' && window.location?.origin) {
    return url.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/storage\//, `${window.location.origin}/storage/`)
  }

  return url
}

const EXTENSION_BY_MIME = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
  'application/pdf': 'pdf',
  'video/mp4': 'mp4',
  'video/webm': 'webm',
  'video/quicktime': 'mov',
}

export function uid(prefix = 'id') {
  const random = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2, 11)}`

  return `${prefix}-${random}`
}

export function getExtension(name = '') {
  const match = /\.([a-z0-9]+)$/i.exec(String(name).split(/[?#]/)[0])

  return match ? match[1].toLowerCase() : ''
}

/**
 * Browsers often report an empty (or generic) `file.type` for .csv / .docx /
 * .heic. Fall back to the extension so validation does not reject valid files.
 */
export function resolveFileMime(file, fallbackName = '') {
  const type = (file?.type || file?.mime_type || '').toLowerCase()

  if (type && type !== 'application/octet-stream') return type

  return MIME_BY_EXTENSION[getExtension(file?.name || file?.file_name || fallbackName)] || type
}

/** @returns {'pdf'|'image'|'video'|'word'|'sheet'|'design'|'text'|'file'} */
export function resolveFileKind(mime = '', name = '') {
  if (mime && typeof mime === 'object') {
    const obj = mime
    const objName = obj.file_name || obj.filename || obj.name || obj.title || obj.url || name || ''
    const objMime = obj.mime_type || obj.mimeType || obj.type || ''

    return resolveFileKind(String(objMime), String(objName))
  }

  const type = (mime || MIME_BY_EXTENSION[getExtension(name)] || '').toLowerCase()
  const ext = getExtension(name)

  if (type.includes('pdf') || ext === 'pdf') return 'pdf'
  if (['ai', 'eps', 'psd', 'dwg', 'dxf'].includes(ext) || type.includes('postscript') || type.includes('illustrator') || type.includes('photoshop')) return 'design'
  if (type.startsWith('image/')) return 'image'
  if (type.startsWith('video/')) return 'video'
  if (type.includes('word') || ['doc', 'docx'].includes(ext)) return 'word'
  if (type.includes('sheet') || type.includes('excel') || type === 'text/csv' || ['xls', 'xlsx', 'csv'].includes(ext)) return 'sheet'
  if (type.startsWith('text/') || ext === 'txt') return 'text'

  return 'file'
}

const ICON_BY_KIND = {
  pdf: 'tabler-file-type-pdf',
  image: 'tabler-photo',
  video: 'tabler-video',
  word: 'tabler-file-type-doc',
  sheet: 'tabler-file-spreadsheet',
  design: 'tabler-vector-bezier-2',
  text: 'tabler-file-text',
  file: 'tabler-file',
}

const COLOR_BY_KIND = {
  pdf: 'error',
  image: 'info',
  video: 'secondary',
  word: 'primary',
  sheet: 'success',
  design: 'info',
  text: 'secondary',
  file: 'secondary',
}

export function isPreviewableFile(mimeOrItem = '', name = '') {
  const kind = resolveFileKind(mimeOrItem, name)

  return ['pdf', 'image', 'video'].includes(kind)
}

export function resolveFileIcon(mime, name) {
  return ICON_BY_KIND[resolveFileKind(mime, name)]
}

export function resolveFileColor(mime, name) {
  return COLOR_BY_KIND[resolveFileKind(mime, name)]
}

export function formatDocumentBytes(bytes) {
  if (!bytes || bytes <= 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB']
  const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))

  return `${parseFloat((bytes / 1024 ** index).toFixed(1))} ${units[index]}`
}

/**
 * Does a File satisfy an <input accept> string (".pdf,image/*,image/png")?
 * Drag & drop bypasses the native picker filter, so this is checked manually.
 */
export function matchesAccept(file, accept = '') {
  const tokens = String(accept).split(',').map(token => token.trim().toLowerCase()).filter(Boolean)

  if (!tokens.length) return true

  const mime = resolveFileMime(file)
  const name = (file?.name || '').toLowerCase()

  return tokens.some(token => {
    if (token.startsWith('.')) return name.endsWith(token)
    if (token.endsWith('/*')) return mime.startsWith(token.slice(0, -1))

    return mime === token
  })
}

export function titleFromFileName(name = '') {
  return String(name).replace(/\.[^/.]+$/, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim()
}

export function extensionForMime(mime = '') {
  return EXTENSION_BY_MIME[String(mime).split(';')[0].toLowerCase()] || ''
}

export function ensureExtension(name, mime) {
  if (getExtension(name)) return name

  const ext = extensionForMime(mime)

  return ext ? `${name}.${ext}` : name
}

// ---- dates ------------------------------------------------------------------

/**
 * Parse an API date as a LOCAL calendar day. `new Date('2026-10-21')` is UTC
 * midnight, which is "yesterday" west of Greenwich and made documents that
 * expire today show as expired.
 */
export function parseDateOnly(value) {
  if (!value) return null
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : new Date(value.getFullYear(), value.getMonth(), value.getDate())

  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value))

  if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))

  const parsed = new Date(value)

  return Number.isNaN(parsed.getTime()) ? null : new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
}

const pad = number => String(number).padStart(2, '0')

/** Local `YYYY-MM-DD` (never shifted by timezone, unlike toISOString). */
export function toDateInputValue(value) {
  const date = parseDateOnly(value)

  return date ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` : null
}

export function addYears(value, years) {
  const date = parseDateOnly(value) || parseDateOnly(new Date())
  const target = new Date(date.getFullYear() + years, date.getMonth(), date.getDate())

  // 29 Feb + 1 year must not roll into March
  if (target.getMonth() !== date.getMonth()) target.setDate(0)

  return toDateInputValue(target)
}

/**
 * @returns {{ state: 'none'|'valid'|'soon'|'expired', days: number|null }}
 *   days is positive when in the future, negative when past, 0 = today.
 */
export function getExpiryState(value, soonDays = 30, now = new Date()) {
  const expiry = parseDateOnly(value)

  if (!expiry) return { state: 'none', days: null }

  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const days = Math.round((expiry - today) / 86400000)

  if (days < 0) return { state: 'expired', days }
  if (days <= soonDays) return { state: 'soon', days }

  return { state: 'valid', days }
}

export function resolveDocumentStatusColor(status) {
  switch (String(status || '').toLowerCase()) {
  case 'active':
    return 'success'
  case 'under_review':
  case 'pending_renewal':
    return 'warning'
  case 'expired':
    return 'error'
  case 'draft':
    return 'secondary'
  case 'terminated':
  case 'archived':
    return 'default'
  default:
    return 'primary'
  }
}

// ---- download ---------------------------------------------------------------

export function clickAnchor(href, filename, newTab = false) {
  const link = document.createElement('a')

  link.href = href
  link.download = filename
  if (newTab) {
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
  }
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

/**
 * Force a real download with the right file name. The `download` attribute is
 * ignored for cross-origin URLs, so try fetch -> blob first and fall back to
 * opening the URL in a new tab when CORS / auth prevents it.
 */
export async function downloadFromUrl(url, filename = 'download') {
  if (!url) return

  try {
    const token = typeof useCookie === 'function' ? useCookie('accessToken')?.value : null

    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
    }

    if (token) {
      headers.Authorization = `Bearer ${token}`
    }

    const response = await fetch(url, {
      credentials: 'include',
      headers,
    })

    if (!response.ok) throw new Error(`HTTP ${response.status}`)

    const blob = await response.blob()
    const objectUrl = URL.createObjectURL(blob)

    clickAnchor(objectUrl, ensureExtension(filename, blob.type))
    setTimeout(() => URL.revokeObjectURL(objectUrl), 4000)
  } catch (err) {
    if (!url.includes('/api/')) {
      clickAnchor(url, filename, true)
    } else {
      throw err
    }
  }
}

// ---- errors -------------------------------------------------------------------

/**
 * Turn an Error carrying `i18nKey` (see i18nError in the composables) into a
 * localised string at the point of display. Falls back to the English message
 * when the key has no translation yet.
 *
 * @param {Error|{message?:string,i18nKey?:string,i18nParams?:Object}} error
 * @param {Function} t  vue-i18n `t`
 */
export function localiseError(error, t, fallback = '') {
  if (error?.i18nKey) return t(error.i18nKey, error.i18nParams || {}, error.message || fallback)

  return error?.message || fallback
}
