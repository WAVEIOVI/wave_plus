/**
 * Signature & Text Synchronization Helpers
 * ─────────────────────────────────────────────────────────────────────────────
 * Prevents duplicate signature insertion and guarantees synchronized plain text.
 */

/**
 * Remove any existing WAVE PLUS signature markup from an HTML string.
 */
export function stripExistingSignature(html) {
  if (!html || typeof html !== 'string') return ''

  return html
    .replace(/<div\s+class="wp-signature[^"]*"[\s\S]*?<\/div>\s*<\/div>/gi, '')
    .replace(/<div\s+class="wp-signature[^"]*"[\s\S]*?<\/div>/gi, '')
    .trim()
}

/**
 * Detect signature type ('personal' | 'company' | 'none') from an HTML body.
 */
export function detectSignatureType(html) {
  if (!html || typeof html !== 'string') return 'none'
  if (html.includes('wp-signature-company')) return 'company'
  if (html.includes('wp-signature-personal') || html.includes('wp-signature')) return 'personal'

  return 'none'
}

/**
 * Append or replace a signature HTML onto an existing body HTML.
 * Ensures the signature is appended exactly once without duplicates.
 */
export function appendSignatureToHtml(bodyHtml, signatureHtml) {
  const clean = stripExistingSignature(bodyHtml || '')
  if (!signatureHtml || !signatureHtml.trim()) return clean

  return `${clean}\n${signatureHtml.trim()}`
}

/**
 * Convert HTML body into clean, synchronized plain text.
 * Mirrors EmailSendingService::htmlToPlainText.
 */
export function htmlToPlainText(html) {
  if (!html || typeof html !== 'string' || !html.trim()) return ''

  let text = html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(?:p|div|h[1-6]|tr|li)>/gi, '\n')
    .replace(/<li>/gi, '• ')
    .replace(/<[^>]+>/g, '')

  // Decode common HTML entities
  text = text
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/g, "'")
    .replace(/&bull;/gi, '•')

  return text
    .replace(/[ \t]+/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}
