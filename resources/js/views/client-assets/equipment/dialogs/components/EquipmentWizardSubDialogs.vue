<script setup>
/* eslint-disable camelcase */
import { WpSearchSelectDialog } from '@/components/shared'
import ClassBlueprintSelectModal from '@/views/client-assets/equipment/dialogs/ClassBlueprintSelectModal.vue'
import EquipmentCreateAnotherDialog from '@/views/client-assets/equipment/dialogs/components/EquipmentCreateAnotherDialog.vue'
import QrScannerModal from '@/views/client-assets/equipment/dialogs/QrScannerModal.vue'
import EquipmentReplaceQrDialog from '@/views/client-assets/equipment/EquipmentReplaceQrDialog.vue'
import EquipmentTransferDialog from '@/views/client-assets/equipment/EquipmentTransferDialog.vue'
import DeleteEquipmentDialog from '@/views/client-assets/equipment/list/DeleteEquipmentDialog.vue'
import SiteAreaSearchDialog from '@/views/client-assets/sites/edit/SiteAreaSearchDialog.vue'

defineProps({
  isModeNew: { type: Boolean, default: false },
  equipmentId: { type: [String, Number], default: null },
  equipment: { type: Object, default: null },
  form: { type: Object, required: true },
  equipmentClasses: { type: Array, default: () => [] },
  blueprints: { type: Array, default: () => [] },
  optionSelections: { type: Object, default: () => ({}) },
  clientPickerConfig: { type: Object, required: true },
  locationPickerConfig: { type: Object, required: true },
  zonePickerConfig: { type: Object, required: true },
  createdItemLabel: { type: String, default: '' },
  siteId: { type: [String, Number], default: null },
  siteAreasList: { type: Array, default: () => [] },
})

defineEmits([
  'selectClient',
  'selectSite',
  'selectArea',
  'confirmClassBlueprint',
  'scannedQr',
  'reloadEquipment',
  'deleted',
  'confirmAddAnother',
  'doneAddAnother',
])

const isClientSearchModalVisible = defineModel('clientModal', { type: Boolean, default: false })
const isSiteAreaModalVisible = defineModel('siteModal', { type: Boolean, default: false })
const isSiteAreaTreeSearchOpen = defineModel('areaModal', { type: Boolean, default: false })
const isClassBlueprintModalVisible = defineModel('classBlueprintModal', { type: Boolean, default: false })
const isQrScannerOpen = defineModel('qrScannerModal', { type: Boolean, default: false })
const isReplaceQrDialogOpen = defineModel('replaceQrModal', { type: Boolean, default: false })
const isTransferDialogOpen = defineModel('transferModal', { type: Boolean, default: false })
const isDeleteEquipmentDialogVisible = defineModel('deleteModal', { type: Boolean, default: false })
const isCreateAnotherPromptOpen = defineModel('createAnotherModal', { type: Boolean, default: false })
</script>

<template>
  <!-- Client Search Dialog -->
  <WpSearchSelectDialog
    v-model="isClientSearchModalVisible"
    :title="clientPickerConfig.title"
    :entity-label="clientPickerConfig.entityLabel"
    :icon="clientPickerConfig.icon"
    :fetcher="clientPickerConfig.fetcher"
    :columns="clientPickerConfig.columns"
    :filters="clientPickerConfig.filters"
    :display="clientPickerConfig.display"
    confirm-behavior="immediate"
    @select="$emit('selectClient', $event)"
  />

  <!-- Site Location Dialog -->
  <WpSearchSelectDialog
    v-model="isSiteAreaModalVisible"
    :title="locationPickerConfig.title"
    :entity-label="locationPickerConfig.entityLabel"
    :icon="locationPickerConfig.icon"
    :fetcher="locationPickerConfig.fetcher"
    :columns="locationPickerConfig.columns"
    :filters="locationPickerConfig.filters"
    :display="locationPickerConfig.display"
    confirm-behavior="immediate"
    @select="$emit('selectSite', $event)"
  />

  <!-- Area / Zone Dialog (Tree list for nested zones / areas as list or cards) -->
  <SiteAreaSearchDialog
    v-model="isSiteAreaTreeSearchOpen"
    :title="zonePickerConfig.title || $t('Assign Zone / Building Area')"
    :site-id="siteId || form.site_id"
    :areas-list="siteAreasList"
    :current-area-id="form.area_id"
    allow-root
    :root-label="$t('Site-Wide (No specific area)')"
    @select="$emit('selectArea', $event)"
  />

  <!-- Class & Blueprint Selection Modal -->
  <ClassBlueprintSelectModal
    v-model:is-dialog-visible="isClassBlueprintModalVisible"
    :equipment-classes="equipmentClasses"
    :blueprints="blueprints"
    :current-class-id="form.equipment_class_id"
    :current-blueprint-id="form.blueprint_id"
    :current-options="optionSelections"
    @confirm="$emit('confirmClassBlueprint', $event)"
  />

  <!-- QR Scanner Modal -->
  <QrScannerModal
    v-model:is-dialog-visible="isQrScannerOpen"
    @scanned="$emit('scannedQr', $event)"
  />

  <!-- Replace QR Dialog (edit mode only) -->
  <EquipmentReplaceQrDialog
    v-if="!isModeNew && equipment"
    v-model:is-dialog-visible="isReplaceQrDialogOpen"
    :equipment="equipment"
    @saved="$emit('reloadEquipment')"
  />

  <!-- Transfer Dialog (edit mode only) -->
  <EquipmentTransferDialog
    v-if="!isModeNew && equipment"
    v-model:is-dialog-visible="isTransferDialogOpen"
    :equipment="equipment"
    @saved="$emit('reloadEquipment')"
    @transferred="$emit('reloadEquipment')"
  />

  <!-- Delete Dialog (edit mode only) -->
  <DeleteEquipmentDialog
    v-if="!isModeNew && equipmentId"
    v-model:is-dialog-visible="isDeleteEquipmentDialogVisible"
    :equipment-id="equipmentId"
    :equipment-name="form.equipment_code || form.model_name"
    @deleted="$emit('deleted')"
  />

  <!-- Post-create "Add another?" prompt -->
  <EquipmentCreateAnotherDialog
    v-model="isCreateAnotherPromptOpen"
    :created-item-label="createdItemLabel"
    @confirm="$emit('confirmAddAnother')"
    @done="$emit('doneAddAnother')"
  />
</template>
