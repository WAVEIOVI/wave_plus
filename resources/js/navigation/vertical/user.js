/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Sidebar Navigation Items
 * ──────────────────────────────────────────────────────────────
 *  Adding `action` + `subject` to a nav item tells Vuexy's
 *  built-in ACL layer to hide it when the user's CASL abilities
 *  don't match.
 *
 *  📖 Vuexy Docs → "Show/Hide navigation items":
 *     The template will automatically check $can(action, subject)
 *     on every nav item. If the ability is not met, the item
 *     is hidden from the sidebar.
 *
 *  📖 Vuexy Docs → "Omitting defining resource and action for
 *     nav groups": Navigation **groups** auto-hide when ALL
 *     their children are hidden. You can optionally also add
 *     action/subject on the group itself for an extra guard.
 *
 *  📖 For full details, see:
 *     • Vuexy Guide → Access Control → "Show/Hide navigation items"
 *     • Vuexy Guide → Access Control → "Using ACL"
 * ──────────────────────────────────────────────────────────────
 */

export default [
  { heading: 'User Management' },
  {
    title: 'User',
    icon: { icon: 'tabler-users' },
    to: 'user-list',
    module: 'user',
    action: 'read',
    subject: 'users',
  },
  {
    title: 'Registration Requests',
    icon: { icon: 'tabler-user-plus' },
    to: 'user-registration-requests',
    module: 'user',
    action: 'read',
    subject: 'users',
  },
  {
    title: 'Roles & Permissions',
    icon: { icon: 'tabler-lock' },
    module: 'user',
    action: 'read',
    subject: 'roles',
    children: [
      { title: 'Roles', to: 'user-roles' },
      { title: 'Permissions', to: 'user-permissions' },
    ],
  },
]
