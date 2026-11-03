import { canNavigate } from '@layouts/plugins/casl'

export const setupGuards = router => {
  // 👉 router.beforeEach
  // Docs: https://router.vuejs.org/guide/advanced/navigation-guards.html#global-before-guards
  router.beforeEach(to => {
    /**
     * Check if user is logged in by checking if token & user data exists in local storage / cookies
     */
    const isLoggedIn = Boolean(useCookie('userData').value?.id)

    /*
     * 👉 Guest-only routes: login, landing/home, and registration request pages.
     * If user is already logged in, redirect them to the authenticated home (/).
     * If user is not logged in, allow visiting the page without further permission checks.
     */
    const guestOnlyPaths = ['/login', '/home', '/register-request']
    const guestOnlyNames = ['login', 'home', 'register-request']
    const normalizedPath = to.path.replace(/\/$/, '')

    const isGuestOnly = Boolean(
      to.meta.unauthenticatedOnly
      || to.matched.some(r => r.meta?.unauthenticatedOnly)
      || guestOnlyNames.includes(to.name)
      || guestOnlyPaths.includes(normalizedPath),
    )

    if (isGuestOnly) {
      if (isLoggedIn)
        return '/'
      else
        return undefined
    }

    /*
     * If it's a public route, continue navigation. This kind of pages are allowed to be visited by login & non-login users. Basically, without any restrictions.
     * Examples of public routes are, 404, not-authorized, under maintenance, etc.
     */
    if (to.meta.public || to.matched.some(r => r.meta?.public))
      return

    /*
     * 👉 If the user is NOT logged in and the route is NOT public/unauthenticatedOnly,
     * redirect appropriately:
     *   - Root (/) → send to landing page (/home)
     *   - Any other route → send to login
     */
    if (!isLoggedIn) {
      if (to.path === '/') {
        return '/home'
      }

      return {
        name: 'login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }

    /*
     * 👉 If logged in, check CASL abilities for fine-grained access control.
     * Routes with action & subject meta will be checked against user's abilities.
     */
    if (!canNavigate(to) && to.matched.length) {
      return { name: 'not-authorized' }
    }
  })
}
