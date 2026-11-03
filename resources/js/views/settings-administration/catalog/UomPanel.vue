<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { usePagination } from '@/composables/usePagination'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { getUomTypes } from '@/utils/catalog-constants'
import { computed, ref } from 'vue'

const resolveUomTypeIcon = type => {
  const map = {
    count: 'tabler-number',
    mass: 'tabler-scale',
    volume: 'tabler-droplet',
    length: 'tabler-ruler-measure',
    service: 'tabler-clock',
  }

  return map[type] || 'tabler-ruler'
}

const resolveUomTypeColor = type => {
  const map = {
    count: 'primary',
    mass: 'warning',
    volume: 'info',
    length: 'success',
    service: 'secondary',
  }

  return map[type] || 'primary'
}

const supportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()

const loading = ref(false)
const saving = ref(false)
const uomList = ref([])

// ── Pagination ──────────────────────────────────────────────────────────
const itemsPerPage = ref(10)
const { page, totalPages, currentItems } = usePagination(uomList, itemsPerPage)

// ── Dialog state ────────────────────────────────────────────────────────
const dialogVisible = ref(false)
const dialogMode = ref('add')
const editingUom = ref(null)
const formRef = ref(null)

const formData = ref({
  code: '',
  name: {},
  type: '',
})

const typeOptions = computed(() => getUomTypes())
const isReadOnly = computed(() => dialogMode.value === 'view')

// ── Fetch ───────────────────────────────────────────────────────────────
const fetchUom = async () => {
  loading.value = true
  try {
    const response = await supportStore.fetchUom()

    uomList.value = response?.data || []
  }
  catch (err) {
    console.error('Error fetching UOM:', err)
  }
  finally {
    loading.value = false
  }
}

// ── CRUD ────────────────────────────────────────────────────────────────
const openAdd = () => {
  dialogMode.value = 'add'
  editingUom.value = null
  formData.value = { code: '', name: {}, type: '' }
  dialogVisible.value = true
}

const openEdit = uom => {
  dialogMode.value = 'edit'
  editingUom.value = uom
  formData.value = {
    code: uom.code || '',
    name: { ...uom.name },
    type: uom.type || '',
  }
  dialogVisible.value = true
}

const openView = uom => {
  dialogMode.value = 'view'
  editingUom.value = uom
  formData.value = {
    code: uom.code || '',
    name: { ...uom.name },
    type: uom.type || '',
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
      await supportStore.createUom(formData.value)
      snackbarStore.showMessage('Unit of measure created.', 'success')
    }
    else {
      await supportStore.updateUom(editingUom.value.id, formData.value)
      snackbarStore.showMessage('Unit of measure updated.', 'success')
    }
    dialogVisible.value = false
    await fetchUom()
  }
  catch (err) {
    snackbarStore.showMessage(err?.data?.message || 'Failed to save unit of measure.', 'error')
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async uom => {
  saving.value = true
  try {
    await supportStore.deleteUom(uom.id)
    snackbarStore.showMessage('Unit of measure deleted.', 'success')
    await fetchUom()
  }
  catch (err) {
    snackbarStore.showMessage('Failed to delete unit of measure.', 'error')
  }
  finally {
    saving.value = false
  }
}

const getTypeTitle = val => typeOptions.value.find(t => t.value === val)?.title || val || '—'

onMounted(() => {
  fetchUom()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between py-3 px-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-ruler-measure"
          size="20"
        />
        Units of Measure
        <VChip
          size="x-small"
          label
          color="primary"
        >
          {{ uomList.length }}
        </VChip>
      </div>
      <VBtn
        v-if="$can('create', 'Catalog')"
        size="small"
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        Add UOM
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
        v-else-if="uomList.length === 0"
        type="info"
        variant="tonal"
      >
        No units of measure yet. Click "Add UOM" to create one.
      </VAlert>

      <!-- UOM List -->
      <template v-else>
        <VTable
          density="compact"
          class="mb-4"
        >
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Type</th>
              <th style="inline-size: 120px;">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="u in currentItems"
              :key="u.id"
            >
              <td>
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    size="32"
                    rounded
                    variant="tonal"
                    :color="resolveUomTypeColor(u.type)"
                  >
                    <VIcon
                      :icon="resolveUomTypeIcon(u.type)"
                      size="18"
                    />
                  </VAvatar>
                  <span class="font-weight-medium font-mono text-disabled">{{ u.code }}</span>
                </div>
              </td>
              <td class="font-weight-medium">
                {{ localizedValue(u.name, 'Untitled') }}
              </td>
              <td>
                <VChip
                  size="x-small"
                  label
                  variant="tonal"
                  :color="resolveUomTypeColor(u.type)"
                  :prepend-icon="resolveUomTypeIcon(u.type)"
                >
                  {{ getTypeTitle(u.type) }}
                </VChip>
              </td>
              <td>
                <IconBtn
                  size="x-small"
                  @click="openView(u)"
                >
                  <VIcon
                    icon="tabler-eye"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('edit', 'Catalog')"
                  size="x-small"
                  @click="openEdit(u)"
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
                  @click="handleDelete(u)"
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

  <!-- 👉 UOM Add/Edit/View Dialog -->
  <VDialog
    v-model="dialogVisible"
    max-width="500"
    persistent
  >
    <DialogCloseBtn @click="dialogVisible = false" />
    <VCard :title="dialogMode === 'add' ? 'Add Unit of Measure' : dialogMode === 'edit' ? 'Edit Unit of Measure' : 'View Unit of Measure'">
      <VCardText>
        <VForm
          ref="formRef"
          @submit.prevent="handleSave"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.code"
                label="Code *"
                variant="outlined"
                placeholder="e.g. kg, L, pcs"
                :disabled="isReadOnly || dialogMode === 'edit'"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.type"
                label="Type *"
                :items="typeOptions"
                variant="outlined"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol cols="12">
              <MultiLangField
                v-model="formData.name"
                label="Name *"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
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
</template>
