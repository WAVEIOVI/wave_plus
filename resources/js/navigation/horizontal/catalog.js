/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Catalog Module Navigation
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboard',
    to: 'catalog',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
  {
    title: 'Catalog Items',

    to: 'catalog-items-list',
    icon: { icon: 'tabler-package' },
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
  {
    title: 'Equipment Blueprints',

    to: 'catalog-blueprints-list',
    icon: { icon: 'tabler-file-settings' },
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
  {
    title: 'Guidelines',
    to: 'catalog-guidelines-list',
    icon: { icon: 'tabler-checklist' },
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
]
