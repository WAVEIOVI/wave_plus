/**
 * ──────────────────────────────────────────────────────────────
 *  Communications Navigation — Horizontal Menu
 * ──────────────────────────────────────────────────────────────
 *  `action` + `subject` on each item triggers Vuexy's built-in
 *  sidebar ACL. Items auto-hide if the user lacks the ability.
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
