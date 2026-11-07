/* eslint-disable camelcase */
// ─────────────────────────────────────────────────────────────────────────────
// Shared helpers for document line components (preview maths only — the
// backend always recalculates the authoritative amounts).
// ─────────────────────────────────────────────────────────────────────────────

/** Every field a document line can expose. */
export const DOCUMENT_LINE_FIELDS = [
  'item',
  'description',
  'quantity',
  'unit',
  'unit_price',
  'discount',
  'tax_rate',
  'required_date',
  'notes',
]

/** Fields shown when the caller does not configure anything. */
export const DEFAULT_LINE_FIELD_VISIBILITY = {
  item: true,
  description: true,
  quantity: true,
  unit: true,
  unit_price: true,
  discount: true,
  tax_rate: true,
  required_date: false,
  notes: true,
}

/**
 * Normalise a `fields` prop into `{ [key]: { visible, label, required, readonly } }`.
 * Accepts an array of keys (those become visible, others hidden), or an object
 * whose values are booleans or `{ visible?, label?, required?, readonly? }`.
 */
export const resolveLineFields = (fields, labelOverrides = {}) => {
  let source = DEFAULT_LINE_FIELD_VISIBILITY

  if (Array.isArray(fields)) {
    source = Object.fromEntries(DOCUMENT_LINE_FIELDS.map(key => [key, fields.includes(key)]))
  }
  else if (fields && typeof fields === 'object' && Object.keys(fields).length) {
    source = { ...DEFAULT_LINE_FIELD_VISIBILITY, ...fields }
  }

  const resolved = {}

  DOCUMENT_LINE_FIELDS.forEach(key => {
    const raw = source[key]
    const cfg = raw && typeof raw === 'object' ? raw : { visible: Boolean(raw) }

    resolved[key] = {
      visible: cfg.visible !== false,
      label: cfg.label || labelOverrides?.[key] || '',
      required: Boolean(cfg.required),
      readonly: Boolean(cfg.readonly),
    }
  })

  return resolved
}

const num = value => {
  const n = Number(value)

  return Number.isFinite(n) ? n : 0
}

export const lineQuantity = line => num(line?.quantity ?? line?.qty ?? 0)
export const lineUnitPrice = line => num(line?.unit_price ?? line?.price ?? 0)

/** Net line total excluding tax: qty × price × (1 − discount%). */
export const computeLineTotal = line => {
  const gross = lineQuantity(line) * lineUnitPrice(line)
  const discount = Math.min(Math.max(num(line?.discount), 0), 100)

  return Math.round(gross * (1 - discount / 100) * 100) / 100
}

/** Return a copy of `line` with `patch` applied and its total recomputed. */
export const patchLine = (line, patch) => {
  const next = { ...line, ...patch }
  const total = computeLineTotal(next)

  next.line_total = total
  if ('total' in next) next.total = total

  return next
}
