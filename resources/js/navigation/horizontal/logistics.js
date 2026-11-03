/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Logistics Module Navigation (Horizontal)
 * ──────────────────────────────────────────────────────────────
 *
 *  Mirrors vertical/logistics.js — see that file for full changelog.
 *  Structure: Dashboard | Delivery Work Order | Delivery Notes | Returns | Delivery Providers
 */

export default [
  {
    title: 'Logistics',
    icon: { icon: 'tabler-truck-delivery' },
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
    children: [
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
      {
        title: 'Delivery Service Providers',
        to: 'logistics-delivery-providers-list',
        icon: { icon: 'tabler-building-warehouse' },
        module: 'logistics',
        action: 'read',
        subject: 'Logistics',
      },
    ],
  },
]
