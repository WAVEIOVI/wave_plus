<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
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

const dialogRef = ref()

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
    dialogRef.value?.resetValidation()
  })
}

const onSubmit = () => {
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

const onCancel = () => {
  resetForm()
}
</script>

<template>
  <WpFormDialog
    ref="dialogRef"
    :model-value="props.isDialogVisible"
    :title="$t('Add New Supplier')"
    :subtitle="$t('Create a new supplier partner profile')"
    icon="tabler-building-store"
    max-width="800"
    :loading="props.loading"
    :submit-label="$t('Create Supplier')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="onCancel"
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
    </VRow>
  </WpFormDialog>
</template>
