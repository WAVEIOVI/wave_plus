<script setup>
/* eslint-disable camelcase */
import { WpDeleteDialog, WpFormDialog, WpSectionCard, WpSimpleTable } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useTrainingStore } from '@/stores/useTrainingStore'
import { resolveStatusColor } from '@/utils/hr-lookups'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed, defineEmits, defineProps, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  trainings: { type: Array, default: () => [] },
  employeeId: { type: String, default: '' },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const trainingStore = useTrainingStore()
const ability = useAbility()

// ── Enroll Dialog State ─────────────────────────────────────────────────────
const isEnrollDialogOpen = ref(false)
const selectedSessionId = ref(null)
const isSubmittingEnroll = ref(false)

// ── Complete Dialog State ───────────────────────────────────────────────────
const isCompleteDialogOpen = ref(false)
const completingParticipantId = ref(null)
const completingProgramName = ref('')
const completeScore = ref(null)
const completeCertificateNumber = ref('')
const isSubmittingComplete = ref(false)

// ── Delete Dialog State ─────────────────────────────────────────────────────
const isDeleteDialogOpen = ref(false)
const itemToDelete = ref(null)
const isDeleting = ref(false)

onMounted(async () => {
  if (!trainingStore.sessions.length && !props.readOnly) {
    await trainingStore.load().catch(() => {})
  }
})

// Available sessions for enrollment
const sessionOptions = computed(() => {
  return trainingStore.sessions
    .filter(s => s.status === 'scheduled' || s.status === 'in_progress')
    .map(s => {
      const progName = localizedValue(s.program?.name, s.session_code || 'Session')
      const code = s.session_code ? `[${s.session_code}]` : ''
      const dates = s.start_date ? `(${s.start_date})` : ''
      
      return {
        title: `${progName} ${code} ${dates}`.trim(),
        value: s.id,
      }
    })
})

const columns = computed(() => {
  const cols = [
    { key: 'program_name', title: t('hr.employees.fields.program') },
    { key: 'session_code', title: t('hr.employees.fields.session') },
    { key: 'trainer', title: t('hr.employees.fields.trainer', 'Trainer') },
    { key: 'dates', title: t('hr.employees.fields.period', 'Dates') },
    { key: 'score', title: t('hr.employees.fields.score') },
    { key: 'status', title: t('hr.employees.fields.status') },
    { key: 'certificate_number', title: t('hr.employees.fields.certificateNumber', 'Certificate No.') },
  ]

  if (!props.readOnly) {
    cols.push({ key: 'actions', title: t('common.actions', 'Action'), align: 'center' })
  }

  return cols
})

const openEnrollDialog = async () => {
  selectedSessionId.value = null
  if (!trainingStore.sessions.length) {
    await trainingStore.load().catch(() => {})
  }
  isEnrollDialogOpen.value = true
}

const handleEnroll = async () => {
  if (!selectedSessionId.value) {
    showMessage(t('hr.employees.trainings.select_session', 'Please select a training session'), 'error')
    
    return
  }

  if (!props.employeeId) return

  isSubmittingEnroll.value = true
  try {
    await trainingStore.enroll(selectedSessionId.value, props.employeeId)
    showMessage(t('hr.employees.trainings.enroll_success', 'Employee enrolled successfully'), 'success')
    isEnrollDialogOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('hr.employees.trainings.enroll_error', 'Failed to enroll employee'), 'error')
  } finally {
    isSubmittingEnroll.value = false
  }
}

const openCompleteDialog = item => {
  completingParticipantId.value = item.id
  completingProgramName.value = localizedValue(item.program_name, 'Training')
  completeScore.value = item.score ?? 80
  completeCertificateNumber.value = item.certificate_number || ''
  isCompleteDialogOpen.value = true
}

const handleComplete = async () => {
  if (!completingParticipantId.value) return

  isSubmittingComplete.value = true
  try {
    await trainingStore.complete(completingParticipantId.value, {
      score: completeScore.value != null ? Number(completeScore.value) : null,
      certificate_number: completeCertificateNumber.value || null,
    })
    showMessage(t('hr.employees.trainings.complete_success', 'Training recorded as completed'), 'success')
    isCompleteDialogOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('hr.employees.trainings.complete_error', 'Failed to complete training'), 'error')
  } finally {
    isSubmittingComplete.value = false
  }
}

const openDelete = item => {
  itemToDelete.value = item
  isDeleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!itemToDelete.value?.id) return
  isDeleting.value = true
  try {
    await trainingStore.removeParticipant(itemToDelete.value.id)
    showMessage(t('hr.employees.trainings.remove_success', 'Participant removed from session'), 'success')
    isDeleteDialogOpen.value = false
    itemToDelete.value = null
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('common.delete_error', 'Failed to remove participant'), 'error')
  } finally {
    isDeleting.value = false
  }
}

defineExpose({ openEnroll: openEnrollDialog })
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.sections.trainings')"
    icon="tabler-school"
    icon-color="info"
    :subtitle="t('hr.employees.trainings.subtitle', 'Completed and in-progress training sessions, scores, and accreditations.')"
  >
    <template
      v-if="!readOnly"
      #actions
    >
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-school"
        class="d-none d-md-inline-flex"
        @click="openEnrollDialog"
      >
        {{ t('hr.employees.trainings.enroll', 'Enroll Training') }}
      </VBtn>
    </template>

    <WpSimpleTable
      :columns="columns"
      :items="trainings"
      :breakpoint="760"
    >
      <template #cell.program_name="{ item }">
        <span class="font-weight-medium">{{ localizedValue(item.program_name, 'N/A') }}</span>
      </template>

      <template #cell.session_code="{ item }">
        <span class="font-monospace text-body-2">{{ item.session_code || '—' }}</span>
      </template>

      <template #cell.trainer="{ item }">
        {{ localizedValue(item.trainer, 'Internal') }}
      </template>

      <template #cell.dates="{ item }">
        <span class="text-caption">
          {{ item.start_date || '—' }} <span v-if="item.end_date">→ {{ item.end_date }}</span>
        </span>
      </template>

      <template #cell.score="{ item }">
        <strong
          v-if="item.score != null"
          :class="item.score >= 50 ? 'text-success' : 'text-error'"
        >
          {{ item.score }}%
        </strong>
        <span
          v-else
          class="text-medium-emphasis"
        >—</span>
      </template>

      <template #cell.status="{ item }">
        <VChip
          size="small"
          :color="resolveStatusColor(item.status, 'warning')"
          class="text-uppercase font-weight-bold"
        >
          {{ t(`hr.employees.trainingStatus.${item.status}`, item.status) }}
        </VChip>
      </template>

      <template #cell.certificate_number="{ item }">
        <span class="font-monospace text-caption">{{ item.certificate_number || '—' }}</span>
      </template>

      <template
        v-if="!readOnly"
        #cell.actions="{ item }"
      >
        <div class="d-flex align-center gap-1 justify-center">
          <VBtn
            v-if="(item.status === 'enrolled' || item.status === 'in_progress') && ability.can('manage', 'training')"
            size="x-small"
            color="success"
            variant="tonal"
            prepend-icon="tabler-certificate"
            @click="openCompleteDialog(item)"
          >
            {{ t('hr.employees.trainings.complete', 'Complete') }}
          </VBtn>
          <VBtn
            v-if="ability.can('manage', 'training')"
            icon="tabler-trash"
            size="x-small"
            variant="text"
            color="error"
            @click="openDelete(item)"
          />
        </div>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-school"
          :title="t('hr.employees.trainings.empty_title', 'No Training Sessions')"
          :message="t('hr.employees.trainings.empty', 'No training sessions recorded for this employee.')"
          :action-label="!readOnly ? t('hr.employees.trainings.enroll', 'Enroll Training') : null"
          action-icon="tabler-school"
          @action="openEnrollDialog"
        />
      </template>
    </WpSimpleTable>

    <!-- ── Enroll in Session Dialog ── -->
    <WpFormDialog
      v-model="isEnrollDialogOpen"
      :title="t('hr.employees.trainings.enrollTitle', 'Enroll in Training Session')"
      :subtitle="t('hr.employees.trainings.enrollSubtitle', 'Select an upcoming training session to enroll this employee.')"
      icon="tabler-school"
      :loading="isSubmittingEnroll"
      :submit-label="t('hr.employees.trainings.enroll', 'Enroll Employee')"
      submit-icon="tabler-user-plus"
      @submit="handleEnroll"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="selectedSessionId"
            :label="t('hr.employees.trainings.sessionSelect', 'Available Session')"
            :items="sessionOptions"
            :placeholder="sessionOptions.length ? t('common.select', 'Select a session') : t('hr.employees.trainings.no_sessions', 'No active sessions found')"
            :disabled="!sessionOptions.length"
            clearable
          />
        </VCol>
      </VRow>
    </WpFormDialog>

    <!-- ── Complete Training Dialog ── -->
    <WpFormDialog
      v-model="isCompleteDialogOpen"
      :title="t('hr.employees.trainings.completeTitle', 'Record Training Completion')"
      :subtitle="completingProgramName"
      icon="tabler-certificate"
      :loading="isSubmittingComplete"
      :submit-label="t('hr.employees.trainings.recordCompletion', 'Record Completion')"
      submit-icon="tabler-check"
      @submit="handleComplete"
    >
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model.number="completeScore"
            type="number"
            min="0"
            max="100"
            :label="t('hr.employees.fields.score', 'Score (%)')"
            placeholder="e.g. 85"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model="completeCertificateNumber"
            :label="t('hr.employees.fields.certificateNumber', 'Certificate No.')"
            placeholder="e.g. CERT-2026-001"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
    <!-- ── Remove Participant Dialog ── -->
    <WpDeleteDialog
      v-model:is-dialog-visible="isDeleteDialogOpen"
      :title="t('common.delete', 'Delete Record')"
      :confirmation-text="t('common.delete_confirm', 'Are you sure you want to remove this employee from the training session?')"
      :item-name="localizedValue(itemToDelete?.program_name)"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </WpSectionCard>
</template>
