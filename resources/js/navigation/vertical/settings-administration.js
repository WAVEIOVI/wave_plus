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
  { heading: 'Settings Administration' },
  {
    title: 'Dashboard',
    
    to: 'settings-administration',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
  {
    title: 'Catalog',

    to: 'settings-administration-catalog',
    icon: { icon: 'tabler-adjustments' },
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
  {
    title: 'Client Assets',
    to: 'settings-administration-client-assets',
    icon: { icon: 'tabler-shirt' },
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
  {
    title: 'Shared Components',
    to: 'settings-administration-shared-components',
    icon: { icon: 'tabler-components' },
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
]
