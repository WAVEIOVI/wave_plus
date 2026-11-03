/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

export const useHrPlannerStore = defineStore('hrPlannerStore', {
  state: () => {
    const today = new Date()

    // Find current Monday
    const day = today.getDay()
    const diffToMonday = today.getDate() - day + (day === 0 ? -6 : 1)
    const monday = new Date(today.setDate(diffToMonday))
    const sunday = new Date(monday)

    sunday.setDate(monday.getDate() + 6)

    const toIsoDate = d => d.toISOString().split('T')[0]

    return {
      startDate: toIsoDate(monday),
      endDate: toIsoDate(sunday),
      departmentId: null,
      teamId: null,
      search: '',
      dates: [],
      shifts: [],
      employees: [],
      meta: {},
      isLoading: false,
      isSaving: false,
      isBulkDialogOpen: false,
      selectedBatchId: null,
    }
  },

  actions: {
    setDateRange(start, end) {
      this.startDate = start
      this.endDate = end
      
      return this.fetchPlannerGrid()
    },

    nextWeek() {
      const start = new Date(this.startDate)

      start.setDate(start.getDate() + 7)

      const end = new Date(this.endDate)

      end.setDate(end.getDate() + 7)
      
      return this.setDateRange(start.toISOString().split('T')[0], end.toISOString().split('T')[0])
    },

    prevWeek() {
      const start = new Date(this.startDate)

      start.setDate(start.getDate() - 7)

      const end = new Date(this.endDate)

      end.setDate(end.getDate() - 7)
      
      return this.setDateRange(start.toISOString().split('T')[0], end.toISOString().split('T')[0])
    },

    goToCurrentWeek() {
      const today = new Date()
      const day = today.getDay()
      const diffToMonday = today.getDate() - day + (day === 0 ? -6 : 1)
      const monday = new Date(today.setDate(diffToMonday))
      const sunday = new Date(monday)

      sunday.setDate(monday.getDate() + 6)

      const toIsoDate = d => d.toISOString().split('T')[0]
      
      return this.setDateRange(toIsoDate(monday), toIsoDate(sunday))
    },

    async fetchPlannerGrid() {
      this.isLoading = true
      try {
        const params = {
          start_date: this.startDate,
          end_date: this.endDate,
        }

        if (this.departmentId) params.department_id = this.departmentId
        if (this.teamId) params.team_id = this.teamId
        if (this.search) params.search = this.search

        const res = await $api('/hr/workforce/planner-grid', {
          method: 'GET',
          params,
        })

        this.dates = res.dates || []
        this.shifts = res.shifts || []
        this.employees = res.employees || []
        this.meta = res.meta || {}
      } catch (error) {
        console.error('Failed to load workforce planner grid:', error)
      } finally {
        this.isLoading = false
      }
    },

    async assignCellSchedule(employeeId, date, payload) {
      this.isSaving = true
      try {
        await $api('/hr/workforce/schedules', {
          method: 'POST',
          body: {
            employee_id: employeeId,
            date,
            ...payload,
          },
        })
        await this.fetchPlannerGrid()
      } catch (error) {
        console.error('Failed to assign schedule cell:', error)
        throw error
      } finally {
        this.isSaving = false
      }
    },

    async removeCellSchedule(scheduleId) {
      this.isSaving = true
      try {
        await $api(`/hr/workforce/schedules/${scheduleId}`, {
          method: 'DELETE',
        })
        await this.fetchPlannerGrid()
      } catch (error) {
        console.error('Failed to remove schedule:', error)
        throw error
      } finally {
        this.isSaving = false
      }
    },

    async bulkSchedule(payload) {
      this.isSaving = true
      try {
        const res = await $api('/hr/workforce/bulk-schedule', {
          method: 'POST',
          body: payload,
        })

        await this.fetchPlannerGrid()
        
        return res
      } catch (error) {
        console.error('Failed to execute bulk schedule:', error)
        throw error
      } finally {
        this.isSaving = false
      }
    },

    async publishSchedules(payload = {}) {
      this.isSaving = true
      try {
        const res = await $api('/hr/workforce/publish-schedules', {
          method: 'POST',
          body: {
            start_date: this.startDate,
            end_date: this.endDate,
            ...payload,
          },
        })

        await this.fetchPlannerGrid()
        
        return res
      } catch (error) {
        console.error('Failed to publish schedules:', error)
        throw error
      } finally {
        this.isSaving = false
      }
    },

    async clearSchedules(payload = {}) {
      this.isSaving = true
      try {
        const res = await $api('/hr/workforce/clear-schedules', {
          method: 'POST',
          body: {
            start_date: this.startDate,
            end_date: this.endDate,
            ...payload,
          },
        })

        await this.fetchPlannerGrid()
        
        return res
      } catch (error) {
        console.error('Failed to clear schedules:', error)
        throw error
      } finally {
        this.isSaving = false
      }
    },
  },
})
