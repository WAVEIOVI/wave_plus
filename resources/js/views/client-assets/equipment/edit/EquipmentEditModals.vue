<script setup>
/* eslint-disable camelcase */
import { WpDeleteDialog, WpImageDialog, WpUnsavedChangesDialog } from '@/components/shared'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import ViewGuidelineDialog from '@/views/catalog/guidelines/list/ViewGuidelineDialog.vue'
import ClassBlueprintSelectModal from '@/views/client-assets/equipment/dialogs/ClassBlueprintSelectModal.vue'
import QrScannerModal from '@/views/client-assets/equipment/dialogs/QrScannerModal.vue'
import SiteAreaSelectModal from '@/views/client-assets/equipment/dialogs/SiteAreaSelectModal.vue'
import EquipmentReplaceQrDialog from '@/views/client-assets/equipment/EquipmentReplaceQrDialog.vue'
import EquipmentTransferDialog from '@/views/client-assets/equipment/EquipmentTransferDialog.vue'
import ClientSearchModal from '@/views/crm-sales/shared/ClientSearchModal.vue'

defineProps({
  form: { type: Object, required: true },
  fetchedEquipment: { type: Object, default: () => ({}) },
  selectedClient: { type: Object, default: null },
  sitesList: { type: Array, default: () => [] },
  equipmentClasses: { type: Array, default: () => [] },
  blueprints: { type: Array, default: () => [] },
  optionSelections: { type: Object, default: () => ({}) },
  selectedGuidelineForView: { type: Object, default: null },
  selectedClientForView: { type: Object, default: null },
  loadingClientView: { type: Boolean, default: false },
  mediaViewerItems: { type: Array, default: () => [] },
  mediaViewerInitialIndex: { type: Number, default: 0 },
  mediaViewerTitle: { type: String, default: '' },
})

defineEmits([
  'clientPicked',
  'siteAreaConfirm',
  'classBlueprintConfirm',
  'qrScanned',
  'equipmentReload',
  'savePhoto',
  'deleteConfirm',
  'confirmLeave',
  'cancelLeave',
])

const isClientSearchModalVisible = defineModel('clientSearchModal', { type: Boolean, default: false })
const isSiteAreaModalVisible = defineModel('siteAreaModal', { type: Boolean, default: false })
const isClassBlueprintModalVisible = defineModel('classBlueprintModal', { type: Boolean, default: false })
const isQrScannerOpen = defineModel('qrScannerModal', { type: Boolean, default: false })
const isReplaceQrDialogOpen = defineModel('replaceQrModal', { type: Boolean, default: false })
const isTransferDialogOpen = defineModel('transferModal', { type: Boolean, default: false })
const isClientViewDialogOpen = defineModel('clientViewModal', { type: Boolean, default: false })
const isGuidelineDialogOpen = defineModel('guidelineModal', { type: Boolean, default: false })
const isImageDialogOpen = defineModel('imageModal', { type: Boolean, default: false })
const isMediaViewerOpen = defineModel('mediaViewerModal', { type: Boolean, default: false })
const isDeleteDialogVisible = defineModel('deleteModal', { type: Boolean, default: false })
const isUnsavedChangesDialogVisible = defineModel('unsavedChangesModal', { type: Boolean, default: false })

const snackbarStore = useSnackbarStore()
</script>

<template>
  <ClientSearchModal
    v-model:is-dialog-visible="isClientSearchModalVisible"
    :selected-id="form.client_id"
    @select="$emit('clientPicked', $event)"
  />

  <SiteAreaSelectModal
    v-model:is-dialog-visible="isSiteAreaModalVisible"
    :sites="sitesList"
    :current-site-id="form.site_id"
    :current-area-id="form.area_id"
    @confirm="$emit('siteAreaConfirm', $event)"
  />

  <ClassBlueprintSelectModal
    v-model:is-dialog-visible="isClassBlueprintModalVisible"
    :equipment-classes="equipmentClasses"
    :blueprints="blueprints"
    :current-class-id="form.equipment_class_id"
    :current-blueprint-id="form.blueprint_id"
    :current-option-selections="optionSelections"
    @confirm="$emit('classBlueprintConfirm', $event)"
  />

  <QrScannerModal
    v-model:is-dialog-visible="isQrScannerOpen"
    @scanned="$emit('qrScanned', $event)"
  />

  <EquipmentReplaceQrDialog
    v-model:is-dialog-visible="isReplaceQrDialogOpen"
    :equipment="fetchedEquipment"
    @saved="$emit('equipmentReload')"
  />

  <EquipmentTransferDialog
    v-model:is-dialog-visible="isTransferDialogOpen"
    :equipment="fetchedEquipment"
    @transferred="$emit('equipmentReload')"
  />

  <ViewClientDialog
    v-model:is-dialog-visible="isClientViewDialogOpen"
    :client-id="form.client_id || selectedClient?.id"
    :client="selectedClientForView"
    :loading="loadingClientView"
  />

  <ViewGuidelineDialog
    v-model:is-dialog-visible="isGuidelineDialogOpen"
    :guideline="selectedGuidelineForView"
  />

  <WpImageDialog
    v-model="isImageDialogOpen"
    :title="$t('Equipment Asset Photo')"
    capture
    show-cropper
    show-title-input
    @save="$emit('savePhoto', $event)"
    @error="err => snackbarStore.showMessage(err?.message || $t('Photo upload error'), 'error')"
  />

  <WpMediaViewerDialog
    v-model="isMediaViewerOpen"
    :items="mediaViewerItems"
    :initial-index="mediaViewerInitialIndex"
    :title="mediaViewerTitle"
  />

  <WpDeleteDialog
    v-model:is-open="isDeleteDialogVisible"
    :title="$t('Delete Equipment')"
    :message="$t('Are you sure you want to delete equipment {name}?', { name: form.model_name || form.equipment_code || localizedValue(fetchedEquipment?.model_name) || '' })"
    @confirm="$emit('deleteConfirm')"
  />

  <WpUnsavedChangesDialog
    v-model:is-open="isUnsavedChangesDialogVisible"
    @confirm="$emit('confirmLeave')"
    @cancel="$emit('cancelLeave')"
  />
</template>
