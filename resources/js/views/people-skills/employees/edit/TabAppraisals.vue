<script setup>
/* eslint-disable camelcase */
import { WpFormDialog, WpSectionCard } from '@/components/shared'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { $api } from '@/utils/api'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { onMounted, ref } from 'vue'
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

const isRecordDialogOpen = ref(false)
const isSubmitting = ref(false)
const cycles = ref([])

const form = ref({
  appraisal_cycle_id: null,
  overall_score: 4,
  status: 'reviewed',
  manager_feedback: '',
  employee_feedback: '',
})

onMounted(async () => {
  try {
    const res = await $api('/hr/appraisals/cycles', { method: 'GET' })

    cycles.value = res.data || []
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('appraisals.cycles_load_failed'), 'error')
  }
})

const openRecordDialog = () => {
  form.value = {
    appraisal_cycle_id: cycles.value[0]?.id || null,
    overall_score: 4,
    status: 'reviewed',
    manager_feedback: '',
    employee_feedback: '',
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
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.appraisals')"
    icon="tabler-chart-bar"
    :subtitle="$t('hr.employees.appraisals.subtitle', 'Annual evaluations, objective ratings, manager feedback, and performance scores.')"
  >
    <template
      v-if="!readOnly"
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
              {{ $t('hr.employees.fields.cycle', 'Cycle') }}: {{ appraisal.cycle_name }}
            </h6>
            <span class="text-caption text-medium-emphasis">
              {{ $t('hr.employees.fields.date', 'Date') }}: {{ appraisal.evaluation_date }}
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
              :color="appraisal.status === 'acknowledged' ? 'success' : 'info'"
              variant="tonal"
              size="small"
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

        <div v-if="appraisal.employee_feedback">
          <div class="text-caption font-weight-medium text-medium-emphasis">
            {{ $t('hr.employees.fields.employeeFeedback', 'Employee Feedback') }}
          </div>
          <p class="text-body-2 mb-0">
            {{ appraisal.employee_feedback }}
          </p>
        </div>
      </VCard>
    </div>

    <EmployeeEditEmptyState
      v-else
      icon="tabler-chart-bar"
      :title="$t('hr.employees.empty.appraisalsTitle', 'No Appraisals Recorded')"
      :description="$t('hr.employees.empty.appraisalsDescription', 'Track performance evaluations, manager reviews, and goal assessments.')"
      :action-label="!readOnly ? $t('hr.employees.appraisals.record', 'Record Appraisal') : null"
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
            :items="cycles"
            item-title="title"
            item-value="id"
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
            step="0.1"
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
