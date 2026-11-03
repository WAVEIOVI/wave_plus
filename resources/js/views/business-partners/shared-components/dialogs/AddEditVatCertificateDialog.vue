<script setup>
import { WpFormDialog } from '@/components/shared'
import { getVatCertStatuses } from '@/utils/bp-constants'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref, watch } from 'vue'
import { VDateInput } from 'vuetify/labs/VDateInput'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  certData: { type: Object, default: () => null },
  isEditMode: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const localData = ref({})

const statusOptions = computed(() => getVatCertStatuses())

watch(() => props.certData, val => {
  if (val) localData.value = { ...val }
}, { immediate: true })

const resetForm = () => {
  localData.value = {}
}

const onSubmit = () => {
  emit('submit', { ...localData.value })
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <WpFormDialog
    :model-value="isDialogVisible"
    :title="isEditMode ? $t('Edit VAT Certificate') : $t('Add VAT Certificate')"
    :icon="isEditMode ? 'tabler-edit' : 'tabler-file-certificate'"
    :submit-label="isEditMode ? $t('Update') : $t('Create')"
    :submit-icon="isEditMode ? 'tabler-device-floppy' : 'tabler-plus'"
    :cancel-label="$t('Cancel')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @cancel="resetForm"
    @submit="onSubmit"
  >
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="localData.certificate_number"
          :rules="[requiredValidator]"
          :label="$t('Certificate Number')"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="localData.issuing_authority"
          :label="$t('Issuing Authority')"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VDateInput
          v-model="localData.issue_date"
          :rules="[requiredValidator]"
          :label="$t('Issue Date')"
          variant="outlined"
          persistent-placeholder
          input-format="dd-mm-yyyy"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VDateInput
          v-model="localData.expiration_date"
          :label="$t('Expiration Date')"
          variant="outlined"
          persistent-placeholder
          input-format="dd-mm-yyyy"
        />
      </VCol>
      <VCol cols="12">
        <AppSelect
          v-model="localData.status"
          :label="$t('Status')"
          :items="statusOptions"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="localData.notes"
          :label="$t('Notes')"
          rows="2"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
