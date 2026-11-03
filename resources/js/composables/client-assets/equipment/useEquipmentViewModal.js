/* eslint-disable camelcase */
import {
  conditionStatusOptions,
  useEquipmentConstants,
} from '@/composables/client-assets/equipment/useEquipmentConstants'
import {
  brandLabel,
  classLabel,
  equipmentPhoto,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { useEquipmentTimeline } from '@/composables/client-assets/equipment/useEquipmentTimeline'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useEquipmentViewModal(props, emit) {
  const { t } = useI18n()
  const router = useRouter()
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const snackbarStore = useSnackbarStore()

  const { optionTitle } = useEquipmentConstants()
  const { equipmentTitle, inspectionCountdown } = useEquipmentHelpers()

  const isOpen = computed({
    get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
    set: val => {
      emit('update:isDialogVisible', val)
      emit('update:modelValue', val)
    },
  })

  const resolvedId = computed(() => props.equipmentId || props.equipment?.id || null)

  const loading = ref(false)
  const error = ref(null)
  const fetchedEquipment = ref(null)
  const activeTab = ref('overview')

  // Sub-dialogs
  const isTransferDialogOpen = ref(false)
  const isReplaceQrDialogOpen = ref(false)
  const isMediaViewerOpen = ref(false)
  const mediaViewerItems = ref([])
  const mediaViewerInitialIndex = ref(0)
  const isClientViewDialogOpen = ref(false)
  const selectedClientForView = ref(null)
  const loadingClientView = ref(false)
  const copiedQr = ref(false)

  const eq = computed(() => fetchedEquipment.value || props.equipment || null)

  const loadEquipment = async () => {
    if (!resolvedId.value) return
    loading.value = true
    error.value = null
    try {
      fetchedEquipment.value = await store.fetchEquipmentItem(resolvedId.value)
    }
    catch (err) {
      console.error('Error loading equipment details:', err)
      error.value = err?.data?.message || t('Failed to load equipment details.')
    }
    finally {
      loading.value = false
    }
  }

  watch(
    [isOpen, resolvedId],
    ([open, id]) => {
      if (open && id) {
        activeTab.value = 'overview'
        loadEquipment()
      }
    },
    { immediate: true },
  )

  // ── Derived Display Data ───────────────────────────────────────────────────
  const mainPhotoUrl = computed(() => equipmentPhoto(eq.value || {}))

  const activeQrCode = computed(() =>
    eq.value?.current_qr_code?.qr_code
    || eq.value?.qr_code
    || eq.value?.equipment_code
    || '',
  )

  const nextInspectionMeta = computed(() =>
    inspectionCountdown(eq.value?.next_inspection_date),
  )

  // Timeline helper adapter
  const timelineFormProxy = computed(() => ({
    install_date: eq.value?.install_date || '',
    manufacture_date: eq.value?.manufacture_date || '',
    last_inspection_date: eq.value?.last_inspection_date || '',
    next_inspection_date: eq.value?.next_inspection_date || '',
    shelf_life_years: eq.value?.shelf_life_years || eq.value?.blueprint?.shelf_life_years || '',
  }))

  const {
    calculatedExpiryDate,
    lifeProgressPercentage,
    lifeRemainingLabel,
    timelineGroupedByYear,
  } = useEquipmentTimeline({
    form: () => timelineFormProxy.value,
    blueprintDetails: () => eq.value?.blueprint,
  })

  const hardwareProfileItems = computed(() => {
    const e = eq.value
    if (!e) return []

    return [
      { label: t('Model Designation'), value: e.model_name || localizedValue(e.blueprint?.name) || '—' },
      { label: t('Equipment Code'), value: e.equipment_code || '—', mono: true },
      { label: t('Serial Number'), value: e.serial_number || '—', mono: true },
      { label: t('Asset Tag'), value: e.asset_tag || '—', mono: true },
      { label: t('Manufacturer / Brand'), value: brandLabel(e) || '—' },
      { label: t('Category Class'), value: classLabel(e) || localizedValue(e.equipment_class?.name) || '—' },
      { label: t('Catalog Blueprint'), value: localizedValue(e.blueprint?.name) || e.blueprint?.code || '—' },
      { label: t('Condition Rating'), value: optionTitle(conditionStatusOptions, e.condition_status) || '—' },
    ]
  })

  const optionBadges = computed(() => {
    return (eq.value?.option_values || []).map(ov => ({
      id: ov.id || ov.blueprint_option_id,
      name: localizedValue(ov.option_name) || ov.option_name || t('Option'),
      value: ov.value_text || ov.display_value || ov.custom_value || localizedValue(ov.value) || '—',
    }))
  })

  const specEntries = computed(() => {
    const s = eq.value?.specs
    if (!s) return []
    if (Array.isArray(s)) {
      return s.map(row => ({ key: row.key || row.name, value: row.value })).filter(r => r.key)
    }

    return Object.entries(s).map(([key, value]) => ({ key, value: String(value ?? '—') }))
  })

  const normalizedCerts = computed(() => {
    return (eq.value?.certifications || []).map((c, idx) => {
      if (typeof c === 'string') return { id: idx, name: c, body: '', expiry: '' }

      return {
        id: c.id || idx,
        name: c.name || c.title || '',
        body: c.body || c.organization || '',
        expiry: c.expiry || c.expiry_date || '',
      }
    }).filter(c => c.name)
  })

  const totalMediaCount = computed(() =>
    (eq.value?.photos?.length || 0)
    + (eq.value?.documents?.length || 0)
    + (eq.value?.certificates?.length || 0),
  )

  const totalHistoryCount = computed(() =>
    (eq.value?.status_histories?.length || 0)
    + (eq.value?.location_histories?.length || 0)
    + (eq.value?.transfers?.length || 0)
    + (eq.value?.qr_codes?.length || 0),
  )

  const dialogTabs = computed(() => [
    { value: 'overview', title: t('Overview & Specs'), icon: 'tabler-layout-grid' },
    { value: 'lifecycle', title: t('Compliance & Lifecycle'), icon: 'tabler-calendar-stats' },
    {
      value: 'media',
      title: t('Media & Docs'),
      icon: 'tabler-photo',
      badge: totalMediaCount.value || undefined,
    },
    {
      value: 'history',
      title: t('Audit Trail'),
      icon: 'tabler-history',
      badge: totalHistoryCount.value || undefined,
    },
  ])

  // ── Actions ────────────────────────────────────────────────────────────────
  const goToEditPage = () => {
    if (!resolvedId.value) return
    isOpen.value = false
    router.push({ name: 'client-assets-equipment-edit-id', params: { id: resolvedId.value } })
  }

  const copyQrCode = async () => {
    if (!activeQrCode.value) return
    try {
      await navigator.clipboard.writeText(activeQrCode.value)
      copiedQr.value = true
      snackbarStore.showMessage(t('QR code identifier copied to clipboard'), 'success')
      setTimeout(() => { copiedQr.value = false }, 2000)
    }
    catch {
      snackbarStore.showMessage(t('Unable to copy code'), 'warning')
    }
  }

  const handleDownloadQr = async () => {
    if (!resolvedId.value) return
    try {
      const code = eq.value?.equipment_code || resolvedId.value

      await store.downloadEquipmentQr(resolvedId.value, `${code}-qr.svg`)
    }
    catch {
      snackbarStore.showMessage(t('Failed to download QR code'), 'error')
    }
  }

  const openPhotoViewer = () => {
    const photos = eq.value?.photos || []
    if (photos.length) {
      mediaViewerItems.value = photos
      mediaViewerInitialIndex.value = 0
      isMediaViewerOpen.value = true
    }
    else if (mainPhotoUrl.value) {
      mediaViewerItems.value = [{ url: mainPhotoUrl.value, title: equipmentTitle(eq.value || {}) }]
      mediaViewerInitialIndex.value = 0
      isMediaViewerOpen.value = true
    }
  }

  const handleViewClient = async () => {
    const clientId = eq.value?.client_id || eq.value?.client?.id
    if (!clientId) return

    loadingClientView.value = true
    isClientViewDialogOpen.value = true
    try {
      selectedClientForView.value = await clientStore.fetchClient(clientId)
    }
    catch {
      selectedClientForView.value = eq.value?.client
    }
    finally {
      loadingClientView.value = false
    }
  }

  const handleSubDialogSaved = async () => {
    await loadEquipment()
    emit('saved')
    emit('updated')
  }

  return {
    isOpen,
    resolvedId,
    eq,
    loading,
    error,
    activeTab,
    loadEquipment,
    mainPhotoUrl,
    activeQrCode,
    nextInspectionMeta,
    timelineFormProxy,
    calculatedExpiryDate,
    lifeProgressPercentage,
    lifeRemainingLabel,
    timelineGroupedByYear,
    hardwareProfileItems,
    optionBadges,
    specEntries,
    normalizedCerts,
    dialogTabs,
    isTransferDialogOpen,
    isReplaceQrDialogOpen,
    isMediaViewerOpen,
    mediaViewerItems,
    mediaViewerInitialIndex,
    isClientViewDialogOpen,
    selectedClientForView,
    loadingClientView,
    copiedQr,
    goToEditPage,
    copyQrCode,
    handleDownloadQr,
    openPhotoViewer,
    handleViewClient,
    handleSubDialogSaved,
    equipmentTitle,
  }
}
