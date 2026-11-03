<script setup>
import { useUserStore } from '@/stores/useUserStore'

definePage({
  path: '/user/permissions',
  name: 'user-permissions',
  meta: {
    layout: 'default',
    module: 'user',
    action: 'read',
    subject: 'permissions',
  },
})

const userStore = useUserStore()

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Assigned To', key: 'roles', sortable: false },
  { title: 'Created Date', key: 'createdDate', sortable: false },
]

const search = ref('')
const permissions = ref([])
const loading = ref(true)
const errorMessage = ref('')

const loadPermissions = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    permissions.value = await userStore.fetchPermissions()
  }
  catch (error) {
    errorMessage.value = error.data?.message || error.response?._data?.message || error.message || 'Failed to load permissions.'
  }
  finally {
    loading.value = false
  }
}

onMounted(loadPermissions)

const filteredPermissions = computed(() => {
  if (!search.value) return permissions.value

  return permissions.value.filter(p =>
    p.name.toLowerCase().includes(search.value.toLowerCase()),
  )
})

const resolveRoleColor = name => {
  const map = {
    'super admin': 'error',
    'admin': 'primary',
    'responsable': 'warning',
    'staff': 'info',
    'client': 'success',
    'client site manager': 'secondary',
    'client group': 'primary',
    'organisation': 'warning',
  }

  return map[name?.toLowerCase()] || 'primary'
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex gap-2 align-center">
            <p class="text-body-1 mb-0">
              Permissions List
            </p>
          </div>

          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              v-model="search"
              placeholder="Search Permission"
              style="inline-size: 15.625rem;"
            />
            <!-- NOTE: No "Add Permission" button — permissions are managed only via seeders -->
          </div>
        </VCardText>

        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          class="mx-4 mb-4"
        >
          {{ errorMessage }}
        </VAlert>

        <VDivider />

        <VDataTable
          :items="filteredPermissions"
          :headers="headers"
          item-value="name"
          class="text-no-wrap"
          :loading="loading"
        >
          <!-- Name -->
          <template #item.name="{ item }">
            <div class="text-high-emphasis text-body-1">
              {{ item.name }}
            </div>
          </template>

          <!-- Assigned To (Roles) -->
          <template #item.roles="{ item }">
            <div class="d-flex gap-2 flex-wrap">
              <VChip
                v-for="roleName in item.roles"
                :key="roleName"
                label
                size="small"
                :color="resolveRoleColor(roleName)"
                class="font-weight-medium"
              >
                {{ roleName }}
              </VChip>
              <span
                v-if="!item.roles || item.roles.length === 0"
                class="text-disabled"
              >
                Not assigned
              </span>
            </div>
          </template>

          <!-- Created Date -->
          <template #item.createdDate="{ item }">
            <div class="text-body-1">
              {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '—' }}
            </div>
          </template>
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>
</template>
