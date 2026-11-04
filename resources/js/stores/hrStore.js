import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

/**
 * Normalise a Laravel JSON payload into an array.
 * Handles: plain arrays, { data: [...] } and { data: { data: [...] } }.
 */
const toList = res => {
  if (Array.isArray(res)) return res
  if (Array.isArray(res?.data)) return res.data
  if (Array.isArray(res?.data?.data)) return res.data.data

  return []
}

export const useHrStore = defineStore('hrStore', {
  state: () => ({
    departments: [],
    departmentTree: [],
    positions: [],
    teams: [],
    skills: [],
    certifications: [],
    expiringCertifications: [],
    leaveTypes: [],
    shifts: [],
    schedules: [],
    managers: [],
    availableUsers: [],
    metaLoaded: false,
    isLoadingMeta: false,
    dashboardStats: null,
    isLoadingStats: false,
  }),

  actions: {
    // ── Core meta ──────────────────────────────────────────────
    // Only loads core employee/org meta (departments, positions, teams,
    // managers, users). Domain lists are fetched per-page on demand.
    async fetchEmployeesMeta(force = false) {
      if (this.metaLoaded && !force) return this.departments

      this.isLoadingMeta = true
      try {
        const metaRes = await $api('/hr/employees/meta', { method: 'GET' })

        this.departments = metaRes.departments || []
        this.positions = metaRes.positions || []
        this.teams = metaRes.teams || []
        this.managers = metaRes.managers || []
        this.availableUsers = metaRes.available_users || []
        this.metaLoaded = true
      } catch (error) {
        console.error('Failed to load HR employees meta:', error)
      } finally {
        this.isLoadingMeta = false
      }
    },

    // Backward-compatible alias for core meta
    async fetchMeta(force = false) {
      return this.fetchEmployeesMeta(force)
    },

    // ── Departments ────────────────────────────────────────────
    async fetchDepartments(force = false) {
      if (this.departments.length > 0 && !force) return this.departments
      try {
        const res = await $api('/hr/departments', { method: 'GET' })

        this.departments = toList(res)
        this.departmentTree = res.tree || []

        return this.departments
      } catch (error) {
        console.error('Failed to load departments:', error)

        return []
      }
    },

    async createDepartment(payload) {
      const res = await $api('/hr/departments', { method: 'POST', body: payload })

      await this.fetchDepartments(true)

      return res.data
    },

    async updateDepartment(id, payload) {
      const res = await $api(`/hr/departments/${id}`, { method: 'PUT', body: payload })

      await this.fetchDepartments(true)

      return res.data
    },

    async deleteDepartment(id) {
      await $api(`/hr/departments/${id}`, { method: 'DELETE' })
      await this.fetchDepartments(true)
    },

    // ── Positions ──────────────────────────────────────────────
    async fetchPositions(force = false) {
      if (this.positions.length > 0 && !force) return this.positions
      try {
        const res = await $api('/hr/positions', { method: 'GET' })

        this.positions = toList(res)

        return this.positions
      } catch (error) {
        console.error('Failed to load positions:', error)

        return []
      }
    },

    async createPosition(payload) {
      const res = await $api('/hr/positions', { method: 'POST', body: payload })

      await this.fetchPositions(true)

      return res.data
    },

    async updatePosition(id, payload) {
      const res = await $api(`/hr/positions/${id}`, { method: 'PUT', body: payload })

      await this.fetchPositions(true)

      return res.data
    },

    async deletePosition(id) {
      await $api(`/hr/positions/${id}`, { method: 'DELETE' })
      await this.fetchPositions(true)
    },

    // ── Work Teams ─────────────────────────────────────────────
    async fetchTeams(force = false) {
      if (this.teams.length > 0 && !force) return this.teams
      try {
        const res = await $api('/hr/teams', { method: 'GET' })

        this.teams = toList(res)

        return this.teams
      } catch (error) {
        console.error('Failed to load teams:', error)

        return []
      }
    },

    async createTeam(payload) {
      const res = await $api('/hr/teams', { method: 'POST', body: payload })

      await this.fetchTeams(true)

      return res.data
    },

    async updateTeam(id, payload) {
      const res = await $api(`/hr/teams/${id}`, { method: 'PUT', body: payload })

      await this.fetchTeams(true)

      return res.data
    },

    async deleteTeam(id) {
      await $api(`/hr/teams/${id}`, { method: 'DELETE' })
      await this.fetchTeams(true)
    },

    async addTeamMember(teamId, payload) {
      const res = await $api(`/hr/teams/${teamId}/members`, { method: 'POST', body: payload })

      await this.fetchTeams(true)

      return res.data
    },

    async removeTeamMember(teamId, memberId) {
      await $api(`/hr/teams/${teamId}/members/${memberId}`, { method: 'DELETE' })
      await this.fetchTeams(true)
    },

    // ── Skills & Certifications ────────────────────────────────
    async fetchSkills(force = false) {
      if (this.skills.length > 0 && !force) return this.skills
      try {
        const res = await $api('/hr/skills', { method: 'GET' })

        this.skills = toList(res)

        return this.skills
      } catch (error) {
        console.error('Failed to load skills:', error)

        return []
      }
    },

    async createSkill(payload) {
      const res = await $api('/hr/skills', { method: 'POST', body: payload })

      await this.fetchSkills(true)

      return res.data
    },

    async updateSkill(id, payload) {
      const res = await $api(`/hr/skills/${id}`, { method: 'PUT', body: payload })

      await this.fetchSkills(true)

      return res.data
    },

    async deleteSkill(id) {
      await $api(`/hr/skills/${id}`, { method: 'DELETE' })
      await this.fetchSkills(true)
    },

    async fetchCertifications(force = false) {
      if (this.certifications.length > 0 && !force) return this.certifications
      try {
        const res = await $api('/hr/certifications', { method: 'GET' })

        this.certifications = toList(res)

        return this.certifications
      } catch (error) {
        console.error('Failed to load certifications:', error)

        return []
      }
    },

    async createCertification(payload) {
      const res = await $api('/hr/certifications', { method: 'POST', body: payload })

      await this.fetchCertifications(true)

      return res.data
    },

    async updateCertification(id, payload) {
      const res = await $api(`/hr/certifications/${id}`, { method: 'PUT', body: payload })

      await this.fetchCertifications(true)

      return res.data
    },

    async deleteCertification(id) {
      await $api(`/hr/certifications/${id}`, { method: 'DELETE' })
      await this.fetchCertifications(true)
    },

    async fetchExpiringCertifications(days = 60) {
      try {
        const res = await $api(`/hr/certifications/expiring?days=${days}`, { method: 'GET' })

        this.expiringCertifications = toList(res)

        return this.expiringCertifications
      } catch (error) {
        console.error('Failed to load expiring certifications:', error)

        return []
      }
    },

    // ── Shifts ─────────────────────────────────────────────────
    async fetchShifts(force = false) {
      if (this.shifts.length > 0 && !force) return this.shifts
      try {
        const res = await $api('/hr/workforce/shifts', { method: 'GET' })

        this.shifts = toList(res)

        return this.shifts
      } catch (error) {
        console.error('Failed to load shifts:', error)

        return []
      }
    },

    async createShift(payload) {
      const res = await $api('/hr/workforce/shifts', { method: 'POST', body: payload })

      await this.fetchShifts(true)

      return res.data
    },

    async updateShift(id, payload) {
      const res = await $api(`/hr/workforce/shifts/${id}`, { method: 'PUT', body: payload })

      await this.fetchShifts(true)

      return res.data
    },

    async deleteShift(id) {
      await $api(`/hr/workforce/shifts/${id}`, { method: 'DELETE' })
      await this.fetchShifts(true)
    },

    // ── Schedules ──────────────────────────────────────────────
    async fetchSchedules(params = {}) {
      try {
        const res = await $api('/hr/workforce/schedules', { method: 'GET', params })

        this.schedules = toList(res)

        return this.schedules
      } catch (error) {
        console.error('Failed to load schedules:', error)

        return []
      }
    },

    async createSchedule(payload) {
      const res = await $api('/hr/workforce/schedules', { method: 'POST', body: payload })

      await this.fetchSchedules()

      return res.data
    },

    async updateSchedule(id, payload) {
      const res = await $api(`/hr/workforce/schedules/${id}`, { method: 'PUT', body: payload })

      await this.fetchSchedules()

      return res.data
    },

    async deleteSchedule(id) {
      await $api(`/hr/workforce/schedules/${id}`, { method: 'DELETE' })
      await this.fetchSchedules()
    },

    // ── Leave Types ────────────────────────────────────────────
    async fetchLeaveTypes(force = false) {
      if (this.leaveTypes.length > 0 && !force) return this.leaveTypes
      try {
        const res = await $api('/hr/leave-types', { method: 'GET' })

        this.leaveTypes = toList(res)

        return this.leaveTypes
      } catch (error) {
        console.error('Failed to load leave types:', error)

        return []
      }
    },

    async createLeaveType(payload) {
      const res = await $api('/hr/leave-types', { method: 'POST', body: payload })

      await this.fetchLeaveTypes(true)

      return res.data
    },

    // ── Dashboard ──────────────────────────────────────────────
    async fetchDashboardStats() {
      this.isLoadingStats = true
      try {
        const res = await $api('/hr/dashboard-stats', { method: 'GET' })

        this.dashboardStats = res.data

        return res.data
      } catch (error) {
        console.error('Failed to fetch HR dashboard stats:', error)
        throw error
      } finally {
        this.isLoadingStats = false
      }
    },
  },
})
