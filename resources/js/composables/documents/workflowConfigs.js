/* eslint-disable camelcase */
/**
 * Document type configs derived from the backend `ProcurementStateMachine`
 * (app/Modules/Procurement/Services/ProcurementStateMachine.php). Keep the
 * constants in sync with the PHP class; the backend remains authoritative.
 *
 * Shape of a config:
 * - `type`            document key (`ipr|rfq|po|grn`)
 * - `transitions`     `{ from: [to, ...] }` (user transitions)
 * - `editableStatuses`, `deletableStatuses`
 * - `lifecycle`       ordered happy-path statuses
 * - `aliases`         `{ status: lifecycleStatus }` for intermediate statuses
 * - `negative`        `{ status: anchorLifecycleStatus|null }` terminal-negative statuses
 * - `transitionMeta`  per target status: `{ action, permission, requiresReason, reasonField, commentField }`
 * - `convert`         conversion targets `{ target, fromStatuses, blockedBy? }`
 * - `routeName`       edit route name
 * - `statusEndpointField` payload key for the target status (always `status`)
 */

const ipr = {
  type: 'ipr',
  routeName: 'procurement-ipr-edit-id',
  transitions: {
    draft: ['pending_approval', 'cancelled'],
    pending_approval: ['approved', 'rejected', 'cancelled'],
    approved: ['cancelled'],
    rejected: ['draft', 'cancelled'],
    merged: [],
    converted_to_po: [],
    cancelled: [],
  },
  editableStatuses: ['draft', 'rejected'],
  deletableStatuses: ['draft', 'cancelled'],
  lifecycle: ['draft', 'pending_approval', 'approved', 'converted_to_po'],
  aliases: {},
  negative: { rejected: 'pending_approval', cancelled: null, merged: 'approved' },
  transitionMeta: {
    pending_approval: { action: 'submit', permission: 'update' },
    approved: { action: 'approve', permission: 'approve', commentField: 'approval_note' },
    rejected: { action: 'reject', permission: 'approve', requiresReason: true, reasonField: 'rejection_reason' },
    draft: { action: 'reopen', permission: 'update' },
    cancelled: { action: 'cancel', permission: 'update' },
  },
  convert: [
    { target: 'rfq', fromStatuses: ['approved'], blockedBy: 'converted_to_po_id' },
    { target: 'po', fromStatuses: ['approved'], blockedBy: 'converted_to_po_id' },
  ],
}

const rfq = {
  type: 'rfq',
  routeName: 'procurement-rfq-edit-id',
  transitions: {
    draft: ['sent', 'cancelled'],
    sent: ['awaiting_responses', 'cancelled'],
    awaiting_responses: ['under_evaluation', 'cancelled'],
    under_evaluation: ['awarded', 'cancelled'],
    awarded: [],
    cancelled: [],
  },
  editableStatuses: ['draft'],
  deletableStatuses: ['draft', 'cancelled'],
  lifecycle: ['draft', 'sent', 'awaiting_responses', 'under_evaluation', 'awarded'],
  aliases: {},
  negative: { cancelled: null },
  transitionMeta: {
    sent: { action: 'submit', permission: 'update' },
    awaiting_responses: { action: 'advance', permission: 'update' },
    under_evaluation: { action: 'advance', permission: 'update' },
    awarded: { action: 'approve', permission: 'approve' },
    cancelled: { action: 'cancel', permission: 'update' },
  },
  convert: [
    { target: 'po', fromStatuses: ['awarded'], blockedBy: 'converted_to_po_id' },
  ],
}

const po = {
  type: 'po',
  routeName: 'procurement-po-edit-id',
  transitions: {
    draft: ['pending_approval', 'approved', 'cancelled'],
    pending_approval: ['approved', 'cancelled'],
    approved: ['sent', 'cancelled'],
    sent: ['acknowledged', 'in_delivery', 'cancelled'],
    acknowledged: ['in_delivery', 'cancelled'],
    in_delivery: ['cancelled'],
    partially_received: ['closed'],
    fully_received: ['closed'],
    closed: [],
    cancelled: [],
  },
  editableStatuses: ['draft', 'pending_approval'],
  deletableStatuses: ['draft', 'cancelled'],
  receivableStatuses: ['sent', 'acknowledged', 'in_delivery', 'partially_received'],
  lifecycle: ['draft', 'pending_approval', 'approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received', 'fully_received', 'closed'],
  aliases: {},
  negative: { cancelled: null },
  transitionMeta: {
    pending_approval: { action: 'submit', permission: 'update' },
    approved: { action: 'approve', permission: 'approve' },
    sent: { action: 'send', permission: 'update' },
    acknowledged: { action: 'advance', permission: 'update' },
    in_delivery: { action: 'advance', permission: 'update' },
    closed: { action: 'close', permission: 'update' },
    cancelled: { action: 'cancel', permission: 'update' },
  },
  convert: [
    { target: 'grn', fromStatuses: ['sent', 'acknowledged', 'in_delivery', 'partially_received'] },
  ],
}

const grn = {
  type: 'grn',
  routeName: 'procurement-grn-edit-id',
  transitions: {
    draft: ['under_qc'],
    under_qc: ['confirmed', 'partially_rejected', 'fully_rejected'],
    partially_rejected: ['confirmed', 'fully_rejected'],
    confirmed: [],
    fully_rejected: [],
    stocked: [],
  },
  editableStatuses: ['draft', 'under_qc', 'partially_rejected'],
  deletableStatuses: ['draft', 'under_qc', 'partially_rejected', 'fully_rejected'],
  lifecycle: ['draft', 'under_qc', 'confirmed', 'stocked'],
  aliases: { partially_rejected: 'under_qc' },
  negative: { fully_rejected: 'under_qc' },
  transitionMeta: {
    under_qc: { action: 'submit', permission: 'update' },
    confirmed: { action: 'approve', permission: 'approve' },
    partially_rejected: { action: 'reject', permission: 'approve', requiresReason: false },
    fully_rejected: { action: 'reject', permission: 'approve', requiresReason: true },
  },
  convert: [],
}

/** Workflow configs keyed by document type. */
export const workflowConfigs = Object.freeze({ ipr, rfq, po, grn })

/**
 * Resolve a config from a type key or pass an object through.
 *
 * @param {string|object} typeOrConfig
 * @returns {object}
 */
export function resolveWorkflowConfig(typeOrConfig) {
  if (typeof typeOrConfig === 'string') {
    const cfg = workflowConfigs[typeOrConfig]

    if (!cfg) throw new Error(`Unknown workflow config "${typeOrConfig}"`)

    return cfg
  }

  return typeOrConfig
}

/**
 * Statuses reachable from `status` (empty for unknown/terminal statuses).
 *
 * @param {object} config
 * @param {string} status
 * @returns {string[]}
 */
export function getNextStatuses(config, status) {
  return [...(config?.transitions?.[status] || [])]
}

/**
 * Whether a document can be edited in a status.
 *
 * @param {object} config
 * @param {string} status
 * @returns {boolean}
 */
export function isEditableStatus(config, status) {
  return (config?.editableStatuses || []).includes(status)
}

/**
 * Compute the lifecycle stepper. Each step has a state:
 * `done | current | upcoming | terminal-negative`. A negative status (e.g.
 * `rejected`, `cancelled`) is appended as a `terminal-negative` step; steps up
 * to its anchor (or `history`) are `done`.
 *
 * @param {object} config
 * @param {string} status
 * @param {{history?: string[]}} [options] Visited statuses when known (used for negative statuses without anchor).
 * @returns {{status:string, state:'done'|'current'|'upcoming'|'terminal-negative', index:number}[]}
 */
export function computeLifecycleSteps(config, status, options = {}) {
  const order = config?.lifecycle || []
  const negative = config?.negative || {}
  const steps = order.map((s, index) => ({ status: s, key: s, state: 'upcoming', index }))

  if (Object.prototype.hasOwnProperty.call(negative, status)) {
    const anchor = negative[status]
    const history = options.history || []
    const anchorIndex = anchor ? order.indexOf(anchor) : Math.max(-1, ...history.map(h => order.indexOf(config.aliases?.[h] || h)))

    steps.forEach(step => {
      if (step.index <= anchorIndex) step.state = 'done'
    })
    if (anchorIndex < 0 && steps[0] && !anchor) steps[0].state = 'done'
    steps.push({ status, key: status, state: 'terminal-negative', index: steps.length })

    return steps
  }

  const resolved = config?.aliases?.[status] || status
  const currentIndex = order.indexOf(resolved)

  steps.forEach(step => {
    if (currentIndex < 0) return
    if (step.index < currentIndex) step.state = 'done'
    else if (step.index === currentIndex) step.state = 'current'
  })

  return steps
}
