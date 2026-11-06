import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

/**
 * Pinia store for Business Partners — Suppliers.
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
export const useSupplierStore = defineStore('supplier', {
  state: () => ({
    suppliers: [],
    totalSuppliers: 0,
    loading: false,
  }),

  actions: {
    // ──────────────────────────────────────────────────
    // CLIENT CORE CRUD
    // ──────────────────────────────────────────────────

    // 👉 Fetch statistics / metadata
    async fetchMetaSuppliers() {
      return await $api('/bp/suppliers/meta', { method: 'GET' })
    },

    // 👉 Fetch paginated/filtered supplier list
    async fetchSupplierOptions(params = { limit: 150 }) {
      const res = await $api('/bp/suppliers/options', {
        method: 'GET',
        params,
      })

      const rawList = res?.data || (Array.isArray(res) ? res : [])

      const normalized = rawList.map(item => ({
        title: item.legal_name || item.name || `Supplier #${item.id}`,
        value: item.id,
        raw: item,
        name: item.legal_name,
        ...item,
      }))

      normalized.data = normalized

      return normalized
    },

    async fetchSuppliers(params) {
      this.loading = true
      try {
        const response = await $api('/bp/suppliers', {
          method: 'GET',
          params,
        })

        this.suppliers = response.data || []
        this.totalSuppliers = response.total || response.meta?.total || 0

        return response
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.suppliers = []
          this.totalSuppliers = 0

          return
        }
        console.error('Error fetching suppliers:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch single supplier with all relations
    async fetchSupplier(id) {
      try {
        const response = await $api(`/bp/suppliers/${id}`, { method: 'GET' })

        return response.data
      }
      catch (error) {
        console.error(`Error fetching supplier ${id}:`, error)
        throw error
      }
    },

    // 👉 Create a new supplier
    async createSupplier(supplierData) {
      return await $api('/bp/suppliers', {
        method: 'POST',
        body: supplierData,
      })
    },

    // 👉 Update an existing supplier (JSON payload)
    async updateSupplier(id, supplierData) {
      return await $api(`/bp/suppliers/${id}`, {
        method: 'PUT',
        body: supplierData,
      })
    },

    // 👉 Upload supplier logo (FormData)
    async uploadSupplierLogo(id, file) {
      const formData = new FormData()

      formData.append('logo', file)
      formData.append('_method', 'PUT')

      return await $api(`/bp/suppliers/${id}`, {
        method: 'POST',
        body: formData,
      })
    },

    // 👉 Delete a supplier (soft-delete on backend)
    async deleteSupplier(id) {
      return await $api(`/bp/suppliers/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Export Suppliers List PDF
    async exportSuppliersPdf() {
      try {
        const response = await $api('/bp/suppliers/export/pdf', {
          method: 'GET',
          responseType: 'blob',
        })

        const blobUrl = window.URL.createObjectURL(response)
        const link = document.createElement('a')

        link.style.display = 'none'
        link.href = blobUrl
        link.download = 'suppliers-list.pdf'
        document.body.appendChild(link)
        link.click()
        window.URL.revokeObjectURL(blobUrl)
        document.body.removeChild(link)
      } catch (error) {
        console.error('Error exporting suppliers PDF:', error)
        throw error
      }
    },

    // 👉 Export Single Supplier Record PDF
    async exportSupplierPdf(id, filename = 'supplier.pdf') {
      try {
        const response = await $api(`/bp/suppliers/${id}/export/pdf`, {
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
        console.error('Error exporting supplier PDF:', error)
        throw error
      }
    },

    // 👉 Send Supplier List PDF via Email
    async sendSupplierListEmail(payload) {
      return await $api('/bp/suppliers/send-email', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Send Single Supplier Record PDF via Email
    async sendSupplierSingleEmail(id, payload) {
      return await $api(`/bp/suppliers/${id}/send-email`, {
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

    async fetchSupplierContacts(supplierId) {
      return await $api(`/bp/suppliers/${supplierId}/contacts`, { method: 'GET' })
    },

    async addSupplierContact(supplierId, contactData) {
      return await $api(`/bp/suppliers/${supplierId}/contacts`, {
        method: 'POST',
        body: contactData,
      })
    },

    async updateSupplierContact(supplierId, contactId, contactData) {
      return await $api(`/bp/contacts/${contactId}`, {
        method: 'PUT',
        body: contactData,
      })
    },

    async deleteSupplierContact(supplierId, contactId) {
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

    async fetchSupplierAddresses(supplierId) {
      return await $api(`/bp/suppliers/${supplierId}/addresses`, { method: 'GET' })
    },

    async addSupplierAddress(supplierId, addressData) {
      return await $api(`/bp/suppliers/${supplierId}/addresses`, {
        method: 'POST',
        body: addressData,
      })
    },

    async updateSupplierAddress(supplierId, addressId, addressData) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'PUT',
        body: addressData,
      })
    },

    async deleteSupplierAddress(supplierId, addressId) {
      return await $api(`/bp/addresses/${addressId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // BANK ACCOUNTS (polymorphic via bp_bank_accounts)
    // ──────────────────────────────────────────────────

    async fetchSupplierBankAccounts(supplierId) {
      return await $api(`/bp/suppliers/${supplierId}/bank-accounts`, { method: 'GET' })
    },

    async addSupplierBankAccount(supplierId, bankData) {
      return await $api(`/bp/suppliers/${supplierId}/bank-accounts`, {
        method: 'POST',
        body: bankData,
      })
    },

    async updateSupplierBankAccount(supplierId, bankId, bankData) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'PUT',
        body: bankData,
      })
    },

    async deleteSupplierBankAccount(supplierId, bankId) {
      return await $api(`/bp/bank-accounts/${bankId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // VAT CERTIFICATES (polymorphic via bp_vat_certificates)
    // ──────────────────────────────────────────────────

    async fetchSupplierVatCerts(supplierId) {
      return await $api(`/bp/suppliers/${supplierId}/vat-certificates`, { method: 'GET' })
    },

    async addSupplierVatCert(supplierId, certData) {
      return await $api(`/bp/suppliers/${supplierId}/vat-certificates`, {
        method: 'POST',
        body: certData,
      })
    },

    async updateSupplierVatCert(supplierId, certId, certData) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'PUT',
        body: certData,
      })
    },

    async deleteSupplierVatCert(supplierId, certId) {
      return await $api(`/bp/vat-certificates/${certId}`, {
        method: 'DELETE',
      })
    },

    // ──────────────────────────────────────────────────
    // DOCUMENTS (polymorphic via bp_documents)
    // ──────────────────────────────────────────────────

    async fetchSupplierDocuments(supplierId) {
      return await $api(`/bp/suppliers/${supplierId}/documents`, { method: 'GET' })
    },

    // 👉 Add document with optional file upload (FormData)
    async addSupplierDocument(supplierId, docData, file = null) {
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

        return await $api(`/bp/suppliers/${supplierId}/documents`, {
          method: 'POST',
          body: formData,
        })
      }

      // JSON payload — convert boolean for consistency
      const payload = { ...docData }
      if ('auto_renewable' in payload) {
        payload['auto_renewable'] = payload['auto_renewable'] ? true : false
      }

      return await $api(`/bp/suppliers/${supplierId}/documents`, {
        method: 'POST',
        body: payload,
      })
    },

    async updateSupplierDocument(supplierId, docId, docData, file = null) {
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

    async deleteSupplierDocument(supplierId, docId) {
      return await $api(`/bp/documents/${docId}`, {
        method: 'DELETE',
      })
    },

    // 👉 Download document securely (passes Auth tokens via $api)
    async downloadSupplierDocument(downloadUrl, fallbackFileName = 'document') {
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
