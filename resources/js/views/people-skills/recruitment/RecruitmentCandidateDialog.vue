<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  openingOptions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  job_opening_id: null,
  notes: '',
})

const resetForm = () => {
  form.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    job_opening_id: null,
    notes: '',
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    resetForm()
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="560"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-user-plus" />
          </VAvatar>
        </template>
        <VCardTitle>{{ t('recruitment.add_candidate', 'Add Candidate') }}</VCardTitle>
        <VCardSubtitle>{{ t('recruitment.add_candidate_desc', 'Enter applicant details to register into hiring pipeline.') }}</VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.first_name"
              :label="t('common.first_name', 'First Name')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.last_name"
              :label="t('common.last_name', 'Last Name')"
              required
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.job_opening_id"
              :label="t('recruitment.job_opening', 'Job Opening')"
              :items="props.openingOptions"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.email"
              :label="t('common.email', 'Email')"
              type="email"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.phone"
              :label="t('common.phone', 'Phone')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.notes"
              :label="t('common.notes', 'Notes')"
              rows="2"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.loading"
          :disabled="!form.first_name || !form.last_name || !form.email"
          @click="handleSubmit"
        >
          {{ t('recruitment.add_candidate', 'Add Candidate') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
