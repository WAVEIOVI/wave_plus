/**
 * useSnackbarStore.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Pinia store wrapper bridging to the unified useAppSnackbar composable.
 * Keeps backwards compatibility with existing callers while using the enhanced
 * queue, deduplication, responsive styling, and theme support.
 */

import { defineStore } from 'pinia'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

export const useSnackbarStore = defineStore('snackbar', {
  state: () => {
    const { snackbar } = useAppSnackbar()
    
    return {
      get show() {
        return snackbar.value.show
      },
      set show(val) {
        if (!val) {
          useAppSnackbar().dismiss()
        }
      },
      get text() {
        return snackbar.value.text
      },
      get color() {
        return snackbar.value.color
      },
      get timeout() {
        return snackbar.value.timeout
      },
    }
  },
  actions: {
    showMessage(text, color = 'success', opts = {}) {
      const { showMessage } = useAppSnackbar()
      
      return showMessage(text, color, opts)
    },
    showSuccess(text, opts = {}) {
      const { success } = useAppSnackbar()
      
      return success(text, opts)
    },
    showError(text, opts = {}) {
      const { error } = useAppSnackbar()
      
      return error(text, opts)
    },
    showWarning(text, opts = {}) {
      const { warning } = useAppSnackbar()
      
      return warning(text, opts)
    },
    showInfo(text, opts = {}) {
      const { info } = useAppSnackbar()
      
      return info(text, opts)
    },
    showApiError(error, defaultFallback, opts = {}) {
      const { showApiError } = useAppSnackbar()
      
      return showApiError(error, defaultFallback, opts)
    },
    hideMessage() {
      const { dismiss } = useAppSnackbar()
      
      dismiss()
    },
  },
})
