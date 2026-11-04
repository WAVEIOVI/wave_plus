<script setup>
/* eslint-disable camelcase */
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
  interview: {
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

const isEvaluating = computed(() => Boolean(props.interview?.id))

const form = ref({
  scheduled_at: '',
  duration_minutes: 45,
  interviewer_id: null,
  job_opening_id: null,
  rating: null,
  feedback: '',
  status: 'scheduled',
})

const interviewerOptions = computed(() =>
  (hrStore.interviewers || []).map(i => ({ title: i.name, value: i.id })),
)

const statusOptions = computed(() => [
  { title: t('recruitment.interview_status.scheduled', 'Scheduled'), value: 'scheduled' },
  { title: t('recruitment.interview_status.completed', 'Completed'), value: 'completed' },
  { title: t('recruitment.interview_status.cancelled', 'Cancelled'), value: 'cancelled' },
])

const resetForm = () => {
  if (props.interview) {
    form.value = {
      scheduled_at: props.interview.scheduled_at
        ? new Date(props.interview.scheduled_at).toISOString().slice(0, 16).replace('T', ' ')
        : '',
      duration_minutes: props.interview.duration_minutes || 45,
      interviewer_id: props.interview.interviewer_id || null,
      job_opening_id: props.interview.job_opening_id || null,
      rating: props.interview.rating || null,
      feedback: props.interview.feedback || '',
      status: props.interview.status || 'completed',
    }
  } else {
    form.value = {
      scheduled_at: '',
      duration_minutes: 45,
      interviewer_id: null,
      job_opening_id: props.candidate?.applications?.[0]?.job_opening_id || null,
      rating: null,
      feedback: '',
      status: 'scheduled',
    }
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    resetForm()
  }
})

watch(() => props.interview, () => {
  if (props.isDialogVisible) {
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
            :color="isEvaluating ? 'primary' : 'warning'"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon :icon="isEvaluating ? 'tabler-star' : 'tabler-calendar-event'" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ isEvaluating ? t('recruitment.evaluate_interview', 'Record Interview Evaluation') : t('recruitment.schedule_interview', 'Schedule Interview') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ props.candidate?.full_name || `${props.candidate?.first_name || ''} ${props.candidate?.last_name || ''}`.trim() }}
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

          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model.number="form.duration_minutes"
              :label="t('recruitment.duration_minutes', 'Duration (minutes)')"
              type="number"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.job_opening_id"
              :label="t('recruitment.job_opening', 'Job Opening')"
              :items="props.openingOptions"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            :sm="isEvaluating ? 6 : 12"
          >
            <AppSelect
              v-model="form.interviewer_id"
              :label="t('recruitment.interviewer', 'Interviewer')"
              :items="interviewerOptions"
              clearable
            />
          </VCol>

          <VCol
            v-if="isEvaluating"
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.status"
              :label="t('common.status', 'Status')"
              :items="statusOptions"
            />
          </VCol>

          <!-- Evaluation Rating (1 - 5 stars) -->
          <VCol
            v-if="isEvaluating"
            cols="12"
          >
            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('recruitment.rating', 'Candidate Evaluation Rating (1-5)') }}
            </div>
            <div class="d-flex align-center gap-2">
              <VRating
                v-model="form.rating"
                hover
                length="5"
                density="compact"
                color="warning"
                active-color="warning"
              />
              <span
                v-if="form.rating"
                class="text-body-2 font-weight-medium ms-2"
              >
                {{ form.rating }} / 5
              </span>
            </div>
          </VCol>

          <VCol cols="12">
            <AppTextarea
              v-model="form.feedback"
              :label="isEvaluating ? t('recruitment.evaluation_notes', 'Evaluation Feedback & Recommendations') : t('recruitment.interview_notes', 'Notes / Instructions')"
              rows="3"
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
          {{ isEvaluating ? t('common.save_evaluation', 'Save Evaluation') : t('recruitment.schedule_interview', 'Schedule Interview') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
