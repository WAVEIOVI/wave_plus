/* eslint-disable camelcase */
import { useWorkwearDocumentOptions } from '@/composables/client-assets/workwear/useWorkwearDocumentOptions'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useWorkwearItemEdit(itemId) {
  const store = useClientAssetStore()
  const { showMessage } = useAppSnackbar()
  const router = useRouter()
  const { t } = useI18n()

  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)
  const itemData = ref(null)
  const originalData = ref(null)
  const activeSection = ref('bio')
  const serverErrors = ref({})

  const supplierOptions = ref([])
  const modelStockMatrix = ref([])
  const employeeOptions = ref([])

  const isImageDialogOpen = ref(false)
  const isUploadingPhoto = ref(false)
  const isViewerOpen = ref(false)
  const viewerFile = ref(null)

  const isAssignDialogOpen = ref(false)
  const isStolenDialogOpen = ref(false)
  const isDeleteDialogVisible = ref(false)

  const assignForm = ref({ employee_id: null, notes: '' })
  const stolenForm = ref({ stolen_notes: '' })

  const statusMap = {
    in_stock: 'success',
    available: 'success',
    assigned: 'info',
    returned: 'secondary',
    repair_needed: 'warning',
    retired: 'warning',
    lost: 'error',
    stolen: 'error',
  }

  const conditionMap = {
    new: 'success',
    good: 'info',
    fair: 'warning',
    worn: 'secondary',
    damaged: 'error',
  }

  const conditionOptions = computed(() => [
    { title: t('New'), value: 'new' },
    { title: t('Good'), value: 'good' },
    { title: t('Fair'), value: 'fair' },
    { title: t('Worn'), value: 'worn' },
    { title: t('Damaged'), value: 'damaged' },
  ])

  const statusOptions = computed(() => [
    { title: t('In Stock'), value: 'in_stock' },
    { title: t('Available'), value: 'available' },
    { title: t('Assigned'), value: 'assigned' },
    { title: t('Returned'), value: 'returned' },
    { title: t('Repair Needed'), value: 'repair_needed' },
    { title: t('Retired'), value: 'retired' },
    { title: t('Lost'), value: 'lost' },
    { title: t('Stolen'), value: 'stolen' },
  ])

  const assignments = computed(() => itemData.value?.assignments || [])

  const fieldErrors = field => serverErrors.value[field] || []

  const isDirty = computed(() => {
    if (!originalData.value || !itemData.value) return false

    return JSON.stringify(itemData.value) !== JSON.stringify(originalData.value)
  })

  const {
    loading: documentsLoading,
    salesOrderItems,
    deliveryNoteItems,
  } = useWorkwearDocumentOptions(
    computed(() => itemData.value?.client_id),
    computed(() => ({ salesOrder: itemData.value?.sales_order, deliveryNote: itemData.value?.delivery_note })),
  )

  const loadSupplierOptions = async () => {
    try {
      supplierOptions.value = await store.fetchSupplierOptions({ limit: 150 }).catch(() => [])
    } catch (err) {
      console.error('Failed to load suppliers:', err)
    }
  }

  const fetchItem = async () => {
    const id = toValue(itemId)
    if (!id) return
    loading.value = true
    error.value = null
    try {
      const data = await store.fetchWorkwearItem(id)

      // Map existing item_option_values into selected_options dictionary
      const selectedOptions = {}
      if (Array.isArray(data?.item_option_values)) {
        data.item_option_values.forEach(iov => {
          if (iov.option_id && iov.value_id) {
            selectedOptions[iov.option_id] = iov.value_id
          }
        })
      }

      const hydrated = {
        ...data,
        selected_options: selectedOptions,
      }

      itemData.value = hydrated
      originalData.value = JSON.parse(JSON.stringify(hydrated))

      if (data?.model_id) {
        modelStockMatrix.value = await store.fetchWorkwearStock({ model_id: data.model_id }).catch(() => [])
      }
    } catch (err) {
      error.value = err?.data?.message || t('Failed to load item')
    } finally {
      loading.value = false
    }
  }

  const handleUpdate = async () => {
    const id = toValue(itemId)
    if (!id || !itemData.value) return false
    saving.value = true
    serverErrors.value = {}
    try {
      const optionValueIds = Object.values(itemData.value.selected_options || {}).filter(Boolean)

      await store.updateWorkwearItem(id, {
        size: itemData.value.size || null,
        color: itemData.value.color || null,
        item_condition: itemData.value.item_condition,
        status: itemData.value.status,
        notes: itemData.value.notes,
        sales_order_id: itemData.value.sales_order_id || null,
        delivery_note_id: itemData.value.delivery_note_id || null,
        option_value_ids: optionValueIds,
      })
      await fetchItem()
      showMessage(t('Item updated successfully'), 'success')

      return true
    } catch (err) {
      serverErrors.value = err?.data?.errors || {}
      showMessage(err?.data?.message || t('Failed to update'), 'error')

      return false
    } finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    const id = toValue(itemId)
    if (!id) return false
    try {
      await store.deleteWorkwearItem(id)
      showMessage(t('Item deleted successfully'), 'success')
      router.push({ name: 'client-assets-workwear-list', query: { tab: 'items' } })

      return true
    } catch (err) {
      showMessage(err?.data?.message || t('Failed to delete item'), 'error')

      return false
    }
  }

  const handleUploadPhoto = async payload => {
    const id = toValue(itemId)
    if (!id || !payload) return
    const rawFile = payload instanceof File || payload instanceof Blob ? payload : payload?.file
    if (!rawFile) return

    const formData = new FormData()

    formData.append('file', rawFile, rawFile.name || 'photo.jpg')
    formData.append('collection', 'photos')
    if (payload?.title) formData.append('title', payload.title)

    isUploadingPhoto.value = true
    try {
      await store.uploadItemMedia(id, formData)
      showMessage(t('Product photo uploaded'), 'success')
      isImageDialogOpen.value = false
      await fetchItem()
    } catch (err) {
      showMessage(err?.data?.message || t('Failed to upload photo'), 'error')
    } finally {
      isUploadingPhoto.value = false
    }
  }

  const handleDeletePhoto = async mediaId => {
    const id = toValue(itemId)
    if (!id || !mediaId) return
    try {
      await store.deleteItemMedia(id, mediaId)
      showMessage(t('Photo removed'), 'success')
      await fetchItem()
    } catch (err) {
      showMessage(err?.data?.message || t('Failed to delete photo'), 'error')
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

  const openAssignDialog = async () => {
    assignForm.value = { employee_id: null, notes: '' }
    try {
      await store.fetchWorkwearEmployees({
        itemsPerPage: -1,
        'filter[client_id]': itemData.value?.client_id || undefined,
      })
      employeeOptions.value = store.workwearEmployees.map(e => ({ title: e.full_name, value: e.id }))
    } catch (e) {
      console.error(e)
    }
    isAssignDialogOpen.value = true
  }

  const handleAssign = async () => {
    const id = toValue(itemId)
    if (!id) return false
    saving.value = true
    try {
      await store.assignWorkwearItem(id, assignForm.value)
      showMessage(t('Item assigned successfully'), 'success')
      isAssignDialogOpen.value = false
      await fetchItem()

      return true
    } catch (err) {
      showMessage(err?.data?.message || t('Assign failed'), 'error')

      return false
    } finally {
      saving.value = false
    }
  }

  const openStolenDialog = () => {
    stolenForm.value = { stolen_notes: '' }
    isStolenDialogOpen.value = true
  }

  const handleFlagStolen = async () => {
    const id = toValue(itemId)
    if (!id) return false
    saving.value = true
    try {
      await store.flagStolenWorkwearItem(id, stolenForm.value)
      showMessage(t('Item flagged as stolen'), 'success')
      isStolenDialogOpen.value = false
      await fetchItem()

      return true
    } catch (err) {
      showMessage(err?.data?.message || t('Failed to flag stolen'), 'error')

      return false
    } finally {
      saving.value = false
    }
  }

  const handleQr = () => {
    const id = toValue(itemId)
    if (id) store.downloadItemQr(id)
  }

  return {
    loading,
    saving,
    error,
    itemData,
    originalData,
    activeSection,
    serverErrors,
    supplierOptions,
    modelStockMatrix,
    employeeOptions,
    assignments,
    statusMap,
    conditionMap,
    conditionOptions,
    statusOptions,
    isDirty,
    fieldErrors,
    documentsLoading,
    salesOrderItems,
    deliveryNoteItems,

    // Dialogs
    isImageDialogOpen,
    isUploadingPhoto,
    isViewerOpen,
    viewerFile,
    isAssignDialogOpen,
    isStolenDialogOpen,
    isDeleteDialogVisible,
    assignForm,
    stolenForm,

    // Actions
    fetchItem,
    handleUpdate,
    handleDelete,
    handleUploadPhoto,
    handleDeletePhoto,
    handlePreviewPhoto,
    openAssignDialog,
    handleAssign,
    openStolenDialog,
    handleFlagStolen,
    handleQr,
    loadSupplierOptions,
  }
}
