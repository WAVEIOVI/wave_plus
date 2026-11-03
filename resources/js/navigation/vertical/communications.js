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
    title: 'Email Center',
    
    to: 'communications-email', 
    icon: { icon: 'tabler-mail' },
    module: 'communications',
    action: 'read',
    subject: 'Communications',
  },
]
