/**
 * Single source of truth for CRM & Sales statuses.
 */

export const PRICE_REQUEST_STATUS_MAP = {
  new: { color: 'primary', icon: 'tabler-file-text', label: 'New' },
  pending_internal: { color: 'warning', icon: 'tabler-clock', label: 'Pending Internal' },
  costing: { color: 'info', icon: 'tabler-calculator', label: 'Costing' },
  responded: { color: 'success', icon: 'tabler-check', label: 'Responded' },
  converted_to_quote: { color: 'secondary', icon: 'tabler-transform', label: 'Converted to Quote' },
  cancelled: { color: 'error', icon: 'tabler-ban', label: 'Cancelled' },
}

export const QUOTATION_STATUS_MAP = {
  draft: { color: 'default', icon: 'tabler-file-text', label: 'Draft' },
  sent: { color: 'info', icon: 'tabler-send', label: 'Sent' },
  under_review: { color: 'warning', icon: 'tabler-git-compare', label: 'Under Review' },
  accepted: { color: 'success', icon: 'tabler-check', label: 'Accepted' },
  rejected: { color: 'error', icon: 'tabler-x', label: 'Rejected' },
  expired: { color: 'error', icon: 'tabler-clock-off', label: 'Expired' },
  converted: { color: 'secondary', icon: 'tabler-transform', label: 'Converted' },
  archived: { color: 'secondary', icon: 'tabler-archive', label: 'Archived' },
}

export const SALES_ORDER_STATUS_MAP = {
  draft: { color: 'default', icon: 'tabler-file-text', label: 'Draft' },
  submitted: { color: 'info', icon: 'tabler-send', label: 'Submitted' },
  under_review: { color: 'warning', icon: 'tabler-search', label: 'Under Review' },
  accepted: { color: 'success', icon: 'tabler-check', label: 'Accepted' },
  dispatched: { color: 'primary', icon: 'tabler-truck', label: 'Dispatched' },
  in_fulfillment: { color: 'primary', icon: 'tabler-progress', label: 'In Fulfillment' },
  completed: { color: 'success', icon: 'tabler-circle-check', label: 'Completed' },
  rejected: { color: 'error', icon: 'tabler-x', label: 'Rejected' },
  cancelled: { color: 'error', icon: 'tabler-ban', label: 'Cancelled' },
}

export const CONTRACT_STATUS_MAP = {
  draft: { color: 'default', icon: 'tabler-file-text', label: 'Draft' },
  negotiation: { color: 'warning', icon: 'tabler-messages', label: 'Negotiation' },
  active: { color: 'success', icon: 'tabler-rocket', label: 'Active' },
  expiring_soon: { color: 'warning', icon: 'tabler-clock', label: 'Expiring Soon' },
  renewed: { color: 'info', icon: 'tabler-refresh', label: 'Renewed' },
  terminated: { color: 'error', icon: 'tabler-ban', label: 'Terminated' },
  expired: { color: 'error', icon: 'tabler-clock-off', label: 'Expired' },
}

export const PRICE_LIST_STATUS_MAP = {
  draft: { color: 'default', icon: 'tabler-file-text', label: 'Draft' },
  active: { color: 'success', icon: 'tabler-check', label: 'Active' },
  expired: { color: 'error', icon: 'tabler-clock-off', label: 'Expired' },
}

const toOptions = map => Object.entries(map).map(([value, { label }]) => ({ title: label, value }))

export const priceRequestStatusOptions = toOptions(PRICE_REQUEST_STATUS_MAP)
export const quotationStatusOptions = toOptions(QUOTATION_STATUS_MAP)

// Only canonical commercial statuses — excludes deprecated operational statuses (dispatched, in_fulfillment)
const CANONICAL_SO_STATUSES = ['draft', 'submitted', 'under_review', 'accepted', 'completed', 'rejected', 'cancelled']

export const salesOrderStatusOptions = CANONICAL_SO_STATUSES.map(v => ({
  title: SALES_ORDER_STATUS_MAP[v]?.label || v,
  value: v,
}))

export const contractStatusOptions = toOptions(CONTRACT_STATUS_MAP)
export const priceListStatusOptions = toOptions(PRICE_LIST_STATUS_MAP)

export const getPriceRequestStatusChip = status => ({
  text: PRICE_REQUEST_STATUS_MAP[status]?.label || status,
  color: PRICE_REQUEST_STATUS_MAP[status]?.color || 'secondary',
})

export const getQuotationStatusChip = status => ({
  text: QUOTATION_STATUS_MAP[status]?.label || status,
  color: QUOTATION_STATUS_MAP[status]?.color || 'default',
})

export const getSalesOrderStatusChip = status => ({
  text: SALES_ORDER_STATUS_MAP[status]?.label || status,
  color: SALES_ORDER_STATUS_MAP[status]?.color || 'default',
})

export const getContractStatusChip = status => ({
  text: CONTRACT_STATUS_MAP[status]?.label || status,
  color: CONTRACT_STATUS_MAP[status]?.color || 'default',
})

export const getPriceListStatusChip = status => ({
  text: PRICE_LIST_STATUS_MAP[status]?.label || status,
  color: PRICE_LIST_STATUS_MAP[status]?.color || 'default',
})

export const ALLOWED_CRM_TRANSITIONS = {
  PriceRequest: {
    new: ['pending_internal', 'cancelled'],
    pending_internal: ['costing', 'cancelled'],
    costing: ['responded', 'cancelled'],
    responded: ['cancelled'],
    converted_to_quote: [],
    cancelled: [],
  },
  Quotation: {
    draft: ['sent', 'expired'],
    sent: ['under_review', 'rejected', 'expired'],
    under_review: ['accepted', 'rejected', 'sent', 'expired'],
    accepted: [],
    rejected: ['draft'],
    expired: ['draft'],
    converted: [],
    archived: [],
  },
  Contract: {
    draft: ['negotiation', 'terminated'],
    negotiation: ['active', 'draft', 'terminated'],
    active: ['expiring_soon', 'renewed', 'terminated', 'expired'],
    expiring_soon: ['renewed', 'terminated', 'expired', 'active'],
    renewed: ['active', 'expired', 'terminated'],
    terminated: [],
    expired: [],
  },
  PriceList: {
    draft: ['active'],
    active: ['expired', 'draft'],
    expired: ['draft'],
  },
  SalesOrder: {
    draft: ['cancelled'],
    submitted: ['cancelled'],
    under_review: ['rejected', 'cancelled'],
    accepted: ['cancelled'],
    dispatched: ['cancelled'],
    in_fulfillment: ['cancelled'],
    completed: [],
    rejected: [],
    cancelled: [],
  },
}

export const allowedCrmStatuses = (documentType, currentStatus, extras = {}) => {
  const from = currentStatus || 'draft'
  let next = [...(ALLOWED_CRM_TRANSITIONS[documentType]?.[from] || [])]

  if (documentType === 'Quotation' && extras.approvalRequired && from === 'sent') {
    next = next.filter(status => status !== 'accepted')
  }

  return [from, ...next]
}
