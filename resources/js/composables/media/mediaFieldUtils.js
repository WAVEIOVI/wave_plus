/** Browser-side UX validation only. The server must validate bytes and permissions. */
export function acceptsFile(file, accept = '') {
  const rules = accept.split(',').map(rule => rule.trim().toLowerCase()).filter(Boolean)
  if (!rules.length) return true
  const name = String(file?.name || '').toLowerCase()
  const mime = String(file?.type || '').toLowerCase().split(';')[0]
  const knownMime = {
    pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png',
    webp: 'image/webp', gif: 'image/gif', svg: 'image/svg+xml', avif: 'image/avif',
    mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', avi: 'video/x-msvideo',
  }[name.split('.').pop()]
  // Empty MIME is common for Office/CAD files. Extension rules remain authoritative.
  const effectiveMime = mime || knownMime || ''
  return rules.some(rule => rule.startsWith('.')
    ? name.endsWith(rule)
    : rule.endsWith('/*')
      ? effectiveMime.startsWith(rule.slice(0, -1))
      : effectiveMime === rule)
}

export function validateFile(file, { accept = '', maxSizeMb = 0 } = {}) {
  if (!file || !Number.isFinite(file.size) || file.size <= 0) return 'empty'
  if (maxSizeMb > 0 && file.size > maxSizeMb * 1024 * 1024) return 'size'
  if (!acceptsFile(file, accept)) return 'type'
  return ''
}

export function safeMediaUrl(value) {
  if (typeof value !== 'string' || !value.trim()) return ''
  const url = value.trim()
  // Block executable schemes, including whitespace/control-character obfuscation.
  if (/[\u0000-\u001f\u007f]/.test(url)) return ''
  if (/^(https?:|blob:)/i.test(url)) return url
  if (/^data:image\/(png|jpeg|webp|gif|avif);base64,/i.test(url)) return url
  if (!/^[a-z][a-z\d+.-]*:/i.test(url)) return url
  return ''
}

export function formatFileBytes(value) {
  const bytes = Number(value)
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
  return `${parseFloat((bytes / 1024 ** index).toFixed(1))} ${units[index]}`
}

export function dateOnly(value) {
  if (!value) return null
  if (typeof value === 'string') {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:$|T)/)
    if (!match) return null
    const [, year, month, day] = match
    const parsed = new Date(Number(year), Number(month) - 1, Number(day))
    if (parsed.getFullYear() !== Number(year) || parsed.getMonth() !== Number(month) - 1 || parsed.getDate() !== Number(day)) return null
    return `${year}-${month}-${day}`
  }
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return null
  return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`
}

export function isMediaConvexQuad(points) {
  if (!Array.isArray(points) || points.length !== 4) return false
  if (points.some(p => !Number.isFinite(p.x) || !Number.isFinite(p.y) || p.x < 0 || p.x > 1 || p.y < 0 || p.y > 1)) return false
  const crosses = points.map((a, i) => {
    const b = points[(i + 1) % 4]
    const c = points[(i + 2) % 4]
    return (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x)
  })
  const area = Math.abs(points.reduce((sum, a, i) => {
    const b = points[(i + 1) % 4]
    return sum + a.x * b.y - b.x * a.y
  }, 0)) / 2
  // Expected order is TL, TR, BR, BL in screen coordinates (positive winding).
  return area >= 0.005 && crosses.every(cross => cross > 0.0001)
}
