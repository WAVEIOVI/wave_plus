<script setup>
/* eslint-disable camelcase */
import { WpWizardDialog } from '@/components/shared'
import { useEquipmentWizard } from '@/composables/client-assets/equipment/useEquipmentWizard'
import { localizedValue } from '@/composables/useLocaleField'
import EquipmentWizardReviewCard from '@/views/client-assets/equipment/dialogs/components/EquipmentWizardReviewCard.vue'
import EquipmentWizardSubDialogs from '@/views/client-assets/equipment/dialogs/components/EquipmentWizardSubDialogs.vue'
import EquipmentClassificationSection from '@/views/client-assets/equipment/sections/EquipmentClassificationSection.vue'
import EquipmentCodeStep from '@/views/client-assets/equipment/sections/EquipmentCodeStep.vue'
import EquipmentCodificationSection from '@/views/client-assets/equipment/sections/EquipmentCodificationSection.vue'
import EquipmentHistorySection from '@/views/client-assets/equipment/sections/EquipmentHistorySection.vue'
import EquipmentLifecycleDatesSection from '@/views/client-assets/equipment/sections/EquipmentLifecycleDatesSection.vue'
import EquipmentLocationSection from '@/views/client-assets/equipment/sections/EquipmentLocationSection.vue'
import EquipmentMediaGallery from '@/views/client-assets/equipment/sections/EquipmentMediaGallery.vue'
import EquipmentSpecsKvEditor from '@/views/client-assets/equipment/sections/EquipmentSpecsKvEditor.vue'
import EquipmentStatusTiles from '@/views/client-assets/equipment/sections/EquipmentStatusTiles.vue'
import EquipmentStepCard from '@/views/client-assets/equipment/sections/EquipmentStepCard.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: undefined },
  modelValue: { type: Boolean, default: false },
  equipmentId: { type: [String, Number], default: null },
  isNew: { type: Boolean, default: false },
  isReadOnly: { type: Boolean, default: false },
  prefilledClient: { type: Object, default: null },
  prefilledSite: { type: Object, default: null },
  prefilledArea: { type: Object, default: null },
  siteId: { type: [String, Number], default: null },
  initialAreaId: { type: [String, Number], default: null },
  areasList: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'saved',
  'created',
  'deleted',
  'close',
])

const wizardDialogRef = ref(null)

const isOpen = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
    if (!val) emit('close')
  },
})

const {
  loading,
  form,
  updateField,
  isModeNew,
  isInsideClientSite,
  isBlueprintLocked,
  selectedClient,
  selectedSite,
  selectedArea,
  selectedAreaFullPath,
  selectedClass,
  selectedBlueprint,
  selectedOptionBadges,
  blueprintInterventionCycles,
  calculatedExpiryDate,
  resolveLifecycleVariant,
  generateRandomCode,
  codeFlash,
  handleQrScanned,
  updateModelNameFromOptions,
  clearSelectedArea,
  handleClassBlueprintConfirm,
  handleAreaSelected,
  handleSiteSelected,
  handleClientSelected,
  loadInitialData,
  resetWizard,
  wizardSteps,
  dialogTitle,
  dialogDescription,
  handleSave,
  handleSubmitted,
  handleDeleted,
  createdItemLabel,
  handleAddAnotherConfirm,
  handleAddAnotherDone,
  clientPickerConfig,
  locationPickerConfig,
  zonePickerConfig,
  isClientSearchModalVisible,
  isSiteAreaModalVisible,
  isClassBlueprintModalVisible,
  isSiteAreaTreeSearchOpen,
  isQrScannerOpen,
  isReplaceQrDialogOpen,
  isTransferDialogOpen,
  isDeleteEquipmentDialogVisible,
  isCreateAnotherPromptOpen,
  equipmentClasses,
  blueprints,
  brandsList,
  optionSelections,
  isCustomModelName,
  fetchedEquipment,
  statusHistories,
  locationHistories,
  transferHistories,
  qrHistories,
  siteAreasList,
  currentEffectiveSiteId,
} = useEquipmentWizard(props, emit, wizardDialogRef)

watch(isOpen, val => {
  if (val) {
    loadInitialData()
  } else {
    resetWizard()
  }
}, { immediate: true })

watch(() => props.equipmentId, () => {
  if (isOpen.value) loadInitialData()
})

const handleClose = () => {
  isOpen.value = false
  resetWizard()
  emit('close')
}

const handleWizardSubmitted = () => {
  handleSubmitted()
  isOpen.value = false
  emit('close')
}
</script>

<template>
  <WpWizardDialog
    ref="wizardDialogRef"
    v-model="isOpen"
    variant="deeper"
    :title="dialogTitle"
    :description="dialogDescription"
    :icon="isModeNew ? 'tabler-plus' : 'tabler-edit'"
    :steps="wizardSteps"
    :initial-values="form"
    :submit="handleSave"
    :success-message="isModeNew ? $t('Equipment created successfully!') : $t('Equipment updated successfully!')"
    close-after-submit
    class="equipment-wizard-modal"
    @submitted="handleWizardSubmitted"
    @cancel="handleClose"
  >
    <!-- Header Actions Slot -->
    <template #header-actions>
      <Transition name="fade-scale">
        <VChip
          v-if="selectedSite"
          size="small"
          color="info"
          variant="tonal"
          class="d-none d-sm-inline-flex me-1 font-weight-medium"
        >
          <VIcon
            start
            icon="tabler-map-pin"
            size="14"
          />
          {{ localizedValue(selectedSite.name) }}
        </VChip>
      </Transition>

      <VBtn
        v-if="!isModeNew && !isReadOnly && props.equipmentId"
        variant="tonal"
        color="error"
        size="small"
        class="rounded-lg me-1"
        prepend-icon="tabler-trash"
        @click="isDeleteEquipmentDialogVisible = true"
      >
        {{ $t('Delete') }}
      </VBtn>
    </template>

    <!-- Step 1: Identification & Code -->
    <template #step-code="{ form: stepForm, errors: stepErrors }">
      <EquipmentCodeStep
        :form="stepForm"
        :errors="stepErrors"
        :loading="loading"
        :code-flash="codeFlash"
        @update:field="(k, v) => updateField(k, v)"
        @generate-code="generateRandomCode"
        @open-qr-scanner="isQrScannerOpen = true"
      />
    </template>

    <!-- Step 2: Classification & Location -->
    <template #step-general="{ form: stepForm, errors: stepErrors }">
      <div class="d-flex flex-column gap-4 w-100 min-w-0">
        <EquipmentClassificationSection
          :form="stepForm"
          :selected-blueprint="selectedBlueprint"
          :selected-class="selectedClass"
          :brands-list="brandsList"
          :selected-option-badges="selectedOptionBadges"
          :blueprint-intervention-cycles="blueprintInterventionCycles"
          :is-custom-model-name="isCustomModelName"
          :is-blueprint-locked="isBlueprintLocked"
          :is-read-only="isReadOnly"
          :loading="loading"
          @update:field="(k, v) => updateField(k, v)"
          @update:is-custom-model-name="v => { isCustomModelName = v }"
          @open-class-blueprint-modal="isClassBlueprintModalVisible = true"
          @sync-model-name="updateModelNameFromOptions"
        />

        <EquipmentLocationSection
          :form="stepForm"
          :errors="stepErrors"
          :selected-client="selectedClient"
          :selected-site="selectedSite"
          :selected-area="selectedArea"
          :selected-area-full-path="selectedAreaFullPath"
          :is-inside-client-site="isInsideClientSite"
          :is-mode-new="isModeNew"
          :loading="loading"
          @open-client-modal="isClientSearchModalVisible = true"
          @open-site-modal="isSiteAreaModalVisible = true"
          @open-area-modal="isSiteAreaTreeSearchOpen = true"
          @open-move-site-modal="isTransferDialogOpen = true"
          @clear-area="clearSelectedArea"
        />
      </div>
    </template>

    <!-- Step 3: Codification & Brand -->
    <template #step-codification="{ form: stepForm, errors: stepErrors }">
      <EquipmentCodificationSection
        :form="stepForm"
        :errors="stepErrors"
        :loading="loading"
        :is-new="isModeNew"
        :is-read-only="isReadOnly"
        @update:field="(k, v) => updateField(k, v)"
        @open-qr-scanner="isQrScannerOpen = true"
        @open-replace-qr="isReplaceQrDialogOpen = true"
      />
    </template>

    <!-- Step 4: Important Service & Inspection Dates -->
    <template #step-dates="{ form: stepForm }">
      <EquipmentLifecycleDatesSection
        :form="stepForm"
        :blueprint-details="selectedBlueprint"
        :loading="loading"
        @update:field="(k, v) => updateField(k, v)"
      />
    </template>

    <!-- Step 5: Specifications & Technical Remarks -->
    <template #step-specs="{ form: stepForm, setValue }">
      <EquipmentSpecsKvEditor
        :specs="stepForm.specs || {}"
        :certifications="stepForm.certifications || []"
        :description="stepForm.description"
        :notes="stepForm.notes"
        :read-only="isReadOnly"
        :disabled="isReadOnly"
        @update:specs="v => setValue('specs', v)"
        @update:certifications="v => setValue('certifications', v)"
        @update:description="v => setValue('description', v)"
        @update:notes="v => setValue('notes', v)"
      />
    </template>

    <!-- Step 6: Status & Operational Profile -->
    <template #step-status="{ form: stepForm, setValue }">
      <EquipmentStepCard
        icon="tabler-activity-heartbeat"
        color="warning"
        :title="$t('Status & Operational Profile')"
        :subtitle="$t('Compliance ratings, physical condition, and criticality rating')"
        :loading="loading"
      >
        <EquipmentStatusTiles
          :lifecycle-status="stepForm.lifecycle_status"
          :compliance-status="stepForm.compliance_status"
          :condition-status="stepForm.condition_status"
          :criticality="stepForm.criticality"
          :disabled="isReadOnly"
          hide-lifecycle
          @update:compliance-status="v => setValue('compliance_status', v)"
          @update:condition-status="v => setValue('condition_status', v)"
          @update:criticality="v => setValue('criticality', v)"
        />
      </EquipmentStepCard>
    </template>

    <!-- Step 7: Photos & Documents -->
    <template #step-media>
      <EquipmentStepCard
        icon="tabler-photo"
        color="success"
        :title="$t('Photos & Media Gallery')"
        :subtitle="$t('Attach equipment photographs, schematics, and inspection documents')"
        :loading="loading"
      >
        <EquipmentMediaGallery
          :equipment-id="props.equipmentId"
          :photos="fetchedEquipment?.photos || []"
          :documents="fetchedEquipment?.documents || []"
          :certificates="fetchedEquipment?.certificates || []"
          :is-new="isModeNew"
          :read-only="isReadOnly"
          @refresh="loadInitialData"
        />
      </EquipmentStepCard>
    </template>

    <!-- Step 8: Audit & Movements (edit mode only) -->
    <template
      v-if="!isModeNew"
      #step-history
    >
      <EquipmentStepCard
        icon="tabler-history"
        color="secondary"
        :title="$t('Audit Trail & Movements')"
        :subtitle="$t('Traceability records, location transfers, and QR replacement logs')"
        :loading="loading"
      >
        <template #append>
          <div class="d-flex gap-2 flex-wrap">
            <VBtn
              size="small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-arrows-left-right"
              @click="isTransferDialogOpen = true"
            >
              {{ $t('Transfer Location') }}
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="warning"
              prepend-icon="tabler-qrcode"
              @click="isReplaceQrDialogOpen = true"
            >
              {{ $t('Replace QR') }}
            </VBtn>
          </div>
        </template>

        <EquipmentHistorySection
          :status-histories="statusHistories"
          :location-histories="locationHistories"
          :transfer-histories="transferHistories"
          :qr-histories="qrHistories"
        />
      </EquipmentStepCard>
    </template>

    <!-- Extra review content -->
    <template #review-extra="{ form: reviewForm }">
      <EquipmentWizardReviewCard
        :review-form="reviewForm"
        :resolve-lifecycle-variant="resolveLifecycleVariant"
        :selected-blueprint="selectedBlueprint"
        :selected-option-badges="selectedOptionBadges"
        :selected-site="selectedSite"
        :selected-area="selectedArea"
        :calculated-expiry-date="calculatedExpiryDate"
      />
    </template>
  </WpWizardDialog>

  <!-- Modals & Sub-dialogs -->
  <EquipmentWizardSubDialogs
    v-model:client-modal="isClientSearchModalVisible"
    v-model:site-modal="isSiteAreaModalVisible"
    v-model:area-modal="isSiteAreaTreeSearchOpen"
    v-model:class-blueprint-modal="isClassBlueprintModalVisible"
    v-model:qr-scanner-modal="isQrScannerOpen"
    v-model:replace-qr-modal="isReplaceQrDialogOpen"
    v-model:transfer-modal="isTransferDialogOpen"
    v-model:delete-modal="isDeleteEquipmentDialogVisible"
    v-model:create-another-modal="isCreateAnotherPromptOpen"
    :is-mode-new="isModeNew"
    :equipment-id="props.equipmentId"
    :equipment="fetchedEquipment"
    :form="form"
    :equipment-classes="equipmentClasses"
    :blueprints="blueprints"
    :option-selections="optionSelections"
    :client-picker-config="clientPickerConfig"
    :location-picker-config="locationPickerConfig"
    :zone-picker-config="zonePickerConfig"
    :created-item-label="createdItemLabel"
    :site-id="currentEffectiveSiteId"
    :site-areas-list="props.areasList?.length ? props.areasList : siteAreasList"
    @select-client="handleClientSelected"
    @select-site="handleSiteSelected"
    @select-area="handleAreaSelected"
    @confirm-class-blueprint="handleClassBlueprintConfirm"
    @scanned-qr="handleQrScanned"
    @reload-equipment="loadInitialData"
    @deleted="handleDeleted"
    @confirm-add-another="handleAddAnotherConfirm"
    @done-add-another="handleAddAnotherDone"
  />
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.equipment-wizard-modal {
  overflow-x: hidden !important;
}

.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: opacity 0.2s $ease, transform 0.2s $ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
