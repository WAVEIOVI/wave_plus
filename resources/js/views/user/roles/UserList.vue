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

// Headers
const headers = [
  { title: 'User', key: 'user' },
  { title: 'Role', key: 'role' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch roles for filter
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
  const map = { active: 'success', inactive: 'secondary', pending: 'warning', suspended: 'error' }

  return map[s] || 'primary'
}

const isAddUserWizardVisible = ref(false)
const editUserData = ref(null)

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
</script>

<template>
  <section>
    <VCard>
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="d-flex gap-2 align-center">
          <p class="text-body-1 mb-0">
            Show
          </p>
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
              { value: -1, title: 'All' },
            ]"
            style="inline-size: 5.5rem;"
            @update:model-value="val => { itemsPerPage = parseInt(val, 10); page = 1; fetchUsers() }"
          />
        </div>

        <VSpacer />

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Search User"
            style="inline-size: 15.625rem;"
          />

          <!-- 👉 Filter by role -->
          <AppSelect
            v-model="selectedRole"
            placeholder="Select Role"
            :items="roleOptions"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />

          <!-- 👉 Filter by status -->
          <AppSelect
            v-model="selectedStatus"
            placeholder="Status"
            :items="statusOptions"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items="userStore.users"
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

    <!-- 👉 Add/Edit User Wizard Dialog -->
    <AddUserWizardDialog
      v-model:is-dialog-open="isAddUserWizardVisible"
      :user-data="editUserData"
      @user-saved="onUserSaved"
    />
  </section>
</template>

<style lang="scss">
.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style>
