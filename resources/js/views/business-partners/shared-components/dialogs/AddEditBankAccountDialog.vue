<script setup>
import { WpFormDialog } from '@/components/shared'
import { getBankUsageOptions } from '@/utils/bp-constants'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  bankData: { type: Object, default: () => null },
  isEditMode: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const localData = ref({})

const usageOptions = computed(() => getBankUsageOptions())

watch(() => props.bankData, val => {
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
    :title="isEditMode ? $t('Edit Bank Account') : $t('Add Bank Account')"
    :icon="isEditMode ? 'tabler-edit' : 'tabler-building-bank'"
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
                    v-model="localData.bank_name"
                    :rules="[requiredValidator]"
                    :label="$t('Bank Name')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.bank_code"
                    :label="$t('Bank Code')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.branch"
                    :label="$t('Branch')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.account_holder"
                    :label="$t('Account Holder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.rib"
                    :label="$t('RIB')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.iban"
                    :label="$t('IBAN')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.swift_bic"
                    :label="$t('SWIFT / BIC')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.account_number"
                    :label="$t('Account Number')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.currency"
                    :label="$t('Currency')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="localData.usage"
                    :label="$t('Usage')"
                    :items="usageOptions"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCheckbox
                    v-model="localData.is_primary"
                    :label="$t('Primary Account')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCheckbox
                    v-model="localData.is_verified"
                    :label="$t('Verified')"
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
