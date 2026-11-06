import { useClientStore } from '@/stores/useClientStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

const normalizeSiteParams = (params = {}) => {
  const normalized = {}

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue

    if (key === 'clientId' || key === 'client_id' || key === 'filter[client_id]') {
      normalized['filter[client_id]'] = value
    } else if (key === 'siteType' || key === 'site_type' || key === 'filter[site_type]') {
      normalized['filter[site_type]'] = value
    } else if (key === 'status' || key === 'filter[status]') {
      normalized['filter[status]'] = value
    } else if (key === 'riskClass' || key === 'risk_class' || key === 'filter[risk_class]') {
      normalized['filter[risk_class]'] = value
    } else {
      normalized[key] = value
    }
  }

  return normalized
}

const normalizeEquipmentParams = (params = {}) => {
  const normalized = {}

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue

    if (key === 'clientId' || key === 'client_id' || key === 'filter[client_id]') {
      normalized['filter[client_id]'] = value
    } else if (key === 'siteId' || key === 'site_id' || key === 'filter[site_id]') {
      normalized['filter[site_id]'] = value
    } else if (key === 'areaId' || key === 'area_id' || key === 'filter[area_id]') {
      normalized['filter[area_id]'] = value
    } else if (key === 'equipmentClassId' || key === 'equipment_class_id' || key === 'filter[equipment_class_id]') {
      normalized['filter[equipment_class_id]'] = value
    } else if (key === 'blueprintId' || key === 'blueprint_id' || key === 'filter[blueprint_id]') {
      normalized['filter[blueprint_id]'] = value
    } else if (key === 'brandId' || key === 'brand_id' || key === 'filter[brand_id]') {
      normalized['filter[brand_id]'] = value
    } else if (key === 'lifecycleStatus' || key === 'lifecycle_status' || key === 'filter[lifecycle_status]') {
      normalized['filter[lifecycle_status]'] = value
    } else if (key === 'complianceStatus' || key === 'compliance_status' || key === 'filter[compliance_status]') {
      normalized['filter[compliance_status]'] = value
    } else if (key === 'conditionStatus' || key === 'condition_status' || key === 'filter[condition_status]') {
      normalized['filter[condition_status]'] = value
    } else if (key === 'criticality' || key === 'filter[criticality]') {
      normalized['filter[criticality]'] = value
    } else {
      normalized[key] = value
    }
  }

  return normalized
}

export const useClientAssetStore = defineStore('clientAsset', {
  state: () => ({
    // ── Sites ───────────────────────────────────────────────────
    sites: [],
    totalSites: 0,
    site: null,
    sitesMeta: null,

    // ── Equipment ───────────────────────────────────────────────
    equipment: [],
    totalEquipment: 0,
    equipmentItem: null,
    equipmentMeta: null,

    // ── Equipment Classes & Blueprints Master ───────────────────
    equipmentClasses: [],
    blueprints: [],

    // ── Workwear Fabrics ────────────────────────────────────────
    workwearFabrics: [],
    totalWorkwearFabrics: 0,

    // ── Workwear Models ─────────────────────────────────────────
    workwearModels: [],
    totalWorkwearModels: 0,

    // ── Workwear Items ──────────────────────────────────────────
    workwearItems: [],
    totalWorkwearItems: 0,

    // ── Workwear Employees ──────────────────────────────────────
    workwearEmployees: [],
    totalWorkwearEmployees: 0,

    // ── Lookup Options Cache ────────────────────────────────────
    clientOptionsCache: null,
    supplierOptionsCache: null,

    // ── UI ──────────────────────────────────────────────────────
    loading: false,
  }),

  actions: {
    // ═══════════════════════════════════════════════════════════
    //  MASTER DATA: EQUIPMENT CLASSES & BLUEPRINTS
    // ═══════════════════════════════════════════════════════════

    async fetchEquipmentClasses(params = {}) {
      try {
        const r = await $api('/client-assets/equipment-classes', { method: 'GET', params: { itemsPerPage: -1, ...params } })

        this.equipmentClasses = r.data || []

        return r.data || []
      } catch (e) {
        console.error('Error fetching equipment classes:', e)
        throw e
      }
    },

    async fetchBlueprints(params = {}) {
      try {
        const r = await $api('/catalog/blueprints', { method: 'GET', params: { itemsPerPage: -1, ...params } })

        this.blueprints = r.data || []

        return r.data || []
      } catch (e) {
        console.error('Error fetching blueprints:', e)
        throw e
      }
    },

    async fetchBlueprint(id) {
      try {
        const r = await $api(`/catalog/blueprints/${id}`, { method: 'GET' })

        return r.data || null
      } catch (e) {
        console.error('Error fetching blueprint details:', e)
        throw e
      }
    },

    async fetchBrands(params = {}) {
      try {
        const r = await $api('/catalog/brands', { method: 'GET', params: { itemsPerPage: -1, ...params } })

        return r.data || []
      } catch (e) {
        console.error('Error fetching catalog brands:', e)

        return []
      }
    },

    // ═══════════════════════════════════════════════════════════
    //  CLIENT SITES
    // ═══════════════════════════════════════════════════════════

    async fetchSitesMeta() {
      try {
        const r = await $api('/client-assets/sites/meta', { method: 'GET' })

        this.sitesMeta = r

        return r
      } catch (e) {
        console.error('Error fetching sites meta:', e)

        return null
      }
    },

    async fetchSites(params = {}) {
      this.loading = true
      try {
        const cleanParams = normalizeSiteParams(params)
        const r = await $api('/client-assets/sites', { method: 'GET', params: cleanParams })

        this.sites = r.data || []
        this.totalSites = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching sites:', e)
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchSite(id) {
      const r = await $api(`/client-assets/sites/${id}`, { method: 'GET' })

      this.site = r.data

      return r.data
    },

    async createSite(data) {
      return await $api('/client-assets/sites', { method: 'POST', body: data })
    },

    async updateSite(id, data) {
      return await $api(`/client-assets/sites/${id}`, { method: 'PUT', body: data })
    },

    async deleteSite(id) {
      return await $api(`/client-assets/sites/${id}`, { method: 'DELETE' })
    },

    // ── Site Areas (Recursive Tree Hierarchy) ───────────────────
    async fetchSiteAreas(siteId, params = {}) {
      const r = await $api(`/client-assets/sites/${siteId}/areas`, { method: 'GET', params })

      const unwrapCollection = value => {
        if (!value) return []
        if (Array.isArray(value)) return value
        if (Array.isArray(value.data)) return value.data
        if (Array.isArray(value.flat_list)) return value.flat_list

        return []
      }

      const tree = unwrapCollection(r?.data ?? r?.tree)
      const flatFromApi = unwrapCollection(r?.flat_list ?? r?.flat)

      const flattenTree = (nodes, parentId = null, acc = []) => {
        unwrapCollection(nodes).forEach(node => {
          const children = node.children || node.all_children || node.allChildren || node.areas || []

          acc.push({
            ...node,
            // eslint-disable-next-line camelcase
            parent_area_id: node.parent_area_id ?? node.parent_id ?? parentId ?? null,
          })
          if (unwrapCollection(children).length) {
            flattenTree(children, node.id, acc)
          }
        })

        return acc
      }

      const flat = flatFromApi.length ? flatFromApi : flattenTree(tree)

      return {
        tree,
        flat,
        data: flat,
      }
    },

    async createSiteArea(siteId, data) {
      return await $api(`/client-assets/sites/${siteId}/areas`, { method: 'POST', body: data })
    },

    async updateSiteArea(areaId, data) {
      return await $api(`/client-assets/areas/${areaId}`, { method: 'PUT', body: data })
    },

    async deleteSiteArea(areaId) {
      return await $api(`/client-assets/areas/${areaId}`, { method: 'DELETE' })
    },

    // ── Site Media & Documents ─────────────────────────────────
    async uploadSiteMedia(siteId, formData) {
      return await $api(`/client-assets/sites/${siteId}/media`, {
        method: 'POST',
        body: formData,
      })
    },

    async uploadSitePhoto(siteId, file, options = {}) {
      const formData = new FormData()

      formData.append('collection', 'photos')
      formData.append('file', file)
      if (options.is_main) formData.append('is_main', '1')
      if (options.title) formData.append('title', options.title)

      return await $api(`/client-assets/sites/${siteId}/media`, {
        method: 'POST',
        body: formData,
      })
    },

    async uploadSiteDocument(siteId, file, options = {}) {
      const formData = new FormData()

      formData.append('collection', options.collection || 'documents')
      formData.append('file', file)
      if (options.title) formData.append('title', options.title)
      if (options.category) formData.append('category', options.category)
      if (options.notes) formData.append('notes', options.notes)

      return await $api(`/client-assets/sites/${siteId}/media`, {
        method: 'POST',
        body: formData,
      })
    },

    async setSiteMainPhoto(siteId, mediaId) {
      return await $api(`/client-assets/sites/${siteId}/media/${mediaId}/set-main`, {
        method: 'POST',
      })
    },

    async deleteSiteMedia(siteId, mediaId) {
      return await $api(`/client-assets/sites/${siteId}/media/${mediaId}`, {
        method: 'DELETE',
      })
    },

    async downloadSiteMedia(siteId, mediaId, filename = 'download') {
      const res = await $api(`/client-assets/sites/${siteId}/media/${mediaId}/download`, {
        method: 'GET',
        responseType: 'blob',
        headers: { Accept: '*/*' },
      })

      const blob = res instanceof Blob ? res : new Blob([res])
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')

      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      setTimeout(() => {
        window.URL.revokeObjectURL(url)
        if (a.parentNode) {
          a.parentNode.removeChild(a)
        }
      }, 1000)
    },

    // ═══════════════════════════════════════════════════════════
    //  CLIENT EQUIPMENT
    // ═══════════════════════════════════════════════════════════

    async fetchEquipmentMeta() {
      try {
        const r = await $api('/client-assets/equipment/meta', { method: 'GET' })

        this.equipmentMeta = r

        return r
      } catch (e) {
        console.error('Error fetching equipment meta:', e)

        return null
      }
    },

    async fetchEquipment(params = {}) {
      this.loading = true
      try {
        const cleanParams = normalizeEquipmentParams(params)
        const r = await $api('/client-assets/equipment', { method: 'GET', params: cleanParams })

        this.equipment = r.data || []
        this.totalEquipment = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching equipment:', e)
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchEquipmentItem(id) {
      const r = await $api(`/client-assets/equipment/${id}`, { method: 'GET' })

      this.equipmentItem = r.data

      return r.data
    },

    async createEquipment(data) {
      return await $api('/client-assets/equipment', { method: 'POST', body: data })
    },

    async updateEquipment(id, data) {
      return await $api(`/client-assets/equipment/${id}`, { method: 'PUT', body: data })
    },

    async deleteEquipment(id) {
      return await $api(`/client-assets/equipment/${id}`, { method: 'DELETE' })
    },

    async transferEquipment(id, data) {
      return await $api(`/client-assets/equipment/${id}/transfer`, { method: 'POST', body: data })
    },

    async replaceQrCode(id, data) {
      return await $api(`/client-assets/equipment/${id}/replace-qr`, { method: 'POST', body: data })
    },

    async downloadEquipmentQr(id) {
      const r = await $api(`/client-assets/equipment/${id}/qr/download`, { method: 'GET', responseType: 'blob' })
      const url = window.URL.createObjectURL(r)
      const a = document.createElement('a')

      a.href = url
      a.download = `qr-eq-${id}.png`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    },

    // ── Equipment Media & Documents ────────────────────────────
    async uploadEquipmentMedia(equipmentId, formData) {
      return await $api(`/client-assets/equipment/${equipmentId}/media`, {
        method: 'POST',
        body: formData,
      })
    },

    async setEquipmentMainPhoto(equipmentId, mediaId) {
      return await $api(`/client-assets/equipment/${equipmentId}/media/${mediaId}/set-main`, {
        method: 'POST',
      })
    },

    async deleteEquipmentMedia(equipmentId, mediaId) {
      return await $api(`/client-assets/equipment/${equipmentId}/media/${mediaId}`, {
        method: 'DELETE',
      })
    },

    async downloadEquipmentMedia(equipmentId, mediaId, filename = 'download') {
      const blob = await $api(`/client-assets/equipment/${equipmentId}/media/${mediaId}/download`, {
        method: 'GET',
        responseType: 'blob',
      })

      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')

      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    },

    // ═══════════════════════════════════════════════════════════
    //  WORKWEAR FABRICS (SETTINGS & LOOKUP)
    // ═══════════════════════════════════════════════════════════

    async fetchWorkwearFabrics(params = {}) {
      try {
        const r = await $api('/client-assets/workwear-fabrics', {
          method: 'GET',
          params: { 'per_page': 100, 'seed_if_empty': 1, ...params },
        })

        this.workwearFabrics = r.data || []
        this.totalWorkwearFabrics = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching workwear fabrics:', e)
        this.workwearFabrics = []
        this.totalWorkwearFabrics = 0

        return { data: [], total: 0 }
      }
    },

    async createWorkwearFabric(data) {
      return await $api('/client-assets/workwear-fabrics', { method: 'POST', body: data })
    },

    async updateWorkwearFabric(id, data) {
      return await $api(`/client-assets/workwear-fabrics/${id}`, { method: 'PUT', body: data })
    },

    async deleteWorkwearFabric(id) {
      return await $api(`/client-assets/workwear-fabrics/${id}`, { method: 'DELETE' })
    },

    async seedDefaultWorkwearFabrics() {
      const r = await $api('/client-assets/workwear-fabrics/seed-defaults', { method: 'POST' })

      this.workwearFabrics = r.data || []
      this.totalWorkwearFabrics = (r.data || []).length

      return r
    },

    async fetchPhysicalCatalogItems(params = {}) {
      const r = await $api('/catalog/items', {
        method: 'GET',
        params: {
          'filter[item_type]': 'product',
          'filter[product_type]': 'physical',
          'include': 'product,category,brand,preferredSupplier,suppliers,options.values,variants.optionValues',
          'per_page': 50,
          ...params,
        },
      })

      return r.data || []
    },

    async fetchCatalogItemDetails(id) {
      const r = await $api(`/catalog/items/${id}`, { method: 'GET' })

      return r.data || null
    },

    // ═══════════════════════════════════════════════════════════
    //  WORKWEAR MODELS
    // ═══════════════════════════════════════════════════════════

    async fetchWorkwearModels(params) {
      this.loading = true
      try {
        const r = await $api('/client-assets/workwear-models', { method: 'GET', params })

        this.workwearModels = r.data || []
        this.totalWorkwearModels = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching workwear models:', e)
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchWorkwearModel(id) {
      const r = await $api(`/client-assets/workwear-models/${id}`, { method: 'GET' })

      return r.data
    },

    async createWorkwearModel(data) {
      return await $api('/client-assets/workwear-models', { method: 'POST', body: data })
    },

    async updateWorkwearModel(id, data) {
      return await $api(`/client-assets/workwear-models/${id}`, { method: 'PUT', body: data })
    },

    async deleteWorkwearModel(id) {
      return await $api(`/client-assets/workwear-models/${id}`, { method: 'DELETE' })
    },

    async createModelVersion(id, data) {
      return await $api(`/client-assets/workwear-models/${id}/version`, { method: 'POST', body: data })
    },

    async addModelOption(modelId, data) {
      return await $api(`/client-assets/workwear-models/${modelId}/options`, { method: 'POST', body: data })
    },

    async updateModelOption(modelId, optionId, data) {
      return await $api(`/client-assets/workwear-models/${modelId}/options/${optionId}`, { method: 'PUT', body: data })
    },

    async deleteModelOption(modelId, optionId) {
      return await $api(`/client-assets/workwear-models/${modelId}/options/${optionId}`, { method: 'DELETE' })
    },

    async addModelOptionValue(modelId, optionId, data) {
      return await $api(`/client-assets/workwear-models/${modelId}/options/${optionId}/values`, { method: 'POST', body: data })
    },

    async deleteModelOptionValue(modelId, optionId, valueId) {
      return await $api(`/client-assets/workwear-models/${modelId}/options/${optionId}/values/${valueId}`, { method: 'DELETE' })
    },

    async uploadModelMedia(modelId, formData) {
      return await $api(`/client-assets/workwear-models/${modelId}/media`, { method: 'POST', body: formData })
    },

    async deleteModelMedia(modelId, mediaId) {
      return await $api(`/client-assets/workwear-models/${modelId}/media/${mediaId}`, { method: 'DELETE' })
    },

    async downloadModelMedia(modelId, mediaId, filename = 'download') {
      const res = await $api(`/client-assets/workwear-models/${modelId}/media/${mediaId}/download`, {
        method: 'GET',
        responseType: 'blob',
        headers: { Accept: '*/*' },
      })

      const blob = res instanceof Blob ? res : new Blob([res])
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')

      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      setTimeout(() => {
        window.URL.revokeObjectURL(url)
        if (a.parentNode) {
          a.parentNode.removeChild(a)
        }
      }, 1000)
    },

    // ═══════════════════════════════════════════════════════════
    //  WORKWEAR ITEMS & KPIS
    // ═══════════════════════════════════════════════════════════

    async fetchWorkwearKpis(params = {}) {
      return await $api('/client-assets/workwear-kpis', { method: 'GET', params })
    },

    async fetchWorkwearStock(params = {}) {
      const r = await $api('/client-assets/workwear-stock', { method: 'GET', params })

      return r.data || []
    },

    async uploadItemMedia(itemId, formData) {
      return await $api(`/client-assets/workwear-items/${itemId}/media`, { method: 'POST', body: formData })
    },

    async deleteItemMedia(itemId, mediaId) {
      return await $api(`/client-assets/workwear-items/${itemId}/media/${mediaId}`, { method: 'DELETE' })
    },

    async fetchWorkwearDocumentOptions(params) {
      const r = await $api('/client-assets/workwear-document-options', { method: 'GET', params })

      return r.data || { 'sales_orders': [], 'delivery_notes': [] }
    },

    async fetchWorkwearItems(params) {
      this.loading = true
      try {
        const r = await $api('/client-assets/workwear-items', { method: 'GET', params })

        this.workwearItems = r.data || []
        this.totalWorkwearItems = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching workwear items:', e)
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchWorkwearItem(id) {
      const r = await $api(`/client-assets/workwear-items/${id}`, { method: 'GET' })

      return r.data
    },

    async createWorkwearItem(data) {
      return await $api('/client-assets/workwear-items', { method: 'POST', body: data })
    },

    async batchCreateWorkwearItems(data) {
      return await $api('/client-assets/workwear-items/batch', { method: 'POST', body: data })
    },

    async updateWorkwearItem(id, data) {
      return await $api(`/client-assets/workwear-items/${id}`, { method: 'PUT', body: data })
    },

    async deleteWorkwearItem(id) {
      return await $api(`/client-assets/workwear-items/${id}`, { method: 'DELETE' })
    },

    async flagItemStolen(id, data) {
      return await $api(`/client-assets/workwear-items/${id}/flag-stolen`, { method: 'POST', body: data })
    },

    async flagStolenWorkwearItem(id, data) {
      return await this.flagItemStolen(id, data)
    },

    // ═══════════════════════════════════════════════════════════
    //  PARTNER OPTIONS LOOKUP CACHE
    // ═══════════════════════════════════════════════════════════

    /**
     * Cached client lookup options normalized for select/autocomplete:
     * [{ title: item.legal_name, value: item.id, raw: item }]
     */
    async fetchClientOptions(params = { limit: 150 }) {
      const isDefault = (!params || (params.limit === 150 && Object.keys(params).length === 1))
      if (isDefault && this.clientOptionsCache) {
        return this.clientOptionsCache
      }

      const clientStore = useClientStore()
      const normalized = await clientStore.fetchClientOptions(params)

      if (isDefault) {
        this.clientOptionsCache = normalized
      }

      return normalized
    },

    /**
     * Cached supplier lookup options normalized for select/autocomplete:
     * [{ title: item.legal_name, value: item.id, raw: item }]
     */
    async fetchSupplierOptions(params = { limit: 150 }) {
      const isDefault = (!params || (params.limit === 150 && Object.keys(params).length === 1))
      if (isDefault && this.supplierOptionsCache) {
        return this.supplierOptionsCache
      }

      const supplierStore = useSupplierStore()
      const normalized = await supplierStore.fetchSupplierOptions(params)

      if (isDefault) {
        this.supplierOptionsCache = normalized
      }

      return normalized
    },

    async assignItem(id, data) {
      return await $api('/client-assets/workwear-items/assign', { 
        method: 'POST', 
        body: { 
          ...data, 
          // eslint-disable-next-line camelcase
          workwear_item_id: id,
          // eslint-disable-next-line camelcase
          item_ids: data?.item_ids || [id],
        }, 
      })
    },

    async assignWorkwearItem(id, data) {
      return await this.assignItem(id, data)
    },

    async assignWorkwearItems(data) {
      return await $api('/client-assets/workwear-items/assign', { method: 'POST', body: data })
    },

    async returnAssignment(assignmentId, data) {
      return await $api(`/client-assets/assignments/${assignmentId}/return`, { method: 'POST', body: data })
    },

    async downloadItemQr(id) {
      const r = await $api(`/client-assets/workwear-items/${id}/qr/download`, { method: 'GET', responseType: 'blob' })
      const url = window.URL.createObjectURL(r)
      const a = document.createElement('a')

      a.href = url
      a.download = `qr-ww-${id}.png`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    },

    // ═══════════════════════════════════════════════════════════
    //  WORKWEAR EMPLOYEES
    // ═══════════════════════════════════════════════════════════

    async fetchWorkwearEmployees(params) {
      this.loading = true
      try {
        const r = await $api('/client-assets/workwear-employees', { method: 'GET', params })

        this.workwearEmployees = r.data || []
        this.totalWorkwearEmployees = r.total || 0

        return r
      } catch (e) {
        console.error('Error fetching employees:', e)
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchWorkwearEmployee(id) {
      const r = await $api(`/client-assets/workwear-employees/${id}`, { method: 'GET' })

      return r.data
    },

    async createWorkwearEmployee(data) {
      return await $api('/client-assets/workwear-employees', { method: 'POST', body: data })
    },

    async batchCreateWorkwearEmployees(data) {
      return await $api('/client-assets/workwear-employees/batch', { method: 'POST', body: data })
    },

    async updateWorkwearEmployee(id, data) {
      return await $api(`/client-assets/workwear-employees/${id}`, { method: 'PUT', body: data })
    },

    async deleteWorkwearEmployee(id) {
      return await $api(`/client-assets/workwear-employees/${id}`, { method: 'DELETE' })
    },

    async fetchEmployeeHistory(id) {
      const r = await $api(`/client-assets/workwear-employees/${id}/history`, { method: 'GET' })

      return r.data
    },
  },
})
