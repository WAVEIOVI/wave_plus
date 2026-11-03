import { ability } from './ability'

export default function (app) {
  ability.syncFromStorage()

  // Make $can globally available in all Vue templates
  app.config.globalProperties.$can = (action, subject) => ability.can(action, subject)
  app.provide('ability', ability)
}
