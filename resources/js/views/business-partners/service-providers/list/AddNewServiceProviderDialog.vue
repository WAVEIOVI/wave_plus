<script setup>
import { MultiLangField } from '@/components/shared'
import {
  getAvailabilityStatuses,
  getBpIndustries,
  getClientStatuses,
  getProviderTiers,
} from '@/utils/bp-constants'
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
  'addServiceProvider',
])

const isFormValid = ref(false)
const refForm = ref()

const providerData = ref({
  legalName: { en: '', fr: '', ar: '' },
  commercialName: { en: '', fr: '', ar: '' },
  industry: '',
  providerTier: null,
  availabilityStatus: 'available',
  status: 'prospect',
})

const tierOptions = computed(() => getProviderTiers())
const availabilityOptions = computed(() => getAvailabilityStatuses())
const statusOptions = computed(() => getClientStatuses())
const industryOptions = computed(() => getBpIndustries())

const resetForm = () => {
  providerData.value = {
    legalName: { en: '', fr: '', ar: '' },
    commercialName: { en: '', fr: '', ar: '' },
    industry: '',
    providerTier: null,
    availabilityStatus: 'available',
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
        legal_name: providerData.value.legalName,
        commercial_name: providerData.value.commercialName,
        industry: providerData.value.industry,
        provider_tier: providerData.value.providerTier,
        availability_status: providerData.value.availabilityStatus,
        status: providerData.value.status,
      }

      emit('addServiceProvider', payload)
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
          {{ $t('Add New Service Provider') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ $t('Create a new service provider profile') }}
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
                v-model="providerData.legalName"
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
                v-model="providerData.commercialName"
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
                v-model="providerData.industry"
                :label="$t('Industry')"
                :items="industryOptions"
                variant="outlined"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>

            <!-- Provider Tier -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="providerData.providerTier"
                :label="$t('Provider Tier')"
                :items="tierOptions"
                variant="outlined"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>

            <!-- Availability Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="providerData.availabilityStatus"
                :label="$t('Availability')"
                :items="availabilityOptions"
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
                v-model="providerData.status"
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
                {{ $t('Create Service Provider') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
