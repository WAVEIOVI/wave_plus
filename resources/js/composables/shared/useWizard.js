import { computed, getCurrentScope, onScopeDispose, reactive, ref, watch } from 'vue'
import { isDeepEqual } from './useDirtyState.js'
import { findFieldError, parseServerErrors } from './useServerErrors.js'

/**
 * useWizard
 * ─────────────────────────────────────────────────────────────────────────────
 * The single wizard engine behind Fast / Standard / Developed / Deeper wizards.
 * It is UI-agnostic: `WpWizardDialog` renders it, but any page can drive it.
 *
 * Step definition
 *   {
 *     id: 'identity',               // unique
 *     title, subtitle, description, icon,   // already-translated strings
 *     optional: true,               // shows an "Optional" tag in the stepper
 *     when: form => boolean,        // conditional step (default: always)
 *     fields: [Field],              // flat fields …
 *     sections: [{ id, title, description, icon, collapsible, collapsed, when, fields: [Field] }],
 *     validate: async (form) => true | string | { fieldKey: 'message' },
 *   }
 *
 * Field definition
 *   { key, type, label, required, rules: [(v, form) => true|string], when,
 *     dependsOn: 'country' | ['a','b'], resetOnChange: true, default,
 *     cols: 6,                      // md columns (default 12)
 *     advanced: true,               // hidden behind "Show advanced options"
 *     review: false,                // leave out of the review step
 *     draft: false,                 // never write this value to the local draft
 *     format: (v, form) => string,  // review formatting (options / remote titles resolve automatically)
 *   }
 *
 * Options (besides steps / initialValues / submit / review / reviewTitle / messages)
 *   draft: { key, storage = localStorage, debounce = 600, maxAge, exclude: [] }
 *     Best-effort local autosave of user edits. Password fields are never stored.
 *     Call `restoreDraft()` after `reset()`; the draft is cleared on a successful submit.
 *
 * Validation is pure data/functions, so server rules stay in the backend and
 * `submit` errors (Laravel 422 payloads) are mapped back to fields and steps.
 * "Reward early, punish late": once a step has been validated, its fields are
 * re-checked live on every change, so errors disappear as soon as they're fixed.
 *
 * Returned (in addition to the original API):
 *   direction         'forward' | 'back' — direction of the last step change
 *   attemptedIds      steps that have been validated at least once
 *   firstInvalidIndex index of the first step with errors (-1 if none)
 *   requiredProgress  (step) => { done, total } required fields filled
 *   validateField     (key) => message — validate one field now (e.g. on blur)
 *   draftRestored / draftSavedAt / restoreDraft() / clearDraft()
 */

export const WIZARD_REVIEW_ID = '__review'

const DEFAULT_MESSAGES = {
  required: 'This field is required',
  invalid: 'This value is invalid',
  review: 'Review',
}

const DRAFT_VERSION = 1

const read = v => (typeof v === 'function' ? v() : v && typeof v === 'object' && 'value' in v ? v.value : v)
const clone = v => (v === undefined ? undefined : JSON.parse(JSON.stringify(v)))

/** localStorage may be missing (SSR) or throw (privacy mode): drafts are best-effort. */
const safeStorage = storage => {
  try {
    const s = storage ?? (typeof window !== 'undefined' ? window.localStorage : null)

    return s && typeof s.getItem === 'function' ? s : null
  }
  catch {
    return null
  }
}

export const isEmptyValue = v => v === null
  || v === undefined
  || (typeof v === 'string' && v.trim() === '')
  || (Array.isArray(v) && v.length === 0)

/** Visibility of a step / section / field (`when` may be a function or boolean). */
export function isVisible(item, form) {
  if (!item || item.when === undefined) return true

  return typeof item.when === 'function' ? Boolean(item.when(form)) : Boolean(item.when)
}

/** Flatten the visible fields of a step (including nested sections). */
export function collectFields(step, form, { includeHidden = false } = {}) {
  const out = []

  const push = (field, section) => {
    if (includeHidden || isVisible(field, form)) out.push({ field, section })
  }

  ;(step?.fields || []).forEach(f => push(f, null))
  ;(step?.sections || []).forEach(section => {
    if (!includeHidden && !isVisible(section, form)) return
    ;(section.fields || []).forEach(f => push(f, section))
  })

  return out
}

/** Required-field completion of a step (visible fields only) → `{ done, total }`. */
export function requiredProgressOf(step, form) {
  if (!step || step.isReview) return { done: 0, total: 0 }

  const required = collectFields(step, form).filter(({ field }) => field.required)

  return {
    total: required.length,
    done: required.filter(({ field }) => !isEmptyValue(form[field.key])).length,
  }
}

/** Run a single field's rules. Returns an error message or ''. */
export function runFieldRules(field, form, messages = DEFAULT_MESSAGES) {
  const value = form[field.key]
  const empty = isEmptyValue(value)

  if (field.required && empty) return messages.required
  if (empty) return '' // optional + empty: nothing else to check

  for (const rule of field.rules || []) {
    if (rule === 'required') continue
    if (typeof rule !== 'function') continue

    const result = rule(value, form)

    if (result === true || result === undefined || result === null) continue
    if (typeof result === 'string' && result) return result
    if (result === false) return messages.invalid
  }

  return ''
}

/** Synchronous per-step field validation → `{ [fieldKey]: message }`. */
export function collectStepErrors(step, form, messages = DEFAULT_MESSAGES) {
  const errors = {}

  collectFields(step, form).forEach(({ field }) => {
    const message = runFieldRules(field, form, messages)

    if (message) errors[field.key] = message
  })

  return errors
}

export function useWizard(options = {}) {
  // Read lazily so a locale switch is picked up (`messages` may be an object, ref or getter).
  const getMessages = () => ({ ...DEFAULT_MESSAGES, ...(read(options.messages) || {}) })
  const initial = clone(options.initialValues || {})

  const form = reactive(clone(initial))
  const baseline = ref(clone(initial))
  const errors = ref({})
  const generalError = ref('')
  const index = ref(0)
  const direction = ref('forward')
  const visitedIds = ref([])
  const completedIds = ref([])
  const attemptedIds = ref([])
  const submitting = ref(false)
  const validating = ref(false)
  const draftRestored = ref(false)
  const draftSavedAt = ref(null)
  let busy = false

  const allSteps = computed(() => (read(options.steps) || []).map((step, idx) => ({
    ...step,
    id: step.id ?? step.key ?? `step-${idx}`,
  })))

  /** Every field of every step (hidden included) with its owning step. Doesn't track `form`. */
  const allFields = computed(() => allSteps.value.flatMap(step =>
    collectFields(step, form, { includeHidden: true }).map(({ field, section }) => ({ field, section, step }))))

  const fieldEntry = key => allFields.value.find(({ field }) => field.key === key) || null

  const activeSteps = computed(() => {
    const list = allSteps.value.filter(step => isVisible(step, form))

    if (options.review) {
      list.push({
        id: WIZARD_REVIEW_ID,
        isReview: true,
        title: options.reviewTitle || getMessages().review,
        icon: 'tabler-checklist',
      })
    }

    return list
  })

  const total = computed(() => activeSteps.value.length)
  const current = computed(() => activeSteps.value[index.value] ?? null)
  const isFirst = computed(() => index.value === 0)
  const isLast = computed(() => index.value >= total.value - 1)
  const progress = computed(() => (total.value ? Math.round(((index.value + 1) / total.value) * 100) : 0))
  const isDirty = computed(() => !isDeepEqual(form, baseline.value))

  const addId = (list, id) => {
    if (id && !list.value.includes(id)) list.value.push(id)
  }

  const markVisited = id => addId(visitedIds, id)
  const markCompleted = id => addId(completedIds, id)
  const markAttempted = id => addId(attemptedIds, id)

  watch(total, len => {
    if (index.value > len - 1) index.value = Math.max(0, len - 1)
  })

  watch(current, step => markVisited(step?.id), { immediate: true })

  // Sync flush: renderers read `direction` in the same tick the index changes.
  watch(index, (to, from) => {
    direction.value = to >= from ? 'forward' : 'back'
  }, { flush: 'sync' })

  // ── Errors ──────────────────────────────────────────────────────────────
  const stepMessageKey = step => `__step:${step.id}`

  /** Immutable single-key update (no-op when nothing changes). */
  const setError = (key, message) => {
    if ((errors.value[key] || '') === (message || '')) return

    const next = { ...errors.value }

    if (message) next[key] = message
    else delete next[key]
    errors.value = next
  }

  /** Validate one visible field now and store the result. */
  function validateField(key) {
    const entry = fieldEntry(key)
    const message = entry && isVisible(entry.field, form) ? runFieldRules(entry.field, form, getMessages()) : ''

    setError(key, message)

    return message
  }

  // ── Values ──────────────────────────────────────────────────────────────
  /**
   * Reset every field that depends on `changedKey`. Cascades: if a reset field
   * actually changed, the fields depending on *it* are reset too (country → region → city).
   */
  function resetDependents(changedKey, seen = new Set()) {
    if (seen.has(changedKey)) return // guards circular `dependsOn`
    seen.add(changedKey)

    allFields.value.forEach(({ field }) => {
      const deps = [].concat(field.dependsOn || [])

      if (!deps.includes(changedKey) || field.resetOnChange === false) return

      const next = field.default !== undefined ? clone(field.default) : null
      const changed = !isDeepEqual(form[field.key], next)

      form[field.key] = next
      setError(field.key, '')
      if (changed) resetDependents(field.key, seen)
    })
  }

  /**
   * Set a value and reset dependent fields. Fields of a step that was already
   * validated are re-checked live; otherwise the error is simply cleared.
   */
  function setValue(key, value) {
    if (isDeepEqual(form[key], value)) return

    draftArmed = true
    form[key] = value
    generalError.value = ''

    const entry = fieldEntry(key)

    if (entry) setError(stepMessageKey(entry.step), '') // the step-level message is now stale
    setError(key, entry && attemptedIds.value.includes(entry.step.id) && isVisible(entry.field, form)
      ? runFieldRules(entry.field, form, getMessages())
      : '')
    resetDependents(key)
  }

  // ── Validation ──────────────────────────────────────────────────────────
  async function validateStep(step = current.value) {
    if (!step || step.isReview) return true

    markAttempted(step.id)

    const messages = getMessages()
    const keys = collectFields(step, form, { includeHidden: true }).map(({ field }) => field.key)
    const fresh = collectStepErrors(step, form, messages)

    // Only run the (possibly async) step validator if basic field rules pass.
    if (typeof step.validate === 'function' && !Object.keys(fresh).length) {
      validating.value = true
      try {
        const result = await step.validate(form)

        if (typeof result === 'string' && result) fresh[stepMessageKey(step)] = result
        else if (result === false) fresh[stepMessageKey(step)] = messages.invalid
        else if (result && typeof result === 'object') {
          Object.entries(result).forEach(([k, v]) => {
            if (v && v !== true) fresh[k] = String(v)
          })
        }
      }
      catch (err) {
        fresh[stepMessageKey(step)] = parseServerErrors(err).message || messages.invalid
      }
      finally {
        validating.value = false
      }
    }

    const kept = { ...errors.value }

    keys.forEach(k => delete kept[k])
    delete kept[stepMessageKey(step)]
    errors.value = { ...kept, ...fresh }

    return Object.keys(fresh).length === 0
  }

  const stepMessage = step => (step ? errors.value[stepMessageKey(step)] || '' : '')

  function stepErrorCount(step) {
    if (!step || step.isReview) return 0

    const own = collectFields(step, form).filter(({ field }) => findFieldError(errors.value, field.key)).length

    return own + (stepMessage(step) ? 1 : 0)
  }

  const requiredProgress = step => requiredProgressOf(step, form)

  const firstInvalidIndex = computed(() => activeSteps.value.findIndex(step => stepErrorCount(step) > 0))

  // ── Navigation ──────────────────────────────────────────────────────────
  // `busy` is a single-flight lock shared by next / goTo / back / submit, so a
  // double click or a Back press during an async step validator can't desync the index.
  async function next() {
    if (busy || submitting.value) return false

    busy = true
    try {
      const step = current.value

      if (!(await validateStep(step))) return false

      markCompleted(step?.id)
      if (index.value < total.value - 1) index.value += 1

      return true
    }
    finally {
      busy = false
    }
  }

  function back() {
    if (busy || submitting.value || index.value === 0) return false
    index.value -= 1

    return true
  }

  /** Jump to a step; going forward validates every step in between. */
  async function goTo(target) {
    if (busy || submitting.value || target === index.value) return false
    if (target < 0 || target >= total.value) return false

    if (target < index.value) {
      index.value = target

      return true
    }

    busy = true
    try {
      for (let i = index.value; i < target; i += 1) {
        if (!(await validateStep(activeSteps.value[i]))) {
          index.value = i

          return false
        }
        markCompleted(activeSteps.value[i].id)
      }
      index.value = target

      return true
    }
    finally {
      busy = false
    }
  }

  /** Stepper policy: 'none' | 'visited' | 'completed'. */
  function canNavigateTo(target, policy = 'visited') {
    if (target === index.value) return true
    if (target < 0 || target >= total.value) return false

    if (target < index.value) return policy !== 'none'

    const id = activeSteps.value[target]?.id
    if (!id) return false

    if (policy === 'visited') return visitedIds.value.includes(id)
    if (policy === 'completed') {
      return visitedIds.value.includes(id)
        || activeSteps.value.slice(0, target).every(s => completedIds.value.includes(s.id))
    }

    return false
  }

  // ── Draft (optional local autosave) ─────────────────────────────────────
  const draftOptions = options.draft?.key ? { debounce: 600, exclude: [], ...options.draft } : null
  const draftStorage = draftOptions ? safeStorage(draftOptions.storage) : null
  let draftTimer = null

  // Armed by user edits / restore only — so `reset()` alone never overwrites a stored draft.
  let draftArmed = false

  const draftExcluded = () => new Set([
    ...draftOptions.exclude,
    ...allFields.value
      .filter(({ field }) => field.type === 'password' || field.draft === false)
      .map(({ field }) => field.key),
  ])

  function clearDraft() {
    clearTimeout(draftTimer)
    draftRestored.value = false
    draftSavedAt.value = null
    try {
      draftStorage?.removeItem(draftOptions.key)
    }
    catch {
      // storage unavailable: nothing to clear
    }
  }

  function saveDraft() {
    if (!draftStorage) return
    if (!isDirty.value) {
      clearDraft()

      return
    }
    try {
      const values = clone(form)

      draftExcluded().forEach(k => delete values[k])

      const savedAt = Date.now()

      draftStorage.setItem(draftOptions.key, JSON.stringify({ v: DRAFT_VERSION, savedAt, values }))
      draftSavedAt.value = savedAt
    }
    catch {
      // quota exceeded / storage disabled: drafts are best-effort
    }
  }

  /** Merge a stored draft into the form. → true when something was restored. */
  function restoreDraft() {
    draftArmed = true
    if (!draftStorage) return false

    try {
      const raw = draftStorage.getItem(draftOptions.key)
      const data = raw ? JSON.parse(raw) : null

      if (!data || data.v !== DRAFT_VERSION || !data.values || typeof data.values !== 'object') return false
      if (draftOptions.maxAge && Date.now() - data.savedAt > draftOptions.maxAge) {
        clearDraft()

        return false
      }

      Object.assign(form, clone(data.values))
      draftRestored.value = !isDeepEqual(form, baseline.value)
      draftSavedAt.value = draftRestored.value ? data.savedAt : null

      return draftRestored.value
    }
    catch {
      return false
    }
  }

  if (draftStorage) {
    watch(form, () => {
      if (!draftArmed) return
      clearTimeout(draftTimer)
      draftTimer = setTimeout(saveDraft, draftOptions.debounce)
    }, { deep: true })

    if (getCurrentScope()) onScopeDispose(() => clearTimeout(draftTimer))
  }

  // ── Submit ──────────────────────────────────────────────────────────────
  /** Current values without the values of fields that are no longer visible. */
  function buildPayload() {
    const payload = clone(form)
    const visibleKeys = new Set()

    activeSteps.value.forEach(step => collectFields(step, form).forEach(({ field }) => visibleKeys.add(field.key)))
    allFields.value.forEach(({ field }) => {
      if (!visibleKeys.has(field.key)) delete payload[field.key]
    })

    return payload
  }

  function applyServerErrors(parsed) {
    const matched = {}
    const unmatched = []

    Object.entries(parsed.fieldErrors).forEach(([serverKey, msg]) => {
      const entry = allFields.value.find(({ field }) => serverKey === field.key || serverKey.startsWith(`${field.key}.`))

      if (entry) {
        matched[entry.field.key] = msg
        markAttempted(entry.step.id)
      }
      else unmatched.push(msg)
    })

    errors.value = { ...errors.value, ...matched }
    generalError.value = (!parsed.isValidation || unmatched.length || !Object.keys(matched).length)
      ? [parsed.message, ...unmatched].filter(Boolean).join(' · ') || getMessages().invalid
      : parsed.message

    if (firstInvalidIndex.value >= 0) index.value = firstInvalidIndex.value
  }

  /**
   * Validate every step then call `options.submit(payload, form)`.
   * Safe against double clicks. Resolves `{ ok, result?, reason?, error? }`.
   */
  async function submit() {
    if (busy || submitting.value) return { ok: false, reason: 'busy' }

    busy = true
    submitting.value = true
    generalError.value = ''

    try {
      const steps = activeSteps.value.filter(s => !s.isReview)

      for (let i = 0; i < steps.length; i += 1) {
        if (!(await validateStep(steps[i]))) {
          index.value = activeSteps.value.findIndex(s => s.id === steps[i].id)

          return { ok: false, reason: 'invalid' }
        }
        markCompleted(steps[i].id)
      }

      if (typeof options.submit !== 'function') return { ok: true, result: buildPayload() }

      const result = await options.submit(buildPayload(), form)

      baseline.value = clone(form)
      if (draftOptions) clearDraft()

      return { ok: true, result }
    }
    catch (err) {
      applyServerErrors(parseServerErrors(err))

      return { ok: false, reason: 'server', error: err }
    }
    finally {
      busy = false
      submitting.value = false
    }
  }

  function reset(values) {
    const next = clone(values ?? baseline.value ?? {})

    clearTimeout(draftTimer)
    draftArmed = false
    draftRestored.value = false
    draftSavedAt.value = null

    Object.keys(form).forEach(k => delete form[k])
    Object.assign(form, clone(next))
    baseline.value = clone(next)
    errors.value = {}
    generalError.value = ''
    index.value = 0
    direction.value = 'forward'
    visitedIds.value = []
    completedIds.value = []
    attemptedIds.value = []
    markVisited(activeSteps.value[0]?.id)
  }

  return {
    form,
    errors,
    generalError,
    index,
    direction,
    current,
    activeSteps,
    total,
    progress,
    isFirst,
    isLast,
    isDirty,
    submitting,
    validating,
    visitedIds,
    completedIds,
    attemptedIds,
    firstInvalidIndex,
    draftRestored,
    draftSavedAt,
    setValue,
    validateStep,
    validateField,
    stepMessage,
    stepErrorCount,
    requiredProgress,
    next,
    back,
    goTo,
    canNavigateTo,
    canNavigate: canNavigateTo,
    submit,
    reset,
    buildPayload,
    restoreDraft,
    clearDraft,
  }
}
