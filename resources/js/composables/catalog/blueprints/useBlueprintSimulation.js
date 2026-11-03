/**
 * Helpers for technician test-bench / blueprint simulation.
 * The resolve-lines API expects option VALUE ids (not codes).
 */

export const optionValueId = value => {
  if (!value) return null
  if (value.id !== undefined && value.id !== null) return String(value.id)

  return null
}

export const collectSelectedOptionValueIds = (selections = {}) =>
  Object.values(selections).filter(Boolean).map(String)

export const mapResolveLinesResponse = (response, equipmentQty = 1) => {
  const data = response?.data || response || {}
  const lines = Array.isArray(data.lines) ? data.lines : []

  const isConsumable = line =>
    Boolean(line.source_consumable_id)
    || line.item_type_snapshot === 'consumable'
    || line.type === 'consumable'
    || line.item_type === 'consumable'

  const isServicing = line =>
    Boolean(line.source_servicing_id)
    || line.item_type_snapshot === 'eq_servicing'
    || line.type === 'servicing'
    || line.item_type === 'eq_servicing'

  const consumableLines = lines.filter(isConsumable)
  const servicingLines = lines.filter(isServicing)

  return {
    ...data,
    generated_name: data.equipment_name || data.generated_name || '',
    lines,
    consumableLines,
    servicingLines,
    consumables_summary: data.consumables_summary || consumableLines,
    totalLinesCount: lines.length,
    hasMissingVariants: data.has_missing_variants || false,
    equipmentQty: Number(equipmentQty) || 1,
  }
}

export const lineDisplayName = line =>
  line?.name || line?.sku || line?.catalog_item_code || line?.code || '—'

export const lineCode = line =>
  line?.catalog_item_code || line?.sku || line?.variant_sku || line?.code || '—'

export const lineQty = line =>
  line?.qty ?? line?.quantity ?? line?.total_quantity ?? 1

export const lineFormula = line => {
  const snap = line?.calculation_snapshot
  if (typeof snap === 'string' && snap) return snap
  if (snap?.formula) return snap.formula
  if (snap?.method) {
    if (snap.method === 'fixed') return `fixed × ${snap.fixed_quantity ?? snap.qty ?? 1}`
    if (snap.source_option || snap.option_code)
      return `${snap.source_option || snap.option_code} × ${snap.multiplier ?? 1}`

    return snap.method
  }

  return line?.formula_breakdown || line?.calculation_detail || line?.calculation_method || '—'
}

export const defaultOptionSelections = (options = []) => {
  const initial = {}

  options.forEach(opt => {
    const first = opt.values?.[0]
    const id = optionValueId(first)
    if (id) initial[opt.code || opt.id] = id
  })

  return initial
}
