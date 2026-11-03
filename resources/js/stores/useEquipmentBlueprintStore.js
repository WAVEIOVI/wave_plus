import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Equipment Blueprints.
 *
 * Core blueprint CRUD + nested checklist & step management.
 */
export const useEquipmentBlueprintStore = defineStore('equipmentBlueprint', {
  state: () => ({
    blueprints: [],
    totalBlueprints: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // BLUEPRINT CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch paginated/filtered blueprint list
    async fetchBlueprints(params) {
      this.loading = true
      try {
        const response = await $api('/catalog/blueprints', {
          method: 'GET',
          params,
        })

        this.blueprints = response.data || []
        this.totalBlueprints = response.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.blueprints = []
          this.totalBlueprints = 0

          return
        }
        console.error('Error fetching blueprints:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single blueprint with checklists & steps
    async fetchBlueprint(id) {
      try {
        const response = await $api(`/catalog/blueprints/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching blueprint ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new blueprint
    async createBlueprint(data) {
      return await $api('/catalog/blueprints', {
        method: 'POST',
        body: data,
      })
    },

    // 👉 Update an existing blueprint
    async updateBlueprint(id, data) {
      return await $api(`/catalog/blueprints/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    // 👉 Delete a blueprint (soft-delete)
    async deleteBlueprint(id) {
      return await $api(`/catalog/blueprints/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Preview calculated results for a blueprint
    async previewBlueprint(id, data = {}) {
      return await $api(`/catalog/blueprints/${id}/preview`, {
        method: 'POST',
        body: data,
      })
    },

    // 👉 Resolve servicing and consumable lines for a blueprint
    async resolveLines(id, data = {}) {
      return await $api(`/catalog/blueprints/${id}/resolve-lines`, {
        method: 'POST',
        body: data,
      })
    },
  },
})
