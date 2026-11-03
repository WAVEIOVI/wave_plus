<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import WpLogoDialog from '@/components/shared/branding/WpLogoDialog.vue'
import { usePagination } from '@/composables/usePagination'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, onMounted, ref } from 'vue'

const supportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()

const loading = ref(false)
const saving = ref(false)
const brands = ref([])

// ── Pagination ──────────────────────────────────────────────────────────
const itemsPerPage = ref(10)
const { page, totalPages, currentItems } = usePagination(brands, itemsPerPage)

// ── Dialog state ────────────────────────────────────────────────────────
const dialogVisible = ref(false)
const dialogMode = ref('add') // 'add' | 'edit' | 'view'
const editingBrand = ref(null)
const formRef = ref(null)
const logoDialogOpen = ref(false)

const formData = ref({
  name: '',
  code: '',
  country_of_origin: '',
  warranty_policy_notes: '',
  logo_source: '',
  logo_domain: '',
  logo_external_url: '',
  logo_url: '',
  logo_file: null,
})

const isReadOnly = computed(() => dialogMode.value === 'view')

// ── Fetch ───────────────────────────────────────────────────────────────
const fetchBrands = async () => {
  loading.value = true
  try {
    const response = await supportStore.fetchBrands()

    brands.value = response?.data || []
  }
  catch (err) {
    console.error('Error fetching brands:', err)
  }
  finally {
    loading.value = false
  }
}

// ── Logo Handling ───────────────────────────────────────────────────────
const handleLogoSaved = payload => {
  if (payload.source === 'upload' && payload.file) {
    formData.value.logo_file = payload.file
    formData.value.logo_url = payload.url
    formData.value.logo_source = 'upload'
  }
  else {
    formData.value.logo_file = null
    formData.value.logo_url = payload.url || ''
    formData.value.logo_source = payload.source || ''
    formData.value.logo_domain = payload.domain || ''
    formData.value.logo_external_url = payload.url || ''
  }
}

// ── CRUD ────────────────────────────────────────────────────────────────
const openAdd = () => {
  dialogMode.value = 'add'
  editingBrand.value = null
  formData.value = {
    name: '',
    code: '',
    country_of_origin: '',
    warranty_policy_notes: '',
    logo_source: '',
    logo_domain: '',
    logo_external_url: '',
    logo_url: '',
    logo_file: null,
  }
  dialogVisible.value = true
}

const openEdit = brand => {
  dialogMode.value = 'edit'
  editingBrand.value = brand
  formData.value = {
    name: brand.name || '',
    code: brand.code || '',
    country_of_origin: brand.country_of_origin || '',
    warranty_policy_notes: brand.warranty_policy_notes || '',
    logo_source: brand.logo_source || '',
    logo_domain: brand.logo_domain || '',
    logo_external_url: brand.logo_external_url || '',
    logo_url: brand.logo_url || brand.logo_external_url || '',
    logo_file: null,
  }
  dialogVisible.value = true
}

const openView = brand => {
  dialogMode.value = 'view'
  editingBrand.value = brand
  formData.value = {
    name: brand.name || '',
    code: brand.code || '',
    country_of_origin: brand.country_of_origin || '',
    warranty_policy_notes: brand.warranty_policy_notes || '',
    logo_source: brand.logo_source || '',
    logo_domain: brand.logo_domain || '',
    logo_external_url: brand.logo_external_url || '',
    logo_url: brand.logo_url || brand.logo_external_url || '',
    logo_file: null,
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
    let payload
    if (formData.value.logo_file) {
      payload = new FormData()
      payload.append('name', formData.value.name)
      payload.append('code', formData.value.code)
      if (formData.value.country_of_origin) payload.append('country_of_origin', formData.value.country_of_origin)
      if (formData.value.warranty_policy_notes) payload.append('warranty_policy_notes', formData.value.warranty_policy_notes)
      payload.append('logo', formData.value.logo_file)
    }
    else {
      payload = {
        name: formData.value.name,
        code: formData.value.code,
        country_of_origin: formData.value.country_of_origin,
        warranty_policy_notes: formData.value.warranty_policy_notes,
        logo_source: formData.value.logo_source,
        logo_domain: formData.value.logo_domain,
        logo_external_url: formData.value.logo_external_url,
      }
    }

    if (dialogMode.value === 'add') {
      await supportStore.createBrand(payload)
      snackbarStore.showMessage('Brand created successfully.', 'success')
    }
    else {
      await supportStore.updateBrand(editingBrand.value.id, payload)
      snackbarStore.showMessage('Brand updated successfully.', 'success')
    }
    dialogVisible.value = false
    await fetchBrands()
  }
  catch (err) {
    snackbarStore.showMessage(err?.data?.message || 'Failed to save brand.', 'error')
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async brand => {
  saving.value = true
  try {
    await supportStore.deleteBrand(brand.id)
    snackbarStore.showMessage('Brand deleted.', 'success')
    await fetchBrands()
  }
  catch (err) {
    snackbarStore.showMessage('Failed to delete brand.', 'error')
  }
  finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchBrands()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between py-3 px-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-brand-firebase"
          size="20"
        />
        Brands
        <VChip
          size="x-small"
          label
          color="primary"
        >
          {{ brands.length }}
        </VChip>
      </div>
      <VBtn
        v-if="$can('create', 'Catalog')"
        size="small"
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        Add Brand
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
        v-else-if="brands.length === 0"
        type="info"
        variant="tonal"
      >
        No brands yet. Click "Add Brand" to create one.
      </VAlert>

      <!-- Brand List -->
      <template v-else>
        <VTable
          density="compact"
          class="mb-4"
        >
          <thead>
            <tr>
              <th style="inline-size: 60px;">
                Logo
              </th>
              <th>Code</th>
              <th>Name</th>
              <th>Origin</th>
              <th style="inline-size: 120px;">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="b in currentItems"
              :key="b.id"
            >
              <td>
                <WpEntityLogo
                  :src="b.logo_url"
                  :name="b.name"
                  :domain="b.logo_domain"
                  :source="b.logo_source"
                  size="36"
                  border-radius="8px"
                />
              </td>
              <td class="text-disabled font-mono">
                {{ b.code }}
              </td>
              <td class="font-weight-medium">
                {{ b.name }}
              </td>
              <td>
                {{ b.country_of_origin || '—' }}
              </td>
              <td>
                <IconBtn
                  size="x-small"
                  @click="openView(b)"
                >
                  <VIcon
                    icon="tabler-eye"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  v-if="$can('edit', 'Catalog')"
                  size="x-small"
                  @click="openEdit(b)"
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
                  @click="handleDelete(b)"
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

  <!-- 👉 Brand Add/Edit/View Dialog -->
  <VDialog
    v-model="dialogVisible"
    max-width="540"
    persistent
  >
    <DialogCloseBtn @click="dialogVisible = false" />
    <VCard :title="dialogMode === 'add' ? 'Add Brand' : dialogMode === 'edit' ? 'Edit Brand' : 'View Brand'">
      <VCardText>
        <!-- Logo Section -->
        <div class="d-flex align-center gap-4 mb-5 pb-3 border-b">
          <div class="position-relative">
            <WpEntityLogo
              :src="formData.logo_url"
              :name="formData.name || 'Brand'"
              :domain="formData.logo_domain"
              :source="formData.logo_source"
              size="64"
              border-radius="12px"
            />
            <VBtn
              v-if="!isReadOnly"
              icon="tabler-camera"
              size="x-small"
              color="primary"
              class="position-absolute"
              style="inset-block-end: -6px; inset-inline-end: -6px;"
              @click="logoDialogOpen = true"
            />
          </div>
          <div>
            <div class="text-subtitle-2 font-weight-bold">
              Brand Logo
            </div>
            <div class="text-caption text-disabled mb-2">
              Brandfetch, Logo.dev or upload image
            </div>
            <VBtn
              v-if="!isReadOnly"
              size="x-small"
              variant="tonal"
              prepend-icon="tabler-photo-edit"
              @click="logoDialogOpen = true"
            >
              {{ formData.logo_url ? 'Change Logo' : 'Add Logo' }}
            </VBtn>
          </div>
        </div>

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
                v-model="formData.name"
                label="Brand Name *"
                variant="outlined"
                :disabled="isReadOnly"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.code"
                label="Code *"
                variant="outlined"
                :disabled="isReadOnly || dialogMode === 'edit'"
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.country_of_origin"
                label="Country of Origin"
                variant="outlined"
                placeholder="e.g. DE, US"
                :disabled="isReadOnly"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="formData.warranty_policy_notes"
                label="Warranty Policy Notes"
                variant="outlined"
                :rows="3"
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

  <!-- 👉 Logo Dialog (shared Wave Plus branding system) -->
  <WpLogoDialog
    v-model="logoDialogOpen"
    :initial-name="formData.name || ''"
    :initial-domain="formData.logo_domain || ''"
    :initial-logo-url="formData.logo_url || ''"
    :initial-source="formData.logo_source || ''"
    @save="handleLogoSaved"
    @error="error => snackbarStore.showMessage(error?.message || 'Logo error', 'error')"
  />
</template>
