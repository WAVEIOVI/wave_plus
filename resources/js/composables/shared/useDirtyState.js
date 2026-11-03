import { computed, ref, unref } from 'vue'

/**
 * Order-insensitive deep equality for plain JSON-like data.
 *
 * @param {any} a
 * @param {any} b
 * @returns {boolean}
 */
export function isDeepEqual(a, b) {
  if (a === b) return true
  if (a == null || b == null) return a === b
  if (typeof a !== typeof b) return false

  if (Array.isArray(a)) {
    if (!Array.isArray(b) || a.length !== b.length) return false

    return a.every((v, i) => isDeepEqual(v, b[i]))
  }

  if (typeof a === 'object') {
    const ka = Object.keys(a)
    const kb = Object.keys(b)

    if (ka.length !== kb.length) return false

    return ka.every(k => Object.prototype.hasOwnProperty.call(b, k) && isDeepEqual(a[k], b[k]))
  }

  // eslint-disable-next-line no-self-compare
  return a !== a && b !== b // NaN
}

const clone = value => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)))

/**
 * useDirtyState
 * ─────────────────────────────────────────────────────────────────────────────
 * Tracks whether a reactive value differs from the last committed snapshot.
 * Shared by wizard dialogs and edit pages (instead of per-page JSON.stringify).
 *
 *   const { isDirty, commit, discard } = useDirtyState(form)
 *   await save(); commit()
 *
 * @param {import('vue').Ref<any>|object} source Reactive source (ref/reactive/getter result).
 * @param {{ ignore?: string[] }} [options] Top-level keys excluded from the comparison.
 */
export function useDirtyState(source, options = {}) {
  const ignore = options.ignore || []

  const normalise = value => {
    const raw = clone(unref(value))
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) ignore.forEach(k => delete raw[k])

    return raw
  }

  const snapshot = ref(normalise(source))

  const isDirty = computed(() => !isDeepEqual(normalise(source), snapshot.value))

  /** Treat the current value as the new clean baseline. */
  const commit = () => {
    snapshot.value = normalise(source)
  }

  /** Restore the baseline into the source (works with ref and reactive). */
  const discard = () => {
    const base = clone(snapshot.value)
    const target = unref(source)

    if (target && typeof target === 'object' && base && typeof base === 'object') {
      Object.keys(target).forEach(k => {
        if (!ignore.includes(k)) delete target[k]
      })
      Object.assign(target, base)
    }
  }

  return { isDirty, commit, discard, snapshot }
}
