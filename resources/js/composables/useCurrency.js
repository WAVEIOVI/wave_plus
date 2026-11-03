import { ref, computed } from 'vue'

const DEFAULT_CURRENCY = 'SAR'
const DEFAULT_LOCALE = 'en'

const currency = ref(DEFAULT_CURRENCY)
const locale = ref(DEFAULT_LOCALE)

export const setCurrency = (code) => {
  currency.value = code
}

export const setLocale = (loc) => {
  locale.value = loc
}

export const useCurrency = () => {
  const currentCurrency = computed(() => currency.value)
  const currentLocale = computed(() => locale.value)

  const format = (value, options = {}) => {
    if (value === null || value === undefined || value === '') return '—'

    const numValue = Number(value)
    if (isNaN(numValue)) return '—'

    const resolvedOptions = typeof options === 'string'
      ? { currency: options }
      : (options || {})

    const curr = resolvedOptions.currency || currentCurrency.value
    const loc = resolvedOptions.locale || currentLocale.value

    try {
      return new Intl.NumberFormat(loc, {
        style: 'currency',
        currency: curr,
        minimumFractionDigits: resolvedOptions.minimumFractionDigits ?? 2,
        maximumFractionDigits: resolvedOptions.maximumFractionDigits ?? 2,
        ...resolvedOptions,
      }).format(numValue)
    }
    catch (e) {
      return `${numValue.toFixed(2)} ${curr}`
    }
  }

  const formatPlain = (value, options = {}) => {
    if (value === null || value === undefined || value === '') return '—'

    const numValue = Number(value)
    if (isNaN(numValue)) return '—'

    const resolvedOptions = typeof options === 'string'
      ? { locale: options }
      : (options || {})

    const loc = resolvedOptions.locale || currentLocale.value

    try {
      return new Intl.NumberFormat(loc, {
        style: 'decimal',
        minimumFractionDigits: resolvedOptions.minimumFractionDigits ?? 2,
        maximumFractionDigits: resolvedOptions.maximumFractionDigits ?? 2,
        ...resolvedOptions,
      }).format(numValue)
    }
    catch (e) {
      return numValue.toFixed(2)
    }
  }

  return {
    currency: currentCurrency,
    locale: currentLocale,
    format,
    formatCurrency: format,
    formatPlain,
  }
}

export const currencySelectionOptions = [
  { title: 'SAR — Saudi Riyal', value: 'SAR' },
  { title: 'USD — US Dollar', value: 'USD' },
  { title: 'EUR — Euro', value: 'EUR' },
  { title: 'TND — Tunisian Dinar', value: 'TND' },
]