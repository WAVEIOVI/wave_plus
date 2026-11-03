<script setup>
import { useHrStore } from '@/stores/hrStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  candidate: {
    type: Object,
    default: null,
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
const hrStore = useHrStore()

const form = ref({
  scheduled_at: '',
  duration_minutes: 45,
  interviewer_id: null,
  job_opening_id: null,
  feedback: '',
})

const interviewerOptions = computed(() =>
  (hrStore.interviewers || []).map(i => ({ title: i.name, value: i.id })),
)

const resetForm = () => {
  form.value = {
    scheduled_at: '',
    duration_minutes: 45,
    interviewer_id: null,
    job_opening_id: props.candidate?.applications?.[0]?.job_opening_id || null,
    feedback: '',
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
            color="warning"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-calendar-event" />
          </VAvatar>
        </template>
        <VCardTitle>{{ t('recruitment.schedule_interview', 'Schedule Interview') }}</VCardTitle>
        <VCardSubtitle>
          {{ props.candidate?.full_name || `${props.candidate?.first_name || ''} ${props.candidate?.last_name || ''}` }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <AppDateTimePicker
              v-model="form.scheduled_at"
              :label="t('recruitment.interview_datetime', 'Date & Time')"
              :config="{ enableTime: true, dateFormat: 'Y-m-d H:i' }"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model.number="form.duration_minutes"
              :label="t('recruitment.duration_minutes', 'Duration (minutes)')"
              type="number"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.job_opening_id"
              :label="t('recruitment.job_opening', 'Job Opening')"
              :items="props.openingOptions"
              clearable
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.interviewer_id"
              :label="t('recruitment.interviewer', 'Interviewer')"
              :items="interviewerOptions"
              clearable
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.feedback"
              :label="t('recruitment.interview_notes', 'Notes / Instructions')"
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
          :disabled="!form.scheduled_at"
          @click="handleSubmit"
        >
          {{ t('recruitment.schedule_interview', 'Schedule Interview') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
