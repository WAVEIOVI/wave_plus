/* eslint-disable camelcase */
import { extractEquipmentQrCode } from '@/composables/scanner/useQrScanner'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useEquipmentEditModals({
  form,
  fetchedEquipment,
  targetEquipmentId,
  selectedClient,
  isNew,
  fetchEquipment,
  handleClientSelected,
}) {
  const { t } = useI18n()
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const guidelineStore = useGuidelineStore()
  const snackbarStore = useSnackbarStore()

  // Modal visibilities
  const isDeleteDialogVisible = ref(false)
  const isTransferDialogOpen = ref(false)
  const isReplaceQrDialogOpen = ref(false)
  const isQrScannerOpen = ref(false)
  const isClientSearchModalVisible = ref(false)
  const isClassBlueprintModalVisible = ref(false)
  const isSiteAreaModalVisible = ref(false)
  const isImageDialogOpen = ref(false)

  // Guideline view modal
  const isGuidelineDialogOpen = ref(false)
  const selectedGuidelineForView = ref(null)

  const handleViewGuideline = async guideline => {
    selectedGuidelineForView.value = guideline
    isGuidelineDialogOpen.value = true
    if (guideline?.id) {
      try {
        const full = await guidelineStore.fetchGuideline(guideline.id)
        if (full) selectedGuidelineForView.value = full
      }
      catch (err) {
        console.warn('Failed to load full guideline:', err)
      }
    }
  }

  // Client view modal
  const isClientViewDialogOpen = ref(false)
  const selectedClientForView = ref(null)
  const loadingClientView = ref(false)

  const onClientPicked = async client => {
    isClientSearchModalVisible.value = false
    await handleClientSelected(client)
  }

  const handleViewClient = async () => {
    const clientId = form.value.client_id || selectedClient.value?.id
    if (!clientId) return

    loadingClientView.value = true
    isClientViewDialogOpen.value = true
    try {
      const data = await clientStore.fetchClient(clientId)

      selectedClientForView.value = data
    }
    catch (err) {
      console.warn('Failed to load full client for dialog:', err?.message)
      selectedClientForView.value = selectedClient.value
    }
    finally {
      loadingClientView.value = false
    }
  }

  // QR Scanned handler
  const handleQrScanned = result => {
    const code = extractEquipmentQrCode(result)
    if (code) {
      form.value.equipment_code = code
      isQrScannerOpen.value = false
      snackbarStore.showMessage(t('QR Code detected!'), 'success')
    }
  }

  // Photo viewer modal
  const isMediaViewerOpen = ref(false)
  const mediaViewerItems = ref([])
  const mediaViewerInitialIndex = ref(0)
  const mediaViewerTitle = ref('')

  const handleSavePhotoFromDialog = async payload => {
    const items = payload?.files?.length ? payload.files : (payload?.file ? [payload] : [])
    if (!items.length || isNew.value || !targetEquipmentId.value) return

    try {
      for (let i = 0; i < items.length; i++) {
        const item = items[i]
        const file = item.file || item
        const formData = new FormData()
        const fileName = file.name || `photo_${Date.now()}_${i + 1}.jpg`

        formData.append('collection', 'photos')
        formData.append('file', file, fileName)
        if (item.title || payload.title) formData.append('title', item.title || payload.title)
        if (item.is_main || (!fetchedEquipment.value?.photos?.length && i === 0)) {
          formData.append('is_main', '1')
        }

        await store.uploadEquipmentMedia(targetEquipmentId.value, formData)
      }

      snackbarStore.showMessage(
        items.length > 1 ? t('Photos uploaded successfully') : t('Photo uploaded successfully'),
        'success',
      )
      isImageDialogOpen.value = false
      await fetchEquipment()
    }
    catch (err) {
      console.error('Photo dialog upload error:', err)
      snackbarStore.showMessage(t('Failed to upload photo'), 'error')
    }
  }

  const openPhotoPreview = photoOrUrl => {
    const photos = fetchedEquipment.value?.photos || []
    if (!photos.length && typeof photoOrUrl === 'string') {
      mediaViewerItems.value = [{ url: photoOrUrl, title: form.value.model_name || t('Equipment Photo') }]
      mediaViewerInitialIndex.value = 0
    }
    else {
      mediaViewerItems.value = photos
      if (typeof photoOrUrl === 'object' && photoOrUrl !== null) {
        const idx = photos.findIndex(p => p.id === photoOrUrl.id)

        mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
      }
      else if (typeof photoOrUrl === 'string') {
        const idx = photos.findIndex(p => p.url === photoOrUrl)

        mediaViewerInitialIndex.value = idx >= 0 ? idx : 0
      }
      else {
        mediaViewerInitialIndex.value = 0
      }
    }
    mediaViewerTitle.value = t('Equipment Photography')
    isMediaViewerOpen.value = true
  }

  return {
    isDeleteDialogVisible,
    isTransferDialogOpen,
    isReplaceQrDialogOpen,
    isQrScannerOpen,
    isClientSearchModalVisible,
    isClassBlueprintModalVisible,
    isSiteAreaModalVisible,
    isImageDialogOpen,
    isGuidelineDialogOpen,
    selectedGuidelineForView,
    handleViewGuideline,
    isClientViewDialogOpen,
    selectedClientForView,
    loadingClientView,
    onClientPicked,
    handleViewClient,
    handleQrScanned,
    isMediaViewerOpen,
    mediaViewerItems,
    mediaViewerInitialIndex,
    mediaViewerTitle,
    handleSavePhotoFromDialog,
    openPhotoPreview,
  }
}
