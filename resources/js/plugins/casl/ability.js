import { reactive, ref } from 'vue'

class SpatiePermissionAbility {
  constructor() {
    this.rules = reactive([])
    this.permissions = reactive(new Set())
    this.roles = reactive(new Set())
    this.isSuperAdmin = ref(false)
  }

  update(newRules = []) {
    this.rules.splice(0, this.rules.length, ...(Array.isArray(newRules) ? newRules : []))
    this.syncFromStorage()
  }

  syncFromStorage() {
    try {
      const userData = useCookie('userData').value
      const perms = userData?.permissions || []
      const roles = userData?.roles || (userData?.role ? [userData.role] : [])

      this.permissions.clear()
      perms.forEach(p => this.permissions.add(p))

      this.roles.clear()
      roles.forEach(r => this.roles.add(r))

      this.isSuperAdmin.value = this.roles.has('Super Admin') || this.roles.has('admin')
    } catch {
      // In SSR or testing contexts where cookies might not be initialized
    }
  }

  can(action, subject) {
    if (this.isSuperAdmin.value) {
      return true
    }

    // 1. Check mapped ability rules if present
    if (this.rules.length > 0) {
      const matchedRule = this.rules.some(r => {
        if (r.action === 'manage' && r.subject === 'all') return true
        const actionMatches = r.action === action || r.action === 'manage'
        const subjectMatches = r.subject === subject || r.subject === 'all'
        return actionMatches && subjectMatches
      })
      if (matchedRule) return true
    }

    // 2. Check direct Spatie permissions
    if (subject && action) {
      // Hyphenated style: e.g. "create-PeopleSkills", "read-Catalog"
      if (this.permissions.has(`${action}-${subject}`)) return true

      // Dot style: e.g. "catalog.create", "hr.attendance.manage"
      const lowerSubject = subject.toLowerCase()
      const lowerAction = action.toLowerCase()
      if (this.permissions.has(`${lowerSubject}.${lowerAction}`)) return true

      // HR domain mappings
      if (subject === 'PeopleSkills') {
        if (this.permissions.has('read-PeopleSkills')) return true
        if (action === 'create' || action === 'update' || action === 'edit') {
          if (this.permissions.has('hr.employee.manage') || this.permissions.has('hr.attendance.manage')) return true
        }
      }

      if (subject === 'Fleet') {
        if (this.permissions.has('hr.fleet.view') || this.permissions.has('hr.fleet.manage')) return true
      }

      if (subject === 'BusinessPartners') {
        if (this.permissions.has(`${action}-BusinessPartners`) || this.permissions.has('manage-BusinessPartners')) return true
      }
    }

    // Fallback: if user has manage: all
    return this.permissions.has('manage-all') || this.permissions.has('*')
  }
}

export const ability = new SpatiePermissionAbility()
export default ability
