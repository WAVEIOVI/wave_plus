/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Client Assets Module Navigation
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboard',
    to: 'client-assets',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
  {
    title: 'Sites',
    to: 'client-assets-sites-list',
    icon: { icon: 'tabler-building-estate' },
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
  {
    title: 'Equipment',
    to: 'client-assets-equipment-list',
    icon: { icon: 'tabler-fire-extinguisher' },
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
  {
    title: 'Workwear & PPE',
    to: 'client-assets-workwear-list',
    icon: { icon: 'tabler-hanger' },
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
]
