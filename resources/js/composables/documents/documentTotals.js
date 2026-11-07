import { computed, unref } from 'vue'
import { useCurrency } from '../useCurrency.js'

/**
 * Field-name presets for the procurement document lines. Every key may be
 * overridden through the `fields` option of {@link useDocumentTotals}.
 *
 * `discountType` is `percent` (0-100) or `amount` (absolute, per line).
 * `taxRate` values are percentages (e.g. 19 for 19%).
 */
export const TOTALS_FIELD_PRESETS = Object.freeze({
  default: { qty: 'qty', price: 'unit_price', discount: 'discount', discountType: 'percent', tax: 'tax_rate' },
  ipr: { qty: 'qty_requested', price: 'estimated_unit_cost', discount: null, discountType: 'percent', tax: null },
  rfq: { qty: 'qty_requested', price: 'target_price_ht', discount: null, discountType: 'percent', tax: null },
  po: { qty: 'qty_ordered', price: 'unit_price_ht', discount: 'discount_pct', discountType: 'percent', tax: 'tax_rate_pct' },
  grn: { qty: 'qty_received', price: 'unit_cost_ht', discount: null, discountType: 'percent', tax: null },
})

/**
 * Coerce any value to a finite number.
 *
 * @param {any} value
 * @param {number} [fallback=0]
 * @returns {number}
 */
export function toNumber(value, fallback = 0) {
  if (value === null || value === undefined || value === '') return fallback
  const n = Number(value)

  return Number.isFinite(n) ? n : fallback
}

/**
 * Round half away from zero to `digits` decimals (float-noise safe).
 *
 * @param {number} value
 * @param {number} [digits=2]
 * @returns {number}
 */
export function roundMoney(value, digits = 2) {
  const factor = 10 ** digits
  const n = toNumber(value)

  return Math.sign(n) * Math.round((Math.abs(n) + Number.EPSILON) * factor) / factor || 0
}

/**
 * Resolve the effective field map from a preset name or partial object.
 *
 * @param {string|object} [fields='default']
 * @returns {{qty:string, price:string, discount:(string|null), discountType:string, tax:(string|null)}}
 */
export function resolveTotalsFields(fields = 'default') {
  if (typeof fields === 'string') return { ...(TOTALS_FIELD_PRESETS[fields] || TOTALS_FIELD_PRESETS.default) }

  return { ...TOTALS_FIELD_PRESETS.default, ...(fields || {}) }
}

/**
 * Compute the amounts of a single line.
 *
 * @param {object} line
 * @param {object|string} [fields]
 * @param {number} [defaultTaxRate=0] Used when the line has no tax field value.
 * @returns {{qty:number, price:number, gross:number, discount:number, net:number, taxRate:number, tax:number, total:number}}
 */
export function computeLineAmounts(line, fields, defaultTaxRate = 0) {
  const f = resolveTotalsFields(fields)
  const qty = Math.max(0, toNumber(line?.[f.qty]))
  const price = Math.max(0, toNumber(line?.[f.price]))
  const gross = qty * price
  const rawDiscount = f.discount ? Math.max(0, toNumber(line?.[f.discount])) : 0
  const discount = Math.min(gross, f.discountType === 'amount' ? rawDiscount : gross * Math.min(rawDiscount, 100) / 100)
  const net = gross - discount
  const taxRate = f.tax ? Math.max(0, toNumber(line?.[f.tax], defaultTaxRate)) : Math.max(0, defaultTaxRate)
  const tax = net * taxRate / 100

  return {
    qty,
    price,
    gross: roundMoney(gross),
    discount: roundMoney(discount),
    net: roundMoney(net),
    taxRate,
    tax: roundMoney(tax),
    total: roundMoney(net + tax),
  }
}

/**
 * Compute document totals from a list of lines. Pure and side-effect free.
 *
 * @param {object[]} lines
 * @param {{fields?: (object|string), defaultTaxRate?: number, shippingCost?: number, additionalCosts?: number, stampDuty?: number, digits?: number}} [options]
 * @returns {{subtotal:number, discountTotal:number, netTotal:number, taxBreakdown:{rate:number, base:number, tax:number}[], taxTotal:number, shippingCost:number, additionalCosts:number, stampDuty:number, grandTotal:number, lineAmounts:object[]}}
 */
export function computeDocumentTotals(lines, options = {}) {
  const list = Array.isArray(lines) ? lines : []
  const lineAmounts = list.map(line => computeLineAmounts(line, options.fields, options.defaultTaxRate || 0))
  const groups = new Map()

  let subtotal = 0
  let discountTotal = 0

  lineAmounts.forEach(a => {
    subtotal += a.gross
    discountTotal += a.discount

    const bucket = groups.get(a.taxRate) || { rate: a.taxRate, base: 0, tax: 0 }

    bucket.base += a.net
    bucket.tax += a.net * a.taxRate / 100
    groups.set(a.taxRate, bucket)
  })

  const taxBreakdown = [...groups.values()]
    .map(g => ({ rate: g.rate, base: roundMoney(g.base), tax: roundMoney(g.tax) }))
    .sort((a, b) => a.rate - b.rate)

  const netTotal = roundMoney(subtotal - discountTotal)
  const taxTotal = roundMoney(taxBreakdown.reduce((sum, g) => sum + g.tax, 0))
  const shippingCost = roundMoney(Math.max(0, toNumber(options.shippingCost)))
  const additionalCosts = roundMoney(Math.max(0, toNumber(options.additionalCosts)))
  const stampDuty = roundMoney(Math.max(0, toNumber(options.stampDuty)))

  return {
    subtotal: roundMoney(subtotal),
    discountTotal: roundMoney(discountTotal),
    netTotal,
    taxBreakdown,
    taxTotal,
    shippingCost,
    additionalCosts,
    stampDuty,
    grandTotal: roundMoney(netTotal + taxTotal + shippingCost + additionalCosts + stampDuty),
    lineAmounts,
  }
}

/**
 * useDocumentTotals
 * Reactive totals preview for a document (the backend stays authoritative).
 *
 * @param {import('vue').Ref<object[]>|object[]|(() => object[])} linesRef
 * @param {{fields?: (object|string), defaultTaxRate?: number, shippingCost?: any, additionalCosts?: any, stampDuty?: any, formatter?: Function}} [options]
 *   `shippingCost`, `additionalCosts`, `stampDuty` and `fields` accept refs/getters.
 */
export function useDocumentTotals(linesRef, options = {}) {
  const { format: formatCurrency } = useCurrency()
  const read = v => (typeof v === 'function' ? v() : unref(v))

  const totals = computed(() => computeDocumentTotals(read(linesRef), {
    fields: read(options.fields),
    defaultTaxRate: toNumber(read(options.defaultTaxRate)),
    shippingCost: read(options.shippingCost),
    additionalCosts: read(options.additionalCosts),
    stampDuty: read(options.stampDuty),
  }))

  /**
   * Format an amount with the active currency.
   *
   * @param {number|string} amount
   * @param {object|string} [formatOptions]
   * @returns {string}
   */
  const format = (amount, formatOptions) => (options.formatter || formatCurrency)(amount, formatOptions)

  return {
    totals,
    subtotal: computed(() => totals.value.subtotal),
    discountTotal: computed(() => totals.value.discountTotal),
    netTotal: computed(() => totals.value.netTotal),
    taxBreakdown: computed(() => totals.value.taxBreakdown),
    taxTotal: computed(() => totals.value.taxTotal),
    shippingCost: computed(() => totals.value.shippingCost),
    additionalCosts: computed(() => totals.value.additionalCosts),
    stampDuty: computed(() => totals.value.stampDuty),
    grandTotal: computed(() => totals.value.grandTotal),
    lineAmounts: computed(() => totals.value.lineAmounts),
    format,
  }
}
