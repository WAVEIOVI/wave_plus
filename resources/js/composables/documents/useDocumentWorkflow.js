import { computed, getCurrentInstance, reactive, unref } from 'vue'
import {
  computeLifecycleSteps,
  getNextStatuses,
  isEditableStatus,
  resolveWorkflowConfig,
} from './workflowConfigs.js'

/** Default CASL action per workflow capability (override with `options.permissionMap`). */
export const DEFAULT_PERMISSION_MAP = Object.freeze({
  edit: 'update',
  submit: 'update',
  approve: 'approve',
  reject: 'approve',
  cancel: 'update',
  convert: 'create',
  delete: 'delete',
})

/**
 * Resolve the permission checker: explicit `can` option, else the global
 * `$can` of the app, else allow-all (permission checking is optional).
 *
 * @param {Function|undefined} can
 * @returns {(action: string, subject: string) => boolean}
 */
function resolveCan(can) {
  if (typeof can === 'function') return can

  const globalCan = getCurrentInstance()?.appContext?.config?.globalProperties?.$can

  return typeof globalCan === 'function' ? globalCan : () => true
}

/**
 * useDocumentWorkflow
 * Status-machine driven capabilities and transition confirmation flow.
 *
 * @param {import('vue').Ref<object|null>} documentRef
 * @param {{
 *   config: string|object,                 // `ipr|rfq|po|grn` or a config object
 *   transitionFn?: (id: any, payload: object) => Promise<any>,
 *   can?: (action: string, subject: string) => boolean,
 *   subject?: string,                       // CASL subject, default `SupplyChainProcurement`
 *   permissionMap?: object,
 *   statusKey?: string,
 *   history?: import('vue').Ref<string[]>|string[],
 *   onTransitioned?: (result: any, payload: object) => void,
 * }} options
 */
export function useDocumentWorkflow(documentRef, options = {}) {
  const config = resolveWorkflowConfig(options.config)
  const statusKey = options.statusKey || 'status'
  const subject = options.subject || 'SupplyChainProcurement'
  const permissionMap = { ...DEFAULT_PERMISSION_MAP, ...(options.permissionMap || {}) }
  const can = resolveCan(options.can)
  const allowed = capability => can(permissionMap[capability], subject)

  const status = computed(() => unref(documentRef)?.[statusKey] || null)

  const confirmState = reactive({
    open: false,
    target: null,
    action: null,
    requiresReason: false,
    reason: '',
    comment: '',
    pending: false,
    error: null,
  })

  const metaFor = target => config.transitionMeta?.[target] || { action: 'advance', permission: 'update' }

  /** Transitions possible from the current status that the user is allowed to perform. */
  const availableTransitions = computed(() => getNextStatuses(config, status.value)
    .map(target => {
      const meta = metaFor(target)

      return {
        to: target,
        action: meta.action,
        requiresReason: Boolean(meta.requiresReason),
        permission: meta.permission,
        allowed: can(permissionMap[meta.action] || meta.permission, subject),
      }
    })
    .filter(t => t.allowed))

  const hasAction = name => availableTransitions.value.some(t => t.action === name)

  const lifecycleSteps = computed(() => computeLifecycleSteps(config, status.value, { history: unref(options.history) }))

  const canEdit = computed(() => isEditableStatus(config, status.value) && allowed('edit'))
  const canSubmit = computed(() => hasAction('submit'))
  const canApprove = computed(() => hasAction('approve'))
  const canReject = computed(() => hasAction('reject'))
  const canCancel = computed(() => hasAction('cancel') && allowed('cancel'))
  const canDelete = computed(() => (config.deletableStatuses || []).includes(status.value) && allowed('delete'))

  /** Conversion targets currently allowed, e.g. `[{ target: 'po' }]`. */
  const convertTargets = computed(() => {
    const doc = unref(documentRef)

    if (!doc || !allowed('convert')) return []

    return (config.convert || [])
      .filter(c => c.fromStatuses.includes(status.value) && !(c.blockedBy && doc[c.blockedBy]))
      .map(c => ({ target: c.target }))
  })

  const canConvert = computed(() => convertTargets.value.length > 0)

  /**
   * Open the confirmation state for a transition.
   *
   * @param {string} to Target status.
   * @returns {boolean} False when the transition is not available.
   */
  const requestTransition = to => {
    const transition = availableTransitions.value.find(t => t.to === to)

    if (!transition) return false
    Object.assign(confirmState, {
      open: true,
      target: to,
      action: transition.action,
      requiresReason: transition.requiresReason,
      reason: '',
      comment: '',
      pending: false,
      error: null,
    })

    return true
  }

  /** Close the confirmation state without calling the API. */
  const cancelTransition = () => {
    Object.assign(confirmState, { open: false, target: null, action: null, requiresReason: false, reason: '', comment: '', error: null })
  }

  /**
   * Execute the pending transition through the injected `transitionFn`.
   * Ignored while another confirmation is in flight (duplicate-submit guard).
   *
   * @returns {Promise<{ok: boolean, result?: any, error?: any}>}
   */
  const confirmTransition = async () => {
    if (confirmState.pending || !confirmState.target) return { ok: false }
    if (typeof options.transitionFn !== 'function') return { ok: false, error: new Error('transitionFn not provided') }

    const meta = metaFor(confirmState.target)
    const reason = confirmState.reason.trim()

    if (confirmState.requiresReason && !reason) {
      confirmState.error = 'reason_required'

      return { ok: false, error: 'reason_required' }
    }

    const payload = { status: confirmState.target }

    if (reason) payload[meta.reasonField || 'reason'] = reason
    if (confirmState.comment.trim()) payload[meta.commentField || 'comment'] = confirmState.comment.trim()

    confirmState.pending = true
    confirmState.error = null
    try {
      const result = await options.transitionFn(unref(documentRef)?.id, payload)

      options.onTransitioned?.(result, payload)
      cancelTransition()

      return { ok: true, result }
    } catch (error) {
      confirmState.error = error

      return { ok: false, error }
    } finally {
      confirmState.pending = false
    }
  }

  return {
    config,
    status,
    availableTransitions,
    lifecycleSteps,
    canEdit,
    canSubmit,
    canApprove,
    canReject,
    canCancel,
    canDelete,
    canConvert,
    convertTargets,
    confirmState,
    requestTransition,
    cancelTransition,
    confirmTransition,

    // exposed for views that need a manual open flag
    isConfirmOpen: computed({ get: () => confirmState.open, set: v => { if (!v) cancelTransition() } }),
  }
}
