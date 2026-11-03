import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Catalog Items.
 *
 * Core CRUD + Options/Variants sub-resource management.
 * All API calls go through the shared `$api` (ofetch) instance.
 */
export const useCatalogItemStore = defineStore('catalogItem', {
  state: () => ({
    items: [],
    totalItems: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // CATALOG ITEM CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch statistics / metadata
    async fetchMeta() {
      return await $api('/catalog/items/meta', { method: 'GET' })
    },

    // 👉 Fetch lightweight item lookup for forms (capped)
    async fetchItemOptions(params = {}) {
      return await $api('/catalog/items/options', {
        method: 'GET',
        params,
      })
    },

    // 👉 Fetch paginated/filtered item list
    async fetchItems(params) {
      this.loading = true
      try {
        const response = await $api('/catalog/items', {
          method: 'GET',
          params,
        })

        this.items = response.data || []
        this.totalItems = response.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.items = []
          this.totalItems = 0

          return
        }
        console.error('Error fetching catalog items:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single item with all relations
    async fetchItem(id) {
      try {
        const response = await $api(`/catalog/items/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching catalog item ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new catalog item (with nested extension data)
    async createItem(itemData) {
      return await $api('/catalog/items', {
        method: 'POST',
        body: itemData,
      })
    },

    // 👉 Update an existing catalog item
    async updateItem(id, itemData) {
      return await $api(`/catalog/items/${id}`, {
        method: 'PUT',
        body: itemData,
      })
    },

    // 👉 Delete (soft-delete) a catalog item
    async deleteItem(id) {
      return await $api(`/catalog/items/${id}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // MEDIA
    // ──────────────────────────────────────────────────

    async uploadMedia(itemId, formData) {
      return await $api(`/catalog/items/${itemId}/media`, {
        method: 'POST',
        body: formData,
      })
    },

    async deleteMedia(itemId, mediaId) {
      return await $api(`/catalog/items/${itemId}/media/${mediaId}`, {
        method: 'DELETE',
      })
    },

    async downloadMedia(url, filename) {
      try {
        const response = await $api(url, {
          method: 'GET',
          responseType: 'blob',
        })

        // Since $api (ofetch) might automatically parse response, 
        // we might prefer to get the blob directly.
        // ofetch actually handles blobs if you use it correctly or use native fetch.
        // Let's use $api.raw or just fix the headers in fetch.
        // Actually, $api is already configured with correct base and auth.
        
        const blob = response instanceof Blob ? response : new Blob([response])
        const downloadUrl = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        
        a.style.display = 'none'
        a.href = downloadUrl
        a.download = filename || 'download'
        document.body.appendChild(a)
        a.click()
        
        window.URL.revokeObjectURL(downloadUrl)
        document.body.removeChild(a)
      }
      catch (error) {
        console.error('Error downloading media:', error)
        throw error
      }
    },

    // ──────────────────────────────────────────────────
    // OPTIONS
    // ──────────────────────────────────────────────────

    async fetchOptions(catalogItemId) {
      return await $api(`/catalog/items/${catalogItemId}/options`, { method: 'GET' })
    },

    async createOption(optionData) {
      return await $api('/catalog/options', {
        method: 'POST',
        body: optionData,
      })
    },

    async updateOption(optionId, optionData) {
      return await $api(`/catalog/options/${optionId}`, {
        method: 'PUT',
        body: optionData,
      })
    },

    async deleteOption(optionId) {
      return await $api(`/catalog/options/${optionId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // VARIANTS
    // ──────────────────────────────────────────────────

    async fetchVariants(catalogItemId) {
      return await $api(`/catalog/items/${catalogItemId}/variants`, { method: 'GET' })
    },

    async createVariant(variantData) {
      return await $api('/catalog/variants', {
        method: 'POST',
        body: variantData,
      })
    },

    async updateVariant(variantId, variantData) {
      return await $api(`/catalog/variants/${variantId}`, {
        method: 'PUT',
        body: variantData,
      })
    },

    async deleteVariant(variantId) {
      return await $api(`/catalog/variants/${variantId}`, {
        method: 'DELETE',
      })
    },

    async generateVariants(itemId, variants) {
      return await $api(`/catalog/items/${itemId}/variants/generate`, {
        method: 'POST',
        body: { variants },
      })
    },

    // ──────────────────────────────────────────────────
    // PDF EXPORT & EMAIL
    // ──────────────────────────────────────────────────

    // 👉 Export Catalog Items List PDF
    async exportCatalogItemsPdf() {
      try {
        const response = await $api('/catalog/items/export/pdf', {
          method: 'GET',
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = 'catalog-items-list.pdf'
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting catalog items PDF:', error)
        throw error
      }
    },

    // 👉 Export Single Catalog Item Record PDF
    async exportCatalogItemPdf(id, filename = 'catalog-item.pdf') {
      try {
        const response = await $api(`/catalog/items/${id}/export/pdf`, {
          method: 'GET',
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = filename
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting catalog item PDF:', error)
        throw error
      }
    },

    // 👉 Send Catalog Items List PDF via Email
    async sendCatalogItemsListEmail(payload) {
      return await $api('/catalog/items/send-email', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Send Single Catalog Item Record PDF via Email
    async sendCatalogItemSingleEmail(id, payload) {
      return await $api(`/catalog/items/${id}/send-email`, {
        method: 'POST',
        body: payload,
      })
    },
  },
})
