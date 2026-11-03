import { $api } from '@/utils/api'
import { defineStore } from 'pinia'

export const useUserStore = defineStore('user', {
  state: () => ({
    users: [],
    totalUsers: 0,
    loading: false,
  }),

  actions: {
    // 👉 Fetch users
    async fetchUsers(params) {
      this.loading = true
      try {
        const response = await $api('/admin/users', {
          method: 'GET',
          params,
        })

        this.users = response.users || []
        this.totalUsers = response.totalUsers || 0

        return response
      }
      catch (error) {
        // Silently handle 401 — user will be redirected by router guard
        if (error?.response?.status === 401 || error?.statusCode === 401) {
          this.users = []
          this.totalUsers = 0

          return
        }
        console.error('Error fetching users:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    // 👉 Fetch roles
    async fetchRoles() {
      try {
        const response = await $api('/admin/roles', { method: 'GET' })

        return response.data || response.roles || []
      }
      catch (error) {
        if (error?.response?.status === 401 || error?.statusCode === 401)
          return []
        console.error('Error fetching roles:', error)

        return []
      }
    },

    // 👉 Fetch permissions
    async fetchPermissions() {
      try {
        const response = await $api('/admin/permissions', { method: 'GET' })

        return response.permissions || []
      }
      catch (error) {
        console.error('Error fetching permissions:', error)
        throw error
      }
    },

    // 👉 Sync role permissions
    async syncRolePermissions(roleId, permissionNames) {
      return await $api(`/admin/roles/${roleId}/permissions`, {
        method: 'PUT',
        body: { permissions: permissionNames },
      })
    },

    // 👉 Create a new role
    async createRole(name, permissionNames = []) {
      return await $api('/admin/roles', {
        method: 'POST',
        body: { name, permissions: permissionNames },
      })
    },

    // 👉 Update role name and permissions
    async updateRole(id, name, permissionNames = []) {
      return await $api(`/admin/roles/${id}`, {
        method: 'PUT',
        body: { name, permissions: permissionNames },
      })
    },

    // 👉 Delete a role
    async deleteRole(id) {
      return await $api(`/admin/roles/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Fetch single user
    async fetchUser(id) {
      try {
        const response = await $api(`/admin/users/${id}`, { method: 'GET' })

        return response.user
      }
      catch (error) {
        console.error(`Error fetching user ${id}:`, error)
        throw error
      }
    },

    // 👉 Add User
    async createUser(userData) {
      return await $api('/admin/users', {
        method: 'POST',
        body: userData,
      })
    },

    // 👉 Update User
    async updateUser(id, userData) {
      return await $api(`/admin/users/${id}`, {
        method: 'PUT',
        body: userData,
      })
    },

    // 👉 Change Password
    async changePassword(id, data) {
      return await $api(`/admin/users/${id}/password`, {
        method: 'PUT',
        body: data,
      })
    },

    // 👉 Delete User
    async deleteUser(id) {
      return await $api(`/admin/users/${id}`, {
        method: 'DELETE',
      })
    },

    // 👉 Export Users List PDF
    async exportUsersPdf() {
      const blob = await this._fetchPdfBlob('/api/admin/users/export/pdf')

      this._downloadBlob(blob, 'users-list.pdf')
    },

    // 👉 Export Single User Record PDF
    async exportUserRecordPdf(id) {
      const blob = await this._fetchPdfBlob(`/api/admin/users/${id}/export/pdf`)

      this._downloadBlob(blob, `user-${id}.pdf`)
    },

    // 👉 Fetch creation options for User Wizard Dialog
    async fetchCreationOptions() {
      try {
        return await $api('/admin/users/creation-options', { method: 'GET' })
      }
      catch (error) {
        console.error('Error fetching creation options:', error)
        throw error
      }
    },

    // 👉 Fetch registration requests
    async fetchRegistrationRequests(params) {
      try {
        return await $api('/admin/registration-requests', {
          method: 'GET',
          params,
        })
      }
      catch (error) {
        console.error('Error fetching registration requests:', error)
        throw error
      }
    },

    // 👉 Create registration request internally
    async createRegistrationRequest(payload) {
      return await $api('/admin/registration-requests', {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Update registration request status
    async updateRegistrationRequestStatus(id, payload) {
      return await $api(`/admin/registration-requests/${id}/status`, {
        method: 'PUT',
        body: payload,
      })
    },

    // 👉 Approve registration request and convert to user
    async approveRegistrationRequest(id, payload) {
      return await $api(`/admin/registration-requests/${id}/approve`, {
        method: 'POST',
        body: payload,
      })
    },

    // 👉 Delete registration request
    async deleteRegistrationRequest(id) {
      return await $api(`/admin/registration-requests/${id}`, {
        method: 'DELETE',
      })
    },

    // ── Internal helpers ────────────────────────────────────

    /**
     * Fetch a PDF endpoint as a Blob using native fetch (ofetch
     * does not handle binary responses cleanly).
     */
    async _fetchPdfBlob(url) {
      const accessToken = useCookie('accessToken').value

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${accessToken}`,
          Accept: 'application/pdf',
        },
      })

      if (!response.ok) {
        throw new Error(`PDF export failed: ${response.statusText}`)
      }

      return await response.blob()
    },

    /**
     * Trigger a browser download from a Blob.
     */
    _downloadBlob(blob, filename) {
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')

      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()

      // Cleanup
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    },
  },
})
