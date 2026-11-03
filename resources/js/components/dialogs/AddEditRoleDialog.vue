<script setup>
import { useUserStore } from '@/stores/useUserStore'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps({
  roleData: {
    type: Object,
    default: null,
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const userStore = useUserStore()
const roleName = ref('')
const permissions = ref([])
const selectedPermissions = ref([])
const isLoading = ref(false)
const isSaving = ref(false)
const search = ref('')
const errorMessage = ref('')
const refPermissionForm = ref()

const filteredPermissions = computed(() => {
  const query = search.value.trim().toLowerCase()

  if (!query)
    return permissions.value

  return permissions.value.filter(permission => permission.name.toLowerCase().includes(query))
})

const isSelectAll = computed(() => permissions.value.length > 0 && selectedPermissions.value.length === permissions.value.length)
const isIndeterminate = computed(() => selectedPermissions.value.length > 0 && !isSelectAll.value)

const permissionNamesFromRole = role => {
  if (!Array.isArray(role?.permissions))
    return []

  return role.permissions.map(permission => typeof permission === 'string' ? permission : permission?.name).filter(Boolean)
}

const loadDialogData = async () => {
  roleName.value = props.roleData?.name ?? ''
  selectedPermissions.value = permissionNamesFromRole(props.roleData)
  search.value = ''
  errorMessage.value = ''
  isLoading.value = true

  try {
    const fetchedPermissions = await userStore.fetchPermissions()

    permissions.value = fetchedPermissions
      .filter(permission => typeof permission?.name === 'string')
      .sort((a, b) => a.name.localeCompare(b.name))
    selectedPermissions.value = selectedPermissions.value.filter(name =>
      permissions.value.some(permission => permission.name === name),
    )
  }
  catch (error) {
    permissions.value = []
    errorMessage.value = error.data?.message || error.response?._data?.message || error.message || 'Failed to load permissions.'
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.isDialogVisible, visible => {
  if (visible)
    loadDialogData()
}, { immediate: true })

const setAllPermissions = checked => {
  selectedPermissions.value = checked ? permissions.value.map(permission => permission.name) : []
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const onDialogVisibilityChange = visible => {
  if (!visible)
    closeDialog()
}

const onSubmit = async () => {
  errorMessage.value = ''

  const { valid } = await refPermissionForm.value.validate()

  if (!valid)
    return

  isSaving.value = true

  try {
    const name = roleName.value.trim()

    if (props.roleData?.id)
      await userStore.updateRole(props.roleData.id, name, selectedPermissions.value)
    else
      await userStore.createRole(name, selectedPermissions.value)

    emit('saved')
    closeDialog()
  }
  catch (error) {
    errorMessage.value = error.data?.message || error.response?._data?.message || error.message || 'Failed to save role.'
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    :persistent="isSaving"
    @update:model-value="onDialogVisibilityChange"
  >
    <DialogCloseBtn
      :disabled="isSaving"
      @click="closeDialog"
    />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <h4 class="text-h4 text-center mb-2">
          {{ props.roleData?.id ? 'Edit' : 'Add New' }} Role
        </h4>
        <p class="text-body-1 text-center mb-6">
          Set Role Permissions
        </p>

        <VForm
          ref="refPermissionForm"
          @submit.prevent="onSubmit"
        >
          <AppTextField
            v-model="roleName"
            label="Role Name"
            placeholder="Enter Role Name"
            :rules="[value => !!value?.trim() || 'Role name is required']"
            :disabled="isSaving"
          />

          <h5 class="text-h5 my-6">
            Role Permissions
          </h5>

          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
            <AppTextField
              v-model="search"
              placeholder="Search permissions"
              prepend-inner-icon="tabler-search"
              :disabled="isLoading || isSaving"
              style="max-inline-size: 20rem;"
            />
            <VCheckbox
              :model-value="isSelectAll"
              :indeterminate="isIndeterminate"
              label="Select All"
              :disabled="isLoading || permissions.length === 0 || isSaving"
              hide-details
              @update:model-value="setAllPermissions"
            />
          </div>

          <VTable class="permission-table text-no-wrap mb-6">
            <thead>
              <tr>
                <th scope="col">
                  Permission
                </th>
                <th
                  scope="col"
                  class="text-end"
                >
                  Enabled
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="isLoading">
                <td
                  colspan="2"
                  class="text-center py-6"
                >
                  <VProgressCircular
                    indeterminate
                    size="24"
                  />
                </td>
              </tr>
              <tr v-else-if="filteredPermissions.length === 0">
                <td
                  colspan="2"
                  class="text-center text-medium-emphasis py-6"
                >
                  {{ permissions.length ? 'No permissions match your search.' : 'No permissions are available.' }}
                </td>
              </tr>
              <template v-else>
                <tr
                  v-for="permission in filteredPermissions"
                  :key="permission.id ?? permission.name"
                >
                  <td>{{ permission.name }}</td>
                  <td class="text-end">
                    <VCheckbox
                      v-model="selectedPermissions"
                      :value="permission.name"
                      :disabled="isSaving"
                      hide-details
                    />
                  </td>
                </tr>
              </template>
            </tbody>
          </VTable>

          <div class="d-flex align-center justify-center gap-4">
            <VBtn
              type="submit"
              :loading="isSaving"
              :disabled="isLoading || !!errorMessage"
            >
              Submit
            </VBtn>

            <VBtn
              color="secondary"
              variant="tonal"
              :disabled="isSaving"
              @click="closeDialog"
            >
              Cancel
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.permission-table {
  max-block-size: 24rem;
  overflow-y: auto;

  td,
  th {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding-block: 0.5rem;
  }

  td:last-child {
    inline-size: 6rem;
  }

  .v-checkbox {
    display: inline-flex;
    min-inline-size: 4.75rem;
  }
}
</style>
