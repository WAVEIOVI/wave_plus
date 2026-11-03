<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import { localizedValue } from '@/composables/useLocaleField'
import { usePagination } from '@/composables/usePagination'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { getCatalogItemTypes, getCategorySubtypeOptions } from '@/utils/catalog-constants'
import MultiLangField from '@/views/catalog/shared/MultiLangField.vue'

const supportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()

const loading = ref(false)
const saving = ref(false)
const categories = ref([])
const expandedCategories = ref(new Set())

// ── Pagination ──────────────────────────────────────────────────────────
const itemsPerPage = ref(10)
const { page, totalPages, currentItems } = usePagination(categories, itemsPerPage)

// ── Dialog state ────────────────────────────────────────────────────────
const dialogVisible = ref(false)
const dialogMode = ref('add') // 'add' | 'edit' | 'view'
const editingCategory = ref(null)
const formRef = ref(null)

const formData = ref({
  name: {},
  parent_id: null,
  item_type_scope: [],
  item_subtype_scope: [],
  icon: '',
  sort_order: 0,
  is_active: true,
})

const typeOptions = computed(() => getCatalogItemTypes())
const subtypeOptions = getCategorySubtypeOptions()
const isReadOnly = computed(() => dialogMode.value === 'view')

const visibleSubtypeOptions = selectedTypes => subtypeOptions.filter(option => selectedTypes.includes(option.itemType))

const categoryRows = computed(() => {
  const rows = []

  const append = (items, depth = 0) => {
    items.forEach(category => {
      rows.push({ category, depth, hasChildren: Boolean(category.children?.length) })
      if (expandedCategories.value.has(category.id)) append(category.children || [], depth + 1)
    })
  }

  append(currentItems.value)
  
  return rows
})

const toggleCategory = category => {
  const next = new Set(expandedCategories.value)
  if (next.has(category.id)) next.delete(category.id)
  else next.add(category.id)
  expandedCategories.value = next
}

const collapseAll = () => {
  expandedCategories.value = new Set()
}

const cleanSubtypeScope = (types, subtypes) => subtypes.filter(value => subtypeOptions.some(option => option.value === value && types.includes(option.itemType)))

watch(() => formData.value.item_type_scope, types => {
  formData.value.item_subtype_scope = cleanSubtypeScope(types, formData.value.item_subtype_scope)
})

// ── Subcategory dialog ──────────────────────────────────────────────────
const subDialogVisible = ref(false)
const subDialogMode = ref('add')
const editingSubcategory = ref(null)
const subFormRef = ref(null)

const subFormData = ref({
  name: {},
  item_type_scope: [],
  item_subtype_scope: [],
  icon: '',
  sort_order: 0,
  is_active: true,
})

watch(() => subFormData.value.item_type_scope, types => {
  subFormData.value.item_subtype_scope = cleanSubtypeScope(types, subFormData.value.item_subtype_scope)
})

// ── Fetch ───────────────────────────────────────────────────────────────
const fetchCategories = async () => {
  loading.value = true
  try {
    const response = await supportStore.fetchCategories({ roots_only: true, force: true })

    categories.value = response?.data || []
    collapseAll()
  }
  catch (err) {
    console.error('Error fetching categories:', err)
  }
  finally {
    loading.value = false
  }
}

// ── Category CRUD ───────────────────────────────────────────────────────
const openAdd = () => {
  dialogMode.value = 'add'
  editingCategory.value = null
  formData.value = { name: {}, parent_id: null, item_type_scope: [], item_subtype_scope: [], icon: '', sort_order: 0, is_active: true }
  dialogVisible.value = true
}

const openEdit = category => {
  dialogMode.value = 'edit'
  editingCategory.value = category
  formData.value = {
    name: { ...category.name },
    parent_id: category.parent_id || null,
    item_type_scope: category.item_type_scope || [],
    item_subtype_scope: category.item_subtype_scope || [],
    icon: category.icon || '',
    sort_order: category.sort_order || 0,
    is_active: category.is_active ?? true,
  }
  dialogVisible.value = true
}

const openView = category => {
  dialogMode.value = 'view'
  editingCategory.value = category
  formData.value = {
    name: { ...category.name },
    parent_id: category.parent_id || null,
    item_type_scope: category.item_type_scope || [],
    item_subtype_scope: category.item_subtype_scope || [],
    icon: category.icon || '',
    sort_order: category.sort_order || 0,
    is_active: category.is_active ?? true,
  }
  dialogVisible.value = true
}

const handleSave = async () => {
  if (formRef.value) {
    const { valid } = await formRef.value.validate()
    if (!valid) return
  }

  saving.value = true
  try {
    if (dialogMode.value === 'add') {
      await supportStore.createCategory(formData.value)
      snackbarStore.showMessage('Category created successfully.', 'success')
    }
    else {
      await supportStore.updateCategory(editingCategory.value.id, formData.value)
      snackbarStore.showMessage('Category updated successfully.', 'success')
    }
    dialogVisible.value = false
    await fetchCategories()
  }
  catch (err) {
    const msg = err?.data?.message || 'Failed to save category.'

    snackbarStore.showMessage(msg, 'error')
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async category => {
  saving.value = true
  try {
    await supportStore.deleteCategory(category.id)
    snackbarStore.showMessage('Category deleted.', 'success')
    await fetchCategories()
  }
  catch (err) {
    snackbarStore.showMessage('Failed to delete category.', 'error')
  }
  finally {
    saving.value = false
  }
}

// ── Subcategory CRUD ────────────────────────────────────────────────────
const openAddSub = parentCategory => {
  subDialogMode.value = 'add'
  editingSubcategory.value = null
  editingCategory.value = parentCategory
  subFormData.value = {
    name: {},
    item_type_scope: parentCategory.item_type_scope || [],
    item_subtype_scope: [],
    icon: '',
    sort_order: 0,
    is_active: true,
  }
  subDialogVisible.value = true
}

const openEditSub = (sub, parentCategory) => {
  subDialogMode.value = 'edit'
  editingSubcategory.value = sub
  editingCategory.value = parentCategory
  subFormData.value = {
    name: { ...sub.name },
    item_type_scope: sub.item_type_scope || [],
    item_subtype_scope: sub.item_subtype_scope || [],
    icon: sub.icon || '',
    sort_order: sub.sort_order || 0,
    is_active: sub.is_active ?? true,
  }
  subDialogVisible.value = true
}

const handleSubSave = async () => {
  if (subFormRef.value) {
    const { valid } = await subFormRef.value.validate()
    if (!valid) return
  }

  saving.value = true
  try {
    if (subDialogMode.value === 'add') {
      await supportStore.createCategory({
        ...subFormData.value,
        parent_id: editingCategory.value.id,
      })
      snackbarStore.showMessage('Subcategory created.', 'success')
    }
    else {
      await supportStore.updateCategory(editingSubcategory.value.id, {
        ...subFormData.value,
        parent_id: editingCategory.value.id,
      })
      snackbarStore.showMessage('Subcategory updated.', 'success')
    }
    subDialogVisible.value = false
    await fetchCategories()
  }
  catch (err) {
    snackbarStore.showMessage(err?.data?.message || 'Failed to save subcategory.', 'error')
  }
  finally {
    saving.value = false
  }
}

const handleDeleteSub = async sub => {
  saving.value = true
  try {
    await supportStore.deleteCategory(sub.id)
    snackbarStore.showMessage('Subcategory deleted.', 'success')
    await fetchCategories()
  }
  catch (err) {
    snackbarStore.showMessage('Failed to delete subcategory.', 'error')
  }
  finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between py-3 px-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-category"
          size="20"
        />
        Categories
        <VChip
          size="x-small"
          label
          color="primary"
        >
          {{ categories.length }}
        </VChip>
      </div>
      <VBtn
        v-if="$can('create', 'Catalog')"
        size="small"
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        Add Category
      </VBtn>
    </VCardTitle>
    <VDivider />
    <VCardText>
      <!-- Loading -->
      <VSkeletonLoader
        v-if="loading"
        type="list-item@5"
      />

      <!-- Empty State -->
      <VAlert
        v-else-if="categories.length === 0"
        type="info"
        variant="tonal"
      >
        No categories yet. Click "Add Category" to create one.
      </VAlert>

      <!-- Category List -->
      <template v-else>
        <div class="d-flex justify-end mb-2">
          <VBtn
            size="x-small"
            variant="text"
            prepend-icon="tabler-fold"
            @click="collapseAll"
          >
            Collapse all
          </VBtn>
        </div>
        <VTable
          density="compact"
          class="mb-4"
        >
          <thead>
            <tr>
              <th style="inline-size: 40px;" />
              <th>Name</th>
              <th>Scope</th>
              <th>Children</th>
              <th>Status</th>
              <th style="inline-size: 120px;">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in categoryRows"
              :key="row.category.id"
            >
              <td>
                <IconBtn
                  size="x-small"
                  :disabled="!row.hasChildren"
                  @click="toggleCategory(row.category)"
                >
                  <VIcon
                    :icon="expandedCategories.has(row.category.id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
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
                  <span class="font-weight-medium">{{ localizedValue(row.category.name, 'Untitled') }}</span>
                </div>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="scope in row.category.item_type_scope || []"
                    :key="scope"
                    size="x-small"
                    label
                    color="primary"
                  >
                    {{ typeOptions.find(option => option.value === scope)?.title || scope }}
                  </VChip>
                  <span
                    v-if="!(row.category.item_type_scope || []).length"
                    class="text-caption text-disabled"
                  >Any type</span>
                </div>
                <div
                  v-if="(row.category.item_subtype_scope || []).length"
                  class="text-caption text-disabled mt-1"
                >
                  {{ row.category.item_subtype_scope.length }} detailed subtype(s)
                </div>
              </td>
              <td>
                <VChip
                  size="x-small"
                  label
                >
                  {{ row.category.children?.length || 0 }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="row.category.is_active ? 'success' : 'warning'"
                  size="x-small"
                  label
                >
                  {{ row.category.is_active ? 'Active' : 'Inactive' }}
                </VChip>
              </td>
              <td>
                <IconBtn
                  size="x-small"
                  @click="openView(row.category)"
                >
                  <VIcon
                    icon="tabler-eye"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('edit', 'Catalog')"
                  size="x-small"
                  @click="openEdit(row.category)"
                >
                  <VIcon
                    icon="tabler-edit"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('delete', 'Catalog')"
                  size="x-small"
                  color="error"
                  @click="handleDelete(row.category)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('create', 'Catalog')"
                  size="x-small"
                  @click="openAddSub(row.category)"
                >
                  <VIcon
                    icon="tabler-folder-plus"
                    size="16"
                  />
                </IconBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <!-- Pagination -->
        <div
          v-if="totalPages > 1"
          class="d-flex justify-center"
        >
          <VPagination
            v-model="page"
            :length="totalPages"
            :total-visible="5"
          />
        </div>
      </template>
    </VCardText>
  </VCard>

  <!-- 👉 Category Add/Edit/View Dialog -->
  <VDialog
    v-model="dialogVisible"
    max-width="600"
    persistent
  >
    <DialogCloseBtn @click="dialogVisible = false" />
    <VCard :title="dialogMode === 'add' ? 'Add Category' : dialogMode === 'edit' ? 'Edit Category' : 'View Category'">
      <VCardText>
        <VForm
          ref="formRef"
          @submit.prevent="handleSave"
        >
          <VRow>
            <VCol cols="12">
              <MultiLangField
                v-model="formData.name"
                label="Category Name *"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.item_type_scope"
                label="Type Scope"
                :items="typeOptions"
                variant="outlined"
                multiple
                chips
                closable-chips
                clearable
                :disabled="isReadOnly"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="formData.item_subtype_scope"
                label="Detailed Subtype Scope"
                :items="visibleSubtypeOptions(formData.item_type_scope)"
                variant="outlined"
                multiple
                chips
                closable-chips
                clearable
                :disabled="isReadOnly || !formData.item_type_scope.length"
                hint="Optional. Choose the detailed types allowed in this category."
                persistent-hint
              />
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <AppTextField
                v-model="formData.sort_order"
                label="Sort Order"
                type="number"
                variant="outlined"
                :disabled="isReadOnly"
              />
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <VSwitch
                v-model="formData.is_active"
                label="Active"
                :disabled="isReadOnly"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions v-if="!isReadOnly">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="dialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="saving"
          @click="handleSave"
        >
          {{ dialogMode === 'add' ? 'Create' : 'Save' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- 👉 Subcategory Add/Edit Dialog -->
  <VDialog
    v-model="subDialogVisible"
    max-width="600"
    persistent
  >
    <DialogCloseBtn @click="subDialogVisible = false" />
    <VCard :title="subDialogMode === 'add' ? 'Add Subcategory' : 'Edit Subcategory'">
      <VCardText>
        <VForm
          ref="subFormRef"
          @submit.prevent="handleSubSave"
        >
          <VRow>
            <VCol cols="12">
              <MultiLangField
                v-model="subFormData.name"
                label="Subcategory Name *"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="subFormData.item_type_scope"
                label="Type Scope"
                :items="typeOptions"
                variant="outlined"
                multiple
                chips
                closable-chips
                clearable
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="subFormData.item_subtype_scope"
                label="Detailed Subtype Scope"
                :items="visibleSubtypeOptions(subFormData.item_type_scope)"
                variant="outlined"
                multiple
                chips
                closable-chips
                clearable
                :disabled="!subFormData.item_type_scope.length"
                hint="Optional. Choose the detailed types allowed in this subcategory."
                persistent-hint
              />
            </VCol>
            <VCol
              cols="6"
              md="4"
            >
              <AppTextField
                v-model="subFormData.sort_order"
                label="Sort Order"
                type="number"
                variant="outlined"
              />
            </VCol>
            <VCol
              cols="6"
              md="4"
            >
              <VSwitch
                v-model="subFormData.is_active"
                label="Active"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="subDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="saving"
          @click="handleSubSave"
        >
          {{ subDialogMode === 'add' ? 'Create' : 'Save' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
