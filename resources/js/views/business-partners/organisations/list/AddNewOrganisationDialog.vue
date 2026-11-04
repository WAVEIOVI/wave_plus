<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
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

const dialogRef = ref()

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
    dialogRef.value?.resetValidation()
  })
}

const onSubmit = () => {
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

const onCancel = () => {
  resetForm()
}
</script>

<template>
  <WpFormDialog
    ref="dialogRef"
    :model-value="props.isDialogVisible"
    :title="$t('Add New Organisation')"
    :subtitle="$t('Create a new organisation partner profile')"
    icon="tabler-building-community"
    max-width="800"
    :loading="props.loading"
    :submit-label="$t('Create Organisation')"
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
    </VRow>
  </WpFormDialog>
</template>
