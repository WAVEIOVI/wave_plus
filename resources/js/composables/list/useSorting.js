import { ref } from 'vue'

/**
 * useSorting
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic server-side sort state compatible with VDataTableServer's
 * `sortBy` format: [{ key: string, order: 'asc'|'desc' }].
 *
 * Usage:
 *   const { sortBy, applySorting } = useSorting({ key: 'created_at', order: 'desc' })
 *
 * @param {object} [opts]
 * @param {string} [opts.key='id']
 * @param {'asc'|'desc'} [opts.order='desc']
 */
export function useSorting({ key = 'id', order = 'desc' } = {}) {
  /** @type {import('vue').Ref<Array<{key: string, order: string}>>} */
  const sortBy = ref([{ key, order }])

  /**
   * Handler to pass to `@update:sort-by` on VDataTableServer.
   * @param {Array<{key: string, order: string}>} value
   */
  const applySorting = value => {
    sortBy.value = value?.length ? value : [{ key, order }]
  }

  return {
    sortBy,
    applySorting,
  }
}
