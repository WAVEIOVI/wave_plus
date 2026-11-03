<script setup>
import { useUserStore } from '@/stores/useUserStore'
import avatar1 from '@images/avatars/avatar-1.png'
import avatar10 from '@images/avatars/avatar-10.png'
import avatar2 from '@images/avatars/avatar-2.png'
import avatar3 from '@images/avatars/avatar-3.png'
import avatar4 from '@images/avatars/avatar-4.png'
import avatar5 from '@images/avatars/avatar-5.png'
import avatar6 from '@images/avatars/avatar-6.png'
import avatar7 from '@images/avatars/avatar-7.png'
import avatar8 from '@images/avatars/avatar-8.png'
import avatar9 from '@images/avatars/avatar-9.png'
import girlUsingMobile from '@images/pages/girl-using-mobile.png'

const userStore = useUserStore()
const roles = ref([])

const avatarPool = [avatar1, avatar2, avatar3, avatar4, avatar5, avatar6, avatar7, avatar8, avatar9, avatar10]

const loadRoles = async () => {
  const fetchedRoles = await userStore.fetchRoles()

  roles.value = fetchedRoles.map(role => ({
    id: role.id,
    role: role.name,
    totalUsers: role.users_count ?? 0,
    users: (role.users || []).map((u, i) => u.avatar || avatarPool[i % avatarPool.length]),
    details: {
      id: role.id,
      name: role.name,
      permissions: Array.isArray(role.permissions)
        ? role.permissions.map(p => (typeof p === 'string' ? p : p.name))
        : [],
    },
  }))
}

onMounted(loadRoles)

const isRoleDialogVisible = ref(false)
const roleDetail = ref()
const isAddRoleDialogVisible = ref(false)

const addRoleData = ref(null)

const openAddRoleDialog = () => {
  addRoleData.value = null
  isAddRoleDialogVisible.value = true
}

const duplicateRole = role => {
  addRoleData.value = {
    name: 'Copy of ' + role.name,
    permissions: role.details.permissions,
    id: null,
  }
  isAddRoleDialogVisible.value = true
}

const deleteRole = async role => {
  if (confirm(`Are you sure you want to delete role "${role.role}"?`)) {
    try {
      await userStore.deleteRole(role.id)
      loadRoles()
    } catch (error) {
      console.error(error)
      alert(error.response?._data?.message || 'Failed to delete role')
    }
  }
}

const editPermission = value => {
  isRoleDialogVisible.value = true
  roleDetail.value = value
}
</script>

<template>
  <VRow>
    <!-- 👉 Roles -->
    <VCol
      v-for="item in roles"
      :key="item.id"
      cols="12"
      sm="6"
      lg="4"
    >
      <VCard>
        <VCardText class="d-flex align-center pb-4">
          <div class="text-body-1">
            Total {{ item.totalUsers }} users
          </div>

          <VSpacer />

          <div class="v-avatar-group">
            <template
              v-for="(user, index) in item.users"
              :key="user"
            >
              <VAvatar
                v-if="item.users.length > 4 && item.users.length !== 4 && index < 3"
                size="40"
                :image="user"
              />

              <VAvatar
                v-if="item.users.length === 4"
                size="40"
                :image="user"
              />
            </template>
            <VAvatar
              v-if="item.users.length > 4"
              :color="$vuetify.theme.current.dark ? '#373B50' : '#EEEDF0'"
            >
              <span>
                +{{ item.users.length - 3 }}
              </span>
            </VAvatar>
          </div>
        </VCardText>

        <VCardText>
          <div class="d-flex justify-space-between align-center">
            <div>
              <h5 class="text-h5">
                {{ item.role }}
              </h5>
              <div class="d-flex align-center">
                <a
                  href="javascript:void(0)"
                  @click="editPermission(item.details)"
                >
                  Edit Role
                </a>
              </div>
            </div>
            <div class="d-flex gap-2">
              <IconBtn @click="duplicateRole(item)">
                <VIcon
                  icon="tabler-copy"
                  class="text-high-emphasis"
                />
              </IconBtn>
              
              <IconBtn @click="deleteRole(item)">
                <VIcon
                  icon="tabler-trash"
                  class="text-error"
                />
              </IconBtn>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Add New Role -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <VCard
        class="h-100"
        :ripple="false"
      >
        <VRow
          no-gutters
          class="h-100"
        >
          <VCol
            cols="5"
            class="d-flex flex-column justify-end align-center mt-5"
          >
            <img
              width="85"
              :src="girlUsingMobile"
            >
          </VCol>

          <VCol cols="7">
            <VCardText class="d-flex flex-column align-end justify-end gap-4">
              <VBtn
                size="small"
                @click="openAddRoleDialog"
              >
                Add New Role
              </VBtn>
              <div class="text-end">
                Add new role,<br> if it doesn't exist.
              </div>
            </VCardText>
          </VCol>
        </VRow>
      </VCard>
      <AddEditRoleDialog
        v-model:is-dialog-visible="isAddRoleDialogVisible"
        :role-data="addRoleData"
        @saved="loadRoles"
      />
    </VCol>
  </VRow>

  <AddEditRoleDialog
    v-model:is-dialog-visible="isRoleDialogVisible"
    :role-data="roleDetail"
    @saved="loadRoles"
  />
</template>
