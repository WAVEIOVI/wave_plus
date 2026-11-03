<script setup>
import { MultiLangField } from '@/components/shared'
import {
  getOrganisationScopes,
  getOrganisationStatuses,
  getOrganisationTypes,
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
  'addOrganisation',
])

const isFormValid = ref(false)
const refForm = ref()

const orgData = ref({
  legalName: { en: '', fr: '', ar: '' },
  commercialName: { en: '', fr: '', ar: '' },
  organisationType: null,
  scope: null,
  status: 'prospect',
  memberCount: 0,
})

const statusOptions = computed(() => getOrganisationStatuses())
const typeOptions = computed(() => getOrganisationTypes())
const scopeOptions = computed(() => getOrganisationScopes())

const resetForm = () => {
  orgData.value = {
    legalName: { en: '', fr: '', ar: '' },
    commercialName: { en: '', fr: '', ar: '' },
    organisationType: null,
    scope: null,
    status: 'prospect',
    memberCount: 0,
  }
  nextTick(() => {
    refForm.value?.resetValidation()
  })
}

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const payload = {
        legal_name: orgData.value.legalName,
        commercial_name: orgData.value.commercialName,
        organisation_type: orgData.value.organisationType,
        scope: orgData.value.scope,
        status: orgData.value.status,
        member_count: orgData.value.memberCount,
      }

      emit('addOrganisation', payload)
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
      <VCardItem class="text-center">
        <VCardTitle class="text-h4 mb-2">
          {{ $t('Add New Organisation') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ $t('Create a new organisation partner profile') }}
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
                v-model="orgData.legalName"
                :label="$t('Organisation Name')"
                :rules="[requiredValidator]"
                variant="outlined"
              />
            </VCol>

            <!-- Commercial / Short Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="orgData.commercialName"
                :label="$t('Short Name / Acronym')"
                variant="outlined"
              />
            </VCol>

            <!-- Organisation Type -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="orgData.organisationType"
                :label="$t('Type')"
                :items="typeOptions"
                variant="outlined"
                clearable
                clear-icon="tabler-x"
                :rules="[requiredValidator]"
              />
            </VCol>

            <!-- Scope -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="orgData.scope"
                :label="$t('Scope')"
                :items="scopeOptions"
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
                v-model="orgData.status"
                :label="$t('Status')"
                :items="statusOptions"
                variant="outlined"
                :rules="[requiredValidator]"
              />
            </VCol>

            <!-- Member Count -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="orgData.memberCount"
                type="number"
                :label="$t('Member Count')"
                variant="outlined"
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
                {{ $t('Create Organisation') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
