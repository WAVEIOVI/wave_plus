/**
 * useAppSnackbar.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Global snackbar and alert composable for WAVE PLUS.
 *
 * Features:
 *   - Queue-based notification handling: multiple alerts display sequentially
 *     without overlapping or dropping messages.
 *   - Duplicate suppression: duplicate messages within debounce window are ignored.
 *   - High-priority error preemption: errors jump ahead of info/success.
 *   - Action buttons support: optional interactive actions.
 *   - Responsive mobile & desktop positioning.
 *   - Light and dark theme integration.
 *   - API error parsing helper: extracts clean messages from Laravel errors.
 *
 * Usage:
 *   import { useAppSnackbar } from '@/composables/useAppSnackbar'
 *   const { success, error, warning, info, showApiError, showMessage } = useAppSnackbar()
 *
 *   success(t('clients.list.messages.create_success'))
 *   showApiError(err, t('clients.list.messages.create_error'))
 */

import { ref } from 'vue'

export const DEFAULT_TIMEOUTS = {
  success: 3500,
  info: 3500,
  warning: 5000,
  error: 6000,
}

export const DEFAULT_ICONS = {
  success: 'tabler-circle-check',
  error: 'tabler-alert-circle',
  warning: 'tabler-alert-triangle',
  info: 'tabler-info-circle',
}

const snackbar = ref({
  show: false,
  text: '',
  title: '',
  color: 'success',
  icon: 'tabler-circle-check',
  timeout: 3500,
  action: null,
  id: 0,
})

const queue = []
let currentId = 0
let lastShown = { text: '', color: '', timestamp: 0 }
let transitionTimer = null
let advancing = false

/**
 * Display a notification immediately.
 */
function displayNotification(item) {
  snackbar.value = {
    show: true,
    text: item.text,
    title: item.title || '',
    color: item.color || 'success',
    icon: item.icon || DEFAULT_ICONS[item.color] || 'tabler-bell',
    timeout: item.timeout ?? (DEFAULT_TIMEOUTS[item.color] || 3500),
    action: item.action || null,
    id: item.id,
  }

  lastShown = {
    text: item.text,
    color: item.color || 'success',
    timestamp: Date.now(),
  }
}

/**
 * Dismiss the currently displayed notification and transition to next in queue.
 */
function dismiss() {
  if (!snackbar.value.show && !advancing && queue.length === 0)
    return

  snackbar.value.show = false

  if (advancing)
    return

  advancing = true
  if (transitionTimer)
    clearTimeout(transitionTimer)

  transitionTimer = setTimeout(() => {
    advancing = false
    if (queue.length > 0) {
      const next = queue.shift()

      displayNotification(next)
    }
    else {
      snackbar.value.text = ''
      snackbar.value.title = ''
      snackbar.value.action = null
    }
  }, 220)
}

/**
 * Handle notification action button click.
 */
function handleAction() {
  if (snackbar.value.action?.onClick && typeof snackbar.value.action.onClick === 'function') {
    try {
      snackbar.value.action.onClick()
    }
    catch (err) {
      console.error('Error executing snackbar action:', err)
    }
  }
  dismiss()
}

/**
 * Show a snackbar notification with queueing and duplicate prevention.
 *
 * @param {string|{ text: string, title?: string, color?: string, timeout?: number, icon?: string, action?: object }} opts
 * @param {string} [colorFallback='success']
 */
function showSnackbar(opts, colorFallback = 'success') {
  const normalized = typeof opts === 'string'
    ? { text: opts, color: colorFallback }
    : { ...opts }

  const text = (normalized.text || '').trim()
  if (!text)
    return

  const color = normalized.color || colorFallback || 'success'
  const title = normalized.title || ''
  const timeout = normalized.timeout ?? (DEFAULT_TIMEOUTS[color] || 3500)
  const icon = normalized.icon || DEFAULT_ICONS[color] || 'tabler-bell'
  const action = normalized.action || null

  // ── Duplicate prevention: ignore identical message within 1.8s ──
  const now = Date.now()
  if (
    lastShown.text === text
    && lastShown.color === color
    && now - lastShown.timestamp < 1800
  ) {
    // If currently visible, extend duration
    if (snackbar.value.show)
      snackbar.value.timeout = timeout

    return
  }

  // Prevent queuing identical message multiple times
  if (queue.some(item => item.text === text && item.color === color))
    return

  const item = {
    id: ++currentId,
    text,
    title,
    color,
    icon,
    timeout,
    action,
  }

  // If no notification is active, display immediately
  if (!snackbar.value.show && !advancing) {
    displayNotification(item)

    return
  }

  // If an urgent error arrives while showing info/success, preempt it
  if (color === 'error' && snackbar.value.color !== 'error') {
    // Place currently showing item back in front of queue
    queue.unshift({
      id: snackbar.value.id,
      text: snackbar.value.text,
      title: snackbar.value.title,
      color: snackbar.value.color,
      icon: snackbar.value.icon,
      timeout: snackbar.value.timeout,
      action: snackbar.value.action,
    })
    displayNotification(item)

    return
  }

  // Otherwise, push to queue
  queue.push(item)
}

/**
 * Shorthand helpers
 */
const success = (text, opts = {}) => showSnackbar({ text, ...opts, color: 'success' })
const error = (text, opts = {}) => showSnackbar({ text, ...opts, color: 'error' })
const warning = (text, opts = {}) => showSnackbar({ text, ...opts, color: 'warning' })
const info = (text, opts = {}) => showSnackbar({ text, ...opts, color: 'info' })
const showMessage = (text, color = 'success', opts = {}) => showSnackbar({ text, color, ...opts })

/**
 * Resolve and display a clean translated error from an API error response.
 *
 * @param {any} err - The caught error
 * @param {string} defaultFallback - Fallback translated message
 * @param {object} [opts={}] - Additional options for showSnackbar
 */
function showApiError(err, defaultFallback, opts = {}) {
  let message = null

  if (err?.data?.message && typeof err.data.message === 'string') {
    message = err.data.message
  }
  else if (err?.data?.errors && typeof err.data.errors === 'object') {
    const firstGroup = Object.values(err.data.errors)[0]
    if (Array.isArray(firstGroup) && firstGroup.length > 0)
      message = firstGroup[0]
  }
  else if (err?.response?._data?.message && typeof err.response._data.message === 'string') {
    message = err.response._data.message
  }
  else if (err?.response?._data?.errors && typeof err.response._data.errors === 'object') {
    const firstGroup = Object.values(err.response._data.errors)[0]
    if (Array.isArray(firstGroup) && firstGroup.length > 0)
      message = firstGroup[0]
  }
  else if (err?.message && typeof err.message === 'string' && !err.message.includes('FetchError:')) {
    message = err.message
  }

  error(message || defaultFallback || 'An unexpected error occurred', opts)
}

/**
 * Clear queue and hide active notification immediately.
 */
function clearAll() {
  queue.length = 0
  if (transitionTimer)
    clearTimeout(transitionTimer)
  advancing = false
  snackbar.value.show = false
  snackbar.value.text = ''
  snackbar.value.title = ''
  snackbar.value.action = null
}

export function useAppSnackbar() {
  return {
    snackbar,
    queue,
    showSnackbar,
    showMessage,
    success,
    error,
    warning,
    info,
    showApiError,
    dismiss,
    handleAction,
    clearAll,
  }
}
