import { reactive, ref, unref } from 'vue'
import { useAppSnackbar } from '../useAppSnackbar.js'

/** Names of the actions that carry a `pending` flag. */
export const DOCUMENT_ACTIONS = Object.freeze(['save', 'submit', 'approve', 'reject', 'convert', 'print', 'pdf', 'email', 'duplicate', 'delete'])

/** i18n keys used for default snackbar feedback (`wp.documents.actions.*`). */
const successKey = name => `wp.documents.actions.${name}_success`
const errorKey = name => `wp.documents.actions.${name}_error`

/**
 * useDocumentActions
 * Orchestrates document-level actions with per-action `pending` flags,
 * duplicate-submit protection and snackbar feedback. All side effects are
 * injected through `handlers`; a missing handler makes the action a no-op
 * returning `{ ok: false, skipped: true }`.
 *
 * @param {import('vue').Ref<object|null>} documentRef
 * @param {{
 *   handlers?: Partial<Record<'save'|'submit'|'approve'|'reject'|'convert'|'print'|'pdf'|'email'|'duplicate'|'delete',
 *     (doc: object, payload?: any) => Promise<any>|any>>,
 *   t?: (key: string, fallback?: string) => string,
 *   snackbar?: { success: Function, showApiError: Function },
 *   silent?: string[],                // actions without automatic snackbar
 *   onSuccess?: (action: string, result: any) => void,
 *   onError?: (action: string, error: any) => void,
 *   readonly?: import('vue').Ref<boolean>|boolean, // blocks mutating actions
 * }} [options]
 */
export function useDocumentActions(documentRef, options = {}) {
  const snackbar = options.snackbar || useAppSnackbar()
  const t = options.t || ((key, fallback) => fallback || key)
  const handlers = options.handlers || {}
  const silent = options.silent || []
  const readOnlyBlocked = ['save', 'submit', 'approve', 'reject', 'convert', 'delete']

  const pending = reactive(Object.fromEntries(DOCUMENT_ACTIONS.map(name => [name, false])))

  const deleteDialog = ref(false)
  const emailDialog = ref(false)
  const convertDialog = ref(false)
  const convertTarget = ref(null)

  const isBusy = () => DOCUMENT_ACTIONS.some(name => pending[name])

  /**
   * Run a named action once at a time. While any action is pending, further
   * calls are ignored (prevents double submit).
   *
   * @param {string} name
   * @param {any} [payload]
   * @returns {Promise<{ok: boolean, result?: any, error?: any, skipped?: boolean}>}
   */
  const run = async (name, payload) => {
    const handler = handlers[name]

    if (!handler) return { ok: false, skipped: true }
    if (isBusy()) return { ok: false, skipped: true, busy: true }
    if (readOnlyBlocked.includes(name) && unref(options.readonly)) return { ok: false, skipped: true }

    pending[name] = true
    try {
      const result = await handler(unref(documentRef), payload)

      if (!silent.includes(name)) snackbar.success(t(successKey(name)))
      options.onSuccess?.(name, result)

      return { ok: true, result }
    } catch (error) {
      if (!silent.includes(name)) snackbar.showApiError(error, t(errorKey(name)))
      options.onError?.(name, error)

      return { ok: false, error }
    } finally {
      pending[name] = false
    }
  }

  /** Persist the document. @param {any} [payload] */
  const save = payload => run('save', payload)

  /** Submit for approval. @param {any} [payload] */
  const submit = payload => run('submit', payload)

  /** Approve. @param {any} [payload] */
  const approve = payload => run('approve', payload)

  /** Reject (payload usually carries the reason). @param {any} [payload] */
  const reject = payload => run('reject', payload)

  /** Print using the injected handler. */
  const print = () => run('print')

  /** Generate / download the PDF. */
  const pdf = () => run('pdf')

  /** Duplicate the document. */
  const duplicate = () => run('duplicate')

  /**
   * Open the convert dialog for a target (`rfq|po|grn`).
   *
   * @param {string} target
   */
  const openConvert = target => {
    convertTarget.value = target
    convertDialog.value = true
  }

  /**
   * Execute the conversion (e.g. open a prefilled wizard) and close the dialog on success.
   *
   * @param {string} [target]
   */
  const convert = async (target = convertTarget.value) => {
    const outcome = await run('convert', { target })

    if (outcome.ok) convertDialog.value = false

    return outcome
  }

  /** Open the delete confirmation dialog (`WpDeleteDialog` v-model). */
  const openDelete = () => { deleteDialog.value = true }

  /** Confirm deletion and close the dialog on success. */
  const confirmDelete = async () => {
    const outcome = await run('delete')

    if (outcome.ok) deleteDialog.value = false

    return outcome
  }

  /** Open the email dialog (`WpEmailDialog` v-model). */
  const openEmail = () => { emailDialog.value = true }

  /**
   * Send the email (payload from `WpEmailDialog` `@send`) and close on success.
   *
   * @param {object} payload
   */
  const sendEmail = async payload => {
    const outcome = await run('email', payload)

    if (outcome.ok) emailDialog.value = false

    return outcome
  }

  return {
    pending,
    isBusy,
    run,
    save,
    submit,
    approve,
    reject,
    print,
    pdf,
    duplicate,
    convert,
    openConvert,
    convertTarget,
    openDelete,
    confirmDelete,
    openEmail,
    sendEmail,
    deleteDialog,
    emailDialog,
    convertDialog,
  }
}
