import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Standalone Guidelines.
 *
 * CRUD for guidelines + steps management + blueprint attach/detach.
 */
export const useGuidelineStore = defineStore('guideline', {
  state: () => ({
    guidelines: [],
    totalGuidelines: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // GUIDELINE CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch paginated/filtered guideline list
    async fetchGuidelines(params) {
      this.loading = true
      try {
        const response = await $api('/catalog/guidelines', {
          method: 'GET',
          params,
        })

        this.guidelines = response.data || []
        this.totalGuidelines = response.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.guidelines = []
          this.totalGuidelines = 0

          return
        }
        console.error('Error fetching guidelines:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single guideline with steps
    async fetchGuideline(id) {
      try {
        const response = await $api(`/catalog/guidelines/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching guideline ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new guideline
    async createGuideline(data) {
      return await $api('/catalog/guidelines', {
        method: 'POST',
        body: data,
      })
    },

    // 👉 Update an existing guideline
    async updateGuideline(id, data) {
      return await $api(`/catalog/guidelines/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    // 👉 Delete a guideline
    async deleteGuideline(id) {
      return await $api(`/catalog/guidelines/${id}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // GUIDELINE STEPS
    // ──────────────────────────────────────────────────

    async createStep(data) {
      return await $api('/catalog/guideline-steps', {
        method: 'POST',
        body: data,
      })
    },

    async updateStep(id, data) {
      return await $api(`/catalog/guideline-steps/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    async deleteStep(id) {
      return await $api(`/catalog/guideline-steps/${id}`, {
        method: 'DELETE',
      })
    },

    async reorderSteps(guidelineId, steps) {
      return await $api(`/catalog/guidelines/${guidelineId}/reorder-steps`, {
        method: 'POST',
        body: { steps },
      })
    },

    // ──────────────────────────────────────────────────
    // BLUEPRINT ↔ GUIDELINE ATTACH/DETACH
    // ──────────────────────────────────────────────────

    async fetchBlueprintGuidelines(blueprintId) {
      return await $api(`/catalog/blueprints/${blueprintId}/guidelines`, { method: 'GET' })
    },

    async attachToBlueprint(blueprintId, guidelineId) {
      return await $api(`/catalog/blueprints/${blueprintId}/guidelines/${guidelineId}`, {
        method: 'POST',
      })
    },

    async detachFromBlueprint(blueprintId, guidelineId) {
      return await $api(`/catalog/blueprints/${blueprintId}/guidelines/${guidelineId}`, {
        method: 'DELETE',
      })
    },
  },
})
