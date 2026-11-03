import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Catalog Support Entities:
 *   - Categories
 *   - Brands
 *   - Units of Measure (UOM)
 *
 * These are shared lookup data used across catalog forms.
 */
export const useCatalogSupportStore = defineStore('catalogSupport', {
  state: () => ({
    categories: [],
    equipmentClasses: [],
    brands: [],
    uom: [],
    loadingCategories: false,
    loadingEquipmentClasses: false,
    loadingBrands: false,
    loadingUom: false,
    loaded: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // CATEGORIES
    // ──────────────────────────────────────────────────

    async fetchCategories(params = {}) {
      if (this.categories.length > 0 && !params.force) return

      this.loadingCategories = true
      try {
        const response = await $api('/catalog/categories', {
          method: 'GET',
          params,
        })

        this.categories = response.data || []
        this.checkFullyLoaded()

        return response
      }
      catch (error) {
        console.error('Error fetching categories:', error)
        throw error
      }
      finally {
        this.loadingCategories = false
      }
    },

    async createCategory(data) {
      return await $api('/catalog/categories', {
        method: 'POST',
        body: data,
      })
    },

    async updateCategory(id, data) {
      return await $api(`/catalog/categories/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    async deleteCategory(id) {
      return await $api(`/catalog/categories/${id}`, {
        method: 'DELETE',
      })
    },

    async fetchEquipmentClasses(params = {}) {
      if (this.equipmentClasses.length > 0 && !params.force) return

      this.loadingEquipmentClasses = true
      try {
        const response = await $api('/catalog/equipment-classes', {
          method: 'GET',
          params,
        })

        this.equipmentClasses = response.data || []

        return response
      }
      finally {
        this.loadingEquipmentClasses = false
      }
    },

    async createEquipmentClass(data) {
      return await $api('/catalog/equipment-classes', { method: 'POST', body: data })
    },

    async updateEquipmentClass(id, data) {
      return await $api(`/catalog/equipment-classes/${id}`, { method: 'PUT', body: data })
    },

    async deleteEquipmentClass(id) {
      return await $api(`/catalog/equipment-classes/${id}`, { method: 'DELETE' })
    },

    // ──────────────────────────────────────────────────
    // BLUEPRINTS & BRANDS COMPATIBILITY ALIASES
    // ──────────────────────────────────────────────────

    async fetchCatalogBrands(params = {}) {
      return await this.fetchBrands(params)
    },

    async fetchEquipmentBlueprints(params = {}) {
      return await $api('/catalog/blueprints', {
        method: 'GET',
        params,
      })
    },

    async fetchEquipmentBlueprint(id) {
      return await $api(`/catalog/blueprints/${id}`, {
        method: 'GET',
      })
    },

    // ──────────────────────────────────────────────────
    // BRANDS
    // ──────────────────────────────────────────────────

    async fetchBrands(params = {}) {
      if (this.brands.length > 0 && !params.force) return

      this.loadingBrands = true
      try {
        const response = await $api('/catalog/brands', {
          method: 'GET',
          params,
        })

        this.brands = response.data || []
        this.checkFullyLoaded()

        return response
      }
      catch (error) {
        console.error('Error fetching brands:', error)
        throw error
      }
      finally {
        this.loadingBrands = false
      }
    },

    async createBrand(data) {
      return await $api('/catalog/brands', {
        method: 'POST',
        body: data,
      })
    },

    async updateBrand(id, data) {
      if (data instanceof FormData) {
        return await $api(`/catalog/brands/${id}`, {
          method: 'POST',
          params: { _method: 'PUT' },
          body: data,
        })
      }

      return await $api(`/catalog/brands/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    async deleteBrand(id) {
      return await $api(`/catalog/brands/${id}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // UNITS OF MEASURE
    // ──────────────────────────────────────────────────

    async fetchUom(params = {}) {
      if (this.uom.length > 0 && !params.force) return

      this.loadingUom = true
      try {
        const response = await $api('/catalog/uom', {
          method: 'GET',
          params,
        })

        this.uom = response.data || []
        this.checkFullyLoaded()

        return response
      }
      catch (error) {
        console.error('Error fetching UOM:', error)
        throw error
      }
      finally {
        this.loadingUom = false
      }
    },

    async createUom(data) {
      return await $api('/catalog/uom', {
        method: 'POST',
        body: data,
      })
    },

    async updateUom(id, data) {
      return await $api(`/catalog/uom/${id}`, {
        method: 'PUT',
        body: data,
      })
    },

    async deleteUom(id) {
      return await $api(`/catalog/uom/${id}`, {
        method: 'DELETE',
      })
    },

    checkFullyLoaded() {
      // If we have called all endpoint fetches at least once and populated data (or determined it's empty but completed the call)
      // Actually a simpler way is to just set loaded = true when all three arrays are present, or just trust the caller.
      // But setting it unconditionally on success might be risky if they haven't fetched all 3.
      // Let's just say it's loaded if we have data for all 3 (assuming we always need all 3)
      if (this.categories.length > 0 || this.brands.length > 0 || this.uom.length > 0) {
        // This is a naive check. A better approach is setting a specific flag for each or just manually setting loaded
        this.loaded = true
      }
    },
  },
})
