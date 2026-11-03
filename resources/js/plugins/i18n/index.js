import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

// ── Load base locale files (locales/*.json) ─────────────────────────────
const messages = Object.fromEntries(Object.entries(import.meta.glob('./locales/*.json', { eager: true }))
  .map(([key, value]) => [key.slice(10, -5), value.default]))

// ── Deep-merge per-module locale files (locales/{lang}/*.json) ──────────
const deepMerge = (target, source) => {
  for (const key in source) {
    if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
      target[key] = deepMerge(target[key] || {}, source[key])
    } else {
      target[key] = source[key]
    }
  }

  return target
}

const moduleLocales = import.meta.glob('./locales/*/*.json', { eager: true })

for (const [path, mod] of Object.entries(moduleLocales)) {
  // path example: "./locales/en/supply-chain-procurement.json"
  const parts = path.replace('./locales/', '').replace('.json', '').split('/')
  const lang = parts[0] // "en"

  if (!messages[lang]) messages[lang] = {}
  deepMerge(messages[lang], mod.default)
}

let _i18n = null
export const getI18n = () => {
  if (_i18n === null) {
    _i18n = createI18n({
      legacy: false,
      locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
      fallbackLocale: 'en',
      messages,
    })
  }
  
  return _i18n
}
export default function (app) {
  app.use(getI18n())
}
