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
    title: 'Dashboard',
    
    to: 'business-partners',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'business-partners',
    action: 'read',
    subject: 'bp-dashboard',
  },
  {
    title: 'Clients',
    
    to: 'business-partners-clients-list', 
    icon: { icon: 'tabler-building-skyscraper' },
    module: 'business-partners',
    action: 'read',
    subject: 'clients',
  },
  {
    title: 'Suppliers',

    to: 'business-partners-suppliers-list',
    icon: { icon: 'tabler-forklift' },
    module: 'business-partners',
    action: 'read',
    subject: 'suppliers',
  },
  {
    title: 'Service Providers',

    to: 'business-partners-service-providers-list',
    icon: { icon: 'tabler-briefcase' },
    module: 'business-partners',
    action: 'read',
    subject: 'service-providers',
  },
  {
    title: 'Organisations',

    to: 'business-partners-organisations-list',
    icon: { icon: 'tabler-building' },
    module: 'business-partners',
    action: 'read',
    subject: 'organisations',
  },
]
