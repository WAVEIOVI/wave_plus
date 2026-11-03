/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Dashboard Navigation
 * ──────────────────────────────────────────────────────────────
 *  `action` + `subject` on this group triggers Vuexy's built-in
 *  sidebar ACL. The group auto-hides if the user lacks the
 *  matching CASL ability.
 *
 *  📖 Vuexy Docs → Access Control → "Show/Hide navigation items"
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboards',
    icon: { icon: 'tabler-smart-home' },
    module: 'dashboard',
    action: 'read',
    subject: 'dashboard',
    children: [
      {
        title: 'Analytics',
        to: '',
      },
      {
        title: 'CRM',
        to: '',
      },
      {
        title: 'Ecommerce',
        to: '',
      },
      {
        title: 'Academy',
        to: '',
      },
      {
        title: 'Logistics',
        to: '',
      },
    ],
    badgeContent: '5',
    badgeClass: 'bg-error',
  },
]
