<script setup>
import { useUserStore } from '@/stores/useUserStore'
import AddUserWizardDialog from '@/views/user/list/AddUserWizardDialog.vue'

const userStore = useUserStore()

const searchQuery = ref('')
const selectedRole = ref()
const selectedStatus = ref()

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()
const selectedRows = ref([])

definePage({
  path: '/user/list',
  name: 'user-list',
  meta: {
    layout: 'default',
    module: 'user',
    action: 'read',
    subject: 'users',
  },
})

// Headers
const headers = [
  { title: 'User', key: 'user' },
  { title: 'Role', key: 'role' },
  { title: 'User Type / Scope', key: 'userType' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch roles for filter dropdown
const roleOptions = ref([])

onMounted(async () => {
  const roles = await userStore.fetchRoles()

  roleOptions.value = roles.map(r => ({
    title: r.name,
    value: r.name,
  }))
})

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Pending', value: 'pending' },
  { title: 'Suspended', value: 'suspended' },
]

// Fetch users reactively with in-flight guard
let isFetching = false
let isFirstLoad = true

const fetchUsers = async () => {
  if (isFetching)
    return

  isFetching = true
  try {
    await userStore.fetchUsers({
      q: searchQuery.value,
      role: selectedRole.value,
      status: selectedStatus.value,
      itemsPerPage: itemsPerPage.value,
      page: page.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    })
  }
  finally {
    isFetching = false
  }
}

const updateOptions = options => {
  const newSortBy = options.sortBy[0]?.key
  const newOrderBy = options.sortBy[0]?.order
  const newPage = options.page
  const newItemsPerPage = options.itemsPerPage

  const hasChanged =
    isFirstLoad ||
    sortBy.value !== newSortBy ||
    orderBy.value !== newOrderBy ||
    page.value !== newPage ||
    itemsPerPage.value !== newItemsPerPage

  isFirstLoad = false

  sortBy.value = newSortBy
  orderBy.value = newOrderBy
  page.value = newPage
  itemsPerPage.value = newItemsPerPage

  if (hasChanged) {
    fetchUsers()
  }
}

// Watch filters
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchUsers()
  }, 350)
})

watch([selectedRole, selectedStatus], () => {
  page.value = 1
  fetchUsers()
})

const resolveUserRoleVariant = role => {
  const r = role?.toLowerCase() || ''

  const map = {
    'super admin': { color: 'error', icon: 'tabler-crown' },
    'admin': { color: 'primary', icon: 'tabler-shield-check' },
    'responsable': { color: 'warning', icon: 'tabler-briefcase' },
    'staff': { color: 'info', icon: 'tabler-users' },
    'client': { color: 'success', icon: 'tabler-user' },
    'client site manager': { color: 'secondary', icon: 'tabler-building' },
    'client group': { color: 'primary', icon: 'tabler-users-group' },
    'organisation': { color: 'warning', icon: 'tabler-building-community' },
  }

  return map[r] || { color: 'primary', icon: 'tabler-user' }
}

const resolveStatusVariant = stat => {
  const s = stat?.toLowerCase() || ''

  const map = {
    active: 'success',
    inactive: 'secondary',
    pending: 'warning',
    suspended: 'error',
  }

  return map[s] || 'primary'
}

const resolveUserTypeVariant = type => {
  const map = {
    super_admin: 'primary',
    admin: 'secondary',
    employee: 'info',
    standard_client: 'success',
    site_responsible: 'warning',
    client_group: 'primary',
    organisation: 'error',
    supplier: 'default',
  }
  return map[type] || 'default'
}

const isAddUserWizardVisible = ref(false)
const editUserData = ref(null)
const exportingPdf = ref(false)

const handleExportPdf = async () => {
  exportingPdf.value = true
  try {
    await userStore.exportUsersPdf()
  } catch (error) {
    console.error('PDF export failed:', error)
  } finally {
    exportingPdf.value = false
  }
}

const openAddDrawer = () => {
  editUserData.value = null
  isAddUserWizardVisible.value = true
}

const openEditDrawer = user => {
  editUserData.value = user
  isAddUserWizardVisible.value = true
}

const onUserSaved = () => {
  fetchUsers()
}

const deleteUser = async id => {
  await userStore.deleteUser(id)

  const index = selectedRows.value.findIndex(row => row === id)
  if (index !== -1)
    selectedRows.value.splice(index, 1)

  fetchUsers()
}

const widgetData = computed(() => [
  {
    title: 'Total Users',
    value: userStore.totalUsers.toString(),
    icon: 'tabler-users',
    iconColor: 'primary',
  },
  {
    title: 'Active Users',
    value: userStore.users.filter(u => u.account_status === 'active').length.toString(),
    icon: 'tabler-user-check',
    iconColor: 'success',
  },
  {
    title: 'Inactive Users',
    value: userStore.users.filter(u => u.account_status === 'inactive').length.toString(),
    icon: 'tabler-user-off',
    iconColor: 'secondary',
  },
  {
    title: 'Pending Users',
    value: userStore.users.filter(u => u.account_status === 'pending').length.toString(),
    icon: 'tabler-user-search',
    iconColor: 'warning',
  },
])
</script>

<template>
  <section>
    <!-- 👉 Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="3"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <h4 class="text-h4">
                      {{ data.value }}
                    </h4>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="42"
                    :icon="data.icon"
                  />
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <!-- 👉 Select Role -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedRole"
              placeholder="Select Role"
              :items="roleOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Select Status"
              :items="statusOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
              { value: -1, title: 'All' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="val => { itemsPerPage = parseInt(val, 10); page = 1; fetchUsers() }"
          />
        </div>
        <VSpacer />

        <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search User"
            />
          </div>

          <!-- 👉 Export PDF button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-file-type-pdf"
            :loading="exportingPdf"
            :disabled="exportingPdf"
            @click="handleExportPdf"
          >
            Export PDF
          </VBtn>

          <!-- 👉 Add user button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="openAddDrawer"
          >
            Add New User
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items="userStore.users"
        item-value="id"
        :items-length="userStore.totalUsers"
        :headers="headers"
        :loading="userStore.loading"
        class="text-no-wrap"
        show-select
        @update:options="updateOptions"
      >
        <!-- User -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              :variant="!item.avatar ? 'tonal' : undefined"
              :color="!item.avatar ? resolveUserRoleVariant(item.role).color : undefined"
            >
              <VImg
                v-if="item.avatar"
                :src="item.avatar"
              />
              <span v-else>{{ avatarText(item.fullName) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                <RouterLink
                  :to="{ name: 'user-view-id', params: { id: item.id } }"
                  class="font-weight-medium text-link"
                >
                  {{ item.fullName }}
                </RouterLink>
              </h6>
              <div class="text-sm">
                {{ item.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- 👉 Role -->
        <template #item.role="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              :icon="resolveUserRoleVariant(item.role).icon"
              :color="resolveUserRoleVariant(item.role).color"
            />

            <div class="text-capitalize text-high-emphasis text-body-1">
              {{ item.role }}
            </div>
          </div>
        </template>

        <!-- User Type -->
        <template #item.userType="{ item }">
          <div class="d-flex flex-column gap-y-1 py-1">
            <VChip
              :color="resolveUserTypeVariant(item.user_type)"
              size="small"
              variant="tonal"
              class="text-capitalize align-self-start font-weight-medium"
            >
              {{ item.user_type ? item.user_type.replace(/_/g, ' ') : 'Standard' }}
            </VChip>
            <span
              v-if="item.employee"
              class="text-caption text-medium-emphasis"
            >
              Emp: {{ item.employee.name }}
            </span>
            <span
              v-else-if="item.client"
              class="text-caption text-medium-emphasis"
            >
              Client: {{ item.client.name }}
              <span v-if="item.client_sites && item.client_sites.length">
                ({{ item.client_sites.length }} sites)
              </span>
            </span>
            <span
              v-else-if="item.clients && item.clients.length"
              class="text-caption text-medium-emphasis"
            >
              {{ item.clients.length }} client companies
            </span>
            <span
              v-else-if="item.supplier"
              class="text-caption text-medium-emphasis"
            >
              Supplier: {{ item.supplier.name }}
            </span>
            <span
              v-else-if="item.organisation"
              class="text-caption text-medium-emphasis"
            >
              Org: {{ item.organisation.name }}
            </span>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatusVariant(item.account_status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.account_status }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="deleteUser(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn @click="openEditDrawer(item)">
            <VIcon icon="tabler-pencil" />
          </IconBtn>

          <VBtn
            icon
            variant="text"
            color="medium-emphasis"
          >
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem :to="{ name: 'user-view-id', params: { id: item.id } }">
                  <template #prepend>
                    <VIcon icon="tabler-eye" />
                  </template>

                  <VListItemTitle>View</VListItemTitle>
                </VListItem>

                <VListItem
                  link
                  @click="openEditDrawer(item)"
                >
                  <template #prepend>
                    <VIcon icon="tabler-pencil" />
                  </template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>

                <VListItem @click="deleteUser(item.id)">
                  <template #prepend>
                    <VIcon icon="tabler-trash" />
                  </template>
                  <VListItemTitle>Delete</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="userStore.totalUsers"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>
    <!-- 👉 Add / Edit User Wizard Dialog -->
    <AddUserWizardDialog
      v-model:is-dialog-open="isAddUserWizardVisible"
      :user-data="editUserData"
      @user-saved="onUserSaved"
    />
  </section>
</template>
