<script setup>
/* eslint-disable camelcase */
import { useCookie } from '@/@core/composable/useCookie'
import { WpFormDialog, WpSectionCard } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { $api } from '@/utils/api'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  appraisals: {
    type: Array,
    default: () => [],
  },
  employeeId: {
    type: String,
    required: true,
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh'])

const { showMessage } = useAppSnackbar()
const { t } = useI18n()
const ability = useAbility()
const currentUser = useCookie('userData').value

const canManage = computed(() => ability.can('manage', 'appraisals') || ability.can('create', 'PeopleSkills') || ability.can('manage', 'all'))

const isRecordDialogOpen = ref(false)
const isSubmitting = ref(false)
const isAcknowledging = ref(false)
const cycles = ref([])

const defaultCriteria = () => ({
  job_knowledge: 4,
  work_quality: 4,
  productivity: 4,
  communication: 4,
  initiative: 4,
})

const form = ref({
  appraisal_cycle_id: null,
  overall_score: 4,
  status: 'reviewed',
  manager_feedback: '',
  employee_feedback: '',
  ratings: defaultCriteria(),
})

const cycleOptions = computed(() =>
  cycles.value.map(c => ({
    title: c.name,
    value: c.id,
  })),
)

const loadCycles = async () => {
  try {
    const res = await $api('/hr/appraisals/cycles', { method: 'GET' })

    cycles.value = res.data || []
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('appraisals.cycles_load_failed', 'Failed to load cycles'), 'error')
  }
}

onMounted(() => {
  loadCycles()
})

const calculateAverageScore = ratings => {
  const values = Object.values(ratings).filter(v => typeof v === 'number' && v > 0)
  if (!values.length) return 4
  const sum = values.reduce((acc, v) => acc + v, 0)
  
  return Number((sum / values.length).toFixed(2))
}

const onRatingChange = () => {
  form.value.overall_score = calculateAverageScore(form.value.ratings)
}

const openRecordDialog = () => {
  const initialRatings = defaultCriteria()

  form.value = {
    appraisal_cycle_id: cycles.value[0]?.id || null,
    overall_score: calculateAverageScore(initialRatings),
    status: 'reviewed',
    manager_feedback: '',
    employee_feedback: '',
    ratings: initialRatings,
  }
  isRecordDialogOpen.value = true
}

defineExpose({ openRecordDialog })

const handleSaveAppraisal = async () => {
  if (!form.value.appraisal_cycle_id) {
    showMessage(t('hr.employees.appraisals.select_cycle', 'Please select an appraisal cycle'), 'error')

    return
  }
  isSubmitting.value = true
  try {
    await $api('/hr/appraisals', {
      method: 'POST',
      body: {
        appraisal_cycle_id: form.value.appraisal_cycle_id,
        employee_id: props.employeeId,
        overall_score: Number(form.value.overall_score),
        status: form.value.status,
        manager_feedback: form.value.manager_feedback,
        employee_feedback: form.value.employee_feedback,
        ratings: form.value.ratings,
      },
    })
    showMessage(t('hr.employees.appraisals.record_success', 'Appraisal recorded successfully'), 'success')
    isRecordDialogOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('hr.employees.appraisals.record_error', 'Failed to record appraisal'), 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleAcknowledge = async appraisal => {
  isAcknowledging.value = true
  try {
    await $api(`/hr/appraisals/${appraisal.id}/acknowledge`, {
      method: 'POST',
      body: {},
    })
    showMessage(t('appraisals.acknowledged_success', 'Appraisal acknowledged successfully.'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('appraisals.acknowledge_error', 'Failed to acknowledge appraisal.'), 'error')
  } finally {
    isAcknowledging.value = false
  }
}
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.appraisals')"
    icon="tabler-chart-bar"
    :subtitle="$t('hr.employees.appraisals.subtitle', 'Annual evaluations, objective ratings, manager feedback, and performance scores.')"
  >
    <template
      v-if="!readOnly && canManage"
      #actions
    >
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-plus"
        class="d-none d-md-inline-flex"
        @click="openRecordDialog"
      >
        {{ $t('hr.employees.appraisals.record', 'Record Appraisal') }}
      </VBtn>
    </template>

    <div
      v-if="appraisals.length > 0"
      class="d-flex flex-column gap-4"
    >
      <VCard
        v-for="appraisal in appraisals"
        :key="appraisal.id"
        variant="outlined"
        class="pa-4"
      >
        <div class="d-flex justify-space-between align-center mb-3 flex-wrap gap-2">
          <div>
            <h6 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('hr.employees.fields.cycle', 'Cycle') }}: {{ appraisal.cycle_name || appraisal.cycle?.name }}
            </h6>
            <span class="text-caption text-medium-emphasis">
              {{ $t('hr.employees.fields.date', 'Date') }}: {{ appraisal.evaluation_date || appraisal.created_at }}
            </span>
          </div>
          <div class="d-flex align-center gap-2">
            <VChip
              color="primary"
              variant="tonal"
              size="small"
            >
              {{ $t('hr.employees.fields.score', 'Score') }}: {{ appraisal.overall_score }} / 5
            </VChip>
            <VChip
              :color="appraisal.status === 'acknowledged' ? 'success' : appraisal.status === 'reviewed' ? 'warning' : 'info'"
              variant="tonal"
              size="small"
              class="text-capitalize"
            >
              {{ appraisal.status }}
            </VChip>
          </div>
        </div>

        <div
          v-if="appraisal.manager_feedback"
          class="mb-2"
        >
          <div class="text-caption font-weight-medium text-medium-emphasis">
            {{ $t('hr.employees.fields.managerFeedback', 'Manager Feedback') }}
          </div>
          <p class="text-body-2 mb-0">
            {{ appraisal.manager_feedback }}
          </p>
        </div>

        <div
          v-if="appraisal.employee_feedback"
          class="mb-2"
        >
          <div class="text-caption font-weight-medium text-medium-emphasis">
            {{ $t('hr.employees.fields.employeeFeedback', 'Employee Feedback') }}
          </div>
          <p class="text-body-2 mb-0">
            {{ appraisal.employee_feedback }}
          </p>
        </div>

        <!-- Acknowledge action if reviewed -->
        <div
          v-if="['submitted', 'reviewed'].includes(appraisal.status) && (canManage || currentUser?.id === appraisal.employee?.user_id)"
          class="d-flex justify-end pt-2 border-t mt-2"
        >
          <VBtn
            size="small"
            color="success"
            variant="tonal"
            prepend-icon="tabler-circle-check"
            :loading="isAcknowledging"
            @click="handleAcknowledge(appraisal)"
          >
            {{ $t('appraisals.acknowledge_button', 'Acknowledge Appraisal') }}
          </VBtn>
        </div>
      </VCard>
    </div>

    <EmployeeEditEmptyState
      v-else
      icon="tabler-chart-bar"
      :title="$t('hr.employees.empty.appraisalsTitle', 'No Appraisals Recorded')"
      :description="$t('hr.employees.empty.appraisalsDescription', 'Track performance evaluations, manager reviews, and goal assessments.')"
      :action-label="!readOnly && canManage ? $t('hr.employees.appraisals.record', 'Record Appraisal') : null"
      action-icon="tabler-plus"
      @action="openRecordDialog"
    />

    <!-- Record Appraisal Dialog -->
    <WpFormDialog
      v-model="isRecordDialogOpen"
      :title="$t('hr.employees.appraisals.record', 'Record Performance Appraisal')"
      :subtitle="$t('hr.employees.appraisals.record_hint', 'Document evaluation cycle score and feedback')"
      icon="tabler-chart-bar"
      :loading="isSubmitting"
      :submit-label="$t('hr.employees.appraisals.record', 'Record Appraisal')"
      submit-icon="tabler-plus"
      @submit="handleSaveAppraisal"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="form.appraisal_cycle_id"
            :label="$t('hr.employees.fields.appraisalCycle', 'Appraisal Cycle')"
            :items="cycleOptions"
            required
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model.number="form.overall_score"
            type="number"
            min="1"
            max="5"
            step="0.01"
            inputmode="decimal"
            :label="$t('hr.employees.fields.overallScore', 'Overall Rating (1-5)')"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppSelect
            v-model="form.status"
            :label="$t('hr.employees.fields.evaluationStatus', 'Evaluation Status')"
            :items="[
              { title: $t('hr.employees.appraisalStatus.draft', 'Draft'), value: 'draft' },
              { title: $t('hr.employees.appraisalStatus.submitted', 'Submitted'), value: 'submitted' },
              { title: $t('hr.employees.appraisalStatus.reviewed', 'Reviewed'), value: 'reviewed' },
              { title: $t('hr.employees.appraisalStatus.acknowledged', 'Acknowledged'), value: 'acknowledged' },
            ]"
          />
        </VCol>

        <!-- Competency Matrix -->
        <VCol cols="12">
          <div class="text-caption font-weight-medium text-medium-emphasis mb-1">
            {{ $t('appraisals.evaluation_criteria', 'Core Competencies') }}
          </div>
          <div class="border rounded pa-3 bg-surface d-flex flex-column gap-2">
            <div
              v-for="(val, key) in form.ratings"
              :key="key"
              class="d-flex justify-space-between align-center"
            >
              <span class="text-caption text-capitalize">{{ key.replace('_', ' ') }}</span>
              <div class="d-flex align-center gap-1">
                <VRating
                  v-model="form.ratings[key]"
                  density="compact"
                  size="18"
                  color="warning"
                  @update:model-value="onRatingChange"
                />
                <span class="text-caption font-weight-bold">({{ form.ratings[key] }}/5)</span>
              </div>
            </div>
          </div>
        </VCol>

        <VCol cols="12">
          <AppTextarea
            v-model="form.manager_feedback"
            :label="$t('hr.employees.fields.managerFeedback', 'Manager Evaluation / Feedback')"
            rows="3"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="form.employee_feedback"
            :label="$t('hr.employees.fields.employeeFeedback', 'Employee Self-Assessment / Notes')"
            rows="2"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>
