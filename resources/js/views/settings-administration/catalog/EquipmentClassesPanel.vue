<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import MultiLangField from '@/views/catalog/shared/MultiLangField.vue'

const supportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()
const loading = ref(false)
const saving = ref(false)
const classes = ref([])
const expanded = ref(new Set())
const dialogVisible = ref(false)
const dialogMode = ref('add')
const editing = ref(null)
const parentClass = ref(null)
const formRef = ref(null)
const formData = ref({ code: '', name: {}, description: {}, parent_id: null, sort_order: 0, status: 'active' })
const isReadOnly = computed(() => dialogMode.value === 'view')

const rows = computed(() => {
  const result = []

  const append = (items, depth = 0) => items.forEach(item => {
    result.push({ item, depth })
    if (expanded.value.has(item.id)) append(item.children || [], depth + 1)
  })

  append(classes.value)
  
  return result
})

const toggle = item => {
  const next = new Set(expanded.value)
  if (next.has(item.id)) next.delete(item.id)
  else next.add(item.id)
  expanded.value = next
}

const fetchClasses = async () => {
  loading.value = true
  try {
    const response = await supportStore.fetchEquipmentClasses({ roots_only: true, force: true })

    classes.value = response?.data || []
    expanded.value = new Set()
  }
  catch (error) {
    console.error('Error fetching equipment classes:', error)
  }
  finally {
    loading.value = false
  }
}

const resetForm = (parent = null) => {
  formData.value = {
    code: '',
    name: {},
    description: {},
    parent_id: parent?.id || null,
    sort_order: 0,
    status: 'active',
  }
}

const openAdd = parent => {
  dialogMode.value = 'add'
  editing.value = null
  parentClass.value = parent
  resetForm(parent)
  dialogVisible.value = true
}

const openEdit = item => {
  dialogMode.value = 'edit'
  editing.value = item
  parentClass.value = null
  formData.value = {
    code: item.code || '',
    name: { ...item.name },
    description: { ...item.description },
    parent_id: item.parent_id || null,
    sort_order: item.sort_order || 0,
    status: item.status || 'active',
  }
  dialogVisible.value = true
}

const openView = item => {
  openEdit(item)
  dialogMode.value = 'view'
}

const save = async () => {
  const result = await formRef.value?.validate()
  if (result && !result.valid) return

  saving.value = true
  try {
    if (dialogMode.value === 'add') {
      await supportStore.createEquipmentClass(formData.value)
      snackbarStore.showMessage('Equipment class created.', 'success')
    }
    else {
      await supportStore.updateEquipmentClass(editing.value.id, formData.value)
      snackbarStore.showMessage('Equipment class updated.', 'success')
    }
    dialogVisible.value = false
    await fetchClasses()
  }
  catch (error) {
    snackbarStore.showMessage(error?.data?.message || 'Failed to save equipment class.', 'error')
  }
  finally {
    saving.value = false
  }
}

const remove = async item => {
  saving.value = true
  try {
    await supportStore.deleteEquipmentClass(item.id)
    snackbarStore.showMessage('Equipment class deleted.', 'success')
    await fetchClasses()
  }
  catch (error) {
    snackbarStore.showMessage(error?.data?.message || 'Failed to delete equipment class.', 'error')
  }
  finally {
    saving.value = false
  }
}

onMounted(fetchClasses)
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between py-3 px-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-tool"
          size="20"
        />
        Equipment Classes
        <VChip
          size="x-small"
          label
          color="primary"
        >
          {{ classes.length }}
        </VChip>
      </div>
      <VBtn
        v-if="$can('create', 'Catalog')"
        size="small"
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        Add Class
      </VBtn>
    </VCardTitle>
    <VDivider />
    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item@5"
      />
      <VAlert
        v-else-if="!classes.length"
        type="info"
        variant="tonal"
      >
        No equipment classes yet. Click "Add Class" to create one.
      </VAlert>
      <VTable
        v-else
        density="compact"
      >
        <thead>
          <tr><th style="inline-size: 40px;" /><th>Class</th><th>Code</th><th>Blueprints</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.item.id"
          >
            <td>
              <IconBtn
                size="x-small"
                :disabled="!row.item.children?.length"
                @click="toggle(row.item)"
              >
                <VIcon
                  :icon="expanded.has(row.item.id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                  size="16"
                />
              </IconBtn>
            </td>
            <td>
              <div
                class="d-flex align-center"
                :style="{ paddingInlineStart: `${row.depth * 28}px` }"
              >
                <VIcon
                  v-if="row.depth"
                  icon="tabler-corner-down-right"
                  size="16"
                  class="me-2 text-disabled"
                />
                <span class="font-weight-medium">{{ localizedValue(row.item.name, row.item.code) }}</span>
              </div>
            </td>
            <td><code>{{ row.item.code }}</code></td>
            <td>
              <VChip
                size="x-small"
                label
              >
                {{ row.item.blueprints_count || 0 }}
              </VChip>
            </td>
            <td>
              <VChip
                :color="row.item.status === 'active' ? 'success' : 'warning'"
                size="x-small"
                label
              >
                {{ row.item.status }}
              </VChip>
            </td>
            <td>
              <IconBtn
                size="x-small"
                @click="openView(row.item)"
              >
                <VIcon
                  icon="tabler-eye"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                v-if="$can('edit', 'Catalog')"
                size="x-small"
                @click="openEdit(row.item)"
              >
                <VIcon
                  icon="tabler-edit"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                v-if="$can('create', 'Catalog')"
                size="x-small"
                @click="openAdd(row.item)"
              >
                <VIcon
                  icon="tabler-folder-plus"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                v-if="$can('delete', 'Catalog')"
                size="x-small"
                color="error"
                @click="remove(row.item)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="16"
                />
              </IconBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <VDialog
    v-model="dialogVisible"
    max-width="650"
    persistent
  >
    <DialogCloseBtn @click="dialogVisible = false" />
    <VCard :title="dialogMode === 'add' ? (parentClass ? 'Add Subclass' : 'Add Equipment Class') : dialogMode === 'edit' ? 'Edit Equipment Class' : 'View Equipment Class'">
      <VCardText>
        <VForm
          ref="formRef"
          @submit.prevent="save"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.code"
                label="Code *"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.sort_order"
                label="Sort Order"
                type="number"
                :disabled="isReadOnly"
              />
            </VCol>
            <VCol cols="12">
              <MultiLangField
                v-model="formData.name"
                label="Class Name *"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol cols="12">
              <MultiLangField
                v-model="formData.description"
                label="Description"
                :disabled="isReadOnly"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.status"
                label="Status"
                :items="[{ value: 'active', title: 'Active' }, { value: 'inactive', title: 'Inactive' }]"
                :disabled="isReadOnly"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions v-if="!isReadOnly">
        <VSpacer /><VBtn
          variant="tonal"
          color="secondary"
          @click="dialogVisible = false"
        >
          Cancel
        </VBtn><VBtn
          color="primary"
          :loading="saving"
          @click="save"
        >
          {{ dialogMode === 'add' ? 'Create' : 'Save' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
