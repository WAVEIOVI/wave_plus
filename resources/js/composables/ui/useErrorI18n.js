import { useI18n } from 'vue-i18n'

/**
 * Resolve a user-facing message for an error object produced by the shared
 * media/camera composables. Prefers the stable i18n key when present so the
 * same error renders in every locale.
 */
export function useErrorI18n() {
  const { t, te } = useI18n()

  function errorMessage(error, fallbackKey = 'wp.error.generic') {
    if (!error) return ''
    if (error.i18nKey && te(error.i18nKey)) return t(error.i18nKey)

    return error.message || t(fallbackKey)
  }

  return { errorMessage }
}
