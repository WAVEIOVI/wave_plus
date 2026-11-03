import { ref } from 'vue'

/**
 * useDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic boolean-open/close dialog state with optional item binding.
 *
 * Usage:
 *   const { isOpen, item: selectedItem, open, close, toggle } = useDialog()
 *   open(row)   // opens with a specific record
 *   open()      // opens without binding an item (e.g. Create dialog)
 *
 * @template T
 * @returns {{
 *   isOpen: import('vue').Ref<boolean>,
 *   item: import('vue').Ref<T|null>,
 *   open: (data?: T) => void,
 *   close: () => void,
 *   toggle: () => void,
 * }}
 */
export function useDialog() {
  const isOpen = ref(false)

  /** @type {import('vue').Ref<any|null>} */
  const item = ref(null)

  /**
   * Open the dialog, optionally binding a record.
   * @param {any} [data]
   */
  const open = (data = null) => {
    item.value = data
    isOpen.value = true
  }

  const close = () => {
    isOpen.value = false
    item.value = null
  }

  const toggle = () => {
    isOpen.value = !isOpen.value
  }

  return {
    isOpen,
    item,
    open,
    close,
    toggle,
  }
}
