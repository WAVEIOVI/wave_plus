<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
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

const defaultCriteria = () => ({
  job_knowledge: 3,
  work_quality: 3,
  productivity: 3,
  communication: 3,
  initiative: 3,
})

const form = ref({
  appraisal_cycle_id: null,
  employee_id: null,
  reviewer_id: null,
  overall_score: 3,
  status: 'draft',
  manager_feedback: '',
  employee_feedback: '',
  ratings: defaultCriteria(),
})

const isFinalized = computed(() => props.appraisal?.status === 'acknowledged')

const statusOptions = computed(() => [
  { title: t('appraisals.draft', 'Draft'), value: 'draft' },
  { title: t('appraisals.submitted', 'Submitted'), value: 'submitted' },
  { title: t('appraisals.reviewed', 'Reviewed'), value: 'reviewed' },
  { title: t('appraisals.acknowledged', 'Acknowledged'), value: 'acknowledged' },
])

const criteriaList = computed(() => [
  { key: 'job_knowledge', label: t('appraisals.criteria.job_knowledge', 'Job Knowledge & Technical Competence'), desc: t('appraisals.criteria.job_knowledge_desc', 'Depth of knowledge, job skills, and application.') },
  { key: 'work_quality', label: t('appraisals.criteria.work_quality', 'Work Quality & Accuracy'), desc: t('appraisals.criteria.work_quality_desc', 'Accuracy, thoroughness, and standard of output.') },
  { key: 'productivity', label: t('appraisals.criteria.productivity', 'Productivity & Timeliness'), desc: t('appraisals.criteria.productivity_desc', 'Volume of work, meeting deadlines, and efficiency.') },
  { key: 'communication', label: t('appraisals.criteria.communication', 'Communication & Teamwork'), desc: t('appraisals.criteria.communication_desc', 'Collaboration, clarity, and constructive engagement.') },
  { key: 'initiative', label: t('appraisals.criteria.initiative', 'Initiative & Problem Solving'), desc: t('appraisals.criteria.initiative_desc', 'Resourcefulness, proactive thinking, and solutions.') },
])

const calculateAverageScore = ratings => {
  const values = Object.values(ratings).filter(v => typeof v === 'number' && v > 0)
  if (!values.length) return 3
  const sum = values.reduce((acc, v) => acc + v, 0)
  
  return Number((sum / values.length).toFixed(2))
}

const onCriterionChange = () => {
  form.value.overall_score = calculateAverageScore(form.value.ratings)
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.appraisal) {
      const incomingRatings = props.appraisal.ratings && typeof props.appraisal.ratings === 'object'
        ? { ...defaultCriteria(), ...props.appraisal.ratings }
        : defaultCriteria()

      form.value = {
        appraisal_cycle_id: props.appraisal.appraisal_cycle_id,
        employee_id: props.appraisal.employee_id,
        reviewer_id: props.appraisal.reviewer_id,
        overall_score: props.appraisal.overall_score || calculateAverageScore(incomingRatings),
        status: props.appraisal.status || 'draft',
        manager_feedback: props.appraisal.manager_feedback || '',
        employee_feedback: props.appraisal.employee_feedback || '',
        ratings: incomingRatings,
      }
    } else {
      const initialRatings = defaultCriteria()

      form.value = {
        appraisal_cycle_id: props.cycleOptions[0]?.value || null,
        employee_id: null,
        reviewer_id: null,
        overall_score: calculateAverageScore(initialRatings),
        status: 'draft',
        manager_feedback: '',
        employee_feedback: '',
        ratings: initialRatings,
      }
    }
  }
})

const handleSubmit = () => {
  if (isFinalized.value) return
  emit('submit', {
    ...form.value,
    overall_score: Number(form.value.overall_score),
  })
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="props.appraisal ? t('appraisals.edit_appraisal', 'Edit Appraisal Review') : t('appraisals.record_appraisal', 'Record Employee Appraisal')"
    :subtitle="t('appraisals.review_desc', 'Evaluate employee performance and record feedback.')"
    icon="tabler-file-analytics"
    size="medium"
    :loading="props.loading"
    :submit-disabled="isFinalized || !form.appraisal_cycle_id || !form.employee_id"
    :submit-label="props.appraisal ? t('common.save', 'Save Changes') : t('common.save', 'Submit Appraisal')"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @submit="handleSubmit"
    @cancel="emit('update:isDialogVisible', false)"
  >
    <VAlert
      v-if="isFinalized"
      type="info"
      variant="tonal"
      class="mb-4"
      icon="tabler-lock"
    >
      {{ t('appraisals.finalized_notice', 'This appraisal has been acknowledged and finalized. Completed evaluations cannot be modified.') }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="form.appraisal_cycle_id"
          :label="t('appraisals.cycle', 'Appraisal Cycle')"
          :items="props.cycleOptions"
          :disabled="!!props.appraisal || isFinalized"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="form.employee_id"
          :label="t('employees.employee', 'Employee')"
          :items="props.employeeOptions"
          :disabled="!!props.appraisal || isFinalized"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="form.reviewer_id"
          :label="t('appraisals.reviewer', 'Reviewer / Manager')"
          :items="props.reviewerOptions"
          :disabled="isFinalized"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="form.status"
          :label="t('common.status', 'Review Status')"
          :items="statusOptions"
          :disabled="isFinalized"
        />
      </VCol>

      <!-- ── Evaluation Criteria Matrix ───────────────────────────────────── -->
      <VCol cols="12">
        <VDivider class="my-2" />
        <div class="d-flex align-center justify-space-between mb-3">
          <div>
            <h6 class="text-subtitle-1 font-weight-bold mb-0">
              {{ t('appraisals.evaluation_criteria', 'Evaluation Criteria') }}
            </h6>
            <span class="text-caption text-medium-emphasis">
              {{ t('appraisals.criteria_hint', 'Rate performance on each key competency (1 to 5).') }}
            </span>
          </div>
          <VChip
            color="primary"
            variant="tonal"
            size="small"
            class="font-weight-bold"
          >
            {{ t('appraisals.score', 'Overall Rating') }}: {{ form.overall_score }} / 5
          </VChip>
        </div>

        <VCard
          variant="outlined"
          class="pa-3 mb-2"
        >
          <div
            v-for="(criterion, idx) in criteriaList"
            :key="criterion.key"
            class="py-2"
            :class="{ 'border-b': idx < criteriaList.length - 1 }"
          >
            <div class="d-flex justify-space-between align-center flex-wrap gap-2">
              <div
                class="flex-grow-1"
                style="max-inline-size: 60%;"
              >
                <div class="text-body-2 font-weight-medium">
                  {{ criterion.label }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ criterion.desc }}
                </div>
              </div>
              <div class="d-flex align-center gap-2">
                <VRating
                  v-model="form.ratings[criterion.key]"
                  :disabled="isFinalized"
                  hover
                  length="5"
                  density="compact"
                  size="22"
                  color="warning"
                  active-color="warning"
                  @update:model-value="onCriterionChange"
                />
                <span
                  class="text-caption font-weight-bold"
                  style="min-inline-size: 32px;"
                >
                  ({{ form.ratings[criterion.key] }}/5)
                </span>
              </div>
            </div>
          </div>
        </VCard>
      </VCol>

      <!-- ── Feedback Section ─────────────────────────────────────────────── -->
      <VCol cols="12">
        <AppTextarea
          v-model="form.manager_feedback"
          :label="t('appraisals.manager_feedback', 'Manager / Reviewer Feedback')"
          :disabled="isFinalized"
          placeholder="Provide detailed feedback on strengths, achievements, and development areas."
          rows="3"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="form.employee_feedback"
          :label="t('appraisals.employee_feedback', 'Employee Self-Assessment / Notes')"
          :disabled="isFinalized"
          placeholder="Employee self-evaluation notes or response to review."
          rows="2"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
