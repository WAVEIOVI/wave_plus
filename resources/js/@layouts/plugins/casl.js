import { ability } from '@/plugins/casl/ability'
import { getCurrentInstance } from 'vue'

/**
 * Returns permission ability result or falls back to true.
 *
 * @param {string} action Permission Action (e.g. 'read', 'create', 'update', 'delete', 'manage')
 * @param {string} subject Permission Subject (e.g. 'PeopleSkills', 'Fleet', 'Catalog', 'all')
 */
export const can = (action, subject) => {
  const vm = getCurrentInstance()
  if (vm?.proxy && '$can' in vm.proxy) {
    return vm.proxy.$can(action, subject)
  }
  return ability.can(action, subject)
}

/**
 * Check if user can view navigation item/group based on permissions.
 * Hides group if no child items are visible.
 *
 * @param {object} item Navigation item or group
 */
export const canViewNavMenuGroup = item => {
  const hasAnyVisibleChild = item.children ? item.children.some(i => can(i.action, i.subject)) : false

  if (!(item.action && item.subject)) {
    return hasAnyVisibleChild
  }

  return can(item.action, item.subject) && hasAnyVisibleChild
}

/**
 * Route navigation guard checking permissions for target and parent routes.
 *
 * @param {RouteLocationNormalized} to Target route
 */
export const canNavigate = to => {
  // Get the most specific route
  const targetRoute = to.matched[to.matched.length - 1]

  if (targetRoute?.meta?.action && targetRoute?.meta?.subject) {
    return ability.can(targetRoute.meta.action, targetRoute.meta.subject)
  }

  // If no action/subject meta is defined on any matched route, route is public to authenticated users
  const hasAclMeta = to.matched.some(route => route.meta?.action && route.meta?.subject)
  if (!hasAclMeta) {
    return true
  }

  // Check if any parent route allows access
  return to.matched.some(route => ability.can(route.meta.action, route.meta.subject))
}
