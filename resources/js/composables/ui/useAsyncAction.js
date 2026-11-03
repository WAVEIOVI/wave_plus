import { ref } from 'vue'

/**
 * useAsyncAction
 * ─────────────────────────────────────────────────────────────────────────────
 * Wraps any async operation with loading / error / success state management.
 *
 * Usage:
 *   const { execute, isLoading, error, isSuccess } = useAsyncAction(async (id) => {
 *     await store.remove(id)
 *   })
 *
 *   await execute(item.id)
 *
 * @param {Function} fn - Async function to wrap.
 * @param {object} [opts]
 * @param {Function} [opts.onSuccess] - Called after fn resolves successfully.
 * @param {Function} [opts.onError]   - Called after fn rejects.
 * @returns {{
 *   execute: (...args: any[]) => Promise<any>,
 *   isLoading: import('vue').Ref<boolean>,
 *   error: import('vue').Ref<string|null>,
 *   isSuccess: import('vue').Ref<boolean>,
 *   reset: () => void,
 * }}
 */
export function useAsyncAction(fn, { onSuccess, onError } = {}) {
  const isLoading = ref(false)
  const error = ref(null)
  const isSuccess = ref(false)

  const reset = () => {
    isLoading.value = false
    error.value = null
    isSuccess.value = false
  }

  /**
   * Execute the wrapped function.
   * @param {...any} args
   * @returns {Promise<any>}
   */
  const execute = async (...args) => {
    isLoading.value = true
    error.value = null
    isSuccess.value = false

    try {
      const result = await fn(...args)

      isSuccess.value = true
      onSuccess?.(result)

      return result
    }
    catch (err) {
      const message = err?.data?.message || err?.message || 'An unexpected error occurred'

      error.value = message
      onError?.(err)
      throw err
    }
    finally {
      isLoading.value = false
    }
  }

  return {
    execute,
    isLoading,
    error,
    isSuccess,
    reset,
  }
}
