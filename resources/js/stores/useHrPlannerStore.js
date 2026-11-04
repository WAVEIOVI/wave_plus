/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

const pad = n => String(n).padStart(2, '0')
const toIsoDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

const computePeriodRange = (mode, baseDate = new Date()) => {
  const d = new Date(baseDate)

  d.setHours(0, 0, 0, 0)

  if (mode === 'month') {
    const start = new Date(d.getFullYear(), d.getMonth(), 1)
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0)

    return { start: toIsoDate(start), end: toIsoDate(end) }
  }

  // Monday-based calculation
  const day = d.getDay()
  const diffToMonday = d.getDate() - day + (day === 0 ? -6 : 1)
  const monday = new Date(d.getFullYear(), d.getMonth(), diffToMonday)

  if (mode === 'biweekly') {
    const end = new Date(monday)

    end.setDate(monday.getDate() + 13)

    return { start: toIsoDate(monday), end: toIsoDate(end) }
  }

  // default 'week'
  const end = new Date(monday)

  end.setDate(monday.getDate() + 6)

  return { start: toIsoDate(monday), end: toIsoDate(end) }
}

export const useHrPlannerStore = defineStore('hrPlannerStore', {
  state: () => {
    const { start, end } = computePeriodRange('week', new Date())

    return {
      rangeMode: 'week', // 'week' | 'biweekly' | 'month' | 'custom'
      startDate: start,
      endDate: end,
      departmentId: null,
      teamId: null,
      search: '',
      dates: [],
      shifts: [],
      employees: [],
      meta: {},
      historyList: [],
      isHistoryLoading: false,
      isLoading: false,
      isSaving: false,
      isBulkDialogOpen: false,
      isHistoryDialogOpen: false,
      selectedBatchId: null,
    }
  },

  actions: {
    setDateRange(start, end) {
      this.startDate = start
      this.endDate = end
      
      return this.fetchPlannerGrid()
    },

    setRangeMode(mode) {
      this.rangeMode = mode
      if (mode !== 'custom') {
        const { start, end } = computePeriodRange(mode, new Date(this.startDate))

        return this.setDateRange(start, end)
      }
      
      return this.fetchPlannerGrid()
    },

    nextPeriod() {
      if (this.rangeMode === 'month') {
        const current = new Date(this.startDate)
        const nextMonth = new Date(current.getFullYear(), current.getMonth() + 1, 1)
        const endMonth = new Date(current.getFullYear(), current.getMonth() + 2, 0)

        return this.setDateRange(toIsoDate(nextMonth), toIsoDate(endMonth))
      }

      const daysToAdd = this.rangeMode === 'biweekly' ? 14 : 7
      const start = new Date(this.startDate)

      start.setDate(start.getDate() + daysToAdd)

      const end = new Date(this.endDate)

      end.setDate(end.getDate() + daysToAdd)
      
      return this.setDateRange(toIsoDate(start), toIsoDate(end))
    },

    prevPeriod() {
      if (this.rangeMode === 'month') {
        const current = new Date(this.startDate)
        const prevMonth = new Date(current.getFullYear(), current.getMonth() - 1, 1)
        const endMonth = new Date(current.getFullYear(), current.getMonth(), 0)

        return this.setDateRange(toIsoDate(prevMonth), toIsoDate(endMonth))
      }

      const daysToSub = this.rangeMode === 'biweekly' ? 14 : 7
      const start = new Date(this.startDate)

      start.setDate(start.getDate() - daysToSub)

      const end = new Date(this.endDate)

      end.setDate(end.getDate() - daysToSub)
      
      return this.setDateRange(toIsoDate(start), toIsoDate(end))
    },

    goToCurrentPeriod() {
      const { start, end } = computePeriodRange(this.rangeMode, new Date())
      
      return this.setDateRange(start, end)
    },

    nextWeek() {
      return this.nextPeriod()
    },

    prevWeek() {
      return this.prevPeriod()
    },

    goToCurrentWeek() {
      return this.goToCurrentPeriod()
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

    async fetchScheduleHistory(limit = 20) {
      this.isHistoryLoading = true
      try {
        const res = await $api('/hr/workforce/schedule-history', {
          method: 'GET',
          params: { limit },
        })

        this.historyList = res.batches || []
        
        return res
      } catch (error) {
        console.error('Failed to load schedule history:', error)
        throw error
      } finally {
        this.isHistoryLoading = false
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
        const body = { ...payload }

        // Only default to date range if batch_id is not specified
        if (!body.batch_id && !body.schedule_ids) {
          body.start_date = this.startDate
          body.end_date = this.endDate
        }

        const res = await $api('/hr/workforce/publish-schedules', {
          method: 'POST',
          body,
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
        const body = { ...payload }

        // Only default to date range if batch_id is not specified
        if (!body.batch_id && !body.schedule_ids) {
          body.start_date = this.startDate
          body.end_date = this.endDate
        }

        const res = await $api('/hr/workforce/clear-schedules', {
          method: 'POST',
          body,
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
