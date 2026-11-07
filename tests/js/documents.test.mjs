/* eslint-disable camelcase -- fixtures mirror snake_case API payloads */
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { ref } from 'vue'
import {
  computeDocumentTotals,
  computeLineAmounts,
  roundMoney,
} from '../../resources/js/composables/documents/documentTotals.js'
import {
  createLineFactory,
  hydrateLineFromCatalog,
  moveItem,
  validateLine,
  validateLines,
} from '../../resources/js/composables/documents/useDocumentItems.js'
import {
  computeLifecycleSteps,
  getNextStatuses,
  isEditableStatus,
  workflowConfigs,
} from '../../resources/js/composables/documents/workflowConfigs.js'
import { useDocumentWorkflow } from '../../resources/js/composables/documents/useDocumentWorkflow.js'
import { buildTraceability } from '../../resources/js/composables/documents/useDocumentTraceability.js'

describe('document totals', () => {
  it('rounds money without float noise', () => {
    assert.equal(roundMoney(1.005), 1.01)
    assert.equal(roundMoney(0.1 + 0.2), 0.3)
  })

  it('computes a PO with discount, grouped taxes and extra costs', () => {
    const lines = [
      { qty_ordered: 2, unit_price_ht: 100, discount_pct: 10, tax_rate_pct: 19 },
      { qty_ordered: 1, unit_price_ht: 50, tax_rate_pct: 19 },
      { qty_ordered: 4, unit_price_ht: 25, tax_rate_pct: 7 },
    ]

    const t = computeDocumentTotals(lines, { fields: 'po', shippingCost: 10, additionalCosts: 5, stampDuty: 1 })

    assert.equal(t.subtotal, 350)
    assert.equal(t.discountTotal, 20)
    assert.equal(t.netTotal, 330)
    assert.deepEqual(t.taxBreakdown, [
      { rate: 7, base: 100, tax: 7 },
      { rate: 19, base: 230, tax: 43.7 },
    ])
    assert.equal(t.taxTotal, 50.7)
    assert.equal(t.grandTotal, 396.7)
  })

  it('supports IPR / GRN field names and ignores invalid numbers', () => {
    assert.equal(computeDocumentTotals([{ qty_requested: 3, estimated_unit_cost: 10 }], { fields: 'ipr' }).grandTotal, 30)
    assert.equal(computeDocumentTotals([{ qty_received: 2, unit_cost_ht: '12.5' }], { fields: 'grn' }).grandTotal, 25)
    assert.equal(computeLineAmounts({ qty: 'x', unit_price: 5 }).gross, 0)
  })

  it('handles absolute discounts capped at the gross amount', () => {
    const a = computeLineAmounts({ q: 1, p: 10, d: 50 }, { qty: 'q', price: 'p', discount: 'd', discountType: 'amount', tax: null })

    assert.equal(a.discount, 10)
    assert.equal(a.net, 0)
  })

  it('returns zeros for empty documents', () => {
    const t = computeDocumentTotals([])

    assert.equal(t.grandTotal, 0)
    assert.deepEqual(t.taxBreakdown, [])
  })
})

describe('document line validation', () => {
  const rules = { qty: 'qty_ordered', price: 'unit_price_ht' }

  it('requires qty > 0, price >= 0 and a catalog or ad-hoc item', () => {
    assert.deepEqual(Object.keys(validateLine({ qty_ordered: 0, unit_price_ht: -1 }, rules)).sort(), ['catalog_item_id', 'qty_ordered', 'unit_price_ht'])
    assert.deepEqual(validateLine({ catalog_item_id: 4, qty_ordered: 1, unit_price_ht: 0 }, rules), {})
    assert.deepEqual(validateLine({ is_ad_hoc: true, ad_hoc_name: 'Bolt', qty_ordered: 1, unit_price_ht: 2 }, rules), {})
    assert.ok(validateLine({ is_ad_hoc: true, ad_hoc_name: '  ', qty_ordered: 1, unit_price_ht: 2 }, rules).catalog_item_id)
  })

  it('treats a missing price as valid unless required', () => {
    const line = { catalog_item_id: 1, qty_ordered: 1 }

    assert.deepEqual(validateLine(line, rules), {})
    assert.ok(validateLine(line, { ...rules, priceRequired: true }).unit_price_ht)
  })

  it('keys errors by line index', () => {
    const errors = validateLines([{ catalog_item_id: 1, qty: 1, unit_price: 1 }, { qty: 0 }])

    assert.deepEqual(Object.keys(errors), ['1'])
    assert.ok(errors[1].qty)
  })

  it('builds default lines and hydrates from catalog resources', () => {
    const line = createLineFactory({ qty: 'qty_ordered', price: 'unit_price_ht' })()

    assert.equal(line.qty_ordered, 1)
    assert.ok(line._key)

    const hydrated = hydrateLineFromCatalog(line, {
      id: 9, code: 'BLT', name: { en: 'Bolt', fr: 'Boulon' }, uom_id: 3, uom: { code: 'PCS', name: { en: 'Piece' } },
    }, {
      locale: 'fr',
      variant: { id: 5, sku: 'BLT-M8', cost_price: '1.5', option_values: [{ value: { en: 'M8' } }] },
      map: { price: 'unit_price_ht' },
    })

    assert.equal(hydrated.catalog_item_id, 9)
    assert.equal(hydrated.variant_id, 5)
    assert.equal(hydrated.item_code, 'BLT-M8')
    assert.equal(hydrated.item_name, 'Boulon')
    assert.equal(hydrated.unit_label, 'PCS')
    assert.equal(hydrated.unit_price_ht, 1.5)
    assert.deepEqual(hydrated.variant_values, ['M8'])
  })

  it('moves items safely', () => {
    assert.deepEqual(moveItem([1, 2, 3], 0, 2), [2, 3, 1])
    assert.deepEqual(moveItem([1, 2, 3], 5, 0), [1, 2, 3])
  })
})

describe('document workflow', () => {
  const stateOf = (cfg, status) => Object.fromEntries(computeLifecycleSteps(workflowConfigs[cfg], status).map(s => [s.status, s.state]))

  it('mirrors the backend state machine', () => {
    assert.deepEqual(getNextStatuses(workflowConfigs.ipr, 'pending_approval'), ['approved', 'rejected', 'cancelled'])
    assert.deepEqual(getNextStatuses(workflowConfigs.po, 'closed'), [])
    assert.equal(isEditableStatus(workflowConfigs.ipr, 'rejected'), true)
    assert.equal(isEditableStatus(workflowConfigs.po, 'approved'), false)
    assert.equal(isEditableStatus(workflowConfigs.grn, 'under_qc'), true)
  })

  it('computes done/current/upcoming steps', () => {
    assert.deepEqual(stateOf('ipr', 'pending_approval'), {
      draft: 'done', pending_approval: 'current', approved: 'upcoming', converted_to_po: 'upcoming',
    })
    assert.equal(stateOf('rfq', 'awarded').awarded, 'current')
    assert.equal(stateOf('grn', 'partially_rejected').under_qc, 'current')
  })

  it('appends terminal-negative steps', () => {
    const rejected = computeLifecycleSteps(workflowConfigs.ipr, 'rejected')

    assert.equal(rejected.at(-1).state, 'terminal-negative')
    assert.equal(rejected.find(s => s.status === 'pending_approval').state, 'done')
    assert.equal(rejected.find(s => s.status === 'approved').state, 'upcoming')

    const cancelled = computeLifecycleSteps(workflowConfigs.po, 'cancelled', { history: ['draft', 'approved'] })

    assert.equal(cancelled.find(s => s.status === 'approved').state, 'done')
    assert.equal(cancelled.find(s => s.status === 'sent').state, 'upcoming')
  })

  it('exposes capabilities, honours permissions and confirms transitions', async () => {
    const doc = ref({ id: 7, status: 'pending_approval' })
    const calls = []

    const wf = useDocumentWorkflow(doc, {
      config: 'ipr',
      can: action => action !== 'update',
      transitionFn: async (id, payload) => { calls.push([id, payload]) 

        return { ok: true } },
    })

    assert.equal(wf.canEdit.value, false)
    assert.equal(wf.canApprove.value, true)
    assert.equal(wf.canReject.value, true)
    assert.equal(wf.canCancel.value, false)
    assert.equal(wf.requestTransition('cancelled'), false)

    assert.equal(wf.requestTransition('rejected'), true)
    assert.equal(wf.confirmState.requiresReason, true)
    assert.equal((await wf.confirmTransition()).ok, false)
    wf.confirmState.reason = 'Too expensive'
    assert.equal((await wf.confirmTransition()).ok, true)
    assert.deepEqual(calls, [[7, { status: 'rejected', rejection_reason: 'Too expensive' }]])
    assert.equal(wf.confirmState.open, false)
  })

  it('computes conversion targets', () => {
    const doc = ref({ id: 1, status: 'approved', converted_to_po_id: null })
    const wf = useDocumentWorkflow(doc, { config: 'ipr' })

    assert.deepEqual(wf.convertTargets.value.map(t => t.target), ['rfq', 'po'])
    doc.value = { id: 1, status: 'approved', converted_to_po_id: 3 }
    assert.equal(wf.canConvert.value, false)
    doc.value = { id: 2, status: 'sent' }
    assert.equal(useDocumentWorkflow(doc, { config: 'po' }).canConvert.value, true)
  })
})

describe('document traceability', () => {
  it('links rfq to its iprs and po', () => {
    const t = buildTraceability('rfq', { id: 5, reference: 'RFQ-5', status: 'awarded', ipr_ids: [1, 2], iprs: [{ id: 1, reference: 'IPR-1' }, { id: 2, reference: 'IPR-2' }], converted_to_po_id: 9 })

    assert.deepEqual(t.chain.map(n => `${n.type}:${n.id}`), ['ipr:1', 'ipr:2', 'rfq:5', 'po:9'])
    assert.equal(t.upstream[0].reference, 'IPR-1')
    assert.deepEqual(t.upstream[0].route, { name: 'procurement-ipr-edit-id', params: { id: 1 } })
    assert.equal(t.downstream[0].resolved, false)
    assert.equal(t.chain[2].current, true)
  })

  it('builds the chain from a po including receipts and stock movements', () => {
    const t = buildTraceability('po', { id: 9, reference: 'PO-9', status: 'sent', rfq_id: 5, ipr_ids: [1], goods_receipt_notes: [{ id: 3, reference: 'GRN-3', status: 'confirmed' }], stock_movements: [{ id: 11, type: 'in' }] })

    assert.deepEqual(t.chain.map(n => n.type), ['ipr', 'rfq', 'po', 'grn', 'stock_movement'])
    assert.equal(t.downstream[1].route, null)
  })

  it('derives po/rfq/ipr upstream of a grn and dedupes', () => {
    const t = buildTraceability('grn', { id: 3, status: 'draft', po_id: 9, purchase_order: { id: 9, reference: 'PO-9', status: 'sent', rfq_id: 5, ipr_ids: [1, 1] } })

    assert.deepEqual(t.upstream.map(n => `${n.type}:${n.id}`), ['ipr:1', 'rfq:5', 'po:9'])
    assert.equal(t.upstream[2].reference, 'PO-9')
  })

  it('handles ipr conversions and empty documents', () => {
    assert.deepEqual(buildTraceability('ipr', { id: 1, converted_to_po_id: 4 }).downstream.map(n => n.id), [4])
    assert.deepEqual(buildTraceability('ipr', null).chain, [])
  })
})
