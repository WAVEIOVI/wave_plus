/**
 * useServerErrors
 * ─────────────────────────────────────────────────────────────────────────────
 * Normalises the error shapes produced by the shared `$api` (ofetch) instance,
 * Axios-style errors and plain `Error`s into `{ message, fieldErrors, status }`.
 *
 * Laravel validation responses look like:
 *   { message: 'The given data was invalid.', errors: { email: ['Taken'], 'contacts.0.name': ['Required'] } }
 *
 * `fieldErrors` keeps only the first message of every field.
 *
 * @param {any} err
 * @returns {{ message: string, fieldErrors: Record<string,string>, status: number|null, isValidation: boolean }}
 */
export function parseServerErrors(err) {
  const payload = err?.data ?? err?.response?.data ?? null
  const rawErrors = payload?.errors && typeof payload.errors === 'object' ? payload.errors : {}
  const fieldErrors = {}

  Object.entries(rawErrors).forEach(([key, value]) => {
    const first = Array.isArray(value) ? value[0] : value
    if (first) fieldErrors[key] = String(first)
  })

  const status = err?.statusCode ?? err?.status ?? err?.response?.status ?? null

  return {
    message: payload?.message || err?.message || '',
    fieldErrors,
    status,
    isValidation: status === 422 || Object.keys(fieldErrors).length > 0,
  }
}

/**
 * Resolve the error message of a field, tolerating dotted/nested server keys
 * (`contacts.0.email` also matches the field `contacts`).
 *
 * @param {Record<string,string>} errors
 * @param {string} key
 * @returns {string|undefined}
 */
export function findFieldError(errors, key) {
  if (!errors || !key) return undefined
  if (errors[key]) return errors[key]

  const nested = Object.keys(errors).find(k => k.startsWith(`${key}.`))

  return nested ? errors[nested] : undefined
}
