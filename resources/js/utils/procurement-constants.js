/**
 * procurement-constants.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Single source of truth for all status/urgency maps used across the
 * Supply Chain & Procurement module.
 *
 * Previously duplicated in 15+ files — now import from here instead.
 *
 * Usage:
 *   import { resolveStatusVariant, resolveStatusIcon, resolveUrgencyVariant } from '@/utils/procurement-constants'
 */

// ── IPR Statuses ──────────────────────────────────────────────────────────────
export const IPR_STATUS_MAP = {
  // status.procurement.draft
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'Brouillon' },

  // status.procurement.pending_approval
  pending_approval: { color: 'warning', icon: 'tabler-clock', label: "En attente d'approbation" },

  // status.procurement.approved
  approved: { color: 'success', icon: 'tabler-check', label: 'Approuvé' },

  // status.procurement.rejected
  rejected: { color: 'error', icon: 'tabler-x', label: 'Rejeté' },

  // status.procurement.merged
  merged: { color: 'info', icon: 'tabler-git-merge', label: 'Fusionné' },

  // status.procurement.converted_to_po
  converted_to_po: { color: 'primary', icon: 'tabler-transform', label: 'Converti en BC' },

  // status.procurement.cancelled
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'Annulé' },
}

/**
 * Returns chip data for IPR status
 * @param {string} status
 * @returns {{ text: string, color: string }}
 */
export const getIprStatusChip = status => ({
  text: IPR_STATUS_MAP[status]?.label || status,
  color: IPR_STATUS_MAP[status]?.color || 'secondary',
})

// ── RFQ Statuses ──────────────────────────────────────────────────────────────
export const RFQ_STATUS_MAP = {
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'Brouillon' },
  sent: { color: 'primary', icon: 'tabler-send', label: 'Envoyé' },
  awaiting_responses: { color: 'warning', icon: 'tabler-clock', label: 'En attente de réponses' },
  under_evaluation: { color: 'info', icon: 'tabler-git-compare', label: 'En évaluation' },
  awarded: { color: 'success', icon: 'tabler-award', label: 'Adjugé' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'Annulé' },
}

/**
 * Returns chip data for RFQ status
 * @param {string} status
 * @returns {{ text: string, color: string }}
 */
export const getRfqStatusChip = status => ({
  text: RFQ_STATUS_MAP[status]?.label || status,
  color: RFQ_STATUS_MAP[status]?.color || 'secondary',
})

// ── PO Statuses ───────────────────────────────────────────────────────────────
export const PO_STATUS_MAP = {
  // status.procurement.draft
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'Brouillon' },

  // status.procurement.pending_approval
  pending_approval: { color: 'warning', icon: 'tabler-clock', label: "En attente d'approbation" },

  // status.procurement.approved
  approved: { color: 'success', icon: 'tabler-check', label: 'Approuvé' },

  // status.procurement.sent
  sent: { color: 'primary', icon: 'tabler-send', label: 'Envoyé' },

  // status.procurement.acknowledged
  acknowledged: { color: 'info', icon: 'tabler-eye-check', label: 'Accusé réception' },

  // status.procurement.in_delivery
  in_delivery: { color: 'warning', icon: 'tabler-truck', label: 'En cours de livraison' },

  // status.procurement.partially_received
  partially_received: { color: 'warning', icon: 'tabler-package-import', label: 'Partiellement reçu' },

  // status.procurement.fully_received
  fully_received: { color: 'success', icon: 'tabler-package-check', label: 'Entièrement reçu' },
  closed: { color: 'secondary', icon: 'tabler-lock', label: 'Clôturé' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'Annulé' },
}

/**
 * Returns chip data for PO status
 * @param {string} status
 * @returns {{ text: string, color: string }}
 */
export const getPoStatusChip = status => ({
  text: PO_STATUS_MAP[status]?.label || status,
  color: PO_STATUS_MAP[status]?.color || 'secondary',
})

// ── GRN Statuses ──────────────────────────────────────────────────────────────
export const GRN_STATUS_MAP = {
  // status.procurement.draft
  draft: { color: 'default', icon: 'tabler-file-text', label: 'Brouillon' },

  // status.procurement.under_qc
  under_qc: { color: 'warning', icon: 'tabler-test-pipe', label: 'Contrôle QC' },

  // status.procurement.confirmed
  confirmed: { color: 'info', icon: 'tabler-circle-check', label: 'Confirmé' },

  // status.procurement.partially_rejected
  partially_rejected: { color: 'orange', icon: 'tabler-package-off', label: 'Partiellement rejeté' },

  // status.procurement.fully_rejected
  fully_rejected: { color: 'error', icon: 'tabler-x', label: 'Rejeté' },

  // status.procurement.stocked
  stocked: { color: 'success', icon: 'tabler-building-warehouse', label: 'Mis en stock' },
}

/**
 * Returns chip data for GRN status
 * @param {string} status
 * @returns {{ text: string, color: string }}
 */
export const getGrnStatusChip = status => ({
  text: GRN_STATUS_MAP[status]?.label || status,
  color: GRN_STATUS_MAP[status]?.color || 'secondary',
})

// ── Unified status resolver (works across all doc types) ──────────────────────
const UNIFIED_STATUS_MAP = {
  ...IPR_STATUS_MAP,
  ...RFQ_STATUS_MAP,
  ...PO_STATUS_MAP,
  ...GRN_STATUS_MAP,
}

/**
 * @param {string} status
 * @returns {string} Vuetify color name
 */
export const resolveStatusVariant = status =>
  UNIFIED_STATUS_MAP[status]?.color ?? 'secondary'

/**
 * @param {string} status
 * @returns {string} Tabler icon string
 */
export const resolveStatusIcon = status =>
  UNIFIED_STATUS_MAP[status]?.icon ?? 'tabler-help'

/**
 * @param {string} status
 * @returns {string} Human-readable label
 */
export const resolveStatusLabel = status =>
  UNIFIED_STATUS_MAP[status]?.label ?? status?.replace(/_/g, ' ') ?? '—'

// ── Urgency ───────────────────────────────────────────────────────────────────
export const URGENCY_MAP = {
  // status.procurement.low
  low: { color: 'info', icon: 'tabler-arrows-down', label: 'Basse' },

  // status.procurement.normal
  normal: { color: 'primary', icon: 'tabler-minus', label: 'Normale' },

  // status.procurement.urgent
  urgent: { color: 'warning', icon: 'tabler-alert-triangle', label: 'Urgente' },

  // status.procurement.critical
  critical: { color: 'error', icon: 'tabler-flame', label: 'Critique' },
}

/**
 * Returns chip data for Urgency
 * @param {string} urgency
 * @returns {{ text: string, color: string }}
 */
export const getUrgencyChip = urgency => ({
  text: URGENCY_MAP[urgency]?.label || urgency,
  color: URGENCY_MAP[urgency]?.color || 'primary',
})

export const resolveUrgencyVariant = urgency =>
  URGENCY_MAP[urgency]?.color ?? 'primary'

export const resolveUrgencyIcon = urgency =>
  URGENCY_MAP[urgency]?.icon ?? 'tabler-help'

export const resolveUrgencyLabel = urgency =>
  URGENCY_MAP[urgency]?.label ?? urgency ?? '—'

// ── Select Options (for filter dropdowns) ─────────────────────────────────────
export const iprStatusOptions = Object.entries(IPR_STATUS_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))

export const rfqStatusOptions = Object.entries(RFQ_STATUS_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))

export const poStatusOptions = Object.entries(PO_STATUS_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))

export const grnStatusOptions = Object.entries(GRN_STATUS_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))

export const urgencyOptions = Object.entries(URGENCY_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))

export const ALLOWED_PROCUREMENT_TRANSITIONS = {
  IPR: {
    draft: ['pending_approval', 'cancelled'],
    pending_approval: ['approved', 'rejected', 'cancelled'],
    approved: ['cancelled'],
    rejected: ['draft', 'cancelled'],
    merged: [],
    converted_to_po: [],
    cancelled: [],
  },
  RFQ: {
    draft: ['sent', 'cancelled'],
    sent: ['awaiting_responses', 'cancelled'],
    awaiting_responses: ['under_evaluation', 'cancelled'],
    under_evaluation: ['awarded', 'cancelled'],
    awarded: [],
    cancelled: [],
  },
  PO: {
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
  GRN: {
    draft: ['under_qc'],
    under_qc: ['confirmed', 'partially_rejected', 'fully_rejected'],
    partially_rejected: ['confirmed', 'fully_rejected'],
    confirmed: [],
    fully_rejected: [],
    stocked: [],
  },
}

export const allowedProcurementStatuses = (documentType, currentStatus, extras = {}) => {
  const from = currentStatus || 'draft'
  let next = [...(ALLOWED_PROCUREMENT_TRANSITIONS[documentType]?.[from] || [])]

  if (documentType === 'PO' && from === 'draft' && extras.approvalRequired) {
    next = next.filter(status => status !== 'approved')
  }

  return [from, ...next]
}

// ── Trigger source options (IPR) ──────────────────────────────────────────────
export const iprSourceOptions = [
  { title: 'Manual',        value: 'manual' },
  { title: 'From Stock',    value: 'stock_threshold' },
  { title: 'From Project',  value: 'project' },
  { title: 'Recurring',     value: 'recurring' },
]

// ── Payment Terms ─────────────────────────────────────────────────────────────
export const paymentTermsOptions = [
  { title: 'Immediate',    value: 'immediate' },
  { title: 'Net 15 days',  value: 'net_15' },
  { title: 'Net 30 days',  value: 'net_30' },
  { title: 'Net 45 days',  value: 'net_45' },
  { title: 'Net 60 days',  value: 'net_60' },
  { title: 'Net 90 days',  value: 'net_90' },
]

// ── Currency ──────────────────────────────────────────────────────────────────
export const currencyOptions = [
  { title: 'TND — Tunisian Dinar', value: 'TND' },
  { title: 'EUR — Euro',           value: 'EUR' },
  { title: 'USD — US Dollar',      value: 'USD' },
]

// ── Movement Types ────────────────────────────────────────────────────────────
export const MOVEMENT_TYPE_MAP = {
  grn_receipt: { color: 'info', icon: 'tabler-truck-delivery', label: 'Réception GRN' },
  adjustment_plus: { color: 'success', icon: 'tabler-plus', label: 'Ajustement (+)' },
  adjustment_minus: { color: 'error', icon: 'tabler-minus', label: 'Ajustement (-)' },
  transfer_out: { color: 'warning', icon: 'tabler-arrow-up-right', label: 'Transfert (Sortie)' },
  transfer_in: { color: 'teal', icon: 'tabler-arrow-down-left', label: 'Transfert (Entrée)' },
  sales_dispatch: { color: 'primary', icon: 'tabler-package-export', label: 'Expédition Vente' },
  initial_load: { color: 'secondary', icon: 'tabler-database-import', label: 'Chargement Initial' },
}

export const getMovementTypeChip = type => ({
  text: MOVEMENT_TYPE_MAP[type]?.label || type,
  color: MOVEMENT_TYPE_MAP[type]?.color || 'default',
})

export const movementTypeOptions = Object.entries(MOVEMENT_TYPE_MAP).map(([value, { label }]) => ({
  title: label,
  value,
}))
