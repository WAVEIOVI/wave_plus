<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
import { getBpIndustries, getClientCategories, getClientStatuses } from '@/utils/bp-constants'
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
  'addClient',
])

const dialogRef = ref()

const clientData = ref({
  legalName: { en: '', fr: '', ar: '' },
  commercialName: { en: '', fr: '', ar: '' },
  industry: '',
  clientCategory: null,
  status: 'prospect',
})

const categoryOptions = computed(() => getClientCategories())
const statusOptions = computed(() => getClientStatuses())
const industryOptions = computed(() => getBpIndustries())

const resetForm = () => {
  clientData.value = {
    legalName: { en: '', fr: '', ar: '' },
    commercialName: { en: '', fr: '', ar: '' },
    industry: '',
    clientCategory: null,
    status: 'prospect',
  }
  nextTick(() => {
    dialogRef.value?.resetValidation()
  })
}

const onSubmit = () => {
  const payload = {
    legal_name: clientData.value.legalName,
    commercial_name: clientData.value.commercialName,
    industry: clientData.value.industry,
    client_category: clientData.value.clientCategory,
    status: clientData.value.status,
  }

  emit('addClient', payload)
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
    :title="$t('clients.list.actions.add_new')"
    icon="tabler-user-plus"
    max-width="600"
    :loading="props.loading"
    :submit-label="$t('clients.list.actions.create')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="onCancel"
  >
    <VRow>
      <!-- 👉 Legal Name -->
      <VCol
        cols="12"
        md="6"
      >
        <MultiLangField
          v-model="clientData.legalName"
          :rules="[requiredValidator]"
          :label="$t('clients.fields.legal_name')"
          variant="outlined"
        />
      </VCol>

      <!-- 👉 Commercial Name -->
      <VCol
        cols="12"
        md="6"
      >
        <MultiLangField
          v-model="clientData.commercialName"
          :label="$t('clients.fields.commercial_name')"
          variant="outlined"
        />
      </VCol>

      <!-- 👉 Industry -->
      <VCol
        cols="12"
        md="6"
      >
        <AppSelect
          v-model="clientData.industry"
          :label="$t('clients.fields.industry')"
          :items="industryOptions"
          variant="outlined"
          clearable
        />
      </VCol>

      <!-- 👉 Client Category -->
      <VCol
        cols="12"
        md="6"
      >
        <AppSelect
          v-model="clientData.clientCategory"
          :label="$t('clients.fields.category')"
          :items="categoryOptions"
          variant="outlined"
          clearable
        />
      </VCol>

      <!-- 👉 Status -->
      <VCol cols="12">
        <AppSelect
          v-model="clientData.status"
          :label="$t('clients.fields.status')"
          :items="statusOptions"
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
