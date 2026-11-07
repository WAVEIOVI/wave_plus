/* eslint-disable camelcase */
import { ref, unref } from 'vue'
import { useI18n } from 'vue-i18n'
import { toNumber } from './documentTotals.js'

/** i18n keys (namespace `wp.documents.lines.errors`) returned by line validation. */
export const LINE_ERROR_KEYS = Object.freeze({
  qty: 'wp.documents.lines.errors.qty_positive',
  price: 'wp.documents.lines.errors.price_min',
  item: 'wp.documents.lines.errors.item_required',
})

const clone = value => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)))

let lineKeyCounter = 0

/**
 * Generate a stable client-side key for a line (never sent as an id).
 *
 * @returns {string}
 */
export function newLineKey() {
  lineKeyCounter += 1

  return `line-${Date.now().toString(36)}-${lineKeyCounter}`
}

/**
 * Resolve a translatable value ({en, fr, ar} or string) for a locale.
 *
 * @param {string|object|null} value
 * @param {string} [locale='en']
 * @returns {string}
 */
export function pickLocalized(value, locale = 'en') {
  if (value == null) return ''
  if (typeof value === 'string') return value

  return value[locale] || value.en || Object.values(value).find(Boolean) || ''
}

/**
 * Build the default line factory.
 *
 * @param {{qty?: string, price?: string, defaults?: object}} [config]
 * @returns {(overrides?: object) => object}
 */
export function createLineFactory(config = {}) {
  const qtyKey = config.qty || 'qty'
  const priceKey = config.price || 'unit_price'

  return (overrides = {}) => ({
    _key: newLineKey(),
    catalog_item_id: null,
    variant_id: null,
    is_ad_hoc: false,
    ad_hoc_name: '',
    ad_hoc_sku: '',
    [qtyKey]: 1,
    [priceKey]: 0,
    ...clone(config.defaults || {}),
    ...overrides,
  })
}

/**
 * Hydrate a line from a catalog item resource (`CatalogItemResource`) and an
 * optional variant (`CatalogVariantResource`).
 *
 * @param {object} line Existing line (not mutated).
 * @param {object} item Catalog item resource.
 * @param {{variant?: object, locale?: string, map?: object}} [options]
 *   `map` overrides the target keys: `{ code, name, unit, unitId, price, tax, variantValues }`.
 * @returns {object} New line.
 */
export function hydrateLineFromCatalog(line, item, options = {}) {
  if (!item) return { ...line }

  const locale = options.locale || 'en'
  const variant = options.variant || null

  const map = {
    code: 'item_code', name: 'item_name', unit: 'unit_label', unitId: 'unit_id',
    price: 'unit_price', tax: 'tax_rate', variantValues: 'variant_values', ...(options.map || {}),
  }

  const optionValues = Array.isArray(variant?.option_values) ? variant.option_values : []

  const variantValues = optionValues
    .map(v => pickLocalized(v?.value ?? v?.name ?? v?.label, locale))
    .filter(Boolean)

  const price = variant?.cost_price ?? variant?.price ?? item.cost_price ?? item.price

  const next = {
    ...line,
    catalog_item_id: item.id,
    variant_id: variant?.id ?? line.variant_id ?? null,
    is_ad_hoc: false,
    ad_hoc_name: '',
    ad_hoc_sku: '',
    [map.code]: variant?.sku || item.code || '',
    [map.name]: pickLocalized(item.name, locale),
    [map.unit]: item.uom ? (item.uom.code || pickLocalized(item.uom.name, locale)) : (line[map.unit] ?? ''),
    [map.variantValues]: variantValues,
  }

  if (item.uom_id != null) next[map.unitId] = item.uom_id
  if (price !== undefined && price !== null && price !== '') next[map.price] = toNumber(price)

  const taxRate = item.tax_rate ?? variant?.tax_rate
  if (taxRate !== undefined && taxRate !== null) next[map.tax] = toNumber(taxRate)

  return next
}

/**
 * Validate a single line. Returns `{ field: i18nKey }` (empty when valid).
 *
 * @param {object} line
 * @param {{qty?: string, price?: string, requireItem?: boolean, allowZeroPrice?: boolean, priceRequired?: boolean, extra?: (line: object) => object}} [rules]
 * @returns {Record<string,string>}
 */
export function validateLine(line, rules = {}) {
  const errors = {}
  const qtyKey = rules.qty || 'qty'
  const priceKey = rules.price || 'unit_price'

  if (!(toNumber(line?.[qtyKey], NaN) > 0)) errors[qtyKey] = LINE_ERROR_KEYS.qty

  const rawPrice = line?.[priceKey]
  const priceMissing = rawPrice === null || rawPrice === undefined || rawPrice === ''

  if (!(priceMissing && !rules.priceRequired) && !(toNumber(rawPrice, NaN) >= 0)) errors[priceKey] = LINE_ERROR_KEYS.price

  if (rules.requireItem !== false) {
    const hasCatalog = line?.catalog_item_id != null && line.catalog_item_id !== ''
    const hasAdHoc = Boolean(line?.is_ad_hoc && String(line?.ad_hoc_name || '').trim())

    if (!hasCatalog && !hasAdHoc) errors.catalog_item_id = LINE_ERROR_KEYS.item
  }

  if (typeof rules.extra === 'function') Object.assign(errors, rules.extra(line) || {})

  return errors
}

/**
 * Validate every line. Result is keyed by line index then field; only lines
 * with errors are present.
 *
 * @param {object[]} lines
 * @param {object} [rules] See {@link validateLine}; `minLines` adds a `_form` entry.
 * @returns {Record<number, Record<string,string>>}
 */
export function validateLines(lines, rules = {}) {
  const result = {}

  ;(Array.isArray(lines) ? lines : []).forEach((line, index) => {
    const errors = validateLine(line, rules)

    if (Object.keys(errors).length) result[index] = errors
  })

  return result
}

/**
 * Move an element inside an array copy.
 *
 * @template T
 * @param {T[]} list
 * @param {number} from
 * @param {number} to
 * @returns {T[]}
 */
export function moveItem(list, from, to) {
  const next = [...list]

  if (from < 0 || from >= next.length || to < 0 || to >= next.length) return next
  const [item] = next.splice(from, 1)

  next.splice(to, 0, item)

  return next
}

/**
 * useDocumentItems
 * Line management for document editors.
 *
 * @param {{
 *   lines?: import('vue').Ref<object[]>,
 *   readonly?: import('vue').Ref<boolean>|boolean,
 *   qtyKey?: string, priceKey?: string,
 *   createLine?: (overrides?: object) => object,
 *   lineDefaults?: object,
 *   hydrateMap?: object,
 *   rules?: object,
 *   locale?: import('vue').Ref<string>|string,
 * }} [options]
 */
export function useDocumentItems(options = {}) {
  let i18nLocale = null

  try {
    i18nLocale = useI18n().locale
  } catch {
    // outside component context: fall back to option / 'en'
  }

  const lines = options.lines || ref([])
  const lineErrors = ref({})
  const qtyKey = options.qtyKey || 'qty'
  const priceKey = options.priceKey || 'unit_price'
  const createLine = options.createLine || createLineFactory({ qty: qtyKey, price: priceKey, defaults: options.lineDefaults })
  const locale = () => unref(options.locale) || unref(i18nLocale) || 'en'
  const guard = () => !unref(options.readonly)

  /**
   * Append a line.
   *
   * @param {object} [overrides]
   * @returns {object|null} The created line.
   */
  const addLine = (overrides = {}) => {
    if (!guard()) return null
    const line = createLine(overrides)

    lines.value.push(line)

    return line
  }

  /**
   * Patch a line by index.
   *
   * @param {number} index
   * @param {object} patch
   */
  const updateLine = (index, patch) => {
    if (!guard() || !lines.value[index]) return
    lines.value[index] = { ...lines.value[index], ...patch }
    if (lineErrors.value[index]) {
      const next = { ...lineErrors.value }

      Object.keys(patch).forEach(k => { if (next[index]) delete next[index][k] })
      lineErrors.value = next
    }
  }

  /**
   * Remove a line by index.
   *
   * @param {number} index
   */
  const removeLine = index => {
    if (!guard() || index < 0 || index >= lines.value.length) return
    lines.value.splice(index, 1)
    lineErrors.value = {}
  }

  /**
   * Duplicate a line right after the original (id/key stripped).
   *
   * @param {number} index
   * @returns {object|null}
   */
  const duplicateLine = index => {
    const source = lines.value[index]

    if (!guard() || !source) return null
    const copy = { ...clone(source), _key: newLineKey() }

    delete copy.id
    lines.value.splice(index + 1, 0, copy)

    return copy
  }

  /**
   * Reorder lines (drag & drop / move up-down).
   *
   * @param {number} from
   * @param {number} to
   */
  const reorderLines = (from, to) => {
    if (!guard()) return
    lines.value = moveItem(lines.value, from, to)
    lineErrors.value = {}
  }

  /**
   * Hydrate an existing line from a catalog item (and optional variant).
   *
   * @param {number} index
   * @param {object} item
   * @param {object} [variant]
   */
  const hydrateLine = (index, item, variant = null) => {
    if (!guard() || !lines.value[index]) return
    lines.value[index] = hydrateLineFromCatalog(lines.value[index], item, { variant, locale: locale(), map: options.hydrateMap })
  }

  /**
   * Add several catalog items at once. Items already present (same item+variant)
   * get their quantity incremented instead of duplicated.
   *
   * @param {Array<object|{item: object, variant?: object, qty?: number}>} entries
   * @returns {object[]} Lines created or updated.
   */
  const bulkAddLines = entries => {
    if (!guard()) return []

    const touched = []

    ;(entries || []).forEach(entry => {
      const item = entry?.item || entry
      const variant = entry?.variant || null
      const qty = toNumber(entry?.qty, 1) || 1

      if (!item) return
      const existing = lines.value.findIndex(l => l.catalog_item_id === item.id && (l.variant_id ?? null) === (variant?.id ?? null))

      if (existing >= 0) {
        lines.value[existing] = { ...lines.value[existing], [qtyKey]: toNumber(lines.value[existing][qtyKey]) + qty }
        touched.push(lines.value[existing])

        return
      }

      const line = hydrateLineFromCatalog(createLine({ [qtyKey]: qty }), item, { variant, locale: locale(), map: options.hydrateMap })

      lines.value.push(line)
      touched.push(line)
    })

    return touched
  }

  /**
   * Import raw lines (CSV/scan/prefill). Existing lines are kept unless `replace`.
   *
   * @param {object[]} rows
   * @param {{replace?: boolean}} [importOptions]
   * @returns {object[]}
   */
  const importLines = (rows, importOptions = {}) => {
    if (!guard()) return []
    const created = (rows || []).map(row => createLine(clone(row) || {}))

    lines.value = importOptions.replace ? created : [...lines.value, ...created]

    return created
  }

  /**
   * Validate all lines and store the errors.
   *
   * @returns {Record<number, Record<string,string>>}
   */
  const validate = () => {
    lineErrors.value = validateLines(lines.value, { qty: qtyKey, price: priceKey, ...(options.rules || {}) })

    return lineErrors.value
  }

  return {
    lines,
    lineErrors,
    addLine,
    updateLine,
    removeLine,
    duplicateLine,
    reorderLines,
    hydrateLine,
    bulkAddLines,
    importLines,
    validateLines: validate,
    hasLineErrors: () => Object.keys(lineErrors.value).length > 0,
  }
}
