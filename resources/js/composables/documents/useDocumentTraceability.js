import { computed, reactive, ref, unref } from 'vue'

/**
 * Default route builders (route names from `pages/supply-chain-procurement/**`
 * `definePage`). Types without a page (stock movements, supplier invoices)
 * resolve to `null` unless `options.routes` supplies a builder.
 */
export const TRACEABILITY_ROUTES = Object.freeze({
  ipr: id => ({ name: 'procurement-ipr-edit-id', params: { id } }),
  rfq: id => ({ name: 'procurement-rfq-edit-id', params: { id } }),
  po: id => ({ name: 'procurement-po-edit-id', params: { id } }),
  grn: id => ({ name: 'procurement-grn-edit-id', params: { id } }),
})

const FLOW_ORDER = ['ipr', 'rfq', 'po', 'grn', 'supplier_invoice', 'stock_movement']

const asArray = v => (Array.isArray(v) ? v : (v && Array.isArray(v.data) ? v.data : []))

/**
 * Build a traceability node.
 *
 * @param {string} type `ipr|rfq|po|grn|supplier_invoice|stock_movement`
 * @param {any} id
 * @param {{reference?: string|null, status?: string|null, routes?: object}} [extra]
 * @returns {{type:string, id:any, reference:string|null, status:string|null, route:(object|null), resolved:boolean}}
 */
export function makeNode(type, id, extra = {}) {
  const builder = extra.routes?.[type] || TRACEABILITY_ROUTES[type]
  const reference = extra.reference ?? null
  const status = extra.status ?? null

  return {
    type,
    id,
    reference,
    status,
    route: builder && id != null ? builder(id) : null,
    resolved: reference !== null,
  }
}

const dedupe = nodes => {
  const seen = new Set()

  return nodes.filter(n => {
    const key = `${n.type}:${n.id}`

    if (seen.has(key)) return false
    seen.add(key)

    return true
  })
}

const sortByFlow = nodes => [...nodes].sort((a, b) => FLOW_ORDER.indexOf(a.type) - FLOW_ORDER.indexOf(b.type))

/**
 * Pure builder: derive upstream/downstream links from a document using only
 * fields exposed by the backend resources:
 *
 * - IPR: `converted_to_po_id` (down), `merged_into_ipr_id` (down)
 * - RFQ: `ipr_ids` / `iprs[{id,reference}]` (up), `converted_to_po_id` (down)
 * - PO: `rfq_id`, `ipr_ids` (up); `goods_receipt_notes|grns` (down, when loaded)
 * - GRN: `po_id` / `purchase_order{id,reference,status,rfq_id,ipr_ids}` (up)
 * - Any: `stock_movements`, `supplier_invoices` (down, when provided)
 *
 * @param {string} type Document type.
 * @param {object|null} doc
 * @param {{routes?: object}} [options]
 * @returns {{upstream: object[], downstream: object[], current: (object|null), chain: object[]}}
 */
export function buildTraceability(type, doc, options = {}) {
  if (!doc) return { upstream: [], downstream: [], current: null, chain: [] }

  const routes = options.routes
  const node = (t, id, extra = {}) => makeNode(t, id, { routes, ...extra })
  const up = []
  const down = []

  const iprRefs = list => asArray(list).forEach(entry => {
    if (entry && typeof entry === 'object') up.push(node('ipr', entry.id, { reference: entry.reference, status: entry.status }))
    else if (entry != null) up.push(node('ipr', entry))
  })

  if (type === 'ipr') {
    if (doc.converted_to_po_id) down.push(node('po', doc.converted_to_po_id))
    if (doc.merged_into_ipr_id) down.push(node('ipr', doc.merged_into_ipr_id))
  }

  if (type === 'rfq') {
    iprRefs(doc.iprs?.length ? doc.iprs : doc.ipr_ids)
    if (doc.converted_to_po_id) down.push(node('po', doc.converted_to_po_id))
  }

  if (type === 'po') {
    if (doc.rfq_id) up.push(node('rfq', doc.rfq_id, { reference: doc.rfq?.reference, status: doc.rfq?.status }))
    iprRefs(doc.iprs?.length ? doc.iprs : doc.ipr_ids)
    asArray(doc.goods_receipt_notes || doc.grns).forEach(g => down.push(node('grn', g.id, { reference: g.reference, status: g.status })))
  }

  if (type === 'grn') {
    const po = doc.purchase_order && doc.purchase_order.id ? doc.purchase_order : null

    if (doc.po_id || po) up.push(node('po', doc.po_id || po.id, { reference: po?.reference, status: po?.status }))
    if (po?.rfq_id) up.push(node('rfq', po.rfq_id))
    iprRefs(po?.ipr_ids)
  }

  asArray(doc.supplier_invoices).forEach(i => down.push(node('supplier_invoice', i.id, { reference: i.reference || i.invoice_number, status: i.status })))
  asArray(doc.stock_movements).forEach(m => down.push(node('stock_movement', m.id, { reference: m.reference || (m.id != null ? `#${m.id}` : null), status: m.type || m.status })))

  const current = node(type, doc.id, { reference: doc.reference, status: doc.status })
  const upstream = sortByFlow(dedupe(up))
  const downstream = sortByFlow(dedupe(down))

  return { upstream, downstream, current, chain: [...upstream, { ...current, current: true }, ...downstream] }
}

/**
 * useDocumentTraceability
 * Unified chain IPR ↔ RFQ ↔ PO ↔ GRN ↔ invoices/stock movements. Nodes whose
 * reference/status are unknown can be resolved lazily through `resolver`.
 *
 * @param {import('vue').Ref<object|null>} documentRef
 * @param {{
 *   type: string,
 *   routes?: object,
 *   resolver?: (node: {type: string, id: any}) => Promise<{reference?: string, status?: string}|null>,
 *   autoResolve?: boolean,
 * }} options
 */
export function useDocumentTraceability(documentRef, options = {}) {
  const resolved = reactive({})
  const resolving = ref(false)
  const base = computed(() => buildTraceability(options.type, unref(documentRef), { routes: options.routes }))

  const enrich = node => {
    const extra = resolved[`${node.type}:${node.id}`]

    return extra ? { ...node, ...extra, resolved: true } : node
  }

  const upstream = computed(() => base.value.upstream.map(enrich))
  const downstream = computed(() => base.value.downstream.map(enrich))
  const chain = computed(() => base.value.chain.map(n => (n.current ? n : enrich(n))))
  const unresolved = computed(() => [...upstream.value, ...downstream.value].filter(n => !n.resolved))

  /**
   * Resolve missing references through the injected resolver (each node once).
   *
   * @returns {Promise<void>}
   */
  const resolve = async () => {
    if (!options.resolver || resolving.value) return
    const pendingNodes = unresolved.value.filter(n => !(`${n.type}:${n.id}` in resolved))

    if (!pendingNodes.length) return
    resolving.value = true
    try {
      await Promise.all(pendingNodes.map(async n => {
        const key = `${n.type}:${n.id}`

        try {
          const data = await options.resolver({ type: n.type, id: n.id })

          resolved[key] = data ? { reference: data.reference ?? null, status: data.status ?? null } : {}
        } catch {
          resolved[key] = {}
        }
      }))
    } finally {
      resolving.value = false
    }
  }

  return { upstream, downstream, chain, unresolved, resolving, resolve, hasLinks: computed(() => base.value.upstream.length + base.value.downstream.length > 0) }
}
