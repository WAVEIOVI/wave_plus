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

  setTestPermissions(perms = [], roles = []) {
    this.permissions.clear()
    perms.forEach(p => this.permissions.add(p))

    this.roles.clear()
    roles.forEach(r => this.roles.add(r))

    this.isSuperAdmin.value = this.roles.has('Super Admin') || this.roles.has('admin')
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

    // 2. Check direct Spatie permissions and normalized mappings
    if (subject && action) {
      // Hyphenated style: e.g. "create-PeopleSkills", "read-Catalog"
      if (this.permissions.has(`${action}-${subject}`)) return true

      // Normalized mapping for entity CRUD operations (backend uses edit-* for updates)
      if ((action === 'update' || action === 'edit') && (this.permissions.has(`edit-${subject}`) || this.permissions.has(`update-${subject}`))) {
        return true
      }
      if (action === 'read' && this.permissions.has(`read-${subject}`)) {
        return true
      }
      if (action === 'create' && this.permissions.has(`create-${subject}`)) {
        return true
      }
      if (action === 'delete' && this.permissions.has(`delete-${subject}`)) {
        return true
      }
      if (action === 'export' && this.permissions.has(`export-${subject}`)) {
        return true
      }

      // Business Partners canonical subjects: clients, suppliers, service-providers, organisations
      const bpEntitySubjects = ['clients', 'suppliers', 'service-providers', 'organisations']
      if (bpEntitySubjects.includes(subject) && action === 'manage' && this.permissions.has(`edit-${subject}`) && this.permissions.has(`create-${subject}`)) {
        return true
      }

      // Bank accounts dedicated permissions
      if (subject === 'bp-bank-accounts') {
        if (action === 'read') {
          return this.permissions.has('read-bp-bank-accounts') || this.permissions.has('manage-bp-bank-accounts')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'delete' || action === 'manage') {
          return this.permissions.has('manage-bp-bank-accounts')
        }
      }

      // Email messages / sending
      if ((subject === 'email-messages' || subject === 'bp-email') && (action === 'create' || action === 'send')) {
        return this.permissions.has('create-email-messages')
      }

      // Email batches
      if ((subject === 'bp-email-batches' || subject === 'all-bp-email-batches') && (action === 'read' || action === 'view')) {
        return this.permissions.has('view-all-bp-email-batches')
      }

      // Dashboard
      if (subject === 'bp-dashboard' && action === 'read') {
        return this.permissions.has('read-bp-dashboard')
      }

      // Top-level module visibility ONLY (e.g. app switcher)
      // Must NOT grant access to any individual entity operations
      if (subject === 'BusinessPartners') {
        if (action === 'read') {
          return this.permissions.has('read-BusinessPartners')
            || this.permissions.has('read-bp-dashboard')
            || this.permissions.has('read-clients')
            || this.permissions.has('read-suppliers')
            || this.permissions.has('read-service-providers')
            || this.permissions.has('read-organisations')
        }

        return false
      }

      // Dot style: e.g. "catalog.create", "hr.attendance.manage"
      const lowerSubject = subject.toLowerCase()
      const lowerAction = action.toLowerCase()
      if (this.permissions.has(`${lowerSubject}.${lowerAction}`)) return true

      // HR canonical entity subjects
      if (lowerSubject === 'employees' || lowerSubject === 'employee') {
        if (action === 'read') {
          return this.permissions.has('hr.employees.view') || this.permissions.has('read-PeopleSkills') || this.permissions.has('read-employees')
        }
        if (action === 'create') {
          return this.permissions.has('hr.employees.create') || this.permissions.has('create-PeopleSkills') || this.permissions.has('create-employees')
        }
        if (action === 'update' || action === 'edit') {
          return this.permissions.has('hr.employees.edit') || this.permissions.has('edit-PeopleSkills') || this.permissions.has('edit-employees') || this.permissions.has('update-employees')
        }
        if (action === 'delete') {
          return this.permissions.has('hr.employees.archive') || this.permissions.has('delete-PeopleSkills') || this.permissions.has('delete-employees')
        }
        if (action === 'view-sensitive' || action === 'sensitive') {
          return this.permissions.has('hr.employees.view-sensitive')
        }
        if (action === 'manage') {
          return (this.permissions.has('hr.employees.create') || this.permissions.has('create-PeopleSkills')) &&
            (this.permissions.has('hr.employees.edit') || this.permissions.has('edit-PeopleSkills'))
        }
      }

      if (['departments', 'department'].includes(lowerSubject)) {
        if (action === 'read') {
          return this.permissions.has('hr.departments.view') || this.permissions.has('hr.departments.manage') || this.permissions.has('hr.employees.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'delete' || action === 'manage') {
          return this.permissions.has('hr.departments.manage') || this.permissions.has('hr.employees.edit') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (['positions', 'position'].includes(lowerSubject)) {
        if (action === 'read') {
          return this.permissions.has('hr.positions.view') || this.permissions.has('hr.positions.manage') || this.permissions.has('hr.employees.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'delete' || action === 'manage') {
          return this.permissions.has('hr.positions.manage') || this.permissions.has('hr.employees.edit') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (['teams', 'team'].includes(lowerSubject)) {
        if (action === 'read') {
          return this.permissions.has('hr.teams.view') || this.permissions.has('hr.teams.manage') || this.permissions.has('hr.workforce.view') || this.permissions.has('hr.employees.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'delete' || action === 'manage') {
          return this.permissions.has('hr.teams.manage') || this.permissions.has('hr.workforce.assign') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if ((lowerSubject === 'hr-dashboard' || lowerSubject === 'people-skills-dashboard') && action === 'read') {
        return this.permissions.has('read-hr-dashboard') || this.permissions.has('read-PeopleSkills') || this.permissions.has('hr.employees.view')
      }

      if (lowerSubject === 'appraisals' || lowerSubject === 'appraisal') {
        if (action === 'read') {
          return this.permissions.has('hr.appraisals.view') || this.permissions.has('hr.appraisals.manage')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.appraisals.manage')
        }
      }

      if (lowerSubject === 'attendance') {
        if (action === 'read') {
          return this.permissions.has('hr.attendance.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.attendance.manage') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
        if (action === 'correct') {
          return this.permissions.has('hr.attendance.correct') || this.permissions.has('hr.attendance.manage') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (lowerSubject === 'leave' || lowerSubject === 'time-off') {
        if (action === 'read') {
          return this.permissions.has('hr.leave.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create') {
          return this.permissions.has('hr.leave.request') || this.permissions.has('hr.leave.manage') || this.permissions.has('create-PeopleSkills')
        }
        if (action === 'approve' || action === 'update' || action === 'edit') {
          return this.permissions.has('hr.leave.approve') || this.permissions.has('hr.leave.manage') || this.permissions.has('edit-PeopleSkills')
        }
        if (action === 'delete' || action === 'manage') {
          return this.permissions.has('hr.leave.manage') || this.permissions.has('delete-PeopleSkills')
        }
      }

      if (lowerSubject === 'recruitment' || lowerSubject === 'candidates') {
        if (action === 'read') {
          return this.permissions.has('hr.recruitment.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.recruitment.manage') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
        if (action === 'hire') {
          return this.permissions.has('hr.recruitment.hire') || this.permissions.has('hr.recruitment.manage') || this.permissions.has('create-PeopleSkills')
        }
      }

      if (lowerSubject === 'training') {
        if (action === 'read') {
          return this.permissions.has('hr.training.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.training.manage') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (lowerSubject === 'skills' || lowerSubject === 'certifications') {
        if (action === 'read') {
          return this.permissions.has('hr.skills.view') || this.permissions.has('hr.certifications.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.skills.manage') || this.permissions.has('hr.certifications.manage') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (lowerSubject === 'workforce') {
        if (action === 'read') {
          return this.permissions.has('hr.workforce.view') || this.permissions.has('read-PeopleSkills')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'assign' || action === 'manage') {
          return this.permissions.has('hr.workforce.assign') || this.permissions.has('create-PeopleSkills') || this.permissions.has('edit-PeopleSkills')
        }
      }

      if (lowerSubject === 'fleet') {
        if (action === 'read') {
          return this.permissions.has('hr.fleet.view') || this.permissions.has('read-Fleet')
        }
        if (action === 'create' || action === 'update' || action === 'edit' || action === 'manage') {
          return this.permissions.has('hr.fleet.manage') || this.permissions.has('create-Fleet') || this.permissions.has('edit-Fleet')
        }
        if (action === 'delete') {
          return this.permissions.has('hr.fleet.archive') || this.permissions.has('hr.fleet.manage') || this.permissions.has('delete-Fleet')
        }
      }

      // HR domain mappings for legacy / umbrella subject
      if (subject === 'PeopleSkills') {
        if (action === 'read' && (
          this.permissions.has('read-PeopleSkills')
          || this.permissions.has('hr.employees.view')
          || this.permissions.has('hr.attendance.view')
          || this.permissions.has('hr.leave.view')
          || this.permissions.has('hr.recruitment.view')
          || this.permissions.has('hr.training.view')
          || this.permissions.has('hr.certifications.view')
          || this.permissions.has('hr.workforce.view')
        )) {
          return true
        }

        if (action === 'create' && (
          this.permissions.has('create-PeopleSkills')
          || this.permissions.has('hr.employees.create')
          || this.permissions.has('hr.attendance.manage')
          || this.permissions.has('hr.leave.request')
          || this.permissions.has('hr.recruitment.manage')
          || this.permissions.has('hr.training.manage')
          || this.permissions.has('hr.certifications.manage')
          || this.permissions.has('hr.workforce.assign')
        )) {
          return true
        }

        if ((action === 'update' || action === 'edit') && (
          this.permissions.has('edit-PeopleSkills')
          || this.permissions.has('hr.employees.edit')
          || this.permissions.has('hr.attendance.manage')
          || this.permissions.has('hr.leave.approve')
          || this.permissions.has('hr.leave.manage')
          || this.permissions.has('hr.recruitment.manage')
          || this.permissions.has('hr.training.manage')
          || this.permissions.has('hr.certifications.manage')
          || this.permissions.has('hr.workforce.assign')
        )) {
          return true
        }

        if (action === 'delete' && (
          this.permissions.has('delete-PeopleSkills')
          || this.permissions.has('hr.employees.archive')
          || this.permissions.has('hr.leave.manage')
        )) {
          return true
        }
      }
    }

    // Fallback: if user has manage: all
    return this.permissions.has('manage-all') || this.permissions.has('*')
  }
}

export const ability = new SpatiePermissionAbility()
export default ability
