import { ofetch } from 'ofetch'

let redirectingAfterUnauthorized = false

function getXsrfToken() {
  if (typeof document === 'undefined')
    return null

  const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[2]) : null
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',
  async onRequest({ options }) {
    options.credentials = 'include'

    const headers = new Headers(options.headers)

    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json')
    }
    headers.set('X-Requested-With', 'XMLHttpRequest')

    const xsrfToken = getXsrfToken()
    if (xsrfToken) {
      headers.set('X-XSRF-TOKEN', xsrfToken)
    }

    const token = typeof useCookie === 'function' ? useCookie('accessToken')?.value : null
    if (token && !headers.has('Authorization')) {
      headers.set('Authorization', `Bearer ${token}`)
    }

    options.headers = headers
  },
  onResponseError({ request, response }) {
    const requestUrl = typeof request === 'string' ? request : request?.url

    // Handle 401 unauthorized or 403 deactivated/suspended user
    const isAuthOrDeactivated = response.status === 401
      || (response.status === 403 && (response._data?.code === 'ACCOUNT_DEACTIVATED' || response._data?.is_active === false))

    if (
      !isAuthOrDeactivated
      || requestUrl?.includes('/auth/login')
      || typeof window === 'undefined'
      || redirectingAfterUnauthorized
    ) return

    useCookie('userData').value = null
    useCookie('accessToken').value = null
    useCookie('userAbilityRules').value = null

    if (window.location.pathname === '/login') return

    redirectingAfterUnauthorized = true

    const returnTo = `${window.location.pathname}${window.location.search}${window.location.hash}`

    window.location.assign(`/login?to=${encodeURIComponent(returnTo)}`)
  },
})

// Attach HTTP method helpers for convenience and robust compatibility
$api.get = (url, options = {}) => $api(url, { ...options, method: 'GET' })
$api.post = (url, body, options = {}) => $api(url, { ...options, method: 'POST', body })
$api.put = (url, body, options = {}) => $api(url, { ...options, method: 'PUT', body })
$api.patch = (url, body, options = {}) => $api(url, { ...options, method: 'PATCH', body })
$api.delete = (url, options = {}) => $api(url, { ...options, method: 'DELETE' })
