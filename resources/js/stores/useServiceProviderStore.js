import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Business Partners — ServiceProviders.
 *
 * Full CRUD + polymorphic relation management:
 *   Contacts, Addresses, Bank Accounts, VAT Certificates, Documents
 *
 * Plus:
 *   Contact ↔ Address M2M linking
 *   Logo upload (FormData)
 *   Document upload (FormData)
 *   Account Manager assignment
 *   Users list for selects
 *
 * All API calls go through the shared `$api` (ofetch) instance.
 */
export const useServiceProviderStore = defineStore('serviceProvider', {
  state: () => ({
    serviceProviders: [],
    totalServiceProviders: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // Service Providers CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch statistics / metadata
    async fetchMetaServiceProviders() {
      return await $api('/bp/service-providers/meta', { method: 'GET' })
    },

    // 👉 Fetch paginated/filtered service-provider list
    async fetchServiceProviderOptions(params = {}) {
      return await $api('/bp/service-providers/options', {
        method: 'GET',
        params,
      })
    },

    async fetchServiceProviders(params) {
      this.loading = true
      try {
        const response = await $api('/bp/service-providers', {
          method: 'GET',
          params,
        })

        this.serviceProviders = response.data || []
        this.totalServiceProviders = response.total || response.meta?.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.serviceProviders = []
          this.totalServiceProviders = 0

          return
        }
        console.error('Error fetching service providers:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single service provider with all relations
    async fetchServiceProvider(id) {
      try {
        const response = await $api(`/bp/service-providers/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching service provider ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new service-provider
    async createServiceProvider(serviceProviderData) {
      return await $api('/bp/service-providers', {
        method: 'POST',
        body: serviceProviderData,
      })
    },

    // 👉 Update an existing service provider (JSON payload)
    async updateServiceProvider(id, serviceProviderData) {
      return await $api(`/bp/service-providers/${id}`, {
        method: 'PUT',
        body: serviceProviderData,
      })
    },

    // 👉 Upload serviceProvider logo (FormData)
    async uploadServiceProviderLogo(id, file) {
      const formData = new FormData()

      formData.append('logo', file)
      formData.append('_method', 'PUT')

      return await $api(`/bp/service-providers/${id}`, {
        method: 'POST',
        body: formData,
      })
    },

    // 👉 Delete a service-provider (soft-delete on backend)
    async deleteServiceProvider(id) {
      return await $api(`/bp/service-providers/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Export ServiceProviders List PDF
    async exportServiceProvidersPdf() {
      try {
        const response = await $api('/bp/service-providers/export/pdf', {
          method: 'GET',
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = 'service-providers-list.pdf'
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting service providers PDF:', error)
        throw error
      }
    },

    // 👉 Export Single ServiceProvider Record PDF
    async exportServiceProviderPdf(id, filename = 'service-provider.pdf') {
      try {
        const response = await $api(`/bp/service-providers/${id}/export/pdf`, {
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
        console.error('Error exporting service provider PDF:', error)
        throw error
      }
    },

    // 👉 Send ServiceProvider List PDF via Email
    async sendServiceProviderListEmail(payload) {
      return await $api('/bp/service-providers/send-email', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Send Single ServiceProvider Record PDF via Email
    async sendServiceProviderSingleEmail(id, payload) {
      return await $api(`/bp/service-providers/${id}/send-email`, {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Search Emails (Global)
    async searchEmails(query) {
      return await $api('/bp/emails/search', {
        method: 'GET',
        query: { q: query },
      })
    },

    // ──────────────────────────────────────────────────
    // USERS LIST (for Account Manager select)
    // ──────────────────────────────────────────────────

    async fetchUsers() {
      try {
        const response = await $api('/admin/users', {
          method: 'GET',
          params: { itemsPerPage: -1 },
        })

        return response.data || []
      }
      catch (err) {
        console.error('Error fetching users:', err)

        return []
      }
    },

    // ──────────────────────────────────────────────────
    // CONTACTS (polymorphic via bp_contacts)
    // ──────────────────────────────────────────────────

    async fetchServiceProviderContacts(serviceProviderId) {
      return await $api(`/bp/service-providers/${serviceProviderId}/contacts`, { method: 'GET' })
    },

    async addServiceProviderContact(serviceProviderId, contactData) {
      return await $api(`/bp/service-providers/${serviceProviderId}/contacts`, {
        method: 'POST',
        body: contactData,
      })
    },

    async updateServiceProviderContact(serviceProviderId, contactId, contactData) {
      return await $api(`/bp/contacts/${contactId}`, {
        method: 'PUT',
        body: contactData,
      })
    },

    async deleteServiceProviderContact(serviceProviderId, contactId) {
      return await $api(`/bp/contacts/${contactId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // CONTACT ↔ ADDRESS M2M LINKING (bp_contact_address)
    // ──────────────────────────────────────────────────

    // 👉 Link a contact to an address
    async linkContactToAddress(contactId, addressId, isPrimaryLocation = false) {
      return await $api(`/bp/contacts/${contactId}/addresses`, {
        method: 'POST',
        body: {
          'bp_contact_id': contactId,
          'bp_address_id': addressId,
          'is_primary_location': isPrimaryLocation,
        },
      })
    },

    // 👉 Unlink a contact from an address
    async unlinkContactFromAddress(contactId, addressId) {
      return await $api(`/bp/contacts/${contactId}/addresses/${addressId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // ADDRESSES (polymorphic via bp_addresses)
    // ──────────────────────────────────────────────────

    async fetchServiceProviderAddresses(serviceProviderId) {
      return await $api(`/bp/service-providers/${serviceProviderId}/addresses`, { method: 'GET' })
    },

    async addServiceProviderAddress(serviceProviderId, addressData) {
      return await $api(`/bp/service-providers/${serviceProviderId}/addresses`, {
        method: 'POST',
        body: addressData,
      })
    },

    async updateServiceProviderAddress(serviceProviderId, addressId, addressData) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'PUT',
        body: addressData,
      })
    },

    async deleteServiceProviderAddress(serviceProviderId, addressId) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // BANK ACCOUNTS (polymorphic via bp_bank_accounts)
    // ──────────────────────────────────────────────────

    async fetchServiceProviderBankAccounts(serviceProviderId) {
      return await $api(`/bp/service-providers/${serviceProviderId}/bank-accounts`, { method: 'GET' })
    },

    async addServiceProviderBankAccount(serviceProviderId, bankData) {
      return await $api(`/bp/service-providers/${serviceProviderId}/bank-accounts`, {
        method: 'POST',
        body: bankData,
      })
    },

    async updateServiceProviderBankAccount(serviceProviderId, bankId, bankData) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'PUT',
        body: bankData,
      })
    },

    async deleteServiceProviderBankAccount(serviceProviderId, bankId) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // VAT CERTIFICATES (polymorphic via bp_vat_certificates)
    // ──────────────────────────────────────────────────

    async fetchServiceProviderVatCerts(serviceProviderId) {
      return await $api(`/bp/service-providers/${serviceProviderId}/vat-certificates`, { method: 'GET' })
    },

    async addServiceProviderVatCert(serviceProviderId, certData) {
      return await $api(`/bp/service-providers/${serviceProviderId}/vat-certificates`, {
        method: 'POST',
        body: certData,
      })
    },

    async updateServiceProviderVatCert(serviceProviderId, certId, certData) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'PUT',
        body: certData,
      })
    },

    async deleteServiceProviderVatCert(serviceProviderId, certId) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // DOCUMENTS (polymorphic via bp_documents)
    // ──────────────────────────────────────────────────

    async fetchServiceProviderDocuments(serviceProviderId) {
      return await $api(`/bp/service-providers/${serviceProviderId}/documents`, { method: 'GET' })
    },

    // 👉 Add document with optional file upload (FormData)
    async addServiceProviderDocument(serviceProviderId, docData, file = null) {
      if (file) {
        const formData = new FormData()

        Object.entries(docData).forEach(([key, value]) => {
          if (value === null || value === undefined) return
          if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0')
          }
          else if (typeof value === 'object') {
            formData.append(key, JSON.stringify(value))
          }
          else {
            formData.append(key, value)
          }
        })


        // Fix Vuetify VFileInput returning an array
        const actualFile = Array.isArray(file) ? file[0] : file

        formData.append('file', actualFile)

        return await $api(`/bp/service-providers/${serviceProviderId}/documents`, {
          method: 'POST',
          body: formData,
        })
      }

      // JSON payload — convert boolean for consistency
      const payload = { ...docData }
      if ('auto_renewable' in payload) {
        payload['auto_renewable'] = payload['auto_renewable'] ? true : false
      }

      return await $api(`/bp/service-providers/${serviceProviderId}/documents`, {
        method: 'POST',
        body: payload,
      })
    },

    async updateServiceProviderDocument(serviceProviderId, docId, docData, file = null) {
      if (file) {
        const formData = new FormData()

        Object.entries(docData).forEach(([key, value]) => {
          if (value === null || value === undefined) return
          if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0')
          }
          else if (typeof value === 'object') {
            formData.append(key, JSON.stringify(value))
          }
          else {
            formData.append(key, value)
          }
        })

        // Fake PUT for Laravel FormData handling
        formData.append('_method', 'PUT')

        // Fix Vuetify VFileInput returning an array
        const actualFile = Array.isArray(file) ? file[0] : file

        formData.append('file', actualFile)

        return await $api(`/bp/documents/${docId}`, {
          method: 'POST', // POST with _method=PUT is required
          body: formData,
        })
      }

      // JSON payload
      const payload = { ...docData }
      if ('auto_renewable' in payload) {
        payload['auto_renewable'] = payload['auto_renewable'] ? true : false
      }

      return await $api(`/bp/documents/${docId}`, {
        method: 'PUT',
        body: payload,
      })
    },

    async deleteServiceProviderDocument(serviceProviderId, docId) {
      return await $api(`/bp/documents/${docId}`, {
        method: 'DELETE',
      })
    },

    // 👉 Download document securely (passes Auth tokens via $api)
    async downloadServiceProviderDocument(downloadUrl, fallbackFileName = 'document') {
      try {
        const response = await $api(downloadUrl, {
          method: 'GET',
          responseType: 'blob', // Request raw binary data
        })

        // Create a blob URL and trigger a virtual click
        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')
        
        link.style.display = 'none'
        link.href = blobUrl
        link.download = fallbackFileName
        document.body.appendChild(link)
        link.click()
        
        // Clean up
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error securely downloading document:', error)
        throw error
      }
    },
  },
})
