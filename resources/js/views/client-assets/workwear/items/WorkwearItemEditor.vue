<script setup>
/* eslint-disable camelcase */
import { WpImageDialog, WpMediaViewerDialog } from '@/components/shared'
import { useWorkwearDocumentOptions } from '@/composables/client-assets/workwear/useWorkwearDocumentOptions'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { $api } from '@/utils/api'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const route = useRoute()
const router = useRouter()
const itemId = computed(() => props.id || route.params.id)
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { smAndDown } = useDisplay()

const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const itemData = ref(null)
const originalData = ref(null)
const activeSection = ref('bio')

const supplierOptions = ref([])
const modelStockMatrix = ref([])
const isImageDialogOpen = ref(false)
const isUploadingPhoto = ref(false)
const isViewerOpen = ref(false)
const viewerFile = ref(null)

const isAssignDialogOpen = ref(false)
const isStolenDialogOpen = ref(false)
const assignForm = ref({ employee_id: null, notes: '' })
const stolenForm = ref({ stolen_notes: '' })
const employeeOptions = ref([])

const assignments = computed(() => itemData.value?.assignments || [])

const serverErrors = ref({})
const fieldErrors = field => serverErrors.value[field] || []

const { loading: documentsLoading, salesOrderItems, deliveryNoteItems } = useWorkwearDocumentOptions(
  computed(() => itemData.value?.client_id),
  computed(() => ({ salesOrder: itemData.value?.sales_order, deliveryNote: itemData.value?.delivery_note })),
)

const sections = [
  { id: 'bio', label: 'General Info & SKU', icon: 'tabler-info-circle' },
  { id: 'media', label: 'Product Media & Model', icon: 'tabler-photo' },
  { id: 'history', label: 'Assignment History', icon: 'tabler-history' },
  { id: 'notes', label: 'Notes', icon: 'tabler-notes' },
]

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const statusMap = { in_stock: 'success', assigned: 'info', returned: 'secondary', retired: 'warning', lost: 'error', stolen: 'error' }
const conditionMap = { new: 'success', good: 'info', fair: 'warning', worn: 'secondary', damaged: 'error' }
const conditionOptions = ['new', 'good', 'fair', 'worn', 'damaged'].map(c => ({ title: c.replace(/\b\w/g, l => l.toUpperCase()), value: c }))
const statusOptions = ['in_stock', 'assigned', 'returned', 'retired', 'lost', 'stolen'].map(s => ({ title: s.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), value: s }))

onMounted(async () => {
  try {
    const suppliersRes = await $api('/bp/suppliers/options', { params: { limit: 150 } }).catch(() => ({ data: [] }))

    supplierOptions.value = (suppliersRes.data || []).map(s => ({ title: s.legal_name, value: s.id }))
  } catch (err) {
    console.error(err)
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => { if (entry.isIntersecting) activeSection.value = entry.target.id.replace('section-', '') })
  }, { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' })

  nextTick(() => { sections.forEach(s => { const el = document.getElementById(`section-${s.id}`); if (el) observer.observe(el) }) })
})

const fetchItem = async () => {
  if (!itemId.value) return
  loading.value = true
  try {
    const data = await store.fetchWorkwearItem(itemId.value)

    itemData.value = { ...data }
    originalData.value = JSON.parse(JSON.stringify(data))

    if (data?.model_id) {
      modelStockMatrix.value = await store.fetchWorkwearStock({ model_id: data.model_id }).catch(() => [])
    }
  }
  catch (err) { error.value = err?.data?.message || t('Failed to load item') }
  finally { loading.value = false }
}

const handleUpdate = async () => {
  if (!itemId.value) return
  saving.value = true
  serverErrors.value = {}
  try {
    await store.updateWorkwearItem(itemId.value, {
      sku: itemData.value.sku || null,
      garment_type: itemData.value.garment_type,
      size: itemData.value.size,
      color: itemData.value.color || null,
      supplier_id: itemData.value.supplier_id || null,
      unit_cost: itemData.value.unit_cost !== '' && itemData.value.unit_cost !== null ? Number(itemData.value.unit_cost) : null,
      item_condition: itemData.value.item_condition,
      status: itemData.value.status,
      notes: itemData.value.notes,
      sales_order_id: itemData.value.sales_order_id || null,
      delivery_note_id: itemData.value.delivery_note_id || null,
    })
    await fetchItem()
    snackbarStore.showMessage(t('Item updated successfully'), 'success')
  }
  catch (err) {
    serverErrors.value = err?.data?.errors || {}
    snackbarStore.showMessage(err?.data?.message || t('Failed to update'), 'error')
  }
  finally { saving.value = false }
}

const handleUploadPhoto = async payload => {
  if (!itemId.value || !payload) return
  const rawFile = payload instanceof File || payload instanceof Blob ? payload : payload?.file
  if (!rawFile) return

  const formData = new FormData()

  formData.append('file', rawFile, rawFile.name || 'photo.jpg')
  formData.append('collection', 'photos')
  if (payload?.title) formData.append('title', payload.title)

  isUploadingPhoto.value = true
  try {
    await store.uploadItemMedia(itemId.value, formData)
    snackbarStore.showMessage(t('Product photo uploaded'), 'success')
    isImageDialogOpen.value = false
    await fetchItem()
  } catch (err) {
    snackbarStore.showMessage(err?.data?.message || t('Failed to upload photo'), 'error')
  } finally {
    isUploadingPhoto.value = false
  }
}

const handleDeletePhoto = async mediaId => {
  if (!itemId.value || !mediaId) return
  try {
    await store.deleteItemMedia(itemId.value, mediaId)
    snackbarStore.showMessage(t('Photo removed'), 'success')
    await fetchItem()
  } catch (err) {
    snackbarStore.showMessage(err?.data?.message || t('Failed to delete photo'), 'error')
  }
}

const handlePreviewPhoto = photo => {
  viewerFile.value = {
    title: photo.name || 'Garment Photo',
    url: photo.url || photo.thumb,
    downloadUrl: photo.url,
    mimeType: 'image/jpeg',
  }
  isViewerOpen.value = true
}

const isDeleteDialogVisible = ref(false)

const handleDelete = async () => {
  if (!itemId.value) return
  try {
    await store.deleteWorkwearItem(itemId.value)
    snackbarStore.showMessage(t('Item deleted'), 'success')
    router.push({ name: 'client-assets-workwear-list', query: { tab: 'items' } })
  }
  catch (err) { snackbarStore.showMessage(t('Failed to delete'), 'error') }
}

// Actions
const openAssignDialog = async () => {
  assignForm.value = { employee_id: null, notes: '' }
  try {
    await store.fetchWorkwearEmployees({ itemsPerPage: -1 })
    employeeOptions.value = store.workwearEmployees.map(e => ({ title: e.full_name, value: e.id }))
  } catch (e) { console.error(e) }
  isAssignDialogOpen.value = true
}

const handleAssign = async () => {
  saving.value = true
  try {
    await store.assignWorkwearItem(route.params.id, assignForm.value)
    snackbarStore.showMessage(t('Item assigned successfully'), 'success')
    isAssignDialogOpen.value = false
    await fetchItem()
  } catch (err) { snackbarStore.showMessage(err?.data?.message || t('Assign failed'), 'error') }
  finally { saving.value = false }
}

const openStolenDialog = () => { stolenForm.value = { stolen_notes: '' }; isStolenDialogOpen.value = true }

const handleFlagStolen = async () => {
  saving.value = true
  try {
    await store.flagStolenWorkwearItem(route.params.id, stolenForm.value)
    snackbarStore.showMessage(t('Item flagged as stolen'), 'success')
    isStolenDialogOpen.value = false
    await fetchItem()
  } catch (err) { snackbarStore.showMessage(err?.data?.message || t('Failed to flag stolen'), 'error') }
  finally { saving.value = false }
}

const handleQr = () => { store.downloadItemQr(route.params.id) }

const unsavedDialogVisible = ref(false)
const nextRouteRef = ref(null)

const isDirty = computed(() => { if (!originalData.value) return false 

  return JSON.stringify(itemData.value) !== JSON.stringify(originalData.value) })

onBeforeRouteLeave((to, from, next) => { if (isDirty.value) { unsavedDialogVisible.value = true; next(false); nextRouteRef.value = to } else { next() } })

const confirmLeave = () => { originalData.value = JSON.parse(JSON.stringify(itemData.value)); unsavedDialogVisible.value = false; router.push(nextRouteRef.value.fullPath) }

onMounted(fetchItem)
</script>

<template>
  <div>
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="$t('Home')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'client-assets-workwear-list', query: { tab: 'items' } }"
        :title="$t('Workwear')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="itemData?.item_code || $t('Loading...')"
        disabled
      />
    </VBreadcrumbs>

    <VSkeletonLoader
      v-if="loading && !itemData"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ error }}
    </VAlert>

    <div
      v-else-if="itemData"
      class="journal-layout"
    >
      <!-- Sidebar -->
      <nav
        v-if="!smAndDown"
        class="journal-nav glass-panel"
      >
        <div class="d-flex flex-column align-center text-center mb-4">
          <VAvatar
            rounded
            :size="64"
            :color="itemData.is_stolen ? 'error' : 'primary'"
            variant="tonal"
            class="mb-3"
          >
            <VIcon
              :icon="itemData.is_stolen ? 'tabler-alert-octagon' : 'tabler-hanger'"
              size="32"
            />
          </VAvatar>
          <h6 class="text-subtitle-1 font-weight-bold">
            {{ itemData.item_code }}
          </h6>
          <div class="d-flex gap-1 mt-1 justify-center flex-wrap">
            <VChip
              v-if="itemData.is_stolen"
              color="error"
              label
              size="x-small"
            >
              STOLEN
            </VChip>
            <VChip
              :color="statusMap[itemData.status] || 'secondary'"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ itemData.status?.replace(/_/g, ' ') }}
            </VChip>
          </div>
        </div>
        <VDivider class="mb-3" />
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          class="nav-scroll-area"
        >
          <VList
            density="compact"
            nav
            class="bg-transparent mb-4"
          >
            <VListItem
              v-for="s in sections"
              :key="s.id"
              :active="activeSection === s.id"
              color="primary"
              rounded="lg"
              @click="scrollToSection(s.id)"
            >
              <template #prepend>
                <VIcon
                  :icon="s.icon"
                  size="18"
                />
              </template>
              <VListItemTitle class="text-body-2">
                {{ $t(s.label) }}
              </VListItemTitle>
            </VListItem>
          </VList>
          
          <VDivider class="mb-4" />
          
          <!-- Actions Menu -->
          <div class="d-flex flex-column gap-2">
            <VBtn
              block
              color="info"
              variant="tonal"
              prepend-icon="tabler-user-plus"
              :disabled="itemData.status === 'assigned'"
              @click="openAssignDialog"
            >
              {{ $t('Assign') }}
            </VBtn>
            <VBtn
              block
              color="warning"
              variant="tonal"
              prepend-icon="tabler-qrcode"
              @click="handleQr"
            >
              {{ $t('QR Code') }}
            </VBtn>
            <VBtn
              v-if="!itemData.is_stolen"
              block
              color="error"
              variant="tonal"
              prepend-icon="tabler-alert-octagon"
              @click="openStolenDialog"
            >
              {{ $t('Flag Stolen') }}
            </VBtn>
          </div>
        </PerfectScrollbar>
        
        <div class="mt-auto pt-4">
          <VDivider class="mb-4" />
          <VBtn
            block
            color="primary"
            :disabled="saving || !isDirty"
            :loading="saving"
            class="mb-2"
            @click="handleUpdate"
          >
            <VIcon
              start
              icon="tabler-device-floppy"
            />{{ $t('Save Changes') }}
          </VBtn>
          <VBtn
            block
            variant="tonal"
            color="error"
            @click="isDeleteDialogVisible = true"
          >
            <VIcon
              start
              icon="tabler-trash"
            />{{ $t('Delete') }}
          </VBtn>
        </div>
      </nav>

      <!-- Feed -->
      <main class="journal-feed">
        <div
          v-if="smAndDown"
          class="d-flex justify-space-between align-center mb-4"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              rounded
              :size="36"
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-hanger" />
            </VAvatar>
            <h6 class="text-subtitle-1 font-weight-bold">
              {{ itemData.item_code }}
            </h6>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              color="primary"
              :disabled="saving || !isDirty"
              :loading="saving"
              @click="handleUpdate"
            >
              {{ $t('Save') }}
            </VBtn>
            <VMenu>
              <template #activator="{ props: activatorProps }">
                <VBtn
                  icon
                  variant="tonal"
                  color="secondary"
                  size="small"
                  v-bind="activatorProps"
                >
                  <VIcon icon="tabler-dots-vertical" />
                </VBtn>
              </template>
              <VList>
                <VListItem
                  prepend-icon="tabler-user-plus"
                  :disabled="itemData.status === 'assigned'"
                  @click="openAssignDialog"
                >
                  <VListItemTitle>Assign</VListItemTitle>
                </VListItem>
                <VListItem
                  prepend-icon="tabler-qrcode"
                  @click="handleQr"
                >
                  <VListItemTitle>QR Code</VListItemTitle>
                </VListItem>
                <VListItem
                  v-if="!itemData.is_stolen"
                  prepend-icon="tabler-alert-octagon"
                  base-color="error"
                  @click="openStolenDialog"
                >
                  <VListItemTitle>Flag Stolen</VListItemTitle>
                </VListItem>
                <VListItem
                  prepend-icon="tabler-trash"
                  base-color="error"
                  @click="isDeleteDialogVisible = true"
                >
                  <VListItemTitle>Delete</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </div>

        <!-- General Info -->
        <section
          id="section-bio"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-info-circle"
                  size="20"
                  class="me-2"
                />{{ $t('General Information') }}
              </h6>
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="itemData.sku"
                    :label="$t('SKU / Variant Code')"
                    :error-messages="fieldErrors('sku')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="itemData.garment_type"
                    :label="$t('Garment Type')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="2"
                >
                  <AppTextField
                    v-model="itemData.size"
                    :label="$t('Size')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="2"
                >
                  <AppTextField
                    v-model="itemData.color"
                    :label="$t('Color')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppAutocomplete
                    v-model="itemData.supplier_id"
                    :items="supplierOptions"
                    :label="$t('Supplier / Manufacturer')"
                    clearable
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model.number="itemData.unit_cost"
                    type="number"
                    step="0.001"
                    min="0"
                    prefix="TND"
                    :label="$t('Unit Cost')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="2"
                >
                  <AppSelect
                    v-model="itemData.item_condition"
                    :label="$t('Condition')"
                    :items="conditionOptions"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="2"
                >
                  <AppSelect
                    v-model="itemData.status"
                    :label="$t('Status')"
                    :items="statusOptions"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppAutocomplete
                    v-model="itemData.sales_order_id"
                    :label="$t('Sales Order (optional)')"
                    :items="salesOrderItems"
                    :loading="documentsLoading"
                    :error-messages="fieldErrors('sales_order_id')"
                    :placeholder="$t('No sales order')"
                    :no-data-text="$t('No sales orders for this client')"
                    clearable
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppAutocomplete
                    v-model="itemData.delivery_note_id"
                    :label="$t('Delivery Note (optional)')"
                    :items="deliveryNoteItems"
                    :loading="documentsLoading"
                    :error-messages="fieldErrors('delivery_note_id')"
                    :placeholder="$t('No delivery note')"
                    :no-data-text="$t('No delivery notes for this client')"
                    clearable
                    variant="outlined"
                  />
                </VCol>
              </VRow>
              <VDivider class="my-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { label: $t('Client'), value: itemData.client?.legal_name || '—' },
                    { label: $t('Linked Model'), value: itemData.model?.name || itemData.workwear_model?.name || '—' },
                    { label: $t('Assigned Staff'), value: itemData.current_assignment?.employee?.full_name || $t('In Stock / Unassigned') },
                    { label: $t('Created'), value: itemData.created_at ? new Date(itemData.created_at).toLocaleDateString() : '—' },
                  ]"
                  :key="item.label"
                  cols="12"
                  sm="3"
                >
                  <div class="detail-row">
                    <div>
                      <span class="text-caption text-disabled text-uppercase">{{ item.label }}</span>
                      <div class="text-body-2 font-weight-medium">
                        {{ item.value }}
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- Product Media & Linked Model Preview -->
        <section
          id="section-media"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <VRow>
                <!-- Left: Linked Model Illustration Preview & Variant Stock -->
                <VCol
                  cols="12"
                  md="5"
                >
                  <div class="d-flex justify-space-between align-center mb-3">
                    <h6 class="text-subtitle-1 font-weight-bold mb-0">
                      <VIcon
                        icon="tabler-shirt"
                        size="18"
                        class="me-1"
                      />{{ $t('Linked Model Specification') }}
                    </h6>
                    <VBtn
                      v-if="itemData.model_id"
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      :to="{ name: 'client-assets-workwear-edit-id', params: { id: itemData.model_id }, query: { type: 'model' } }"
                    >
                      {{ $t('View Model') }}
                    </VBtn>
                  </div>
                  <WorkwearGarmentIllustration
                    v-if="itemData.model"
                    :silhouette="itemData.model.silhouette || itemData.garment_type || 'coverall'"
                    :colors="itemData.model.colors?.length ? itemData.model.colors : [itemData.color || 'navy', 'hi-vis-yellow']"
                    :placements="itemData.model.placements || []"
                    :logos="itemData.model.logos || []"
                    readonly
                    compact
                  />
                  <div
                    v-if="modelStockMatrix.length"
                    class="mt-3"
                  >
                    <div class="text-caption font-weight-bold text-uppercase text-disabled mb-1">
                      {{ $t('Sibling Variant Stock Availability') }}
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-for="(st, sIdx) in modelStockMatrix"
                        :key="sIdx"
                        size="x-small"
                        :color="st.available > 0 ? 'success' : 'secondary'"
                        variant="tonal"
                      >
                        {{ st.size || 'STD' }}{{ st.color ? ` (${st.color})` : '' }}: {{ st.available }}/{{ st.total }}
                      </VChip>
                    </div>
                  </div>
                </VCol>

                <!-- Right: Product Photos Gallery -->
                <VCol
                  cols="12"
                  md="7"
                >
                  <div class="d-flex justify-space-between align-center mb-3">
                    <div>
                      <h6 class="text-subtitle-1 font-weight-bold mb-0">
                        <VIcon
                          icon="tabler-photo"
                          size="18"
                          class="me-1"
                        />{{ $t('Product Media & Inspection Photos') }}
                      </h6>
                      <span class="text-caption text-medium-emphasis">
                        {{ $t('Upload actual garment photos, barcode tags, or condition snapshots.') }}
                      </span>
                    </div>
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-upload"
                      :loading="isUploadingPhoto"
                      @click="isImageDialogOpen = true"
                    >
                      {{ $t('Upload Photo') }}
                    </VBtn>
                  </div>

                  <div
                    v-if="itemData.photos?.length"
                    class="d-flex flex-wrap gap-3"
                  >
                    <VCard
                      v-for="photo in itemData.photos"
                      :key="photo.id"
                      variant="outlined"
                      class="pa-2 d-flex flex-column align-center cursor-pointer"
                      style="inline-size: 135px;"
                      @click="handlePreviewPhoto(photo)"
                    >
                      <VImg
                        :src="photo.thumb || photo.url"
                        height="85"
                        width="115"
                        cover
                        class="rounded mb-2"
                      />
                      <span class="text-caption text-truncate w-100 text-center">{{ photo.name }}</span>
                      <VBtn
                        size="x-small"
                        variant="text"
                        color="error"
                        class="mt-1"
                        @click.stop="handleDeletePhoto(photo.id)"
                      >
                        {{ $t('Remove') }}
                      </VBtn>
                    </VCard>
                  </div>
                  <div
                    v-else
                    class="text-center py-8 border rounded text-disabled"
                  >
                    <VIcon
                      icon="tabler-camera-plus"
                      size="32"
                      class="mb-1"
                    />
                    <p class="text-caption mb-0">
                      {{ $t('No product photos uploaded for this item yet.') }}
                    </p>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- History -->
        <section
          id="section-history"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-history"
                  size="20"
                  class="me-2"
                />{{ $t('Assignment History') }}
              </h6>
              <VTable
                v-if="assignments.length"
                density="compact"
                class="text-no-wrap bg-transparent"
              >
                <thead><tr><th>{{ $t('Employee') }}</th><th>{{ $t('Assigned') }}</th><th>{{ $t('Returned') }}</th><th>{{ $t('Status') }}</th></tr></thead>
                <tbody>
                  <tr
                    v-for="a in assignments"
                    :key="a.id"
                  >
                    <td>{{ a.employee?.full_name || '—' }}</td>
                    <td>{{ a.assigned_at ? new Date(a.assigned_at).toLocaleDateString() : '—' }}</td>
                    <td>{{ a.returned_at ? new Date(a.returned_at).toLocaleDateString() : '—' }}</td>
                    <td>
                      <VChip
                        size="small"
                        label
                        :color="a.returned_at ? 'secondary' : 'success'"
                      >
                        {{ a.returned_at ? 'Returned' : 'Active' }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <div
                v-else
                class="text-center py-6 text-disabled"
              >
                <VIcon
                  icon="tabler-history"
                  size="32"
                  class="mb-2"
                />
                <p>{{ $t('No assignment history') }}</p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- Notes -->
        <section
          id="section-notes"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-notes"
                  size="20"
                  class="me-2"
                />{{ $t('Notes') }}
              </h6>
              <AppTextarea
                v-model="itemData.notes"
                :label="$t('Notes')"
                rows="4"
                variant="outlined"
              />
            </VCardText>
          </VCard>
        </section>
      </main>
    </div>

    <!-- Modals -->
    <VDialog
      v-model="isDeleteDialogVisible"
      max-width="420"
    >
      <VCard class="pa-4">
        <VCardText class="text-center">
          <VIcon
            icon="tabler-alert-triangle"
            size="48"
            color="warning"
            class="mb-4"
          /><h6 class="text-lg font-weight-medium mb-2">
            {{ $t('Delete Item') }}
          </h6><p class="text-body-2 mb-0">
            {{ $t('Are you sure? This cannot be undone.') }}
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer /><VBtn
            variant="tonal"
            color="secondary"
            @click="isDeleteDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn><VBtn
            color="error"
            @click="handleDelete"
          >
            {{ $t('Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    
    <VDialog
      v-model="isAssignDialogOpen"
      max-width="500"
    >
      <VCard :title="$t('Assign to Employee')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppAutocomplete
                v-model="assignForm.employee_id"
                :items="employeeOptions"
                :label="$t('Employee *')"
                :placeholder="$t('Select employee')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="assignForm.notes"
                :label="$t('Notes')"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer /><VBtn
            variant="tonal"
            @click="isAssignDialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn><VBtn
            color="primary"
            :loading="saving"
            :disabled="!assignForm.employee_id"
            @click="handleAssign"
          >
            {{ $t('Assign') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isStolenDialogOpen"
      max-width="450"
    >
      <VCard :title="$t('Flag as Stolen')">
        <VCardText>
          <VIcon
            icon="tabler-alert-octagon"
            size="40"
            color="error"
            class="mb-4"
          />
          <p>{{ $t('This will flag the item as stolen and update its status.') }}</p>
          <AppTextarea
            v-model="stolenForm.stolen_notes"
            :label="$t('Stolen Notes')"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer /><VBtn
            variant="tonal"
            @click="isStolenDialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn><VBtn
            color="error"
            :loading="saving"
            @click="handleFlagStolen"
          >
            {{ $t('Flag Stolen') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="unsavedDialogVisible"
      max-width="450"
    >
      <VCard class="glass-panel">
        <VCardText class="text-center px-10 py-6">
          <VIcon
            icon="tabler-alert-triangle"
            size="48"
            color="warning"
            class="mb-4"
          /><h6 class="text-lg font-weight-medium mb-2">
            {{ $t('Unsaved Changes') }}
          </h6><p class="text-body-2 mb-0">
            {{ $t('You have unsaved changes. Do you really want to leave without saving?') }}
          </p>
        </VCardText>
        <VCardText class="d-flex justify-center gap-4 pb-6">
          <VBtn
            color="warning"
            @click="confirmLeave"
          >
            {{ $t('Leave') }}
          </VBtn><VBtn
            variant="tonal"
            color="secondary"
            @click="unsavedDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('Upload Garment Product Photo')"
      show-title-input
      @save="handleUploadPhoto"
    />

    <WpMediaViewerDialog
      v-model:model-value="isViewerOpen"
      :file="viewerFile"
    />

    <VSnackbar
      v-model="snackbarStore.show"
      :color="snackbarStore.color"
      :timeout="snackbarStore.timeout"
      transition="scale-transition"
      variant="tonal"
    >
      <div style="text-align: center;">
        {{ snackbarStore.text }}
      </div>
    </VSnackbar>
  </div>
</template>

<style lang="scss" scoped>
.journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;
  min-block-size: 100vh;

  @media (max-width: 960px) { flex-direction: column; padding-block-end: 80px; }
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow: 0 4px 32px rgba(0, 0, 0, 6%), 0 1px 4px rgba(0, 0, 0, 4%), inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow: 0 8px 40px rgba(0, 0, 0, 10%), 0 2px 8px rgba(0, 0, 0, 6%), inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
}

.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 240px;
  flex-direction: column;
  padding: 1.25rem;
  inline-size: 240px;
  inset-block-start: 80px;
  max-block-size: calc(100vh - 100px);
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 320px);
}

.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.journal-section {
  scroll-margin-block-start: 80px;

  @media (max-width: 960px) { scroll-margin-block-start: 16px; }
}

.detail-row {
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: 10px;
  gap: 12px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
