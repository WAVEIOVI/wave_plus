<script setup>
/* eslint-disable camelcase */
import { MultiLangField } from '@/components/shared'
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

const isFormValid = ref(false)
const refForm = ref()

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
    refForm.value?.resetValidation()
  })
}

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
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
  })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="closeDialog" />
    <VCard :title="$t('clients.list.actions.add_new')">
      <VCardText>
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
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

            <!-- 👉 Actions -->
            <VCol
              cols="12"
              class="d-flex gap-4 justify-end mt-2"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                @click="closeDialog"
              >
                {{ $t('clients.list.actions.cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="props.loading"
              >
                {{ $t('clients.list.actions.create') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
