/* eslint-disable camelcase */
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import {
  normalizePlacementForTemplate,
  normalizeSilhouetteTaxonomy,
  normalizeStudioPlacements,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useWorkwearModelEdit(modelId) {
  const store = useClientAssetStore()
  const snackbarStore = useSnackbarStore()
  const router = useRouter()
  const { t } = useI18n()

  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)
  const modelData = ref(null)
  const originalData = ref(null)

  const items = computed(() => modelData.value?.items || [])
  const options = computed(() => modelData.value?.options || [])
  const versions = computed(() => modelData.value?.versions || [])

  const statusMap = { draft: 'secondary', active: 'success', discontinued: 'error' }

  const statusOptions = Object.keys(statusMap).map(s => ({
    title: s.replace(/\b\w/g, l => l.toUpperCase()),
    value: s,
  }))

  const isDirty = computed(() => {
    if (!originalData.value || !modelData.value) return false

    return JSON.stringify(modelData.value) !== JSON.stringify(originalData.value)
  })

  const normalizeModelData = data => {
    const rawSpecs = data.technical_specs && typeof data.technical_specs === 'object' && !Array.isArray(data.technical_specs)
      ? data.technical_specs
      : {}

    const rawCustomMade = data.custom_made_requirements && typeof data.custom_made_requirements === 'object' && !Array.isArray(data.custom_made_requirements)
      ? data.custom_made_requirements
      : {}

    const normalizedTaxonomy = normalizeSilhouetteTaxonomy({
      ...data,
      catalog_item_id: data.catalog_item_id || null,
      fabric_id: data.fabric_id || null,
      fabric: data.workwear_fabric?.name || data.fabric || '',
      silhouette_category: data.silhouette_category || 'clothing',
      silhouette_subcategory: data.silhouette_subcategory || 'one_piece',
      silhouette_item: data.silhouette_item || null,
      silhouette: data.silhouette || data.garment_types?.[0] || 'coverall',
      colors: Array.isArray(data.colors) && data.colors.length ? data.colors : ['navy', 'hi-vis-yellow'],
      size_range: Array.isArray(data.size_range) ? data.size_range : ['S', 'M', 'L', 'XL', '2XL'],
      placements: Array.isArray(data.placements) ? data.placements : [],
      certifications: Array.isArray(data.certifications) ? data.certifications : [],
      standards: Array.isArray(data.standards)
        ? data.standards
        : (data.en_standards ? data.en_standards.split(',').map(s => s.trim()).filter(Boolean) : []),
      technical_specs: {
        fastening: rawSpecs.fastening || '',
        storage: rawSpecs.storage || '',
        reinforcements: rawSpecs.reinforcements || '',
        ergonomics: rawSpecs.ergonomics || '',
        adjustability: rawSpecs.adjustability || '',
        visibility: rawSpecs.visibility || '',
        collar: rawSpecs.collar || '',
        care: rawSpecs.care || '',
      },
      custom_made_requirements: {
        is_custom_made: Boolean(rawCustomMade.is_custom_made || data.custom_type === 'bespoke'),
        fit_variation: rawCustomMade.fit_variation || 'unisex_regular',
        special_sizing: rawCustomMade.special_sizing || '',
        construction_requirements: Array.isArray(rawCustomMade.construction_requirements)
          ? rawCustomMade.construction_requirements
          : [],
        lead_time_days: rawCustomMade.lead_time_days ?? null,
        moq: rawCustomMade.moq ?? null,
        notes: rawCustomMade.notes || '',
      },
    })

    normalizedTaxonomy.placements = normalizeStudioPlacements(
      normalizedTaxonomy.placements,
      normalizedTaxonomy.silhouette || 'coverall',
    )

    return normalizedTaxonomy
  }

  const fetchModel = async () => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id) return
    loading.value = true
    error.value = null
    try {
      const data = await store.fetchWorkwearModel(id)
      const normalized = normalizeModelData(data)

      modelData.value = normalized
      originalData.value = JSON.parse(JSON.stringify(normalized))
    }
    catch (err) {
      error.value = err?.data?.message || t('Failed to load model')
    }
    finally {
      loading.value = false
    }
  }

  const handleUpdate = async () => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id || !modelData.value) return
    saving.value = true
    try {
      const updated = await store.updateWorkwearModel(id, {
        client_id: modelData.value.client_id,
        catalog_item_id: modelData.value.catalog_item_id || null,
        supplier_id: modelData.value.supplier_id || null,
        name: modelData.value.name,
        fabric_id: modelData.value.fabric_id || null,
        fabric: modelData.value.fabric || null,
        silhouette_category: modelData.value.silhouette_category || null,
        silhouette_subcategory: modelData.value.silhouette_subcategory || null,
        silhouette_item: modelData.value.silhouette_item || null,
        silhouette: modelData.value.silhouette || 'coverall',
        colors: modelData.value.colors,
        size_range: modelData.value.size_range,
        status: modelData.value.status,
        certifications: modelData.value.certifications || [],
        standards: modelData.value.standards || [],
        technical_specs: modelData.value.technical_specs,
        custom_made_requirements: modelData.value.custom_made_requirements,
        placements: (modelData.value.placements || []).map((rawP, idx) => {
          const p = normalizePlacementForTemplate(rawP, modelData.value.silhouette || 'coverall')

          return {
            id: p.id || undefined,
            view: p.view || 'front',
            type: p.type || 'logo',
            label: p.label || p.zone || 'Placement',
            zone: p.zone || 'chest_left',
            text: p.text || null,
            method: p.method || null,
            color: p.color || null,
            width_mm: p.width_mm ?? null,
            height_mm: p.height_mm ?? null,
            offset_x_mm: p.offset_x_mm ?? 0,
            offset_y_mm: p.offset_y_mm ?? 0,
            media_id: p.media_id || null,
            notes: p.notes || null,
            sort_order: idx,
          }
        }),
        notes: modelData.value.notes,
      })

      if (updated?.data) {
        const normalized = normalizeModelData(updated.data)

        modelData.value = normalized
        originalData.value = JSON.parse(JSON.stringify(normalized))
      } else {
        await fetchModel()
      }
      snackbarStore.showMessage(t('Model updated successfully'), 'success')
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to update'), 'error')
    }
    finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id) return
    try {
      await store.deleteWorkwearModel(id)
      snackbarStore.showMessage(t('Model deleted'), 'success')
      router.push({ name: 'client-assets-workwear-list', query: { tab: 'models' } })
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to delete: model has items'), 'error')
    }
  }

  // ── Options Management (Legacy & Custom Model Options) ─────────────
  const isAddOptionDialogOpen = ref(false)
  const isAddingOption = ref(false)
  const newValueInput = ref({})

  const openAddOptionDialog = () => {
    isAddOptionDialogOpen.value = true
  }

  const handleCreateOption = async ({ name, ui_control, values_input }) => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id || !name?.trim()) return
    isAddingOption.value = true
    try {
      const rawValues = (values_input || '')
        .split(',')
        .map(v => v.trim())
        .filter(Boolean)

      await store.addModelOption(id, {
        name: name.trim(),
        ui_control: ui_control || 'select',
        values: rawValues.length ? rawValues : undefined,
      })

      snackbarStore.showMessage(t('Option added successfully'), 'success')
      isAddOptionDialogOpen.value = false
      await fetchModel()
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to add option'), 'error')
    }
    finally {
      isAddingOption.value = false
    }
  }

  const handleDeleteOption = async optionId => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id) return
    try {
      await store.deleteModelOption(id, optionId)
      snackbarStore.showMessage(t('Option deleted'), 'success')
      await fetchModel()
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Cannot delete option in use'), 'error')
    }
  }

  const handleAddOptionValue = async optionId => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    const val = newValueInput.value[optionId]?.trim()
    if (!id || !val) return
    try {
      await store.addModelOptionValue(id, optionId, { value: val })
      newValueInput.value[optionId] = ''
      snackbarStore.showMessage(t('Value added'), 'success')
      await fetchModel()
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to add value'), 'error')
    }
  }

  const handleDeleteOptionValue = async (optionId, valueId) => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id) return
    try {
      await store.deleteModelOptionValue(id, optionId, valueId)
      snackbarStore.showMessage(t('Value deleted'), 'success')
      await fetchModel()
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Cannot delete value in use'), 'error')
    }
  }

  // ── Media Management ───────────────────────────────────────────────
  const fileInputRef = ref(null)
  const mediaCollectionToUpload = ref('logos')
  const isUploadingMedia = ref(false)

  const triggerUpload = collection => {
    mediaCollectionToUpload.value = collection
    fileInputRef.value?.click()
  }

  const uploadMediaFile = async (fileOrPayload, collection = null, titleOrMeta = null) => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id || !fileOrPayload) return null

    const targetCollection = collection || mediaCollectionToUpload.value || 'logos'

    const meta = typeof titleOrMeta === 'object' && titleOrMeta !== null
      ? titleOrMeta
      : { title: titleOrMeta || fileOrPayload?.title }

    const itemsToUpload = Array.isArray(fileOrPayload?.files) && fileOrPayload.files.length
      ? fileOrPayload.files
      : [fileOrPayload]

    isUploadingMedia.value = true
    let lastUploadedLogo = null

    try {
      for (const item of itemsToUpload) {
        const rawFile = item instanceof File || item instanceof Blob ? item : item?.file
        if (!rawFile) continue

        const itemTitle = item?.title || meta?.title || fileOrPayload?.title || null
        const itemCategory = item?.category || item?.type || meta?.category || meta?.type || fileOrPayload?.category || fileOrPayload?.type || null
        const itemRef = item?.reference || meta?.reference || fileOrPayload?.reference || null
        const itemNotes = item?.notes || meta?.notes || fileOrPayload?.notes || null

        const formData = new FormData()
        const fileName = rawFile.name || (itemTitle ? `${itemTitle}.jpg` : 'file')

        formData.append('file', rawFile, fileName)
        formData.append('collection', targetCollection)
        if (itemTitle) formData.append('title', itemTitle)
        if (itemCategory) formData.append('category', itemCategory)
        if (itemRef) formData.append('reference', itemRef)
        if (itemNotes) formData.append('notes', itemNotes)

        const res = await store.uploadModelMedia(id, formData)

        if (res?.data?.logos && modelData.value) {
          modelData.value.logos = res.data.logos
        }
        if (res?.data?.design_files && modelData.value) {
          modelData.value.design_files = res.data.design_files
        }
        const logos = res?.data?.logos || []
        if (logos.length) {
          lastUploadedLogo = logos[logos.length - 1]
        }
      }

      snackbarStore.showMessage(t('Media uploaded successfully'), 'success')

      return lastUploadedLogo
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to upload media'), 'error')

      return null
    }
    finally {
      isUploadingMedia.value = false
    }
  }

  const onFileSelected = async event => {
    const file = event.target.files?.[0]
    if (!file) return
    await uploadMediaFile(file, mediaCollectionToUpload.value)
    if (fileInputRef.value) fileInputRef.value.value = ''
  }

  const handleDeleteMedia = async itemOrId => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    const mediaId = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
    if (!id || !mediaId) return
    try {
      await store.deleteModelMedia(id, mediaId)
      snackbarStore.showMessage(t('Media deleted'), 'success')
      if (modelData.value) {
        modelData.value.logos = (modelData.value.logos || []).filter(l => String(l.id) !== String(mediaId))
        modelData.value.design_files = (modelData.value.design_files || []).filter(d => String(d.id) !== String(mediaId))
      }
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to delete media'), 'error')
    }
  }

  const handleDownloadMedia = async item => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id || !item?.id) return
    try {
      await store.downloadModelMedia(id, item.id, item.file_name || item.name || item.title || 'download')
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to download media'), 'error')
    }
  }

  // ── Version Snapshot ───────────────────────────────────────────────
  const isVersionDialogOpen = ref(false)
  const isCreatingVersion = ref(false)

  const handleCreateVersion = async changeSummary => {
    const id = typeof modelId === 'object' && 'value' in modelId ? modelId.value : modelId
    if (!id) return
    isCreatingVersion.value = true
    try {
      await store.createModelVersion(id, { change_summary: changeSummary || null })
      snackbarStore.showMessage(t('Version snapshot recorded'), 'success')
      isVersionDialogOpen.value = false
      await fetchModel()
    }
    catch (err) {
      snackbarStore.showMessage(err?.data?.message || t('Failed to create version snapshot'), 'error')
    }
    finally {
      isCreatingVersion.value = false
    }
  }

  return {
    loading,
    saving,
    error,
    modelData,
    originalData,
    items,
    options,
    versions,
    statusOptions,
    isDirty,
    fetchModel,
    handleUpdate,
    handleDelete,

    // Options
    isAddOptionDialogOpen,
    isAddingOption,
    newValueInput,
    openAddOptionDialog,
    handleCreateOption,
    handleDeleteOption,
    handleAddOptionValue,
    handleDeleteOptionValue,

    // Media
    fileInputRef,
    mediaCollectionToUpload,
    isUploadingMedia,
    triggerUpload,
    onFileSelected,
    uploadMediaFile,
    handleDeleteMedia,
    handleDownloadMedia,

    // Versions
    isVersionDialogOpen,
    isCreatingVersion,
    handleCreateVersion,
  }
}
