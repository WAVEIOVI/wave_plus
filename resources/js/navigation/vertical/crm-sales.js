/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Procurement Module Navigation
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboard',
    to: 'crm-sales',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
  {
    title: 'Price Request (PR)',
    to: 'crm-price-request-list',
    icon: { icon: 'tabler-receipt' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
  {
    title: 'Quotation (QT)',
    to: 'crm-quotation-list',
    icon: { icon: 'tabler-file-invoice' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
  {
    title: 'Sales Order (SO)',
    to: 'crm-sales-order-list',
    icon: { icon: 'tabler-shopping-bag' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },

  {
    title: 'Price List',
    to: 'crm-sales-price-lists-list',
    icon: { icon: 'tabler-tag' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
  {
    title: 'Contract (CT)',
    to: 'crm-contract-list',
    icon: { icon: 'tabler-writing-sign' },
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
]
