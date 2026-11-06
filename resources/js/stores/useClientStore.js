import { normalizeExportIds } from '@/composables/shared/exportFormats'
import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Business Partners — Clients.
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
export const useClientStore = defineStore('client', {
  state: () => ({
    clients: [],
    totalClients: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // CLIENT CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch statistics / metadata
    async fetchMetaClients() {
      return await $api('/bp/clients/meta', { method: 'GET' })
    },

    // 👉 Lightweight lookup for other modules
    async fetchClientOptions(params = { limit: 150 }) {
      const res = await $api('/bp/clients/options', {
        method: 'GET',
        params,
      })

      const rawList = res?.data || (Array.isArray(res) ? res : [])

      const normalized = rawList.map(item => ({
        title: item.legal_name || item.commercial_name || item.name || `Client #${item.id}`,
        value: item.id,
        raw: item,
        name: item.legal_name,
        // eslint-disable-next-line camelcase
        company_name: item.commercial_name,
        ...item,
      }))

      normalized.data = normalized

      return normalized
    },

    // 👉 Fetch paginated/filtered client list
    async fetchClients(params, options = {}) {
      this.loading = true
      try {
        const response = await $api('/bp/clients', {
          method: 'GET',
          params,
          signal: options?.signal,
        })

        this.clients = response.data || []
        this.totalClients = response.total || response.meta?.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.clients = []
          this.totalClients = 0

          return
        }
        console.error('Error fetching clients:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single client with all relations
    async fetchClient(id) {
      try {
        const response = await $api(`/bp/clients/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching client ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new client
    async createClient(clientData) {
      return await $api('/bp/clients', {
        method: 'POST',
        body: clientData,
      })
    },

    // 👉 Update an existing client (JSON payload)
    async updateClient(id, clientData) {
      return await $api(`/bp/clients/${id}`, {
        method: 'PUT',
        body: clientData,
      })
    },

    // 👉 Upload client logo (FormData)
    async uploadClientLogo(id, file) {
      const formData = new FormData()

      formData.append('logo', file)
      formData.append('_method', 'PUT')

      return await $api(`/bp/clients/${id}`, {
        method: 'POST',
        body: formData,
      })
    },

    // 👉 Delete a client (soft-delete on backend)
    async deleteClient(id) {
      return await $api(`/bp/clients/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Export Selected Clients List PDF
    async exportClientsPdf(ids = []) {
      const normalizedIds = normalizeExportIds(ids)
      if (normalizedIds.length === 0)
        throw new Error('Please select at least one client to export.')

      try {
        const response = await $api('/bp/clients/export/pdf', {
          method: 'POST',
          body: { ids: normalizedIds },
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = 'clients-list.pdf'
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting clients PDF:', error)
        throw error
      }
    },

    // 👉 Export Selected Clients List CSV
    async exportClientsCsv(ids = []) {
      const normalizedIds = normalizeExportIds(ids)
      if (normalizedIds.length === 0)
        throw new Error('Please select at least one client to export.')

      try {
        const response = await $api('/bp/clients/export/csv', {
          method: 'POST',
          body: { ids: normalizedIds },
          responseType: 'blob',
        })

        const dateStamp = new Date().toISOString().slice(0, 10)
        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = `clients-${dateStamp}.csv`
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting clients CSV:', error)
        throw error
      }
    },

    // 👉 Export Selected Clients by Format ('pdf' | 'csv')
    async exportClients({ ids = [], format = 'pdf' } = {}) {
      const normalizedFormat = String(format || 'pdf').trim().toLowerCase()
      if (normalizedFormat === 'csv')
        return await this.exportClientsCsv(ids)

      return await this.exportClientsPdf(ids)
    },

    // 👉 Export Single Client Record PDF
    async exportClientPdf(id, filename = 'client.pdf') {
      try {
        const response = await $api(`/bp/clients/${id}/export/pdf`, {
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
        console.error('Error exporting client PDF:', error)
        throw error
      }
    },

    // 👉 Send Client List PDF via Email
    async sendClientListEmail(payload) {
      return await $api('/bp/clients/send-email', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Send Single Client Record PDF via Email
    async sendClientSingleEmail(id, payload) {
      return await $api(`/bp/clients/${id}/send-email`, {
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

    async fetchClientContacts(clientId) {
      return await $api(`/bp/clients/${clientId}/contacts`, { method: 'GET' })
    },

    async addClientContact(clientId, contactData) {
      return await $api(`/bp/clients/${clientId}/contacts`, {
        method: 'POST',
        body: contactData,
      })
    },

    async updateClientContact(clientId, contactId, contactData) {
      return await $api(`/bp/contacts/${contactId}`, {
        method: 'PUT',
        body: contactData,
      })
    },

    async deleteClientContact(clientId, contactId) {
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

    async fetchClientAddresses(clientId) {
      return await $api(`/bp/clients/${clientId}/addresses`, { method: 'GET' })
    },

    async addClientAddress(clientId, addressData) {
      return await $api(`/bp/clients/${clientId}/addresses`, {
        method: 'POST',
        body: addressData,
      })
    },

    async updateClientAddress(clientId, addressId, addressData) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'PUT',
        body: addressData,
      })
    },

    async deleteClientAddress(clientId, addressId) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // BANK ACCOUNTS (polymorphic via bp_bank_accounts)
    // ──────────────────────────────────────────────────

    async fetchClientBankAccounts(clientId) {
      return await $api(`/bp/clients/${clientId}/bank-accounts`, { method: 'GET' })
    },

    async addClientBankAccount(clientId, bankData) {
      return await $api(`/bp/clients/${clientId}/bank-accounts`, {
        method: 'POST',
        body: bankData,
      })
    },

    async updateClientBankAccount(clientId, bankId, bankData) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'PUT',
        body: bankData,
      })
    },

    async deleteClientBankAccount(clientId, bankId) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // VAT CERTIFICATES (polymorphic via bp_vat_certificates)
    // ──────────────────────────────────────────────────

    async fetchClientVatCerts(clientId) {
      return await $api(`/bp/clients/${clientId}/vat-certificates`, { method: 'GET' })
    },

    async addClientVatCert(clientId, certData) {
      return await $api(`/bp/clients/${clientId}/vat-certificates`, {
        method: 'POST',
        body: certData,
      })
    },

    async updateClientVatCert(clientId, certId, certData) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'PUT',
        body: certData,
      })
    },

    async deleteClientVatCert(clientId, certId) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // DOCUMENTS (polymorphic via bp_documents)
    // ──────────────────────────────────────────────────

    async fetchClientDocuments(clientId) {
      return await $api(`/bp/clients/${clientId}/documents`, { method: 'GET' })
    },

    // 👉 Add document with optional file upload (FormData)
    async addClientDocument(clientId, docData, file = null) {
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

        return await $api(`/bp/clients/${clientId}/documents`, {
          method: 'POST',
          body: formData,
        })
      }

      // JSON payload — convert boolean for consistency
      const payload = { ...docData }
      if ('auto_renewable' in payload) {
        payload['auto_renewable'] = payload['auto_renewable'] ? true : false
      }

      return await $api(`/bp/clients/${clientId}/documents`, {
        method: 'POST',
        body: payload,
      })
    },

    async updateClientDocument(clientId, docId, docData, file = null) {
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

    async deleteClientDocument(clientId, docId) {
      return await $api(`/bp/documents/${docId}`, {
        method: 'DELETE',
      })
    },

    // 👉 Download document securely (passes Auth tokens via $api)
    async downloadClientDocument(downloadUrl, fallbackFileName = 'document') {
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
