/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Logistics Module Navigation (Vertical)
 * ──────────────────────────────────────────────────────────────
 *
 *  Navigation structure (simplified, user-oriented):
 *    • Dashboard            — logistics overview
 *    • Delivery Work Order  — unified DWO list (table + board)
 *    • Delivery Notes       — BL / shipping documents
 *    • Returns              — return orders
 *    • Delivery Providers   — external logistics providers
 *
 *  "Routes & Waves" removed — accessible from within DWO workflow.
 */

export default [
  { heading: 'Logistics' },

  {
    title: 'Dashboard',
    to: 'logistics',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },

  {
    title: 'Delivery Work Order',
    to: 'logistics-dwos-list',
    icon: { icon: 'tabler-truck-delivery' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },

  {
    title: 'Delivery Notes',
    to: 'logistics-delivery-notes-list',
    icon: { icon: 'tabler-file-certificate' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },

  {
    title: 'Returns',
    to: 'logistics-returns-list',
    icon: { icon: 'tabler-arrow-back-up' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },

  { heading: 'Providers' },

  {
    title: 'Delivery Service Providers',
    to: 'logistics-delivery-providers-list',
    icon: { icon: 'tabler-building-warehouse' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },
]
