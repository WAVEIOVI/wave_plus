<script setup>
import { WpFormDialog } from '@/components/shared'
import { ensureTranslatable, useLocaleField } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  session: {
    type: Object,
    default: null,
  },
  programOptions: {
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
  training_program_id: null,
  session_code: '',
  trainer: ensureTranslatable(),
  start_date: '',
  end_date: '',
  location: '',
  max_participants: null,
  status: 'scheduled',
})

const formRef = ref(form.value)
const trainerField = useLocaleField(formRef, 'trainer')

const statusOptions = [
  { title: 'Scheduled', value: 'scheduled' },
  { title: 'In Progress', value: 'in_progress' },
  { title: 'Completed', value: 'completed' },
  { title: 'Cancelled', value: 'cancelled' },
]

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.session) {
      form.value = {
        training_program_id: props.session.training_program_id || props.session.program?.id,
        session_code: props.session.session_code || '',
        trainer: ensureTranslatable(props.session.trainer),
        start_date: props.session.start_date || '',
        end_date: props.session.end_date || '',
        location: props.session.location || '',
        max_participants: props.session.max_participants || null,
        status: props.session.status || 'scheduled',
      }
    } else {
      form.value = {
        training_program_id: props.programOptions[0]?.value || null,
        session_code: '',
        trainer: ensureTranslatable(),
        start_date: '',
        end_date: '',
        location: '',
        max_participants: null,
        status: 'scheduled',
      }
    }
    formRef.value = form.value
  }
})

const isFormValid = computed(() => {
  return !!(form.value.training_program_id && form.value.session_code && form.value.start_date && form.value.end_date)
})

const handleSubmit = () => {
  if (!isFormValid.value) return
  emit('submit', { ...form.value })
}

const dialogTitle = computed(() => {
  return props.session ? t('training.edit_session', 'Edit Training Session') : t('training.schedule_session', 'Schedule Training Session')
})
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    max-width="700"
    :title="dialogTitle"
    :subtitle="t('training.session_desc', 'Set dates, venue, trainer, and capacity limits.')"
    icon="tabler-calendar-plus"
    icon-color="info"
    :loading="props.loading"
    :submit-label="props.session ? t('common.save', 'Save Changes') : t('training.schedule_session', 'Schedule Session')"
    :submit-icon="props.session ? 'tabler-device-floppy' : 'tabler-calendar-plus'"
    :submit-disabled="!isFormValid"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @submit="handleSubmit"
  >
    <VRow>
      <VCol cols="12">
        <AppSelect
          v-model="form.training_program_id"
          :label="t('training.program', 'Training Program')"
          :items="props.programOptions"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="form.session_code"
          :label="t('training.session_code', 'Session Code')"
          placeholder="e.g. SESS-2026-001"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="trainerField"
          :label="t('training.trainer', 'Trainer Name')"
          placeholder="Instructor / Organization"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppDateTimePicker
          v-model="form.start_date"
          :label="t('common.start_date', 'Start Date')"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppDateTimePicker
          v-model="form.end_date"
          :label="t('common.end_date', 'End Date')"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="form.location"
          :label="t('common.location', 'Location / Room / Online URL')"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model.number="form.max_participants"
          :label="t('training.max_participants', 'Max Participants')"
          type="number"
          min="1"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="form.status"
          :label="t('common.status', 'Status')"
          :items="statusOptions"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
