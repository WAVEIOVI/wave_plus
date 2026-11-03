import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Business Partners — Organisations.
 *
 * Full CRUD + polymorphic relation management:
 *   Contacts, Addresses, Bank Accounts, VAT Certificates, Documents
 *
 * Plus:
 *   Contact ↔ Address M2M linking
 *   Document upload (FormData)
 *   Account Manager assignment
 *   Users list for selects
 *
 * Organisations use SoftDeletes.
 *
 * All API calls go through the shared `$api` (ofetch) instance.
 */
export const useOrganisationStore = defineStore('organisation', {
  state: () => ({
    organisations: [],
    totalOrganisations: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // ORGANISATION CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch statistics / metadata
    async fetchMetaOrganisations() {
      return await $api('/bp/organisations/meta', { method: 'GET' })
    },

    // 👉 Fetch paginated/filtered organisation list
    async fetchOrganisationOptions(params = {}) {
      return await $api('/bp/organisations/options', {
        method: 'GET',
        params,
      })
    },

    async fetchOrganisations(params) {
      this.loading = true
      try {
        const response = await $api('/bp/organisations', {
          method: 'GET',
          params,
        })

        this.organisations = response.data || []
        this.totalOrganisations = response.total || response.meta?.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.organisations = []
          this.totalOrganisations = 0

          return
        }
        console.error('Error fetching organisations:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single organisation with all relations
    async fetchOrganisation(id) {
      try {
        const response = await $api(`/bp/organisations/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching organisation ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new organisation
    async createOrganisation(organisationData) {
      return await $api('/bp/organisations', {
        method: 'POST',
        body: organisationData,
      })
    },

    // 👉 Update an existing organisation (JSON payload)
    async updateOrganisation(id, organisationData) {
      return await $api(`/bp/organisations/${id}`, {
        method: 'PUT',
        body: organisationData,
      })
    },

    async uploadOrganisationLogo(id, file) {
      const formData = new FormData()

      formData.append('logo', file)
      formData.append('_method', 'PUT')

      return await $api(`/bp/organisations/${id}`, {
        method: 'POST',
        body: formData,
      })
    },

    // 👉 Delete an organisation (HARD delete — permanent)
    async deleteOrganisation(id) {
      return await $api(`/bp/organisations/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Export Organisations List PDF
    async exportOrganisationsPdf() {
      try {
        const response = await $api('/bp/organisations/export/pdf', {
          method: 'GET',
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = 'organisations-list.pdf'
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting organisations PDF:', error)
        throw error
      }
    },

    // 👉 Export Single Organisation Record PDF
    async exportOrganisationPdf(id, filename = 'organisation.pdf') {
      try {
        const response = await $api(`/bp/organisations/${id}/export/pdf`, {
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
        console.error('Error exporting organisation PDF:', error)
        throw error
      }
    },

    // 👉 Send Organisation List PDF via Email
    async sendOrganisationListEmail(payload) {
      return await $api('/bp/organisations/send-email', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Send Single Organisation Record PDF via Email
    async sendOrganisationSingleEmail(id, payload) {
      return await $api(`/bp/organisations/${id}/send-email`, {
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

    async fetchOrganisationContacts(organisationId) {
      return await $api(`/bp/organisations/${organisationId}/contacts`, { method: 'GET' })
    },

    async addOrganisationContact(organisationId, contactData) {
      return await $api(`/bp/organisations/${organisationId}/contacts`, {
        method: 'POST',
        body: contactData,
      })
    },

    async updateOrganisationContact(organisationId, contactId, contactData) {
      return await $api(`/bp/contacts/${contactId}`, {
        method: 'PUT',
        body: contactData,
      })
    },

    async deleteOrganisationContact(organisationId, contactId) {
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

    async fetchOrganisationAddresses(organisationId) {
      return await $api(`/bp/organisations/${organisationId}/addresses`, { method: 'GET' })
    },

    async addOrganisationAddress(organisationId, addressData) {
      return await $api(`/bp/organisations/${organisationId}/addresses`, {
        method: 'POST',
        body: addressData,
      })
    },

    async updateOrganisationAddress(organisationId, addressId, addressData) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'PUT',
        body: addressData,
      })
    },

    async deleteOrganisationAddress(organisationId, addressId) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // BANK ACCOUNTS (polymorphic via bp_bank_accounts)
    // ──────────────────────────────────────────────────

    async fetchOrganisationBankAccounts(organisationId) {
      return await $api(`/bp/organisations/${organisationId}/bank-accounts`, { method: 'GET' })
    },

    async addOrganisationBankAccount(organisationId, bankData) {
      return await $api(`/bp/organisations/${organisationId}/bank-accounts`, {
        method: 'POST',
        body: bankData,
      })
    },

    async updateOrganisationBankAccount(organisationId, bankId, bankData) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'PUT',
        body: bankData,
      })
    },

    async deleteOrganisationBankAccount(organisationId, bankId) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // VAT CERTIFICATES (polymorphic via bp_vat_certificates)
    // ──────────────────────────────────────────────────

    async fetchOrganisationVatCerts(organisationId) {
      return await $api(`/bp/organisations/${organisationId}/vat-certificates`, { method: 'GET' })
    },

    async addOrganisationVatCert(organisationId, certData) {
      return await $api(`/bp/organisations/${organisationId}/vat-certificates`, {
        method: 'POST',
        body: certData,
      })
    },

    async updateOrganisationVatCert(organisationId, certId, certData) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'PUT',
        body: certData,
      })
    },

    async deleteOrganisationVatCert(organisationId, certId) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // DOCUMENTS (polymorphic via bp_documents)
    // ──────────────────────────────────────────────────

    async fetchOrganisationDocuments(organisationId) {
      return await $api(`/bp/organisations/${organisationId}/documents`, { method: 'GET' })
    },

    // 👉 Add document with optional file upload (FormData)
    async addOrganisationDocument(organisationId, docData, file = null) {
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

        return await $api(`/bp/organisations/${organisationId}/documents`, {
          method: 'POST',
          body: formData,
        })
      }

      // JSON payload — convert boolean for consistency
      const payload = { ...docData }
      if ('auto_renewable' in payload) {
        payload['auto_renewable'] = payload['auto_renewable'] ? true : false
      }

      return await $api(`/bp/organisations/${organisationId}/documents`, {
        method: 'POST',
        body: payload,
      })
    },

    async updateOrganisationDocument(organisationId, docId, docData, file = null) {
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

    async deleteOrganisationDocument(organisationId, docId) {
      return await $api(`/bp/documents/${docId}`, {
        method: 'DELETE',
      })
    },

    // 👉 Download document securely (passes Auth tokens via $api)
    async downloadOrganisationDocument(downloadUrl, fallbackFileName = 'document') {
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
