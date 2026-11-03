import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useLocaleField
 * Returns a computed writable ref for a multilingual field { en, fr, ar }.
 *
 * @param {import('vue').Ref<any>} targetRef
 * @param {string} fieldKey
 * @param {import('vue').Ref<string>|null} [customLocale]
 */
export function useLocaleField(targetRef, fieldKey, customLocale = null) {
  const i18n = useI18n()
  const localeRef = customLocale || i18n.locale

  return computed({
    get() {
      const field = targetRef.value?.[fieldKey]
      if (field == null) return ''
      if (typeof field === 'string') return field
      const currentLocale = localeRef.value || 'en'
      
      return field[currentLocale] || field.en || Object.values(field)[0] || ''
    },
    set(newVal) {
      if (!targetRef.value) return
      const currentField = targetRef.value[fieldKey]
      const currentLocale = localeRef.value || 'en'

      if (currentField !== null && typeof currentField === 'object' && !Array.isArray(currentField)) {
        targetRef.value[fieldKey] = {
          ...currentField,
          [currentLocale]: newVal,
        }
      } else {
        targetRef.value[fieldKey] = {
          [currentLocale]: newVal,
        }
      }
    },
  })
}

/**
 * localizedValue
 * Resolves a multilingual string/object based on current active locale with fallback.
 *
 * @param {string|object|null} value
 * @param {string} [fallback='']
 */
export function localizedValue(value, fallback = '') {
  if (value == null) return fallback
  if (typeof value === 'string') return value || fallback

  let currentLocale = 'en'
  try {
    const { locale } = useI18n()

    currentLocale = locale.value || 'en'
  } catch {
    // Fallback if called outside vue-i18n injection context
  }

  return value[currentLocale] || value.en || Object.values(value)[0] || fallback
}

export function strictLocalizedValue(value, fallback = '') {
  if (value == null) return fallback
  if (typeof value === 'string') return value || fallback

  let currentLocale = 'en'
  try {
    const { locale } = useI18n()

    currentLocale = locale.value || 'en'
  } catch {
    // Fallback if called outside vue-i18n injection context
  }

  return value[currentLocale] || fallback
}

export function ensureLocalizedObject(value) {
  if (value == null) return {}
  if (typeof value === 'string') {
    let currentLocale = 'en'
    try {
      const { locale } = useI18n()

      currentLocale = locale.value || 'en'
    } catch {}
    
    return { [currentLocale]: value }
  }
  if (Array.isArray(value)) return {}
  
  return value
}

/**
 * ensureTranslatable
 * Ensures a value is a localized object with keys for all supported locales { en, fr, ar }.
 *
 * @param {string|object|null} value
 * @returns {{ en: string, fr: string, ar: string }}
 */
export function ensureTranslatable(value) {
  const base = { en: '', fr: '', ar: '' }
  if (value == null) return base
  if (typeof value === 'string') {
    let currentLocale = 'en'
    try {
      const { locale } = useI18n()

      currentLocale = locale.value || 'en'
    } catch {}
    
    return { ...base, [currentLocale]: value }
  }
  if (typeof value === 'object' && !Array.isArray(value)) {
    return {
      ...base,
      ...value,
    }
  }
  
  return base
}

