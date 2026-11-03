<script setup>
import { MultiLangField } from '@/components/shared'
import { getBpIndustries, getClientStatuses, getSupplierCategories } from '@/utils/bp-constants'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'addSupplier',
])

const isFormValid = ref(false)
const refForm = ref()

const supplierData = ref({
  legalName: { en: '', fr: '', ar: '' },
  commercialName: { en: '', fr: '', ar: '' },
  industry: '',
  supplierCategory: null,
  status: 'prospect',
})

const categoryOptions = computed(() => getSupplierCategories())
const statusOptions = computed(() => getClientStatuses())
const industryOptions = computed(() => getBpIndustries())

const resetForm = () => {
  supplierData.value = {
    legalName: { en: '', fr: '', ar: '' },
    commercialName: { en: '', fr: '', ar: '' },
    industry: '',
    supplierCategory: null,
    status: 'prospect',
  }
  nextTick(() => {
    refForm.value?.resetValidation()
  })
}

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const payload = {
        legal_name: supplierData.value.legalName,
        commercial_name: supplierData.value.commercialName,
        industry: supplierData.value.industry,
        supplier_category: supplierData.value.supplierCategory,
        status: supplierData.value.status,
      }

      emit('addSupplier', payload)
      emit('update:isDialogVisible', false)
      resetForm()
    }
  })
}

const onReset = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="800"
    @update:model-value="onReset"
  >
    <VCard class="pa-sm-8 pa-5">
      <!-- Title -->
      <VCardItem class="text-center">
        <VCardTitle class="text-h4 mb-2">
          {{ $t('Add New Supplier') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ $t('Create a new supplier partner profile') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-6">
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Legal Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="supplierData.legalName"
                :label="$t('Legal Name')"
                :rules="[requiredValidator]"
                variant="outlined"
              />
            </VCol>

            <!-- Commercial Name (Multilingual) -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="supplierData.commercialName"
                :label="$t('Commercial Name')"
                variant="outlined"
              />
            </VCol>

            <!-- Industry -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="supplierData.industry"
                :label="$t('Industry')"
                :items="industryOptions"
                variant="outlined"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>

            <!-- Supplier Category -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="supplierData.supplierCategory"
                :label="$t('Category')"
                :items="categoryOptions"
                variant="outlined"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="supplierData.status"
                :label="$t('Status')"
                :items="statusOptions"
                variant="outlined"
                :rules="[requiredValidator]"
              />
            </VCol>

            <!-- Actions -->
            <VCol
              cols="12"
              class="d-flex justify-end gap-3 mt-4"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                @click="onReset"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="props.loading"
              >
                {{ $t('Create Supplier') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
