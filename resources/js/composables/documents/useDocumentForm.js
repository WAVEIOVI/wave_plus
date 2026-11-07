import { computed, ref, unref, watch } from 'vue'
import { useDirtyState } from '../shared/useDirtyState.js'
import { findFieldError, parseServerErrors } from '../shared/useServerErrors.js'
import { useUnsavedChangesGuard } from '../dialog/useUnsavedChangesGuard.js'

const clone = value => (value === undefined || value === null ? value : JSON.parse(JSON.stringify(value)))

/**
 * useDocumentForm
 * Reactive document state with dirty tracking, server error mapping and
 * status-based read-only locking.
 *
 * @param {{
 *   initial?: object | (() => object),
 *   lockedStatuses?: string[],            // statuses in which the document is read-only
 *   statusKey?: string,                   // default `status`
 *   linesKey?: string,                    // default `lines` (used by lineErrors)
 *   readonly?: import('vue').Ref<boolean>|boolean, // extra forced read-only
 *   ignoreDirty?: string[],               // top-level keys excluded from dirty tracking
 *   unsavedGuard?: boolean,               // wire useUnsavedChangesGuard (needs component+router context)
 * }} [options]
 */
export function useDocumentForm(options = {}) {
  const statusKey = options.statusKey || 'status'
  const linesKey = options.linesKey || 'lines'
  const lockedStatuses = options.lockedStatuses || []
  const buildInitial = () => clone(typeof options.initial === 'function' ? options.initial() : (options.initial || {}))

  const document = ref(buildInitial())
  const loading = ref(false)
  const saving = ref(false)
  const errors = ref({})
  const errorMessage = ref('')
  const clientLineErrors = ref({})

  const { isDirty, commit: commitSnapshot, discard } = useDirtyState(document, { ignore: options.ignoreDirty || [] })

  const guard = options.unsavedGuard ? useUnsavedChangesGuard(isDirty) : null

  const isReadonly = computed(() => Boolean(unref(options.readonly)) || lockedStatuses.includes(document.value?.[statusKey]))

  /**
   * Load a server document and make it the clean baseline.
   *
   * @param {object} data
   */
  const setDocument = data => {
    document.value = clone(data) || {}
    clearErrors()
    commitSnapshot()
  }

  /**
   * Accept the current state as saved (clears dirty flag and errors).
   */
  const commit = () => {
    clearErrors()
    commitSnapshot()
  }

  /**
   * Restore the last committed snapshot.
   */
  const reset = () => {
    discard()
    clearErrors()
  }

  /** Remove all server and client-side errors. */
  function clearErrors() {
    errors.value = {}
    errorMessage.value = ''
    clientLineErrors.value = {}
  }

  /**
   * Error message for a field path (supports nested server keys).
   *
   * @param {string} path e.g. `supplier_id` or `lines`
   * @returns {string|undefined}
   */
  const fieldErrors = path => findFieldError(errors.value, path)

  /**
   * Error message for a line field. Server keys look like `lines.2.qty_ordered`;
   * client validation errors (from `useDocumentItems`) take precedence.
   *
   * @param {number} index
   * @param {string} field
   * @returns {string|undefined}
   */
  const lineErrors = (index, field) => clientLineErrors.value?.[index]?.[field] || errors.value[`${linesKey}.${index}.${field}`]

  /**
   * Store client-side line validation errors (output of `validateLines()`).
   *
   * @param {Record<number, Record<string,string>>} map
   */
  const setLineErrors = map => {
    clientLineErrors.value = map || {}
  }

  /**
   * Map an API error into the form error state.
   *
   * @param {any} err
   * @returns {{message: string, fieldErrors: Record<string,string>, status: number|null, isValidation: boolean}}
   */
  const applyServerErrors = err => {
    const parsed = parseServerErrors(err)

    errors.value = parsed.fieldErrors
    errorMessage.value = parsed.message

    return parsed
  }

  // A fresh edit on a field clears its stale server error.
  watch(document, () => {
    if (Object.keys(errors.value).length) errors.value = {}
  }, { deep: true, flush: 'sync' })

  return {
    document,
    loading,
    saving,
    errors,
    errorMessage,
    isDirty,
    isReadonly,
    setDocument,
    commit,
    reset,
    clearErrors,
    fieldErrors,
    lineErrors,
    setLineErrors,
    applyServerErrors,
    unsavedGuard: guard,
  }
}
