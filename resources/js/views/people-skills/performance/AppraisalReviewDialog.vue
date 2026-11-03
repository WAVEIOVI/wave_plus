<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  appraisal: {
    type: Object,
    default: null,
  },
  cycleOptions: {
    type: Array,
    default: () => [],
  },
  employeeOptions: {
    type: Array,
    default: () => [],
  },
  reviewerOptions: {
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
  appraisal_cycle_id: null,
  employee_id: null,
  reviewer_id: null,
  overall_score: 3,
  status: 'draft',
  manager_feedback: '',
  employee_feedback: '',
})

const statusOptions = computed(() => [
  { title: t('appraisals.draft', 'Draft'), value: 'draft' },
  { title: t('appraisals.submitted', 'Submitted'), value: 'submitted' },
  { title: t('appraisals.reviewed', 'Reviewed'), value: 'reviewed' },
  { title: t('appraisals.acknowledged', 'Acknowledged'), value: 'acknowledged' },
])

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.appraisal) {
      form.value = {
        appraisal_cycle_id: props.appraisal.appraisal_cycle_id,
        employee_id: props.appraisal.employee_id,
        reviewer_id: props.appraisal.reviewer_id,
        overall_score: props.appraisal.overall_score || 3,
        status: props.appraisal.status || 'draft',
        manager_feedback: props.appraisal.manager_feedback || '',
        employee_feedback: props.appraisal.employee_feedback || '',
      }
    } else {
      form.value = {
        appraisal_cycle_id: props.cycleOptions[0]?.value || null,
        employee_id: null,
        reviewer_id: null,
        overall_score: 3,
        status: 'draft',
        manager_feedback: '',
        employee_feedback: '',
      }
    }
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="620"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="info"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-file-analytics" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.appraisal ? t('appraisals.edit_appraisal', 'Edit Appraisal Review') : t('appraisals.record_appraisal', 'Record Employee Appraisal') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('appraisals.review_desc', 'Evaluate employee performance and record feedback.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.appraisal_cycle_id"
              :label="t('appraisals.cycle', 'Appraisal Cycle')"
              :items="props.cycleOptions"
              :disabled="!!props.appraisal"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.employee_id"
              :label="t('employees.employee', 'Employee')"
              :items="props.employeeOptions"
              :disabled="!!props.appraisal"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.reviewer_id"
              :label="t('appraisals.reviewer', 'Reviewer / Manager')"
              :items="props.reviewerOptions"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.status"
              :label="t('common.status', 'Review Status')"
              :items="statusOptions"
            />
          </VCol>
          <VCol cols="12">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-body-2 font-weight-medium">{{ t('appraisals.score', 'Overall Rating / Score') }} ({{ form.overall_score }}/5)</span>
            </div>
            <VRating
              v-model="form.overall_score"
              hover
              length="5"
              size="32"
              color="warning"
              active-color="warning"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.manager_feedback"
              :label="t('appraisals.manager_feedback', 'Manager / Reviewer Feedback')"
              rows="3"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.employee_feedback"
              :label="t('appraisals.employee_feedback', 'Employee Self-Assessment / Notes')"
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
          :disabled="!form.appraisal_cycle_id || !form.employee_id"
          @click="handleSubmit"
        >
          {{ props.appraisal ? t('common.save', 'Save Changes') : t('common.save', 'Submit Appraisal') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
